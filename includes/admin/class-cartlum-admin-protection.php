<?php
/**
 * Fake-order protection for staff: a risk column on the Orders list and a box on each order with
 * the number's history and a Block / Unblock button.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Protection.
 */
class Cartlum_Admin_Protection {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'protect_enabled' ) ) {
			return;
		}
		// Orders list: HPOS and the older posts-based screen.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_posts' ), 10, 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_post_cartlum_block_number', array( __CLASS__, 'handle_block' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_head', array( __CLASS__, 'styles' ) );
	}

	/**
	 * Adds the column after the order status.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['cartlum_risk'] = __( 'Cartlum check', 'cartlum' );
			}
		}
		if ( ! isset( $out['cartlum_risk'] ) ) {
			$out['cartlum_risk'] = __( 'Cartlum check', 'cartlum' );
		}
		return $out;
	}

	/**
	 * HPOS list cell.
	 *
	 * @param string   $column Column.
	 * @param WC_Order $order  Order.
	 */
	public static function column_hpos( $column, $order ) {
		if ( 'cartlum_risk' === $column && $order instanceof WC_Order ) {
			self::cell( $order );
		}
	}

	/**
	 * Posts list cell.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order id.
	 */
	public static function column_posts( $column, $post_id ) {
		if ( 'cartlum_risk' === $column ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				self::cell( $order );
			}
		}
	}

	/**
	 * The badge and counts for one order.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function cell( $order ) {
		$stats = Cartlum_Protection::history( $order->get_billing_phone(), $order->get_id() );
		self::badge( $stats['risk'] );
		echo '<br><span class="cl-risk__counts">';
		/* translators: 1: completed orders, 2: cancelled orders */
		echo esc_html( sprintf( __( '%1$d completed · %2$d cancelled', 'cartlum' ), $stats['completed'], $stats['cancelled'] ) );
		if ( '' !== $stats['courier_ratio'] ) {
			/* translators: %s: courier delivery success rate */
			echo '<br>' . esc_html( sprintf( __( 'Courier success %s', 'cartlum' ), $stats['courier_ratio'] ) );
		}
		echo '</span>';

		/**
		 * Adds to an order's cell in the "Cartlum check" column (Cartlum Pro: courier record, advance).
		 *
		 * @param WC_Order $order Order.
		 */
		do_action( 'cartlum_order_check_cell', $order );
	}

	/**
	 * A risk badge: label and colour, never colour alone.
	 *
	 * @param string $risk Risk.
	 */
	private static function badge( $risk ) {
		printf( '<span class="cl-risk cl-risk--%1$s">%2$s</span>', esc_attr( $risk ), esc_html( Cartlum_Protection::risk_label( $risk ) ) );
	}

	/**
	 * The box on the order screen.
	 */
	public static function meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'cartlum-check', __( 'Cartlum check', 'cartlum' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
		}
	}

	/**
	 * Box contents.
	 *
	 * @param WP_Post|WC_Order $post_or_order Order or its post.
	 */
	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
		if ( '' === $phone ) {
			echo '<p>' . esc_html__( 'No phone number on this order.', 'cartlum' ) . '</p>';
			return;
		}
		$stats = Cartlum_Protection::history( $order->get_billing_phone(), $order->get_id() );
		echo '<p>';
		self::badge( $stats['risk'] );
		echo '</p><p>';
		/* translators: 1: earlier orders, 2: completed, 3: cancelled */
		echo esc_html( sprintf( __( 'Earlier orders from this number: %1$d — %2$d completed, %3$d cancelled.', 'cartlum' ), $stats['orders'], $stats['completed'], $stats['cancelled'] ) );
		if ( '' !== $stats['courier_ratio'] ) {
			echo '<br>';
			/* translators: %s: courier delivery success rate */
			echo esc_html( sprintf( __( 'Courier delivery success: %s', 'cartlum' ), $stats['courier_ratio'] ) );
		}
		echo '</p>';
		$blocked = 'blocked' === $stats['risk'];
		printf(
			'<p><a class="button%1$s" href="%2$s">%3$s</a></p><p class="description">%4$s</p>',
			$blocked ? '' : ' cl-risk__block',
			esc_url( self::block_url( $order, 'number', $blocked ) ),
			$blocked ? esc_html__( 'Unblock this number', 'cartlum' ) : esc_html__( 'Block this number', 'cartlum' ),
			$blocked
				? esc_html__( 'This number cannot order online.', 'cartlum' )
				: esc_html__( 'A blocked number cannot place orders online. You can still take its orders by phone.', 'cartlum' )
		);

		$list = Cartlum_Protection::blocklist();
		$ip   = (string) $order->get_customer_ip_address();
		if ( '' !== $ip ) {
			$ip_blocked = Cartlum_Protection::ip_blocked( $ip, $list );
			/* translators: %s: IP address */
			echo '<p>' . esc_html( sprintf( __( 'IP address: %s', 'cartlum' ), $ip ) ) . ( $ip_blocked ? ' — <strong>' . esc_html__( 'blocked', 'cartlum' ) . '</strong>' : '' ) . '<br>';
			echo '<a href="' . esc_url( self::block_url( $order, 'ip', $ip_blocked ) ) . '">' . ( $ip_blocked ? esc_html__( 'Unblock this IP address', 'cartlum' ) : esc_html__( 'Block this IP address', 'cartlum' ) ) . '</a></p>';
		}
		$device = (string) $order->get_meta( '_cartlum_device' );
		if ( '' !== $device ) {
			$device_blocked = in_array( $device, $list['devices'], true );
			echo '<p>' . esc_html__( 'Device:', 'cartlum' ) . ' <code>' . esc_html( substr( $device, 0, 8 ) ) . '</code>' . ( $device_blocked ? ' — <strong>' . esc_html__( 'blocked', 'cartlum' ) . '</strong>' : '' ) . '<br>';
			echo '<a href="' . esc_url( self::block_url( $order, 'device', $device_blocked ) ) . '">' . ( $device_blocked ? esc_html__( 'Unblock this device', 'cartlum' ) : esc_html__( 'Block this device', 'cartlum' ) ) . '</a></p>';
		}
		if ( '' !== $ip || '' !== $device ) {
			echo '<p class="description">' . esc_html__( 'Mobile networks share IP addresses among many customers, so block an IP only when it keeps sending fake orders. A device can come back by clearing its cookies.', 'cartlum' ) . '</p>';
		}

		/**
		 * Adds to the "Cartlum check" box on the order screen (Cartlum Pro: courier record).
		 *
		 * @param WC_Order $order Order.
		 */
		do_action( 'cartlum_order_check_box', $order );
	}

	/**
	 * Block / Unblock link for an order's number, IP address or device.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $what    number|ip|device.
	 * @param bool     $blocked Currently blocked.
	 * @return string
	 */
	private static function block_url( $order, $what, $blocked ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'cartlum_block_number',
					'order'  => $order->get_id(),
					'what'   => $what,
					'do'     => $blocked ? 'unblock' : 'block',
				),
				admin_url( 'admin-post.php' )
			),
			'cartlum_block_' . $order->get_id()
		);
	}

	/**
	 * Block / Unblock button.
	 */
	public static function handle_block() {
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		check_admin_referer( 'cartlum_block_' . $order_id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'cartlum' ), 404 );
		}
		$unblock = isset( $_GET['do'] ) && 'unblock' === $_GET['do'];
		$what    = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : 'number';
		if ( 'ip' === $what || 'device' === $what ) {
			$entry = 'ip' === $what ? (string) $order->get_customer_ip_address() : 'device:' . (string) $order->get_meta( '_cartlum_device' );
			$done  = $unblock ? Cartlum_Protection::unblock_entry( $entry ) : Cartlum_Protection::block_entry( $entry );
			if ( $done ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: IP address or device, 2: blocked/unblocked, 3: staff member */
						__( 'Cartlum: %1$s %2$s by %3$s.', 'cartlum' ),
						'ip' === $what ? sprintf( /* translators: %s: IP address */ __( 'IP address %s', 'cartlum' ), $entry ) : __( 'device', 'cartlum' ),
						$unblock ? __( 'unblocked', 'cartlum' ) : __( 'blocked', 'cartlum' ),
						wp_get_current_user()->display_name
					)
				);
			}
			wp_safe_redirect( $order->get_edit_order_url() );
			exit;
		}
		$done = $unblock ? Cartlum_Protection::unblock( $order->get_billing_phone() ) : Cartlum_Protection::block( $order->get_billing_phone() );
		if ( $done ) {
			$order->add_order_note(
				$unblock
					/* translators: %s: staff member */
					? sprintf( __( 'Cartlum: number unblocked by %s.', 'cartlum' ), wp_get_current_user()->display_name )
					/* translators: %s: staff member */
					: sprintf( __( 'Cartlum: number blocked from ordering online by %s.', 'cartlum' ), wp_get_current_user()->display_name )
			);
		}
		wp_safe_redirect( add_query_arg( 'cartlum_blocked', $unblock ? 'off' : 'on', $order->get_edit_order_url() ) );
		exit;
	}

	/**
	 * Confirmation after Block / Unblock.
	 */
	public static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$state = isset( $_GET['cartlum_blocked'] ) ? sanitize_key( $_GET['cartlum_blocked'] ) : '';
		if ( 'on' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Number blocked: it can no longer order online.', 'cartlum' ) . '</p></div>';
		} elseif ( 'off' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Number unblocked.', 'cartlum' ) . '</p></div>';
		}
	}

	/**
	 * Badge styles on order screens.
	 */
	public static function styles() {
		$screen = get_current_screen();
		if ( ! $screen || ( false === strpos( $screen->id, 'shop_order' ) && false === strpos( $screen->id, 'wc-orders' ) ) ) {
			return;
		}
		echo '<style>
.cl-risk{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;line-height:1.6;white-space:nowrap}
.cl-risk--good{background:#e3f2e8;color:#14532d}
.cl-risk--new{background:#eef0f2;color:#1f2937}
.cl-risk--risky{background:#fdecd8;color:#7c2d12}
.cl-risk--blocked{background:#fbe0dc;color:#7f1d1d}
.cl-risk__counts{color:#50575e;font-size:12px}
.cl-risk__block{color:#b32d2e!important;border-color:#b32d2e!important}
</style>';
	}
}
