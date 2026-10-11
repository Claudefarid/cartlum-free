<?php
/**
 * Order summary: products with their photo and quantity, then the totals. Replaces WooCommerce's
 * checkout/review-order.php while the phone-first checkout is on; WooCommerce swaps it in place
 * after every change (district, coupon, shipping method), so the root keeps its class.
 *
 * Shipping methods are chosen in the form (Cartlum_Checkout::shipping_methods_html); here only the
 * chosen charge is shown.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- template-scoped variables; the hooks are WooCommerce's own.
?>
<div class="clx-review woocommerce-checkout-review-order-table">
	<ul class="clx-items">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}
			?>
			<li class="clx-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<span class="clx-item__thumb">
					<?php echo wp_kses_post( $_product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?>
					<span class="clx-item__qty" aria-label="<?php /* translators: %d: quantity */ echo esc_attr( sprintf( __( 'Quantity: %d', 'cartlum' ), $cart_item['quantity'] ) ); ?>"><?php echo esc_html( $cart_item['quantity'] ); ?></span>
				</span>
				<span class="clx-item__name">
					<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?>
					<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes it. ?>
				</span>
				<span class="clx-item__price"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) ); ?></span>
			</li>
			<?php
		}
		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</ul>

	<?php if ( wc_coupons_enabled() ) : ?>
		<div class="clx-coupon">
			<div class="clx-coupon__row">
				<div class="form-row clx-coupon__field">
					<label for="clx-coupon-code"><?php esc_html_e( 'Discount code', 'cartlum' ); ?></label>
					<span class="woocommerce-input-wrapper"><input type="text" id="clx-coupon-code" class="input-text" autocomplete="off" autocapitalize="characters" spellcheck="false"></span>
				</div>
				<button type="button" class="clx-btn-secondary clx-coupon__apply" disabled><?php esc_html_e( 'Apply', 'cartlum' ); ?></button>
			</div>
			<div class="clx-coupon__msg" role="status" aria-live="polite"></div>
		</div>
	<?php endif; ?>

	<dl class="clx-totals">
		<div class="clx-totals__row">
			<dt><?php esc_html_e( 'Subtotal', 'cartlum' ); ?></dt>
			<dd><?php wc_cart_totals_subtotal_html(); ?></dd>
		</div>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<div class="clx-totals__row clx-totals__row--discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<dt><?php wc_cart_totals_coupon_label( $coupon ); ?></dt>
				<dd><?php wc_cart_totals_coupon_html( $coupon ); ?></dd>
			</div>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() ) : ?>
			<div class="clx-totals__row">
				<dt><?php esc_html_e( 'Shipping', 'cartlum' ); ?></dt>
				<dd><?php echo wp_kses_post( Cartlum_Checkout::shipping_total_html() ); ?></dd>
			</div>
		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<div class="clx-totals__row">
				<dt><?php echo esc_html( $fee->name ); ?></dt>
				<dd><?php wc_cart_totals_fee_html( $fee ); ?></dd>
			</div>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<div class="clx-totals__row">
						<dt><?php echo esc_html( $tax->label ); ?></dt>
						<dd><?php echo wp_kses_post( $tax->formatted_amount ); ?></dd>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="clx-totals__row">
					<dt><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></dt>
					<dd><?php wc_cart_totals_taxes_total_html(); ?></dd>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
		<div class="clx-totals__row clx-totals__row--total">
			<dt><?php esc_html_e( 'Total', 'cartlum' ); ?></dt>
			<dd><span class="clx-currency"><?php echo esc_html( get_woocommerce_currency() ); ?></span> <?php wc_cart_totals_order_total_html(); ?></dd>
		</div>
		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</dl>
</div>
