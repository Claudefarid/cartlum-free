<?php
/**
 * eSMS (Bangladesh) -- login.esms.com.bd, API v3.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_ESMS.
 */
class Cartlum_SMS_ESMS extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'esms';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'eSMS (Bangladesh)';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_esms_token'  => array(
				'label' => __( 'API token', 'cartlum' ),
				'type'  => 'password',
				'help'  => __( 'From your eSMS dashboard → Developer → API token.', 'cartlum' ),
			),
			'sms_esms_sender' => array(
				'label' => __( 'Sender ID', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'Your approved sender ID or masking name.', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		if ( '' === $this->opt( 'sms_esms_token' ) || '' === $this->opt( 'sms_esms_sender' ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'eSMS token or sender ID is missing.', 'cartlum' ) );
		}
		$response = wp_remote_post(
			'https://login.esms.com.bd/api/v3/sms/send',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->opt( 'sms_esms_token' ),
				),
				'body'    => wp_json_encode(
					array(
						'recipient' => $phone,
						'sender_id' => $this->opt( 'sms_esms_sender' ),
						'type'      => 'plain',
						'message'   => $message,
					)
				),
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				return $code >= 200 && $code < 300 && is_array( $json ) && isset( $json['status'] ) && 'success' === $json['status'];
			}
		);
	}
}
