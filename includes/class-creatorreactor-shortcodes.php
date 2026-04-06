<?php
/**
 * Front-end shortcodes: tier gates and Fanvue / Google login controls.
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

	/** Solid fill for minimal Fanvue OAuth control (front-end, wp-login, admin preview). */
	public const FANVUE_MINIMAL_OAUTH_BACKGROUND = '#47e961';

	/** Minimal control: logo box (matches Google compact padding). */
	public const FANVUE_MINIMAL_LINK_PADDING_PX = 9;

	/** Minimal control: `fanvue-logo.webp` display size (transparent glyph on {@see FANVUE_MINIMAL_OAUTH_BACKGROUND}). */
	public const FANVUE_MINIMAL_ICON_SIZE_PX = 24;

	/**
	 * Optical vertical nudge for minimal Fanvue mark (px). Negative shifts the glyph up inside the square box.
	 */
	public const FANVUE_MINIMAL_ICON_TRANSLATE_Y_PX = -2;

	/**
	 * Instagram-style gradient for admin previews (approximates Meta brand palette). For production marks use assets from Meta’s Brand Resource Center.
	 *
	 * @link https://www.meta.com/brand/resources/instagram/instagram-brand/
	 */
	public const INSTAGRAM_BRAND_GRADIENT_CSS = 'linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)';

	/** Minimal Instagram-style preview control padding (matches Google compact). */
	public const INSTAGRAM_MINIMAL_LINK_PADDING_PX = 9;

	/** Glyph size in minimal Instagram-style preview (px; matches Google compact icon). */
	public const INSTAGRAM_MINIMAL_ICON_SIZE_PX = 22;

	/** Optical vertical nudge for minimal Instagram-style glyph in the square box (px). */
	public const INSTAGRAM_MINIMAL_ICON_TRANSLATE_Y_PX = -1;

	/**
	 * OnlyFans / OFAuth-style admin preview: dark button (common pairing with creator blue accent).
	 */
	public const ONLYFANS_OAUTH_ADMIN_BUTTON_BG = '#0f0f0f';

	/** Accent border / glyph color for OnlyFans-style previews (brand primary). */
	public const ONLYFANS_OAUTH_ADMIN_ACCENT = '#00AFF0';

	public const ONLYFANS_MINIMAL_LINK_PADDING_PX = 9;

	public const ONLYFANS_MINIMAL_ICON_SIZE_PX = 24;

	/** Optical vertical nudge for minimal OnlyFans-style glyph (px). */
	public const ONLYFANS_MINIMAL_ICON_TRANSLATE_Y_PX = 0;

	/** @var bool */
	private static $fanvue_oauth_footer_style_scheduled = false;

	/** @var bool */
	private static $google_oauth_footer_style_scheduled = false;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ], 20 );
	}

	/**
	 * Run enclosing gate logic on HTML without building a shortcode string.
	 *
	 * Elementor (and other) markup often contains `]` in attributes/JSON; {@see do_shortcode()} on
	 * `[tag]…[/tag]` then mis-pairs delimiters and drops or corrupts output for every role.
	 *
	 * @param string      $tag     Registered shortcode tag.
	 * @param string|null $content Inner HTML.
	 * @return string
	 */
	public static function apply_enclosing_gate( $tag, $content = null ) {
		$tag = sanitize_key( (string) $tag );
		$inner = ( $content === null || $content === '' ) ? '' : (string) $content;
		switch ( $tag ) {
			case 'follower':
				return self::follower( [], $inner );
			case 'subscriber':
				return self::subscriber( [], $inner );
			case 'logged_out':
				return self::logged_out( [], $inner );
			case 'logged_in':
				return self::logged_in( [], $inner );
			case 'fanvue_connected':
				return self::fanvue_connected( [], $inner );
			case 'fanvue_not_connected':
				return self::fanvue_not_connected( [], $inner );
			default:
				return $inner;
		}
	}

	public static function register() {
		add_shortcode( 'follower', [ __CLASS__, 'follower' ] );
		add_shortcode( 'subscriber', [ __CLASS__, 'subscriber' ] );
		add_shortcode( 'logged_out', [ __CLASS__, 'logged_out' ] );
		add_shortcode( 'logged_in', [ __CLASS__, 'logged_in' ] );
		add_shortcode( 'has_tier', [ __CLASS__, 'has_tier' ] );
		add_shortcode( 'fanvue_connected', [ __CLASS__, 'fanvue_connected' ] );
		add_shortcode( 'fanvue_not_connected', [ __CLASS__, 'fanvue_not_connected' ] );
		add_shortcode( 'standard_fanvue_login_button', [ __CLASS__, 'standard_fanvue_login_button' ] );
		add_shortcode( 'minimal_fanvue_login_button', [ __CLASS__, 'minimal_fanvue_login_button' ] );
		add_shortcode( 'standard_google_login_button', [ __CLASS__, 'standard_google_login_button' ] );
		add_shortcode( 'minimal_google_login_button', [ __CLASS__, 'minimal_google_login_button' ] );
		// Legacy aliases (same as standard_*).
		add_shortcode( 'fanvue_login_button', [ __CLASS__, 'fanvue_oauth' ] );
		add_shortcode( 'google_login_button', [ __CLASS__, 'google_oauth' ] );
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
		$css   = '.creatorreactor-fanvue-oauth-link{cursor:pointer;pointer-events:auto;line-height:1.35;text-decoration:none;color:#8e2d77}'
			. '.creatorreactor-fanvue-oauth-text{display:block;margin-top:8px;font-size:14px;font-weight:600;text-align:center}'
			. '.creatorreactor-fanvue-oauth-link[aria-disabled="true"]{cursor:not-allowed;pointer-events:none;opacity:.55;filter:grayscale(100%)}'
			. '.creatorreactor-fanvue-oauth-wrap--minimal{margin:0;text-align:inherit}'
			. '.creatorreactor-fanvue-oauth-link--minimal{display:inline-flex;align-items:center;justify-content:center;padding:' . (int) self::FANVUE_MINIMAL_LINK_PADDING_PX . 'px;border:0;border-radius:4px;background:' . self::FANVUE_MINIMAL_OAUTH_BACKGROUND . ';text-decoration:none;box-sizing:border-box;line-height:0}'
			. '.creatorreactor-fanvue-oauth-link--minimal .creatorreactor-fanvue-oauth-img-minimal{display:block;width:' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . 'px;height:' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . 'px;object-fit:contain;flex-shrink:0;transform:translateY(' . (int) self::FANVUE_MINIMAL_ICON_TRANSLATE_Y_PX . 'px)}'
			. '.creatorreactor-fanvue-oauth-link--minimal[aria-disabled="true"]{opacity:.55;filter:grayscale(100%)}';
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
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		$uid = get_current_user_id();
		// Strict role-only behavior:
		// - subscriber role should not receive follower content
		// - follower role receives follower content
		$user  = get_userdata( $uid );
		$roles = ( $user instanceof \WP_User ) ? Role_Impersonation::get_effective_role_slugs_for_user( $user ) : [];
		$has_subscriber_role = in_array( 'creatorreactor_subscriber', $roles, true );
		$has_follower_role   = in_array( 'creatorreactor_follower', $roles, true );

		if ( $has_subscriber_role ) {
			return '';
		}
		if ( $has_follower_role ) {
			return self::render_enclosed( $content );
		}
		return '';
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null          $content Enclosed content.
	 */
	public static function subscriber( $atts, $content = null ) {
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		$uid = get_current_user_id();
		// Strict role-only behavior:
		// - only creatorreactor_subscriber role receives subscriber content
		// - follower role should not receive subscriber content
		$user  = get_userdata( $uid );
		$roles = ( $user instanceof \WP_User ) ? Role_Impersonation::get_effective_role_slugs_for_user( $user ) : [];
		$has_subscriber_role = in_array( 'creatorreactor_subscriber', $roles, true );
		$has_follower_role   = in_array( 'creatorreactor_follower', $roles, true );

		if ( $has_subscriber_role ) {
			return self::render_enclosed( $content );
		}
		if ( $has_follower_role ) {
			return '';
		}
		return '';
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function logged_out( $atts, $content = null ) {
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function logged_in( $atts, $content = null ) {
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function has_tier( $atts, $content = null ) {
		// Deprecated: visibility logic is role-based only for now.
		return '';
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 * @param string|null           $content Enclosed content.
	 */
	public static function fanvue_connected( $atts, $content = null ) {
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
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
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked === '1' || $linked === 1 || $linked === true ) {
			return '';
		}
		return self::render_enclosed( $content );
	}

	/**
	 * @param array<string, string> $atts Attributes. Optional `variant`: standard (default) or minimal.
	 */
	public static function standard_fanvue_login_button( $atts = [] ) {
		return self::fanvue_oauth( array_merge( (array) $atts, [ 'variant' => 'standard' ] ) );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 */
	public static function minimal_fanvue_login_button( $atts = [] ) {
		return self::fanvue_oauth( array_merge( (array) $atts, [ 'variant' => 'minimal' ] ) );
	}

	/**
	 * @param array<string, string> $atts Attributes. Optional `variant`: standard (default) or minimal.
	 */
	public static function standard_google_login_button( $atts = [] ) {
		return self::google_oauth( array_merge( (array) $atts, [ 'variant' => 'standard' ] ) );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 */
	public static function minimal_google_login_button( $atts = [] ) {
		return self::google_oauth( array_merge( (array) $atts, [ 'variant' => 'minimal' ] ) );
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 */
	public static function fanvue_oauth( $atts = [] ) {
		$default_variant = Admin_Settings::get_fanvue_login_button_variant();
		$atts            = \shortcode_atts( [ 'variant' => $default_variant ], $atts, '' );
		$variant         = ( isset( $atts['variant'] ) && $atts['variant'] === 'minimal' ) ? 'minimal' : 'standard';

		if ( Admin_Settings::is_broker_mode() ) {
			return '<p class="creatorreactor-fanvue-oauth-unavailable">' . esc_html__( 'Login with Fanvue is not available in Agency (broker) mode.', 'wp-creatorreactor' ) . '</p>';
		}

		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$dashboard = admin_url();
			$home      = home_url( '/' );
			return '<p class="creatorreactor-fanvue-oauth-wrap creatorreactor-fanvue-oauth-logged-in">'
				. esc_html__( 'You are already signed in.', 'wp-creatorreactor' ) . ' '
				. '<a href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Dashboard', 'wp-creatorreactor' ) . '</a>'
				. ' <span class="creatorreactor-fanvue-oauth-logged-in-sep" aria-hidden="true">—</span> '
				. '<a href="' . esc_url( wp_logout_url( $home ) ) . '">' . esc_html__( 'Log out', 'wp-creatorreactor' ) . '</a>'
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

		$label          = __( 'Log in with Fanvue', 'wp-creatorreactor' );
		$site_connected = Admin_Settings::is_connected();

		if ( $variant === 'minimal' ) {
			$link_class = 'creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth-link--minimal';
			$wrap_class = 'creatorreactor-fanvue-oauth-wrap creatorreactor-fanvue-oauth-wrap--minimal';
			$link_attrs = 'class="' . esc_attr( $link_class ) . '" aria-label="' . esc_attr( $label ) . '"';
			if ( ! $site_connected ) {
				$link_attrs .= ' aria-disabled="true" role="button" tabindex="-1"';
			} else {
				$link_attrs .= ' href="' . esc_url( $href ) . '"';
			}
			$logo_url = CREATORREACTOR_PLUGIN_URL . 'img/fanvue-logo.webp';
			return '<p class="' . esc_attr( $wrap_class ) . '">'
				. '<a ' . $link_attrs . '>'
				. '<img src="' . esc_url( $logo_url ) . '" alt="" class="creatorreactor-fanvue-oauth-img-minimal" width="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" height="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" decoding="async" />'
				. '</a></p>';
		}

		$img_url    = CREATORREACTOR_PLUGIN_URL . 'img/login-fanvue.webp';
		$link_attrs = 'class="creatorreactor-fanvue-oauth-link" aria-label="' . esc_attr( $label ) . '"';
		if ( ! $site_connected ) {
			$link_attrs .= ' aria-disabled="true" role="button" tabindex="-1"';
		} else {
			$link_attrs .= ' href="' . esc_url( $href ) . '"';
		}

		return '<p class="creatorreactor-fanvue-oauth-wrap">'
			. '<a ' . $link_attrs . '>'
			. '<img src="' . esc_url( $img_url ) . '" alt="" class="creatorreactor-fanvue-oauth-img" width="220" decoding="async" />'
			. '<span class="creatorreactor-fanvue-oauth-text">' . esc_html( $label ) . '</span>'
			. '</a></p>';
	}

	/**
	 * Admin settings: non-interactive preview of standard vs minimal Fanvue login control.
	 *
	 * @param string $variant `standard` or `minimal`.
	 * @return string HTML
	 */
	public static function fanvue_oauth_admin_preview_chip( $variant ) {
		$variant = ( $variant === 'minimal' ) ? 'minimal' : 'standard';
		$label   = __( 'Log in with Fanvue', 'wp-creatorreactor' );
		if ( $variant === 'minimal' ) {
			$logo = CREATORREACTOR_PLUGIN_URL . 'img/fanvue-logo.webp';
			return '<span class="creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth-link--minimal creatorreactor-fanvue-oauth--admin-preview">'
				. '<img src="' . esc_url( $logo ) . '" alt="" class="creatorreactor-fanvue-oauth-img-minimal" width="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" height="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" decoding="async" />'
				. '</span>';
		}
		$banner = CREATORREACTOR_PLUGIN_URL . 'img/login-fanvue.webp';
		return '<span class="creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth--admin-preview">'
			. '<img src="' . esc_url( $banner ) . '" alt="" class="creatorreactor-fanvue-oauth-img" width="200" decoding="async" />'
			. '<span class="creatorreactor-fanvue-oauth-text">' . esc_html( $label ) . '</span>'
			. '</span>';
	}

	/**
	 * Instagram camera glyph for admin previews (white outline + dot; pair with {@see INSTAGRAM_BRAND_GRADIENT_CSS}).
	 * Proportions follow Meta’s Instagram glyph; use official assets from the Brand Resource Center for print.
	 *
	 * @param int $size Width/height in px.
	 * @return string Raw SVG markup.
	 * @link https://www.meta.com/brand/resources/instagram/instagram-brand/
	 */
	private static function instagram_preview_glyph_svg( $size ) {
		$w = (int) $size;
		return '<svg class="creatorreactor-instagram-oauth-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $w . '" height="' . $w . '" fill="none" aria-hidden="true" focusable="false">'
			. '<rect x="2.25" y="2.25" width="19.5" height="19.5" rx="5" ry="5" stroke="#ffffff" stroke-width="1.75" />'
			. '<circle cx="12" cy="12" r="4.25" stroke="#ffffff" stroke-width="1.75" />'
			. '<circle cx="17.25" cy="6.75" r="1.35" fill="#ffffff" />'
			. '</svg>';
	}

	/**
	 * Admin settings: non-interactive preview of standard vs minimal Instagram-style login control (gradient per Meta palette).
	 *
	 * @param string $variant `standard` or `minimal`.
	 * @return string HTML
	 */
	public static function instagram_oauth_admin_preview_chip( $variant ) {
		$variant = ( $variant === 'minimal' ) ? 'minimal' : 'standard';
		$label   = __( 'Login with Instagram', 'wp-creatorreactor' );
		if ( $variant === 'minimal' ) {
			return '<span class="creatorreactor-instagram-oauth-link creatorreactor-instagram-oauth-link--minimal creatorreactor-instagram-oauth--admin-preview">'
				. self::instagram_preview_glyph_svg( self::INSTAGRAM_MINIMAL_ICON_SIZE_PX )
				. '</span>';
		}
		return '<span class="creatorreactor-instagram-oauth-link creatorreactor-instagram-oauth-link--standard creatorreactor-instagram-oauth--admin-preview">'
			. self::instagram_preview_glyph_svg( 20 )
			. '<span class="creatorreactor-instagram-oauth-label">' . esc_html( $label ) . '</span>'
			. '</span>';
	}

	/**
	 * OnlyFans logo mark for admin previews (single-color; pair with brand guidelines).
	 *
	 * Path/viewBox match the widely used vector mark (e.g. Simple Icons, CC0).
	 *
	 * @param int    $size Width/height in px.
	 * @param string $fill CSS color for fill.
	 * @return string Raw SVG markup.
	 * @link https://onlyfans.com/brand
	 */
	private static function onlyfans_preview_glyph_svg( $size, $fill ) {
		$w = (int) $size;
		$f = preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $fill ) ? (string) $fill : '#00AFF0';
		return '<svg class="creatorreactor-onlyfans-oauth-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $w . '" height="' . $w . '" fill="' . $f . '" aria-hidden="true" focusable="false">'
			. '<path d="M24 4.003h-4.015c-3.45 0-5.3.197-6.748 1.957a7.996 7.996 0 1 0 2.103 9.211c3.182-.231 5.39-2.134 6.085-5.173 0 0-2.399.585-4.43 0 4.018-.777 6.333-3.037 7.005-5.995zM5.61 11.999A2.391 2.391 0 0 1 9.28 9.97a2.966 2.966 0 0 1 2.998-2.528h.008c-.92 1.778-1.407 3.352-1.998 5.263A2.392 2.392 0 0 1 5.61 12Zm2.386-7.996a7.996 7.996 0 1 0 7.996 7.996 7.996 7.996 0 0 0-7.996-7.996Zm0 10.394A2.399 2.399 0 1 1 10.395 12a2.396 2.396 0 0 1-2.399 2.398Z"/>'
			. '</svg>';
	}

	/**
	 * Admin settings: non-interactive preview of standard vs minimal OnlyFans / OFAuth-style control.
	 *
	 * @param string $variant `standard` or `minimal`.
	 * @return string HTML
	 */
	public static function onlyfans_oauth_admin_preview_chip( $variant ) {
		$variant = ( $variant === 'minimal' ) ? 'minimal' : 'standard';
		$label   = __( 'Link OnlyFans account', 'wp-creatorreactor' );
		$accent  = self::ONLYFANS_OAUTH_ADMIN_ACCENT;
		if ( $variant === 'minimal' ) {
			return '<span class="creatorreactor-onlyfans-oauth-link creatorreactor-onlyfans-oauth-link--minimal creatorreactor-onlyfans-oauth--admin-preview">'
				. self::onlyfans_preview_glyph_svg( self::ONLYFANS_MINIMAL_ICON_SIZE_PX, $accent )
				. '</span>';
		}
		return '<span class="creatorreactor-onlyfans-oauth-link creatorreactor-onlyfans-oauth-link--standard creatorreactor-onlyfans-oauth--admin-preview">'
			. self::onlyfans_preview_glyph_svg( 22, $accent )
			. '<span class="creatorreactor-onlyfans-oauth-label">' . esc_html( $label ) . '</span>'
			. '</span>';
	}

	/**
	 * @param array<string, string> $atts Attributes.
	 */
	public static function google_oauth( $atts = [] ) {
		$atts    = \shortcode_atts( [ 'variant' => 'standard' ], $atts, '' );
		$variant = ( isset( $atts['variant'] ) && $atts['variant'] === 'minimal' ) ? 'minimal' : 'standard';

		if ( Admin_Settings::is_broker_mode() ) {
			return '<p class="creatorreactor-google-oauth-unavailable">' . esc_html__( 'Sign in with Google is not available in Agency (broker) mode.', 'wp-creatorreactor' ) . '</p>';
		}

		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$dashboard = admin_url();
			$home      = home_url( '/' );
			return '<p class="creatorreactor-google-oauth-wrap creatorreactor-google-oauth-logged-in">'
				. esc_html__( 'You are already signed in.', 'wp-creatorreactor' ) . ' '
				. '<a href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Dashboard', 'wp-creatorreactor' ) . '</a>'
				. ' <span class="creatorreactor-google-oauth-logged-in-sep" aria-hidden="true">—</span> '
				. '<a href="' . esc_url( wp_logout_url( $home ) ) . '">' . esc_html__( 'Log out', 'wp-creatorreactor' ) . '</a>'
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
		$redirect_to = remove_query_arg( 'creatorreactor_google', $redirect_to );

		$rest_path = CreatorReactor_OAuth::REST_NAMESPACE . '/' . ltrim( Google_OAuth::REST_ROUTE_START, '/' );
		$rest_base = rest_url( $rest_path );
		if ( ! is_string( $rest_base ) || $rest_base === '' || ! preg_match( '#\Ahttps?://#i', $rest_base ) ) {
			$rest_base = add_query_arg( 'rest_route', '/' . trim( $rest_path, '/' ), home_url( '/' ) );
		}
		$start = add_query_arg(
			[
				'_wpnonce'    => wp_create_nonce( Google_OAuth::NONCE_ACTION ),
				'redirect_to' => $redirect_to,
			],
			$rest_base
		);
		if ( strlen( $start ) > 1900 ) {
			$start = add_query_arg(
				[
					'_wpnonce'    => wp_create_nonce( Google_OAuth::NONCE_ACTION ),
					'redirect_to' => home_url( '/' ),
				],
				$rest_base
			);
		}

		$href = esc_url( $start, [ 'http', 'https' ] );
		if ( $href === '' ) {
			$start = add_query_arg(
				'_wpnonce',
				wp_create_nonce( Google_OAuth::NONCE_ACTION ),
				$rest_base
			);
			$href = esc_url( $start, [ 'http', 'https' ] );
		}
		if ( $href === '' ) {
			$href = esc_url( esc_url_raw( $start ), [ 'http', 'https' ] );
		}

		self::schedule_google_oauth_footer_style();

		$label = __( 'Sign in with Google', 'wp-creatorreactor' );
		$style = $variant === 'minimal'
			? 'logo_only'
			: Admin_Settings::get_google_login_button_style();
		$configured     = Admin_Settings::is_google_login_configured();
		$link_class     = 'creatorreactor-google-oauth-link creatorreactor-google-oauth-style--' . sanitize_html_class( $style );
		$link_attrs     = 'class="' . esc_attr( $link_class ) . '" aria-label="' . esc_attr( $label ) . '"';
		if ( ! $configured ) {
			$link_attrs .= ' aria-disabled="true" role="button" tabindex="-1"';
		} else {
			$link_attrs .= ' href="' . esc_url( $href ) . '"';
		}

		$inner = self::google_oauth_button_inner_html( $style, $label );

		$wrap_class = 'creatorreactor-google-oauth-wrap';
		if ( $variant === 'minimal' ) {
			$wrap_class .= ' creatorreactor-google-oauth-wrap--minimal';
		}

		return '<p class="' . esc_attr( $wrap_class ) . '">'
			. '<a ' . $link_attrs . '>'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner uses esc_html for text; SVG is static trusted markup.
			. $inner
			. '</a></p>';
	}

	/**
	 * Admin settings: visual preview chip (not a link).
	 *
	 * @param string $style_slug Style slug from {@see Admin_Settings::google_login_button_style_slugs()}.
	 * @return string HTML
	 */
	public static function google_oauth_admin_preview_chip( $style_slug ) {
		$style_slug = Admin_Settings::sanitize_google_login_button_style( (string) $style_slug );
		$label      = __( 'Sign in with Google', 'wp-creatorreactor' );
		$class      = 'creatorreactor-google-oauth-link creatorreactor-google-oauth-style--' . sanitize_html_class( $style_slug ) . ' creatorreactor-google-oauth--admin-preview';
		$inner      = self::google_oauth_button_inner_html( $style_slug, $label );
		return '<span class="' . esc_attr( $class ) . '">'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner uses esc_html for text; SVG is static trusted markup.
			. $inner
			. '</span>';
	}

	/**
	 * Multicolor Google “G” mark (inline SVG).
	 *
	 * @return string
	 */
	private static function google_oauth_brand_svg() {
		return '<svg class="creatorreactor-google-oauth-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true" focusable="false">'
			. '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
			. '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6C44.43 37.96 46.98 31.87 46.98 24.55z"/>'
			. '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
			. '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
			. '<path fill="none" d="M0 0h48v48H0z"/>'
			. '</svg>';
	}

	/**
	 * @param string $style Sanitized style slug.
	 * @param string $label Accessible button label (translated).
	 * @return string Inner HTML for the Google control.
	 */
	private static function google_oauth_button_inner_html( $style, $label ) {
		$style = Admin_Settings::sanitize_google_login_button_style( $style );
		$svg   = self::google_oauth_brand_svg();
		if ( 'logo_only' === $style ) {
			return '<span class="creatorreactor-google-oauth-button-inner creatorreactor-google-oauth-button-inner--logo-only">' . $svg . '</span>';
		}
		return '<span class="creatorreactor-google-oauth-button-inner">'
			. '<span class="creatorreactor-google-oauth-icon">' . $svg . '</span>'
			. '<span class="creatorreactor-google-oauth-label">' . esc_html( $label ) . '</span>'
			. '</span>';
	}

	/**
	 * Shared CSS for Google login shortcodes and wp-login social column.
	 *
	 * @return string
	 */
	public static function get_google_oauth_button_css() {
		return <<<'CSS'
.creatorreactor-google-oauth-wrap {
	margin: 12px 0 0;
	text-align: center;
}
.creatorreactor-google-oauth-link {
	box-sizing: border-box;
	cursor: pointer;
	pointer-events: auto;
	line-height: 1.35;
	text-decoration: none;
	font-family: "Roboto", system-ui, -apple-system, "Segoe UI", sans-serif;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	vertical-align: middle;
}
.creatorreactor-google-oauth-link[aria-disabled="true"] {
	cursor: not-allowed;
	pointer-events: none;
	opacity: 0.55;
	filter: grayscale(100%);
}
.creatorreactor-google-oauth-svg {
	display: block;
	flex-shrink: 0;
}
.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--standard_light {
	color: #1f1f1f;
	background: #fff;
	border: 1px solid #747775;
	border-radius: 4px;
	padding: 10px 16px;
	font-weight: 500;
	font-size: 14px;
	gap: 0;
}
.creatorreactor-google-oauth-style--standard_light .creatorreactor-google-oauth-button-inner {
	display: inline-flex;
	align-items: center;
	gap: 12px;
}
.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--standard_dark {
	color: #e3e3e3;
	background: #131314;
	border: 1px solid #8e918f;
	border-radius: 4px;
	padding: 10px 16px;
	font-weight: 500;
	font-size: 14px;
	gap: 0;
}
.creatorreactor-google-oauth-style--standard_dark .creatorreactor-google-oauth-button-inner {
	display: inline-flex;
	align-items: center;
	gap: 12px;
}
.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--logo_only {
	padding: 11px;
	border: 1px solid #dadce0;
	border-radius: 4px;
	background: #fff;
}
.creatorreactor-google-oauth-style--logo_only .creatorreactor-google-oauth-button-inner--logo-only {
	display: flex;
	align-items: center;
	justify-content: center;
	line-height: 0;
}
.creatorreactor-google-oauth-style--logo_only .creatorreactor-google-oauth-svg {
	width: 22px;
	height: 22px;
}
.creatorreactor-wp-login-social .creatorreactor-google-oauth-wrap {
	margin: 12px 0 0;
}
.creatorreactor-google-oauth-wrap--minimal {
	margin: 0;
	text-align: inherit;
}
.creatorreactor-google-oauth-wrap--minimal .creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--logo_only {
	padding: 9px;
}
.creatorreactor-google-oauth-wrap--minimal .creatorreactor-google-oauth-style--logo_only .creatorreactor-google-oauth-svg {
	width: 20px;
	height: 20px;
}
CSS;
	}

	/**
	 * @return void
	 */
	private static function schedule_google_oauth_footer_style() {
		if ( self::$google_oauth_footer_style_scheduled ) {
			return;
		}
		self::$google_oauth_footer_style_scheduled = true;
		if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
			return;
		}
		$css   = self::get_google_oauth_button_css();
		$print = static function () use ( $css ) {
			echo '<style id="creatorreactor-google-oauth-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		};
		add_action( 'wp_footer', $print, 1 );
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
