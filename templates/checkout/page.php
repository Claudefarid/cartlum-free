<?php
/**
 * The checkout page without the theme's header, menu and footer: only the store's logo and a way
 * back to the cart, so nothing pulls the customer away from finishing the order.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-scoped variables, included from a function.

$logo_url = trim( (string) Cartlum_Settings::get( 'checkout_logo' ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'clx-page' ); ?>>
<?php wp_body_open(); ?>
<script>document.documentElement.className += ' clx-js';</script>
<header class="clx-header">
	<div class="clx-cols">
		<div class="clx-col-main"><div class="clx-inner clx-inner--main clx-header__bar">
			<a class="clx-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php elseif ( has_custom_logo() ) : ?>
					<?php echo wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'medium', false, array( 'alt' => get_bloginfo( 'name' ) ) ); ?>
				<?php else : ?>
					<span class="clx-logo__text"><?php bloginfo( 'name' ); ?></span>
				<?php endif; ?>
			</a>
			<a class="clx-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Back to cart', 'cartlum' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
			</a>
		</div></div>
		<div class="clx-col-side" aria-hidden="true"></div>
	</div>
</header>
<main class="clx-page__main">
	<?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own checkout output. ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
