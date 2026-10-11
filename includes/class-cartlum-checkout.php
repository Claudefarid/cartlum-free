<?php
/**
 * Phone-first checkout: a short WooCommerce checkout for stores whose customers order with a phone
 * number and pay on delivery. Phone, name, address, district -- nothing else unless the store needs
 * it.
 *
 * It reshapes WooCommerce's own (classic) checkout rather than replacing it, so payment gateways,
 * shipping zones, coupons, stock and every plugin that listens to WooCommerce orders keep working.
 * Pages built with the Checkout block are rendered with the classic checkout while it is on.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Checkout.
 */
class Cartlum_Checkout {

	/**
	 * Whether the phone-first checkout is on for this visitor: for everyone, or -- while it is in
	 * preview -- only for shop managers, so it can be tried on the live store first.
	 *
	 * @return bool
	 */
	public static function active() {
		if ( 'yes' !== Cartlum_Settings::get( 'checkout_enabled' ) ) {
			return false;
		}
		return 'yes' !== Cartlum_Settings::get( 'checkout_preview' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Whether this request belongs to a CartFlows checkout step. Those keep CartFlows' own form;
	 * Cartlum's checkout only replaces the store's checkout page.
	 *
	 * @return bool
	 */
	private static function in_cartflows() {
		// phpcs:ignore WordPress.Security.NonceVerification -- only reads which page the request belongs to.
		if ( ! empty( $_GET['wcf_checkout_id'] ) || ! empty( $_POST['_wcf_checkout_id'] ) ) {
			return true;
		}
		return did_action( 'wp' ) && is_singular( 'cartflows_step' );
	}

	/**
	 * Wraps a hook callback so it does nothing inside a CartFlows step (filters return their value
	 * unchanged).
	 *
	 * @param callable $callback Callback.
	 * @return Closure
	 */
	private static function outside_cartflows( $callback ) {
		return function ( ...$args ) use ( $callback ) {
			if ( self::in_cartflows() ) {
				return isset( $args[0] ) ? $args[0] : null;
			}
			return call_user_func_array( $callback, $args );
		};
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		// Orders from this checkout have the district as their city; do not print it twice.
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'dedupe_state' ), 10, 1 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'dedupe_state' ), 10, 1 );

		if ( ! self::active() ) {
			return;
		}
		$hooks = array(
			// Before the block renders, so its (large) scripts are never loaded.
			array( 'pre_render_block', 'render_block', 10, 2 ),
			array( 'woocommerce_checkout_fields', 'fields', 1000, 1 ),
			// WooCommerce's address script re-applies the country's labels, order and required flags
			// in the browser, so the same changes go into the country locale as well.
			array( 'woocommerce_get_country_locale', 'locale', 10, 1 ),
			// One address: WooCommerce's "ship to billing address only" mode, which also copies it
			// into the order's shipping address for couriers.
			array( 'pre_option_woocommerce_ship_to_destination', 'ship_to_billing', 10, 1 ),
			array( 'woocommerce_checkout_posted_data', 'posted_data', 10, 1 ),
			// The hidden country field, and the district list and delivery charges that depend on it.
			array( 'default_checkout_billing_country', 'country', 10, 1 ),
			array( 'default_checkout_shipping_country', 'country', 10, 1 ),
			array( 'woocommerce_after_checkout_validation', 'validate', 10, 2 ),
			array( 'woocommerce_checkout_create_order', 'fill_city', 10, 1 ),
			array( 'woocommerce_order_button_text', 'button_text', 10, 1 ),
			array( 'woocommerce_checkout_required_field_notice', 'required_notice', 10, 3 ),
			array( 'wp_enqueue_scripts', 'enqueue', 20, 0 ),
			array( 'body_class', 'body_class', 10, 1 ),
			// Layout: a focused page, Cartlum's own form and summary templates, live-updated parts.
			array( 'template_include', 'page_template', 99, 1 ),
			array( 'woocommerce_locate_template', 'locate_template', 20, 2 ),
			array( 'woocommerce_update_order_review_fragments', 'fragments', 10, 1 ),
			array( 'woocommerce_before_checkout_form', 'remove_prompts', 1, 0 ),
			// The "(optional)" WooCommerce adds to labels, in Cartlum's language.
			array( 'gettext_woocommerce', 'optional_word', 10, 2 ),
		);
		foreach ( $hooks as $hook ) {
			add_filter( $hook[0], self::outside_cartflows( array( __CLASS__, $hook[1] ) ), $hook[2], $hook[3] );
		}

