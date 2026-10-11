<?php
/**
 * The designed reminder email. Tables and inline styles so it looks the same in Gmail, Outlook and
 * phone mail apps.
 *
 * @package Cartlum
 *
 * @var array $args See Cartlum_Email::designed().
 */

defined( 'ABSPATH' ) || exit;

$cartlum_font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans Bengali',Arial,sans-serif";
$cartlum_cart = $args['cart'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $args['subject'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;">
<tr><td align="center" style="padding:24px 12px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
		<tr><td align="center" style="padding:0 0 20px;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="text-decoration:none;color:#111111;font-family:<?php echo esc_attr( $cartlum_font ); ?>;font-size:22px;font-weight:700;">
				<?php if ( $args['logo'] ) : ?>
					<img src="<?php echo esc_url( $args['logo'] ); ?>" alt="<?php echo esc_attr( $args['store'] ); ?>" style="display:block;max-height:56px;max-width:220px;width:auto;height:auto;border:0;">
				<?php else : ?>
					<?php echo esc_html( $args['store'] ); ?>
				<?php endif; ?>
			</a>
		</td></tr>
		<tr><td style="background:#ffffff;border-radius:14px;padding:32px 28px;font-family:<?php echo esc_attr( $cartlum_font ); ?>;font-size:16px;line-height:1.55;color:#1f2937;">
			<?php foreach ( $args['blocks'] as $cartlum_block ) : ?>
				<?php if ( 'text' === $cartlum_block[0] ) : ?>
					<p style="margin:0 0 16px;"><?php echo implode( '<br>', array_map( 'make_clickable', array_map( 'esc_html', $cartlum_block[1] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then links added. ?></p>
				<?php elseif ( 'items' === $cartlum_block[0] && $args['products'] ) : ?>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 20px;border-top:1px solid #e5e7eb;">
						<?php foreach ( $args['products'] as $cartlum_item ) : ?>
							<tr>
								<td width="72" style="padding:14px 14px 14px 0;border-bottom:1px solid #e5e7eb;vertical-align:middle;">
									<img src="<?php echo esc_url( $cartlum_item['image'] ); ?>" alt="" width="64" height="64" style="display:block;width:64px;height:64px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;">
								</td>
								<td style="padding:14px 0;border-bottom:1px solid #e5e7eb;vertical-align:middle;font-size:15px;color:#111111;">
									<?php echo esc_html( $cartlum_item['name'] ); ?>
									<div style="font-size:13px;color:#6b7280;">
										<?php
										/* translators: %d: quantity */
										echo esc_html( sprintf( __( 'Quantity: %d', 'cartlum' ), $cartlum_item['quantity'] ) );
										?>
									</div>
								</td>
								<td align="right" style="padding:14px 0 14px 12px;border-bottom:1px solid #e5e7eb;vertical-align:middle;font-size:15px;color:#111111;white-space:nowrap;">
									<?php echo wp_kses_post( $cartlum_item['total'] ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td colspan="2" style="padding:14px 0 0;font-size:15px;font-weight:700;color:#111111;"><?php esc_html_e( 'Total', 'cartlum' ); ?></td>
							<td align="right" style="padding:14px 0 0;font-size:17px;font-weight:700;color:#111111;white-space:nowrap;"><?php echo wp_kses_post( wc_price( (float) $cartlum_cart->cart_total, array( 'currency' => $cartlum_cart->currency ) ) ); ?></td>
						</tr>
					</table>
				<?php elseif ( 'button' === $cartlum_block[0] ) : ?>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 20px;">
						<tr><td align="center" style="border-radius:10px;background:<?php echo esc_attr( $args['accent'] ); ?>;">
							<a href="<?php echo esc_url( $args['link'] ); ?>" style="display:block;padding:15px 24px;font-family:<?php echo esc_attr( $cartlum_font ); ?>;font-size:17px;font-weight:700;color:<?php echo esc_attr( $args['ink'] ); ?>;text-decoration:none;border-radius:10px;"><?php esc_html_e( 'Complete your order', 'cartlum' ); ?></a>
						</td></tr>
					</table>
				<?php endif; ?>
			<?php endforeach; ?>
		</td></tr>
		<tr><td align="center" style="padding:20px 12px 0;font-family:<?php echo esc_attr( $cartlum_font ); ?>;font-size:12px;line-height:1.6;color:#6b7280;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#6b7280;text-decoration:none;font-weight:600;"><?php echo esc_html( $args['store'] ); ?></a><br>
			<?php esc_html_e( 'You got this email because you started an order with us.', 'cartlum' ); ?>
			<a href="<?php echo esc_url( $args['optout'] ); ?>" style="color:#6b7280;text-decoration:underline;"><?php esc_html_e( 'Stop these reminders', 'cartlum' ); ?></a>
		</td></tr>
	</table>
</td></tr>
</table>
</body>
</html>
