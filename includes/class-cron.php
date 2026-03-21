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
		/*
		 * Ensure the recurring event exists on every load. Activation runs before Cron::init()
		 * registers the custom interval, so wp_schedule_event() can fail silently then.
		 */
		add_action( 'init', [ __CLASS__, 'schedule' ], 20 );
		add_action( 'updated_option', [ __CLASS__, 'maybe_reschedule_on_settings_update' ], 10, 3 );
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

	/**
	 * When the stored sync interval changes, drop the old recurring event and register a new one.
	 * Runs on updated_option so the option row already contains the new minutes when cron_schedules runs.
	 *
	 * @param string $option      Option name.
	 * @param mixed  $old_value   Previous value.
	 * @param mixed  $value       New value.
	 */
	public static function maybe_reschedule_on_settings_update( $option, $old_value, $value ) {
		if ( $option !== Admin_Settings::OPTION_NAME ) {
			return;
		}
		if ( ! is_array( $value ) ) {
			return;
		}
		$new_m = isset( $value['cron_interval_minutes'] ) ? max( 5, (int) $value['cron_interval_minutes'] ) : 15;
		$old_m = 15;
		if ( is_array( $old_value ) ) {
			$old_m = isset( $old_value['cron_interval_minutes'] ) ? max( 5, (int) $old_value['cron_interval_minutes'] ) : 15;
		}
		if ( $new_m === $old_m ) {
			return;
		}
		self::clear_and_reschedule_with_interval( $new_m );
	}

	/**
	 * Clear the sync hook and schedule a new recurring event with the given interval (minutes).
	 *
	 * @param int $mins Interval in minutes (clamped to at least 5).
	 */
	public static function clear_and_reschedule_with_interval( $mins ) {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( 'fan' . 'bridge_sync' );
		$mins = max( 5, (int) $mins );
		wp_schedule_event( time(), 'creatorreactor_' . $mins . 'min', self::HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( 'fan' . 'bridge_sync' );
	}

	/**
	 * Localized date/time string for the next WP-Cron run of this sync, or null if nothing is scheduled.
	 *
	 * @return string|null
	 */
	public static function get_next_sync_datetime_for_display() {
		$ts = wp_next_scheduled( self::HOOK );
		if ( ! $ts ) {
			return null;
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$ts
		);
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

	/**
	 * Pull subscribers and followers from the API into the entitlements table (direct or broker mode).
	 *
	 * @return bool True if at least one list request succeeded (same semantics as {@see CreatorReactor_Client::sync_subscribers_to_table_with_listers}).
	 */
	public static function sync_entitlements_now() {
		$opts = Admin_Settings::get_options();
		$ttl  = (int) ( $opts['entitlement_cache_ttl_seconds'] ?? 900 );

		if ( Plugin::is_broker_mode() ) {
			return CreatorReactor_Client::sync_subscribers_to_table_with_listers(
				$ttl,
				function ( $page, $size ) {
					return CreatorReactor_Client::normalize_broker_list_response( Broker_Client::get_subscribers( $page, $size ) );
				},
				function ( $page, $size ) {
					return CreatorReactor_Client::normalize_broker_list_response( Broker_Client::get_followers( $page, $size ) );
				}
			);
		}

		$client = new CreatorReactor_Client();
		return $client->sync_subscribers_to_table( $ttl );
	}

	public static function run_sync() {
		try {
			$ok = self::sync_entitlements_now();
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
