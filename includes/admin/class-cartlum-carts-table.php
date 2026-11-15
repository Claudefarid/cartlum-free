<?php
/**
 * The carts list.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Cartlum_Carts_Table.
 */
class Cartlum_Carts_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'cart',
				'plural'   => 'carts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'customer' => __( 'Customer', 'cartlum' ),
			'items'    => __( 'Items', 'cartlum' ),
			'value'    => __( 'Value', 'cartlum' ),
			'status'   => __( 'Status', 'cartlum' ),
			'updated'  => __( 'Last activity', 'cartlum' ),
			'contact'  => __( 'Contacted', 'cartlum' ),
			'actions'  => __( 'Reach out', 'cartlum' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'value'   => array( 'cart_total', true ),
			'updated' => array( 'updated_at', true ),
		);
	}

	/**
	 * The period and product chosen above the list.
	 *
	 * @return array { period: today|7|30|all, product: int, since: UTC or '' }
	 */
	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
		$period  = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'all';
		$period  = in_array( $period, array( 'today', '7', '30' ), true ) ? $period : 'all';
		$product = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return array(
			'period'  => $period,
			'product' => $product,
			'since'   => Cartlum_Carts::period_since( $period ),
		);
	}

	/**
	 * The list's address with its filters, changed by $args.
	 *
	 * @param array $args Query args to change ('' removes one).
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$f    = self::filters();
		$args = array_merge(
			array(
				'status'  => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'period'  => 'all' === $f['period'] ? '' : $f['period'],
				'product' => $f['product'] ? $f['product'] : '',
			),
			$args
		);
		return Cartlum_Admin::url( 'cartlum-carts', array_filter( $args, 'strlen' ) );
	}

	/**
	 * How many carts match the period and product, by status.
	 *
	 * @param string $since   UTC or ''.
	 * @param int    $product Product id or 0.
	 * @return int[] status => count
	 */
	public static function counts( $since, $product ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array( Cartlum_Install::table( 'carts' ) );
		if ( '' !== $since ) {
			$where[] = 'created_at >= %s';
			$vals[]  = $since;
		}
		if ( $product ) {
			$where[] = 'cart_contents LIKE %s';
			$vals[]  = '%' . $wpdb->esc_like( '"product_id":' . (int) $product . ',' ) . '%';
		}
		$sql_w = implode( ' AND ', $where );
		// Cartlum's own table, counts must be live. The WHERE clause is built only from fixed strings
		// with placeholders; every value goes through prepare().
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM %i WHERE {$sql_w} GROUP BY status", $vals ), OBJECT_K );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return array_map( 'intval', wp_list_pluck( (array) $rows, 'n' ) );
	}

	/**
	 * Status filter links with counts (within the chosen period and product).
	 *
	 * @return array
	 */
	protected function get_views() {
		$f       = self::filters();
		$counts  = self::counts( $f['since'], $f['product'] );
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all     = array_sum( $counts );
		$views   = array(
			'all' => sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( self::list_url( array( 'status' => '' ) ) ), '' === $current ? ' class="current"' : '', esc_html__( 'All', 'cartlum' ), $all ),
		);
		$names = array(
			'abandoned' => __( 'Abandoned', 'cartlum' ),
			'active'    => __( 'Checking out', 'cartlum' ),
			'recovered' => __( 'Recovered', 'cartlum' ),
			'converted' => __( 'Ordered', 'cartlum' ),
			'lost'      => __( 'Lost', 'cartlum' ),
			'optout'    => __( 'Unsubscribed', 'cartlum' ),
		);
		foreach ( $names as $status => $label ) {
			$n = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
			if ( ! $n ) {
				continue;
			}
			$views[ $status ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( self::list_url( array( 'status' => $status ) ) ), $current === $status ? ' class="current"' : '', esc_html( $label ), $n );
		}
		return $views;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'lost'   => __( 'Mark as lost', 'cartlum' ),
			'delete' => __( 'Delete', 'cartlum' ),
		);
	}

	/**
	 * Loads the rows.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$f        = self::filters();
		$result   = Cartlum_Carts::query(
			array(
				'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'since'    => $f['since'],
				'product'  => $f['product'],
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'updated_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="cart[]" value="' . esc_attr( $item->id ) . '" />';
	}

	/**
	 * Customer.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_customer( $item ) {
		$url  = Cartlum_Admin::url( 'cartlum-carts', array( 'cart' => $item->id ) );
		$name = '' !== $item->name ? $item->name : __( '(no name)', 'cartlum' );
		$out  = '<a class="row-title" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a> ' . wp_kses_post( apply_filters( 'cartlum_customer_badges', '', $item ) );
		if ( $item->phone ) {
			$out .= '<br><span class="cl-muted">' . esc_html( Cartlum_Phone::display( $item->phone ) ) . '</span>';
		}
		if ( $item->email && is_email( $item->email ) ) {
			$out .= '<br><span class="cl-muted">' . esc_html( $item->email ) . '</span>';
		}
		return $out;
	}

	/**
	 * Items.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_items( $item ) {
		return esc_html( wp_trim_words( Cartlum_Messages::items_text( $item ), 12 ) );
	}

	/**
	 * Value.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_value( $item ) {
		$out = Cartlum_Admin::money( $item->cart_total, $item->currency );
		if ( $item->order_id ) {
			$out .= '<br><a class="cl-muted" href="' . esc_url( Cartlum_Admin::order_url( $item->order_id ) ) . '">' . esc_html( sprintf( /* translators: %d: order number */ __( 'Order #%d', 'cartlum' ), $item->order_id ) ) . '</a>';
		}
		return $out;
	}

	/**
	 * Status.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_status( $item ) {
		return Cartlum_Admin::badge( $item->status );
	}

	/**
	 * Last activity.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_updated( $item ) {
		return esc_html( Cartlum_Admin::ago( $item->updated_at ) );
	}

	/**
	 * Contact count.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_contact( $item ) {
		if ( ! (int) $item->contact_count ) {
			return '<span class="cl-muted">' . esc_html__( 'Not yet', 'cartlum' ) . '</span>';
		}
		/* translators: %d: number of times */
		$out = esc_html( sprintf( _n( '%d time', '%d times', (int) $item->contact_count, 'cartlum' ), (int) $item->contact_count ) );
		if ( $item->call_outcome ) {
			$out .= '<br><span class="cl-muted">' . esc_html( Cartlum_Admin_Actions::call_outcomes()[ $item->call_outcome ] ?? $item->call_outcome ) . '</span>';
		}
		return $out;
	}

	/**
	 * Quick actions: call, WhatsApp, open.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_actions( $item ) {
		$name = '' !== $item->name ? $item->name : Cartlum_Phone::display( $item->phone );
		$out  = '<span class="cl-person__actions">';
		if ( $item->phone ) {
			/* translators: %s: customer name */
			$out .= '<a class="cl-icon-btn" href="' . esc_url( 'tel:+' . $item->phone ) . '" aria-label="' . esc_attr( sprintf( __( 'Call %s', 'cartlum' ), $name ) ) . '"><span class="dashicons dashicons-phone" aria-hidden="true"></span></a>';
			/* translators: %s: customer name */
			$out .= '<a class="cl-icon-btn" target="_blank" rel="noopener" href="' . esc_url( Cartlum_Admin_Actions::whatsapp_url( $item ) ) . '" aria-label="' . esc_attr( sprintf( __( 'WhatsApp %s', 'cartlum' ), $name ) ) . '"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span></a>';
		}
		return $out . '</span>';
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		echo '<div class="cl-empty"><p><strong>' . esc_html__( 'No carts here yet.', 'cartlum' ) . '</strong></p><p>' . esc_html__( 'A cart appears as soon as a customer types a phone number or email on the checkout page — before they place the order.', 'cartlum' ) . '</p></div>';
	}
}
