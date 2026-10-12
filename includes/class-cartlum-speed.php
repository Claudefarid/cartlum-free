<?php
/**
 * Two optional speed settings for the shop pages, both off by default.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Speed.
 */
class Cartlum_Speed {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' === Cartlum_Settings::get( 'speed_grid_images' ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'grid_image' ), 20, 3 );
		}
		if ( 'yes' === Cartlum_Settings::get( 'speed_no_elementor_icons' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'drop_elementor_icons' ), 999 );
		}
	}

	/**
	 * Product grid photos: WooCommerce says "300px wide", so a sharp phone screen fetches the
	 * largest copy. With the real width (about half the screen on phones) it fetches a smaller
	 * one. Grid photos also always load lazily, so they never hold up the first screen.
	 *
	 * @param array        $attr       Attributes.
	 * @param WP_Post      $attachment Image.
	 * @param string|array $size       Size.
	 * @return array
	 */
	public static function grid_image( $attr, $attachment, $size ) {
		unset( $attachment );
		if ( 'woocommerce_thumbnail' === $size ) {
			$attr['sizes']   = '(max-width: 768px) 46vw, 300px';
			$attr['loading'] = 'lazy';
			unset( $attr['fetchpriority'] );
		}
		return $attr;
	}

	/**
	 * Elementor's own icon font (about 100 KB on every page), for sites whose icons come from
	 * elsewhere (Font Awesome, SVG).
	 */
	public static function drop_elementor_icons() {
		foreach ( array( 'elementor-icons', 'hfe-elementor-icons' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}
}
