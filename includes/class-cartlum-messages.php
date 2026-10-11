<?php
/**
 * Fills {placeholders} in message templates.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Messages.
 */
class Cartlum_Messages {

	/**
	 * Placeholders and what they mean, for the settings screen.
	 *
	 * @return array
	 */
	public static function placeholders() {
		$list = array(
			'{name}'        => __( 'Customer first name', 'cartlum' ),
			'{store}'       => __( 'Store name', 'cartlum' ),
			'{items}'       => __( 'Items in the cart, e.g. "2 × Leather Tote"', 'cartlum' ),
			'{items_short}' => __( 'Items, shortened to 60 characters (keeps SMS short)', 'cartlum' ),
			'{total}'       => __( 'Cart total', 'cartlum' ),
			'{amount}'      => __( 'Cart total as a plain number, e.g. 6,716 — write “Tk {amount}” to keep the SMS free of Bangla characters (cheaper)', 'cartlum' ),
			'{cart_link}'   => __( 'Link that refills the cart and opens checkout', 'cartlum' ),
			'{coupon}'      => __( 'Coupon code (created for this cart if needed)', 'cartlum' ),
			'{discount}'    => __( 'Coupon value, e.g. 100৳ or 10%', 'cartlum' ),
			'{optout_link}' => __( 'Unsubscribe link', 'cartlum' ),
		);

		/**
		 * Documents extra placeholders (fill them through cartlum_placeholders).
		 *
		 * @param array $list tag => description.
		 */
		return apply_filters( 'cartlum_placeholder_list', $list );
	}

	/**
	 * The customer's first name, safe to put in a message to them. The name comes from whoever filled
	 * in the checkout, so it is cut down to letters only (no links, digits or punctuation) and kept
	 * short: otherwise a stranger could type a victim's number and a "name" such as a phishing link,
	 * and the store's own SMS or email would deliver it.
	 *
	 * @param string $name Name as typed.
	 * @return string May be ''.
	 */
	public static function safe_name( $name ) {
		$first = (string) strtok( trim( $name ), " \t\n\r" );
		$first = (string) preg_replace( '/[^\p{L}\p{M}]+/u', '', $first );
		return function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 20 ) : substr( $first, 0, 40 );
	}

	/**
	 * Renders a template for a cart.
	 *
	 * @param string $template Template.
	 * @param object $cart     Row.
	 * @return string|WP_Error Error only when {coupon} is used and a coupon cannot be created.
	 */
	public static function render( $template, $cart ) {
		$template = (string) $template;
		$first    = self::safe_name( (string) $cart->name );

		$values = array(
			'{name}'        => '' !== $first ? $first : __( 'there', 'cartlum' ),
			'{store}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{items}'       => self::items_text( $cart ),
			'{items_short}' => self::shorten( str_replace( ' × ', ' x ', self::items_text( $cart ) ), 60 ), // Plain "x": × would make the SMS unicode.
			'{amount}'      => number_format( (float) $cart->cart_total, 0 ),
			'{total}'       => html_entity_decode( wp_strip_all_tags( wc_price( $cart->cart_total, array( 'currency' => $cart->currency ) ) ), ENT_QUOTES, 'UTF-8' ),
			'{cart_link}'   => Cartlum_Restore::link( $cart ),
			'{optout_link}' => Cartlum_Restore::optout_link( $cart ),
		);

		// Only create a coupon when the message actually offers one.
		if ( false !== strpos( $template, '{coupon}' ) || false !== strpos( $template, '{discount}' ) ) {
			$code = Cartlum_Coupons::for_cart( $cart );
			if ( is_wp_error( $code ) ) {
				return $code;
			}
			$values['{coupon}']   = $code;
			$values['{discount}'] = Cartlum_Coupons::describe( $code );
		}

		/**
		 * Filters placeholder values.
		 *
		 * @param array  $values Placeholder => value.
		 * @param object $cart   Cart row.
		 */
		$values = apply_filters( 'cartlum_placeholders', $values, $cart );

		return strtr( $template, $values );
	}

	/**
	 * Text cut to a length, ending in "..." when cut.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function shorten( $text, $max ) {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len <= $max ) {
			return $text;
		}
		return rtrim( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 3 ) : substr( $text, 0, $max - 3 ) ) . '...';
	}

	/**
	 * "2 × Leather Tote, 1 × Mini Bag".
	 *
	 * @param object $cart Row.
	 * @return string
	 */
	public static function items_text( $cart ) {
		$parts = array();
		foreach ( Cartlum_Carts::items( $cart ) as $item ) {
			$parts[] = ( (int) $item['quantity'] > 1 ? (int) $item['quantity'] . ' × ' : '' ) . $item['name'];
		}
		return implode( ', ', $parts );
	}
}
