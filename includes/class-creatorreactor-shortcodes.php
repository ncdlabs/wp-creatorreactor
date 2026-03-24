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

	/** @var bool */
	private static $fanvue_oauth_footer_style_scheduled = false;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ], 20 );
	}

	public static function register() {
		add_shortcode( 'follower', [ __CLASS__, 'follower' ] );
		add_shortcode( 'subscriber', [ __CLASS__, 'subscriber' ] );
		add_shortcode( 'logged_out', [ __CLASS__, 'logged_out' ] );
		add_shortcode( 'logged_in', [ __CLASS__, 'logged_in' ] );
		add_shortcode( 'logged_in_no_role', [ __CLASS__, 'logged_in_no_role' ] );
		add_shortcode( 'has_tier', [ __CLASS__, 'has_tier' ] );
		add_shortcode( 'onboarding_incomplete', [ __CLASS__, 'onboarding_incomplete' ] );
		add_shortcode( 'onboarding_complete', [ __CLASS__, 'onboarding_complete' ] );
		add_shortcode( 'fanvue_connected', [ __CLASS__, 'fanvue_connected' ] );
		add_shortcode( 'fanvue_not_connected', [ __CLASS__, 'fanvue_not_connected' ] );
		add_shortcode( 'fanvue_login_button', [ __CLASS__, 'fanvue_oauth' ] );
	}

	/**
	 * Never print &lt;style&gt; inside #loginform — it breaks the form in some browsers. Print in footer instead.
	 */
	private static function schedule_fanvue_oauth_footer_style() {
		if ( self::$fanvue_oauth_footer_style_scheduled ) {
			return;
		}
		self::$fanvue_oauth_footer_style_scheduled = true;
		// wp-login.php already inlines these rules in {@see Login_Page::enqueue_login_assets()}; avoid duplicate &lt;style&gt;.
		if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
			return;
		}
		$css   = '.creatorreactor-fanvue-oauth-link{cursor:pointer;pointer-events:auto;line-height:1.35;text-decoration:none;color:#2271b1}'
			. '.creatorreactor-fanvue-oauth-text{display:block;margin-top:8px;font-size:14px;font-weight:600;text-align:center}';
		$print = static function () use ( $css ) {
			echo '<style id="creatorreactor-fanvue-oauth-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		};
		add_action( 'wp_footer', $print, 1 );
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
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}
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
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}
		if ( ! Entitlements::wp_user_has_active_subscriber_entitlement( $uid ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function logged_out( $atts, $content = null ) {
		if ( is_user_logged_in() ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function logged_in( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function logged_in_no_role( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( self::user_has_any_active_entitlement( get_current_user_id() ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function has_tier( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}

		$atts    = is_array( $atts ) ? $atts : [];
		$parsed  = shortcode_atts( [ 'tier' => '', 'product' => '' ], $atts, 'has_tier' );
		$tier    = isset( $parsed['tier'] ) ? trim( sanitize_text_field( (string) $parsed['tier'] ) ) : '';
		$product = isset( $parsed['product'] ) ? trim( sanitize_text_field( (string) $parsed['product'] ) ) : '';

		$has_entitlement = Entitlements::check_user_entitlement(
			$uid,
			$tier !== '' ? $tier : null,
			$product !== '' ? $product : null
		);
		if ( ! $has_entitlement ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function onboarding_incomplete( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( ! Onboarding::user_needs_onboarding( get_current_user_id() ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function onboarding_complete( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( Onboarding::user_needs_onboarding( get_current_user_id() ) ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function fanvue_connected( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked !== '1' && $linked !== 1 && $linked !== true ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function fanvue_not_connected( $atts, $content = null ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked === '1' || $linked === 1 || $linked === true ) {
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

		if ( is_user_logged_in() ) {
			$dashboard = admin_url();
			$home      = home_url( '/' );
			return '<p class="creatorreactor-fanvue-oauth-wrap creatorreactor-fanvue-oauth-logged-in">'
				. esc_html__( 'You are already signed in.', 'creatorreactor' ) . ' '
				. '<a href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Dashboard', 'creatorreactor' ) . '</a>'
				. ' <span class="creatorreactor-fanvue-oauth-logged-in-sep" aria-hidden="true">—</span> '
				. '<a href="' . esc_url( wp_logout_url( $home ) ) . '">' . esc_html__( 'Log out', 'creatorreactor' ) . '</a>'
				. '</p>';
		}

		$redirect_to = '';
		if ( isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), '' );
		}
		if ( $redirect_to === '' && is_singular() ) {
			$redirect_to = get_permalink();
		}
		if ( ! is_string( $redirect_to ) || $redirect_to === '' ) {
			$on_wp_login = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
				&& stripos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false;
			if ( $on_wp_login ) {
				$redirect_to = admin_url();
				if ( isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) {
					$redirect_to = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), $redirect_to );
				}
			} elseif ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
				$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			} else {
				$redirect_to = home_url( '/' );
			}
		}
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		$redirect_to = Plugin::normalize_url_path_slashes( $redirect_to );
		$redirect_to = remove_query_arg( 'creatorreactor_fanvue', $redirect_to );

		$rest_path = CreatorReactor_OAuth::REST_NAMESPACE . '/' . ltrim( Fan_OAuth::REST_ROUTE_START, '/' );
		$rest_base = rest_url( $rest_path );
		if ( ! is_string( $rest_base ) || $rest_base === '' || ! preg_match( '#\Ahttps?://#i', $rest_base ) ) {
			$rest_base = add_query_arg( 'rest_route', '/' . trim( $rest_path, '/' ), home_url( '/' ) );
		}
		// add_query_arg encodes values; do not pre-rawurlencode( redirect_to ) — that doubles encoding and can exceed esc_url() limits (empty href).
		$start = add_query_arg(
			[
				'_wpnonce'    => wp_create_nonce( 'creatorreactor_fan_oauth' ),
				'redirect_to' => $redirect_to,
			],
			$rest_base
		);
		if ( strlen( $start ) > 1900 ) {
			$start = add_query_arg(
				[
					'_wpnonce'    => wp_create_nonce( 'creatorreactor_fan_oauth' ),
					'redirect_to' => home_url( '/' ),
				],
				$rest_base
			);
		}

		$href = esc_url( $start, [ 'http', 'https' ] );
		if ( $href === '' ) {
			$start = add_query_arg(
				'_wpnonce',
				wp_create_nonce( 'creatorreactor_fan_oauth' ),
				$rest_base
			);
			$href = esc_url( $start, [ 'http', 'https' ] );
		}
		if ( $href === '' ) {
			$href = esc_url( esc_url_raw( $start ), [ 'http', 'https' ] );
		}

		self::schedule_fanvue_oauth_footer_style();

		$img_url = CREATORREACTOR_PLUGIN_URL . 'img/login-fanvue.webp';
		$label   = __( 'Log in with Fanvue', 'creatorreactor' );

		return '<p class="creatorreactor-fanvue-oauth-wrap">'
			. '<a class="creatorreactor-fanvue-oauth-link" href="' . esc_url( $href ) . '" aria-label="' . esc_attr( $label ) . '">'
			. '<img src="' . esc_url( $img_url ) . '" alt="" class="creatorreactor-fanvue-oauth-img" width="220" decoding="async" />'
			. '<span class="creatorreactor-fanvue-oauth-text">' . esc_html( $label ) . '</span>'
			. '</a></p>';
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

	/**
	 * @param int $user_id WordPress user ID.
	 */
	private static function user_has_any_active_entitlement( $user_id ) {
		$rows = Entitlements::get_active_entitlement_rows_for_wp_user( (int) $user_id );
		return ! empty( $rows );
	}
}
