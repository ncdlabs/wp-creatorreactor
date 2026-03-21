<?php
/**
 * CreatorReactor OAuth 2.0: PKCE, token exchange, refresh, and secure storage.
 *
 * Fanvue documents the same OAuth shape (PKCE required, auth + token endpoints) in their
 * Quick Start: https://api.fanvue.com/docs/authentication/quick-start
 * Authorization, token, and API base URLs default to Fanvue; override in plugin settings if needed.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatorReactor_OAuth {

	const OPTION_TOKENS          = 'creatorreactor_oauth_tokens';
	const TRANSIENT_PKCE_PREFIX  = 'creatorreactor_oauth_pkce_';
	const PKCE_TTL               = 600;
	const AUTH_URL               = 'https://auth.fanvue.com/oauth2/auth';
	const TOKEN_URL              = 'https://auth.fanvue.com/oauth2/token';
	const API_BASE_URL           = 'https://api.fanvue.com';
	const REST_NAMESPACE         = 'creatorreactor/v1';
	/** Fanvue docs / older apps often use this namespace; we register the same routes here so redirects still match. */
	const REST_NAMESPACE_LEGACY_FANVUE = 'fanvue/v1';
	const REST_ROUTE_CALLBACK   = '/oauth-callback';
	const REST_ROUTE_START      = '/oauth-start';

	const DEFAULT_SCOPES = 'openid offline_access offline read:self read:fan';

	/**
	 * Authorization (authorize) URL: plugin settings override, else {@see AUTH_URL}.
	 * Normalized to HTTPS, no trailing slash on path, no stray query/fragment on the base.
	 *
	 * @param array $opts Sanitized options from {@see Admin_Settings::get_options()}.
	 */
	private static function get_authorization_endpoint( array $opts ) {
		$url = isset( $opts['creatorreactor_authorization_url'] ) ? trim( (string) $opts['creatorreactor_authorization_url'] ) : '';
		return self::normalize_authorization_url( $url !== '' ? $url : self::AUTH_URL );
	}

	/**
	 * Canonical Fanvue authorize base URL (avoids malformed URLs that yield Fanvue 404s).
	 *
	 * @param string $url Full authorize URL or empty.
	 */
	private static function normalize_authorization_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return self::AUTH_URL;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return self::AUTH_URL;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( $scheme !== 'https' ) {
			return self::AUTH_URL;
		}
		$path = isset( $parts['path'] ) ? untrailingslashit( trim( $parts['path'] ) ) : '';
		if ( $path === '' ) {
			return self::AUTH_URL;
		}
		$host = $parts['host'];
		$rebuilt = 'https://' . $host;
		if ( ! empty( $parts['port'] ) && (int) $parts['port'] !== 443 ) {
			$rebuilt .= ':' . (int) $parts['port'];
		}
		$rebuilt .= $path[0] === '/' ? $path : '/' . $path;
		return $rebuilt;
	}

	/**
	 * Token URL: plugin settings override, else {@see TOKEN_URL}.
	 *
	 * @param array $opts Sanitized options from {@see Admin_Settings::get_options()}.
	 */
	private static function get_token_endpoint( array $opts ) {
		$url = isset( $opts['creatorreactor_token_url'] ) ? trim( (string) $opts['creatorreactor_token_url'] ) : '';
		return $url !== '' ? $url : self::TOKEN_URL;
	}

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
						$route_rtrim = rtrim( '/' . ltrim( $route, '/' ), '/' );
						foreach ( self::oauth_callback_namespaces() as $ns ) {
							$start = rtrim( '/' . $ns . self::REST_ROUTE_START, '/' );
							$cb    = rtrim( '/' . $ns . self::REST_ROUTE_CALLBACK, '/' );
							if ( $route_rtrim === $start || $route_rtrim === $cb ) {
								return true;
							}
						}
					}
				}
			}
		}
		// Cookie nonce runs before the REST server always has a matched route; fall back to path.
		$path = self::get_rest_request_path();
		if ( $path !== '' ) {
			foreach ( self::oauth_callback_namespaces() as $ns ) {
				if ( strpos( $path, $ns . self::REST_ROUTE_START ) !== false
					|| strpos( $path, $ns . self::REST_ROUTE_CALLBACK ) !== false ) {
					return true;
				}
			}
		}
		// Plain permalinks: ?rest_route=/creatorreactor/v1/oauth-callback (no /wp-json/ in REQUEST_URI).
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) {
			$rr = '/' . ltrim( wp_unslash( $_GET['rest_route'] ), '/' );
			foreach ( self::oauth_callback_namespaces() as $ns ) {
				$start = '/' . $ns . self::REST_ROUTE_START;
				$cb    = '/' . $ns . self::REST_ROUTE_CALLBACK;
				if ( strpos( $rr, $start ) !== false || strpos( $rr, $cb ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Path for the current HTTP request (no query string) for OAuth route detection.
	 *
	 * @return string
	 */
	private static function get_rest_request_path() {
		$candidates = [ 'REQUEST_URI', 'REDIRECT_URL', 'HTTP_X_ORIGINAL_URL', 'SCRIPT_NAME' ];
		foreach ( $candidates as $var ) {
			if ( empty( $_SERVER[ $var ] ) || ! is_string( $_SERVER[ $var ] ) ) {
				continue;
			}
			$raw  = sanitize_text_field( wp_unslash( $_SERVER[ $var ] ) );
			$path = strtok( $raw, '?' );
			if ( $path !== false && $path !== '' && strpos( $path, '/wp-json/' ) !== false ) {
				return $path;
			}
		}
		return '';
	}

	/**
	 * REST namespaces where oauth-start and oauth-callback are registered.
	 *
	 * @return string[]
	 */
	public static function oauth_callback_namespaces() {
		return [
			self::REST_NAMESPACE,
			self::REST_NAMESPACE_LEGACY_FANVUE,
		];
	}

	public static function register_routes() {
		$callback_args = [
			'methods'             => [ 'GET', 'POST', 'HEAD' ],
			'callback'            => [ __CLASS__, 'oauth_callback' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'code'              => [ 'required' => false, 'type' => 'string' ],
				'state'             => [ 'required' => false, 'type' => 'string' ],
				'error'             => [ 'required' => false, 'type' => 'string' ],
				'error_description' => [ 'required' => false, 'type' => 'string' ],
			],
		];

		foreach ( self::oauth_callback_namespaces() as $namespace ) {
			register_rest_route(
				$namespace,
				self::REST_ROUTE_START,
				[
					'methods'             => [ 'GET', 'HEAD' ],
					'callback'            => [ __CLASS__, 'oauth_start' ],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				]
			);

			register_rest_route(
				$namespace,
				self::REST_ROUTE_CALLBACK,
				$callback_args
			);
			// Fanvue may redirect with a trailing slash; WP REST matching can be strict.
			register_rest_route(
				$namespace,
				self::REST_ROUTE_CALLBACK . '/',
				$callback_args
			);
			register_rest_route(
				$namespace,
				self::REST_ROUTE_START . '/',
				[
					'methods'             => [ 'GET', 'HEAD' ],
					'callback'            => [ __CLASS__, 'oauth_start' ],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				]
			);
		}
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

	/**
	 * Canonical HTTPS redirect for a registered REST route. Uses {@see rest_url()} so it matches
	 * the live REST base (subdirectory installs, custom rest prefix, plain permalinks).
	 *
	 * @param string $namespace REST namespace, e.g. creatorreactor/v1.
	 * @param string $route     Route with leading slash, e.g. /oauth-callback.
	 */
	public static function get_rest_redirect_uri( $namespace, $route ) {
		$namespace = trim( (string) $namespace, '/' );
		$route     = '/' . ltrim( (string) $route, '/' );
		$path      = $namespace . $route;
		if ( function_exists( 'rest_url' ) ) {
			return trailingslashit( rest_url( $path ) );
		}
		$site_url = get_site_url();
		return trailingslashit( $site_url . '/wp-json/' . $path );
	}

	public static function get_default_redirect_uri() {
		return self::get_rest_redirect_uri( self::REST_NAMESPACE, self::REST_ROUTE_CALLBACK );
	}

	/** Legacy namespace; same handler — use in Fanvue if the app was registered with this path. */
	public static function get_legacy_fanvue_oauth_redirect_uri() {
		return self::get_rest_redirect_uri( self::REST_NAMESPACE_LEGACY_FANVUE, self::REST_ROUTE_CALLBACK );
	}

	public static function get_authorization_url( $redirect_uri_override = null ) {
		$opts        = Admin_Settings::get_options();
		$client_id   = $opts['creatorreactor_oauth_client_id'] ?? '';
		
		$default_redirect = self::get_default_redirect_uri();
		$redirect_uri = $redirect_uri_override !== null
			? $redirect_uri_override
			: ( ! empty( $opts['creatorreactor_oauth_redirect_uri'] ) ? $opts['creatorreactor_oauth_redirect_uri'] : $default_redirect );

		$scopes = $opts['creatorreactor_oauth_scopes'] ?? self::DEFAULT_SCOPES;

		if ( $client_id === '' ) {
			return null;
		}

		$code_verifier  = self::generate_code_verifier();
		$code_challenge = self::generate_code_challenge( $code_verifier );
		$state          = wp_generate_password( 32, false );

		// Persist exact redirect_uri used in the authorize request — token endpoint must match byte-for-byte.
		set_transient(
			self::TRANSIENT_PKCE_PREFIX . $state,
			[
				'code_verifier' => $code_verifier,
				'redirect_uri'  => $redirect_uri,
			],
			self::PKCE_TTL
		);

		$params = [
			'client_id'             => $client_id,
			'redirect_uri'          => $redirect_uri,
			'response_type'         => 'code',
			'scope'                => $scopes,
			'state'                => $state,
			'code_challenge'       => $code_challenge,
			'code_challenge_method' => 'S256',
		];
		
		$auth_base = self::get_authorization_endpoint( $opts );
		Admin_Settings::log_connection(
			'debug',
			'OAuth: authorization request prepared (redirect_uri=' . $redirect_uri . ', auth_endpoint=' . $auth_base . ').'
		);
		return $auth_base . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
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
			Admin_Settings::log_connection( 'info', 'OAuth REST oauth-start: invoked (redirect to Fanvue).' );
			$url = self::get_authorization_url();
			if ( ! $url ) {
				Admin_Settings::log_connection( 'error', 'OAuth REST oauth-start: failed — missing Client ID or could not build authorize URL.' );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'creatorreactor_oauth' => 'error',
							'message'        => __( 'OAuth not configured. Set Client ID and Redirect URI in settings.', 'creatorreactor' ),
						]
					)
				);
				exit;
			}
			Admin_Settings::log_connection( 'info', 'OAuth REST oauth-start: redirecting to Fanvue.' );
			wp_redirect( $url );
			exit;
		} catch ( \Throwable $e ) {
			Admin_Settings::log_connection( 'error', 'OAuth REST oauth-start: exception — ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( 'OAuth start failed: ' . $e->getMessage() );
			wp_safe_redirect( self::settings_redirect_url( [ 'creatorreactor_oauth' => 'error', 'message' => 'OAuth start failed' ] ) );
			exit;
		}
	}

	public static function oauth_callback( $request ) {
		try {
			$code  = $request->get_param( 'code' );
			$state = $request->get_param( 'state' );
			$error = $request->get_param( 'error' );

			$route = $request instanceof \WP_REST_Request ? $request->get_route() : '';
			Admin_Settings::log_connection( 'info', 'OAuth callback: hit REST route ' . ( is_string( $route ) ? $route : '(unknown)' ) . '.' );

			if ( $error ) {
				$msg = $request->get_param( 'error_description' ) ?: $error;
				Admin_Settings::log_connection( 'error', 'OAuth callback: Fanvue returned error parameter — ' . ( is_string( $msg ) ? $msg : wp_json_encode( $msg ) ) );
				Admin_Settings::set_last_error( 'OAuth: ' . $msg );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'creatorreactor_oauth' => 'error',
							'message'          => 'OAuth: ' . $msg,
						]
					)
				);
				exit;
			}

			if ( ! $code || ! $state ) {
				Admin_Settings::log_connection(
					'debug',
					'OAuth callback: no code/state (probe or manual GET). code=' . ( $code ? 'set' : 'empty' ) . ', state=' . ( $state ? 'set' : 'empty' ) . '.'
				);
				wp_safe_redirect( self::settings_redirect_url() );
				exit;
			}

			$pkce_payload = get_transient( self::TRANSIENT_PKCE_PREFIX . $state );
			delete_transient( self::TRANSIENT_PKCE_PREFIX . $state );

			$code_verifier = null;
			$redirect_uri  = null;
			if ( is_array( $pkce_payload ) && isset( $pkce_payload['code_verifier'] ) ) {
				$code_verifier = $pkce_payload['code_verifier'];
				$redirect_uri  = isset( $pkce_payload['redirect_uri'] ) && is_string( $pkce_payload['redirect_uri'] )
					? $pkce_payload['redirect_uri']
					: null;
			} elseif ( is_string( $pkce_payload ) && $pkce_payload !== '' ) {
				$code_verifier = $pkce_payload;
			}

			if ( ! $code_verifier || ! is_string( $code_verifier ) ) {
				$msg = __( 'OAuth: Invalid or expired state. Please try connecting again.', 'creatorreactor' );
				Admin_Settings::log_connection( 'error', 'OAuth callback: PKCE/state mismatch or expired (state present, verifier missing).' );
				Admin_Settings::set_last_error( $msg );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'creatorreactor_oauth' => 'error',
							'message'          => $msg,
						]
					)
				);
				exit;
			}

			$opts             = Admin_Settings::get_options();
			$default_redirect = self::get_default_redirect_uri();
			if ( $redirect_uri === null || $redirect_uri === '' ) {
				$redirect_uri = ! empty( $opts['creatorreactor_oauth_redirect_uri'] ) ? $opts['creatorreactor_oauth_redirect_uri'] : $default_redirect;
			}

			Admin_Settings::log_connection( 'info', 'OAuth callback: exchanging code for tokens (redirect_uri used in token request matches authorize step).' );

			$tokens = self::exchange_code_for_tokens( $code, $code_verifier, $redirect_uri, $opts );
			if ( is_wp_error( $tokens ) ) {
				$msg = 'OAuth: ' . $tokens->get_error_message();
				Admin_Settings::set_last_error( $msg );
				wp_safe_redirect(
					self::settings_redirect_url(
						[
							'creatorreactor_oauth' => 'error',
							'message'          => $msg,
						]
					)
				);
				exit;
			}

			self::store_tokens( $tokens );
			Admin_Settings::log_connection( 'info', 'OAuth callback: success — tokens stored.' );
			Admin_Settings::set_last_error( '' );
			wp_safe_redirect(
				self::settings_redirect_url(
					[
						'creatorreactor_oauth' => 'success',
					]
				)
			);
			exit;
		} catch ( \Throwable $e ) {
			Admin_Settings::log_connection( 'error', 'OAuth callback: exception — ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( 'OAuth callback failed: ' . $e->getMessage() );
			wp_safe_redirect( self::settings_redirect_url( [ 'creatorreactor_oauth' => 'error', 'message' => 'OAuth callback failed' ] ) );
			exit;
		}
	}

	public static function exchange_code_for_tokens( $code, $code_verifier, $redirect_uri, $opts ) {
		$client_id     = isset( $opts['creatorreactor_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_oauth_client_id'] ) : '';
		$client_secret = isset( $opts['creatorreactor_oauth_client_secret'] ) ? (string) $opts['creatorreactor_oauth_client_secret'] : '';
		
		if ( $client_id === '' || $client_secret === '' ) {
			$msg = $client_secret === ''
				? __( 'OAuth Client Secret is missing or not saved. Re-enter the Client Secret from your creatorreactor app, click Save settings, then try Connect again.', 'creatorreactor' )
				: __( 'OAuth client ID or secret not set.', 'creatorreactor' );
			Admin_Settings::log_connection( 'error', 'OAuth token exchange: configuration error — ' . $msg );
			return new \WP_Error( 'config', $msg );
		}

		$body        = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
			'code_verifier' => $code_verifier,
		];
		$auth_header = 'Basic ' . base64_encode( $client_id . ':' . $client_secret );
		$token_url = self::get_token_endpoint( $opts );
		$response  = wp_remote_post(
			$token_url,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => $auth_header,
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			Admin_Settings::log_connection( 'error', 'OAuth token exchange: transport error — ' . $response->get_error_message() );
			return new \WP_Error( 'token_exchange', $response->get_error_message() );
		}

		$code_http = wp_remote_retrieve_response_code( $response );
		$body_res  = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body_res, true );
		if ( $code_http !== 200 ) {
			$msg = isset( $data['error_description'] ) ? $data['error_description'] : ( isset( $data['error'] ) ? $data['error'] : 'HTTP ' . $code_http );
			$snippet = $body_res;
			if ( strlen( $snippet ) > 800 ) {
				$snippet = substr( $snippet, 0, 800 ) . '…';
			}
			Admin_Settings::log_connection(
				'error',
				'OAuth token exchange: HTTP ' . $code_http . ' from ' . $token_url . '. Body: ' . preg_replace( '/\s+/', ' ', wp_strip_all_tags( $snippet ) )
			);
			return new \WP_Error( 'token_exchange', $msg );
		}
		if ( ! isset( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			Admin_Settings::log_connection( 'error', 'OAuth token exchange: HTTP 200 but response missing access_token.' );
			return new \WP_Error( 'token_exchange', __( 'Invalid token response.', 'creatorreactor' ) );
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
		$client_id     = $opts['creatorreactor_oauth_client_id'] ?? '';
		$client_secret = $opts['creatorreactor_oauth_client_secret'] ?? '';
		if ( $client_id === '' || $client_secret === '' ) {
			return false;
		}

		$body        = [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
		];
		$auth_header = 'Basic ' . base64_encode( $client_id . ':' . $client_secret );
		$token_url   = self::get_token_endpoint( $opts );
		$response    = wp_remote_post(
			$token_url,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => $auth_header,
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			Admin_Settings::log_connection( 'error', 'OAuth token refresh: transport error — ' . $response->get_error_message() );
			Admin_Settings::set_last_error( __( 'OAuth refresh failed.', 'creatorreactor' ) );
			return false;
		}

		$code_http = wp_remote_retrieve_response_code( $response );
		$body_res  = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body_res, true );
		if ( $code_http !== 200 ) {
			$snippet = $body_res;
			if ( strlen( $snippet ) > 500 ) {
				$snippet = substr( $snippet, 0, 500 ) . '…';
			}
			$desc = isset( $data['error_description'] ) ? (string) $data['error_description'] : '';
			// Fanvue: refresh token is bound to the Client ID used at authorize time; mismatch yields invalid_grant.
			$client_mismatch = ( stripos( $desc, 'client id' ) !== false && stripos( $desc, 'does not match' ) !== false )
				|| stripos( $desc, 'does not match the id during the initial token issuance' ) !== false;
			if ( $client_mismatch ) {
				delete_option( self::OPTION_TOKENS );
				$msg = __( 'OAuth tokens were issued for a different Client ID than the one in settings. Use Disconnect, confirm Client ID and Secret match your Fanvue app, then Connect again.', 'creatorreactor' );
				Admin_Settings::set_last_error( $msg );
				Admin_Settings::log_connection(
					'info',
					'OAuth token refresh: cleared stored tokens (Client ID no longer matches token issuer). ' . preg_replace( '/\s+/', ' ', wp_strip_all_tags( $snippet ) )
				);
				return false;
			}
			Admin_Settings::log_connection(
				'error',
				'OAuth token refresh: HTTP ' . $code_http . '. ' . preg_replace( '/\s+/', ' ', wp_strip_all_tags( $snippet ) )
			);
			Admin_Settings::set_last_error( __( 'OAuth refresh failed.', 'creatorreactor' ) );
			return false;
		}
		if ( ! isset( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			Admin_Settings::log_connection( 'error', 'OAuth token refresh: success response missing access_token.' );
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
		if ( $encrypted === '' ) {
			Admin_Settings::log_connection( 'error', 'OAuth: failed to encrypt tokens for storage (openssl or empty ciphertext).' );
		}
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
					error_log( 'CreatorReactor encryption failed' );
				}
				return '';
			}
			return base64_encode( $iv . $tag . $enc );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor encrypt_tokens error: ' . $e->getMessage() );
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
				error_log( 'CreatorReactor decrypt_tokens error: ' . $e->getMessage() );
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
