<?php
/**
 * Log in to My Account with a phone number and a code sent by SMS. Customers who order with a
 * phone number rarely have a password; the code proves they own the number. The first login
 * creates the account, and every login attaches that number's guest orders to it, so customers
 * see all their orders.
 *
 * Safety: codes and their limits are Cartlum_OTP's; accounts that can edit the site never log in
 * this way.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Phone_Login.
 */
class Cartlum_Phone_Login {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'phone_login' ) ) {
			return;
		}
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 20, 2 );
		add_action( 'wc_ajax_cartlum_otp_send', array( __CLASS__, 'ajax_send' ) );
		add_action( 'wc_ajax_cartlum_otp_verify', array( __CLASS__, 'ajax_verify' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// Accounts made from a phone number may have no email address.
		add_filter( 'woocommerce_save_account_details_required_fields', array( __CLASS__, 'account_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'address_fields' ), 20 );
	}

	/**
	 * Cartlum's login form on My Account.
	 *
	 * @param string $template      Located file.
	 * @param string $template_name WooCommerce template name.
	 * @return string
	 */
	public static function locate_template( $template, $template_name ) {
		return 'myaccount/form-login.php' === $template_name ? CARTLUM_DIR . 'templates/myaccount/form-login.php' : $template;
	}

	/**
	 * WooCommerce's own login form (for staff and email logins), inside Cartlum's. Loaded by path:
	 * WooCommerce caches where it found a template, so asking it again would return Cartlum's.
	 */
	public static function classic_form() {
		static $inside = false;
		if ( $inside ) {
			return;
		}
		$inside = true;
		$file   = locate_template( array( trailingslashit( WC()->template_path() ) . 'myaccount/form-login.php' ) );
		if ( ! $file ) {
			$file = WC()->plugin_path() . '/templates/myaccount/form-login.php';
		}
		include $file;
		$inside = false;
	}

	/**
	 * Script and styles on the logged-out My Account page.
	 */
	public static function assets() {
		if ( is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		wp_enqueue_style( 'cartlum-account', CARTLUM_URL . 'assets/css/account.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-account', Cartlum_Checkout::accent_css( '.clx-login' ) );
		wp_enqueue_script( 'cartlum-phone-login', CARTLUM_URL . 'assets/js/phone-login.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-phone-login',
			'cartlumLogin',
			array(
				'send'     => WC_AJAX::get_endpoint( 'cartlum_otp_send' ),
				'verify'   => WC_AJAX::get_endpoint( 'cartlum_otp_verify' ),
				'nonce'    => wp_create_nonce( 'cartlum_otp' ),
				'wait'     => Cartlum_OTP::RESEND_WAIT,
				'sendText' => __( 'Send code', 'cartlum' ),
				'login'    => __( 'Log in', 'cartlum' ),
				'resend'   => __( 'Send a new code', 'cartlum' ),
				/* translators: %d: seconds */
				'resendIn' => __( 'Send a new code (%d)', 'cartlum' ),
				'busy'     => __( 'Please wait…', 'cartlum' ),
				'network'  => __( 'Could not reach the store. Check your connection and try again.', 'cartlum' ),
			)
		);
	}

	/* ---------- Codes ---------- */

	/**
	 * The phone number from the request, normalized, or '' if it is not a usable mobile number.
	 *
	 * @return string
	 */
	private static function posted_phone() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the callers verify the nonce first.
		return Cartlum_OTP::mobile( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
	}

	/**
	 * Checks the request's nonce, or ends it.
	 */
	private static function check_nonce() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, not stored.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'cartlum_otp' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Please reload it and try again.', 'cartlum' ) ), 403 );
		}
	}

	/**
	 * AJAX: send a code.
	 */
	public static function ajax_send() {
		self::check_nonce();
		$phone = self::posted_phone();
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an 11-digit mobile number, e.g. 01712345678.', 'cartlum' ) ) );
		}
		$template = trim( (string) Cartlum_Settings::get( 'otp_message' ) );
		$template = '' !== $template ? $template : __( '{code} is your {store} login code. It expires in 10 minutes.', 'cartlum' );
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
	 * AJAX: check a code and log in.
	 */
	public static function ajax_verify() {
		self::check_nonce();
		$phone = self::posted_phone();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in check_nonce().
		$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$result = Cartlum_OTP::verify( $phone, $code );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$user = self::find_user( $phone );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => $user->get_error_message() ) );
		}
		if ( ! $user ) {
			$user = self::create_user( $phone );
			if ( is_wp_error( $user ) ) {
				wp_send_json_error( array( 'message' => __( 'Your account could not be created. Please contact the store.', 'cartlum' ) ) );
			}
		}

		self::claim_orders( $user->ID, $phone );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own login action, so other plugins see the login.
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success( array( 'redirect' => wc_get_page_permalink( 'myaccount' ) ) );
	}

	/* ---------- Accounts and orders ---------- */

	/**
	 * The account that owns this number, null if none, or an error for staff accounts.
	 *
	 * @param string $phone Normalized digits.
	 * @return WP_User|null|WP_Error
	 */
	private static function find_user( $phone ) {
		// A lost or swapped SIM must never open an account that can change the store, so a number
		// that belongs to staff (as their billing phone or a proven one) is refused outright.
		$staff = get_users(
			array(
				'number'         => 1,
				'fields'         => 'ID',
				'capability__in' => array( 'edit_posts', 'manage_woocommerce' ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only on a successful code check.
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => 'cartlum_phone',
						'value' => $phone,
					),
					array(
						'key'     => 'billing_phone',
						'value'   => Cartlum_Phone::variants( $phone ),
						'compare' => 'IN',
					),
				),
			)
		);
		if ( $staff ) {
			return new WP_Error( 'cartlum_staff', __( 'This number belongs to a staff account. Please log in with your email and password.', 'cartlum' ) );
		}

		// Only a number someone has already proven with a code identifies their account. A billing
		// phone is just text a customer typed: anyone can put someone else's number in their own
		// account, and matching on it would hand that person the real owner's orders.
		$users = get_users(
			array(
				'number'     => 1,
				'orderby'    => 'ID',
				'order'      => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only on a successful code check.
				'meta_query' => array(
					array(
						'key'   => 'cartlum_phone',
						'value' => $phone,
					),
				),
			)
		);
		return $users ? $users[0] : null;
	}

	/**
	 * A new customer account for this number, named after their latest order if there is one.
	 *
	 * @param string $phone Normalized digits.
	 * @return WP_User|WP_Error
	 */
	private static function create_user( $phone ) {
		$login = 'cl' . $phone;
		while ( username_exists( $login ) ) {
			$login = 'cl' . $phone . '-' . wp_rand( 100, 999 );
		}
		$name   = '';
		$orders = self::orders_for( $phone, 1 );
		if ( $orders ) {
			$name = trim( $orders[0]->get_billing_first_name() . ' ' . $orders[0]->get_billing_last_name() );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'    => $login,
				'user_pass'     => wp_generate_password( 32, true, true ),
				'role'          => 'customer',
				// Author URLs use the nicename; keep the phone number out of anything public.
				'user_nicename' => 'c-' . strtolower( wp_generate_password( 12, false, false ) ),
				'display_name'  => '' !== $name ? $name : __( 'Customer', 'cartlum' ),
				'first_name'    => $name,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$variants = Cartlum_Phone::variants( $phone );
		update_user_meta( $user_id, 'cartlum_phone', $phone );
		update_user_meta( $user_id, 'billing_phone', end( $variants ) );
		update_user_meta( $user_id, 'billing_first_name', $name );
		update_user_meta( $user_id, 'billing_country', WC()->countries->get_base_country() );

		/**
		 * Fires when Cartlum creates a customer account from a phone login.
		 *
		 * @param int    $user_id User.
		 * @param string $phone   Normalized phone.
		 */
		do_action( 'cartlum_phone_account_created', $user_id, $phone );
		return get_user_by( 'id', $user_id );
	}

	/**
	 * Orders placed with this number, newest first.
	 *
	 * @param string $phone Normalized digits.
	 * @param int    $limit Most per stored format.
	 * @return WC_Order[]
	 */
	private static function orders_for( $phone, $limit = 200 ) {
		$orders = array();
		foreach ( Cartlum_Phone::variants( $phone ) as $variant ) {
			foreach ( wc_get_orders(
				array(
					'billing_phone' => $variant,
					'limit'         => $limit,
					'type'          => 'shop_order',
					'orderby'       => 'date',
					'order'         => 'DESC',
				)
			) as $order ) {
				$orders[ $order->get_id() ] = $order;
			}
		}
		uasort(
			$orders,
			function ( $a, $b ) {
				return $b->get_id() - $a->get_id();
			}
		);
		return array_values( $orders );
	}

	/**
	 * Attaches this number's guest orders to the account, so My Account lists them and lets the
	 * customer open them. Only after the code has proved the number is theirs.
	 *
	 * @param int    $user_id User.
	 * @param string $phone   Normalized digits.
	 */
	private static function claim_orders( $user_id, $phone ) {
		foreach ( self::orders_for( $phone ) as $order ) {
			if ( 0 === (int) $order->get_customer_id() ) {
				$order->set_customer_id( $user_id );
				$order->add_order_note( __( 'Linked to the customer’s account after they logged in with this phone number (Cartlum).', 'cartlum' ) );
				$order->save();
			}
		}
	}

	/* ---------- Accounts without email ---------- */

	/**
	 * Account details can be saved without an email address by customers who have none.
	 *
	 * @param array $fields Required fields.
	 * @return array
	 */
	public static function account_fields( $fields ) {
		$user = wp_get_current_user();
		if ( $user->exists() && '' === (string) $user->user_email ) {
			unset( $fields['account_email'] );
		}
		return $fields;
	}

	/**
	 * The billing address can be saved without an email address by customers who have none.
	 *
	 * @param array $fields Billing fields.
	 * @return array
	 */
	public static function address_fields( $fields ) {
		if ( isset( $fields['billing_email'] ) && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) ) {
			$user = wp_get_current_user();
			if ( $user->exists() && '' === (string) $user->user_email ) {
				$fields['billing_email']['required'] = false;
			}
		}
		return $fields;
	}
}
