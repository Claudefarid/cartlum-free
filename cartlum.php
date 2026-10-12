<?php
/**
 * Plugin Name:          Cartlum – Abandoned Cart Recovery for WooCommerce
 * Plugin URI:           https://cartlum.com/
 * Description:          Recover abandoned WooCommerce carts by SMS, phone call, WhatsApp and email. Captures the cart as soon as the customer types a phone number, gives every cart a one-tap restore link, and tracks exactly how much revenue each channel brings back.
 * Version:              0.24.0
 * Author:               Cartlum
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          cartlum
 * Domain Path:          /languages
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

define( 'CARTLUM_VERSION', '0.24.0' );
define( 'CARTLUM_DB_VERSION', '2' );
define( 'CARTLUM_FILE', __FILE__ );
define( 'CARTLUM_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARTLUM_URL', plugin_dir_url( __FILE__ ) );

require_once CARTLUM_DIR . 'includes/class-cartlum.php';

register_activation_hook( __FILE__, array( 'Cartlum_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Cartlum_Install', 'deactivate' ) );

// Declare compatibility with WooCommerce's order tables (HPOS) and the block checkout.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * The plugin instance.
 *
 * @return Cartlum
 */
function cartlum() {
	return Cartlum::instance();
}

add_action( 'plugins_loaded', 'cartlum', 20 );
