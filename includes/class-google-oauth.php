<?php
/**
 * Google OAuth 2.0 (OpenID Connect): Sign in with Google for wp-login and shortcodes.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_OAuth {

	const REST_ROUTE_START    = '/google-oauth-start';
	const REST_ROUTE_CALLBACK = '/google-oauth-callback';
	const USERMETA_GOOGLE_SUB = 'creatorreactor_google_sub';
	const NONCE_ACTION        = 'creatorreactor_google_oauth';

	const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

	/**
	 * Prevent CDNs / full-page cache from caching OAuth redirects.
	 */
	private static function send_no_store_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
	}

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_without_cookie_nonce' ], 999 );
	}

	/**
	 * @param mixed $result Previous value.
	 * @return mixed
	 */
	public static function allow_without_cookie_nonce( $result ) {
		$is_nonce_error = is_wp_error( $result ) && $result->get_error_code() === 'rest_cookie_invalid_nonce';
		if ( $is_nonce_error && self::is_google_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_google_oauth_rest_request() {
		$needles = [ 'google-oauth-start', 'google-oauth-callback' ];

		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server && method_exists( $server, 'get_request' ) ) {
				$request = $server->get_request();
				if ( $request instanceof \WP_REST_Request ) {
					$route = $request->get_route();
					if ( is_string( $route ) ) {
						foreach ( $needles as $needle ) {
							if ( stripos( $route, $needle ) !== false ) {
								return true;
							}
						}
					}
				}
			}
		}
		$server_keys = [ 'REQUEST_URI', 'REDIRECT_URL', 'HTTP_X_ORIGINAL_URL', 'PATH_INFO' ];
		foreach ( $server_keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
				continue;
			}
			$chunk = wp_unslash( $_SERVER[ $key ] );
			foreach ( $needles as $needle ) {
				if ( stripos( $chunk, $needle ) !== false ) {
					return true;
				}
			}
		}
		if ( ! empty( $_SERVER['QUERY_STRING'] ) && is_string( $_SERVER['QUERY_STRING'] ) ) {
			$qs = wp_unslash( $_SERVER['QUERY_STRING'] );
			foreach ( $needles as $needle ) {
				if ( stripos( $qs, $needle ) !== false ) {
					return true;
				}
			}
		}
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) {
			$rr = wp_unslash( $_GET['rest_route'] );
			foreach ( $needles as $needle ) {
				if ( stripos( $rr, $needle ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function register_routes() {
		$ns    = CreatorReactor_OAuth::REST_NAMESPACE;
		$start = [
			'methods'             => [ 'GET', 'HEAD' ],
			'callback'            => [ __CLASS__, 'rest_start' ],
			'permission_callback' => '__return_true',
		];
		$cb = [
			'methods'             => [ 'GET', 'POST', 'HEAD' ],
			'callback'            => [ __CLASS__, 'rest_callback' ],
			'permission_callback' => '__return_true',
		];
		register_rest_route( $ns, self::REST_ROUTE_START, $start );
		register_rest_route( $ns, self::REST_ROUTE_START . '/', $start );
		register_rest_route( $ns, self::REST_ROUTE_CALLBACK, $cb );
		register_rest_route( $ns, self::REST_ROUTE_CALLBACK . '/', $cb );
	}

	/**
	 * Add this Authorized redirect URI in Google Cloud Console (OAuth client).
	 */
	public static function get_callback_redirect_uri() {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, self::REST_ROUTE_CALLBACK )
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_start( $request ) {
		self::send_no_store_headers();
		$method = strtoupper( (string) $request->get_method() );
		$home   = home_url( '/' );

		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}

		$nonce = $request->get_param( '_wpnonce' );
		$nonce = is_string( $nonce ) ? $nonce : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'nonce', $home ) );
			exit;
		}

		$redirect_to = $request->get_param( 'redirect_to' );
		$redirect_to = is_string( $redirect_to ) ? rawurldecode( $redirect_to ) : '';
		$redirect_to = $redirect_to !== '' ? wp_validate_redirect( $redirect_to, $home ) : $home;
		$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );

		if ( Admin_Settings::is_broker_mode() ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'agency', $redirect_to ) );
			exit;
		}

		if ( ! Admin_Settings::is_google_login_configured() ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'config', $redirect_to ) );
			exit;
		}

		$opts      = Admin_Settings::get_options();
		$client_id = isset( $opts['creatorreactor_google_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_id'] ) : '';
		if ( $client_id === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'config', $redirect_to ) );
			exit;
		}

		$callback     = self::get_callback_redirect_uri();
		$code_verifier  = CreatorReactor_OAuth::generate_code_verifier();
		$code_challenge = CreatorReactor_OAuth::generate_code_challenge( $code_verifier );
		$state          = wp_generate_password( 32, false );

		CreatorReactor_OAuth::store_pkce_payload(
			$state,
			[
				'code_verifier' => $code_verifier,
				'redirect_uri'  => $callback,
				'redirect_to'   => $redirect_to,
				'google_oauth'  => true,
			]
		);

		$params = [
			'client_id'             => $client_id,
			'redirect_uri'          => $callback,
			'response_type'         => 'code',
			'scope'                 => 'openid email profile',
			'state'                 => $state,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => 'S256',
		];

		$auth_url = self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_callback( $request ) {
		self::send_no_store_headers();
		$home = home_url( '/' );

		$method = strtoupper( (string) $request->get_method() );
		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}

		$state = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'state' );
		$pkce  = ( $state !== '' ) ? CreatorReactor_OAuth::get_pkce_payload( $state ) : false;
		$redirect_to = $home;
		if ( is_array( $pkce ) && ! empty( $pkce['redirect_to'] ) && is_string( $pkce['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( $pkce['redirect_to'], $home );
			$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );
		}

		$error = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'error' );
		if ( $error !== '' ) {
			if ( $state !== '' ) {
				CreatorReactor_OAuth::delete_pkce_payload( $state );
			}
			$ecode  = sanitize_key( $error );
			$notice = 'denied';
			if ( $ecode === 'access_denied' ) {
				$notice = 'denied';
			} elseif ( $ecode === 'redirect_uri_mismatch' ) {
				$notice = 'oauth_redirect';
			} elseif ( in_array( $ecode, [ 'invalid_client', 'unauthorized_client' ], true ) ) {
				$notice = 'oauth_client';
			} elseif ( $ecode === 'invalid_request' ) {
				$notice = 'oauth_request';
			} elseif ( $ecode !== '' ) {
				$notice = 'oauth_error';
			}
			Admin_Settings::log_connection( 'error', 'Google OAuth: authorization error: ' . $ecode );
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', $notice, $redirect_to ) );
			exit;
		}

		$code = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'code' );
		if ( $code === '' || $state === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'missing', $redirect_to ) );
			exit;
		}

		CreatorReactor_OAuth::delete_pkce_payload( $state );

		if ( ! is_array( $pkce ) || empty( $pkce['google_oauth'] ) || empty( $pkce['code_verifier'] ) || ! is_string( $pkce['code_verifier'] ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'state', $redirect_to ) );
			exit;
		}

		$redirect_uri = isset( $pkce['redirect_uri'] ) && is_string( $pkce['redirect_uri'] ) ? $pkce['redirect_uri'] : self::get_callback_redirect_uri();
		$opts         = Admin_Settings::get_options();
		$client_id    = isset( $opts['creatorreactor_google_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_id'] ) : '';
		$client_secret = isset( $opts['creatorreactor_google_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_secret'] ) : '';

		if ( $client_id === '' || $client_secret === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'config', $redirect_to ) );
			exit;
		}

		$tokens = self::exchange_code_for_tokens( $code, $pkce['code_verifier'], $redirect_uri, $client_id, $client_secret );
		if ( is_wp_error( $tokens ) ) {
			Admin_Settings::log_connection( 'error', 'Google OAuth: token exchange failed: ' . $tokens->get_error_message() );
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'token', $redirect_to ) );
			exit;
		}

		$access = isset( $tokens['access_token'] ) && is_string( $tokens['access_token'] ) ? $tokens['access_token'] : '';
		if ( $access === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'token', $redirect_to ) );
			exit;
		}

		$profile = self::fetch_google_userinfo( $access );
		if ( ! is_array( $profile ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'profile', $redirect_to ) );
			exit;
		}

		$sub   = isset( $profile['sub'] ) && is_string( $profile['sub'] ) ? sanitize_text_field( $profile['sub'] ) : '';
		$email = isset( $profile['email'] ) && is_string( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
		$name  = isset( $profile['name'] ) && is_string( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : '';

		if ( $sub === '' || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'user', $redirect_to ) );
			exit;
		}

		$user = self::get_user_by_google_sub( $sub );
		if ( ! $user && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
		}

		if ( ! $user ) {
			if ( ! get_option( 'users_can_register' ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'closed', $redirect_to ) );
				exit;
			}
			$new_uid = self::insert_wp_user_from_google( $email, $name, $sub );
			if ( is_wp_error( $new_uid ) ) {
				Admin_Settings::log_connection( 'error', 'Google OAuth: user creation failed: ' . $new_uid->get_error_message() );
				wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'user', $redirect_to ) );
				exit;
			}
			$user = get_user_by( 'id', (int) $new_uid );
			if ( ! ( $user instanceof \WP_User ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_google', 'user', $redirect_to ) );
				exit;
			}
		}

		update_user_meta( $user->ID, self::USERMETA_GOOGLE_SUB, $sub );

		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( Onboarding::get_post_oauth_redirect( $user->ID, $redirect_to ) );
		exit;
	}

	/**
	 * @param string $code          Authorization code.
	 * @param string $code_verifier PKCE verifier.
	 * @param string $redirect_uri  Callback URI.
	 * @param string $client_id     OAuth client ID.
	 * @param string $client_secret OAuth client secret.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function exchange_code_for_tokens( $code, $code_verifier, $redirect_uri, $client_id, $client_secret ) {
		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 30,
				'body'    => [
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
					'code_verifier' => $code_verifier,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code_http = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( is_string( $body ) ? $body : '', true );
		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'google_token', __( 'Invalid token response from Google.', 'creatorreactor' ) );
		}
		return $data;
	}

	/**
	 * @param string $access_token Bearer token.
	 * @return array<string, mixed>|null
	 */
	private static function fetch_google_userinfo( $access_token ) {
		$response = wp_remote_get(
			self::USERINFO_URL,
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return null;
		}
		return $data;
	}

	/**
	 * @param string $sub Google subject identifier.
	 * @return \WP_User|false
	 */
	private static function get_user_by_google_sub( $sub ) {
		$sub = sanitize_text_field( (string) $sub );
		if ( $sub === '' ) {
			return false;
		}
		$users = get_users(
			[
				'meta_key'    => self::USERMETA_GOOGLE_SUB,
				'meta_value'  => $sub,
				'number'      => 1,
				'count_total' => false,
			]
		);
		if ( ! is_array( $users ) || ! isset( $users[0] ) || ! ( $users[0] instanceof \WP_User ) ) {
			return false;
		}
		return $users[0];
	}

	/**
	 * @param string $email Verified email.
	 * @param string $name  Display name.
	 * @param string $sub   Google sub.
	 * @return int|\WP_Error
	 */
	private static function insert_wp_user_from_google( $email, $name, $sub ) {
		$email = sanitize_email( (string) $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email from Google profile.', 'creatorreactor' ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'exists', __( 'A user with this email already exists.', 'creatorreactor' ) );
		}
		$login_base = sanitize_user( current( explode( '@', $email, 2 ) ), true );
		if ( $login_base === '' ) {
			$login_base = 'google_user';
		}
		$login = $login_base;
		$n     = 0;
		while ( username_exists( $login ) ) {
			++$n;
			$login = $login_base . $n;
		}
		$display = $name !== '' ? $name : $login;
		return wp_insert_user(
			[
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $display,
				'role'         => get_option( 'default_role', 'subscriber' ),
			]
		);
	}
}
