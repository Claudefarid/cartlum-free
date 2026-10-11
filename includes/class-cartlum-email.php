<?php
/**
 * Recovery email, wrapped in the store's WooCommerce email styling.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Email.
 */
class Cartlum_Email {

	/**
	 * Whether email can reach this cart's customer.
	 *
	 * Some stores remove the email field and generate a placeholder address from the phone number;
	 * those can be excluded with the cartlum_email_is_deliverable filter.
	 *
	 * @param object $cart Row.
	 * @return bool
	 */
	public static function can_email( $cart ) {
		$ok = is_email( $cart->email ) && ! Cartlum_Restore::is_opted_out( $cart->email );

		/**
		 * Filters whether a cart's email address can receive mail.
		 *
		 * @param bool   $ok   Deliverable.
		 * @param string $email Address.
		 */
		return (bool) apply_filters( 'cartlum_email_is_deliverable', $ok, $cart->email );
	}

	/**
	 * Sends a recovery email.
	 *
	 * @param object $cart    Row.
	 * @param string $subject Rendered subject.
	 * @param string $body    Rendered plain-text body; links are made clickable.
	 * @param string $context manual|automation.
	 * @param array  $meta    Extra details for the timeline event.
	 * @return true|WP_Error
	 */
	public static function send_to_cart( $cart, $subject, $body, $context = 'manual', array $meta = array() ) {
		if ( ! self::can_email( $cart ) ) {
			return new WP_Error( 'cartlum_no_email', __( 'This cart has no email address that can receive mail.', 'cartlum' ) );
		}

		if ( 'woocommerce' === Cartlum_Settings::get( 'email_design' ) ) {
			$body .= "\n\n" . sprintf( /* translators: %s: unsubscribe link */ __( 'Do not want these reminders? %s', 'cartlum' ), Cartlum_Restore::optout_link( $cart ) );
			$html  = wpautop( make_clickable( esc_html( $body ) ) );
			$html  = WC()->mailer()->wrap_message( $subject, $html );
			$html  = ( new WC_Email() )->style_inline( $html );
		} else {
			$html = self::designed( $cart, $subject, $body );
		}

		$sent = wp_mail( $cart->email, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );

		Cartlum_Events::add(
			$cart->id,
			$sent ? 'email_sent' : 'email_failed',
			array(
				'channel' => 'email',
				'message' => $subject . "\n\n" . $body,
				'meta'    => array_merge( $meta, array( 'context' => $context ) ),
			)
		);
		if ( ! $sent ) {
			return new WP_Error( 'cartlum_email_failed', __( 'WordPress could not send the email. Check your site’s mail settings.', 'cartlum' ) );
		}
		Cartlum_Contact::mark_contacted( $cart );
		return true;
	}

