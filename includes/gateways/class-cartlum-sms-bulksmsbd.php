<?php
/**
 * BulkSMSBD (Bangladesh) -- bulksmsbd.net. Answers HTTP 200 either way; response_code 202 means
 * the SMS was accepted.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_BulkSMSBD.
 */
class Cartlum_SMS_BulkSMSBD extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'bulksmsbd';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'BulkSMSBD (Bangladesh)';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_bulksmsbd_key'    => array(
				'label' => __( 'API key', 'cartlum' ),
				'type'  => 'password',
				'help'  => __( 'From your BulkSMSBD panel → API. Turn off IP whitelisting there, or add your server’s IP address.', 'cartlum' ),
			),
			'sms_bulksmsbd_sender' => array(
				'label' => __( 'Sender ID', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'Your approved sender ID (a number or masking name).', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		if ( '' === $this->opt( 'sms_bulksmsbd_key' ) || '' === $this->opt( 'sms_bulksmsbd_sender' ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'BulkSMSBD API key or sender ID is missing.', 'cartlum' ) );
		}
		$response = wp_remote_post(
			'https://bulksmsbd.net/api/smsapi',
			array(
				'timeout' => 20,
				'body'    => array(
					'api_key'  => $this->opt( 'sms_bulksmsbd_key' ),
					'type'     => 'text',
					'senderid' => $this->opt( 'sms_bulksmsbd_sender' ),
					'number'   => $phone,
					'message'  => $message,
				),
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				return 200 === $code && is_array( $json ) && 202 === (int) ( $json['response_code'] ?? 0 );
			}
		);
	}
}
