<?php
/**
 * The per-cart timeline: every capture, message, call and click.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cartlum's own tables: WordPress has no API for them, and the rows change on every checkout, so an object cache would serve stale carts.

/**
 * Cartlum_Events.
 */
class Cartlum_Events {

	/**
	 * Records an event.
	 *
	 * @param int    $cart_id Cart.
	 * @param string $type    e.g. captured, abandoned, sms_sent, sms_failed, email_sent, whatsapp_opened,
	 *                        call_logged, link_clicked, coupon_created, recovered, converted, optout.
	 * @param array  $args    { channel, message, meta (array), user_id }.
	 * @return int
	 */
	public static function add( $cart_id, $type, array $args = array() ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Cartlum_Install::table( 'events' ),
			array(
				'cart_id'    => (int) $cart_id,
				'type'       => sanitize_key( $type ),
				'channel'    => isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : '',
				'message'    => isset( $args['message'] ) ? (string) $args['message'] : null,
				'meta'       => isset( $args['meta'] ) ? wp_json_encode( $args['meta'] ) : null,
				'user_id'    => isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id(),
				'created_at' => Cartlum_Carts::now(),
			)
		);
		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a timeline event is recorded.
		 *
		 * @param int    $id      Event id.
		 * @param int    $cart_id Cart id.
		 * @param string $type    Event type.
		 * @param array  $args    Event args.
		 */
		do_action( 'cartlum_event', $id, $cart_id, $type, $args );
		return $id;
	}

	/**
	 * A cart's timeline, oldest first.
	 *
	 * @param int $cart_id Cart.
	 * @return array
	 */
	public static function for_cart( $cart_id ) {
		global $wpdb;
		$table = Cartlum_Install::table( 'events' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE cart_id = %d ORDER BY id ASC', $table, $cart_id ) );
	}

	/**
	 * The latest contact with each of these carts: what it was and when.
	 *
	 * @param int[] $cart_ids Carts.
	 * @return array cart_id => object { type, created_at }
	 */
	public static function last_contacts( array $cart_ids ) {
		global $wpdb;
		$cart_ids = array_values( array_filter( array_map( 'intval', $cart_ids ) ) );
		if ( ! $cart_ids ) {
			return array();
		}
		$types = array( 'sms_sent', 'email_sent', 'whatsapp_opened', 'whatsapp_sent', 'call_logged', 'ai_call_done', 'ai_call_started' );
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are built for each id and type.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cart_id, type, created_at FROM %i WHERE cart_id IN (' . implode( ',', array_fill( 0, count( $cart_ids ), '%d' ) ) . ') AND type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY id DESC',
				array_merge( array( Cartlum_Install::table( 'events' ) ), $cart_ids, $types )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $out[ (int) $row->cart_id ] ) ) {
				$out[ (int) $row->cart_id ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Counts of outbound contact per channel for carts created in the window, plus how many of
	 * those carts went on to be recovered -- the "which channel works" table on the dashboard.
	 *
	 * @param int $days Look-back.
	 * @return array channel => { contacted, recovered, revenue }
	 */
	public static function channel_stats( $days = 30 ) {
		global $wpdb;
		$events = Cartlum_Install::table( 'events' );
		$carts  = Cartlum_Install::table( 'carts' );
		$since  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// One row per (channel, cart) first, so a cart messaged twice on the same channel is counted
		// once and its order value is added once -- SUM(DISTINCT ...) would also drop two different
		// carts that happen to have the same order total.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.channel, COUNT(*) AS contacted,
					SUM(t.status = 'recovered') AS recovered,
					SUM(CASE WHEN t.status = 'recovered' THEN t.order_total ELSE 0 END) AS revenue
				FROM (
					SELECT DISTINCT e.channel, c.id, c.status, c.order_total
					FROM %i e JOIN %i c ON c.id = e.cart_id
					WHERE e.type IN ('sms_sent','email_sent','whatsapp_opened','whatsapp_sent','call_logged','ai_call_done') AND c.created_at >= %s
				) t
				GROUP BY t.channel",
				$events,
				$carts,
				$since
			),
			OBJECT_K
		);
		return $rows ? $rows : array();
	}
}
