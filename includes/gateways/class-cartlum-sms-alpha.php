<?php
/**
 * Alpha SMS (Bangladesh) -- sms.net.bd, api.sms.net.bd. Answers HTTP 200 either way; error 0
 * means the SMS was accepted.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_Alpha.
 */
class Cartlum_SMS_Alpha extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'alpha';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'Alpha SMS (Bangladesh)';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_alpha_key'    => array(
				'label' => __( 'API key', 'cartlum' ),
				'type'  => 'password',
				'help'  => __( 'From your Alpha SMS panel → API.', 'cartlum' ),
			),
			'sms_alpha_sender' => array(
				'label' => __( 'Sender ID (optional)', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'An approved sender ID or masking name. Leave empty to send from Alpha SMS’s default number.', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		if ( '' === $this->opt( 'sms_alpha_key' ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'Alpha SMS API key is missing.', 'cartlum' ) );
		}
		$body = array(
			'api_key' => $this->opt( 'sms_alpha_key' ),
			'msg'     => $message,
			'to'      => $phone,
		);
		if ( '' !== $this->opt( 'sms_alpha_sender' ) ) {
			$body['sender_id'] = $this->opt( 'sms_alpha_sender' );
		}
		$response = wp_remote_post(
			'https://api.sms.net.bd/sendsms',
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				return 200 === $code && is_array( $json ) && isset( $json['error'] ) && 0 === (int) $json['error'];
			}
		);
	}
}
