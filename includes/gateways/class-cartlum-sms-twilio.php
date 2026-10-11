<?php
/**
 * Twilio Programmable Messaging.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_Twilio.
 */
class Cartlum_SMS_Twilio extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'twilio';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'Twilio';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_twilio_sid'   => array(
				'label' => __( 'Account SID', 'cartlum' ),
				'type'  => 'text',
			),
			'sms_twilio_token' => array(
				'label' => __( 'Auth token', 'cartlum' ),
				'type'  => 'password',
			),
			'sms_twilio_from'  => array(
				'label' => __( 'From', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'A Twilio phone number in +E.164 format, or a Messaging Service SID (starts with MG).', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		$sid   = $this->opt( 'sms_twilio_sid' );
		$token = $this->opt( 'sms_twilio_token' );
		$from  = $this->opt( 'sms_twilio_from' );
		if ( '' === $sid || '' === $token || '' === $from ) {
			return new WP_Error( 'cartlum_sms_config', __( 'Twilio Account SID, auth token or From is missing.', 'cartlum' ) );
		}
		$body = array(
			'To'   => '+' . $phone,
			'Body' => $message,
		);
		if ( 0 === strpos( $from, 'MG' ) ) {
			$body['MessagingServiceSid'] = $from;
		} else {
			$body['From'] = $from;
		}
		$response = wp_remote_post(
			'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
				'body'    => $body,
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				return 201 === $code || ( 200 === $code && is_array( $json ) && ! empty( $json['sid'] ) );
			}
		);
	}
}
