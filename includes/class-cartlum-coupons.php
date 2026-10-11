<?php
/**
 * One-off coupons, one per cart.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Coupons.
 */
class Cartlum_Coupons {

	/**
	 * The cart's coupon, creating it if needed. Each code works once and expires, so a code that is
	 * forwarded or posted online is worth little.
	 *
	 * @param object $cart Row.
	 * @return string|WP_Error Code.
	 */
	public static function for_cart( $cart ) {
		if ( $cart->coupon_code && self::is_usable( $cart->coupon_code ) ) {
			return $cart->coupon_code;
		}

		$amount = (float) Cartlum_Settings::get( 'coupon_amount' );
		if ( $amount <= 0 ) {
			return new WP_Error( 'cartlum_coupon_amount', __( 'Set a coupon amount in Cartlum → Messages → Coupons first.', 'cartlum' ) );
		}
		$type = in_array( Cartlum_Settings::get( 'coupon_type' ), array( 'fixed_cart', 'percent' ), true ) ? Cartlum_Settings::get( 'coupon_type' ) : 'fixed_cart';
		$days = max( 1, (int) Cartlum_Settings::get( 'coupon_expiry_days' ) );

		$code = self::unique_code();
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
		$coupon->set_description( sprintf( /* translators: %d: cart id */ __( 'Cartlum recovery coupon for cart #%d', 'cartlum' ), $cart->id ) );
		$coupon->update_meta_data( '_cartlum_cart_id', (int) $cart->id );
		$coupon->save();

		Cartlum_Carts::update( $cart->id, array( 'coupon_code' => $code ) );
		$cart->coupon_code = $code;
		Cartlum_Events::add(
			$cart->id,
			'coupon_created',
			array(
				'meta' => array(
					'code'   => $code,
					'type'   => $type,
					'amount' => $amount,
				),
			)
		);
		return $code;
	}

	/**
	 * Whether a code still exists, is unused and has not expired.
	 *
	 * @param string $code Code.
	 * @return bool
	 */
	public static function is_usable( $code ) {
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return false;
		}
		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			return false;
		}
		return ! $coupon->get_usage_limit() || $coupon->get_usage_count() < $coupon->get_usage_limit();
	}

	/**
	 * A code that does not exist yet, e.g. CL-7KQ2XP. No 0/O/1/I, which get misread over the phone.
	 *
	 * @return string
	 */
	private static function unique_code() {
		$prefix   = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) Cartlum_Settings::get( 'coupon_prefix' ) ) );
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$rand = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$rand .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$code = ( $prefix ? $prefix . '-' : '' ) . $rand;
		} while ( wc_get_coupon_id_by_code( $code ) );
		return $code;
	}

	/**
	 * Human description of the discount, e.g. "100৳" or "10%".
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function describe( $code ) {
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return '';
		}
		if ( 'percent' === $coupon->get_discount_type() ) {
			return wc_format_decimal( $coupon->get_amount(), 0 ) . '%';
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( $coupon->get_amount(), array( 'decimals' => 0 ) ) ), ENT_QUOTES, 'UTF-8' );
	}
}