		// CartFlows' "global checkout" shows its own checkout on the store's checkout page; let the
		// store's page through for visitors who get Cartlum's checkout. (CartFlows' own filter.)
		add_filter( 'cartflows_allow_display_global_checkout', '__return_true' );
	}

	/**
	 * Drops the state (district) from a formatted address when it is the same as the city.
	 *
	 * @param array $address Address parts.
	 * @return array
	 */
	public static function dedupe_state( $address ) {
		if ( empty( $address['state'] ) || empty( $address['city'] ) || empty( $address['country'] ) ) {
			return $address;
		}
		$states = WC()->countries->get_states( $address['country'] );
		$name   = isset( $states[ $address['state'] ] ) ? html_entity_decode( $states[ $address['state'] ], ENT_QUOTES, 'UTF-8' ) : $address['state'];
		if ( 0 === strcasecmp( trim( $name ), trim( $address['city'] ) ) ) {
			$address['state'] = '';
		}
		return $address;
	}

	/**
	 * WooCommerce's "optional" label suffix, translated with Cartlum's strings on its checkout.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original.
	 * @return string
	 */
	public static function optional_word( $translation, $text ) {
		return 'optional' === $text && self::in_checkout() ? __( 'optional', 'cartlum' ) : $translation;
	}

	/**
	 * The discount code lives in the summary and the page has no room for a login prompt.
	 */
	public static function remove_prompts() {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
	}

	/**
	 * The checkout form page (not the thank-you or pay-for-order pages).
	 *
	 * @return bool
	 */
	private static function is_form_page() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' );
	}

	/**
	 * The thank-you page after an order.
	 *
	 * @return bool
	 */
	private static function is_thanks_page() {
		return function_exists( 'is_checkout' ) && is_checkout() && is_wc_endpoint_url( 'order-received' );
	}

	/**
	 * The focused page template, without the theme's header and footer.
	 *
	 * @param string $template Template file.
	 * @return string
	 */
	public static function page_template( $template ) {
		return self::is_form_page() || self::is_thanks_page() ? CARTLUM_DIR . 'templates/checkout/page.php' : $template;
	}

	/**
	 * Cartlum's checkout form and order summary templates.
	 *
	 * @param string $template      Located file.
	 * @param string $template_name WooCommerce template name.
	 * @return string
	 */
	public static function locate_template( $template, $template_name ) {
		$ours = array(
			'checkout/form-checkout.php' => 'templates/checkout/form-checkout.php',
			'checkout/review-order.php'  => 'templates/checkout/review-order.php',
			'checkout/thankyou.php'      => 'templates/checkout/thankyou.php',
		);
		return isset( $ours[ $template_name ] ) ? CARTLUM_DIR . $ours[ $template_name ] : $template;
	}

	/**
	 * Parts WooCommerce replaces after each change, besides the summary and payment.
	 *
	 * @param array $fragments Selector => HTML.
	 * @return array
	 */
	public static function fragments( $fragments ) {
		$fragments['.clx-shipping-methods'] = self::shipping_methods_html();
		ob_start();
		wc_cart_totals_order_total_html();
		$fragments['.clx-total-amount'] = '<span class="clx-total-amount">' . ob_get_clean() . '</span>';
		return $fragments;
	}

	/**
	 * Shipping methods as a list of options, in the form rather than in the summary.
	 *
	 * @return string
	 */
	public static function shipping_methods_html() {
		$html     = '<div class="clx-shipping-methods">';
		$packages = WC()->shipping()->get_packages();
		$chosen   = (array) WC()->session->get( 'chosen_shipping_methods' );
		if ( ! $packages ) {
			$html .= '<p class="clx-empty">' . esc_html__( 'Enter your address to see the delivery charge.', 'cartlum' ) . '</p>';
		}
		foreach ( $packages as $i => $package ) {
			$rates = isset( $package['rates'] ) ? $package['rates'] : array();
			if ( ! $rates ) {
				$html .= '<p class="clx-empty">' . esc_html__( 'Choose your district to see the delivery charge.', 'cartlum' ) . '</p>';
				continue;
			}
			$current = isset( $chosen[ $i ] ) && isset( $rates[ $chosen[ $i ] ] ) ? $chosen[ $i ] : key( $rates );
			$html   .= '<ul class="clx-options">';
			foreach ( $rates as $rate_id => $rate ) {
				$id    = 'shipping_method_' . $i . '_' . sanitize_title( $rate_id );
				$cost  = (float) $rate->get_cost();
				$cost += WC()->cart->display_prices_including_tax() ? array_sum( $rate->get_taxes() ) : 0;
				$html .= sprintf(
					'<li class="clx-option"><label for="%1$s"><input type="radio" name="shipping_method[%2$d]" data-index="%2$d" id="%1$s" value="%3$s" class="shipping_method" %4$s><span class="clx-option__name">%5$s</span><span class="clx-option__price">%6$s</span></label></li>',
					esc_attr( $id ),
					(int) $i,
					esc_attr( $rate_id ),
					checked( $rate_id, $current, false ),
					esc_html( $rate->get_label() ),
					$cost > 0 ? wp_kses_post( wc_price( $cost ) ) : esc_html__( 'Free', 'cartlum' )
				);
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	/**
	 * The chosen delivery charge, for the summary.
	 *
	 * @return string
	 */
	public static function shipping_total_html() {
		$packages = WC()->shipping()->get_packages();
		$has_rate = false;
		foreach ( $packages as $package ) {
			$has_rate = $has_rate || ! empty( $package['rates'] );
		}
		if ( ! $has_rate ) {
			return '<span class="clx-muted">' . esc_html__( 'Enter district', 'cartlum' ) . '</span>';
		}
		$total = (float) WC()->cart->get_shipping_total() + ( WC()->cart->display_prices_including_tax() ? (float) WC()->cart->get_shipping_tax() : 0 );
		return $total > 0 ? wc_price( $total ) : esc_html__( 'Free', 'cartlum' );
	}

	/**
	 * Links to the store's policies under the form.
	 *
	 * @return string
	 */
	public static function policy_links_html() {
		$links = array();
		$pages = array(
			(int) get_option( 'woocommerce_terms_page_id' ) => __( 'Terms', 'cartlum' ),
			(int) get_option( 'wp_page_for_privacy_policy' ) => __( 'Privacy policy', 'cartlum' ),
		);
		foreach ( $pages as $page_id => $label ) {
			if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
				$links[] = '<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		return $links ? '<nav class="clx-policies" aria-label="' . esc_attr__( 'Store policies', 'cartlum' ) . '">' . implode( '', $links ) . '</nav>' : '';
	}

	/**
	 * Value of the woocommerce_ship_to_destination option while this checkout is on.
	 *
	 * @return string
	 */
	public static function ship_to_billing() {
		return 'billing_only';
	}

	/**
	 * The Checkout block becomes the classic checkout, which the rest of this class reshapes.
	 *
	 * @param string|null $pre   Null to render normally.
	 * @param array       $block Block.
	 * @return string|null
	 */
	public static function render_block( $pre, $block ) {
		if ( isset( $block['blockName'] ) && 'woocommerce/checkout' === $block['blockName'] ) {
			return do_shortcode( '[woocommerce_checkout]' );
		}
		return $pre;
	}

	/**
	 * The delivery country: always the store's own. This checkout is for stores that deliver
	 * within their country, which is what lets it drop the country, city and postcode fields.
	 *
	 * @return string
	 */
	public static function country() {
		return (string) WC()->countries->get_base_country();
	}

	/**
	 * Whether the store is in Bangladesh, where mobile numbers have a known shape.
	 *
	 * @return bool
	 */
	private static function is_bd() {
		return '880' === Cartlum_Phone::default_calling_code();
	}

	/**
	 * Label, order and required flag of the fields this checkout changes, by address field key.
	 *
	 * @return array
	 */
	private static function overrides() {
		$fields = array(
			'phone'      => array(
				'label'       => __( 'Mobile number', 'cartlum' ),
				'placeholder' => self::is_bd() ? '01XXXXXXXXX' : '',
				'required'    => true,
				'priority'    => 10,
			),
			'first_name' => array(
				'label'    => __( 'Full name', 'cartlum' ),
				'required' => true,
				'priority' => 20,
			),
			'address_1'  => array(
				'label'       => __( 'Full address', 'cartlum' ),
				'placeholder' => __( 'House, road, area', 'cartlum' ),
				'required'    => true,
				'priority'    => 30,
			),
		);
		// The district (state) replaces the city, but only in countries that have a list of them.
		if ( WC()->countries->get_states( self::country() ) ) {
			$fields['state'] = array(
				'required' => true,
				'priority' => 40,
			);
			if ( self::is_bd() ) {
				$fields['state']['label'] = __( 'District', 'cartlum' );
			}
		}
		return $fields;
	}

	/**
	 * Whether this request shows or submits the checkout (not My Account address forms).
	 *
	 * @return bool
	 */
	private static function in_checkout() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which WooCommerce endpoint this is.
		$endpoint = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';
		return in_array( $endpoint, array( 'update_order_review', 'checkout' ), true ) || ( function_exists( 'is_checkout' ) && is_checkout() );
	}

	/**
	 * The store country's locale, with this checkout's labels and order.
	 *
	 * @param array $locale Locale by country code.
	 * @return array
	 */
	public static function locale( $locale ) {
		if ( ! self::in_checkout() ) {
			return $locale;
		}
		$country            = self::country();
		$locale[ $country ] = array_replace_recursive( isset( $locale[ $country ] ) ? $locale[ $country ] : array(), self::overrides() );
		return $locale;
	}

	/**
	 * Reshapes the checkout form.
	 *
	 * @param array $fields Checkout fields by fieldset.
	 * @return array
	 */
	public static function fields( $fields ) {
		if ( empty( $fields['billing'] ) ) {
			return $fields;
		}
		$b       = $fields['billing'];
		$country = self::country();
		$states  = WC()->countries->get_states( $country );
		$locale  = WC()->countries->get_country_locale();

		unset( $b['billing_last_name'], $b['billing_company'], $b['billing_address_2'] );

		foreach ( self::overrides() as $key => $over ) {
			if ( isset( $b[ 'billing_' . $key ] ) ) {
				$b[ 'billing_' . $key ] = array_merge( $b[ 'billing_' . $key ], $over );
			}
		}
		if ( isset( $b['billing_phone'] ) ) {
			$b['billing_phone']['class']             = array( 'form-row-wide' );
			$b['billing_phone']['custom_attributes'] = array(
				'inputmode'    => 'tel',
				'autocomplete' => 'tel',
			);
			if ( 'yes' === Cartlum_Settings::get( 'capture_notice' ) && 'yes' === Cartlum_Settings::get( 'enabled' ) ) {
				$b['billing_phone']['description'] = (string) Cartlum_Settings::get( 'capture_notice_text' );
			}
		}
		if ( isset( $b['billing_first_name'] ) ) {
			$b['billing_first_name']['class']        = array( 'form-row-wide' );
			$b['billing_first_name']['autocomplete'] = 'name';
		}

		if ( $country ) {
			// Nothing to choose. The field stays (WooCommerce's scripts read it) but is not shown.
			if ( isset( $b['billing_country'] ) ) {
				$b['billing_country']['priority'] = 1;
				$b['billing_country']['class'][]  = 'cartlum-hide';
			}
			if ( $states && isset( $b['billing_state'] ) ) {
				// The district is enough to deliver; the city is filled in from it (fill_city).
				unset( $b['billing_city'] );
				$b['billing_state']['class'] = array( 'form-row-wide', 'address-field' );
			}
			// Only where the country does not need one (e.g. Bangladesh); shipping zones may use it elsewhere.
			if ( isset( $locale[ $country ]['postcode']['required'] ) && ! $locale[ $country ]['postcode']['required'] ) {
				unset( $b['billing_postcode'] );
			}
		}

		$email = (string) Cartlum_Settings::get( 'checkout_email' );
		if ( 'hidden' === $email ) {
			unset( $b['billing_email'] );
		} elseif ( isset( $b['billing_email'] ) ) {
			$b['billing_email']['label']    = __( 'Email', 'cartlum' );
			$b['billing_email']['required'] = 'required' === $email;
			$b['billing_email']['priority'] = 60;
			$b['billing_email']['class']    = array( 'form-row-wide' );
		}

		if ( self::is_bd() && isset( $b['billing_state'] ) ) {
			$b['billing_state']['label'] = __( 'District', 'cartlum' );
		}
		$fields['billing'] = $b;
		if ( 'yes' !== Cartlum_Settings::get( 'checkout_notes' ) ) {
			unset( $fields['order']['order_comments'] );
		} elseif ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['label']       = __( 'Order note', 'cartlum' );
			$fields['order']['order_comments']['placeholder'] = __( 'Anything we should know for delivery?', 'cartlum' );
		}
		return $fields;
	}

	/**
	 * Cleans submitted data before WooCommerce validates it.
	 *
	 * @param array $data Posted data.
	 * @return array
	 */
	public static function posted_data( $data ) {
		// The country field is not shown, so whatever arrives is replaced by the store's country.
		$data['billing_country'] = self::country();
		if ( ! empty( $data['billing_phone'] ) ) {
			$phone = Cartlum_Phone::to_ascii_digits( $data['billing_phone'] );
			$n     = Cartlum_Phone::normalize( $phone );
			// Bangladeshi couriers expect 01XXXXXXXXX, whatever the customer typed.
			if ( self::is_bd() && preg_match( '/^8801[3-9]\d{8}$/', $n ) ) {
				$phone = '0' . substr( $n, 3 );
			}
			$data['billing_phone'] = $phone;
			// WooCommerce copied the raw number into the shipping address before this filter ran.
			if ( array_key_exists( 'shipping_phone', $data ) ) {
				$data['shipping_phone'] = $phone;
			}
		}
		return $data;
	}

	/**
	 * A phone number that can actually be called.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		$phone = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		if ( '' === $phone ) {
			return; // WooCommerce already reports the empty required field.
		}
		$n  = Cartlum_Phone::normalize( $phone );
		$ok = self::is_bd() ? (bool) preg_match( '/^8801[3-9]\d{8}$/', $n ) : '' !== $n;
		if ( ! $ok ) {
			$errors->add(
				'billing_phone_validation',
				self::is_bd()
					? __( 'Please enter an 11-digit mobile number, e.g. 01712345678.', 'cartlum' )
					: __( 'Please enter a valid mobile number.', 'cartlum' ),
				array( 'id' => 'billing_phone' )
			);
		}
	}

	/**
	 * Without a city field, use the district's name, so couriers and order emails still get one.
	 *
	 * @param WC_Order $order Order being created.
	 */
	public static function fill_city( $order ) {
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( '' !== (string) $order->{"get_{$type}_city"}() ) {
				continue;
			}
			$country = (string) $order->{"get_{$type}_country"}();
			$state   = (string) $order->{"get_{$type}_state"}();
			$states  = $country ? WC()->countries->get_states( $country ) : array();
			if ( $state && isset( $states[ $state ] ) ) {
				$order->{"set_{$type}_city"}( html_entity_decode( $states[ $state ], ENT_QUOTES, 'UTF-8' ) );
			}
		}
	}

	/**
	 * "Please enter your full name." rather than "Billing Full name is a required field." -- there
	 * is only one address on this form.
	 *
	 * @param string $notice Default notice.
	 * @param string $label  Field label, already prefixed "Billing".
	 * @param string $key    Field key.
	 * @return string
	 */
	public static function required_notice( $notice, $label, $key = '' ) {
		foreach ( WC()->checkout()->get_checkout_fields() as $set ) {
			if ( isset( $set[ $key ]['label'] ) ) {
				$label = $set[ $key ]['label'];
				break;
			}
		}
		/* translators: %s: field name, e.g. "full name" */
		return sprintf( __( 'Please enter your %s.', 'cartlum' ), '<strong>' . esc_html( wc_strtolower( wp_strip_all_tags( $label ) ) ) . '</strong>' );
	}

	/**
	 * Custom button text, if set.
	 *
	 * @param string $text Default.
	 * @return string
	 */
	public static function button_text( $text ) {
		$custom = trim( (string) Cartlum_Settings::get( 'checkout_button_text' ) );
		return '' !== $custom ? $custom : __( 'Place order', 'cartlum' );
	}

	/**
	 * Checkout styles, plus WooCommerce's classic checkout scripts: WooCommerce does not load them
	 * when the checkout page is built with the Checkout block. WooCommerce registers them and adds
	 * their settings itself once they are enqueued.
	 */
	public static function enqueue() {
		if ( self::is_thanks_page() ) {
			wp_enqueue_style( 'cartlum-checkout', CARTLUM_URL . 'assets/css/checkout.css', array(), CARTLUM_VERSION );
			wp_add_inline_style( 'cartlum-checkout', self::accent_css( '.clx-page' ) );
			wp_enqueue_script( 'cartlum-checkout', CARTLUM_URL . 'assets/js/checkout.js', array( 'jquery' ), CARTLUM_VERSION, true );
			return;
		}
		if ( ! self::is_form_page() ) {
			return;
		}
		foreach ( array( 'selectWoo', 'wc-country-select', 'wc-address-i18n', 'wc-checkout' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		}
		wp_enqueue_style( 'cartlum-checkout', CARTLUM_URL . 'assets/css/checkout.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-checkout', self::accent_css( '.clx-page' ) );
		wp_enqueue_script( 'cartlum-checkout', CARTLUM_URL . 'assets/js/checkout.js', array( 'jquery', 'wc-checkout' ), CARTLUM_VERSION, true );
		wp_localize_script( 'cartlum-checkout', 'cartlumCheckout', array( 'couponError' => __( 'The code could not be checked. Please try again.', 'cartlum' ) ) );
	}

	/**
	 * The store's button colour, with text that stays readable on it, as CSS variables.
	 *
	 * @param string $selector Where the variables apply.
	 * @return string
	 */
	public static function accent_css( $selector ) {
		$accent = sanitize_hex_color( (string) Cartlum_Settings::get( 'checkout_accent' ) );
		$accent = $accent ? $accent : '#1f2937';
		return $selector . '{--clx-accent:' . $accent . ';--clx-on-accent:' . self::ink_for( $accent ) . ';}';
	}

	/**
	 * White or near-black, whichever reads better on a colour (WCAG contrast).
	 *
	 * @param string $color Hex colour.
	 * @return string
	 */
	public static function ink_for( $color ) {
		$hex = ltrim( (string) $color, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$lin = array();
		foreach ( str_split( $hex, 2 ) as $c ) {
			$v     = hexdec( $c ) / 255;
			$lin[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		$lum = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
		return ( 1.05 / ( $lum + 0.05 ) ) >= ( ( $lum + 0.05 ) / 0.0556 ) ? '#ffffff' : '#111111';
	}

	/**
	 * Scopes the styles.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$classes[] = 'cartlum-checkout';
		}
		return $classes;
	}
}
