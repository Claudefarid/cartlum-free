<?php
/**
 * "Order on WhatsApp" and "Call to order" buttons on product pages, and the 2 x 2 group they form
 * with "Add to cart" and "Buy now". Many customers would rather ask or order by chat or phone;
 * these put the store one tap away, with the product already named.
 *
 * WhatsApp opens wa.me in the visitor's own browser or app with the message filled in; the site
 * sends nothing anywhere.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Product_Buttons.
 */
class Cartlum_Product_Buttons {

	/**
	 * Printed once per page, whichever hook gets there first.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Whether the group wrapper is open (between the add-to-cart button hooks).
	 *
	 * @var bool
	 */
	private static $open = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'cartlum_contact_buttons', array( __CLASS__, 'shortcode' ) );
		$contacts = self::whatsapp_number() || self::call_number();
		$buy_now  = 'yes' === Cartlum_Settings::get( 'buy_now_enabled' );
		if ( ! $contacts && ! $buy_now ) {
			return;
		}
		// One group around WooCommerce's quantity and button, Buy now (priority 10) and the contact
		// buttons, so all four sit together whatever the theme does with the form.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'open' ), 1 );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'close' ), 99 );
		if ( $contacts ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'output' ), 20 );
			// Products without an add-to-cart form, such as out of stock ones.
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'output' ), 31 );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// WooCommerce's default "Add to cart" sits in the group; give it the group's language.
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 20 );
	}

	/**
	 * "Add to cart" in Cartlum's language, unless the store has its own wording.
	 *
	 * @param string $text Button text.
	 * @return string
	 */
	public static function add_to_cart_text( $text ) {
		return 'Add to cart' === $text ? __( 'Add to cart', 'cartlum' ) : $text;
	}

	/**
	 * The product button colour, or '' for the theme's own Add to cart style.
	 *
	 * @return string
	 */
	private static function uniform_color() {
		return (string) sanitize_hex_color( (string) Cartlum_Settings::get( 'product_button_color' ) );
	}

	/**
	 * Opens the group.
	 */
	public static function open() {
		if ( self::$open ) {
			return;
		}
		self::$open = true;
		echo '<div class="cartlum-actions' . ( self::uniform_color() ? ' cartlum-actions--uniform' : '' ) . '">';
	}

	/**
	 * Closes the group.
	 */
	public static function close() {
		if ( self::$open ) {
			self::$open = false;
			echo '</div>';
		}
	}

	/**
	 * The store's WhatsApp number in international digits, or '' when the button is off.
	 *
	 * @return string
	 */
	private static function whatsapp_number() {
		return 'yes' === Cartlum_Settings::get( 'whatsapp_button' ) ? Cartlum_Phone::normalize( (string) Cartlum_Settings::get( 'whatsapp_number' ) ) : '';
	}

	/**
	 * The store's phone number in international digits, or '' when the button is off.
	 *
	 * @return string
	 */
	private static function call_number() {
		return 'yes' === Cartlum_Settings::get( 'call_button' ) ? Cartlum_Phone::normalize( (string) Cartlum_Settings::get( 'call_number' ) ) : '';
	}

	/**
	 * Prints the buttons once, on product pages.
	 */
	public static function output() {
		global $product;
		if ( self::$printed || ! is_product() ) {
			return;
		}
		// The summary slot is only for products without an add-to-cart form; the others get the
		// buttons inside the group, even where a theme runs the summary hook before the form.
		if ( 'woocommerce_single_product_summary' === current_filter() && $product instanceof WC_Product && $product->is_purchasable() && $product->is_in_stock() ) {
			return;
		}
		echo self::html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built.
	}

	/**
	 * [cartlum_contact_buttons] for placing them by hand, e.g. in an Elementor product template.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( self::$printed ) {
			return '';
		}
		self::assets( true );
		return self::html();
	}

	/**
	 * The buttons for the current product.
	 *
	 * @return string
	 */
	private static function html() {
		global $product;
		$wa   = self::whatsapp_number();
		$call = self::call_number();
		if ( ( ! $wa && ! $call ) || ! $product instanceof WC_Product ) {
			return '';
		}
		self::$printed = true;

		$out = '<div class="cartlum-contact' . ( $wa && $call ? ' cartlum-contact--two' : '' ) . ( self::uniform_color() ? ' cartlum-contact--uniform' : '' ) . '">';
		if ( $wa ) {
			$message = self::message( $product );
			$out    .= sprintf(
				'<a class="cartlum-contact__btn cartlum-contact__btn--wa" href="%1$s" target="_blank" rel="noopener" data-cartlum-wa="%2$s" data-template="%3$s" data-name="%4$s" data-price="%5$s" data-link="%6$s">%7$s<span>%8$s</span></a>',
				esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $message ) ),
				esc_attr( $wa ),
				esc_attr( self::template() ),
				esc_attr( $product->get_name() ),
				esc_attr( self::price_text( $product->get_price() ) ),
				esc_url( $product->get_permalink() ),
				'<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6a2.5 2.5 0 0 0 1.7-1.2 2 2 0 0 0 .1-1.2c0-.1-.2-.2-.5-.3z"/></svg>',
				esc_html( self::text( 'whatsapp_button_text', __( 'Order on WhatsApp', 'cartlum' ) ) )
			);
		}
		if ( $call ) {
			$out .= sprintf(
				'<a class="cartlum-contact__btn cartlum-contact__btn--call" href="%1$s">%2$s<span>%3$s</span></a>',
				esc_url( 'tel:+' . $call ),
				'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
				esc_html( self::text( 'call_button_text', __( 'Call to order', 'cartlum' ) ) )
			);
		}
		return $out . '</div>';
	}

	/**
	 * A text setting, or its default when empty.
	 *
	 * @param string $key      Setting.
	 * @param string $fallback Default.
	 * @return string
	 */
	private static function text( $key, $fallback ) {
		$text = trim( (string) Cartlum_Settings::get( $key ) );
		return '' !== $text ? $text : $fallback;
	}

	/**
	 * The WhatsApp message template, with {product} {quantity} {price} {link}.
	 *
	 * @return string
	 */
	private static function template() {
		return self::text( 'whatsapp_order_message', __( "Hi! I'd like to order:\n{product} × {quantity}\nPrice: {price}\n{link}", 'cartlum' ) );
	}

	/**
	 * The message as first printed (one item, the product's own price). The script updates it with
	 * the chosen colour or size and quantity when the button is tapped.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function message( $product ) {
		return strtr(
			self::template(),
			array(
				'{product}'  => $product->get_name(),
				'{quantity}' => '1',
				'{price}'    => self::price_text( $product->get_price() ),
				'{link}'     => $product->get_permalink(),
			)
		);
	}

	/**
	 * A price as plain text for a chat message, e.g. "৳ 1,350.00".
	 *
	 * @param float|string $amount Amount.
	 * @return string
	 */
	private static function price_text( $amount ) {
		$text = html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
		return trim( str_replace( "\xc2\xa0", ' ', $text ) );
	}

	/**
	 * Styles and the small script, on product pages (or wherever the shortcode is used).
	 *
	 * @param bool $force Load outside product pages (shortcode).
	 */
	public static function assets( $force = false ) {
		if ( ! $force && ! is_product() && ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}
		if ( wp_style_is( 'cartlum-product', 'enqueued' ) ) {
			return;
		}
		$css   = Cartlum_Checkout::accent_css( 'body' );
		$color = self::uniform_color();
		if ( $color ) {
			$css .= 'body{--clx-btn:' . $color . ';--clx-on-btn:' . Cartlum_Checkout::ink_for( $color ) . ';}';
		}
		wp_enqueue_style( 'cartlum-product', CARTLUM_URL . 'assets/css/product.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-product', $css );
		if ( $force || is_product() ) {
			wp_enqueue_script( 'cartlum-contact', CARTLUM_URL . 'assets/js/product-buttons.js', array(), CARTLUM_VERSION, true );
		}
	}
}
