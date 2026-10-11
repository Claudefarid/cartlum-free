<?php
/**
 * Admin menu and screens: Dashboard, Carts (list + single cart), Settings.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin.
 */
class Cartlum_Admin {

	const CAP = 'manage_woocommerce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'flash' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CARTLUM_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'Cartlum', 'Cartlum', self::CAP, 'cartlum', array( __CLASS__, 'page_dashboard' ), 'dashicons-cart', 56 );
		add_submenu_page( 'cartlum', __( 'Dashboard', 'cartlum' ), __( 'Dashboard', 'cartlum' ), self::CAP, 'cartlum', array( __CLASS__, 'page_dashboard' ) );
		// One page per job; each owns its settings tabs (Cartlum_Admin_Settings::sections()).
		foreach ( Cartlum_Admin_Settings::sections() as $slug => $section ) {
			if ( ! $section['tabs'] && '' === $section['view'] ) {
				continue; // A Pro page without Pro.
			}
			$hook = add_submenu_page( 'cartlum', $section['label'], $section['label'], self::CAP, $slug, array( 'Cartlum_Admin_Settings', 'page' ) );
			if ( 'cartlum-carts' === $slug ) {
				add_action( 'load-' . $hook, array( 'Cartlum_Admin_Actions', 'bulk' ) );
			} elseif ( 'cartlum-settings' === $slug ) {
				add_action( 'load-' . $hook, array( 'Cartlum_Admin_Settings', 'redirect_old_links' ) );
			}
		}
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=cartlum-settings' ) ) . '">' . esc_html__( 'Settings', 'cartlum' ) . '</a>' );
		return $links;
	}

	/**
	 * CSS/JS on our screens only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'cartlum' ) ) {
			return;
		}
		wp_enqueue_style( 'cartlum-admin', CARTLUM_URL . 'assets/css/admin.css', array(), CARTLUM_VERSION );
		wp_enqueue_script( 'cartlum-admin', CARTLUM_URL . 'assets/js/admin.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-admin',
			'cartlumAdmin',
			array(
				'copied'  => __( 'Copied', 'cartlum' ),
				'sending' => __( 'Sending…', 'cartlum' ),
				'chars'   => __( 'characters', 'cartlum' ),
				'unicode' => __( 'unicode', 'cartlum' ),
			)
		);
	}

	/**
	 * URL of an admin screen.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		// Settings tabs live on the page for their job (Messages, Fake Orders …): old-style
		// "cartlum-settings + tab" links go there.
		if ( 'cartlum-settings' === $page && ! empty( $args['tab'] ) && class_exists( 'Cartlum_Admin_Settings' ) ) {
			$page = Cartlum_Admin_Settings::page_for_tab( (string) $args['tab'] );
		}
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Query arguments for a notice after a redirect. The text is signed, so a link from elsewhere
	 * cannot make a Cartlum screen show words of its choosing.
	 *
	 * @param string $message Text.
	 * @param string $type    success|error.
	 * @return array
	 */
	public static function notice_args( $message, $type = 'success' ) {
		$type = 'error' === $type ? 'error' : 'success';
		return array(
			'cartlum_msg'  => rawurlencode( $message ),
			'cartlum_type' => $type,
			'cartlum_sig'  => self::notice_sig( $message, $type ),
		);
	}

	/**
	 * Signature for a notice, bound to the current user.
	 *
	 * @param string $message Text.
	 * @param string $type    Type.
	 * @return string
	 */
	private static function notice_sig( $message, $type ) {
		return substr( wp_hash( 'cartlum_notice|' . get_current_user_id() . '|' . $type . '|' . $message ), 0, 20 );
	}

	/**
	 * Notice after an action redirect (see notice_args()).
	 */
	public static function flash() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- display only; the signature is checked against the raw text, which is escaped when shown.
		if ( empty( $_GET['cartlum_msg'] ) || empty( $_GET['cartlum_sig'] ) ) {
			return;
		}
		$type = ( isset( $_GET['cartlum_type'] ) && 'error' === $_GET['cartlum_type'] ) ? 'error' : 'success';
		$raw  = (string) wp_unslash( $_GET['cartlum_msg'] );
		$sig  = sanitize_key( wp_unslash( $_GET['cartlum_sig'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! hash_equals( self::notice_sig( $raw, $type ), $sig ) ) {
			return;
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( sanitize_text_field( $raw ) ) );
	}

	/**
	 * Status label and colour.
	 *
	 * @param string $status Status.
	 * @return string HTML.
	 */
	public static function badge( $status ) {
		$labels = array(
			'active'    => __( 'Checking out', 'cartlum' ),
			'abandoned' => __( 'Abandoned', 'cartlum' ),
			'recovered' => __( 'Recovered', 'cartlum' ),
			'converted' => __( 'Ordered', 'cartlum' ),
			'lost'      => __( 'Lost', 'cartlum' ),
			'optout'    => __( 'Unsubscribed', 'cartlum' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		return '<span class="cl-badge cl-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Formatted money.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @return string HTML from wc_price.
	 */
	public static function money( $amount, $currency = '' ) {
		return wc_price( $amount, $currency ? array( 'currency' => $currency ) : array() );
	}

	/**
	 * Edit link for an order, correct with or without WooCommerce's order tables (HPOS).
	 *
	 * @param int $order_id Order.
	 * @return string
	 */
	public static function order_url( $order_id ) {
		$order = wc_get_order( $order_id );
		return $order ? $order->get_edit_order_url() : '';
	}

	/**
	 * "5 minutes ago" from a UTC MySQL date.
	 *
	 * @param string|null $utc Date.
	 * @return string
	 */
	public static function ago( $utc ) {
		if ( ! $utc ) {
			return '—';
		}
		/* translators: %s: human time difference */
		return sprintf( __( '%s ago', 'cartlum' ), human_time_diff( strtotime( $utc . ' UTC' ), time() ) );
	}

	/**
	 * Dashboard screen.
	 */
	public static function page_dashboard() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;

		$stats    = Cartlum_Carts::stats( $days );
		$previous = Cartlum_Carts::stats( $days, $days );
		$channels = Cartlum_Events::channel_stats( $days );
		$products = Cartlum_Carts::top_products( $days, 6 );
		$open     = Cartlum_Carts::query(
			array(
				'status'   => 'abandoned',
				'per_page' => 8,
				'orderby'  => 'abandoned_at',
			)
		);
		// What has happened with each person so far, so staff do not call someone twice.
		$contacts = Cartlum_Events::last_contacts( wp_list_pluck( $open['rows'], 'id' ) );
		$next     = array();
		if ( Cartlum_Flow::enabled() ) {
			foreach ( $open['rows'] as $row ) {
				$next[ (int) $row->id ] = Cartlum_Flow::next_run( $row );
			}
		}
		require CARTLUM_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Carts screen: the list, or one cart when ?cart=ID.
	 */
	public static function page_carts() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$cart_id = isset( $_GET['cart'] ) ? absint( $_GET['cart'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $cart_id ) {
			$cart = Cartlum_Carts::get( $cart_id );
			if ( ! $cart ) {
				echo '<div class="wrap"><p>' . esc_html__( 'Cart not found.', 'cartlum' ) . '</p></div>';
				return;
			}
			$events = Cartlum_Events::for_cart( $cart_id );
			require CARTLUM_DIR . 'includes/admin/views/cart.php';
			return;
		}
		require_once CARTLUM_DIR . 'includes/admin/class-cartlum-carts-table.php';
		$table = new Cartlum_Carts_Table();
		$table->prepare_items();
		require CARTLUM_DIR . 'includes/admin/views/carts.php';
	}
}
