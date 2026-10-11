<?php
/**
 * One-time SMS codes, shared by phone login and the checkout check: 6 digits, valid 10 minutes,
 * 5 tries, single use; sending is limited per number (once a minute, 5 an hour, 10 a day), per IP
 * address and per day for the whole store. Codes are stored hashed, never as they are.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_OTP.
 */
class Cartlum_OTP {

	const TTL         = 600;  // A code is valid for 10 minutes.
	const MAX_TRIES   = 5;    // Wrong codes before it is thrown away.
	const RESEND_WAIT = 60;   // Seconds between codes to one number.
	const PER_HOUR    = 5;    // Codes per number per hour.
	const PER_DAY     = 10;   // Codes per number per day; with 5 tries each, guessing stays hopeless.
	const IP_PER_HOUR = 30;   // Codes per IP address per hour; mobile networks share addresses.

	/**
	 * A typed number, normalized, or '' if it is not a usable mobile number.
	 *
	 * @param string $raw Phone as typed.
	 * @return string
	 */
	public static function mobile( $raw ) {
		$phone = Cartlum_Phone::normalize( (string) $raw );
		if ( '880' === Cartlum_Phone::default_calling_code() && ! preg_match( '/^8801[3-9]\d{8}$/', $phone ) ) {
			return '';
		}
		return $phone;
	}

	/**
	 * The number as customers write it, e.g. 01712345678.
	 *
	 * @param string $phone Normalized digits.
	 * @return string
	 */
	public static function shown( $phone ) {
		$variants = Cartlum_Phone::variants( $phone );
		return (string) end( $variants );
	}

	/**
	 * Transient key for something about a number or address.
	 *
	 * @param string $what  Kind.
	 * @param string $value Phone or IP.
	 * @return string
	 */
	private static function key( $what, $value ) {
		return 'cartlum_otp_' . $what . '_' . Cartlum_Guard::hash( $value );
	}

	/**
	 * The stored form of a code: never the code itself.
	 *
	 * @param string $code  Code.
	 * @param string $phone Phone.
	 * @return string
	 */
	private static function hash( $code, $phone ) {
		return hash_hmac( 'sha256', $code . '|' . $phone, wp_salt( 'auth' ) );
	}

	/**
	 * Sends a code.
	 *
	 * @param string $phone    Normalized mobile number.
	 * @param string $template SMS text with {code} and {store}.
	 * @return true|WP_Error
	 */
	public static function send( $phone, $template ) {
		$code_key = self::key( 'code', $phone );
		$wait_key = self::key( 'wait', $phone );
		// Its own timer, so throwing a code away (too many wrong tries) does not skip the wait.
		if ( get_transient( $wait_key ) ) {
			return new WP_Error( 'cartlum_otp_wait', __( 'A code was just sent. Please wait a minute before asking for a new one.', 'cartlum' ) );
		}
		/**
		 * Codes one IP address may request per hour. Mobile networks put many customers behind one
		 * address, so keep this generous.
		 *
		 * @param int $limit Limit.
		 */
		$ip_limit = (int) apply_filters( 'cartlum_otp_ip_limit', self::IP_PER_HOUR );
		$counters = array(
			array( self::key( 'hour', $phone ), self::PER_HOUR, HOUR_IN_SECONDS ),
			array( self::key( 'day', $phone ), self::PER_DAY, DAY_IN_SECONDS ),
			array( self::key( 'ip', Cartlum_Guard::ip() ), $ip_limit, HOUR_IN_SECONDS ),
		);
		foreach ( $counters as $c ) {
			if ( ! Cartlum_Guard::under( $c[0], $c[1] ) ) {
				return new WP_Error( 'cartlum_otp_limit', __( 'Too many codes requested. Please try again in an hour.', 'cartlum' ) );
			}
		}
		$day_key = 'cartlum_otp_day_' . gmdate( 'Ymd' );
		if ( ! Cartlum_Guard::under( $day_key, (int) Cartlum_Settings::get( 'phone_login_daily_limit' ) ) ) {
			return new WP_Error( 'cartlum_otp_store_limit', __( 'Codes are not available right now. Please try again later or contact the store.', 'cartlum' ) );
		}
		// Counted only now that a code will actually be sent.
		$counters[] = array( $day_key, 0, DAY_IN_SECONDS );
		foreach ( $counters as $c ) {
			Cartlum_Guard::bump( $c[0], $c[2] );
		}

		$code    = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$message = strtr(
			$template,
			array(
				'{code}'  => $code,
				'{store}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);
		set_transient(
			$code_key,
			array(
				'hash'  => self::hash( $code, $phone ),
				'sent'  => time(),
				'tries' => 0,
			),
			self::TTL
		);
		set_transient( $wait_key, 1, self::RESEND_WAIT );
		$result = Cartlum_SMS::send_raw( $phone, $message );
		if ( is_wp_error( $result ) ) {
			delete_transient( $code_key );
			return new WP_Error( 'cartlum_otp_sms', __( 'The code could not be sent. Please try again in a few minutes.', 'cartlum' ) );
		}
		return true;
	}

	/**
	 * Checks a code. A right code is used up.
	 *
	 * @param string $phone Normalized mobile number.
	 * @param string $code  Code as typed (Bangla digits are fine).
	 * @return true|WP_Error
	 */
	public static function verify( $phone, $code ) {
		$code = preg_replace( '/\D/', '', Cartlum_Phone::to_ascii_digits( (string) $code ) );
		$key  = '' !== $phone ? self::key( 'code', $phone ) : '';
		$rec  = $key ? get_transient( $key ) : false;
		if ( ! is_array( $rec ) ) {
			return new WP_Error( 'cartlum_otp_expired', __( 'This code has expired. Please ask for a new one.', 'cartlum' ) );
		}
		if ( 6 !== strlen( $code ) || ! hash_equals( $rec['hash'], self::hash( $code, $phone ) ) ) {
			++$rec['tries'];
			if ( $rec['tries'] >= self::MAX_TRIES ) {
				delete_transient( $key );
				return new WP_Error( 'cartlum_otp_tries', __( 'Too many wrong codes. Please ask for a new one.', 'cartlum' ) );
			}
			set_transient( $key, $rec, max( 1, self::TTL - ( time() - (int) $rec['sent'] ) ) );
			return new WP_Error(
				'cartlum_otp_wrong',
				/* translators: %d: tries left */
				sprintf( _n( 'That code is not right. %d try left.', 'That code is not right. %d tries left.', self::MAX_TRIES - $rec['tries'], 'cartlum' ), self::MAX_TRIES - $rec['tries'] )
			);
		}
		delete_transient( $key ); // Single use.
		return true;
	}
}
