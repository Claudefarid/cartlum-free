<?php
/**
 * First-run setup: a few short steps that set up the things almost every shop needs (SMS
 * provider, Easy checkout, order SMS, buy buttons, and with Cartlum Pro the courier and
 * Facebook). Each step shows existing settings and saves them the normal way; any step can be
 * skipped. Offered on Today until it is finished or dismissed.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Setup.
 */
class Cartlum_Admin_Setup {

	const DONE = 'cartlum_setup_done';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cartlum_setup_skip', array( __CLASS__, 'skip' ) );
	}

	/**
	 * The steps: slug => { title, text, keys }. Pro steps only when Pro offers them.
	 *
	 * @return array
	 */
	public static function steps() {
		$sms_keys = array( 'sms_gateway' );
		foreach ( Cartlum_SMS::gateways() as $gateway ) {
			$sms_keys = array_merge( $sms_keys, array_keys( $gateway->fields() ) );
		}
		$steps = array(
			'sms'      => array(
				'title' => __( 'SMS', 'cartlum' ),
				'text'  => __( 'Choose your SMS provider and paste its keys. Every SMS Cartlum sends — order messages, reminders, login codes — goes through it.', 'cartlum' ),
				'keys'  => $sms_keys,
			),
			'checkout' => array(
				'title' => __( 'Easy checkout', 'cartlum' ),
				'text'  => __( 'A short one-page checkout: mobile number first, then name, address and district. Turn on Preview to try it yourself before customers see it.', 'cartlum' ),
				'keys'  => array( 'checkout_enabled', 'checkout_preview', 'checkout_email' ),
			),
			'orders'   => array(
				'title' => __( 'Order SMS', 'cartlum' ),
				'text'  => __( 'One SMS to the customer when the order goes through, and one to you with the products.', 'cartlum' ),
				'keys'  => array( 'order_sms_enabled', 'order_sms_placed', 'order_sms_placed_text', 'order_sms_admin', 'order_sms_admin_phone' ),
			),
			'buttons'  => array(
				'title' => __( 'Buy buttons', 'cartlum' ),
				'text'  => __( 'Buttons under each product: Buy now, Order on WhatsApp and Call to order.', 'cartlum' ),
				'keys'  => array( 'buy_now_enabled', 'whatsapp_button', 'whatsapp_number', 'call_button', 'call_number' ),
			),
			'courier'  => array(
				'title' => 'Steadfast',
				'text'  => __( 'Paste your Steadfast API key and secret key to send parcels from WooCommerce.', 'cartlum' ),
				'keys'  => array( 'pro_sf_api_key', 'pro_sf_secret' ),
			),
			'facebook' => array(
				'title' => 'Facebook',
				'text'  => __( 'Report purchases to Meta. With Meta for WooCommerce installed, choose “Orders only” and leave the pixel ID and token empty.', 'cartlum' ),
				'keys'  => array( 'pro_fb_scope', 'pro_fb_purchase', 'pro_fb_pixel_id', 'pro_fb_token' ),
			),
		);
		// A step shows only when its settings exist (Pro steps need Pro).
		foreach ( $steps as $slug => $step ) {
			if ( ! self::fields( $slug, $step ) ) {
				unset( $steps[ $slug ] );
			}
		}
		return $steps;
	}

	/**
	 * A step's fields, taken from wherever they live.
	 *
	 * @param string     $slug Step.
	 * @param array|null $step Step (looked up when null).
	 * @return array
	 */
	public static function fields( $slug, $step = null ) {
		if ( null === $step ) {
			$steps = self::steps();
			$step  = $steps[ $slug ] ?? null;
		}
		if ( ! $step ) {
			return array();
		}
		$out = array();
		foreach ( array_keys( Cartlum_Admin_Settings::tabs() ) as $tab ) {
			foreach ( Cartlum_Admin_Settings::fields( $tab ) as $key => $field ) {
				if ( in_array( $key, $step['keys'], true ) ) {
					$out[ $key ] = $field;
				}
			}
		}
		// In the step's own order.
		return array_replace( array_intersect_key( array_flip( $step['keys'] ), $out ), $out );
	}

	/**
	 * Whether the wizard is still offered.
	 *
	 * @return bool
	 */
	public static function pending() {
		return ! get_option( self::DONE );
	}

	/**
	 * The wizard's address.
	 *
	 * @param string $step Step ('' = first).
	 * @return string
	 */
	public static function url( $step = '' ) {
		return Cartlum_Admin::url( 'cartlum-setup', '' !== $step ? array( 'step' => $step ) : array() );
	}

	/**
	 * Where saving a step goes next.
	 *
	 * @param string $slug Step just saved.
	 * @return string
	 */
	public static function next_url( $slug ) {
		$keys = array_keys( self::steps() );
		$i    = array_search( $slug, $keys, true );
		if ( false === $i || ! isset( $keys[ $i + 1 ] ) ) {
			update_option( self::DONE, time(), false );
			return Cartlum_Admin::url( 'cartlum', Cartlum_Admin::notice_args( __( 'Cartlum is set up. You can change anything later from the menu.', 'cartlum' ) ) );
		}
		return self::url( $keys[ $i + 1 ] );
	}

	/**
	 * "Skip setup".
	 */
	public static function skip() {
		check_admin_referer( 'cartlum_setup_skip' );
		if ( current_user_can( Cartlum_Admin::CAP ) ) {
			update_option( self::DONE, time(), false );
		}
		wp_safe_redirect( Cartlum_Admin::url( 'cartlum' ) );
		exit;
	}

	/**
	 * The banner on Today.
	 */
	public static function banner() {
		if ( ! self::pending() ) {
			return;
		}
		?>
		<div class="cl-card cl-setup-banner">
			<div>
				<h2><?php esc_html_e( 'Set up Cartlum in 5 minutes', 'cartlum' ); ?></h2>
				<p class="cl-muted"><?php esc_html_e( 'SMS, Easy checkout, order SMS and buy buttons — step by step. You can skip any step.', 'cartlum' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Start', 'cartlum' ); ?></a>
			<a class="cl-muted" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cartlum_setup_skip' ), 'cartlum_setup_skip' ) ); ?>"><?php esc_html_e( 'Not now', 'cartlum' ); ?></a>
		</div>
		<?php
	}

	/**
	 * The wizard page.
	 */
	public static function page() {
		$steps = self::steps();
		$keys  = array_keys( $steps );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$slug   = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : '';
		$slug   = isset( $steps[ $slug ] ) ? $slug : $keys[0];
		$step   = $steps[ $slug ];
		$index  = array_search( $slug, $keys, true );
		$values = Cartlum_Settings::all();
		?>
		<div class="wrap cartlum cl-setup">
			<div class="cl-head">
				<div>
					<h1><?php esc_html_e( 'Set up Cartlum', 'cartlum' ); ?></h1>
					<p class="cl-lead">
						<?php
						/* translators: 1: step number, 2: number of steps */
						echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'cartlum' ), $index + 1, count( $keys ) ) );
						?>
					</p>
				</div>
			</div>
			<hr class="wp-header-end">
			<ol class="cl-steps">
				<?php foreach ( $steps as $s => $info ) : ?>
					<li class="<?php echo esc_attr( $s === $slug ? 'is-current' : ( array_search( $s, $keys, true ) < $index ? 'is-done' : '' ) ); ?>">
						<a href="<?php echo esc_url( self::url( $s ) ); ?>"<?php echo $s === $slug ? ' aria-current="step"' : ''; ?>><?php echo esc_html( $info['title'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cl-settings">
				<?php wp_nonce_field( 'cartlum_save_settings' ); ?>
				<input type="hidden" name="action" value="cartlum_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( 'setup_' . $slug ); ?>">
				<div class="cl-card cl-fields">
					<h2><?php echo esc_html( $step['title'] ); ?></h2>
					<p class="cl-help"><?php echo esc_html( $step['text'] ); ?></p>
					<?php
					foreach ( self::fields( $slug ) as $key => $field ) {
						Cartlum_Admin_Settings::render_row( $key, $field, $values[ $key ] ?? '' );
					}
					?>
				</div>
				<div class="cl-setup__nav">
					<?php if ( $index > 0 ) : ?>
						<a class="button" href="<?php echo esc_url( self::url( $keys[ $index - 1 ] ) ); ?>"><?php esc_html_e( 'Back', 'cartlum' ); ?></a>
					<?php endif; ?>
					<a class="cl-muted cl-setup__skip" href="<?php echo esc_url( isset( $keys[ $index + 1 ] ) ? self::url( $keys[ $index + 1 ] ) : wp_nonce_url( admin_url( 'admin-post.php?action=cartlum_setup_skip' ), 'cartlum_setup_skip' ) ); ?>"><?php esc_html_e( 'Skip this step', 'cartlum' ); ?></a>
					<button type="submit" class="button button-primary"><?php echo esc_html( isset( $keys[ $index + 1 ] ) ? __( 'Save and continue', 'cartlum' ) : __( 'Save and finish', 'cartlum' ) ); ?></button>
				</div>
			</form>
			<?php if ( 'sms' === $slug && Cartlum_SMS::gateway() ) : ?>
				<div class="cl-card cl-test">
					<h2><?php esc_html_e( 'Send a test SMS', 'cartlum' ); ?></h2>
					<p class="cl-muted"><?php esc_html_e( 'Under Integrations → SMS you can send yourself a test message at any time.', 'cartlum' ); ?> <a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-integrations', array( 'tab' => 'int_sms' ) ) ); ?>"><?php esc_html_e( 'Open', 'cartlum' ); ?></a></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
