<?php
/**
 * Front-end shortcodes: tier gates and Fanvue / Google / social OAuth login controls.
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

	/**
	 * Outer width/height for minimal (compact) OAuth controls — same for Fanvue, Google logo-only, Instagram, OnlyFans.
	 */
	public const OAUTH_COMPACT_BOX_PX = 44;

	/**
	 * Logo/glyph size inside compact controls ({@see OAUTH_COMPACT_BOX_PX}).
	 */
	public const OAUTH_COMPACT_ICON_PX = 24;

	/** @deprecated Padding is not used for minimal controls; outer size is {@see OAUTH_COMPACT_BOX_PX}. Kept for compatibility. */
	public const FANVUE_MINIMAL_LINK_PADDING_PX = 9;

	/** Minimal control: `fanvue-logo.webp` display size (transparent glyph on {@see FANVUE_MINIMAL_OAUTH_BACKGROUND}). */
	public const FANVUE_MINIMAL_ICON_SIZE_PX = self::OAUTH_COMPACT_ICON_PX;

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

	/** @deprecated See {@see OAUTH_COMPACT_BOX_PX}. */
	public const INSTAGRAM_MINIMAL_LINK_PADDING_PX = 9;

	/** Glyph size in minimal Instagram-style preview. */
	public const INSTAGRAM_MINIMAL_ICON_SIZE_PX = self::OAUTH_COMPACT_ICON_PX;

	/** Optical vertical nudge for minimal Instagram-style glyph in the square box (px). */
	public const INSTAGRAM_MINIMAL_ICON_TRANSLATE_Y_PX = -1;

	/**
	 * OnlyFans / OFAuth-style admin preview: dark button (common pairing with creator blue accent).
	 */
	public const ONLYFANS_OAUTH_ADMIN_BUTTON_BG = '#0f0f0f';

	/** Accent border / glyph color for OnlyFans-style previews (brand primary). */
	public const ONLYFANS_OAUTH_ADMIN_ACCENT = '#00AFF0';

	/** @deprecated See {@see OAUTH_COMPACT_BOX_PX}. */
	public const ONLYFANS_MINIMAL_LINK_PADDING_PX = 9;

	public const ONLYFANS_MINIMAL_ICON_SIZE_PX = self::OAUTH_COMPACT_ICON_PX;

	/** Optical vertical nudge for minimal OnlyFans-style glyph (px). */
	public const ONLYFANS_MINIMAL_ICON_TRANSLATE_Y_PX = 0;

	/** @var bool */
	private static $fanvue_oauth_footer_style_scheduled = false;

	/** @var bool */
	private static $google_oauth_footer_style_scheduled = false;

	/** @var bool */
	private static $social_oauth_footer_style_scheduled = false;

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

		foreach ( Admin_Settings::all_settings_social_oauth_slugs() as $social_slug ) {
			add_shortcode(
				'standard_' . $social_slug . '_login_button',
				static function ( $atts ) use ( $social_slug ) {
					return Shortcodes::social_oauth_login( $social_slug, array_merge( (array) $atts, [ 'variant' => 'standard' ] ) );
				}
			);
			add_shortcode(
				'minimal_' . $social_slug . '_login_button',
				static function ( $atts ) use ( $social_slug ) {
					return Shortcodes::social_oauth_login( $social_slug, array_merge( (array) $atts, [ 'variant' => 'minimal' ] ) );
				}
			);
			add_shortcode(
				$social_slug . '_login_button',
				static function ( $atts ) use ( $social_slug ) {
					return Shortcodes::social_oauth_login( $social_slug, (array) $atts );
				}
			);
		}
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
		$box = (int) self::OAUTH_COMPACT_BOX_PX;
		$css = '.creatorreactor-fanvue-oauth-link{cursor:pointer;pointer-events:auto;line-height:1.35;text-decoration:none;color:#8e2d77}'
			. '.creatorreactor-fanvue-oauth-link[aria-disabled="true"]{cursor:not-allowed;pointer-events:none;opacity:.55;filter:grayscale(100%)}'
			. '.creatorreactor-fanvue-oauth-wrap--minimal{margin:0;text-align:inherit}'
			. '.creatorreactor-fanvue-oauth-link--minimal{display:inline-flex;align-items:center;justify-content:center;width:' . $box . 'px;height:' . $box . 'px;min-width:' . $box . 'px;min-height:' . $box . 'px;padding:0;border:0;border-radius:4px;background:' . self::FANVUE_MINIMAL_OAUTH_BACKGROUND . ';text-decoration:none;box-sizing:border-box;line-height:0}'
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
		if ( ! Admin_Settings::is_fanvue_oauth_settings_configured() ) {
			return '';
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

		$label = __( 'Log in with Fanvue', 'wp-creatorreactor' );

		if ( $variant === 'minimal' ) {
			$link_class = 'creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth-link--minimal';
			$wrap_class = 'creatorreactor-fanvue-oauth-wrap creatorreactor-fanvue-oauth-wrap--minimal';
			$link_attrs = 'class="' . esc_attr( $link_class ) . '" aria-label="' . esc_attr( $label ) . '"';
			$link_attrs .= ' href="' . esc_url( $href ) . '"';
			$logo_url = CREATORREACTOR_PLUGIN_URL . 'img/fanvue-logo.webp';
			return '<p class="' . esc_attr( $wrap_class ) . '">'
				. '<a ' . $link_attrs . '>'
				. '<img src="' . esc_url( $logo_url ) . '" alt="" class="creatorreactor-fanvue-oauth-img-minimal" width="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" height="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" decoding="async" />'
				. '</a></p>';
		}

		$img_url    = CREATORREACTOR_PLUGIN_URL . 'img/login-fanvue.webp';
		$link_attrs = 'class="creatorreactor-fanvue-oauth-link" aria-label="' . esc_attr( $label ) . '"';
		$link_attrs .= ' href="' . esc_url( $href ) . '"';

		return '<p class="creatorreactor-fanvue-oauth-wrap">'
			. '<a ' . $link_attrs . '>'
			. '<img src="' . esc_url( $img_url ) . '" alt="" class="creatorreactor-fanvue-oauth-img" width="220" decoding="async" />'
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
		if ( $variant === 'minimal' ) {
			$logo = CREATORREACTOR_PLUGIN_URL . 'img/fanvue-logo.webp';
			return '<span class="creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth-link--minimal creatorreactor-fanvue-oauth--admin-preview">'
				. '<img src="' . esc_url( $logo ) . '" alt="" class="creatorreactor-fanvue-oauth-img-minimal" width="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" height="' . (int) self::FANVUE_MINIMAL_ICON_SIZE_PX . '" decoding="async" />'
				. '</span>';
		}
		$banner = CREATORREACTOR_PLUGIN_URL . 'img/login-fanvue.webp';
		return '<span class="creatorreactor-fanvue-oauth-link creatorreactor-fanvue-oauth--admin-preview">'
			. '<img src="' . esc_url( $banner ) . '" alt="" class="creatorreactor-fanvue-oauth-img" width="220" decoding="async" />'
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
		if ( $variant === 'minimal' ) {
			return '<span class="creatorreactor-instagram-oauth-link creatorreactor-instagram-oauth-link--minimal creatorreactor-instagram-oauth--admin-preview">'
				. self::instagram_preview_glyph_svg( self::INSTAGRAM_MINIMAL_ICON_SIZE_PX )
				. '</span>';
		}
		return '<span class="creatorreactor-instagram-oauth-link creatorreactor-instagram-oauth-link--standard creatorreactor-instagram-oauth--admin-preview">'
			. self::instagram_preview_glyph_svg( 20 )
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
		$accent  = self::ONLYFANS_OAUTH_ADMIN_ACCENT;
		if ( $variant === 'minimal' ) {
			return '<span class="creatorreactor-onlyfans-oauth-link creatorreactor-onlyfans-oauth-link--minimal creatorreactor-onlyfans-oauth--admin-preview">'
				. self::onlyfans_preview_glyph_svg( self::ONLYFANS_MINIMAL_ICON_SIZE_PX, $accent )
				. '</span>';
		}
		return '<span class="creatorreactor-onlyfans-oauth-link creatorreactor-onlyfans-oauth-link--standard creatorreactor-onlyfans-oauth--admin-preview">'
			. self::onlyfans_preview_glyph_svg( 20, $accent )
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
		if ( ! Admin_Settings::is_google_login_configured() ) {
			return '';
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
		$link_class     = 'creatorreactor-google-oauth-link creatorreactor-google-oauth-style--' . sanitize_html_class( $style );
		$link_attrs     = 'class="' . esc_attr( $link_class ) . '" aria-label="' . esc_attr( $label ) . '"';
		$link_attrs .= ' href="' . esc_url( $href ) . '"';

		$inner = self::google_oauth_button_inner_html( $style );

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
		$class      = 'creatorreactor-google-oauth-link creatorreactor-google-oauth-style--' . sanitize_html_class( $style_slug ) . ' creatorreactor-google-oauth--admin-preview';
		$inner      = self::google_oauth_button_inner_html( $style_slug );
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
		return '<svg class="creatorreactor-google-oauth-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
			. '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
			. '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6C44.43 37.96 46.98 31.87 46.98 24.55z"/>'
			. '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
			. '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
			. '<path fill="none" d="M0 0h48v48H0z"/>'
			. '</svg>';
	}

	/**
	 * @param string $style Sanitized style slug.
	 * @return string Inner HTML for the Google control (brand mark only; use link aria-label for accessibility).
	 */
	private static function google_oauth_button_inner_html( $style ) {
		$style = Admin_Settings::sanitize_google_login_button_style( $style );
		$svg   = self::google_oauth_brand_svg();
		if ( 'logo_only' === $style ) {
			return '<span class="creatorreactor-google-oauth-button-inner creatorreactor-google-oauth-button-inner--logo-only">' . $svg . '</span>';
		}
		return '<span class="creatorreactor-google-oauth-button-inner">'
			. '<span class="creatorreactor-google-oauth-icon">' . $svg . '</span>'
			. '</span>';
	}

	/**
	 * Shared CSS for Google login shortcodes and wp-login social column.
	 *
	 * @return string
	 */
	public static function get_google_oauth_button_css() {
		$box  = (int) self::OAUTH_COMPACT_BOX_PX;
		$cico = (int) self::OAUTH_COMPACT_ICON_PX;
		return implode(
			"\n",
			[
				'.creatorreactor-google-oauth-wrap {',
				"\tmargin: 12px 0 0;",
				"\ttext-align: center;",
				'}',
				'.creatorreactor-google-oauth-link {',
				"\tbox-sizing: border-box;",
				"\tcursor: pointer;",
				"\tpointer-events: auto;",
				"\tline-height: 1.35;",
				"\ttext-decoration: none;",
				"\tfont-family: \"Roboto\", system-ui, -apple-system, \"Segoe UI\", sans-serif;",
				"\tdisplay: inline-flex;",
				"\talign-items: center;",
				"\tjustify-content: center;",
				"\tvertical-align: middle;",
				'}',
				'.creatorreactor-google-oauth-link[aria-disabled="true"] {',
				"\tcursor: not-allowed;",
				"\tpointer-events: none;",
				"\topacity: 0.55;",
				"\tfilter: grayscale(100%);",
				'}',
				'.creatorreactor-google-oauth-svg {',
				"\tdisplay: block;",
				"\tflex-shrink: 0;",
				'}',
				'.creatorreactor-google-oauth-icon .creatorreactor-google-oauth-svg {',
				"\twidth: 20px;",
				"\theight: 20px;",
				'}',
				'.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--standard_light {',
				"\tcolor: #1f1f1f;",
				"\tbackground: #fff;",
				"\tborder: 1px solid #747775;",
				"\tborder-radius: 4px;",
				"\tpadding: 10px 16px;",
				"\tmin-height: 40px;",
				"\tbox-sizing: border-box;",
				"\tfont-weight: 500;",
				"\tfont-size: 14px;",
				"\tgap: 0;",
				'}',
				'.creatorreactor-google-oauth-style--standard_light .creatorreactor-google-oauth-button-inner {',
				"\tdisplay: inline-flex;",
				"\talign-items: center;",
				"\tgap: 12px;",
				'}',
				'.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--standard_dark {',
				"\tcolor: #e3e3e3;",
				"\tbackground: #131314;",
				"\tborder: 1px solid #8e918f;",
				"\tborder-radius: 4px;",
				"\tpadding: 10px 16px;",
				"\tmin-height: 40px;",
				"\tbox-sizing: border-box;",
				"\tfont-weight: 500;",
				"\tfont-size: 14px;",
				"\tgap: 0;",
				'}',
				'.creatorreactor-google-oauth-style--standard_dark .creatorreactor-google-oauth-button-inner {',
				"\tdisplay: inline-flex;",
				"\talign-items: center;",
				"\tgap: 12px;",
				'}',
				'.creatorreactor-google-oauth-link.creatorreactor-google-oauth-style--logo_only {',
				"\tdisplay: inline-flex;",
				"\talign-items: center;",
				"\tjustify-content: center;",
				"\twidth: {$box}px;",
				"\theight: {$box}px;",
				"\tmin-width: {$box}px;",
				"\tmin-height: {$box}px;",
				"\tpadding: 0;",
				"\tborder: 1px solid #dadce0;",
				"\tborder-radius: 4px;",
				"\tbackground: #fff;",
				"\tbox-sizing: border-box;",
				'}',
				'.creatorreactor-google-oauth-style--logo_only .creatorreactor-google-oauth-button-inner--logo-only {',
				"\tdisplay: flex;",
				"\talign-items: center;",
				"\tjustify-content: center;",
				"\tline-height: 0;",
				'}',
				'.creatorreactor-google-oauth-style--logo_only .creatorreactor-google-oauth-svg {',
				"\twidth: {$cico}px;",
				"\theight: {$cico}px;",
				'}',
				'.creatorreactor-wp-login-social .creatorreactor-google-oauth-wrap {',
				"\tmargin: 12px 0 0;",
				'}',
				'.creatorreactor-google-oauth-wrap--minimal {',
				"\tmargin: 0;",
				"\ttext-align: inherit;",
				'}',
			]
		);
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
	 * Human-readable provider name for labels.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function social_oauth_provider_label( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( 'bluesky' === $slug ) {
			return __( 'Bluesky', 'wp-creatorreactor' );
		}
		$cfg = Social_OAuth_Registry::get_config( $slug );
		if ( is_array( $cfg ) && ! empty( $cfg['label'] ) ) {
			return (string) $cfg['label'];
		}
		return $slug;
	}

	/**
	 * @param string $slug Provider slug.
	 * @return string Raw SVG (24×24 viewBox).
	 */
	private static function social_oauth_brand_glyph_svg( $slug ) {
		$slug = sanitize_key( (string) $slug );
		$w    = (int) self::OAUTH_COMPACT_ICON_PX;
		$svg  = '<svg class="creatorreactor-social-oauth-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $w . '" height="' . $w . '" aria-hidden="true" focusable="false">';
		switch ( $slug ) {
			case 'tiktok':
				$svg .= '<path fill="currentColor" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>';
				break;
			case 'x_twitter':
				$svg .= '<path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>';
				break;
			case 'snapchat':
				$svg .= '<path fill="currentColor" d="M12.206.793c.99 0 4.347.276 5.93 3.821.529 1.193.403 3.219.299 4.847l-.003.06c-.012.18-.022.345-.03.51.075.045.203.09.401.09.3-.016.659-.12 1.033-.301.165-.08.287-.121.45-.121.227 0 .42.15.477.36.045.18.015.39-.12.57-.615.75-1.433 1.161-2.214 1.161-.165 0-.33-.015-.48-.06-.03 3.001-1.161 5.132-3.001 6.313-1.05.69-2.313 1.05-3.751 1.05-1.47 0-2.751-.36-3.811-1.05-1.83-1.181-2.97-3.312-3-6.312-.15.045-.315.06-.48.06-.78 0-1.59-.411-2.22-1.161-.135-.18-.165-.39-.12-.57.06-.21.255-.36.48-.36.165 0 .285.045.45.12.375.181.735.285 1.035.301.195 0 .33-.045.405-.09-.015-.165-.03-.33-.045-.51l-.003-.06c-.105-1.628-.231-3.654.298-4.847C7.851 1.069 11.217.793 12.207.793z"/>';
				break;
			case 'linkedin':
				$svg .= '<path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>';
				break;
			case 'pinterest':
				$svg .= '<path fill="currentColor" d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>';
				break;
			case 'reddit':
				$svg .= '<path fill="currentColor" d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52 4.718 4.718 0 0 1-4.09 4.69 4.648 4.648 0 0 1-2.52.99 4.64 4.64 0 0 1-2.52-.99 4.718 4.718 0 0 1-4.09-4.69 4.718 4.718 0 0 1 4.09-4.69 4.64 4.64 0 0 1 2.52-.99c.716 0 1.39.154 1.99.422l1.2-5.637a.624.624 0 0 1 .55-.45h.004zm-8.4 8.4c-.716 0-1.296.58-1.296 1.296s.58 1.296 1.296 1.296 1.296-.58 1.296-1.296-.58-1.296-1.296-1.296zm6.666 0c-.716 0-1.296.58-1.296 1.296s.58 1.296 1.296 1.296 1.296-.58 1.296-1.296-.58-1.296-1.296-1.296z"/>';
				break;
			case 'twitch':
				$svg .= '<path fill="currentColor" d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z"/>';
				break;
			case 'discord':
				$svg .= '<path fill="currentColor" d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>';
				break;
			case 'mastodon':
				$svg .= '<path fill="currentColor" d="M23.193 4.154c-.347-.597-1.056-.854-1.756-.612L15.5 6.5 9.5 4.5 3.743 3.027c-.7-.241-1.409.015-1.756.612C1.5 5.5 1.5 5.5 1.5 5.5v9c0 0 0 2.5 2.5 2.5h17c2.5 0 2.5-2.5 2.5-2.5v-9s0-2-1.5-3.346z"/>';
				break;
			case 'bluesky':
				$svg .= '<path fill="currentColor" d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.595C2.222.943 1.08 1.082.902 2.48.64 4.57 2.98 12 2.98 12s-2.34 7.43-2.598 9.52c-.178 1.398 1.32 1.537 2.304.905 2.752-1.542 5.711-5.481 6.798-7.595.178-.35.267-.525.267-.705s-.089-.355-.267-.705z"/>';
				break;
			default:
				$svg .= '<circle cx="12" cy="12" r="10" fill="currentColor"/>';
				break;
		}
		return $svg . '</svg>';
	}

	/**
	 * @param string $slug Provider slug.
	 * @return string
	 */
	private static function social_oauth_button_inner_html( $slug ) {
		$svg = self::social_oauth_brand_glyph_svg( $slug );
		return '<span class="creatorreactor-social-oauth-button-inner creatorreactor-social-oauth-button-inner--logo-only">' . $svg . '</span>';
	}

	/**
	 * Admin settings: compact vs full preview chips for social OAuth appearance.
	 *
	 * @param string $slug         Provider slug.
	 * @param string $preview_size `compact` or `full` (matches appearance card rows).
	 * @return string HTML
	 */
	public static function social_oauth_admin_preview_chip( $slug, $preview_size ) {
		$slug         = sanitize_key( (string) $slug );
		$preview_size = ( 'compact' === (string) $preview_size ) ? 'compact' : 'full';
		$base         = 'creatorreactor-social-oauth-link creatorreactor-social-oauth-link--' . sanitize_html_class( $slug ) . ' creatorreactor-social-oauth--admin-preview';
		if ( 'compact' === $preview_size ) {
			return '<span class="' . esc_attr( $base . ' creatorreactor-social-oauth-link--minimal' ) . '">'
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				. self::social_oauth_brand_glyph_svg( $slug )
				. '</span>';
		}
		return '<span class="' . esc_attr( $base . ' creatorreactor-social-oauth-link--standard' ) . '">'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			. self::social_oauth_brand_glyph_svg( $slug )
			. '</span>';
	}

	/**
	 * @param string               $slug Provider slug.
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return string
	 */
	public static function social_oauth_login( $slug, $atts = [] ) {
		$slug = sanitize_key( (string) $slug );
		$opts = Admin_Settings::get_options();
		$def  = Admin_Settings::resolve_social_oauth_login_button_variant_from_opts( $opts, $slug );
		$atts = \shortcode_atts( [ 'variant' => $def ], $atts, '' );
		$variant = ( isset( $atts['variant'] ) && $atts['variant'] === 'minimal' ) ? 'minimal' : 'standard';

		$plabel = self::social_oauth_provider_label( $slug );
		if ( Admin_Settings::is_broker_mode() ) {
			return '<p class="creatorreactor-social-oauth-unavailable">'
				. esc_html(
					sprintf(
						/* translators: %s: Provider name */
						__( '%s sign-in is not available in Agency (broker) mode.', 'wp-creatorreactor' ),
						$plabel
					)
				)
				. '</p>';
		}

		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$dashboard = admin_url();
			$home      = home_url( '/' );
			return '<p class="creatorreactor-social-oauth-wrap creatorreactor-social-oauth-logged-in">'
				. esc_html__( 'You are already signed in.', 'wp-creatorreactor' ) . ' '
				. '<a href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Dashboard', 'wp-creatorreactor' ) . '</a>'
				. ' <span class="creatorreactor-social-oauth-logged-in-sep" aria-hidden="true">—</span> '
				. '<a href="' . esc_url( wp_logout_url( $home ) ) . '">' . esc_html__( 'Log out', 'wp-creatorreactor' ) . '</a>'
				. '</p>';
		}
		if ( ! Admin_Settings::is_social_oauth_provider_configured( $slug ) ) {
			return '';
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
		$redirect_to = remove_query_arg( Social_OAuth_Registry::query_arg( $slug ), $redirect_to );

		$route     = ( 'bluesky' === $slug ) ? Bluesky_OAuth::REST_ROUTE_START : Social_OAuth_Registry::rest_route_start( $slug );
		$rest_path = CreatorReactor_OAuth::REST_NAMESPACE . '/' . ltrim( (string) $route, '/' );
		$rest_base = rest_url( $rest_path );
		if ( ! is_string( $rest_base ) || $rest_base === '' || ! preg_match( '#\Ahttps?://#i', $rest_base ) ) {
			$rest_base = add_query_arg( 'rest_route', '/' . trim( $rest_path, '/' ), home_url( '/' ) );
		}
		$start = add_query_arg(
			[
				'_wpnonce'    => wp_create_nonce( Social_OAuth_Registry::nonce_action( $slug ) ),
				'redirect_to' => $redirect_to,
			],
			$rest_base
		);
		if ( strlen( $start ) > 1900 ) {
			$start = add_query_arg(
				[
					'_wpnonce'    => wp_create_nonce( Social_OAuth_Registry::nonce_action( $slug ) ),
					'redirect_to' => home_url( '/' ),
				],
				$rest_base
			);
		}
		$href = esc_url( $start, [ 'http', 'https' ] );
		if ( $href === '' ) {
			$start = add_query_arg(
				'_wpnonce',
				wp_create_nonce( Social_OAuth_Registry::nonce_action( $slug ) ),
				$rest_base
			);
			$href = esc_url( $start, [ 'http', 'https' ] );
		}
		if ( $href === '' ) {
			$href = esc_url( esc_url_raw( $start ), [ 'http', 'https' ] );
		}

		self::schedule_social_oauth_footer_style();

		$minimal    = ( $variant === 'minimal' );
		$btn_label  = sprintf(
			/* translators: %s: provider name */
			__( 'Sign in with %s', 'wp-creatorreactor' ),
			$plabel
		);
		$link_class = 'creatorreactor-social-oauth-link creatorreactor-social-oauth-link--' . sanitize_html_class( $slug );
		if ( $minimal ) {
			$link_class .= ' creatorreactor-social-oauth-link--minimal';
		} else {
			$link_class .= ' creatorreactor-social-oauth-link--standard';
		}
		$link_attrs = 'class="' . esc_attr( $link_class ) . '" aria-label="' . esc_attr( $btn_label ) . '"';
		$link_attrs .= ' href="' . esc_url( $href ) . '"';
		$inner = self::social_oauth_button_inner_html( $slug );

		$wrap_class = 'creatorreactor-social-oauth-wrap creatorreactor-social-oauth-wrap--' . sanitize_html_class( $slug );
		if ( $minimal ) {
			$wrap_class .= ' creatorreactor-social-oauth-wrap--minimal';
		}

		return '<p class="' . esc_attr( $wrap_class ) . '">'
			. '<a ' . $link_attrs . '>'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner escapes text; SVG is static.
			. $inner
			. '</a></p>';
	}

	/**
	 * Shared CSS for social OAuth shortcodes and wp-login.
	 *
	 * @return string
	 */
	public static function get_social_oauth_button_css() {
		$box  = (int) self::OAUTH_COMPACT_BOX_PX;
		$cico = (int) self::OAUTH_COMPACT_ICON_PX;
		$css  = '';
		$colors = [
			'tiktok'    => [ 'bg' => '#000000', 'fg' => '#ffffff' ],
			'x_twitter' => [ 'bg' => '#000000', 'fg' => '#ffffff' ],
			'snapchat'  => [ 'bg' => '#FFFC00', 'fg' => '#000000' ],
			'linkedin'  => [ 'bg' => '#0A66C2', 'fg' => '#ffffff' ],
			'pinterest' => [ 'bg' => '#E60023', 'fg' => '#ffffff' ],
			'reddit'    => [ 'bg' => '#FF4500', 'fg' => '#ffffff' ],
			'twitch'    => [ 'bg' => '#9146FF', 'fg' => '#ffffff' ],
			'discord'   => [ 'bg' => '#5865F2', 'fg' => '#ffffff' ],
			'mastodon'  => [ 'bg' => '#6364FF', 'fg' => '#ffffff' ],
			'bluesky'   => [ 'bg' => '#1185fe', 'fg' => '#ffffff' ],
		];
		$css .= '.creatorreactor-social-oauth-wrap{margin:12px 0 0;text-align:center}';
		$css .= '.creatorreactor-social-oauth-wrap--minimal{margin:0;text-align:inherit}';
		$css .= '.creatorreactor-social-oauth-link{box-sizing:border-box;cursor:pointer;pointer-events:auto;line-height:1.35;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;font-weight:500;font-size:14px;border-radius:4px}';
		$css .= '.creatorreactor-social-oauth-link[aria-disabled="true"]{cursor:not-allowed;pointer-events:none;opacity:.55;filter:grayscale(100%)}';
		$css .= '.creatorreactor-social-oauth-link--standard{padding:10px 16px;min-height:40px;gap:10px;border:1px solid rgba(0,0,0,.12)}';
		$css .= '.creatorreactor-social-oauth-button-inner{display:inline-flex;align-items:center;gap:10px}';
		$css .= '.creatorreactor-social-oauth-icon .creatorreactor-social-oauth-svg,.creatorreactor-social-oauth-link--standard .creatorreactor-social-oauth-svg{width:20px;height:20px}';
		$css .= '.creatorreactor-social-oauth-link--minimal{width:' . $box . 'px;height:' . $box . 'px;min-width:' . $box . 'px;min-height:' . $box . 'px;padding:0;border:1px solid rgba(0,0,0,.12)}';
		$css .= '.creatorreactor-social-oauth-link--minimal .creatorreactor-social-oauth-button-inner--logo-only,.creatorreactor-social-oauth-link--standard .creatorreactor-social-oauth-button-inner--logo-only{display:flex;align-items:center;justify-content:center;line-height:0}';
		$css .= '.creatorreactor-social-oauth-link--minimal .creatorreactor-social-oauth-svg{width:' . $cico . 'px;height:' . $cico . 'px}';
		$css .= '.creatorreactor-wp-login-social .creatorreactor-social-oauth-wrap{margin:12px 0 0}';
		foreach ( $colors as $slug => $c ) {
			$s = sanitize_html_class( $slug );
			$css .= '.creatorreactor-social-oauth-link--' . $s . '{background:' . $c['bg'] . ';color:' . $c['fg'] . '}';
		}
		return $css;
	}

	/**
	 * @return void
	 */
	private static function schedule_social_oauth_footer_style() {
		if ( self::$social_oauth_footer_style_scheduled ) {
			return;
		}
		self::$social_oauth_footer_style_scheduled = true;
		if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
			return;
		}
		$css   = self::get_social_oauth_button_css();
		$print = static function () use ( $css ) {
			echo '<style id="creatorreactor-social-oauth-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
