<?php
/**
 * Sends anonymized operational metrics to the CreatorReactor metrics edge (Kafka-backed).
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Metrics_Ingest {

	const SOURCE = 'wp-creatorreactor';

	public static function init() {
		add_action( 'creatorreactor_after_scheduled_sync', [ __CLASS__, 'on_after_scheduled_sync' ], 10, 1 );
	}

	/**
	 * @param array<string, mixed> $payload Scheduled sync outcome.
	 */
	public static function on_after_scheduled_sync( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}
		if ( ! self::is_enabled_and_configured() ) {
			return;
		}

		$event = [
			'type' => 'sync.scheduled',
			'ts_ms' => (int) round( microtime( true ) * 1000 ),
			'data' => [
				'success'     => ! empty( $payload['success'] ),
				'duration_ms' => isset( $payload['duration_ms'] ) ? (int) $payload['duration_ms'] : null,
				'completed_at' => isset( $payload['completed_at'] ) ? (int) $payload['completed_at'] : null,
			],
		];

		self::send_batch( [ $event ] );
	}

	/**
	 * @return bool
	 */
	private static function is_enabled_and_configured() {
		$opts = Admin_Settings::get_options();
		if ( empty( $opts['metrics_ingest_enabled'] ) ) {
			return false;
		}
		$url = Admin_Settings::get_metrics_ingest_url_for_requests();
		if ( $url === '' ) {
			return false;
		}
		$token = Admin_Settings::get_metrics_ingest_token_for_requests();
		return $token !== '';
	}

	/**
	 * @param array<int, array<string, mixed>> $events Normalized event objects.
	 */
	private static function send_batch( array $events ) {
		if ( $events === [] ) {
			return;
		}

		$url = Admin_Settings::get_metrics_ingest_url_for_requests();
		$token = Admin_Settings::get_metrics_ingest_token_for_requests();
		if ( $url === '' || $token === '' ) {
			return;
		}

		global $wp_version;

		$body = [
			'source'          => self::SOURCE,
			'site_fp'         => self::site_fingerprint(),
			'plugin_version'  => defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '',
			'wp_version'      => is_string( $wp_version ) ? $wp_version : '',
			'events'          => $events,
		];

		$endpoint = $url . '/v1/ingest';

		wp_remote_post(
			$endpoint,
			[
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				],
				'body'     => wp_json_encode( $body ),
			]
		);
	}

	/**
	 * Stable anonymous site identifier (no raw URL or secrets).
	 *
	 * @return string
	 */
	private static function site_fingerprint() {
		$siteurl = (string) get_option( 'siteurl' );
		$salt    = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : (string) wp_hash( 'metrics' );
		return substr( hash( 'sha256', $salt . '|' . $siteurl ), 0, 16 );
	}
}
