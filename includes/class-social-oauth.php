<?php
/**
 * Generic OAuth 2.0 social login for TikTok, X, Snapchat, LinkedIn, Pinterest, Reddit, Twitch, Discord, Mastodon.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_OAuth {

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
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_without_cookie_nonce' ], 998 );
	}

	/**
	 * @param mixed $result Previous value.
	 * @return mixed
	 */
	public static function allow_without_cookie_nonce( $result ) {
		$is_nonce_error = is_wp_error( $result ) && $result->get_error_code() === 'rest_cookie_invalid_nonce';
		if ( $is_nonce_error && self::is_social_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_social_oauth_rest_request() {
		$needles = [];
		foreach ( Social_OAuth_Registry::generic_slugs() as $slug ) {
			$seg = Social_OAuth_Registry::rest_segment( $slug );
			$needles[] = $seg . '-oauth-start';
			$needles[] = $seg . '-oauth-callback';
		}
		$needles[] = 'bluesky-oauth-start';
		$needles[] = 'bluesky-oauth-callback';
		$needles[] = 'bluesky-oauth-client-metadata';
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
		$ns = CreatorReactor_OAuth::REST_NAMESPACE;
		foreach ( Social_OAuth_Registry::generic_slugs() as $slug ) {
			$start = Social_OAuth_Registry::rest_route_start( $slug );
			$cb    = Social_OAuth_Registry::rest_route_callback( $slug );
			$start_args = [
				'methods'             => [ 'GET', 'HEAD' ],
				'callback'            => static function ( $request ) use ( $slug ) {
					return Social_OAuth::rest_start( $request, $slug );
				},
				'permission_callback' => '__return_true',
			];
			$cb_args = [
				'methods'             => [ 'GET', 'POST', 'HEAD' ],
				'callback'            => static function ( $request ) use ( $slug ) {
					return Social_OAuth::rest_callback( $request, $slug );
				},
				'permission_callback' => '__return_true',
			];
			register_rest_route( $ns, $start, $start_args );
			register_rest_route( $ns, $start . '/', $start_args );
			register_rest_route( $ns, $cb, $cb_args );
			register_rest_route( $ns, $cb . '/', $cb_args );
		}
	}

	/**
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function get_callback_redirect_uri( $slug ) {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri(
				CreatorReactor_OAuth::REST_NAMESPACE,
				Social_OAuth_Registry::rest_route_callback( $slug )
			)
		);
	}

	/**
	 * @param string               $slug          Provider slug.
	 * @param string               $client_id     Client ID.
	 * @param string               $client_secret Client secret.
	 * @param string               $redirect_uri  Callback URI.
	 * @param array<string, mixed> $opts          Options (Mastodon instance).
	 * @return true|\WP_Error
	 */
	public static function probe_client_credentials_for_settings_test( $slug, $client_id, $client_secret, $redirect_uri, array $opts ) {
		$client_id     = trim( (string) $client_id );
		$client_secret = trim( (string) $client_secret );
		$redirect_uri  = (string) $redirect_uri;
		if ( $client_id === '' ) {
			return new \WP_Error( 'social_oauth_probe_no_client_id', __( 'Client ID is empty.', 'wp-creatorreactor' ) );
		}
		if ( $client_secret === '' ) {
			return new \WP_Error( 'social_oauth_probe_no_secret', __( 'Client Secret is empty.', 'wp-creatorreactor' ) );
		}
		$cfg = Social_OAuth_Registry::get_config( $slug );
		if ( ! is_array( $cfg ) ) {
			return new \WP_Error( 'social_oauth_probe_no_cfg', __( 'Unknown provider.', 'wp-creatorreactor' ) );
		}
		if ( ! empty( $cfg['mastodon'] ) ) {
			$base = self::normalize_mastodon_base_from_opts( $opts );
			if ( $base === '' ) {
				return new \WP_Error( 'social_oauth_probe_no_instance', __( 'Enter your Mastodon instance URL.', 'wp-creatorreactor' ) );
			}
			$token_url = $base . '/oauth/token';
		} else {
			$token_url = (string) $cfg['token_url'];
		}
		$token_auth = isset( $cfg['token_auth'] ) ? (string) $cfg['token_auth'] : 'post';
		$args       = self::build_token_request_args(
			$slug,
			$cfg,
			$token_url,
			$client_id,
			$client_secret,
			$redirect_uri,
			'creatorreactor_settings_test_invalid_code',
			'creatorreactor_settings_test_verifier',
			$token_auth
		);
		$response   = wp_remote_post( $token_url, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'social_oauth_probe_http',
				sprintf(
					/* translators: %s: HTTP error message. */
					__( 'Could not reach the token endpoint (%s).', 'wp-creatorreactor' ),
					$response->get_error_message()
				)
			);
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'social_oauth_probe_bad_response', __( 'Unexpected token response.', 'wp-creatorreactor' ) );
		}
		if ( ! empty( $data['access_token'] ) ) {
			return true;
		}
		$err = isset( $data['error'] ) ? sanitize_key( (string) $data['error'] ) : '';
		if ( $err === 'invalid_grant' ) {
			return true;
		}
		$desc = isset( $data['error_description'] ) ? sanitize_text_field( (string) $data['error_description'] ) : '';
		return new \WP_Error(
			'social_oauth_probe_failed',
			$desc !== '' ? $desc : ( $err !== '' ? $err : __( 'Credentials were rejected.', 'wp-creatorreactor' ) ),
			[ 'oauth_error' => $err ]
		);
	}

	/**
	 * @param array<string, mixed> $opts Options.
	 * @return string Normalized https base or ''.
	 */
	public static function normalize_mastodon_base_from_opts( array $opts ) {
		$raw = isset( $opts['creatorreactor_mastodon_instance'] ) ? trim( (string) $opts['creatorreactor_mastodon_instance'] ) : '';
		if ( $raw === '' ) {
			return '';
		}
		if ( strpos( $raw, 'http://' ) !== 0 && strpos( $raw, 'https://' ) !== 0 ) {
			$raw = 'https://' . $raw;
		}
		$parts = \wp_parse_url( $raw );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		if ( $scheme !== 'https' && $scheme !== 'http' ) {
			$scheme = 'https';
		}
		$host = $parts['host'];
		$port = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @param string           $slug    Provider slug.
	 */
	public static function rest_start( $request, $slug ) {
		self::send_no_store_headers();
		$method = strtoupper( (string) $request->get_method() );
		$home   = home_url( '/' );
		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}
		$nonce = $request->get_param( '_wpnonce' );
		$nonce = is_string( $nonce ) ? $nonce : '';
		if ( ! wp_verify_nonce( $nonce, Social_OAuth_Registry::nonce_action( $slug ) ) ) {
			wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'nonce', $home ) );
			exit;
		}
		$redirect_to = $request->get_param( 'redirect_to' );
		$redirect_to = is_string( $redirect_to ) ? rawurldecode( $redirect_to ) : '';
		$redirect_to = $redirect_to !== '' ? wp_validate_redirect( $redirect_to, $home ) : $home;
		$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );
		if ( Admin_Settings::is_broker_mode() ) {
			wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'agency', $redirect_to ) );
			exit;
		}
		if ( ! Admin_Settings::is_social_oauth_provider_configured( $slug ) ) {
			wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'config', $redirect_to ) );
			exit;
		}
		$opts      = Admin_Settings::get_options();
		$client_id = isset( $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) : '';
		$secret    = isset( $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) : '';
		if ( $client_id === '' || $secret === '' ) {
			wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'config', $redirect_to ) );
			exit;
		}
		$cfg = Social_OAuth_Registry::get_config( $slug );
		if ( ! is_array( $cfg ) ) {
			wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'config', $redirect_to ) );
			exit;
		}
		if ( ! empty( $cfg['mastodon'] ) ) {
			$base = self::normalize_mastodon_base_from_opts( $opts );
			if ( $base === '' ) {
				wp_safe_redirect( add_query_arg( Social_OAuth_Registry::query_arg( $slug ), 'config', $redirect_to ) );
				exit;
			}
		}
		$callback      = self::get_callback_redirect_uri( $slug );
		$state         = wp_generate_password( 32, false );
		$use_pkce      = ! isset( $cfg['pkce'] ) || $cfg['pkce'];
		$payload_store = [
			'redirect_uri'  => $callback,
			'redirect_to'   => $redirect_to,
			'social_oauth'  => true,
			'provider_slug' => $slug,
		];
		if ( $use_pkce ) {
			$code_verifier                   = CreatorReactor_OAuth::generate_code_verifier();
			$code_challenge                  = CreatorReactor_OAuth::generate_code_challenge( $code_verifier );
			$payload_store['code_verifier']  = $code_verifier;
			$payload_store['code_challenge'] = $code_challenge;
		}
		CreatorReactor_OAuth::store_pkce_payload( $state, $payload_store );
		$cid_param = isset( $cfg['client_id_param'] ) ? (string) $cfg['client_id_param'] : 'client_id';
		$params    = [
			$cid_param      => $client_id,
			'redirect_uri'  => $callback,
			'response_type' => 'code',
			'scope'         => isset( $cfg['scopes'] ) ? (string) $cfg['scopes'] : '',
			'state'         => $state,
		];
		if ( $use_pkce && isset( $code_challenge ) ) {
			$params['code_challenge']        = $code_challenge;
			$params['code_challenge_method'] = 'S256';
		}
		if ( ! empty( $cfg['extra_auth_params'] ) && is_array( $cfg['extra_auth_params'] ) ) {
			$params = array_merge( $params, $cfg['extra_auth_params'] );
		}
		$auth_url = ! empty( $cfg['mastodon'] )
			? self::normalize_mastodon_base_from_opts( $opts ) . '/oauth/authorize'
			: (string) $cfg['auth_url'];
		$auth_url = $auth_url . ( strpos( $auth_url, '?' ) !== false ? '&' : '?' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @param string           $slug    Provider slug.
	 */
	public static function rest_callback( $request, $slug ) {
		self::send_no_store_headers();
		$home = home_url( '/' );
		$method = strtoupper( (string) $request->get_method() );
		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}
		$qarg = Social_OAuth_Registry::query_arg( $slug );
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
			Admin_Settings::log_connection( 'error', 'Social OAuth (' . $slug . '): authorization error: ' . $ecode );
			wp_safe_redirect( add_query_arg( $qarg, $notice, $redirect_to ) );
			exit;
		}
		$code = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'code' );
		if ( $code === '' || $state === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'missing', $redirect_to ) );
			exit;
		}
		CreatorReactor_OAuth::delete_pkce_payload( $state );
		$cfg_chk = Social_OAuth_Registry::get_config( $slug );
		$needs_verifier = is_array( $cfg_chk ) && ( ! isset( $cfg_chk['pkce'] ) || $cfg_chk['pkce'] );
		if ( ! is_array( $pkce ) || empty( $pkce['social_oauth'] ) || empty( $pkce['provider_slug'] ) || $pkce['provider_slug'] !== $slug ) {
			wp_safe_redirect( add_query_arg( $qarg, 'state', $redirect_to ) );
			exit;
		}
		if ( $needs_verifier && ( empty( $pkce['code_verifier'] ) || ! is_string( $pkce['code_verifier'] ) ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'state', $redirect_to ) );
			exit;
		}
		$redirect_uri = isset( $pkce['redirect_uri'] ) && is_string( $pkce['redirect_uri'] ) ? $pkce['redirect_uri'] : self::get_callback_redirect_uri( $slug );
		$opts         = Admin_Settings::get_options();
		$client_id    = isset( $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) : '';
		$client_secret = isset( $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) : '';
		if ( $client_id === '' || $client_secret === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'config', $redirect_to ) );
			exit;
		}
		$cfg = Social_OAuth_Registry::get_config( $slug );
		if ( ! is_array( $cfg ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'config', $redirect_to ) );
			exit;
		}
		$token_auth = isset( $cfg['token_auth'] ) ? (string) $cfg['token_auth'] : 'post';
		$token_url  = ! empty( $cfg['mastodon'] )
			? self::normalize_mastodon_base_from_opts( $opts ) . '/oauth/token'
			: (string) $cfg['token_url'];
		$verifier = ( isset( $pkce['code_verifier'] ) && is_string( $pkce['code_verifier'] ) ) ? $pkce['code_verifier'] : '';
		$tokens   = self::exchange_code_for_tokens(
			$slug,
			$cfg,
			$token_url,
			$code,
			$verifier,
			$redirect_uri,
			$client_id,
			$client_secret,
			$token_auth
		);
		if ( is_wp_error( $tokens ) ) {
			Admin_Settings::log_connection( 'error', 'Social OAuth (' . $slug . '): token exchange failed: ' . $tokens->get_error_message() );
			wp_safe_redirect( add_query_arg( $qarg, 'token', $redirect_to ) );
			exit;
		}
		$access = isset( $tokens['access_token'] ) && is_string( $tokens['access_token'] ) ? $tokens['access_token'] : '';
		if ( $access === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'token', $redirect_to ) );
			exit;
		}
		$profile = self::fetch_profile( $slug, $cfg, $access, $client_id, $opts );
		if ( ! is_array( $profile ) || empty( $profile['id'] ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'profile', $redirect_to ) );
			exit;
		}
		self::finalize_oauth_login( $slug, $redirect_to, $profile, $qarg );
	}

	/**
	 * Create/update WP user from OAuth profile and redirect (shared with Bluesky_OAuth).
	 *
	 * @param string               $slug        Provider slug.
	 * @param string               $redirect_to Validated redirect URL.
	 * @param array<string, mixed> $profile     Keys: id, optional email, optional name.
	 * @param string               $query_arg   Notice query arg (e.g. creatorreactor_tiktok).
	 */
	public static function finalize_oauth_login( $slug, $redirect_to, array $profile, $query_arg ) {
		$qarg = (string) $query_arg;
		if ( ! is_array( $profile ) || empty( $profile['id'] ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'profile', $redirect_to ) );
			exit;
		}
		$sub   = sanitize_text_field( (string) $profile['id'] );
		$email = isset( $profile['email'] ) && is_string( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
		$name  = isset( $profile['name'] ) && is_string( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : '';
		if ( $sub === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'user', $redirect_to ) );
			exit;
		}
		if ( ! is_email( $email ) ) {
			$email = self::synthetic_email_for_oauth( $slug, $sub );
		}
		$user = self::get_user_by_sub( $slug, $sub );
		if ( ! $user && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
		}
		if ( ! $user ) {
			if ( ! get_option( 'users_can_register' ) ) {
				wp_safe_redirect( add_query_arg( $qarg, 'closed', $redirect_to ) );
				exit;
			}
			$new_uid = self::insert_wp_user_from_oauth( $email, $name, $slug );
			if ( is_wp_error( $new_uid ) ) {
				Admin_Settings::log_connection( 'error', 'Social OAuth (' . $slug . '): user creation failed: ' . $new_uid->get_error_message() );
				wp_safe_redirect( add_query_arg( $qarg, 'user', $redirect_to ) );
				exit;
			}
			$user = get_user_by( 'id', (int) $new_uid );
			if ( ! ( $user instanceof \WP_User ) ) {
				wp_safe_redirect( add_query_arg( $qarg, 'user', $redirect_to ) );
				exit;
			}
		}
		update_user_meta( $user->ID, Social_OAuth_Registry::usermeta_sub_key( $slug ), $sub );
		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );
		do_action( 'wp_login', $user->user_login, $user );
		wp_safe_redirect( Onboarding::get_post_oauth_redirect( $user->ID, $redirect_to ) );
		exit;
	}

	/**
	 * @param string               $slug          Provider slug.
	 * @param array<string, mixed> $cfg           Registry config.
	 * @param string               $token_url     Token endpoint.
	 * @param string               $code          Auth code.
	 * @param string               $code_verifier PKCE verifier.
	 * @param string               $redirect_uri  Callback URI.
	 * @param string               $client_id     Client ID.
	 * @param string               $client_secret Secret.
	 * @param string               $token_auth    post|basic.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function exchange_code_for_tokens( $slug, array $cfg, $token_url, $code, $code_verifier, $redirect_uri, $client_id, $client_secret, $token_auth ) {
		$args = self::build_token_request_args(
			$slug,
			$cfg,
			$token_url,
			$client_id,
			$client_secret,
			$redirect_uri,
			$code,
			$code_verifier,
			$token_auth
		);
		$response = wp_remote_post( $token_url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code_http = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( is_string( $body ) ? $body : '', true );
		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'social_token', __( 'Invalid token response.', 'wp-creatorreactor' ) );
		}
		return $data;
	}

	/**
	 * @param string               $slug          Provider slug.
	 * @param array<string, mixed> $cfg           Registry config.
	 * @param string               $token_url     Token URL.
	 * @param string               $client_id     Client ID.
	 * @param string               $client_secret Secret.
	 * @param string               $redirect_uri  Redirect.
	 * @param string               $code          Code or test code.
	 * @param string               $code_verifier Verifier.
	 * @param string               $token_auth    post|basic.
	 * @return array<string, mixed>
	 */
	private static function build_token_request_args( $slug, array $cfg, $token_url, $client_id, $client_secret, $redirect_uri, $code, $code_verifier, $token_auth ) {
		$cid_key   = isset( $cfg['client_id_param'] ) ? (string) $cfg['client_id_param'] : 'client_id';
		$use_pkce  = ! isset( $cfg['pkce'] ) || $cfg['pkce'];
		$body      = [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $redirect_uri,
		];
		if ( $use_pkce && $code_verifier !== '' ) {
			$body['code_verifier'] = $code_verifier;
		}
		$body[ $cid_key ] = $client_id;
		if ( $token_auth === 'basic' ) {
			$headers = [
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
			];
			return [
				'timeout' => 30,
				'headers' => $headers,
				'body'    => $body,
			];
		}
		$body['client_secret'] = $client_secret;
		return [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
		];
	}

	/**
	 * Reddit probe: no PKCE body field.
	 *
	 * @param string               $slug          Provider slug.
	 * @param array<string, mixed> $cfg           Config.
	 * @param string               $access_token  Bearer token.
	 * @param string               $client_id     Client ID (Twitch header).
	 * @param array<string, mixed> $opts          Options.
	 * @return array<string, mixed>|null id, email, name.
	 */
	private static function fetch_profile( $slug, array $cfg, $access_token, $client_id, array $opts ) {
		$strategy = isset( $cfg['profile_strategy'] ) ? (string) $cfg['profile_strategy'] : '';
		switch ( $strategy ) {
			case 'tiktok':
				return self::profile_tiktok( $access_token );
			case 'twitter':
				return self::profile_twitter( $access_token );
			case 'snapchat':
				return self::profile_snapchat( $access_token );
			case 'linkedin_oidc':
				return self::profile_linkedin( $access_token );
			case 'pinterest':
				return self::profile_pinterest( $access_token );
			case 'reddit':
				return self::profile_reddit( $access_token );
			case 'twitch':
				return self::profile_twitch( $access_token, $client_id );
			case 'discord':
				return self::profile_discord( $access_token );
			case 'mastodon':
				$base = self::normalize_mastodon_base_from_opts( $opts );
				return $base !== '' ? self::profile_mastodon( $base, $access_token ) : null;
			default:
				return null;
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_tiktok( $access_token ) {
		$response = wp_remote_post(
			'https://open.tiktokapis.com/v2/user/info/',
			[
				'timeout' => 30,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
				],
				'body'    => wp_json_encode(
					[
						'fields' => [ 'open_id', 'union_id', 'display_name', 'avatar_url' ],
					]
				),
			]
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['data']['user'] ) || ! is_array( $data['data']['user'] ) ) {
			return null;
		}
		$u = $data['data']['user'];
		$id = isset( $u['open_id'] ) ? (string) $u['open_id'] : '';
		return [
			'id'    => $id,
			'email' => '',
			'name'  => isset( $u['display_name'] ) ? (string) $u['display_name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_twitter( $access_token ) {
		$url      = 'https://api.twitter.com/2/users/me?user.fields=profile_image_url,username,name';
		$response = wp_remote_get(
			$url,
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['data']['id'] ) ) {
			return null;
		}
		$d = $data['data'];
		return [
			'id'    => (string) $d['id'],
			'email' => '',
			'name'  => isset( $d['name'] ) ? (string) $d['name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_snapchat( $access_token ) {
		$response = wp_remote_get(
			'https://kit.snapchat.com/v1/userinfo',
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$id = '';
		if ( isset( $data['sub'] ) ) {
			$id = (string) $data['sub'];
		} elseif ( isset( $data['external_id'] ) ) {
			$id = (string) $data['external_id'];
		}
		return [
			'id'    => $id,
			'email' => isset( $data['email'] ) ? (string) $data['email'] : '',
			'name'  => isset( $data['name'] ) ? (string) $data['name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_linkedin( $access_token ) {
		$response = wp_remote_get(
			'https://api.linkedin.com/v2/userinfo',
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['sub'] ) ) {
			return null;
		}
		return [
			'id'    => (string) $data['sub'],
			'email' => isset( $data['email'] ) ? (string) $data['email'] : '',
			'name'  => isset( $data['name'] ) ? (string) $data['name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_pinterest( $access_token ) {
		$response = wp_remote_get(
			'https://api.pinterest.com/v5/user_account',
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return null;
		}
		return [
			'id'    => (string) $data['id'],
			'email' => isset( $data['email'] ) ? (string) $data['email'] : '',
			'name'  => isset( $data['username'] ) ? (string) $data['username'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_reddit( $access_token ) {
		$response = wp_remote_get(
			'https://oauth.reddit.com/api/v1/me',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'User-Agent'    => 'CreatorReactor/1.0 (WordPress)',
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return null;
		}
		return [
			'id'    => (string) $data['id'],
			'email' => '',
			'name'  => isset( $data['name'] ) ? (string) $data['name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_twitch( $access_token, $client_id ) {
		$response = wp_remote_get(
			'https://api.twitch.tv/helix/users',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Client-Id'     => $client_id,
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['data'][0] ) || ! is_array( $data['data'][0] ) ) {
			return null;
		}
		$u = $data['data'][0];
		return [
			'id'    => isset( $u['id'] ) ? (string) $u['id'] : '',
			'email' => isset( $u['email'] ) ? (string) $u['email'] : '',
			'name'  => isset( $u['display_name'] ) ? (string) $u['display_name'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_discord( $access_token ) {
		$response = wp_remote_get(
			'https://discord.com/api/users/@me',
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return null;
		}
		$email = isset( $data['email'] ) ? (string) $data['email'] : '';
		return [
			'id'    => (string) $data['id'],
			'email' => $email,
			'name'  => isset( $data['username'] ) ? (string) $data['username'] : '',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function profile_mastodon( $base, $access_token ) {
		$response = wp_remote_get(
			$base . '/api/v1/accounts/verify_credentials',
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return null;
		}
		return [
			'id'    => (string) $data['id'],
			'email' => '',
			'name'  => isset( $data['display_name'] ) ? (string) $data['display_name'] : ( isset( $data['username'] ) ? (string) $data['username'] : '' ),
		];
	}

	/**
	 * @param string $slug Provider slug.
	 * @param string $sub  Stable id.
	 * @return string
	 */
	public static function synthetic_email_for_oauth( $slug, $sub ) {
		$host = \wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			$host = 'invalid.invalid';
		}
		$local = 'oauth.' . sanitize_key( (string) $slug ) . '.' . preg_replace( '/[^a-z0-9._-]/i', '', (string) $sub );
		if ( $local === 'oauth..' ) {
			$local = 'oauth.user';
		}
		return $local . '@' . $host;
	}

	/**
	 * @param string $slug Provider slug.
	 * @param string $sub  Subject id.
	 * @return \WP_User|false
	 */
	private static function get_user_by_sub( $slug, $sub ) {
		$key = Social_OAuth_Registry::usermeta_sub_key( $slug );
		$users = get_users(
			[
				'meta_key'    => $key,
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
	 * @param string $email Verified or synthetic email.
	 * @param string $name  Display name.
	 * @param string $slug  Provider slug.
	 * @return int|\WP_Error
	 */
	private static function insert_wp_user_from_oauth( $email, $name, $slug ) {
		$email = sanitize_email( (string) $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email for profile.', 'wp-creatorreactor' ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'exists', __( 'A user with this email already exists.', 'wp-creatorreactor' ) );
		}
		$login_base = sanitize_user( current( explode( '@', $email, 2 ) ), true );
		if ( $login_base === '' ) {
			$login_base = sanitize_key( $slug ) . '_user';
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
