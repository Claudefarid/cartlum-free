<?php
/**
 * Abuse guards: rate-limit counters (with a fixed time window, stored as transients so they work
 * with or without an object cache) and a check that a URL from the settings is public.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Guard.
 */
class Cartlum_Guard {

	/**
	 * A counter's current value: { n, until }. The window keeps its end time however often the
	 * counter is updated.
	 *
	 * @param string $key Transient.
	 * @return array
	 */
	private static function counter( $key ) {
		$c = get_transient( $key );
		return is_array( $c ) && isset( $c['n'], $c['until'] ) && $c['until'] > time() ? $c : array(
			'n'     => 0,
			'until' => 0,
		);
	}

	/**
	 * Whether a counter is still under its limit.
	 *
	 * @param string $key   Transient.
	 * @param int    $limit Limit; 0 = none.
	 * @return bool
	 */
	public static function under( $key, $limit ) {
		return $limit <= 0 || self::counter( $key )['n'] < $limit;
	}

	/**
	 * Adds one to a counter. The window starts at the first count.
	 *
	 * @param string $key Transient.
	 * @param int    $ttl Window in seconds.
	 */
	public static function bump( $key, $ttl ) {
		$c = self::counter( $key );
		if ( ! $c['n'] ) {
			$c['until'] = time() + $ttl;
		}
		++$c['n'];
		set_transient( $key, $c, max( 1, $c['until'] - time() ) );
	}

	/**
	 * A short, non-reversible key part for a phone number or IP address.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function hash( $value ) {
		return substr( hash_hmac( 'sha256', (string) $value, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * The visitor's IP address, as WooCommerce records it.
	 *
	 * @return string
	 */
	public static function ip() {
		if ( class_exists( 'WC_Geolocation' ) ) {
			return (string) WC_Geolocation::get_ip_address();
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Whether a URL entered in the settings points at the public internet. Refuses private, local,
	 * link-local (e.g. a cloud server's metadata service at 169.254.169.254) and reserved addresses,
	 * so a settings box cannot be used to make the server read its own internal network. Checked
	 * here rather than left to WordPress, whose own check skips link-local addresses and can be
	 * switched off by the host.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_public_url( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		/**
		 * Allows a URL Cartlum would otherwise refuse, e.g. an SMS server on your own network.
		 *
		 * @param bool   $allow Allow.
		 * @param string $url   URL.
		 */
		if ( apply_filters( 'cartlum_allow_internal_url', false, $url ) ) {
			return true;
		}
		$host = strtolower( trim( $parts['host'], '[]' ) );
		if ( 'localhost' === $host || '.localhost' === substr( $host, -10 ) ) {
			return false;
		}
		$ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : gethostbynamel( $host );
		if ( ! $ips ) {
			// The name does not resolve here, so nothing internal can be reached through it either;
			// the request itself (wp_safe_remote_*) will fail or be refused.
			return true;
		}
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}
		return true;
	}
}
