<?php
/**
 * Database tables, scheduled jobs, activation and upgrades.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Install.
 */
class Cartlum_Install {

	/**
	 * Activation: tables and the recurring jobs.
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'cartlum_db_version', CARTLUM_DB_VERSION );
		self::schedule();
	}

	/**
	 * Deactivation: stop the recurring jobs. Data stays until uninstall.
	 */
	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'cartlum_sweep' );
			as_unschedule_all_actions( 'cartlum_cleanup' );
		}
		wp_clear_scheduled_hook( 'cartlum_usage_report' );
	}

	/**
	 * Runs on every load; cheap unless the schema version changed.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cartlum_db_version' ) !== CARTLUM_DB_VERSION ) {
			self::activate();
		}
		// Action Scheduler is only ready after init, and the jobs can be lost (e.g. a cleared queue).
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Recurring jobs: the sweep that marks carts abandoned, and the daily cleanup.
	 */
	public static function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! did_action( 'init' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( 'cartlum_sweep', array(), 'cartlum' ) ) {
			as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, 'cartlum_sweep', array(), 'cartlum' );
		} elseif ( function_exists( 'as_get_scheduled_actions' ) && ! get_transient( 'cartlum_sweep_checked' ) ) {
			// Two requests can schedule the sweep at the same moment; keep one (checked hourly).
			set_transient( 'cartlum_sweep_checked', 1, HOUR_IN_SECONDS );
			$pending = as_get_scheduled_actions(
				array(
					'hook'     => 'cartlum_sweep',
					'group'    => 'cartlum',
					'status'   => ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 5,
				),
				'ids'
			);
			if ( count( $pending ) > 1 ) {
				as_unschedule_all_actions( 'cartlum_sweep', array(), 'cartlum' );
				as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, 'cartlum_sweep', array(), 'cartlum' );
			}
		}
		if ( ! as_has_scheduled_action( 'cartlum_cleanup', array(), 'cartlum' ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'cartlum_cleanup', array(), 'cartlum' );
		}
	}

	/**
	 * Table names.
	 *
	 * @param string $which carts|events.
	 * @return string
	 */
	public static function table( $which ) {
		global $wpdb;
		return $wpdb->prefix . 'cartlum_' . $which;
	}

	/**
	 * Creates or updates the tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();
		$carts   = self::table( 'carts' );
		$events  = self::table( 'events' );

		// status: active -> abandoned -> recovered (ordered after we contacted them) | converted
		// (ordered without any contact from us) | lost (gave up / expired) | optout.
		dbDelta(
			"CREATE TABLE {$carts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token char(32) NOT NULL,
			session_key varchar(100) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phone varchar(20) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			cart_contents longtext NOT NULL,
			cart_total decimal(19,4) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			coupon_code varchar(60) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_total decimal(19,4) NOT NULL DEFAULT 0,
			source varchar(20) NOT NULL DEFAULT '',
			locale varchar(20) NOT NULL DEFAULT '',
			contact_count int(10) unsigned NOT NULL DEFAULT 0,
			call_outcome varchar(30) NOT NULL DEFAULT '',
			flow_step smallint(5) unsigned NOT NULL DEFAULT 0,
			flow_stopped tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			abandoned_at datetime DEFAULT NULL,
			contacted_at datetime DEFAULT NULL,
			closed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY session_key (session_key),
			KEY phone (phone),
			KEY status_updated (status,updated_at),
			KEY created_at (created_at)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cart_id bigint(20) unsigned NOT NULL,
			type varchar(40) NOT NULL,
			channel varchar(20) NOT NULL DEFAULT '',
			message text NULL,
			meta longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY cart_id (cart_id),
			KEY type_created (type,created_at)
			) {$collate};"
		);
	}
}
