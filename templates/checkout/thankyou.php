<?php
/**
 * Thank-you page in the phone-first checkout's style: the confirmation and order details on the left,
 * the order summary on the right (on phones, the folding bar at the top).
 *
 * Replaces WooCommerce's checkout/thankyou.php while the phone-first checkout is on. WooCommerce's
 * thank-you hooks still run, so payment instructions (e.g. bank details) and tracking keep working.
 *
 * @package Cartlum
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- template-scoped variables; the hooks are WooCommerce's own.

if ( ! $order ) {
	echo '<div class="clx clx-thanks"><div class="clx-inner clx-thanks__solo"><h1 class="clx-thanks__title">' . esc_html__( 'Thank you. Your order has been received.', 'cartlum' ) . '</h1></div></div>';
	return;
}

// The order details are shown above; WooCommerce's own table would repeat them.
remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

$failed    = $order->has_status( 'failed' );
$name      = trim( (string) $order->get_billing_first_name() );
$phone     = (string) $order->get_billing_phone();
$totals    = $order->get_order_item_totals();
$wa_number = 'yes' === Cartlum_Settings::get( 'whatsapp_button' ) ? Cartlum_Phone::normalize( (string) Cartlum_Settings::get( 'whatsapp_number' ) ) : '';
$message   = trim( (string) Cartlum_Settings::get( 'thankyou_message' ) );
$message   = '' !== $message ? $message : __( 'We have received your order and will contact you on {phone} about delivery.', 'cartlum' );
?>
<div class="clx clx-thanks">
	<div class="clx-cols">

		<aside class="clx-col-side clx-summary" aria-label="<?php esc_attr_e( 'Order summary', 'cartlum' ); ?>">
			<button type="button" class="clx-summary__toggle" aria-expanded="false" aria-controls="clx-summary-body">
				<span class="clx-summary__toggle-label">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
					<span data-show="<?php esc_attr_e( 'Show order summary', 'cartlum' ); ?>" data-hide="<?php esc_attr_e( 'Hide order summary', 'cartlum' ); ?>"><?php esc_html_e( 'Show order summary', 'cartlum' ); ?></span>
					<svg class="clx-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
				</span>
				<span class="clx-total-amount"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			</button>
			<div class="clx-inner clx-inner--side clx-summary__body" id="clx-summary-body">
				<ul class="clx-items">
					<?php foreach ( $order->get_items() as $item ) : ?>
						<?php
						$product = $item->get_product();
						$image   = $product ? $product->get_image( 'woocommerce_gallery_thumbnail' ) : wc_placeholder_img( 'woocommerce_gallery_thumbnail' );
						?>
						<li class="clx-item">
							<span class="clx-item__thumb">
								<?php echo wp_kses_post( $image ); ?>
								<span class="clx-item__qty" aria-label="<?php /* translators: %d: quantity */ echo esc_attr( sprintf( __( 'Quantity: %d', 'cartlum' ), $item->get_quantity() ) ); ?>"><?php echo esc_html( $item->get_quantity() ); ?></span>
							</span>
							<span class="clx-item__name">
								<?php echo esc_html( $item->get_name() ); ?>
								<?php wc_display_item_meta( $item, array( 'before' => '<span class="clx-item__meta">', 'after' => '</span>', 'separator' => ', ', 'echo' => true, 'autop' => false, 'label_before' => '', 'label_after' => ': ' ) ); ?>
							</span>
							<span class="clx-item__price"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<dl class="clx-totals">
					<?php foreach ( $totals as $key => $row ) : ?>
						<?php
						if ( 'payment_method' === $key ) {
							continue;
						}
						?>
						<div class="clx-totals__row<?php echo 'order_total' === $key ? ' clx-totals__row--total' : ''; ?><?php echo 0 === strpos( $key, 'discount' ) ? ' clx-totals__row--discount' : ''; ?>">
							<dt><?php echo esc_html( rtrim( wp_strip_all_tags( $row['label'] ), ':' ) ); ?></dt>
							<dd>
								<?php if ( 'order_total' === $key ) : ?>
									<span class="clx-currency"><?php echo esc_html( $order->get_currency() ); ?></span>
								<?php endif; ?>
								<?php
								// The method is named under Order details; here only the amount.
								$value = 'shipping' === $key && $order->get_shipping_total() > 0
									? wc_price( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), array( 'currency' => $order->get_currency() ) )
									: $row['value'];
								echo wp_kses_post( $value );
								?>
							</dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>
		</aside>

		<div class="clx-col-main">
			<div class="clx-inner clx-inner--main">

				<div class="clx-thanks__head">
					<span class="clx-thanks__icon<?php echo $failed ? ' is-failed' : ''; ?>" aria-hidden="true">
						<?php if ( $failed ) : ?>
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>
						<?php else : ?>
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
						<?php endif; ?>
					</span>
					<div>
						<p class="clx-thanks__order">
							<?php /* translators: %s: order number */ echo esc_html( sprintf( __( 'Order #%s', 'cartlum' ), $order->get_order_number() ) ); ?>
						</p>
						<h1 class="clx-thanks__title">
							<?php
							if ( $failed ) {
								esc_html_e( 'Payment did not go through', 'cartlum' );
							} elseif ( '' !== $name ) {
								/* translators: %s: customer's first name */
								echo esc_html( sprintf( __( 'Thank you, %s!', 'cartlum' ), $name ) );
							} else {
								esc_html_e( 'Thank you!', 'cartlum' );
							}
							?>
						</h1>
					</div>
				</div>

				<?php if ( $failed ) : ?>
					<section class="clx-card">
						<p><?php esc_html_e( 'Your order is saved, but the payment was declined by the bank or payment provider. Please try again.', 'cartlum' ); ?></p>
						<p class="clx-thanks__actions">
							<a class="clx-btn" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay again', 'cartlum' ); ?></a>
						</p>
					</section>
				<?php else : ?>
					<section class="clx-card">
						<h2 class="clx-card__title"><?php esc_html_e( 'Your order is confirmed', 'cartlum' ); ?></h2>
						<p><?php echo esc_html( str_replace( '{phone}', $phone, $message ) ); ?></p>
					</section>
				<?php endif; ?>

				<?php
				// Payment instructions (e.g. bank details), in a box only when the method prints any.
				ob_start();
				do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
				$gateway_note = trim( (string) ob_get_clean() );
				if ( '' !== $gateway_note ) {
					echo '<div class="clx-gateway-note">' . $gateway_note . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the payment method's own output.
				}
				?>

				<section class="clx-card">
					<h2 class="clx-card__title"><?php esc_html_e( 'Order details', 'cartlum' ); ?></h2>
					<dl class="clx-details">
						<div>
							<dt><?php esc_html_e( 'Contact', 'cartlum' ); ?></dt>
							<dd>
								<?php echo esc_html( $phone ); ?>
								<?php if ( $order->get_billing_email() ) : ?>
									<br><?php echo esc_html( $order->get_billing_email() ); ?>
								<?php endif; ?>
							</dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Delivery address', 'cartlum' ); ?></dt>
							<dd><?php echo wp_kses_post( $order->get_formatted_shipping_address( $order->get_formatted_billing_address() ) ); ?></dd>
						</div>
						<?php if ( $order->get_shipping_method() ) : ?>
							<div>
								<dt><?php esc_html_e( 'Shipping method', 'cartlum' ); ?></dt>
								<dd><?php echo esc_html( $order->get_shipping_method() ); ?></dd>
							</div>
						<?php endif; ?>
						<div>
							<dt><?php esc_html_e( 'Payment', 'cartlum' ); ?></dt>
							<dd>
								<?php echo esc_html( $order->get_payment_method_title() ); ?>
								<?php if ( 'cod' === $order->get_payment_method() && ! $failed ) : ?>
									<br><span class="clx-muted">
										<?php /* translators: %s: order total */ echo wp_kses_post( sprintf( __( 'Pay %s when it arrives', 'cartlum' ), $order->get_formatted_order_total() ) ); ?>
									</span>
								<?php endif; ?>
							</dd>
						</div>
					</dl>
				</section>

				<p class="clx-thanks__actions">
					<a class="clx-btn" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Continue shopping', 'cartlum' ); ?></a>
					<?php if ( $wa_number ) : ?>
						<?php /* translators: %s: order number */ $wa_text = sprintf( __( 'Hi! About my order #%s', 'cartlum' ), $order->get_order_number() ); ?>
						<a class="clx-btn clx-btn--ghost" href="<?php echo esc_url( 'https://wa.me/' . $wa_number . '?text=' . rawurlencode( $wa_text ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Message us on WhatsApp', 'cartlum' ); ?></a>
					<?php endif; ?>
				</p>

				<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
			</div>
		</div>

	</div>
</div>
