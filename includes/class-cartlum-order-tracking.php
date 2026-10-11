<?php
/**
 * Order tracking in My Account: a progress bar (placed, processing, with the courier, delivered)
 * and the courier's consignment and status on each order's page.
 *
 * Steadfast is read out of the box; other couriers can supply their details through the
 * cartlum_order_courier filter.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Order_Tracking.
 */
class Cartlum_Order_Tracking {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( 'yes' !== Cartlum_Settings::get( 'order_tracking' ) ) {
			return;
		}
		add_action( 'woocommerce_view_order', array( __CLASS__, 'output' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * The courier's details for an order, or null.
	 *
	 * @param WC_Order $order Order.
	 * @return array|null { name, id, status, status_label, url }
	 */
	public static function courier( $order ) {
		$courier = null;
		// Steadfast keeps its data as post meta on the order id, with or without HPOS.
		$id = (string) get_post_meta( $order->get_id(), 'steadfast_consignment_id', true );
		if ( '' !== $id ) {
			$status  = (string) get_post_meta( $order->get_id(), 'stdf_delivery_status', true );
			$courier = array(
				'name'   => 'Steadfast',
				'id'     => $id,
				'status' => $status,
			);
		}

		/**
		 * Courier details for an order: { name, id, status } or null. Status words such as
		 * "delivered", "cancelled", "hold" and "in_review" get friendly labels.
		 *
		 * @param array|null $courier Details.
		 * @param WC_Order   $order   Order.
		 */
		$courier = apply_filters( 'cartlum_order_courier', $courier, $order );
		if ( ! is_array( $courier ) || empty( $courier['id'] ) ) {
			return null;
		}
		$courier['status']       = isset( $courier['status'] ) ? (string) $courier['status'] : '';
		$courier['status_label'] = self::courier_status_label( $courier['status'] );
		$pattern                 = trim( (string) Cartlum_Settings::get( 'courier_tracking_url' ) );
		$courier['url']          = '' !== $pattern ? str_replace( '{consignment}', rawurlencode( $courier['id'] ), $pattern ) : '';
		return $courier;
	}

	/**
	 * A courier status in words.
	 *
	 * @param string $status Status word.
	 * @return string
	 */
	private static function courier_status_label( $status ) {
		$labels = array(
			'in_review'                         => __( 'Being checked by the courier', 'cartlum' ),
			'pending'                           => __( 'On the way', 'cartlum' ),
			'hold'                              => __( 'On hold at the courier', 'cartlum' ),
			'delivered_approval_pending'        => __( 'Delivered', 'cartlum' ),
			'delivered'                         => __( 'Delivered', 'cartlum' ),
			'partial_delivered_approval_pending' => __( 'Partly delivered', 'cartlum' ),
			'partial_delivered'                 => __( 'Partly delivered', 'cartlum' ),
			'cancelled_approval_pending'        => __( 'Cancelled', 'cartlum' ),
			'cancelled'                         => __( 'Cancelled', 'cartlum' ),
		);
		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}
		return '' === $status ? __( 'Handed to the courier', 'cartlum' ) : ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Where an order is: steps done, and whether it stopped.
	 *
	 * @param WC_Order $order Order.
	 * @return array { steps: [ [label, done] ], stopped: string }
	 */
	public static function progress( $order ) {
		$courier   = self::courier( $order );
		$status    = $order->get_status();
		$delivered = 'completed' === $status || ( $courier && 0 === strpos( $courier['status'], 'delivered' ) );
		$shipped   = $delivered || (bool) $courier;
		$working   = $shipped || ! in_array( $status, array( 'pending', 'on-hold', 'failed', 'cancelled', 'refunded', 'checkout-draft' ), true );

		$stopped = '';
		if ( in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$stopped = wc_get_order_status_name( $status );
		} elseif ( $courier && 0 === strpos( $courier['status'], 'cancelled' ) ) {
			$stopped = __( 'Cancelled by the courier', 'cartlum' );
		}

		return array(
			'steps'   => array(
				array( __( 'Order placed', 'cartlum' ), true ),
				array( __( 'Processing', 'cartlum' ), $working ),
				array( __( 'With the courier', 'cartlum' ), $shipped ),
				array( __( 'Delivered', 'cartlum' ), $delivered ),
			),
			'stopped' => $stopped,
		);
	}

	/**
	 * The tracker, above the order details on My Account → Orders → View.
	 *
	 * @param int $order_id Order.
	 */
	public static function output( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$progress = self::progress( $order );
		$courier  = self::courier( $order );
		// The store marked it delivered; an older courier update must not say otherwise.
		if ( $courier && end( $progress['steps'] )[1] ) {
			$courier['status_label'] = __( 'Delivered', 'cartlum' );
		}
		$current  = 0;
		foreach ( $progress['steps'] as $i => $step ) {
			if ( $step[1] ) {
				$current = $i;
			}
		}
		?>
		<section class="clx-track" aria-label="<?php esc_attr_e( 'Order tracking', 'cartlum' ); ?>">
			<?php if ( $progress['stopped'] ) : ?>
				<p class="clx-track__stopped"><?php echo esc_html( $progress['stopped'] ); ?></p>
			<?php else : ?>
				<ol class="clx-track__steps">
					<?php foreach ( $progress['steps'] as $i => $step ) : ?>
						<li class="clx-track__step<?php echo $step[1] ? ' is-done' : ''; ?><?php echo $i === $current ? ' is-current' : ''; ?>"<?php echo $i === $current ? ' aria-current="step"' : ''; ?>>
							<span class="clx-track__dot" aria-hidden="true"></span>
							<span class="clx-track__label"><?php echo esc_html( $step[0] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php if ( $courier ) : ?>
				<div class="clx-track__courier">
					<div>
						<p class="clx-track__courier-name"><?php echo esc_html( $courier['name'] ); ?></p>
						<p class="clx-track__courier-meta">
							<?php /* translators: %s: consignment number */ echo esc_html( sprintf( __( 'Consignment %s', 'cartlum' ), $courier['id'] ) ); ?>
							· <?php echo esc_html( $courier['status_label'] ); ?>
						</p>
					</div>
					<?php if ( $courier['url'] ) : ?>
						<a class="clx-track__link" href="<?php echo esc_url( $courier['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Track parcel', 'cartlum' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Styles, on the order page only.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}
		wp_enqueue_style( 'cartlum-tracking', CARTLUM_URL . 'assets/css/tracking.css', array(), CARTLUM_VERSION );
		wp_add_inline_style( 'cartlum-tracking', Cartlum_Checkout::accent_css( '.clx-track' ) );
	}
}
