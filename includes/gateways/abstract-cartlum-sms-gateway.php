<?php
/**
 * Base class for SMS gateways. Cartlum Pro and third parties add more through the
 * cartlum_sms_gateways filter.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_Gateway.
 */
abstract class Cartlum_SMS_Gateway {

	/**
	 * Unique id, e.g. "twilio".
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Name shown in settings.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Settings fields: key => { label, type (text|password|textarea|select), help, options }.
	 * Keys are stored in the main Cartlum settings.
	 *
	 * @return array
	 */
	abstract public function fields();

	/**
	 * Sends one SMS.
	 *
	 * @param string $phone   International digits, no "+", e.g. 8801712345678.
	 * @param string $message Text.
	 * @return true|WP_Error
	 */
	abstract public function send( $phone, $message );

	/**
	 * A setting.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	protected function opt( $key ) {
		return trim( (string) Cartlum_Settings::get( $key ) );
	}

	/**
	 * Turns an HTTP response into true or a WP_Error carrying the gateway's own words.
	 *
	 * @param array|WP_Error $response    wp_remote_* result.
	 * @param callable       $is_success  fn( int $code, string $body, array|null $json ): bool.
	 * @return true|WP_Error
	 */
	protected function result( $response, $is_success ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( call_user_func( $is_success, $code, $body, is_array( $json ) ? $json : null ) ) {
			return true;
		}
		// The provider's own words when its answer has them (error_message, msg, ...), else the raw answer.
		$words = '';
		if ( is_array( $json ) ) {
			foreach ( array( 'error_message', 'responseResult', 'msg', 'message', 'error' ) as $key ) {
				if ( isset( $json[ $key ] ) && is_string( $json[ $key ] ) && '' !== trim( $json[ $key ] ) ) {
					$words = $json[ $key ];
					break;
				}
			}
		}
		return new WP_Error( 'cartlum_sms_failed', sprintf( 'HTTP %d: %s', $code, wp_strip_all_tags( substr( '' !== $words ? $words : $body, 0, 300 ) ) ) );
	}
}
