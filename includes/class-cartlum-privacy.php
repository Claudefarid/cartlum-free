<?php
/**
 * WordPress privacy tools: export and erase a person's saved carts, plus the policy text.
 *
 * WordPress identifies people by email in these tools, so carts saved with a phone number only
 * are not found here; they are removed by the retention setting instead.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cartlum's own tables: WordPress has no API for them, and the rows change on every checkout, so an object cache would serve stale carts.

/**
 * Cartlum_Privacy.
 */
class Cartlum_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['cartlum'] = array(
			'exporter_friendly_name' => __( 'Cartlum saved carts', 'cartlum' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['cartlum'] = array(
			'eraser_friendly_name' => __( 'Cartlum saved carts', 'cartlum' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Carts for an email.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	private static function carts_for( $email ) {
		global $wpdb;
		$table = Cartlum_Install::table( 'carts' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $table, $email ) );
	}

	/**
	 * Export callback.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$data = array();
		foreach ( self::carts_for( $email ) as $cart ) {
			$data[] = array(
				'group_id'    => 'cartlum',
				'group_label' => __( 'Saved carts', 'cartlum' ),
				'item_id'     => 'cartlum-' . $cart->id,
				'data'        => array(
					array(
						'name'  => __( 'Phone', 'cartlum' ),
						'value' => Cartlum_Phone::display( $cart->phone ),
					),
					array(
						'name'  => __( 'Name', 'cartlum' ),
						'value' => $cart->name,
					),
					array(
						'name'  => __( 'Items', 'cartlum' ),
						'value' => Cartlum_Messages::items_text( $cart ),
					),
					array(
						'name'  => __( 'Saved', 'cartlum' ),
						'value' => $cart->created_at . ' UTC',
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase callback.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		$carts = self::carts_for( $email );
		Cartlum_Carts::delete( wp_list_pluck( $carts, 'id' ) );
		return array(
			'items_removed'  => count( $carts ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Cartlum',
			wp_kses_post(
				wpautop(
					__( 'When you enter your phone number or email address at checkout, we save it together with the contents of your cart before you place the order, so that we can help you finish the order if it does not go through. We may contact you by SMS, phone, WhatsApp or email about that cart. Every message has a link to stop further reminders. Saved carts are deleted automatically after a set period.', 'cartlum' )
				)
			)
		);
	}
}
