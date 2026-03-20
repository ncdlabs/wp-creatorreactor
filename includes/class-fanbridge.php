<?php
/**
 * FanBridge - Unified Plugin
 * Supports both Direct and Broker modes
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace FanBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FanBridge {

	const MODE_BROKER = 'broker';
	const MODE_DIRECT = 'direct';

	public static function init() {
		add_action( 'plugins_loaded', [ __CLASS__, 'bootstrap' ] );
	}

	public static function bootstrap() {
		try {
			require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-entitlements.php';
			require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-admin-settings.php';
			require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-fanvue-oauth.php';
			require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-fanvue-client.php';
			require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-cron.php';

			Fanvue_OAuth::init();
			Cron::init();
			Admin_Settings::init();

			if ( Admin_Settings::is_broker_mode() ) {
				require_once FANBRIDGE_PLUGIN_DIR . 'includes/class-broker-client.php';
				Broker_Client::init();
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			try {
				Admin_Settings::set_critical_error(
					__( 'Bootstrap error:', 'fanbridge' ) . ' ' . $e->getMessage()
				);
			} catch ( \Throwable $inner ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FanBridge error logging failed: ' . $inner->getMessage() );
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
		$client = new Fanvue_Client();
		return $client->get_profile();
	}

	public static function get_subscribers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_subscribers( $page, $size );
		}
		$client = new Fanvue_Client();
		return $client->list_subscribers( $page, $size );
	}

	public static function get_followers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_followers( $page, $size );
		}
		$client = new Fanvue_Client();
		return $client->list_followers( $page, $size );
	}
}
