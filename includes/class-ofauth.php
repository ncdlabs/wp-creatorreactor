<?php
/**
 * OnlyFans OFAuth: REST webhook endpoint and helpers (Account Linking).
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OFAuth {

	const REST_ROUTE_WEBHOOK = '/ofauth-webhook';

	/** OFAuth HTTP API host (Account Linking). */
	const API_BASE_URL = 'https://api.ofauth.com';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Public URL to register in the OFAuth dashboard (POST webhooks).
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, self::REST_ROUTE_WEBHOOK );
	}

	/**
	 * Verify an OFAuth API key with a lightweight GET (no client session created).
	 *
	 * @param string $api_key Plaintext access key from the OFAuth dashboard.
	 * @return true|\WP_Error
	 */
	public static function probe_api_key_for_settings_test( $api_key ) {
		$api_key = trim( (string) $api_key );
		if ( $api_key === '' ) {
			return new \WP_Error( 'ofauth_probe_no_key', __( 'API key is empty.', 'wp-creatorreactor' ) );
		}

		$url      = self::API_BASE_URL . '/v2/account';
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 15,
				'headers' => [
					'apiKey' => $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'ofauth_probe_http',
				sprintf(
					/* translators: %s: WordPress HTTP error message. */
					__( 'Could not reach OFAuth (%s). Check outbound HTTPS from this server.', 'wp-creatorreactor' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 401 ) {
			return new \WP_Error(
				'ofauth_probe_unauthorized',
				__( 'OFAuth rejected this API key (Unauthorized).', 'wp-creatorreactor' ),
				[ 'http_code' => 401 ]
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$snippet = is_string( $body ) ? wp_strip_all_tags( substr( $body, 0, 300 ) ) : '';

			return new \WP_Error(
				'ofauth_probe_http',
				$snippet !== ''
					? sprintf(
						/* translators: 1: HTTP status code, 2: response snippet */
						__( 'OFAuth returned HTTP %1$s: %2$s', 'wp-creatorreactor' ),
						(string) $code,
						$snippet
					)
					: sprintf(
						/* translators: %s: HTTP status code */
						__( 'OFAuth returned HTTP %s.', 'wp-creatorreactor' ),
						(string) $code
					),
				[ 'http_code' => $code ]
			);
		}

		return true;
	}

	public static function register_routes() {
		register_rest_route(
			CreatorReactor_OAuth::REST_NAMESPACE,
			self::REST_ROUTE_WEBHOOK,
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ __CLASS__, 'handle_webhook' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_webhook( $request ) {
		$opts     = Admin_Settings::get_options();
		$expected = isset( $opts['creatorreactor_ofauth_webhook_secret'] ) ? (string) $opts['creatorreactor_ofauth_webhook_secret'] : '';
		if ( $expected === '' ) {
			Admin_Settings::log_connection( 'debug', 'OFAuth webhook rejected: webhook secret not configured.' );
			return new \WP_REST_Response(
				[ 'error' => 'webhook_secret_not_configured' ],
				503
			);
		}

		$provided = $request->get_header( 'x-webhook-secret' );
		if ( ! is_string( $provided ) || $provided === '' || ! hash_equals( $expected, $provided ) ) {
			Admin_Settings::log_connection( 'error', 'OFAuth webhook rejected: invalid x-webhook-secret.' );
			return new \WP_REST_Response(
				[ 'error' => 'unauthorized' ],
				401
			);
		}

		$raw = $request->get_body();
		if ( ! is_string( $raw ) ) {
			$raw = '';
		}
		$preview = $raw;
		if ( strlen( $preview ) > 3500 ) {
			$preview = substr( $preview, 0, 3500 ) . '…';
		}
		Admin_Settings::log_connection( 'info', 'OFAuth webhook received: ' . $preview );

		/**
		 * After a verified OFAuth webhook payload is received (link.success, etc.).
		 *
		 * @param string           $raw     Raw body.
		 * @param \WP_REST_Request $request Request.
		 */
		do_action( 'creatorreactor_ofauth_webhook', $raw, $request );

		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}
}
