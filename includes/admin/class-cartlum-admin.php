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
		add_action( 'cartlum_settings_before_fields', array( __CLASS__, 'pro_hint' ) );
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
		// What Cartlum Pro adds, only while Pro is not installed.
		if ( ! defined( 'CARTLUM_PRO_VERSION' ) ) {
			add_submenu_page( 'cartlum', __( 'Cartlum Pro', 'cartlum' ), __( 'Pro', 'cartlum' ), self::CAP, 'cartlum-pro', array( __CLASS__, 'page_pro' ) );
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

	/**
	 * Where to get Cartlum Pro.
	 *
	 * @return string
	 */
	public static function pro_url() {
		/**
		 * Filters the "Get Cartlum Pro" link.
		 *
		 * @param string $url URL.
		 */
		return (string) apply_filters( 'cartlum_pro_url', 'https://github.com/Claudefarid/cartlum-free#cartlum-pro' );
	}

	/**
	 * One quiet line on the tabs where Pro adds something, while Pro is not installed.
	 *
	 * @param string $tab Tab.
	 */
	public static function pro_hint( $tab ) {
		if ( defined( 'CARTLUM_PRO_VERSION' ) ) {
			return;
		}
		$hints = array(
			'automation' => __( 'With Cartlum Pro: as many reminder steps as you like (SMS, email, automatic WhatsApp, AI call), A/B tests and smart coupons.', 'cartlum' ),
			'protection' => __( 'With Cartlum Pro: each customer’s courier success rate (Steadfast, Pathao, RedX, Paperfly) and advance payment by bKash or Nagad.', 'cartlum' ),
			'whatsapp'   => __( 'With Cartlum Pro: WhatsApp messages sent automatically through Meta’s WhatsApp Cloud API.', 'cartlum' ),
		);
		if ( isset( $hints[ $tab ] ) ) {
			printf( '<p class="cl-pro-hint"><span class="cl-pro-tag">Pro</span> %1$s <a href="%2$s">%3$s</a></p>', esc_html( $hints[ $tab ] ), esc_url( self::url( 'cartlum-pro' ) ), esc_html__( 'See what Pro adds', 'cartlum' ) );
		}
	}

	/**
	 * Cartlum → Pro: what the paid add-on adds. No locked features in this plugin: Pro is a
	 * separate plugin.
	 */
	public static function page_pro() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$groups = array(
			__( 'Reminders that work harder', 'cartlum' ) => array(
				array( __( 'Multi-step reminders', 'cartlum' ), __( 'As many steps as you like, each with its own timing, channel and message.', 'cartlum' ) ),
				array( __( 'A/B tests', 'cartlum' ), __( 'Two versions of a message; Cartlum shows which brings more orders back.', 'cartlum' ) ),
				array( __( 'Smart coupons', 'cartlum' ), __( 'A discount only when it helps — not for loyal or risky customers.', 'cartlum' ) ),
				array( __( 'Automatic WhatsApp', 'cartlum' ), __( 'Approved WhatsApp templates sent by themselves through Meta’s WhatsApp Cloud API.', 'cartlum' ) ),
				array( __( 'Win-back SMS', 'cartlum' ), __( 'Brings back customers who bought before but have gone quiet, with a one-time coupon.', 'cartlum' ) ),
			),
			__( 'AI', 'cartlum' )                         => array(
				array( __( 'AI call', 'cartlum' ), __( 'A voice agent phones the customer in Bangla over your own phone line, talks for 1–3 minutes and reports back.', 'cartlum' ) ),
				array( __( 'AI message writer', 'cartlum' ), __( 'Drafts reminder SMS in English, Bangla or Banglish.', 'cartlum' ) ),
			),
			__( 'Fewer fake orders', 'cartlum' )          => array(
				array( __( 'Courier check', 'cartlum' ), __( 'Each customer’s delivered and refused parcels at Steadfast, Pathao, RedX and Paperfly, on every order.', 'cartlum' ) ),
				array( __( 'Advance payment', 'cartlum' ), __( 'Risky cash-on-delivery customers send a small advance by bKash or Nagad first.', 'cartlum' ) ),
			),
			__( 'Sell more', 'cartlum' )                  => array(
				array( __( 'Facebook Pixel and Conversions API', 'cartlum' ), __( 'Browser and server events with the same IDs, so Facebook counts each once.', 'cartlum' ) ),
				array( __( 'Bundle offers and customer score', 'cartlum' ), __( 'Offers on product pages, and a score on every cart showing who is worth a call.', 'cartlum' ) ),
			),
		);
		?>
		<div class="wrap cartlum">
			<div class="cl-head"><h1><?php esc_html_e( 'Cartlum Pro', 'cartlum' ); ?></h1></div>
			<hr class="wp-header-end">
			<p class="cl-pro-intro"><?php esc_html_e( 'Everything in Cartlum stays free. Cartlum Pro is a separate add-on that installs next to it and adds:', 'cartlum' ); ?></p>
			<div class="cl-pro-grid">
				<?php foreach ( $groups as $title => $items ) : ?>
					<section class="cl-section">
						<h2><?php echo esc_html( $title ); ?></h2>
						<ul class="cl-pro-list">
							<?php foreach ( $items as $item ) : ?>
								<li><strong><?php echo esc_html( $item[0] ); ?></strong><span><?php echo esc_html( $item[1] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>
			<p class="cl-pro-cta">
				<a class="button button-primary button-hero" href="<?php echo esc_url( self::pro_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Cartlum Pro', 'cartlum' ); ?></a>
				<span class="cl-muted"><?php esc_html_e( 'Pro uses your own accounts for SMS, AI and courier data; you pay those services directly.', 'cartlum' ); ?></span>
			</p>
		</div>
		<?php
	}
}
