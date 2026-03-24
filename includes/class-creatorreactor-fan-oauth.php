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

	/**
	 * Prevent CDNs / full-page cache from caching 302 responses (breaks Fanvue redirect chain).
	 */
	private static function send_fan_oauth_no_store_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
	}

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		// Run late so other plugins do not reintroduce a cookie-nonce error after we clear it.
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_without_cookie_nonce' ], 999 );
	}

	public static function allow_without_cookie_nonce( $result ) {
		$is_nonce_error = is_wp_error( $result ) && $result->get_error_code() === 'rest_cookie_invalid_nonce';
		if ( $is_nonce_error && self::is_fan_oauth_rest_request() ) {
			return true;
		}
		return $result;
	}

	private static function is_fan_oauth_rest_request() {
		$needles = [ 'fan-oauth-start', 'fan-oauth-callback' ];

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
		// Cookie nonce is validated before the matched route is always available; mirror
		// {@see CreatorReactor_OAuth::is_oauth_rest_request()} fallbacks (proxies, rewrites, plain permalinks).
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
	 * Redirect URI registered in the Fanvue app (add this URL if you use [fanvue_login_button]).
	 */
	public static function get_callback_redirect_uri() {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, self::REST_ROUTE_CALLBACK )
		);
	}

	/**
	 * Find a WordPress user previously linked to this Fanvue account (UUID in user meta).
	 *
	 * @param string $uuid Fanvue user id from profile.
	 * @return \WP_User|false
	 */
	private static function get_user_by_fanvue_uuid( $uuid ) {
		$uuid = is_string( $uuid ) ? sanitize_text_field( $uuid ) : '';
		if ( $uuid === '' ) {
			return false;
		}
		$users = get_users(
			[
				'meta_key'    => Entitlements::USERMETA_CREATORREACTOR_UUID,
				'meta_value'  => $uuid,
				'number'      => 2,
				'count_total' => false,
			]
		);
		if ( ! is_array( $users ) || $users === [] ) {
			return false;
		}
		if ( count( $users ) > 1 ) {
			Admin_Settings::log_connection( 'error', 'Fan OAuth: multiple WP users share the same Fanvue UUID; using the first match.' );
		}
		$u = $users[0];
		return ( $u instanceof \WP_User ) ? $u : false;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_start( $request ) {
		self::send_fan_oauth_no_store_headers();
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
		$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );

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
		self::send_fan_oauth_no_store_headers();
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
			$ecode = sanitize_key( $error );
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
			$edesc = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'error_description' );
			$edesc = is_string( $edesc ) ? trim( wp_strip_all_tags( $edesc ) ) : '';
			$log   = 'Fan OAuth: Fanvue authorization error: ' . $ecode;
			if ( $edesc !== '' ) {
				$log .= ' — ' . $edesc;
			}
			Admin_Settings::log_connection( 'error', $log );
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', $notice, $redirect_to ) );
			exit;
		}

		$code = CreatorReactor_OAuth::get_oauth_callback_query_param( $request, 'code' );
		if ( $code === '' || $state === '' ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'missing', $redirect_to ) );
			exit;
		}

		CreatorReactor_OAuth::delete_pkce_payload( $state );

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

		$has_email = $identity['email'] !== '' && is_email( $identity['email'] );
		$has_uuid  = $identity['uuid'] !== '';

		$user = false;
		if ( $has_email ) {
			$user = get_user_by( 'email', $identity['email'] );
		}
		if ( ! $user && $has_uuid ) {
			$user = self::get_user_by_fanvue_uuid( $identity['uuid'] );
		}
		if ( ! $user ) {
			$pending = Onboarding::store_pending_fanvue_registration( $identity, $redirect_to, $tokens, $profile );
			if ( $pending === '' ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
				exit;
			}
			Onboarding::set_fan_pending_cookie( $pending );
			$ob_url = Onboarding::get_onboarding_url_with_pending( $pending, $redirect_to );
			$ob_log = 'Fan OAuth: new fan — pending registration stored; redirecting to onboarding (combine Fanvue data with form, then create WP user).';
			if ( ! $has_email ) {
				$ob_log .= ' Fanvue profile had no email.';
			}
			if ( ! $has_uuid ) {
				$ob_log .= ' Fanvue profile had no user id.';
			}
			Admin_Settings::log_connection( 'info', $ob_log );
			wp_safe_redirect( $ob_url );
			exit;
		}

		if ( ! $user ) {
			wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
			exit;
		}

		if ( $identity['uuid'] !== '' ) {
			update_user_meta( $user->ID, Entitlements::USERMETA_CREATORREACTOR_UUID, $identity['uuid'] );
		}
		update_user_meta( $user->ID, Onboarding::META_FANVUE_OAUTH_LINKED, '1' );

		if ( is_array( $tokens ) ) {
			CreatorReactor_OAuth::save_fan_oauth_tokens_to_user( $user->ID, $tokens );
		}
		if ( is_array( $profile ) ) {
			$pj = wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( is_string( $pj ) && $pj !== '' && strlen( $pj ) <= 100000 ) {
				update_user_meta( $user->ID, Onboarding::META_FANVUE_PROFILE_SNAPSHOT, $pj );
			}
		}

		$display_for_sync = $identity['display'] !== '' ? $identity['display'] : $user->display_name;
		$sync_email       = $has_email ? $identity['email'] : $user->user_email;
		CreatorReactor_Client::sync_entitlement_for_fan_after_login(
			$identity['uuid'],
			$user->ID,
			is_string( $sync_email ) ? $sync_email : '',
			is_string( $display_for_sync ) ? $display_for_sync : ''
		);
		self::sync_entitlement_from_oauth_profile( $user->ID, $identity, $profile );

		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( Onboarding::get_post_oauth_redirect( $user->ID, $redirect_to ) );
		exit;
	}

	/**
	 * Upsert entitlement row from OAuth profile role/tier when available.
	 *
	 * @param int                  $wp_user_id WordPress user ID.
	 * @param array<string, mixed> $identity   Normalized identity from profile.
	 * @param array<string, mixed>|null $profile Raw OAuth profile payload.
	 * @return bool True if an entitlement row was updated.
	 */
	public static function sync_entitlement_from_oauth_profile( $wp_user_id, $identity, $profile ) {
		$wp_user_id = (int) $wp_user_id;
		if ( $wp_user_id <= 0 || ! is_array( $identity ) || ! is_array( $profile ) ) {
			return false;
		}

		$data = $profile;
		if ( isset( $profile['data'] ) && is_array( $profile['data'] ) ) {
			$data = $profile['data'];
		}

		$role_raw = '';
		if ( isset( $data['role'] ) && is_scalar( $data['role'] ) && ! is_bool( $data['role'] ) ) {
			$role_raw = sanitize_key( (string) $data['role'] );
		}

		$tier_raw  = isset( $data['tier'] ) ? $data['tier'] : null;
		$tier_norm = CreatorReactor_Client::normalize_tier( $tier_raw );

		$is_follower_role = in_array( $role_raw, [ 'follower', 'fan' ], true );
		$is_subscriber_role = in_array( $role_raw, [ 'subscriber', 'subscribed' ], true );

		if ( $is_follower_role ) {
			$stored_tier = Entitlements::tier_stored_for_follower( Entitlements::PRODUCT_FANVUE );
		} elseif ( $tier_norm !== null && $tier_norm !== '' ) {
			$stored_tier = Entitlements::tier_stored_for_subscriber( Entitlements::PRODUCT_FANVUE, $tier_norm );
		} elseif ( $is_subscriber_role ) {
			$stored_tier = Entitlements::tier_stored_for_subscriber( Entitlements::PRODUCT_FANVUE, null );
		} else {
			return false;
		}

		$uuid = isset( $identity['uuid'] ) && is_string( $identity['uuid'] ) ? sanitize_text_field( $identity['uuid'] ) : '';
		if ( $uuid === '' ) {
			$stored_uuid = get_user_meta( $wp_user_id, Entitlements::USERMETA_CREATORREACTOR_UUID, true );
			$uuid        = is_string( $stored_uuid ) ? sanitize_text_field( $stored_uuid ) : '';
		}
		if ( $uuid === '' ) {
			return false;
		}

		$email = isset( $identity['email'] ) && is_string( $identity['email'] ) ? sanitize_email( $identity['email'] ) : '';
		if ( ! is_email( $email ) ) {
			$user = get_user_by( 'id', $wp_user_id );
			$email = ( $user instanceof \WP_User ) ? sanitize_email( (string) $user->user_email ) : '';
		}
		if ( ! is_email( $email ) ) {
			return false;
		}

		$display_name = isset( $identity['display'] ) && is_string( $identity['display'] ) ? sanitize_text_field( $identity['display'] ) : '';
		if ( $display_name === '' ) {
			$from_profile = CreatorReactor_Client::item_display_name( $data );
			$display_name = is_string( $from_profile ) ? sanitize_text_field( $from_profile ) : '';
		}

		$opts       = Admin_Settings::get_options();
		$ttl        = (int) ( $opts['entitlement_cache_ttl_seconds'] ?? 900 );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + max( 60, $ttl ) );
		$snapshot   = CreatorReactor_Client::fanvue_list_item_sync_snapshot_json( $data );

		Entitlements::upsert_by_creatorreactor_uuid(
			$uuid,
			Entitlements::STATUS_ACTIVE,
			$expires_at,
			$wp_user_id,
			$email,
			$stored_tier,
			$display_name !== '' ? $display_name : null,
			Entitlements::PRODUCT_FANVUE,
			$snapshot
		);
		update_user_meta( $wp_user_id, Entitlements::USERMETA_CREATORREACTOR_UUID, $uuid );

		$existing = get_option( Admin_Settings::OPTION_TIERS, [] );
		$merged   = is_array( $existing ) ? $existing : [];
		$merged[ $stored_tier ] = true;
		update_option( Admin_Settings::OPTION_TIERS, array_keys( $merged ) );

		$def = CreatorReactor_Client::tier_definition_from_item( $tier_raw );
		if ( $def !== null ) {
			$subs_tiers = get_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, [] );
			$by_id      = [];
			if ( is_array( $subs_tiers ) ) {
				foreach ( $subs_tiers as $row ) {
					if ( is_array( $row ) && ! empty( $row['id'] ) ) {
						$by_id[ (string) $row['id'] ] = $row;
					}
				}
			}
			$by_id[ $def['id'] ] = $def;
			update_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, array_values( $by_id ) );
		}

		return true;
	}

	/**
	 * @param array<string, mixed>|null $profile API JSON.
	 * @return array{email: string, uuid: string, display: string}
	 */
	public static function identity_from_profile( $profile ) {
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
			if ( ! isset( $d[ $k ] ) || is_bool( $d[ $k ] ) ) {
				continue;
			}
			if ( is_scalar( $d[ $k ] ) ) {
				$s = trim( (string) $d[ $k ] );
				if ( $s !== '' ) {
					$out['uuid'] = sanitize_text_field( $s );
					break;
				}
			}
		}
		$display = CreatorReactor_Client::item_display_name( $d );
		if ( is_string( $display ) && $display !== '' ) {
			$out['display'] = sanitize_text_field( $display );
		}
		if ( $out['display'] === '' ) {
			foreach ( [ 'username', 'userName', 'name', 'fullName' ] as $k ) {
				if ( ! empty( $d[ $k ] ) && is_string( $d[ $k ] ) ) {
					$out['display'] = sanitize_text_field( trim( $d[ $k ] ) );
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Create a new WordPress user from Fanvue profile identity (subscriber role, random password).
	 *
	 * @param array{email: string, uuid?: string, display?: string} $identity Normalized identity.
	 * @return int|\WP_Error User ID or error.
	 */
	public static function insert_wp_user_from_fanvue_identity( array $identity ) {
		$email = isset( $identity['email'] ) ? sanitize_email( (string) $identity['email'] ) : '';
		$uuid  = isset( $identity['uuid'] ) && is_scalar( $identity['uuid'] ) && ! is_bool( $identity['uuid'] )
			? sanitize_text_field( trim( (string) $identity['uuid'] ) )
			: '';
		if ( $email === '' || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email from Fanvue profile.', 'creatorreactor' ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'exists', __( 'A user with this email already exists.', 'creatorreactor' ) );
		}
		$display = isset( $identity['display'] ) && is_string( $identity['display'] ) ? $identity['display'] : '';
		$login_base = sanitize_user( current( explode( '@', $email, 2 ) ), true );
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
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $display !== '' ? $display : $login,
				'role'         => get_option( 'default_role', 'subscriber' ),
			]
		);
		if ( ! is_wp_error( $uid ) && $uuid !== '' ) {
			update_user_meta( (int) $uid, Entitlements::USERMETA_CREATORREACTOR_UUID, $uuid );
		}
		return $uid;
	}
}
