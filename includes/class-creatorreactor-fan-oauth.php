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
	const USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT = 'creatorreactor_fan_oauth_payload_synced_at';

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
		add_action( 'wp_login', [ __CLASS__, 'on_wp_login_sync_from_oauth_payload' ], 14, 2 );
		// Run late so other plugins do not reintroduce a cookie-nonce error after we clear it.
		add_filter( 'rest_authentication_errors', [ __CLASS__, 'allow_without_cookie_nonce' ], 999 );
	}

	/**
	 * Per-login Fan OAuth profile sync (one-time per request): refresh identity/role from OAuth payload.
	 *
	 * @param string         $user_login Login name (hook signature).
	 * @param \WP_User|false $user      User object.
	 */
	public static function on_wp_login_sync_from_oauth_payload( $user_login, $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return;
		}
		if ( ! apply_filters( 'creatorreactor_sync_fan_oauth_profile_on_login', true, $user ) ) {
			return;
		}

		static $done = [];
		$uid = (int) $user->ID;
		if ( $uid <= 0 || isset( $done[ $uid ] ) ) {
			return;
		}
		$done[ $uid ] = true;

		$is_fan_linked = get_user_meta( $uid, Onboarding::META_FANVUE_OAUTH_LINKED, true ) === '1';
		$fan_tokens    = CreatorReactor_OAuth::get_fan_oauth_tokens_for_user( $uid );
		if ( ! $is_fan_linked && empty( $fan_tokens ) ) {
			return;
		}

		$access_token = CreatorReactor_OAuth::get_fan_oauth_access_token_for_user( $uid );
		$profile      = null;
		$profile_src  = 'live';
		if ( is_string( $access_token ) && $access_token !== '' ) {
			$profile = CreatorReactor_Client::fetch_profile_with_access_token( $access_token );
		}
		if ( ! is_array( $profile ) ) {
			$profile     = self::get_stored_profile_snapshot_for_user( $uid );
			$profile_src = 'snapshot';
		}
		if ( ! is_array( $profile ) ) {
			Admin_Settings::log_connection( 'debug', sprintf( 'Fan OAuth login sync skipped for WP user %d: no usable OAuth profile payload.', $uid ) );
			self::sync_wp_role_from_active_fanvue_entitlements( $uid );
			return;
		}

		$identity = self::identity_from_profile( $profile );
		if ( isset( $identity['uuid'] ) && is_string( $identity['uuid'] ) && $identity['uuid'] !== '' ) {
			update_user_meta( $uid, Entitlements::USERMETA_CREATORREACTOR_UUID, sanitize_text_field( $identity['uuid'] ) );
		}

		$oauth_role = self::role_from_oauth_profile( $profile );
		$did_sync   = self::sync_entitlement_from_oauth_profile( $uid, $identity, $profile );
		update_user_meta( $uid, self::USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT, gmdate( 'c' ) );
		Admin_Settings::log_connection(
			'debug',
			sprintf(
				'Fan OAuth login sync complete for WP user %d: role=%s uuid=%s entitlement_sync=%s profile_source=%s',
				$uid,
				$oauth_role !== '' ? $oauth_role : 'unknown',
				self::format_uuid_for_log( isset( $identity['uuid'] ) ? (string) $identity['uuid'] : '' ),
				$did_sync ? 'yes' : 'no',
				$profile_src
			)
		);
		self::sync_wp_role_from_active_fanvue_entitlements( $uid );
	}

	/**
	 * Read/decode the stored OAuth profile snapshot for a user.
	 *
	 * @param int $wp_user_id WordPress user ID.
	 * @return array<string, mixed>|null
	 */
	private static function get_stored_profile_snapshot_for_user( $wp_user_id ) {
		$wp_user_id = (int) $wp_user_id;
		if ( $wp_user_id <= 0 ) {
			return null;
		}
		$snapshot = get_user_meta( $wp_user_id, Onboarding::META_FANVUE_PROFILE_SNAPSHOT, true );
		if ( ! is_string( $snapshot ) || $snapshot === '' ) {
			return null;
		}
		$decoded = json_decode( $snapshot, true );
		return is_array( $decoded ) ? $decoded : null;
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
	 * Redirect URI registered in the Fanvue app (add this URL if you use Fanvue login shortcodes).
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
		self::log_oauth_profile_payload_debug( $profile );
		$identity = self::identity_from_profile( $profile );

		$has_email = $identity['email'] !== '' && is_email( $identity['email'] );
		$has_uuid  = $identity['uuid'] !== '';
		$oauth_role = is_array( $profile ) ? self::role_from_oauth_profile( $profile ) : '';
		Admin_Settings::log_connection(
			'debug',
			sprintf(
				'Fan OAuth callback identity parsed: role=%s uuid=%s email_present=%s',
				$oauth_role !== '' ? $oauth_role : 'unknown',
				self::format_uuid_for_log( $identity['uuid'] ),
				$has_email ? 'yes' : 'no'
			)
		);

		$user = false;
		if ( $has_email ) {
			$user = get_user_by( 'email', $identity['email'] );
		}
		if ( ! $user && $has_uuid ) {
			$user = self::get_user_by_fanvue_uuid( $identity['uuid'] );
		}
		if ( ! $user ) {
			if ( ! $has_email ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
				exit;
			}
			$identity_for_insert = [
				'email'   => $identity['email'],
				'uuid'    => $identity['uuid'],
				'display' => $identity['display'],
			];
			$new_uid = self::insert_wp_user_from_fanvue_identity( $identity_for_insert );
			if ( is_wp_error( $new_uid ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
				exit;
			}
			$user = get_user_by( 'id', (int) $new_uid );
			if ( ! ( $user instanceof \WP_User ) ) {
				wp_safe_redirect( add_query_arg( 'creatorreactor_fanvue', 'user', $redirect_to ) );
				exit;
			}
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
		self::sync_wp_role_from_active_fanvue_entitlements( $user->ID );

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
		self::sync_wp_profile_fields_from_oauth_profile( $wp_user_id, $profile );

		$oauth_role = self::role_from_oauth_profile( $profile );
		if ( $oauth_role !== '' ) {
			self::apply_wp_role_from_oauth_profile( $wp_user_id, $oauth_role );
		}

		$data = $profile;
		if ( isset( $profile['data'] ) && is_array( $profile['data'] ) ) {
			$data = $profile['data'];
		}

		$role_raw = '';
		if ( isset( $data['role'] ) && is_scalar( $data['role'] ) && ! is_bool( $data['role'] ) ) {
			$role_raw = sanitize_key( (string) $data['role'] );
		} elseif ( isset( $data['user'] ) && is_array( $data['user'] ) && isset( $data['user']['role'] ) && is_scalar( $data['user']['role'] ) && ! is_bool( $data['user']['role'] ) ) {
			$role_raw = sanitize_key( (string) $data['user']['role'] );
		}

		$tier_raw  = CreatorReactor_Client::tier_raw_from_oauth_data( $data );
		$tier_norm = $tier_raw !== null ? CreatorReactor_Client::normalize_tier( $tier_raw ) : null;

		$is_follower_role = in_array( $role_raw, [ 'follower', 'fan' ], true );
		$is_subscriber_role = in_array( $role_raw, [ 'subscriber', 'subscribed' ], true );

		if ( $tier_norm !== null && $tier_norm !== '' ) {
			$stored_tier = Entitlements::tier_stored_for_subscriber( Entitlements::PRODUCT_FANVUE, $tier_norm );
		} elseif ( $is_follower_role ) {
			$stored_tier = Entitlements::tier_stored_for_follower( Entitlements::PRODUCT_FANVUE );
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
	 * Map Fanvue OAuth payload fields into WordPress profile fields/user meta.
	 *
	 * Mapping:
	 * - fanvue_id   => user meta `fanvue_id`
	 * - bio         => WP biographical info (`description`)
	 * - displayName => WP `display_name`
	 * - isCreator   => user meta `isFanvueCreator`
	 * - createdAt   => user meta `fanvueAccountCreatedAt`
	 * - updatedAt   => user meta `fanvueAccountUpdatedAt`
	 * - avatarUrl   => user meta `avatarUrl`
	 * - bannerUrl   => user meta `bannerUrl`
	 *
	 * @param int                  $wp_user_id WordPress user ID.
	 * @param array<string, mixed> $profile    Raw OAuth profile payload.
	 * @return void
	 */
	private static function sync_wp_profile_fields_from_oauth_profile( $wp_user_id, array $profile ) {
		$data = $profile;
		if ( isset( $profile['data'] ) && is_array( $profile['data'] ) ) {
			$data = $profile['data'];
		}
		$user_data = ( isset( $data['user'] ) && is_array( $data['user'] ) ) ? $data['user'] : [];

		$fanvue_id = self::first_scalar_field( $data, $user_data, [ 'fanvue_id', 'id', 'uuid', 'userId', 'user_id' ] );
		if ( $fanvue_id !== '' ) {
			update_user_meta( $wp_user_id, 'fanvue_id', sanitize_text_field( $fanvue_id ) );
		}

		$bio = self::first_scalar_field( $data, $user_data, [ 'bio' ] );
		if ( $bio !== '' ) {
			wp_update_user(
				[
					'ID'          => $wp_user_id,
					'description' => sanitize_textarea_field( $bio ),
				]
			);
		}

		$display = self::first_scalar_field( $data, $user_data, [ 'displayName' ] );
		if ( $display !== '' ) {
			wp_update_user(
				[
					'ID'           => $wp_user_id,
					'display_name' => sanitize_text_field( $display ),
				]
			);
		}

		$is_creator_raw = self::first_scalar_field( $data, $user_data, [ 'isCreator' ] );
		if ( $is_creator_raw !== '' ) {
			$is_creator_norm = in_array( strtolower( trim( $is_creator_raw ) ), [ '1', 'true', 'yes' ], true ) ? '1' : '0';
			update_user_meta( $wp_user_id, 'isFanvueCreator', $is_creator_norm );
		}

		$created_at = self::first_scalar_field( $data, $user_data, [ 'createdAt' ] );
		if ( $created_at !== '' ) {
			update_user_meta( $wp_user_id, 'fanvueAccountCreatedAt', sanitize_text_field( $created_at ) );
		}

		$updated_at = self::first_scalar_field( $data, $user_data, [ 'updatedAt' ] );
		if ( $updated_at !== '' ) {
			update_user_meta( $wp_user_id, 'fanvueAccountUpdatedAt', sanitize_text_field( $updated_at ) );
		}

		$avatar = self::first_scalar_field( $data, $user_data, [ 'avatarUrl' ] );
		if ( $avatar !== '' ) {
			update_user_meta( $wp_user_id, 'avatarUrl', esc_url_raw( $avatar ) );
		}

		$banner = self::first_scalar_field( $data, $user_data, [ 'bannerUrl' ] );
		if ( $banner !== '' ) {
			update_user_meta( $wp_user_id, 'bannerUrl', esc_url_raw( $banner ) );
		}
	}

	/**
	 * Get first non-null scalar value from profile/data/user payload keys.
	 *
	 * @param array<string, mixed> $data      Primary payload map.
	 * @param array<string, mixed> $user_data Nested user payload map.
	 * @param array<int, string>   $keys      Candidate keys in priority order.
	 * @return string
	 */
	private static function first_scalar_field( array $data, array $user_data, array $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) && $data[ $key ] !== null && is_scalar( $data[ $key ] ) ) {
				if ( is_bool( $data[ $key ] ) ) {
					return $data[ $key ] ? '1' : '0';
				}
				return trim( (string) $data[ $key ] );
			}
			if ( array_key_exists( $key, $user_data ) && $user_data[ $key ] !== null && is_scalar( $user_data[ $key ] ) ) {
				if ( is_bool( $user_data[ $key ] ) ) {
					return $user_data[ $key ] ? '1' : '0';
				}
				return trim( (string) $user_data[ $key ] );
			}
		}
		return '';
	}

	/**
	 * Decide which WordPress role should be applied from Fanvue OAuth profile data.
	 *
	 * @param array<string, mixed> $profile Raw OAuth profile payload.
	 * @return string Role slug or empty string when no mapping is available.
	 */
	private static function role_from_oauth_profile( array $profile ) {
		$data = $profile;
		if ( isset( $profile['data'] ) && is_array( $profile['data'] ) ) {
			$data = $profile['data'];
		}

		$role_raw = '';
		if ( isset( $data['role'] ) && is_scalar( $data['role'] ) && ! is_bool( $data['role'] ) ) {
			$role_raw = sanitize_key( (string) $data['role'] );
		} elseif ( isset( $data['user'] ) && is_array( $data['user'] ) && isset( $data['user']['role'] ) && is_scalar( $data['user']['role'] ) && ! is_bool( $data['user']['role'] ) ) {
			$role_raw = sanitize_key( (string) $data['user']['role'] );
		}

		$tier_raw  = CreatorReactor_Client::tier_raw_from_oauth_data( $data );
		$tier_norm = $tier_raw !== null ? CreatorReactor_Client::normalize_tier( $tier_raw ) : null;

		// Prefer explicit role in OAuth payload first.
		// Some payloads may include a subscription tier for followers; we should not treat that as subscriber.
		if ( in_array( $role_raw, [ 'follower', 'fan' ], true ) ) {
			return 'creatorreactor_follower';
		}

		if ( in_array( $role_raw, [ 'subscriber', 'subscribed' ], true ) ) {
			return 'creatorreactor_subscriber';
		}

		// Fallback: infer from tier when role is missing/unknown.
		if ( is_string( $tier_norm ) && $tier_norm !== '' ) {
			$mapped = Entitlements::mapped_fanvue_wp_role_from_stored_tier( (string) $tier_raw );
			if ( $mapped === 'creatorreactor_follower' ) {
				return 'creatorreactor_follower';
			}
			if ( $mapped === 'creatorreactor_subscriber' ) {
				return 'creatorreactor_subscriber';
			}
			// Heuristic: if tier name contains follower keywords, treat as follower.
			$t = strtolower( (string) $tier_norm );
			if ( strpos( $t, 'follower' ) !== false || strpos( $t, '_follower' ) !== false || strpos( $t, 'fan' ) !== false ) {
				return 'creatorreactor_follower';
			}
			return 'creatorreactor_subscriber';
		}

		return '';
	}

	/**
	 * Safe UUID formatting for connection logs.
	 *
	 * @param string $uuid Raw UUID.
	 * @return string Redacted UUID string.
	 */
	private static function format_uuid_for_log( $uuid ) {
		$uuid = is_string( $uuid ) ? sanitize_text_field( $uuid ) : '';
		if ( $uuid === '' ) {
			return 'none';
		}
		if ( strlen( $uuid ) <= 10 ) {
			return $uuid;
		}
		return substr( $uuid, 0, 6 ) . '...' . substr( $uuid, -4 );
	}

	/**
	 * Write a compact, redacted OAuth payload snapshot to debug logs for test verification.
	 *
	 * @param array<string, mixed>|null $profile OAuth profile payload from Fanvue /me endpoint.
	 * @return void
	 */
	private static function log_oauth_profile_payload_debug( $profile ) {
		if ( ! is_array( $profile ) ) {
			Admin_Settings::log_connection( 'debug', 'Fan OAuth callback payload: empty or non-JSON profile.' );
			return;
		}

		$safe_profile = $profile;
		self::redact_profile_fields_recursive( $safe_profile );

		$json = wp_json_encode( $safe_profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || $json === '' ) {
			Admin_Settings::log_connection( 'debug', 'Fan OAuth callback payload: profile present but could not be encoded.' );
			return;
		}
		if ( strlen( $json ) > 4000 ) {
			$json = substr( $json, 0, 4000 ) . '... [truncated]';
		}

		Admin_Settings::log_connection( 'debug', 'Fan OAuth callback payload (redacted): ' . $json );
	}

	/**
	 * Recursively redact sensitive keys before writing OAuth payload debug logs.
	 *
	 * @param mixed $value Nested payload value.
	 * @return void
	 */
	private static function redact_profile_fields_recursive( &$value ) {
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( $value as $key => &$child ) {
			$key_s = is_string( $key ) ? strtolower( $key ) : '';
			if ( in_array( $key_s, [ 'email', 'phone', 'token', 'access_token', 'refresh_token' ], true ) ) {
				$child = '[redacted]';
				continue;
			}
			if ( is_array( $child ) ) {
				self::redact_profile_fields_recursive( $child );
			}
		}
	}

	/**
	 * Apply Fanvue-derived WordPress role while preserving privileged admins.
	 *
	 * @param int    $wp_user_id WordPress user ID.
	 * @param string $role_slug  Target role slug.
	 * @return bool True when the role is already set or successfully applied.
	 */
	private static function apply_wp_role_from_oauth_profile( $wp_user_id, $role_slug ) {
		$wp_user_id = (int) $wp_user_id;
		$role_slug  = sanitize_key( (string) $role_slug );
		if ( $wp_user_id <= 0 || $role_slug === '' ) {
			return false;
		}

		$user = get_user_by( 'id', $wp_user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			Admin_Settings::log_connection(
				'debug',
				sprintf( 'Fan OAuth role apply skipped for WP user %d: privileged account.', $wp_user_id )
			);
			return false;
		}

		if ( ! get_role( $role_slug ) ) {
			$label = $role_slug === 'creatorreactor_follower'
				? __( 'CreatorReactor Follower', 'creatorreactor' )
				: __( 'CreatorReactor Subscriber', 'creatorreactor' );
			add_role( $role_slug, $label, [ 'read' => true ] );
		}

		if ( in_array( $role_slug, (array) $user->roles, true ) ) {
			Admin_Settings::log_connection(
				'debug',
				sprintf( 'Fan OAuth role apply no-op for WP user %d: already %s.', $wp_user_id, $role_slug )
			);
			return true;
		}

		$user->set_role( $role_slug );
		Admin_Settings::log_connection(
			'debug',
			sprintf( 'Fan OAuth role applied for WP user %d: %s.', $wp_user_id, $role_slug )
		);
		return true;
	}

	/**
	 * Apply follower/subscriber role from Fanvue-derived data (list sync, entitlements), respecting admin skip rules.
	 *
	 * @param int    $wp_user_id WordPress user ID.
	 * @param string $role_slug  creatorreactor_follower or creatorreactor_subscriber.
	 * @return bool True if already set, applied, or skipped for privileged users.
	 */
	public static function apply_fanvue_derived_wp_role( $wp_user_id, $role_slug ) {
		$role_slug = sanitize_key( (string) $role_slug );
		if ( ! in_array( $role_slug, [ 'creatorreactor_follower', 'creatorreactor_subscriber' ], true ) ) {
			return false;
		}
		return self::apply_wp_role_from_oauth_profile( (int) $wp_user_id, $role_slug );
	}

	/**
	 * Align WP role with active Fanvue entitlement rows (GET /users/me has no role/tier; list sync fills the table).
	 *
	 * @param int $wp_user_id WordPress user ID.
	 * @return bool True if a role was applied or already matched.
	 */
	public static function sync_wp_role_from_active_fanvue_entitlements( $wp_user_id ) {
		$wp_user_id = (int) $wp_user_id;
		if ( $wp_user_id <= 0 ) {
			return false;
		}
		$rows = Entitlements::get_active_entitlement_rows_for_wp_user( $wp_user_id );
		if ( $rows === [] ) {
			return false;
		}
		$want_subscriber = false;
		$want_follower   = false;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$prod = isset( $row['product'] ) ? Entitlements::normalize_product( (string) $row['product'] ) : '';
			if ( $prod !== Entitlements::PRODUCT_FANVUE ) {
				continue;
			}
			$tier = isset( $row['tier'] ) ? (string) $row['tier'] : '';
			$slug = Entitlements::mapped_fanvue_wp_role_from_stored_tier( $tier );
			if ( $slug === 'creatorreactor_subscriber' ) {
				$want_subscriber = true;
			} elseif ( $slug === 'creatorreactor_follower' ) {
				$want_follower = true;
			}
		}
		if ( $want_subscriber ) {
			return self::apply_fanvue_derived_wp_role( $wp_user_id, 'creatorreactor_subscriber' );
		}
		if ( $want_follower ) {
			return self::apply_fanvue_derived_wp_role( $wp_user_id, 'creatorreactor_follower' );
		}
		return false;
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
		$user_data = ( isset( $d['user'] ) && is_array( $d['user'] ) ) ? $d['user'] : [];
		foreach ( [ 'email', 'Email' ] as $k ) {
			if ( ! empty( $d[ $k ] ) && is_string( $d[ $k ] ) ) {
				$out['email'] = sanitize_email( $d[ $k ] );
				break;
			}
			if ( ! empty( $user_data[ $k ] ) && is_string( $user_data[ $k ] ) ) {
				$out['email'] = sanitize_email( $user_data[ $k ] );
				break;
			}
		}
		foreach ( [ 'id', 'uuid', 'userId', 'user_id' ] as $k ) {
			if ( ! isset( $d[ $k ] ) || is_bool( $d[ $k ] ) ) {
				if ( ! isset( $user_data[ $k ] ) || is_bool( $user_data[ $k ] ) ) {
					continue;
				}
				$raw_uuid = $user_data[ $k ];
			} else {
				$raw_uuid = $d[ $k ];
			}
			if ( is_scalar( $raw_uuid ) ) {
				$s = trim( (string) $raw_uuid );
				if ( $s !== '' ) {
					$out['uuid'] = sanitize_text_field( $s );
					break;
				}
			}
		}
		$display = CreatorReactor_Client::item_display_name( $d );
		if ( ! is_string( $display ) || $display === '' ) {
			$display = CreatorReactor_Client::item_display_name( $user_data );
		}
		if ( is_string( $display ) && $display !== '' ) {
			$out['display'] = sanitize_text_field( $display );
		}
		if ( $out['display'] === '' ) {
			foreach ( [ 'username', 'userName', 'name', 'fullName' ] as $k ) {
				if ( ! empty( $d[ $k ] ) && is_string( $d[ $k ] ) ) {
					$out['display'] = sanitize_text_field( trim( $d[ $k ] ) );
					break;
				}
				if ( ! empty( $user_data[ $k ] ) && is_string( $user_data[ $k ] ) ) {
					$out['display'] = sanitize_text_field( trim( $user_data[ $k ] ) );
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
