<?php
/**
 * Today: the home screen's "needs you now" cards (carts to call, orders to confirm, parcels to
 * send, risky orders, cancellations without a reason, setup problems) and the feature board,
 * which lists every feature with whether it is on, off, waiting for setup, or part of Pro.
 * The counts are worked out after the page shows (one request) and kept for 5 minutes.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Today.
 */
class Cartlum_Admin_Today {

	const CACHE = 'cartlum_today';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_cartlum_today', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Whether any courier account is connected.
	 *
	 * @param array $ints Cartlum_Admin_Integrations::all().
	 * @return bool
	 */
	public static function courier_connected( array $ints ) {
		foreach ( array( 'steadfast', 'pathao', 'redx', 'paperfly' ) as $id ) {
			if ( isset( $ints[ $id ] ) && in_array( $ints[ $id ]['status'], array( 'on', 'err' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every feature, grouped by menu page: { label, status (on|off|setup|pro), url }.
	 *
	 * @return array page label => item[]
	 */
	public static function features() {
		$s       = Cartlum_Settings::all();
		$on      = function ( $key ) use ( $s ) {
			return 'yes' === ( $s[ $key ] ?? '' );
		};
		$ints    = Cartlum_Admin_Integrations::all();
		$sms     = 'on' === $ints['sms']['status'];
		$tabs    = array();
		foreach ( Cartlum_Admin_Settings::sections() as $slug => $section ) {
			foreach ( $section['tabs'] as $tab ) {
				$tabs[ $tab ] = $slug;
			}
		}
		$link    = function ( $tab ) use ( $tabs ) {
			return isset( $tabs[ $tab ] ) ? Cartlum_Admin::url( $tabs[ $tab ], array( 'tab' => $tab ) ) : Cartlum_Admin::url( 'cartlum-pro' );
		};
		// Off, on, or on but its service is missing.
		$state   = function ( $enabled, $ready = true ) {
			return ! $enabled ? 'off' : ( $ready ? 'on' : 'setup' );
		};
		$pro_one = function ( $tab, $status ) use ( $tabs ) {
			return isset( $tabs[ $tab ] ) ? $status : 'pro';
		};
		// Pro 0.12+ has Orders → Parcels for every courier; older Pro a Steadfast tab.
		$parcels = isset( $tabs['parcels'] ) ? 'parcels' : 'steadfast';
		$groups  = array(
			__( 'Abandoned Carts', 'cartlum' )    => array(
				array( __( 'Save carts as numbers are typed', 'cartlum' ), $state( $on( 'enabled' ) ), $link( 'capture' ) ),
				array( __( 'Automatic reminders', 'cartlum' ), $state( $on( 'flow_enabled' ), 'email' === ( $s['flow_channel'] ?? 'sms' ) || $sms ), $link( 'automation' ) ),
				array( __( 'Save-cart popup', 'cartlum' ), $state( $on( 'popup_enabled' ) ), $link( 'popup' ) ),
				array( __( 'Win-back SMS', 'cartlum' ), $pro_one( 'winback', $state( $on( 'pro_winback_enabled' ), $sms ) ), $link( 'winback' ) ),
			),
			__( 'Orders', 'cartlum' )             => array(
				array( __( 'Order SMS', 'cartlum' ), $state( $on( 'order_sms_enabled' ), $sms ), $link( 'orders' ) ),
				array( __( 'Order tracking', 'cartlum' ), $state( $on( 'order_tracking' ) ), $link( 'tracking' ) ),
				array( __( 'Cancellation reasons', 'cartlum' ), $state( $on( 'cancel_reasons' ) ), $link( 'tracking' ) ),
				array( __( 'Courier parcels', 'cartlum' ), $pro_one( $parcels, self::courier_connected( $ints ) ? 'on' : 'off' ), $link( $parcels ) ),
				array( __( 'Advance payment', 'cartlum' ), $pro_one( 'advance', $state( $on( 'pro_adv_enabled' ) ) ), $link( 'advance' ) ),
			),
			__( 'Fake Orders', 'cartlum' )        => array(
				array( __( 'Fake-order protection', 'cartlum' ), $state( $on( 'protect_enabled' ) ), $link( 'protection' ) ),
				array( __( 'SMS code for risky orders', 'cartlum' ), $state( $on( 'protect_enabled' ) && $on( 'protect_otp' ), $sms ), $link( 'protection' ) ),
				array( __( 'Courier check', 'cartlum' ), $pro_one( 'courier', $state( $on( 'pro_courier_checkout' ), 'on' === $ints['fraudbd']['status'] ) ), $link( 'courier' ) ),
			),
			__( 'Checkout & Buttons', 'cartlum' ) => array(
				array( __( 'Easy checkout', 'cartlum' ), $state( $on( 'checkout_enabled' ) ), $link( 'checkout' ) ),
				array( __( 'Buy now button', 'cartlum' ), $state( $on( 'buy_now_enabled' ) ), $link( 'product' ) ),
				array( __( 'WhatsApp & call buttons', 'cartlum' ), $state( $on( 'whatsapp_button' ) || $on( 'call_button' ) ), $link( 'product' ) ),
				array( __( 'Phone login', 'cartlum' ), $state( $on( 'phone_login' ), $sms ), $link( 'account' ) ),
			),
			__( 'AI & marketing', 'cartlum' )     => array(
				array( __( 'AI call', 'cartlum' ), $pro_one( 'ai_call', 'on' === $ints['elevenlabs']['status'] ? 'on' : 'off' ), $link( 'ai_call' ) ),
				array( __( 'AI writer', 'cartlum' ), $pro_one( 'ai', 'on' === $ints['ai']['status'] ? 'on' : 'off' ), $link( 'ai' ) ),
				array( __( 'Facebook purchases', 'cartlum' ), $pro_one( 'facebook', 'on' === $ints['facebook']['status'] ? 'on' : 'off' ), $link( 'facebook' ) ),
				array( __( 'Faster shop pages', 'cartlum' ), $state( $on( 'speed_grid_images' ) || $on( 'speed_no_elementor_icons' ) ), $link( 'speed' ) ),
			),
		);
		/**
		 * Filters the feature board: group label => array( label, status, url )[].
		 *
		 * @param array $groups Groups.
		 */
		return (array) apply_filters( 'cartlum_features', $groups );
	}

	/**
	 * Status words.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'on'    => __( 'On', 'cartlum' ),
			'off'   => __( 'Off', 'cartlum' ),
			'setup' => __( 'Needs setup', 'cartlum' ),
			'pro'   => 'Pro',
		);
	}

	/**
	 * The counts behind the cards, cached for 5 minutes.
	 *
	 * @param bool $fresh Ignore the cache.
	 * @return array
	 */
	public static function counts( $fresh = false ) {
		$cached = $fresh ? false : get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'cartlum_carts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS n, COALESCE(SUM(cart_total),0) AS total FROM %i WHERE status = %s AND contact_count = 0', $table, 'abandoned' ) );

		$today   = new DateTime( 'today', wp_timezone() );
		$confirm = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'processing', 'on-hold' ),
				'date_created' => '>=' . $today->getTimestamp(),
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
			)
		);

		$parcels = null;
		$ints    = Cartlum_Admin_Integrations::all();
		if ( self::courier_connected( $ints ) ) {
			$parcels = 0;
			foreach ( wc_get_orders(
				array(
					'type'         => 'shop_order',
					'status'       => 'processing',
					'date_created' => '>' . ( time() - 14 * DAY_IN_SECONDS ),
					'limit'        => 300,
					'return'       => 'ids',
				)
			) as $id ) {
				// Steadfast keeps its parcel in post meta (as its own plugin does); other couriers in order meta.
				if ( '' === (string) get_post_meta( $id, 'steadfast_consignment_id', true ) && '' === (string) wc_get_order( $id )->get_meta( '_cartlum_parcel_id' ) ) {
					++$parcels;
				}
			}
		}

		$risky   = 'yes' === Cartlum_Settings::get( 'protect_enabled' ) ? count( Cartlum_Admin_Fraud::orders( true ) ) : null;
		$reasons = 'yes' === Cartlum_Settings::get( 'cancel_reasons' ) && class_exists( 'Cartlum_Cancel_Reasons' ) ? count( Cartlum_Cancel_Reasons::summary()['missing'] ) : null;

		$out = array(
			'carts'       => (int) ( $row->n ?? 0 ),
			'carts_value' => (float) ( $row->total ?? 0 ),
			'confirm'     => (int) $confirm->total,
			'parcels'     => $parcels,
			'risky'       => $risky,
			'reasons'     => $reasons,
			'at'          => time(),
		);
		set_transient( self::CACHE, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * The orders list, filtered to a status.
	 *
	 * @param string $status Status without wc-.
	 * @return string
	 */
	private static function orders_url( $status ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return $hpos ? admin_url( 'admin.php?page=wc-orders&status=wc-' . $status ) : admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . $status );
	}

	/**
	 * The cards (HTML), for the request made after the page shows.
	 */
	public static function ajax() {
		check_ajax_referer( 'cartlum_today', 'nonce' );
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		ob_start();
		self::cards( self::counts( ! empty( $_POST['fresh'] ) ) );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * "Needs you now" cards and setup problems.
	 *
	 * @param array $c Counts.
	 */
	public static function cards( array $c ) {
		$cards = array();
		if ( $c['carts'] ) {
			$cards[] = array(
				'bad',
				$c['carts'],
				__( 'Carts to call', 'cartlum' ),
				/* translators: %s: money waiting in carts */
				sprintf( __( 'No contact yet · %s waiting', 'cartlum' ), wp_strip_all_tags( wc_price( $c['carts_value'] ) ) ),
				Cartlum_Admin::url( 'cartlum-carts', array( 'status' => 'abandoned' ) ),
			);
		}
		if ( $c['confirm'] ) {
			$cards[] = array( 'info', $c['confirm'], __( 'Orders to confirm', 'cartlum' ), __( 'Placed today, not shipped yet', 'cartlum' ), self::orders_url( 'processing' ) );
		}
		if ( $c['parcels'] ) {
			$cards[] = array( 'warn', $c['parcels'], __( 'Parcels to send', 'cartlum' ), __( 'Processing, not sent to a courier', 'cartlum' ), self::orders_url( 'processing' ) );
		}
		if ( $c['risky'] ) {
			$cards[] = array( 'bad', $c['risky'], __( 'Risky orders', 'cartlum' ), __( 'Often cancel · still to ship', 'cartlum' ), Cartlum_Admin::url( 'cartlum-fraud' ) );
		}
		if ( $c['reasons'] ) {
			$cards[] = array( 'warn', $c['reasons'], __( 'Cancelled without a reason', 'cartlum' ), __( 'Tag them in two clicks', 'cartlum' ), Cartlum_Admin::url( 'cartlum-fraud' ) . '#cancel-reasons' );
		}
		$problems = array();
		foreach ( self::features() as $items ) {
			foreach ( $items as $item ) {
				if ( 'setup' === $item[1] ) {
					$problems[] = $item;
				}
			}
		}
		foreach ( $problems as $item ) {
			printf(
				'<div class="cl-alert"><span>%1$s</span> <a class="button" href="%2$s">%3$s</a></div>',
				/* translators: %s: feature */
				esc_html( sprintf( __( '%s is on, but needs setting up to work.', 'cartlum' ), $item[0] ) ),
				esc_url( $item[2] ),
				esc_html__( 'Set up', 'cartlum' )
			);
		}
		if ( ! $cards ) {
			echo '<div class="cl-card cl-allclear"><strong>' . esc_html__( 'All caught up.', 'cartlum' ) . '</strong> ' . esc_html__( 'No carts to call, orders to confirm or risky orders right now.', 'cartlum' ) . '</div>';
		} else {
			echo '<div class="cl-act-grid">';
			foreach ( $cards as $card ) {
				printf(
					'<a class="cl-act cl-act--%1$s" href="%2$s"><span class="cl-act__n">%3$s</span><span class="cl-act__t">%4$s</span><span class="cl-act__s">%5$s</span></a>',
					esc_attr( $card[0] ),
					esc_url( $card[4] ),
					esc_html( number_format_i18n( $card[1] ) ),
					esc_html( $card[2] ),
					esc_html( $card[3] )
				);
			}
			echo '</div>';
		}
		printf(
			'<p class="cl-muted cl-updated">%1$s · <button type="button" class="button-link" data-cartlum-today-refresh>%2$s</button></p>',
			/* translators: %s: time ago */
			esc_html( sprintf( __( 'Updated %s ago', 'cartlum' ), human_time_diff( (int) $c['at'] ) ) ),
			esc_html__( 'Refresh', 'cartlum' )
		);
	}

	/**
	 * The Today block at the top of the home screen: cards (loaded after the page) and the
	 * feature board.
	 */
	public static function render() {
		$labels = self::status_labels();
		Cartlum_Admin_Setup::banner();
		?>
		<section class="cl-today" aria-label="<?php esc_attr_e( 'Needs you now', 'cartlum' ); ?>" data-cartlum-today data-nonce="<?php echo esc_attr( wp_create_nonce( 'cartlum_today' ) ); ?>">
			<div class="cl-act-grid" aria-busy="true">
				<span class="cl-act cl-skel"></span><span class="cl-act cl-skel"></span><span class="cl-act cl-skel"></span><span class="cl-act cl-skel"></span>
			</div>
		</section>
		<details class="cl-card cl-features" open>
			<summary><h2><?php esc_html_e( 'Your features', 'cartlum' ); ?></h2><span class="cl-muted"><?php esc_html_e( 'Everything Cartlum can do, and whether it is on', 'cartlum' ); ?></span></summary>
			<div class="cl-features__grid">
				<?php foreach ( self::features() as $group => $items ) : ?>
					<div class="cl-features__group">
						<h3><?php echo esc_html( $group ); ?></h3>
						<ul>
							<?php foreach ( $items as $item ) : ?>
								<li>
									<a href="<?php echo esc_url( $item[2] ); ?>"><?php echo esc_html( $item[0] ); ?></a>
									<span class="cl-pill cl-pill--<?php echo esc_attr( $item[1] ); ?>"><?php echo esc_html( $labels[ $item[1] ] ?? $item[1] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</details>
		<?php
	}
}
