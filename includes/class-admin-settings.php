<?php
/**
 * Unified Admin Settings for CreatorReactor
 * Supports Creator (direct OAuth) and Agency (broker) authentication modes.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	const OPTION_NAME                 = 'creatorreactor_settings';
	const OPTION_LAST_ERROR           = 'creatorreactor_last_error';
	const OPTION_CRITICAL_ERROR       = 'creatorreactor_critical_error';
	const OPTION_LAST_SYNC            = 'creatorreactor_last_sync';
	const OPTION_CONNECTION_TEST      = 'creatorreactor_connection_test';
	/** Option key: connection / OAuth log entries (array of { time, level, message }). */
	const OPTION_CONNECTION_LOGS      = 'creatorreactor_connection_logs';
	const MAX_CONNECTION_LOG_ENTRIES  = 500;
	const OPTION_TIERS               = 'creatorreactor_tiers';
	const OPTION_SUBSCRIPTION_TIERS  = 'creatorreactor_subscription_tiers';
	const ENCRYPTED_FIELDS            = [ 'creatorreactor_oauth_client_id', 'creatorreactor_oauth_client_secret' ];
	const DEFAULT_CREATORREACTOR_SCOPES       = 'openid offline_access offline read:self read:fan';
	const PAGE_SLUG                   = 'creatorreactor';

	/** Form value authentication_mode maps to stored broker_mode (Agency = true). */
	const AUTH_MODE_CREATOR = 'creator';
	const AUTH_MODE_AGENCY  = 'agency';

	/** Legacy wp_options keys from installs before the CreatorReactor rename. */
	const LEGACY_OPTION_BROKER        = 'fan' . 'bridge_broker_options';
	const LEGACY_OPTION_DIRECT        = 'fan' . 'bridge_direct_options';

	private static function get_current_product() {
		return Entitlements::PRODUCT_FANVUE;
	}

	private static function get_current_product_label() {
		return Entitlements::product_label( self::get_current_product() );
	}

	public static function init() {
		self::migrate_prefixed_options_from_before_rename();
		self::migrate_legacy_options();
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_oauth_start' ], 1 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_post_creatorreactor_disconnect', [ __CLASS__, 'handle_disconnect' ] );
		add_action( 'admin_post_creatorreactor_test_connection', [ __CLASS__, 'handle_connection_test' ] );
		add_action( 'admin_post_creatorreactor_clear_connection_logs', [ __CLASS__, 'handle_clear_connection_logs' ] );
		add_action( 'wp_ajax_creatorreactor_auth_mode_fields', [ __CLASS__, 'ajax_auth_mode_fields' ] );
		add_action( 'wp_ajax_creatorreactor_get_users_table', [ __CLASS__, 'ajax_get_users_table' ] );
	}

	/**
	 * Copy options from pre-rename keys into creatorreactor_* keys once.
	 */
	private static function migrate_prefixed_options_from_before_rename() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$pre = 'fan' . 'bridge';

		$pairs = [
			$pre . '_last_error'           => self::OPTION_LAST_ERROR,
			$pre . '_critical_error'       => self::OPTION_CRITICAL_ERROR,
			$pre . '_last_sync'            => self::OPTION_LAST_SYNC,
			$pre . '_connection_test'      => self::OPTION_CONNECTION_TEST,
			$pre . '_tiers'                => self::OPTION_TIERS,
			$pre . '_subscription_tiers'   => self::OPTION_SUBSCRIPTION_TIERS,
			$pre . '_oauth_tokens'         => CreatorReactor_OAuth::OPTION_TOKENS,
			$pre . '_login_jwt_token'      => 'creatorreactor_login_jwt_token',
		];

		$sentinel = '__creatorreactor_migrate_absent__';

		foreach ( $pairs as $old_key => $new_key ) {
			$old_val = get_option( $old_key, $sentinel );
			if ( $old_val === $sentinel ) {
				continue;
			}
			if ( get_option( $new_key, $sentinel ) !== $sentinel ) {
				delete_option( $old_key );
				continue;
			}
			update_option( $new_key, $old_val );
			delete_option( $old_key );
		}
	}

	private static function migrate_legacy_options() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( is_array( $opts ) && ! empty( $opts ) ) {
			$merged_opts = self::merge_legacy_oauth_fields( $opts );
			$merged_opts = self::maybe_upgrade_fanvue_default_endpoints( $merged_opts );
			if ( $merged_opts !== $opts ) {
				update_option( self::OPTION_NAME, $merged_opts );
			}
			return;
		}

		$broker_opts = get_option( self::LEGACY_OPTION_BROKER, [] );
		$direct_opts = get_option( self::LEGACY_OPTION_DIRECT, [] );

		$opts = [
			'product' => Entitlements::PRODUCT_FANVUE,
			'broker_mode' => false,
			'broker_url' => '',
			'site_id' => '',
			'creatorreactor_oauth_client_id' => '',
			'creatorreactor_oauth_client_secret' => '',
			'creatorreactor_oauth_redirect_uri' => CreatorReactor_OAuth::get_default_redirect_uri(),
			'creatorreactor_authorization_url' => CreatorReactor_OAuth::AUTH_URL,
			'creatorreactor_token_url' => CreatorReactor_OAuth::TOKEN_URL,
			'creatorreactor_api_base_url' => CreatorReactor_OAuth::API_BASE_URL,
			'creatorreactor_oauth_scopes' => self::DEFAULT_CREATORREACTOR_SCOPES,
			'creatorreactor_api_version' => '2025-06-26',
			'creatorreactor_creator_id' => '',
			'cron_interval_minutes' => 15,
			'entitlement_cache_ttl_seconds' => 900,
		];

		if ( is_array( $broker_opts ) && ! empty( $broker_opts ) ) {
			$opts['broker_mode'] = true;
			$opts['broker_url'] = $broker_opts['broker_url'] ?? '';
			$opts['site_id'] = $broker_opts['site_id'] ?? '';
			$opts['creatorreactor_oauth_client_id'] = $broker_opts['creatorreactor_oauth_client_id'] ?? '';
			$opts['creatorreactor_oauth_client_secret'] = $broker_opts['creatorreactor_oauth_client_secret'] ?? '';
			$opts['creatorreactor_oauth_redirect_uri'] = $broker_opts['creatorreactor_oauth_redirect_uri'] ?? self::get_broker_default_redirect_uri();
			$opts['creatorreactor_oauth_scopes'] = $broker_opts['creatorreactor_oauth_scopes'] ?? self::DEFAULT_CREATORREACTOR_SCOPES;
			$opts['creatorreactor_api_base_url'] = $broker_opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL;
		} elseif ( is_array( $direct_opts ) && ! empty( $direct_opts ) ) {
			$opts['broker_mode'] = false;
			$opts['creatorreactor_oauth_client_id'] = $direct_opts['creatorreactor_oauth_client_id'] ?? '';
			$opts['creatorreactor_oauth_client_secret'] = $direct_opts['creatorreactor_oauth_client_secret'] ?? '';
			$opts['creatorreactor_oauth_redirect_uri'] = $direct_opts['creatorreactor_oauth_redirect_uri'] ?? CreatorReactor_OAuth::get_default_redirect_uri();
			$opts['creatorreactor_oauth_scopes'] = $direct_opts['creatorreactor_oauth_scopes'] ?? CreatorReactor_OAuth::DEFAULT_SCOPES;
			$opts['creatorreactor_api_base_url'] = $direct_opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL;
			$opts['creatorreactor_creator_id'] = $direct_opts['creatorreactor_creator_id'] ?? '';
			$opts['cron_interval_minutes'] = $direct_opts['cron_interval_minutes'] ?? 15;
			$opts['entitlement_cache_ttl_seconds'] = $direct_opts['entitlement_cache_ttl_seconds'] ?? 900;
		}

		update_option( self::OPTION_NAME, $opts );
	}

	private static function is_missing_sensitive_setting( $value ) {
		if ( empty( $value ) || $value === '********' ) {
			return true;
		}
		if ( self::is_encrypted( $value ) ) {
			return self::decrypt_value( (string) $value ) === null;
		}
		return false;
	}

	private static function merge_legacy_oauth_fields( array $opts ) {
		$client_id_keys = [ 'creatorreactor_oauth_client_id', 'creatorreactor_client_id', 'oauth_client_id', 'client_id' ];
		$secret_keys    = [ 'creatorreactor_oauth_client_secret', 'creatorreactor_client_secret', 'oauth_client_secret', 'client_secret' ];

		$needs_client_id = self::is_missing_sensitive_setting( $opts['creatorreactor_oauth_client_id'] ?? '' );
		$needs_secret    = self::is_missing_sensitive_setting( $opts['creatorreactor_oauth_client_secret'] ?? '' );

		if ( $needs_client_id ) {
			$fallback_client_id = self::find_first_usable_sensitive_value( $opts, $client_id_keys );
			if ( $fallback_client_id !== null ) {
				$opts['creatorreactor_oauth_client_id'] = $fallback_client_id;
				$needs_client_id = false;
			}
		}

		if ( $needs_secret ) {
			$fallback_secret = self::find_first_usable_sensitive_value( $opts, $secret_keys );
			if ( $fallback_secret !== null ) {
				$opts['creatorreactor_oauth_client_secret'] = $fallback_secret;
				$needs_secret = false;
			}
		}

		if ( ! $needs_client_id && ! $needs_secret ) {
			return $opts;
		}

		$prefer_broker = ! empty( $opts['broker_mode'] );
		$legacy_sources = $prefer_broker
			? [ self::LEGACY_OPTION_BROKER, self::LEGACY_OPTION_DIRECT ]
			: [ self::LEGACY_OPTION_DIRECT, self::LEGACY_OPTION_BROKER ];

		foreach ( $legacy_sources as $legacy_option_name ) {
			$legacy_opts = get_option( $legacy_option_name, [] );
			if ( ! is_array( $legacy_opts ) || empty( $legacy_opts ) ) {
				continue;
			}

			if ( $needs_client_id ) {
				$legacy_client_id = self::find_first_usable_sensitive_value( $legacy_opts, $client_id_keys );
				if ( $legacy_client_id !== null ) {
					$opts['creatorreactor_oauth_client_id'] = $legacy_client_id;
					$needs_client_id = false;
				}
			}

			if ( $needs_secret ) {
				$legacy_secret = self::find_first_usable_sensitive_value( $legacy_opts, $secret_keys );
				if ( $legacy_secret !== null ) {
					$opts['creatorreactor_oauth_client_secret'] = $legacy_secret;
					$needs_secret = false;
				}
			}

			if ( ! $needs_client_id && ! $needs_secret ) {
				break;
			}
		}

		return $opts;
	}

	/**
	 * One-time upgrade: Creator mode previously defaulted to CreatorReactor-hosted URLs; align stored defaults with Fanvue.
	 *
	 * @param array $opts Options array.
	 * @return array
	 */
	private static function maybe_upgrade_fanvue_default_endpoints( array $opts ) {
		if ( ! empty( $opts['broker_mode'] ) ) {
			return $opts;
		}
		$pairs = [
			'creatorreactor_authorization_url' => [
				'old' => 'https://auth.creatorreactor.com/oauth2/auth',
				'new' => CreatorReactor_OAuth::AUTH_URL,
			],
			'creatorreactor_token_url' => [
				'old' => 'https://auth.creatorreactor.com/oauth2/token',
				'new' => CreatorReactor_OAuth::TOKEN_URL,
			],
			'creatorreactor_api_base_url' => [
				'old' => 'https://api.creatorreactor.com',
				'new' => CreatorReactor_OAuth::API_BASE_URL,
			],
		];
		foreach ( $pairs as $key => $map ) {
			if ( ! isset( $opts[ $key ] ) ) {
				continue;
			}
			if ( trim( (string) $opts[ $key ] ) === $map['old'] ) {
				$opts[ $key ] = $map['new'];
			}
		}
		return $opts;
	}

	private static function find_first_usable_sensitive_value( array $source, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			$value = is_string( $source[ $key ] ) ? $source[ $key ] : '';
			if ( ! self::is_missing_sensitive_setting( $value ) ) {
				return $value;
			}
		}
		return null;
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME,
			[ __CLASS__, 'sanitize_options' ]
		);
	}

	public static function get_options() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			return [];
		}
		return self::decrypt_option_fields( $opts );
	}

	public static function get_raw_options() {
		return get_option( self::OPTION_NAME, [] );
	}

	public static function is_encrypted( $value ) {
		return is_string( $value ) && strlen( $value ) > 50 && preg_match( '/^[A-Za-z0-9+\/=]+$/', $value );
	}

	private static function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-gcm' ) );
		$tag = '';
		$enc = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( $enc === false ) {
			return '';
		}
		return base64_encode( $iv . $tag . $enc );
	}

	public static function encrypt_sensitive_value( $value ) {
		return self::encrypt_value( (string) $value );
	}

	private static function decrypt_value( $encrypted ) {
		$decoded = base64_decode( $encrypted );
		if ( $decoded === false || strlen( $decoded ) < 28 ) {
			return null;
		}
		$iv_len     = openssl_cipher_iv_length( 'aes-256-gcm' );
		$tag_len    = 16;
		$iv         = substr( $decoded, 0, $iv_len );
		$tag        = substr( $decoded, $iv_len, $tag_len );
		$ciphertext = substr( $decoded, $iv_len + $tag_len );
		$key        = self::get_encryption_key();
		$decrypted  = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return $decrypted !== false ? $decrypted : null;
	}

	private static function get_encryption_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secret   = $auth_key ?: wp_salt( 'auth' );
		return hash( 'sha256', $secret, true );
	}

	private static function decrypt_option_fields( array $opts ) {
		foreach ( self::ENCRYPTED_FIELDS as $field ) {
			if ( isset( $opts[ $field ] ) && self::is_encrypted( $opts[ $field ] ) ) {
				$decrypted = self::decrypt_value( $opts[ $field ] );
				$opts[ $field ] = $decrypted !== null ? $decrypted : '';
			}
		}
		return $opts;
	}

	private static function sanitize_https_url( $value ) {
		$url = esc_url_raw( (string) $value, [ 'https' ] );
		if ( $url === '' ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || strtolower( $parts['scheme'] ) !== 'https' ) {
			return '';
		}
		return $url;
	}

	public static function get_broker_default_redirect_uri() {
		return Broker_Client::get_default_redirect_uri();
	}

	/**
	 * Redirect URI for the settings text field: canonical default when unset or blank;
	 * in Creator mode, replaces obsolete fanbridge/v1 paths with the CreatorReactor callback URL.
	 *
	 * @param array $opts        Options from {@see get_options()}.
	 * @param bool  $broker_mode Whether Agency (broker) mode is active.
	 */
	public static function get_redirect_uri_input_value( array $opts, $broker_mode ) {
		$default = $broker_mode ? Broker_Client::get_default_redirect_uri() : CreatorReactor_OAuth::get_default_redirect_uri();
		$stored  = isset( $opts['creatorreactor_oauth_redirect_uri'] ) ? trim( (string) $opts['creatorreactor_oauth_redirect_uri'] ) : '';
		if ( $stored === '' ) {
			return $default;
		}
		if ( ! $broker_mode && strpos( $stored, '/fanbridge/v1/' ) !== false ) {
			return CreatorReactor_OAuth::get_default_redirect_uri();
		}
		return $stored;
	}

	public static function sanitize_options( $input ) {
		$raw_opts = self::get_raw_options();
		$opts     = [];
		$opts['product'] = Entitlements::PRODUCT_FANVUE;

		$auth_mode = isset( $input['authentication_mode'] ) ? sanitize_key( wp_unslash( $input['authentication_mode'] ) ) : '';
		if ( $auth_mode === '' ) {
			$auth_mode = ! empty( $raw_opts['broker_mode'] ) ? self::AUTH_MODE_AGENCY : self::AUTH_MODE_CREATOR;
		}
		if ( $auth_mode !== self::AUTH_MODE_AGENCY ) {
			$auth_mode = self::AUTH_MODE_CREATOR;
		}
		$opts['broker_mode'] = ( $auth_mode === self::AUTH_MODE_AGENCY );

		if ( isset( $input['broker_url'] ) ) {
			$broker_url = self::sanitize_https_url( wp_unslash( $input['broker_url'] ) );
		} else {
			$prev = isset( $raw_opts['broker_url'] ) ? (string) $raw_opts['broker_url'] : '';
			$broker_url = $prev !== '' ? self::sanitize_https_url( $prev ) : '';
		}
		if ( $broker_url === '' && $opts['broker_mode'] ) {
			$broker_url = 'https://auth.ncdlabs.com';
		}
		$opts['broker_url'] = $broker_url;

		if ( isset( $input['site_id'] ) ) {
			$opts['site_id'] = sanitize_text_field( wp_unslash( $input['site_id'] ) );
		} else {
			$opts['site_id'] = isset( $raw_opts['site_id'] ) ? sanitize_text_field( (string) $raw_opts['site_id'] ) : '';
		}

		if ( isset( $input['creatorreactor_oauth_client_id'] ) ) {
			$client_id = sanitize_text_field( wp_unslash( $input['creatorreactor_oauth_client_id'] ) );
			$opts['creatorreactor_oauth_client_id'] = $client_id === '' ? '' : self::encrypt_value( $client_id );
		} else {
			$opts['creatorreactor_oauth_client_id'] = isset( $raw_opts['creatorreactor_oauth_client_id'] ) ? $raw_opts['creatorreactor_oauth_client_id'] : '';
		}

		$client_secret = isset( $input['creatorreactor_oauth_client_secret'] ) ? (string) wp_unslash( $input['creatorreactor_oauth_client_secret'] ) : '';
		if ( $client_secret === '********' || $client_secret === '' ) {
			$opts['creatorreactor_oauth_client_secret'] = isset( $raw_opts['creatorreactor_oauth_client_secret'] ) ? $raw_opts['creatorreactor_oauth_client_secret'] : '';
		} else {
			$encrypted = self::encrypt_value( $client_secret );
			if ( $encrypted === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_client_secret_encrypt_failed',
					__( 'Could not encrypt OAuth Client Secret (OpenSSL unavailable or encryption failed). The previous secret was kept.', 'creatorreactor' )
				);
				$opts['creatorreactor_oauth_client_secret'] = isset( $raw_opts['creatorreactor_oauth_client_secret'] ) ? $raw_opts['creatorreactor_oauth_client_secret'] : '';
			} else {
				$opts['creatorreactor_oauth_client_secret'] = $encrypted;
			}
		}

		$opts['creatorreactor_oauth_redirect_uri'] = isset( $input['creatorreactor_oauth_redirect_uri'] )
			? self::sanitize_https_url( wp_unslash( $input['creatorreactor_oauth_redirect_uri'] ) )
			: ( $opts['broker_mode'] ? self::get_broker_default_redirect_uri() : CreatorReactor_OAuth::get_default_redirect_uri() );
		if ( $opts['creatorreactor_oauth_redirect_uri'] !== '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = trailingslashit( untrailingslashit( $opts['creatorreactor_oauth_redirect_uri'] ) );
		}

		$opts['creatorreactor_authorization_url'] = isset( $input['creatorreactor_authorization_url'] )
			? self::sanitize_https_url( wp_unslash( $input['creatorreactor_authorization_url'] ) )
			: CreatorReactor_OAuth::AUTH_URL;

		$opts['creatorreactor_token_url'] = isset( $input['creatorreactor_token_url'] )
			? self::sanitize_https_url( wp_unslash( $input['creatorreactor_token_url'] ) )
			: CreatorReactor_OAuth::TOKEN_URL;

		$opts['creatorreactor_api_base_url'] = isset( $input['creatorreactor_api_base_url'] )
			? self::sanitize_https_url( wp_unslash( $input['creatorreactor_api_base_url'] ) )
			: CreatorReactor_OAuth::API_BASE_URL;

		$opts['creatorreactor_oauth_scopes'] = isset( $input['creatorreactor_oauth_scopes'] )
			? sanitize_text_field( wp_unslash( $input['creatorreactor_oauth_scopes'] ) )
			: self::DEFAULT_CREATORREACTOR_SCOPES;

		if ( isset( $input['creatorreactor_api_version'] ) ) {
			$opts['creatorreactor_api_version'] = preg_replace( '/[^0-9\-]/', '', sanitize_text_field( wp_unslash( $input['creatorreactor_api_version'] ) ) );
		} else {
			$opts['creatorreactor_api_version'] = isset( $raw_opts['creatorreactor_api_version'] )
				? preg_replace( '/[^0-9\-]/', '', sanitize_text_field( (string) $raw_opts['creatorreactor_api_version'] ) )
				: '2025-06-26';
		}

		if ( isset( $input['cron_interval_minutes'] ) ) {
			$opts['cron_interval_minutes'] = max( 5, (int) $input['cron_interval_minutes'] );
		} else {
			$opts['cron_interval_minutes'] = isset( $raw_opts['cron_interval_minutes'] ) ? max( 5, (int) $raw_opts['cron_interval_minutes'] ) : 15;
		}

		if ( isset( $input['entitlement_cache_ttl_seconds'] ) ) {
			$opts['entitlement_cache_ttl_seconds'] = max( 60, (int) $input['entitlement_cache_ttl_seconds'] );
		} else {
			$opts['entitlement_cache_ttl_seconds'] = isset( $raw_opts['entitlement_cache_ttl_seconds'] ) ? max( 60, (int) $raw_opts['entitlement_cache_ttl_seconds'] ) : 900;
		}

		if ( isset( $input['creatorreactor_creator_id'] ) ) {
			$opts['creatorreactor_creator_id'] = sanitize_text_field( wp_unslash( $input['creatorreactor_creator_id'] ) );
		} else {
			$opts['creatorreactor_creator_id'] = isset( $raw_opts['creatorreactor_creator_id'] ) ? sanitize_text_field( (string) $raw_opts['creatorreactor_creator_id'] ) : '';
		}

		if ( $opts['creatorreactor_oauth_redirect_uri'] === '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: CreatorReactor_OAuth::get_default_redirect_uri();
		}
		if ( $opts['creatorreactor_oauth_scopes'] === '' ) {
			$opts['creatorreactor_oauth_scopes'] = self::DEFAULT_CREATORREACTOR_SCOPES;
		}
		if ( $opts['creatorreactor_api_base_url'] === '' ) {
			$opts['creatorreactor_api_base_url'] = CreatorReactor_OAuth::API_BASE_URL;
		}
		if ( $opts['creatorreactor_api_version'] === '' ) {
			$opts['creatorreactor_api_version'] = '2025-06-26';
		}
		if ( $opts['creatorreactor_authorization_url'] === '' ) {
			$opts['creatorreactor_authorization_url'] = CreatorReactor_OAuth::AUTH_URL;
		}
		if ( $opts['creatorreactor_token_url'] === '' ) {
			$opts['creatorreactor_token_url'] = CreatorReactor_OAuth::TOKEN_URL;
		}

		if ( $opts['broker_mode'] ) {
			if ( empty( $opts['broker_url'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_broker_url_required', __( 'Broker URL is required for Agency (broker) authentication.', 'creatorreactor' ) );
			}
			if ( empty( $opts['site_id'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_site_id_required', __( 'Site ID is required for Agency (broker) authentication.', 'creatorreactor' ) );
			}
		}

		$product_label = Entitlements::product_label( $opts['product'] );

		// Creator (direct) mode: OAuth app credentials are required for token exchange. Agency (broker) mode only
		// requires broker URL and site ID per README / Broker_Client; client ID and redirect are optional on the connect URL.
		if ( ! $opts['broker_mode'] ) {
			if ( empty( $opts['creatorreactor_oauth_client_id'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_client_id_required', sprintf( __( '%s OAuth Client ID is required for Creator (direct) mode.', 'creatorreactor' ), $product_label ) );
			}
			if ( empty( $opts['creatorreactor_oauth_client_secret'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_client_secret_required', sprintf( __( '%s OAuth Client Secret is required for Creator (direct) mode.', 'creatorreactor' ), $product_label ) );
			}
		}

		return $opts;
	}

	/**
	 * Creator (direct) mode: start Fanvue OAuth from Dashboard "Connect" (PKCE + redirect at click time).
	 */
	public static function handle_oauth_start() {
		if ( ! is_admin() || empty( $_GET['creatorreactor_oauth_start'] ) || (string) wp_unslash( $_GET['creatorreactor_oauth_start'] ) !== '1' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::log_connection( 'error', 'OAuth Connect: rejected (user lacks manage_options).' );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) );
			exit;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'creatorreactor_oauth_start' ) ) {
			$msg = __( 'Connect link expired. Reload CreatorReactor settings and click Connect again.', 'creatorreactor' );
			self::log_connection( 'error', 'OAuth Connect: invalid or expired nonce.' );
			self::set_last_error( $msg );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) );
			exit;
		}
		if ( self::is_broker_mode() ) {
			self::log_connection( 'info', 'OAuth Connect: ignored (Agency/broker mode; use broker Connect).' );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) );
			exit;
		}
		$auth_url = CreatorReactor_OAuth::get_authorization_url();
		if ( ! $auth_url ) {
			$msg = __( 'OAuth Client ID is missing. Enter your Fanvue Client ID, save settings, then connect again.', 'creatorreactor' );
			self::log_connection( 'error', 'OAuth Connect: cannot build authorize URL (missing Client ID).' );
			self::set_last_error( $msg );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) );
			exit;
		}
		self::log_connection( 'info', 'OAuth Connect: redirecting browser to Fanvue authorization.' );
		wp_redirect( $auth_url );
		exit;
	}

	public static function set_defaults() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}

		if ( ! isset( $opts['broker_mode'] ) ) {
			$opts['broker_mode'] = false;
		}
		if ( ! isset( $opts['product'] ) || $opts['product'] === '' ) {
			$opts['product'] = Entitlements::PRODUCT_FANVUE;
		}
		if ( ! isset( $opts['broker_url'] ) || $opts['broker_url'] === '' ) {
			$opts['broker_url'] = 'https://auth.ncdlabs.com';
		}
		if ( ! isset( $opts['creatorreactor_authorization_url'] ) || $opts['creatorreactor_authorization_url'] === '' ) {
			$opts['creatorreactor_authorization_url'] = CreatorReactor_OAuth::AUTH_URL;
		}
		if ( ! isset( $opts['creatorreactor_token_url'] ) || $opts['creatorreactor_token_url'] === '' ) {
			$opts['creatorreactor_token_url'] = CreatorReactor_OAuth::TOKEN_URL;
		}
		if ( ! isset( $opts['creatorreactor_api_base_url'] ) || $opts['creatorreactor_api_base_url'] === '' ) {
			$opts['creatorreactor_api_base_url'] = CreatorReactor_OAuth::API_BASE_URL;
		}
		if ( ! isset( $opts['creatorreactor_oauth_scopes'] ) || $opts['creatorreactor_oauth_scopes'] === '' ) {
			$opts['creatorreactor_oauth_scopes'] = self::DEFAULT_CREATORREACTOR_SCOPES;
		}
		if ( ! isset( $opts['creatorreactor_api_version'] ) || $opts['creatorreactor_api_version'] === '' ) {
			$opts['creatorreactor_api_version'] = '2025-06-26';
		}
		if ( ! isset( $opts['creatorreactor_oauth_redirect_uri'] ) || $opts['creatorreactor_oauth_redirect_uri'] === '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: CreatorReactor_OAuth::get_default_redirect_uri();
		}
		if ( ! isset( $opts['cron_interval_minutes'] ) ) {
			$opts['cron_interval_minutes'] = 15;
		}
		if ( ! isset( $opts['entitlement_cache_ttl_seconds'] ) ) {
			$opts['entitlement_cache_ttl_seconds'] = 900;
		}

		update_option( self::OPTION_NAME, $opts );
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'creatorreactor_disconnect' );

		if ( self::is_broker_mode() ) {
			self::log_connection( 'info', 'Disconnect: Agency/broker mode — clearing broker session.' );
			Broker_Client::disconnect();
		} else {
			self::log_connection( 'info', 'Disconnect: Creator/direct mode — deleting stored OAuth tokens.' );
			delete_option( CreatorReactor_OAuth::OPTION_TOKENS );
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&status=disconnected' ) );
		exit;
	}

	public static function is_broker_mode() {
		$opts = self::get_raw_options();
		return ! empty( $opts['broker_mode'] );
	}

	public static function handle_connection_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'creatorreactor_test_connection' );

		self::log_connection( 'info', 'Connection test: started.' );

		if ( self::is_broker_mode() ) {
			$result = Broker_Client::test_connection();
			$checks = [];
			if ( ! empty( $result['checks'] ) && is_array( $result['checks'] ) ) {
				foreach ( $result['checks'] as $check ) {
					if ( ! is_array( $check ) ) {
						continue;
					}
					$checks[] = [
						'label' => isset( $check['label'] ) ? sanitize_text_field( (string) $check['label'] ) : '',
						'pass' => ! empty( $check['pass'] ),
						'message' => isset( $check['message'] ) ? sanitize_text_field( (string) $check['message'] ) : '',
					];
				}
			} else {
				$checks[] = [
					'label' => __( 'Agency (broker) connection', 'creatorreactor' ),
					'pass' => ! empty( $result['success'] ),
					'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
				];
			}
		} else {
			$result = CreatorReactor_Client::test_connection();
			$checks = [];

			if ( ! empty( $result['checks'] ) && is_array( $result['checks'] ) ) {
				foreach ( $result['checks'] as $check ) {
					if ( ! is_array( $check ) ) {
						continue;
					}
					$checks[] = [
						'label' => isset( $check['label'] ) ? sanitize_text_field( (string) $check['label'] ) : '',
						'pass' => ! empty( $check['pass'] ),
						'message' => isset( $check['message'] ) ? sanitize_text_field( (string) $check['message'] ) : '',
					];
				}
			}
		}

		update_option(
			self::OPTION_CONNECTION_TEST,
			[
				'time' => time(),
				'success' => ! empty( $result['success'] ),
				'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
				'checks' => $checks,
			],
			false
		);

		if ( ! empty( $result['success'] ) ) {
			self::log_connection( 'info', 'Connection test: finished successfully. ' . ( isset( $result['message'] ) ? (string) $result['message'] : '' ) );
		} else {
			self::log_connection(
				'error',
				'Connection test: finished with failure. ' . ( isset( $result['message'] ) ? (string) $result['message'] : '' )
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard&status=connection_tested' ) );
		exit;
	}

	/**
	 * Return OAuth + Sync field HTML for the selected authentication mode (AJAX).
	 */
	public static function ajax_auth_mode_fields() {
		check_ajax_referer( 'creatorreactor_auth_mode', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : self::AUTH_MODE_CREATOR;
		$broker_mode = ( $mode === self::AUTH_MODE_AGENCY );

		$opts                = self::get_options();
		$secret_mask         = ! empty( $opts['creatorreactor_oauth_client_secret'] ) ? '********' : '';
		$current_product_label = Entitlements::product_label( Entitlements::PRODUCT_FANVUE );

		ob_start();
		self::render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label );
		$oauth_html = ob_get_clean();

		ob_start();
		self::render_sync_dynamic_fields( $broker_mode, $opts );
		$sync_html = ob_get_clean();

		wp_send_json_success(
			[
				'oauth' => $oauth_html,
				'sync'  => $sync_html,
			]
		);
	}

	/**
	 * Load entitlements totals and latest rows for the Users settings tab.
	 *
	 * @return array{totals: array{total: int, active: int, inactive: int}, rows: array<int, array<string, mixed>>}
	 */
	private static function get_users_tab_snapshot() {
		$user_rows   = [];
		$user_totals = [
			'total'    => 0,
			'active'   => 0,
			'inactive' => 0,
		];

		global $wpdb;
		$table_name   = Entitlements::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists === $table_name ) {
			Entitlements::maybe_add_product_column();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$user_totals['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$user_totals['active'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_ACTIVE )
			);
			$user_totals['inactive'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_INACTIVE )
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$user_rows = $wpdb->get_results( "SELECT product, display_name, email, status, tier, expires_at, updated_at FROM {$table_name} ORDER BY updated_at DESC LIMIT 50", ARRAY_A );
			if ( ! is_array( $user_rows ) ) {
				$user_rows = [];
			}
		}

		return [
			'totals' => $user_totals,
			'rows'   => $user_rows,
		];
	}

	/**
	 * HTML for the Users tab list (totals, toolbar, table). Used on first paint and via AJAX refresh.
	 *
	 * @param array{total: int, active: int, inactive: int} $user_totals Counts.
	 * @param array<int, array<string, mixed>>               $user_rows   Rows from entitlements table.
	 */
	private static function render_users_tab_inner_html( array $user_totals, array $user_rows ) {
		ob_start();
		?>
		<div class="creatorreactor-users-toolbar">
			<p class="creatorreactor-users-totals">
				<strong><?php esc_html_e( 'Total:', 'creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['total'] ); ?>,
				<strong><?php esc_html_e( 'Active:', 'creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['active'] ); ?>,
				<strong><?php esc_html_e( 'Inactive:', 'creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['inactive'] ); ?>
			</p>
			<button type="button" class="button" id="creatorreactor-users-refresh"><?php esc_html_e( 'Refresh list', 'creatorreactor' ); ?></button>
		</div>

		<?php if ( ! empty( $user_rows ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Name', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Email', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Tier', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'creatorreactor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $user_rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( Entitlements::product_label( $row['product'] ?? Entitlements::PRODUCT_FANVUE ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['display_name'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['email'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['status'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['tier'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['expires_at'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['updated_at'] ?: '-' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No synced users found yet. Run a sync and refresh this tab.', 'creatorreactor' ); ?></p>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: return refreshed Users tab inner HTML (same markup as initial render).
	 */
	public static function ajax_get_users_table() {
		check_ajax_referer( 'creatorreactor_users_table', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'creatorreactor' ), 403 );
		}

		$snapshot = self::get_users_tab_snapshot();
		wp_send_json_success( self::render_users_tab_inner_html( $snapshot['totals'], $snapshot['rows'] ) );
	}

	/**
	 * @param bool   $broker_mode Agency mode when true.
	 * @param array  $opts        Options from {@see self::get_options()}.
	 * @param string $secret_mask Mask for client secret field.
	 */
	private static function render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label ) {
		$option_name = self::OPTION_NAME;
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/oauth-auth-mode-fields.php';
	}

	/**
	 * @param bool  $broker_mode Agency mode when true.
	 * @param array $opts        Options from {@see self::get_options()}.
	 */
	private static function render_sync_dynamic_fields( $broker_mode, $opts ) {
		$option_name = self::OPTION_NAME;
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/sync-auth-mode-fields.php';
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$css = '
		.creatorreactor-wrap { margin-top: 20px; max-width: 1100px; }
		.creatorreactor-settings-header { margin-bottom: 20px; }
		.creatorreactor-settings-header h1 { margin-bottom: 5px; }
		.creatorreactor-settings-header p { color: #646970; margin-top: 0; }
		.creatorreactor-section { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
		.creatorreactor-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #dcdcde; font-size: 16px; }
		.creatorreactor-section h3 { margin-top: 0; font-size: 14px; }
		.creatorreactor-users-toolbar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 12px;
			flex-wrap: wrap;
		}
		.creatorreactor-users-toolbar .creatorreactor-users-totals { margin: 0; }
		.creatorreactor-users-inner[aria-busy="true"] { opacity: 0.55; pointer-events: none; transition: opacity 0.15s ease; }
		.creatorreactor-settings-auth-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			overflow: hidden;
			margin-bottom: 20px;
		}
		.creatorreactor-settings-auth-card > h2 {
			margin: 0;
			padding: 16px 20px;
			font-size: 16px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.creatorreactor-settings-auth-card .creatorreactor-settings-block { padding: 20px; border-top: none; }
		.creatorreactor-auth-mode-intro { margin: 0 0 12px; color: #1d2327; font-size: 14px; }
		.creatorreactor-auth-mode-segmented {
			display: inline-flex;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			overflow: hidden;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
		}
		.creatorreactor-auth-mode-segmented label {
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0;
			padding: 10px 22px;
			min-width: 120px;
			background: #f6f7f7;
			cursor: pointer;
			font-weight: 600;
			font-size: 14px;
			color: #2c3338;
			border-right: 1px solid #c3c4c7;
			user-select: none;
		}
		.creatorreactor-auth-mode-segmented label:last-child { border-right: 0; }
		.creatorreactor-auth-mode-segmented label { position: relative; }
		.creatorreactor-auth-mode-segmented input.creatorreactor-auth-mode-input {
			position: absolute;
			opacity: 0;
			width: 1px;
			height: 1px;
			clip: rect(0, 0, 0, 0);
		}
		.creatorreactor-auth-mode-segmented label.is-selected {
			background: #fff;
			box-shadow: inset 0 0 0 2px #2271b1;
			z-index: 1;
		}
		.creatorreactor-auth-mode-segmented input.creatorreactor-auth-mode-input:focus-visible + span {
			outline: 2px solid #2271b1;
			outline-offset: 2px;
		}
		.creatorreactor-auth-mode-hint { margin: 12px 0 0; color: #646970; font-size: 13px; max-width: 520px; }
		.creatorreactor-auth-mode-hint ol { margin: 8px 0 0 1.25em; padding: 0; }
		.creatorreactor-auth-mode-hint ol li { margin: 4px 0; }
		.creatorreactor-auth-mode-hint-url { display: block; margin: 4px 0 0 1.25em; }
		.creatorreactor-mode-notice { padding: 12px 15px; border-radius: 4px; margin: 15px 0; }
		.creatorreactor-mode-notice.direct { background: #f0f6fc; border-left: 4px solid #2271b1; }
		.creatorreactor-mode-notice.broker { background: #f0f6ce; border-left: 4px solid #00a32a; }
		.creatorreactor-mode-notice p { margin: 0; font-size: 13px; }
		.creatorreactor-broker-field { transition: opacity 0.2s ease; }
		.creatorreactor-broker-field:disabled { opacity: 0.5; cursor: not-allowed; }
		.creatorreactor-redirect-uri-row { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; margin: 0 0 4px; }
		.creatorreactor-redirect-uri-row .creatorreactor-oauth-redirect-uri-input { flex: 1 1 240px; min-width: 200px; max-width: 100%; }
		.creatorreactor-redirect-uri-row .creatorreactor-copy-redirect-uri { flex: 0 0 auto; margin-top: 1px; }
		.form-table th { width: 200px; }
		.form-table input[type="text"],
		.form-table input[type="url"],
		.form-table input[type="password"],
		.form-table textarea { width: 100%; max-width: 400px; }
		input.creatorreactor-oauth-client-secret { -webkit-text-security: disc; font-family: Consolas, Monaco, monospace; }
		.form-table .description { color: #646970; font-size: 13px; }
		.creatorreactor-status-row { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
		.creatorreactor-status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
		.creatorreactor-status-green { background: #edfaef; color: #1a7f37; }
		.creatorreactor-status-yellow { background: #fff7e5; color: #8a6d1d; }
		.creatorreactor-status-red { background: #fbeaea; color: #b52727; }
		.creatorreactor-tab-nav { margin: 0 0 16px; }
		.creatorreactor-tab-panel { display: none; }
		.creatorreactor-tab-panel.is-active { display: block; }
		.creatorreactor-sync-row { display: flex; gap: 15px; align-items: flex-end; margin-top: 15px; }
		.creatorreactor-sync-row input[type="number"] { width: 80px; }
		.creatorreactor-check-list { margin: 10px 0 0; }
		.creatorreactor-check-list li { margin-bottom: 8px; }
		.creatorreactor-check-result-pass { color: #1a7f37; font-weight: 600; }
		.creatorreactor-check-result-fail { color: #b52727; font-weight: 600; }
		.creatorreactor-muted { color: #646970; }
		.creatorreactor-meta-list { margin: 0; }
		.creatorreactor-meta-list p { margin: 0 0 10px; }
		.creatorreactor-connection-overview { display: flex; justify-content: space-between; gap: 16px; padding: 14px; border: 1px solid #dcdcde; border-radius: 6px; background: #f6f7f7; margin-bottom: 16px; }
		.creatorreactor-connection-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
		.creatorreactor-connection-actions .creatorreactor-connect-fanvue-hint { flex-basis: 100%; margin: 8px 0 0; max-width: 28rem; }
		.creatorreactor-connection-actions form { margin: 0; }
		.creatorreactor-connection-actions .submit { margin: 0; padding: 0; }
		.creatorreactor-kv { display: grid; grid-template-columns: minmax(120px, 180px) 1fr; gap: 8px 12px; margin: 0; }
		.creatorreactor-kv dt { font-weight: 600; color: #1d2327; }
		.creatorreactor-kv dd { margin: 0; }
		.creatorreactor-test-details { margin-top: 12px; }
		.creatorreactor-test-details summary { cursor: pointer; color: #3858e9; }
		.creatorreactor-product-stack { margin-top: 0; }
		.creatorreactor-product-stack summary { font-weight: 600; }
		.creatorreactor-test-modal-trigger { margin-left: 8px; }
		.creatorreactor-test-errors { margin-top: 14px; padding: 12px; border: 1px solid #f3c7c7; border-radius: 6px; background: #fff7f7; }
		.creatorreactor-test-errors h3 { margin: 0 0 8px; font-size: 13px; color: #7a1f1f; }
		.creatorreactor-test-errors[data-visible="false"] { display: none; }
		.creatorreactor-modal { position: fixed; inset: 0; display: none; z-index: 100000; }
		.creatorreactor-modal[aria-hidden="false"] { display: block; }
		.creatorreactor-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.45); }
		.creatorreactor-modal-dialog { position: relative; width: min(620px, calc(100% - 32px)); max-height: calc(100vh - 64px); margin: 32px auto; background: #fff; border-radius: 8px; border: 1px solid #dcdcde; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25); overflow: auto; }
		.creatorreactor-modal-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px 10px; border-bottom: 1px solid #dcdcde; }
		.creatorreactor-modal-header h3 { margin: 0; font-size: 16px; }
		.creatorreactor-modal-body { padding: 14px 18px; }
		.creatorreactor-modal-footer { padding: 12px 18px 16px; border-top: 1px solid #dcdcde; text-align: right; }
		.creatorreactor-modal-close { border: 0; background: transparent; color: #50575e; cursor: pointer; font-size: 22px; line-height: 1; }
		.creatorreactor-inline-status { margin-left: 8px; }
		.creatorreactor-connection-card { border-width: 1px; }
		.creatorreactor-connection-card.is-red { background: #fff1f1; border-color: #f3c7c7; }
		.creatorreactor-connection-card.is-yellow { background: #fff9e8; border-color: #f1deaa; }
		.creatorreactor-connection-card.is-green { background: #eefbf1; border-color: #b8e2c1; }
		.creatorreactor-connection-alert { margin: 0 0 12px; color: #7a1f1f; font-weight: 600; }
		@media (max-width: 782px) {
			.creatorreactor-connection-overview { flex-direction: column; }
		}
		/* Danger Zone Styles */
		.creatorreactor-danger-zone {
			background: #fff8f8;
			border: 1px solid #f9d6d6;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.creatorreactor-danger-zone h2 {
			margin-top: 0;
			color: #b52727;
			font-size: 16px;
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
		}
		.creatorreactor-danger-zone h2::after {
			content: "";
			display: inline-block;
			width: 0;
			height: 0;
			border-left: 4px solid transparent;
			border-right: 4px solid transparent;
			border-top: 4px solid #666;
			margin-left: auto;
			transition: transform 0.2s ease;
		}
		.creatorreactor-danger-zone.collapsed h2::after {
			transform: rotate(-90deg);
		}
		.creatorreactor-danger-zone-content {
			max-height: 0;
			overflow: hidden;
			transition: max-height 0.3s ease;
		}
		.creatorreactor-danger-zone.expanded .creatorreactor-danger-zone-content {
			max-height: 200px;
		}
		.creatorreactor-settings-container { display: flex; gap: 20px; }
		.creatorreactor-settings-sidebar { width: 200px; flex-shrink: 0; }
		.creatorreactor-sidebar-nav { display: flex; flex-direction: column; }
		.creatorreactor-sidebar-link { display: block; padding: 12px 20px; border-bottom: 1px solid #eee; color: #50575e; text-decoration: none; font-weight: 500; }
		.creatorreactor-sidebar-link.is-active { background: #f6f7f7; color: #007cba; border-left: 3px solid #007cba; }
		.creatorreactor-sidebar-link:hover:not(.is-active) { background: #f5f5f5; }
		.creatorreactor-settings-content { flex: 1; min-width: 0; }
		.creatorreactor-settings-form-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			overflow: hidden;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel { display: none; }
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel.is-active { display: block; }
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > h2 {
			margin: 0;
			padding: 16px 20px;
			font-size: 16px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.creatorreactor-settings-block {
			padding: 20px;
			border-top: 1px solid #dcdcde;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > .creatorreactor-settings-block:first-of-type {
			border-top: none;
		}
		.creatorreactor-settings-block > h3 {
			margin: 0 0 12px;
			padding: 0;
			font-size: 14px;
		}
		.creatorreactor-settings-block .creatorreactor-subsection:first-of-type {
			margin-top: 0;
		}
		.creatorreactor-settings-form-card .creatorreactor-settings-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: flex-end;
			gap: 10px;
			margin: 0;
			padding: 16px 20px;
			border-top: 1px solid #dcdcde;
			position: sticky;
			bottom: 0;
			z-index: 5;
			background: #fff;
			box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.06);
		}
		.creatorreactor-settings-form-card .creatorreactor-settings-actions .submit { margin: 0; padding: 0; }
		.creatorreactor-subsection { margin-top: 25px; }
		.creatorreactor-subsection h4 { margin-top: 0; margin-bottom: 15px; font-size: 15px; color: #50575e; }
		.creatorreactor-advanced { margin-top: 25px; }
		.creatorreactor-advanced-toggle {
			display: inline-flex;
			align-items: center;
			gap: 0.35em;
			padding: 0;
			margin: 0;
			height: auto;
			min-height: 0;
			background: none;
			border: none;
			border-radius: 0;
			box-shadow: none;
			color: #2271b1;
			font-size: 13px;
			font-weight: 400;
			line-height: 1.4;
			cursor: pointer;
			text-decoration: none;
			vertical-align: baseline;
		}
		.creatorreactor-advanced-toggle:hover,
		.creatorreactor-advanced-toggle:focus {
			color: #135e96;
			background: none;
			border: none;
			box-shadow: none;
			text-decoration: underline;
		}
		.creatorreactor-advanced-toggle:focus-visible {
			outline: 1px solid currentColor;
			outline-offset: 2px;
		}
		.creatorreactor-advanced-toggle-label { line-height: 1.4; }
		.creatorreactor-advanced-toggle .creatorreactor-advanced-toggle-icon.dashicons {
			flex-shrink: 0;
			width: 14px;
			height: 14px;
			font-size: 14px;
			line-height: 1;
			transition: transform 0.2s ease;
			transform: rotate(-90deg);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			vertical-align: middle;
		}
		.creatorreactor-advanced.is-expanded .creatorreactor-advanced-toggle-icon { transform: rotate(0deg); }
		.creatorreactor-advanced-panel { margin-top: 12px; }
		.creatorreactor-advanced-panel-inner {
			padding: 0;
			border: none;
			background: transparent;
			border-radius: 0;
		}
		.creatorreactor-advanced-toolbar {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			margin-bottom: 12px;
			flex-wrap: wrap;
		}
		.creatorreactor-advanced-toolbar .creatorreactor-advanced-lock-hint { flex: 1; min-width: 200px; margin: 0; padding-top: 4px; }
		.creatorreactor-advanced-lock .dashicons { width: 18px; height: 18px; font-size: 18px; line-height: 1; }
		.creatorreactor-advanced-lock[aria-pressed="true"] .creatorreactor-advanced-lock-icon-off { display: none; }
		.creatorreactor-advanced-lock[aria-pressed="true"] .creatorreactor-advanced-lock-icon-on { display: inline-block; }
		.creatorreactor-advanced-lock[aria-pressed="false"] .creatorreactor-advanced-lock-icon-on { display: none; }
		.creatorreactor-advanced-lock[aria-pressed="false"] .creatorreactor-advanced-lock-icon-off { display: inline-block; }
		input.creatorreactor-advanced-endpoint-input[readonly] {
			background: #f0f0f1;
			color: #2c3338;
			cursor: not-allowed;
		}
		input.creatorreactor-advanced-endpoint-input:not([readonly]) { cursor: text; }
		@media (max-width: 960px) {
			.creatorreactor-settings-container { flex-direction: column; }
			.creatorreactor-settings-sidebar { width: 100%; }
			.creatorreactor-sidebar-link { border-left: none; }
		}
		.creatorreactor-auth-mode-dynamic[aria-busy="true"] { opacity: 0.55; pointer-events: none; transition: opacity 0.15s ease; }

		';

		wp_register_style( 'creatorreactor-admin', false, [], CREATORREACTOR_VERSION );
		wp_enqueue_style( 'creatorreactor-admin' );
		wp_add_inline_style( 'creatorreactor-admin', $css );

		wp_register_script(
			'creatorreactor-users-tab',
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-users-tab.js',
			[ 'jquery' ],
			CREATORREACTOR_VERSION,
			true
		);
		wp_enqueue_script( 'creatorreactor-users-tab' );
		wp_localize_script(
			'creatorreactor-users-tab',
			'creatorreactorUsersTable',
			[
				'nonce'         => wp_create_nonce( 'creatorreactor_users_table' ),
				'refreshLabel'  => __( 'Refresh list', 'creatorreactor' ),
				'loadError'     => __( 'Error loading user table.', 'creatorreactor' ),
			]
		);

		$product_label = self::get_current_product_label();
		$js = '
		(function() {
			document.addEventListener("DOMContentLoaded", function() {
			var authInputs = document.querySelectorAll(".creatorreactor-auth-mode-input");
			var oauthDynamic = document.getElementById("creatorreactor-oauth-dynamic");
			var syncDynamic = document.getElementById("creatorreactor-sync-dynamic");

			function setupRedirectUriCopyDelegation(root) {
				if (!root || root.dataset.creatorreactorRedirectCopyBound) {
					return;
				}
				root.dataset.creatorreactorRedirectCopyBound = "1";
				root.addEventListener("click", function(e) {
					var btn = e.target.closest(".creatorreactor-copy-redirect-uri");
					if (!btn || !root.contains(btn)) {
						return;
					}
					e.preventDefault();
					var fromAttr = btn.getAttribute("data-copy-text");
					var value = (fromAttr !== null && fromAttr !== "") ? fromAttr : "";
					var input = null;
					if (value === "") {
						var row = btn.closest(".creatorreactor-redirect-uri-row");
						input = row ? row.querySelector(".creatorreactor-oauth-redirect-uri-input") : null;
						if (!input) {
							return;
						}
						value = input.value || "";
					}
					var copiedLabel = (window.creatorreactorAuthMode && window.creatorreactorAuthMode.copiedLabel) || "Copied!";
					var origBtnLabel = btn.textContent;
					function showCopied() {
						btn.textContent = copiedLabel;
						window.setTimeout(function() {
							btn.textContent = origBtnLabel;
						}, 2000);
					}
					function fallbackCopy() {
						if (input) {
							input.focus();
							input.select();
							input.setSelectionRange(0, value.length);
							try {
								if (document.execCommand("copy")) {
									showCopied();
								}
							} catch (err) {}
							return;
						}
						var ta = document.createElement("textarea");
						ta.value = value;
						ta.setAttribute("readonly", "");
						ta.style.position = "fixed";
						ta.style.left = "-9999px";
						document.body.appendChild(ta);
						ta.select();
						try {
							if (document.execCommand("copy")) {
								showCopied();
							}
						} catch (err) {}
						document.body.removeChild(ta);
					}
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(value).then(showCopied).catch(fallbackCopy);
					} else {
						fallbackCopy();
					}
				});
			}
			setupRedirectUriCopyDelegation(oauthDynamic);

			function setupCreatorreactorAdvancedDelegation(root) {
				if (!root || root.dataset.creatorreactorAdvancedBound) {
					return;
				}
				root.dataset.creatorreactorAdvancedBound = "1";
				root.addEventListener("click", function(e) {
					var toggleBtn = e.target.closest(".creatorreactor-advanced-toggle");
					if (toggleBtn && root.contains(toggleBtn)) {
						e.preventDefault();
						var block = toggleBtn.closest(".creatorreactor-advanced");
						var panel = block ? block.querySelector(".creatorreactor-advanced-panel") : null;
						if (!block || !panel) {
							return;
						}
						var expanded = toggleBtn.getAttribute("aria-expanded") === "true";
						var next = !expanded;
						toggleBtn.setAttribute("aria-expanded", next ? "true" : "false");
						panel.hidden = !next;
						block.classList.toggle("is-expanded", next);
						return;
					}
					var lockBtn = e.target.closest(".creatorreactor-advanced-lock");
					if (lockBtn && root.contains(lockBtn)) {
						e.preventDefault();
						var block = lockBtn.closest(".creatorreactor-advanced");
						if (!block) {
							return;
						}
						var pressed = lockBtn.getAttribute("aria-pressed") === "true";
						var nextLocked = !pressed;
						lockBtn.setAttribute("aria-pressed", nextLocked ? "true" : "false");
						var lk = lockBtn.getAttribute("data-label-locked") || "";
						var uk = lockBtn.getAttribute("data-label-unlocked") || "";
						lockBtn.setAttribute("aria-label", nextLocked ? lk : uk);
						block.querySelectorAll(".creatorreactor-advanced-endpoint-input").forEach(function(inp) {
							inp.readOnly = nextLocked;
						});
						block.classList.toggle("is-locked", nextLocked);
					}
				});
			}

			function applyCreatorreactorAdvancedDefaults(root) {
				if (!root) {
					return;
				}
				root.querySelectorAll(".creatorreactor-advanced").forEach(function(block) {
					var toggleBtn = block.querySelector(".creatorreactor-advanced-toggle");
					var panel = block.querySelector(".creatorreactor-advanced-panel");
					var lockBtn = block.querySelector(".creatorreactor-advanced-lock");
					if (!toggleBtn || !panel || !lockBtn) {
						return;
					}
					var expanded = toggleBtn.getAttribute("aria-expanded") === "true";
					panel.hidden = !expanded;
					block.classList.toggle("is-expanded", expanded);
					var locked = lockBtn.getAttribute("aria-pressed") !== "false";
					var lk = lockBtn.getAttribute("data-label-locked") || "";
					var uk = lockBtn.getAttribute("data-label-unlocked") || "";
					lockBtn.setAttribute("aria-label", locked ? lk : uk);
					block.querySelectorAll(".creatorreactor-advanced-endpoint-input").forEach(function(inp) {
						inp.readOnly = locked;
					});
					block.classList.toggle("is-locked", locked);
				});
			}

			function snapshotFieldValues(container) {
				var vals = {};
				if (!container) {
					return vals;
				}
				container.querySelectorAll("input[name], select[name], textarea[name]").forEach(function(el) {
					if (!el.name) {
						return;
					}
					if (el.type === "password") {
						return;
					}
					if (el.type === "checkbox" || el.type === "radio") {
						if (el.checked) {
							vals[el.name] = el.value;
						}
						return;
					}
					vals[el.name] = el.value;
				});
				return vals;
			}

			function restoreFieldValues(container, vals) {
				if (!container || !vals) {
					return;
				}
				container.querySelectorAll("input[name], select[name], textarea[name]").forEach(function(el) {
					if (!el.name || vals[el.name] === undefined) {
						return;
					}
					if (el.type === "password") {
						return;
					}
					if (el.type === "checkbox" || el.type === "radio") {
						if (el.value === vals[el.name]) {
							el.checked = true;
						}
						return;
					}
					el.value = vals[el.name];
				});
			}

			function updateAuthModeLabels() {
				authInputs.forEach(function(inp) {
					var lab = inp.closest("label");
					if (lab) {
						lab.classList.toggle("is-selected", inp.checked);
					}
				});
			}

			function getAuthModeAjaxConfig() {
				if (window.creatorreactorAuthMode && window.creatorreactorAuthMode.ajaxUrl && window.creatorreactorAuthMode.nonce) {
					return window.creatorreactorAuthMode;
				}
				var root = document.getElementById("creatorreactor-auth-mode-root");
				if (root && root.dataset.ajaxUrl && root.dataset.nonce) {
					return { ajaxUrl: root.dataset.ajaxUrl, nonce: root.dataset.nonce };
				}
				return null;
			}

			function loadAuthModeFields(mode) {
				var cfg = getAuthModeAjaxConfig();
				if (!oauthDynamic || !syncDynamic || !cfg) {
					return;
				}
				var prevOauth = snapshotFieldValues(oauthDynamic);
				var prevSync = snapshotFieldValues(syncDynamic);
				oauthDynamic.setAttribute("aria-busy", "true");
				syncDynamic.setAttribute("aria-busy", "true");
				var body = new URLSearchParams();
				body.append("action", "creatorreactor_auth_mode_fields");
				body.append("nonce", cfg.nonce);
				body.append("mode", mode);
				fetch(cfg.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					headers: { "Content-Type": "application/x-www-form-urlencoded" },
					body: body.toString()
				})
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (!res || !res.success || !res.data) {
							return;
						}
						if (typeof res.data.oauth === "string") {
							oauthDynamic.innerHTML = res.data.oauth;
						}
						if (typeof res.data.sync === "string") {
							syncDynamic.innerHTML = res.data.sync;
						}
						restoreFieldValues(oauthDynamic, prevOauth);
						restoreFieldValues(syncDynamic, prevSync);
						applyCreatorreactorAdvancedDefaults(oauthDynamic);
					})
					.catch(function() {})
					.finally(function() {
						oauthDynamic.removeAttribute("aria-busy");
						syncDynamic.removeAttribute("aria-busy");
					});
			}

			function initTabs() {
				var tabLinks = document.querySelectorAll(".creatorreactor-tab-link");
				var tabPanels = document.querySelectorAll(".creatorreactor-tab-panel");
				if (!tabLinks.length || !tabPanels.length) {
					return;
				}

				function activateTab(tabName, updateUrl) {
					tabLinks.forEach(function(link) {
						var isActive = link.getAttribute("data-tab") === tabName;
						link.classList.toggle("nav-tab-active", isActive);
					});

					tabPanels.forEach(function(panel) {
						var isActive = panel.getAttribute("data-tab") === tabName;
						panel.classList.toggle("is-active", isActive);
					});

					if (tabName === "settings" && updateUrl) {
						var sidebarLinks = document.querySelectorAll(".creatorreactor-sidebar-link");
						var sidebarPanels = document.querySelectorAll(".creatorreactor-settings-panel");
						sidebarLinks.forEach(function(link) {
							link.classList.toggle("is-active", link.getAttribute("data-subtab") === "oauth");
						});
						sidebarPanels.forEach(function(panel) {
							panel.classList.toggle("is-active", panel.getAttribute("data-subtab") === "oauth");
						});
					}

					if (updateUrl) {
						var url = new URL(window.location.href);
						url.searchParams.set("tab", tabName);
						if (tabName === "settings") {
							url.searchParams.set("subtab", "oauth");
						}
						window.history.replaceState({}, "", url.toString());
					}
				}

				tabLinks.forEach(function(link) {
					link.addEventListener("click", function(event) {
						event.preventDefault();
						activateTab(link.getAttribute("data-tab"), true);
					});
				});

				var currentTab = new URLSearchParams(window.location.search).get("tab") || "dashboard";
				activateTab(currentTab, false);
			}

			authInputs.forEach(function(inp) {
				inp.addEventListener("change", function() {
					if (!inp.checked) {
						return;
					}
					updateAuthModeLabels();
					loadAuthModeFields(inp.value);
				});
			});
			updateAuthModeLabels();

			initTabs();

			setupCreatorreactorAdvancedDelegation(oauthDynamic);
			applyCreatorreactorAdvancedDefaults(oauthDynamic);

			// Danger Zone collapsible functionality
			var dangerZoneTitle = document.querySelector(".creatorreactor-danger-zone-title");
			var dangerZoneContent = document.querySelector(".creatorreactor-danger-zone-content");
			var dangerZone = document.querySelector(".creatorreactor-danger-zone");
			
			if (dangerZoneTitle && dangerZoneContent && dangerZone) {
				dangerZoneTitle.addEventListener("click", function() {
					dangerZone.classList.toggle("expanded");
					dangerZone.classList.toggle("collapsed");
				});
				
				// Start collapsed by default
				dangerZone.classList.add("collapsed");
			}

			var urlParams = new URLSearchParams(window.location.search);
			var status = urlParams.get("status");
			var testModal = document.getElementById("creatorreactor-test-modal");
			var testErrors = document.getElementById("creatorreactor-test-errors");
			var testModalButtons = document.querySelectorAll(".creatorreactor-open-test-modal");
			var ackButton = document.querySelector(".creatorreactor-ack-test-modal");
			var closeModalButton = document.querySelector(".creatorreactor-modal-close");
			var modalBackdrop = document.querySelector(".creatorreactor-modal-backdrop");
			var modalTime = testModal ? parseInt(testModal.getAttribute("data-test-time") || "0", 10) : 0;
			var ackStorageKey = "creatorreactorConnectionTestAcknowledgedAt";

			function getAcknowledgedTime() {
				try {
					return parseInt(window.localStorage.getItem(ackStorageKey) || "0", 10) || 0;
				} catch (error) {
					return 0;
				}
			}

			function isCurrentTestAcknowledged() {
				return modalTime > 0 && getAcknowledgedTime() >= modalTime;
			}

			function togglePersistentErrors() {
				if (!testErrors) {
					return;
				}
				testErrors.setAttribute("data-visible", isCurrentTestAcknowledged() ? "true" : "false");
			}

			function openTestModal() {
				if (!testModal) {
					return;
				}
				testModal.setAttribute("aria-hidden", "false");
			}

			function closeTestModal() {
				if (!testModal) {
					return;
				}
				testModal.setAttribute("aria-hidden", "true");
			}

			testModalButtons.forEach(function(button) {
				button.addEventListener("click", function(event) {
					event.preventDefault();
					openTestModal();
				});
			});

			if (closeModalButton) {
				closeModalButton.addEventListener("click", closeTestModal);
			}

			if (modalBackdrop) {
				modalBackdrop.addEventListener("click", closeTestModal);
			}

			document.addEventListener("keydown", function(event) {
				if (event.key === "Escape" && testModal && testModal.getAttribute("aria-hidden") === "false") {
					closeTestModal();
				}
			});

			if (ackButton) {
				ackButton.addEventListener("click", function() {
					if (modalTime > 0) {
						try {
							window.localStorage.setItem(ackStorageKey, String(modalTime));
						} catch (error) {}
					}
					togglePersistentErrors();
					closeTestModal();
				});
			}

			var notices = {
				"disconnected": "' . esc_js( sprintf( __( 'Disconnected from %s.', 'creatorreactor' ), $product_label ) ) . '",
				"connected": "' . esc_js( sprintf( __( 'Connected to %s successfully.', 'creatorreactor' ), $product_label ) ) . '",
				"saved": "' . esc_js( __( 'Settings saved.', 'creatorreactor' ) ) . '",
				"connection_tested": "' . esc_js( __( 'Connection test completed.', 'creatorreactor' ) ) . '"
			};

			togglePersistentErrors();
			if (status === "connection_tested" && testModal && modalTime > 0 && !isCurrentTestAcknowledged()) {
				openTestModal();
			}

			if (status && notices[status]) {
				var notice = document.createElement("div");
				notice.className = "notice notice-success is-dismissible";
				notice.innerHTML = "<p>" + notices[status] + "</p>";
				var header = document.querySelector(".creatorreactor-settings-header");
				if (header) {
					header.parentNode.insertBefore(notice, header.nextSibling);
				}

				window.setTimeout(function() {
					notice.style.transition = "opacity 0.2s ease";
					notice.style.opacity = "0";
					window.setTimeout(function() {
						if (notice.parentNode) {
							notice.parentNode.removeChild(notice);
						}
					}, 200);
				}, 4000);

				var cleanUrl = new URL(window.location.href);
				cleanUrl.searchParams.delete("status");
				window.history.replaceState({}, "", cleanUrl.toString());
			}

			function activateSubtab(subtab) {
				sidebarLinks.forEach(function(link) {
					link.classList.toggle("is-active", link.getAttribute("data-subtab") === subtab);
				});
				sidebarPanels.forEach(function(panel) {
					panel.classList.toggle("is-active", panel.getAttribute("data-subtab") === subtab);
				});
			}

			var sidebarLinks = document.querySelectorAll(".creatorreactor-sidebar-link");
			var sidebarPanels = document.querySelectorAll(".creatorreactor-settings-panel");
			if (sidebarLinks.length && sidebarPanels.length) {
				sidebarLinks.forEach(function(link) {
					link.addEventListener("click", function(event) {
						event.preventDefault();
						var subtab = link.getAttribute("data-subtab");
						activateSubtab(subtab);

						var url = new URL(window.location.href);
						url.searchParams.set("subtab", subtab);
						window.history.replaceState({}, "", url.toString());
					});
				});

				var urlParams = new URLSearchParams(window.location.search);
				var initialSubtab = urlParams.get("subtab") || (document.querySelector(".creatorreactor-settings-panel.is-active") ? document.querySelector(".creatorreactor-settings-panel.is-active").getAttribute("data-subtab") : "oauth");
				activateSubtab(initialSubtab);
			}
			});
		})();
		';

		wp_register_script( 'creatorreactor-admin', false, [], CREATORREACTOR_VERSION, true );
		wp_enqueue_script( 'creatorreactor-admin' );
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorAuthMode',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'creatorreactor_auth_mode' ),
				'copyLabel'      => __( 'Copy', 'creatorreactor' ),
				'copiedLabel'    => __( 'Copied!', 'creatorreactor' ),
			]
		);
		wp_add_inline_script( 'creatorreactor-admin', $js );
	}

	public static function get_status_summary() {
		$opts = self::get_options();
		$broker_mode = ! empty( $opts['broker_mode'] );
		$product_label = Entitlements::product_label( Entitlements::PRODUCT_FANVUE );

		$overall = 'green';
		$overall_message = sprintf( __( '%s is ready.', 'creatorreactor' ), $product_label );

		if ( $broker_mode ) {
			$configured = Broker_Client::is_configured();
			$connected = self::is_connected();

			if ( ! $configured ) {
				$overall = 'yellow';
				$overall_message = sprintf( __( 'Agency mode (%s): Set broker URL and Site ID (OAuth app fields below are optional).', 'creatorreactor' ), $product_label );
			} elseif ( ! $connected ) {
				$overall = 'yellow';
				$overall_message = sprintf( __( 'Agency mode (%s): Connect to complete setup.', 'creatorreactor' ), $product_label );
			} else {
				$overall_message = sprintf( __( 'Agency mode (%s): Connected to broker.', 'creatorreactor' ), $product_label );
			}
		} else {
			$client_id = ! empty( $opts['creatorreactor_oauth_client_id'] );
			$secret_set = ! empty( $opts['creatorreactor_oauth_client_secret'] ) && $opts['creatorreactor_oauth_client_secret'] !== '********';
			$connected = self::is_connected();

			if ( ! $client_id || ! $secret_set ) {
				$overall = 'yellow';
				$overall_message = sprintf( __( 'Creator mode (%s): Configure OAuth credentials.', 'creatorreactor' ), $product_label );
			} elseif ( ! $connected ) {
				$overall = 'yellow';
				$overall_message = sprintf( __( 'Creator mode (%s): Connect to complete setup.', 'creatorreactor' ), $product_label );
			} else {
				$overall_message = sprintf( __( 'Creator mode (%s): Connected.', 'creatorreactor' ), $product_label );
			}
		}

		return [
			'overall' => $overall,
			'overall_message' => $overall_message,
			'broker_mode' => $broker_mode,
			'connected' => self::is_connected(),
			'last_sync' => get_option( self::OPTION_LAST_SYNC ),
			'last_error' => get_option( self::OPTION_LAST_ERROR ),
			'critical_error' => get_option( self::OPTION_CRITICAL_ERROR ),
		];
	}

	public static function is_connected() {
		if ( self::is_broker_mode() ) {
			return Broker_Client::is_connected();
		}
		return CreatorReactor_OAuth::is_connected();
	}

	public static function add_menu() {
		add_options_page(
			__( 'CreatorReactor Settings', 'creatorreactor' ),
			__( 'CreatorReactor', 'creatorreactor' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = self::get_status_summary();
		$opts   = self::get_options();
		$current_product = Entitlements::PRODUCT_FANVUE;
		$current_product_label = Entitlements::product_label( $current_product );
		$secret_mask = ! empty( $opts['creatorreactor_oauth_client_secret'] ) ? '********' : '';
		$broker_mode         = ! empty( $opts['broker_mode'] );
		$authentication_mode = $broker_mode ? self::AUTH_MODE_AGENCY : self::AUTH_MODE_CREATOR;
		$connection_test = get_option( self::OPTION_CONNECTION_TEST, [] );
		$next_sync_time = wp_next_scheduled( Cron::HOOK );
		$allowed_tabs = [ 'dashboard', 'users', 'settings' ];
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$active_tab = in_array( $requested_tab, $allowed_tabs, true ) ? $requested_tab : 'dashboard';
		$allowed_subtabs = [ 'oauth', 'sync' ];
		$requested_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'oauth';
		$active_subtab = in_array( $requested_subtab, $allowed_subtabs, true ) ? $requested_subtab : 'oauth';
		$users_snapshot = self::get_users_tab_snapshot();
		$user_totals      = $users_snapshot['totals'];
		$user_rows        = $users_snapshot['rows'];
		$connection_logs  = self::get_connection_logs();
		?>
		<div class="wrap creatorreactor-wrap">
			<div class="creatorreactor-settings-header">
				<h1><?php esc_html_e( 'CreatorReactor Settings', 'creatorreactor' ); ?></h1>
				<p><?php printf( esc_html__( 'Configure OAuth integration for %s.', 'creatorreactor' ), esc_html( $current_product_label ) ); ?></p>
			</div>

			<?php if ( ! empty( $_GET['connection_log_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connection log cleared.', 'creatorreactor' ); ?></p></div>
			<?php endif; ?>

			<div class="creatorreactor-status-row">
				<span class="creatorreactor-status-badge <?php echo 'green' === $status['overall'] ? 'creatorreactor-status-green' : ( 'yellow' === $status['overall'] ? 'creatorreactor-status-yellow' : 'creatorreactor-status-red' ); ?>">
					<?php
					if ( 'green' === $status['overall'] ) {
						esc_html_e( 'Ready', 'creatorreactor' );
					} elseif ( 'yellow' === $status['overall'] ) {
						esc_html_e( 'Setup Required', 'creatorreactor' );
					} else {
						esc_html_e( 'Error', 'creatorreactor' );
					}
					?>
				</span>
				<span><?php echo esc_html( $status['overall_message'] ); ?></span>
			</div>

			<nav class="nav-tab-wrapper creatorreactor-tab-nav" aria-label="<?php esc_attr_e( 'CreatorReactor sections', 'creatorreactor' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) ); ?>" class="nav-tab creatorreactor-tab-link <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="dashboard"><?php esc_html_e( 'Dashboard', 'creatorreactor' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=users' ) ); ?>" class="nav-tab creatorreactor-tab-link <?php echo 'users' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="users"><?php esc_html_e( 'Users', 'creatorreactor' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings&subtab=oauth' ) ); ?>" class="nav-tab creatorreactor-tab-link <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="settings"><?php echo esc_html( $current_product_label ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<?php settings_errors( self::OPTION_NAME ); ?>

<div class="creatorreactor-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
	<div class="creatorreactor-settings-container">
		<div class="creatorreactor-settings-sidebar">
			<nav class="creatorreactor-sidebar-nav">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings&subtab=oauth' ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'oauth' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth"><?php esc_html_e( 'OAuth', 'creatorreactor' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings&subtab=sync' ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'sync' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="sync"><?php esc_html_e( 'Sync', 'creatorreactor' ); ?></a>
			</nav>
		</div>
		<div class="creatorreactor-settings-content">
			<div id="creatorreactor-auth-mode-root" class="creatorreactor-settings-auth-card" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'creatorreactor_auth_mode' ) ); ?>">
				<h2><?php esc_html_e( 'Authentication Modes', 'creatorreactor' ); ?></h2>
				<div class="creatorreactor-settings-block">
					<p class="creatorreactor-auth-mode-intro"><?php esc_html_e( 'Choose what type of Fanvue account you have:', 'creatorreactor' ); ?></p>
					<div class="creatorreactor-auth-mode-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Fanvue account type', 'creatorreactor' ); ?>">
						<label class="<?php echo self::AUTH_MODE_CREATOR === $authentication_mode ? 'is-selected' : ''; ?>">
							<input type="radio" class="creatorreactor-auth-mode-input" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[authentication_mode]" value="<?php echo esc_attr( self::AUTH_MODE_CREATOR ); ?>" <?php checked( $authentication_mode, self::AUTH_MODE_CREATOR ); ?> />
							<span><?php esc_html_e( 'Creator', 'creatorreactor' ); ?></span>
						</label>
						<label class="<?php echo self::AUTH_MODE_AGENCY === $authentication_mode ? 'is-selected' : ''; ?>">
							<input type="radio" class="creatorreactor-auth-mode-input" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[authentication_mode]" value="<?php echo esc_attr( self::AUTH_MODE_AGENCY ); ?>" <?php checked( $authentication_mode, self::AUTH_MODE_AGENCY ); ?> />
							<span><?php esc_html_e( 'Agency', 'creatorreactor' ); ?></span>
						</label>
					</div>
					<div class="creatorreactor-auth-mode-hint">
						<p><strong><?php esc_html_e( 'Creator:', 'creatorreactor' ); ?></strong><br />
						<?php esc_html_e( 'To use Creator mode you need to:', 'creatorreactor' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Log into your Fanvue account.', 'creatorreactor' ); ?></li>
							<li><?php
							printf(
								wp_kses_post(
									/* translators: %s: Fanvue developer apps URL */
									__( 'Go to CREATOR TOOL → BUILD<br /><span class="creatorreactor-auth-mode-hint-url"><a href="%s" target="_blank" rel="noopener noreferrer">https://www.fanvue.com/developers/apps</a></span>', 'creatorreactor' )
								),
								esc_url( 'https://www.fanvue.com/developers/apps' )
							);
							?></li>
							<li><?php esc_html_e( 'Create App (recommended name: CreatorReactor-OAuth).', 'creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the Client ID and Client Secret from your new Fanvue app into the plugin settings.', 'creatorreactor' ); ?></li>
						</ol>
					</div>
				</div>
			</div>
			<div class="creatorreactor-settings-form-card">
			<div class="creatorreactor-settings-panel <?php echo 'oauth' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth">
				<h2><?php esc_html_e( 'OAuth', 'creatorreactor' ); ?></h2>
				<div id="creatorreactor-oauth-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
					<?php self::render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label ); ?>
				</div>
			</div>
			<div class="creatorreactor-settings-panel <?php echo 'sync' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="sync">
				<h2><?php esc_html_e( 'Sync', 'creatorreactor' ); ?></h2>
				<div id="creatorreactor-sync-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
					<?php self::render_sync_dynamic_fields( $broker_mode, $opts ); ?>
				</div>
			</div>
			<div class="creatorreactor-settings-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings&subtab=' . rawurlencode( $active_subtab ) ) ); ?>"><?php esc_html_e( 'Cancel', 'creatorreactor' ); ?></a>
				<?php submit_button( __( 'Save Settings', 'creatorreactor' ) ); ?>
			</div>
			</div>
		</div>
	</div>
</div>
			</form>

			<div class="creatorreactor-tab-panel <?php echo 'users' === $active_tab ? 'is-active' : ''; ?>" data-tab="users">
				<div class="creatorreactor-section">
					<h2><?php esc_html_e( 'Users', 'creatorreactor' ); ?></h2>
					<p class="creatorreactor-muted"><?php esc_html_e( 'Each record shows its source product (fanvue, OnlyFans, or another configured product key).', 'creatorreactor' ); ?></p>
					<div id="creatorreactor-users-inner" class="creatorreactor-users-inner">
						<?php echo self::render_users_tab_inner_html( $user_totals, $user_rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer. ?>
					</div>
				</div>
			</div>

			<div class="creatorreactor-tab-panel <?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>" data-tab="dashboard">
				<?php
				$connection_test_ran = ! empty( $connection_test ) && is_array( $connection_test );
				$connection_test_passed = $connection_test_ran && ! empty( $connection_test['success'] );
				$connection_state = 'yellow';
				$connection_failure_message = '';

				if ( $connection_test_ran && ! $connection_test_passed ) {
					$connection_state = 'red';
					$connection_failure_message = isset( $connection_test['message'] ) ? trim( (string) $connection_test['message'] ) : '';
					if ( ! empty( $connection_test['checks'] ) && is_array( $connection_test['checks'] ) ) {
						foreach ( $connection_test['checks'] as $check ) {
							if ( empty( $check['pass'] ) ) {
								$check_label = isset( $check['label'] ) ? trim( (string) $check['label'] ) : '';
								$check_message = isset( $check['message'] ) ? trim( (string) $check['message'] ) : '';
								if ( $check_message !== '' ) {
									$connection_failure_message = $check_label !== '' ? $check_label . ': ' . $check_message : $check_message;
									break;
								}
							}
						}
					}
				} elseif ( $connection_test_passed && ! $status['connected'] ) {
					$connection_state = 'yellow';
				} elseif ( $connection_test_passed && $status['connected'] ) {
					$connection_state = 'green';
				} elseif ( ! $status['connected'] && ! empty( $status['critical_error'] ) ) {
					$connection_state = 'red';
					$connection_failure_message = trim( (string) $status['critical_error'] );
				}

				$connection_card_class = 'creatorreactor-connection-card is-' . $connection_state;
				?>
				<div class="creatorreactor-section <?php echo esc_attr( $connection_card_class ); ?>">
					<h2><?php esc_html_e( 'Connection Status', 'creatorreactor' ); ?></h2>
					<?php
					$connect_url = '';
					if ( $broker_mode ) {
						$maybe_connect = Broker_Client::get_connect_url();
						$connect_url = ( is_string( $maybe_connect ) && $maybe_connect !== '' ) ? $maybe_connect : '';
					} elseif ( ! empty( $opts['creatorreactor_oauth_client_id'] ) ) {
						$connect_url = admin_url(
							'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard&creatorreactor_oauth_start=1&_wpnonce=' . wp_create_nonce( 'creatorreactor_oauth_start' )
						);
					}
					$last_sync = $status['last_sync'];
					$last_sync_time = is_array( $last_sync ) && isset( $last_sync['time'] ) ? (int) $last_sync['time'] : ( $last_sync ? (int) $last_sync : 0 );
					$last_sync_success = is_array( $last_sync ) && array_key_exists( 'success', $last_sync ) ? ! empty( $last_sync['success'] ) : true;
					?>

					<?php if ( $connection_state === 'red' && $connection_failure_message !== '' ) : ?>
						<p class="creatorreactor-connection-alert">
							<?php echo esc_html( $connection_failure_message ); ?>
						</p>
					<?php endif; ?>

					<div class="creatorreactor-connection-overview">
						<div>
							<p>
								<span class="creatorreactor-status-badge <?php echo 'green' === $connection_state ? 'creatorreactor-status-green' : ( 'red' === $connection_state ? 'creatorreactor-status-red' : 'creatorreactor-status-yellow' ); ?>">
									<?php echo 'green' === $connection_state ? esc_html__( 'Healthy', 'creatorreactor' ) : ( 'red' === $connection_state ? esc_html__( 'Attention', 'creatorreactor' ) : esc_html__( 'Pending', 'creatorreactor' ) ); ?>
								</span>
							</p>
							<p class="creatorreactor-muted">
								<?php
								if ( 'green' === $connection_state ) {
									printf( esc_html__( 'All checks passed and %s is connected.', 'creatorreactor' ), esc_html( $current_product_label ) );
								} elseif ( 'red' === $connection_state ) {
									esc_html_e( 'Connection needs attention. Review the failure and retry.', 'creatorreactor' );
								} else {
									printf( esc_html__( 'Checks look good. Connect to %s to finish setup.', 'creatorreactor' ), esc_html( $current_product_label ) );
								}
								?>
							</p>
						</div>
						<div class="creatorreactor-connection-actions">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'creatorreactor_test_connection' ); ?>
								<input type="hidden" name="action" value="creatorreactor_test_connection" />
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Run Test', 'creatorreactor' ); ?>" />
								</p>
							</form>
							<?php if ( $status['connected'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'creatorreactor_disconnect' ); ?>
									<input type="hidden" name="action" value="creatorreactor_disconnect" />
									<p class="submit">
										<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Disconnect', 'creatorreactor' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect?', 'creatorreactor' ); ?>');" />
									</p>
								</form>
							<?php elseif ( $connect_url ) : ?>
								<a href="<?php echo esc_url( $connect_url, [ 'https', 'http' ] ); ?>" class="button button-secondary"><?php esc_html_e( 'Connect', 'creatorreactor' ); ?></a>
							<?php endif; ?>
					</div>
					</div>

					<?php
					$connection_test_checks = ! empty( $connection_test['checks'] ) && is_array( $connection_test['checks'] ) ? $connection_test['checks'] : [];
					$failed_connection_checks = [];
					if ( ! empty( $connection_test_checks ) ) {
						foreach ( $connection_test_checks as $check ) {
							if ( empty( $check['pass'] ) ) {
								$failed_connection_checks[] = $check;
							}
						}
					}
					?>

					<dl class="creatorreactor-kv">
						<dt><?php esc_html_e( 'Product', 'creatorreactor' ); ?></dt>
						<dd>
							<details class="creatorreactor-test-details creatorreactor-product-stack">
								<summary><?php echo esc_html( $current_product_label ); ?></summary>
								<ul class="creatorreactor-check-list">
									<li><strong><?php esc_html_e( 'Key', 'creatorreactor' ); ?>:</strong> <?php echo esc_html( $current_product ); ?></li>
									<li><strong><?php esc_html_e( 'Mode', 'creatorreactor' ); ?>:</strong> <?php echo $broker_mode ? esc_html__( 'Agency', 'creatorreactor' ) : esc_html__( 'Creator', 'creatorreactor' ); ?></li>
									<li>
										<strong><?php esc_html_e( 'Test', 'creatorreactor' ); ?>:</strong>
										<?php if ( ! empty( $connection_test ) && is_array( $connection_test ) ) : ?>
											<span class="creatorreactor-status-badge <?php echo ! empty( $connection_test['success'] ) ? 'creatorreactor-status-green' : 'creatorreactor-status-red'; ?>">
												<?php echo ! empty( $connection_test['success'] ) ? esc_html__( 'Pass', 'creatorreactor' ) : esc_html__( 'Fail', 'creatorreactor' ); ?>
											</span>
											<?php if ( ! empty( $connection_test['time'] ) ) : ?>
												<span class="creatorreactor-inline-status creatorreactor-muted">
													<?php printf( esc_html__( '%s ago', 'creatorreactor' ), esc_html( human_time_diff( (int) $connection_test['time'], time() ) ) ); ?>
												</span>
											<?php endif; ?>
											<?php if ( ! empty( $connection_test_checks ) ) : ?>
												<button type="button" class="button-link creatorreactor-test-modal-trigger creatorreactor-open-test-modal">
													<?php esc_html_e( 'View details', 'creatorreactor' ); ?>
												</button>
											<?php endif; ?>
										<?php else : ?>
											<span class="creatorreactor-muted"><?php esc_html_e( 'Not run yet', 'creatorreactor' ); ?></span>
										<?php endif; ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Last Sync', 'creatorreactor' ); ?>:</strong>
										<?php if ( $last_sync_time > 0 ) : ?>
											<?php printf( esc_html__( '%s ago', 'creatorreactor' ), esc_html( human_time_diff( $last_sync_time, time() ) ) ); ?>
											<span class="creatorreactor-inline-status <?php echo $last_sync_success ? 'creatorreactor-check-result-pass' : 'creatorreactor-check-result-fail'; ?>">
												<?php echo $last_sync_success ? esc_html__( 'Success', 'creatorreactor' ) : esc_html__( 'Failed', 'creatorreactor' ); ?>
											</span>
										<?php else : ?>
											<span class="creatorreactor-muted"><?php esc_html_e( 'No sync has run yet', 'creatorreactor' ); ?></span>
										<?php endif; ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Next Sync', 'creatorreactor' ); ?>:</strong>
										<?php if ( $next_sync_time ) : ?>
											<?php printf( esc_html__( '%s from now', 'creatorreactor' ), esc_html( human_time_diff( time(), (int) $next_sync_time ) ) ); ?>
										<?php else : ?>
											<span class="creatorreactor-check-result-fail"><?php esc_html_e( 'Not scheduled', 'creatorreactor' ); ?></span>
										<?php endif; ?>
									</li>
								</ul>
							</details>
						</dd>
					</dl>

					<?php if ( ! empty( $connection_test_checks ) ) : ?>
						<div id="creatorreactor-test-modal" class="creatorreactor-modal" aria-hidden="true" data-test-time="<?php echo esc_attr( (string) (int) ( $connection_test['time'] ?? 0 ) ); ?>">
							<div class="creatorreactor-modal-backdrop"></div>
							<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-test-modal-title">
								<div class="creatorreactor-modal-header">
									<h3 id="creatorreactor-test-modal-title"><?php esc_html_e( 'Test Details', 'creatorreactor' ); ?></h3>
									<button type="button" class="creatorreactor-modal-close" aria-label="<?php esc_attr_e( 'Close', 'creatorreactor' ); ?>">&times;</button>
								</div>
								<div class="creatorreactor-modal-body">
									<ul class="creatorreactor-check-list">
										<?php foreach ( $connection_test_checks as $check ) : ?>
											<?php
											$check_label = isset( $check['label'] ) ? (string) $check['label'] : '';
											$check_message = isset( $check['message'] ) ? (string) $check['message'] : '';
											$check_pass = ! empty( $check['pass'] );
											?>
											<li>
												<strong><?php echo esc_html( $check_label ); ?>:</strong>
												<span class="<?php echo $check_pass ? 'creatorreactor-check-result-pass' : 'creatorreactor-check-result-fail'; ?>">
													<?php echo $check_pass ? esc_html__( 'OK', 'creatorreactor' ) : esc_html__( 'Issue', 'creatorreactor' ); ?>
												</span>
												<?php if ( $check_message !== '' ) : ?>
													&mdash; <?php echo esc_html( $check_message ); ?>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="creatorreactor-modal-footer">
									<button type="button" class="button button-primary creatorreactor-ack-test-modal"><?php esc_html_e( 'Acknowledge', 'creatorreactor' ); ?></button>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $status['last_error'] ) : ?>
						<p class="creatorreactor-check-result-fail">
							<strong><?php esc_html_e( 'Last Error:', 'creatorreactor' ); ?></strong>
							<?php echo esc_html( $status['last_error'] ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $failed_connection_checks ) ) : ?>
						<div id="creatorreactor-test-errors" class="creatorreactor-test-errors" data-visible="false">
							<h3><?php esc_html_e( 'Active Test Errors', 'creatorreactor' ); ?></h3>
							<ul class="creatorreactor-check-list">
								<?php foreach ( $failed_connection_checks as $check ) : ?>
									<?php
									$check_label = isset( $check['label'] ) ? (string) $check['label'] : '';
									$check_message = isset( $check['message'] ) ? (string) $check['message'] : '';
									?>
									<li>
										<strong><?php echo esc_html( $check_label ); ?>:</strong>
										<span class="creatorreactor-check-result-fail"><?php esc_html_e( 'Issue', 'creatorreactor' ); ?></span>
										<?php if ( $check_message !== '' ) : ?>
											&mdash; <?php echo esc_html( $check_message ); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="creatorreactor-connection-log">
						<h3><?php esc_html_e( 'Connection log', 'creatorreactor' ); ?></h3>
						<p class="description"><?php esc_html_e( 'OAuth, broker, and connection-test events (newest first). Mirrors to the PHP error log when available.', 'creatorreactor' ); ?></p>
						<?php if ( empty( $connection_logs ) ) : ?>
							<p class="creatorreactor-muted"><?php esc_html_e( 'No log entries yet.', 'creatorreactor' ); ?></p>
						<?php else : ?>
							<ul class="creatorreactor-connection-log-list" style="max-height: 22rem; overflow: auto; font-family: monospace; font-size: 12px; background: #fff; border: 1px solid #dcdcde; padding: 10px 12px; margin: 0 0 12px; list-style: none;">
								<?php
								$logs_rev = array_reverse( $connection_logs );
								foreach ( $logs_rev as $entry ) {
									if ( ! is_array( $entry ) ) {
										continue;
									}
									$t = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
									$lvl = isset( $entry['level'] ) ? (string) $entry['level'] : 'info';
									$msg = isset( $entry['message'] ) ? (string) $entry['message'] : '';
									$line = gmdate( 'Y-m-d H:i:s', $t ) . ' UTC [' . $lvl . '] ' . $msg;
									echo '<li style="margin: 0 0 6px;">' . esc_html( $line ) . '</li>';
								}
								?>
							</ul>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
							<?php wp_nonce_field( 'creatorreactor_clear_connection_logs' ); ?>
							<input type="hidden" name="action" value="creatorreactor_clear_connection_logs" />
							<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Clear connection log', 'creatorreactor' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear all connection log entries?', 'creatorreactor' ) ); ?>');" />
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function set_last_error( $message ) {
		update_option( self::OPTION_LAST_ERROR, (string) $message, false );
	}

	public static function set_critical_error( $message ) {
		update_option( self::OPTION_CRITICAL_ERROR, (string) $message, false );
	}

	/**
	 * Append a connection/OAuth log entry (stored in options, capped) and mirror to PHP error_log.
	 *
	 * @param string $level   'info'|'error'|'debug'.
	 * @param string $message Plain text; never include secrets or bearer tokens.
	 */
	public static function log_connection( $level, $message ) {
		$level = in_array( $level, [ 'info', 'error', 'debug' ], true ) ? $level : 'info';
		if ( ! is_string( $message ) ) {
			$message = wp_json_encode( $message );
		}
		$message = wp_strip_all_tags( (string) $message );
		$message = preg_replace( '/\s+/', ' ', $message );
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 4000 ) . '…';
		}
		$logs = get_option( self::OPTION_CONNECTION_LOGS, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}
		$logs[] = [
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		];
		if ( count( $logs ) > self::MAX_CONNECTION_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_CONNECTION_LOG_ENTRIES );
		}
		update_option( self::OPTION_CONNECTION_LOGS, $logs, false );
		if ( function_exists( 'error_log' ) ) {
			error_log( '[CreatorReactor][' . $level . '] ' . $message );
		}
	}

	/**
	 * @return array<int, array{time:int, level:string, message:string}>
	 */
	public static function get_connection_logs() {
		$logs = get_option( self::OPTION_CONNECTION_LOGS, [] );
		return is_array( $logs ) ? $logs : [];
	}

	public static function clear_connection_logs() {
		delete_option( self::OPTION_CONNECTION_LOGS );
	}

	public static function handle_clear_connection_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'creatorreactor' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'creatorreactor_clear_connection_logs' );
		self::clear_connection_logs();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard&connection_log_cleared=1' ) );
		exit;
	}
}
