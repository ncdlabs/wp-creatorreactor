<?php
/**
 * Core plugin bootstrap (loaded from creatorreactor.php).
 * Supports both Direct and Broker modes.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	const MODE_BROKER = 'broker';
	const MODE_DIRECT = 'direct';

	public static function init() {
		add_action( 'plugins_loaded', [ __CLASS__, 'bootstrap' ] );
	}

	public static function bootstrap() {
		try {
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-entitlements.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-admin-settings.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-oauth.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-client.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-cron.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-broker-client.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-fan-oauth.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-shortcodes.php';

			Entitlements::maybe_migrate_fanvue_product_key();
			Entitlements::maybe_migrate_legacy_follower_tier_stored();

			CreatorReactor_OAuth::init();
			Fan_OAuth::init();
			Cron::init();
			Admin_Settings::init();
			Shortcodes::init();

			// Broker REST callback must register in all modes so OAuth redirects to
			// .../broker-callback never hit rest_no_route (e.g. Fanvue app URI vs mode mismatch).
			Broker_Client::init();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			try {
				Admin_Settings::set_critical_error(
					__( 'Bootstrap error:', 'creatorreactor' ) . ' ' . $e->getMessage()
				);
			} catch ( \Throwable $inner ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CreatorReactor error logging failed: ' . $inner->getMessage() );
				}
			}
		}
	}

	public static function is_broker_mode() {
		return Admin_Settings::is_broker_mode();
	}

	public static function is_direct_mode() {
		return ! self::is_broker_mode();
	}

	public static function is_connected() {
		return Admin_Settings::is_connected();
	}

	public static function get_profile() {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_profile();
		}
		$client = new CreatorReactor_Client();
		return $client->get_profile();
	}

	public static function get_subscribers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_subscribers( $page, $size );
		}
		$client = new CreatorReactor_Client();
		return $client->list_subscribers( $page, $size );
	}

	public static function get_followers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_followers( $page, $size );
		}
		$client = new CreatorReactor_Client();
		return $client->list_followers( $page, $size );
	}
}
