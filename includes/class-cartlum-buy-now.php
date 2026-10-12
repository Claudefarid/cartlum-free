<?php
/**
 * "Buy now": a button next to "Add to cart" that adds the product and goes straight to checkout.
 *
 * On the product page it is a second submit button inside WooCommerce's own add-to-cart form, so the
 * chosen variation (colour, size) and quantity travel with it and WooCommerce does the adding. On
 * shop pages it is a link for simple products. Works with any checkout page.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Buy_Now.
 */
class Cartlum_Buy_Now {

	const PARAM = 'cartlum_buy_now';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'buy_now_enabled' ) ) {
			return;
		}
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'product_button' ) );
		if ( 'yes' === Cartlum_Settings::get( 'buy_now_loop' ) ) {
			// Next to WooCommerce's own Add to cart, wherever it is printed. Cards that a theme or
			// plugin gives its own buttons (e.g. with a colour picker) are left alone.
			add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'loop_link' ), 20, 2 );
		}
		// Runs before the product is added: "only this product" empties the cart first.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'maybe_empty_cart' ), 1 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect' ), 99 );
		add_filter( 'wc_add_to_cart_message_html', array( __CLASS__, 'no_message' ), 99 );
	}

	/**
	 * Whether this request came from a Buy now button.
	 *
	 * @return bool
	 */
	private static function requested() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- like WooCommerce's own add-to-cart links, adding to a cart needs no nonce.
		return ! empty( $_REQUEST[ self::PARAM ] );
	}

	/**
	 * The button's text.
	 *
	 * @return string
	 */
	private static function label() {
		$text = trim( (string) Cartlum_Settings::get( 'buy_now_text' ) );
		return '' !== $text ? $text : __( 'Buy now', 'cartlum' );
	}

	/**
	 * Product page: a second submit button in the add-to-cart form. It posts the same form (with the
	 * chosen variation and quantity) to the product page plus the Buy now flag.
	 */
	public static function product_button() {
		global $product;
		if ( ! $product instanceof WC_Product || $product->is_type( 'external' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		// Simple and grouped products name the product on the clicked button; variable products in
		// a hidden field, which the form already has.
		$name = $product->is_type( 'variable' ) ? '' : sprintf( ' name="add-to-cart" value="%d"', $product->get_id() );
		// "class" comes before "type" on purpose: WooCommerce 8.x's Add to Cart Form block replaces every
		// `<button type="submit"…>` in the form with a copy of the first one, which turned this button
		// into a second "Add to cart".
		printf(
			'<button class="single_add_to_cart_button button alt cartlum-buy-now" type="submit"%1$s formaction="%2$s">%3$s</button>',
			$name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from an integer above.
			esc_url( add_query_arg( self::PARAM, '1', $product->get_permalink() ) ),
			esc_html( self::label() )
		);
	}

	/**
	 * Shop pages: a Buy now link after the Add to cart link of simple products that can be bought
	 * as they are.
	 *
	 * @param string     $html    Add to cart link.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function loop_link( $html, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return $html;
		}
		// Product grids also show up on pages that are not shop pages (home page, landing pages):
		// bring the button styles along.
		if ( ! wp_style_is( 'cartlum-product', 'enqueued' ) && class_exists( 'Cartlum_Product_Buttons' ) ) {
			Cartlum_Product_Buttons::assets( true );
		}
		// Dress it like the Add to cart beside it: the theme's own button classes, minus the ones
		// that would make it an add-to-cart button.
		$classes = array( 'button' );
		if ( preg_match( '/class="([^"]*)"/', $html, $m ) ) {
			foreach ( preg_split( '/\s+/', $m[1] ) as $class ) {
				if ( '' !== $class && ! preg_match( '/^(add_to_cart_button|ajax_add_to_cart|product_type_|added|loading)/', $class ) ) {
					$classes[] = $class;
				}
			}
		}
		ob_start();
		self::loop_button( $product, array_unique( $classes ) );
		return $html . ob_get_clean();
	}

	/**
	 * The Buy now link for a shop-page card.
	 *
	 * @param WC_Product $product Product.
	 * @param string[]   $classes Button classes.
	 */
	public static function loop_button( $product, array $classes = array( 'button', 'alt' ) ) {
		$url = add_query_arg(
			array(
				'add-to-cart' => $product->get_id(),
				self::PARAM   => '1',
			),
			home_url( '/' )
		);
		printf(
			'<a href="%1$s" class="%2$s cartlum-buy-now cartlum-buy-now--loop" rel="nofollow">%3$s</a>',
			esc_url( $url ),
			esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ),
			esc_html( self::label() )
		);
	}

	/**
	 * "Only this product": start from an empty cart.
	 *
	 * @param bool $passed Whether the product may be added.
	 * @return bool
	 */
	public static function maybe_empty_cart( $passed ) {
		if ( $passed && self::requested() && 'only' === Cartlum_Settings::get( 'buy_now_mode' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return $passed;
	}

	/**
	 * After adding: to the checkout.
	 *
	 * @param string|false $url Redirect.
	 * @return string|false
	 */
	public static function redirect( $url ) {
		return self::requested() ? wc_get_checkout_url() : $url;
	}

	/**
	 * No "added to your cart" message on the way to checkout.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	public static function no_message( $message ) {
		return self::requested() ? '' : $message;
	}
}
