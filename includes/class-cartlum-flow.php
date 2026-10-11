<?php
/**
 * Flow: automatic messages after a cart is abandoned.
 *
 * Each cart walks through a list of steps -- { delay, channel, template } -- one Action Scheduler
 * job per step. The free plugin has one step, configured under Settings → Automation. Cartlum Pro
 * supplies more through the cartlum_flow_steps filter; the engine does not care how many there are.
 *
 * Safety rails, because this sends messages on the store's behalf with nobody watching:
 * - nothing is sent once the cart is ordered, lost, unsubscribed, or stopped by hand;
 * - nothing is sent in quiet hours; the step waits for the morning instead;
 * - a daily cap on automatic SMS, so a bug or a bot cannot drain the SMS balance;
 * - a test number that receives every automatic SMS instead of the customer.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Flow.
 */
class Cartlum_Flow {

	const HOOK = 'cartlum_flow_step';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'cartlum_cart_abandoned', array( __CLASS__, 'on_abandoned' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_step' ), 10, 2 );
	}

	/**
	 * Whether automation is switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === Cartlum_Settings::get( 'flow_enabled' );
	}

	/**
	 * The steps, in order.
	 *
	 * @return array[] Each: { delay (minutes after abandonment), channel (sms|email|...), template, subject }.
	 */
	public static function steps() {
		$steps = array(
			array(
				'delay'    => max( 5, (int) Cartlum_Settings::get( 'flow_delay' ) ),
				'channel'  => (string) Cartlum_Settings::get( 'flow_channel' ),
				'template' => 'email' === Cartlum_Settings::get( 'flow_channel' ) ? (string) Cartlum_Settings::get( 'email_template' ) : (string) Cartlum_Settings::get( 'flow_sms_template' ),
				'subject'  => (string) Cartlum_Settings::get( 'email_subject' ),
			),
		);

		/**
		 * Replaces the automation steps (Cartlum Pro: multi-step flows, more channels).
		 *
		 * @param array[] $steps Steps.
		 */
		$steps = apply_filters( 'cartlum_flow_steps', $steps );
		return array_values( array_filter( (array) $steps, 'is_array' ) );
	}

	/**
	 * A cart just became abandoned: schedule its next step. A cart that comes back to checkout and
	 * is abandoned again continues where it left off rather than starting the sequence over, so a
	 * customer never gets the same message twice.
	 *
	 * @param int $cart_id Cart.
	 */
	public static function on_abandoned( $cart_id ) {
		if ( ! self::enabled() || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		$cart = Cartlum_Carts::get( $cart_id );
		if ( ! $cart || (int) $cart->flow_stopped ) {
			return;
		}
		self::schedule( $cart, (int) $cart->flow_step );
	}

	/**
	 * Schedules step $index for a cart, timed from when it was abandoned.
	 *
	 * @param object $cart  Row.
	 * @param int    $index Step.
	 * @return int|false Timestamp, or false when there is no such step.
	 */
	public static function schedule( $cart, $index ) {
		$steps = self::steps();
		if ( ! isset( $steps[ $index ] ) ) {
			return false;
		}
		$args = array( (int) $cart->id, (int) $index );
		if ( as_has_scheduled_action( self::HOOK, $args, 'cartlum' ) ) {
			return false;
		}
		$from = $cart->abandoned_at ? strtotime( $cart->abandoned_at . ' UTC' ) : time();
		$when = max( time() + MINUTE_IN_SECONDS, $from + (int) $steps[ $index ]['delay'] * MINUTE_IN_SECONDS );
		as_schedule_single_action( $when, self::HOOK, $args, 'cartlum' );
		return $when;
	}

	/**
	 * The next automatic message for a cart, for the admin screen.
	 *
	 * @param object $cart Row.
	 * @return int|null Timestamp.
	 */
	public static function next_run( $cart ) {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}
		$ts = as_next_scheduled_action( self::HOOK, array( (int) $cart->id, (int) $cart->flow_step ), 'cartlum' );
		return is_int( $ts ) ? $ts : null;
	}

