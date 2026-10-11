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
	 * Status filter links with counts.
	 *
	 * @return array
	 */
	protected function get_views() {
		global $wpdb;
		$table   = Cartlum_Install::table( 'carts' );
		$counts  = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', $table ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cartlum's own table; counts must be live.
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all     = array_sum( array_map( 'intval', wp_list_pluck( $counts, 'n' ) ) );
		$views   = array(
			'all' => sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( Cartlum_Admin::url( 'cartlum-carts' ) ), '' === $current ? ' class="current"' : '', esc_html__( 'All', 'cartlum' ), $all ),
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
			$n = isset( $counts[ $status ] ) ? (int) $counts[ $status ]->n : 0;
			if ( ! $n ) {
				continue;
			}
			$views[ $status ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( Cartlum_Admin::url( 'cartlum-carts', array( 'status' => $status ) ) ), $current === $status ? ' class="current"' : '', esc_html( $label ), $n );
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
		$result   = Cartlum_Carts::query(
			array(
				'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
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
