<?php
/**
 * Selling through AI assistants. People ask ChatGPT, Gemini, Claude, Grok, Perplexity or Kimi what
 * to buy; this makes the store easy for them to read, recommend and send buyers to:
 *
 * - /llms.txt: the shop in plain words for AI assistants (what it sells, how ordering, delivery,
 *   cash on delivery and returns work, and its products with prices and order links);
 * - a product feed (JSON) with prices in the store's currency, stock, colours and order links;
 * - richer product data in each product page (delivery time, returns, cash on delivery);
 * - an order link (?cartlum_order=ID) that opens the checkout with that product in the cart;
 * - orders that came from an AI assistant are marked and counted in Reports.
 *
 * Checkout stays the store's own: in-chat checkout in ChatGPT or Gemini needs card payments, which
 * cash-on-delivery stores do not take.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_AI_Shop.
 */
class Cartlum_AI_Shop {

	const SESSION = 'cartlum_ai_source';
	const META    = '_cartlum_ai_source';
	const MAX     = 500; // Products in llms.txt and the feed.

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'ai_shop_enabled' ) ) {
			return;
		}
		add_action( 'parse_request', array( __CLASS__, 'llms_txt' ), 0 );
		add_action( 'wp_loaded', array( __CLASS__, 'requests' ), 40 );
		add_action( 'template_redirect', array( __CLASS__, 'remember_source' ), 1 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'mark_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_order' ) );
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'structured_data' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'llms_link' ) );
		add_action( 'cartlum_reports_after', array( __CLASS__, 'report' ), 5, 2 );
	}

	/**
	 * AI assistants by the address their visitors come from (or utm_source they add).
	 *
	 * @return array host fragment => name
	 */
	public static function sources() {
		return array(
			'chatgpt.com'           => 'ChatGPT',
			'chat.openai.com'       => 'ChatGPT',
			'openai.com'            => 'ChatGPT',
			'gemini.google.com'     => 'Gemini',
			'bard.google.com'       => 'Gemini',
			'claude.ai'             => 'Claude',
			'perplexity.ai'         => 'Perplexity',
			'grok.com'              => 'Grok',
			'x.ai'                  => 'Grok',
			'kimi.com'              => 'Kimi',
			'kimi.moonshot.cn'      => 'Kimi',
			'copilot.microsoft.com' => 'Copilot',
			'chat.deepseek.com'     => 'DeepSeek',
			'meta.ai'               => 'Meta AI',
		);
	}

	/**
	 * The assistant a host or utm_source belongs to, or ''.
	 *
	 * @param string $value Host or utm_source.
	 * @return string
	 */
	public static function source_for( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		foreach ( self::sources() as $host => $name ) {
			// The host itself or a subdomain of it, or the assistant's name as a utm_source.
			if ( $value === $host || '.' . $host === substr( $value, -strlen( $host ) - 1 ) || strtolower( str_replace( ' ', '', $name ) ) === $value ) {
				return $name;
			}
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Where the visitor came from
	 * ------------------------------------------------------------------ */

	/**
	 * Remembers, for this visit, that the visitor came from an AI assistant.
	 */
	public static function remember_source() {
		if ( is_admin() || ! WC()->session ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads where a visit came from.
		$utm  = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '';
		$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';
		$name = self::source_for( $utm );
		$name = '' !== $name ? $name : self::source_for( $ref );
		if ( '' !== $name ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( self::SESSION, $name );
		}
	}

	/**
	 * Marks an order placed in a visit that came from an AI assistant.
	 *
	 * @param int|WC_Order $order Order or its id.
	 */
	public static function mark_order( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		$name  = WC()->session ? (string) WC()->session->get( self::SESSION ) : '';
		if ( ! $order || '' === $name ) {
			return;
		}
		$order->update_meta_data( self::META, $name );
		$order->save_meta_data();
		/* translators: %s: ChatGPT, Gemini … */
		$order->add_order_note( sprintf( __( 'Cartlum: the customer came from %s.', 'cartlum' ), $name ) );
	}

	/* ---------------------------------------------------------------------
	 * Order links, the feed
	 * ------------------------------------------------------------------ */

	/**
	 * A product's order link: the checkout with the product in the cart.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	public static function order_link( $product ) {
		return add_query_arg( 'cartlum_order', $product->get_id(), home_url( '/' ) );
	}

	/**
	 * ?cartlum_order=ID[&qty=N] and ?cartlum_feed=json.
	 */
	public static function requests() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public links: a product into one's own cart, a public feed.
		if ( isset( $_GET['cartlum_feed'] ) && ! is_admin() ) {
			self::feed();
		}
		if ( empty( $_GET['cartlum_order'] ) || is_admin() || ! WC()->cart || Cartlum_Restore::is_preview_bot() ) {
			return;
		}
		$product = wc_get_product( absint( $_GET['cartlum_order'] ) );
		$qty     = isset( $_GET['qty'] ) ? max( 1, min( 99, absint( $_GET['qty'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type( array( 'variable', 'grouped', 'external' ) ) ) {
			// A product with options, or one that cannot be bought: its page, to choose there.
			wp_safe_redirect( $product ? $product->get_permalink() : wc_get_page_permalink( 'shop' ) );
			exit;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->cart->empty_cart();
		if ( $product->is_type( 'variation' ) ) {
			WC()->cart->add_to_cart( $product->get_parent_id(), $qty, $product->get_id(), $product->get_variation_attributes() );
		} else {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Products for llms.txt and the feed: published, visible, newest best sellers first.
	 *
	 * @return WC_Product[]
	 */
	private static function products() {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'visible',
				'limit'      => self::MAX,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);
		usort(
			$products,
			function ( $a, $b ) {
				return (int) $b->get_total_sales() <=> (int) $a->get_total_sales();
			}
		);
		return $products;
	}

	/**
	 * Plain text from product HTML.
	 *
	 * @param string $html HTML.
	 * @param int    $words Most words.
	 * @return string
	 */
	private static function text( $html, $words = 60 ) {
		return wp_trim_words( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $html ) ) ) ), $words, '…' );
	}

	/**
	 * Price as plain text, e.g. "1299.00 BDT".
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function price( $product ) {
		return wc_format_decimal( wc_get_price_to_display( $product ), wc_get_price_decimals() ) . ' ' . get_woocommerce_currency();
	}

	/**
	 * Lines a product becomes in the feed: itself, or one per variation that can be bought.
	 *
	 * @param WC_Product $product Product.
	 * @return array[]
	 */
	private static function feed_items( $product ) {
		$base = array(
			'title'        => $product->get_name(),
			'description'  => self::text( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
			'link'         => $product->get_permalink(),
			'image_link'   => (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' ),
			'brand'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'product_category' => implode( ' > ', wp_list_pluck( wc_get_product_terms( $product->get_id(), 'product_cat' ), 'name' ) ),
			'seller_name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'payment'      => self::payments(),
			'delivery'     => self::delivery_text(),
			'return_policy' => self::returns_text(),
		);
		if ( ! $product->is_type( 'variable' ) ) {
			return array(
				$base + array(
					'id'           => (string) $product->get_id(),
					'price'        => self::price( $product ),
					'availability' => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
					'order_link'   => $product->is_in_stock() ? self::order_link( $product ) : '',
				),
			);
		}
		$out = array();
		foreach ( $product->get_children() as $child_id ) {
			$v = wc_get_product( $child_id );
			if ( ! $v || ! $v->is_purchasable() ) {
				continue;
			}
			$out[] = array(
				'id'            => (string) $v->get_id(),
				'item_group_id' => (string) $product->get_id(),
				'title'         => $product->get_name() . ' – ' . wc_get_formatted_variation( $v, true, false, false ),
				'options'       => wc_get_formatted_variation( $v, true, true, false ),
				'price'         => self::price( $v ),
				'availability'  => $v->is_in_stock() ? 'in_stock' : 'out_of_stock',
				'order_link'    => $v->is_in_stock() ? self::order_link( $v ) : '',
			) + $base;
		}
		return $out;
	}

	/**
	 * The product feed, ?cartlum_feed=json.
	 */
	private static function feed() {
		$items = array();
		foreach ( self::products() as $product ) {
			$items = array_merge( $items, self::feed_items( $product ) );
		}
		nocache_headers();
		wp_send_json(
			array(
				'store'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'        => home_url( '/' ),
				'currency'   => get_woocommerce_currency(),
				'updated_at' => gmdate( 'c' ),
				'how_to_order' => self::how_to_order(),
				'items'      => $items,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Words about the shop
	 * ------------------------------------------------------------------ */

	/**
	 * Payment methods the checkout offers, by name.
	 *
	 * @return string
	 */
	private static function payments() {
		$names = array();
		foreach ( WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array() as $gateway ) {
			$names[] = wp_strip_all_tags( $gateway->get_title() );
		}
		return implode( ', ', $names );
	}

	/**
	 * Delivery times, e.g. "Inside Dhaka 1-2 days; outside Dhaka 3-5 days."
	 *
	 * @return string
	 */
	private static function delivery_text() {
		$in  = trim( (string) Cartlum_Settings::get( 'ai_delivery_inside' ) );
		$out = trim( (string) Cartlum_Settings::get( 'ai_delivery_outside' ) );
		$parts = array();
		if ( '' !== $in ) {
			/* translators: %s: days, e.g. 1-2 */
			$parts[] = sprintf( __( 'Inside Dhaka: %s days', 'cartlum' ), $in );
		}
		if ( '' !== $out ) {
			/* translators: %s: days, e.g. 3-5 */
			$parts[] = sprintf( __( 'Outside Dhaka: %s days', 'cartlum' ), $out );
		}
		return implode( '; ', $parts );
	}

	/**
	 * Returns, e.g. "Exchange or return within 7 days."
	 *
	 * @return string
	 */
	private static function returns_text() {
		$days = absint( Cartlum_Settings::get( 'ai_returns_days' ) );
		/* translators: %d: days */
		return $days ? sprintf( _n( 'Exchange or return within %d day.', 'Exchange or return within %d days.', $days, 'cartlum' ), $days ) : '';
	}

	/**
	 * How ordering works, in a few sentences (also Pro's shopping chat).
	 *
	 * @return string
	 */
	public static function how_to_order() {
		$lines = array( __( 'Use the order link of a product: it opens the checkout with the product in the cart. The buyer types their mobile number, name, address and district, and places the order.', 'cartlum' ) );
		$pay   = self::payments();
		if ( '' !== $pay ) {
			/* translators: %s: payment methods */
			$lines[] = sprintf( __( 'Payment: %s.', 'cartlum' ), $pay );
		}
		if ( '' !== self::delivery_text() ) {
			/* translators: %s: delivery times */
			$lines[] = sprintf( __( 'Delivery: %s.', 'cartlum' ), self::delivery_text() );
		}
		if ( '' !== self::returns_text() ) {
			$lines[] = self::returns_text();
		}
		$phone = trim( (string) Cartlum_Settings::get( 'call_number' ) );
		if ( '' !== $phone ) {
			/* translators: %s: phone number */
			$lines[] = sprintf( __( 'Phone: %s.', 'cartlum' ), $phone );
		}
		return implode( ' ', $lines );
	}

	/**
	 * Markdown-safe text for llms.txt.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function md( $text ) {
		return trim( str_replace( array( "\r", "\n", '[', ']' ), array( ' ', ' ', '(', ')' ), (string) $text ) );
	}

	/**
	 * /llms.txt: the shop for AI assistants, in Markdown (llmstxt.org).
	 *
	 * @param WP $wp Request.
	 */
	public static function llms_txt( $wp ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( rtrim( $home, '/' ) . '/llms.txt' !== $path ) {
			return;
		}
		$name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$about = trim( (string) Cartlum_Settings::get( 'ai_shop_about' ) );
		$about = '' !== $about ? $about : wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$out   = '# ' . self::md( $name ) . "\n\n";
		if ( '' !== $about ) {
			$out .= '> ' . self::md( $about ) . "\n\n";
		}
		$out .= '## ' . __( 'How to order', 'cartlum' ) . "\n\n" . self::how_to_order() . "\n\n";
		/* translators: %s: feed address */
		$out .= sprintf( __( 'All products as JSON, with prices, stock and order links: %s', 'cartlum' ), add_query_arg( 'cartlum_feed', 'json', home_url( '/' ) ) ) . "\n\n";
		$out .= '## ' . __( 'Products', 'cartlum' ) . "\n\n";
		foreach ( self::products() as $product ) {
			$line = '- [' . self::md( $product->get_name() ) . '](' . $product->get_permalink() . '): ' . html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' );
			$line .= $product->is_in_stock() ? '' : ' (' . __( 'out of stock', 'cartlum' ) . ')';
			$desc  = self::text( $product->get_short_description() ? $product->get_short_description() : $product->get_description(), 25 );
			$line .= '' !== $desc ? '. ' . self::md( $desc ) : '';
			if ( $product->is_in_stock() && ! $product->is_type( 'variable' ) && $product->is_purchasable() ) {
				/* translators: %s: order link */
				$line .= ' ' . sprintf( __( 'Order: %s', 'cartlum' ), self::order_link( $product ) );
			}
			$out .= $line . "\n";
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a plain-text (Markdown) file, not HTML.
		exit;
	}

	/**
	 * Points AI assistants reading a page to llms.txt.
	 */
	public static function llms_link() {
		echo '<link rel="alternate" type="text/markdown" title="llms.txt" href="' . esc_url( home_url( '/llms.txt' ) ) . '">' . "\n";
	}

	/**
	 * Delivery, returns and cash on delivery in each product page's data (schema.org), which AI
	 * assistants and search engines read.
	 *
	 * @param array      $markup  Product data.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function structured_data( $markup, $product ) {
		if ( empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
			return $markup;
		}
		$country = WC()->countries ? WC()->countries->get_base_country() : 'BD';
		$days    = function ( $text ) {
			return preg_match( '/(\d+)\D+(\d+)/', (string) $text, $m ) ? array( (int) $m[1], (int) $m[2] ) : ( preg_match( '/(\d+)/', (string) $text, $m ) ? array( (int) $m[1], (int) $m[1] ) : null );
		};
		$transit = $days( Cartlum_Settings::get( 'ai_delivery_outside' ) );
		$transit = $transit ? $transit : $days( Cartlum_Settings::get( 'ai_delivery_inside' ) );
		$returns = absint( Cartlum_Settings::get( 'ai_returns_days' ) );
		foreach ( $markup['offers'] as $i => $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			$markup['offers'][ $i ]['acceptedPaymentMethod'] = array(
				'@type' => 'PaymentMethod',
				'name'  => self::payments(),
			);
			if ( $transit ) {
				$markup['offers'][ $i ]['shippingDetails'] = array(
					'@type'               => 'OfferShippingDetails',
					'shippingDestination' => array(
						'@type'          => 'DefinedRegion',
						'addressCountry' => $country,
					),
					'deliveryTime'        => array(
						'@type'        => 'ShippingDeliveryTime',
						'handlingTime' => array(
							'@type'    => 'QuantitativeValue',
							'minValue' => 0,
							'maxValue' => 1,
							'unitCode' => 'DAY',
						),
						'transitTime'  => array(
							'@type'    => 'QuantitativeValue',
							'minValue' => $transit[0],
							'maxValue' => $transit[1],
							'unitCode' => 'DAY',
						),
					),
				);
			}
			if ( $returns ) {
				$markup['offers'][ $i ]['hasMerchantReturnPolicy'] = array(
					'@type'                => 'MerchantReturnPolicy',
					'applicableCountry'    => $country,
					'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
					'merchantReturnDays'   => $returns,
				);
			}
		}
		return $markup;
	}

	/* ---------------------------------------------------------------------
	 * Reports
	 * ------------------------------------------------------------------ */

	/**
	 * "Orders from AI assistants" on the Reports page.
	 *
	 * @param array $rows Report rows (id, outcome, total, …).
	 * @param int   $days Period.
	 */
	public static function report( $rows, $days ) {
		$by = array();
		foreach ( $rows as $row ) {
			$order = wc_get_order( $row['id'] );
			$name  = $order ? (string) $order->get_meta( self::META ) : '';
			if ( '' === $name ) {
				continue;
			}
			if ( ! isset( $by[ $name ] ) ) {
				$by[ $name ] = array(
					'orders'    => 0,
					'delivered' => 0,
					'value'     => 0.0,
				);
			}
			++$by[ $name ]['orders'];
			$by[ $name ]['value'] += (float) $row['total'];
			$by[ $name ]['delivered'] += 'delivered' === $row['outcome'] ? 1 : 0;
		}
		?>
		<section class="cl-section">
			<div class="cl-section__head"><h2><?php esc_html_e( 'Orders from AI assistants', 'cartlum' ); ?></h2></div>
			<?php if ( ! $by ) : ?>
				<p class="cl-note"><?php esc_html_e( 'No orders from ChatGPT, Gemini, Claude, Grok, Perplexity or Kimi in this period yet.', 'cartlum' ); ?></p>
			<?php else : ?>
				<table class="cl-table">
					<thead><tr><th><?php esc_html_e( 'Assistant', 'cartlum' ); ?></th><th class="cl-num"><?php esc_html_e( 'Orders', 'cartlum' ); ?></th><th class="cl-num"><?php esc_html_e( 'Delivered', 'cartlum' ); ?></th><th class="cl-num"><?php esc_html_e( 'Value', 'cartlum' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $by as $name => $n ) : ?>
						<tr><th scope="row"><?php echo esc_html( $name ); ?></th><td class="cl-num"><?php echo esc_html( number_format_i18n( $n['orders'] ) ); ?></td><td class="cl-num"><?php echo esc_html( number_format_i18n( $n['delivered'] ) ); ?></td><td class="cl-num"><?php echo wp_kses_post( wc_price( $n['value'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}
}
