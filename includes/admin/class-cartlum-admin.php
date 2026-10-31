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
		add_action( 'wp_ajax_cartlum_search_index', array( __CLASS__, 'search_index' ) );
		add_action( 'admin_post_cartlum_admin_lang', array( __CLASS__, 'switch_language' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CARTLUM_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'Cartlum', 'Cartlum', self::CAP, 'cartlum', array( __CLASS__, 'screen' ), 'dashicons-cart', 56 );
		add_submenu_page( 'cartlum', __( 'Today', 'cartlum' ), __( 'Today', 'cartlum' ), self::CAP, 'cartlum', array( __CLASS__, 'screen' ) );
		// One page per job; each owns its settings tabs (Cartlum_Admin_Settings::sections()).
		foreach ( Cartlum_Admin_Settings::sections() as $slug => $section ) {
			if ( ! $section['tabs'] && '' === $section['view'] ) {
				continue; // A Pro page without Pro.
			}
			$hook = add_submenu_page( 'cartlum', $section['label'], $section['label'], self::CAP, $slug, array( __CLASS__, 'screen' ) );
			if ( 'cartlum-carts' === $slug ) {
				add_action( 'load-' . $hook, array( 'Cartlum_Admin_Actions', 'bulk' ) );
			}
			if ( 'cartlum-settings' === $slug ) {
				add_action( 'load-' . $hook, array( 'Cartlum_Admin_Settings', 'redirect_old_links' ) );
			} else {
				add_action( 'load-' . $hook, array( 'Cartlum_Admin_Settings', 'redirect_moved_tab' ) );
			}
		}
		// What Cartlum Pro adds, only while Pro is not installed.
		if ( ! defined( 'CARTLUM_PRO_VERSION' ) ) {
			add_submenu_page( 'cartlum', __( 'Cartlum Pro', 'cartlum' ), __( 'Pro', 'cartlum' ), self::CAP, 'cartlum-pro', array( __CLASS__, 'screen' ) );
		}
		// The first-run setup: reached from Today, not from the menu.
		add_submenu_page( '', __( 'Set up Cartlum', 'cartlum' ), __( 'Set up Cartlum', 'cartlum' ), self::CAP, 'cartlum-setup', array( __CLASS__, 'screen' ) );
		// Pages that moved: hidden, they only send bookmarks and old links to the new place.
		foreach ( array( 'cartlum-messages', 'cartlum-facebook' ) as $old ) {
			$hook = add_submenu_page( '', 'Cartlum', 'Cartlum', self::CAP, $old, '__return_null' );
			add_action( 'load-' . $hook, array( __CLASS__, 'old_pages' ) );
		}
	}

	/**
	 * Pages that moved (Messages, Facebook): bookmarks and old links land on the new place.
	 */
	public static function old_pages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( ! in_array( $page, array( 'cartlum-messages', 'cartlum-facebook' ), true ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ( 'cartlum-facebook' === $page ? 'facebook' : 'automation' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( self::url( 'cartlum-settings', array( 'tab' => $tab ) ) );
		exit;
	}

	/**
	 * Our screens get the app look.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'cartlum' ) ) {
			$classes .= ' cartlum-app-screen';
		}
		return $classes;
	}

	/**
	 * The menu in its groups (Daily work, Sell more, Setup), with how many jobs wait behind the
	 * daily pages. Counts come from Today's cache only, so the menu never adds a query.
	 *
	 * @param array $items Menu items.
	 * @return array group id => { label, items }
	 */
	private static function rail_groups( array $items ) {
		$home   = array(
			'cartlum'              => 'daily',
			'cartlum-carts'        => 'daily',
			'cartlum-orders'       => 'daily',
			'cartlum-fraud'        => 'daily',
			'cartlum-reports'      => 'daily',
			'cartlum-offers'       => 'sell',
			'cartlum-landing'      => 'sell',
			'cartlum-loyalty'      => 'sell',
		);
		$groups = array(
			'daily' => array(
				'label' => __( 'Daily work', 'cartlum' ),
				'items' => array(),
			),
			'sell'  => array(
				'label' => __( 'Sell more', 'cartlum' ),
				'items' => array(),
			),
			'setup' => array(
				'label' => __( 'Setup', 'cartlum' ),
				'items' => array(),
			),
		);
		$counts = get_transient( Cartlum_Admin_Today::CACHE );
		$jobs   = is_array( $counts ) ? array(
			'cartlum-carts'  => array( (int) $counts['carts'], 'bad', __( 'carts to call', 'cartlum' ) ),
			'cartlum-orders' => array( (int) $counts['confirm'], 'info', __( 'orders to confirm', 'cartlum' ) ),
			'cartlum-fraud'  => array( (int) $counts['risky'], 'bad', __( 'risky orders', 'cartlum' ) ),
		) : array();
		foreach ( $items as $slug => $item ) {
			if ( ! empty( $jobs[ $slug ][0] ) ) {
				$item['count']       = $jobs[ $slug ][0];
				$item['tone']        = $jobs[ $slug ][1];
				$item['count_label'] = $jobs[ $slug ][2];
			}
			$group = isset( $item['group'] ) && isset( $groups[ $item['group'] ] ) ? $item['group'] : ( $home[ $slug ] ?? 'setup' );
			$groups[ $group ]['items'][ $slug ] = $item;
		}
		return array_filter(
			$groups,
			function ( $g ) {
				return (bool) $g['items'];
			}
		);
	}

	/**
	 * The SVG shapes a menu icon may use.
	 *
	 * @return array
	 */
	private static function svg_tags() {
		return array(
			'path'   => array( 'd' => true ),
			'circle' => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'rect'   => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			),
		);
	}

	/**
	 * The menu's items with their icons (simple line drawings).
	 *
	 * @return array slug => { label, icon }
	 */
	public static function rail_items() {
		$icons = array(
			'cartlum'              => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
			'cartlum-carts'        => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h12l2-8H6.5"/>',
			'cartlum-orders'       => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
			'cartlum-fraud'        => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
			'cartlum-reports'      => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'cartlum-checkout'     => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>',
			'cartlum-offers'       => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'cartlum-loyalty'      => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
			'cartlum-ai'           => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5"/>',
			'cartlum-integrations' => '<path d="M9 7V3M15 7V3M7 7h10v5a5 5 0 0 1-10 0zM12 17v4"/>',
			'cartlum-settings'     => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
			'cartlum-pro'          => '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6-5.3-3-5.3 3 1.2-6L3.4 9.3l6-.7z"/>',
		);
		$items = array(
			'cartlum' => array(
				'label' => __( 'Today', 'cartlum' ),
				'icon'  => $icons['cartlum'],
			),
		);
		foreach ( Cartlum_Admin_Settings::sections() as $slug => $section ) {
			if ( ! $section['tabs'] && '' === $section['view'] ) {
				continue;
			}
			$items[ $slug ] = array(
				'label' => $section['label'],
				'icon'  => $icons[ $slug ] ?? $icons['cartlum-settings'],
			);
		}
		/**
		 * Filters the Cartlum menu. Each item: slug => { label, icon (SVG paths), url (optional, for
		 * screens outside Cartlum's pages, e.g. Cartlum Pro's landing pages) }.
		 *
		 * @param array $items Items, in menu order.
		 */
		$items = (array) apply_filters( 'cartlum_rail_items', $items );
		if ( ! defined( 'CARTLUM_PRO_VERSION' ) ) {
			$items['cartlum-pro'] = array(
				'label' => __( 'Get Pro', 'cartlum' ),
				'icon'  => $icons['cartlum-pro'],
			);
		}
		return $items;
	}

	/**
	 * Every Cartlum screen: the app frame (menu on the left, search on top) around the page.
	 */
	public static function screen() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$page  = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'cartlum';
		$items = self::rail_items();
		$crumb = $items[ $page ]['label'] ?? 'Cartlum';
		$tab   = '';
		if ( isset( Cartlum_Admin_Settings::sections()[ $page ] ) ) {
			$current = Cartlum_Admin_Settings::current_tab( $page );
			$section = Cartlum_Admin_Settings::sections()[ $page ];
			$tab     = '' !== $current ? ( Cartlum_Admin_Settings::tabs()[ $current ] ?? '' ) : $section['view'];
			$tab     = $tab === $section['label'] ? '' : $tab; // A page that is only its list.
		}
		?>
		<div class="cl-app">
			<nav class="cl-rail" aria-label="<?php esc_attr_e( 'Cartlum menu', 'cartlum' ); ?>">
				<a class="cl-rail__brand" href="<?php echo esc_url( self::url( 'cartlum' ) ); ?>"><span aria-hidden="true">C</span>Cartlum</a>
				<div class="cl-rail__groups">
					<?php foreach ( self::rail_groups( $items ) as $gid => $group ) : ?>
						<div class="cl-rail__group">
							<p class="cl-rail__label" id="cl-rail-<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $group['label'] ); ?></p>
							<ul aria-labelledby="cl-rail-<?php echo esc_attr( $gid ); ?>">
								<?php foreach ( $group['items'] as $slug => $item ) : ?>
									<li>
										<a href="<?php echo esc_url( isset( $item['url'] ) ? $item['url'] : self::url( $slug ) ); ?>"<?php echo $slug === $page ? ' aria-current="page"' : ''; ?>>
											<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo wp_kses( $item['icon'], self::svg_tags() ); ?></svg>
											<span><?php echo esc_html( $item['label'] ); ?></span>
											<?php if ( ! empty( $item['count'] ) ) : ?>
												<span class="cl-rail__count<?php echo 'info' === ( $item['tone'] ?? '' ) ? ' cl-rail__count--info' : ''; ?>"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?><span class="screen-reader-text"> <?php echo esc_html( $item['count_label'] ?? '' ); ?></span></span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="cl-rail__foot">
					<?php
					echo esc_html( 'Cartlum ' . CARTLUM_VERSION );
					if ( defined( 'CARTLUM_PRO_VERSION' ) ) {
						echo esc_html( ' · Pro ' . CARTLUM_PRO_VERSION );
					}
					?>
				</p>
			</nav>
			<div class="cl-main">
				<div class="cl-top">
					<p class="cl-crumb">Cartlum › <strong><?php echo esc_html( $crumb ); ?></strong><?php echo '' !== $tab ? ' › ' . esc_html( $tab ) : ''; ?></p>
					<button type="button" class="cl-search-btn" data-cartlum-search>
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
						<span><?php esc_html_e( 'Find a setting…', 'cartlum' ); ?></span>
						<kbd>Ctrl K</kbd>
					</button>
					<?php
					$bn   = 'bn_BD' === Cartlum_Settings::admin_language();
					$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
					$lang = wp_nonce_url( add_query_arg( array( 'action' => 'cartlum_admin_lang', 'to' => $bn ? '' : 'bn_BD', 'back' => rawurlencode( $back ) ), admin_url( 'admin-post.php' ) ), 'cartlum_admin_lang' );
					?>
					<a class="cl-lang" href="<?php echo esc_url( $lang ); ?>" lang="<?php echo $bn ? 'en' : 'bn'; ?>" title="<?php esc_attr_e( 'Cartlum screens in another language, for you only', 'cartlum' ); ?>"><?php echo $bn ? 'English' : 'বাংলা'; ?></a>
				</div>
				<div class="cl-notices" data-cartlum-notices hidden></div>
				<?php
				if ( 'cartlum' === $page ) {
					self::page_dashboard();
				} elseif ( 'cartlum-pro' === $page ) {
					self::page_pro();
				} elseif ( 'cartlum-setup' === $page ) {
					Cartlum_Admin_Setup::page();
				} else {
					Cartlum_Admin_Settings::page();
				}
				?>
			</div>
		</div>
		<div class="cl-palette" data-cartlum-palette hidden>
			<div class="cl-palette__box" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Find a setting', 'cartlum' ); ?>">
				<input type="search" data-cartlum-palette-input placeholder="<?php esc_attr_e( 'Search settings, pages and features…', 'cartlum' ); ?>" aria-label="<?php esc_attr_e( 'Find a setting', 'cartlum' ); ?>" autocomplete="off">
				<ul role="listbox" data-cartlum-palette-list></ul>
			</div>
		</div>
		<div class="cl-toast" data-cartlum-toast role="status" hidden></div>
		<?php
	}

	/**
	 * The বাংলা / English button: Cartlum's screens in that language, for this user only.
	 */
	public static function switch_language() {
		check_admin_referer( 'cartlum_admin_lang' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		$to = isset( $_GET['to'] ) && 'bn_BD' === $_GET['to'] ? 'bn_BD' : '';
		update_user_meta( get_current_user_id(), 'cartlum_admin_lang', $to );
		$back = isset( $_GET['back'] ) ? rawurldecode( esc_url_raw( wp_unslash( $_GET['back'] ) ) ) : '';
		wp_safe_redirect( '' !== $back ? $back : self::url( 'cartlum' ) );
		exit;
	}

	/**
	 * Everything the "Find a setting" box can jump to: pages, tabs, settings and features.
	 */
	public static function search_index() {
		check_ajax_referer( 'cartlum_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$out  = array();
		$tabs = Cartlum_Admin_Settings::tabs();
		foreach ( Cartlum_Admin_Settings::sections() as $slug => $section ) {
			if ( ! $section['tabs'] && '' === $section['view'] ) {
				continue;
			}
			$out[] = array( $section['label'], __( 'Page', 'cartlum' ), self::url( $slug ) );
			foreach ( $section['tabs'] as $tab ) {
				$where = $section['label'] . ' › ' . ( $tabs[ $tab ] ?? $tab );
				$url   = self::url( $slug, array( 'tab' => $tab ) );
				$out[] = array( $tabs[ $tab ] ?? $tab, $section['label'], $url );
				foreach ( Cartlum_Admin_Settings::tab_fields( $tab ) as $key => $field ) {
					if ( 'heading' === $field['type'] || empty( $field['label'] ) ) {
						continue;
					}
					$help  = isset( $field['help'] ) ? wp_html_excerpt( (string) $field['help'], 90 ) : '';
					$out[] = array( $field['label'], $where, $url . '#cl-row-' . $key, $help . ' ' . $key );
				}
			}
		}
		foreach ( Cartlum_Admin_Today::features() as $group => $items ) {
			foreach ( $items as $item ) {
				$out[] = array( $item[0], $group, $item[2] );
			}
		}
		wp_send_json_success( $out );
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
				'copied'   => __( 'Copied', 'cartlum' ),
				'sending'  => __( 'Sending…', 'cartlum' ),
				'chars'    => __( 'characters', 'cartlum' ),
				'unicode'  => __( 'unicode', 'cartlum' ),
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cartlum_admin' ),
				'saving'   => __( 'Saving…', 'cartlum' ),
				'unsaved'  => __( 'You have unsaved changes', 'cartlum' ),
				'leave'    => __( 'You have unsaved changes. Leave without saving?', 'cartlum' ),
				'failed'   => __( 'Could not save. Check your connection and try again.', 'cartlum' ),
				'noMatch'  => __( 'No setting matches. Try “sms”, “buy now” or “steadfast”.', 'cartlum' ),
				'notices'  => __( 'Notices', 'cartlum' ),
				'saved'    => __( 'Saved — leave empty to keep', 'cartlum' ),
				/* translators: %d: number of orders */
				'selected' => __( '%d selected', 'cartlum' ),
				'pickFirst' => __( 'Select one or more orders first.', 'cartlum' ),
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
			$args['tab'] = Cartlum_Admin_Settings::resolve_tab( (string) $args['tab'] );
			$page        = Cartlum_Admin_Settings::page_for_tab( $args['tab'] );
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
		printf( '<div class="notice notice-%1$s is-dismissible cl-flash" data-cartlum-flash="%1$s"><p>%2$s</p></div>', esc_attr( $type ), esc_html( sanitize_text_field( $raw ) ) );
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
		$series   = Cartlum_Carts::daily( $days );
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
				array( __( 'Phone app for staff', 'cartlum' ), __( 'Orders to confirm and carts to call on a phone, with a notification for each new order.', 'cartlum' ) ),
				array( __( 'Customers confirm their own order', 'cartlum' ), __( 'A link in the “order received” SMS: one tap confirms, and the parcel can go to the courier straight away.', 'cartlum' ) ),
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
