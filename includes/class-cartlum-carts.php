<?php
/**
 * Data access for captured carts.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cartlum's own tables: WordPress has no API for them, and the rows change on every checkout, so an object cache would serve stale carts.

/**
 * Cartlum_Carts.
 */
class Cartlum_Carts {

	const STATUSES = array( 'active', 'abandoned', 'recovered', 'converted', 'lost', 'optout' );

	/**
	 * Statuses that are still worth contacting.
	 */
	const OPEN = array( 'active', 'abandoned' );

	/**
	 * Current time in UTC, MySQL format. Everything is stored in UTC.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * One cart by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Cartlum_Install::table( 'carts' );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );
	}

	/**
	 * One cart by its restore token.
	 *
	 * @param string $token 32 hex characters.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		$table = Cartlum_Install::table( 'carts' );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token = %s', $table, $token ) );
	}

	/**
	 * The open cart for a session, if any.
	 *
	 * @param string $session_key WooCommerce session customer id.
	 * @return object|null
	 */
	public static function get_open_by_session( $session_key ) {
		global $wpdb;
		if ( '' === (string) $session_key ) {
			return null;
		}
		$table = Cartlum_Install::table( 'carts' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_key = %s AND status IN ('active','abandoned') ORDER BY id DESC LIMIT 1",
				$table,
				$session_key
			)
		);
	}

	/**
	 * Open carts with this phone number from the last N days -- used to match an order placed from
	 * another device or session back to the cart we were chasing.
	 *
	 * @param string $phone Normalized phone.
	 * @param int    $days  Look-back.
	 * @return array
	 */
	public static function find_open_by_phone( $phone, $days = 30 ) {
		global $wpdb;
		if ( '' === (string) $phone ) {
			return array();
		}
		$table = Cartlum_Install::table( 'carts' );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE phone = %s AND status IN ('active','abandoned') AND created_at >= %s",
				$table,
				$phone,
				$since
			)
		);
	}

	/**
	 * Inserts a cart.
	 *
	 * @param array $data Column => value.
	 * @return int New id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now  = self::now();
		$data = wp_parse_args(
			$data,
			array(
				'token'         => self::new_token(),
				'cart_contents' => '[]',
				'status'        => 'active',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		$wpdb->insert( Cartlum_Install::table( 'carts' ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a cart.
	 *
	 * @param int   $id   Id.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		return false !== $wpdb->update( Cartlum_Install::table( 'carts' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Deletes carts and their events.
	 *
	 * @param int[] $ids Ids.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}
		$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $marks is only "%d" placeholders, one per id; the ids themselves go through prepare().
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE cart_id IN ({$marks})", array_merge( array( Cartlum_Install::table( 'events' ) ), $ids ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$marks})", array_merge( array( Cartlum_Install::table( 'carts' ) ), $ids ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Unguessable restore token.
	 *
	 * @return string
	 */
	public static function new_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Decoded cart items.
	 *
	 * @param object $cart Row.
	 * @return array[] { product_id, variation_id, quantity, variation, name, price, line_total }
	 */
	public static function items( $cart ) {
		$items = json_decode( (string) $cart->cart_contents, true );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Paged list for the admin screen.
	 *
	 * @param array $args { status, search, per_page, page, orderby, order }.
	 * @return array { rows, total }
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'updated_at',
				'order'    => 'DESC',
			)
		);
		$where = array( '1=1' );
		$vals  = array();

		if ( in_array( $args['status'], self::STATUSES, true ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(phone LIKE %s OR name LIKE %s OR email LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}

		$orderby = in_array( $args['orderby'], array( 'updated_at', 'created_at', 'cart_total', 'abandoned_at' ), true ) ? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = max( 1, (int) $args['per_page'] );
		$offset  = max( 0, ( (int) $args['page'] - 1 ) * $limit );
		$sql_w   = implode( ' AND ', $where );
		$table   = Cartlum_Install::table( 'carts' );

		// The WHERE clause is built only from fixed strings with placeholders, and $order is one of
		// two literals; every value goes through prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql_w}", array_merge( array( $table ), $vals ) ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_w} ORDER BY %i {$order} LIMIT %d OFFSET %d", array_merge( array( $table ), $vals, array( $orderby, $limit, $offset ) ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Headline numbers for the dashboard.
	 *
	 * @param int $days   Look-back window.
	 * @param int $offset Days to shift the window back (30 = the 30 days before the last 30).
	 * @return array
	 */
	public static function stats( $days = 30, $offset = 0 ) {
		global $wpdb;
		$table = Cartlum_Install::table( 'carts' );
		$until = gmdate( 'Y-m-d H:i:s', time() - $offset * DAY_IN_SECONDS );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days + $offset ) * DAY_IN_SECONDS );

		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS n, SUM(cart_total) AS cart_value, SUM(order_total) AS order_value FROM %i WHERE created_at >= %s AND created_at < %s GROUP BY status",
				$table,
				$since,
				$until
			),
			OBJECT_K
		);

		$get = function ( $status, $field ) use ( $by_status ) {
			return isset( $by_status[ $status ] ) ? (float) $by_status[ $status ]->$field : 0;
		};

		// "Abandoned" for the headline means every cart that reached abandonment, whatever happened next.
		$abandoned = $get( 'abandoned', 'n' ) + $get( 'recovered', 'n' ) + $get( 'lost', 'n' ) + $get( 'optout', 'n' );
		$recovered = $get( 'recovered', 'n' );

		return array(
			'days'              => $days,
			'captured'          => array_sum( array_map( 'intval', wp_list_pluck( $by_status, 'n' ) ) ),
			'abandoned'         => (int) $abandoned,
			'abandoned_value'   => $get( 'abandoned', 'cart_value' ) + $get( 'recovered', 'cart_value' ) + $get( 'lost', 'cart_value' ) + $get( 'optout', 'cart_value' ),
			'open'              => (int) $get( 'abandoned', 'n' ),
			'open_value'        => $get( 'abandoned', 'cart_value' ),
			'recovered'         => (int) $recovered,
			'recovered_revenue' => $get( 'recovered', 'order_value' ),
			'converted'         => (int) $get( 'converted', 'n' ),
			'lost'              => (int) ( $get( 'lost', 'n' ) + $get( 'optout', 'n' ) ),
			'recovery_rate'     => $abandoned > 0 ? round( 100 * $recovered / $abandoned, 1 ) : 0,
		);
	}

	/**
	 * The period's carts per day in the store's time zone (per week for 90 days), for the dashboard's
	 * charts: captured, abandoned, recovered and the money recovered, counted by the day the cart
	 * was saved, as stats() counts them.
	 *
	 * @param int $days Look-back window.
	 * @return array[] { start (Y-m-d), label, captured, abandoned, recovered, revenue }, oldest first.
	 */
	public static function daily( $days = 30 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT created_at, status, order_total FROM %i WHERE created_at >= %s ORDER BY id DESC LIMIT 20000',
				Cartlum_Install::table( 'carts' ),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
		$step    = $days > 31 ? 7 : 1;
		$today   = new DateTimeImmutable( 'today', wp_timezone() );
		$first   = $today->modify( '-' . ( $days - 1 ) . ' days' );
		$buckets = array();
		for ( $d = 0; $d < $days; $d += $step ) {
			$start            = $first->modify( '+' . $d . ' days' );
			$buckets[ $d ]    = array(
				'start'     => $start->format( 'Y-m-d' ),
				'label'     => 7 === $step
					/* translators: %s: first day of the week, e.g. 12 Oct */
					? sprintf( __( 'Week of %s', 'cartlum' ), wp_date( 'j M', $start->getTimestamp() ) )
					: wp_date( 'j M', $start->getTimestamp() ),
				'captured'  => 0,
				'abandoned' => 0,
				'recovered' => 0,
				'revenue'   => 0.0,
			);
		}
		foreach ( (array) $rows as $row ) {
			$local = ( new DateTimeImmutable( $row->created_at, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
			$index = (int) floor( ( $local->setTime( 0, 0 )->getTimestamp() - $first->getTimestamp() ) / DAY_IN_SECONDS );
			if ( $index < 0 || $index >= $days ) {
				continue;
			}
			$key = $index - ( $index % $step );
			++$buckets[ $key ]['captured'];
			if ( in_array( $row->status, array( 'abandoned', 'recovered', 'lost', 'optout' ), true ) ) {
				++$buckets[ $key ]['abandoned'];
			}
			if ( 'recovered' === $row->status ) {
				++$buckets[ $key ]['recovered'];
				$buckets[ $key ]['revenue'] += (float) $row->order_total;
			}
		}
		return array_values( $buckets );
	}

	/**
	 * Products most often left in abandoned carts: how many carts, how many of those came back, and
	 * the value left behind. Counts every cart that reached abandonment, whatever happened next.
	 *
	 * @param int $days  Look-back window.
	 * @param int $limit Rows.
	 * @return array[] { product_id, name, carts, recovered, quantity, value }, most carts first.
	 */
	public static function top_products( $days = 30, $limit = 10 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, cart_contents FROM %i WHERE created_at >= %s AND status IN ('abandoned','recovered','lost','optout') ORDER BY id DESC LIMIT 5000",
				Cartlum_Install::table( 'carts' ),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$seen = array();
			foreach ( self::items( $row ) as $item ) {
				$id = (int) ( $item['product_id'] ?? 0 );
				if ( ! $id ) {
					continue;
				}
				if ( ! isset( $out[ $id ] ) ) {
					$out[ $id ] = array(
						'product_id' => $id,
						'name'       => (string) ( $item['name'] ?? '' ),
						'carts'      => 0,
						'recovered'  => 0,
						'quantity'   => 0,
						'value'      => 0.0,
					);
				}
				$out[ $id ]['quantity'] += (int) ( $item['quantity'] ?? 0 );
				$out[ $id ]['value']    += (float) ( $item['line_total'] ?? 0 );
				if ( ! isset( $seen[ $id ] ) ) { // Two variations of one product in a cart count once.
					$seen[ $id ] = true;
					++$out[ $id ]['carts'];
					if ( 'recovered' === $row->status ) {
						++$out[ $id ]['recovered'];
					}
				}
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return array( $b['carts'], $b['value'] ) <=> array( $a['carts'], $a['value'] );
			}
		);
		$out = array_slice( $out, 0, $limit );
		foreach ( $out as &$product ) {
			$current = wc_get_product( $product['product_id'] );
			if ( $current ) {
				$product['name'] = $current->get_name(); // Variation names in carts carry their options.
			}
		}
		unset( $product );
		return $out;
	}
}
