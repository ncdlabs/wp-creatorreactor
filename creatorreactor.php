<?php
/**
 * Plugin Name: FanBridge
 * Plugin URI: https://github.com/ncdlabs/fanbridge
 * Description: FanBridge is a secure, multi-tenant OAuth integration platform for creator products (currently FanVue, with support for additional products such as OnlyFans).
 * Version: 2.0.0
 * Author: ncdLabs
 * Author URI: https://ncdlabs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fanbridge
 * Requires at least: 5.9
 * Requires PHP: 8.1
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FANBRIDGE_VERSION', '2.0.0' );
define( 'FANBRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FANBRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FANBRIDGE_TABLE_ENTITLEMENTS', 'fanbridge_entitlements' );

require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-fanbridge.php';
require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-entitlements.php';
require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-fanvue-oauth.php';
require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-fanvue-client.php';
require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-cron.php';

if ( \FanBridge\Admin_Settings::is_broker_mode() ) {
	require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-broker-client.php';
}

function fanbridge_init() {
	try {
		\FanBridge\FanBridge::bootstrap();
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FanBridge init error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		try {
			\FanBridge\Admin_Settings::set_critical_error(
				__( 'Initialization error:', 'fanbridge' ) . ' ' . $e->getMessage()
			);
		} catch ( \Throwable $inner ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge critical error logging failed: ' . $inner->getMessage() );
			}
		}
	}
}
add_action( 'plugins_loaded', 'fanbridge_init' );

function fanbridge_activate() {
	try {
		FanBridge\Entitlements::create_table();
		FanBridge\Admin_Settings::set_defaults();
		FanBridge\Cron::schedule();
	} catch ( \Throwable $e ) {
		FanBridge\Admin_Settings::set_critical_error(
			__( 'Activation error:', 'fanbridge' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FanBridge activation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
	}
}
register_activation_hook( __FILE__, 'fanbridge_activate' );

function fanbridge_deactivate() {
	FanBridge\Cron::unschedule();
}
register_deactivation_hook( __FILE__, 'fanbridge_deactivate' );

register_uninstall_hook( __FILE__, __DIR__ . '/uninstall.php' );
