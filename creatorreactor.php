<?php
/**
 * Plugin Name: CreatorReactor
 * Plugin URI: https://github.com/ncdlabs/creatorreactor
 * Description: OAuth integration, sync, and entitlements for creator products (CreatorReactor, with support for additional products such as OnlyFans).
 * Version: 2.0.1
 * Author: ncdLabs
 * Author URI: https://ncdlabs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: creatorreactor
 * Requires at least: 5.9
 * Requires PHP: 8.1
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CREATORREACTOR_VERSION', '2.0.1' );
define( 'CREATORREACTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREATORREACTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CREATORREACTOR_TABLE_ENTITLEMENTS', 'creatorreactor_entitlements' );

require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor.php';
require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-entitlements.php';
require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-oauth.php';
require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-client.php';
require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-cron.php';

require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-broker-client.php';

function creatorreactor_init() {
	try {
		\CreatorReactor\Plugin::bootstrap();
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CreatorReactor init error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		try {
			\CreatorReactor\Admin_Settings::set_critical_error(
				__( 'Initialization error:', 'creatorreactor' ) . ' ' . $e->getMessage()
			);
		} catch ( \Throwable $inner ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor critical error logging failed: ' . $inner->getMessage() );
			}
		}
	}
}
add_action( 'plugins_loaded', 'creatorreactor_init' );

function creatorreactor_activate() {
	try {
		CreatorReactor\Entitlements::create_table();
		CreatorReactor\Admin_Settings::set_defaults();
		CreatorReactor\Cron::schedule();
	} catch ( \Throwable $e ) {
		CreatorReactor\Admin_Settings::set_critical_error(
			__( 'Activation error:', 'creatorreactor' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CreatorReactor activation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
	}
}
register_activation_hook( __FILE__, 'creatorreactor_activate' );

function creatorreactor_deactivate() {
	CreatorReactor\Cron::unschedule();
}
register_deactivation_hook( __FILE__, 'creatorreactor_deactivate' );

register_uninstall_hook( __FILE__, __DIR__ . '/uninstall.php' );