	/**
	 * The designed email: the store's logo, the message, the cart with product photos and a button
	 * in the store's colour. A line of the message that is only {items} becomes the product list and
	 * a line that is only {cart_link} becomes the button; either is added below the message if missing.
	 *
	 * @param object $cart    Row.
	 * @param string $subject Rendered subject.
	 * @param string $body    Rendered plain-text body.
	 * @return string HTML.
	 */
	public static function designed( $cart, $subject, $body ) {
		$accent = sanitize_hex_color( (string) Cartlum_Settings::get( 'checkout_accent' ) );
		$accent = $accent ? $accent : '#1f2937';
		$link   = Cartlum_Restore::link( $cart );
		$items  = Cartlum_Messages::items_text( $cart );

		$blocks = array();
		$para   = array();
		$used   = array();
		$flush  = function () use ( &$blocks, &$para ) {
			if ( $para ) {
				$blocks[] = array( 'text', $para );
				$para     = array();
			}
		};
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $body ) as $line ) {
			$t = trim( $line );
			if ( '' === $t ) {
				$flush();
			} elseif ( '' !== $items && $t === $items && ! isset( $used['items'] ) ) {
				$flush();
				$blocks[]      = array( 'items' );
				$used['items'] = true;
			} elseif ( $t === $link && ! isset( $used['button'] ) ) {
				$flush();
				$blocks[]       = array( 'button' );
				$used['button'] = true;
			} else {
				$para[] = $t;
			}
		}
		$flush();
		if ( ! isset( $used['items'] ) ) {
			$blocks[] = array( 'items' );
		}
		if ( ! isset( $used['button'] ) ) {
			$blocks[] = array( 'button' );
		}

		$args = array(
			'cart'     => $cart,
			'subject'  => $subject,
			'blocks'   => $blocks,
			'products' => self::products( $cart ),
			'link'     => $link,
			'optout'   => Cartlum_Restore::optout_link( $cart ),
			'logo'     => self::logo(),
			'store'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'accent'   => $accent,
			'ink'      => Cartlum_Checkout::ink_for( $accent ),
		);
		ob_start();
		include CARTLUM_DIR . 'templates/emails/reminder.php';
		$html = (string) ob_get_clean();

		/**
		 * Filters the designed reminder email's HTML.
		 *
		 * @param string $html HTML.
		 * @param object $cart Cart row.
		 * @param array  $args Values the template used.
		 */
		return (string) apply_filters( 'cartlum_email_html', $html, $cart, $args );
	}

	/**
	 * The cart's products with a photo for each.
	 *
	 * @param object $cart Row.
	 * @return array[] { name, quantity, total, image }
	 */
	private static function products( $cart ) {
		$out = array();
		foreach ( Cartlum_Carts::items( $cart ) as $item ) {
			$product = wc_get_product( ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) ( $item['product_id'] ?? 0 ) );
			$image   = $product ? wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' ) : '';
			if ( ! $image && $product && $product->get_parent_id() ) {
				$parent = wc_get_product( $product->get_parent_id() );
				$image  = $parent ? wp_get_attachment_image_url( (int) $parent->get_image_id(), 'woocommerce_thumbnail' ) : '';
			}
			$out[] = array(
				'name'     => (string) ( $item['name'] ?? '' ),
				'quantity' => max( 1, (int) ( $item['quantity'] ?? 1 ) ),
				'total'    => wc_price( (float) ( $item['line_total'] ?? 0 ), array( 'currency' => $cart->currency ) ),
				'image'    => $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			);
		}
		return $out;
	}

	/**
	 * The checkout logo, else the theme's logo.
	 *
	 * @return string URL or ''.
	 */
	private static function logo() {
		$url = trim( (string) Cartlum_Settings::get( 'checkout_logo' ) );
		if ( '' === $url && get_theme_mod( 'custom_logo' ) ) {
			$url = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
		}
		return $url;
	}

	/**
	 * Settings → Email: a preview link.
	 *
	 * @param string $tab Tab.
	 */
	public static function preview_link( $tab ) {
		if ( 'email' !== $tab ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=cartlum_email_preview' ), 'cartlum_email_preview' );
		echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html__( 'Preview the email', 'cartlum' ) . '</a> <span class="description">' . esc_html__( 'Shows the saved subject and message with products from your store. Save first to see your changes.', 'cartlum' ) . '</span></p>';
	}

	/**
	 * Shows the reminder email for a sample cart. Nothing is sent and no coupon is created.
	 */
	public static function preview() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_email_preview' );

		$items = array();
		foreach ( wc_get_products(
			array(
				'limit'  => 2,
				'status' => 'publish',
			)
		) as $i => $product ) {
			$qty     = 0 === $i ? 1 : 2;
			$items[] = array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
				'quantity'     => $qty,
				'name'         => $product->get_name(),
				'line_total'   => $qty * (float) $product->get_price(),
			);
		}
		$user = wp_get_current_user();
		$cart = (object) array(
			'id'            => 0,
			'token'         => 'preview',
			'name'          => $user->first_name ? $user->first_name : $user->display_name,
			'email'         => $user->user_email,
			'currency'      => get_woocommerce_currency(),
			'cart_total'    => array_sum( wp_list_pluck( $items, 'line_total' ) ),
			'cart_contents' => wp_json_encode( $items ),
		);
		$sample  = array(
			'{coupon}'   => 'SAMPLE10',
			'{discount}' => __( '10% off', 'cartlum' ),
		);
		$subject = Cartlum_Messages::render( strtr( (string) Cartlum_Settings::get( 'email_subject' ), $sample ), $cart );
		$body    = Cartlum_Messages::render( strtr( (string) Cartlum_Settings::get( 'email_template' ), $sample ), $cart );
		if ( 'woocommerce' === Cartlum_Settings::get( 'email_design' ) ) {
			$html = WC()->mailer()->wrap_message( $subject, wpautop( make_clickable( esc_html( $body ) ) ) );
			$html = ( new WC_Email() )->style_inline( $html );
		} else {
			$html = self::designed( $cart, $subject, $body );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		exit;
	}
}
