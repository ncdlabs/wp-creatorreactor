<?php
/**
 * Fanvue OAuth 2.0: PKCE, token exchange, refresh, and secure storage.
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace FanBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanvue_OAuth {

	const OPTION_TOKENS          = 'fanbridge_oauth_tokens';
	const TRANSIENT_PKCE_PREFIX  = 'fanbridge_oauth_pkce_';
	const PKCE_TTL               = 600;
	const AUTH_URL               = 'https://auth.fanvue.com/oauth2/auth';
	const TOKEN_URL              = 'https://auth.fanvue.com/oauth2/token';
	const REST_NAMESPACE         = 'fanbridge/v1';
	const REST_ROUTE_CALLBACK   = '/oauth-callback';
	const REST_ROUTE_START      = '/oauth-start';

	const DEFAULT_SCOPES = 'openid offline_access offline read:self read:fan';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_oauth_callback_without_nonce' ], 99 );
	}

	public static function allow_oauth_callback_without_nonce( $result ) {
		$is_nonce_error = is_wp_error( $result ) && $result->get_error_code() === 'rest_cookie_invalid_nonce';
		if ( $is_nonce_error && self::is_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_oauth_rest_request() {
		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server && method_exists( $server, 'get_request' ) ) {
				$request = $server->get_request();
				if ( $request instanceof \WP_REST_Request ) {
					$route = $request->get_route();
					if ( is_string( $route ) ) {
						$route = '/' . ltrim( $route, '/' );
						$route_rtrim = rtrim( $route, '/' );
						if ( $route === '/' . self::REST_NAMESPACE . self::REST_ROUTE_START
							|| $route_rtrim === '/' . self::REST_NAMESPACE . self::REST_ROUTE_CALLBACK
						) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_START,
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'oauth_start' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_CALLBACK,
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'oauth_callback' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'code'              => [ 'required' => false, 'type' => 'string' ],
					'state'             => [ 'required' => false, 'type' => 'string' ],
					'error'             => [ 'required' => false, 'type' => 'string' ],
					'error_description' => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);
	}

	public static function generate_code_verifier() {
		$bytes = random_bytes( 32 );
		return self::base64url_encode( $bytes );
	}

	public static function generate_code_challenge( $code_verifier ) {
		$hash = hash( 'sha256', $code_verifier, true );
		return self::base64url_encode( $hash );
	}

	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	public static function get_default_redirect_uri() {
		$site_url = get_site_url();
		return trailingslashit( $site_url . '/wp-json/' . self::REST_NAMESPACE . self::REST_ROUTE_CALLBACK );
	}

	public static function get_authorization_url( $redirect_uri_override = null ) {
		$opts        = Admin_Settings::get_options();
		$client_id   = $opts['fanvue_oauth_client_id'] ?? '';
		
		$default_redirect = self::get_default_redirect_uri();
		$redirect_uri = $redirect_uri_override !== null
			? $redirect_uri_override
			: ( ! empty( $opts['fanvue_oauth_redirect_uri'] ) ? $opts['fanvue_oauth_redirect_uri'] : $default_redirect );
		
		error_log( 'FanBridge AUTH URL - saved redirect_uri: ' . ( ! empty( $opts['fanvue_oauth_redirect_uri'] ) ? $opts['fanvue_oauth_redirect_uri'] : '(empty)' ) );
		error_log( 'FanBridge AUTH URL - default redirect_uri: ' . $default_redirect );
		error_log( 'FanBridge AUTH URL - redirect_uri used: ' . $redirect_uri );
		
		$scopes = $opts['fanvue_oauth_scopes'] ?? self::DEFAULT_SCOPES;

		if ( $client_id === '' ) {
			return null;
		}

		$code_verifier  = self::generate_code_verifier();
		$code_challenge = self::generate_code_challenge( $code_verifier );
		$state          = wp_generate_password( 32, false );

		set_transient( self::TRANSIENT_PKCE_PREFIX . $state, $code_verifier, self::PKCE_TTL );

		$params = [
			'client_id'             => $client_id,
			'redirect_uri'          => $redirect_uri,
			'response_type'         => 'code',
			'scope'                => $scopes,
			'state'                => $state,
			'code_challenge'       => $code_challenge,
			'code_challenge_method' => 'S256',
		];
		
		$auth_url = self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		error_log( 'FanBridge AUTH URL - full URL: ' . $auth_url );
		return $auth_url;
	}

	private static function settings_redirect_url( array $query_args = [] ) {
		$url = admin_url( 'options-general.php?page=' . Admin_Settings::PAGE_SLUG );
		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}
		return $url . '#direct';
	}

	public static function oauth_start( $request ) {
		try {
			$url = self::get_authorization_url();
			if ( ! $url ) {
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'fanbridge_oauth' => 'error',
							'message'        => __( 'OAuth not configured. Set Client ID and Redirect URI in settings.', 'fanbridge' ),
						]
					)
				);
				exit;
			}
			wp_redirect( $url );
			exit;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge oauth_start error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			Admin_Settings::set_last_error( 'OAuth start failed: ' . $e->getMessage() );
			wp_safe_redirect( self::settings_redirect_url( [ 'fanbridge_oauth' => 'error', 'message' => 'OAuth start failed' ] ) );
			exit;
		}
	}

	public static function oauth_callback( $request ) {
		try {
			$code  = $request->get_param( 'code' );
			$state = $request->get_param( 'state' );
			$error = $request->get_param( 'error' );

			if ( $error ) {
				$msg = $request->get_param( 'error_description' ) ?: $error;
				Admin_Settings::set_last_error( 'OAuth: ' . $msg );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'fanbridge_oauth' => 'error',
							'message'          => 'OAuth: ' . $msg,
						]
					)
				);
				exit;
			}

			if ( ! $code || ! $state ) {
				wp_safe_redirect( self::settings_redirect_url() );
				exit;
			}

			$code_verifier = get_transient( self::TRANSIENT_PKCE_PREFIX . $state );
			delete_transient( self::TRANSIENT_PKCE_PREFIX . $state );
			error_log( 'FanBridge PKCE check - state: ' . $state . ', code_verifier found: ' . ( $code_verifier ? 'yes' : 'no' ) );
			if ( ! $code_verifier || ! is_string( $code_verifier ) ) {
				$msg = __( 'OAuth: Invalid or expired state. Please try connecting again.', 'fanbridge' );
				Admin_Settings::set_last_error( $msg );
				error_log( 'FanBridge PKCE fail - state: ' . $state . ', found: ' . var_export( $code_verifier, true ) );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'fanbridge_oauth' => 'error',
							'message'          => $msg,
						]
					)
				);
				exit;
			}

			$opts         = Admin_Settings::get_options();
			$default_redirect = self::get_default_redirect_uri();
			$redirect_uri = ! empty( $opts['fanvue_oauth_redirect_uri'] ) ? $opts['fanvue_oauth_redirect_uri'] : $default_redirect;

			error_log( 'FanBridge CALLBACK - saved redirect_uri: ' . ( ! empty( $opts['fanvue_oauth_redirect_uri'] ) ? $opts['fanvue_oauth_redirect_uri'] : '(empty)' ) );
			error_log( 'FanBridge CALLBACK - default redirect_uri: ' . $default_redirect );
			error_log( 'FanBridge CALLBACK - redirect_uri used: ' . $redirect_uri );

			$tokens = self::exchange_code_for_tokens( $code, $code_verifier, $redirect_uri, $opts );
			if ( is_wp_error( $tokens ) ) {
				$msg = 'OAuth: ' . $tokens->get_error_message();
				Admin_Settings::set_last_error( $msg );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'fanbridge_oauth' => 'error',
							'message'          => $msg,
						]
					)
				);
				exit;
			}

			self::store_tokens( $tokens );
			Admin_Settings::set_last_error( '' );
			wp_safe_redirect(
				self::settings_redirect_url(
					[
						'fanbridge_oauth' => 'success',
					]
				)
			);
			exit;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge oauth_callback error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			Admin_Settings::set_last_error( 'OAuth callback failed: ' . $e->getMessage() );
			wp_safe_redirect( self::settings_redirect_url( [ 'fanbridge_oauth' => 'error', 'message' => 'OAuth callback failed' ] ) );
			exit;
		}
	}

	public static function exchange_code_for_tokens( $code, $code_verifier, $redirect_uri, $opts ) {
		$client_id     = isset( $opts['fanvue_oauth_client_id'] ) ? trim( (string) $opts['fanvue_oauth_client_id'] ) : '';
		$client_secret = isset( $opts['fanvue_oauth_client_secret'] ) ? (string) $opts['fanvue_oauth_client_secret'] : '';
		
		if ( $client_id === '' || $client_secret === '' ) {
			$msg = $client_secret === ''
				? __( 'OAuth Client Secret is missing or not saved. Re-enter the Client Secret from your Fanvue app, click Save settings, then try Connect again.', 'fanbridge' )
				: __( 'OAuth client ID or secret not set.', 'fanbridge' );
			return new \WP_Error( 'config', $msg );
		}

		$body        = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
			'code_verifier' => $code_verifier,
		];
		$auth_header = 'Basic ' . base64_encode( $client_id . ':' . $client_secret );
		error_log( 'FanBridge TOKEN - redirect_uri in body: ' . $redirect_uri );
		$response    = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => $auth_header,
				],
				'body'    => $body,
			]
		);

		$code_http = wp_remote_retrieve_response_code( $response );
		$body_res  = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body_res, true );
		error_log( 'FanBridge token exchange - HTTP: ' . $code_http . ', body: ' . $body_res );
		if ( $code_http !== 200 ) {
			$msg = isset( $data['error_description'] ) ? $data['error_description'] : ( isset( $data['error'] ) ? $data['error'] : 'HTTP ' . $code_http );
			return new \WP_Error( 'token_exchange', $msg );
		}
		if ( ! isset( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			return new \WP_Error( 'token_exchange', __( 'Invalid token response.', 'fanbridge' ) );
		}

		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		return [
			'access_token'  => $data['access_token'],
			'refresh_token' => isset( $data['refresh_token'] ) ? $data['refresh_token'] : '',
			'expires_at'    => time() + $expires_in,
		];
	}

	public static function refresh_tokens_if_needed() {
		$tokens = self::get_tokens();
		if ( empty( $tokens ) ) {
			return false;
		}
		$expires_at = isset( $tokens['expires_at'] ) ? (int) $tokens['expires_at'] : 0;
		if ( time() < $expires_at - 60 ) {
			return true;
		}
		$refresh_token = isset( $tokens['refresh_token'] ) ? $tokens['refresh_token'] : '';
		if ( $refresh_token === '' ) {
			return false;
		}
		$opts          = Admin_Settings::get_options();
		$client_id     = $opts['fanvue_oauth_client_id'] ?? '';
		$client_secret = $opts['fanvue_oauth_client_secret'] ?? '';
		if ( $client_id === '' || $client_secret === '' ) {
			return false;
		}

		$body        = [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
		];
		$auth_header = 'Basic ' . base64_encode( $client_id . ':' . $client_secret );
		$response    = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => $auth_header,
				],
				'body'    => $body,
			]
		);

		$code_http = wp_remote_retrieve_response_code( $response );
		$body_res  = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body_res, true );
		if ( $code_http !== 200 ) {
			Admin_Settings::set_last_error( __( 'OAuth refresh failed.', 'fanbridge' ) );
			return false;
		}
		if ( ! isset( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			return false;
		}

		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		$new_tokens = [
			'access_token'  => $data['access_token'],
			'refresh_token' => isset( $data['refresh_token'] ) ? $data['refresh_token'] : $refresh_token,
			'expires_at'    => time() + $expires_in,
		];

		self::store_tokens( $new_tokens );
		return true;
	}

	public static function store_tokens( $tokens ) {
		$token_data = [
			'access_token'  => $tokens['access_token'],
			'refresh_token' => isset( $tokens['refresh_token'] ) ? $tokens['refresh_token'] : '',
			'expires_at'    => isset( $tokens['expires_at'] ) ? (int) $tokens['expires_at'] : 0,
		];
		$encrypted = self::encrypt_tokens( $token_data );
		update_option( self::OPTION_TOKENS, $encrypted );
	}

	private static function encrypt_tokens( $data ) {
		try {
			$key = self::get_encryption_key();
			$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-gcm' ) );
			$tag = '';
			$enc = openssl_encrypt( json_encode( $data ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( $enc === false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FanBridge encryption failed' );
				}
				return '';
			}
			return base64_encode( $iv . $tag . $enc );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge encrypt_tokens error: ' . $e->getMessage() );
			}
			return '';
		}
	}

	private static function decrypt_tokens( $encrypted ) {
		try {
			$decoded = base64_decode( $encrypted );
			if ( $decoded === false || strlen( $decoded ) < 28 ) {
				return null;
			}
			$iv_len     = openssl_cipher_iv_length( 'aes-256-gcm' );
			$tag_len    = 16;
			$iv         = substr( $decoded, 0, $iv_len );
			$tag        = substr( $decoded, $iv_len, $tag_len );
			$ciphertext = substr( $decoded, $iv_len + $tag_len );
			$key       = self::get_encryption_key();
			$decrypted = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( $decrypted === false ) {
				return null;
			}
			return json_decode( $decrypted, true );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FanBridge decrypt_tokens error: ' . $e->getMessage() );
			}
			return null;
		}
	}

	private static function get_encryption_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secret   = $auth_key ?: wp_salt( 'auth' );
		return hash( 'sha256', $secret, true );
	}

	public static function get_tokens() {
		$stored = get_option( self::OPTION_TOKENS, '' );
		if ( empty( $stored ) ) {
			return [];
		}
		$decrypted = self::decrypt_tokens( $stored );
		return is_array( $decrypted ) ? $decrypted : [];
	}

	public static function get_access_token() {
		if ( ! self::refresh_tokens_if_needed() ) {
			return null;
		}
		$tokens = self::get_tokens();
		return isset( $tokens['access_token'] ) ? $tokens['access_token'] : null;
	}

	public static function is_connected() {
		return self::get_access_token() !== null;
	}
}
