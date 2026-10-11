<?php
/**
 * Webhooks: posts cart events as JSON to a URL, for Zapier, Make, Pabbly, n8n or the store's own
 * system. Sent in the background so shoppers never wait for it. Each request is signed with an
 * HMAC of the body (X-Cartlum-Signature) so the receiver can check it came from this store.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Webhooks.
 */
class Cartlum_Webhooks {

	const SECRET = 'cartlum_webhook_secret';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'cartlum_webhook', array( __CLASS__, 'deliver' ), 10, 3 );
		if ( '' === self::url() ) {
			return;
		}
		add_action( 'cartlum_cart_captured', array( __CLASS__, 'on_captured' ) );
		add_action( 'cartlum_cart_abandoned', array( __CLASS__, 'on_abandoned' ) );
		add_action( 'cartlum_cart_closed', array( __CLASS__, 'on_closed' ), 10, 2 );
	}

	/**
	 * Events a store can send: setting => label.
	 *
	 * @return array
	 */
	public static function events() {
		return array(
			'captured'  => __( 'Cart saved (a phone number or email was typed)', 'cartlum' ),
			'abandoned' => __( 'Cart abandoned', 'cartlum' ),
			'recovered' => __( 'Cart recovered (ordered after being contacted)', 'cartlum' ),
		);
	}

	/**
	 * The webhook URL, or ''.
	 *
	 * @return string
	 */
	public static function url() {
		return trim( (string) Cartlum_Settings::get( 'webhook_url' ) );
	}

	/**
	 * The signing secret, created on first use.
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = (string) get_option( self::SECRET );
		if ( '' === $secret ) {
			$secret = wp_generate_password( 32, false, false );
			update_option( self::SECRET, $secret, false );
		}
		return $secret;
	}

	/**
	 * Cart saved.
	 *
	 * @param int $cart_id Cart.
	 */
	public static function on_captured( $cart_id ) {
		self::queue( 'captured', $cart_id );
	}

	/**
	 * Cart abandoned.
	 *
	 * @param int $cart_id Cart.
	 */
	public static function on_abandoned( $cart_id ) {
		self::queue( 'abandoned', $cart_id );
	}

	/**
	 * Cart closed by an order.
	 *
	 * @param int    $cart_id Cart.
	 * @param string $status  recovered|converted.
	 */
	public static function on_closed( $cart_id, $status ) {
		if ( 'recovered' === $status ) {
			self::queue( 'recovered', $cart_id );
		}
	}

	/**
	 * Queues a delivery when the event is switched on.
	 *
	 * @param string $event   Event.
	 * @param int    $cart_id Cart.
	 */
	private static function queue( $event, $cart_id ) {
		if ( 'yes' !== Cartlum_Settings::get( 'webhook_on_' . $event ) ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'cartlum_webhook', array( $event, (int) $cart_id, 1 ), 'cartlum' );
		} else {
			self::deliver( $event, $cart_id, 1 );
		}
	}

	/**
	 * The JSON body for a cart.
	 *
	 * @param string $event Event.
	 * @param object $cart  Row.
	 * @return array
	 */
	public static function payload( $event, $cart ) {
		$items = array();
		foreach ( Cartlum_Carts::items( $cart ) as $item ) {
			$items[] = array(
				'product_id'   => (int) ( $item['product_id'] ?? 0 ),
				'variation_id' => (int) ( $item['variation_id'] ?? 0 ),
				'name'         => (string) ( $item['name'] ?? '' ),
				'quantity'     => (int) ( $item['quantity'] ?? 0 ),
				'line_total'   => (float) ( $item['line_total'] ?? 0 ),
			);
		}
		$data = array(
			'event'       => $event,
			'sent_at'     => gmdate( 'c' ),
			'store'       => array(
				'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'  => home_url( '/' ),
			),
			'cart'        => array(
				'id'           => (int) $cart->id,
				'status'       => (string) $cart->status,
				'source'       => (string) $cart->source,
				'total'        => (float) $cart->cart_total,
				'currency'     => (string) $cart->currency,
				'items'        => $items,
				'items_text'   => Cartlum_Messages::items_text( $cart ),
				'link'         => Cartlum_Restore::link( $cart ),
				'coupon'       => (string) $cart->coupon_code,
				'created_at'   => self::iso( $cart->created_at ),
				'abandoned_at' => self::iso( $cart->abandoned_at ?? '' ),
			),
			'customer'    => array(
				'name'          => (string) $cart->name,
				'first_name'    => Cartlum_Messages::safe_name( (string) $cart->name ),
				'phone'         => '' !== (string) $cart->phone ? Cartlum_OTP::shown( $cart->phone ) : '',
				'phone_e164'    => '' !== (string) $cart->phone ? '+' . $cart->phone : '',
				'email'         => (string) $cart->email,
				'unsubscribed'  => 'optout' === $cart->status,
				'unsubscribe'   => Cartlum_Restore::optout_link( $cart ),
			),
			'order'       => $cart->order_id ? array(
				'id'    => (int) $cart->order_id,
				'total' => (float) $cart->order_total,
			) : null,
		);

		/**
		 * Filters a webhook's JSON body.
		 *
		 * @param array  $data  Body.
		 * @param string $event Event.
		 * @param object $cart  Cart row.
		 */
		return apply_filters( 'cartlum_webhook_payload', $data, $event, $cart );
	}

	/**
	 * UTC database time as ISO 8601, or ''.
	 *
	 * @param string $utc Time.
	 * @return string
	 */
	private static function iso( $utc ) {
		return $utc ? gmdate( 'c', strtotime( $utc . ' UTC' ) ) : '';
	}

	/**
	 * Sends a body to the URL.
	 *
	 * @param array $data Body.
	 * @return true|WP_Error
	 */
	public static function post( array $data ) {
		$url = self::url();
		if ( '' === $url || ! wp_http_validate_url( $url ) || ! Cartlum_Guard::is_public_url( $url ) ) {
			return new WP_Error( 'cartlum_webhook_url', __( 'The webhook URL must be a public internet address starting with https:// or http://.', 'cartlum' ) );
		}
		$body     = (string) wp_json_encode( $data );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'        => 'application/json',
					'User-Agent'          => 'Cartlum/' . CARTLUM_VERSION,
					'X-Cartlum-Event'     => (string) $data['event'],
					'X-Cartlum-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, self::secret() ),
				),
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'cartlum_webhook_http', sprintf( __( 'The webhook URL answered with error %d.', 'cartlum' ), $code ) );
		}
		return true;
	}

	/**
	 * Background delivery. A failure is retried twice (after 5 and 30 minutes) and noted on the cart.
	 *
	 * @param string $event   Event.
	 * @param int    $cart_id Cart.
	 * @param int    $attempt Attempt number.
	 */
	public static function deliver( $event, $cart_id, $attempt = 1 ) {
		$cart = Cartlum_Carts::get( (int) $cart_id );
		if ( ! $cart || '' === self::url() ) {
			return;
		}
		$result = self::post( self::payload( (string) $event, $cart ) );
		if ( true === $result ) {
			Cartlum_Events::add(
				$cart->id,
				'webhook_sent',
				array(
					'user_id' => 0,
					'meta'    => array( 'event' => $event ),
				)
			);
			return;
		}
		$waits = array(
			1 => 5 * MINUTE_IN_SECONDS,
			2 => 30 * MINUTE_IN_SECONDS,
		);
		if ( isset( $waits[ $attempt ] ) && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $waits[ $attempt ], 'cartlum_webhook', array( $event, (int) $cart->id, $attempt + 1 ), 'cartlum' );
			return;
		}
		Cartlum_Events::add(
			$cart->id,
			'webhook_failed',
			array(
				'user_id' => 0,
				'message' => $result->get_error_message(),
				'meta'    => array( 'event' => $event ),
			)
		);
	}

	/**
	 * Settings → Webhooks: the secret and a test button.
	 *
	 * @param string $tab Tab.
	 */
	public static function settings_extra( $tab ) {
		if ( 'webhooks' !== $tab ) {
			return;
		}
		$test = wp_nonce_url( admin_url( 'admin-post.php?action=cartlum_webhook_test' ), 'cartlum_webhook_test' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cartlum-webhook-secret"><?php esc_html_e( 'Signing secret', 'cartlum' ); ?></label></th>
				<td>
					<input type="text" id="cartlum-webhook-secret" class="regular-text code" readonly value="<?php echo esc_attr( self::secret() ); ?>" onfocus="this.select()">
					<p class="description"><?php esc_html_e( 'Each request has an X-Cartlum-Signature header: “sha256=” and the HMAC-SHA256 of the body with this secret. Check it in your receiver if you want to be sure a request came from your store.', 'cartlum' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Test', 'cartlum' ); ?></th>
				<td>
					<?php if ( '' === self::url() ) : ?>
						<p class="description"><?php esc_html_e( 'Save a webhook URL first, then send a test.', 'cartlum' ); ?></p>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( $test ); ?>"><?php esc_html_e( 'Send a test event', 'cartlum' ); ?></a>
						<p class="description"><?php esc_html_e( 'Sends a sample abandoned cart to the saved URL so you can map its fields in Zapier, Make or Pabbly.', 'cartlum' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Sends a sample event.
	 */
	public static function test() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_webhook_test' );
		$product = current( wc_get_products( array( 'limit' => 1 ) ) );
		$items   = $product ? array(
			array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
				'quantity'     => 1,
				'name'         => $product->get_name(),
				'line_total'   => (float) $product->get_price(),
			),
		) : array();
		$cart    = (object) array(
			'id'            => 0,
			'token'         => 'test',
			'status'        => 'abandoned',
			'source'        => 'classic',
			'name'          => 'Test Customer',
			'phone'         => '8801700000000',
			'email'         => 'test@example.com',
			'cart_total'    => $product ? (float) $product->get_price() : 0,
			'currency'      => get_woocommerce_currency(),
			'cart_contents' => wp_json_encode( $items ),
			'coupon_code'   => '',
			'order_id'      => 0,
			'order_total'   => 0,
			'created_at'    => Cartlum_Carts::now(),
			'abandoned_at'  => Cartlum_Carts::now(),
		);
		$data         = self::payload( 'abandoned', $cart );
		$data['test'] = true;
		$result       = self::post( $data );
		$notice       = true === $result
			? Cartlum_Admin::notice_args( __( 'Test event sent. Check your Zapier, Make or Pabbly scenario for it.', 'cartlum' ) )
			: Cartlum_Admin::notice_args( $result->get_error_message(), 'error' );
		wp_safe_redirect( Cartlum_Admin::url( 'cartlum-settings', array_merge( array( 'tab' => 'webhooks' ), $notice ) ) );
		exit;
	}
}
