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
