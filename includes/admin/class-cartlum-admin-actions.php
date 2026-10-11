<?php
/**
 * Form actions from the admin screens. Each one checks the capability and its nonce, does the
 * work, and redirects back with a notice.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Actions.
 */
class Cartlum_Admin_Actions {

	/**
	 * Hooks.
	 */
	public static function init() {
		foreach ( array( 'send_sms', 'send_email', 'log_call', 'whatsapp', 'coupon', 'set_status', 'test_sms', 'flow_stop', 'export' ) as $action ) {
			add_action( 'admin_post_cartlum_' . $action, array( __CLASS__, $action ) );
		}
	}

	/**
	 * Call outcomes staff can log.
	 *
	 * @return array
	 */
	public static function call_outcomes() {
		return array(
			'no_answer'      => __( 'No answer', 'cartlum' ),
			'call_back'      => __( 'Asked to call back', 'cartlum' ),
			'will_order'     => __( 'Will order', 'cartlum' ),
			'ordered_phone'  => __( 'Ordered on the phone', 'cartlum' ),
			'not_interested' => __( 'Not interested', 'cartlum' ),
			'wrong_number'   => __( 'Wrong number', 'cartlum' ),
		);
	}

	/**
	 * Admin-post URL for the WhatsApp button: logs the contact, then opens wa.me.
	 *
	 * @param object $cart Row.
	 * @return string
	 */
	public static function whatsapp_url( $cart ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'cartlum_whatsapp',
					'cart'   => (int) $cart->id,
				),
				admin_url( 'admin-post.php' )
			),
			'cartlum_whatsapp_' . $cart->id
		);
	}

	/**
	 * Guard: capability + nonce, returns the cart.
	 *
	 * @param string $nonce_action Nonce action prefix; the cart id is appended.
	 * @return object Cart row.
	 */
	private static function guard( $nonce_action ) {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		$id = isset( $_REQUEST['cart'] ) ? absint( $_REQUEST['cart'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( $nonce_action . '_' . $id );
		$cart = Cartlum_Carts::get( $id );
		if ( ! $cart ) {
			wp_die( esc_html__( 'Cart not found.', 'cartlum' ), 404 );
		}
		return $cart;
	}

	/**
	 * Back to the cart screen with a notice.
	 *
	 * @param int    $cart_id Cart.
	 * @param string $message Text.
	 * @param string $type    success|error.
	 */
	private static function back( $cart_id, $message, $type = 'success' ) {
		wp_safe_redirect(
			Cartlum_Admin::url(
				'cartlum-carts',
				array_merge( array( 'cart' => (int) $cart_id ), Cartlum_Admin::notice_args( $message, $type ) )
			)
		);
		exit;
	}

	/**
	 * Posted text field.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function posted( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
	}

	/**
	 * Send SMS.
	 */
	public static function send_sms() {
		$cart    = self::guard( 'cartlum_send_sms' );
		$message = Cartlum_Messages::render( self::posted( 'message' ), $cart );
		if ( is_wp_error( $message ) ) {
			self::back( $cart->id, $message->get_error_message(), 'error' );
		}
		$result = Cartlum_SMS::send_to_cart( $cart, $message );
		if ( is_wp_error( $result ) ) {
			/* translators: %s: error */
			self::back( $cart->id, sprintf( __( 'SMS not sent: %s', 'cartlum' ), $result->get_error_message() ), 'error' );
		}
		self::back( $cart->id, __( 'SMS sent.', 'cartlum' ) );
	}

	/**
	 * Send email.
	 */
	public static function send_email() {
		$cart    = self::guard( 'cartlum_send_email' );
		$subject = Cartlum_Messages::render( self::posted( 'subject' ), $cart );
		$body    = Cartlum_Messages::render( self::posted( 'message' ), $cart );
		foreach ( array( $subject, $body ) as $part ) {
			if ( is_wp_error( $part ) ) {
				self::back( $cart->id, $part->get_error_message(), 'error' );
			}
		}
		$result = Cartlum_Email::send_to_cart( $cart, $subject, $body );
		if ( is_wp_error( $result ) ) {
			self::back( $cart->id, $result->get_error_message(), 'error' );
		}
		self::back( $cart->id, __( 'Email sent.', 'cartlum' ) );
	}

	/**
	 * Log a phone call.
	 */
	public static function log_call() {
		$cart     = self::guard( 'cartlum_log_call' );
		$outcomes = self::call_outcomes();
		$outcome  = sanitize_key( self::posted( 'outcome' ) );
		if ( ! isset( $outcomes[ $outcome ] ) ) {
			self::back( $cart->id, __( 'Choose how the call went.', 'cartlum' ), 'error' );
		}
		Cartlum_Events::add(
			$cart->id,
			'call_logged',
			array(
				'channel' => 'call',
				'message' => self::posted( 'note' ),
				'meta'    => array( 'outcome' => $outcome ),
			)
		);
		Cartlum_Carts::update( $cart->id, array( 'call_outcome' => $outcome ) );
		// A call that reached nobody is not contact.
		if ( ! in_array( $outcome, array( 'no_answer', 'wrong_number' ), true ) ) {
			Cartlum_Contact::mark_contacted( $cart );
		}
		if ( in_array( $outcome, array( 'not_interested', 'wrong_number' ), true ) && in_array( $cart->status, Cartlum_Carts::OPEN, true ) ) {
			Cartlum_Carts::update(
				$cart->id,
				array(
					'status'    => 'lost',
					'closed_at' => Cartlum_Carts::now(),
				)
			);
		}
		self::back( $cart->id, __( 'Call logged.', 'cartlum' ) );
	}

	/**
	 * WhatsApp: log, then open the chat.
	 */
	public static function whatsapp() {
		$cart = self::guard( 'cartlum_whatsapp' );
		if ( '' === $cart->phone ) {
			self::back( $cart->id, __( 'This cart has no phone number.', 'cartlum' ), 'error' );
		}
		$template = isset( $_GET['message'] ) ? sanitize_textarea_field( wp_unslash( $_GET['message'] ) ) : (string) Cartlum_Settings::get( 'whatsapp_template' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in guard().
		$message  = Cartlum_Messages::render( $template, $cart );
		if ( is_wp_error( $message ) ) {
			self::back( $cart->id, $message->get_error_message(), 'error' );
		}
		Cartlum_Events::add(
			$cart->id,
			'whatsapp_opened',
			array(
				'channel' => 'whatsapp',
				'message' => $message,
			)
		);
		Cartlum_Contact::mark_contacted( $cart );
		// External redirect on purpose: wa.me opens WhatsApp with the message filled in.
		wp_redirect( Cartlum_WhatsApp::chat_link( $cart->phone, $message ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Create (or show) the cart's coupon.
	 */
	public static function coupon() {
		$cart = self::guard( 'cartlum_coupon' );
		$code = Cartlum_Coupons::for_cart( $cart );
		if ( is_wp_error( $code ) ) {
			self::back( $cart->id, $code->get_error_message(), 'error' );
		}
		/* translators: %s: coupon code */
		self::back( $cart->id, sprintf( __( 'Coupon %s is ready. The restore link applies it automatically.', 'cartlum' ), $code ) );
	}

	/**
	 * Stop automatic messages for one cart -- e.g. after a phone call settled it.
	 */
	public static function flow_stop() {
		$cart = self::guard( 'cartlum_flow_stop' );
		Cartlum_Flow::stop( $cart );
		self::back( $cart->id, __( 'Automatic messages stopped for this cart.', 'cartlum' ) );
	}

	/**
	 * Change status by hand (mark lost / reopen).
	 */
	public static function set_status() {
		$cart   = self::guard( 'cartlum_set_status' );
		$status = sanitize_key( self::posted( 'status' ) );
		if ( ! in_array( $status, array( 'lost', 'abandoned' ), true ) ) {
			self::back( $cart->id, __( 'Unknown status.', 'cartlum' ), 'error' );
		}
		Cartlum_Carts::update(
			$cart->id,
			array(
				'status'    => $status,
				'closed_at' => 'lost' === $status ? Cartlum_Carts::now() : null,
			)
		);
		Cartlum_Events::add( $cart->id, 'status_' . $status );
		self::back( $cart->id, __( 'Status updated.', 'cartlum' ) );
	}

	/**
	 * Bulk action from the carts list. Runs on the screen's load-* hook rather than through
	 * admin-post.php: the list table's bulk dropdown is itself named "action", which is the
	 * parameter admin-post.php routes on.
	 */
	public static function bulk() {
		// phpcs:disable WordPress.Security.NonceVerification -- checked below once we know it is a bulk request.
		$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ? sanitize_key( $_REQUEST['action'] ) : '';
		if ( '' === $action && isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_key( $_REQUEST['action2'] );
		}
		if ( ! in_array( $action, array( 'delete', 'lost' ), true ) || empty( $_REQUEST['cart'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'bulk-carts' );
		$ids = array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['cart'] ) ) );
		if ( 'delete' === $action ) {
			Cartlum_Carts::delete( $ids );
		} else {
			foreach ( $ids as $id ) {
				Cartlum_Carts::update(
					$id,
					array(
						'status'    => 'lost',
						'closed_at' => Cartlum_Carts::now(),
					)
				);
			}
		}
		wp_safe_redirect(
			Cartlum_Admin::url(
				'cartlum-carts',
				/* translators: %d: number of carts */
				Cartlum_Admin::notice_args( sprintf( _n( '%d cart updated.', '%d carts updated.', count( $ids ), 'cartlum' ), count( $ids ) ) )
			)
		);
		exit;
	}

	/**
	 * Settings → SMS → "Send test".
	 */
	public static function test_sms() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_test_sms' );
		$phone  = Cartlum_Phone::normalize( self::posted( 'test_phone' ) );
		$back   = Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => 'sms' ) );
		$result = '' === $phone
			? new WP_Error( 'cartlum_phone', __( 'Enter a valid phone number.', 'cartlum' ) )
			/* translators: %s: store name */
			: Cartlum_SMS::send_raw( $phone, sprintf( __( 'Test message from Cartlum on %s. SMS is working.', 'cartlum' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
		wp_safe_redirect(
			add_query_arg(
				Cartlum_Admin::notice_args(
					/* translators: %s: error */
					is_wp_error( $result ) ? sprintf( __( 'Test failed: %s', 'cartlum' ), $result->get_error_message() ) : __( 'Test SMS sent.', 'cartlum' ),
					is_wp_error( $result ) ? 'error' : 'success'
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Export link for the carts list, keeping its status filter and search.
	 *
	 * @param string $status Status filter.
	 * @param string $search Search.
	 * @return string
	 */
	public static function export_url( $status, $search ) {
		return wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action' => 'cartlum_export',
						'status' => $status,
						's'      => $search,
					)
				),
				admin_url( 'admin-post.php' )
			),
			'cartlum_export'
		);
	}

	/**
	 * Carts as a CSV file (opens in Excel and Google Sheets).
	 */
	public static function export() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_export' );
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cartlum-carts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming the download.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- byte order mark, so Excel shows Bangla names.
		fputcsv(
			$out,
			array(
				__( 'Cart', 'cartlum' ),
				__( 'Created', 'cartlum' ),
				__( 'Status', 'cartlum' ),
				__( 'Name', 'cartlum' ),
				__( 'Phone', 'cartlum' ),
				__( 'Email', 'cartlum' ),
				__( 'Products', 'cartlum' ),
				__( 'Cart total', 'cartlum' ),
				__( 'Currency', 'cartlum' ),
				__( 'Source', 'cartlum' ),
				__( 'Times contacted', 'cartlum' ),
				__( 'Call outcome', 'cartlum' ),
				__( 'Coupon', 'cartlum' ),
				__( 'Order', 'cartlum' ),
				__( 'Order total', 'cartlum' ),
			),
			',',
			'"',
			'' // No escape character: plain RFC 4180 quoting, and PHP 8.4 wants it stated.
		);
		$page = 1;
		do {
			$result = Cartlum_Carts::query(
				array(
					'status'   => $status,
					'search'   => $search,
					'per_page' => 500,
					'page'     => $page,
					'orderby'  => 'created_at',
				)
			);
			foreach ( $result['rows'] as $cart ) {
				$row = array(
					$cart->id,
					get_date_from_gmt( $cart->created_at, 'Y-m-d H:i' ),
					wp_strip_all_tags( Cartlum_Admin::badge( $cart->status ) ),
					$cart->name,
					'' !== $cart->phone ? Cartlum_OTP::shown( $cart->phone ) : '',
					$cart->email,
					Cartlum_Messages::items_text( $cart ),
					$cart->cart_total,
					$cart->currency,
					$cart->source,
					$cart->contact_count,
					$cart->call_outcome,
					$cart->coupon_code,
					$cart->order_id ? $cart->order_id : '',
					$cart->order_id ? $cart->order_total : '',
				);
				fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), $row ), ',', '"', '' );
			}
			++$page;
		} while ( count( $result['rows'] ) === 500 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen.
		exit;
	}

	/**
	 * Names and products are typed by shoppers; a cell starting with = + - @ would run as a formula
	 * in a spreadsheet, so those get a leading apostrophe.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@\t\r]/', $value ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}
}
