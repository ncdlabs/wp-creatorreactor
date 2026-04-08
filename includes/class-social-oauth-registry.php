<?php
/**
 * OAuth 2.0 provider definitions for generic social login (REST + admin tabs).
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
 * Registry of slugs and endpoints. Bluesky (atproto) is implemented separately in {@see Bluesky_OAuth}.
 */
class Social_OAuth_Registry {

	/**
	 * Provider slugs handled by {@see Social_OAuth} (not Bluesky).
	 *
	 * @return string[]
	 */
	public static function generic_slugs() {
		return [
			'tiktok',
			'x_twitter',
			'snapchat',
			'linkedin',
			'pinterest',
			'reddit',
			'twitch',
			'discord',
			'mastodon',
		];
	}

	/**
	 * REST path segment: x_twitter → x-twitter.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function rest_segment( $slug ) {
		return str_replace( '_', '-', sanitize_key( (string) $slug ) );
	}

	/**
	 * @param string $slug Provider slug.
	 * @return string e.g. /tiktok-oauth-start
	 */
	public static function rest_route_start( $slug ) {
		return '/' . self::rest_segment( $slug ) . '-oauth-start';
	}

	/**
	 * @param string $slug Provider slug.
	 * @return string e.g. /tiktok-oauth-callback
	 */
	public static function rest_route_callback( $slug ) {
		return '/' . self::rest_segment( $slug ) . '-oauth-callback';
	}

	/**
	 * Nonce action for wp_nonce and shortcodes.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function nonce_action( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug ) . '_oauth';
	}

	/**
	 * User meta key for stable provider account id.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function usermeta_sub_key( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug ) . '_sub';
	}

	/**
	 * Query arg for wp-login notices.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function query_arg( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug );
	}

	/**
	 * Option name: client id (encrypted).
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function option_client_id( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug ) . '_oauth_client_id';
	}

	/**
	 * Option name: client secret (encrypted).
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function option_client_secret( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug ) . '_oauth_client_secret';
	}

	/**
	 * Option name: button size mode.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function option_button_size_mode( $slug ) {
		return 'creatorreactor_' . sanitize_key( (string) $slug ) . '_oauth_button_size_mode';
	}

	/**
	 * Config for a provider (static URLs; Mastodon overrides host via options).
	 *
	 * token_auth: post | basic (reddit).
	 * client_id_param: query/body name for client id at authorize/token (tiktok uses client_key).
	 * profile_strategy: how to load user id + email after token exchange.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_config( $slug ) {
		$key = sanitize_key( (string) $slug );
		$all = self::configs();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function configs() {
		return [
			'tiktok'    => [
				'label'           => __( 'TikTok', 'wp-creatorreactor' ),
				'auth_url'        => 'https://www.tiktok.com/v2/auth/authorize/',
				'token_url'       => 'https://open.tiktokapis.com/v2/oauth/token/',
				'scopes'          => 'user.info.basic',
				'client_id_param' => 'client_key',
				'token_auth'      => 'post',
				'extra_auth_params' => [
					'response_type' => 'code',
				],
				'profile_strategy' => 'tiktok',
			],
			'x_twitter' => [
				'label'         => __( 'X (Twitter)', 'wp-creatorreactor' ),
				'auth_url'      => 'https://twitter.com/i/oauth2/authorize',
				'token_url'     => 'https://api.twitter.com/2/oauth2/token',
				'scopes'        => 'tweet.read users.read offline.access',
				'client_id_param' => 'client_id',
				'token_auth'    => 'basic',
				'profile_strategy' => 'twitter',
			],
			'snapchat'  => [
				'label'         => __( 'Snapchat', 'wp-creatorreactor' ),
				'auth_url'      => 'https://accounts.snapchat.com/accounts/oauth2/auth',
				'token_url'     => 'https://accounts.snapchat.com/accounts/oauth2/token',
				'scopes'        => 'https://auth.snapchat.com/oauth2/api/user.external_id',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'snapchat',
			],
			'linkedin'  => [
				'label'         => __( 'LinkedIn', 'wp-creatorreactor' ),
				'auth_url'      => 'https://www.linkedin.com/oauth/v2/authorization',
				'token_url'     => 'https://www.linkedin.com/oauth/v2/accessToken',
				'scopes'        => 'openid profile email',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'linkedin_oidc',
			],
			'pinterest' => [
				'label'         => __( 'Pinterest', 'wp-creatorreactor' ),
				'auth_url'      => 'https://www.pinterest.com/oauth/',
				'token_url'     => 'https://api.pinterest.com/v5/oauth/token',
				'scopes'        => 'user_accounts:read',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'pinterest',
			],
			'reddit'    => [
				'label'         => __( 'Reddit', 'wp-creatorreactor' ),
				'auth_url'      => 'https://www.reddit.com/api/v1/authorize',
				'token_url'     => 'https://www.reddit.com/api/v1/access_token',
				'scopes'        => 'identity',
				'client_id_param' => 'client_id',
				'token_auth'    => 'basic',
				'pkce'          => false,
				'extra_auth_params' => [
					'duration' => 'permanent',
				],
				'profile_strategy' => 'reddit',
			],
			'twitch'    => [
				'label'         => __( 'Twitch', 'wp-creatorreactor' ),
				'auth_url'      => 'https://id.twitch.tv/oauth2/authorize',
				'token_url'     => 'https://id.twitch.tv/oauth2/token',
				'scopes'        => 'user:read:email',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'twitch',
			],
			'discord'   => [
				'label'         => __( 'Discord', 'wp-creatorreactor' ),
				'auth_url'      => 'https://discord.com/api/oauth2/authorize',
				'token_url'     => 'https://discord.com/api/oauth2/token',
				'scopes'        => 'identify email',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'discord',
			],
			'mastodon'  => [
				'label'         => __( 'Mastodon', 'wp-creatorreactor' ),
				'auth_url'      => '', // Built from instance.
				'token_url'     => '',
				'scopes'        => 'read:accounts',
				'client_id_param' => 'client_id',
				'token_auth'    => 'post',
				'profile_strategy' => 'mastodon',
				'mastodon'      => true,
			],
		];
	}
}
