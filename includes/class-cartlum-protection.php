<?php
/**
 * Fake-order protection for cash-on-delivery stores: a blocklist of numbers and IP addresses,
 * a stop on repeat orders minutes apart, daily order limits per number and per IP address, a
 * hidden bot trap, and a risk check from the number's order history for staff.
 *
 * Works on the classic checkout (and builders that keep it, such as CartFlows), the block
 * checkout and Cartlum's own. Shop managers are never stopped, so phone orders keep working.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Protection.
 */
class Cartlum_Protection {

	const HONEYPOT = 'cartlum_website';
	const DEVICE   = 'cartlum_did';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'protect_enabled' ) ) {
			return;
		}
		// A random ID in a cookie, so staff can block a device that orders under many numbers.
		add_action( 'template_redirect', array( __CLASS__, 'device_cookie' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_device' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'save_device' ), 5 );
		// Classic checkout (also CartFlows and Cartlum's checkout).
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'honeypot_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_classic' ), 20, 2 );
		// Block checkout.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate_block' ), 20, 2 );
		// What staff see.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'note_risk' ), 30 );
		// Fresh counts as soon as one of a number's orders is created or changes status.
		add_action( 'woocommerce_new_order', array( __CLASS__, 'forget' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'forget' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'note_risk' ), 30 );

		// SMS code for risky cash-on-delivery orders (classic checkouts).
		if ( 'yes' === Cartlum_Settings::get( 'protect_otp' ) ) {
			add_action( 'wc_ajax_cartlum_checkout_code', array( __CLASS__, 'ajax_send_code' ) );
			add_action( 'wc_ajax_cartlum_checkout_verify', array( __CLASS__, 'ajax_verify_code' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'code_assets' ), 30 );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'note_verified' ), 31 );
		}
	}

	/* ---------- Rules ---------- */

	/**
	 * Why an order from this number and IP address must not go through, or ''.
	 *
	 * @param string $raw_phone Phone as typed.
	 * @param string $ip        Visitor's IP address.
	 * @return string Message for the customer.
	 */
	public static function check( $raw_phone, $ip ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return ''; // Staff placing an order for a customer.
		}
		$phone   = Cartlum_Phone::normalize( $raw_phone );
		$blocked = self::blocklist();
		$device  = self::device();
		if ( ( '' !== $phone && in_array( $phone, $blocked['phones'], true ) ) || self::ip_blocked( $ip, $blocked ) || ( '' !== $device && in_array( $device, $blocked['devices'], true ) ) ) {
			$message = trim( (string) Cartlum_Settings::get( 'protect_block_message' ) );
			return '' !== $message ? $message : __( 'We cannot take this order online. Please call us to order.', 'cartlum' );
		}

		if ( '' !== $phone ) {
			$minutes = (int) Cartlum_Settings::get( 'protect_duplicate_minutes' );
			if ( $minutes > 0 ) {
				$recent = self::orders( $phone, $minutes * MINUTE_IN_SECONDS );
				if ( $recent ) {
					return sprintf(
						/* translators: %s: order number */
						__( 'You placed order #%s a few minutes ago. To change it or add to it, please call us instead of ordering again.', 'cartlum' ),
						$recent[0]->get_order_number()
					);
				}
			}
			$daily = (int) Cartlum_Settings::get( 'protect_daily_orders' );
			if ( $daily > 0 && count( self::orders( $phone, DAY_IN_SECONDS ) ) >= $daily ) {
				return __( 'This number has reached today’s online order limit. Please call us to order.', 'cartlum' );
			}
		}

		$ip_daily = (int) Cartlum_Settings::get( 'protect_ip_daily_orders' );
		if ( $ip_daily > 0 && '' !== $ip && self::orders_from_ip( $ip ) >= $ip_daily ) {
			return __( 'Too many orders from this connection today. Please call us to order.', 'cartlum' );
		}
		return '';
	}

	/**
	 * The blocklist setting, split into normalized numbers, IP addresses, IP ranges (CIDR, such as
	 * 103.4.5.0/24) and devices ("device:" and the device ID).
	 *
	 * @return array { phones: string[], ips: string[], ranges: string[], devices: string[] }
	 */
	public static function blocklist() {
		$out = array(
			'phones'  => array(),
			'ips'     => array(),
			'ranges'  => array(),
			'devices' => array(),
		);
		foreach ( preg_split( '/[\r\n,;]+/', (string) Cartlum_Settings::get( 'protect_blocklist' ) ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( 0 === stripos( $entry, 'device:' ) ) {
				$id = strtolower( trim( substr( $entry, 7 ) ) );
				if ( preg_match( '/^[a-f0-9]{20}$/', $id ) ) {
					$out['devices'][] = $id;
				}
			} elseif ( false !== strpos( $entry, '/' ) && self::cidr( $entry ) ) {
				$out['ranges'][] = $entry;
			} elseif ( filter_var( $entry, FILTER_VALIDATE_IP ) ) {
				$out['ips'][] = $entry;
			} else {
				$phone = Cartlum_Phone::normalize( $entry );
				if ( '' !== $phone ) {
					$out['phones'][] = $phone;
				}
			}
		}
		return $out;
	}

	/**
	 * A valid CIDR range, split into [ packed network, prefix length ], or null.
	 *
	 * @param string $range e.g. 103.4.5.0/24.
	 * @return array|null
	 */
	private static function cidr( $range ) {
		list( $net, $bits ) = array_pad( explode( '/', $range, 2 ), 2, '' );
		$packed = filter_var( $net, FILTER_VALIDATE_IP ) ? inet_pton( $net ) : false;
		if ( false === $packed || ! ctype_digit( $bits ) || (int) $bits > strlen( $packed ) * 8 ) {
			return null;
		}
		return array( $packed, (int) $bits );
	}

	/**
	 * Whether an IP address is blocked, by itself or inside a blocked range.
	 *
	 * @param string $ip      IP address.
	 * @param array  $blocked From blocklist().
	 * @return bool
	 */
	public static function ip_blocked( $ip, array $blocked ) {
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( in_array( $ip, $blocked['ips'], true ) ) {
			return true;
		}
		$packed = inet_pton( $ip );
		foreach ( $blocked['ranges'] as $range ) {
			list( $net, $bits ) = self::cidr( $range );
			if ( strlen( $net ) !== strlen( $packed ) ) {
				continue; // IPv4 range, IPv6 visitor or the other way round.
			}
			$bytes = intdiv( $bits, 8 );
			$rest  = $bits % 8;
			if ( substr( $packed, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * This browser's device ID, or ''.
	 *
	 * @return string
	 */
	public static function device() {
		$id = isset( $_COOKIE[ self::DEVICE ] ) ? strtolower( sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE ] ) ) ) : '';
		return preg_match( '/^[a-f0-9]{20}$/', $id ) ? $id : '';
	}

	/**
	 * Gives the browser a device ID on shop pages. Clearing cookies or switching browser gives a
	 * new one, so a blocked device can come back that way; phone and IP blocks still apply.
	 */
	public static function device_cookie() {
		if ( '' !== self::device() || headers_sent() || is_admin() || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		if ( ! ( is_woocommerce() || is_cart() || is_checkout() ) ) {
			return;
		}
		$id = bin2hex( random_bytes( 10 ) );
		setcookie(
			self::DEVICE,
			$id,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::DEVICE ] = $id;
	}

	/**
	 * Keeps the device ID with the order, for the Block device button.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function save_device( $order ) {
		$device = self::device();
		if ( '' !== $device && $order instanceof WC_Order ) {
			$order->update_meta_data( '_cartlum_device', $device );
		}
	}

	/**
	 * Adds an IP address or device to the blocklist.
	 *
	 * @param string $entry IP address, or "device:" and a device ID.
	 * @return bool Added.
	 */
	public static function block_entry( $entry ) {
		$entry = trim( (string) $entry );
		if ( ! filter_var( $entry, FILTER_VALIDATE_IP ) && ! preg_match( '/^device:[a-f0-9]{20}$/', $entry ) ) {
			return false;
		}
		$lines = preg_split( '/\r\n|\r|\n/', (string) Cartlum_Settings::get( 'protect_blocklist' ) );
		if ( in_array( $entry, array_map( 'trim', $lines ), true ) ) {
			return false;
		}
		$all                      = get_option( Cartlum_Settings::OPTION, array() );
		$all                      = is_array( $all ) ? $all : array();
		$all['protect_blocklist'] = ltrim( trim( (string) Cartlum_Settings::get( 'protect_blocklist' ) ) . "\n" . $entry );
		Cartlum_Settings::save( $all );
		return true;
	}

	/**
	 * Takes an IP address or device off the blocklist.
	 *
	 * @param string $entry IP address, or "device:" and a device ID.
	 * @return bool Removed.
	 */
	public static function unblock_entry( $entry ) {
		$entry   = trim( (string) $entry );
		$kept    = array();
		$removed = false;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Cartlum_Settings::get( 'protect_blocklist' ) ) as $line ) {
			if ( '' !== $entry && strtolower( trim( $line ) ) === strtolower( $entry ) ) {
				$removed = true;
				continue;
			}
			$kept[] = $line;
		}
		if ( $removed ) {
			$all                      = get_option( Cartlum_Settings::OPTION, array() );
			$all                      = is_array( $all ) ? $all : array();
			$all['protect_blocklist'] = trim( implode( "\n", $kept ) );
			Cartlum_Settings::save( $all );
		}
		return $removed;
	}

	/**
	 * Adds a number to the blocklist.
	 *
	 * @param string $raw_phone Phone.
	 * @return bool Added.
	 */
	public static function block( $raw_phone ) {
		$phone = Cartlum_Phone::normalize( $raw_phone );
		if ( '' === $phone || in_array( $phone, self::blocklist()['phones'], true ) ) {
			return false;
		}
		$all                      = get_option( Cartlum_Settings::OPTION, array() );
		$all                      = is_array( $all ) ? $all : array();
		$list                     = trim( (string) Cartlum_Settings::get( 'protect_blocklist' ) );
		$variants                 = Cartlum_Phone::variants( $phone );
		$all['protect_blocklist'] = ltrim( $list . "\n" . end( $variants ) );
		Cartlum_Settings::save( $all );
		return true;
	}

	/**
	 * Takes a number off the blocklist, however it was written there.
	 *
	 * @param string $raw_phone Phone.
	 * @return bool Removed.
	 */
	public static function unblock( $raw_phone ) {
		$phone = Cartlum_Phone::normalize( $raw_phone );
		if ( '' === $phone ) {
			return false;
		}
		$kept    = array();
		$removed = false;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Cartlum_Settings::get( 'protect_blocklist' ) ) as $line ) {
			if ( '' !== trim( $line ) && Cartlum_Phone::normalize( $line ) === $phone && ! filter_var( trim( $line ), FILTER_VALIDATE_IP ) ) {
				$removed = true;
				continue;
			}
			$kept[] = $line;
		}
		if ( $removed ) {
			$all                      = get_option( Cartlum_Settings::OPTION, array() );
			$all                      = is_array( $all ) ? $all : array();
			$all['protect_blocklist'] = trim( implode( "\n", $kept ) );
			Cartlum_Settings::save( $all );
		}
		return $removed;
	}

	/**
	 * Orders placed with this number in the last $seconds (live statuses only: a cancelled or
	 * failed order does not count against the customer).
	 *
	 * @param string $phone   Normalized digits.
	 * @param int    $seconds Window.
	 * @return WC_Order[] Newest first.
	 */
	private static function orders( $phone, $seconds ) {
		$found = array();
		foreach ( Cartlum_Phone::variants( $phone ) as $variant ) {
			foreach ( wc_get_orders(
				array(
					'billing_phone' => $variant,
					'date_created'  => '>' . ( time() - $seconds ),
					'status'        => self::live_statuses(),
					'type'          => 'shop_order',
					'limit'         => 20,
				)
			) as $order ) {
				$found[ $order->get_id() ] = $order;
			}
		}
		krsort( $found );
		return array_values( $found );
	}

	/**
	 * Orders from this IP address in the last day, live statuses only.
	 *
	 * @param string $ip IP address.
	 * @return int
	 */
	private static function orders_from_ip( $ip ) {
		return count(
			wc_get_orders(
				array(
					'customer_ip_address' => $ip,
					'date_created'        => '>' . ( time() - DAY_IN_SECONDS ),
					'status'              => self::live_statuses(),
					'type'                => 'shop_order',
					'limit'               => 100,
					'return'              => 'ids',
				)
			)
		);
	}

	/**
	 * Order statuses that mean "a real order exists".
	 *
	 * @return string[]
	 */
	private static function live_statuses() {
		$all = array_map(
			function ( $status ) {
				return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
			},
			array_keys( wc_get_order_statuses() )
		);
		return array_values( array_diff( $all, array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft' ) ) );
	}

	/**
	 * The visitor's IP address, as WooCommerce records it on orders.
	 *
	 * @return string
	 */
	private static function ip() {
		return class_exists( 'WC_Geolocation' ) ? (string) WC_Geolocation::get_ip_address() : '';
	}

	/* ---------- Checkout ---------- */

	/**
	 * A field people never see; bots fill in every field they find.
	 */
	public static function honeypot_field() {
		printf(
			'<div style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label for="%1$s">%2$s</label><input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off"></div>',
			esc_attr( self::HONEYPOT ),
			esc_html__( 'Leave this empty', 'cartlum' )
		);
	}

	/**
	 * Classic checkout.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate_classic( $data, $errors ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce.
		if ( ! empty( $_POST[ self::HONEYPOT ] ) ) {
			$errors->add( 'cartlum_bot', __( 'Your order could not be placed. Please try again.', 'cartlum' ) );
			return;
		}
		$phone   = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		$message = self::check( $phone, self::ip() );
		if ( '' !== $message ) {
			$errors->add( 'cartlum_protect', $message );
			return;
		}
		// Ask for the SMS code last, once nothing else is wrong with the order.
		if ( ! $errors->has_errors() && 'yes' === Cartlum_Settings::get( 'protect_otp' ) && self::needs_code( $phone, isset( $data['payment_method'] ) ? (string) $data['payment_method'] : '' ) ) {
			// The code may only be sent to the number of this checkout attempt (see ajax_send_code).
			if ( WC()->session ) {
				WC()->session->set( 'cartlum_code_for', Cartlum_OTP::mobile( $phone ) );
			}
			$errors->add(
				'cartlum_otp_required',
				esc_html__( 'Please confirm your mobile number with the code we send by SMS.', 'cartlum' ) . '<span class="cartlum-otp-required" hidden></span>'
			);
		}
	}

	/* ---------- SMS code at checkout ---------- */

	/**
	 * Whether this order must be confirmed with an SMS code: cash on delivery from a number that
	 * often cancels, a new number ordering a large amount, or a connection that already ordered
	 * several times today. A number confirmed earlier in the visit, or proven by phone login, is
	 * not asked again.
	 *
	 * @param string $raw_phone      Phone as typed.
	 * @param string $payment_method Chosen payment method.
	 * @return bool
	 */
	public static function needs_code( $raw_phone, $payment_method ) {
		if ( 'cod' !== $payment_method || current_user_can( 'manage_woocommerce' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		$phone = Cartlum_OTP::mobile( $raw_phone );
		if ( '' === $phone ) {
			return false; // The checkout's own phone check reports a bad number.
		}
		if ( WC()->session && WC()->session->get( 'cartlum_verified_phone' ) === $phone ) {
			return false;
		}
		if ( is_user_logged_in() && get_user_meta( get_current_user_id(), 'cartlum_phone', true ) === $phone ) {
			return false;
		}
		$stats = self::history( $phone );
		if ( 'risky' === $stats['risk'] ) {
			return true;
		}
		$amount = (float) Cartlum_Settings::get( 'protect_otp_amount' );
		if ( 'new' === $stats['risk'] && $amount > 0 && (float) WC()->cart->get_total( 'edit' ) >= $amount ) {
			return true;
		}
		$ip = self::ip();
		return '' !== $ip && self::orders_from_ip( $ip ) >= 2;
	}

	/**
	 * Checks the code request's nonce, or ends it.
	 */
	private static function check_code_nonce() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, not stored.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'cartlum_checkout_code' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Please reload it and try again.', 'cartlum' ) ), 403 );
		}
	}

	/**
	 * AJAX: send the code. Only to the number whose checkout attempt in this visit was just asked
	 * for one, and only while that cart still needs it, so the endpoint cannot be used to text
	 * arbitrary numbers; the code limits of Cartlum_OTP apply on top.
	 */
	public static function ajax_send_code() {
		self::check_code_nonce();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$raw     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$payment = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$phone = Cartlum_OTP::mobile( $raw );
		$asked = WC()->session ? (string) WC()->session->get( 'cartlum_code_for' ) : '';
		if ( '' === $phone || $phone !== $asked || ! self::needs_code( $phone, $payment ) ) {
			wp_send_json_error( array( 'message' => __( 'No code is needed for this order. Please place it again.', 'cartlum' ) ) );
		}
		$template = trim( (string) Cartlum_Settings::get( 'protect_otp_message' ) );
		$template = '' !== $template ? $template : __( '{code} is your {store} order code. It expires in 10 minutes.', 'cartlum' );
		$result   = Cartlum_OTP::send( $phone, $template );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				/* translators: %s: phone number */
				'message' => sprintf( __( 'We sent a 6-digit code to %s.', 'cartlum' ), Cartlum_OTP::shown( $phone ) ),
			)
		);
	}

	/**
	 * AJAX: check the code; the number counts as confirmed for the rest of the visit.
	 */
	public static function ajax_verify_code() {
		self::check_code_nonce();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$phone = Cartlum_OTP::mobile( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = Cartlum_OTP::verify( $phone, $code );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( WC()->session ) {
			WC()->session->set( 'cartlum_verified_phone', $phone );
		}
		wp_send_json_success();
	}

	/**
	 * A note on orders whose number was confirmed by code.
	 *
	 * @param int|WC_Order $order Order.
	 */
	public static function note_verified( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || ! WC()->session ) {
			return;
		}
		$verified = (string) WC()->session->get( 'cartlum_verified_phone' );
		if ( '' !== $verified && Cartlum_Phone::normalize( $order->get_billing_phone() ) === $verified ) {
			$order->update_meta_data( '_cartlum_phone_verified', 'yes' );
			$order->add_order_note( __( 'Cartlum: the customer confirmed this phone number with an SMS code.', 'cartlum' ) );
			$order->save();
		}
	}

	/**
	 * The code box's script and texts, on checkout pages.
	 */
	public static function code_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		wp_enqueue_script( 'cartlum-checkout-code', CARTLUM_URL . 'assets/js/checkout-code.js', array( 'jquery' ), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-checkout-code',
			'cartlumCode',
			array(
				'send'     => WC_AJAX::get_endpoint( 'cartlum_checkout_code' ),
				'verify'   => WC_AJAX::get_endpoint( 'cartlum_checkout_verify' ),
				'nonce'    => wp_create_nonce( 'cartlum_checkout_code' ),
				'wait'     => Cartlum_OTP::RESEND_WAIT,
				'title'    => __( 'Confirm your mobile number', 'cartlum' ),
				'label'    => __( '6-digit code', 'cartlum' ),
				'confirm'  => __( 'Confirm and place order', 'cartlum' ),
				'resend'   => __( 'Send a new code', 'cartlum' ),
				/* translators: %d: seconds */
				'resendIn' => __( 'Send a new code (%d)', 'cartlum' ),
				'busy'     => __( 'Please wait…', 'cartlum' ),
				'network'  => __( 'Could not reach the store. Check your connection and try again.', 'cartlum' ),
			)
		);
		wp_register_style( 'cartlum-checkout-code', false, array(), CARTLUM_VERSION );
		wp_enqueue_style( 'cartlum-checkout-code' );
		wp_add_inline_style(
			'cartlum-checkout-code',
			Cartlum_Checkout::accent_css( '.cartlum-otp' ) . '
.cartlum-otp{box-sizing:border-box;margin:14px 0;padding:16px;border:1px solid var(--clx-accent);border-radius:6px;background:#fff;color:#1a1a1a;font-size:15px;line-height:1.5}
.cartlum-otp *{box-sizing:border-box}
.cartlum-otp__title{margin:0 0 4px;font-size:16px;font-weight:600}
.cartlum-otp__msg{margin:0 0 12px;color:#5e5e5e;font-size:14px}
.cartlum-otp__msg.is-error{color:#c4320a}
.cartlum-otp label{display:block;margin:0 0 6px;font-size:14px;font-weight:600}
.cartlum-otp input{display:block;width:100%;height:52px;margin:0 0 12px;padding:0 14px;border:1px solid #8c8c8c;border-radius:6px;font-size:22px;letter-spacing:.3em;font-variant-numeric:tabular-nums}
.cartlum-otp input:focus{border-color:var(--clx-accent);box-shadow:0 0 0 1px var(--clx-accent);outline:0}
.cartlum-otp__go{display:block;width:100%;min-height:52px;margin:0;padding:12px 18px;border:0;border-radius:6px;background:var(--clx-accent);color:var(--clx-on-accent);font-size:16px;font-weight:600;cursor:pointer}
.cartlum-otp__go:disabled{opacity:.7;cursor:progress}
.cartlum-otp__resend{min-height:44px;margin:8px 0 0;padding:0;border:0;background:none;color:#1a1a1a;font-size:14px;text-decoration:underline;cursor:pointer}
.cartlum-otp__resend:disabled{color:#5e5e5e;text-decoration:none;cursor:default}'
		);
	}

	/**
	 * Block checkout: stop the order before it is placed.
	 *
	 * @param WC_Order        $order   Order being built.
	 * @param WP_REST_Request $request Request.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the order must not go through.
	 */
	public static function validate_block( $order, $request ) {
		unset( $request );
		$message = self::check( $order->get_billing_phone(), self::ip() );
		if ( '' !== $message && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'cartlum_protect', esc_html( $message ), 400 );
		}
	}

	/* ---------- For staff ---------- */

	/**
	 * This store's history with a number: orders, completed, cancelled (or failed / refunded), and
	 * the courier's delivery success rate when Steadfast recorded one.
	 *
	 * @param string   $raw_phone Phone.
	 * @param int|null $exclude   Order to leave out (the one being looked at).
	 * @return array { orders, completed, cancelled, courier_ratio, risk: good|new|risky|blocked }
	 */
	public static function history( $raw_phone, $exclude = null ) {
		static $memo = array();
		$phone = Cartlum_Phone::normalize( $raw_phone );
		$memo_key = $phone . '|' . (int) $exclude;
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}
		$cache_key = 'cartlum_hist_' . md5( $memo_key . '|' . (int) get_transient( self::version_key( $phone ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['risk']    = self::risk_for( $phone, $cached );
			$memo[ $memo_key ] = $cached;
			return $cached;
		}
		$stats = array(
			'orders'        => 0,
			'completed'     => 0,
			'cancelled'     => 0,
			'courier_ratio' => '',
			'risk'          => 'new',
		);
		if ( '' === $phone ) {
			return $stats;
		}
		$seen = array();
		foreach ( Cartlum_Phone::variants( $phone ) as $variant ) {
			foreach ( wc_get_orders(
				array(
					'billing_phone' => $variant,
					'type'          => 'shop_order',
					'limit'         => 200,
				)
			) as $order ) {
				if ( isset( $seen[ $order->get_id() ] ) || (int) $exclude === $order->get_id() ) {
					continue;
				}
				$seen[ $order->get_id() ] = true;
				++$stats['orders'];
				if ( $order->has_status( 'completed' ) ) {
					++$stats['completed'];
				} elseif ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
					++$stats['cancelled'];
				}
				if ( '' === $stats['courier_ratio'] ) {
					$stats['courier_ratio'] = (string) get_post_meta( $order->get_id(), 'stdf_success_ratio', true );
				}
			}
		}

		// Counting orders is the slow part; the blocklist is checked fresh every time.
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
		$stats['risk']     = self::risk_for( $phone, $stats );
		$memo[ $memo_key ] = $stats;
		return $stats;
	}

	/**
	 * Cache version for a number's history.
	 *
	 * @param string $phone Normalized digits.
	 * @return string
	 */
	private static function version_key( $phone ) {
		return 'cartlum_hist_v_' . md5( $phone );
	}

	/**
	 * Drops cached history for an order's number.
	 *
	 * @param int $order_id Order.
	 */
	public static function forget( $order_id ) {
		$order = wc_get_order( $order_id );
		$phone = $order ? Cartlum_Phone::normalize( $order->get_billing_phone() ) : '';
		if ( '' !== $phone ) {
			set_transient( self::version_key( $phone ), microtime( true ) * 1000, DAY_IN_SECONDS );
		}
	}

	/**
	 * Risk from the counts.
	 *
	 * @param string $phone Normalized digits.
	 * @param array  $stats Counts.
	 * @return string good|new|risky|blocked
	 */
	private static function risk_for( $phone, $stats ) {
		$risky_after = max( 1, (int) Cartlum_Settings::get( 'protect_risky_cancelled' ) );
		if ( in_array( $phone, self::blocklist()['phones'], true ) ) {
			return 'blocked';
		}
		if ( $stats['cancelled'] >= $risky_after && $stats['cancelled'] >= $stats['completed'] ) {
			$risk = 'risky';
		} else {
			$risk = $stats['completed'] > 0 ? 'good' : 'new';
		}

		/**
		 * Filters a number's risk (Cartlum Pro: courier success rate).
		 *
		 * @param string $risk  good|new|risky.
		 * @param string $phone Normalized phone.
		 * @param array  $stats This store's history with the number.
		 */
		return (string) apply_filters( 'cartlum_risk', $risk, $phone, $stats );
	}

	/**
	 * Risk badge text.
	 *
	 * @param string $risk Risk.
	 * @return string
	 */
	public static function risk_label( $risk ) {
		$labels = array(
			'good'    => __( 'Good customer', 'cartlum' ),
			'new'     => __( 'New number', 'cartlum' ),
			'risky'   => __( 'Often cancels', 'cartlum' ),
			'blocked' => __( 'Blocked', 'cartlum' ),
		);
		return isset( $labels[ $risk ] ) ? $labels[ $risk ] : $risk;
	}

	/**
	 * A note on new orders from risky numbers, so staff call before shipping.
	 *
	 * @param int|WC_Order $order Order.
	 */
	public static function note_risk( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return;
		}
		$stats = self::history( $order->get_billing_phone(), $order->get_id() );
		if ( 'risky' === $stats['risk'] ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: cancelled orders, 2: all earlier orders */
					__( 'Cartlum: this number cancelled %1$d of its %2$d earlier orders. Consider calling to confirm before shipping.', 'cartlum' ),
					$stats['cancelled'],
					$stats['orders']
				)
			);
		}
	}
}
