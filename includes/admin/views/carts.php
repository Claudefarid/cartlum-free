<?php
/**
 * Carts list. A table on wide screens; on phones the same rows as a list with the call and
 * WhatsApp buttons in reach, because the table's collapsed mobile layout hides them.
 *
 * @package Cartlum
 * @var Cartlum_Carts_Table $table
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included from inside a method, so its variables are local to that method, not globals.

$cl_status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cl_page          = $table->get_pagenum();
$cl_pages         = (int) $table->get_pagination_arg( 'total_pages' );
// Period (when the cart was started, in the store's time zone) and product, above the status links.
$cl_filters  = Cartlum_Carts_Table::filters();
$cl_periods  = array(
	'today' => __( 'Today', 'cartlum' ),
	'7'     => __( 'Last 7 days', 'cartlum' ),
	'30'    => __( 'Last 30 days', 'cartlum' ),
	'all'   => __( 'All time', 'cartlum' ),
);
$cl_products = Cartlum_Carts::products_in( $cl_filters['since'] );
if ( $cl_filters['product'] && ! isset( $cl_products[ $cl_filters['product'] ] ) ) {
	$cl_products[ $cl_filters['product'] ] = array(
		'name'  => '',
		'carts' => 0,
	);
}
?>
<div class="wrap cartlum">
	<div class="cl-head">
		<h1><?php esc_html_e( 'Abandoned carts', 'cartlum' ); ?></h1>
		<a class="button" href="<?php echo esc_url( Cartlum_Admin_Actions::export_url( $cl_status_filter, isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', $cl_filters['period'], $cl_filters['product'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php esc_html_e( 'Export CSV', 'cartlum' ); ?></a>
	</div>
	<hr class="wp-header-end">
	<?php Cartlum_Admin_Settings::nav( 'cartlum-carts', '' ); ?>
	<nav class="cl-chips cl-ofilter cl-carts-period" aria-label="<?php esc_attr_e( 'Period', 'cartlum' ); ?>">
		<?php foreach ( $cl_periods as $cl_key => $cl_label ) : ?>
			<?php
			$cl_n = Cartlum_Carts_Table::counts( Cartlum_Carts::period_since( $cl_key ), $cl_filters['product'] );
			$cl_n = $cl_status_filter ? ( $cl_n[ $cl_status_filter ] ?? 0 ) : array_sum( $cl_n );
			?>
			<a class="cl-chip<?php echo $cl_key === $cl_filters['period'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( Cartlum_Carts_Table::list_url( array( 'period' => 'all' === $cl_key ? '' : $cl_key ) ) ); ?>"<?php echo $cl_key === $cl_filters['period'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cl_label ); ?> <span class="cl-ofilter__n"><?php echo esc_html( number_format_i18n( $cl_n ) ); ?></span></a>
		<?php endforeach; ?>
	</nav>
	<?php $table->views(); ?>
	<form method="get" class="cl-carts-form">
		<input type="hidden" name="page" value="cartlum-carts">
		<?php if ( $cl_status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $cl_status_filter ); ?>">
		<?php endif; ?>
		<?php if ( 'all' !== $cl_filters['period'] ) : ?>
			<input type="hidden" name="period" value="<?php echo esc_attr( $cl_filters['period'] ); ?>">
		<?php endif; ?>
		<?php if ( $cl_products ) : ?>
			<p class="cl-carts-product">
				<label for="cl-cart-product"><?php esc_html_e( 'Product', 'cartlum' ); ?></label>
				<select id="cl-cart-product" name="product" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All products', 'cartlum' ); ?></option>
					<?php foreach ( $cl_products as $cl_id => $cl_info ) : ?>
						<?php
						$cl_prod = wc_get_product( $cl_id );
						$cl_name = $cl_prod ? $cl_prod->get_name() : ( '' !== $cl_info['name'] ? $cl_info['name'] : '#' . $cl_id );
						?>
						<option value="<?php echo esc_attr( (string) $cl_id ); ?>" <?php selected( $cl_filters['product'], $cl_id ); ?>>
							<?php
							/* translators: 1: product, 2: number of carts */
							echo esc_html( $cl_info['carts'] ? sprintf( __( '%1$s (%2$d)', 'cartlum' ), $cl_name, $cl_info['carts'] ) : $cl_name );
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript><button type="submit" class="button"><?php esc_html_e( 'Filter', 'cartlum' ); ?></button></noscript>
			</p>
		<?php endif; ?>
		<?php $table->search_box( __( 'Search', 'cartlum' ), 'cartlum-search' ); ?>

		<div class="cl-only-wide">
			<?php $table->display(); ?>
		</div>
	</form>

	<div class="cl-only-narrow">
		<?php if ( ! $table->items ) : ?>
			<?php $table->no_items(); ?>
		<?php else : ?>
			<ul class="cl-people">
				<?php
				$cl_status = true;
				foreach ( $table->items as $row ) {
					require CARTLUM_DIR . 'includes/admin/views/partials/person-row.php';
				}
				?>
			</ul>
			<?php if ( $cl_pages > 1 ) : ?>
				<nav class="cl-pager" aria-label="<?php esc_attr_e( 'Pages', 'cartlum' ); ?>">
					<?php if ( $cl_page > 1 ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $cl_page - 1 ) ); ?>">← <?php esc_html_e( 'Newer', 'cartlum' ); ?></a>
					<?php endif; ?>
					<span class="cl-muted">
						<?php
						/* translators: 1: page, 2: pages */
						echo esc_html( sprintf( __( '%1$d of %2$d', 'cartlum' ), $cl_page, $cl_pages ) );
						?>
					</span>
					<?php if ( $cl_page < $cl_pages ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $cl_page + 1 ) ); ?>"><?php esc_html_e( 'Older', 'cartlum' ); ?> →</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
