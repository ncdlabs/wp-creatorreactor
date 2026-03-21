<?php
/**
 * WP-Cron: scheduled subscriber and follower sync.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron {

	const HOOK = 'creatorreactor_sync';

	public static function init() {
		add_action( self::HOOK, [ __CLASS__, 'run_sync' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'add_interval' ] );
	}

	public static function schedule() {
		wp_clear_scheduled_hook( 'fan' . 'bridge_sync' );

		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		$opts = Admin_Settings::get_options();
		$mins = (int) ( $opts['cron_interval_minutes'] ?? 15 );
		$mins = max( 5, $mins );

		wp_schedule_event( time(), 'creatorreactor_' . $mins . 'min', self::HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( 'fan' . 'bridge_sync' );
	}

	public static function add_interval( $schedules ) {
		$opts = Admin_Settings::get_options();
		$mins = (int) ( $opts['cron_interval_minutes'] ?? 15 );
		$mins = max( 5, $mins );
		$key  = 'creatorreactor_' . $mins . 'min';

		$schedules[ $key ] = [
			'interval' => $mins * 60,
			'display'  => sprintf(
				esc_html__( 'Every %d minutes', 'creatorreactor' ),
				$mins
			),
		];

		return $schedules;
	}

	public static function run_sync() {
		try {
			if ( Plugin::is_broker_mode() ) {
				return;
			}

			$opts   = Admin_Settings::get_options();
			$ttl    = (int) ( $opts['entitlement_cache_ttl_seconds'] ?? 900 );
			$client = new CreatorReactor_Client();
			$ok     = $client->sync_subscribers_to_table( $ttl );

			update_option( Admin_Settings::OPTION_LAST_SYNC, [ 'time' => time(), 'success' => $ok ] );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor run_sync error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			Admin_Settings::set_last_error( 'Sync failed: ' . $e->getMessage() );
			update_option( Admin_Settings::OPTION_LAST_SYNC, [ 'time' => time(), 'success' => false ] );
		}
	}
}
