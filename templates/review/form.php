<?php
/**
 * Review form opened from the private link in the review request SMS.
 *
 * @package Cartlum
 *
 * @var array $args { order, products, state: form|thanks|done|invalid, error, token }
 */

defined( 'ABSPATH' ) || exit;

$cartlum_state    = $args['state'];
$cartlum_order    = $args['order'];
$cartlum_products = $args['products'];
$cartlum_name     = $cartlum_order ? Cartlum_Messages::safe_name( $cartlum_order->get_billing_first_name() ) : '';
$cartlum_shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

// Block themes have no header.php: print the page shell and their header template part.
$cartlum_block = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
if ( $cartlum_block ) :
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cartlum-review-page' ); ?>>
	<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php
	block_template_part( 'header' );
else :
	get_header();
endif;
?>
<main class="clr-page" id="cartlum-review">
	<div class="clr-wrap">
	<?php if ( 'invalid' === $cartlum_state ) : ?>
		<h1 class="clr-title"><?php esc_html_e( 'This review link is not valid', 'cartlum' ); ?></h1>
		<p class="clr-lede"><?php esc_html_e( 'It may be incomplete or no longer in use. Please copy the whole link from your SMS, or contact the shop.', 'cartlum' ); ?></p>
		<p><a class="clr-btn" href="<?php echo esc_url( $cartlum_shop ); ?>"><?php esc_html_e( 'Go to the shop', 'cartlum' ); ?></a></p>

	<?php elseif ( 'thanks' === $cartlum_state || 'done' === $cartlum_state ) : ?>
		<p class="clr-mark" aria-hidden="true">★</p>
		<h1 class="clr-title"><?php echo 'thanks' === $cartlum_state ? esc_html__( 'Thank you for your review!', 'cartlum' ) : esc_html__( 'You have already reviewed this order', 'cartlum' ); ?></h1>
		<p class="clr-lede"><?php esc_html_e( 'Your review helps other customers choose. We read every one.', 'cartlum' ); ?></p>
		<p><a class="clr-btn" href="<?php echo esc_url( $cartlum_shop ); ?>"><?php esc_html_e( 'Continue shopping', 'cartlum' ); ?></a></p>

	<?php else : ?>
		<h1 class="clr-title">
			<?php
			/* translators: %s: customer's first name */
			echo esc_html( '' !== $cartlum_name ? sprintf( __( '%s, how do you like your order?', 'cartlum' ), $cartlum_name ) : __( 'How do you like your order?', 'cartlum' ) );
			?>
		</h1>
		<p class="clr-lede">
			<?php
			/* translators: %s: order number */
			echo esc_html( sprintf( __( 'Order #%s. Choose stars for each product; a few words help even more.', 'cartlum' ), $cartlum_order->get_order_number() ) );
			?>
		</p>
		<?php if ( '' !== $args['error'] ) : ?>
			<p class="clr-error" role="alert"><?php echo esc_html( $args['error'] ); ?></p>
		<?php endif; ?>
		<form method="post" class="clr-form" novalidate>
			<?php wp_nonce_field( 'cartlum_review_' . $args['token'], 'cartlum_review_nonce' ); ?>
			<?php
			foreach ( $cartlum_products as $cartlum_id => $cartlum_product ) :
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- refilling the form after an error; the nonce is checked when saving.
				$cartlum_old = isset( $_POST['cartlum_rating'][ $cartlum_id ] ) ? absint( $_POST['cartlum_rating'][ $cartlum_id ] ) : 0;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
				$cartlum_text = isset( $_POST['cartlum_text'][ $cartlum_id ] ) ? sanitize_textarea_field( wp_unslash( $_POST['cartlum_text'][ $cartlum_id ] ) ) : '';
				?>
				<fieldset class="clr-item">
					<legend class="clr-product">
						<?php echo wp_kses_post( $cartlum_product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'clr-thumb' ) ) ); ?>
						<span><?php echo esc_html( $cartlum_product->get_name() ); ?></span>
					</legend>
					<div class="clr-stars" role="radiogroup" aria-label="<?php esc_attr_e( 'Your rating', 'cartlum' ); ?>">
						<?php
						// Highest first in the markup: with row-reverse, "checked ~ label" fills the stars to its left.
						for ( $cartlum_s = 5; $cartlum_s >= 1; $cartlum_s-- ) :
							/* translators: %d: number of stars */
							$cartlum_star = sprintf( _n( '%d star', '%d stars', $cartlum_s, 'cartlum' ), $cartlum_s );
							$cartlum_for  = 'clr-' . $cartlum_id . '-' . $cartlum_s;
							?>
							<input type="radio" id="<?php echo esc_attr( $cartlum_for ); ?>" name="cartlum_rating[<?php echo esc_attr( $cartlum_id ); ?>]" value="<?php echo esc_attr( $cartlum_s ); ?>" <?php checked( $cartlum_old, $cartlum_s ); ?>>
							<label for="<?php echo esc_attr( $cartlum_for ); ?>" title="<?php echo esc_attr( $cartlum_star ); ?>"><span aria-hidden="true">★</span><span class="clr-sr"><?php echo esc_html( $cartlum_star ); ?></span></label>
						<?php endfor; ?>
					</div>
					<label class="clr-label" for="clr-text-<?php echo esc_attr( $cartlum_id ); ?>"><?php esc_html_e( 'Your review (optional)', 'cartlum' ); ?></label>
					<textarea id="clr-text-<?php echo esc_attr( $cartlum_id ); ?>" name="cartlum_text[<?php echo esc_attr( $cartlum_id ); ?>]" rows="3" maxlength="2000" placeholder="<?php esc_attr_e( 'Quality, size, colour, delivery…', 'cartlum' ); ?>"><?php echo esc_textarea( $cartlum_text ); ?></textarea>
				</fieldset>
			<?php endforeach; ?>
			<label class="clr-label" for="clr-name"><?php esc_html_e( 'Name shown with your review', 'cartlum' ); ?></label>
			<input type="text" id="clr-name" name="cartlum_name" maxlength="60" value="<?php echo esc_attr( $cartlum_name ); ?>" autocomplete="given-name">
			<div class="clr-hp" aria-hidden="true"><input type="text" name="cartlum_website" tabindex="-1" autocomplete="off"></div>
			<button type="submit" class="clr-btn clr-submit"><?php esc_html_e( 'Send review', 'cartlum' ); ?></button>
			<p class="clr-fine"><?php esc_html_e( 'Only your name and review are shown, never your phone number.', 'cartlum' ); ?></p>
		</form>
	<?php endif; ?>
	</div>
</main>
<?php
if ( $cartlum_block ) :
	block_template_part( 'footer' );
	?>
</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
else :
	get_footer();
endif;
