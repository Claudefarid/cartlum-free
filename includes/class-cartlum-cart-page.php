<?php
/**
 * Cart page: where "Add to cart" leads, skipping the cart page, a mobile number field on it, and
 * free delivery from an amount, with a bar on the cart and checkout showing how much more is needed.
 * Works on the classic cart and checkout (and checkout builders that keep their hooks, such as
 * CartFlows) and on the block cart and checkout.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Cart_Page.
 */
class Cartlum_Cart_Page {

	/**
	 * Hooks.
	 */
	public static function init() {
		$after = (string) Cartlum_Settings::get( 'cart_after_add' );
		if ( in_array( $after, array( 'stay', 'cart', 'checkout' ), true ) ) {
			// WooCommerce's own "Redirect to the cart page after successful addition", which its
			// shop-page script and the product form both follow.
			add_filter( 'pre_option_woocommerce_cart_redirect_after_add', array( __CLASS__, 'redirect_option' ) );
		}
		if ( 'checkout' === $after ) {
			// Buy now (priority 99) still has the last word.
			add_filter( 'woocommerce_add_to_cart_redirect', 'wc_get_checkout_url', 50 );
			add_filter( 'woocommerce_get_script_data', array( __CLASS__, 'script_data' ), 10, 2 );
			add_filter( 'wc_add_to_cart_message_html', '__return_empty_string', 99 );
		}
		if ( 'yes' === Cartlum_Settings::get( 'cart_skip' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'skip' ) );
		}
		if ( self::phone_on() ) {
			add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'print_phone' ), 5 );
			add_action( 'wc_ajax_cartlum_cart_phone', array( __CLASS__, 'ajax_phone' ) );
		}
		if ( self::free_amount() > 0 ) {
			add_filter( 'woocommerce_package_rates', array( __CLASS__, 'free_delivery' ), 100 );
			// Inside the cart form, so it is redrawn when quantities change.
			add_action( 'woocommerce_before_cart_table', array( __CLASS__, 'print_bar' ) );
			add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'print_bar' ), 5 );
			add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'bar_fragment' ) );
		}
		if ( self::phone_on() || self::free_amount() > 0 ) {
			add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'block_cart' ) );
			add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'block_checkout' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}
		// Shipping rates are kept per cart: a new amount must not wait for the cart to change.
		add_action( 'update_option_' . Cartlum_Settings::OPTION, array( __CLASS__, 'amount_changed' ), 10, 2 );
	}

	/**
	 * Whether the cart page asks for the mobile number. Saving carts must be on.
	 *
	 * @return bool
	 */
	private static function phone_on() {
		return 'yes' === Cartlum_Settings::get( 'cart_phone' ) && 'yes' === Cartlum_Settings::get( 'enabled' );
	}

	/**
	 * Free delivery from this amount; 0 = off.
	 *
	 * @return float
	 */
	public static function free_amount() {
		return max( 0, (float) Cartlum_Settings::get( 'cart_free_amount' ) );
	}

	/**
	 * Redirect after adding: yes for the cart or checkout, no to stay on the page. WooCommerce's
	 * settings screen keeps showing the store's own choice.
	 *
	 * @param mixed $pre Value so far (false = read the option).
	 * @return mixed
	 */
	public static function redirect_option( $pre ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $pre;
		}
		return 'stay' === Cartlum_Settings::get( 'cart_after_add' ) ? 'no' : 'yes';
	}

	/**
	 * Shop pages that add without reloading send the shopper to the checkout, not the cart.
	 *
	 * @param array|false $params Script data.
	 * @param string      $handle Script.
	 * @return array|false
	 */
	public static function script_data( $params, $handle ) {
		if ( 'wc-add-to-cart' === $handle && is_array( $params ) ) {
			$params['cart_url'] = wc_get_checkout_url();
		}
		return $params;
	}

	/**
	 * The cart page with products in it: to the checkout. Requests with anything in the address
	 * (WooCommerce's remove and undo links, page builder previews, add-ons) are left alone.
	 */
	public static function skip() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether the address has a query.
		if ( 'get' !== $method || ! empty( $_GET ) || ! is_cart() || is_checkout() || is_customize_preview() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * The mobile number field above "Proceed to checkout".
	 */
	public static function print_phone() {
		echo self::phone_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in phone_html().
	}

	/**
	 * The mobile number field, with the number the store already has.
	 *
	 * @return string
	 */
	private static function phone_html() {
		$phone = WC()->customer ? (string) WC()->customer->get_billing_phone() : '';
		return sprintf(
			'<div class="cartlum-cart-phone"><label for="cartlum-cart-phone">%1$s</label><input type="tel" id="cartlum-cart-phone" inputmode="tel" autocomplete="tel" value="%2$s"><p class="cartlum-cart-phone__note" aria-live="polite">%3$s</p></div>',
			esc_html__( 'Mobile number', 'cartlum' ),
			esc_attr( $phone ),
			esc_html__( 'We save your cart and fill in this number at checkout.', 'cartlum' )
		);
	}

	/**
	 * AJAX: save the cart with the number typed on the cart page.
	 */
	public static function ajax_phone() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified just below.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cartlum_cart_phone' ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save right now. Please try again.', 'cartlum' ) ), 403 );
		}
		$phone = Cartlum_OTP::mobile( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid mobile number.', 'cartlum' ) ) );
		}
		$id = Cartlum_Capture::save(
			array(
				'phone'  => $phone,
				'source' => 'cart',
			)
		);
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save right now. Please try again.', 'cartlum' ) ) );
		}
		// Fill it in at checkout.
		if ( WC()->customer ) {
			WC()->customer->set_billing_phone( Cartlum_OTP::shown( $phone ) );
			WC()->customer->save();
		}
		wp_send_json_success();
	}

	/**
	 * What the products in the cart add up to, as WooCommerce's free shipping counts it: the
	 * subtotal shown to the customer, after discounts.
	 *
	 * @return float
	 */
	private static function products_total() {
		$cart  = WC()->cart;
		$total = (float) $cart->get_displayed_subtotal() - (float) $cart->get_discount_total();
		if ( $cart->display_prices_including_tax() ) {
			$total -= (float) $cart->get_discount_tax();
		}
		return round( $total, wc_get_price_decimals() );
	}

	/**
	 * From the amount on, every delivery option costs nothing.
	 *
	 * @param array $rates Shipping rates.
	 * @return array
	 */
	public static function free_delivery( $rates ) {
		if ( ! WC()->cart || self::products_total() < self::free_amount() ) {
			return $rates;
		}
		foreach ( $rates as $rate ) {
			$rate->set_cost( 0 );
			$rate->set_taxes( array_map( '__return_zero', (array) $rate->get_taxes() ) );
		}
		return $rates;
	}

	/**
	 * The free-delivery bar. An empty cart, or one with nothing to deliver, gets a hidden one, so
	 * the checkout has something to update.
	 *
	 * @param bool $block For the block cart or checkout, which redraw it themselves (cart-page.js).
	 * @return string
	 */
	public static function bar_html( $block = false ) {
		$attr = $block ? ' data-cartlum-block' : '';
		if ( ! WC()->cart || WC()->cart->is_empty() || ! WC()->cart->needs_shipping() ) {
			return '<div class="cartlum-free-bar" role="status"' . $attr . ' hidden></div>';
		}
		$amount = self::free_amount();
		$total  = self::products_total();
		$left   = $amount - $total;
		/* translators: %s: amount still needed, e.g. ৳300 */
		$text = $left > 0 ? sprintf( __( 'Add %s more for free delivery.', 'cartlum' ), wc_price( $left ) ) : esc_html__( 'You get free delivery.', 'cartlum' );
		return sprintf(
			'<div class="cartlum-free-bar%1$s" role="status"%2$s><p class="cartlum-free-bar__text">%3$s</p><span class="cartlum-free-bar__track" aria-hidden="true"><span class="cartlum-free-bar__fill" style="width:%4$s%%"></span></span></div>',
			$left > 0 ? '' : ' is-done',
			$attr,
			wp_kses_post( $text ),
			esc_attr( (string) round( min( 100, max( 0, $total / $amount * 100 ) ), 1 ) )
		);
	}

	/**
	 * The bar on the classic cart and checkout.
	 */
	public static function print_bar() {
		echo self::bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar_html().
	}

	/**
	 * The checkout redraws the bar each time the order totals change.
	 *
	 * @param array $fragments Selector => HTML.
	 * @return array
	 */
	public static function bar_fragment( $fragments ) {
		$fragments['.cartlum-free-bar'] = self::bar_html();
		return $fragments;
	}

	/**
	 * Block cart: the bar and the mobile number field above it.
	 *
	 * @param string $html Block HTML.
	 * @return string
	 */
	public static function block_cart( $html ) {
		$top = self::free_amount() > 0 ? self::bar_html( true ) : '';
		if ( self::phone_on() && WC()->cart && ! WC()->cart->is_empty() ) {
			$top .= self::phone_html();
		}
		return $top . $html;
	}

	/**
	 * Block checkout: the bar above it.
	 *
	 * @param string $html Block HTML.
	 * @return string
	 */
	public static function block_checkout( $html ) {
		return ( self::free_amount() > 0 ? self::bar_html( true ) : '' ) . $html;
	}

	/**
	 * Styles and script on the cart and checkout.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) || is_order_received_page() ) {
			return;
		}
		wp_enqueue_style( 'cartlum-cart-page', CARTLUM_URL . 'assets/css/cart-page.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-cart-page', Cartlum_Checkout::accent_css( '.cartlum-free-bar' ) );
		wp_enqueue_script( 'cartlum-cart-page', CARTLUM_URL . 'assets/js/cart-page.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-cart-page',
			'cartlumCartPage',
			array(
				'url'     => WC_AJAX::get_endpoint( 'cartlum_cart_phone' ),
				'nonce'   => wp_create_nonce( 'cartlum_cart_phone' ),
				'saved'   => __( 'Saved. The checkout has your number filled in.', 'cartlum' ),
				'error'   => __( 'Could not save right now. Please try again.', 'cartlum' ),
				'amount'  => self::free_amount(),
				'inclTax' => WC()->cart && WC()->cart->display_prices_including_tax(),
				/* translators: %s: amount still needed, e.g. ৳300 */
				'left'    => __( 'Add %s more for free delivery.', 'cartlum' ),
				'done'    => __( 'You get free delivery.', 'cartlum' ),
			)
		);
	}

	/**
	 * A new free-delivery amount: work out delivery charges again.
	 *
	 * @param mixed $old Old settings.
	 * @param mixed $new New settings.
	 */
	public static function amount_changed( $old, $new ) {
		$was = is_array( $old ) ? (float) ( $old['cart_free_amount'] ?? 0 ) : 0;
		$now = is_array( $new ) ? (float) ( $new['cart_free_amount'] ?? 0 ) : 0;
		if ( $was !== $now && class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}
}
