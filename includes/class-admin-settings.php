<?php
/**
 * Unified Admin Settings for FanBridge
 * Supports both Direct and Broker modes with dynamic toggle
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace FanBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	const OPTION_NAME                 = 'fanvue_settings';
	const OPTION_LAST_ERROR           = 'fanbridge_last_error';
	const OPTION_CRITICAL_ERROR       = 'fanbridge_critical_error';
	const OPTION_LAST_SYNC            = 'fanbridge_last_sync';
	const OPTION_CONNECTION_TEST      = 'fanbridge_connection_test';
	const OPTION_TIERS               = 'fanbridge_tiers';
	const OPTION_SUBSCRIPTION_TIERS  = 'fanbridge_subscription_tiers';
	const ENCRYPTED_FIELDS            = [ 'fanvue_oauth_client_id', 'fanvue_oauth_client_secret' ];
	const DEFAULT_FANVUE_SCOPES       = 'openid offline_access offline read:self read:fan';
	const PAGE_SLUG                   = 'fanbridge';

	const LEGACY_OPTION_BROKER        = 'fanbridge_broker_options';
	const LEGACY_OPTION_DIRECT        = 'fanbridge_direct_options';

	public static function init() {
		self::migrate_legacy_options();
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_post_fanbridge_disconnect', [ __CLASS__, 'handle_disconnect' ] );
		add_action( 'admin_post_fanbridge_test_connection', [ __CLASS__, 'handle_connection_test' ] );
	}

	private static function migrate_legacy_options() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( is_array( $opts ) && ! empty( $opts ) ) {
			$merged_opts = self::merge_legacy_oauth_fields( $opts );
			if ( $merged_opts !== $opts ) {
				update_option( self::OPTION_NAME, $merged_opts );
			}
			return;
		}

		$broker_opts = get_option( self::LEGACY_OPTION_BROKER, [] );
		$direct_opts = get_option( self::LEGACY_OPTION_DIRECT, [] );

		$opts = [
			'broker_mode' => false,
			'broker_url' => '',
			'site_id' => '',
			'fanvue_oauth_client_id' => '',
			'fanvue_oauth_client_secret' => '',
			'fanvue_oauth_redirect_uri' => Fanvue_OAuth::get_default_redirect_uri(),
			'fanvue_authorization_url' => 'https://auth.fanvue.com/oauth2/auth',
			'fanvue_token_url' => 'https://auth.fanvue.com/oauth2/token',
			'fanvue_api_base_url' => 'https://api.fanvue.com',
			'fanvue_oauth_scopes' => self::DEFAULT_FANVUE_SCOPES,
			'fanvue_api_version' => '2025-06-26',
			'fanvue_creator_id' => '',
			'cron_interval_minutes' => 15,
			'entitlement_cache_ttl_seconds' => 900,
		];

		if ( is_array( $broker_opts ) && ! empty( $broker_opts ) ) {
			$opts['broker_mode'] = true;
			$opts['broker_url'] = $broker_opts['broker_url'] ?? '';
			$opts['site_id'] = $broker_opts['site_id'] ?? '';
			$opts['fanvue_oauth_client_id'] = $broker_opts['fanvue_oauth_client_id'] ?? '';
			$opts['fanvue_oauth_client_secret'] = $broker_opts['fanvue_oauth_client_secret'] ?? '';
			$opts['fanvue_oauth_redirect_uri'] = $broker_opts['fanvue_oauth_redirect_uri'] ?? self::get_broker_default_redirect_uri();
			$opts['fanvue_oauth_scopes'] = $broker_opts['fanvue_oauth_scopes'] ?? self::DEFAULT_FANVUE_SCOPES;
			$opts['fanvue_api_base_url'] = $broker_opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com';
		} elseif ( is_array( $direct_opts ) && ! empty( $direct_opts ) ) {
			$opts['broker_mode'] = false;
			$opts['fanvue_oauth_client_id'] = $direct_opts['fanvue_oauth_client_id'] ?? '';
			$opts['fanvue_oauth_client_secret'] = $direct_opts['fanvue_oauth_client_secret'] ?? '';
			$opts['fanvue_oauth_redirect_uri'] = $direct_opts['fanvue_oauth_redirect_uri'] ?? Fanvue_OAuth::get_default_redirect_uri();
			$opts['fanvue_oauth_scopes'] = $direct_opts['fanvue_oauth_scopes'] ?? Fanvue_OAuth::DEFAULT_SCOPES;
			$opts['fanvue_api_base_url'] = $direct_opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com';
			$opts['fanvue_creator_id'] = $direct_opts['fanvue_creator_id'] ?? '';
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
		$client_id_keys = [ 'fanvue_oauth_client_id', 'fanvue_client_id', 'oauth_client_id', 'client_id' ];
		$secret_keys    = [ 'fanvue_oauth_client_secret', 'fanvue_client_secret', 'oauth_client_secret', 'client_secret' ];

		$needs_client_id = self::is_missing_sensitive_setting( $opts['fanvue_oauth_client_id'] ?? '' );
		$needs_secret    = self::is_missing_sensitive_setting( $opts['fanvue_oauth_client_secret'] ?? '' );

		if ( $needs_client_id ) {
			$fallback_client_id = self::find_first_usable_sensitive_value( $opts, $client_id_keys );
			if ( $fallback_client_id !== null ) {
				$opts['fanvue_oauth_client_id'] = $fallback_client_id;
				$needs_client_id = false;
			}
		}

		if ( $needs_secret ) {
			$fallback_secret = self::find_first_usable_sensitive_value( $opts, $secret_keys );
			if ( $fallback_secret !== null ) {
				$opts['fanvue_oauth_client_secret'] = $fallback_secret;
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
					$opts['fanvue_oauth_client_id'] = $legacy_client_id;
					$needs_client_id = false;
				}
			}

			if ( $needs_secret ) {
				$legacy_secret = self::find_first_usable_sensitive_value( $legacy_opts, $secret_keys );
				if ( $legacy_secret !== null ) {
					$opts['fanvue_oauth_client_secret'] = $legacy_secret;
					$needs_secret = false;
				}
			}

			if ( ! $needs_client_id && ! $needs_secret ) {
				break;
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
		if ( function_exists( 'rest_url' ) && did_action( 'rest_api_init' ) ) {
			return rtrim( rest_url( 'fanbridge/v1/broker-callback' ), '/' ) . '/';
		}
		return trailingslashit( get_site_url() ) . 'wp-json/fanbridge/v1/broker-callback';
	}

	public static function sanitize_options( $input ) {
		$raw_opts = self::get_raw_options();
		$opts     = [];

		$opts['broker_mode'] = ! empty( $input['broker_mode'] );

		$broker_url = isset( $input['broker_url'] ) ? self::sanitize_https_url( wp_unslash( $input['broker_url'] ) ) : '';
		if ( $broker_url === '' && $opts['broker_mode'] ) {
			$broker_url = 'https://auth.ncdlabs.com';
		}
		$opts['broker_url'] = $broker_url;

		$opts['site_id'] = isset( $input['site_id'] ) ? sanitize_text_field( wp_unslash( $input['site_id'] ) ) : '';

		$client_id = isset( $input['fanvue_oauth_client_id'] ) ? sanitize_text_field( wp_unslash( $input['fanvue_oauth_client_id'] ) ) : '';
		$opts['fanvue_oauth_client_id'] = $client_id === '' ? '' : self::encrypt_value( $client_id );

		$client_secret = isset( $input['fanvue_oauth_client_secret'] ) ? (string) wp_unslash( $input['fanvue_oauth_client_secret'] ) : '';
		if ( $client_secret === '********' || $client_secret === '' ) {
			$opts['fanvue_oauth_client_secret'] = isset( $raw_opts['fanvue_oauth_client_secret'] ) ? $raw_opts['fanvue_oauth_client_secret'] : '';
		} else {
			$opts['fanvue_oauth_client_secret'] = self::encrypt_value( $client_secret );
		}

		$opts['fanvue_oauth_redirect_uri'] = isset( $input['fanvue_oauth_redirect_uri'] )
			? self::sanitize_https_url( wp_unslash( $input['fanvue_oauth_redirect_uri'] ) )
			: ( $opts['broker_mode'] ? self::get_broker_default_redirect_uri() : Fanvue_OAuth::get_default_redirect_uri() );

		$opts['fanvue_authorization_url'] = isset( $input['fanvue_authorization_url'] )
			? self::sanitize_https_url( wp_unslash( $input['fanvue_authorization_url'] ) )
			: 'https://auth.fanvue.com/oauth2/auth';

		$opts['fanvue_token_url'] = isset( $input['fanvue_token_url'] )
			? self::sanitize_https_url( wp_unslash( $input['fanvue_token_url'] ) )
			: 'https://auth.fanvue.com/oauth2/token';

		$opts['fanvue_api_base_url'] = isset( $input['fanvue_api_base_url'] )
			? self::sanitize_https_url( wp_unslash( $input['fanvue_api_base_url'] ) )
			: 'https://api.fanvue.com';

		$opts['fanvue_oauth_scopes'] = isset( $input['fanvue_oauth_scopes'] )
			? sanitize_text_field( wp_unslash( $input['fanvue_oauth_scopes'] ) )
			: self::DEFAULT_FANVUE_SCOPES;

		$opts['fanvue_api_version'] = isset( $input['fanvue_api_version'] )
			? preg_replace( '/[^0-9\-]/', '', sanitize_text_field( wp_unslash( $input['fanvue_api_version'] ) ) )
			: '2025-06-26';

		$opts['cron_interval_minutes'] = isset( $input['cron_interval_minutes'] )
			? max( 5, (int) $input['cron_interval_minutes'] )
			: 15;

		$opts['entitlement_cache_ttl_seconds'] = isset( $input['entitlement_cache_ttl_seconds'] )
			? max( 60, (int) $input['entitlement_cache_ttl_seconds'] )
			: 900;

		$opts['fanvue_creator_id'] = isset( $input['fanvue_creator_id'] )
			? sanitize_text_field( wp_unslash( $input['fanvue_creator_id'] ) )
			: '';

		if ( $opts['fanvue_oauth_redirect_uri'] === '' ) {
			$opts['fanvue_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: Fanvue_OAuth::get_default_redirect_uri();
		}
		if ( $opts['fanvue_oauth_scopes'] === '' ) {
			$opts['fanvue_oauth_scopes'] = self::DEFAULT_FANVUE_SCOPES;
		}
		if ( $opts['fanvue_api_base_url'] === '' ) {
			$opts['fanvue_api_base_url'] = 'https://api.fanvue.com';
		}
		if ( $opts['fanvue_api_version'] === '' ) {
			$opts['fanvue_api_version'] = '2025-06-26';
		}
		if ( $opts['fanvue_authorization_url'] === '' ) {
			$opts['fanvue_authorization_url'] = 'https://auth.fanvue.com/oauth2/auth';
		}
		if ( $opts['fanvue_token_url'] === '' ) {
			$opts['fanvue_token_url'] = 'https://auth.fanvue.com/oauth2/token';
		}

		if ( $opts['broker_mode'] ) {
			if ( empty( $opts['broker_url'] ) ) {
				add_settings_error( self::OPTION_NAME, 'fanbridge_broker_url_required', __( 'Broker URL is required when Broker Mode is enabled.', 'fanbridge' ) );
			}
			if ( empty( $opts['site_id'] ) ) {
				add_settings_error( self::OPTION_NAME, 'fanbridge_site_id_required', __( 'Site ID is required when Broker Mode is enabled.', 'fanbridge' ) );
			}
		}

		if ( empty( $opts['fanvue_oauth_client_id'] ) ) {
			add_settings_error( self::OPTION_NAME, 'fanbridge_client_id_required', __( 'Fanvue OAuth Client ID is required.', 'fanbridge' ) );
		}
		if ( empty( $opts['fanvue_oauth_client_secret'] ) ) {
			add_settings_error( self::OPTION_NAME, 'fanbridge_client_secret_required', __( 'Fanvue OAuth Client Secret is required.', 'fanbridge' ) );
		}

		return $opts;
	}

	public static function set_defaults() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}

		if ( ! isset( $opts['broker_mode'] ) ) {
			$opts['broker_mode'] = false;
		}
		if ( ! isset( $opts['broker_url'] ) || $opts['broker_url'] === '' ) {
			$opts['broker_url'] = 'https://auth.ncdlabs.com';
		}
		if ( ! isset( $opts['fanvue_authorization_url'] ) || $opts['fanvue_authorization_url'] === '' ) {
			$opts['fanvue_authorization_url'] = 'https://auth.fanvue.com/oauth2/auth';
		}
		if ( ! isset( $opts['fanvue_token_url'] ) || $opts['fanvue_token_url'] === '' ) {
			$opts['fanvue_token_url'] = 'https://auth.fanvue.com/oauth2/token';
		}
		if ( ! isset( $opts['fanvue_api_base_url'] ) || $opts['fanvue_api_base_url'] === '' ) {
			$opts['fanvue_api_base_url'] = 'https://api.fanvue.com';
		}
		if ( ! isset( $opts['fanvue_oauth_scopes'] ) || $opts['fanvue_oauth_scopes'] === '' ) {
			$opts['fanvue_oauth_scopes'] = self::DEFAULT_FANVUE_SCOPES;
		}
		if ( ! isset( $opts['fanvue_api_version'] ) || $opts['fanvue_api_version'] === '' ) {
			$opts['fanvue_api_version'] = '2025-06-26';
		}
		if ( ! isset( $opts['fanvue_oauth_redirect_uri'] ) || $opts['fanvue_oauth_redirect_uri'] === '' ) {
			$opts['fanvue_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: Fanvue_OAuth::get_default_redirect_uri();
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

		check_admin_referer( 'fanbridge_disconnect' );

		if ( self::is_broker_mode() ) {
			Broker_Client::disconnect();
		} else {
			delete_option( Fanvue_OAuth::OPTION_TOKENS );
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

		check_admin_referer( 'fanbridge_test_connection' );

		$result = Fanvue_Client::test_connection();
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

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard&status=connection_tested' ) );
		exit;
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$css = '
		.fanbridge-wrap { margin-top: 20px; max-width: 800px; }
		.fanbridge-settings-header { margin-bottom: 20px; }
		.fanbridge-settings-header h1 { margin-bottom: 5px; }
		.fanbridge-settings-header p { color: #646970; margin-top: 0; }
		.fanbridge-section { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
		.fanbridge-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #dcdcde; font-size: 16px; }
		.fanbridge-section h3 { margin-top: 0; font-size: 14px; }
		.fanbridge-mode-toggle { display: flex; align-items: flex-start; gap: 10px; padding: 15px; background: #f6f7f7; border-radius: 4px; margin-bottom: 15px; }
		.fanbridge-mode-toggle input[type="checkbox"] { margin-top: 3px; }
		.fanbridge-mode-toggle label { font-weight: 600; cursor: pointer; }
		.fanbridge-mode-toggle .description { color: #646970; font-size: 13px; margin-top: 2px; }
		.fanbridge-mode-notice { padding: 12px 15px; border-radius: 4px; margin: 15px 0; }
		.fanbridge-mode-notice.direct { background: #f0f6fc; border-left: 4px solid #2271b1; }
		.fanbridge-mode-notice.broker { background: #f0f6ce; border-left: 4px solid #00a32a; }
		.fanbridge-mode-notice p { margin: 0; font-size: 13px; }
		.fanbridge-broker-field { transition: opacity 0.2s ease; }
		.fanbridge-broker-field:disabled { opacity: 0.5; cursor: not-allowed; }
		.fanbridge-callback-url { background: #f6f7f7; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-top: 5px; }
		.fanbridge-callback-url code { font-size: 12px; }
		.form-table th { width: 200px; }
		.form-table input[type="text"],
		.form-table input[type="url"],
		.form-table input[type="password"],
		.form-table textarea { width: 100%; max-width: 400px; }
		.form-table .description { color: #646970; font-size: 13px; }
		.fanbridge-status-row { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
		.fanbridge-status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
		.fanbridge-status-green { background: #edfaef; color: #1a7f37; }
		.fanbridge-status-yellow { background: #fff7e5; color: #8a6d1d; }
		.fanbridge-status-red { background: #fbeaea; color: #b52727; }
		.fanbridge-tab-nav { margin: 0 0 16px; }
		.fanbridge-tab-panel { display: none; }
		.fanbridge-tab-panel.is-active { display: block; }
		.fanbridge-sync-row { display: flex; gap: 15px; align-items: flex-end; margin-top: 15px; }
		.fanbridge-sync-row input[type="number"] { width: 80px; }
		.fanbridge-check-list { margin: 10px 0 0; }
		.fanbridge-check-list li { margin-bottom: 8px; }
		.fanbridge-check-result-pass { color: #1a7f37; font-weight: 600; }
		.fanbridge-check-result-fail { color: #b52727; font-weight: 600; }
		.fanbridge-muted { color: #646970; }
		.fanbridge-meta-list { margin: 0; }
		.fanbridge-meta-list p { margin: 0 0 10px; }
		.fanbridge-connection-overview { display: flex; justify-content: space-between; gap: 16px; padding: 14px; border: 1px solid #dcdcde; border-radius: 6px; background: #f6f7f7; margin-bottom: 16px; }
		.fanbridge-connection-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
		.fanbridge-connection-actions form { margin: 0; }
		.fanbridge-connection-actions .submit { margin: 0; padding: 0; }
		.fanbridge-kv { display: grid; grid-template-columns: minmax(120px, 180px) 1fr; gap: 8px 12px; margin: 0; }
		.fanbridge-kv dt { font-weight: 600; color: #1d2327; }
		.fanbridge-kv dd { margin: 0; }
		.fanbridge-test-details { margin-top: 12px; }
		.fanbridge-test-details summary { cursor: pointer; color: #3858e9; }
		.fanbridge-inline-status { margin-left: 8px; }
		.fanbridge-connection-card { border-width: 1px; }
		.fanbridge-connection-card.is-red { background: #fff1f1; border-color: #f3c7c7; }
		.fanbridge-connection-card.is-yellow { background: #fff9e8; border-color: #f1deaa; }
		.fanbridge-connection-card.is-green { background: #eefbf1; border-color: #b8e2c1; }
		.fanbridge-connection-alert { margin: 0 0 12px; color: #7a1f1f; font-weight: 600; }
		@media (max-width: 782px) {
			.fanbridge-connection-overview { flex-direction: column; }
		}
		/* Danger Zone Styles */
		.fanbridge-danger-zone {
			background: #fff8f8;
			border: 1px solid #f9d6d6;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.fanbridge-danger-zone h2 {
			margin-top: 0;
			color: #b52727;
			font-size: 16px;
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
		}
		.fanbridge-danger-zone h2::after {
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
		.fanbridge-danger-zone.collapsed h2::after {
			transform: rotate(-90deg);
		}
		.fanbridge-danger-zone-content {
			max-height: 0;
			overflow: hidden;
			transition: max-height 0.3s ease;
		}
		.fanbridge-danger-zone.expanded .fanbridge-danger-zone-content {
			max-height: 200px;
		}
		';

		wp_register_style( 'fanbridge-admin', false, [], FANBRIDGE_VERSION );
		wp_enqueue_style( 'fanbridge-admin' );
		wp_add_inline_style( 'fanbridge-admin', $css );

		$js = '
		(function() {
			document.addEventListener("DOMContentLoaded", function() {
				var checkbox = document.getElementById("fanvue_broker_mode");
				var brokerFields = document.querySelectorAll(".fanvue-broker-field");
				var directNotice = document.getElementById("fanvue-mode-notice-direct");
				var brokerNotice = document.getElementById("fanvue-mode-notice-broker");

				function toggleBrokerFields() {
					var isBrokerMode = checkbox && checkbox.checked;
					brokerFields.forEach(function(field) {
						field.disabled = !isBrokerMode;
					});
					if (directNotice) {
				directNotice.style.display = isBrokerMode ? "none" : "block";
				}
				if (brokerNotice) {
					brokerNotice.style.display = isBrokerMode ? "block" : "none";
				}
			}

			function initTabs() {
				var tabLinks = document.querySelectorAll(".fanbridge-tab-link");
				var tabPanels = document.querySelectorAll(".fanbridge-tab-panel");
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

					if (updateUrl) {
						var url = new URL(window.location.href);
						url.searchParams.set("tab", tabName);
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

			if (checkbox) {
				checkbox.addEventListener("change", toggleBrokerFields);
				toggleBrokerFields();
			}

			initTabs();

			// Danger Zone collapsible functionality
			var dangerZoneTitle = document.querySelector(".fanbridge-danger-zone-title");
			var dangerZoneContent = document.querySelector(".fanbridge-danger-zone-content");
			var dangerZone = document.querySelector(".fanbridge-danger-zone");
			
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
			var notices = {
				"disconnected": "' . esc_js( __( 'Disconnected from Fanvue.', 'fanbridge' ) ) . '",
				"connected": "' . esc_js( __( 'Connected to Fanvue successfully.', 'fanbridge' ) ) . '",
				"saved": "' . esc_js( __( 'Settings saved.', 'fanbridge' ) ) . '",
				"connection_tested": "' . esc_js( __( 'Connection test completed.', 'fanbridge' ) ) . '"
			};

				if (status && notices[status]) {
					var notice = document.createElement("div");
					notice.className = "notice notice-success is-dismissible";
					notice.innerHTML = "<p>" + notices[status] + "</p>";
					var header = document.querySelector(".fanbridge-settings-header");
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
			});
		})();
		';

		wp_register_script( 'fanbridge-admin', false, [], FANBRIDGE_VERSION, true );
		wp_enqueue_script( 'fanbridge-admin' );
		wp_add_inline_script( 'fanbridge-admin', $js );
	}

	public static function get_status_summary() {
		$opts = self::get_options();
		$broker_mode = ! empty( $opts['broker_mode'] );

		$overall = 'green';
		$overall_message = __( 'FanBridge is ready.', 'fanbridge' );

		if ( $broker_mode ) {
			$configured = ! empty( $opts['broker_url'] )
				&& ! empty( $opts['site_id'] )
				&& ! empty( $opts['fanvue_oauth_client_id'] )
				&& ! empty( $opts['fanvue_oauth_client_secret'] );
			$connected = self::is_connected();

			if ( ! $configured ) {
				$overall = 'yellow';
				$overall_message = __( 'Broker mode: Configure broker and Fanvue OAuth app settings.', 'fanbridge' );
			} elseif ( ! $connected ) {
				$overall = 'yellow';
				$overall_message = __( 'Broker mode: Connect to Fanvue to complete setup.', 'fanbridge' );
			} else {
				$overall_message = __( 'Broker mode: Connected to broker.', 'fanbridge' );
			}
		} else {
			$client_id = ! empty( $opts['fanvue_oauth_client_id'] );
			$secret_set = ! empty( $opts['fanvue_oauth_client_secret'] ) && $opts['fanvue_oauth_client_secret'] !== '********';
			$connected = self::is_connected();

			if ( ! $client_id || ! $secret_set ) {
				$overall = 'yellow';
				$overall_message = __( 'Direct mode: Configure OAuth credentials.', 'fanbridge' );
			} elseif ( ! $connected ) {
				$overall = 'yellow';
				$overall_message = __( 'Direct mode: Connect to Fanvue to complete setup.', 'fanbridge' );
			} else {
				$overall_message = __( 'Direct mode: Connected to Fanvue.', 'fanbridge' );
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
		return Fanvue_OAuth::is_connected();
	}

	public static function add_menu() {
		add_options_page(
			__( 'FanBridge Settings', 'fanbridge' ),
			__( 'FanBridge', 'fanbridge' ),
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
		$secret_mask = ! empty( $opts['fanvue_oauth_client_secret'] ) ? '********' : '';
		$broker_mode = ! empty( $opts['broker_mode'] );
		$connection_test = get_option( self::OPTION_CONNECTION_TEST, [] );
		$next_sync_time = wp_next_scheduled( Cron::HOOK );
		$allowed_tabs = [ 'dashboard', 'users', 'settings' ];
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$active_tab = in_array( $requested_tab, $allowed_tabs, true ) ? $requested_tab : 'dashboard';
		$user_rows = [];
		$user_totals = [
			'total' => 0,
			'active' => 0,
			'inactive' => 0,
		];

		global $wpdb;
		$table_name = Entitlements::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists === $table_name ) {
			Entitlements::maybe_add_product_column();
			$user_totals['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$user_totals['active'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_ACTIVE )
			);
			$user_totals['inactive'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_INACTIVE )
			);
			$user_rows = $wpdb->get_results( "SELECT product, display_name, email, status, tier, expires_at, updated_at FROM {$table_name} ORDER BY updated_at DESC LIMIT 50", ARRAY_A );
		}
		?>
		<div class="wrap fanbridge-wrap">
			<div class="fanbridge-settings-header">
				<h1><?php esc_html_e( 'FanBridge Settings', 'fanbridge' ); ?></h1>
				<p><?php esc_html_e( 'Configure FanBridge OAuth integration with Fanvue.', 'fanbridge' ); ?></p>
			</div>

			<div class="fanbridge-status-row">
				<span class="fanbridge-status-badge <?php echo 'green' === $status['overall'] ? 'fanbridge-status-green' : ( 'yellow' === $status['overall'] ? 'fanbridge-status-yellow' : 'fanbridge-status-red' ); ?>">
					<?php
					if ( 'green' === $status['overall'] ) {
						esc_html_e( 'Ready', 'fanbridge' );
					} elseif ( 'yellow' === $status['overall'] ) {
						esc_html_e( 'Setup Required', 'fanbridge' );
					} else {
						esc_html_e( 'Error', 'fanbridge' );
					}
					?>
				</span>
				<span><?php echo esc_html( $status['overall_message'] ); ?></span>
			</div>

			<nav class="nav-tab-wrapper fanbridge-tab-nav" aria-label="<?php esc_attr_e( 'FanBridge sections', 'fanbridge' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=dashboard' ) ); ?>" class="nav-tab fanbridge-tab-link <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="dashboard"><?php esc_html_e( 'Dashboard', 'fanbridge' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=users' ) ); ?>" class="nav-tab fanbridge-tab-link <?php echo 'users' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="users"><?php esc_html_e( 'Users', 'fanbridge' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>" class="nav-tab fanbridge-tab-link <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="settings"><?php esc_html_e( 'Settings', 'fanbridge' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<?php settings_errors( self::OPTION_NAME ); ?>

				<div class="fanbridge-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'Mode', 'fanbridge' ); ?></h2>

					<div class="fanbridge-mode-toggle">
						<input type="checkbox" id="fanvue_broker_mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[broker_mode]" value="1" <?php checked( $broker_mode ); ?> />
						<div>
							<label for="fanvue_broker_mode"><?php esc_html_e( 'Enable Broker Mode', 'fanbridge' ); ?></label>
							<p class="description"><?php esc_html_e( 'Use an external OAuth broker service instead of direct Fanvue authentication.', 'fanbridge' ); ?></p>
						</div>
					</div>

					<div id="fanvue-mode-notice-direct" class="fanbridge-mode-notice direct" style="<?php echo $broker_mode ? 'display:none;' : ''; ?>">
						<p><strong><?php esc_html_e( 'Direct Mode Active:', 'fanbridge' ); ?></strong> <?php esc_html_e( 'This plugin will authenticate directly with Fanvue using the configured OAuth credentials.', 'fanbridge' ); ?></p>
					</div>

					<div id="fanvue-mode-notice-broker" class="fanbridge-mode-notice broker" style="<?php echo $broker_mode ? '' : 'display:none;'; ?>">
						<p><strong><?php esc_html_e( 'Broker Mode Active:', 'fanbridge' ); ?></strong> <?php esc_html_e( 'Authentication requests will be routed through the configured OAuth broker service.', 'fanbridge' ); ?></p>
					</div>
				</div>
				</div>

				<div class="fanbridge-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'OAuth Credentials', 'fanbridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Client ID', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_oauth_client_id]" value="<?php echo esc_attr( $opts['fanvue_oauth_client_id'] ?? '' ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Client ID from your Fanvue OAuth app.', 'fanbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Client Secret', 'fanbridge' ); ?></th>
							<td>
								<input type="password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_oauth_client_secret]" value="<?php echo esc_attr( $secret_mask ); ?>" class="regular-text" autocomplete="new-password" />
								<p class="description"><?php esc_html_e( 'Stored encrypted. Leave as ******** to keep the existing value.', 'fanbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Redirect URI', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_oauth_redirect_uri]" value="<?php echo esc_attr( $opts['fanvue_oauth_redirect_uri'] ?? Fanvue_OAuth::get_default_redirect_uri() ); ?>" class="regular-text code" />
								<p class="description"><?php esc_html_e( 'Must exactly match the URI configured in your Fanvue OAuth app.', 'fanbridge' ); ?></p>
								<div class="fanbridge-callback-url">
									<strong><?php esc_html_e( 'Suggested Callback URL:', 'fanbridge' ); ?></strong><br />
									<code><?php echo esc_html( $broker_mode ? self::get_broker_default_redirect_uri() : Fanvue_OAuth::get_default_redirect_uri() ); ?></code>
								</div>
							</td>
						</tr>
					</table>
				</div>

				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'OAuth Endpoints', 'fanbridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Authorization URL', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_authorization_url]" value="<?php echo esc_attr( $opts['fanvue_authorization_url'] ?? 'https://auth.fanvue.com/oauth2/auth' ); ?>" class="regular-text code" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Token URL', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_token_url]" value="<?php echo esc_attr( $opts['fanvue_token_url'] ?? 'https://auth.fanvue.com/oauth2/token' ); ?>" class="regular-text code" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'API Base URL', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_api_base_url]" value="<?php echo esc_attr( $opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com' ); ?>" class="regular-text code" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Scopes', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_oauth_scopes]" value="<?php echo esc_attr( $opts['fanvue_oauth_scopes'] ?? self::DEFAULT_FANVUE_SCOPES ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Space-separated OAuth scopes.', 'fanbridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				</div>

				<div class="fanbridge-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'Broker Settings', 'fanbridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Broker URL', 'fanbridge' ); ?></th>
							<td>
								<input type="text" class="fanvue-broker-field regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[broker_url]" value="<?php echo esc_attr( $opts['broker_url'] ?? 'https://auth.ncdlabs.com' ); ?>" <?php echo $broker_mode ? '' : 'disabled'; ?> />
								<p class="description"><?php esc_html_e( 'The URL of the OAuth broker service.', 'fanbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Site ID', 'fanbridge' ); ?></th>
							<td>
								<input type="text" class="fanvue-broker-field regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[site_id]" value="<?php echo esc_attr( $opts['site_id'] ?? '' ); ?>" <?php echo $broker_mode ? '' : 'disabled'; ?> />
								<p class="description"><?php esc_html_e( 'Your unique site identifier from the broker admin.', 'fanbridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php if ( $broker_mode ) : ?>
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'API Settings', 'fanbridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'API Version', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_api_version]" value="<?php echo esc_attr( $opts['fanvue_api_version'] ?? '2025-06-26' ); ?>" class="regular-text code" />
								<p class="description"><?php esc_html_e( 'Date-based API version header.', 'fanbridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				<?php endif; ?>
				</div>

				<div class="fanbridge-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
				<?php if ( ! $broker_mode ) : ?>
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'Sync Settings', 'fanbridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Creator ID', 'fanbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fanvue_creator_id]" value="<?php echo esc_attr( $opts['fanvue_creator_id'] ?? '' ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Optional: Filter subscribers to a specific creator.', 'fanbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sync Interval', 'fanbridge' ); ?></th>
							<td>
								<div class="fanbridge-sync-row">
									<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cron_interval_minutes]" value="<?php echo esc_attr( $opts['cron_interval_minutes'] ?? 15 ); ?>" min="5" max="1440" />
									<span class="description"><?php esc_html_e( 'minutes', 'fanbridge' ); ?></span>
								</div>
								<p class="description"><?php esc_html_e( 'How often to sync subscribers and followers.', 'fanbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cache TTL', 'fanbridge' ); ?></th>
							<td>
								<div class="fanbridge-sync-row">
									<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[entitlement_cache_ttl_seconds]" value="<?php echo esc_attr( $opts['entitlement_cache_ttl_seconds'] ?? 900 ); ?>" min="60" />
									<span class="description"><?php esc_html_e( 'seconds', 'fanbridge' ); ?></span>
								</div>
								<p class="description"><?php esc_html_e( 'How long entitlements are valid before requiring a new sync.', 'fanbridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				<?php endif; ?>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="fanbridge-tab-panel <?php echo 'users' === $active_tab ? 'is-active' : ''; ?>" data-tab="users">
				<div class="fanbridge-section">
					<h2><?php esc_html_e( 'Users', 'fanbridge' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Total:', 'fanbridge' ); ?></strong> <?php echo esc_html( (string) $user_totals['total'] ); ?>,
						<strong><?php esc_html_e( 'Active:', 'fanbridge' ); ?></strong> <?php echo esc_html( (string) $user_totals['active'] ); ?>,
						<strong><?php esc_html_e( 'Inactive:', 'fanbridge' ); ?></strong> <?php echo esc_html( (string) $user_totals['inactive'] ); ?>
					</p>

					<?php if ( ! empty( $user_rows ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Product', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Name', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Email', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Status', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Tier', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Expires', 'fanbridge' ); ?></th>
									<th><?php esc_html_e( 'Updated', 'fanbridge' ); ?></th>
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
						<p><?php esc_html_e( 'No synced users found yet. Run a sync and refresh this tab.', 'fanbridge' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="fanbridge-tab-panel <?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>" data-tab="dashboard">
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

				$connection_card_class = 'fanbridge-connection-card is-' . $connection_state;
				?>
				<div class="fanbridge-section <?php echo esc_attr( $connection_card_class ); ?>">
					<h2><?php esc_html_e( 'Connection Status', 'fanbridge' ); ?></h2>
					<?php
					$connect_url = '';
					if ( ! empty( $opts['fanvue_oauth_client_id'] ) && ! empty( $opts['fanvue_oauth_client_secret'] ) && $opts['fanvue_oauth_client_secret'] !== '********' ) {
						$connect_url = $broker_mode ? Broker_Client::get_connect_url() : Fanvue_OAuth::get_authorization_url();
					}
					$last_sync = $status['last_sync'];
					$last_sync_time = is_array( $last_sync ) && isset( $last_sync['time'] ) ? (int) $last_sync['time'] : ( $last_sync ? (int) $last_sync : 0 );
					$last_sync_success = is_array( $last_sync ) && array_key_exists( 'success', $last_sync ) ? ! empty( $last_sync['success'] ) : true;
					?>

					<?php if ( $connection_state === 'red' && $connection_failure_message !== '' ) : ?>
						<p class="fanbridge-connection-alert">
							<?php echo esc_html( $connection_failure_message ); ?>
						</p>
					<?php endif; ?>

					<div class="fanbridge-connection-overview">
						<div>
							<p>
								<span class="fanbridge-status-badge <?php echo 'green' === $connection_state ? 'fanbridge-status-green' : ( 'red' === $connection_state ? 'fanbridge-status-red' : 'fanbridge-status-yellow' ); ?>">
									<?php echo 'green' === $connection_state ? esc_html__( 'Healthy', 'fanbridge' ) : ( 'red' === $connection_state ? esc_html__( 'Attention', 'fanbridge' ) : esc_html__( 'Pending', 'fanbridge' ) ); ?>
								</span>
							</p>
							<p class="fanbridge-muted">
								<?php
								if ( 'green' === $connection_state ) {
									esc_html_e( 'All checks passed and FanBridge is connected.', 'fanbridge' );
								} elseif ( 'red' === $connection_state ) {
									esc_html_e( 'Connection needs attention. Review the failure and retry.', 'fanbridge' );
								} else {
									esc_html_e( 'Checks look good. Connect FanBridge to finish setup.', 'fanbridge' );
								}
								?>
							</p>
						</div>
						<div class="fanbridge-connection-actions">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'fanbridge_test_connection' ); ?>
								<input type="hidden" name="action" value="fanbridge_test_connection" />
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Run Test', 'fanbridge' ); ?>" />
								</p>
							</form>
							<?php if ( $status['connected'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'fanbridge_disconnect' ); ?>
									<input type="hidden" name="action" value="fanbridge_disconnect" />
									<p class="submit">
										<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Disconnect', 'fanbridge' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect?', 'fanbridge' ); ?>');" />
									</p>
								</form>
							<?php elseif ( $connect_url ) : ?>
								<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Connect', 'fanbridge' ); ?></a>
							<?php endif; ?>
						</div>
					</div>

					<dl class="fanbridge-kv">
						<dt><?php esc_html_e( 'Mode', 'fanbridge' ); ?></dt>
						<dd><?php echo $broker_mode ? esc_html__( 'Broker', 'fanbridge' ) : esc_html__( 'Direct', 'fanbridge' ); ?></dd>
						<dt><?php esc_html_e( 'Test', 'fanbridge' ); ?></dt>
						<dd>
							<?php if ( ! empty( $connection_test ) && is_array( $connection_test ) ) : ?>
								<span class="fanbridge-status-badge <?php echo ! empty( $connection_test['success'] ) ? 'fanbridge-status-green' : 'fanbridge-status-red'; ?>">
									<?php echo ! empty( $connection_test['success'] ) ? esc_html__( 'Pass', 'fanbridge' ) : esc_html__( 'Fail', 'fanbridge' ); ?>
								</span>
								<?php if ( ! empty( $connection_test['time'] ) ) : ?>
									<span class="fanbridge-inline-status fanbridge-muted">
										<?php printf( esc_html__( '%s ago', 'fanbridge' ), esc_html( human_time_diff( (int) $connection_test['time'], time() ) ) ); ?>
									</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="fanbridge-muted"><?php esc_html_e( 'Not run yet', 'fanbridge' ); ?></span>
							<?php endif; ?>
						</dd>
						<dt><?php esc_html_e( 'Last Sync', 'fanbridge' ); ?></dt>
						<dd>
							<?php if ( $last_sync_time > 0 ) : ?>
								<?php printf( esc_html__( '%s ago', 'fanbridge' ), esc_html( human_time_diff( $last_sync_time, time() ) ) ); ?>
								<span class="fanbridge-inline-status <?php echo $last_sync_success ? 'fanbridge-check-result-pass' : 'fanbridge-check-result-fail'; ?>">
									<?php echo $last_sync_success ? esc_html__( 'Success', 'fanbridge' ) : esc_html__( 'Failed', 'fanbridge' ); ?>
								</span>
							<?php else : ?>
								<span class="fanbridge-muted"><?php esc_html_e( 'No sync has run yet', 'fanbridge' ); ?></span>
							<?php endif; ?>
						</dd>
						<dt><?php esc_html_e( 'Next Sync', 'fanbridge' ); ?></dt>
						<dd>
							<?php if ( $next_sync_time ) : ?>
								<?php printf( esc_html__( '%s from now', 'fanbridge' ), esc_html( human_time_diff( time(), (int) $next_sync_time ) ) ); ?>
							<?php else : ?>
								<span class="fanbridge-check-result-fail"><?php esc_html_e( 'Not scheduled', 'fanbridge' ); ?></span>
							<?php endif; ?>
						</dd>
					</dl>

					<?php if ( $status['last_error'] ) : ?>
						<p class="fanbridge-check-result-fail">
							<strong><?php esc_html_e( 'Last Error:', 'fanbridge' ); ?></strong>
							<?php echo esc_html( $status['last_error'] ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $connection_test['checks'] ) && is_array( $connection_test['checks'] ) ) : ?>
						<details class="fanbridge-test-details">
							<summary><?php esc_html_e( 'View test details', 'fanbridge' ); ?></summary>
							<ul class="fanbridge-check-list">
								<?php foreach ( $connection_test['checks'] as $check ) : ?>
									<?php
									$check_label = isset( $check['label'] ) ? (string) $check['label'] : '';
									$check_message = isset( $check['message'] ) ? (string) $check['message'] : '';
									$check_pass = ! empty( $check['pass'] );
									?>
									<li>
										<strong><?php echo esc_html( $check_label ); ?>:</strong>
										<span class="<?php echo $check_pass ? 'fanbridge-check-result-pass' : 'fanbridge-check-result-fail'; ?>">
											<?php echo $check_pass ? esc_html__( 'OK', 'fanbridge' ) : esc_html__( 'Issue', 'fanbridge' ); ?>
										</span>
										<?php if ( $check_message !== '' ) : ?>
											&mdash; <?php echo esc_html( $check_message ); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
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
}
