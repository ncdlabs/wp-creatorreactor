<?php
/**
 * Instagram (Meta) OAuth 2.0 helpers: callback URL and settings credential checks.
 *
 * Uses the Instagram API with Instagram Login product (authorization + token exchange).
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Instagram_OAuth {

	/** REST path segment (matches {@see CreatorReactor_OAuth::REST_NAMESPACE}). */
	const REST_ROUTE_CALLBACK = '/instagram-oauth-callback';

	const AUTH_URL  = 'https://www.instagram.com/oauth/authorize';
	const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

	/**
	 * Add this OAuth redirect URI in the Meta developer app (Instagram Login).
	 */
	public static function get_callback_redirect_uri() {
		return trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, self::REST_ROUTE_CALLBACK )
		);
	}

	/**
	 * Ask Instagram’s token endpoint whether client_id and client_secret are recognized.
	 *
	 * Sends a deliberately invalid authorization code. A response indicating the code
	 * (not the client) is invalid implies the app credentials were accepted.
	 *
	 * @param string $client_id     Instagram app ID (OAuth client ID).
	 * @param string $client_secret Instagram app secret.
	 * @param string $redirect_uri  Registered redirect URI (must match the app settings).
	 * @return true|\WP_Error True when credentials appear valid; WP_Error otherwise.
	 */
	public static function probe_client_credentials_for_settings_test( $client_id, $client_secret, $redirect_uri ) {
		$client_id     = trim( (string) $client_id );
		$client_secret = trim( (string) $client_secret );
		$redirect_uri  = (string) $redirect_uri;

		if ( $client_id === '' ) {
			return new \WP_Error( 'instagram_probe_no_client_id', __( 'Client ID is empty.', 'wp-creatorreactor' ) );
		}
		if ( $client_secret === '' ) {
			return new \WP_Error( 'instagram_probe_no_secret', __( 'Client Secret is empty.', 'wp-creatorreactor' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'body'    => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
					'code'          => 'creatorreactor_settings_test_invalid_code',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'instagram_probe_http',
				sprintf(
					/* translators: %s: WordPress HTTP error message. */
					__( 'Could not reach Instagram (%s). Check that this server can make outbound HTTPS requests.', 'wp-creatorreactor' ),
					$response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( is_string( $body ) ? $body : '', true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'instagram_probe_bad_response',
				__( 'Instagram returned an unexpected response. Try again in a moment.', 'wp-creatorreactor' )
			);
		}

		if ( ! empty( $data['access_token'] ) ) {
			return true;
		}

		$msg = isset( $data['error_message'] ) ? sanitize_text_field( (string) $data['error_message'] ) : '';
		if ( $msg === '' && isset( $data['error'] ) ) {
			$msg = sanitize_text_field( (string) $data['error'] );
		}
		$lower = strtolower( $msg );

		if ( strpos( $lower, 'redirect' ) !== false && strpos( $lower, 'uri' ) !== false ) {
			return new \WP_Error(
				'instagram_probe_redirect',
				$msg !== '' ? $msg : __( 'Redirect URI does not match the app configuration.', 'wp-creatorreactor' ),
				[ 'instagram_error' => 'redirect_uri' ]
			);
		}

		if (
			( strpos( $lower, 'client' ) !== false && strpos( $lower, 'invalid' ) !== false )
			|| strpos( $lower, 'client_secret' ) !== false
			|| strpos( $lower, 'client id' ) !== false
		) {
			return new \WP_Error(
				'instagram_probe_client',
				$msg !== '' ? $msg : __( 'Instagram rejected the Client ID or Client Secret.', 'wp-creatorreactor' ),
				[ 'instagram_error' => 'invalid_client' ]
			);
		}

		if (
			strpos( $lower, 'code' ) !== false
			|| strpos( $lower, 'matching' ) !== false
			|| strpos( $lower, 'verification' ) !== false
		) {
			return true;
		}

		return new \WP_Error(
			'instagram_probe_failed',
			$msg !== '' ? $msg : __( 'Instagram rejected the request.', 'wp-creatorreactor' ),
			[ 'instagram_error' => isset( $data['error_type'] ) ? (string) $data['error_type'] : '' ]
		);
	}
}
