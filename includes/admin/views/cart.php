<?php
/**
 * Single cart: who the customer is and the ways to reach them first, the cart and its tools
 * beside it, and the history below.
 *
 * @package Cartlum
 * @var object $cart
 * @var array  $events
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included from inside a method, so its variables are local to that method, not globals.

$post_url  = admin_url( 'admin-post.php' );
$is_open   = in_array( $cart->status, Cartlum_Carts::OPEN, true );
$has_phone = '' !== $cart->phone;
$can_email = Cartlum_Email::can_email( $cart );
$outcomes  = Cartlum_Admin_Actions::call_outcomes();
$link      = Cartlum_Restore::link( $cart );
$cl_name   = '' !== $cart->name ? $cart->name : __( '(no name)', 'cartlum' );

$event_labels = array(
	'captured'         => __( 'Cart saved at checkout', 'cartlum' ),
	'abandoned'        => __( 'Marked abandoned', 'cartlum' ),
	'sms_sent'         => __( 'SMS sent', 'cartlum' ),
	'sms_failed'       => __( 'SMS failed', 'cartlum' ),
	'sms_test'         => __( 'Automatic SMS (test mode — sent to your test number)', 'cartlum' ),
	'email_sent'       => __( 'Email sent', 'cartlum' ),
	'email_failed'     => __( 'Email failed', 'cartlum' ),
	'whatsapp_opened'  => __( 'WhatsApp opened', 'cartlum' ),
	'whatsapp_sent'    => __( 'WhatsApp sent', 'cartlum' ),
	'whatsapp_failed'  => __( 'WhatsApp failed', 'cartlum' ),
	'ai_call_started'  => __( 'AI call placed', 'cartlum' ),
	'ai_call_done'     => __( 'AI call', 'cartlum' ),
	'ai_call_failed'   => __( 'AI call not answered or failed', 'cartlum' ),
	'whatsapp_test'    => __( 'Automatic WhatsApp (test mode — sent to your test number)', 'cartlum' ),
	'call_logged'      => __( 'Call', 'cartlum' ),
	'link_clicked'     => __( 'Customer opened the cart link', 'cartlum' ),
	'coupon_created'   => __( 'Coupon created', 'cartlum' ),
	'recovered'        => __( 'Recovered — order placed', 'cartlum' ),
	'converted'        => __( 'Ordered (without contact)', 'cartlum' ),
	'order_cancelled'  => __( 'Order cancelled', 'cartlum' ),
	'optout'           => __( 'Unsubscribed', 'cartlum' ),
	'status_lost'      => __( 'Marked lost', 'cartlum' ),
	'status_abandoned' => __( 'Reopened', 'cartlum' ),
	'flow_skipped'     => __( 'Automatic message skipped', 'cartlum' ),
	'flow_stopped'     => __( 'Automatic messages stopped', 'cartlum' ),
	'flow_delayed'     => __( 'Automatic message delayed', 'cartlum' ),
	'webhook_sent'     => __( 'Sent to webhook', 'cartlum' ),
	'webhook_failed'   => __( 'Webhook failed', 'cartlum' ),
);

// Tones for the timeline dots: what happened, not decoration.
$event_tone = array(
	'recovered'    => 'good',
	'converted'    => 'good',
	'sms_failed'   => 'bad',
	'email_failed' => 'bad',
	'flow_skipped' => 'warn',
	'flow_delayed' => 'warn',
);

$chip_labels = array(
	'{name}'      => __( 'Name', 'cartlum' ),
	'{items}'     => __( 'Items', 'cartlum' ),
	'{total}'     => __( 'Total', 'cartlum' ),
	'{cart_link}' => __( 'Cart link', 'cartlum' ),
	'{coupon}'    => __( 'Coupon', 'cartlum' ),
	'{discount}'  => __( 'Discount', 'cartlum' ),
	'{store}'     => __( 'Store name', 'cartlum' ),
);
$chip_labels = apply_filters( 'cartlum_compose_chips', $chip_labels );
$chips       = function () use ( $chip_labels ) {
	echo '<div class="cl-chips" aria-label="' . esc_attr__( 'Insert', 'cartlum' ) . '"><span class="cl-chips__label">' . esc_html__( 'Insert', 'cartlum' ) . '</span>';
	foreach ( $chip_labels as $tag => $label ) {
		echo '<button type="button" class="cl-chip" data-cartlum-insert="' . esc_attr( $tag ) . '">' . esc_html( $label ) . '</button>';
	}
	echo '</div>';
};
?>
<div class="wrap cartlum cl-cart">
	<a class="cl-back" href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-carts' ) ); ?>">← <?php esc_html_e( 'All carts', 'cartlum' ); ?></a>

	<header class="cl-cart__head">
		<div class="cl-cart__who">
			<h1><?php echo esc_html( $cl_name ); ?> <?php echo wp_kses_post( Cartlum_Admin::badge( $cart->status ) ); ?></h1>
			<p class="cl-cart__facts">
				<?php if ( $has_phone ) : ?>
					<span><?php echo esc_html( Cartlum_Phone::display( $cart->phone ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cart->email && is_email( $cart->email ) ) : ?>
					<span><?php echo esc_html( $cart->email ); ?></span>
				<?php endif; ?>
				<span class="cl-strong"><?php echo wp_kses_post( Cartlum_Admin::money( $cart->cart_total, $cart->currency ) ); ?></span>
				<span class="cl-muted">
					<?php
					/* translators: %s: time ago */
					echo esc_html( sprintf( __( 'saved %s', 'cartlum' ), Cartlum_Admin::ago( $cart->created_at ) ) );
					?>
				</span>
				<?php echo wp_kses_post( apply_filters( 'cartlum_customer_badges', '', $cart ) ); ?>
			</p>
		</div>
		<?php if ( $has_phone ) : ?>
			<div class="cl-cart__cta cl-only-wide">
				<a class="button button-primary cl-btn-lg" href="<?php echo esc_url( 'tel:+' . $cart->phone ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span><?php esc_html_e( 'Call', 'cartlum' ); ?></a>
				<a class="button cl-btn-lg" target="_blank" rel="noopener" href="<?php echo esc_url( Cartlum_Admin_Actions::whatsapp_url( $cart ) ); ?>"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>WhatsApp</a>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $cart->order_id ) : ?>
		<p class="cl-banner cl-banner--good">
			<?php
			/* translators: 1: order link, 2: amount */
			printf( esc_html__( 'Order %1$s placed — %2$s.', 'cartlum' ), '<a href="' . esc_url( Cartlum_Admin::order_url( $cart->order_id ) ) . '">#' . esc_html( $cart->order_id ) . '</a>', wp_kses_post( Cartlum_Admin::money( $cart->order_total, $cart->currency ) ) );
			?>
		</p>
	<?php elseif ( ! $is_open ) : ?>
		<p class="cl-banner"><?php esc_html_e( 'This cart is closed. You can still contact the customer, but it will not change the result.', 'cartlum' ); ?></p>
	<?php endif; ?>

	<div class="cl-cart__grid">
		<section class="cl-section cl-cart__compose" aria-labelledby="cl-reach">
			<h2 id="cl-reach"><?php esc_html_e( 'Reach out', 'cartlum' ); ?></h2>

			<div class="cl-tabs" role="tablist">
				<button type="button" role="tab" aria-selected="true" class="is-active" data-cartlum-tab="sms"><?php esc_html_e( 'SMS', 'cartlum' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-cartlum-tab="whatsapp">WhatsApp</button>
				<button type="button" role="tab" aria-selected="false" data-cartlum-tab="email"><?php esc_html_e( 'Email', 'cartlum' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-cartlum-tab="call"><?php esc_html_e( 'Log a call', 'cartlum' ); ?></button>
			</div>

			<div class="cl-tab is-active" role="tabpanel" data-cartlum-pane="sms">
				<?php if ( ! $has_phone ) : ?>
					<p class="cl-muted"><?php esc_html_e( 'No phone number on this cart.', 'cartlum' ); ?></p>
				<?php elseif ( ! Cartlum_SMS::gateway() ) : ?>
					<p><?php esc_html_e( 'No SMS gateway yet.', 'cartlum' ); ?> <a href="<?php echo esc_url( Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => 'sms' ) ) ); ?>"><?php esc_html_e( 'Set up SMS', 'cartlum' ); ?></a></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" data-cartlum-busy>
						<?php wp_nonce_field( 'cartlum_send_sms_' . $cart->id ); ?>
						<input type="hidden" name="action" value="cartlum_send_sms">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<label class="screen-reader-text" for="cl-sms"><?php esc_html_e( 'SMS message', 'cartlum' ); ?></label>
						<textarea id="cl-sms" name="message" rows="4" class="cl-textarea" data-cartlum-count><?php echo esc_textarea( Cartlum_Settings::get( 'sms_template' ) ); ?></textarea>
						<?php $chips(); ?>
						<?php do_action( 'cartlum_compose_tools', $cart, 'sms' ); ?>
						<div class="cl-form-foot">
							<span class="cl-muted" data-cartlum-counter aria-live="polite"></span>
							<button class="button button-primary"><?php esc_html_e( 'Send SMS', 'cartlum' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>

			<div class="cl-tab" role="tabpanel" data-cartlum-pane="whatsapp">
				<?php if ( ! $has_phone ) : ?>
					<p class="cl-muted"><?php esc_html_e( 'No phone number on this cart.', 'cartlum' ); ?></p>
				<?php else : ?>
					<form method="get" action="<?php echo esc_url( $post_url ); ?>" target="_blank">
						<?php wp_nonce_field( 'cartlum_whatsapp_' . $cart->id, '_wpnonce', false ); ?>
						<input type="hidden" name="action" value="cartlum_whatsapp">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<label class="screen-reader-text" for="cl-wa"><?php esc_html_e( 'WhatsApp message', 'cartlum' ); ?></label>
						<textarea id="cl-wa" name="message" rows="4" class="cl-textarea"><?php echo esc_textarea( Cartlum_Settings::get( 'whatsapp_template' ) ); ?></textarea>
						<?php $chips(); ?>
						<?php do_action( 'cartlum_compose_tools', $cart, 'whatsapp' ); ?>
						<div class="cl-form-foot">
							<span class="cl-muted"><?php esc_html_e( 'Opens WhatsApp with this message ready — you press Send.', 'cartlum' ); ?></span>
							<button class="button button-primary"><?php esc_html_e( 'Open in WhatsApp', 'cartlum' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>

			<div class="cl-tab" role="tabpanel" data-cartlum-pane="email">
				<?php if ( ! $can_email ) : ?>
					<p class="cl-muted"><?php esc_html_e( 'No email address on this cart that can receive mail.', 'cartlum' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" data-cartlum-busy>
						<?php wp_nonce_field( 'cartlum_send_email_' . $cart->id ); ?>
						<input type="hidden" name="action" value="cartlum_send_email">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<label class="cl-label" for="cl-subject"><?php esc_html_e( 'Subject', 'cartlum' ); ?></label>
						<input id="cl-subject" type="text" name="subject" class="cl-input" value="<?php echo esc_attr( Cartlum_Settings::get( 'email_subject' ) ); ?>">
						<label class="cl-label" for="cl-email"><?php esc_html_e( 'Message', 'cartlum' ); ?></label>
						<textarea id="cl-email" name="message" rows="8" class="cl-textarea"><?php echo esc_textarea( Cartlum_Settings::get( 'email_template' ) ); ?></textarea>
						<?php $chips(); ?>
						<?php do_action( 'cartlum_compose_tools', $cart, 'email' ); ?>
						<div class="cl-form-foot">
							<span></span>
							<button class="button button-primary"><?php esc_html_e( 'Send email', 'cartlum' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>

			<div class="cl-tab" role="tabpanel" data-cartlum-pane="call">
				<?php if ( ! $has_phone ) : ?>
					<p class="cl-muted"><?php esc_html_e( 'No phone number on this cart.', 'cartlum' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>">
						<?php wp_nonce_field( 'cartlum_log_call_' . $cart->id ); ?>
						<input type="hidden" name="action" value="cartlum_log_call">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<fieldset class="cl-outcomes">
							<legend class="cl-label"><?php esc_html_e( 'How did the call go?', 'cartlum' ); ?></legend>
							<?php foreach ( $outcomes as $key => $label ) : ?>
								<label class="cl-outcome"><input type="radio" name="outcome" value="<?php echo esc_attr( $key ); ?>" required> <span><?php echo esc_html( $label ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>
						<label class="screen-reader-text" for="cl-note"><?php esc_html_e( 'Note', 'cartlum' ); ?></label>
						<textarea id="cl-note" name="note" rows="2" class="cl-textarea" placeholder="<?php esc_attr_e( 'Note (optional)', 'cartlum' ); ?>"></textarea>
						<div class="cl-form-foot">
							<span></span>
							<button class="button button-primary"><?php esc_html_e( 'Save call', 'cartlum' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</section>

		<aside class="cl-cart__aside">
			<?php do_action( 'cartlum_cart_aside', $cart ); ?>
			<section class="cl-section" aria-labelledby="cl-items">
				<h2 id="cl-items"><?php esc_html_e( 'Cart', 'cartlum' ); ?></h2>
				<ul class="cl-items">
					<?php foreach ( Cartlum_Carts::items( $cart ) as $item ) : ?>
						<li>
							<span class="cl-items__name">
								<?php
								$product_link = get_edit_post_link( (int) $item['product_id'] );
								echo $product_link ? '<a href="' . esc_url( $product_link ) . '">' . esc_html( $item['name'] ) . '</a>' : esc_html( $item['name'] );
								?>
								<?php if ( (int) $item['quantity'] > 1 ) : ?>
									<span class="cl-muted">× <?php echo esc_html( (int) $item['quantity'] ); ?></span>
								<?php endif; ?>
							</span>
							<span class="cl-num"><?php echo wp_kses_post( Cartlum_Admin::money( $item['line_total'], $cart->currency ) ); ?></span>
						</li>
					<?php endforeach; ?>
					<li class="cl-items__total">
						<span><?php esc_html_e( 'Total', 'cartlum' ); ?></span>
						<span class="cl-num"><?php echo wp_kses_post( Cartlum_Admin::money( $cart->cart_total, $cart->currency ) ); ?></span>
					</li>
				</ul>

				<h3><?php esc_html_e( 'Cart link', 'cartlum' ); ?></h3>
				<div class="cl-copy">
					<label class="screen-reader-text" for="cl-link"><?php esc_html_e( 'Cart link', 'cartlum' ); ?></label>
					<input id="cl-link" type="text" readonly value="<?php echo esc_attr( $link ); ?>" class="cl-input cl-mono">
					<button type="button" class="button" data-cartlum-copy><?php esc_html_e( 'Copy', 'cartlum' ); ?></button>
				</div>
				<p class="cl-note"><?php esc_html_e( 'Refills this cart and opens checkout.', 'cartlum' ); ?></p>

				<h3><?php esc_html_e( 'Coupon', 'cartlum' ); ?></h3>
				<?php if ( $cart->coupon_code ) : ?>
					<p class="cl-coupon">
						<code><?php echo esc_html( $cart->coupon_code ); ?></code>
						<span><?php echo esc_html( Cartlum_Coupons::describe( $cart->coupon_code ) ); ?></span>
						<?php if ( ! Cartlum_Coupons::is_usable( $cart->coupon_code ) ) : ?>
							<span class="cl-muted"><?php esc_html_e( 'used or expired', 'cartlum' ); ?></span>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>">
						<?php wp_nonce_field( 'cartlum_coupon_' . $cart->id ); ?>
						<input type="hidden" name="action" value="cartlum_coupon">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<button class="button"><?php esc_html_e( 'Create one-time coupon', 'cartlum' ); ?></button>
					</form>
					<p class="cl-note"><?php esc_html_e( 'Or insert “Coupon” in a message — it is created when needed.', 'cartlum' ); ?></p>
				<?php endif; ?>

				<?php // A closed cart (ordered, lost, unsubscribed) gets no more automatic messages, so say nothing about them. ?>
				<?php if ( Cartlum_Flow::enabled() && $is_open ) : ?>
					<h3><?php esc_html_e( 'Automatic messages', 'cartlum' ); ?></h3>
					<?php
					$next_run    = Cartlum_Flow::next_run( $cart );
					$total_steps = count( Cartlum_Flow::steps() );
					if ( (int) $cart->flow_stopped ) {
						echo '<p class="cl-muted">' . esc_html__( 'Stopped for this cart.', 'cartlum' ) . '</p>';
					} elseif ( $next_run ) {
						/* translators: 1: step number, 2: number of steps, 3: date and time */
						echo '<p>' . esc_html( sprintf( __( 'Step %1$d of %2$d is scheduled for %3$s.', 'cartlum' ), (int) $cart->flow_step + 1, $total_steps, wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ) ) . '</p>';
					} elseif ( (int) $cart->flow_step >= $total_steps ) {
						echo '<p class="cl-muted">' . esc_html__( 'All automatic messages have been sent.', 'cartlum' ) . '</p>';
					} else {
						echo '<p class="cl-muted">' . esc_html__( 'Starts when the cart is abandoned.', 'cartlum' ) . '</p>';
					}
					?>
					<?php if ( $is_open && ! (int) $cart->flow_stopped && (int) $cart->flow_step < $total_steps ) : ?>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>">
							<?php wp_nonce_field( 'cartlum_flow_stop_' . $cart->id ); ?>
							<input type="hidden" name="action" value="cartlum_flow_stop">
							<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
							<button class="button"><?php esc_html_e( 'Stop automatic messages', 'cartlum' ); ?></button>
						</form>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $is_open || 'lost' === $cart->status ) : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="cl-status-form">
						<?php wp_nonce_field( 'cartlum_set_status_' . $cart->id ); ?>
						<input type="hidden" name="action" value="cartlum_set_status">
						<input type="hidden" name="cart" value="<?php echo esc_attr( $cart->id ); ?>">
						<?php if ( $is_open ) : ?>
							<button class="button-link cl-link-quiet" name="status" value="lost"><?php esc_html_e( 'Mark as lost', 'cartlum' ); ?></button>
						<?php else : ?>
							<button class="button-link cl-link-quiet" name="status" value="abandoned"><?php esc_html_e( 'Reopen this cart', 'cartlum' ); ?></button>
						<?php endif; ?>
					</form>
				<?php endif; ?>
			</section>
		</aside>

		<section class="cl-section cl-cart__history" aria-labelledby="cl-history">
			<h2 id="cl-history"><?php esc_html_e( 'History', 'cartlum' ); ?></h2>
			<ol class="cl-timeline">
				<?php foreach ( array_reverse( $events ) as $event ) : ?>
					<?php
					$meta  = $event->meta ? json_decode( $event->meta, true ) : array();
					$label = isset( $event_labels[ $event->type ] ) ? $event_labels[ $event->type ] : $event->type;
					if ( 'call_logged' === $event->type && ! empty( $meta['outcome'] ) && isset( $outcomes[ $meta['outcome'] ] ) ) {
						$label .= ': ' . $outcomes[ $meta['outcome'] ];
					}
					$who  = $event->user_id ? get_the_author_meta( 'display_name', $event->user_id ) : '';
					$tone = isset( $event_tone[ $event->type ] ) ? $event_tone[ $event->type ] : ( in_array( $event->type, array( 'sms_sent', 'email_sent', 'whatsapp_opened', 'whatsapp_sent', 'call_logged', 'sms_test' ), true ) ? 'act' : '' );
					?>
					<li class="cl-timeline__item<?php echo $tone ? ' is-' . esc_attr( $tone ) : ''; ?>">
						<div class="cl-timeline__head">
							<strong><?php echo esc_html( $label ); ?></strong>
							<span class="cl-muted"><?php echo esc_html( Cartlum_Admin::ago( $event->created_at ) . ( $who ? ' · ' . $who : '' ) ); ?></span>
						</div>
						<?php if ( ! empty( $meta['error'] ) ) : ?>
							<div class="cl-timeline__error<?php echo 'warn' === $tone ? ' is-warn' : ''; ?>"><?php echo esc_html( $meta['error'] ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $meta['test_to'] ) ) : ?>
							<div class="cl-muted">→ <?php echo esc_html( Cartlum_Phone::display( $meta['test_to'] ) ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $meta['code'] ) ) : ?>
							<div><code><?php echo esc_html( $meta['code'] ); ?></code></div>
						<?php endif; ?>
						<?php if ( ! empty( $meta['order_id'] ) ) : ?>
							<div><a href="<?php echo esc_url( Cartlum_Admin::order_url( $meta['order_id'] ) ); ?>">#<?php echo esc_html( $meta['order_id'] ); ?></a></div>
						<?php endif; ?>
						<?php if ( $event->message ) : ?>
							<div class="cl-timeline__message"><?php echo nl2br( esc_html( $event->message ) ); ?></div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	</div>

	<?php if ( $has_phone ) : ?>
		<div class="cl-actionbar cl-only-narrow">
				<a class="button button-primary cl-btn-lg" href="<?php echo esc_url( 'tel:+' . $cart->phone ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span><?php esc_html_e( 'Call', 'cartlum' ); ?></a>
				<a class="button cl-btn-lg" target="_blank" rel="noopener" href="<?php echo esc_url( Cartlum_Admin_Actions::whatsapp_url( $cart ) ); ?>"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>WhatsApp</a>
		</div>
	<?php endif; ?>
</div>
