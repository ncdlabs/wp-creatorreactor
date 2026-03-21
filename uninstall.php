<?php
/**
 * Uninstall script for FanBridge plugin.
 * Handles cleanup when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_name = 'fanvue_settings';
$opts = get_option( $option_name, [] );

// Check if the option to delete records on uninstall is set
if ( ! empty( $opts['delete_records_on_uninstall'] ) ) {
	global $wpdb;
	
	// Delete the entitlements table
	$table_name = $wpdb->prefix . 'fanbridge_entitlements';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	
	// Delete plugin options
	delete_option( $option_name );
	delete_option( 'fanbridge_last_error' );
	delete_option( 'fanbridge_critical_error' );
	delete_option( 'fanbridge_last_sync' );
	delete_option( 'fanbridge_connection_test' );
	
	// Delete any transients that might be set
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%fanbridge%'" );
}