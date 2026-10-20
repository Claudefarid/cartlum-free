<?php
/**
 * The admin's small charts and icons, in plain HTML and inline SVG (no chart library): stat cards
 * with a sparkline, a column chart with a tooltip on every column and a table view, and the line
 * icons that name what a row or number is about.
 *
 * One colour per chart (the admin accent); status colours only where they mean a state. Every
 * value a tooltip shows is also in the table view.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Charts.
 */
class Cartlum_Admin_Charts {

	/**
	 * Line icons (24×24, stroked with currentColor).
	 *
	 * @return array name => SVG shapes
	 */
	private static function icons() {
		return array(
			'call'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'check'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
			'parcel'   => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5L12 12l9-4.5M12 12v9"/>',
			'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M12 8v5M12 16h.01"/>',
			'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'setup'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
			'money'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/>',
			'percent'  => '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h12l2-8H6.5"/>',
			'sms'      => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
			'whatsapp' => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1a4 4 0 0 1-2-2l1-1-1-2z"/>',
			'email'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			'ai_call'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/><path d="M17 3v4M15 5h4"/>',
		);
	}

	/**
	 * An icon, decorative (the words beside it carry the meaning).
	 *
	 * @param string $name Icon.
	 * @return string SVG.
	 */
	public static function icon( $name ) {
		$icons = self::icons();
		$shape = isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['setup'];
		return '<svg class="cl-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $shape . '</svg>';
	}

	/**
	 * Money for axes and cards, shortened from a thousand up (৳9.6K).
	 *
	 * @param float $amount Amount.
	 * @return string Plain text.
	 */
	public static function short_money( $amount ) {
		$amount = (float) $amount;
		$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		if ( abs( $amount ) >= 1000000 ) {
			$text = number_format_i18n( $amount / 1000000, 1 ) . 'M';
		} elseif ( abs( $amount ) >= 1000 ) {
			$text = number_format_i18n( $amount / 1000, abs( $amount ) >= 10000 ? 0 : 1 ) . 'K';
		} else {
			$text = number_format_i18n( $amount );
		}
		return $symbol . $text;
	}

	/**
	 * A sparkline: the period in the muted line, its last point in the accent.
	 *
	 * @param float[] $values Oldest first.
	 * @return string HTML.
	 */
	public static function sparkline( array $values ) {
		$values = array_map( 'floatval', array_values( $values ) );
		$n      = count( $values );
		if ( $n < 2 || ! array_filter( $values ) ) {
			return '';
		}
		$max    = max( $values );
		$max    = $max > 0 ? $max : 1;
		$points = array();
		foreach ( $values as $i => $v ) {
			$points[] = round( 100 * $i / ( $n - 1 ), 2 ) . ',' . round( 30 - 26 * $v / $max, 2 );
		}
		$last = end( $values );
		return '<span class="cl-spark" aria-hidden="true"><svg viewBox="0 0 100 32" preserveAspectRatio="none"><polyline vector-effect="non-scaling-stroke" points="' . esc_attr( implode( ' ', $points ) ) . '"/></svg>'
			. '<span class="cl-spark__dot" style="left:100%;top:' . esc_attr( (string) round( 100 * ( 30 - 26 * $last / $max ) / 32, 2 ) ) . '%"></span></span>';
	}

	/**
	 * A change against the period before, coloured by whether it is good.
	 *
	 * @param float  $now    This period.
	 * @param float  $before The period before.
	 * @param string $text   What to say, e.g. "+৳1.2K".
	 * @return string HTML.
	 */
	public static function delta( $now, $before, $text ) {
		$dir = $now > $before ? 'up' : ( $now < $before ? 'down' : 'flat' );
		$arrow = array(
			'up'   => '↑',
			'down' => '↓',
			'flat' => '→',
		)[ $dir ];
		return '<span class="cl-delta cl-delta--' . esc_attr( $dir ) . '">' . esc_html( $arrow . ' ' . $text ) . '</span>';
	}

