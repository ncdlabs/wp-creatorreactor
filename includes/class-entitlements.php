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

	/**
	 * Legacy stored tier for followers (pre–product-scoped format).
	 *
	 * @deprecated Replaced by {@see tier_stored_for_follower()}; kept for migrations and query matching.
	 */
	const TIER_FOLLOWER = '__creatorreactor_follower__';

	/** Option: one-time migration legacy follower tier → {product}_follower. */
	const OPTION_TIER_FOLLOWER_FORMAT_MIGRATED = 'creatorreactor_tier_follower_format_v1';

	/** Canonical FanVue integration key (stored in entitlements and settings). */
	const PRODUCT_FANVUE = 'fanvue';

	/** @deprecated Legacy slug; normalized to {@see PRODUCT_FANVUE}. */
	const PRODUCT_CREATORREACTOR = 'creatorreactor';

	const PRODUCT_ONLYFANS = 'onlyfans';

	/** Set when entitlements/options were migrated from creatorreactor → fanvue. */
	const OPTION_FANVUE_PRODUCT_KEY_MIGRATED = 'creatorreactor_fanvue_product_key_v1';

	const USERMETA_CREATORREACTOR_UUID = 'creatorreactor_user_uuid';

	/**
	 * JSON snapshot of active-style entitlements per product (fanvue, onlyfans, …) for fast reads without querying the entitlements table.
	 * Refreshed on {@see 'wp_login'} (including after Fanvue OAuth). Schema version in `v`.
	 */
	const USERMETA_SOCIAL_ENTITLEMENTS = 'creatorreactor_social_entitlements';

	const SOCIAL_ENTITLEMENTS_SNAPSHOT_VERSION = 1;

	private static $schema_checked = false;

	/** @var array<string, int|null> In-request cache for {@see resolve_wp_user_id_for_fanvue_sync()}. */
	private static $fanvue_sync_user_resolve_cache = [];

	public static function init() {
		add_action( 'wp_login', [ __CLASS__, 'on_wp_login_refresh_social_entitlements' ], 15, 2 );
	}

	/**
	 * @param string         $user_login Login name (required by hook signature).
	 * @param \WP_User|false $user       User object.
	 */
	public static function on_wp_login_refresh_social_entitlements( $user_login, $user ) {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$fan_uuid = get_user_meta( (int) $user->ID, self::USERMETA_CREATORREACTOR_UUID, true );
		$fan_uuid = is_string( $fan_uuid ) ? sanitize_text_field( $fan_uuid ) : '';
		$is_fan_linked = get_user_meta( (int) $user->ID, Onboarding::META_FANVUE_OAUTH_LINKED, true ) === '1';
		$email         = is_string( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
		if ( ( $is_fan_linked || $fan_uuid !== '' ) && $email !== '' && apply_filters( 'creatorreactor_sync_fan_entitlement_on_login', true, $user ) ) {
			CreatorReactor_Client::sync_entitlement_for_fan_after_login(
				$fan_uuid,
				(int) $user->ID,
				$email,
				is_string( $user->display_name ) ? $user->display_name : ''
			);
		}

		if ( ! apply_filters( 'creatorreactor_refresh_social_entitlements_on_login', true, $user ) ) {
			return;
		}
		self::refresh_social_entitlements_user_meta( (int) $user->ID, 'wp_login' );
	}

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
				fanvue_email varchar(255) DEFAULT NULL,
				fanvue_display_name varchar(255) DEFAULT NULL,
				fanvue_user_uuid varchar(36) DEFAULT NULL,
				creatorreactor_uuid varchar(36) DEFAULT NULL,
				creatorreactor_user_uuid varchar(36) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'unknown',
				tier varchar(100) DEFAULT NULL,
				fanvue_tier varchar(100) DEFAULT NULL,
				fanvue_sync_snapshot longtext DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				expires_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY wp_user_id (wp_user_id),
				KEY product (product),
				KEY email (email(191)),
				KEY fanvue_email (fanvue_email(191)),
				KEY fanvue_user_uuid (fanvue_user_uuid),
				KEY creatorreactor_uuid (creatorreactor_uuid),
				KEY creatorreactor_user_uuid (creatorreactor_user_uuid),
				KEY status_expires (status, expires_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			self::maybe_add_product_column();
			self::maybe_add_display_name_column();
			self::maybe_add_fanvue_payload_columns();
			self::maybe_add_fanvue_user_uuid_column();
			self::maybe_add_creatorreactor_uuid_column();
			self::maybe_add_creatorreactor_user_uuid_column();
			self::maybe_add_fanvue_sync_snapshot_column();
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
		self::maybe_add_fanvue_payload_columns();
		self::maybe_add_fanvue_user_uuid_column();
		self::maybe_add_creatorreactor_uuid_column();
		self::maybe_add_creatorreactor_user_uuid_column();
		self::maybe_add_fanvue_sync_snapshot_column();
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

	/**
	 * Stored tier for a follower row: {product_id}_follower (e.g. fanvue_follower).
	 *
	 * @param string|null $product Product key; normalized.
	 */
	public static function tier_stored_for_follower( $product ) {
		return self::normalize_product( $product ) . '_follower';
	}

	/**
	 * Stored tier for a subscriber row: {product_id}_subscriber or {product_id}_subscriber_{api_tier}.
	 *
	 * @param string|null $product    Product key; normalized.
	 * @param string|null $api_tier   Raw/normalized API tier slug (optional).
	 */
	public static function tier_stored_for_subscriber( $product, $api_tier = null ) {
		$p    = self::normalize_product( $product );
		$base = $p . '_subscriber';
		if ( $api_tier === null || $api_tier === '' ) {
			return $base;
		}
		$t = sanitize_key( (string) $api_tier );
		if ( $t === '' ) {
			return $base;
		}
		$full = $base . '_' . $t;
		return strlen( $full ) > 100 ? substr( $full, 0, 100 ) : $full;
	}

	/**
	 * Values to match in SQL when filtering by tier (supports legacy + new stored formats).
	 *
	 * @param string      $tier    Requested tier filter.
	 * @param string|null $product Product filter or null.
	 * @return array<int, string>
	 */
	private static function tier_filter_match_values( $tier, $product = null ) {
		$t = (string) $tier;
		$variants = [ $t ];

		if ( $product !== null ) {
			$p = self::normalize_product( $product );
			$follower_stored = self::tier_stored_for_follower( $p );
			if ( $t === self::TIER_FOLLOWER || $t === $follower_stored ) {
				$variants[] = self::TIER_FOLLOWER;
				$variants[] = $follower_stored;
				return array_values( array_unique( array_filter( $variants, 'strlen' ) ) );
			}
			$base_sub = $p . '_subscriber';
			$prefix_sub = $p . '_subscriber_';
			if ( str_starts_with( $t, $prefix_sub ) ) {
				$suffix = substr( $t, strlen( $prefix_sub ) );
				if ( $suffix !== '' ) {
					$variants[] = $suffix;
				}
			} elseif ( $t !== $base_sub ) {
				$composed = self::tier_stored_for_subscriber( $p, $t );
				if ( $composed !== $t ) {
					$variants[] = $composed;
				}
			}
			return array_values( array_unique( array_filter( $variants, 'strlen' ) ) );
		}

		if ( $t === self::TIER_FOLLOWER || str_ends_with( $t, '_follower' ) ) {
			$variants[] = self::TIER_FOLLOWER;
			foreach ( [ self::PRODUCT_FANVUE, self::PRODUCT_ONLYFANS ] as $prod ) {
				$variants[] = self::tier_stored_for_follower( $prod );
			}
		} elseif ( strpos( $t, '_subscriber_' ) !== false && preg_match( '/^([a-z0-9-]+)_subscriber_(.+)$/', $t, $m ) ) {
			if ( ! in_array( $m[2], $variants, true ) ) {
				$variants[] = $m[2];
			}
		} elseif ( strpos( $t, '_subscriber_' ) === false && $t !== self::TIER_FOLLOWER && ! str_ends_with( $t, '_follower' ) ) {
			foreach ( [ self::PRODUCT_FANVUE, self::PRODUCT_ONLYFANS ] as $prod ) {
				$c = self::tier_stored_for_subscriber( $prod, $t );
				if ( ! in_array( $c, $variants, true ) ) {
					$variants[] = $c;
				}
			}
		}

		return array_values( array_unique( array_filter( $variants, 'strlen' ) ) );
	}

	/**
	 * Users tab: collapse stored tier into Follower vs paid Subscriber.
	 *
	 * @param string|null $tier Raw tier from entitlements row.
	 * @return string Translated label or hyphen when unknown.
	 */
	public static function tier_audience_label( $tier ) {
		if ( $tier === null || $tier === '' ) {
			return '-';
		}
		$tier = (string) $tier;
		if ( $tier === self::TIER_FOLLOWER || str_ends_with( $tier, '_follower' ) ) {
			return __( 'Follower', 'creatorreactor' );
		}
		return __( 'Subscriber', 'creatorreactor' );
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

	public static function maybe_add_fanvue_payload_columns() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! method_exists( $wpdb, 'query' ) ) {
			return;
		}
		if ( ! self::has_column( 'fanvue_email' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fanvue_email varchar(255) DEFAULT NULL AFTER display_name" );
		}
		if ( ! self::has_column( 'fanvue_display_name' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fanvue_display_name varchar(255) DEFAULT NULL AFTER fanvue_email" );
		}
		if ( ! self::has_column( 'fanvue_tier' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fanvue_tier varchar(100) DEFAULT NULL AFTER tier" );
		}

		// Backfill Fanvue-prefixed columns from existing generic columns for upgraded installs.
		$wpdb->query( "UPDATE {$table} SET fanvue_email = email WHERE product = 'fanvue' AND (fanvue_email IS NULL OR fanvue_email = '') AND email IS NOT NULL AND email != ''" );
		$wpdb->query( "UPDATE {$table} SET fanvue_display_name = display_name WHERE product = 'fanvue' AND (fanvue_display_name IS NULL OR fanvue_display_name = '') AND display_name IS NOT NULL AND display_name != ''" );
		$wpdb->query( "UPDATE {$table} SET fanvue_tier = tier WHERE product = 'fanvue' AND (fanvue_tier IS NULL OR fanvue_tier = '') AND tier IS NOT NULL AND tier != ''" );

		if ( ! self::has_index( 'fanvue_email' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY fanvue_email (fanvue_email(191))" );
		}
	}

	public static function maybe_add_fanvue_user_uuid_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! method_exists( $wpdb, 'query' ) ) {
			return;
		}
		if ( ! self::has_column( 'fanvue_user_uuid' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fanvue_user_uuid varchar(36) DEFAULT NULL AFTER display_name" );
		}
		// Backfill for upgraded installs so existing rows remain queryable by Fanvue UUID.
		$wpdb->query( "UPDATE {$table} SET fanvue_user_uuid = creatorreactor_user_uuid WHERE (fanvue_user_uuid IS NULL OR fanvue_user_uuid = '') AND creatorreactor_user_uuid IS NOT NULL AND creatorreactor_user_uuid != ''" );
		if ( ! self::has_index( 'fanvue_user_uuid' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY fanvue_user_uuid (fanvue_user_uuid)" );
		}
	}

	public static function maybe_add_creatorreactor_uuid_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! method_exists( $wpdb, 'query' ) ) {
			return;
		}
		if ( ! self::has_column( 'creatorreactor_uuid' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN creatorreactor_uuid varchar(36) DEFAULT NULL AFTER fanvue_user_uuid" );
		}
		// Backfill rows created before creatorreactor_uuid existed.
		$rows = $wpdb->get_results( "SELECT id FROM {$table} WHERE creatorreactor_uuid IS NULL OR creatorreactor_uuid = '' LIMIT 1000", ARRAY_A );
		if ( is_array( $rows ) && ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$row_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $row_id <= 0 ) {
					continue;
				}
				if ( method_exists( $wpdb, 'update' ) ) {
					$wpdb->update(
						$table,
						[ 'creatorreactor_uuid' => self::generate_creatorreactor_uuid() ],
						[ 'id' => $row_id ],
						[ '%s' ],
						[ '%d' ]
					);
				}
			}
		}
		if ( ! self::has_index( 'creatorreactor_uuid' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY creatorreactor_uuid (creatorreactor_uuid)" );
		}
	}

	/**
	 * Generate a UUID for creatorreactor_uuid storage.
	 *
	 * @return string
	 */
	private static function generate_creatorreactor_uuid() {
		$uuid = wp_generate_uuid4();
		if ( ! is_string( $uuid ) || $uuid === '' ) {
			$uuid = sprintf(
				'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0x0fff ) | 0x4000,
				wp_rand( 0, 0x3fff ) | 0x8000,
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0xffff ),
				wp_rand( 0, 0xffff )
			);
		}
		return sanitize_text_field( strtolower( $uuid ) );
	}

	/**
	 * Ensure creatorreactor_user_uuid exists (older installs / dbDelta gaps may omit it).
	 * Renames legacy column `uuid` if present.
	 */
	public static function maybe_add_creatorreactor_user_uuid_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( self::has_column( 'creatorreactor_user_uuid' ) ) {
			if ( ! self::has_index( 'creatorreactor_user_uuid' ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
				$wpdb->query( "ALTER TABLE {$table} ADD KEY creatorreactor_user_uuid (creatorreactor_user_uuid)" );
			}
			return;
		}
		if ( self::has_column( 'uuid' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$wpdb->query( "ALTER TABLE `{$table}` CHANGE COLUMN `uuid` `creatorreactor_user_uuid` varchar(36) DEFAULT NULL" );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN creatorreactor_user_uuid varchar(36) DEFAULT NULL" );
		}
		if ( ! self::has_index( 'creatorreactor_user_uuid' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$wpdb->query( "ALTER TABLE {$table} ADD KEY creatorreactor_user_uuid (creatorreactor_user_uuid)" );
		}
	}

	public static function maybe_add_fanvue_sync_snapshot_column() {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! self::has_column( 'fanvue_sync_snapshot' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fanvue_sync_snapshot longtext DEFAULT NULL AFTER tier" );
		}
	}

	/**
	 * Clear per-request cache used while syncing Fanvue lists (call at start of a full sync).
	 */
	public static function clear_fanvue_sync_user_resolve_cache() {
		self::$fanvue_sync_user_resolve_cache = [];
	}

	/**
	 * SQL fragment args: rows whose tier is a stored follower value (legacy or * _follower).
	 *
	 * @global \wpdb $wpdb
	 * @return array{0: string, 1: string, 2: string} [ sql, arg_legacy_tier, arg_like_pattern ]
	 */
	private static function follower_tier_sql_match() {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '_follower' );
		return [ '(tier <=> %s OR tier LIKE %s)', self::TIER_FOLLOWER, $like ];
	}

	/**
	 * During sync: attach entitlements to an existing WordPress user only (by UUID meta or email).
	 * New accounts are created only when the fan uses the Fanvue OAuth login button ({@see Fan_OAuth}), not during sync.
	 *
	 * @param string $email List email from API (may be empty).
	 * @param string $uuid  Fanvue user UUID.
	 * @return int|null WordPress user ID, or null if no matching user exists yet.
	 */
	public static function resolve_wp_user_id_for_fanvue_sync( $email, $uuid ) {
		$uuid = is_string( $uuid ) ? sanitize_text_field( trim( $uuid ) ) : '';
		if ( $uuid === '' ) {
			return null;
		}

		$email = is_string( $email ) ? sanitize_email( trim( $email ) ) : '';
		$cache_key = strtolower( $uuid ) . '|' . strtolower( $email );
		if ( array_key_exists( $cache_key, self::$fanvue_sync_user_resolve_cache ) ) {
			return self::$fanvue_sync_user_resolve_cache[ $cache_key ];
		}

		$by_uuid = get_users(
			[
				'meta_key'    => self::USERMETA_CREATORREACTOR_UUID,
				'meta_value'  => $uuid,
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'ID',
			]
		);
		$uid_uuid = ! empty( $by_uuid[0] ) ? (int) $by_uuid[0] : 0;

		$uid_email = 0;
		if ( $email !== '' && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$uid_email = (int) $user->ID;
			}
		}

		if ( $uid_uuid > 0 && $uid_email > 0 && $uid_uuid !== $uid_email ) {
			self::$fanvue_sync_user_resolve_cache[ $cache_key ] = $uid_uuid;
			return $uid_uuid;
		}

		$chosen = $uid_uuid > 0 ? $uid_uuid : $uid_email;
		if ( $chosen > 0 ) {
			update_user_meta( $chosen, self::USERMETA_CREATORREACTOR_UUID, $uuid );
			self::$fanvue_sync_user_resolve_cache[ $cache_key ] = $chosen;
			return $chosen;
		}

		self::$fanvue_sync_user_resolve_cache[ $cache_key ] = null;
		return null;
	}

	/**
	 * Surface $wpdb errors to last error + sync log (WordPress does not throw on SQL errors).
	 *
	 * @param string $context Short label for the failing operation.
	 * @return bool True if an error was reported.
	 */
	private static function report_db_error( $context ) {
		global $wpdb;
		$err = isset( $wpdb->last_error ) ? trim( (string) $wpdb->last_error ) : '';
		if ( $err === '' ) {
			return false;
		}
		$msg = $context . ': ' . $err;
		if ( class_exists( __NAMESPACE__ . '\\Admin_Settings', false ) ) {
			Admin_Settings::set_last_error( $msg );
			Admin_Settings::log_sync( 'error', $msg );
		}
		return true;
	}

	public static function upsert_by_creatorreactor_uuid( $creatorreactor_uuid, $status, $expires_at, $wp_user_id = null, $email = '', $tier = null, $display_name = null, $product = self::PRODUCT_FANVUE, $fanvue_sync_snapshot = null ) {
		try {
			self::maybe_ensure_schema();
			global $wpdb;
			$table = self::get_table_name();
			$fanvue_user_uuid     = sanitize_text_field( $creatorreactor_uuid );
			$status       = in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_UNKNOWN ], true ) ? $status : self::STATUS_UNKNOWN;
			$product      = self::normalize_product( $product );
			$email        = sanitize_email( $email );
			$tier         = $tier !== null ? sanitize_text_field( $tier ) : null;
			$display_name = $display_name !== null && $display_name !== '' ? sanitize_text_field( wp_strip_all_tags( (string) $display_name ) ) : null;
			$snapshot     = null;
			if ( is_string( $fanvue_sync_snapshot ) && $fanvue_sync_snapshot !== '' ) {
				$snapshot = $fanvue_sync_snapshot;
			}

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE product = %s AND (fanvue_user_uuid = %s OR ((fanvue_user_uuid IS NULL OR fanvue_user_uuid = '') AND creatorreactor_user_uuid = %s)) LIMIT 1",
					$product,
					$fanvue_user_uuid,
					$fanvue_user_uuid
				)
			);
			if ( self::report_db_error( 'Entitlements SELECT' ) ) {
				return false;
			}

			$wp_uid = ( $wp_user_id !== null && (int) $wp_user_id > 0 ) ? (int) $wp_user_id : null;
			if ( $existing && $wp_uid === null ) {
				$prev = $wpdb->get_var( $wpdb->prepare( "SELECT wp_user_id FROM {$table} WHERE id = %d LIMIT 1", (int) $existing ) );
				if ( self::report_db_error( 'Entitlements SELECT wp_user_id' ) ) {
					return false;
				}
				if ( $prev !== null && (string) $prev !== '' && (int) $prev > 0 ) {
					$wp_uid = (int) $prev;
				}
			}

			$data = [
				'product'                  => $product,
				'fanvue_user_uuid'         => $fanvue_user_uuid,
				'creatorreactor_user_uuid' => $fanvue_user_uuid,
				'status'                   => $status,
				'expires_at'               => $expires_at,
				'updated_at'               => current_time( 'mysql' ),
				'wp_user_id'               => $wp_uid,
				'email'                    => $email !== '' ? $email : null,
				'display_name'             => $display_name,
				'tier'                     => $tier,
			];
			if ( $product === self::PRODUCT_FANVUE ) {
				$data['fanvue_email']        = $email !== '' ? $email : null;
				$data['fanvue_display_name'] = $display_name;
				$data['fanvue_tier']         = $tier;
			}
			if ( $snapshot !== null ) {
				$data['fanvue_sync_snapshot'] = $snapshot;
			}

			if ( $existing ) {
				unset( $data['updated_at'] );
				if ( $snapshot === null ) {
					unset( $data['fanvue_sync_snapshot'] );
				}
				$formats = [];
				foreach ( array_keys( $data ) as $_k ) {
					$formats[] = ( $_k === 'wp_user_id' ) ? '%d' : '%s';
				}
				$result = $wpdb->update(
					$table,
					$data,
					[ 'id' => (int) $existing ],
					$formats,
					[ '%d' ]
				);
				if ( self::report_db_error( 'Entitlements UPDATE' ) ) {
					return false;
				}
				return $result !== false;
			}

			$data['creatorreactor_uuid'] = self::generate_creatorreactor_uuid();
			$format = [];
			foreach ( array_keys( $data ) as $_k ) {
				$format[] = ( $_k === 'wp_user_id' ) ? '%d' : '%s';
			}
			$result = $wpdb->insert( $table, $data, $format );
			if ( self::report_db_error( 'Entitlements INSERT' ) ) {
				return false;
			}
			return $result !== false;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor upsert_by_creatorreactor_uuid error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return false;
		}
	}

	/**
	 * Mark paid subscriber rows missing from the latest API pull as inactive (does not touch follower-tier rows).
	 */
	public static function mark_missing_subscribers_as_inactive( array $active_creatorreactor_uuids, $expires_at, $product = self::PRODUCT_FANVUE ) {
		try {
			self::maybe_ensure_schema();
			global $wpdb;
			$table   = self::get_table_name();
			$product = self::normalize_product( $product );
			list( $ft_sql, $ft_legacy, $ft_like ) = self::follower_tier_sql_match();

			if ( empty( $active_creatorreactor_uuids ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s AND creatorreactor_user_uuid IS NOT NULL AND creatorreactor_user_uuid != '' AND NOT ($ft_sql)",
						array_merge(
							[
								self::STATUS_INACTIVE,
								$expires_at,
								current_time( 'mysql' ),
								self::STATUS_ACTIVE,
								$product,
							],
							[ $ft_legacy, $ft_like ]
						)
					)
				);
				self::report_db_error( 'Entitlements mark_missing_subscribers_as_inactive (empty active set)' );
				return (int) $wpdb->rows_affected;
			}

			$placeholders = implode( ',', array_fill( 0, count( $active_creatorreactor_uuids ), '%s' ) );
			$query        = $wpdb->prepare(
				"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s AND creatorreactor_user_uuid IS NOT NULL AND creatorreactor_user_uuid != '' AND NOT ($ft_sql) AND creatorreactor_user_uuid NOT IN ($placeholders)",
				array_merge(
					[
						self::STATUS_INACTIVE,
						$expires_at,
						current_time( 'mysql' ),
						self::STATUS_ACTIVE,
						$product,
						$ft_legacy,
						$ft_like,
					],
					array_map( 'sanitize_text_field', $active_creatorreactor_uuids )
				)
			);
			$wpdb->query( $query );
			self::report_db_error( 'Entitlements mark_missing_subscribers_as_inactive' );
			return (int) $wpdb->rows_affected;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor mark_missing_subscribers_as_inactive error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return 0;
		}
	}

	/**
	 * Mark follower-tier rows missing from the latest followers API pull as inactive.
	 */
	public static function mark_missing_followers_as_inactive( array $active_follower_uuids, $expires_at, $product = self::PRODUCT_FANVUE ) {
		try {
			self::maybe_ensure_schema();
			global $wpdb;
			$table   = self::get_table_name();
			$product = self::normalize_product( $product );
			list( $ft_sql, $ft_legacy, $ft_like ) = self::follower_tier_sql_match();

			if ( empty( $active_follower_uuids ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s AND creatorreactor_user_uuid IS NOT NULL AND creatorreactor_user_uuid != '' AND ($ft_sql)",
						array_merge(
							[
								self::STATUS_INACTIVE,
								$expires_at,
								current_time( 'mysql' ),
								self::STATUS_ACTIVE,
								$product,
							],
							[ $ft_legacy, $ft_like ]
						)
					)
				);
				self::report_db_error( 'Entitlements mark_missing_followers_as_inactive (empty active set)' );
				return (int) $wpdb->rows_affected;
			}

			$placeholders = implode( ',', array_fill( 0, count( $active_follower_uuids ), '%s' ) );
			$query        = $wpdb->prepare(
				"UPDATE {$table} SET status = %s, expires_at = %s, updated_at = %s WHERE status = %s AND product = %s AND ($ft_sql) AND creatorreactor_user_uuid NOT IN ($placeholders)",
				array_merge(
					[
						self::STATUS_INACTIVE,
						$expires_at,
						current_time( 'mysql' ),
						self::STATUS_ACTIVE,
						$product,
						$ft_legacy,
						$ft_like,
					],
					array_map( 'sanitize_text_field', $active_follower_uuids )
				)
			);
			$wpdb->query( $query );
			self::report_db_error( 'Entitlements mark_missing_followers_as_inactive' );
			return (int) $wpdb->rows_affected;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor mark_missing_followers_as_inactive error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
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
			$vals    = self::tier_filter_match_values( $tier, $product );
			$ph      = implode( ',', array_fill( 0, count( $vals ), '%s' ) );
			$where[] = "tier IN ($ph)";
			$args    = array_merge( $args, $vals );
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
			$vals    = self::tier_filter_match_values( $tier, $product );
			$ph      = implode( ',', array_fill( 0, count( $vals ), '%s' ) );
			$where[] = "tier IN ($ph)";
			$args    = array_merge( $args, $vals );
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
	 * Active entitlement rows for a WordPress user (by wp_user_id or email).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_active_entitlement_rows_for_wp_user( $user_id ) {
		self::maybe_ensure_schema();
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return [];
		}

		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		$select_cols = implode(
			', ',
			[
				'id',
				'fanvue_user_uuid',
				'creatorreactor_uuid',
				'creatorreactor_user_uuid',
				'email',
				'display_name',
				'tier',
				'product',
				'status',
				'expires_at',
				'wp_user_id',
			]
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$select_cols} FROM {$table} WHERE status = %s AND expires_at > %s AND (wp_user_id = %d OR email = %s)",
				self::STATUS_ACTIVE,
				$now,
				(int) $user_id,
				$user->user_email
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Follower-tier rows that stay entitlement-bearing while status is inactive (sync/mark-missing convention).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_inactive_unexpired_follower_entitlement_rows_for_wp_user( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return [];
		}
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );
		$select_cols = implode(
			', ',
			[
				'id',
				'fanvue_user_uuid',
				'creatorreactor_uuid',
				'creatorreactor_user_uuid',
				'email',
				'display_name',
				'tier',
				'product',
				'status',
				'expires_at',
				'wp_user_id',
			]
		);
		list( $ft_sql, $ft_legacy, $ft_like ) = self::follower_tier_sql_match();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$select_cols} FROM {$table} WHERE status = %s AND expires_at > %s AND (wp_user_id = %d OR email = %s) AND ($ft_sql)",
				array_merge(
					[ self::STATUS_INACTIVE, $now, (int) $user_id, $user->user_email ],
					[ $ft_legacy, $ft_like ]
				)
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Build a portable snapshot array: one entry per product with subscriber/follower flags and tier list.
	 *
	 * @param string $source How the snapshot was triggered (e.g. wp_login, fanvue_oauth).
	 * @return array<string, mixed>
	 */
	public static function build_social_entitlements_snapshot( $user_id, $source = 'db' ) {
		$user_id = (int) $user_id;
		$merged  = [];
		foreach ( self::get_active_entitlement_rows_for_wp_user( $user_id ) as $row ) {
			if ( isset( $row['id'] ) ) {
				$merged[ (int) $row['id'] ] = $row;
			}
		}
		foreach ( self::get_inactive_unexpired_follower_entitlement_rows_for_wp_user( $user_id ) as $row ) {
			if ( isset( $row['id'] ) ) {
				$merged[ (int) $row['id'] ] = $row;
			}
		}

		$by_product = [];
		foreach ( $merged as $row ) {
			$p = self::normalize_product( $row['product'] ?? self::PRODUCT_FANVUE );
			if ( ! isset( $by_product[ $p ] ) ) {
				$by_product[ $p ] = [
					'subscriber' => false,
					'follower'   => false,
					'tiers'      => [],
					'uuids'      => [],
				];
			}
			$tier = isset( $row['tier'] ) ? (string) $row['tier'] : '';
			$st   = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( $tier !== '' && ! in_array( $tier, $by_product[ $p ]['tiers'], true ) ) {
				$by_product[ $p ]['tiers'][] = $tier;
			}
			$ext_uuid = isset( $row['creatorreactor_uuid'] ) ? sanitize_text_field( (string) $row['creatorreactor_uuid'] ) : '';
			if ( $ext_uuid === '' ) {
				$ext_uuid = isset( $row['creatorreactor_user_uuid'] ) ? sanitize_text_field( (string) $row['creatorreactor_user_uuid'] ) : '';
			}
			if ( $ext_uuid !== '' && ! in_array( $ext_uuid, $by_product[ $p ]['uuids'], true ) ) {
				$by_product[ $p ]['uuids'][] = $ext_uuid;
			}
			if ( self::tier_stored_is_follower( $tier ) && ( $st === self::STATUS_ACTIVE || $st === self::STATUS_INACTIVE ) ) {
				$by_product[ $p ]['follower'] = true;
			}
			if ( self::tier_stored_is_subscriber( $tier ) && $st === self::STATUS_ACTIVE ) {
				$by_product[ $p ]['subscriber'] = true;
			}
		}

		$fanvue_uuid = get_user_meta( $user_id, self::USERMETA_CREATORREACTOR_UUID, true );
		$fanvue_uuid = is_string( $fanvue_uuid ) ? sanitize_text_field( $fanvue_uuid ) : '';
		$creatorreactor_uuid = '';
		foreach ( $merged as $row ) {
			$creatorreactor_uuid = isset( $row['creatorreactor_uuid'] ) ? sanitize_text_field( (string) $row['creatorreactor_uuid'] ) : '';
			if ( $creatorreactor_uuid !== '' ) {
				break;
			}
		}

		return [
			'v'          => self::SOCIAL_ENTITLEMENTS_SNAPSHOT_VERSION,
			'updated_at' => gmdate( 'c' ),
			'source'     => is_string( $source ) ? sanitize_key( $source ) : 'db',
			'user_id'    => $user_id,
			'by_product' => $by_product,
			'linked'     => array_filter(
				[
					'fanvueUserUUID'      => $fanvue_uuid !== '' ? $fanvue_uuid : null,
					'creatorReactorUUID'  => $creatorreactor_uuid !== '' ? $creatorreactor_uuid : null,
				]
			),
		];
	}

	/**
	 * Persist {@see build_social_entitlements_snapshot()} as JSON user meta.
	 *
	 * @param string $source Trigger label stored in the snapshot.
	 * @return bool True if meta was updated.
	 */
	public static function refresh_social_entitlements_user_meta( $user_id, $source = 'wp_login' ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$snapshot = self::build_social_entitlements_snapshot( $user_id, $source );
		$json     = wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return false;
		}
		update_user_meta( $user_id, self::USERMETA_SOCIAL_ENTITLEMENTS, $json );
		/**
		 * Fires after the social entitlements snapshot user meta is refreshed.
		 *
		 * @param int                  $user_id  User ID.
		 * @param array<string, mixed> $snapshot Decoded snapshot (same as stored).
		 */
		do_action( 'creatorreactor_social_entitlements_refreshed', $user_id, $snapshot );
		return true;
	}

	/**
	 * @return array<string, mixed>|null Decoded snapshot or null.
	 */
	public static function get_social_entitlements_snapshot( $user_id ) {
		$raw = get_user_meta( (int) $user_id, self::USERMETA_SOCIAL_ENTITLEMENTS, true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Whether a stored tier value represents a follower (not a paid subscriber tier).
	 */
	public static function tier_stored_is_follower( $tier ) {
		if ( $tier === null || $tier === '' ) {
			return false;
		}
		$tier = (string) $tier;
		return $tier === self::TIER_FOLLOWER || str_ends_with( $tier, '_follower' );
	}

	/**
	 * Whether a stored tier value represents a subscriber (any non-follower tier with a value).
	 */
	public static function tier_stored_is_subscriber( $tier ) {
		if ( $tier === null || $tier === '' ) {
			return false;
		}
		return ! self::tier_stored_is_follower( $tier );
	}

	/**
	 * User may see [follower] content: has an active follower tier (not paid subscriber-only rows).
	 */
	public static function wp_user_has_active_follower_entitlement( $user_id ) {
		foreach ( self::get_active_entitlement_rows_for_wp_user( $user_id ) as $row ) {
			if ( self::tier_stored_is_follower( $row['tier'] ?? '' ) ) {
				return true;
			}
		}
		foreach ( self::get_inactive_unexpired_follower_entitlement_rows_for_wp_user( $user_id ) as $row ) {
			if ( self::tier_stored_is_follower( $row['tier'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * User may see [subscriber] content: has an active paid subscriber tier only.
	 */
	public static function wp_user_has_active_subscriber_entitlement( $user_id ) {
		foreach ( self::get_active_entitlement_rows_for_wp_user( $user_id ) as $row ) {
			if ( self::tier_stored_is_subscriber( $row['tier'] ?? '' ) ) {
				return true;
			}
		}
		return false;
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

	/**
	 * One-time: rewrite legacy follower tier token to {product}_follower using the row's product column.
	 */
	public static function maybe_migrate_legacy_follower_tier_stored() {
		if ( get_option( self::OPTION_TIER_FOLLOWER_FORMAT_MIGRATED, '' ) === '1' ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			update_option( self::OPTION_TIER_FOLLOWER_FORMAT_MIGRATED, '1' );
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers from $wpdb->prefix only.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET tier = CONCAT(product, '_follower') WHERE tier = %s",
				self::TIER_FOLLOWER
			)
		);
		self::report_db_error( 'Entitlements migrate legacy follower tier' );

		update_option( self::OPTION_TIER_FOLLOWER_FORMAT_MIGRATED, '1' );
	}
}
