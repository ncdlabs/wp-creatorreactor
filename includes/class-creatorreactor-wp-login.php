<?php
/**
 * wp-login.php: add Fanvue social login (image button) beside the login form when enabled.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_Page {

	/**
	 * Fix duplicate path slashes in redirect_to before wp-login.php reads superglobals (e.g. .../wp-admin//).
	 */
	public static function normalize_request_redirect_to() {
		$fix = static function ( $v ) {
			if ( ! is_string( $v ) || $v === '' ) {
				return $v;
			}
			return Plugin::normalize_url_path_slashes( wp_unslash( $v ) );
		};
		if ( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['redirect_to'] = $fix( $_GET['redirect_to'] );
		}
		if ( isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['redirect_to'] = $fix( $_POST['redirect_to'] );
		}
		if ( isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_REQUEST['redirect_to'] = $fix( $_REQUEST['redirect_to'] );
		}
	}

	public static function init() {
		add_action( 'login_init', [ __CLASS__, 'normalize_request_redirect_to' ], 0 );
		add_action( 'login_init', [ __CLASS__, 'maybe_offer_pending_fanvue_resume' ], 2 );
		add_action( 'login_form_login', [ __CLASS__, 'on_login_form_login' ] );
		add_action( 'login_init', [ __CLASS__, 'maybe_add_fanvue_oauth_login_notice' ] );
		add_action( 'login_init', [ __CLASS__, 'maybe_add_google_oauth_login_notice' ] );
		add_filter( 'login_redirect', [ __CLASS__, 'force_home_login_redirect' ], 20, 3 );
		add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue_login_branding_assets' ], 5 );
		add_filter( 'login_headerurl', [ __CLASS__, 'filter_login_header_url' ] );
		add_filter( 'login_headertext', [ __CLASS__, 'filter_login_header_text' ] );
		add_filter( 'login_body_class', [ __CLASS__, 'filter_login_body_class' ], 10, 2 );
	}

	/**
	 * Brand colors, logo, and layout for wp-login.php (all login actions).
	 */
	public static function enqueue_login_branding_assets() {
		$handle = 'creatorreactor-wp-login-brand';
		wp_enqueue_style(
			$handle,
			CREATORREACTOR_PLUGIN_URL . 'assets/css/creatorreactor-wp-login.css',
			[],
			CREATORREACTOR_VERSION
		);
		$logo = esc_url( CREATORREACTOR_PLUGIN_URL . 'img/cr-logo.png' );
		wp_add_inline_style(
			$handle,
			':root{--cr-logo-url:url("' . $logo . '");}'
		);
	}

	/**
	 * @param string $url Default login logo link URL.
	 * @return string
	 */
	public static function filter_login_header_url( $url ) {
		return home_url( '/' );
	}

	/**
	 * @param string $text Default login logo link title / alt text.
	 * @return string
	 */
	public static function filter_login_header_text( $text ) {
		return __( 'CreatorReactor', 'creatorreactor' );
	}

	/**
	 * @param string[] $classes Body classes.
	 * @param string   $action  Login action (login, lostpassword, etc.).
	 * @return string[]
	 */
	public static function filter_login_body_class( $classes, $action ) {
		$classes[] = 'creatorreactor-login';
		return $classes;
	}

	/**
	 * Force post-login destination to the public homepage.
	 *
	 * @param string           $redirect_to           Requested redirect URL.
	 * @param string           $requested_redirect_to Raw redirect_to from request.
	 * @param \WP_User|\WP_Error $user               Authenticated user or error.
	 * @return string
	 */
	public static function force_home_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof \WP_User ) {
			return home_url( '/' );
		}
		return $redirect_to;
	}

	/**
	 * Show a link to continue Fanvue registration instead of auto-redirecting (avoids redirect loops / invisible flashes).
	 */
	public static function maybe_offer_pending_fanvue_resume() {
		// Onboarding has been removed from the UX, so we no longer offer a "continue setup" resume box.
		return;
	}

	/**
	 * @param string $message Existing login message HTML.
	 * @return string
	 */
	public static function filter_login_message_pending_fanvue_resume( $message ) {
		// Onboarding has been removed from the UX, so we never append a resume box.
		return $message;
	}

	/**
	 * Fanvue callback often redirects to wp-admin/?creatorreactor_fanvue=…; unauthenticated users are
	 * bounced here with that URL inside redirect_to only — top-level creatorreactor_fanvue is empty.
	 *
	 * @return string Sanitized code or ''.
	 */
	private static function get_fanvue_oauth_notice_code_from_request() {
		if ( isset( $_GET['creatorreactor_fanvue'] ) && is_string( $_GET['creatorreactor_fanvue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_key( wp_unslash( $_GET['creatorreactor_fanvue'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! isset( $_GET['redirect_to'] ) || ! is_string( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$raw = wp_unslash( $_GET['redirect_to'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$decoded = rawurldecode( $raw );
		$parts   = wp_parse_url( $decoded );
		if ( empty( $parts['query'] ) || ! is_string( $parts['query'] ) ) {
			return '';
		}
		parse_str( $parts['query'], $q );
		if ( empty( $q['creatorreactor_fanvue'] ) || ! is_string( $q['creatorreactor_fanvue'] ) ) {
			return '';
		}
		return sanitize_key( $q['creatorreactor_fanvue'] );
	}

	/**
	 * Surface Fanvue OAuth return codes on wp-login (callback redirects append creatorreactor_fanvue=…).
	 */
	public static function maybe_add_fanvue_oauth_login_notice() {
		if ( self::get_fanvue_oauth_notice_code_from_request() === '' ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'login' !== $action ) {
			return;
		}
		add_filter( 'login_message', [ __CLASS__, 'filter_login_message_fanvue_oauth' ], 10, 1 );
	}

	/**
	 * @param string $message Existing login message HTML.
	 * @return string
	 */
	public static function filter_login_message_fanvue_oauth( $message ) {
		$raw = self::get_fanvue_oauth_notice_code_from_request();
		$map = [
			'nonce'   => __( 'Fanvue sign-in could not start (link expired or invalid). Use the Fanvue button again.', 'creatorreactor' ),
			'agency'  => __( 'Fanvue visitor login is not available in Agency (broker) mode.', 'creatorreactor' ),
			'config'  => __( 'Fanvue sign-in is not configured on this site (OAuth Client ID or endpoints). Ask the site administrator to complete CreatorReactor OAuth settings.', 'creatorreactor' ),
			'denied'  => __( 'Fanvue sign-in was cancelled or denied.', 'creatorreactor' ),
			'oauth_redirect' => __( 'Fanvue redirect URI does not match this site. In the Fanvue app settings, add the exact visitor (fan) OAuth callback URL shown under CreatorReactor → Settings, then try again.', 'creatorreactor' ),
			'oauth_client'   => __( 'Fanvue rejected the OAuth client. Check Client ID and Client Secret under Settings → Fanvue → OAuth and that the Fanvue app is active.', 'creatorreactor' ),
			'oauth_request'  => __( 'Fanvue rejected the sign-in request as invalid. Try the Fanvue button again; if it keeps failing, verify the app’s redirect URLs and OAuth settings.', 'creatorreactor' ),
			'oauth_error'    => __( 'Fanvue returned an authorization error before sign-in could finish. Try again or ask the site administrator to check CreatorReactor connection logs.', 'creatorreactor' ),
			'state'   => __( 'Fanvue sign-in could not be verified (session expired or mismatch). Use the Fanvue button to try again.', 'creatorreactor' ),
			'token'   => __( 'Fanvue did not return a usable token. Check OAuth Client ID, Secret, and redirect URLs under Settings → Fanvue → OAuth, then try again.', 'creatorreactor' ),
			'profile' => __( 'Fanvue sign-in could not load profile data. Use your WordPress login or try the Fanvue button again.', 'creatorreactor' ),
			'closed'  => __( 'New accounts are not allowed on this site. An administrator must create your WordPress user or enable registration.', 'creatorreactor' ),
			'user'    => __( 'Could not create or load your WordPress user after Fanvue sign-in. Contact the site administrator.', 'creatorreactor' ),
			'missing'         => __( 'Fanvue did not return a complete authorization response. Confirm the redirect URI in your Fanvue app matches this site exactly, then try again.', 'creatorreactor' ),
			'pending_expired' => __( 'Your Fanvue sign-in session expired before account setup finished. Use Log in with Fanvue again.', 'creatorreactor' ),
		];
		$text = isset( $map[ $raw ] ) ? $map[ $raw ] : __( 'Fanvue sign-in did not finish. Use the Fanvue button to try again.', 'creatorreactor' );
		$box  = '<div class="creatorreactor-fanvue-login-notice" role="alert"><p style="margin:0;">' . esc_html( $text ) . '</p></div>';
		return $message . $box;
	}

	/**
	 * @return string Sanitized code or ''.
	 */
	private static function get_google_oauth_notice_code_from_request() {
		if ( isset( $_GET['creatorreactor_google'] ) && is_string( $_GET['creatorreactor_google'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_key( wp_unslash( $_GET['creatorreactor_google'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! isset( $_GET['redirect_to'] ) || ! is_string( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$raw = wp_unslash( $_GET['redirect_to'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$decoded = rawurldecode( $raw );
		$parts   = wp_parse_url( $decoded );
		if ( empty( $parts['query'] ) || ! is_string( $parts['query'] ) ) {
			return '';
		}
		parse_str( $parts['query'], $q );
		if ( empty( $q['creatorreactor_google'] ) || ! is_string( $q['creatorreactor_google'] ) ) {
			return '';
		}
		return sanitize_key( $q['creatorreactor_google'] );
	}

	/**
	 * Surface Google OAuth return codes on wp-login.
	 */
	public static function maybe_add_google_oauth_login_notice() {
		if ( self::get_google_oauth_notice_code_from_request() === '' ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'login' !== $action ) {
			return;
		}
		add_filter( 'login_message', [ __CLASS__, 'filter_login_message_google_oauth' ], 10, 1 );
	}

	/**
	 * @param string $message Existing login message HTML.
	 * @return string
	 */
	public static function filter_login_message_google_oauth( $message ) {
		$raw = self::get_google_oauth_notice_code_from_request();
		$map = [
			'nonce'           => __( 'Google sign-in could not start (link expired or invalid). Use Sign in with Google again.', 'creatorreactor' ),
			'agency'          => __( 'Google sign-in is not available in Agency (broker) mode.', 'creatorreactor' ),
			'config'          => __( 'Google sign-in is not configured. Ask the site administrator to add OAuth credentials under Settings → Google.', 'creatorreactor' ),
			'denied'          => __( 'Google sign-in was cancelled or denied.', 'creatorreactor' ),
			'oauth_redirect'  => __( 'Google redirect URI does not match this site. In Google Cloud Console, set the Authorized redirect URI to the value shown under Settings → Google.', 'creatorreactor' ),
			'oauth_client'    => __( 'Google rejected the OAuth client. Check Client ID and Client Secret under Settings → Google.', 'creatorreactor' ),
			'oauth_request'   => __( 'Google rejected the sign-in request. Try Sign in with Google again.', 'creatorreactor' ),
			'oauth_error'     => __( 'Google returned an authorization error. Try again or ask the site administrator to check connection logs.', 'creatorreactor' ),
			'state'           => __( 'Google sign-in could not be verified (session expired). Use Sign in with Google again.', 'creatorreactor' ),
			'token'           => __( 'Google did not return a usable token. Check OAuth settings under Settings → Google.', 'creatorreactor' ),
			'profile'         => __( 'Google sign-in could not load profile data. Try again or use your WordPress login.', 'creatorreactor' ),
			'closed'          => __( 'New accounts are not allowed on this site. An administrator must create your WordPress user or enable registration.', 'creatorreactor' ),
			'user'            => __( 'Could not create or load your WordPress user after Google sign-in. Contact the site administrator.', 'creatorreactor' ),
			'missing'         => __( 'Google did not return a complete authorization response. Confirm the redirect URI in Google Cloud Console matches this site.', 'creatorreactor' ),
		];
		$text = isset( $map[ $raw ] ) ? $map[ $raw ] : __( 'Google sign-in did not finish. Use Sign in with Google to try again.', 'creatorreactor' );
		$box  = '<div class="creatorreactor-google-login-notice" role="alert"><p style="margin:0;">' . esc_html( $text ) . '</p></div>';
		return $message . $box;
	}

	/**
	 * Register hooks for the primary login screen only (not lost password, etc.).
	 */
	public static function on_login_form_login() {
		if ( ! Admin_Settings::is_replace_wp_login_with_social() ) {
			return;
		}

		add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue_login_assets' ] );
		add_action( 'login_form', [ __CLASS__, 'render_social_login_markup' ], 0 );
	}

	public static function enqueue_login_assets() {
		$style_handle = 'creatorreactor-login-social';
		$script_handle = 'creatorreactor-login-social-js';
		wp_register_style( $style_handle, false, [], CREATORREACTOR_VERSION );
		wp_enqueue_style( $style_handle );
		$css = <<<'CSS'
.creatorreactor-wp-login-split {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: 28px;
	width: 100%;
	max-width: 100%;
}
.creatorreactor-wp-login-split-form {
	flex: 0 1 auto;
	min-width: min(100%, 280px);
}
.creatorreactor-wp-login-split-social {
	flex: 0 0 auto;
	align-self: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	position: relative;
	z-index: 1;
}
#loginform .creatorreactor-wp-login-social {
	text-align: center;
}
.creatorreactor-wp-login-split-social .creatorreactor-fanvue-oauth-wrap {
	margin: 0;
}
.creatorreactor-wp-login-split-social .creatorreactor-fanvue-oauth-link {
	display: inline-block;
	line-height: 1.35;
	box-shadow: none;
	position: relative;
	z-index: 2;
	cursor: pointer;
	pointer-events: auto;
	text-decoration: none;
	color: #8e2d77;
}
.creatorreactor-fanvue-oauth-text {
	display: block;
	margin-top: 8px;
	font-size: 14px;
	font-weight: 600;
}
.creatorreactor-fanvue-oauth-logged-in {
	margin: 0;
	text-align: center;
	max-width: min(220px, 100%);
}
.creatorreactor-wp-login-split-social .creatorreactor-fanvue-oauth-img {
	display: block;
	height: auto;
	max-width: min(220px, 100%);
	width: auto;
}
.creatorreactor-wp-login-social .creatorreactor-google-oauth-wrap {
	margin: 12px 0 0;
}
.creatorreactor-wp-login-social .creatorreactor-google-oauth-link {
	display: inline-block;
	line-height: 1.35;
	box-shadow: none;
	text-decoration: none;
	color: #1a73e8;
	border: 1px solid #dadce0;
	border-radius: 4px;
	padding: 10px 16px;
	font-weight: 600;
}
.creatorreactor-wp-login-social .creatorreactor-google-oauth-link[aria-disabled="true"] {
	cursor: not-allowed;
	opacity: 0.55;
	filter: grayscale(100%);
	pointer-events: none;
}
#login:has(.creatorreactor-wp-login-split) {
	max-width: 720px;
	width: 100%;
}
CSS;
		wp_add_inline_style( $style_handle, $css );

		wp_register_script( $script_handle, false, [], CREATORREACTOR_VERSION, true );
		wp_enqueue_script( $script_handle );
		$js = <<<'JS'
document.addEventListener("DOMContentLoaded",function(){var f=document.getElementById("loginform"),s=f&&f.querySelector(".creatorreactor-wp-login-social");if(!f||!s||!f.parentNode)return;var r=document.createElement("div");r.className="creatorreactor-wp-login-split";var fw=document.createElement("div");fw.className="creatorreactor-wp-login-split-form";var sw=document.createElement("div");sw.className="creatorreactor-wp-login-split-social";f.parentNode.insertBefore(r,f);fw.appendChild(f);sw.appendChild(s);r.appendChild(fw);r.appendChild(sw);});
JS;
		wp_add_inline_script( $script_handle, $js );
	}

	public static function render_social_login_markup() {
		echo '<div class="creatorreactor-wp-login-social">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode HTML (same as post content).
		echo do_shortcode( '[fanvue_login_button]' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode HTML (same as post content).
		echo do_shortcode( '[google_login_button]' );
		echo '</div>';
	}
}
