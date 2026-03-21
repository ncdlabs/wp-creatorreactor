<?php
/**
 * Uninstall script for CreatorReactor plugin.
 * Handles cleanup when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_name = 'creatorreactor_settings';
$opts = get_option( $option_name, [] );

// Check if the option to delete records on uninstall is set
if ( ! empty( $opts['delete_records_on_uninstall'] ) ) {
	global $wpdb;
	
	// Delete the entitlements table
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}creatorreactor_entitlements" );
	$old_entitlements = $wpdb->prefix . 'fan' . 'bridge_entitlements';
	$wpdb->query( "DROP TABLE IF EXISTS `{$old_entitlements}`" );
	
	// Delete plugin options
	delete_option( $option_name );
	delete_option( 'creatorreactor_last_error' );
	delete_option( 'creatorreactor_critical_error' );
	delete_option( 'creatorreactor_last_sync' );
	delete_option( 'creatorreactor_connection_test' );
	
	// Delete any transients that might be set
	$legacy = 'fan' . 'bridge';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'%creatorreactor%',
			'%' . $wpdb->esc_like( $legacy ) . '%'
		)
	);
}