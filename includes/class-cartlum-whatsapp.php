<?php
/**
 * WhatsApp click-to-chat: opens WhatsApp with the customer's number and a ready message, for a
 * person to send. Automatic sending through the WhatsApp Business Cloud API is in Cartlum Pro.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_WhatsApp.
 */
class Cartlum_WhatsApp {

	/**
	 * wa.me link.
	 *
	 * @param string $phone   Normalized phone.
	 * @param string $message Text.
	 * @return string
	 */
	public static function chat_link( $phone, $message ) {
		return 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( $message );
	}
}
