<?php
/**
 * Moves carts through their life: active -> abandoned -> recovered / converted / lost.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cartlum's own tables: WordPress has no API for them, and the rows change on every checkout, so an object cache would serve stale carts.

/**
 * Cartlum_Tracker.
 */
class Cartlum_Tracker {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_order_lost' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_order_lost' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'on_order_lost' ) );

		add_action( 'cartlum_sweep', array( __CLASS__, 'sweep' ) );
		add_action( 'cartlum_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * An order was placed: close every open cart that belongs to it.
	 *
	 * A cart belongs to the order if it is this session's cart, or an open cart with the same phone
	 * number from the last 30 days -- customers often come back on another device, e.g. by tapping
	 * our SMS link on their phone after abandoning on a laptop.
	 *
	 * "Recovered" means we contacted them (message, call, or they used our restore link) before they
	 * ordered. Without any contact it is "converted": they came back on their own, and claiming it
	 * as recovered revenue would overstate what the plugin did.
	 *
	 * @param int|WC_Order $order Order or id.
	 */
	public static function on_order( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return;
		}

		$carts = array();
		if ( WC()->session ) {
			$session_cart = Cartlum_Capture::current_cart();
			if ( $session_cart ) {
				$carts[ $session_cart->id ] = $session_cart;
			}
		}
		$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
		foreach ( Cartlum_Carts::find_open_by_phone( $phone ) as $row ) {
			$carts[ $row->id ] = $row;
		}

		foreach ( $carts as $cart ) {
			$status = ( (int) $cart->contact_count > 0 ) ? 'recovered' : 'converted';
			Cartlum_Carts::update(
				$cart->id,
				array(
					'status'      => $status,
					'order_id'    => $order->get_id(),
					'order_total' => (float) $order->get_total(),
					'closed_at'   => Cartlum_Carts::now(),
				)
			);
			Cartlum_Events::add(
				$cart->id,
				$status,
				array(
					'meta'    => array( 'order_id' => $order->get_id() ),
					'user_id' => 0,
				)
			);

			/**
			 * Fires when an order closes a cart.
			 *
			 * @param int      $cart_id Cart.
			 * @param string   $status  recovered|converted.
			 * @param WC_Order $order   Order.
			 */
			do_action( 'cartlum_cart_closed', (int) $cart->id, $status, $order );
		}

		if ( WC()->session ) {
			WC()->session->set( Cartlum_Capture::SESSION_KEY, null );
		}
	}

	/**
	 * The order was cancelled, failed or refunded: it is not recovered revenue after all.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_order_lost( $order_id ) {
		global $wpdb;
		$table = Cartlum_Install::table( 'carts' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE order_id = %d AND status IN ('recovered','converted')", $table, $order_id ) );
		foreach ( $ids as $id ) {
			Cartlum_Carts::update(
				$id,
				array(
					'status'      => 'lost',
					'order_total' => 0,
				)
			);
			Cartlum_Events::add(
				$id,
				'order_cancelled',
				array(
					'meta'    => array( 'order_id' => (int) $order_id ),
					'user_id' => 0,
				)
			);
		}
	}

	/**
	 * Every 5 minutes: mark quiet carts abandoned, and give up on very old ones.
	 */
	public static function sweep() {
		global $wpdb;
		$table   = Cartlum_Install::table( 'carts' );
		$minutes = max( 5, (int) Cartlum_Settings::get( 'abandon_after' ) );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS );

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE status = 'active' AND updated_at < %s LIMIT 200", $table, $cutoff ) );
		foreach ( $ids as $id ) {
			Cartlum_Carts::update(
				$id,
				array(
					'status'       => 'abandoned',
					'abandoned_at' => Cartlum_Carts::now(),
				)
			);
			Cartlum_Events::add( $id, 'abandoned', array( 'user_id' => 0 ) );

			/**
			 * Fires when a cart becomes abandoned. Automations start here.
			 *
			 * @param int $id Cart id.
			 */
			do_action( 'cartlum_cart_abandoned', (int) $id );
		}

		$days = max( 1, (int) Cartlum_Settings::get( 'lost_after' ) );
		$old  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'lost', closed_at = %s WHERE status = 'abandoned' AND updated_at < %s", $table, Cartlum_Carts::now(), $old ) );
	}

	/**
	 * Daily: delete carts older than the retention period.
	 */
	public static function cleanup() {
		global $wpdb;
		$days = (int) Cartlum_Settings::get( 'retention_days' );
		if ( $days < 1 ) {
			return;
		}
		$table = Cartlum_Install::table( 'carts' );
		$old   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE created_at < %s LIMIT 1000', $table, $old ) );
		Cartlum_Carts::delete( $ids );
	}
}
