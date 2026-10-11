<?php
/**
 * Fake Orders → Risky orders: recent orders from numbers that often cancel or are blocked, with
 * each number's history and one tap to call before shipping. "All recent" shows every order with
 * its check, so staff can spot what the rules missed.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Fraud.
 */
class Cartlum_Admin_Fraud {

	const DAYS = 30;

	/**
	 * Recent orders with their risk, newest first.
	 *
	 * @param bool $risky_only Only risky and blocked numbers.
	 * @return array[] { order, stats }
	 */
	private static function orders( $risky_only ) {
		$out = array();
		foreach ( wc_get_orders(
			array(
				'type'         => 'shop_order',
				'date_created' => '>' . ( time() - self::DAYS * DAY_IN_SECONDS ),
				'orderby'      => 'date',
				'order'        => 'DESC',
				'limit'        => 100,
			)
		) as $order ) {
			if ( '' === Cartlum_Phone::normalize( $order->get_billing_phone() ) ) {
				continue;
			}
			$stats = Cartlum_Protection::history( $order->get_billing_phone(), $order->get_id() );
			// Risky: orders still to ship or fulfil; cancelled and failed ones need nothing more.
			if ( $risky_only && ( ! in_array( $stats['risk'], array( 'risky', 'blocked' ), true ) || $order->has_status( array( 'cancelled', 'failed', 'refunded', 'completed', 'checkout-draft' ) ) ) ) {
				continue;
			}
			$out[] = array(
				'order' => $order,
				'stats' => $stats,
			);
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * The page.
	 */
	public static function page() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			return;
		}
		$on    = 'yes' === Cartlum_Settings::get( 'protect_enabled' );
		$show  = isset( $_GET['show'] ) && 'all' === $_GET['show'] ? 'all' : 'risky'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rules = Cartlum_Admin::url( 'cartlum-fraud', array( 'tab' => 'protection' ) );
		?>
		<div class="wrap cartlum">
			<div class="cl-head">
				<h1><?php esc_html_e( 'Fake orders', 'cartlum' ); ?></h1>
				<?php if ( $on ) : ?>
					<nav class="cl-segment" aria-label="<?php esc_attr_e( 'Show', 'cartlum' ); ?>">
						<a <?php echo 'risky' === $show ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-fraud' ) ); ?>"><?php esc_html_e( 'Risky and blocked', 'cartlum' ); ?></a>
						<a <?php echo 'all' === $show ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-fraud', array( 'show' => 'all' ) ) ); ?>"><?php esc_html_e( 'All recent', 'cartlum' ); ?></a>
					</nav>
				<?php endif; ?>
			</div>
			<hr class="wp-header-end">
			<?php Cartlum_Admin_Settings::nav( 'cartlum-fraud', '' ); ?>

			<?php if ( ! $on ) : ?>
				<div class="cl-section">
					<div class="cl-empty">
						<p><strong><?php esc_html_e( 'Fake-order protection is off.', 'cartlum' ); ?></strong></p>
						<p><?php esc_html_e( 'Turn it on to block numbers, IP addresses and devices, stop repeat orders, ask risky cash-on-delivery customers for an SMS code, and see each number’s order history here.', 'cartlum' ); ?></p>
						<p><a class="button button-primary" href="<?php echo esc_url( $rules ); ?>"><?php esc_html_e( 'Set up protection', 'cartlum' ); ?></a></p>
					</div>
				</div>
				<?php Cartlum_Cancel_Reasons::panel(); ?>
			</div>
				<?php
				return;
			endif;

			$list    = Cartlum_Protection::blocklist();
			$blocked = array(
				/* translators: %d: count */
				sprintf( _n( '%d number', '%d numbers', count( $list['phones'] ), 'cartlum' ), count( $list['phones'] ) ),
				/* translators: %d: count */
				sprintf( _n( '%d IP address or range', '%d IP addresses or ranges', count( $list['ips'] ) + count( $list['ranges'] ), 'cartlum' ), count( $list['ips'] ) + count( $list['ranges'] ) ),
				/* translators: %d: count */
				sprintf( _n( '%d device', '%d devices', count( $list['devices'] ), 'cartlum' ), count( $list['devices'] ) ),
			);
			$rows = self::orders( 'risky' === $show );
			?>
			<p class="cl-section__intro cl-fraud__summary">
				<?php
				/* translators: %s: list of blocked counts */
				echo esc_html( sprintf( __( 'Blocked: %s.', 'cartlum' ), implode( ' · ', $blocked ) ) );
				?>
				<a href="<?php echo esc_url( $rules ); ?>"><?php esc_html_e( 'Edit the blocklist and rules', 'cartlum' ); ?></a>
			</p>

			<section class="cl-section">
				<?php if ( ! $rows ) : ?>
					<div class="cl-empty">
						<?php if ( 'risky' === $show ) : ?>
							<p><strong>
								<?php
								/* translators: %d: number of days */
								echo esc_html( sprintf( __( 'No orders from risky or blocked numbers in the last %d days.', 'cartlum' ), self::DAYS ) );
								?>
							</strong></p>
							<p><?php esc_html_e( 'A number becomes risky when it cancels or refuses orders more often than it completes them. Orders from such numbers appear here so you can call before shipping.', 'cartlum' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'No recent orders with a phone number.', 'cartlum' ); ?></p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<ul class="cl-people">
						<?php foreach ( $rows as $item ) : ?>
							<?php
							$order = $item['order'];
							$stats = $item['stats'];
							$phone = Cartlum_Phone::normalize( $order->get_billing_phone() );
							$name  = trim( $order->get_formatted_billing_full_name() );
							$name  = '' !== $name ? $name : Cartlum_Phone::display( $phone );
							?>
							<li class="cl-person">
								<a class="cl-person__main" href="<?php echo esc_url( Cartlum_Admin::order_url( $order->get_id() ) ); ?>">
									<span class="cl-person__line">
										<span class="cl-person__name"><span class="cl-person__nametext"><?php echo esc_html( $name ); ?></span></span>
										<span class="cl-person__value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
									</span>
									<span class="cl-person__line cl-muted">
										<span class="cl-person__items">
											<?php
											/* translators: 1: order number, 2: order status */
											echo esc_html( sprintf( __( 'Order #%1$s · %2$s', 'cartlum' ), $order->get_order_number(), wc_get_order_status_name( $order->get_status() ) ) );
											?>
										</span>
										<span class="cl-person__time"><?php echo esc_html( $order->get_date_created() ? sprintf( /* translators: %s: time */ __( '%s ago', 'cartlum' ), human_time_diff( $order->get_date_created()->getTimestamp() ) ) : '' ); ?></span>
									</span>
									<span class="cl-fraud__check">
										<span class="cl-risk cl-risk--<?php echo esc_attr( $stats['risk'] ); ?>"><?php echo esc_html( Cartlum_Protection::risk_label( $stats['risk'] ) ); ?></span>
										<span class="cl-risk__counts">
											<?php
											/* translators: 1: completed orders, 2: cancelled orders */
											echo esc_html( sprintf( __( '%1$d completed · %2$d cancelled', 'cartlum' ), $stats['completed'], $stats['cancelled'] ) );
											?>
										</span>
										<?php
										/** This action is documented in includes/admin/class-cartlum-admin-protection.php */
										do_action( 'cartlum_order_check_cell', $order );
										?>
									</span>
								</a>
								<span class="cl-person__actions">
									<a class="cl-icon-btn" href="<?php echo esc_url( 'tel:+' . $phone ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'Call %s', 'cartlum' ), $name ) ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span></a>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="cl-note">
						<?php
						/* translators: %d: number of days */
						echo esc_html( sprintf( __( 'Orders from the last %d days with a phone number. Open an order to block its number, IP address or device.', 'cartlum' ), self::DAYS ) );
						?>
					</p>
				<?php endif; ?>
			</section>
			<?php Cartlum_Cancel_Reasons::panel(); ?>
		</div>
		<?php
	}
}
