<?php
/**
 * Phone number normalisation.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Phone.
 */
class Cartlum_Phone {

	/**
	 * The calling code used for numbers typed in local format, e.g. "880" for Bangladesh.
	 *
	 * @return string Digits only.
	 */
	public static function default_calling_code() {
		$set = preg_replace( '/\D/', '', (string) Cartlum_Settings::get( 'country_code' ) );
		if ( '' !== $set ) {
			return $set;
		}
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$code = WC()->countries->get_country_calling_code( WC()->countries->get_base_country() );
			return preg_replace( '/\D/', '', (string) $code );
		}
		return '';
	}

	/**
	 * Normalises a phone number to international digits without "+", e.g. 8801712345678.
	 *
	 * Accepts "+880 1712-345678", "008801712345678", "01712345678" and "8801712345678".
	 * Returns '' when it is clearly not a phone number.
	 *
	 * @param string $raw Phone as typed.
	 * @param string $calling_code Optional override for the local calling code.
	 * @return string
	 */
	public static function normalize( $raw, $calling_code = null ) {
		$raw = trim( self::to_ascii_digits( $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		$international = ( 0 === strpos( $raw, '+' ) );
		$digits        = preg_replace( '/\D/', '', $raw );

		if ( 0 === strpos( $digits, '00' ) ) {
			$digits        = substr( $digits, 2 );
			$international = true;
		}

		if ( ! $international ) {
			$cc = null === $calling_code ? self::default_calling_code() : preg_replace( '/\D/', '', $calling_code );
			if ( '' !== $cc && 0 !== strpos( $digits, $cc ) ) {
				// Local format: a leading trunk "0" is dropped before the country code is added.
				$digits = $cc . ltrim( $digits, '0' );
			}
		}

		$len = strlen( $digits );
		return ( $len >= 8 && $len <= 15 ) ? $digits : '';
	}

	/**
	 * Bangla (০-৯), Arabic-Indic and Devanagari digits to 0-9; many customers type their number
	 * with their phone's native keyboard.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function to_ascii_digits( $text ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( array( '০১২৩৪৫৬৭৮৯', '٠١٢٣٤٥٦٧٨٩', '۰۱۲۳۴۵۶۷۸۹', '०१२३४५६७८९' ) as $digits ) {
				foreach ( preg_split( '//u', $digits, -1, PREG_SPLIT_NO_EMPTY ) as $i => $char ) {
					$map[ $char ] = (string) $i;
				}
			}
		}
		return strtr( (string) $text, $map );
	}

	/**
	 * Ways the same number is commonly stored on orders and accounts: 8801712345678,
	 * +8801712345678 and the local 01712345678.
	 *
	 * @param string $normalized Digits.
	 * @return string[]
	 */
	public static function variants( $normalized ) {
		if ( '' === $normalized ) {
			return array();
		}
		$cc   = self::default_calling_code();
		$list = array( $normalized, '+' . $normalized );
		if ( '' !== $cc && 0 === strpos( $normalized, $cc ) ) {
			$list[] = '0' . substr( $normalized, strlen( $cc ) );
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * For display and tel: links: "+8801712345678".
	 *
	 * @param string $normalized Digits.
	 * @return string
	 */
	public static function display( $normalized ) {
		return '' === $normalized ? '' : '+' . $normalized;
	}
}
