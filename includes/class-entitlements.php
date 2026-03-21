<?php
/**
 * Entitlements table and helpers for CreatorReactor subscribers and followers.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Entitlements {

	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';
	const STATUS_UNKNOWN  = 'unknown';

	const TIER_FOLLOWER = '__creatorreactor_follower__';

	/** Canonical FanVue integration key (stored in entitlements and settings). */
	const PRODUCT_FANVUE = 'fanvue';

	/** @deprecated Legacy slug; normalized to {@see PRODUCT_FANVUE}. */
	const PRODUCT_CREATORREACTOR = 'creatorreactor';

	const PRODUCT_ONLYFANS = 'onlyfans';

	/** Set when entitlements/options were migrated from creatorreactor → fanvue. */
	const OPTION_FANVUE_PRODUCT_KEY_MIGRATED = 'creatorreactor_fanvue_product_key_v1';

	const USERMETA_CREATORREACTOR_UUID = 'creatorreactor_user_uuid';

	private static $schema_checked = false;

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . CREATORREACTOR_TABLE_ENTITLEMENTS;
	}

	/**
	 * Rename the pre-rename entitlements table to the current name if present.
	 */
	private static function maybe_rename_table_from_before_rename() {
		global $wpdb;
		$old = $wpdb->prefix . 'fan' . 'bridge_entitlements';
		$new = $wpdb->prefix . CREATORREACTOR_TABLE_ENTITLEMENTS;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from trusted prefixes.
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );
		if ( $old_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers from $wpdb->prefix only.
			$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
		}
	}

	public static function create_table() {
		try {
			global $wpdb;
			self::maybe_rename_table_from_before_rename();
			$table   = self::get_table_name();
			$charset = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_user_id bigint(20) unsigned DEFAULT NULL,
				product varchar(50) NOT NULL DEFAULT 'fanvue',
				email varchar(255) DEFAULT NULL,
				display_name varchar(255) DEFAULT NULL,
				creatorreactor_user_uuid varchar(36) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'unknown',
				tier varchar(100) DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				expires_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY wp_user_id (wp_user_id),
				KEY product (product),
				KEY email (email(191)),
				KEY creatorreactor_user_uuid (creatorreactor_user_uuid),
				KEY status_expires (status, expires_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			self::maybe_add_product_column();
			self::maybe_add_display_name_column();
			self::$schema_checked = true;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor create_table error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			throw $e;
		}
	}

	private static function maybe_ensure_schema() {
		if ( self::$schema_checked ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			self::$schema_checked = true;
			return;
		}

		self::maybe_add_product_column();
		self::maybe_add_display_name_column();
		self::$schema_checked = true;
	}

	private static function has_column( $column_name ) {
		global $wpdb;
		$table = self::get_table_name();
		$col   = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column_name ) );
		return ! empty( $col );
	}

	private static function has_index( $index_name ) {
		global $wpdb;
		$table = self::get_table_name();
		$index = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index_name ) );
		return ! empty( $index );
	}

	public static function normalize_product( $product ) {
		$product = strtolower( trim( sanitize_text_field( (string) $product ) ) );
		if ( $product === '' ) {
			return self::PRODUCT_FANVUE;
		}
		if ( $product === self::PRODUCT_CREATORREACTOR ) {
			return self::PRODUCT_FANVUE;
		}
		return $product;
	}

	public static function product_label( $product ) {
		$product = self::normalize_product( $product );
		if ( $product === self::PRODUCT_FANVUE ) {
			return 'fanvue';
		}
		if ( $product === self::PRODUCT_ONLYFANS ) {
			return 'OnlyFans';
		}
		return ucwords( str_replace( [ '-', '_' ], ' ', $product ) );
	}

	public static function maybe_add_product_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! self::has_column( 'product' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN product varchar(50) NOT NULL DEFAULT 'fanvue' AFTER wp_user_id" );
		}
		if ( ! self::has_index( 'product' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY product (product)" );
		}
	}

	public static function maybe_add_display_name_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! self::has_column( 'display_name' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN display_name varchar(255) DEFAULT NULL AFTER email" );
		}
	}

	public static function upsert_by_creatorreactor_uuid( $creatorreactor_uuid, $status, $expires_at, $wp_user_id = null, $email = '', $tier = null, $display_name = null, $product = self::PRODUCT_FANVUE ) {
		try {
			self::maybe_ensure_schema();
			global $wpdb;
			$table = self::get_table_name();
			$creatorreactor_uuid  = sanitize_text_field( $creatorreactor_uuid );
			$status       = in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_UNKNOWN ], true ) ? $status : self::STATUS_UNKNOWN;
			$product      = self::normalize_product( $product );
			$email        = sanitize_email( $email );
			$tier         = $tier !== null ? sanitize_text_field( $tier ) : null;
			$display_name = $display_name !== null && $display_name !== '' ? sanitize_text_field( wp_strip_all_tags( (string) $display_name ) ) : null;

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE creatorreactor_user_uuid = %s AND product = %s LIMIT 1",
					$creatorreactor_uuid,
					$product
				)
			);

			$data = [
				'product'          => $product,
				'creatorreactor_user_uuid' => $creatorreactor_uuid,
				'status'           => $status,
				'expires_at'       => $expires_at,
				'updated_at'       => current_time( 'mysql' ),
				'wp_user_id'       => $wp_user_id ? (int) $wp_user_id : null,
				'email'            => $email !== '' ? $email : null,
				'display_name'     => $display_name,
				'tier'             => $tier,
			];
			$format = [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ];

			if ( $existing ) {
				unset( $data['updated_at'] );
				$result = $wpdb->update(
					$table,
					$data,
					[ 'id' => (int) $existing ],
					[ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ],
					[ '%d' ]
				);
				return $result !== false;
			}

			$result = $wpdb->insert( $table, $data, $format );
			return $result !== false;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor upsert_by_creatorreactor_uuid error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return false;
		}
	}

	public static function mark_missing_as_inactive( array $active_creatorreactor_uuids, $expires_at, $product = self::PRODUCT_FANVUE ) {
		try {
			self::maybe_ensure_schema();
			global $wpdb;
			$table = self::get_table_name();
			$product = self::normalize_product( $product );
			if ( empty( $active_creatorreactor_uuids ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s",
						self::STATUS_INACTIVE,
						$expires_at,
						current_time( 'mysql' ),
						self::STATUS_ACTIVE,
						$product
					)
				);
				return (int) $wpdb->rows_affected;
			}

			$placeholders = implode( ',', array_fill( 0, count( $active_creatorreactor_uuids ), '%s' ) );
			$query        = $wpdb->prepare(
				"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s AND creatorreactor_user_uuid NOT IN ($placeholders)",
				array_merge(
					[ self::STATUS_INACTIVE, $expires_at, current_time( 'mysql' ), self::STATUS_ACTIVE, $product ],
					array_map( 'sanitize_text_field', $active_creatorreactor_uuids )
				)
			);
			$wpdb->query( $query );
			return (int) $wpdb->rows_affected;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor mark_missing_as_inactive error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return 0;
		}
	}

	public static function get_active_subscribers( $tier = null, $product = null ) {
		self::maybe_ensure_schema();
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		$where = [ 'status = %s', 'expires_at > %s' ];
		$args  = [ self::STATUS_ACTIVE, $now ];

		if ( $tier !== null ) {
			$where[] = 'tier = %s';
			$args[]  = $tier;
		}

		if ( $product !== null ) {
			$where[] = 'product = %s';
			$args[]  = self::normalize_product( $product );
		}

		$query = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where );

		return $wpdb->get_results( $wpdb->prepare( $query, $args ), ARRAY_A );
	}

	public static function check_user_entitlement( $user_id, $tier = null, $product = null ) {
		self::maybe_ensure_schema();
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$email = $user->user_email;

		$where = [ 'email = %s', 'status = %s', 'expires_at > %s' ];
		$args  = [ $email, self::STATUS_ACTIVE, $now ];

		if ( $tier !== null ) {
			$where[] = 'tier = %s';
			$args[]  = $tier;
		}

		if ( $product !== null ) {
			$where[] = 'product = %s';
			$args[]  = self::normalize_product( $product );
		}

		$query = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' LIMIT 1';
		$row   = $wpdb->get_row( $wpdb->prepare( $query, $args ) );

		return $row !== null;
	}

	/**
	 * One-time migration: stored product key fanvue (replaces legacy creatorreactor).
	 */
	public static function maybe_migrate_fanvue_product_key() {
		if ( get_option( self::OPTION_FANVUE_PRODUCT_KEY_MIGRATED, '' ) === '1' ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists === $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers from $wpdb->prefix only.
			$wpdb->query( "UPDATE {$table} SET product = 'fanvue' WHERE product = 'creatorreactor'" );
		}

		$opts = get_option( 'creatorreactor_settings', [] );
		if ( is_array( $opts ) && isset( $opts['product'] ) && is_string( $opts['product'] ) && strtolower( trim( $opts['product'] ) ) === self::PRODUCT_CREATORREACTOR ) {
			$opts['product'] = self::PRODUCT_FANVUE;
			update_option( 'creatorreactor_settings', $opts );
		}

		update_option( self::OPTION_FANVUE_PRODUCT_KEY_MIGRATED, '1' );
	}
}
