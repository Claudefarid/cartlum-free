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
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
	}

	/**
	 * Saves an answer from the list.
	 */
	public static function handle() {
		$id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		if ( ! current_user_can( Cartlum_Admin::CAP ) || ! check_admin_referer( 'cartlum_confirm_' . $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cartlum' ), 403 );
		}
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
		if ( $done ) {
			/* translators: 1: order number, 2: answer */
			$msg  = sprintf( __( 'Order #%1$s: %2$s.', 'cartlum' ), $order->get_order_number(), Cartlum_Confirm::outcomes()[ $outcome ] );
			$type = 'success';
		} else {
			$msg  = __( 'That order could not be updated. It may have been cancelled or completed meanwhile.', 'cartlum' );
			$type = 'error';
		}
		wp_safe_redirect( add_query_arg( Cartlum_Admin::notice_args( $msg, $type ), Cartlum_Admin::url( 'cartlum-orders' ) ) );
		exit;
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
		$orders  = Cartlum_Confirm::queue();
		$today   = Cartlum_Confirm::confirmed_today();
		$reasons = Cartlum_Cancel_Reasons::reasons();
		// Reasons that fit a confirmation call, "said no" first; fake has its own button.
		unset( $reasons['fake_order'], $reasons['refused_changed_mind'], $reasons['refused_product_doubt'] );
		if ( isset( $reasons['declined_on_call'] ) ) {
			$reasons = array( 'declined_on_call' => $reasons['declined_on_call'] ) + $reasons;
		}
		$value = 0;
		foreach ( $orders as $order ) {
			$value += (float) $order->get_total();
		}
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

			<section class="cl-section">
				<?php if ( ! $orders ) : ?>
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
					<p class="cl-section__intro">
						<?php
						/* translators: 1: number of orders, 2: money, 3: orders confirmed today */
						echo wp_kses_post( sprintf( _n( '%1$d order waiting · %2$s · %3$d confirmed today', '%1$d orders waiting · %2$s · %3$d confirmed today', count( $orders ), 'cartlum' ), count( $orders ), Cartlum_Admin::money( $value ), $today ) );
						?>
					</p>
					<ul class="cl-people cl-confirm">
						<?php foreach ( $orders as $order ) : ?>
							<?php
							$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
							$name  = trim( $order->get_formatted_billing_full_name() );
							$name  = '' !== $name ? $name : Cartlum_Phone::display( $phone );
							$stats = '' !== $phone ? Cartlum_Protection::history( $order->get_billing_phone(), $order->get_id() ) : null;
							$items = array();
							foreach ( $order->get_items() as $item ) {
								$items[] = ( $item->get_quantity() > 1 ? $item->get_quantity() . ' × ' : '' ) . $item->get_name();
							}
							$states = WC()->countries ? WC()->countries->get_states( $order->get_billing_country() ) : array();
							$place  = is_array( $states ) && isset( $states[ $order->get_billing_state() ] ) ? $states[ $order->get_billing_state() ] : $order->get_billing_state();
							$place  = implode( ', ', array_filter( array( $order->get_billing_address_1(), $place ) ) );
							$tried  = self::state_text( $order );
							?>
							<li class="cl-person cl-confirm__row">
								<div class="cl-confirm__top">
									<a class="cl-person__main" href="<?php echo esc_url( Cartlum_Admin::order_url( $order->get_id() ) ); ?>">
										<span class="cl-person__line">
											<span class="cl-person__name"><span class="cl-person__nametext"><?php echo esc_html( $name ); ?></span></span>
											<span class="cl-person__value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
										</span>
										<span class="cl-person__line cl-muted">
											<span class="cl-person__items">
												<?php
												/* translators: 1: order number, 2: products */
												echo esc_html( sprintf( __( '#%1$s · %2$s', 'cartlum' ), $order->get_order_number(), implode( ', ', $items ) ) );
												?>
											</span>
											<span class="cl-person__time"><?php echo esc_html( $order->get_date_created() ? sprintf( /* translators: %s: time */ __( '%s ago', 'cartlum' ), human_time_diff( $order->get_date_created()->getTimestamp() ) ) : '' ); ?></span>
										</span>
										<?php if ( '' !== $place ) : ?>
											<span class="cl-muted cl-confirm__place"><?php echo esc_html( $place ); ?></span>
										<?php endif; ?>
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
											<?php if ( '' !== $tried ) : ?>
												<span class="cl-confirm__tried"><span class="cl-dot" aria-hidden="true"></span><?php echo esc_html( $tried ); ?></span>
											<?php endif; ?>
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
									self::button( $order, 'confirmed', 'button-primary' );
									self::button( $order, 'no_answer', '' );
									self::button( $order, 'call_back', '' );
									?>
									<details class="cl-confirm__more">
										<summary class="button"><?php esc_html_e( 'Cancel or fake…', 'cartlum' ); ?></summary>
										<div class="cl-confirm__panel">
											<?php
											$select = '<label class="screen-reader-text" for="cl-reason-' . esc_attr( (string) $order->get_id() ) . '">' . esc_html__( 'Cancellation reason', 'cartlum' ) . '</label><select name="reason" id="cl-reason-' . esc_attr( (string) $order->get_id() ) . '">';
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
