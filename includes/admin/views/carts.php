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
?>
<div class="wrap cartlum">
	<div class="cl-head">
		<h1><?php esc_html_e( 'Abandoned carts', 'cartlum' ); ?></h1>
		<a class="button" href="<?php echo esc_url( Cartlum_Admin_Actions::export_url( $cl_status_filter, isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php esc_html_e( 'Export CSV', 'cartlum' ); ?></a>
	</div>
	<hr class="wp-header-end">
	<?php Cartlum_Admin_Settings::nav( 'cartlum-carts', '' ); ?>
	<?php $table->views(); ?>
	<form method="get" class="cl-carts-form">
		<input type="hidden" name="page" value="cartlum-carts">
		<?php if ( $cl_status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $cl_status_filter ); ?>">
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
