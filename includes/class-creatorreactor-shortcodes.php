<?php
/**
 * Front-end shortcodes: tier gates and Fanvue login link.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ], 20 );
	}

	public static function register() {
		add_shortcode( 'follower', [ __CLASS__, 'follower' ] );
		add_shortcode( 'subscriber', [ __CLASS__, 'subscriber' ] );
		add_shortcode( 'not_logged_in', [ __CLASS__, 'not_logged_in' ] );
		add_shortcode( 'fanvue_oauth', [ __CLASS__, 'fanvue_oauth' ] );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null          $content Enclosed content.
	 */
	public static function follower( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( ! Entitlements::wp_user_has_active_follower_entitlement( $uid ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null          $content Enclosed content.
	 */
	public static function subscriber( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( ! Entitlements::wp_user_has_active_subscriber_entitlement( $uid ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null          $content Enclosed content.
	 */
	public static function not_logged_in( $atts, $content = null ) {
		if ( is_user_logged_in() ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 */
	public static function fanvue_oauth( $atts = [] ) {
		if ( Admin_Settings::is_broker_mode() ) {
			return '<p class="creatorreactor-fanvue-oauth-unavailable">' . esc_html__( 'Login with Fanvue is not available in Agency (broker) mode.', 'creatorreactor' ) . '</p>';
		}

		$redirect_to = '';
		if ( is_singular() ) {
			$redirect_to = get_permalink();
		}
		if ( ! is_string( $redirect_to ) || $redirect_to === '' ) {
			if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
				$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			} else {
				$redirect_to = home_url( '/' );
			}
		}
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );

		$rest_path = CreatorReactor_OAuth::REST_NAMESPACE . '/' . ltrim( Fan_OAuth::REST_ROUTE_START, '/' );
		$start     = add_query_arg(
			[
				'_wpnonce'    => wp_create_nonce( 'creatorreactor_fan_oauth' ),
				'redirect_to' => rawurlencode( $redirect_to ),
			],
			rest_url( $rest_path )
		);

		return '<p class="creatorreactor-fanvue-oauth-wrap"><a class="creatorreactor-fanvue-oauth-link" href="' . esc_url( $start ) . '">' . esc_html__( 'Login with Fanvue', 'creatorreactor' ) . '</a></p>';
	}

	/**
	 * @param string|null $content Raw shortcode content.
	 */
	private static function render_enclosed( $content ) {
		if ( $content === null || $content === '' ) {
			return '';
		}
		return do_shortcode( $content );
	}
}
