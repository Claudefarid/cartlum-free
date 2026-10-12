<?php
/**
 * Integrations: the outside services Cartlum connects to (SMS provider, Steadfast, Facebook …),
 * each set up once on its own tab. The overview shows every service as a card with its status,
 * and feature tabs that need a service say whether it is connected.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Integrations.
 */
class Cartlum_Admin_Integrations {

	const LAST_TEST = 'cartlum_int_last_test';

	/**
	 * The services: id => { name, for, tab, color, pro, status }. Status: on (connected), off
	 * (not set up), err (the last test failed), pro (needs Cartlum Pro).
	 *
	 * @return array
	 */
	public static function all() {
		$s       = Cartlum_Settings::all();
		$filled  = function ( array $keys ) use ( $s ) {
			foreach ( $keys as $key ) {
				if ( '' === trim( (string) ( $s[ $key ] ?? '' ) ) ) {
					return false;
				}
			}
			return true;
		};
		$gateway = Cartlum_SMS::gateway();
		$fb_pix  = '' !== trim( (string) ( $s['pro_fb_pixel_id'] ?? '' ) ) || ( 'orders' === ( $s['pro_fb_scope'] ?? '' ) && '' !== (string) get_option( 'wc_facebook_pixel_id', '' ) );
		$list    = array(
			'sms'        => array(
				'name'   => $gateway ? sprintf( 'SMS · %s', $gateway->label() ) : __( 'SMS', 'cartlum' ),
				'for'    => __( 'Order SMS, cart reminders and login codes', 'cartlum' ),
				'tab'    => 'int_sms',
				'color'  => '#0f766e',
				'pro'    => false,
				'status' => $gateway ? 'on' : 'off',
			),
			'steadfast'  => array(
				'name'   => 'Steadfast',
				'for'    => __( 'Send parcels and get their delivery status', 'cartlum' ),
				'tab'    => 'int_steadfast',
				'color'  => '#15803d',
				'pro'    => true,
				'status' => $filled( array( 'pro_sf_api_key', 'pro_sf_secret' ) ) ? 'on' : 'off',
			),
			'facebook'   => array(
				'name'   => 'Facebook',
				'for'    => __( 'Purchases and cancellations reported to Meta', 'cartlum' ),
				'tab'    => 'facebook',
				'color'  => '#1877f2',
				'pro'    => true,
				'status' => $fb_pix ? 'on' : 'off',
			),
			'fraudbd'    => array(
				'name'   => 'FraudBD',
				'for'    => __( 'Each customer’s record at all couriers', 'cartlum' ),
				'tab'    => 'int_fraudbd',
				'color'  => '#b45309',
				'pro'    => true,
				'status' => $filled( array( 'pro_courier_key' ) ) ? 'on' : 'off',
			),
			'whatsapp'   => array(
				'name'   => __( 'WhatsApp Cloud API', 'cartlum' ),
				'for'    => __( 'WhatsApp reminders sent by themselves', 'cartlum' ),
				'tab'    => 'int_whatsapp',
				'color'  => '#25a244',
				'pro'    => true,
				'status' => $filled( array( 'pro_wa_phone_id', 'pro_wa_token' ) ) ? 'on' : 'off',
			),
			'elevenlabs' => array(
				'name'   => 'ElevenLabs',
				'for'    => __( 'AI voice calls in Bangla over your phone line', 'cartlum' ),
				'tab'    => 'int_elevenlabs',
				'color'  => '#1f2937',
				'pro'    => true,
				'status' => $filled( array( 'pro_aicall_api_key', 'pro_aicall_agent_id', 'pro_aicall_phone_id' ) ) ? 'on' : 'off',
			),
			'ai'         => array(
				'name'   => __( 'AI provider', 'cartlum' ),
				'for'    => __( 'Writes message drafts', 'cartlum' ),
				'tab'    => 'int_ai',
				'color'  => '#7c3aed',
				'pro'    => true,
				'status' => $filled( array( 'pro_ai_key' ) ) ? 'on' : 'off',
			),
			'webhooks'   => array(
				'name'   => __( 'Webhooks', 'cartlum' ),
				'for'    => __( 'Cart events to Zapier, Make or Pabbly', 'cartlum' ),
				'tab'    => 'webhooks',
				'color'  => '#475569',
				'pro'    => false,
				'status' => $filled( array( 'webhook_url' ) ) ? 'on' : 'off',
			),
		);
		$tests = get_option( self::LAST_TEST, array() );
		foreach ( $list as $id => $item ) {
			if ( $item['pro'] && ! in_array( $item['tab'], Cartlum_Admin_Settings::sections()['cartlum-integrations']['tabs'] ?? array(), true ) ) {
				$list[ $id ]['status'] = 'pro';
			} elseif ( 'on' === $item['status'] && isset( $tests[ $id ] ) && empty( $tests[ $id ]['ok'] ) ) {
				$list[ $id ]['status'] = 'err';
			}
		}
		/**
		 * Filters the integrations shown in Cartlum → Integrations.
		 *
		 * @param array $list Services.
		 */
		return (array) apply_filters( 'cartlum_integrations', $list );
	}

