<?php
/**
 * One-tap reorder. A private link ({reorder_link} in order SMS, "Order again" in My Account) puts
 * an earlier order back in the cart and opens the checkout already filled in with that order's
 * name, phone and address; the customer only taps "Place order". Returning customers who are not
 * logged in also find the checkout filled in from their last order on the same phone or computer.
 *
 * Cash on delivery has no saved card to charge, so this is what "one-click" means here.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Reorder.
 */
class Cartlum_Reorder {

	const PARAM  = 'cartlum_reorder';
	const TOKEN  = '_cartlum_reorder_token';
	const COOKIE = 'cartlum_last_order';
	const DAYS   = 180; // How long a device remembers its last order.

	/**
	 * Billing fields copied from an order to the checkout.
	 */
	const FIELDS = array( 'first_name', 'last_name', 'phone', 'email', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );

	/**
	 * Hooks.
	 */
	public static function init() {
		// The link works whenever a store puts it in a message.
		add_filter( 'cartlum_order_sms_placeholders', array( __CLASS__, 'placeholder' ), 10, 3 );
		add_action( 'wp_loaded', array( __CLASS__, 'handle' ), 30 );
		if ( 'yes' !== Cartlum_Settings::get( 'reorder_enabled' ) ) {
			return;
		}
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'account_action' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'remember' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'remember' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prefill' ) );
	}

	/**
	 * {reorder_link} for order SMS texts. Made only when a text uses it.
	 *
	 * @param array    $values   Placeholders.
	 * @param WC_Order $order    Order.
	 * @param string   $template Text.
	 * @return array
	 */
	public static function placeholder( $values, $order, $template ) {
		$values['{reorder_link}'] = false !== strpos( (string) $template, '{reorder_link}' ) ? self::link( $order ) : '';
		return $values;
	}

	/**
	 * The order's private reorder link.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function link( $order ) {
		$token = (string) $order->get_meta( self::TOKEN );
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false );
			$order->update_meta_data( self::TOKEN, $token );
			$order->save_meta_data();
		}
		return add_query_arg( self::PARAM, $token, home_url( '/' ) );
	}

	/**
	 * "Order again" next to each order in My Account → Orders.
	 *
	 * @param array    $actions Actions.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public static function account_action( $actions, $order ) {
		if ( $order instanceof WC_Order && $order->get_items() ) {
			$actions['cartlum-reorder'] = array(
				'url'  => self::link( $order ),
				'name' => __( 'Order again', 'cartlum' ),
			);
		}
		return $actions;
	}

	/**
	 * Follows a reorder link: the order's items in the cart, its details in the checkout.
	 */
	public static function handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the private token in the link is what authorises.
		$token = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : '';
		if ( '' === $token || is_admin() || ! WC()->cart || Cartlum_Restore::is_preview_bot() ) {
			return;
		}
		$order = preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ? self::order_for( $token ) : null;
		if ( ! $order ) {
			wc_add_notice( __( 'This link no longer works. Please choose your products again.', 'cartlum' ), 'notice' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->cart->empty_cart();
		$skipped = 0;
		foreach ( $order->get_items() as $item ) {
			if ( ! self::add_item( $item ) ) {
				++$skipped;
			}
		}
		if ( $skipped ) {
			wc_add_notice( __( 'Some products from that order are no longer available.', 'cartlum' ), 'notice' );
		}
		self::fill_customer( $order );
		wp_safe_redirect( WC()->cart->is_empty() ? wc_get_page_permalink( 'shop' ) : wc_get_checkout_url() );
		exit;
	}

	/**
	 * The order a link belongs to.
	 *
	 * @param string $token Token.
	 * @return WC_Order|null
	 */
	private static function order_for( $token ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'type'       => 'shop_order',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one lookup when a customer opens their link.
					array(
						'key'   => self::TOKEN,
						'value' => $token,
					),
				),
			)
		);
		return $orders ? $orders[0] : null;
	}

	/**
	 * Puts one order line back in the cart, with its colour or size.
	 *
	 * @param WC_Order_Item_Product $item Order line.
	 * @return bool Whether it was added.
	 */
	private static function add_item( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return true; // Not a product line: nothing to add.
		}
		$product_id   = (int) $item->get_product_id();
		$variation_id = (int) $item->get_variation_id();
		$product      = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return false;
		}
		// The chosen options, as WooCommerce's own "Order again" reads them from the line.
		$variation = array();
		if ( $variation_id ) {
			foreach ( $item->get_meta_data() as $meta ) {
				if ( taxonomy_is_product_attribute( $meta->key ) || meta_is_product_attribute( $meta->key, $meta->value, $product_id ) ) {
					$variation[ 'attribute_' . $meta->key ] = $meta->value;
				}
			}
		}
		return (bool) WC()->cart->add_to_cart( $product_id, max( 1, (int) $item->get_quantity() ), $variation_id, $variation );
	}

	/**
	 * The checkout's name, phone and address from an order.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function fill_customer( $order ) {
		if ( ! WC()->customer ) {
			return;
		}
		foreach ( self::FIELDS as $field ) {
			$value = (string) $order->{'get_billing_' . $field}();
			if ( '' !== $value ) {
				WC()->customer->{'set_billing_' . $field}( $value );
			}
		}
		WC()->customer->save();
	}

	/**
	 * Remembers a new order on this device, for the next checkout.
	 *
	 * @param int|WC_Order $order Order or its id.
	 */
	public static function remember( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || is_user_logged_in() || headers_sent() ) {
			return; // Logged-in customers already get their saved address from WooCommerce.
		}
		wc_setcookie( self::COOKIE, $order->get_id() . '.' . self::sign( $order->get_id() ), time() + self::DAYS * DAY_IN_SECONDS, is_ssl(), true );
	}

	/**
	 * Signature for an order id in the cookie, so a visitor cannot load another customer's details
	 * by changing the number.
	 *
	 * @param int $order_id Order id.
	 * @return string
	 */
	private static function sign( $order_id ) {
		return substr( hash_hmac( 'sha256', 'cartlum-reorder|' . (int) $order_id, wp_salt( 'auth' ) ), 0, 24 );
	}

	/**
	 * Fills an empty checkout from the order this device remembers.
	 */
	public static function prefill() {
		if ( ! is_checkout() || is_wc_endpoint_url() || is_user_logged_in() || ! WC()->customer || empty( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}
		if ( '' !== WC()->customer->get_billing_phone() || '' !== WC()->customer->get_billing_first_name() ) {
			return; // Already filled in this visit: never overwrite what the customer typed.
		}
		$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		if ( 2 !== count( $parts ) || ! hash_equals( self::sign( (int) $parts[0] ), $parts[1] ) ) {
			return;
		}
		$order = wc_get_order( (int) $parts[0] );
		if ( $order && '' !== $order->get_billing_phone() ) {
			self::fill_customer( $order );
		}
	}
}
