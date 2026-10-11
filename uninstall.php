<?php
/**
 * Uninstall: removes Cartlum's data only if the owner chose that in Settings → Privacy.
 *
 * @package Cartlum
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cartlum_settings = get_option( 'cartlum_settings', array() );
if ( ! is_array( $cartlum_settings ) || empty( $cartlum_settings['delete_on_uninstall'] ) || 'yes' !== $cartlum_settings['delete_on_uninstall'] ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cartlum_events" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cartlum_carts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'cartlum_settings' );
delete_option( 'cartlum_db_version' );
delete_option( 'cartlum_optouts' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'cartlum' );
}
