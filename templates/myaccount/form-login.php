<?php
/**
 * My Account login: phone number and a code by SMS, with WooCommerce's email and password form
 * folded underneath for staff and customers who prefer it.
 *
 * Replaces WooCommerce's myaccount/form-login.php while phone login is on.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own hooks.

do_action( 'woocommerce_before_customer_login_form' );
?>
<div class="clx-login">
	<h2 class="clx-login__title"><?php esc_html_e( 'Log in', 'cartlum' ); ?></h2>
	<p class="clx-login__lead"><?php esc_html_e( 'Enter your mobile number and we will send you a code. No password needed.', 'cartlum' ); ?></p>

	<form class="clx-login__form" data-step="phone" novalidate>
		<div class="clx-login__field" data-field="phone">
			<label for="clx-login-phone"><?php esc_html_e( 'Mobile number', 'cartlum' ); ?></label>
			<input type="tel" id="clx-login-phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="<?php echo esc_attr( '880' === Cartlum_Phone::default_calling_code() ? '01XXXXXXXXX' : '' ); ?>" required>
		</div>
		<div class="clx-login__field" data-field="code" hidden>
			<label for="clx-login-code"><?php esc_html_e( '6-digit code', 'cartlum' ); ?></label>
			<input type="text" id="clx-login-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*">
		</div>

		<p class="clx-login__msg" role="status" aria-live="polite"></p>

		<button type="submit" class="clx-login__submit"><?php esc_html_e( 'Send code', 'cartlum' ); ?></button>

		<p class="clx-login__links" hidden>
			<button type="button" class="clx-login__link" data-resend disabled></button>
			<button type="button" class="clx-login__link" data-change><?php esc_html_e( 'Change number', 'cartlum' ); ?></button>
		</p>
	</form>

	<p class="clx-login__note"><?php esc_html_e( 'New here? Your account is created when you log in, and orders you placed with this number appear in it.', 'cartlum' ); ?></p>

	<details class="clx-login__classic">
		<summary><?php esc_html_e( 'Log in with email and password', 'cartlum' ); ?></summary>
		<div class="clx-login__classic-body">
			<?php Cartlum_Phone_Login::classic_form(); ?>
		</div>
	</details>
</div>
