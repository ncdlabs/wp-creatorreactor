<?php
/**
 * Entitlements table and helpers for Fanvue subscribers and followers.
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace FanBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Entitlements {

	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';
	const STATUS_UNKNOWN  = 'unknown';

	const TIER_FOLLOWER = '__fanvue_follower__';

	const USERMETA_FANVUE_UUID = 'fanvue_user_uuid';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . FANBRIDGE_TABLE_ENTITLEMENTS;
	}

	public static function create_table() {
		try {
			global $wpdb;
			$table   = self::get_table_name();
			$charset = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_user_id bigint(20) unsigned DEFAULT NULL,
				email varchar(255) DEFAULT NULL,
				display_name varchar(255) DEFAULT NULL,
				fanvue_user_uuid varchar(36) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'unknown',
				tier varchar(100) DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				expires_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY wp_user_id (wp_user_id),
				KEY email (email(191)),
				KEY fanvue_user_uuid (fanvue_user_uuid),
				KEY status_expires (status, expires_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			self::maybe_add_display_name_column();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge create_table error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			throw $e;
		}
	}

	public static function maybe_add_display_name_column() {
		global $wpdb;
		$table = self::get_table_name();
		$col   = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'display_name' ) );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN display_name varchar(255) DEFAULT NULL AFTER email" );
		}
	}

	public static function upsert_by_fanvue_uuid( $fanvue_uuid, $status, $expires_at, $wp_user_id = null, $email = '', $tier = null, $display_name = null ) {
		try {
			global $wpdb;
			$table = self::get_table_name();
			$fanvue_uuid  = sanitize_text_field( $fanvue_uuid );
			$status       = in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_UNKNOWN ], true ) ? $status : self::STATUS_UNKNOWN;
			$email        = sanitize_email( $email );
			$tier         = $tier !== null ? sanitize_text_field( $tier ) : null;
			$display_name = $display_name !== null && $display_name !== '' ? sanitize_text_field( wp_strip_all_tags( (string) $display_name ) ) : null;

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE fanvue_user_uuid = %s LIMIT 1",
					$fanvue_uuid
				)
			);

			$data = [
				'fanvue_user_uuid' => $fanvue_uuid,
				'status'           => $status,
				'expires_at'       => $expires_at,
				'updated_at'       => current_time( 'mysql' ),
				'wp_user_id'       => $wp_user_id ? (int) $wp_user_id : null,
				'email'            => $email !== '' ? $email : null,
				'display_name'     => $display_name,
				'tier'             => $tier,
			];
			$format = [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ];

			if ( $existing ) {
				unset( $data['updated_at'] );
				$result = $wpdb->update(
					$table,
					$data,
					[ 'id' => (int) $existing ],
					[ '%s', '%s', '%s', '%d', '%s', '%s', '%s' ],
					[ '%d' ]
				);
				return $result !== false;
			}

			$result = $wpdb->insert( $table, $data, $format );
			return $result !== false;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge upsert_by_fanvue_uuid error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return false;
		}
	}

	public static function mark_missing_as_inactive( array $active_fanvue_uuids, $expires_at ) {
		try {
			global $wpdb;
			$table = self::get_table_name();
			if ( empty( $active_fanvue_uuids ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s",
						self::STATUS_INACTIVE,
						$expires_at,
						current_time( 'mysql' ),
						self::STATUS_ACTIVE
					)
				);
				return (int) $wpdb->rows_affected;
			}

			$placeholders = implode( ',', array_fill( 0, count( $active_fanvue_uuids ), '%s' ) );
			$query        = $wpdb->prepare(
				"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND fanvue_user_uuid NOT IN ($placeholders)",
				array_merge(
					[ self::STATUS_INACTIVE, $expires_at, current_time( 'mysql' ), self::STATUS_ACTIVE ],
					array_map( 'sanitize_text_field', $active_fanvue_uuids )
				)
			);
			$wpdb->query( $query );
			return (int) $wpdb->rows_affected;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge mark_missing_as_inactive error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return 0;
		}
	}

	public static function get_active_subscribers( $tier = null ) {
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		if ( $tier !== null ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s AND expires_at > %s AND tier = %s",
					self::STATUS_ACTIVE,
					$now,
					$tier
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND expires_at > %s",
				self::STATUS_ACTIVE,
				$now
			),
			ARRAY_A
		);
	}

	public static function check_user_entitlement( $user_id, $tier = null ) {
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$email = $user->user_email;

		if ( $tier !== null ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND status = %s AND expires_at > %s AND tier = %s LIMIT 1",
					$email,
					self::STATUS_ACTIVE,
					$now,
					$tier
				)
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND status = %s AND expires_at > %s LIMIT 1",
					$email,
					self::STATUS_ACTIVE,
					$now
				)
			);
		}

		return $row !== null;
	}
}
