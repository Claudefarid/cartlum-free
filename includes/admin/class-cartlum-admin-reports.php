<?php
/**
 * Reports: where the orders of a period ended up (delivered, on the way, waiting, cancelled
 * before shipping, returned), the delivery success rate, the confirmation calls and fake orders
 * stopped, and why orders were cancelled. Each order is placed once, from its status, its courier
 * status and its cancellation reason. Cartlum Pro adds the same by district and by product.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Reports.
 */
class Cartlum_Admin_Reports {

	const LIMIT = 3000;

	/**
	 * Where an order ended up.
	 *
	 * @param WC_Order $order Order.
	 * @return string delivered|returned|cancelled|shipping|waiting|''
	 */
	public static function outcome( $order ) {
		$courier = Cartlum_Order_Tracking::courier( $order );
		$state   = $courier ? (string) $courier['status'] : '';
		if ( $order->has_status( 'completed' ) || 0 === strpos( $state, 'delivered' ) || 0 === strpos( $state, 'partial_delivered' ) ) {
			return 'delivered';
		}
		if ( in_array( $state, array( 'returned', 'returning', 'cancelled', 'cancelled_approval_pending' ), true ) ) {
			return 'returned';
		}
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			// Sent to a courier, or refused at the door: a return. Otherwise stopped before shipping.
			$reason = class_exists( 'Cartlum_Cancel_Reasons' ) ? Cartlum_Cancel_Reasons::get( $order ) : '';
			return $courier || 0 === strpos( $reason, 'refused_' ) ? 'returned' : 'cancelled';
		}
		if ( $order->has_status( array( 'processing', 'on-hold' ) ) ) {
			return $courier ? 'shipping' : 'waiting';
		}
		return ''; // Pending payment, drafts: not real orders yet.
	}

	/**
	 * The period's orders, one row each, cached for 10 minutes.
	 *
	 * @param int  $days  Days back.
	 * @param bool $fresh Ignore the cache.
	 * @return array { rows: array[], capped: bool, at: int }
	 */
	public static function data( $days, $fresh = false ) {
		$key    = 'cartlum_reports_' . (int) $days;
		$cached = $fresh ? false : get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( ! class_exists( 'Cartlum_Cancel_Reasons' ) ) {
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-cancel-reasons.php';
		}
		$rows   = array();
		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'date_created' => '>' . ( time() - (int) $days * DAY_IN_SECONDS ),
				'limit'        => self::LIMIT,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		foreach ( $orders as $order ) {
			$outcome = self::outcome( $order );
			if ( '' === $outcome ) {
				continue;
			}
			$products = array();
			foreach ( $order->get_items() as $item ) {
				$products[ (int) $item->get_product_id() ] = $item->get_name();
			}
			$rows[] = array(
				'id'       => $order->get_id(),
				'outcome'  => $outcome,
				'total'    => (float) $order->get_total(),
				'country'  => $order->get_billing_country(),
				'state'    => $order->get_billing_state(),
				'products' => $products,
				'confirm'  => (string) $order->get_meta( Cartlum_Confirm::META ),
				'by'       => (string) $order->get_meta( Cartlum_Confirm::BY ),
				'reason'   => in_array( $outcome, array( 'cancelled', 'returned' ), true ) ? Cartlum_Cancel_Reasons::get( $order ) : '',
			);
		}
		$out = array(
			'rows'   => $rows,
			'capped' => count( $orders ) >= self::LIMIT,
			'at'     => time(),
		);
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Delivered out of finished deliveries (delivered + returned), or null when there are none.
	 *
	 * @param int $delivered Delivered.
	 * @param int $returned  Returned.
	 * @return int|null Percent.
	 */
	public static function success( $delivered, $returned ) {
		$done = $delivered + $returned;
		return $done ? (int) round( 100 * $delivered / $done ) : null;
	}

	/**
	 * Outcome words.
	 *
	 * @return array
	 */
	public static function outcome_labels() {
		return array(
			'delivered' => __( 'Delivered', 'cartlum' ),
			'shipping'  => __( 'With the courier', 'cartlum' ),
			'waiting'   => __( 'Not sent yet', 'cartlum' ),
			'cancelled' => __( 'Cancelled before shipping', 'cartlum' ),
			'returned'  => __( 'Returned or refused', 'cartlum' ),
		);
	}

	/**
	 * The page.
	 */
	public static function page() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$days = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$data = self::data( $days, ! empty( $_GET['fresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$rows = $data['rows'];

		$count = array_fill_keys( array_keys( self::outcome_labels() ), 0 );
		$money = $count;
		foreach ( $rows as $row ) {
			++$count[ $row['outcome'] ];
			$money[ $row['outcome'] ] += $row['total'];
		}
		$total   = count( $rows );
		$rate    = self::success( $count['delivered'], $count['returned'] );
		$reasons = Cartlum_Cancel_Reasons::reasons();
		$tally   = array();
		$fake    = 0;
		$by      = array(
			'staff'    => 0,
			'customer' => 0,
		);
		foreach ( $rows as $row ) {
			if ( '' !== $row['reason'] ) {
				$tally[ $row['reason'] ] = ( $tally[ $row['reason'] ] ?? 0 ) + 1;
			}
			if ( 'fake_order' === $row['reason'] ) {
				++$fake;
			}
			if ( 'confirmed' === $row['confirm'] ) {
				++$by[ 'customer' === $row['by'] ? 'customer' : 'staff' ];
			}
		}
		arsort( $tally );
		$no_reason = $count['cancelled'] + $count['returned'] - array_sum( $tally );
		?>
		<div class="wrap cartlum cl-reports">
			<div class="cl-head">
				<div>
					<h1><?php esc_html_e( 'Reports', 'cartlum' ); ?></h1>
					<p class="cl-lead"><?php esc_html_e( 'Where your orders ended up, and what stopped the ones that did not arrive.', 'cartlum' ); ?></p>
				</div>
				<nav class="cl-segment" aria-label="<?php esc_attr_e( 'Period', 'cartlum' ); ?>">
					<?php foreach ( array( 7, 30, 90 ) as $d ) : ?>
						<a <?php echo $d === $days ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-reports', array( 'days' => $d ) ) ); ?>">
							<?php
							/* translators: %d: number of days */
							echo esc_html( sprintf( __( '%d days', 'cartlum' ), $d ) );
							?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="wp-header-end">

			<?php if ( ! $total ) : ?>
				<section class="cl-section">
					<div class="cl-empty">
						<p><strong>
							<?php
							/* translators: %d: number of days */
							echo esc_html( sprintf( __( 'No orders in the last %d days.', 'cartlum' ), $days ) );
							?>
						</strong></p>
					</div>
				</section>
			</div>
				<?php
				return;
			endif;
			?>

			<section class="cl-ledger" aria-label="<?php esc_attr_e( 'Summary', 'cartlum' ); ?>">
				<div class="cl-ledger__lead">
					<span class="cl-ledger__label"><?php esc_html_e( 'Delivery success', 'cartlum' ); ?></span>
					<span class="cl-ledger__value <?php echo null !== $rate && $rate >= 80 ? 'cl-good' : 'cl-warn'; ?>"><?php echo null === $rate ? '–' : esc_html( number_format_i18n( $rate ) . '%' ); ?></span>
					<span class="cl-ledger__sub">
						<?php
						/* translators: 1: delivered orders, 2: returned orders */
						echo esc_html( sprintf( __( '%1$d delivered · %2$d returned or refused', 'cartlum' ), $count['delivered'], $count['returned'] ) );
						?>
					</span>
				</div>
				<div class="cl-ledger__item">
					<span class="cl-ledger__label"><?php esc_html_e( 'Delivered', 'cartlum' ); ?></span>
					<span class="cl-ledger__value cl-good"><?php echo wp_kses_post( Cartlum_Admin::money( $money['delivered'] ) ); ?></span>
					<span class="cl-ledger__sub">
						<?php
						/* translators: 1: orders, 2: money */
						echo wp_kses_post( sprintf( _n( 'of %1$d order worth %2$s', 'of %1$d orders worth %2$s', $total, 'cartlum' ), $total, Cartlum_Admin::money( array_sum( $money ) ) ) );
						?>
					</span>
				</div>
				<div class="cl-ledger__item">
					<span class="cl-ledger__label"><?php esc_html_e( 'Fake orders stopped', 'cartlum' ); ?></span>
					<span class="cl-ledger__value"><?php echo esc_html( number_format_i18n( $fake ) ); ?></span>
					<span class="cl-ledger__sub"><?php esc_html_e( 'cancelled as fake before shipping', 'cartlum' ); ?></span>
				</div>
				<div class="cl-went">
					<span class="cl-went__label">
						<?php
						/* translators: %d: number of orders */
						echo esc_html( sprintf( _n( 'Where %d order went', 'Where %d orders went', $total, 'cartlum' ), $total ) );
						?>
					</span>
					<span class="cl-went__bar" role="img" aria-label="<?php echo esc_attr( implode( ', ', array_map( function ( $k, $l ) use ( $count ) { return $l . ': ' . $count[ $k ]; }, array_keys( self::outcome_labels() ), self::outcome_labels() ) ) ); ?>">
						<?php foreach ( self::outcome_labels() as $k => $label ) : ?>
							<?php if ( $count[ $k ] ) : ?>
								<span class="cl-went__seg cl-out--<?php echo esc_attr( $k ); ?>" style="flex-grow:<?php echo esc_attr( (string) round( 100 * $count[ $k ] / $total, 2 ) ); ?>"></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</span>
					<span class="cl-went__legend">
						<?php foreach ( self::outcome_labels() as $k => $label ) : ?>
							<span class="cl-went__key cl-out--<?php echo esc_attr( $k ); ?>"><span class="cl-swatch" aria-hidden="true"></span><?php echo esc_html( $label ); ?> <strong><?php echo esc_html( number_format_i18n( $count[ $k ] ) ); ?></strong></span>
						<?php endforeach; ?>
					</span>
				</div>
			</section>

			<div class="cl-columns">
				<section class="cl-section">
					<div class="cl-section__head"><h2><?php esc_html_e( 'Why orders did not arrive', 'cartlum' ); ?></h2></div>
					<?php if ( ! $tally && ! $no_reason ) : ?>
						<p class="cl-empty"><?php esc_html_e( 'No cancelled or returned orders in this period.', 'cartlum' ); ?></p>
					<?php else : ?>
						<?php $lost = max( 1, $count['cancelled'] + $count['returned'] ); ?>
						<ul class="cl-rates">
							<?php foreach ( $tally as $slug => $n ) : ?>
								<li class="cl-rate">
									<span class="cl-rate__line">
										<span class="cl-rate__name"><?php echo esc_html( $reasons[ $slug ] ?? $slug ); ?></span>
										<span class="cl-num"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
									</span>
									<span class="cl-meter cl-meter--bad" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) round( 100 * $n / $lost ) ); ?>%"></span></span>
								</li>
							<?php endforeach; ?>
							<?php if ( $no_reason > 0 ) : ?>
								<li class="cl-rate">
									<span class="cl-rate__line">
										<span class="cl-rate__name cl-muted"><?php esc_html_e( 'No reason given', 'cartlum' ); ?></span>
										<span class="cl-num"><?php echo esc_html( number_format_i18n( $no_reason ) ); ?></span>
									</span>
								</li>
							<?php endif; ?>
						</ul>
						<?php if ( $no_reason > 0 && 'yes' === Cartlum_Settings::get( 'cancel_reasons' ) ) : ?>
							<p class="cl-note"><a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-fraud' ) . '#cancel-reasons' ); ?>"><?php esc_html_e( 'Tag the cancelled orders without a reason', 'cartlum' ); ?></a></p>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<section class="cl-section">
					<div class="cl-section__head"><h2><?php esc_html_e( 'Confirmation', 'cartlum' ); ?></h2></div>
					<table class="cl-table">
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'Confirmed on the phone', 'cartlum' ); ?></th><td class="cl-num"><?php echo esc_html( number_format_i18n( $by['staff'] ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Confirmed by the customer’s link', 'cartlum' ); ?></th><td class="cl-num"><?php echo esc_html( number_format_i18n( $by['customer'] ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Cancelled before shipping', 'cartlum' ); ?></th><td class="cl-num"><?php echo esc_html( number_format_i18n( $count['cancelled'] ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Not sent yet', 'cartlum' ); ?></th><td class="cl-num"><a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-orders' ) ); ?>"><?php echo esc_html( number_format_i18n( $count['waiting'] ) ); ?></a></td></tr>
						</tbody>
					</table>
					<p class="cl-note"><?php esc_html_e( 'Every order cancelled before shipping is a courier charge and a return you did not pay for.', 'cartlum' ); ?></p>
				</section>
			</div>

			<?php
			/**
			 * More reports below the free ones (Cartlum Pro: by district and by product).
			 *
			 * @param array $rows One row per order: id, outcome, total, country, state, products, confirm, by, reason.
			 * @param int   $days Period.
			 */
			do_action( 'cartlum_reports_after', $rows, $days );
			if ( ! defined( 'CARTLUM_PRO_VERSION' ) ) {
				printf( '<p class="cl-pro-hint"><span class="cl-pro-tag">Pro</span> %1$s <a href="%2$s">%3$s</a></p>', esc_html__( 'With Cartlum Pro: delivery success and returns by district and by product, to see where to ask for an advance.', 'cartlum' ), esc_url( Cartlum_Admin::url( 'cartlum-pro' ) ), esc_html__( 'See what Pro adds', 'cartlum' ) );
			}
			?>
			<p class="cl-note">
				<?php
				/* translators: %s: time ago */
				echo esc_html( sprintf( __( 'Orders by the date they were placed. Updated %s ago.', 'cartlum' ), human_time_diff( (int) $data['at'] ) ) );
				?>
				<a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-reports', array( 'days' => $days, 'fresh' => 1 ) ) ); ?>"><?php esc_html_e( 'Refresh', 'cartlum' ); ?></a>
				<?php if ( $data['capped'] ) : ?>
					<?php
					/* translators: %d: number of orders */
					echo esc_html( sprintf( __( 'Only the newest %d orders are counted.', 'cartlum' ), self::LIMIT ) );
					?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
