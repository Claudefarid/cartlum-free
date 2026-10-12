<?php
/**
 * The one-tap links in every message: restore the cart, and opt out.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Restore.
 */
class Cartlum_Restore {

	/**
	 * Hooks.
	 */
	public static function init() {
		// wp_loaded: the cart and session exist, nothing has been sent to the browser yet.
		add_action( 'wp_loaded', array( __CLASS__, 'handle' ), 30 );
	}

	/**
	 * Short restore link, kept short because it goes into SMS.
	 *
	 * @param object $cart Row.
	 * @return string
	 */
	public static function link( $cart ) {
		return add_query_arg( 'cartlum', $cart->token, home_url( '/' ) );
	}

	/**
	 * Opt-out link.
	 *
	 * @param object $cart Row.
	 * @return string
	 */
	public static function optout_link( $cart ) {
		return add_query_arg( 'cartlum_optout', $cart->token, home_url( '/' ) );
	}

	/**
	 * Handles both links.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		if ( ( isset( $_GET['cartlum_optout'] ) || isset( $_GET['cartlum'] ) ) && self::is_preview_bot() ) {
			return; // A preview fetch changes nothing; the page itself still loads for the preview.
		}
		if ( isset( $_GET['cartlum_optout'] ) ) {
			self::optout( sanitize_text_field( wp_unslash( $_GET['cartlum_optout'] ) ) );
		}
		if ( isset( $_GET['cartlum'] ) && ! is_admin() ) {
			self::restore( sanitize_text_field( wp_unslash( $_GET['cartlum'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether the request comes from a link preview (WhatsApp, Facebook, Telegram …) rather than a
	 * person. Those fetch links in messages on their own; counting that as a click would mark the
	 * cart as contacted, and on the unsubscribe link it would unsubscribe the customer.
	 *
	 * @return bool
	 */
	private static function is_preview_bot() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		foreach ( array( 'whatsapp/', 'facebookexternalhit', 'facebot', 'telegrambot', 'twitterbot', 'slackbot', 'discordbot', 'linkedinbot', 'skypeuripreview', 'googlebot', 'bingbot', 'applebot' ) as $bot ) {
			if ( false !== strpos( $agent, $bot ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Refills the cart and sends the customer to checkout.
	 *
	 * @param string $token Token.
	 */
	private static function restore( $token ) {
		$cart = Cartlum_Carts::get_by_token( $token );
		if ( ! $cart || ! WC()->cart ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		// Already ordered: don't refill a cart they have bought.
		if ( in_array( $cart->status, array( 'recovered', 'converted' ), true ) ) {
			wc_add_notice( __( 'Good news — this order has already been placed.', 'cartlum' ), 'notice' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		WC()->cart->empty_cart();
		$skipped = 0;
		foreach ( Cartlum_Carts::items( $cart ) as $item ) {
			$product_id = (int) $item['product_id'];
			$product    = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $product_id );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				++$skipped;
				continue;
			}
			WC()->cart->add_to_cart( $product_id, max( 1, (int) $item['quantity'] ), (int) $item['variation_id'], (array) ( $item['variation'] ?? array() ) );
		}
		if ( $skipped ) {
			wc_add_notice( __( 'Some items in your saved cart are no longer available.', 'cartlum' ), 'notice' );
		}

		if ( $cart->coupon_code && ! WC()->cart->has_discount( $cart->coupon_code ) ) {
			WC()->cart->apply_coupon( $cart->coupon_code );
		}

		// Pre-fill what we already know, so checkout is quicker.
		if ( WC()->customer ) {
			if ( $cart->phone && ! WC()->customer->get_billing_phone() ) {
				WC()->customer->set_billing_phone( '+' . $cart->phone );
			}
			if ( $cart->email && ! WC()->customer->get_billing_email() ) {
				WC()->customer->set_billing_email( $cart->email );
			}
			if ( $cart->name && ! WC()->customer->get_billing_first_name() ) {
				WC()->customer->set_billing_first_name( $cart->name );
			}
			WC()->customer->save();
		}

		WC()->session->set( Cartlum_Capture::SESSION_KEY, (int) $cart->id );

		// Following our link is a response to our contact, so an order from here counts as recovered.
		Cartlum_Carts::update(
			$cart->id,
			array(
				'status'        => 'active',
				'contact_count' => max( 1, (int) $cart->contact_count ),
				'updated_at'    => Cartlum_Carts::now(),
			)
		);
		Cartlum_Events::add( $cart->id, 'link_clicked', array( 'user_id' => 0 ) );

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Stops all further messages for this cart's phone and email.
	 *
	 * @param string $token Token.
	 */
	private static function optout( $token ) {
		$cart = Cartlum_Carts::get_by_token( $token );
		if ( $cart ) {
			self::add_optout( $cart->phone, $cart->email );
			if ( in_array( $cart->status, Cartlum_Carts::OPEN, true ) ) {
				Cartlum_Carts::update(
					$cart->id,
					array(
						'status'    => 'optout',
						'closed_at' => Cartlum_Carts::now(),
					)
				);
			}
			Cartlum_Events::add( $cart->id, 'optout', array( 'user_id' => 0 ) );
		}
		wp_die(
			esc_html__( 'You will not receive any more reminders about this cart.', 'cartlum' ),
			esc_html__( 'Unsubscribed', 'cartlum' ),
			array(
				'response'  => 200,
				'link_url'  => esc_url( home_url( '/' ) ),
				'link_text' => esc_html__( 'Back to the shop', 'cartlum' ),
			)
		);
	}

	/**
	 * Remembers an opt-out. Stored hashed, so the list itself is not a list of phone numbers.
	 *
	 * @param string $phone Normalized phone.
	 * @param string $email Email.
	 */
	public static function add_optout( $phone, $email ) {
		$list = get_option( 'cartlum_optouts', array() );
		$list = is_array( $list ) ? $list : array();
		foreach ( array( $phone, strtolower( (string) $email ) ) as $value ) {
			if ( '' !== (string) $value ) {
				$list[ wp_hash( $value ) ] = 1;
			}
		}
		update_option( 'cartlum_optouts', $list, false );
	}

	/**
	 * Whether a phone or email has opted out.
	 *
	 * @param string $value Normalized phone or email.
	 * @return bool
	 */
	public static function is_opted_out( $value ) {
		if ( '' === (string) $value ) {
			return false;
		}
		$list = get_option( 'cartlum_optouts', array() );
		return is_array( $list ) && isset( $list[ wp_hash( strtolower( (string) $value ) ) ] );
	}
}
