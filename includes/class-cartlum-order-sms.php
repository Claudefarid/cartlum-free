<?php
/**
 * Order SMS: tells the customer their order was received, handed to the courier, delivered or
 * cancelled, and optionally tells the store about new orders. Each message goes once per order,
 * from the background (Action Scheduler), so checkout never waits for the SMS gateway.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Order_SMS.
 */
class Cartlum_Order_SMS {

	const EVENTS = array( 'placed', 'shipped', 'delivered', 'cancelled' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'cartlum_order_sms', array( __CLASS__, 'send' ), 10, 2 );
		if ( 'yes' !== Cartlum_Settings::get( 'order_sms_enabled' ) ) {
			return;
		}
		// "Received": when the order goes through, as WooCommerce's own confirmation email does.
		foreach ( array( 'pending_to_processing', 'pending_to_on-hold', 'failed_to_processing', 'failed_to_on-hold' ) as $transition ) {
			add_action( 'woocommerce_order_status_' . $transition, array( __CLASS__, 'on_placed' ) );
		}
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_delivered' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ) );
		// Handed to the courier: a Steadfast consignment was created; other couriers can call
		// do_action( 'cartlum_order_shipped', $order_id ).
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'cartlum_order_shipped', array( __CLASS__, 'on_shipped' ) );
		add_action( 'stdf_webhook_delivery_status_updated', array( __CLASS__, 'on_courier_status' ), 10, 2 );
	}

	/**
	 * Order went through.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_placed( $order_id ) {
		self::queue( $order_id, 'placed' );
		self::queue( $order_id, 'admin' );
	}

	/**
	 * Order completed.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_delivered( $order_id ) {
		self::queue( $order_id, 'delivered' );
	}

	/**
	 * Order cancelled.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_cancelled( $order_id ) {
		self::queue( $order_id, 'cancelled' );
	}

	/**
	 * Handed to the courier.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_shipped( $order_id ) {
		self::queue( (int) $order_id, 'shipped' );
	}

	/**
	 * Steadfast stores the consignment number on the order when it creates the parcel.
	 *
	 * @param int    $meta_id   Meta row.
	 * @param int    $object_id Post / order id.
	 * @param string $key       Meta key.
	 * @param mixed  $value     Value.
	 */
	public static function on_meta( $meta_id, $object_id, $key, $value ) {
		if ( 'steadfast_consignment_id' === $key && '' !== (string) $value ) {
			self::queue( (int) $object_id, 'shipped' );
		}
	}

	/**
	 * Steadfast's delivery status webhook: "delivered" counts as delivered.
	 *
	 * @param int    $order_id Order.
	 * @param string $status   Courier status.
	 */
	public static function on_courier_status( $order_id, $status ) {
		if ( 'delivered' === $status ) {
			self::queue( (int) $order_id, 'delivered' );
		}
	}

	/**
	 * Schedules one message for an order, once.
	 *
	 * @param int    $order_id Order.
	 * @param string $event    placed|shipped|delivered|cancelled|admin.
	 */
	private static function queue( $order_id, $event ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== Cartlum_Settings::get( 'order_sms_' . $event ) ) {
			return;
		}
		if ( $order->get_meta( '_cartlum_sms_' . $event ) ) {
			return; // Already sent (or on its way).
		}
		$order->update_meta_data( '_cartlum_sms_' . $event, time() );
		$order->save_meta_data();
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'cartlum_order_sms', array( (int) $order_id, $event ), 'cartlum' );
		} else {
			self::send( $order_id, $event );
		}
	}

	/**
	 * Default message texts.
	 *
	 * @return array event => text
	 */
	public static function default_texts() {
		return array(
			'placed'    => __( 'Thank you {name}! We received your order #{order} ({total}). Track it here: {track_link} - {store}', 'cartlum' ),
			'shipped'   => __( 'Your order #{order} is on its way with {courier} (consignment {consignment}). - {store}', 'cartlum' ),
			'delivered' => __( 'Your order #{order} has been delivered. Thank you for shopping with {store}!', 'cartlum' ),
			'cancelled' => __( 'Your order #{order} has been cancelled. If this is a mistake, please contact {store}.', 'cartlum' ),
			'admin'     => __( 'New order #{order}: {total} from {name}, {phone}.', 'cartlum' ),
		);
	}

	/**
	 * Sends a scheduled message.
	 *
	 * @param int    $order_id Order.
	 * @param string $event    Event.
	 */
	public static function send( $order_id, $event ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $event, array_merge( self::EVENTS, array( 'admin' ) ), true ) ) {
			return;
		}
		$to = 'admin' === $event
			? Cartlum_Phone::normalize( (string) Cartlum_Settings::get( 'order_sms_admin_phone' ) )
			: Cartlum_Phone::normalize( $order->get_billing_phone() );
		if ( '' === $to ) {
			return;
		}
		$template = trim( (string) Cartlum_Settings::get( 'order_sms_' . $event . '_text' ) );
		$defaults = self::default_texts();
		$message  = self::render( '' !== $template ? $template : $defaults[ $event ], $order );
		$result   = Cartlum_SMS::send_raw( $to, $message );

		$labels = array(
			'placed'    => __( 'order received', 'cartlum' ),
			'shipped'   => __( 'handed to courier', 'cartlum' ),
			'delivered' => __( 'delivered', 'cartlum' ),
			'cancelled' => __( 'cancelled', 'cartlum' ),
			'admin'     => __( 'new order (to the store)', 'cartlum' ),
		);
		if ( is_wp_error( $result ) ) {
			/* translators: 1: kind of message, 2: error */
			$order->add_order_note( sprintf( __( 'Cartlum: the “%1$s” SMS could not be sent: %2$s', 'cartlum' ), $labels[ $event ], $result->get_error_message() ) );
			return;
		}
		/* translators: 1: kind of message, 2: phone number */
		$order->add_order_note( sprintf( __( 'Cartlum: “%1$s” SMS sent to %2$s.', 'cartlum' ), $labels[ $event ], Cartlum_Phone::display( $to ) ) );
	}

	/**
	 * Fills in a message.
	 *
	 * @param string   $template Text with {placeholders}.
	 * @param WC_Order $order    Order.
	 * @return string
	 */
	public static function render( $template, $order ) {
		$courier = Cartlum_Order_Tracking::courier( $order );
		$total   = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		$items   = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ( $item->get_quantity() > 1 ? ' x' . $item->get_quantity() : '' );
		}
		return strtr(
			$template,
			array(
				'{name}'        => Cartlum_Messages::safe_name( $order->get_billing_first_name() ),
				'{order}'       => $order->get_order_number(),
				'{total}'       => trim( str_replace( "\xc2\xa0", ' ', $total ) ),
				'{items}'       => implode( ', ', $items ),
				'{phone}'       => $order->get_billing_phone(),
				'{store}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				// Guests can only open My Account with phone login; otherwise their order's own page.
				'{track_link}'  => 'yes' === Cartlum_Settings::get( 'phone_login' ) || $order->get_customer_id() ? $order->get_view_order_url() : $order->get_checkout_order_received_url(),
				'{courier}'     => $courier ? $courier['name'] : '',
				'{consignment}' => $courier ? $courier['id'] : '',
			)
		);
	}
}
