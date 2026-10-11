<?php
/**
 * One customer in a follow-up list: who, what, how much, and the ways to reach them.
 * Shared by the dashboard and the mobile carts list so the row reads the same everywhere.
 *
 * @package Cartlum
 * @var object $row         Cart row.
 * @var bool   $cl_status   Show the status badge (lists that mix statuses).
 * @var array  $cl_state    Optional [ todo|done, text ]: contact so far (dashboard).
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included from inside a method, so its variables are local to that method, not globals.

$cl_url  = Cartlum_Admin::url( 'cartlum-carts', array( 'cart' => $row->id ) );
$cl_name = '' !== $row->name ? $row->name : Cartlum_Phone::display( $row->phone );
?>
<li class="cl-person">
	<a class="cl-person__main" href="<?php echo esc_url( $cl_url ); ?>">
		<span class="cl-person__line">
			<span class="cl-person__name"><span class="cl-person__nametext"><?php echo esc_html( $cl_name ); ?></span></span>
			<span class="cl-person__value"><?php echo wp_kses_post( Cartlum_Admin::money( $row->cart_total, $row->currency ) ); ?></span>
		</span>
		<span class="cl-person__line cl-muted">
			<?php // The name keeps the first line to itself; the status leads the second so it is never squeezed out. ?>
			<span class="cl-person__meta"><?php echo ! empty( $cl_status ) ? wp_kses_post( Cartlum_Admin::badge( $row->status ) ) : ''; ?><?php echo wp_kses_post( apply_filters( 'cartlum_customer_badges', '', $row ) ); ?><span class="cl-person__items"><?php echo esc_html( Cartlum_Messages::items_text( $row ) ); ?></span></span>
			<span class="cl-person__time"><?php echo esc_html( Cartlum_Admin::ago( $row->abandoned_at ? $row->abandoned_at : $row->updated_at ) ); ?></span>
		</span>
		<?php if ( ! empty( $cl_state ) ) : ?>
			<?php // What has been tried so far, so nobody is contacted twice by mistake. ?>
			<span class="cl-person__state cl-person__state--<?php echo esc_attr( $cl_state[0] ); ?>"><span class="cl-dot" aria-hidden="true"></span><?php echo esc_html( $cl_state[1] ); ?></span>
		<?php endif; ?>
	</a>
	<?php if ( $row->phone ) : ?>
		<span class="cl-person__actions">
			<a class="cl-icon-btn" href="<?php echo esc_url( 'tel:+' . $row->phone ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'Call %s', 'cartlum' ), $cl_name ) ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span></a>
			<a class="cl-icon-btn" target="_blank" rel="noopener" href="<?php echo esc_url( Cartlum_Admin_Actions::whatsapp_url( $row ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'WhatsApp %s', 'cartlum' ), $cl_name ) ); ?>"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span></a>
		</span>
	<?php endif; ?>
</li>
