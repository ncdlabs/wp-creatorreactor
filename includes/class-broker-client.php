<?php
/**
 * CreatorReactor OAuth Broker Client
 * Handles communication with the centralized OAuth broker
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Broker_Client {

	const REST_NAMESPACE            = 'creatorreactor/v1';
	const REST_ROUTE_CONNECT       = '/broker-connect';
	const REST_ROUTE_DISCONNECT    = '/broker-disconnect';
	const REST_ROUTE_STATUS        = '/broker-status';
	const REST_ROUTE_CALLBACK      = '/broker-callback';

	const OPTION_JWT_TOKEN         = 'creatorreactor_login_jwt_token';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/** Default broker OAuth callback URL (creatorreactor/v1/broker-callback). */
	public static function get_default_redirect_uri() {
		return CreatorReactor_OAuth::get_rest_redirect_uri( self::REST_NAMESPACE, self::REST_ROUTE_CALLBACK );
	}

	/** Legacy fanvue/v1 path for the same broker callback. */
	public static function get_legacy_fanvue_redirect_uri() {
		return CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE_LEGACY_FANVUE, self::REST_ROUTE_CALLBACK );
	}

	public static function register_routes() {
		$args = [
			'methods'             => [ 'GET', 'POST', 'HEAD' ],
			'callback'            => [ __CLASS__, 'handle_callback' ],
			'permission_callback' => '__return_true',
		];
		foreach ( CreatorReactor_OAuth::oauth_callback_namespaces() as $namespace ) {
			register_rest_route( $namespace, self::REST_ROUTE_CALLBACK, $args );
		}
	}

	private static function get_broker_options() {
		return Admin_Settings::get_options();
	}

	public static function get_broker_url() {
		$opts = self::get_broker_options();
		return isset( $opts['broker_url'] ) ? rtrim( $opts['broker_url'], '/' ) : 'https://auth.ncdlabs.com';
	}

	public static function get_site_id() {
		$opts = self::get_broker_options();
		return isset( $opts['site_id'] ) ? $opts['site_id'] : null;
	}

	public static function get_jwt_token() {
		$opts = Admin_Settings::get_raw_options();
		if ( isset( $opts['jwt_token'] ) ) {
			if ( Admin_Settings::is_encrypted( $opts['jwt_token'] ) ) {
				return Admin_Settings::encrypt_sensitive_value( $opts['jwt_token'] );
			}
			return $opts['jwt_token'];
		}
		return null;
	}

	public static function get_creatorreactor_oauth_client_id() {
		$opts = self::get_broker_options();
		return isset( $opts['creatorreactor_oauth_client_id'] ) ? $opts['creatorreactor_oauth_client_id'] : null;
	}

	public static function get_creatorreactor_oauth_redirect_uri() {
		$opts = self::get_broker_options();
		return isset( $opts['creatorreactor_oauth_redirect_uri'] ) ? $opts['creatorreactor_oauth_redirect_uri'] : null;
	}

	public static function get_creatorreactor_oauth_scopes() {
		$opts = self::get_broker_options();
		return CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] ?? '' );
	}

	public static function get_creatorreactor_api_version() {
		$opts = self::get_broker_options();
		return isset( $opts['creatorreactor_api_version'] ) ? $opts['creatorreactor_api_version'] : '2025-06-26';
	}

	public static function is_configured() {
		return ! empty( self::get_broker_url() ) && ! empty( self::get_site_id() );
	}

	public static function is_connected() {
		$opts = Admin_Settings::get_raw_options();
		return ! empty( $opts['jwt_token'] );
	}

	public static function get_connect_url() {
		$site_id = self::get_site_id();
		if ( ! $site_id ) {
			return null;
		}

		$params = [
			'site_id' => $site_id,
		];

		$client_id = self::get_creatorreactor_oauth_client_id();
		if ( ! empty( $client_id ) ) {
			$params['client_id'] = $client_id;
		}

		$redirect_uri = self::get_creatorreactor_oauth_redirect_uri();
		if ( ! empty( $redirect_uri ) ) {
			$params['redirect_uri'] = $redirect_uri;
		}

		$scopes = self::get_creatorreactor_oauth_scopes();
		if ( ! empty( $scopes ) ) {
			$params['scope'] = $scopes;
		}

		return add_query_arg( $params, self::get_broker_url() . '/connect/creatorreactor' );
	}

	public static function handle_callback( $request ) {
		try {
			Admin_Settings::log_connection( 'info', 'Broker REST callback: request received.' );

			$connection = $request->get_param( 'creatorreactor_connection' );
			$creator_id = $request->get_param( 'creator_id' );
			$site_id    = $request->get_param( 'site_id' );
			$error      = $request->get_param( 'error' );

			if ( $error ) {
				Admin_Settings::log_connection( 'error', 'Broker callback: error parameter — ' . (string) $error );
				Admin_Settings::set_last_error( 'Broker: ' . $error );
				wp_safe_redirect(
					admin_url( 'options-general.php?page=' . Admin_Settings::PAGE_SLUG . '&status=error' )
				);
				exit;
			}

			if ( $connection === 'success' && $creator_id && $site_id ) {
				$jwt = self::exchange_code_for_jwt( $site_id, $creator_id );
				if ( $jwt ) {
					self::store_jwt_token( $jwt );
					Admin_Settings::log_connection( 'info', 'Broker callback: JWT stored; connection success.' );
					Admin_Settings::set_last_error( '' );
					wp_safe_redirect(
						admin_url( 'options-general.php?page=' . Admin_Settings::PAGE_SLUG . '&status=connected' )
					);
					exit;
				}
				Admin_Settings::log_connection( 'error', 'Broker callback: JWT exchange returned empty token.' );
			} else {
				Admin_Settings::log_connection(
					'error',
					'Broker callback: unexpected parameters (connection=' . (string) $connection . ', site_id=' . ( $site_id ? 'set' : 'empty' ) . ', creator_id=' . ( $creator_id ? 'set' : 'empty' ) . ').'
				);
			}

			wp_safe_redirect(
				admin_url( 'options-general.php?page=' . Admin_Settings::PAGE_SLUG . '&status=pending' )
			);
			exit;
		} catch ( \Throwable $e ) {
			Admin_Settings::log_connection( 'error', 'Broker callback: exception — ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( 'Broker callback failed: ' . $e->getMessage() );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . Admin_Settings::PAGE_SLUG . '&status=error' ) );
			exit;
		}
	}

	private static function exchange_code_for_jwt( $site_id, $creator_id ) {
		$response = wp_remote_post(
			self::get_broker_url() . '/api/internal/token',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( [
					'site_id'    => $site_id,
					'creator_id' => $creator_id,
				] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			Admin_Settings::log_connection( 'error', 'Broker JWT exchange: request failed — ' . $response->get_error_message() );
			Admin_Settings::set_last_error( 'Failed to exchange code: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			if ( strlen( $body ) > 500 ) {
				$body = substr( $body, 0, 500 ) . '…';
			}
			Admin_Settings::log_connection( 'error', 'Broker JWT exchange: HTTP ' . $code . '. ' . preg_replace( '/\s+/', ' ', wp_strip_all_tags( $body ) ) );
			Admin_Settings::set_last_error( 'Token exchange failed: HTTP ' . $code );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['token'] ) ) {
			return $body['token'];
		}
		Admin_Settings::log_connection( 'error', 'Broker JWT exchange: HTTP 200 but response JSON missing token key.' );
		return null;
	}

	private static function store_jwt_token( $token ) {
		$opts              = Admin_Settings::get_raw_options();
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['jwt_token'] = Admin_Settings::encrypt_sensitive_value( $token );
		update_option( Admin_Settings::OPTION_NAME, $opts );
	}

	public static function disconnect() {
		try {
			$jwt = self::get_jwt_token();
			if ( ! $jwt ) {
				Admin_Settings::log_connection( 'debug', 'Broker disconnect: no JWT to revoke (already cleared).' );
				return false;
			}

			$site_id    = self::get_site_id();
			$creator_id = self::get_creator_id_from_jwt( $jwt );

			if ( ! $creator_id ) {
				self::clear_credentials();
				return true;
			}

			$response = wp_remote_post(
				self::get_broker_url() . '/oauth/disconnect',
				[
					'timeout' => 15,
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $jwt,
					],
					'body'    => wp_json_encode( [
						'site_id'    => $site_id,
						'creator_id' => $creator_id,
					] ),
				]
			);

			self::clear_credentials();
			return true;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor disconnect error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			self::clear_credentials();
			return false;
		}
	}

	private static function get_creator_id_from_jwt( $jwt ) {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return null;
		}

		$payload = json_decode( base64_decode( $parts[1] ), true );
		return isset( $payload['creator_id'] ) ? $payload['creator_id'] : null;
	}

	private static function clear_credentials() {
		$opts = Admin_Settings::get_raw_options();
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		unset( $opts['jwt_token'] );
		update_option( Admin_Settings::OPTION_NAME, $opts );
	}

	public static function test_connection() {
		try {
			if ( ! self::is_configured() ) {
				return [
					'success' => false,
					'message' => __( 'Broker URL and Site ID must be configured for Agency mode.', 'creatorreactor' ),
					'checks' => [
						[
							'label' => __( 'Broker URL & Site ID', 'creatorreactor' ),
							'pass' => false,
							'message' => __( 'Required: set both in Broker Settings.', 'creatorreactor' ),
						],
					],
				];
			}

			$jwt = self::get_jwt_token();
			if ( ! $jwt ) {
				return [
					'success' => false,
					'message' => __( 'Not connected to broker. Use Connect after saving broker settings.', 'creatorreactor' ),
					'checks' => [
						[
							'label' => __( 'Broker session', 'creatorreactor' ),
							'pass' => false,
							'message' => __( 'No JWT yet — complete OAuth via Connect.', 'creatorreactor' ),
						],
					],
				];
			}

			$result = self::api_get( '/me' );
			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'message' => $result->get_error_message(),
					'checks' => [
						[
							'label' => __( 'CreatorReactor API (via broker)', 'creatorreactor' ),
							'pass' => false,
							'message' => $result->get_error_message(),
						],
					],
				];
			}

			$name = isset( $result['displayName'] ) ? $result['displayName'] : ( isset( $result['handle'] ) ? $result['handle'] : __( 'Unknown', 'creatorreactor' ) );

			return [
				'success' => true,
				'message' => sprintf( __( 'Connected successfully as %s', 'creatorreactor' ), $name ),
				'checks' => [
					[
						'label' => __( 'CreatorReactor API (via broker)', 'creatorreactor' ),
						'pass' => true,
						'message' => sprintf( __( 'OK (%s)', 'creatorreactor' ), $name ),
					],
				],
			];
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	public static function api_get( $endpoint, $args = [] ) {
		try {
			$jwt = self::get_jwt_token();
			if ( ! $jwt ) {
				return new \WP_Error( 'not_connected', 'Not connected to broker' );
			}

			$url = self::get_broker_url() . '/api/creatorreactor' . $endpoint;
			if ( ! empty( $args ) ) {
				$url = add_query_arg( $args, $url );
			}

			$api_ver = self::get_creatorreactor_api_version();
			$response = wp_remote_get(
				$url,
				[
					'timeout' => 20,
					'headers' => [
						'Authorization'                => 'Bearer ' . $jwt,
						'X-Fanvue-API-Version'         => $api_ver,
						'X-CreatorReactor-API-Version' => $api_ver,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code === 401 ) {
				self::clear_credentials();
				return new \WP_Error( 'token_expired', 'JWT token expired, please reconnect' );
			}

			if ( $code !== 200 ) {
				$body    = wp_remote_retrieve_body( $response );
				$snippet = is_string( $body ) && $body !== '' ? substr( wp_strip_all_tags( $body ), 0, 500 ) : '';
				$msg     = 'API request failed: HTTP ' . $code . ( $snippet !== '' ? '. Response: ' . $snippet : '' );
				if ( (int) $code === 403 && is_string( $body ) && stripos( $body, 'Insufficient scopes' ) !== false ) {
					$msg .= ' ' . CreatorReactor_Client::get_insufficient_scopes_hint_text();
				}
				return new \WP_Error( 'api_error', $msg );
			}

			return json_decode( wp_remote_retrieve_body( $response ), true );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor api_get error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return new \WP_Error( 'api_error', 'API request failed: ' . $e->getMessage() );
		}
	}

	public static function get_profile() {
		return self::api_get( '/profile' );
	}

	public static function get_subscribers( $page = 1, $size = 50 ) {
		return self::api_get( '/subscribers', [ 'page' => $page, 'size' => $size ] );
	}

	public static function get_followers( $page = 1, $size = 50 ) {
		return self::api_get( '/followers', [ 'page' => $page, 'size' => $size ] );
	}

	public static function get_status() {
		$site_id = self::get_site_id();
		if ( ! $site_id ) {
			return null;
		}

		$response = wp_remote_get(
			self::get_broker_url() . '/oauth/status/' . $site_id,
			[
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
