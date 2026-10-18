<?php
/**
 * SSL Wireless (Bangladesh) -- ISMS Plus API v3, smsplus.sslwireless.com. Answers HTTP 200 either
 * way; status "SUCCESS" means the SMS was accepted.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_SSLWireless.
 */
class Cartlum_SMS_SSLWireless extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'sslwireless';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'SSL Wireless (Bangladesh)';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_sslw_token' => array(
				'label' => __( 'API token', 'cartlum' ),
				'type'  => 'password',
				'help'  => __( 'From SSL Wireless (ISMS Plus) for your account.', 'cartlum' ),
			),
			'sms_sslw_sid'   => array(
				'label' => __( 'SID', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'The SID SSL Wireless gave you for your sender ID or masking name.', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		if ( '' === $this->opt( 'sms_sslw_token' ) || '' === $this->opt( 'sms_sslw_sid' ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'SSL Wireless API token or SID is missing.', 'cartlum' ) );
		}
		$response = wp_remote_post(
			'https://smsplus.sslwireless.com/api/v3/send-sms',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'api_token' => $this->opt( 'sms_sslw_token' ),
						'sid'       => $this->opt( 'sms_sslw_sid' ),
						'msisdn'    => $phone,
						'sms'       => $message,
						// SSL Wireless wants a reference of our own for each SMS, unique per account.
						'csms_id'   => strtoupper( wp_generate_password( 20, false ) ),
					)
				),
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				return $code >= 200 && $code < 300 && is_array( $json ) && 'SUCCESS' === strtoupper( (string) ( $json['status'] ?? '' ) );
			}
		);
	}
}
