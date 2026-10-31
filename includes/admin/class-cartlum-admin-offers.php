<?php
/**
 * Offers → Overview: one card per kind of offer, with what is live and what it brought in. The
 * cards come from add-ons through the cartlum_offers_overview filter (Cartlum Pro); the free plugin
 * only draws them.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Offers.
 */
class Cartlum_Admin_Offers {

	/**
	 * The cards.
	 *
	 * @return array[] { id (tab), name, for, status: on|off, stat, added (money), color }
	 */
	public static function items() {
		/**
		 * The kinds of offers on Offers → Overview.
		 *
		 * @param array[] $items { id: the settings tab, name, for: one line on what it does,
		 *                       status: on|off, stat: what is live or what it did, added: money it
		 *                       added to orders (optional), color: the card's letter colour }.
		 */
		$items = apply_filters( 'cartlum_offers_overview', array() );
		// Only the kinds that live on Offers (loyalty points and gift cards have their own page).
		return is_array( $items ) ? array_values(
			array_filter(
				$items,
				function ( $item ) {
					return is_array( $item ) && 'cartlum-offers' === Cartlum_Admin_Settings::page_for_tab( (string) ( $item['id'] ?? '' ) );
				}
			)
		) : array();
	}

	/**
	 * The page.
	 */
	public static function page() {
		$items  = self::items();
		$labels = array(
			'on'  => __( 'On', 'cartlum' ),
			'off' => __( 'Off', 'cartlum' ),
		);
		$live   = count(
			array_filter(
				$items,
				function ( $item ) {
					return 'on' === ( $item['status'] ?? '' );
				}
			)
		);
		$added  = 0.0;
		foreach ( $items as $item ) {
			$added += (float) ( $item['added'] ?? 0 );
		}
		?>
		<div class="wrap cartlum">
			<div class="cl-head">
				<div>
					<h1><?php esc_html_e( 'Offers', 'cartlum' ); ?></h1>
					<p class="cl-lead"><?php esc_html_e( 'Ways to make each order a little bigger. Switch on the ones that fit your shop.', 'cartlum' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end">
			<?php Cartlum_Admin_Settings::nav( 'cartlum-offers', '' ); ?>
			<?php if ( $items ) : ?>
				<p class="cl-offers__sum">
					<?php
					/* translators: 1: kinds of offers switched on, 2: all kinds */
					echo esc_html( sprintf( __( '%1$d of %2$d switched on', 'cartlum' ), $live, count( $items ) ) );
					if ( $added > 0 ) {
						echo ' · ';
						/* translators: %s: money, e.g. ৳12,400 */
						echo esc_html( sprintf( __( '%s added to orders by upsells and order bumps', 'cartlum' ), html_entity_decode( wp_strip_all_tags( wc_price( $added, array( 'decimals' => 0 ) ) ), ENT_QUOTES, 'UTF-8' ) ) );
					}
					?>
				</p>
			<?php endif; ?>
			<div class="cl-int-grid">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$on  = 'on' === ( $item['status'] ?? '' );
					$url = Cartlum_Admin::url( 'cartlum-offers', array( 'tab' => (string) ( $item['id'] ?? '' ) ) );
					?>
					<section class="cl-card cl-int cl-offer">
						<div class="cl-int__head">
							<span class="cl-int__logo" style="background:<?php echo esc_attr( sanitize_hex_color( (string) ( $item['color'] ?? '' ) ) ? $item['color'] : '#0f6b5c' ); ?>" aria-hidden="true"><?php echo esc_html( mb_substr( (string) ( $item['name'] ?? '' ), 0, 1 ) ); ?></span>
							<h2><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></h2>
						</div>
						<p class="cl-int__for"><?php echo esc_html( (string) ( $item['for'] ?? '' ) ); ?></p>
						<?php if ( '' !== (string) ( $item['stat'] ?? '' ) ) : ?>
							<p class="cl-offer__stat"><?php echo esc_html( $item['stat'] ); ?></p>
						<?php endif; ?>
						<div class="cl-int__foot">
							<span class="cl-pill cl-pill--<?php echo $on ? 'on' : 'off'; ?>"><?php echo esc_html( $labels[ $on ? 'on' : 'off' ] ); ?></span>
							<a class="button<?php echo $on ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo $on ? esc_html__( 'Edit', 'cartlum' ) : esc_html__( 'Set up', 'cartlum' ); ?></a>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
