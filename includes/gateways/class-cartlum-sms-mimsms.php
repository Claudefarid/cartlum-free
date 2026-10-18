<?php
/**
 * MiMSMS (Bangladesh) -- api.mimsms.com. Answers HTTP 200 either way; status "Success" with
 * status code 200 means the SMS was accepted.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_MiMSMS.
 */
class Cartlum_SMS_MiMSMS extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'mimsms';
	}

	/** {@inheritdoc} */
	public function label() {
		return 'MiMSMS (Bangladesh)';
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_mim_user'   => array(
				'label' => __( 'User name', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'The email address you log in to MiMSMS with.', 'cartlum' ),
			),
			'sms_mim_key'    => array(
				'label' => __( 'API key', 'cartlum' ),
				'type'  => 'password',
				'help'  => __( 'From your MiMSMS panel → API.', 'cartlum' ),
			),
			'sms_mim_sender' => array(
				'label' => __( 'Sender ID', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'Your registered sender ID, exactly as MiMSMS shows it.', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		if ( '' === $this->opt( 'sms_mim_user' ) || '' === $this->opt( 'sms_mim_key' ) || '' === $this->opt( 'sms_mim_sender' ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'MiMSMS user name, API key or sender ID is missing.', 'cartlum' ) );
		}
		$response = wp_remote_post(
			'https://api.mimsms.com/api/SmsSending/SMS',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'UserName'        => $this->opt( 'sms_mim_user' ),
						'Apikey'          => $this->opt( 'sms_mim_key' ),
						'MobileNumber'    => $phone,
						'SenderName'      => $this->opt( 'sms_mim_sender' ),
						'TransactionType' => 'T', // Transactional: order and reminder messages to one customer.
						'Message'         => $message,
						'CampaignId'      => 'null',
					)
				),
			)
		);
		return $this->result(
			$response,
			function ( $code, $body, $json ) {
				// MiMSMS's own examples sometimes swap statusCode and status, so either order counts.
				$words = is_array( $json ) ? array( strtolower( (string) ( $json['status'] ?? '' ) ), strtolower( (string) ( $json['statusCode'] ?? '' ) ) ) : array();
				return 200 === $code && in_array( 'success', $words, true ) && in_array( '200', $words, true );
			}
		);
	}
}
