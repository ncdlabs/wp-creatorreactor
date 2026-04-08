<?php
/**
 * Bluesky (atproto) OAuth: client metadata URL, PAR, PKCE, DPoP, token exchange.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public OAuth client (token_endpoint_auth_method: none) against bsky.social by default.
 */
class Bluesky_OAuth {

	const REST_ROUTE_CLIENT_METADATA = '/bluesky-oauth-client-metadata';
	const REST_ROUTE_START           = '/bluesky-oauth-start';
	const REST_ROUTE_CALLBACK        = '/bluesky-oauth-callback';

	/** Default authorization server (entryway). */
	const DEFAULT_ISSUER = 'https://bsky.social';

	const SCOPES = 'atproto transition:generic';

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
		if ( $is_nonce_error && self::is_bluesky_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_bluesky_oauth_rest_request() {
		$needles = [ 'bluesky-oauth-start', 'bluesky-oauth-callback', 'bluesky-oauth-client-metadata' ];
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
		return false;
	}

	/**
	 * Public client_id URL (must match JSON client_id field).
	 */
	public static function get_client_id_url() {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri(
				CreatorReactor_OAuth::REST_NAMESPACE,
				self::REST_ROUTE_CLIENT_METADATA
			)
		);
	}

	/**
	 * OAuth redirect/callback URI registered in client metadata.
	 */
	public static function get_callback_redirect_uri() {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri(
				CreatorReactor_OAuth::REST_NAMESPACE,
				self::REST_ROUTE_CALLBACK
			)
		);
	}

	public static function register_routes() {
		$ns = CreatorReactor_OAuth::REST_NAMESPACE;
		register_rest_route(
			$ns,
			self::REST_ROUTE_CLIENT_METADATA,
			[
				'methods'             => [ 'GET', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_client_metadata' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			$ns,
			self::REST_ROUTE_CLIENT_METADATA . '/',
			[
				'methods'             => [ 'GET', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_client_metadata' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			$ns,
			self::REST_ROUTE_START,
			[
				'methods'             => [ 'GET', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_start' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			$ns,
			self::REST_ROUTE_START . '/',
			[
				'methods'             => [ 'GET', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_start' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			$ns,
			self::REST_ROUTE_CALLBACK,
			[
				'methods'             => [ 'GET', 'POST', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_callback' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			$ns,
			self::REST_ROUTE_CALLBACK . '/',
			[
				'methods'             => [ 'GET', 'POST', 'HEAD' ],
				'callback'            => [ __CLASS__, 'rest_callback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_client_metadata( $request ) {
		$method = strtoupper( (string) $request->get_method() );
		if ( $method === 'HEAD' ) {
			return new \WP_REST_Response( null, 200, [ 'Content-Type' => 'application/json' ] );
		}
		$client_id = self::get_client_id_url();
		$redirect    = self::get_callback_redirect_uri();
		$body        = [
			'client_id'                 => $client_id,
			'application_type'          => 'web',
			'client_name'               => 'CreatorReactor',
			'dpop_bound_access_tokens'  => true,
			'grant_types'               => [ 'authorization_code', 'refresh_token' ],
			'redirect_uris'             => [ $redirect ],
			'response_types'            => [ 'code' ],
			'scope'                     => self::SCOPES,
			'token_endpoint_auth_method' => 'none',
		];
		return new \WP_REST_Response( $body, 200, [ 'Content-Type' => 'application/json; charset=' . get_bloginfo( 'charset' ) ] );
	}

	private static function send_no_store_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_start( $request ) {
		self::send_no_store_headers();
		$method = strtoupper( (string) $request->get_method() );
		$home   = home_url( '/' );
		$qarg   = Social_OAuth_Registry::query_arg( 'bluesky' );
		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}
		$nonce = $request->get_param( '_wpnonce' );
		$nonce = is_string( $nonce ) ? $nonce : '';
		if ( ! wp_verify_nonce( $nonce, Social_OAuth_Registry::nonce_action( 'bluesky' ) ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'nonce', $home ) );
			exit;
		}
		$redirect_to = $request->get_param( 'redirect_to' );
		$redirect_to = is_string( $redirect_to ) ? rawurldecode( $redirect_to ) : '';
		$redirect_to = $redirect_to !== '' ? wp_validate_redirect( $redirect_to, $home ) : $home;
		$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );
		if ( Admin_Settings::is_broker_mode() ) {
			wp_safe_redirect( add_query_arg( $qarg, 'agency', $redirect_to ) );
			exit;
		}
		if ( ! Admin_Settings::is_social_oauth_provider_configured( 'bluesky' ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'config', $redirect_to ) );
			exit;
		}
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			Admin_Settings::log_connection( 'error', 'Bluesky OAuth: OpenSSL extension required.' );
			wp_safe_redirect( add_query_arg( $qarg, 'token', $redirect_to ) );
			exit;
		}
		$issuer = self::DEFAULT_ISSUER;
		$as     = self::fetch_authorization_server_metadata( $issuer );
		if ( ! is_array( $as ) ) {
			Admin_Settings::log_connection( 'error', 'Bluesky OAuth: could not load authorization server metadata.' );
			wp_safe_redirect( add_query_arg( $qarg, 'oauth_error', $redirect_to ) );
			exit;
		}
		$par_endpoint = isset( $as['pushed_authorization_request_endpoint'] ) ? (string) $as['pushed_authorization_request_endpoint'] : '';
		$auth_ep      = isset( $as['authorization_endpoint'] ) ? (string) $as['authorization_endpoint'] : '';
		$token_ep     = isset( $as['token_endpoint'] ) ? (string) $as['token_endpoint'] : '';
		$as_issuer    = isset( $as['issuer'] ) ? (string) $as['issuer'] : $issuer;
		if ( $par_endpoint === '' || $auth_ep === '' || $token_ep === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'oauth_error', $redirect_to ) );
			exit;
		}
		$dpop = self::generate_ec_keypair_pem();
		if ( ! is_string( $dpop ) || $dpop === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'oauth_error', $redirect_to ) );
			exit;
		}
		$code_verifier  = CreatorReactor_OAuth::generate_code_verifier();
		$code_challenge = CreatorReactor_OAuth::generate_code_challenge( $code_verifier );
		$state          = wp_generate_password( 32, false );
		$client_id      = self::get_client_id_url();
		$redirect_uri   = self::get_callback_redirect_uri();

		$par_body = [
			'client_id'             => $client_id,
			'response_type'         => 'code',
			'redirect_uri'          => $redirect_uri,
			'scope'                 => self::SCOPES,
			'state'                 => $state,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => 'S256',
		];
		$par_res = self::post_par_with_dpop( $par_endpoint, $par_body, $dpop );
		if ( ! is_array( $par_res ) || empty( $par_res['request_uri'] ) ) {
			$msg = isset( $par_res['_error'] ) ? (string) $par_res['_error'] : 'PAR failed';
			Admin_Settings::log_connection( 'error', 'Bluesky OAuth: PAR failed: ' . $msg );
			wp_safe_redirect( add_query_arg( $qarg, 'oauth_error', $redirect_to ) );
			exit;
		}
		$request_uri = (string) $par_res['request_uri'];
		$dpop_nonce  = isset( $par_res['_dpop_nonce'] ) ? (string) $par_res['_dpop_nonce'] : '';

		CreatorReactor_OAuth::store_pkce_payload(
			$state,
			[
				'social_oauth'       => true,
				'bluesky_oauth'      => true,
				'redirect_to'        => $redirect_to,
				'redirect_uri'       => $redirect_uri,
				'code_verifier'      => $code_verifier,
				'issuer'             => $as_issuer,
				'token_endpoint'     => $token_ep,
				'dpop_private_pem'   => $dpop,
				'dpop_nonce_token'   => $dpop_nonce,
				'provider_slug'      => 'bluesky',
			]
		);
		$auth_redirect = $auth_ep . ( strpos( $auth_ep, '?' ) !== false ? '&' : '?' ) . http_build_query(
			[
				'client_id'    => $client_id,
				'request_uri'  => $request_uri,
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);
		wp_redirect( $auth_redirect );
		exit;
	}

	/**
	 * @param string $issuer Issuer origin URL.
	 * @return array<string, mixed>|null
	 */
	private static function fetch_authorization_server_metadata( $issuer ) {
		$issuer = trailingslashit( untrailingslashit( (string) $issuer ) );
		$url    = $issuer . '.well-known/oauth-authorization-server';
		$res    = wp_remote_get(
			$url,
			[
				'timeout' => 20,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$data = json_decode( is_string( $body ) ? $body : '', true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @return string PEM or ''.
	 */
	private static function generate_ec_keypair_pem() {
		$key = openssl_pkey_new(
			[
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			]
		);
		if ( ! $key ) {
			return '';
		}
		$out = '';
		if ( ! openssl_pkey_export( $key, $out ) ) {
			return '';
		}
		return is_string( $out ) ? $out : '';
	}

	/**
	 * @param mixed $headers Headers from {@see wp_remote_retrieve_headers()}.
	 * @return string
	 */
	private static function get_header_dpop_nonce( $headers ) {
		if ( is_array( $headers ) ) {
			foreach ( [ 'dpop-nonce', 'DPoP-Nonce' ] as $k ) {
				if ( isset( $headers[ $k ] ) ) {
					return (string) $headers[ $k ];
				}
			}
		}
		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			foreach ( [ 'dpop-nonce', 'DPoP-Nonce' ] as $k ) {
				try {
					$v = $headers->offsetGet( $k );
					if ( $v !== null && $v !== '' ) {
						return (string) $v;
					}
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				}
			}
		}
		return '';
	}

	/**
	 * @param string               $par_endpoint URL.
	 * @param array<string,string> $body         Form fields.
	 * @param string               $dpop_pem     EC private PEM.
	 * @return array<string, mixed>|null Result with request_uri or _error.
	 */
	private static function post_par_with_dpop( $par_endpoint, array $body, $dpop_pem ) {
		$nonce = '';
		for ( $attempt = 0; $attempt < 4; $attempt++ ) {
			$proof = self::build_dpop_proof( 'POST', $par_endpoint, $nonce, $dpop_pem );
			if ( $proof === '' ) {
				return [ '_error' => 'dpop_sign' ];
			}
			$res = wp_remote_post(
				$par_endpoint,
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type' => 'application/x-www-form-urlencoded',
						'Accept'       => 'application/json',
						'DPoP'         => $proof,
					],
					'body'    => $body,
				]
			);
			if ( is_wp_error( $res ) ) {
				return [ '_error' => $res->get_error_message() ];
			}
			$code      = wp_remote_retrieve_response_code( $res );
			$hdrs      = wp_remote_retrieve_headers( $res );
			$new_nonce = self::get_header_dpop_nonce( $hdrs );
			$jb        = wp_remote_retrieve_body( $res );
			$js = json_decode( is_string( $jb ) ? $jb : '', true );
			if ( $code === 401 && is_array( $js ) && isset( $js['error'] ) && (string) $js['error'] === 'use_dpop_nonce' ) {
				if ( $new_nonce !== '' ) {
					$nonce = $new_nonce;
				}
				continue;
			}
			if ( $code < 200 || $code >= 300 || ! is_array( $js ) ) {
				return [ '_error' => 'par_http_' . (string) $code ];
			}
			if ( ! empty( $js['request_uri'] ) ) {
				$js['_dpop_nonce'] = $new_nonce !== '' ? $new_nonce : $nonce;
				return $js;
			}
			return [ '_error' => 'par_bad_body' ];
		}
		return [ '_error' => 'par_nonce_retries' ];
	}

	/**
	 * @param string $htm   HTTP method.
	 * @param string $htu   Full request URL.
	 * @param string $nonce DPoP nonce (optional).
	 * @param string $pem   EC private PEM.
	 * @return string JWT or ''.
	 */
	private static function build_dpop_proof( $htm, $htu, $nonce, $pem ) {
		$jwk = self::ec_private_pem_to_public_jwk( $pem );
		if ( ! is_array( $jwk ) ) {
			return '';
		}
		$header = [
			'typ' => 'dpop+jwt',
			'alg' => 'ES256',
			'jwk' => $jwk,
		];
		$now    = time();
		$claims = [
			'jti' => wp_generate_password( 40, false, false ),
			'htm' => strtoupper( (string) $htm ),
			'htu' => (string) $htu,
			'iat' => $now,
		];
		if ( $nonce !== '' ) {
			$claims['nonce'] = $nonce;
		}
		return self::jwt_es256_sign( $header, $claims, $pem );
	}

	/**
	 * @param string $pem EC private PEM.
	 * @return array<string, mixed>|null JWK public fields.
	 */
	private static function ec_private_pem_to_public_jwk( $pem ) {
		$res = openssl_pkey_get_private( $pem );
		if ( ! $res ) {
			return null;
		}
		$d = openssl_pkey_get_details( $res );
		if ( ! is_array( $d ) || empty( $d['ec'] ) || ! is_array( $d['ec'] ) ) {
			return null;
		}
		$ec = $d['ec'];
		$x = isset( $ec['x'] ) ? $ec['x'] : '';
		$y = isset( $ec['y'] ) ? $ec['y'] : '';
		if ( is_string( $x ) && strlen( $x ) === 64 && ctype_xdigit( $x ) ) {
			$x = hex2bin( $x );
		}
		if ( is_string( $y ) && strlen( $y ) === 64 && ctype_xdigit( $y ) ) {
			$y = hex2bin( $y );
		}
		if ( ! is_string( $x ) || ! is_string( $y ) || strlen( $x ) !== 32 || strlen( $y ) !== 32 ) {
			return null;
		}
		return [
			'kty' => 'EC',
			'crv' => 'P-256',
			'x'   => self::base64url_encode( $x ),
			'y'   => self::base64url_encode( $y ),
		];
	}

	private static function base64url_encode( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * @param array<string, mixed> $header Header claims.
	 * @param array<string, mixed> $claims Payload claims.
	 * @param string               $pem    EC private PEM.
	 * @return string Signed JWT or ''.
	 */
	private static function jwt_es256_sign( array $header, array $claims, $pem ) {
		$h = self::base64url_encode( wp_json_encode( $header ) );
		$p = self::base64url_encode( wp_json_encode( $claims ) );
		$t = $h . '.' . $p;
		$key = openssl_pkey_get_private( $pem );
		if ( ! $key ) {
			return '';
		}
		$sig = '';
		if ( ! openssl_sign( $t, $sig, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}
		$raw = self::ecdsa_der_to_raw_p256( $sig );
		if ( $raw === '' ) {
			return '';
		}
		return $t . '.' . self::base64url_encode( $raw );
	}

	/**
	 * @param string $der ASN.1 ECDSA signature.
	 * @return string 64-byte raw r|s or ''.
	 */
	private static function ecdsa_der_to_raw_p256( $der ) {
		$len = strlen( $der );
		if ( $len < 8 ) {
			return '';
		}
		if ( ord( $der[0] ) !== 0x30 ) {
			return '';
		}
		$pos = 2;
		if ( $pos >= $len ) {
			return '';
		}
		if ( ord( $der[ $pos ] ) !== 0x02 ) {
			return '';
		}
		++$pos;
		$lr = ord( $der[ $pos ] );
		++$pos;
		$r = substr( $der, $pos, $lr );
		$pos += $lr;
		if ( $pos >= $len || ord( $der[ $pos ] ) !== 0x02 ) {
			return '';
		}
		++$pos;
		$ls = ord( $der[ $pos ] );
		++$pos;
		$s = substr( $der, $pos, $ls );
		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );
		$r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );
		return $r . $s;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_callback( $request ) {
		self::send_no_store_headers();
		$home  = home_url( '/' );
		$qarg  = Social_OAuth_Registry::query_arg( 'bluesky' );
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
			$ecode = sanitize_key( $error );
			$notice = 'oauth_error';
			if ( $ecode === 'access_denied' ) {
				$notice = 'denied';
			}
			wp_safe_redirect( add_query_arg( $qarg, $notice, $redirect_to ) );
			exit;
		}
		$code = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'code' );
		if ( $code === '' || $state === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'missing', $redirect_to ) );
			exit;
		}
		CreatorReactor_OAuth::delete_pkce_payload( $state );
		if ( ! is_array( $pkce ) || empty( $pkce['bluesky_oauth'] ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'state', $redirect_to ) );
			exit;
		}
		$verifier     = isset( $pkce['code_verifier'] ) && is_string( $pkce['code_verifier'] ) ? $pkce['code_verifier'] : '';
		$redirect_uri = isset( $pkce['redirect_uri'] ) && is_string( $pkce['redirect_uri'] ) ? $pkce['redirect_uri'] : self::get_callback_redirect_uri();
		$token_ep     = isset( $pkce['token_endpoint'] ) && is_string( $pkce['token_endpoint'] ) ? $pkce['token_endpoint'] : '';
		$dpop_pem     = isset( $pkce['dpop_private_pem'] ) && is_string( $pkce['dpop_private_pem'] ) ? $pkce['dpop_private_pem'] : '';
		$nonce_hint   = isset( $pkce['dpop_nonce_token'] ) && is_string( $pkce['dpop_nonce_token'] ) ? $pkce['dpop_nonce_token'] : '';
		if ( $verifier === '' || $token_ep === '' || $dpop_pem === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'state', $redirect_to ) );
			exit;
		}
		$client_id = self::get_client_id_url();
		$body      = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
			'client_id'     => $client_id,
			'code_verifier' => $verifier,
		];
		$tok = self::post_token_with_dpop( $token_ep, $body, $dpop_pem, $nonce_hint );
		if ( ! is_array( $tok ) || empty( $tok['access_token'] ) ) {
			wp_safe_redirect( add_query_arg( $qarg, 'token', $redirect_to ) );
			exit;
		}
		$sub = isset( $tok['sub'] ) && is_string( $tok['sub'] ) ? sanitize_text_field( $tok['sub'] ) : '';
		if ( $sub === '' ) {
			wp_safe_redirect( add_query_arg( $qarg, 'profile', $redirect_to ) );
			exit;
		}
		Social_OAuth::finalize_oauth_login(
			'bluesky',
			$redirect_to,
			[
				'id'    => $sub,
				'email' => '',
				'name'  => $sub,
			],
			$qarg
		);
	}

	/**
	 * @param string               $token_ep Token URL.
	 * @param array<string, string> $body     Form body.
	 * @param string               $dpop_pem PEM.
	 * @param string               $nonce    Initial nonce hint.
	 * @return array<string, mixed>|null
	 */
	private static function post_token_with_dpop( $token_ep, array $body, $dpop_pem, $nonce ) {
		$nonce = (string) $nonce;
		for ( $attempt = 0; $attempt < 6; $attempt++ ) {
			$proof = self::build_dpop_proof( 'POST', $token_ep, $nonce, $dpop_pem );
			if ( $proof === '' ) {
				return null;
			}
			$res = wp_remote_post(
				$token_ep,
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type' => 'application/x-www-form-urlencoded',
						'Accept'       => 'application/json',
						'DPoP'         => $proof,
					],
					'body'    => $body,
				]
			);
			if ( is_wp_error( $res ) ) {
				return null;
			}
			$code = wp_remote_retrieve_response_code( $res );
			$hdrs      = wp_remote_retrieve_headers( $res );
			$new_nonce = self::get_header_dpop_nonce( $hdrs );
			$jb        = wp_remote_retrieve_body( $res );
			$js        = json_decode( is_string( $jb ) ? $jb : '', true );
			if ( $code === 401 && is_array( $js ) && isset( $js['error'] ) && (string) $js['error'] === 'use_dpop_nonce' && $new_nonce !== '' ) {
				$nonce = $new_nonce;
				continue;
			}
			if ( $code >= 200 && $code < 300 && is_array( $js ) ) {
				return $js;
			}
			return null;
		}
		return null;
	}
}
