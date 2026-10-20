<?php
/**
 * Dashboard view: how reminders are set up, what came back (against the previous period), where
 * abandoned carts went, who to contact now and what has already been tried with them, which
 * channels work, and which products are left behind.
 *
 * @package Cartlum
 * @var array $stats
 * @var array $previous
 * @var array $channels
 * @var array $products
 * @var array $open
 * @var array $contacts
 * @var array $next
 * @var array $series
 * @var int   $days
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included from inside a method, so its variables are local to that method, not globals.

$channel_names = array(
	'sms'      => __( 'SMS', 'cartlum' ),
	'whatsapp' => 'WhatsApp',
	'email'    => __( 'Email', 'cartlum' ),
	'call'     => __( 'Phone call', 'cartlum' ),
	'ai_call'  => __( 'AI call', 'cartlum' ),
);

// Automation status, said plainly even when everything is fine.
$gateway   = Cartlum_SMS::gateway();
$flow_on   = Cartlum_Flow::enabled();
$test_mode = $flow_on && '' !== trim( (string) Cartlum_Settings::get( 'flow_test_phone' ) );
$quiet_a   = (int) Cartlum_Settings::get( 'quiet_start' );
$quiet_b   = (int) Cartlum_Settings::get( 'quiet_end' );

// Where the abandoned carts of this period ended up.
$went = array(
	'recovered' => array( $stats['recovered'], __( 'Recovered', 'cartlum' ) ),
	'waiting'   => array( $stats['open'], __( 'Still waiting', 'cartlum' ) ),
	'lost'      => array( $stats['lost'], __( 'Lost or unsubscribed', 'cartlum' ) ),
);
$went_total = max( 1, array_sum( wp_list_pluck( $went, 0 ) ) );

$contact_words = array(
	'sms_sent'        => __( 'SMS sent', 'cartlum' ),
	'email_sent'      => __( 'Email sent', 'cartlum' ),
	'whatsapp_opened' => __( 'WhatsApp opened', 'cartlum' ),
	'whatsapp_sent'   => __( 'WhatsApp sent', 'cartlum' ),
	'call_logged'     => __( 'Called', 'cartlum' ),
	'ai_call_started' => __( 'AI call placed', 'cartlum' ),
	'ai_call_done'    => __( 'AI call', 'cartlum' ),
);
$outcomes = Cartlum_Admin_Actions::call_outcomes();

// Best channel first; a rate from a handful of carts is labelled as such.
uasort(
	$channels,
	function ( $a, $b ) {
		return (float) $b->revenue <=> (float) $a->revenue;
	}
);
$open_value = array_sum( array_map( 'floatval', wp_list_pluck( $open['rows'], 'cart_total' ) ) );
?>
<div class="wrap cartlum cl-dash">
	<div class="cl-head">
		<div>
			<h1><?php esc_html_e( 'Today', 'cartlum' ); ?></h1>
			<p class="cl-lead"><?php esc_html_e( 'What needs you now, and how your carts are doing.', 'cartlum' ); ?></p>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php Cartlum_Admin_Today::render(); ?>

	<div class="cl-head cl-head--sub">
		<h2><?php esc_html_e( 'Results', 'cartlum' ); ?></h2>
		<nav class="cl-segment" aria-label="<?php esc_attr_e( 'Period', 'cartlum' ); ?>">
			<?php foreach ( array( 7, 30, 90 ) as $d ) : ?>
				<a <?php echo $d === $days ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum', array( 'days' => $d ) ) ); ?>">
					<?php
					/* translators: %d: number of days */
					echo esc_html( sprintf( __( '%d days', 'cartlum' ), $d ) );
					?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>

	<ul class="cl-status" aria-label="<?php esc_attr_e( 'Setup', 'cartlum' ); ?>">
		<li class="cl-status__item cl-status--<?php echo esc_attr( $flow_on ? ( $test_mode ? 'warn' : 'good' ) : 'off' ); ?>">
			<span class="cl-dot" aria-hidden="true"></span>
			<?php if ( ! $flow_on ) : ?>
				<?php esc_html_e( 'Automatic reminders off', 'cartlum' ); ?>
				<a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => 'automation' ) ) ); ?>"><?php esc_html_e( 'Turn on', 'cartlum' ); ?></a>
			<?php elseif ( $test_mode ) : ?>
				<?php esc_html_e( 'Reminders in test mode: they go to your test number, not to customers', 'cartlum' ); ?>
				<a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => 'automation' ) ) ); ?>"><?php esc_html_e( 'Change', 'cartlum' ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'Automatic reminders on', 'cartlum' ); ?>
			<?php endif; ?>
		</li>
		<li class="cl-status__item cl-status--<?php echo esc_attr( $gateway ? 'good' : 'warn' ); ?>">
			<span class="cl-dot" aria-hidden="true"></span>
			<?php if ( $gateway ) : ?>
				<?php
				/* translators: %s: SMS gateway name */
				echo esc_html( sprintf( __( 'SMS via %s', 'cartlum' ), $gateway->label() ) );
				?>
			<?php else : ?>
				<?php esc_html_e( 'No SMS gateway', 'cartlum' ); ?>
				<a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => 'sms' ) ) ); ?>"><?php esc_html_e( 'Set up SMS', 'cartlum' ); ?></a>
			<?php endif; ?>
		</li>
		<?php if ( $flow_on && $quiet_a !== $quiet_b ) : ?>
			<li class="cl-status__item cl-status--off">
				<span class="cl-dot" aria-hidden="true"></span>
				<?php
				/* translators: 1: start hour, 2: end hour */
				echo esc_html( sprintf( __( 'Quiet %1$s–%2$s', 'cartlum' ), sprintf( '%02d:00', $quiet_a ), sprintf( '%02d:00', $quiet_b ) ) );
				?>
			</li>
		<?php endif; ?>
	</ul>

	<?php
	$money_change = $stats['recovered_revenue'] - $previous['recovered_revenue'];
	$rate_before  = (float) $previous['recovery_rate'];
	$trend_rate   = array_map(
		function ( $b ) {
			return $b['abandoned'] ? 100 * $b['recovered'] / $b['abandoned'] : 0;
		},
		$series
	);
	$has_before   = $previous['captured'] > 0;
	echo '<div class="cl-stats">';
	Cartlum_Admin_Charts::stat(
		array(
			'icon'  => 'money',
			'label' => __( 'Recovered', 'cartlum' ),
			'value' => Cartlum_Admin::money( $stats['recovered_revenue'] ),
			/* translators: %d: number of orders */
			'sub'   => sprintf( _n( '%d order', '%d orders', $stats['recovered'], 'cartlum' ), $stats['recovered'] ),
			'delta' => $has_before ? Cartlum_Admin_Charts::delta( $stats['recovered_revenue'], $previous['recovered_revenue'], ( $money_change >= 0 ? '+' : '−' ) . Cartlum_Admin_Charts::short_money( abs( $money_change ) ) ) : null,
			'trend' => wp_list_pluck( $series, 'revenue' ),
			'tone'  => 'good',
		)
	);
	Cartlum_Admin_Charts::stat(
		array(
			'icon'  => 'percent',
			'label' => __( 'Recovery rate', 'cartlum' ),
			'value' => esc_html( number_format_i18n( $stats['recovery_rate'], 0 ) . '%' ),
			/* translators: %d: abandoned carts */
			'sub'   => sprintf( _n( 'of %d abandoned cart', 'of %d abandoned carts', $stats['abandoned'], 'cartlum' ), $stats['abandoned'] ),
			/* translators: %s: percentage points */
			'delta' => $has_before ? Cartlum_Admin_Charts::delta( $stats['recovery_rate'], $rate_before, sprintf( __( '%s pts', 'cartlum' ), number_format_i18n( abs( $stats['recovery_rate'] - $rate_before ), 0 ) ) ) : null,
			'trend' => $trend_rate,
		)
	);
	Cartlum_Admin_Charts::stat(
		array(
			'icon'  => 'clock',
			'label' => __( 'Still waiting', 'cartlum' ),
			'value' => Cartlum_Admin::money( $stats['open_value'] ),
			/* translators: %d: number of carts */
			'sub'   => sprintf( _n( '%d cart to follow up', '%d carts to follow up', $stats['open'], 'cartlum' ), $stats['open'] ),
			'tone'  => 'warn',
		)
	);
	Cartlum_Admin_Charts::stat(
		array(
			'icon'  => 'cart',
			'label' => __( 'Carts saved', 'cartlum' ),
			'value' => esc_html( number_format_i18n( $stats['captured'] ) ),
			/* translators: %d: number of carts */
			'sub'   => sprintf( __( '%d ordered without help', 'cartlum' ), $stats['converted'] ),
			'delta' => $has_before ? Cartlum_Admin_Charts::delta( $stats['captured'], $previous['captured'], ( $stats['captured'] >= $previous['captured'] ? '+' : '−' ) . number_format_i18n( abs( $stats['captured'] - $previous['captured'] ) ) ) : null,
			'trend' => wp_list_pluck( $series, 'captured' ),
		)
	);
	echo '</div>';
	?>

	<section class="cl-section cl-chartcard">
		<div class="cl-section__head">
			<h2><?php echo esc_html( $days > 31 ? __( 'Recovered per week', 'cartlum' ) : __( 'Recovered per day', 'cartlum' ) ); ?></h2>
			<span class="cl-muted">
				<?php
				/* translators: 1: money, 2: number of days */
				echo wp_kses_post( sprintf( __( '%1$s in the last %2$d days', 'cartlum' ), Cartlum_Admin::money( $stats['recovered_revenue'] ), $days ) );
				?>
			</span>
		</div>
		<?php Cartlum_Admin_Charts::columns( $series, $days > 31 ? __( 'Recovered per week', 'cartlum' ) : __( 'Recovered per day', 'cartlum' ) ); ?>
		<?php if ( $stats['abandoned'] > 0 ) : ?>
			<div class="cl-went">
				<span class="cl-went__label">
					<?php
					/* translators: %d: number of carts */
					echo esc_html( sprintf( _n( 'Where %d abandoned cart went', 'Where %d abandoned carts went', $stats['abandoned'], 'cartlum' ), $stats['abandoned'] ) );
					?>
				</span>
				<span class="cl-went__bar" role="img" aria-label="<?php echo esc_attr( implode( ', ', array_map( function ( $w ) { return $w[1] . ': ' . $w[0]; }, $went ) ) ); ?>">
					<?php foreach ( $went as $key => $w ) : ?>
						<?php if ( $w[0] > 0 ) : ?>
							<span class="cl-went__seg cl-went__seg--<?php echo esc_attr( $key ); ?>" style="flex-grow:<?php echo esc_attr( (string) round( 100 * $w[0] / $went_total, 2 ) ); ?>"></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</span>
				<span class="cl-went__legend">
					<?php foreach ( $went as $key => $w ) : ?>
						<span class="cl-went__key cl-went__key--<?php echo esc_attr( $key ); ?>"><span class="cl-swatch" aria-hidden="true"></span><?php echo esc_html( $w[1] ); ?> <strong><?php echo esc_html( number_format_i18n( $w[0] ) ); ?></strong></span>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	</section>

	<div class="cl-columns">
		<section class="cl-section">
			<div class="cl-section__head">
				<h2>
					<?php esc_html_e( 'Waiting for you', 'cartlum' ); ?>
					<?php if ( $open['rows'] ) : ?>
						<span class="cl-count"><?php echo esc_html( number_format_i18n( (int) $open['total'] ) ); ?></span>
					<?php endif; ?>
				</h2>
				<?php if ( $open['rows'] ) : ?>
					<a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-carts', array( 'status' => 'abandoned' ) ) ); ?>"><?php esc_html_e( 'All abandoned carts', 'cartlum' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! $open['rows'] ) : ?>
				<div class="cl-empty">
					<p><strong><?php esc_html_e( 'Nobody to follow up right now.', 'cartlum' ); ?></strong></p>
					<p><?php esc_html_e( 'When a customer types a phone number at checkout and leaves, they appear here with buttons to call or message them.', 'cartlum' ); ?></p>
				</div>
			<?php else : ?>
				<p class="cl-section__intro">
					<?php
					/* translators: %s: money */
					echo wp_kses_post( sprintf( __( 'Newest first · %s in these carts', 'cartlum' ), Cartlum_Admin::money( $open_value ) ) );
					?>
				</p>
				<ul class="cl-people">
					<?php
					foreach ( $open['rows'] as $row ) {
						// What has been tried with this person, and what happens next.
						$last = $contacts[ (int) $row->id ] ?? null;
						if ( '' !== (string) $row->call_outcome && isset( $outcomes[ $row->call_outcome ] ) ) {
							/* translators: %s: call outcome */
							$cl_state = array( 'done', sprintf( __( 'Called: %s', 'cartlum' ), $outcomes[ $row->call_outcome ] ) );
						} elseif ( $last ) {
							/* translators: 1: what was done, 2: time ago */
							$cl_state = array( 'done', sprintf( __( '%1$s %2$s', 'cartlum' ), $contact_words[ $last->type ] ?? $last->type, Cartlum_Admin::ago( $last->created_at ) ) );
						} else {
							$cl_state = array( 'todo', __( 'Not contacted yet', 'cartlum' ) );
						}
						if ( ! empty( $next[ (int) $row->id ] ) && ! (int) $row->flow_stopped ) {
							/* translators: %s: time until the next reminder */
							$cl_state[1] .= ' · ' . sprintf( __( 'next reminder in %s', 'cartlum' ), human_time_diff( time(), (int) $next[ (int) $row->id ] ) );
						}
						require CARTLUM_DIR . 'includes/admin/views/partials/person-row.php';
					}
					?>
				</ul>
			<?php endif; ?>
		</section>

		<div class="cl-stack">
			<section class="cl-section">
				<div class="cl-section__head">
					<h2><?php esc_html_e( 'What works', 'cartlum' ); ?></h2>
				</div>
				<?php if ( ! $channels ) : ?>
					<div class="cl-empty">
						<p><?php esc_html_e( 'Once you contact customers, this shows which channel brings carts back.', 'cartlum' ); ?></p>
					</div>
				<?php else : ?>
					<?php $any_few = false; ?>
					<ul class="cl-rates">
						<?php foreach ( $channels as $channel => $row ) : ?>
							<?php
							$rate    = $row->contacted ? round( 100 * $row->recovered / $row->contacted ) : 0;
							$few     = (int) $row->contacted < 5;
							$any_few = $any_few || $few;
							?>
							<li class="cl-rate">
								<span class="cl-rate__line">
									<span class="cl-rate__name"><?php echo Cartlum_Admin_Charts::icon( $channel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG shapes. ?><?php echo esc_html( $channel_names[ $channel ] ?? $channel ); ?></span>
									<span class="cl-rate__money"><?php echo wp_kses_post( Cartlum_Admin::money( $row->revenue ) ); ?></span>
								</span>
								<span class="cl-meter<?php echo $few ? ' cl-meter--few' : ''; ?>" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) $rate ); ?>%"></span></span>
								<span class="cl-rate__sub">
									<?php
									/* translators: 1: carts recovered, 2: carts contacted, 3: rate */
									echo esc_html( sprintf( __( '%1$d of %2$d recovered · %3$d%%', 'cartlum' ), $row->recovered, $row->contacted, $rate ) );
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="cl-note">
						<?php
						esc_html_e( 'A cart contacted on two channels counts under both.', 'cartlum' );
						if ( $any_few ) {
							echo ' ' . esc_html__( 'Lighter bars: fewer than 5 carts, too early to judge.', 'cartlum' );
						}
						?>
					</p>
				<?php endif; ?>
			</section>

			<section class="cl-section">
				<div class="cl-section__head">
					<h2><?php esc_html_e( 'Most abandoned products', 'cartlum' ); ?></h2>
				</div>
				<?php if ( ! $products ) : ?>
					<div class="cl-empty">
						<p><?php esc_html_e( 'Once carts are abandoned, this shows which products people leave behind most — worth checking their price, photos and delivery charge.', 'cartlum' ); ?></p>
					</div>
				<?php else : ?>
					<ul class="cl-products">
						<?php foreach ( $products as $product ) : ?>
							<?php
							$wc_product = wc_get_product( $product['product_id'] );
							$thumb      = $wc_product ? wp_get_attachment_image_url( (int) $wc_product->get_image_id(), 'thumbnail' ) : '';
							$edit       = get_edit_post_link( $product['product_id'] );
							$p_rate     = $product['carts'] ? round( 100 * $product['recovered'] / $product['carts'] ) : 0;
							?>
							<li class="cl-product">
								<span class="cl-product__thumb" aria-hidden="true">
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="36" height="36" loading="lazy">
									<?php endif; ?>
								</span>
								<span class="cl-product__main">
									<?php if ( $edit ) : ?>
										<a class="cl-product__name" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $product['name'] ); ?></a>
									<?php else : ?>
										<span class="cl-product__name"><?php echo esc_html( $product['name'] ); ?></span>
									<?php endif; ?>
									<span class="cl-product__sub">
										<?php
										/* translators: 1: abandoned carts, 2: recovered, 3: rate */
										echo esc_html( sprintf( _n( '%1$d cart · %2$d recovered (%3$d%%)', '%1$d carts · %2$d recovered (%3$d%%)', $product['carts'], 'cartlum' ), $product['carts'], $product['recovered'], $p_rate ) );
										?>
									</span>
								</span>
								<span class="cl-product__value">
									<?php echo wp_kses_post( Cartlum_Admin::money( $product['value'] ) ); ?>
									<span class="cl-product__left"><?php esc_html_e( 'left', 'cartlum' ); ?></span>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	</div>

	<?php Cartlum_Admin_Today::features_board(); ?>
</div>
