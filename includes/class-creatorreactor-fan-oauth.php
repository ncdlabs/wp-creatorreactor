<?php
/**
 * Front-end Fanvue OAuth: login / link fan accounts without overwriting site (creator) tokens.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fan_OAuth {

	const REST_ROUTE_START    = '/fan-oauth-start';
	const REST_ROUTE_CALLBACK = '/fan-oauth-callback';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_without_cookie_nonce' ], 99 );
	}

	public static function allow_without_cookie_nonce( $result ) {
		$is_nonce_error = is_wp_error( $result ) && $result->get_error_code() === 'rest_cookie_invalid_nonce';
		if ( $is_nonce_error && self::is_fan_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_fan_oauth_rest_request() {
		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server && method_exists( $server, 'get_request' ) ) {
				$request = $server->get_request();
				if ( $request instanceof \WP_REST_Request ) {
					$route = $request->get_route();
					if ( is_string( $route ) && strpos( $route, 'fan-oauth' ) !== false ) {
						return true;
					}
				}
			}
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( strpos( $raw, 'fan-oauth-start' ) !== false || strpos( $raw, 'fan-oauth-callback' ) !== false ) {
				return true;
			}
		}
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) {
			$rr = wp_unslash( $_GET['rest_route'] );
			if ( strpos( $rr, 'fan-oauth' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public static function register_routes() {
		$start = [
			'methods'             => [ 'GET', 'HEAD' ],
			'callback'            => [ __CLASS__, 'rest_start' ],
			'permission_callback' => '__return_true',
		];
		$callback_args = [
			'methods'             => [ 'GET', 'POST', 'HEAD' ],
			'callback'            => [ __CLASS__, 'rest_callback' ],
			'permission_callback' => '__return_true',
		];
		$ns = CreatorReactor_OAuth::REST_NAMESPACE;
		register_rest_route( $ns, self::REST_ROUTE_START, $start );
		register_rest_route( $ns, self::REST_ROUTE_START . '/', $start );
		register_rest_route( $ns, self::REST_ROUTE_CALLBACK, $callback_args );
		register_rest_route( $ns, self::REST_ROUTE_CALLBACK . '/', $callback_args );
	}

	/**
	 * Redirect URI registered in the Fanvue app (add this URL if you use [fanvue_oauth]).
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
		$method = strtoupper( (string) $request->get_method() );
		$home   = home_url( '/' );

		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}

		$nonce = $request->get_param( '_wpnonce' );
		$nonce = is_string( $nonce ) ? $nonce : '';
		if ( ! wp_verify_nonce( $nonce, 'creatorreactor_fan_oauth' ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'nonce', $home ) );
			exit;
		}

		$redirect_to = $request->get_param( 'redirect_to' );
		$redirect_to = is_string( $redirect_to ) ? rawurldecode( $redirect_to ) : '';
		$redirect_to = $redirect_to !== '' ? wp_validate_redirect( $redirect_to, $home ) : $home;

		if ( Admin_Settings::is_broker_mode() ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'agency', $redirect_to ) );
			exit;
		}

		$fan_callback = self::get_callback_redirect_uri();
		$auth_url     = CreatorReactor_OAuth::get_authorization_url(
			$fan_callback,
			null,
			[
				'redirect_to' => $redirect_to,
				'fan_oauth'   => true,
			]
		);

		if ( ! is_string( $auth_url ) || $auth_url === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'config', $redirect_to ) );
			exit;
		}

		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_callback( $request ) {
		$home = home_url( '/' );

		$method = strtoupper( (string) $request->get_method() );
		if ( $method === 'HEAD' ) {
			wp_safe_redirect( $home );
			exit;
		}

		$state = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'state' );
		$pkce  = ( $state !== '' ) ? get_transient( CreatorReactor_OAuth::TRANSIENT_PKCE_PREFIX . $state ) : false;
		$redirect_to = $home;
		if ( is_array( $pkce ) && ! empty( $pkce['redirect_to'] ) && is_string( $pkce['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( $pkce['redirect_to'], $home );
		}

		$error = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'error' );
		if ( $error !== '' ) {
			if ( $state !== '' ) {
				delete_transient( CreatorReactor_OAuth::TRANSIENT_PKCE_PREFIX . $state );
			}
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'denied', $redirect_to ) );
			exit;
		}

		$code = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'code' );
		if ( $code === '' || $state === '' ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		delete_transient( CreatorReactor_OAuth::TRANSIENT_PKCE_PREFIX . $state );

		if ( ! is_array( $pkce ) || empty( $pkce['fan_oauth'] ) || empty( $pkce['code_verifier'] ) || ! is_string( $pkce['code_verifier'] ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'state', $redirect_to ) );
			exit;
		}

		$redirect_uri = isset( $pkce['redirect_uri'] ) && is_string( $pkce['redirect_uri'] ) ? $pkce['redirect_uri'] : self::get_callback_redirect_uri();
		$opts         = Admin_Settings::get_options();
		$tokens       = CreatorReactor_OAuth::exchange_code_for_tokens( $code, $pkce['code_verifier'], $redirect_uri, $opts );

		if ( is_wp_error( $tokens ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'token', $redirect_to ) );
			exit;
		}

		$access = isset( $tokens['access_token'] ) ? $tokens['access_token'] : '';
		$profile = CreatorReactor_Client::fetch_profile_with_access_token( $access );
		$identity = self::identity_from_profile( $profile );

		if ( $identity['email'] === '' || ! is_email( $identity['email'] ) ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'profile', $redirect_to ) );
			exit;
		}

		$user = get_user_by( 'email', $identity['email'] );
		if ( ! $user ) {
			if ( ! get_option( 'users_can_register' ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'closed', $redirect_to ) );
				exit;
			}
			$login_base = sanitize_user( current( explode( '@', $identity['email'], 2 ) ), true );
			if ( $login_base === '' ) {
				$login_base = 'fanvue_user';
			}
			$login = $login_base;
			$n     = 0;
			while ( username_exists( $login ) ) {
				++$n;
				$login = $login_base . $n;
			}
			$uid = wp_insert_user(
				[
					'user_login'   => $login,
					'user_email'   => $identity['email'],
					'user_pass'    => wp_generate_password( 32, true, true ),
					'display_name' => $identity['display'] !== '' ? $identity['display'] : $login,
					'role'         => get_option( 'default_role', 'subscriber' ),
				]
			);
			if ( is_wp_error( $uid ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
				exit;
			}
			$user = get_user_by( 'id', $uid );
		}

		if ( ! $user ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
			exit;
		}

		if ( $identity['uuid'] !== '' ) {
			update_user_meta( $user->ID, Entitlements::USERMETA_CREATORREACTOR_UUID, $identity['uuid'] );
		}

		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * @param array<string, mixed>|null $profile API JSON.
	 * @return array{email: string, uuid: string, display: string}
	 */
	private static function identity_from_profile( $profile ) {
		$out = [
			'email'   => '',
			'uuid'    => '',
			'display' => '',
		];
		if ( ! is_array( $profile ) ) {
			return $out;
		}
		$d = $profile;
		if ( isset( $profile['data'] ) && is_array( $profile['data'] ) ) {
			$d = $profile['data'];
		}
		foreach ( [ 'email', 'Email' ] as $k ) {
			if ( ! empty( $d[ $k ] ) && is_string( $d[ $k ] ) ) {
				$out['email'] = sanitize_email( $d[ $k ] );
				break;
			}
		}
		foreach ( [ 'id', 'uuid', 'userId' ] as $k ) {
			if ( isset( $d[ $k ] ) && is_string( $d[ $k ] ) && $d[ $k ] !== '' ) {
				$out['uuid'] = sanitize_text_field( $d[ $k ] );
				break;
			}
		}
		$display = CreatorReactor_Client::item_display_name( $d );
		if ( is_string( $display ) && $display !== '' ) {
			$out['display'] = sanitize_text_field( $display );
		}
		return $out;
	}
}
