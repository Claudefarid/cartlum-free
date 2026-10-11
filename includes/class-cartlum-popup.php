<?php
/**
 * Save-cart popup: asks for the mobile number when a shopper with items in the cart is about to
 * leave, or right after "Add to cart", so carts are saved even when the shopper never reaches the
 * checkout. Never shown on the checkout, account or thank-you pages, and easy to close.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Popup.
 */
class Cartlum_Popup {

	const JUST_ADDED = 'cartlum_popup_now';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'enabled' ) || 'yes' !== Cartlum_Settings::get( 'popup_enabled' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
		// No nonce: pages are often cached, and a nonce would not stop a script anyway. Abuse is held
		// back by the capture limit per IP address and the reminder limits per number.
		add_action( 'wc_ajax_cartlum_popup', array( __CLASS__, 'ajax_save' ) );
		if ( 'exit' !== self::trigger() ) {
			add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'mark_added' ) );
		}
	}

	/**
	 * exit|add_to_cart|both.
	 *
	 * @return string
	 */
	private static function trigger() {
		return (string) Cartlum_Settings::get( 'popup_trigger' );
	}

	/**
	 * Pages where the popup never shows.
	 *
	 * @return bool
	 */
	private static function excluded() {
		return is_checkout() || is_account_page() || is_order_received_page() || is_admin();
	}

	/**
	 * Whether the store already has this shopper's number.
	 *
	 * @return bool
	 */
	private static function known() {
		$cart = Cartlum_Capture::current_cart();
		if ( $cart && '' !== (string) $cart->phone ) {
			return true;
		}
		return WC()->customer && '' !== (string) WC()->customer->get_billing_phone();
	}

	/**
	 * A product was added on a full page load: show the popup on the next page.
	 */
	public static function mark_added() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads a flag.
		if ( WC()->session && ! wp_doing_ajax() && empty( $_REQUEST['cartlum_buy_now'] ) ) {
			WC()->session->set( self::JUST_ADDED, 1 );
		}
	}

	/**
	 * Script and styles.
	 */
	public static function enqueue() {
		if ( self::excluded() || self::known() ) {
			return;
		}
		$just_added = false;
		if ( WC()->session && WC()->session->get( self::JUST_ADDED ) ) {
			$just_added = true;
			WC()->session->set( self::JUST_ADDED, null );
		}
		wp_enqueue_style( 'cartlum-popup', CARTLUM_URL . 'assets/css/popup.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-popup', Cartlum_Checkout::accent_css( '.clx-popup' ) );
		wp_enqueue_script( 'cartlum-popup', CARTLUM_URL . 'assets/js/popup.js', array(), CARTLUM_VERSION, true );
		wp_localize_script(
			'cartlum-popup',
			'cartlumPopup',
			array(
				'url'       => WC_AJAX::get_endpoint( 'cartlum_popup' ),
				'trigger'   => self::trigger(),
				'justAdded' => $just_added,
				'error'     => __( 'Could not save right now. Please try again.', 'cartlum' ),
			)
		);
	}

	/**
	 * The popup, hidden until the script opens it.
	 */
	public static function render() {
		if ( ! wp_script_is( 'cartlum-popup', 'enqueued' ) ) {
			return;
		}
		?>
		<div class="clx-popup" id="cartlum-popup" hidden>
			<div class="clx-popup__backdrop" data-cartlum-close></div>
			<div class="clx-popup__card" role="dialog" aria-modal="true" aria-labelledby="cartlum-popup-title">
				<button type="button" class="clx-popup__x" data-cartlum-close aria-label="<?php esc_attr_e( 'Close', 'cartlum' ); ?>">&times;</button>
				<form class="clx-popup__form" novalidate>
					<h2 class="clx-popup__title" id="cartlum-popup-title"><?php echo esc_html( self::text( 'popup_title', __( 'Save your cart?', 'cartlum' ) ) ); ?></h2>
					<p class="clx-popup__text"><?php echo esc_html( self::text( 'popup_text', __( 'Leave your mobile number and we will keep your cart for you. We may send you a reminder by SMS.', 'cartlum' ) ) ); ?></p>
					<label class="screen-reader-text" for="cartlum-popup-phone"><?php esc_html_e( 'Mobile number', 'cartlum' ); ?></label>
					<input type="tel" id="cartlum-popup-phone" name="phone" class="clx-popup__input" inputmode="tel" autocomplete="tel" placeholder="<?php esc_attr_e( 'Mobile number', 'cartlum' ); ?>" required>
					<input type="text" name="cartlum_website" value="" tabindex="-1" autocomplete="off" class="clx-popup__hp" aria-hidden="true">
					<p class="clx-popup__error" role="alert"></p>
					<button type="submit" class="clx-popup__button"><?php echo esc_html( self::text( 'popup_button', __( 'Save my cart', 'cartlum' ) ) ); ?></button>
					<button type="button" class="clx-popup__later" data-cartlum-close><?php esc_html_e( 'No thanks', 'cartlum' ); ?></button>
				</form>
				<div class="clx-popup__done" hidden>
					<h2 class="clx-popup__title"><?php esc_html_e( 'Your cart is saved', 'cartlum' ); ?></h2>
					<p class="clx-popup__text"><?php esc_html_e( 'Your number will be filled in for you at checkout.', 'cartlum' ); ?></p>
					<button type="button" class="clx-popup__button" data-cartlum-close><?php esc_html_e( 'Continue shopping', 'cartlum' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * A text setting, or its translated default when empty.
	 *
	 * @param string $key      Setting.
	 * @param string $fallback Default.
	 * @return string
	 */
	private static function text( $key, $fallback ) {
		$value = trim( (string) Cartlum_Settings::get( $key ) );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * AJAX: save the cart with the number.
	 */
	public static function ajax_save() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see init().
		if ( ! empty( $_POST['cartlum_website'] ) ) {
			wp_send_json_success(); // A bot filled the hidden field; pretend it worked.
		}
		$phone = Cartlum_OTP::mobile( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid mobile number.', 'cartlum' ) ) );
		}
		$id = Cartlum_Capture::save(
			array(
				'phone'  => $phone,
				'source' => 'popup',
			)
		);
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save right now. Please try again.', 'cartlum' ) ) );
		}
		// Fill it in at checkout.
		if ( WC()->customer && '' === (string) WC()->customer->get_billing_phone() ) {
			WC()->customer->set_billing_phone( Cartlum_OTP::shown( $phone ) );
			WC()->customer->save();
		}
		wp_send_json_success();
	}
}
