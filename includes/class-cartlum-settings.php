<?php
/**
 * Settings access with defaults.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Settings.
 */
class Cartlum_Settings {

	const OPTION = 'cartlum_settings';

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults. Message templates use {placeholders}, see Cartlum_Messages.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			// Capture.
			'enabled'               => 'yes',
			'abandon_after'         => 30,  // Minutes without activity before a cart counts as abandoned.
			'lost_after'            => 14,  // Days after which an unrecovered cart is closed as lost.
			'retention_days'        => 90,  // Days before cart data is deleted.
			'capture_notice'        => 'yes',
			'capture_notice_text'   => __( 'We save your details so we can help if your order does not go through.', 'cartlum' ),
			'country_code'          => '',  // Calling code for local numbers; empty = store base country.
			'customer_language'     => '',  // '' = the site's language; e.g. bn_BD for Bangla to shoppers only.

			// Phone-first checkout.
			'checkout_enabled'      => 'no',
			'checkout_preview'      => 'yes', // Only shop managers see it until this is turned off.
			'checkout_email'        => 'optional', // optional|required|hidden.
			'checkout_notes'        => 'yes',
			'checkout_button_text'  => '',
			'checkout_accent'       => '#1f2937',
			'checkout_logo'         => '',
			'thankyou_message'      => '',      // Empty = the default text, translated. {phone} = the customer's number.
			'webhook_url'           => '',
			'webhook_on_captured'   => 'no',
			'webhook_on_abandoned'  => 'yes',
			'webhook_on_recovered'  => 'yes',
			'popup_enabled'         => 'no',
			'popup_trigger'         => 'exit',
			'popup_title'           => '',      // Empty = translated defaults, see Cartlum_Popup.
			'popup_text'            => '',
			'popup_button'          => '',
			'buy_now_enabled'       => 'no',
			'buy_now_text'          => '',      // Empty = "Buy now", translated.
			'buy_now_mode'          => 'add',   // add|only.
			'buy_now_loop'          => 'no',
			'whatsapp_button'       => 'no',
			'whatsapp_number'       => '',
			'whatsapp_button_text'  => '',      // Empty = "Order on WhatsApp", translated.
			'whatsapp_order_message' => '',     // Empty = the default message, translated.
			'call_button'           => 'no',
			'call_number'           => '',
			'call_button_text'      => '',      // Empty = "Call to order", translated.
			'product_button_color'  => '',      // Empty = the theme's Add to cart style.

			// Phone login (My Account).
			'phone_login'             => 'no',
			'otp_message'             => '',    // Empty = the default text, translated. {code} {store}.
			'phone_login_daily_limit' => 300,   // Login codes per day, all numbers together.

			// Order SMS and tracking.
			'order_sms_enabled'       => 'no',
			'order_sms_placed'        => 'yes',
			'order_sms_placed_text'   => '',    // Empty = the default text, translated.
			'order_sms_shipped'       => 'no',
			'order_sms_shipped_text'  => '',
			'order_sms_delivered'     => 'no',
			'order_sms_delivered_text' => '',
			'order_sms_cancelled'     => 'no',
			'order_sms_cancelled_text' => '',
			'order_sms_admin'         => 'no',
			'order_sms_status_alert'  => array(), // Statuses that send the store an SMS, e.g. array( 'cancelled' ).
			'order_sms_status_alert_text' => '',
			'cancel_reasons'          => 'yes',
			'order_sms_admin_phone'   => '',
			'order_sms_admin_text'    => '',
			'order_tracking'          => 'no',
			'courier_tracking_url'    => '',    // e.g. a courier's tracking page with {consignment}.

			// Fake-order protection.
			'protect_enabled'           => 'no',
			'protect_duplicate_minutes' => 10,   // 0 = off.
			'protect_daily_orders'      => 3,    // Per number per 24 hours; 0 = off.
			'protect_ip_daily_orders'   => 10,   // Per IP address per 24 hours; 0 = off.
			'protect_blocklist'         => '',   // Numbers and IP addresses, one per line.
			'protect_block_message'     => '',   // Empty = the default text, translated.
			'protect_risky_cancelled'   => 2,    // Cancelled orders that make a number "often cancels".
			'protect_otp'               => 'no', // SMS code for risky cash-on-delivery orders.
			'protect_otp_amount'        => 3000, // New numbers ordering at least this much get a code; 0 = never for this reason.
			'protect_otp_message'       => '',   // Empty = the default text, translated. {code} {store}.

			// SMS.
			'sms_gateway'           => '',
			'sms_esms_token'        => '',
			'sms_esms_sender'       => '',
			'sms_twilio_sid'        => '',
			'sms_twilio_token'      => '',
			'sms_twilio_from'       => '',
			'sms_custom_method'     => 'POST',
			'sms_custom_url'        => '',
			'sms_custom_body'       => '',
			'sms_custom_headers'    => '',
			'sms_custom_success'    => '',
			'sms_template'          => __( 'Hi {name}, you left something in your cart at {store}. Complete your order here: {cart_link}', 'cartlum' ),

			// WhatsApp (click-to-chat).
			'whatsapp_template'     => __( 'Hi {name}! This is {store}. You were about to order {items}. Can we help you finish? Your cart is saved here: {cart_link}', 'cartlum' ),

			// Email.
			'email_design'          => 'designed',
			'email_subject'         => __( 'You left something behind at {store}', 'cartlum' ),
			'email_template'        => __( "Hi {name},\n\nYou were about to order:\n{items}\n\nYour cart is saved — finish your order with one click:\n{cart_link}\n\nThanks,\n{store}", 'cartlum' ),

			// Coupons.
			'coupon_type'           => 'fixed_cart',
			'coupon_amount'         => 100,
			'coupon_expiry_days'    => 3,
			'coupon_prefix'         => 'CL',

			// Automation (Flow).
			'flow_enabled'          => 'no',
			'flow_delay'            => 60,  // Minutes after the cart is abandoned.
			'flow_channel'          => 'sms',
			'flow_sms_template'     => __( 'Hi {name}, your cart at {store} is saved. Finish your order here: {cart_link}', 'cartlum' ),
			'quiet_start'           => 22,  // Store-local hour; no automatic messages from here...
			'quiet_end'             => 9,   // ...until here.
			'flow_daily_sms_limit'  => 200,
			'flow_test_phone'       => '',  // When set, automatic SMS go here instead of to customers.

			// Privacy.
			'delete_on_uninstall'   => 'no',
		);

		/**
		 * Defaults for settings added by add-ons (Cartlum Pro). Without one, a field shows up
		 * empty in the form and saving the tab stores the empty value.
		 *
		 * @param array $defaults Defaults.
		 */
		return apply_filters( 'cartlum_settings_defaults', $defaults );
	}

	/**
	 * All settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Saves settings.
	 *
	 * @param array $values Already sanitized values.
	 */
	public static function save( array $values ) {
		update_option( self::OPTION, $values, false );
		self::$cache = null;
	}
}