	/**
	 * Runs one step.
	 *
	 * @param int $cart_id Cart.
	 * @param int $index   Step.
	 */
	public static function run_step( $cart_id, $index ) {
		$cart = Cartlum_Carts::get( $cart_id );
		// Ordered, lost, unsubscribed, stopped, or a stale duplicate job: do nothing.
		if ( ! $cart || 'abandoned' !== $cart->status || (int) $cart->flow_stopped || (int) $cart->flow_step !== (int) $index || ! self::enabled() ) {
			return;
		}
		$steps = self::steps();
		if ( ! isset( $steps[ $index ] ) ) {
			return;
		}

		$wait_until = self::quiet_until();
		if ( $wait_until ) {
			self::requeue( $cart, $index, $wait_until );
			return;
		}

		/**
		 * Adjusts one step for one cart just before it is sent (Cartlum Pro: A/B variants and
		 * smart coupons choose the template here). Put extra keys in $step['event_meta'] to record
		 * them on the timeline event.
		 *
		 * @param array  $step  Step.
		 * @param object $cart  Cart row.
		 * @param int    $index Step index.
		 */
		$step = apply_filters( 'cartlum_flow_prepare_step', $steps[ $index ], $cart, (int) $index );
		$step['event_meta'] = array_merge( array( 'step' => (int) $index ), isset( $step['event_meta'] ) ? (array) $step['event_meta'] : array() );

		$result = self::send( $cart, $step );
		if ( 'limit' === $result ) {
			// Over today's cap: try again tomorrow morning rather than skip the customer, and say so on
			// the timeline -- otherwise the owner just sees a reminder that never went out.
			$when = self::next_morning();
			self::requeue( $cart, $index, $when );
			Cartlum_Events::add(
				$cart->id,
				'flow_delayed',
				array(
					'channel' => 'sms',
					'user_id' => 0,
					'meta'    => array(
						/* translators: %s: date and time */
						'error' => sprintf( __( 'Daily SMS limit reached — will send %s.', 'cartlum' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $when ) ),
					),
				)
			);
			return;
		}

		Cartlum_Carts::update( $cart->id, array( 'flow_step' => (int) $index + 1 ) );
		$cart->flow_step = (int) $index + 1;
		self::schedule( $cart, (int) $index + 1 );
	}

	/**
	 * Puts a step back in the queue for later, unless it is already waiting there.
	 *
	 * @param object $cart  Row.
	 * @param int    $index Step.
	 * @param int    $when  Timestamp.
	 */
	private static function requeue( $cart, $index, $when ) {
		$args = array( (int) $cart->id, (int) $index );
		if ( ! as_has_scheduled_action( self::HOOK, $args, 'cartlum' ) ) {
			as_schedule_single_action( $when, self::HOOK, $args, 'cartlum' );
		}
	}

	/**
	 * Sends one step's message.
	 *
	 * @param object $cart Row.
	 * @param array  $step Step.
	 * @return true|WP_Error|string 'skipped' when the channel cannot reach this customer, 'limit' at the daily cap.
	 */
	private static function send( $cart, array $step ) {
		$channel = isset( $step['channel'] ) ? $step['channel'] : 'sms';

		/**
		 * Lets add-ons send through their own channels (for example a WhatsApp Business API sender).
		 * Return anything other than null to say the step was handled.
		 *
		 * @param mixed  $result  null to let Cartlum handle it.
		 * @param object $cart    Cart row.
		 * @param array  $step    Step.
		 */
		$handled = apply_filters( 'cartlum_flow_send', null, $cart, $step );
		if ( null !== $handled ) {
			return $handled;
		}

		if ( 'email' === $channel ) {
			if ( ! Cartlum_Email::can_email( $cart ) ) {
				return self::skip( $cart, 'email', __( 'no email address that can receive mail', 'cartlum' ), $step );
			}
			$subject = Cartlum_Messages::render( $step['subject'], $cart );
			$body    = Cartlum_Messages::render( $step['template'], $cart );
			if ( is_wp_error( $subject ) || is_wp_error( $body ) ) {
				return self::skip( $cart, 'email', is_wp_error( $subject ) ? $subject->get_error_message() : $body->get_error_message(), $step );
			}
			if ( self::recently_reminded( $cart, 'email' ) ) {
				return self::skip( $cart, 'email', __( 'this address already got a reminder for another cart in the last 24 hours', 'cartlum' ), $step );
			}
			return Cartlum_Email::send_to_cart( $cart, $subject, $body, 'automation', $step['event_meta'] );
		}

		// SMS.
		if ( '' === $cart->phone ) {
			return self::skip( $cart, 'sms', __( 'no phone number', 'cartlum' ), $step );
		}
		if ( ! Cartlum_SMS::gateway() ) {
			return self::skip( $cart, 'sms', __( 'no SMS gateway set up', 'cartlum' ), $step );
		}
		if ( self::recently_reminded( $cart ) ) {
			return self::skip( $cart, 'sms', __( 'this number already got a reminder for another cart in the last 24 hours', 'cartlum' ), $step );
		}
		if ( ! self::take_sms_quota() ) {
			return 'limit';
		}
		$message = Cartlum_Messages::render( $step['template'], $cart );
		if ( is_wp_error( $message ) ) {
			return self::skip( $cart, 'sms', $message->get_error_message(), $step );
		}

		$test = Cartlum_Phone::normalize( (string) Cartlum_Settings::get( 'flow_test_phone' ) );
		if ( '' !== $test ) {
			// Test mode: the customer is not contacted, so the cart is not marked contacted either.
			$result = Cartlum_SMS::send_raw( $test, $message );
			Cartlum_Events::add(
				$cart->id,
				is_wp_error( $result ) ? 'sms_failed' : 'sms_test',
				array(
					'channel' => 'sms',
					'message' => $message,
					'user_id' => 0,
					'meta'    => array_merge(
						$step['event_meta'],
						array(
							'context' => 'automation',
							'test_to' => $test,
							'error'   => is_wp_error( $result ) ? $result->get_error_message() : '',
						)
					),
				)
			);
			return $result;
		}
		return Cartlum_SMS::send_to_cart( $cart, $message, 'automation', $step['event_meta'] );
	}

