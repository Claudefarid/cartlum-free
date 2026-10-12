<?php
/**
 * Settings screen.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Admin_Settings.
 */
class Cartlum_Admin_Settings {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cartlum_save_settings', array( __CLASS__, 'save' ) );
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	public static function tabs() {
		$tabs = array(
			'general'    => __( 'General', 'cartlum' ),
			'checkout'   => __( 'Checkout', 'cartlum' ),
			'product'    => __( 'Product page', 'cartlum' ),
			'popup'      => __( 'Popup', 'cartlum' ),
			'account'    => __( 'Account', 'cartlum' ),
			'orders'     => __( 'Orders', 'cartlum' ),
			'protection' => __( 'Protection', 'cartlum' ),
			'automation' => __( 'Automation', 'cartlum' ),
			'sms'        => __( 'SMS', 'cartlum' ),
			'whatsapp' => 'WhatsApp',
			'email'    => __( 'Email', 'cartlum' ),
			'coupons'  => __( 'Coupons', 'cartlum' ),
			'webhooks' => __( 'Webhooks', 'cartlum' ),
			'privacy'  => __( 'Privacy', 'cartlum' ),
		);

		/**
		 * Adds settings tabs (Cartlum Pro).
		 *
		 * @param array $tabs slug => label.
		 */
		return apply_filters( 'cartlum_settings_tabs', $tabs );
	}

	/**
	 * The admin menu: each page does one job and owns the settings tabs for it. A page with a
	 * "view" opens on a list (carts, risky orders) and shows its tabs beside it.
	 *
	 * @return array slug => { label, tabs: string[], view: string|'' }
	 */
	public static function sections() {
		$sections = array(
			'cartlum-carts'    => array(
				'label' => __( 'Abandoned Carts', 'cartlum' ),
				'tabs'  => array( 'popup' ),
				'view'  => __( 'Carts', 'cartlum' ),
			),
			'cartlum-fraud'    => array(
				'label' => __( 'Fake Orders', 'cartlum' ),
				'tabs'  => array( 'protection', 'courier', 'advance' ),
				'view'  => __( 'Risky orders', 'cartlum' ),
			),
			'cartlum-messages' => array(
				'label' => __( 'Messages', 'cartlum' ),
				'tabs'  => array( 'automation', 'sms', 'whatsapp', 'email', 'orders', 'coupons', 'winback', 'whatsapp_api' ),
				'view'  => '',
			),
			'cartlum-ai'       => array(
				'label' => __( 'AI Agent', 'cartlum' ),
				'tabs'  => array( 'ai_call', 'ai' ),
				'view'  => '',
			),
			'cartlum-checkout' => array(
				'label' => __( 'Checkout', 'cartlum' ),
				'tabs'  => array( 'checkout', 'product', 'account', 'bundles' ),
				'view'  => '',
			),
			'cartlum-facebook' => array(
				'label' => 'Facebook',
				'tabs'  => array( 'facebook' ),
				'view'  => '',
			),
			'cartlum-settings' => array(
				'label' => __( 'Settings', 'cartlum' ),
				'tabs'  => array( 'general', 'webhooks', 'privacy', 'licence' ),
				'view'  => '',
			),
		);
		// Cartlum Pro before 0.6.1 loads the bundle editor only on the Settings page.
		if ( defined( 'CARTLUM_PRO_VERSION' ) && version_compare( CARTLUM_PRO_VERSION, '0.6.1', '<' ) ) {
			$sections['cartlum-checkout']['tabs'] = array_values( array_diff( $sections['cartlum-checkout']['tabs'], array( 'bundles' ) ) );
			$sections['cartlum-settings']['tabs'][] = 'bundles';
		}

		/**
		 * Filters the admin pages and which settings tabs each one shows.
		 *
		 * @param array $sections slug => { label, tabs, view }.
		 */
		$sections = apply_filters( 'cartlum_admin_sections', $sections );

		// Only tabs that exist; any other tab (from an add-on) goes to Settings.
		$all    = self::tabs();
		$placed = array();
		foreach ( $sections as $slug => $section ) {
			$sections[ $slug ]['tabs'] = array_values(
				array_filter(
					(array) $section['tabs'],
					function ( $tab ) use ( $all ) {
						return isset( $all[ $tab ] );
					}
				)
			);
			$placed = array_merge( $placed, $sections[ $slug ]['tabs'] );
		}
		foreach ( array_keys( $all ) as $tab ) {
			if ( ! in_array( $tab, $placed, true ) ) {
				$sections['cartlum-settings']['tabs'][] = $tab;
			}
		}
		return $sections;
	}

