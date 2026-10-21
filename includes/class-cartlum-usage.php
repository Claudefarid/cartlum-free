<?php
/**
 * Usage sharing, only after the store owner agrees. Once a week Cartlum tells cartlum.com this
 * site's address, store name, admin email, versions and which Cartlum features are switched on,
 * so Cartlum's maker knows who uses it and what to improve. Never customers, orders, carts or keys.
 * Asked once on Today; changed any time in Settings → Privacy. Leaving the plugin can come with an
 * optional reason, sent only when the owner presses "Send".
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Usage.
 */
class Cartlum_Usage {

	const HOOK = 'cartlum_usage_report';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'report' ) );
		if ( self::shared() && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::HOOK );
		}
		if ( is_admin() ) {
			add_action( 'admin_post_cartlum_usage', array( __CLASS__, 'answer' ) );
			add_action( 'wp_ajax_cartlum_leave_reason', array( __CLASS__, 'leave_reason' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'plugins_page' ) );
			add_filter( 'cartlum_settings_save', array( __CLASS__, 'saved' ), 10, 2 );
		}
	}

	/**
	 * Whether the owner agreed.
	 *
	 * @return bool
	 */
	public static function shared() {
		return 'yes' === Cartlum_Settings::get( 'usage_share' );
	}

	/**
	 * Where reports go.
	 *
	 * @return string
	 */
	private static function url() {
		/**
		 * The address usage reports and leaving reasons are sent to.
		 *
		 * @param string $url URL.
		 */
		return (string) apply_filters( 'cartlum_usage_url', 'https://cartlum.com/wp-json/cls/v1/usage' );
	}

	/**
	 * What a report says.
	 *
	 * @param bool $who With the site's address, name and email (only when the owner agreed).
	 * @return array
	 */
	private static function data( $who ) {
		global $wp_version;
		$settings = Cartlum_Settings::all();
		$features = array();
		foreach ( $settings as $key => $value ) {
			// Switches only (…_enabled and the like): never texts, numbers or keys.
			if ( 'yes' === $value && 'usage_share' !== $key ) {
				$features[] = $key;
			}
		}
		$data = array(
			'versions' => array(
				'cartlum'     => CARTLUM_VERSION,
				'cartlum_pro' => defined( 'CARTLUM_PRO_VERSION' ) ? CARTLUM_PRO_VERSION : '',
				'wordpress'   => $wp_version,
				'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'php'         => PHP_VERSION,
			),
			'locale'   => get_locale(),
			'theme'    => get_stylesheet(),
			'features' => $features,
			'sms'      => (string) ( $settings['sms_gateway'] ?? '' ),
		);
		if ( $who ) {
			$data['site']  = home_url( '/' );
			$data['name']  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$data['email'] = (string) get_option( 'admin_email' );
		}
		return $data;
	}

	/**
	 * Sends something to cartlum.com.
	 *
	 * @param string $event report | leave.
	 * @param array  $data  Data.
	 * @return bool Accepted.
	 */
	private static function send( $event, array $data ) {
		$response = wp_safe_remote_post(
			self::url(),
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'event' => $event ) + $data ),
			)
		);
		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * The weekly report.
	 */
	public static function report() {
		if ( self::shared() ) {
			self::send( 'report', self::data( true ) );
		}
	}

	/**
	 * Settings → Privacy saved: start or stop the weekly report.
	 *
	 * @param array  $values Values about to be saved.
	 * @param string $tab    Tab.
	 * @return array
	 */
	public static function saved( $values, $tab ) {
		if ( 'privacy' === $tab ) {
			// An unticked box is a "no" only from someone who had said yes; otherwise Today still asks.
			if ( 'no' === ( $values['usage_share'] ?? '' ) && '' === (string) Cartlum_Settings::get( 'usage_share' ) ) {
				$values['usage_share'] = '';
			}
			self::switched( 'yes' === $values['usage_share'] );
		}
		return $values;
	}

	/**
	 * Starts (and sends the first report) or stops the weekly report.
	 *
	 * @param bool $on Agreed.
	 */
	private static function switched( $on ) {
		wp_clear_scheduled_hook( self::HOOK );
		if ( $on ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::HOOK );
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * The question, on Today until it is answered.
	 */
	public static function ask() {
		if ( '' !== (string) Cartlum_Settings::get( 'usage_share' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$url = function ( $choice ) {
			return wp_nonce_url( add_query_arg( array( 'action' => 'cartlum_usage', 'share' => $choice ), admin_url( 'admin-post.php' ) ), 'cartlum_usage' );
		};
		?>
		<div class="cl-card cl-setup-banner cl-usage" data-cartlum-usage>
			<div>
				<h2><?php esc_html_e( 'Help make Cartlum better?', 'cartlum' ); ?></h2>
				<p class="cl-muted"><?php esc_html_e( 'Once a week, Cartlum would send cartlum.com your site address, store name and admin email, your WordPress, WooCommerce, PHP and Cartlum versions, and which Cartlum features are switched on. Never your customers, orders, carts, messages or keys. We use it to see which features stores use, fix what breaks with your setup, and sometimes email you about Cartlum. You can stop any time in Settings → Privacy.', 'cartlum' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( $url( 'yes' ) ); ?>"><?php esc_html_e( 'Yes, share', 'cartlum' ); ?></a>
			<a class="button" href="<?php echo esc_url( $url( 'no' ) ); ?>"><?php esc_html_e( 'No, thanks', 'cartlum' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Yes or No from the question.
	 */
	public static function answer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_usage' );
		$on                    = isset( $_GET['share'] ) && 'yes' === $_GET['share'];
		$values                = get_option( Cartlum_Settings::OPTION, array() );
		$values                = is_array( $values ) ? $values : array();
		$values['usage_share'] = $on ? 'yes' : 'no';
		Cartlum_Settings::save( $values );
		self::switched( $on );
		wp_safe_redirect(
			Cartlum_Admin::url(
				'cartlum',
				Cartlum_Admin::notice_args( $on ? __( 'Thank you! Cartlum will share usage once a week.', 'cartlum' ) : __( 'OK, nothing will be shared.', 'cartlum' ) )
			)
		);
		exit;
	}

	/**
	 * The "Why are you leaving?" box on the Plugins page.
	 *
	 * @param string $hook Admin page.
	 */
	public static function plugins_page( $hook ) {
		if ( 'plugins.php' !== $hook || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		wp_enqueue_script( 'cartlum-leave', CARTLUM_URL . 'assets/js/leave.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-leave',
			'cartlumLeave',
			array(
				'plugin'  => plugin_basename( CARTLUM_FILE ),
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cartlum_leave' ),
				'title'   => __( 'Why are you leaving Cartlum?', 'cartlum' ),
				'note'    => self::shared()
					? __( 'Optional. Your answer is sent to cartlum.com with your site address, as you agreed to share.', 'cartlum' )
					: __( 'Optional. Only your answer and your WordPress, WooCommerce, PHP and Cartlum versions are sent to cartlum.com, not your site address.', 'cartlum' ),
				'reasons' => array(
					'temporary' => __( 'I only need it for a while', 'cartlum' ),
					'broken'    => __( 'It doesn’t work with my theme or checkout', 'cartlum' ),
					'hard'      => __( 'It was hard to set up', 'cartlum' ),
					'missing'   => __( 'It’s missing something I need', 'cartlum' ),
					'other'     => __( 'I found something better', 'cartlum' ),
				),
				'more'    => __( 'Anything you want to tell us? (optional)', 'cartlum' ),
				'send'    => __( 'Send and deactivate', 'cartlum' ),
				'skip'    => __( 'Skip and deactivate', 'cartlum' ),
			)
		);
	}

	/**
	 * Sends the reason the owner chose (they pressed "Send").
	 */
	public static function leave_reason() {
		check_ajax_referer( 'cartlum_leave', 'nonce' );
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}
		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$text   = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		// Without the owner's agreement: the reason and the versions, nothing about the site.
		$data           = self::shared() ? self::data( true ) : array( 'versions' => self::data( false )['versions'] );
		$data['reason'] = $reason;
		$data['text']   = mb_substr( $text, 0, 500 );
		self::send( 'leave', $data );
		wp_send_json_success();
	}

	/**
	 * Plugin deactivated: stop the weekly report.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
