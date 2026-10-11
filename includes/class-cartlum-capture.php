<?php
/**
 * Captures the cart while the customer is still on the checkout page -- as soon as a phone number
 * or email is typed, before "Place order" is pressed. Works on the classic checkout (and
 * checkout builders that keep its fields, such as CartFlows) and on the block checkout.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Capture.
 */
class Cartlum_Capture {

	const SESSION_KEY = 'cartlum_cart_id';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'enabled' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		// wc-ajax rather than admin-ajax: WooCommerce loads the cart and session for these requests.
		add_action( 'wc_ajax_cartlum_capture', array( __CLASS__, 'ajax_capture' ) );
		// Keep the saved contents current if the customer changes the cart after being captured.
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'refresh_contents' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'print_notice' ) );
	}

	/**
	 * Front-end script, checkout only.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		wp_enqueue_script( 'cartlum-capture', CARTLUM_URL . 'assets/js/capture.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-capture',
			'cartlumCapture',
			array(
				'url'    => WC_AJAX::get_endpoint( 'cartlum_capture' ),
				'nonce'  => wp_create_nonce( 'cartlum_capture' ),
				'notice' => 'yes' === Cartlum_Settings::get( 'capture_notice' ) ? (string) Cartlum_Settings::get( 'capture_notice_text' ) : '',
			)
		);
	}

	/**
	 * The small privacy notice under the billing form (classic checkout). The block checkout gets
	 * the same text from capture.js.
	 */
	public static function print_notice() {
		// The phone-first checkout shows it under the mobile number field instead.
		if ( 'yes' !== Cartlum_Settings::get( 'capture_notice' ) || Cartlum_Checkout::active() ) {
			return;
		}
		echo '<p class="cartlum-notice" style="font-size:12px;opacity:.75;margin:6px 0 0;">' . esc_html( Cartlum_Settings::get( 'capture_notice_text' ) ) . '</p>';
	}

	/**
	 * AJAX: save or update the cart for this session.
	 */
	public static function ajax_capture() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified just below.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cartlum_capture' ) ) {
			wp_send_json_error( array( 'reason' => 'nonce' ), 403 );
		}
		$phone = isset( $_POST['phone'] ) ? Cartlum_Phone::normalize( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$block = ! empty( $_POST['block'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$email = is_email( $email ) ? $email : '';
		if ( '' === $phone && '' === $email ) {
			wp_send_json_success( array( 'saved' => false ) );
		}

		$id = self::save(
			array(
				'phone'  => $phone,
				'email'  => $email,
				'name'   => trim( $first . ' ' . $last ),
				'source' => $block ? 'block' : 'classic',
			)
		);
		wp_send_json_success( array( 'saved' => (bool) $id ) );
	}

	/**
	 * Saves the current WooCommerce cart against this session.
	 *
	 * @param array $contact { phone, email, name, source }.
	 * @return int Cart id, or 0 when there was nothing to save.
	 */
	public static function save( array $contact ) {
		if ( ! WC()->cart || WC()->cart->is_empty() || ! WC()->session ) {
			return 0;
		}

		/**
		 * Lets a site skip capture, e.g. for staff placing phone orders.
		 *
		 * @param bool  $skip    Skip.
		 * @param array $contact Contact details.
		 */
		if ( apply_filters( 'cartlum_skip_capture', false, $contact ) ) {
			return 0;
		}

		// Make sure the session survives for guests, so the next update finds the same row.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$data = array_merge(
			self::snapshot(),
			array(
				'session_key' => (string) WC()->session->get_customer_id(),
				'user_id'     => get_current_user_id(),
				'locale'      => get_locale(),
				'updated_at'  => Cartlum_Carts::now(),
			)
		);
		foreach ( array( 'phone', 'email', 'name', 'source' ) as $field ) {
			if ( ! empty( $contact[ $field ] ) ) {
				$data[ $field ] = $contact[ $field ];
			}
		}

		$existing = self::current_cart();
		if ( $existing ) {
			// Typing again on the checkout page means they are active again.
			$data['status'] = 'active';
			Cartlum_Carts::update( $existing->id, $data );
			return (int) $existing->id;
		}

		/**
		 * New carts one IP address may create per hour. A cart can lead to automatic messages, so
		 * this keeps a script from creating carts under many numbers. Mobile networks share
		 * addresses, so keep it generous.
		 *
		 * @param int $limit Limit; 0 = none.
		 */
		$limit  = (int) apply_filters( 'cartlum_capture_ip_limit', 30 );
		$ip_key = 'cartlum_cap_ip_' . Cartlum_Guard::hash( Cartlum_Guard::ip() );
		if ( ! Cartlum_Guard::under( $ip_key, $limit ) ) {
			return 0;
		}
		Cartlum_Guard::bump( $ip_key, HOUR_IN_SECONDS );

		$id = Cartlum_Carts::insert( $data );
		if ( $id ) {
			WC()->session->set( self::SESSION_KEY, $id );
			Cartlum_Events::add( $id, 'captured', array( 'user_id' => 0 ) );

			/**
			 * Fires when a new cart is captured.
			 *
			 * @param int $id Cart id.
			 */
			do_action( 'cartlum_cart_captured', $id );
		}
		return $id;
	}

	/**
	 * The open cart row belonging to this session, if any.
	 *
	 * @return object|null
	 */
	public static function current_cart() {
		if ( ! WC()->session ) {
			return null;
		}
		$id = (int) WC()->session->get( self::SESSION_KEY );
		if ( $id ) {
			$cart = Cartlum_Carts::get( $id );
			if ( $cart && in_array( $cart->status, Cartlum_Carts::OPEN, true ) ) {
				return $cart;
			}
		}
		return Cartlum_Carts::get_open_by_session( (string) WC()->session->get_customer_id() );
	}

	/**
	 * Cart contents and value as stored columns.
	 *
	 * @return array
	 */
	public static function snapshot() {
		$items = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$product = isset( $line['data'] ) ? $line['data'] : null;
			$items[] = array(
				'product_id'   => (int) $line['product_id'],
				'variation_id' => (int) $line['variation_id'],
				'quantity'     => (int) $line['quantity'],
				'variation'    => isset( $line['variation'] ) ? (array) $line['variation'] : array(),
				'name'         => $product ? $product->get_name() : '',
				'line_total'   => (float) $line['line_total'] + (float) $line['line_tax'],
			);
		}
		WC()->cart->calculate_totals();
		return array(
			'cart_contents' => wp_json_encode( $items ),
			'cart_total'    => (float) WC()->cart->get_total( 'edit' ),
			'currency'      => get_woocommerce_currency(),
		);
	}

	/**
	 * Cart changed after capture: update the saved copy. Only touches rows that already exist, so
	 * nothing is ever saved for a shopper who has not typed contact details.
	 */
	public static function refresh_contents() {
		static $running = false;
		if ( $running || ! WC()->session || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$id = (int) WC()->session->get( self::SESSION_KEY );
		if ( ! $id ) {
			return;
		}
		$cart = Cartlum_Carts::get( $id );
		if ( ! $cart || ! in_array( $cart->status, Cartlum_Carts::OPEN, true ) ) {
			return;
		}
		$running = true; // calculate_totals() inside snapshot() can fire cart hooks again.
		Cartlum_Carts::update( $id, array_merge( self::snapshot(), array( 'updated_at' => Cartlum_Carts::now() ) ) );
		$running = false;
	}
}