	/**
	 * The page that shows a settings tab.
	 *
	 * @param string $tab Tab.
	 * @return string Page slug.
	 */
	public static function page_for_tab( $tab ) {
		foreach ( self::sections() as $slug => $section ) {
			if ( in_array( $tab, $section['tabs'], true ) ) {
				return $slug;
			}
		}
		return 'cartlum-settings';
	}

	/**
	 * Old links (Settings → a tab that now lives on another page) go to the new page.
	 */
	public static function redirect_old_links() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( '' !== $tab && 'cartlum-settings' !== self::page_for_tab( $tab ) ) {
			wp_safe_redirect( Cartlum_Admin::url( 'cartlum-settings', array( 'tab' => $tab ) ) );
			exit;
		}
	}

	/**
	 * The tab links at the top of a page.
	 *
	 * @param string $slug    Page.
	 * @param string $current Current tab, '' for the page's list.
	 */
	public static function nav( $slug, $current ) {
		$sections = self::sections();
		$section  = $sections[ $slug ] ?? null;
		if ( ! $section ) {
			return;
		}
		$links = array();
		if ( '' !== $section['view'] ) {
			$links[''] = $section['view'];
		}
		$all = self::tabs();
		foreach ( $section['tabs'] as $tab ) {
			$links[ $tab ] = $all[ $tab ];
		}
		if ( count( $links ) < 2 ) {
			return;
		}
		echo '<nav class="nav-tab-wrapper cl-nav">';
		foreach ( $links as $tab => $label ) {
			$url = Cartlum_Admin::url( $slug, '' !== $tab ? array( 'tab' => $tab ) : array() );
			printf( '<a class="nav-tab%1$s" href="%2$s"%3$s>%4$s</a>', $tab === $current ? ' nav-tab-active' : '', esc_url( $url ), $tab === $current ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}

	/**
	 * Field definitions per tab: key => { label, type, help, options, min, max }.
	 *
	 * @param string $tab Tab.
	 * @return array
	 */
	public static function fields( $tab ) {
		/**
		 * Field definitions for a settings tab, in the same shape as Cartlum's own. Fields added
		 * here are rendered and sanitized by Cartlum and stored in the main settings option.
		 *
		 * @param array  $fields Fields.
		 * @param string $tab    Tab.
		 */
		return apply_filters( 'cartlum_settings_fields', self::core_fields( $tab ), $tab );
	}

	/**
	 * Cartlum's own fields for a tab.
	 *
	 * @param string $tab Tab.
	 * @return array
	 */
	private static function core_fields( $tab ) {
		switch ( $tab ) {
			case 'general':
				return array(
					'enabled'             => array(
						'label' => __( 'Capture carts', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Save the cart as soon as a customer types a phone number or email at checkout.', 'cartlum' ),
					),
					'abandon_after'       => array(
						'label' => __( 'Abandoned after (minutes)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 5,
						'max'   => 1440,
						'help'  => __( 'How long a cart sits untouched before it counts as abandoned.', 'cartlum' ),
					),
					'lost_after'          => array(
						'label' => __( 'Give up after (days)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 1,
						'max'   => 90,
						'help'  => __( 'Abandoned carts older than this are closed as lost.', 'cartlum' ),
					),
					'country_code'        => array(
						'label' => __( 'Country calling code', 'cartlum' ),
						'type'  => 'text',
						/* translators: %s: calling code */
						'help'  => sprintf( __( 'Used for numbers typed without one, e.g. 01712345678. Empty = your store country (%s).', 'cartlum' ), '+' . Cartlum_Phone::default_calling_code() ),
					),
					'customer_language'   => array(
						'label'   => __( 'Language for customers', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							''      => __( 'Site language', 'cartlum' ),
							'bn_BD' => 'বাংলা (Bangla)',
						),
						'help'    => __( 'What shoppers see from Cartlum — checkout, thank-you page, login, buttons, tracking — and the default SMS texts. Your admin screens keep the site language.', 'cartlum' ),
					),
					'capture_notice'      => array(
						'label' => __( 'Show a notice at checkout', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Tells customers their details are saved before they order. Recommended, and required in some countries.', 'cartlum' ),
					),
					'capture_notice_text' => array(
						'label' => __( 'Notice text', 'cartlum' ),
						'type'  => 'text',
					),
				);

			case 'checkout':
				$help = __( 'A short checkout built on WooCommerce’s own: mobile number first, then name, address and district. Payment methods, delivery charges, coupons and order plugins keep working. For stores that deliver within their own country.', 'cartlum' );
				if ( defined( 'CARTFLOWS_VER' ) ) {
					$help .= ' ' . __( 'CartFlows is active: its global checkout is set aside for visitors who see this checkout. CartFlows funnel pages keep their own checkout.', 'cartlum' );
				}
				return array(
					'checkout_enabled'     => array(
						'label' => __( 'Use the phone-first checkout', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => $help,
					),
					'checkout_preview'     => array(
						'label' => __( 'Preview', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Only shop managers and administrators see the new checkout; customers keep the current one. Place a test order, then turn this off to show it to everyone.', 'cartlum' ),
					),
					'checkout_email'       => array(
						'label'   => __( 'Email field', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'optional' => __( 'Optional', 'cartlum' ),
							'required' => __( 'Required', 'cartlum' ),
							'hidden'   => __( 'Hidden', 'cartlum' ),
						),
						'help'    => __( 'Without an email, the customer gets no order emails from WooCommerce.', 'cartlum' ),
					),
					'checkout_notes'       => array(
						'label' => __( 'Order note field', 'cartlum' ),
						'type'  => 'checkbox',
					),
					'checkout_button_text' => array(
						'label' => __( 'Order button text', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = WooCommerce’s default (“Place order”).', 'cartlum' ),
					),
					'checkout_note'        => array(
						'label' => __( 'Note above the order button', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'For example your delivery charge and refund policy, in one or two languages. Write [link text](https://…) for a link. Shown on this checkout, and on WooCommerce’s classic checkout when this one is off (not the block checkout). Empty = none.', 'cartlum' ),
					),
					'checkout_accent'      => array(
						'label' => __( 'Button colour', 'cartlum' ),
						'type'  => 'color',
						'help'  => __( 'Your brand colour, used for the order button and selected options. The text on it turns white or black to stay readable.', 'cartlum' ),
					),
					'checkout_logo'        => array(
						'label' => __( 'Logo image address', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Shown at the top of the checkout. Empty = your theme’s logo, or the site name.', 'cartlum' ),
					),
					'thankyou_message'     => array(
						'label' => __( 'Thank-you message', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Shown under “Your order is confirmed”. {phone} becomes the customer’s number. Empty = “We have received your order and will contact you on {phone} about delivery.”', 'cartlum' ),
					),
				);

			case 'webhooks':
				$fields = array(
					'webhook_url' => array(
						'label' => __( 'Webhook URL', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Cartlum posts cart events here as JSON — paste the URL from a Zapier “Catch Hook”, a Make or Pabbly webhook, or your own system. Empty = off.', 'cartlum' ),
					),
				);
				foreach ( Cartlum_Webhooks::events() as $event => $label ) {
					$fields[ 'webhook_on_' . $event ] = array(
						'label' => $label,
						'type'  => 'checkbox',
					);
				}
				return $fields;
			case 'popup':
				return array(
					'popup_enabled' => array(
						'label' => __( 'Save-cart popup', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Asks shoppers who have something in the cart for their mobile number, so the cart is saved even if they never reach the checkout. Never shown on the checkout, account or thank-you pages, or to shoppers whose number you already have. After “No thanks” it stays away for 3 days.', 'cartlum' ),
					),
					'popup_trigger' => array(
						'label'   => __( 'Show', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'exit'        => __( 'When they are about to leave', 'cartlum' ),
							'add_to_cart' => __( 'Right after “Add to cart”', 'cartlum' ),
							'both'        => __( 'Both (once)', 'cartlum' ),
						),
						'help'    => __( 'On computers “about to leave” means the mouse heads out of the window; on phones, a quick scroll back to the top. On phones “Right after Add to cart” catches more shoppers.', 'cartlum' ),
					),
					'popup_title'   => array(
						'label' => __( 'Heading', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “Save your cart?”', 'cartlum' ),
					),
					'popup_text'    => array(
						'label' => __( 'Text', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Empty = “Leave your mobile number and we will keep your cart for you. We may send you a reminder by SMS.”', 'cartlum' ),
					),
					'popup_button'  => array(
						'label' => __( 'Button', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “Save my cart”', 'cartlum' ),
					),
				);
			case 'product':
				return array(
					'buy_now_enabled'      => array(
						'label' => __( 'Buy now button', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'A “Buy now” button next to “Add to cart” that adds the product, with the chosen options and quantity, and goes straight to checkout. Works with any checkout.', 'cartlum' ),
					),
					'buy_now_text'         => array(
						'label' => __( 'Buy now text', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “Buy now”.', 'cartlum' ),
					),
					'buy_now_mode'         => array(
						'label'   => __( 'Buy now checks out', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'add'  => __( 'This product plus anything already in the cart', 'cartlum' ),
							'only' => __( 'Only this product (the cart is emptied first)', 'cartlum' ),
						),
					),
					'buy_now_loop'         => array(
						'label' => __( 'Buy now on shop pages', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Also under each product in the shop and category pages (products without options only).', 'cartlum' ),
					),
					'whatsapp_button'      => array(
						'label' => __( 'WhatsApp button', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( '“Order on WhatsApp” under the buy buttons: opens a chat with your number, with the product, chosen options, quantity and price already written.', 'cartlum' ),
					),
					'whatsapp_number'      => array(
						'label' => __( 'Your WhatsApp number', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'e.g. 01712345678 or +8801712345678.', 'cartlum' ),
					),
					'whatsapp_button_text' => array(
						'label' => __( 'WhatsApp button text', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “Order on WhatsApp”.', 'cartlum' ),
					),
					'whatsapp_order_message' => array(
						'label' => __( 'WhatsApp message', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Use {product}, {quantity}, {price} and {link}. Empty = a short English message.', 'cartlum' ),
					),
					'call_button'          => array(
						'label' => __( 'Call button', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( '“Call to order”: one tap dials your number on a phone.', 'cartlum' ),
					),
					'call_number'          => array(
						'label' => __( 'Your phone number', 'cartlum' ),
						'type'  => 'text',
					),
					'call_button_text'     => array(
						'label' => __( 'Call button text', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “Call to order”. To place the buttons yourself in a page builder, use the shortcode [cartlum_contact_buttons].', 'cartlum' ),
					),
					'product_button_color' => array(
						'label' => __( 'Product button colour', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Add to cart, Buy now, WhatsApp and Call sit together as a 2 × 2 group. Enter a colour code, e.g. #2B2620, to give all four that colour; leave empty to keep your theme’s Add to cart style.', 'cartlum' ),
					),
				);

			case 'account':
				$help = __( 'Customers log in to My Account with their mobile number and a 6-digit code sent by SMS — no password. The first login creates their account, and orders placed with that number appear in it. Staff accounts must still use their password.', 'cartlum' );
				if ( ! Cartlum_SMS::gateway() ) {
					$help .= ' ' . __( 'Set up an SMS gateway on the SMS tab first; without one no code can be sent.', 'cartlum' );
				}
				return array(
					'phone_login'             => array(
						'label' => __( 'Log in with phone number', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => $help,
					),
					'otp_message'             => array(
						'label' => __( 'Code message', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'The SMS with the code. Use {code} and {store}. Empty = “{code} is your {store} login code. It expires in 10 minutes.”', 'cartlum' ),
					),
					'phone_login_daily_limit' => array(
						'label' => __( 'Codes per day', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 100000,
						'help'  => __( 'Most SMS codes sent per day — login and checkout codes, all numbers together. Protects your SMS balance. 0 = no limit.', 'cartlum' ),
					),
					'account_cart_link'       => array(
						'label' => __( 'Cart link in My Account', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Adds “Cart” to the My Account menu, after Orders. Works with or without phone login.', 'cartlum' ),
					),
				);

			case 'orders':
				$defaults = Cartlum_Order_SMS::default_texts();
				$sms_help = __( 'Text messages about orders, each sent once. Use {name}, {customer} (full name), {order}, {total}, {amount} (plain number: write “Tk {amount}” to keep an SMS free of Bangla characters), {items}, {items_detail} (one line per item: SKU or name, quantity, colour), {phone}, {address}, {status}, {store}, {track_link}, {courier} and {consignment}. Leave a text empty to use the default shown.', 'cartlum' );
				if ( ! Cartlum_SMS::gateway() ) {
					$sms_help .= ' ' . __( 'Set up an SMS gateway on the SMS tab first.', 'cartlum' );
				}
				$fields = array(
					'order_sms_enabled' => array(
						'label' => __( 'Order SMS', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => $sms_help . ' ' . __( 'If another plugin already texts customers about orders, turn that off, or customers get two messages.', 'cartlum' ),
					),
				);
				$events = array(
					'placed'    => array( __( 'Order received', 'cartlum' ), __( 'When the order goes through.', 'cartlum' ) ),
					'shipped'   => array( __( 'Handed to the courier', 'cartlum' ), __( 'When a Steadfast parcel is created for the order.', 'cartlum' ) ),
					'delivered' => array( __( 'Delivered', 'cartlum' ), __( 'When the order is marked completed, or Steadfast reports it delivered.', 'cartlum' ) ),
					'cancelled' => array( __( 'Cancelled', 'cartlum' ), __( 'When the order is cancelled. Fake orders cost an SMS too.', 'cartlum' ) ),
					'admin'     => array( __( 'New order SMS to you', 'cartlum' ), __( 'A message to your own number for every new order.', 'cartlum' ) ),
				);
				foreach ( $events as $event => $label ) {
					$fields[ 'order_sms_' . $event ] = array(
						'label' => $label[0],
						'type'  => 'checkbox',
						'help'  => $label[1],
					);
					if ( 'admin' === $event ) {
						$fields['order_sms_admin_phone'] = array(
							'label' => __( 'Your numbers', 'cartlum' ),
							'type'  => 'text',
							'help'  => __( 'One or more, separated by commas, e.g. 01712345678, 01812345678. Every number gets each message for the store.', 'cartlum' ),
						);
					}
					$fields[ 'order_sms_' . $event . '_text' ] = array(
						/* translators: %s: kind of message */
						'label' => sprintf( __( '“%s” text', 'cartlum' ), $label[0] ),
						'type'  => 'textarea',
						/* translators: %s: default message */
						'help'  => sprintf( __( 'Default: %s', 'cartlum' ), $defaults[ $event ] ),
					);
				}
				$statuses = array();
				foreach ( wc_get_order_statuses() as $slug => $label ) {
					$statuses[ 0 === strpos( $slug, 'wc-' ) ? substr( $slug, 3 ) : $slug ] = $label;
				}
				unset( $statuses['checkout-draft'] );
				$fields['order_sms_status_alert']      = array(
					'label'   => __( 'Tell the store when an order becomes', 'cartlum' ),
					'type'    => 'checkboxes',
					'options' => $statuses,
					'help'    => __( 'An SMS to your numbers above each time an order changes to one of these statuses, for example Cancelled. Customers get nothing from this.', 'cartlum' ),
				);
				$fields['order_sms_status_alert_text'] = array(
					'label' => __( 'Store status SMS text', 'cartlum' ),
					'type'  => 'textarea',
					/* translators: %s: default message */
					'help'  => sprintf( __( 'Default: %s', 'cartlum' ), Cartlum_Order_SMS::status_alert_default() ),
				);
				$fields['order_tracking']       = array(
					'label' => __( 'Order tracking in My Account', 'cartlum' ),
					'type'  => 'checkbox',
					'help'  => __( 'On each order’s page: placed → processing → with the courier → delivered, and the courier’s consignment number and status (Steadfast is read automatically).', 'cartlum' ),
				);
				$fields['courier_tracking_url'] = array(
					'label' => __( 'Courier tracking link', 'cartlum' ),
					'type'  => 'text',
					'help'  => __( 'Optional: your courier’s tracking page address with {consignment} where the consignment number goes. With it, customers get a “Track parcel” button.', 'cartlum' ),
				);
				return $fields;

			case 'protection':
				return array(
					'protect_enabled'           => array(
						'label' => __( 'Fake-order protection', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Stops blocked numbers, repeat orders and bots at checkout, and shows staff each number’s order history on the Orders screen. Shop managers placing orders are never stopped.', 'cartlum' ),
					),
					'protect_duplicate_minutes' => array(
						'label' => __( 'Repeat order wait (minutes)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 1440,
						'help'  => __( 'A number that ordered within this time cannot order again online; it is told its earlier order number and asked to call. 0 = off.', 'cartlum' ),
					),
					'protect_daily_orders'      => array(
						'label' => __( 'Orders per number per day', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 100,
						'help'  => __( 'Cancelled and failed orders do not count. 0 = no limit.', 'cartlum' ),
					),
					'protect_ip_daily_orders'   => array(
						'label' => __( 'Orders per IP address per day', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 1000,
						'help'  => __( 'Stops one device ordering under many numbers. Many mobile customers can share one address, so keep this generous. 0 = no limit.', 'cartlum' ),
					),
					'cancel_reasons'            => array(
						'label' => __( 'Cancellation reasons', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Pick why an order was cancelled on the order screen; see the reasons, and the cancelled orders still missing one, on Fake Orders.', 'cartlum' ),
					),
					'protect_blocklist'         => array(
						'label' => __( 'Blocked numbers, IP addresses and devices', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'One per line. Any way of writing a number works (01712345678, +8801712345678). IP addresses can be single (103.4.5.6) or ranges (103.4.5.0/24). Devices are added from an order’s screen, as are numbers and IP addresses.', 'cartlum' ),
					),
					'protect_block_message'     => array(
						'label' => __( 'Message to blocked customers', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Empty = “We cannot take this order online. Please call us to order.”', 'cartlum' ),
					),
					'protect_risky_cancelled'   => array(
						'label' => __( '“Often cancels” after', 'cartlum' ),
						'type'  => 'number',
						'min'   => 1,
						'max'   => 50,
						'help'  => __( 'Cancelled orders before a number is marked “Often cancels” (when it also cancelled at least as many as it completed). Its new orders get a note to call before shipping.', 'cartlum' ),
					),
					'protect_otp'               => array(
						'label' => __( 'SMS code for risky orders', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Cash-on-delivery customers confirm their number with a code before ordering when the number often cancels, a new number orders a large amount, or the connection already ordered twice today. Everyone else orders as before. Works on classic checkouts (also CartFlows and Cartlum’s), not the block checkout. Needs an SMS gateway.', 'cartlum' ),
					),
					'protect_otp_amount'        => array(
						'label' => __( 'Code for new numbers from', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 10000000,
						'help'  => __( 'A number with no completed orders yet gets a code when the order is at least this much. 0 = not for this reason.', 'cartlum' ),
					),
					'protect_otp_message'       => array(
						'label' => __( 'Order code message', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Use {code} and {store}. Empty = “{code} is your {store} order code. It expires in 10 minutes.”', 'cartlum' ),
					),
				);

			case 'automation':
				$hours = array();
				for ( $h = 0; $h < 24; $h++ ) {
					$hours[ $h ] = sprintf( '%02d:00', $h );
				}
				return array(
					'flow_enabled'         => array(
						'label' => __( 'Automatic reminder', 'cartlum' ),
						'type'  => 'checkbox',
						'help'  => __( 'Send a reminder automatically after a cart is abandoned. It stops as soon as the customer orders, unsubscribes, or you stop it on the cart.', 'cartlum' ),
					),
					'flow_delay'           => array(
						'label' => __( 'Send after (minutes)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 5,
						'max'   => 10080,
						'help'  => __( 'Counted from the moment the cart is marked abandoned.', 'cartlum' ),
					),
					'flow_channel'         => array(
						'label'   => __( 'Send by', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'sms'   => __( 'SMS', 'cartlum' ),
							'email' => __( 'Email (uses the Email tab’s message)', 'cartlum' ),
						),
					),
					'flow_sms_template'    => array(
						'label' => __( 'SMS message', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Add {coupon} to include a one-time coupon. Without it no coupon is created, which keeps your margin for customers who would have bought anyway.', 'cartlum' ),
					),
					'quiet_start'          => array(
						'label'   => __( 'Quiet hours from', 'cartlum' ),
						'type'    => 'select',
						'options' => $hours,
					),
					'quiet_end'            => array(
						'label'   => __( 'Quiet hours until', 'cartlum' ),
						'type'    => 'select',
						'options' => $hours,
						/* translators: %s: timezone name */
						'help'    => sprintf( __( 'No automatic messages in this window; they wait until it ends. Store time zone: %s. Same hour in both = no quiet hours.', 'cartlum' ), wp_timezone_string() ),
					),
					'flow_daily_sms_limit' => array(
						'label' => __( 'Daily SMS limit', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 100000,
						'help'  => __( 'Most automatic SMS per day. Protects your SMS balance. 0 = no limit.', 'cartlum' ),
					),
					'flow_test_phone'      => array(
						'label' => __( 'Test mode: send to', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'While a number is set here, every automatic SMS goes to it instead of the customer — use it to check the flow before going live. Leave empty to send to customers.', 'cartlum' ),
					),
				);

			case 'sms':
				$fields = array(
					'sms_gateway' => array(
						'label'   => __( 'Gateway', 'cartlum' ),
						'type'    => 'select',
						'options' => array( '' => __( '— None —', 'cartlum' ) ) + array_map(
							function ( $g ) {
								return $g->label();
							},
							Cartlum_SMS::gateways()
						),
					),
				);
				foreach ( Cartlum_SMS::gateways() as $id => $gateway ) {
					foreach ( $gateway->fields() as $key => $field ) {
						$field['gateway'] = $id;
						$fields[ $key ]   = $field;
					}
				}
				$fields['sms_template'] = array(
					'label' => __( 'Default message', 'cartlum' ),
					'type'  => 'textarea',
					'help'  => __( 'Keep it short: one SMS is 160 letters in English, 70 in Bangla and other non-Latin scripts.', 'cartlum' ),
				);
				return $fields;

			case 'whatsapp':
				return array(
					'whatsapp_template' => array(
						'label' => __( 'Default message', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Opens in WhatsApp for you to send.', 'cartlum' ),
					),
				);

			case 'email':
				return array(
					'email_design'   => array(
						'label'   => __( 'Design', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'designed'    => __( 'Cartlum design — logo, product photos and a button', 'cartlum' ),
							'woocommerce' => __( 'Plain — your WooCommerce email style', 'cartlum' ),
						),
						'help'    => __( 'The design uses the logo and button colour from the Checkout tab (or your theme’s logo). In the message, a line with only {items} becomes the product list and a line with only {cart_link} becomes the button.', 'cartlum' ),
					),
					'email_subject'  => array(
						'label' => __( 'Subject', 'cartlum' ),
						'type'  => 'text',
					),
					'email_template' => array(
						'label' => __( 'Message', 'cartlum' ),
						'type'  => 'textarea',
						'help'  => __( 'Plain text; links become clickable. An unsubscribe line is added automatically.', 'cartlum' ),
					),
				);

			case 'coupons':
				return array(
					'coupon_type'        => array(
						'label'   => __( 'Discount type', 'cartlum' ),
						'type'    => 'select',
						'options' => array(
							'fixed_cart' => __( 'Fixed amount', 'cartlum' ),
							'percent'    => __( 'Percentage', 'cartlum' ),
						),
					),
					'coupon_amount'      => array(
						'label' => __( 'Amount', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 100000,
					),
					'coupon_expiry_days' => array(
						'label' => __( 'Valid for (days)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 1,
						'max'   => 365,
					),
					'coupon_prefix'      => array(
						'label' => __( 'Code prefix', 'cartlum' ),
						'type'  => 'text',
						'help'  => __( 'Codes look like PREFIX-7KQ2XP. Each works once, for one cart.', 'cartlum' ),
					),
				);

			case 'privacy':
				return array(
					'retention_days'      => array(
						'label' => __( 'Delete carts after (days)', 'cartlum' ),
						'type'  => 'number',
						'min'   => 0,
						'max'   => 3650,
						'help'  => __( '0 keeps them forever.', 'cartlum' ),
					),
					'delete_on_uninstall' => array(
						'label' => __( 'Delete all data on uninstall', 'cartlum' ),
						'type'  => 'checkbox',
					),
				);
		}
		return array();
	}

	/**
	 * The current admin page slug.
	 *
	 * @return string
	 */
	private static function current_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'cartlum-settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( self::sections()[ $page ] ) ? $page : 'cartlum-settings';
	}

	/**
	 * Current tab on a page: '' means the page's list.
	 *
	 * @param string $slug Page.
	 * @return string
	 */
	public static function current_tab( $slug = '' ) {
		$slug    = '' !== $slug ? $slug : self::current_page();
		$section = self::sections()[ $slug ];
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $tab, $section['tabs'], true ) ) {
			return $tab;
		}
		return '' !== $section['view'] ? '' : (string) ( $section['tabs'][0] ?? '' );
	}

	/**
	 * Renders a menu page: its list, or one of its settings tabs.
	 */
	public static function page() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			return;
		}
		$slug = self::current_page();
		$tab  = self::current_tab( $slug );
		if ( '' === $tab ) {
			if ( 'cartlum-carts' === $slug ) {
				Cartlum_Admin::page_carts();
			} elseif ( 'cartlum-fraud' === $slug ) {
				Cartlum_Admin_Fraud::page();
			}
			return;
		}
		$values = Cartlum_Settings::all();
		?>
		<div class="wrap cartlum">
			<div class="cl-head"><h1><?php echo esc_html( self::sections()[ $slug ]['label'] ); ?></h1></div>
			<?php self::nav( $slug, $tab ); ?>
			<?php
			/**
			 * Above a settings tab's form (Cartlum Pro: "enter your licence key" on locked tabs).
			 *
			 * @param string $tab Tab.
			 */
			do_action( 'cartlum_settings_before_fields', $tab );
			?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cartlum_save_settings' ); ?>
				<input type="hidden" name="action" value="cartlum_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<table class="form-table" role="presentation">
					<?php foreach ( self::fields( $tab ) as $key => $field ) : ?>
						<tr<?php echo isset( $field['gateway'] ) ? ' data-cartlum-gateway="' . esc_attr( $field['gateway'] ) . '"' : ''; ?>>
							<th scope="row"><label for="cartlum-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
							<td><?php self::render_field( $key, $field, $values[ $key ] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php
				/**
				 * Renders extra settings UI inside the form (Cartlum Pro: the step editor). Save it
				 * through the cartlum_settings_save filter.
				 *
				 * @param string $tab    Tab.
				 * @param array  $values Current settings.
				 */
				do_action( 'cartlum_settings_after_fields', $tab, $values );
				?>
				<?php if ( in_array( $tab, apply_filters( 'cartlum_settings_chip_tabs', array( 'automation', 'sms', 'whatsapp', 'email' ) ), true ) ) : ?>
					<div class="cl-chips">
						<span class="cl-chips__label"><?php esc_html_e( 'Insert into the message you are editing', 'cartlum' ); ?></span>
						<?php foreach ( Cartlum_Messages::placeholders() as $tag => $desc ) : ?>
							<button type="button" class="cl-chip" data-cartlum-insert="<?php echo esc_attr( $tag ); ?>" title="<?php echo esc_attr( $desc ); ?>"><?php echo esc_html( $tag ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>

			<?php if ( 'sms' === $tab && Cartlum_SMS::gateway() ) : ?>
				<h2><?php esc_html_e( 'Send a test SMS', 'cartlum' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cartlum_test_sms' ); ?>
					<input type="hidden" name="action" value="cartlum_test_sms">
					<input type="tel" name="test_phone" placeholder="<?php esc_attr_e( 'Your phone number', 'cartlum' ); ?>" required>
					<button class="button"><?php esc_html_e( 'Send test', 'cartlum' ); ?></button>
					<p class="cl-muted"><?php esc_html_e( 'Save your settings first — the test uses the saved ones.', 'cartlum' ); ?></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * One input.
	 *
	 * @param string $key   Setting key.
	 * @param array  $field Definition.
	 * @param mixed  $value Current value.
	 */
	private static function render_field( $key, array $field, $value ) {
		$id   = 'cartlum-' . $key;
		$name = 'cartlum[' . $key . ']';
		switch ( $field['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="yes" %3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( 'yes', $value, false ), esc_html( $field['help'] ?? '' ) );
				return;
			case 'select':
				printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), 'sms_gateway' === $key ? ' data-cartlum-gateway-select' : '' );
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $opt ), selected( (string) $opt, (string) $value, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%2$s" rows="5" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'checkboxes':
				echo '<fieldset id="' . esc_attr( $id ) . '" class="cl-checks">';
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>', esc_attr( $name ), esc_attr( $opt ), checked( in_array( (string) $opt, (array) $value, true ), true, false ), esc_html( $label ) );
				}
				echo '</fieldset>';
				break;
			case 'number':
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $field['min'] ?? 0 ), esc_attr( $field['max'] ?? '' ) );
				break;
			case 'color':
				printf( '<input type="color" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			case 'password':
				// Never print a saved secret back into the page; an empty box keeps the saved one.
				printf( '<input type="password" id="%1$s" name="%2$s" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s">', esc_attr( $id ), esc_attr( $name ), $value ? esc_attr__( 'Saved — leave empty to keep', 'cartlum' ) : '' );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
	}

	/**
	 * Saves one tab.
	 */
	public static function save() {
		if ( ! current_user_can( Cartlum_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'cartlum' ), 403 );
		}
		check_admin_referer( 'cartlum_save_settings' );

		$tab    = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'general';
		$input  = isset( $_POST['cartlum'] ) && is_array( $_POST['cartlum'] ) ? wp_unslash( $_POST['cartlum'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$values = get_option( Cartlum_Settings::OPTION, array() );
		$values = is_array( $values ) ? $values : array();

		foreach ( self::fields( $tab ) as $key => $field ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';
			switch ( $field['type'] ) {
				case 'checkbox':
					$values[ $key ] = 'yes' === $raw ? 'yes' : 'no';
					break;
				case 'number':
					$num            = is_numeric( $raw ) ? 0 + $raw : 0;
					$num            = max( $field['min'] ?? 0, $num );
					$values[ $key ] = isset( $field['max'] ) ? min( $field['max'], $num ) : $num;
					break;
				case 'select':
					$values[ $key ] = array_key_exists( $raw, $field['options'] ) ? (string) $raw : '';
					break;
				case 'textarea':
					$values[ $key ] = sanitize_textarea_field( $raw );
					break;
				case 'checkboxes':
					$values[ $key ] = array_values( array_intersect( array_map( 'strval', (array) $raw ), array_map( 'strval', array_keys( $field['options'] ) ) ) );
					break;
				case 'color':
					$values[ $key ] = (string) sanitize_hex_color( $raw );
					break;
				case 'password':
					if ( '' !== trim( (string) $raw ) ) {
						$values[ $key ] = sanitize_text_field( $raw );
					}
					break;
				default:
					// URLs keep their {placeholders}; esc_url_raw would mangle the braces.
					$values[ $key ] = sanitize_text_field( $raw );
			}
		}

		/**
		 * Sanitizes and stores extra settings posted from cartlum_settings_after_fields.
		 *
		 * @param array  $values Settings about to be saved.
		 * @param string $tab    Tab.
		 * @param array  $input  Raw posted cartlum[] values (unslashed, unsanitized).
		 */
		$values = apply_filters( 'cartlum_settings_save', $values, $tab, $input );

		Cartlum_Settings::save( $values );

		// Numbers that get SMS: saved as typed, but a mistyped one is pointed out straight away.
		$bad = array();
		foreach ( array( 'order_sms_admin_phone', 'flow_test_phone' ) as $key ) {
			if ( isset( $values[ $key ] ) && '' !== trim( (string) $values[ $key ] ) ) {
				foreach ( preg_split( '/[,;\s]+/', trim( (string) $values[ $key ] ) ) as $number ) {
					$reason = Cartlum_Phone::invalid_reason( $number );
					if ( '' !== $reason ) {
						$bad[] = $reason;
					}
				}
			}
		}
		$notice = $bad
			? Cartlum_Admin::notice_args( __( 'Settings saved, but please check:', 'cartlum' ) . ' ' . implode( ' ', $bad ), 'error' )
			: Cartlum_Admin::notice_args( __( 'Settings saved.', 'cartlum' ) );
		wp_safe_redirect( Cartlum_Admin::url( 'cartlum-settings', array_merge( array( 'tab' => $tab ), $notice ) ) );
		exit;
	}
}
