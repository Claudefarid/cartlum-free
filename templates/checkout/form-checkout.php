<?php
/**
 * Checkout form, phone-first layout: contact, delivery, shipping method and payment on the left;
 * the order summary on the right (on phones, a collapsible bar at the top).
 *
 * Replaces WooCommerce's checkout/form-checkout.php while the phone-first checkout is on. It keeps
 * the form, field names and the elements WooCommerce's checkout script updates, so payment
 * gateways and order plugins work as on the standard checkout.
 *
 * @package Cartlum
 *
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- template-scoped variables; the hooks are WooCommerce's own.

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'cartlum' ) ) );
	return;
}

$fields       = $checkout->get_checkout_fields();
$billing      = isset( $fields['billing'] ) ? $fields['billing'] : array();
$contact_keys = array( 'billing_phone', 'billing_email' );
$render       = function ( $keys, $set ) use ( $checkout ) {
	foreach ( $keys as $key ) {
		if ( isset( $set[ $key ] ) ) {
			woocommerce_form_field( $key, $set[ $key ], $checkout->get_value( $key ) );
		}
	}
};
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout clx" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'cartlum' ); ?>">
	<div class="clx-cols">

		<aside class="clx-col-side clx-summary" aria-label="<?php esc_attr_e( 'Order summary', 'cartlum' ); ?>">
			<button type="button" class="clx-summary__toggle" aria-expanded="false" aria-controls="clx-summary-body">
				<span class="clx-summary__toggle-label">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
					<span data-show="<?php esc_attr_e( 'Show order summary', 'cartlum' ); ?>" data-hide="<?php esc_attr_e( 'Hide order summary', 'cartlum' ); ?>"><?php esc_html_e( 'Show order summary', 'cartlum' ); ?></span>
					<svg class="clx-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
				</span>
				<span class="clx-total-amount"><?php wc_cart_totals_order_total_html(); ?></span>
			</button>
			<div class="clx-inner clx-inner--side clx-summary__body" id="clx-summary-body">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<h2 class="clx-sr-only"><?php esc_html_e( 'Order summary', 'cartlum' ); ?></h2>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
				<?php woocommerce_order_review(); ?>
			</div>
		</aside>

		<div class="clx-col-main">
			<div class="clx-inner clx-inner--main">
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
				<div id="customer_details">
					<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

					<section class="clx-section woocommerce-billing-fields-contact">
						<h2 class="clx-h"><?php esc_html_e( 'Contact', 'cartlum' ); ?></h2>
						<?php $render( $contact_keys, $billing ); ?>
						<?php
						if ( ! is_user_logged_in() && $checkout->is_registration_required() && ! empty( $fields['account'] ) ) {
							$render( array_keys( $fields['account'] ), $fields['account'] );
						}
						?>
					</section>

					<section class="clx-section">
						<h2 class="clx-h"><?php esc_html_e( 'Delivery', 'cartlum' ); ?></h2>
						<?php $render( array_diff( array_keys( $billing ), $contact_keys ), $billing ); ?>
						<?php
						if ( ! empty( $fields['order'] ) ) {
							$render( array_keys( $fields['order'] ), $fields['order'] );
						}
						?>
					</section>

					<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
				</div>
				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

				<?php if ( WC()->cart->needs_shipping() ) : ?>
					<section class="clx-section">
						<h2 class="clx-h"><?php esc_html_e( 'Shipping method', 'cartlum' ); ?></h2>
						<?php echo Cartlum_Checkout::shipping_methods_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
					</section>
				<?php endif; ?>

				<section class="clx-section" id="order_review">
					<h2 class="clx-h"><?php esc_html_e( 'Payment', 'cartlum' ); ?></h2>
					<div class="clx-pay-total">
						<span><?php esc_html_e( 'Total', 'cartlum' ); ?></span>
						<span class="clx-total-amount"><?php wc_cart_totals_order_total_html(); ?></span>
					</div>
					<?php woocommerce_checkout_payment(); ?>
				</section>
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

				<?php echo Cartlum_Checkout::policy_links_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
			</div>
		</div>

	</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
