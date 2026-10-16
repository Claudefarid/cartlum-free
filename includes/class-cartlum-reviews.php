<?php
/**
 * Review requests: some days after an order (or after delivery), the customer gets one SMS with a
 * private link to a short form — stars and an optional comment for each product, no login. Each
 * answer is saved as a WooCommerce product review, marked "verified owner", so it shows on the
 * product page with the store's other reviews.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Reviews.
 */
class Cartlum_Reviews {

	const PARAM = 'cartlum_review';

	/**
	 * Hooks.
	 */
	public static function init() {
		// The form and the job stay registered when requests are off: links already sent keep
		// working, and a job queued before the switch was turned off simply does nothing.
		add_action( 'cartlum_review_request', array( __CLASS__, 'send' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_form' ), 1 );
		if ( 'yes' !== Cartlum_Settings::get( 'review_enabled' ) ) {
			return;
		}
		if ( 'delivered' === Cartlum_Settings::get( 'review_trigger' ) ) {
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule' ) );
			add_action( 'cartlum_order_courier_status', array( __CLASS__, 'on_courier_status' ), 10, 2 );
			add_action( 'stdf_webhook_delivery_status_updated', array( __CLASS__, 'on_courier_status' ), 10, 2 );
		} else {
			foreach ( array( 'pending_to_processing', 'pending_to_on-hold', 'failed_to_processing', 'failed_to_on-hold' ) as $transition ) {
				add_action( 'woocommerce_order_status_' . $transition, array( __CLASS__, 'schedule' ) );
			}
		}
	}

	/**
	 * Default request text.
	 *
	 * @return string
	 */
	public static function default_text() {
		return __( '{name}, how do you like your order from {store}? Please leave a quick review: {review_link}', 'cartlum' );
	}

	/**
	 * A courier reported the parcel delivered.
	 *
	 * @param int    $order_id Order.
	 * @param string $status   Courier status.
	 */
	public static function on_courier_status( $order_id, $status ) {
		if ( 'delivered' === $status ) {
			self::schedule( (int) $order_id );
		}
	}

	/**
	 * Queues the request for an order, once.
	 *
	 * @param int $order_id Order.
	 */
	public static function schedule( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_cartlum_review_queued' ) ) {
			return;
		}
		$days = max( 0, (int) Cartlum_Settings::get( 'review_delay_days' ) );
		$at   = time() + $days * DAY_IN_SECONDS;
		$order->update_meta_data( '_cartlum_review_queued', $at );
		$order->save_meta_data();
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $at, 'cartlum_review_request', array( (int) $order_id ), 'cartlum' );
		}
	}

	/**
	 * Whether an order may still get a request: not cancelled, refunded or failed, and the
	 * customer has not answered yet.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function wants_request( $order ) {
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed', 'pending', 'checkout-draft', 'trash' ), true ) ) {
			return false;
		}
		return ! $order->get_meta( '_cartlum_review_sent' ) && ! $order->get_meta( '_cartlum_reviewed' );
	}

	/**
	 * Sends the request (Action Scheduler job), outside quiet hours.
	 *
	 * @param int $order_id Order.
	 */
	public static function send( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== Cartlum_Settings::get( 'review_enabled' ) || ! self::wants_request( $order ) ) {
			return;
		}
		$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
		if ( '' === $phone ) {
			return;
		}
		$resume = Cartlum_Flow::quiet_until();
		if ( $resume && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $resume, 'cartlum_review_request', array( (int) $order_id ), 'cartlum' );
			return;
		}
		$template = trim( (string) Cartlum_Settings::get( 'review_sms_text' ) );
		$message  = Cartlum_Order_SMS::render( '' !== $template ? $template : self::default_text(), $order );
		$order->update_meta_data( '_cartlum_review_sent', time() );
		$order->save_meta_data();
		$result = Cartlum_SMS::send_raw( $phone, $message );
		if ( is_wp_error( $result ) ) {
			/* translators: %s: error */
			$order->add_order_note( sprintf( __( 'Cartlum: the review request SMS could not be sent: %s', 'cartlum' ), $result->get_error_message() ) );
			return;
		}
		/* translators: %s: phone number */
		$order->add_order_note( sprintf( __( 'Cartlum: review request SMS sent to %s.', 'cartlum' ), Cartlum_Phone::display( $phone ) ) );
	}

	/**
	 * The order's private review link (made on first use).
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function link( $order ) {
		$token = (string) $order->get_meta( '_cartlum_review_token' );
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false );
			$order->update_meta_data( '_cartlum_review_token', $token );
			$order->save_meta_data();
		}
		return add_query_arg( self::PARAM, $token, home_url( '/' ) );
	}

	/**
	 * The order a review link belongs to.
	 *
	 * @param string $token Token from the link.
	 * @return WC_Order|null
	 */
	public static function order_for( $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'type'       => 'shop_order',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed lookup when a customer opens their link.
					array(
						'key'   => '_cartlum_review_token',
						'value' => $token,
					),
				),
			)
		);
		return $orders ? $orders[0] : null;
	}

	/**
	 * The products in an order that can be reviewed (variations as their parent), once each.
	 *
	 * @param WC_Order $order Order.
	 * @return WC_Product[] product id => product
	 */
	public static function products( $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			$p  = wc_get_product( $id );
			if ( $p && comments_open( $id ) ) {
				$out[ $id ] = $p;
			}
		}
		return $out;
	}

	/**
	 * Shows the review form for a link, and saves the answers.
	 */
	public static function maybe_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the private token in the link is what authorises; saving checks a nonce.
		$token = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : '';
		if ( '' === $token ) {
			return;
		}
		// A private, per-order page: never stored by page caches (LiteSpeed Cache keeps query-string URLs).
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the shared constant page caches read.
		}
		do_action( 'litespeed_control_set_nocache', 'cartlum review form' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		$order = self::order_for( $token );
		$state = 'form';
		$error = '';
		if ( ! $order ) {
			$state = 'invalid';
		} elseif ( $order->get_meta( '_cartlum_reviewed' ) ) {
			$state = 'done';
		} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$error = self::save( $order, $token );
			$state = '' === $error ? 'thanks' : 'form';
		}
		$products = $order ? self::products( $order ) : array();
		if ( 'form' === $state && ! $products ) {
			$state = 'invalid';
		}
		wp_enqueue_style( 'cartlum-review', CARTLUM_URL . 'assets/css/review.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-review', Cartlum_Checkout::accent_css( '.clr-page' ) );
		status_header( 'invalid' === $state ? 404 : 200 );
		$args = array(
			'order'    => $order,
			'products' => $products,
			'state'    => $state,
			'error'    => $error,
			'token'    => $token,
		);
		include CARTLUM_DIR . 'templates/review/form.php';
		exit;
	}

	/**
	 * Saves the submitted reviews.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $token Token.
	 * @return string Error to show, or '' when saved.
	 */
	private static function save( $order, $token ) {
		if ( ! isset( $_POST['cartlum_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartlum_review_nonce'] ) ), 'cartlum_review_' . $token ) ) {
			return __( 'This page was open too long. Please try again.', 'cartlum' );
		}
		if ( ! empty( $_POST['cartlum_website'] ) ) {
			return __( 'Please try again.', 'cartlum' ); // Honeypot: only bots fill it.
		}
		$ratings  = isset( $_POST['cartlum_rating'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['cartlum_rating'] ) ) : array();
		$texts    = isset( $_POST['cartlum_text'] ) ? array_map( 'sanitize_textarea_field', (array) wp_unslash( $_POST['cartlum_text'] ) ) : array();
		$name     = isset( $_POST['cartlum_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cartlum_name'] ) ) : '';
		$name     = '' !== $name ? mb_substr( $name, 0, 60 ) : Cartlum_Messages::safe_name( $order->get_billing_first_name() );
		$products = self::products( $order );
		$saved    = array();
		$approve  = 'yes' === Cartlum_Settings::get( 'review_auto_approve' ) ? 1 : 0;
		foreach ( $products as $id => $product ) {
			$stars = isset( $ratings[ $id ] ) ? (int) $ratings[ $id ] : 0;
			if ( $stars < 1 || $stars > 5 ) {
				continue;
			}
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $id,
					'comment_author'       => '' !== $name ? $name : __( 'Customer', 'cartlum' ),
					'comment_author_email' => (string) $order->get_billing_email(),
					'comment_content'      => mb_substr( isset( $texts[ $id ] ) ? trim( $texts[ $id ] ) : '', 0, 2000 ),
					'comment_type'         => 'review',
					'comment_approved'     => $approve,
					'user_id'              => (int) $order->get_customer_id(),
					'comment_meta'         => array(
						'rating'           => $stars,
						'verified'         => 1,
						'_cartlum_order'   => $order->get_id(),
					),
				)
			);
			if ( $comment_id ) {
				$saved[ $id ] = $stars;
				if ( class_exists( 'WC_Comments' ) ) {
					WC_Comments::clear_transients( $id );
				}
			}
		}
		if ( ! $saved ) {
			return __( 'Please choose stars for at least one product.', 'cartlum' );
		}
		$order->update_meta_data( '_cartlum_reviewed', time() );
		$order->save_meta_data();
		$parts = array();
		foreach ( $saved as $id => $stars ) {
			$parts[] = $products[ $id ]->get_name() . ' ' . str_repeat( '★', $stars );
		}
		$order->add_order_note(
			/* translators: 1: products and stars, 2: "waiting for approval" or "published" */
			sprintf( __( 'Cartlum: customer review received: %1$s (%2$s).', 'cartlum' ), implode( ', ', $parts ), $approve ? __( 'published', 'cartlum' ) : __( 'waiting for approval in Products → Reviews', 'cartlum' ) )
		);
		/**
		 * A customer answered a review request.
		 *
		 * @param WC_Order $order Order.
		 * @param array    $saved product id => stars.
		 */
		do_action( 'cartlum_review_received', $order, $saved );
		return '';
	}
}
