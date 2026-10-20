<?php
/**
 * Option buttons: colours, sizes and other options of a product shown as buttons instead of
 * drop-down lists, so customers see every choice at once and pick with one tap.
 *
 * WooCommerce's own select stays in the form (hidden), and each button only sets it, so the price,
 * stock, image and Buy now keep working exactly as with the drop-down.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Swatches.
 */
class Cartlum_Swatches {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'swatches' ) ) {
			return;
		}
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'buttons' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Scripts and styles on the pages of products with options.
	 */
	public static function assets() {
		$product = is_product() ? wc_get_product( get_queried_object_id() ) : null;
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		wp_enqueue_style( 'cartlum-swatches', CARTLUM_URL . 'assets/css/swatches.css', array(), CARTLUM_VERSION );
		wp_enqueue_script( 'cartlum-swatches', CARTLUM_URL . 'assets/js/swatches.js', array( 'jquery' ), CARTLUM_VERSION, true );
	}

	/**
	 * The buttons, after WooCommerce's select for one option.
	 *
	 * @param string $html Select HTML.
	 * @param array  $args { options, attribute, product, selected, ... }.
	 * @return string
	 */
	public static function buttons( $html, $args ) {
		$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		$product   = isset( $args['product'] ) ? $args['product'] : null;
		$options   = isset( $args['options'] ) ? (array) $args['options'] : array();
		if ( '' === $attribute || ! $options || false === strpos( $html, '<select' ) ) {
			return $html;
		}
		$choices = array(); // value => label, in the select's order.
		if ( $product && taxonomy_exists( $attribute ) ) {
			foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
				if ( in_array( $term->slug, $options, true ) ) {
					$choices[ $term->slug ] = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, as its select uses it.
				}
			}
		} else {
			foreach ( $options as $option ) {
				$choices[ $option ] = apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, as its select uses it.
			}
		}
		if ( ! $choices ) {
			return $html;
		}
		$selected = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$out      = '<div class="cartlum-swatches" role="group" aria-label="' . esc_attr( wc_attribute_label( $attribute, $product ) ) . '">';
		foreach ( $choices as $value => $label ) {
			$out .= sprintf(
				'<button type="button" class="cartlum-swatch" data-value="%1$s" aria-pressed="%2$s">%3$s</button>',
				esc_attr( $value ),
				(string) $value === $selected ? 'true' : 'false',
				esc_html( $label )
			);
		}
		$out .= '</div>';
		// The select stays for the form and WooCommerce's script, out of sight.
		return preg_replace( '/<select\b/', '<select data-cartlum-swatches="1"', $html, 1 ) . $out;
	}
}
