<?php
/**
 * Order confirmation: new cash-on-delivery orders wait in Orders → To confirm until someone (staff
 * on the phone, or the customer through a link Cartlum Pro adds) says yes or no. The order's
 * status is never changed by confirming, so courier plugins, order SMS and Facebook purchases
 * keep working as before; the answer is kept on the order with a note.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Confirm.
 */
class Cartlum_Confirm {

	const META  = '_cartlum_confirm';
	const TRIES = '_cartlum_confirm_tries';
	const AT    = '_cartlum_confirm_at';
	const BY    = '_cartlum_confirm_by';
	const DAYS  = 14;

	/**
	 * What staff can record.
	 *
	 * @return array slug => label
	 */
	public static function outcomes() {
		return array(
			'confirmed' => __( 'Confirmed', 'cartlum' ),
			'no_answer' => __( 'No answer', 'cartlum' ),
			'call_back' => __( 'Call back', 'cartlum' ),
			'cancel'    => __( 'Cancel order', 'cartlum' ),
			'fake'      => __( 'Fake order', 'cartlum' ),
		);
	}

	/**
	 * Whether an order is confirmed.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_confirmed( $order ) {
		return 'confirmed' === $order->get_meta( self::META );
	}

	/**
	 * Orders waiting to be confirmed, oldest first: Processing or On hold, from the last 14 days,
	 * not confirmed and not yet with a courier.
	 *
	 * @param int $limit Most to return.
	 * @return WC_Order[]
	 */
	public static function queue( $limit = 100 ) {
		$out = array();
		foreach ( wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'processing', 'on-hold' ),
				'date_created' => '>' . ( time() - self::DAYS * DAY_IN_SECONDS ),
				'orderby'      => 'date',
				'order'        => 'ASC',
				'limit'        => 300,
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- staff screen and a 5-minute cached count.
					'relation' => 'OR',
					array(
						'key'     => self::META,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META,
						'value'   => 'confirmed',
						'compare' => '!=',
					),
				),
			)
		) as $order ) {
			if ( Cartlum_Order_Tracking::courier( $order ) ) {
				continue; // Already handed to a courier: it was confirmed some other way.
			}
			$out[] = $order;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * How many orders were confirmed since midnight.
	 *
	 * @return int
	 */
	public static function confirmed_today() {
		$today = new DateTime( 'today', wp_timezone() );
		$found = wc_get_orders(
			array(
				'type'       => 'shop_order',
				'limit'      => 1,
				'paginate'   => true,
				'return'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- staff screen only.
					array(
						'key'   => self::META,
						'value' => 'confirmed',
					),
					array(
						'key'     => self::AT,
						'value'   => $today->getTimestamp(),
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		return (int) $found->total;
	}

	/**
	 * Records an answer.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $outcome One of outcomes().
	 * @param array    $args    { by: staff|customer|ai, reason: cancel reason slug, block: bool }.
	 * @return bool
	 */
	public static function record( $order, $outcome, array $args = array() ) {
		if ( ! isset( self::outcomes()[ $outcome ] ) || $order->has_status( array( 'cancelled', 'failed', 'refunded', 'completed' ) ) ) {
			return false;
		}
		delete_transient( 'cartlum_today' ); // Today's "Orders to confirm" count.
		$by   = in_array( $args['by'] ?? 'staff', array( 'customer', 'ai' ), true ) ? $args['by'] : 'staff';
		$user = wp_get_current_user();
		if ( 'customer' === $by ) {
			$who = __( 'the customer, from their confirmation link', 'cartlum' );
		} elseif ( 'ai' === $by ) {
			$who = __( 'the AI call', 'cartlum' );
		} else {
			$who = $user->exists() ? $user->display_name : __( 'staff', 'cartlum' );
		}

		if ( 'confirmed' === $outcome ) {
			if ( self::is_confirmed( $order ) ) {
				return true;
			}
			$order->update_meta_data( self::META, 'confirmed' );
			$order->update_meta_data( self::AT, time() );
			$order->update_meta_data( self::BY, $by );
			/* translators: %s: who confirmed */
			$order->add_order_note( sprintf( __( 'Cartlum: order confirmed by %s.', 'cartlum' ), $who ) );
			$order->save();
			/**
			 * An order was confirmed, by staff or by the customer.
			 *
			 * @param int    $order_id Order.
			 * @param string $by       staff|customer|ai.
			 */
			do_action( 'cartlum_order_confirmed', $order->get_id(), $by );
			return true;
		}

		if ( 'no_answer' === $outcome || 'call_back' === $outcome ) {
			$tries = (int) $order->get_meta( self::TRIES ) + ( 'no_answer' === $outcome ? 1 : 0 );
			$order->update_meta_data( self::META, $outcome );
			$order->update_meta_data( self::TRIES, $tries );
			$order->update_meta_data( self::AT, time() );
			$order->add_order_note(
				'no_answer' === $outcome
					/* translators: 1: who called, 2: number of unanswered calls */
					? sprintf( __( 'Cartlum: confirmation call by %1$s — no answer (%2$d).', 'cartlum' ), $who, $tries )
					/* translators: %s: who called */
					: sprintf( __( 'Cartlum: confirmation call by %s — asked to call back.', 'cartlum' ), $who )
			);
			$order->save();
			return true;
		}

		// Cancel, or cancel as fake.
		if ( ! class_exists( 'Cartlum_Cancel_Reasons' ) ) {
			require_once CARTLUM_DIR . 'includes/admin/class-cartlum-cancel-reasons.php';
		}
		$reason = 'fake' === $outcome ? 'fake_order' : (string) ( $args['reason'] ?? '' );
		$reason = isset( Cartlum_Cancel_Reasons::reasons()[ $reason ] ) ? $reason : ( 'customer' === $by ? 'customer_cancelled' : 'declined_on_call' );
		Cartlum_Cancel_Reasons::set( $order, $reason );
		if ( 'fake' === $outcome && ! empty( $args['block'] ) && Cartlum_Protection::block( $order->get_billing_phone() ) ) {
			/* translators: %s: who blocked */
			$order->add_order_note( sprintf( __( 'Cartlum: number blocked from ordering online by %s.', 'cartlum' ), $who ) );
		}
		/* translators: %s: who cancelled */
		$order->update_status( 'cancelled', sprintf( __( 'Cartlum: cancelled by %s at confirmation.', 'cartlum' ), $who ) );
		return true;
	}
}
