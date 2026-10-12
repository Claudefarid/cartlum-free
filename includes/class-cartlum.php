<?php
/**
 * Main plugin class: loads the parts and wires them together.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

require_once CARTLUM_DIR . 'includes/class-cartlum-install.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-settings.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-phone.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-guard.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-otp.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-carts.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-events.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-capture.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-tracker.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-restore.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-coupons.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-messages.php';
require_once CARTLUM_DIR . 'includes/gateways/abstract-cartlum-sms-gateway.php';
require_once CARTLUM_DIR . 'includes/gateways/class-cartlum-sms-esms.php';
require_once CARTLUM_DIR . 'includes/gateways/class-cartlum-sms-twilio.php';
require_once CARTLUM_DIR . 'includes/gateways/class-cartlum-sms-custom.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-sms.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-email.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-whatsapp.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-privacy.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-flow.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-checkout.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-buy-now.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-product-buttons.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-phone-login.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-order-tracking.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-order-sms.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-protection.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-popup.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-webhooks.php';
require_once CARTLUM_DIR . 'includes/class-cartlum-speed.php';

/**
 * Cartlum.
 */
final class Cartlum {

	/**
	 * Single instance.
	 *
	 * @var Cartlum|null
	 */
	private static $instance = null;

	/**
	 * The instance.
	 *
	 * @return Cartlum
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Created on plugins_loaded. Everything depends on WooCommerce, so do nothing without it, and
	 * start the parts on init: the default settings include translated text, and WordPress
	 * (6.7+) requires translations to be loaded no earlier than init.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_woocommerce_missing' ) );
			return;
		}
		add_action( 'init', array( $this, 'boot' ), 0 );
	}

	/**
	 * Starts the parts.
	 */
	public function boot() {
		// "Language for customers": everything shoppers see (and their SMS) in that language, while
		// wp-admin keeps the site's. WordPress keeps translations per locale, so the chosen file is
		// loaded for the locale in use. Read straight from the option: settings are not loaded yet.
		$saved    = get_option( 'cartlum_settings', array() );
		$customer = is_array( $saved ) && isset( $saved['customer_language'] ) ? (string) $saved['customer_language'] : '';
		$customer = preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $customer ) ? $customer : '';
		$mofile   = CARTLUM_DIR . 'languages/cartlum-' . $customer . '.mo';
		// "Cartlum in Bangla for me": Cartlum's own admin screens in the language this user chose.
		$admin_lang = is_admin() && ! wp_doing_cron() ? Cartlum_Settings::admin_language() : '';
		$admin_mo   = CARTLUM_DIR . 'languages/cartlum-' . $admin_lang . '.mo';
		if ( '' !== $admin_lang && is_readable( $admin_mo ) ) {
			load_textdomain( 'cartlum', $admin_mo, determine_locale() );
		} elseif ( '' !== $customer && ( ! is_admin() || wp_doing_cron() ) && is_readable( $mofile ) ) {
			load_textdomain( 'cartlum', $mofile, determine_locale() );
		} else {
			// The bundled translations (e.g. Bangla) for the site language. WordPress.org language
			// packs, once available, are loaded first by WordPress and take precedence.
			load_plugin_textdomain( 'cartlum', false, dirname( plugin_basename( CARTLUM_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- ships its own Bangla translation.
		}
		Cartlum_Install::maybe_upgrade();

		Cartlum_Capture::init();
		Cartlum_Tracker::init();
		Cartlum_Restore::init();
		Cartlum_Privacy::init();
		Cartlum_Flow::init();
		Cartlum_Checkout::init();
		Cartlum_Buy_Now::init();
		Cartlum_Product_Buttons::init();
		Cartlum_Phone_Login::init();
		Cartlum_Order_Tracking::init();
		Cartlum_Order_SMS::init();
		Cartlum_Protection::init();
		Cartlum_Popup::init();
		Cartlum_Webhooks::init();
		Cartlum_Speed::init();

		if ( is_admin() ) {
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-settings.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-actions.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-protection.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-fraud.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-cancel-reasons.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-integrations.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-today.php';
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-admin-setup.php';
			Cartlum_Admin::init();
			Cartlum_Admin_Settings::init();
			Cartlum_Admin_Today::init();
			Cartlum_Admin_Setup::init();
			Cartlum_Admin_Actions::init();
			Cartlum_Admin_Protection::init();
			Cartlum_Cancel_Reasons::init();
			add_action( 'cartlum_settings_after_fields', array( 'Cartlum_Email', 'preview_link' ) );
			add_action( 'admin_post_cartlum_email_preview', array( 'Cartlum_Email', 'preview' ) );
			add_action( 'cartlum_settings_after_fields', array( 'Cartlum_Webhooks', 'settings_extra' ) );
			add_action( 'admin_post_cartlum_webhook_test', array( 'Cartlum_Webhooks', 'test' ) );
		}

		/**
		 * Fires once Cartlum has started. Add-ons (Cartlum Pro) hook in here.
		 */
		do_action( 'cartlum_loaded' );
	}

	/**
	 * Shown when WooCommerce is not active.
	 */
	public function notice_woocommerce_missing() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Cartlum needs WooCommerce to be installed and active.', 'cartlum' ) . '</p></div>';
	}
}
