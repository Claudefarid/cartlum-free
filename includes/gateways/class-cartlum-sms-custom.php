<?php
/**
 * Any HTTP SMS API, configured with templates. Covers most local gateways without code.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_SMS_Custom.
 */
class Cartlum_SMS_Custom extends Cartlum_SMS_Gateway {

	/** {@inheritdoc} */
	public function id() {
		return 'custom';
	}

	/** {@inheritdoc} */
	public function label() {
		return __( 'Custom HTTP API', 'cartlum' );
	}

	/** {@inheritdoc} */
	public function fields() {
		return array(
			'sms_custom_method'  => array(
				'label'   => __( 'Method', 'cartlum' ),
				'type'    => 'select',
				'options' => array(
					'POST' => 'POST',
					'GET'  => 'GET',
				),
			),
			'sms_custom_url'     => array(
				'label' => __( 'URL', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'May contain {phone} and {message}, e.g. https://api.example.com/send?to={phone}&text={message}', 'cartlum' ),
			),
			'sms_custom_body'    => array(
				'label' => __( 'Body (POST)', 'cartlum' ),
				'type'  => 'textarea',
				'help'  => __( 'JSON or form text with {phone} and {message}, e.g. {"to":"{phone}","msg":"{message}"}. Leave empty for GET.', 'cartlum' ),
			),
			'sms_custom_headers' => array(
				'label' => __( 'Headers', 'cartlum' ),
				'type'  => 'textarea',
				'help'  => __( 'One per line, e.g. Authorization: Bearer xyz', 'cartlum' ),
			),
			'sms_custom_success' => array(
				'label' => __( 'Success text', 'cartlum' ),
				'type'  => 'text',
				'help'  => __( 'Text that appears in the response when the SMS was accepted. Empty = any 2xx response counts as success.', 'cartlum' ),
			),
		);
	}

	/** {@inheritdoc} */
	public function send( $phone, $message ) {
		$url = $this->opt( 'sms_custom_url' );
		if ( '' === $url ) {
			return new WP_Error( 'cartlum_sms_config', __( 'Custom SMS URL is missing.', 'cartlum' ) );
		}
		if ( ! Cartlum_Guard::is_public_url( $url ) ) {
			return new WP_Error( 'cartlum_sms_config', __( 'The custom SMS URL must be a public internet address (not a local or private one).', 'cartlum' ) );
		}
		$method  = 'GET' === $this->opt( 'sms_custom_method' ) ? 'GET' : 'POST';
		$url     = strtr(
			$url,
			array(
				'{phone}'   => rawurlencode( $phone ),
				'{message}' => rawurlencode( $message ),
			)
		);
		$headers = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Cartlum_Settings::get( 'sms_custom_headers' ) ) as $line ) {
			if ( false !== strpos( $line, ':' ) ) {
				list( $name, $val )         = array_map( 'trim', explode( ':', $line, 2 ) );
				$headers[ $name ] = $val;
			}
		}
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => $headers,
		);
		$body = (string) Cartlum_Settings::get( 'sms_custom_body' );
		if ( 'POST' === $method && '' !== trim( $body ) ) {
			$json         = 0 === strpos( ltrim( $body ), '{' );
			$args['body'] = strtr(
				$body,
				array(
					'{phone}'   => $json ? trim( wp_json_encode( $phone ), '"' ) : rawurlencode( $phone ),
					'{message}' => $json ? trim( wp_json_encode( $message ), '"' ) : rawurlencode( $message ),
				)
			);
			if ( $json && ! isset( $headers['Content-Type'] ) ) {
				$args['headers']['Content-Type'] = 'application/json';
			}
		}
		$success = $this->opt( 'sms_custom_success' );
		return $this->result(
			// Safe request: refuses internal and private addresses, so the URL box cannot be used to
			// reach services on the server's own network.
			wp_safe_remote_request( $url, $args ),
			function ( $code, $resp ) use ( $success ) {
				return $code >= 200 && $code < 300 && ( '' === $success || false !== stripos( $resp, $success ) );
			}
		);
	}
}
