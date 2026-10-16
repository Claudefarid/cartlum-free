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
		// Store alerts for chosen statuses (e.g. cancelled), to the store's numbers only.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
		// Handed to the courier: a Steadfast consignment was created; other couriers can call
		// do_action( 'cartlum_order_shipped', $order_id ).
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'cartlum_order_shipped', array( __CLASS__, 'on_shipped' ) );
		add_action( 'stdf_webhook_delivery_status_updated', array( __CLASS__, 'on_courier_status' ), 10, 2 );
		// Any courier in Cartlum Pro 0.12+, with its status in shared words.
		add_action( 'cartlum_order_courier_status', array( __CLASS__, 'on_courier_status' ), 10, 2 );
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
	 * A courier's status update: "delivered" counts as delivered.
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
	 * Order changed status: tell the store, if it asked for this status.
	 *
	 * @param int    $order_id Order.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public static function on_status_changed( $order_id, $from, $to ) {
		unset( $from );
		$to = 0 === strpos( (string) $to, 'wc-' ) ? substr( (string) $to, 3 ) : (string) $to;
		if ( in_array( $to, (array) Cartlum_Settings::get( 'order_sms_status_alert' ), true ) ) {
			self::queue( $order_id, 'status_' . sanitize_key( $to ) );
		}
	}

	/**
	 * Default store status SMS.
	 *
	 * @return string
	 */
	public static function status_alert_default() {
		return __( 'Order #{order} is now {status}: {customer}, {phone}, Tk {amount}.', 'cartlum' );
	}

	/**
	 * The store's numbers.
	 *
	 * @return string[] Normalized.
	 */
	public static function admin_numbers() {
		$out = array();
		foreach ( preg_split( '/[,;\s]+/', (string) Cartlum_Settings::get( 'order_sms_admin_phone' ) ) as $raw ) {
			$phone = Cartlum_Phone::normalize( $raw );
			if ( '' !== $phone ) {
				$out[ $phone ] = $phone;
			}
		}
		return array_values( $out );
	}

	/**
	 * Schedules one message for an order, once.
	 *
	 * @param int    $order_id Order.
	 * @param string $event    placed|shipped|delivered|cancelled|admin.
	 */
	private static function queue( $order_id, $event ) {
		$order = wc_get_order( $order_id );
		$on    = 0 === strpos( $event, 'status_' ) ? true : 'yes' === Cartlum_Settings::get( 'order_sms_' . $event );
		if ( ! $order || ! $on ) {
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
		$order  = wc_get_order( $order_id );
		$status = 0 === strpos( (string) $event, 'status_' ) ? substr( (string) $event, 7 ) : '';
		if ( ! $order || ( '' === $status && ! in_array( $event, array_merge( self::EVENTS, array( 'admin' ) ), true ) ) ) {
			return;
		}
		if ( '' !== $status ) {
			$template = trim( (string) Cartlum_Settings::get( 'order_sms_status_alert_text' ) );
			$template = '' !== $template ? $template : self::status_alert_default();
		} else {
			$template = trim( (string) Cartlum_Settings::get( 'order_sms_' . $event . '_text' ) );
			$defaults = self::default_texts();
			$template = '' !== $template ? $template : $defaults[ $event ];
		}
		$message = self::render( $template, $order );
		$numbers = ( 'admin' === $event || '' !== $status ) ? self::admin_numbers() : array_filter( array( Cartlum_Phone::normalize( $order->get_billing_phone() ) ) );
		foreach ( $numbers as $to ) {
			self::deliver( $order, $event, $to, $message );
		}
	}

	/**
	 * Sends one message and notes it on the order.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $event   Event.
	 * @param string   $to      Normalized phone.
	 * @param string   $message Text.
	 */
	private static function deliver( $order, $event, $to, $message ) {
		$result = Cartlum_SMS::send_raw( $to, $message );

		$labels = array(
			'placed'    => __( 'order received', 'cartlum' ),
			'shipped'   => __( 'handed to courier', 'cartlum' ),
			'delivered' => __( 'delivered', 'cartlum' ),
			'cancelled' => __( 'cancelled', 'cartlum' ),
			'admin'     => __( 'new order (to the store)', 'cartlum' ),
		);
		if ( ! isset( $labels[ $event ] ) ) {
			/* translators: %s: order status */
			$labels[ $event ] = sprintf( __( '%s (to the store)', 'cartlum' ), wc_get_order_status_name( substr( $event, 7 ) ) );
		}
		if ( is_wp_error( $result ) ) {
			/* translators: 1: kind of message, 2: error */
			$order->add_order_note( sprintf( __( 'Cartlum: the “%1$s” SMS could not be sent: %2$s', 'cartlum' ), $labels[ $event ], $result->get_error_message() ) );
			return;
		}
		/* translators: 1: kind of message, 2: phone number */
		$note = sprintf( __( 'Cartlum: “%1$s” SMS sent to %2$s.', 'cartlum' ), $labels[ $event ], Cartlum_Phone::display( $to ) );
		if ( Cartlum_SMS::$unconfirmed ) {
			$note .= ' ' . __( 'The SMS provider was slow to confirm it; it usually still arrives.', 'cartlum' );
		}
		$order->add_order_note( $note );
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
		$detail  = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ( $item->get_quantity() > 1 ? ' x' . $item->get_quantity() : '' );
			// For the store: SKU (or name), quantity and colour, one line per item.
			$product = $item->get_product();
			$line    = ( $product && $product->get_sku() ? $product->get_sku() : $item->get_name() ) . ' x' . $item->get_quantity();
			foreach ( $item->get_formatted_meta_data() as $meta ) {
				if ( false !== stripos( $meta->display_key, 'colour' ) || false !== stripos( $meta->display_key, 'color' ) || false !== stripos( $meta->display_key, 'রং' ) ) {
					$line .= ' - ' . wp_strip_all_tags( $meta->display_key ) . ': ' . wp_strip_all_tags( $meta->display_value );
					break;
				}
			}
			$detail[] = $line;
		}
		$states  = WC()->countries ? WC()->countries->get_states( $order->get_billing_country() ) : array();
		$state   = is_array( $states ) && isset( $states[ $order->get_billing_state() ] ) ? $states[ $order->get_billing_state() ] : $order->get_billing_state();
		return strtr(
			$template,
			array(
				'{name}'        => Cartlum_Messages::safe_name( $order->get_billing_first_name() ),
				'{order}'       => $order->get_order_number(),
				'{total}'       => trim( str_replace( "\xc2\xa0", ' ', $total ) ),
				'{amount}'      => number_format( (float) $order->get_total(), 0 ),
				'{customer}'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'{items_detail}' => implode( "\n", $detail ),
				'{address}'     => trim( implode( ', ', array_filter( array( $order->get_billing_address_1(), $state ) ) ) ),
				'{status}'      => wc_get_order_status_name( $order->get_status() ),
				'{items}'       => implode( ', ', $items ),
				'{phone}'       => $order->get_billing_phone(),
				'{store}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				// Guests can only open My Account with phone login; otherwise their order's own page.
				'{track_link}'  => 'yes' === Cartlum_Settings::get( 'phone_login' ) || $order->get_customer_id() ? $order->get_view_order_url() : $order->get_checkout_order_received_url(),
				'{courier}'     => $courier ? $courier['name'] : '',
				'{consignment}' => $courier ? $courier['id'] : '',
				// Made only when a text uses it, so orders without one get no review token.
				'{review_link}' => false !== strpos( $template, '{review_link}' ) ? Cartlum_Reviews::link( $order ) : '',
			)
		);
	}
}
