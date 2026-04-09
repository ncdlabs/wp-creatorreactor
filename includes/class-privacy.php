<?php
/**
 * WordPress privacy exporters/erasers for CreatorReactor data.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privacy {

	const ROWS_PER_PAGE          = 200;
	const CRON_HOOK_RETENTION_PURGE = 'creatorreactor_privacy_retention_purge';

	/**
	 * User meta keys that can contain personal data controlled by this plugin.
	 *
	 * @return string[]
	 */
	private static function user_meta_keys() {
		return [
			Onboarding::META_PHONE,
			Onboarding::META_ADDRESS,
			Onboarding::META_COUNTRY,
			Onboarding::META_CONTACT_PREF,
			Onboarding::META_OPT_OUT_EMAILS,
			Onboarding::META_TOS_ACCEPTED,
			Onboarding::META_TOS_ACCEPTED_AT,
			Onboarding::META_FANVUE_OAUTH_LINKED,
			Onboarding::META_FANVUE_PROFILE_SNAPSHOT,
			Onboarding::META_COMPLETE,
			Entitlements::USERMETA_CREATORREACTOR_UUID,
			Entitlements::USERMETA_SOCIAL_ENTITLEMENTS,
			CreatorReactor_OAuth::USERMETA_FAN_OAUTH_TOKENS,
			Fan_OAuth::USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT,
			'fanvue_id',
			'isFanvueCreator',
			'fanvueAccountCreatedAt',
			'fanvueAccountUpdatedAt',
			'avatarUrl',
			'bannerUrl',
		];
	}

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
		add_action( self::CRON_HOOK_RETENTION_PURGE, [ __CLASS__, 'run_retention_purge' ] );
		add_action( 'init', [ __CLASS__, 'ensure_retention_purge_scheduled' ], 20 );
		add_action( 'admin_init', [ __CLASS__, 'register_privacy_policy_content' ] );
	}

	/**
	 * Add plugin-specific privacy policy guidance in WordPress privacy policy tools.
	 *
	 * @return void
	 */
	public static function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__(
			'CreatorReactor stores account linkage and entitlement data needed to grant content access, including identifiers (email, external UUID), entitlement status/tier, and selected onboarding profile fields.',
			'wp-creatorreactor'
		) . '</p>';
		$content .= '<p>' . esc_html__(
			'CreatorReactor also stores encrypted OAuth tokens for configured integrations and operational logs (connection and sync logs) with retention limits configured in plugin settings.',
			'wp-creatorreactor'
		) . '</p>';
		$content .= '<p>' . esc_html__(
			'For fan onboarding, a short-lived essential cookie may be used to complete OAuth redirect flows. This cookie is HttpOnly, SameSite=Lax, and expires automatically.',
			'wp-creatorreactor'
		) . '</p>';
		$content .= '<p>' . esc_html__(
			'Data exports and erasure requests can be handled via WordPress Tools > Export Personal Data and Tools > Erase Personal Data.',
			'wp-creatorreactor'
		) . '</p>';
		wp_add_privacy_policy_content( 'CreatorReactor', wp_kses_post( $content ) );
	}

	/**
	 * Clear privacy retention cron jobs.
	 */
	public static function unschedule_retention_purge() {
		wp_clear_scheduled_hook( self::CRON_HOOK_RETENTION_PURGE );
	}

	/**
	 * Ensure daily privacy retention purge runs.
	 */
	public static function ensure_retention_purge_scheduled() {
		if ( wp_next_scheduled( self::CRON_HOOK_RETENTION_PURGE ) ) {
			return;
		}
		wp_schedule_event( time() + 120, 'daily', self::CRON_HOOK_RETENTION_PURGE );
	}

	/**
	 * Return log retention days from options/filters.
	 *
	 * @return int
	 */
	private static function log_retention_days() {
		$opts = Admin_Settings::get_options();
		$days = isset( $opts['privacy_log_retention_days'] ) ? (int) $opts['privacy_log_retention_days'] : 30;
		$days = max( 1, $days );
		return (int) apply_filters( 'creatorreactor_privacy_log_retention_days', $days );
	}

	/**
	 * Return profile snapshot retention days from options/filters.
	 *
	 * @return int
	 */
	private static function profile_snapshot_retention_days() {
		$opts = Admin_Settings::get_options();
		$days = isset( $opts['privacy_profile_snapshot_retention_days'] ) ? (int) $opts['privacy_profile_snapshot_retention_days'] : 90;
		$days = max( 1, $days );
		return (int) apply_filters( 'creatorreactor_privacy_profile_snapshot_retention_days', $days );
	}

	/**
	 * Remove old personal data based on configured retention windows.
	 *
	 * @return void
	 */
	public static function run_retention_purge() {
		self::purge_old_log_entries();
		self::purge_stale_profile_snapshots();
		self::purge_expired_pending_registration_options();
	}

	/**
	 * @param array<string, mixed> $exporters Existing exporters.
	 * @return array<string, mixed>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['creatorreactor-data'] = [
			'exporter_friendly_name' => __( 'CreatorReactor Data', 'wp-creatorreactor' ),
			'callback'               => [ __CLASS__, 'export_personal_data' ],
		];
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers Existing erasers.
	 * @return array<string, mixed>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['creatorreactor-data'] = [
			'eraser_friendly_name' => __( 'CreatorReactor Data', 'wp-creatorreactor' ),
			'callback'             => [ __CLASS__, 'erase_personal_data' ],
		];
		return $erasers;
	}

	/**
	 * WordPress personal data export callback.
	 *
	 * @param string   $email_address Data subject email.
	 * @param int|null $page          1-based page number.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export_personal_data( $email_address, $page = 1 ) {
		$page      = max( 1, (int) $page );
		$email     = sanitize_email( (string) $email_address );
		$data      = [];
		$offset    = ( $page - 1 ) * self::ROWS_PER_PAGE;
		$remaining = 0;

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			foreach ( self::user_meta_keys() as $key ) {
				$values = get_user_meta( (int) $user->ID, $key, false );
				if ( ! is_array( $values ) || $values === [] ) {
					continue;
				}
				foreach ( $values as $value ) {
					$data[] = [
						'group_id'    => 'creatorreactor-user-meta',
						'group_label' => __( 'CreatorReactor User Meta', 'wp-creatorreactor' ),
						'item_id'     => 'creatorreactor-user-' . (int) $user->ID,
						'data'        => [
							[
								'name'  => $key,
								'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
							],
						],
					];
				}
			}
		}

		$entitlements_result = self::export_entitlements_page( $email, $user, $offset, self::ROWS_PER_PAGE );
		$data                = array_merge( $data, $entitlements_result['items'] );
		$remaining          += (int) $entitlements_result['remaining'];

		$pending_result = self::export_pending_options_page( $email, $offset, self::ROWS_PER_PAGE );
		$data           = array_merge( $data, $pending_result['items'] );
		$remaining     += (int) $pending_result['remaining'];

		return [
			'data' => $data,
			'done' => $remaining <= 0,
		];
	}

	/**
	 * WordPress personal data erase callback.
	 *
	 * @param string   $email_address Data subject email.
	 * @param int|null $page          1-based page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase_personal_data( $email_address, $page = 1 ) {
		$email         = sanitize_email( (string) $email_address );
		$items_removed = false;
		$messages      = [];

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			foreach ( self::user_meta_keys() as $key ) {
				$deleted = delete_user_meta( (int) $user->ID, $key );
				if ( $deleted ) {
					$items_removed = true;
				}
			}
		}

		$deleted_rows = self::erase_entitlements( $email, $user );
		if ( $deleted_rows > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d is the number of entitlements rows removed. */
				__( 'Removed %d entitlement rows.', 'wp-creatorreactor' ),
				$deleted_rows
			);
		}

		$deleted_pending = self::erase_pending_options( $email );
		if ( $deleted_pending > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d is the number of pending registration rows removed. */
				__( 'Removed %d pending registration rows.', 'wp-creatorreactor' ),
				$deleted_pending
			);
		}

		$removed_log_entries = self::erase_logs_by_email( $email );
		if ( $removed_log_entries > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d is the number of log entries redacted/removed. */
				__( 'Removed %d log entries linked to this email.', 'wp-creatorreactor' ),
				$removed_log_entries
			);
		}

		return [
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		];
	}

	/**
	 * @param string          $email Subject email.
	 * @param \WP_User|false  $user  Matched WP user.
	 * @param int             $offset SQL offset.
	 * @param int             $limit SQL limit.
	 * @return array{items: array<int, array<string, mixed>>, remaining: int}
	 */
	private static function export_entitlements_page( $email, $user, $offset, $limit ) {
		global $wpdb;
		$table = Entitlements::get_table_name();
		$uid   = ( $user instanceof \WP_User ) ? (int) $user->ID : 0;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product, email, display_name, fanvue_user_uuid, creatorreactor_uuid, creatorreactor_user_uuid, status, tier, expires_at, updated_at, fanvue_sync_snapshot FROM {$table} WHERE email = %s OR wp_user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$email,
				$uid,
				$limit,
				$offset
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE email = %s OR wp_user_id = %d",
				$email,
				$uid
			)
		);

		$items = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$ent_id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$item_row = is_array( $row ) ? $row : [];
				$item     = [];
				foreach ( $item_row as $name => $value ) {
					$item[] = [
						'name'  => (string) $name,
						'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
					];
				}
				$items[] = [
					'group_id'    => 'creatorreactor-entitlements',
					'group_label' => __( 'CreatorReactor Entitlements', 'wp-creatorreactor' ),
					'item_id'     => 'creatorreactor-entitlement-' . $ent_id,
					'data'        => $item,
				];
			}
		}

		$remaining = max( 0, $total - ( $offset + $limit ) );
		return [
			'items'     => $items,
			'remaining' => $remaining,
		];
	}

	/**
	 * Export pending registration option rows that contain this email address.
	 *
	 * @param string $email Subject email.
	 * @param int    $offset SQL offset.
	 * @param int    $limit SQL limit.
	 * @return array{items: array<int, array<string, mixed>>, remaining: int}
	 */
	private static function export_pending_options_page( $email, $offset, $limit ) {
		global $wpdb;
		$options_table = $wpdb->options;
		$like          = 'creatorreactor_fv_pen_%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$options_table} WHERE option_name LIKE %s LIMIT %d OFFSET %d",
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);

		$items    = [];
		$matches  = 0;
		$scanned  = is_array( $rows ) ? $rows : [];
		foreach ( $scanned as $row ) {
			$option_name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			$raw         = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( ! is_array( $raw ) || empty( $raw['data'] ) || ! is_array( $raw['data'] ) ) {
				continue;
			}
			$row_email = isset( $raw['data']['email'] ) ? sanitize_email( (string) $raw['data']['email'] ) : '';
			if ( $row_email === '' || strtolower( $row_email ) !== strtolower( $email ) ) {
				continue;
			}

			++$matches;
			$items[] = [
				'group_id'    => 'creatorreactor-pending',
				'group_label' => __( 'CreatorReactor Pending Registration', 'wp-creatorreactor' ),
				'item_id'     => 'creatorreactor-pending-' . $option_name,
				'data'        => [
					[
						'name'  => 'option_name',
						'value' => $option_name,
					],
					[
						'name'  => 'payload',
						'value' => wp_json_encode( $raw ),
					],
				],
			];
		}

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$options_table} WHERE option_name LIKE %s",
				$like
			)
		);
		$remaining = max( 0, $total - ( $offset + $limit ) );
		// Remaining should consider only matching rows, but unknown without scanning all rows.
		// Use scanned-page completion signal from total pool to keep callback deterministic.
		if ( $matches === 0 && $remaining > 0 ) {
			$remaining = $remaining;
		}

		return [
			'items'     => $items,
			'remaining' => $remaining,
		];
	}

	/**
	 * @param string         $email Subject email.
	 * @param \WP_User|false $user  Matched WP user.
	 * @return int Number of deleted rows.
	 */
	private static function erase_entitlements( $email, $user ) {
		global $wpdb;
		$table = Entitlements::get_table_name();
		$uid   = ( $user instanceof \WP_User ) ? (int) $user->ID : 0;

		$table_sql = esc_sql( $table );
		if ( $uid > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table_sql}` WHERE email = %s OR wp_user_id = %d",
					$email,
					$uid
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table_sql}` WHERE email = %s",
					$email
				)
			);
		}
		return (int) $wpdb->rows_affected;
	}

	/**
	 * Delete pending registration option rows matching an email.
	 *
	 * @param string $email Subject email.
	 * @return int Number of deleted rows.
	 */
	private static function erase_pending_options( $email ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'creatorreactor_fv_pen_%'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || $rows === [] ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $rows as $row ) {
			$option_name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			$raw         = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( $option_name === '' || ! is_array( $raw ) || empty( $raw['data'] ) || ! is_array( $raw['data'] ) ) {
				continue;
			}
			$row_email = isset( $raw['data']['email'] ) ? sanitize_email( (string) $raw['data']['email'] ) : '';
			if ( $row_email === '' || strtolower( $row_email ) !== strtolower( $email ) ) {
				continue;
			}
			if ( delete_option( $option_name ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * Remove connection/sync log entries containing the subject email.
	 *
	 * @param string $email Subject email.
	 * @return int Number of removed entries.
	 */
	private static function erase_logs_by_email( $email ) {
		$removed = 0;
		$removed += self::erase_log_option_entries_by_email( Admin_Settings::OPTION_CONNECTION_LOGS, $email );
		$removed += self::erase_log_option_entries_by_email( Admin_Settings::OPTION_SYNC_LOGS, $email );
		return $removed;
	}

	/**
	 * @param string $option_name Option key with log array.
	 * @param string $email       Subject email.
	 * @return int Number of removed entries.
	 */
	private static function erase_log_option_entries_by_email( $option_name, $email ) {
		$logs = get_option( $option_name, [] );
		if ( ! is_array( $logs ) || $logs === [] ) {
			return 0;
		}

		$needle    = strtolower( trim( (string) $email ) );
		$kept      = [];
		$removed   = 0;
		foreach ( $logs as $entry ) {
			$message = '';
			if ( is_array( $entry ) && isset( $entry['message'] ) && is_string( $entry['message'] ) ) {
				$message = strtolower( $entry['message'] );
			}
			if ( $needle !== '' && $message !== '' && strpos( $message, $needle ) !== false ) {
				++$removed;
				continue;
			}
			$kept[] = $entry;
		}

		if ( $removed > 0 ) {
			update_option( $option_name, $kept, false );
		}
		return $removed;
	}

	/**
	 * Purge old entries from connection/sync logs by retention window.
	 *
	 * @return void
	 */
	private static function purge_old_log_entries() {
		$cutoff = time() - ( self::log_retention_days() * DAY_IN_SECONDS );
		self::purge_old_log_option_entries( Admin_Settings::OPTION_CONNECTION_LOGS, $cutoff );
		self::purge_old_log_option_entries( Admin_Settings::OPTION_SYNC_LOGS, $cutoff );
	}

	/**
	 * @param string $option_name Option key with log entries.
	 * @param int    $cutoff_unix Keep entries newer than this UNIX timestamp.
	 * @return void
	 */
	private static function purge_old_log_option_entries( $option_name, $cutoff_unix ) {
		$logs = get_option( $option_name, [] );
		if ( ! is_array( $logs ) || $logs === [] ) {
			return;
		}
		$kept = [];
		foreach ( $logs as $entry ) {
			$ts = 0;
			if ( is_array( $entry ) && isset( $entry['time'] ) ) {
				$ts = (int) $entry['time'];
			}
			if ( $ts > 0 && $ts < (int) $cutoff_unix ) {
				continue;
			}
			$kept[] = $entry;
		}
		if ( count( $kept ) !== count( $logs ) ) {
			update_option( $option_name, $kept, false );
		}
	}

	/**
	 * Remove stale profile snapshots and synced-at markers based on retention window.
	 *
	 * @return void
	 */
	private static function purge_stale_profile_snapshots() {
		$cutoff_ts = time() - ( self::profile_snapshot_retention_days() * DAY_IN_SECONDS );
		$users     = get_users(
			[
				'fields'      => 'ids',
				'number'      => self::ROWS_PER_PAGE,
				'paged'       => 1,
				'count_total' => true,
			]
		);
		if ( ! is_array( $users ) || $users === [] ) {
			return;
		}
		$page = 1;
		do {
			$user_ids = get_users(
				[
					'fields'      => 'ids',
					'number'      => self::ROWS_PER_PAGE,
					'paged'       => $page,
					'count_total' => false,
				]
			);
			if ( ! is_array( $user_ids ) || $user_ids === [] ) {
				break;
			}
			foreach ( $user_ids as $uid ) {
				$uid      = (int) $uid;
				$last_raw = get_user_meta( $uid, Fan_OAuth::USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT, true );
				$last_ts  = is_string( $last_raw ) ? strtotime( $last_raw ) : false;
				if ( ! is_int( $last_ts ) || $last_ts <= 0 ) {
					continue;
				}
				if ( $last_ts >= $cutoff_ts ) {
					continue;
				}
				delete_user_meta( $uid, Onboarding::META_FANVUE_PROFILE_SNAPSHOT );
				delete_user_meta( $uid, Fan_OAuth::USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT );
			}
			++$page;
		} while ( count( $user_ids ) === self::ROWS_PER_PAGE );
	}

	/**
	 * Remove expired pending registration option rows.
	 *
	 * @return void
	 */
	private static function purge_expired_pending_registration_options() {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'creatorreactor_fv_pen_%'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || $rows === [] ) {
			return;
		}
		$now = time();
		foreach ( $rows as $row ) {
			$option_name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			if ( $option_name === '' ) {
				continue;
			}
			$raw = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( ! is_array( $raw ) || ! isset( $raw['exp'] ) ) {
				continue;
			}
			if ( (int) $raw['exp'] < $now ) {
				delete_option( $option_name );
			}
		}
	}
}
