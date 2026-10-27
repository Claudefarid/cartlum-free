<?php
/**
 * Orders → To confirm: the day's confirmation calls. Each new order with the customer's number,
 * what they ordered and their history, one tap to call or WhatsApp, and the answer in one more
 * tap: confirmed, no answer, call back, cancel, or fake (with the number blocked).
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Confirm.
 */
class Cartlum_Admin_Confirm {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cartlum_confirm', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_cartlum_confirm_answer', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_cartlum_confirm_bulk', array( __CLASS__, 'bulk' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
	}

	/**
	 * Records one answer from the posted form.
	 *
	 * @param int $id Order.
	 * @return array { done: bool, message, outcome, order: WC_Order|null }
	 */
	private static function answer( $id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the callers check the order's nonce.
		$order   = wc_get_order( $id );
		$outcome = isset( $_POST['outcome'] ) ? sanitize_key( $_POST['outcome'] ) : '';
		$done    = $order && Cartlum_Confirm::record(
			$order,
			$outcome,
			array(
				'reason' => isset( $_POST['reason'] ) ? sanitize_key( $_POST['reason'] ) : '',
				'block'  => ! empty( $_POST['block'] ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return array(
			'done'    => $done,
			'outcome' => $outcome,
			'order'   => $order ? wc_get_order( $id ) : null,
			/* translators: 1: order number, 2: answer */
			'message' => $done ? sprintf( __( 'Order #%1$s: %2$s.', 'cartlum' ), $order->get_order_number(), Cartlum_Confirm::outcomes()[ $outcome ] ) : __( 'That order could not be updated. It may have been cancelled or completed meanwhile.', 'cartlum' ),
		);
	}

	/**
	 * Saves an answer from the list (without JavaScript).
	 */
	public static function handle() {
		$id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		if ( ! current_user_can( Cartlum_Admin::CAP ) || ! check_admin_referer( 'cartlum_confirm_' . $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cartlum' ), 403 );
		}
		$r = self::answer( $id );
		wp_safe_redirect( add_query_arg( Cartlum_Admin::notice_args( $r['message'], $r['done'] ? 'success' : 'error' ), self::back() ) );
		exit;
	}

	/**
	 * Saves an answer in place: { message, gone (leaves the list), tried (what the card says now) }.
	 */
	public static function ajax() {
		$id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		if ( ! current_user_can( Cartlum_Admin::CAP ) || ! check_ajax_referer( 'cartlum_confirm_' . $id, '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'cartlum' ) ), 403 );
		}
		$r = self::answer( $id );
		if ( ! $r['done'] ) {
			wp_send_json_error( array( 'message' => $r['message'] ) );
		}
		wp_send_json_success(
			array(
				'message' => $r['message'],
				'gone'    => in_array( $r['outcome'], array( 'confirmed', 'cancel', 'fake' ), true ),
				'tried'   => $r['order'] ? self::state_text( $r['order'] ) : '',
			)
		);
	}

	/**
	 * What can be done to several selected orders at once.
	 *
	 * @return array action => label
	 */
	public static function bulk_actions() {
		/**
		 * Actions for the selected orders on Orders → To confirm (Cartlum Pro adds "Confirm and send
		 * to the courier"). Run them on the cartlum_confirm_bulk_run filter.
		 *
		 * @param array $actions action => label.
		 */
		return (array) apply_filters( 'cartlum_confirm_bulk_actions', array( 'confirmed' => __( 'Confirm', 'cartlum' ) ) );
	}

	/**
	 * Runs an action on the selected orders.
	 */
	public static function bulk() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) || ! check_admin_referer( 'cartlum_confirm_bulk' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cartlum' ), 403 );
		}
		$action = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
		$orders = array();
		foreach ( isset( $_POST['orders'] ) ? array_map( 'absint', (array) $_POST['orders'] ) : array() as $id ) {
			$order = $id ? wc_get_order( $id ) : null;
			if ( $order ) {
				$orders[] = $order;
			}
		}
		if ( ! $orders || ! isset( self::bulk_actions()[ $action ] ) ) {
			wp_safe_redirect( add_query_arg( Cartlum_Admin::notice_args( __( 'Select one or more orders first.', 'cartlum' ), 'error' ), self::back() ) );
			exit;
		}
		if ( 'confirmed' === $action ) {
			$n = 0;
			foreach ( $orders as $order ) {
				$n += Cartlum_Confirm::record( $order, 'confirmed' ) ? 1 : 0;
			}
			/* translators: %d: number of orders */
			$msg = sprintf( _n( '%d order confirmed.', '%d orders confirmed.', $n, 'cartlum' ), $n );
		} else {
			/**
			 * Runs an add-on's action on the selected orders, returning the message to show.
			 *
			 * @param string     $message Message.
			 * @param string     $action  Action.
			 * @param WC_Order[] $orders  Orders.
			 */
			$msg = (string) apply_filters( 'cartlum_confirm_bulk_run', '', $action, $orders );
		}
		wp_safe_redirect( add_query_arg( Cartlum_Admin::notice_args( '' !== $msg ? $msg : __( 'Done.', 'cartlum' ) ), self::back() ) );
		exit;
	}

	/**
	 * The list, with the filter it was showing.
	 *
	 * @return string
	 */
	private static function back() {
		$show = isset( $_POST['show'] ) ? sanitize_key( $_POST['show'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the callers check the nonce.
		return Cartlum_Admin::url( 'cartlum-orders', isset( self::filters()[ $show ] ) && 'all' !== $show ? array( 'show' => $show ) : array() );
	}

	/**
	 * The list's filters.
	 *
	 * @return array slug => label
	 */
	private static function filters() {
		return array(
			'all'       => __( 'All', 'cartlum' ),
			'new'       => __( 'New', 'cartlum' ),
			'no_answer' => __( 'No answer', 'cartlum' ),
			'call_back' => __( 'Call back', 'cartlum' ),
			'risky'     => __( 'Risky', 'cartlum' ),
		);
	}

	/**
	 * Which filters an order belongs to.
	 *
	 * @param WC_Order   $order Order.
	 * @param array|null $stats Its number's history.
	 * @return string[]
	 */
	private static function buckets( $order, $stats ) {
		$state = (string) $order->get_meta( Cartlum_Confirm::META );
		$in    = array( 'all', '' === $state ? 'new' : $state );
		if ( $stats && in_array( $stats['risk'], array( 'risky', 'blocked' ), true ) ) {
			$in[] = 'risky';
		}
		return $in;
	}

	/**
	 * Orders placed since midnight.
	 *
	 * @return int
	 */
	private static function new_today() {
		$found = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft', 'wc-failed' ) ),
				'date_created' => '>=' . ( new DateTime( 'today', wp_timezone() ) )->getTimestamp(),
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
			)
		);
		return (int) $found->total;
	}

	/**
	 * Small photos of an order's first products.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function photos( $order ) {
		$shown = 0;
		echo '<span class="cl-ocf__pics" aria-hidden="true">';
		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			$image   = $product ? $product->get_image_id() : 0;
			if ( ! $image && $product && $product->get_parent_id() ) {
				$parent = wc_get_product( $product->get_parent_id() );
				$image  = $parent ? $parent->get_image_id() : 0;
			}
			echo $image ? wp_get_attachment_image( $image, array( 96, 96 ), false, array( 'alt' => '' ) ) : '<span class="cl-ocf__nopic"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			if ( ++$shown >= 2 ) {
				break;
			}
		}
		$more = count( $order->get_items() ) - $shown;
		if ( $more > 0 ) {
			echo '<span class="cl-ocf__more">+' . esc_html( (string) $more ) . '</span>';
		}
		echo '</span>';
	}

	/**
	 * Orders list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function column( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['cartlum_confirm'] = __( 'Confirmed', 'cartlum' );
			}
		}
		if ( ! isset( $out['cartlum_confirm'] ) ) {
			$out['cartlum_confirm'] = __( 'Confirmed', 'cartlum' );
		}
		return $out;
	}

	/**
	 * Orders list cell.
	 *
	 * @param string       $column           Column.
	 * @param WC_Order|int $order_or_post_id Order (HPOS) or its id.
	 */
	public static function cell( $column, $order_or_post_id ) {
		if ( 'cartlum_confirm' !== $column ) {
			return;
		}
		$order = $order_or_post_id instanceof WC_Order ? $order_or_post_id : wc_get_order( $order_or_post_id );
		if ( ! $order ) {
			return;
		}
		$state = (string) $order->get_meta( Cartlum_Confirm::META );
		if ( 'confirmed' === $state ) {
			echo '<span class="cl-confirm-mark">✓ ' . esc_html__( 'Confirmed', 'cartlum' ) . '</span>';
		} elseif ( '' !== $state && isset( Cartlum_Confirm::outcomes()[ $state ] ) ) {
			echo esc_html( self::state_text( $order ) );
		} else {
			echo '<span aria-hidden="true">–</span>';
		}
	}

	/**
	 * What has been tried so far: "No answer ×2", "Call back".
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function state_text( $order ) {
		$state = (string) $order->get_meta( Cartlum_Confirm::META );
		if ( 'no_answer' === $state ) {
			/* translators: %d: number of unanswered calls */
			return sprintf( __( 'No answer ×%d', 'cartlum' ), max( 1, (int) $order->get_meta( Cartlum_Confirm::TRIES ) ) );
		}
		return 'call_back' === $state ? __( 'Asked to call back', 'cartlum' ) : '';
	}

	/**
	 * One answer button.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $outcome Outcome.
	 * @param string   $class   Button class.
	 * @param string   $extra   Extra fields (HTML, already escaped).
	 */
	private static function button( $order, $outcome, $class, $extra = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cl-confirm__form">
			<input type="hidden" name="action" value="cartlum_confirm">
			<input type="hidden" name="order" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
			<input type="hidden" name="outcome" value="<?php echo esc_attr( $outcome ); ?>">
			<?php wp_nonce_field( 'cartlum_confirm_' . $order->get_id() ); ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by the caller. ?>
			<button type="submit" class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( Cartlum_Confirm::outcomes()[ $outcome ] ); ?></button>
		</form>
		<?php
	}

	/**
	 * The page.
	 */
	public static function page() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			return;
		}
		if ( ! class_exists( 'Cartlum_Cancel_Reasons' ) ) {
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-cancel-reasons.php';
		}
		$filters = self::filters();
		$show    = isset( $_GET['show'] ) ? sanitize_key( $_GET['show'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter.
		$show    = isset( $filters[ $show ] ) ? $show : 'all';
		$queue   = Cartlum_Confirm::queue();
		$counts  = array_fill_keys( array_keys( $filters ), 0 );
		$rows    = array();
		$value   = 0;
		foreach ( $queue as $order ) {
			$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
			$stats = '' !== $phone ? Cartlum_Protection::history( $order->get_billing_phone(), $order->get_id() ) : null;
			$in    = self::buckets( $order, $stats );
			foreach ( $in as $bucket ) {
				if ( isset( $counts[ $bucket ] ) ) {
					++$counts[ $bucket ];
				}
			}
			$value += (float) $order->get_total();
			if ( in_array( $show, $in, true ) ) {
				$rows[] = array( $order, $phone, $stats );
			}
		}
		$reasons = Cartlum_Cancel_Reasons::reasons();
		// Reasons that fit a confirmation call, "said no" first; fake has its own button.
		unset( $reasons['fake_order'], $reasons['refused_changed_mind'], $reasons['refused_product_doubt'] );
		if ( isset( $reasons['declined_on_call'] ) ) {
			$reasons = array( 'declined_on_call' => $reasons['declined_on_call'] ) + $reasons;
		}
		$bulk = self::bulk_actions();
		?>
		<div class="wrap cartlum">
			<div class="cl-head">
				<div>
					<h1><?php esc_html_e( 'Orders to confirm', 'cartlum' ); ?></h1>
					<p class="cl-lead"><?php esc_html_e( 'Call each new order before it goes to the courier. Oldest first.', 'cartlum' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end">
			<?php Cartlum_Admin_Settings::nav( 'cartlum-orders', '' ); ?>

			<dl class="cl-ostrip">
				<div><dt><?php esc_html_e( 'New today', 'cartlum' ); ?></dt><dd><?php echo esc_html( number_format_i18n( self::new_today() ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Waiting', 'cartlum' ); ?></dt><dd data-cartlum-waiting><?php echo esc_html( number_format_i18n( count( $queue ) ) ); ?></dd><dd class="cl-ostrip__sub"><?php echo wp_kses_post( Cartlum_Admin::money( $value ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Confirmed today', 'cartlum' ); ?></dt><dd data-cartlum-today><?php echo esc_html( number_format_i18n( Cartlum_Confirm::confirmed_today() ) ); ?></dd></div>
			</dl>

			<section class="cl-section">
				<?php if ( ! $queue ) : ?>
					<div class="cl-empty">
						<p><strong><?php esc_html_e( 'No orders waiting for confirmation.', 'cartlum' ); ?></strong></p>
						<p>
							<?php
							/* translators: %d: number of days */
							echo esc_html( sprintf( __( 'New Processing and On hold orders from the last %d days appear here until they are confirmed, cancelled or sent to a courier.', 'cartlum' ), Cartlum_Confirm::DAYS ) );
							?>
						</p>
					</div>
				<?php else : ?>
					<nav class="cl-chips cl-ofilter" aria-label="<?php esc_attr_e( 'Show', 'cartlum' ); ?>">
						<?php foreach ( $filters as $slug => $label ) : ?>
							<a class="cl-chip<?php echo $slug === $show ? ' is-active' : ''; ?>" href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-orders', 'all' === $slug ? array() : array( 'show' => $slug ) ) ); ?>"<?php echo $slug === $show ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?> <span class="cl-ofilter__n"><?php echo esc_html( number_format_i18n( $counts[ $slug ] ) ); ?></span></a>
						<?php endforeach; ?>
					</nav>

					<form id="cl-obulk" class="cl-obulk" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cartlum-bulk>
						<input type="hidden" name="action" value="cartlum_confirm_bulk">
						<input type="hidden" name="show" value="<?php echo esc_attr( $show ); ?>">
						<?php wp_nonce_field( 'cartlum_confirm_bulk' ); ?>
						<label class="cl-obulk__all"><input type="checkbox" data-cartlum-selectall> <?php esc_html_e( 'Select all', 'cartlum' ); ?></label>
						<span class="cl-obulk__n" data-cartlum-selected aria-live="polite"></span>
						<label class="screen-reader-text" for="cl-obulk-action"><?php esc_html_e( 'Action', 'cartlum' ); ?></label>
						<select id="cl-obulk-action" name="bulk">
							<?php foreach ( $bulk as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button"><?php esc_html_e( 'Apply to selected', 'cartlum' ); ?></button>
					</form>

					<?php if ( ! $rows ) : ?>
						<div class="cl-empty"><p><?php esc_html_e( 'No orders here. Choose “All” to see every order waiting.', 'cartlum' ); ?></p></div>
					<?php endif; ?>
					<ul class="cl-ocfs" data-cartlum-confirm>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							list( $order, $phone, $stats ) = $row;
							$name  = trim( $order->get_formatted_billing_full_name() );
							$name  = '' !== $name ? $name : Cartlum_Phone::display( $phone );
							$items = array();
							foreach ( $order->get_items() as $item ) {
								$items[] = ( $item->get_quantity() > 1 ? $item->get_quantity() . ' × ' : '' ) . $item->get_name();
							}
							$states = WC()->countries ? WC()->countries->get_states( $order->get_billing_country() ) : array();
							$place  = is_array( $states ) && isset( $states[ $order->get_billing_state() ] ) ? $states[ $order->get_billing_state() ] : $order->get_billing_state();
							$place  = implode( ', ', array_filter( array( $order->get_billing_address_1(), $place ) ) );
							$tried  = self::state_text( $order );
							$id     = (string) $order->get_id();
							?>
							<li class="cl-ocf" data-cartlum-order="<?php echo esc_attr( $id ); ?>">
								<div class="cl-ocf__top">
									<input type="checkbox" class="cl-ocf__pick" form="cl-obulk" name="orders[]" value="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: order number */ __( 'Select order #%s', 'cartlum' ), $order->get_order_number() ) ); ?>">
									<?php self::photos( $order ); ?>
									<a class="cl-ocf__main" href="<?php echo esc_url( Cartlum_Admin::order_url( $order->get_id() ) ); ?>">
										<span class="cl-ocf__line">
											<strong class="cl-ocf__name"><?php echo esc_html( $name ); ?></strong>
											<span class="cl-ocf__total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
										</span>
										<span class="cl-ocf__items">
											<?php
											/* translators: 1: order number, 2: products */
											echo esc_html( sprintf( __( '#%1$s · %2$s', 'cartlum' ), $order->get_order_number(), implode( ', ', $items ) ) );
											?>
										</span>
										<span class="cl-ocf__meta">
											<?php echo esc_html( implode( ' · ', array_filter( array( $place, $order->get_date_created() ? sprintf( /* translators: %s: time */ __( '%s ago', 'cartlum' ), human_time_diff( $order->get_date_created()->getTimestamp() ) ) : '' ) ) ) ); ?>
										</span>
										<span class="cl-fraud__check">
											<?php if ( $stats ) : ?>
												<span class="cl-risk cl-risk--<?php echo esc_attr( $stats['risk'] ); ?>"><?php echo esc_html( Cartlum_Protection::risk_label( $stats['risk'] ) ); ?></span>
												<?php if ( $stats['orders'] ) : ?>
													<span class="cl-risk__counts">
														<?php
														/* translators: 1: completed orders, 2: cancelled orders */
														echo esc_html( sprintf( __( '%1$d completed · %2$d cancelled', 'cartlum' ), $stats['completed'], $stats['cancelled'] ) );
														?>
													</span>
												<?php endif; ?>
											<?php endif; ?>
											<span class="cl-confirm__tried"<?php echo '' === $tried ? ' hidden' : ''; ?> data-cartlum-tried><span class="cl-dot" aria-hidden="true"></span><span><?php echo esc_html( $tried ); ?></span></span>
											<?php
											/** This action is documented in includes/admin/class-cartlum-admin-protection.php */
											do_action( 'cartlum_order_check_cell', $order );
											?>
										</span>
									</a>
									<?php if ( '' !== $phone ) : ?>
										<span class="cl-person__actions">
											<a class="cl-icon-btn" href="<?php echo esc_url( 'tel:+' . $phone ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'Call %s', 'cartlum' ), $name ) ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span></a>
											<a class="cl-icon-btn" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $phone ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'WhatsApp %s', 'cartlum' ), $name ) ); ?>"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span></a>
										</span>
									<?php endif; ?>
								</div>
								<div class="cl-confirm__answers">
									<?php
									self::button( $order, 'confirmed', 'button-primary', '<input type="hidden" name="show" value="' . esc_attr( $show ) . '">' );
									self::button( $order, 'no_answer', '', '<input type="hidden" name="show" value="' . esc_attr( $show ) . '">' );
									self::button( $order, 'call_back', '', '<input type="hidden" name="show" value="' . esc_attr( $show ) . '">' );
									?>
									<details class="cl-confirm__more">
										<summary class="button"><?php esc_html_e( 'Cancel or fake…', 'cartlum' ); ?></summary>
										<div class="cl-confirm__panel">
											<?php
											$select = '<label class="screen-reader-text" for="cl-reason-' . esc_attr( $id ) . '">' . esc_html__( 'Cancellation reason', 'cartlum' ) . '</label><select name="reason" id="cl-reason-' . esc_attr( $id ) . '">';
											foreach ( $reasons as $slug => $label ) {
												$select .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
											}
											$select .= '</select>';
											self::button( $order, 'cancel', '', $select );
											if ( '' !== $phone ) {
												$block = '<label class="cl-confirm__block"><input type="checkbox" name="block" value="1" checked> ' . esc_html__( 'Also block this number', 'cartlum' ) . '</label>';
												self::button( $order, 'fake', 'cl-button-danger', $block );
											}
											?>
										</div>
									</details>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}
