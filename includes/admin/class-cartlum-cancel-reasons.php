<?php
/**
 * Cancellation reasons: staff pick why an order was cancelled (unreachable on the confirmation
 * call, refused at delivery, price …) on the order screen; the orders list shows it; and Fake
 * Orders lists the reasons of the last 30 days with the cancelled orders still missing one, each
 * taggable in a couple of seconds (orders cancelled from the WooCommerce app never see the field).
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Cancel_Reasons.
 */
class Cartlum_Cancel_Reasons {

	const META = '_cartlum_cancel_reason';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'cancel_reasons' ) ) {
			return;
		}
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_field' ), 50 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'admin_post_cartlum_cancel_reason', array( __CLASS__, 'handle_tag' ) );
	}

	/**
	 * The reasons: slug => label.
	 *
	 * @return array
	 */
	public static function reasons() {
		/**
		 * Filters the cancellation reasons staff can pick.
		 *
		 * @param array $reasons slug => label.
		 */
		return (array) apply_filters(
			'cartlum_cancel_reasons',
			array(
				'no_answer'             => __( 'Unreachable on the confirmation call', 'cartlum' ),
				'declined_on_call'      => __( 'Said no on the confirmation call', 'cartlum' ),
				'refused_changed_mind'  => __( 'Refused at delivery — changed mind', 'cartlum' ),
				'refused_product_doubt' => __( 'Refused at delivery — doubts about the product', 'cartlum' ),
				'price_concern'         => __( 'Price or delivery charge', 'cartlum' ),
				'duplicate_or_wrong'    => __( 'Duplicate or wrong order', 'cartlum' ),
				'customer_cancelled'    => __( 'Cancelled by the customer', 'cartlum' ),
				'fake_order'            => __( 'Fake order or wrong number', 'cartlum' ),
				'other'                 => __( 'Other', 'cartlum' ),
			)
		);
	}

	/**
	 * An order's reason. Reasons saved by an earlier tool can be read too: list its meta key with
	 * the cartlum_cancel_reason_legacy_keys filter.
	 *
	 * @param WC_Order $order Order.
	 * @return string Slug, or ''.
	 */
	public static function get( $order ) {
		$reason = (string) $order->get_meta( self::META );
		if ( '' === $reason ) {
			$keys = array_filter( array_map( 'trim', explode( ',', (string) Cartlum_Settings::get( 'cancel_reason_old_key' ) ) ) );
			/**
			 * Order meta keys an earlier tool saved cancellation reasons under (same slugs).
			 * Starts with the keys from the "Earlier reason field" setting.
			 *
			 * @param string[] $keys Meta keys.
			 */
			foreach ( (array) apply_filters( 'cartlum_cancel_reason_legacy_keys', $keys ) as $key ) {
				$reason = (string) $order->get_meta( (string) $key );
				if ( '' !== $reason ) {
					break;
				}
			}
		}
		return isset( self::reasons()[ $reason ] ) ? $reason : '';
	}

	/**
	 * Saves a reason with an order note.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $reason Slug, or '' to clear.
	 * @return bool
	 */
	public static function set( $order, $reason ) {
		$reasons = self::reasons();
		if ( '' !== $reason && ! isset( $reasons[ $reason ] ) ) {
			return false;
		}
		if ( self::get( $order ) === $reason ) {
			return true;
		}
		if ( '' === $reason ) {
			$order->delete_meta_data( self::META );
		} else {
			$order->update_meta_data( self::META, $reason );
			/* translators: %s: reason */
			$order->add_order_note( sprintf( __( 'Cancellation reason: %s', 'cartlum' ), $reasons[ $reason ] ) );
		}
		$order->save();
		/**
		 * A cancellation reason was saved on an order (Cartlum Pro: reports fake orders to Facebook).
		 *
		 * @param WC_Order $order  Order.
		 * @param string   $reason Slug, '' when cleared.
		 */
		do_action( 'cartlum_cancel_reason_set', $order, $reason );
		return true;
	}

	/**
	 * The dropdown on the order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function field( $order ) {
		$current = self::get( $order );
		?>
		<p class="form-field form-field-wide">
			<label for="cartlum_cancel_reason"><?php esc_html_e( 'Cancellation reason', 'cartlum' ); ?></label>
			<select id="cartlum_cancel_reason" name="cartlum_cancel_reason" style="width:100%">
				<option value=""><?php esc_html_e( '— If cancelling, pick a reason —', 'cartlum' ); ?></option>
				<?php foreach ( self::reasons() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Saves the dropdown with the order (WooCommerce checked its own nonce).
	 *
	 * @param int $order_id Order.
	 */
	public static function save_field( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the order form's nonce.
		if ( ! isset( $_POST['cartlum_cancel_reason'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			self::set( $order, sanitize_key( wp_unslash( $_POST['cartlum_cancel_reason'] ) ) );
		}
	}

	/**
	 * Orders list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function column( $columns ) {
		$columns['cartlum_cancel_reason'] = __( 'Cancel reason', 'cartlum' );
		return $columns;
	}

	/**
	 * Orders list cell: only for cancelled orders.
	 *
	 * @param string       $column           Column.
	 * @param WC_Order|int $order_or_post_id Order or its post ID.
	 */
	public static function cell( $column, $order_or_post_id ) {
		if ( 'cartlum_cancel_reason' !== $column ) {
			return;
		}
		$order = $order_or_post_id instanceof WC_Order ? $order_or_post_id : wc_get_order( $order_or_post_id );
		if ( ! $order || ! $order->has_status( 'cancelled' ) ) {
			echo '&#8212;';
			return;
		}
		$reason = self::get( $order );
		echo '' !== $reason ? esc_html( self::reasons()[ $reason ] ) : '<span style="color:#b32d2e">' . esc_html__( 'not set', 'cartlum' ) . '</span>';
	}

	/**
	 * Cancelled orders of the last 30 days: reasons counted, and the ones still missing a reason.
	 *
	 * @return array { total, tally: slug => n, missing: WC_Order[] }
	 */
	public static function summary() {
		$orders  = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => 'cancelled',
				'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
				'orderby'      => 'date',
				'order'        => 'ASC',
				'limit'        => 200,
			)
		);
		$tally   = array();
		$missing = array();
		foreach ( $orders as $order ) {
			$reason = self::get( $order );
			if ( '' === $reason ) {
				$missing[] = $order;
			} else {
				$tally[ $reason ] = ( $tally[ $reason ] ?? 0 ) + 1;
			}
		}
		arsort( $tally );
		return array(
			'total'   => count( $orders ),
			'tally'   => $tally,
			'missing' => $missing,
		);
	}

	/**
	 * The panel on Fake Orders.
	 */
	public static function panel() {
		if ( 'yes' !== Cartlum_Settings::get( 'cancel_reasons' ) ) {
			return;
		}
		$data    = self::summary();
		$reasons = self::reasons();
		?>
		<section class="cl-section" id="cancel-reasons">
			<div class="cl-section__head"><h2><?php esc_html_e( 'Why orders were cancelled (last 30 days)', 'cartlum' ); ?></h2></div>
			<?php if ( ! $data['total'] ) : ?>
				<div class="cl-empty"><p><?php esc_html_e( 'No cancelled orders in the last 30 days.', 'cartlum' ); ?></p></div>
			<?php else : ?>
				<p class="cl-section__intro">
					<?php
					/* translators: 1: cancelled orders, 2: with a reason */
					echo esc_html( sprintf( __( '%1$d cancelled, %2$d with a reason.', 'cartlum' ), $data['total'], $data['total'] - count( $data['missing'] ) ) );
					?>
				</p>
				<?php if ( $data['tally'] ) : ?>
					<ul class="cl-rates">
						<?php foreach ( $data['tally'] as $slug => $n ) : ?>
							<?php $share = (int) round( 100 * $n / $data['total'] ); ?>
							<li class="cl-rate">
								<span class="cl-rate__line"><span class="cl-rate__name"><?php echo esc_html( $reasons[ $slug ] ); ?></span><span class="cl-rate__money"><?php echo esc_html( number_format_i18n( $n ) ); ?></span></span>
								<span class="cl-meter" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) $share ); ?>%"></span></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $data['missing'] ) : ?>
					<h3><?php esc_html_e( 'Still need a reason', 'cartlum' ); ?></h3>
					<?php foreach ( array_slice( $data['missing'], 0, 8 ) as $order ) : ?>
						<form class="cl-reason-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="cartlum_cancel_reason">
							<input type="hidden" name="order" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
							<?php wp_nonce_field( 'cartlum_cancel_reason_' . $order->get_id() ); ?>
							<a href="<?php echo esc_url( Cartlum_Admin::order_url( $order->get_id() ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
							<span class="cl-muted cl-reason-row__name"><?php echo esc_html( trim( $order->get_formatted_billing_full_name() ) ); ?></span>
							<select name="reason" required aria-label="<?php esc_attr_e( 'Reason', 'cartlum' ); ?>">
								<option value=""><?php esc_html_e( 'Choose…', 'cartlum' ); ?></option>
								<?php foreach ( $reasons as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="button"><?php esc_html_e( 'Save', 'cartlum' ); ?></button>
						</form>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="cl-note"><?php esc_html_e( 'Every cancelled order has a reason.', 'cartlum' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Saves a reason from the panel.
	 */
	public static function handle_tag() {
		$order_id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		check_admin_referer( 'cartlum_cancel_reason_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::set( $order, isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '' );
		}
		wp_safe_redirect( Cartlum_Admin::url( 'cartlum-fraud' ) . '#cancel-reasons' );
		exit;
	}
}