	/**
	 * Remembers how a service's last test went, so its card can warn.
	 *
	 * @param string $id Service.
	 * @param bool   $ok Whether it worked.
	 */
	public static function record_test( $id, $ok ) {
		$tests        = get_option( self::LAST_TEST, array() );
		$tests        = is_array( $tests ) ? $tests : array();
		$tests[ $id ] = array(
			'ok' => (bool) $ok,
			'at' => time(),
		);
		update_option( self::LAST_TEST, $tests, false );
	}

	/**
	 * Status words.
	 *
	 * @return array status => label
	 */
	public static function status_labels() {
		return array(
			'on'  => __( 'Connected', 'cartlum' ),
			'off' => __( 'Not set up', 'cartlum' ),
			'err' => __( 'Last test failed', 'cartlum' ),
			'pro' => 'Pro',
		);
	}

	/**
	 * Where a service is set up.
	 *
	 * @param array $item Service.
	 * @return string
	 */
	public static function url( array $item ) {
		return 'pro' === $item['status'] ? Cartlum_Admin::url( 'cartlum-pro' ) : Cartlum_Admin::url( 'cartlum-integrations', array( 'tab' => $item['tab'] ) );
	}

	/**
	 * Which service a feature tab needs.
	 *
	 * @param string $tab Tab.
	 * @return string Service id, or ''.
	 */
	public static function needed_by( $tab ) {
		$map = array(
			'orders'     => 'sms',
			'automation' => 'sms',
			'account'    => 'sms',
			'winback'    => 'sms',
			'steadfast'  => 'steadfast',
			'courier'    => 'fraudbd',
			'ai_call'    => 'elevenlabs',
			'ai'         => 'ai',
		);
		return $map[ $tab ] ?? '';
	}

	/**
	 * On a feature tab: "Uses Steadfast · Connected · Edit".
	 *
	 * @param string $tab Tab.
	 */
	public static function uses_line( $tab ) {
		$id  = self::needed_by( $tab );
		$all = self::all();
		if ( '' === $id || ! isset( $all[ $id ] ) ) {
			return;
		}
		$item   = $all[ $id ];
		$labels = self::status_labels();
		printf(
			'<div class="cl-uses cl-uses--%1$s"><span>%2$s</span> <span class="cl-pill cl-pill--%1$s">%3$s</span> <a href="%4$s">%5$s</a></div>',
			esc_attr( $item['status'] ),
			/* translators: %s: service name */
			wp_kses( sprintf( __( 'Uses %s', 'cartlum' ), '<strong>' . esc_html( $item['name'] ) . '</strong>' ), array( 'strong' => array() ) ),
			esc_html( $labels[ $item['status'] ] ),
			esc_url( self::url( $item ) ),
			'on' === $item['status'] ? esc_html__( 'Edit', 'cartlum' ) : esc_html__( 'Set up', 'cartlum' )
		);
	}

	/**
	 * The overview: one card per service.
	 */
	public static function page() {
		$labels = self::status_labels();
		?>
		<div class="wrap cartlum">
			<div class="cl-head">
				<div>
					<h1><?php esc_html_e( 'Integrations', 'cartlum' ); ?></h1>
					<p class="cl-lead"><?php esc_html_e( 'The services Cartlum connects to. Set each one up once; every feature that needs it then works.', 'cartlum' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end">
			<?php Cartlum_Admin_Settings::nav( 'cartlum-integrations', '' ); ?>
			<div class="cl-int-grid">
				<?php foreach ( self::all() as $id => $item ) : ?>
					<section class="cl-card cl-int" id="cl-int-<?php echo esc_attr( $id ); ?>">
						<div class="cl-int__head">
							<span class="cl-int__logo" style="background:<?php echo esc_attr( $item['color'] ); ?>" aria-hidden="true"><?php echo esc_html( mb_substr( $item['name'], 0, 1 ) ); ?></span>
							<h2><?php echo esc_html( $item['name'] ); ?></h2>
						</div>
						<p class="cl-int__for"><?php echo esc_html( $item['for'] ); ?></p>
						<div class="cl-int__foot">
							<span class="cl-pill cl-pill--<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( $labels[ $item['status'] ] ); ?></span>
							<a class="button<?php echo 'on' === $item['status'] ? '' : ' button-primary'; ?>" href="<?php echo esc_url( self::url( $item ) ); ?>">
								<?php
								if ( 'pro' === $item['status'] ) {
									esc_html_e( 'See Pro', 'cartlum' );
								} elseif ( 'on' === $item['status'] ) {
									esc_html_e( 'Edit', 'cartlum' );
								} else {
									esc_html_e( 'Set up', 'cartlum' );
								}
								?>
							</a>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