	/**
	 * A stat card: icon, label, value, what it means, change and trend.
	 *
	 * @param array $card { icon, label, value (HTML), sub, delta (HTML), trend float[], tone }.
	 */
	public static function stat( array $card ) {
		$tone = isset( $card['tone'] ) ? $card['tone'] : '';
		?>
		<div class="cl-stat<?php echo $tone ? ' cl-stat--' . esc_attr( $tone ) : ''; ?>">
			<div class="cl-stat__top">
				<span class="cl-stat__icon"><?php echo self::icon( $card['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG shapes defined in this class. ?></span>
				<span class="cl-stat__label"><?php echo esc_html( $card['label'] ); ?></span>
			</div>
			<div class="cl-stat__mid">
				<span class="cl-stat__value"><?php echo wp_kses_post( $card['value'] ); ?></span>
				<?php echo self::sparkline( isset( $card['trend'] ) ? $card['trend'] : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- numbers escaped with esc_attr when built. ?>
			</div>
			<div class="cl-stat__sub"><?php echo esc_html( $card['sub'] ); ?><?php echo isset( $card['delta'] ) ? ' ' . wp_kses_post( $card['delta'] ) : ''; ?></div>
		</div>
		<?php
	}

	/**
	 * Clean axis steps: 0 and up to three round values covering the largest one.
	 *
	 * @param float $max Largest value.
	 * @return float[]
	 */
	private static function ticks( $max ) {
		if ( $max <= 0 ) {
			return array( 0, 1 );
		}
		$raw  = $max / 3;
		$mag  = pow( 10, floor( log10( $raw ) ) );
		$step = $mag;
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $m * $mag >= $raw ) {
				$step = $m * $mag;
				break;
			}
		}
		$out = array();
		for ( $v = 0; $v < $max + $step; $v += $step ) {
			$out[] = $v;
			if ( $v >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * A column chart of one money series, with a tooltip on each column (hover and keyboard) and the
	 * same numbers as a table.
	 *
	 * @param array[] $series Buckets from Cartlum_Carts::daily().
	 * @param string  $title  Chart title (also the table's caption).
	 */
	public static function columns( array $series, $title ) {
		$max   = max( array_merge( array( 0 ), wp_list_pluck( $series, 'revenue' ) ) );
		$ticks = self::ticks( $max );
		$top   = end( $ticks );
		$n     = count( $series );
		$last  = $n - 1;
		?>
		<div class="cl-chart" data-cartlum-chart>
			<div class="cl-chart__plot">
				<div class="cl-chart__grid" aria-hidden="true">
					<?php foreach ( array_reverse( $ticks ) as $t ) : ?>
						<span class="cl-chart__tick" style="bottom:<?php echo esc_attr( (string) round( 100 * $t / $top, 2 ) ); ?>%"><span><?php echo esc_html( self::short_money( $t ) ); ?></span></span>
					<?php endforeach; ?>
				</div>
				<div class="cl-chart__cols">
					<?php foreach ( $series as $i => $b ) : ?>
						<?php
						$label = sprintf(
							/* translators: 1: day or week, 2: money, 3: orders */
							_n( '%1$s: %2$s from %3$d recovered order', '%1$s: %2$s from %3$d recovered orders', $b['recovered'], 'cartlum' ),
							$b['label'],
							html_entity_decode( wp_strip_all_tags( wc_price( $b['revenue'] ) ), ENT_QUOTES, 'UTF-8' ),
							$b['recovered']
						);
						?>
						<span class="cl-chart__col<?php echo $i === $last ? ' is-current' : ''; ?>" tabindex="0" role="img" aria-label="<?php echo esc_attr( $label ); ?>" data-tip="<?php echo esc_attr( $label ); ?>">
							<span class="cl-chart__bar" style="height:<?php echo esc_attr( (string) ( $b['revenue'] > 0 ? max( 1.5, round( 100 * $b['revenue'] / $top, 2 ) ) : 0 ) ); ?>%"></span>
						</span>
					<?php endforeach; ?>
				</div>
				<span class="cl-chart__tip" role="tooltip" hidden></span>
			</div>
			<div class="cl-chart__x" aria-hidden="true">
				<span><?php echo esc_html( $series[0]['label'] ?? '' ); ?></span>
				<?php if ( $n > 2 ) : ?>
					<span><?php echo esc_html( $series[ (int) floor( $n / 2 ) ]['label'] ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $series[ $last ]['label'] ?? '' ); ?></span>
			</div>
			<details class="cl-chart__table">
				<summary><?php esc_html_e( 'Show as table', 'cartlum' ); ?></summary>
				<table class="cl-table">
					<caption class="screen-reader-text"><?php echo esc_html( $title ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Day', 'cartlum' ); ?></th><th scope="col" class="cl-num"><?php esc_html_e( 'Carts saved', 'cartlum' ); ?></th><th scope="col" class="cl-num"><?php esc_html_e( 'Recovered orders', 'cartlum' ); ?></th><th scope="col" class="cl-num"><?php esc_html_e( 'Recovered', 'cartlum' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( array_reverse( $series ) as $b ) : ?>
							<tr><th scope="row"><?php echo esc_html( $b['label'] ); ?></th><td class="cl-num"><?php echo esc_html( number_format_i18n( $b['captured'] ) ); ?></td><td class="cl-num"><?php echo esc_html( number_format_i18n( $b['recovered'] ) ); ?></td><td class="cl-num"><?php echo wp_kses_post( wc_price( $b['revenue'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		</div>
		<?php
	}
}
