<?php
/**
 * Sends SMS through the chosen gateway and records the result on the cart's timeline.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS.
 */
class Cartlum_SMS {

	/**
	 * Whether the last send_raw() was accepted but not confirmed (the provider answered too slowly).
	 *
	 * @var bool
	 */
	public static $unconfirmed = false;

	/**
	 * Available gateways, id => instance.
	 *
	 * @return Cartlum_SMS_Gateway[]
	 */
	public static function gateways() {
		static $gateways = null;
		if ( null === $gateways ) {
			$list = array( new Cartlum_SMS_ESMS(), new Cartlum_SMS_BulkSMSBD(), new Cartlum_SMS_SSLWireless(), new Cartlum_SMS_Alpha(), new Cartlum_SMS_MiMSMS(), new Cartlum_SMS_Twilio(), new Cartlum_SMS_Custom() );

			/**
			 * Adds SMS gateways. Each must extend Cartlum_SMS_Gateway.
			 *
			 * @param Cartlum_SMS_Gateway[] $list Gateways.
			 */
			$list     = apply_filters( 'cartlum_sms_gateways', $list );
			$gateways = array();
			foreach ( $list as $gateway ) {
				if ( $gateway instanceof Cartlum_SMS_Gateway ) {
					$gateways[ $gateway->id() ] = $gateway;
				}
			}
		}
		return $gateways;
	}

	/**
	 * The configured gateway.
	 *
	 * @return Cartlum_SMS_Gateway|null
	 */
	public static function gateway() {
		$all = self::gateways();
		$id  = (string) Cartlum_Settings::get( 'sms_gateway' );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Sends an SMS to a cart's customer.
	 *
	 * @param object $cart    Row.
	 * @param string $message Text (already rendered).
	 * @param string $context manual|automation.
	 * @param array  $meta    Extra details for the timeline event (step, variant ...).
	 * @return true|WP_Error
	 */
	public static function send_to_cart( $cart, $message, $context = 'manual', array $meta = array() ) {
		if ( '' === $cart->phone ) {
			return new WP_Error( 'cartlum_no_phone', __( 'This cart has no phone number.', 'cartlum' ) );
		}
		if ( Cartlum_Restore::is_opted_out( $cart->phone ) ) {
			return new WP_Error( 'cartlum_optout', __( 'This customer has unsubscribed from reminders.', 'cartlum' ) );
		}
		$result = self::send_raw( $cart->phone, $message );

		Cartlum_Events::add(
			$cart->id,
			is_wp_error( $result ) ? 'sms_failed' : 'sms_sent',
			array(
				'channel' => 'sms',
				'message' => $message,
				'meta'    => array_merge(
					$meta,
					array(
						'context'     => $context,
						'gateway'     => (string) Cartlum_Settings::get( 'sms_gateway' ),
						'error'       => is_wp_error( $result ) ? $result->get_error_message() : '',
						'unconfirmed' => self::$unconfirmed ? 1 : 0,
					)
				),
			)
		);
		if ( ! is_wp_error( $result ) ) {
			Cartlum_Contact::mark_contacted( $cart );
		}
		return $result;
	}

	/**
	 * Sends to any number. Used by the settings "send test" button.
	 *
	 * @param string $phone   Normalized phone.
	 * @param string $message Text.
	 * @return true|WP_Error
	 */
	public static function send_raw( $phone, $message ) {
		$gateway = self::gateway();
		if ( ! $gateway ) {
			return new WP_Error( 'cartlum_no_gateway', __( 'Choose an SMS gateway in Cartlum → Messages → SMS.', 'cartlum' ) );
		}
		// Caught here, so a mistyped number gets a clear message and never reaches the gateway.
		$invalid = Cartlum_Phone::invalid_reason( $phone );
		if ( '' !== $invalid ) {
			return new WP_Error( 'cartlum_bad_phone', $invalid );
		}
		self::$unconfirmed = false;
		$result            = $gateway->send( $phone, $message );
		// Connected and sent, but no answer in time: providers such as eSMS are sometimes slow to
		// reply while the SMS still goes out. Counted as sent (a login code stays valid, nothing is
		// sent twice); callers can say it was not confirmed. A failed connection is still an error.
		if ( is_wp_error( $result ) && preg_match( '/Operation timed out after \d+ milliseconds with \d+ (out of \d+ )?bytes received/i', $result->get_error_message() ) ) {
			self::$unconfirmed = true;
			return true;
		}
		return $result;
	}
}

/**
 * Shared bookkeeping for any channel that reaches the customer.
 */
class Cartlum_Contact {

	/**
	 * Counts a contact, which is what later makes an order "recovered" rather than "converted".
	 *
	 * @param object $cart Row.
	 */
	public static function mark_contacted( $cart ) {
		Cartlum_Carts::update(
			$cart->id,
			array(
				'contact_count' => (int) $cart->contact_count + 1,
				'contacted_at'  => Cartlum_Carts::now(),
			)
		);
		$cart->contact_count = (int) $cart->contact_count + 1;
	}
}