	/**
	 * Whether another cart with the same phone (or email) got an automatic reminder in the last 24
	 * hours. Checkout accepts any number, so without this a stranger could open carts under someone
	 * else's number again and again and have the store text them repeatedly. A cart's own later steps
	 * are not affected.
	 *
	 * @param object $cart    Row.
	 * @param string $channel sms|email.
	 * @return bool
	 */
	private static function recently_reminded( $cart, $channel = 'sms' ) {
		global $wpdb;
		$field = 'email' === $channel ? 'email' : 'phone';
		if ( '' === (string) $cart->$field ) {
			return false;
		}
		$events = Cartlum_Install::table( 'events' );
		$carts  = Cartlum_Install::table( 'carts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cartlum's own tables; must be live.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT e.id FROM %i e JOIN %i c ON c.id = e.cart_id WHERE c.%i = %s AND c.id <> %d AND e.type IN (%s, %s) AND e.meta LIKE %s AND e.created_at > %s LIMIT 1',
				$events,
				$carts,
				$field,
				$cart->$field,
				(int) $cart->id,
				'email' === $channel ? 'email_sent' : 'sms_sent',
				'email' === $channel ? 'email_sent' : 'sms_test',
				'%' . $wpdb->esc_like( '"context":"automation"' ) . '%',
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Records a step that could not be sent, and moves on.
	 *
	 * @param object $cart    Row.
	 * @param string $channel Channel.
	 * @param string $why     Reason.
	 * @param array  $step    Step (its event_meta is kept).
	 * @return string
	 */
	private static function skip( $cart, $channel, $why, array $step = array() ) {
		Cartlum_Events::add(
			$cart->id,
			'flow_skipped',
			array(
				'channel' => $channel,
				'user_id' => 0,
				'meta'    => array_merge( isset( $step['event_meta'] ) ? (array) $step['event_meta'] : array(), array( 'error' => $why ) ),
			)
		);
		return 'skipped';
	}

	/**
	 * Counts one automatic SMS against today's cap.
	 *
	 * @return bool False when the cap is reached.
	 */
	private static function take_sms_quota() {
		$limit = (int) Cartlum_Settings::get( 'flow_daily_sms_limit' );
		if ( $limit <= 0 ) {
			return true;
		}
		$key   = 'cartlum_sms_count_' . wp_date( 'Ymd' );
		$count = (int) get_option( $key, 0 );
		if ( $count >= $limit ) {
			return false;
		}
		update_option( $key, $count + 1, false );
		return true;
	}

	/**
	 * Whether the store-local clock is inside quiet hours.
	 *
	 * @param int|null $now Timestamp, for tests.
	 * @return int 0 when sending is allowed now, else the timestamp when quiet hours end.
	 */
	public static function quiet_until( $now = null ) {
		$start = (int) Cartlum_Settings::get( 'quiet_start' );
		$end   = (int) Cartlum_Settings::get( 'quiet_end' );
		if ( $start === $end ) {
			return 0; // No quiet hours.
		}
		$tz    = wp_timezone();
		$local = new DateTimeImmutable( '@' . ( null === $now ? time() : $now ) );
		$local = $local->setTimezone( $tz );
		$hour  = (int) $local->format( 'G' );

		$quiet = $start > $end ? ( $hour >= $start || $hour < $end ) : ( $hour >= $start && $hour < $end );
		if ( ! $quiet ) {
			return 0;
		}
		$resume = $local->setTime( $end, 0 );
		if ( $resume <= $local ) {
			$resume = $resume->modify( '+1 day' );
		}
		return $resume->getTimestamp();
	}

	/**
	 * Tomorrow at the end of quiet hours (or 9:00 when there are none), store-local.
	 *
	 * @return int
	 */
	private static function next_morning() {
		$end   = (int) Cartlum_Settings::get( 'quiet_end' );
		$local = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 day' )->setTime( $end ? $end : 9, 0 );
		return $local->getTimestamp();
	}

	/**
	 * Stops automation for one cart.
	 *
	 * @param object $cart Row.
	 */
	public static function stop( $cart ) {
		Cartlum_Carts::update( $cart->id, array( 'flow_stopped' => 1 ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( (int) $cart->id, (int) $cart->flow_step ), 'cartlum' );
		}
		Cartlum_Events::add( $cart->id, 'flow_stopped' );
	}
}
