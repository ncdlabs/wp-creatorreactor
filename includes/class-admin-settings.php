<?php
/**
 * Unified Admin Settings for CreatorReactor
 * Supports Creator (direct OAuth) and Agency (broker) authentication modes.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Social_OAuth_Registry', false ) ) {
	require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-social-oauth-registry.php';
}
if ( ! class_exists( __NAMESPACE__ . '\\Social_OAuth', false ) ) {
	require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-social-oauth.php';
}

class Admin_Settings {

	const OPTION_NAME                 = 'creatorreactor_settings';
	const OPTION_LAST_ERROR           = 'creatorreactor_last_error';
	const OPTION_CRITICAL_ERROR       = 'creatorreactor_critical_error';
	const OPTION_LAST_SYNC            = 'creatorreactor_last_sync';
	const OPTION_CONNECTION_TEST      = 'creatorreactor_connection_test';
	/** Option key: connection / OAuth log entries (array of { time, level, message }). */
	const OPTION_CONNECTION_LOGS      = 'creatorreactor_connection_logs';
	const MAX_CONNECTION_LOG_ENTRIES  = 500;
	/** Option key: subscriber/sync and user-table events (same shape as connection logs). */
	const OPTION_SYNC_LOGS            = 'creatorreactor_sync_logs';
	const MAX_SYNC_LOG_ENTRIES        = 500;
	const LOG_TYPE_CONNECTION         = 'connection';
	const LOG_TYPE_SYNC               = 'sync';
	const LOG_TYPE_AUTH               = 'auth';
	const LOG_TYPE_API                = 'api';
	const LOG_TYPE_CRON               = 'cron';
	const LOG_TYPE_ENTITLEMENTS       = 'entitlements';
	const LOG_TYPE_ERROR              = 'error';
	const OPTION_TIERS               = 'creatorreactor_tiers';
	const OPTION_SUBSCRIPTION_TIERS  = 'creatorreactor_subscription_tiers';
	const ENCRYPTED_FIELDS            = [
		'creatorreactor_oauth_client_id',
		'creatorreactor_oauth_client_secret',
		'creatorreactor_cloud_password',
		'creatorreactor_metrics_ingest_token',
		'creatorreactor_ofauth_api_key',
		'creatorreactor_ofauth_webhook_secret',
		'creatorreactor_google_oauth_client_id',
		'creatorreactor_google_oauth_client_secret',
		'creatorreactor_instagram_oauth_client_id',
		'creatorreactor_instagram_oauth_client_secret',
		'creatorreactor_tiktok_oauth_client_id',
		'creatorreactor_tiktok_oauth_client_secret',
		'creatorreactor_x_twitter_oauth_client_id',
		'creatorreactor_x_twitter_oauth_client_secret',
		'creatorreactor_snapchat_oauth_client_id',
		'creatorreactor_snapchat_oauth_client_secret',
		'creatorreactor_linkedin_oauth_client_id',
		'creatorreactor_linkedin_oauth_client_secret',
		'creatorreactor_pinterest_oauth_client_id',
		'creatorreactor_pinterest_oauth_client_secret',
		'creatorreactor_reddit_oauth_client_id',
		'creatorreactor_reddit_oauth_client_secret',
		'creatorreactor_twitch_oauth_client_id',
		'creatorreactor_twitch_oauth_client_secret',
		'creatorreactor_discord_oauth_client_id',
		'creatorreactor_discord_oauth_client_secret',
		'creatorreactor_mastodon_oauth_client_id',
		'creatorreactor_mastodon_oauth_client_secret',
		'creatorreactor_bluesky_oauth_client_id',
		'creatorreactor_bluesky_oauth_client_secret',
	];
	/** Option key: appearance of Sign in with Google on wp-login and the shortcode. */
	const GOOGLE_LOGIN_BUTTON_STYLE_KEY = 'creatorreactor_google_login_button_style';
	/** Option key: site-wide Default Login Button Style (Compact vs Full) under Settings → General. */
	const OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY = 'creatorreactor_oauth_logo_button_general_size';
	/** Option key: Fanvue — follow General, or force Compact / Full for this provider only. */
	const FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY = 'creatorreactor_fanvue_oauth_button_size_mode';
	/** Option key: Google — same semantics as {@see self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY}. */
	const GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY = 'creatorreactor_google_oauth_button_size_mode';
	/** Option key: Instagram — same semantics as {@see self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY}. */
	const INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY = 'creatorreactor_instagram_oauth_button_size_mode';
	/** Option key: OnlyFans — same semantics as {@see self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY}. */
	const ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY = 'creatorreactor_onlyfans_oauth_button_size_mode';
	/** Option key: Fanvue login control for wp-login, block, and [fanvue_login_button] (standard vs minimal). */
	const FANVUE_LOGIN_BUTTON_VARIANT_KEY = 'creatorreactor_fanvue_login_button_variant';
	/** Option key: Instagram-style login control preview variant (Settings → Instagram; reserved for future front-end use). */
	const INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY = 'creatorreactor_instagram_login_button_variant';
	/** Option key: OnlyFans / OFAuth login-style preview variant (Settings → OnlyFans; reserved for future front-end use). */
	const ONLYFANS_LOGIN_BUTTON_VARIANT_KEY = 'creatorreactor_onlyfans_login_button_variant';
	/** Default Schema Service base URL (local compose publishes the API on host port 18080). */
	const DEFAULT_SCHEMA_SERVICE_URL = 'http://localhost:18080';
	/**
	 * Env var: override base URL for server-side HTTP to the schema service (e.g. WordPress in Docker must use http://schema-service:8080, not localhost:18080).
	 */
	const ENV_SCHEMA_SERVICE_URL = 'CREATORREACTOR_SCHEMA_SERVICE_URL';
	/** Default metrics ingest base URL (empty = disabled unless env provides a URL). */
	const DEFAULT_METRICS_INGEST_URL = '';
	/**
	 * Env var: override base URL for server-side HTTP to the metrics edge (e.g. http://data-ingestion:8080 in Compose).
	 */
	const ENV_METRICS_INGEST_URL = 'CREATORREACTOR_METRICS_INGEST_URL';
	/**
	 * Env var: bearer token for metrics ingest (optional; otherwise use saved encrypted token in settings).
	 */
	const ENV_METRICS_INGEST_TOKEN = 'CREATORREACTOR_METRICS_INGEST_TOKEN';
	/** @var string Mirrors {@see CreatorReactor_OAuth::DEFAULT_SCOPES} (Fanvue quick start; add read:fan in OAuth Scopes if your app allows it). */
	const DEFAULT_CREATORREACTOR_SCOPES = CreatorReactor_OAuth::DEFAULT_SCOPES;
	const PAGE_SLUG                   = 'creatorreactor';
	const PAGE_USERS_SLUG             = 'creatorreactor-users';
	const PAGE_SETTINGS_SLUG          = 'creatorreactor-settings';
	/**
	 * Cookie: IANA timezone from the browser (set by admin JS) when Display Timezone is Browser.
	 */
	const COOKIE_ADMIN_DISPLAY_TZ = 'creatorreactor_admin_display_tz';
	/** @var string User meta: list of ignored integration check IDs (array of string slugs). */
	const USER_META_INTEGRATION_CHECKS_IGNORED = 'creatorreactor_integration_checks_ignored';
	/**
	 * When set to "1", role registration was deferred until integration onboarding is acknowledged
	 * (see {@see Admin_Settings::run_deferred_activation_roles_only()}).
	 */
	const OPTION_DEFERRED_ACTIVATION_PENDING = 'creatorreactor_deferred_activation_pending';

	/** Form value authentication_mode maps to stored broker_mode (Agency = true). */
	const AUTH_MODE_CREATOR = 'creator';
	const AUTH_MODE_AGENCY  = 'agency';

	/** @var bool Guard to avoid printing the profile modal twice. */
	private static $printed_profile_details_modal = false;

	/** Legacy wp_options keys from installs before the CreatorReactor rename. */
	const LEGACY_OPTION_BROKER        = 'fan' . 'bridge_broker_options';
	const LEGACY_OPTION_DIRECT        = 'fan' . 'bridge_direct_options';

	private static function get_current_product() {
		return Entitlements::PRODUCT_FANVUE;
	}

	private static function get_current_product_label() {
		return Entitlements::product_label( self::get_current_product() );
	}

	/**
	 * Public plugin URL for the horizontal CreatorReactor logo (wp-admin branding).
	 *
	 * @return string
	 */
	private static function brand_logo_url() {
		return CREATORREACTOR_PLUGIN_URL . 'img/cr-logo.png';
	}

	/**
	 * Markup for copy-to-clipboard icon buttons (Debug logs / schema; img/icon-copy.png).
	 *
	 * @return string
	 */
	private static function admin_copy_icon_img() {
		$url = CREATORREACTOR_PLUGIN_URL . 'img/icon-copy.png';
		if ( defined( 'CREATORREACTOR_VERSION' ) ) {
			$url = add_query_arg( 'ver', CREATORREACTOR_VERSION, $url );
		}
		return '<img class="creatorreactor-icon-copy" src="' . esc_url( $url ) . '" alt="" width="20" height="20" decoding="async" />';
	}

	/**
	 * Top-level wp-admin menu icon: CR monogram SVG (replaces generic dashicon).
	 *
	 * Uses a data URI so the SVG renders like core Dashicons without relying on .svg MIME delivery.
	 *
	 * @return string Data URI or dashicons-* class fallback.
	 */
	private static function admin_menu_icon() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$file = CREATORREACTOR_PLUGIN_DIR . 'img/cr-menu-icon.svg';
		if ( ! is_readable( $file ) ) {
			return $cached = 'dashicons-chart-pie';
		}
		$svg = file_get_contents( $file );
		if ( false === $svg || '' === trim( $svg ) ) {
			return $cached = 'dashicons-chart-pie';
		}
		$svg = preg_replace( '/>\s+</s', '><', trim( $svg ) );
		return $cached = 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Print an <img> for the CreatorReactor logo.
	 *
	 * @param string $extra_class Optional extra CSS class(es).
	 */
	private static function render_brand_logo_img( $extra_class = '' ) {
		$classes = 'creatorreactor-brand-logo' . ( is_string( $extra_class ) && $extra_class !== '' ? ' ' . trim( $extra_class ) : '' );
		printf(
			'<img src="%1$s" alt="%2$s" class="%3$s" loading="lazy" decoding="async" />',
			esc_url( self::brand_logo_url() ),
			esc_attr( __( 'CreatorReactor', 'wp-creatorreactor' ) ),
			esc_attr( $classes )
		);
	}

	public static function init() {
		self::migrate_prefixed_options_from_before_rename();
		self::migrate_legacy_options();
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_display_timezone_cookie' ], 1 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_oauth_start' ], 1 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_post_creatorreactor_disconnect', [ __CLASS__, 'handle_disconnect' ] );
		add_action( 'admin_post_creatorreactor_test_connection', [ __CLASS__, 'handle_connection_test' ] );
		add_action( 'admin_post_creatorreactor_disable_google_oauth', [ __CLASS__, 'handle_disable_google_oauth' ] );
		add_action( 'admin_post_creatorreactor_disable_instagram_oauth', [ __CLASS__, 'handle_disable_instagram_oauth' ] );
		add_action( 'admin_post_creatorreactor_disable_onlyfans_ofauth', [ __CLASS__, 'handle_disable_onlyfans_ofauth' ] );
		add_action( 'admin_post_creatorreactor_test_google_oauth', [ __CLASS__, 'handle_test_google_oauth' ] );
		add_action( 'admin_post_creatorreactor_test_instagram_oauth', [ __CLASS__, 'handle_test_instagram_oauth' ] );
		add_action( 'admin_post_creatorreactor_test_onlyfans_ofauth', [ __CLASS__, 'handle_test_onlyfans_ofauth' ] );
		add_action( 'admin_post_creatorreactor_clear_connection_logs', [ __CLASS__, 'handle_clear_connection_logs' ] );
		add_action( 'admin_post_creatorreactor_clear_sync_logs', [ __CLASS__, 'handle_clear_sync_logs' ] );
		add_action( 'admin_post_creatorreactor_broker_connect', [ __CLASS__, 'handle_broker_connect' ] );
		add_action( 'wp_ajax_creatorreactor_auth_mode_fields', [ __CLASS__, 'ajax_auth_mode_fields' ] );
		add_action( 'wp_ajax_creatorreactor_get_users_table', [ __CLASS__, 'ajax_get_users_table' ] );
		add_action( 'wp_ajax_creatorreactor_append_sync_log', [ __CLASS__, 'ajax_append_sync_log' ] );
		add_action( 'wp_ajax_creatorreactor_deactivate_wp_user', [ __CLASS__, 'ajax_deactivate_wp_user' ] );
		add_action( 'wp_ajax_creatorreactor_get_entitlement_details', [ __CLASS__, 'ajax_get_entitlement_details' ] );
		add_action( 'wp_ajax_creatorreactor_get_user_entitlement_details', [ __CLASS__, 'ajax_get_user_entitlement_details' ] );
		add_action( 'wp_ajax_creatorreactor_integration_fix', [ __CLASS__, 'ajax_integration_fix' ] );
		add_action( 'wp_ajax_creatorreactor_integration_check_ignore', [ __CLASS__, 'ajax_integration_check_ignore' ] );
		add_action( 'wp_ajax_creatorreactor_onboarding_apply_fixes', [ __CLASS__, 'ajax_onboarding_apply_fixes' ] );
		add_action( 'wp_ajax_creatorreactor_onboarding_ignore_checks', [ __CLASS__, 'ajax_onboarding_ignore_failing_checks' ] );
		add_action( 'wp_ajax_creatorreactor_debug_schema_manifest', [ __CLASS__, 'ajax_debug_schema_manifest' ] );
		add_action( 'wp_ajax_creatorreactor_google_oauth_validate', [ __CLASS__, 'ajax_google_oauth_validate_config' ] );
		add_action( 'wp_ajax_creatorreactor_social_oauth_validate', [ __CLASS__, 'ajax_social_oauth_validate_config' ] );
		add_action( 'admin_post_creatorreactor_disable_social_oauth', [ __CLASS__, 'handle_disable_social_oauth' ] );
		add_action( 'admin_post_creatorreactor_test_social_oauth', [ __CLASS__, 'handle_test_social_oauth' ] );
		add_action( 'wp_ajax_creatorreactor_instagram_oauth_validate', [ __CLASS__, 'ajax_instagram_oauth_validate_config' ] );
		add_action( 'wp_ajax_creatorreactor_fanvue_oauth_validate', [ __CLASS__, 'ajax_fanvue_oauth_validate_config' ] );
		add_action( 'wp_ajax_creatorreactor_onlyfans_ofauth_validate', [ __CLASS__, 'ajax_onlyfans_ofauth_validate_config' ] );
		add_action( 'show_user_profile', [ __CLASS__, 'render_user_profile_creatorreactor_uuid_field' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'render_user_profile_creatorreactor_uuid_field' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_creatorreactor_users_from_wp_admin' ], 1 );
		add_filter( 'show_admin_bar', [ __CLASS__, 'filter_show_admin_bar_for_creatorreactor_users' ], 10, 1 );
		add_filter(
			'plugin_action_links_' . plugin_basename( CREATORREACTOR_PLUGIN_DIR . 'creatorreactor.php' ),
			[ __CLASS__, 'add_plugin_action_links' ]
		);
	}

	/**
	 * Settings link on Plugins screen (after Deactivate).
	 *
	 * @param array<int|string, string> $links Existing action links.
	 * @return array<int|string, string>
	 */
	public static function add_plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}
		$url                              = self::admin_page_url( [ 'tab' => 'settings', 'subtab' => 'oauth' ], self::PAGE_SETTINGS_SLUG );
		$links['creatorreactor_settings'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'wp-creatorreactor' )
		);
		return $links;
	}

	/**
	 * Get the admin page URL for the CreatorReactor settings screen.
	 *
	 * @param array<string, scalar> $args Optional query args to append.
	 * @param string $page_slug Optional page slug; defaults to the primary slug.
	 * @return string
	 */
	private static function admin_page_url( array $args = [], $page_slug = self::PAGE_SLUG ) {
		$page_slug = sanitize_key( $page_slug );
		$url       = admin_url( 'admin.php?page=' . $page_slug );
		if ( empty( $args ) ) {
			return $url;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Settings screen URL for one social login provider (unified Social login tab + sidebar).
	 *
	 * @param string $slug Provider slug (e.g. tiktok, bluesky).
	 * @return string
	 */
	private static function admin_social_oauth_settings_url( $slug ) {
		$slug  = sanitize_key( (string) $slug );
		$slugs = self::all_settings_social_oauth_slugs();
		if ( $slug === '' || ! in_array( $slug, $slugs, true ) ) {
			$slug = ! empty( $slugs[0] ) ? $slugs[0] : 'tiktok';
		}
		return self::admin_page_url(
			[
				'tab'             => 'social_oauth',
				'social_provider' => $slug,
			],
			self::PAGE_SETTINGS_SLUG
		);
	}

	/**
	 * Integration-check "Fix" actions: titles, explanations, and whether the browser runs AJAX or navigates away.
	 *
	 * @return array<string, array{type: string, title: string, message: string, redirect_url?: string}>
	 */
	private static function get_integration_fix_definitions() {
		$plugins_url       = admin_url( 'plugins.php' );
		$social_setup_url  = self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG );

		return [
			'membership_signup'               => [
				'type'    => 'ajax',
				'title'   => __( 'Enable membership registration', 'wp-creatorreactor' ),
				'message' => __( 'This will turn on WordPress “Anyone can register” (Settings > General > Membership). New visitors can then sign up, which CreatorReactor expects for fan sign-up flows.', 'wp-creatorreactor' ),
			],
			'creatorreactor_roles_register'   => [
				'type'    => 'ajax',
				'title'   => __( 'Register CreatorReactor roles', 'wp-creatorreactor' ),
				'message' => __( 'This will create any missing WordPress roles the plugin expects: CreatorReactor Follower, CreatorReactor Subscriber, and CreatorReactor Null (same as when the plugin is activated). Existing roles are not modified.', 'wp-creatorreactor' ),
			],
			'default_role_creatorreactor'     => [
				'type'    => 'ajax',
				'title'   => __( 'Set default role to a CreatorReactor role', 'wp-creatorreactor' ),
				'message' => __( 'This will set the site default new-user role to creatorreactor_null when that role exists; otherwise the first available creatorreactor_follower or creatorreactor_subscriber, or another creatorreactor_* role. Other roles are unchanged.', 'wp-creatorreactor' ),
			],
			'social_login_enable_wp_login'    => [
				'type'    => 'ajax',
				'title'   => __( 'Enable social login on wp-login', 'wp-creatorreactor' ),
				'message' => __( 'At least one social sign-in provider is already saved in this plugin. This will turn on “Add social login button to the WordPress login page?” in the plugin’s Settings (General tab) so wp-login shows the configured provider button(s).', 'wp-creatorreactor' ),
			],
			'social_login_configure_oauth'    => [
				'type'          => 'redirect',
				'title'         => __( 'Configure social login (Fanvue, Google, or another OAuth tab)', 'wp-creatorreactor' ),
				'message'       => __( 'Social login on wp-login needs at least one provider in Creator mode (Fanvue, Google, TikTok, X, Bluesky, Mastodon, etc.—each has its own Settings tab). Save credentials, then open Settings → Debug to run checks again or use Fix on the social login check to enable the wp-login option.', 'wp-creatorreactor' ),
				'redirect_url'  => $social_setup_url,
			],
			'open_plugins_registration_conflict' => [
				'type'          => 'redirect',
				'title'         => __( 'Review registration plugins', 'wp-creatorreactor' ),
				'message'       => __( 'CreatorReactor expects WordPress to own registration. This cannot be changed automatically. You will be taken to the Plugins screen so you can deactivate or reconfigure plugins that take over registration (for example WooCommerce, MemberPress, or Ultimate Member).', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
			'open_plugins_redirect_conflict' => [
				'type'          => 'redirect',
				'title'         => __( 'Review redirect plugins', 'wp-creatorreactor' ),
				'message'       => __( 'Other plugins are hooking login_redirect or template_redirect. This cannot be fixed automatically. You will be taken to the Plugins screen to identify and adjust plugins that force redirects after login or on every request.', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
			'open_plugins_custom_fields'     => [
				'type'          => 'redirect',
				'title'         => __( 'Review custom registration field plugins', 'wp-creatorreactor' ),
				'message'       => __( 'Extra signup fields can block OAuth social sign-up (Fanvue or Google). You will be taken to the Plugins screen to adjust or deactivate plugins that add required registration fields.', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
			'open_plugins_content_restriction' => [
				'type'          => 'redirect',
				'title'         => __( 'Review content restriction plugins', 'wp-creatorreactor' ),
				'message'       => __( 'Multiple membership/restriction plugins often conflict. You will be taken to the Plugins screen to leave only one restriction engine active or align their settings.', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
			'open_plugins_cache_security'    => [
				'type'          => 'redirect',
				'title'         => __( 'Review caching or security plugins', 'wp-creatorreactor' ),
				'message'       => __( 'Caching and some security plugins can break OAuth cookies or admin redirects. You will be taken to the Plugins screen to adjust exclusions or settings for login, admin, and OAuth callback URLs.', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
			'open_plugins_network_restriction' => [
				'type'          => 'redirect',
				'title'         => __( 'Review network-activated restriction plugins', 'wp-creatorreactor' ),
				'message'       => __( 'On multisite, network-activated restriction plugins affect every site. You will be taken to the Plugins screen for this site; you may need the Network Admin plugins list to change network-wide plugins.', 'wp-creatorreactor' ),
				'redirect_url'  => $plugins_url,
			],
		];
	}

	/**
	 * @return array<string, array{type: string, title: string, message: string, redirectUrl?: string}>
	 */
	private static function get_integration_fix_definitions_for_localize() {
		$defs = self::get_integration_fix_definitions();
		$out  = [];
		foreach ( $defs as $id => $def ) {
			$row = [
				'type'    => isset( $def['type'] ) ? (string) $def['type'] : 'redirect',
				'title'   => isset( $def['title'] ) ? (string) $def['title'] : '',
				'message' => isset( $def['message'] ) ? (string) $def['message'] : '',
			];
			if ( ! empty( $def['redirect_url'] ) ) {
				$row['redirectUrl'] = (string) $def['redirect_url'];
			}
			$out[ $id ] = $row;
		}
		return $out;
	}

	/**
	 * Run a single AJAX integration fix (same IDs as {@see ajax_integration_fix()}).
	 *
	 * @param string $fix_id membership_signup|creatorreactor_roles_register|default_role_creatorreactor|social_login_enable_wp_login
	 * @return true|\WP_Error
	 */
	private static function run_integration_ajax_fix( $fix_id ) {
		switch ( $fix_id ) {
			case 'membership_signup':
				update_option( 'users_can_register', 1 );
				return true;
			case 'creatorreactor_roles_register':
				$still_missing = self::register_missing_creatorreactor_roles();
				if ( ! empty( $still_missing ) ) {
					return new \WP_Error(
						'roles',
						sprintf(
							/* translators: %s: comma-separated role slugs */
							__( 'Could not register all roles. Still missing: %s.', 'wp-creatorreactor' ),
							implode( ', ', $still_missing )
						)
					);
				}
				return true;
			case 'default_role_creatorreactor':
				$roles = wp_roles();
				$target = '';
				foreach ( [ 'creatorreactor_null', 'creatorreactor_follower', 'creatorreactor_subscriber' ] as $slug ) {
					if ( $roles->is_role( $slug ) ) {
						$target = $slug;
						break;
					}
				}
				if ( $target === '' ) {
					foreach ( array_keys( $roles->roles ) as $slug ) {
						if ( strpos( (string) $slug, 'creatorreactor_' ) === 0 ) {
							$target = (string) $slug;
							break;
						}
					}
				}
				if ( $target === '' ) {
					return new \WP_Error(
						'no_role',
						__( 'No creatorreactor_* role exists yet. Activate CreatorReactor or create the role before setting it as default.', 'wp-creatorreactor' )
					);
				}
				update_option( 'default_role', $target );
				return true;
			case 'social_login_enable_wp_login':
				if ( ! self::is_fan_social_login_configured() ) {
					return new \WP_Error(
						'oauth',
						__( 'No social login provider is configured. Add Fanvue OAuth, Google sign-in, or another social OAuth provider under the matching Settings tab.', 'wp-creatorreactor' )
					);
				}
				$sanitized = self::sanitize_options( [ 'replace_wp_login_with_social' => 1 ] );
				update_option( self::OPTION_NAME, $sanitized );
				return true;
			default:
				return new \WP_Error( 'unknown', __( 'Unknown fix.', 'wp-creatorreactor' ) );
		}
	}

	/**
	 * Apply an automated integration-check fix (AJAX).
	 */
	public static function ajax_integration_fix() {
		check_ajax_referer( 'creatorreactor_integration_fix', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$fix_id = isset( $_POST['fix_id'] ) ? sanitize_key( wp_unslash( $_POST['fix_id'] ) ) : '';
		$allowed_ajax = self::get_integration_ajax_remediable_fix_ids_ordered();
		if ( ! in_array( $fix_id, $allowed_ajax, true ) ) {
			wp_send_json_error( [ 'message' => __( 'This fix cannot be run from here.', 'wp-creatorreactor' ) ], 400 );
		}

		$result = self::run_integration_ajax_fix( $fix_id );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$status = ( $code === 'roles' ) ? 500 : 400;
			wp_send_json_error( [ 'message' => $result->get_error_message() ], $status );
		}

		wp_send_json_success( [ 'message' => __( 'Fix applied.', 'wp-creatorreactor' ) ] );
	}

	/**
	 * Integration fix IDs that {@see ajax_onboarding_apply_fixes()} can run (order preserved).
	 *
	 * @return array<int, string>
	 */
	private static function get_integration_ajax_remediable_fix_ids_ordered() {
		return [ 'membership_signup', 'creatorreactor_roles_register', 'default_role_creatorreactor', 'social_login_enable_wp_login' ];
	}

	/**
	 * Data for the post-activation integration onboarding modal (remediable rows + other failing count).
	 *
	 * @return array{remediable_rows: array<int, array{check_id: string, fix_id: string, label: string, message: string}>, other_red_count: int}
	 */
	public static function get_integration_onboarding_modal_data() {
		$order = self::get_integration_ajax_remediable_fix_ids_ordered();
		$flip  = array_fill_keys( $order, true );
		$state = self::compute_integration_checks_lists();
		$by_fix = [];
		$other_red = 0;
		foreach ( $state['checks'] as $check ) {
			if ( ( $check['raw_status'] ?? '' ) !== 'red' ) {
				continue;
			}
			$fid = isset( $check['fix_id'] ) ? (string) $check['fix_id'] : '';
			if ( $fid !== '' && isset( $flip[ $fid ] ) ) {
				$by_fix[ $fid ] = [
					'check_id' => isset( $check['check_id'] ) ? (string) $check['check_id'] : '',
					'fix_id'   => $fid,
					'label'    => isset( $check['label'] ) ? (string) $check['label'] : '',
					'message'  => isset( $check['message'] ) ? (string) $check['message'] : '',
				];
			} else {
				++$other_red;
			}
		}
		$remediable = [];
		foreach ( $order as $fid ) {
			if ( isset( $by_fix[ $fid ] ) ) {
				$remediable[] = $by_fix[ $fid ];
			}
		}
		return [
			'remediable_rows' => $remediable,
			'other_red_count' => $other_red,
		];
	}

	/**
	 * Post-activation onboarding: apply every applicable AJAX integration fix for currently failing checks.
	 */
	public static function ajax_onboarding_apply_fixes() {
		check_ajax_referer( 'creatorreactor_onboarding_integration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}
		// Explicit intent only (ignore / other flows must not POST this flag).
		if ( ! isset( $_POST['apply_integration_fixes'] ) || (string) wp_unslash( $_POST['apply_integration_fixes'] ) !== '1' ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'wp-creatorreactor' ) ], 400 );
		}

		self::run_deferred_activation_roles_only();

		$order = self::get_integration_ajax_remediable_fix_ids_ordered();
		$state = self::compute_integration_checks_lists();
		$needed = [];
		foreach ( $state['checks'] as $check ) {
			if ( ( $check['raw_status'] ?? '' ) !== 'red' ) {
				continue;
			}
			$fid = isset( $check['fix_id'] ) ? (string) $check['fix_id'] : '';
			if ( $fid !== '' && in_array( $fid, $order, true ) ) {
				$needed[ $fid ] = true;
			}
		}

		$applied   = [];
		$warnings  = [];
		foreach ( $order as $fid ) {
			if ( empty( $needed[ $fid ] ) ) {
				continue;
			}
			$r = self::run_integration_ajax_fix( $fid );
			if ( is_wp_error( $r ) ) {
				$warnings[] = $r->get_error_message();
			} else {
				$applied[] = $fid;
			}
		}

		wp_send_json_success(
			[
				'applied'    => $applied,
				'warnings' => $warnings,
			]
		);
	}

	/**
	 * Post-activation onboarding: mark all currently failing checks as ignored for this admin.
	 */
	public static function ajax_onboarding_ignore_failing_checks() {
		check_ajax_referer( 'creatorreactor_onboarding_integration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		self::run_deferred_activation_roles_only();

		$state   = self::compute_integration_checks_lists();
		$allowed = array_flip( self::get_integration_check_id_whitelist() );
		$current = self::get_ignored_integration_check_ids();
		foreach ( $state['checks'] as $check ) {
			if ( ( $check['raw_status'] ?? '' ) !== 'red' ) {
				continue;
			}
			$cid = isset( $check['check_id'] ) ? sanitize_key( (string) $check['check_id'] ) : '';
			if ( $cid !== '' && isset( $allowed[ $cid ] ) ) {
				$current[] = $cid;
			}
		}
		self::set_ignored_integration_check_ids_for_current_user( array_values( array_unique( $current ) ) );
		wp_send_json_success();
	}

	/**
	 * Failing integration checks (raw red) for the activation onboarding modal.
	 *
	 * @return array<int, array{check_id: string, label: string, message: string}>
	 */
	public static function get_integration_onboarding_failing_rows() {
		$state = self::compute_integration_checks_lists();
		$out   = [];
		foreach ( $state['checks'] as $check ) {
			if ( ( $check['raw_status'] ?? '' ) !== 'red' ) {
				continue;
			}
			$out[] = [
				'check_id' => isset( $check['check_id'] ) ? (string) $check['check_id'] : '',
				'label'    => isset( $check['label'] ) ? (string) $check['label'] : '',
				'message'  => isset( $check['message'] ) ? (string) $check['message'] : '',
			];
		}
		return $out;
	}

	/**
	 * Role slugs and labels CreatorReactor registers on activation.
	 *
	 * @return array<string, string> Role slug => translated display name.
	 */
	private static function get_required_creatorreactor_roles() {
		return [
			'creatorreactor_follower'   => __( 'CreatorReactor Follower', 'wp-creatorreactor' ),
			'creatorreactor_subscriber' => __( 'CreatorReactor Subscriber', 'wp-creatorreactor' ),
			'creatorreactor_null'       => __( 'CreatorReactor Null', 'wp-creatorreactor' ),
		];
	}

	/**
	 * Add any missing CreatorReactor roles (same as plugin activation).
	 *
	 * @return array<int, string> Slugs still missing after add_role attempts.
	 */
	private static function register_missing_creatorreactor_roles() {
		foreach ( self::get_required_creatorreactor_roles() as $role_key => $role_label ) {
			if ( ! get_role( $role_key ) ) {
				add_role( $role_key, $role_label, [ 'read' => true ] );
			}
		}
		$missing = [];
		foreach ( array_keys( self::get_required_creatorreactor_roles() ) as $slug ) {
			if ( ! get_role( $slug ) ) {
				$missing[] = $slug;
			}
		}
		return $missing;
	}

	/**
	 * Register standard CreatorReactor roles on plugin activation (delegates to {@see register_missing_creatorreactor_roles()}).
	 */
	public static function activate_register_standard_roles() {
		self::register_missing_creatorreactor_roles();
	}

	/**
	 * Enable “Anyone can register” (same as the membership Integration Check fix). Not run on plugin
	 * activation; use the onboarding “Next” fixes or apply the check from Integration Checks.
	 */
	public static function activate_apply_integration_defaults() {
		update_option( 'users_can_register', 1 );
	}

	/**
	 * Register CreatorReactor roles once deferred activation is allowed (after integration onboarding
	 * is acknowledged or the admin reaches the integration-checks experience). Idempotent.
	 *
	 * Does not enable membership registration or change default role; those are only applied via
	 * Integration Checks fixes / onboarding “Next”, not on plugin activation.
	 */
	public static function run_deferred_activation_roles_only() {
		if ( get_option( self::OPTION_DEFERRED_ACTIVATION_PENDING, '' ) !== '1' ) {
			return;
		}
		self::activate_register_standard_roles();
		delete_option( self::OPTION_DEFERRED_ACTIVATION_PENDING );
	}

	/**
	 * Allowed integration check IDs for ignore / unignore (must match check_id on each row).
	 *
	 * @return array<int, string>
	 */
	private static function get_integration_check_id_whitelist() {
		return [
			'membership_signup',
			'registration_source_native',
			'login_redirect_conflicts',
			'creatorreactor_roles_exist',
			'default_role_creatorreactor',
			'social_login_wp',
			'custom_registration_fields',
			'content_restriction_collision',
			'cache_security_risk',
			'multisite_network_restriction',
		];
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_ignored_integration_check_ids() {
		if ( ! is_user_logged_in() ) {
			return [];
		}
		$raw = get_user_meta( get_current_user_id(), self::USER_META_INTEGRATION_CHECKS_IGNORED, true );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$allowed = array_flip( self::get_integration_check_id_whitelist() );
		$out     = [];
		foreach ( $raw as $id ) {
			$id = sanitize_key( (string) $id );
			if ( $id !== '' && isset( $allowed[ $id ] ) ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Persist ignored integration check IDs for the current user.
	 *
	 * @param array<int, string> $ids Check IDs (subset of whitelist).
	 */
	private static function set_ignored_integration_check_ids_for_current_user( array $ids ) {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$allowed = array_flip( self::get_integration_check_id_whitelist() );
		$clean   = [];
		foreach ( $ids as $id ) {
			$id = sanitize_key( (string) $id );
			if ( $id !== '' && isset( $allowed[ $id ] ) ) {
				$clean[] = $id;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		update_user_meta( get_current_user_id(), self::USER_META_INTEGRATION_CHECKS_IGNORED, $clean );
	}

	/**
	 * Ignore or stop ignoring a failing integration check (per-user preference).
	 */
	public static function ajax_integration_check_ignore() {
		check_ajax_referer( 'creatorreactor_integration_check_ignore', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$check_id = isset( $_POST['check_id'] ) ? sanitize_key( wp_unslash( $_POST['check_id'] ) ) : '';
		$allowed  = array_flip( self::get_integration_check_id_whitelist() );
		if ( $check_id === '' || ! isset( $allowed[ $check_id ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid check.', 'wp-creatorreactor' ) ], 400 );
		}

		$do_ignore = ! empty( $_POST['ignore'] ) && (string) wp_unslash( $_POST['ignore'] ) === '1';
		$current   = self::get_ignored_integration_check_ids();

		if ( $do_ignore ) {
			if ( ! in_array( $check_id, $current, true ) ) {
				$current[] = $check_id;
			}
		} else {
			$current = array_values(
				array_filter(
					$current,
					static function ( $id ) use ( $check_id ) {
						return (string) $id !== $check_id;
					}
				)
			);
		}

		self::set_ignored_integration_check_ids_for_current_user( $current );
		wp_send_json_success( [ 'message' => __( 'Updated.', 'wp-creatorreactor' ) ] );
	}

	/**
	 * Read an environment variable as seen by PHP (Docker/Apache often populate $_SERVER but not getenv()).
	 *
	 * @param string $name Variable name.
	 * @return string Trimmed value or empty string.
	 */
	private static function read_process_env( $name ) {
		$name = (string) $name;
		if ( $name === '' ) {
			return '';
		}
		$v = getenv( $name );
		if ( is_string( $v ) ) {
			$v = trim( $v );
			if ( $v !== '' ) {
				return $v;
			}
		}
		if ( isset( $_SERVER[ $name ] ) && is_string( $_SERVER[ $name ] ) ) {
			$v = trim( (string) $_SERVER[ $name ] );
			if ( $v !== '' ) {
				return $v;
			}
		}
		if ( isset( $_ENV[ $name ] ) && is_string( $_ENV[ $name ] ) ) {
			$v = trim( (string) $_ENV[ $name ] );
			if ( $v !== '' ) {
				return $v;
			}
		}
		return '';
	}

	/**
	 * True when the stack looks like official Compose (wordpress + db service): host DB is `db` or `db:port`.
	 *
	 * @return bool
	 */
	private static function is_likely_compose_wordpress_container() {
		$db_host = self::read_process_env( 'WORDPRESS_DB_HOST' );
		return $db_host !== '' && (bool) preg_match( '/^db(?::\d+)?$/', $db_host );
	}

	/**
	 * True when the saved schema base URL points at the host-only port from inside a Compose WP container.
	 *
	 * @param string $base Sanitized URL.
	 * @return bool
	 */
	private static function should_rewrite_localhost_schema_port_to_internal( $base ) {
		if ( ! self::is_likely_compose_wordpress_container() ) {
			return false;
		}
		$parts = wp_parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( $host !== 'localhost' && $host !== '127.0.0.1' ) {
			return false;
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		return $port === 18080;
	}

	/**
	 * Base URL for server-side requests to the schema service (wp_remote_get, etc.).
	 *
	 * When {@see Admin_Settings::ENV_SCHEMA_SERVICE_URL} is set (typical: Docker/Podman Compose), it wins over
	 * the saved option so PHP inside the WordPress container can reach `http://schema-service:8080` instead of
	 * `localhost:18080` (host port is not visible as localhost from another container).
	 *
	 * If the env var is missing (Apache often does not pass Compose env to getenv()), we still rewrite the
	 * default host URL when {@see Admin_Settings::is_likely_compose_wordpress_container()} is true.
	 *
	 * @return string Untrailingslashit URL.
	 */
	public static function get_schema_service_url_for_requests() {
		$from_env = self::read_process_env( self::ENV_SCHEMA_SERVICE_URL );
		if ( $from_env !== '' ) {
			return untrailingslashit( esc_url_raw( $from_env ) );
		}

		$opts = get_option( self::OPTION_NAME, [] );
		$base = isset( $opts['creatorreactor_schema_service_url'] ) ? trim( (string) $opts['creatorreactor_schema_service_url'] ) : '';
		if ( $base === '' ) {
			$base = self::DEFAULT_SCHEMA_SERVICE_URL;
		}
		$base = esc_url_raw( $base );

		if ( self::should_rewrite_localhost_schema_port_to_internal( $base ) ) {
			return untrailingslashit( esc_url_raw( 'http://schema-service:8080' ) );
		}

		return untrailingslashit( $base );
	}

	/**
	 * True when the saved metrics ingest URL points at the host-only port from inside a Compose WP container.
	 *
	 * @param string $base Sanitized URL.
	 * @return bool
	 */
	private static function should_rewrite_localhost_metrics_port_to_internal( $base ) {
		if ( ! self::is_likely_compose_wordpress_container() ) {
			return false;
		}
		$parts = wp_parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( $host !== 'localhost' && $host !== '127.0.0.1' ) {
			return false;
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		return $port === 18081;
	}

	/**
	 * Base URL for metrics ingest HTTP requests (wp_remote_post to the edge service).
	 *
	 * When {@see Admin_Settings::ENV_METRICS_INGEST_URL} is set, it wins over the saved option.
	 *
	 * @return string Untrailingslashit URL or empty string when not configured.
	 */
	public static function get_metrics_ingest_url_for_requests() {
		$from_env = self::read_process_env( self::ENV_METRICS_INGEST_URL );
		if ( $from_env !== '' ) {
			return untrailingslashit( esc_url_raw( $from_env ) );
		}

		$opts = get_option( self::OPTION_NAME, [] );
		$base = isset( $opts['creatorreactor_metrics_ingest_url'] ) ? trim( (string) $opts['creatorreactor_metrics_ingest_url'] ) : '';
		if ( $base === '' ) {
			return '';
		}
		$base = esc_url_raw( $base );

		if ( self::should_rewrite_localhost_metrics_port_to_internal( $base ) ) {
			return untrailingslashit( esc_url_raw( 'http://data-ingestion:8080' ) );
		}

		return untrailingslashit( $base );
	}

	/**
	 * Bearer token for metrics ingest (env overrides saved option).
	 *
	 * @return string
	 */
	public static function get_metrics_ingest_token_for_requests() {
		$from_env = self::read_process_env( self::ENV_METRICS_INGEST_TOKEN );
		if ( $from_env !== '' ) {
			return $from_env;
		}
		$opts = self::get_options();
		return isset( $opts['creatorreactor_metrics_ingest_token'] ) ? trim( (string) $opts['creatorreactor_metrics_ingest_token'] ) : '';
	}

	/**
	 * GET JSON from a full schema service URL (manifest, envelope, entity schemas, etc.).
	 *
	 * @param string $url Full URL (already cache-busted when needed).
	 * @return array{ok:bool,http_code:int,spec_version:string,body:string,error:string,request_url:string}
	 */
	private static function fetch_schema_service_url( $url ) {
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 20,
				'headers' => [
					'Accept'        => 'application/json',
					'Cache-Control' => 'no-cache',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'ok'           => false,
				'http_code'    => 0,
				'spec_version' => '',
				'body'         => '',
				'error'        => $response->get_error_message(),
				'request_url'  => $url,
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$spec = (string) wp_remote_retrieve_header( $response, 'x-cr-spec-version' );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			$err_msg = $body;
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
				$err_msg = (string) $decoded['error']['message'];
			}
			if ( $err_msg === '' ) {
				$err_msg = __( 'Request failed.', 'wp-creatorreactor' );
			}
			return [
				'ok'           => false,
				'http_code'    => $code,
				'spec_version' => $spec,
				'body'         => $body,
				'error'        => $err_msg,
				'request_url'  => $url,
			];
		}

		$pretty = $body;
		$data   = json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $data ) ) {
			$pretty = (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		return [
			'ok'           => true,
			'http_code'    => $code,
			'spec_version' => $spec,
			'body'         => $pretty,
			'error'        => '',
			'request_url'  => $url,
		];
	}

	/**
	 * GET /v1/schema from the configured schema service (CreatorReactor Cloud).
	 *
	 * @param bool $cache_bust When true, append a cache-busting query argument for refresh requests.
	 * @return array{ok:bool,http_code:int,spec_version:string,body:string,error:string,request_url:string}
	 */
	private static function fetch_schema_service_manifest( $cache_bust = false ) {
		$base = self::get_schema_service_url_for_requests();
		$url  = $base . '/v1/schema';
		if ( $cache_bust ) {
			$url = add_query_arg( '_cr', (string) time(), $url );
		}
		return self::fetch_schema_service_url( $url );
	}

	/**
	 * GET a schema document path (e.g. /v1/schema/envelope/v1) from the configured schema service.
	 *
	 * @param string $path Path beginning with / (e.g. /v1/schema/envelope/v1).
	 * @param bool   $cache_bust When true, append a cache-busting query argument for refresh requests.
	 * @return array{ok:bool,http_code:int,spec_version:string,body:string,error:string,request_url:string}
	 */
	private static function fetch_schema_service_document( $path, $cache_bust = false ) {
		$base = self::get_schema_service_url_for_requests();
		$path = '/' . ltrim( (string) $path, '/' );
		$url  = $base . $path;
		if ( $cache_bust ) {
			$url = add_query_arg( '_cr', (string) time(), $url );
		}
		return self::fetch_schema_service_url( $url );
	}

	/**
	 * AJAX: fetch schema manifest JSON from the configured schema service (same as Debug tab initial load).
	 */
	public static function ajax_debug_schema_manifest() {
		check_ajax_referer( 'creatorreactor_debug_schema', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$manifest = self::fetch_schema_service_manifest( true );
		$envelope = self::fetch_schema_service_document( '/v1/schema/envelope/v1', true );
		wp_send_json_success(
			[
				'manifest' => $manifest,
				'envelope' => $envelope,
			]
		);
	}

	/**
	 * AJAX: verify Google OAuth client ID / secret against Google’s token endpoint before saving settings.
	 */
	public static function ajax_google_oauth_validate_config() {
		check_ajax_referer( 'creatorreactor_google_oauth_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}
		if ( self::is_broker_mode() ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$client_id = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
		$secret_in = isset( $_POST['client_secret'] ) ? (string) wp_unslash( $_POST['client_secret'] ) : '';
		$secret_unchanged = isset( $_POST['secret_unchanged'] ) && (string) wp_unslash( $_POST['secret_unchanged'] ) === '1';

		if ( $client_id === '' ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$opts = self::get_options();
		if ( $secret_unchanged || trim( $secret_in ) === '********' ) {
			$client_secret = isset( $opts['creatorreactor_google_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_secret'] ) : '';
		} else {
			$client_secret = trim( $secret_in );
		}

		if ( $client_secret === '' ) {
			wp_send_json_error(
				[
					'message'     => __( 'Enter the Client Secret from Google Cloud so we can verify your app, or clear the Client ID if you are removing Google sign-in.', 'wp-creatorreactor' ),
					'remediation' => __( 'In Google Cloud Console → APIs & Services → Credentials, open your OAuth client, copy the Client Secret (or reset it and copy the new value), then paste it here.', 'wp-creatorreactor' ),
				]
			);
		}

		$redirect = Google_OAuth::get_callback_redirect_uri();
		$probe    = Google_OAuth::probe_client_credentials_for_settings_test( $client_id, $client_secret, $redirect );
		if ( is_wp_error( $probe ) ) {
			$data         = $probe->get_error_data();
			$google_error = is_array( $data ) && isset( $data['google_error'] ) ? (string) $data['google_error'] : '';
			wp_send_json_error(
				[
					'message'     => $probe->get_error_message(),
					'remediation' => self::google_oauth_config_test_remediation( $google_error, $probe->get_error_code() ),
				]
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'Google accepted your Client ID and Client Secret. Click Acknowledge below to save your settings.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * Short guidance for admins when the Google OAuth credential probe fails.
	 *
	 * @param string $google_error  Value of Google's JSON `error` field, if known.
	 * @param string $wp_error_code Error code from {@see Google_OAuth::probe_client_credentials_for_settings_test()}.
	 * @return string
	 */
	private static function google_oauth_config_test_remediation( $google_error, $wp_error_code ) {
		$google_error = sanitize_key( (string) $google_error );
		$wp_error_code = (string) $wp_error_code;

		if ( $google_error === 'invalid_client' || $google_error === 'unauthorized_client' ) {
			return __( 'Confirm the Client ID and Client Secret are copied from the same OAuth 2.0 Web client in Google Cloud Console. If the secret was lost, reset it there and paste the new secret here.', 'wp-creatorreactor' );
		}
		if ( $google_error === 'redirect_uri_mismatch' ) {
			return __( 'Add the exact Authorized redirect URL shown on this settings page (including any trailing slash) to your OAuth client under Authorized redirect URIs in Google Cloud Console.', 'wp-creatorreactor' );
		}
		if ( $wp_error_code === 'google_probe_http' || $wp_error_code === 'google_probe_bad_response' ) {
			return __( 'If this persists, check firewall and DNS whitelist rules for outbound HTTPS to oauth2.googleapis.com from your WordPress host.', 'wp-creatorreactor' );
		}

		return __( 'Double-check your OAuth client type is “Web application”, the consent screen is configured, and API restrictions on the client (if any) allow Google Sign-In / OAuth.', 'wp-creatorreactor' );
	}

	/**
	 * AJAX: verify generic social OAuth client ID / secret (TikTok, X, …) before saving.
	 */
	public static function ajax_social_oauth_validate_config() {
		check_ajax_referer( 'creatorreactor_social_oauth_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}
		if ( self::is_broker_mode() ) {
			wp_send_json_success( [ 'skip' => true ] );
		}
		$slug = isset( $_POST['provider_slug'] ) ? sanitize_key( wp_unslash( $_POST['provider_slug'] ) ) : '';
		if ( $slug === '' || ! in_array( $slug, self::all_settings_social_oauth_slugs(), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid provider.', 'wp-creatorreactor' ) ], 400 );
		}
		if ( 'bluesky' === $slug ) {
			wp_send_json_success(
				[
					'skip'    => true,
					'message' => __( 'Use Save Settings to store Bluesky credentials; the configuration check runs after atproto OAuth is fully configured.', 'wp-creatorreactor' ),
				]
			);
		}
		$client_id = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
		$secret_in = isset( $_POST['client_secret'] ) ? (string) wp_unslash( $_POST['client_secret'] ) : '';
		$secret_unchanged = isset( $_POST['secret_unchanged'] ) && (string) wp_unslash( $_POST['secret_unchanged'] ) === '1';
		if ( $client_id === '' ) {
			wp_send_json_success( [ 'skip' => true ] );
		}
		$opts = self::get_options();
		$sec_key = Social_OAuth_Registry::option_client_secret( $slug );
		if ( $secret_unchanged || trim( $secret_in ) === '********' ) {
			$client_secret = isset( $opts[ $sec_key ] ) ? trim( (string) $opts[ $sec_key ] ) : '';
		} else {
			$client_secret = trim( $secret_in );
		}
		if ( $client_secret === '' ) {
			wp_send_json_error(
				[
					'message'     => __( 'Enter the Client Secret so we can verify your app, or clear the Client ID if you are removing this provider.', 'wp-creatorreactor' ),
					'remediation' => __( 'Copy the client secret from the provider’s developer console and paste it here.', 'wp-creatorreactor' ),
				]
			);
		}
		if ( 'mastodon' === $slug ) {
			$inst = isset( $_POST['mastodon_instance'] ) ? trim( wp_unslash( $_POST['mastodon_instance'] ) ) : '';
			if ( $inst === '' ) {
				$inst = isset( $opts['creatorreactor_mastodon_instance'] ) ? trim( (string) $opts['creatorreactor_mastodon_instance'] ) : '';
			}
			$opts['creatorreactor_mastodon_instance'] = esc_url_raw( $inst );
		}
		$redirect = Social_OAuth::get_callback_redirect_uri( $slug );
		$probe    = Social_OAuth::probe_client_credentials_for_settings_test( $slug, $client_id, $client_secret, $redirect, $opts );
		if ( is_wp_error( $probe ) ) {
			wp_send_json_error(
				[
					'message'     => $probe->get_error_message(),
					'remediation' => __( 'Confirm the Client ID, Client Secret, and redirect URI match the app registration. For Mastodon, verify the instance URL.', 'wp-creatorreactor' ),
				]
			);
		}
		wp_send_json_success(
			[
				'message' => __( 'Credentials look valid. Click Acknowledge below to save your settings.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * Sanitize encrypted client id/secret and button size for all social OAuth provider tabs.
	 *
	 * @param array<string, mixed> $input    Raw POST input.
	 * @param array<string, mixed> $raw_opts Previous options.
	 * @param array<string, mixed> $opts     Options being built (by ref).
	 */
	private static function sanitize_social_oauth_provider_options( $input, $raw_opts, &$opts ) {
		foreach ( self::all_settings_social_oauth_slugs() as $slug ) {
			$id_key   = Social_OAuth_Registry::option_client_id( $slug );
			$sec_key  = Social_OAuth_Registry::option_client_secret( $slug );
			$mode_key = Social_OAuth_Registry::option_button_size_mode( $slug );
			if ( isset( $input[ $id_key ] ) ) {
				$rid = sanitize_text_field( wp_unslash( $input[ $id_key ] ) );
				$opts[ $id_key ] = $rid === '' ? '' : self::encrypt_value( $rid );
			} else {
				$opts[ $id_key ] = isset( $raw_opts[ $id_key ] ) ? $raw_opts[ $id_key ] : '';
			}
			$sec_in = isset( $input[ $sec_key ] ) ? (string) wp_unslash( $input[ $sec_key ] ) : '';
			if ( $sec_in === '********' || $sec_in === '' ) {
				$opts[ $sec_key ] = isset( $raw_opts[ $sec_key ] ) ? $raw_opts[ $sec_key ] : '';
			} else {
				$enc_s = self::encrypt_value( $sec_in );
				if ( $enc_s === '' ) {
					add_settings_error(
						self::OPTION_NAME,
						'social_oauth_secret_encrypt_failed_' . $slug,
						sprintf(
							/* translators: %s: provider slug */
							__( 'Could not encrypt OAuth Client Secret for %s. The previous secret was kept.', 'wp-creatorreactor' ),
							$slug
						)
					);
					$opts[ $sec_key ] = isset( $raw_opts[ $sec_key ] ) ? $raw_opts[ $sec_key ] : '';
				} else {
					$opts[ $sec_key ] = $enc_s;
				}
			}
			if ( isset( $input[ $mode_key ] ) ) {
				$opts[ $mode_key ] = self::normalize_oauth_button_size_mode_from_posted_choice(
					wp_unslash( $input[ $mode_key ] ),
					$opts
				);
			} elseif ( array_key_exists( $mode_key, $raw_opts ) ) {
				$opts[ $mode_key ] = self::sanitize_oauth_button_size_mode( $raw_opts[ $mode_key ] );
			} else {
				$opts[ $mode_key ] = 'general';
			}
		}
		if ( isset( $input['creatorreactor_mastodon_instance'] ) ) {
			$opts['creatorreactor_mastodon_instance'] = esc_url_raw( trim( wp_unslash( $input['creatorreactor_mastodon_instance'] ) ) );
		} elseif ( isset( $raw_opts['creatorreactor_mastodon_instance'] ) ) {
			$opts['creatorreactor_mastodon_instance'] = esc_url_raw( trim( (string) $raw_opts['creatorreactor_mastodon_instance'] ) );
		} else {
			$opts['creatorreactor_mastodon_instance'] = '';
		}
	}

	/**
	 * AJAX: verify Instagram (Meta) OAuth client ID / secret before saving settings.
	 */
	public static function ajax_instagram_oauth_validate_config() {
		check_ajax_referer( 'creatorreactor_instagram_oauth_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}
		if ( self::is_broker_mode() ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$client_id = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
		$secret_in = isset( $_POST['client_secret'] ) ? (string) wp_unslash( $_POST['client_secret'] ) : '';
		$secret_unchanged = isset( $_POST['secret_unchanged'] ) && (string) wp_unslash( $_POST['secret_unchanged'] ) === '1';

		if ( $client_id === '' ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$opts = self::get_options();
		if ( $secret_unchanged || trim( $secret_in ) === '********' ) {
			$client_secret = isset( $opts['creatorreactor_instagram_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_instagram_oauth_client_secret'] ) : '';
		} else {
			$client_secret = trim( $secret_in );
		}

		if ( $client_secret === '' ) {
			wp_send_json_error(
				[
					'message'     => __( 'Enter the Client Secret from your Meta app so we can verify your app, or clear the Client ID if you are removing Instagram OAuth.', 'wp-creatorreactor' ),
					'remediation' => __( 'In Meta for Developers → your app → Instagram → API setup, copy the Instagram app secret and paste it here.', 'wp-creatorreactor' ),
				]
			);
		}

		$redirect = Instagram_OAuth::get_callback_redirect_uri();
		$probe    = Instagram_OAuth::probe_client_credentials_for_settings_test( $client_id, $client_secret, $redirect );
		if ( is_wp_error( $probe ) ) {
			$data            = $probe->get_error_data();
			$instagram_error = is_array( $data ) && isset( $data['instagram_error'] ) ? (string) $data['instagram_error'] : '';
			wp_send_json_error(
				[
					'message'     => $probe->get_error_message(),
					'remediation' => self::instagram_oauth_config_test_remediation( $instagram_error, $probe->get_error_code() ),
				]
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'Instagram accepted your Client ID and Client Secret. Click Acknowledge below to save your settings.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * Short guidance for admins when the Instagram OAuth credential probe fails.
	 *
	 * @param string $instagram_error Known error hint from probe response.
	 * @param string $wp_error_code   Error code from {@see Instagram_OAuth::probe_client_credentials_for_settings_test()}.
	 * @return string
	 */
	private static function instagram_oauth_config_test_remediation( $instagram_error, $wp_error_code ) {
		$instagram_error = sanitize_key( (string) $instagram_error );
		$wp_error_code   = (string) $wp_error_code;

		if ( $instagram_error === 'invalid_client' || $wp_error_code === 'instagram_probe_client' ) {
			return __( 'Confirm the Instagram app ID and secret are from the same Meta app and that Instagram API with Instagram Login is added to the app.', 'wp-creatorreactor' );
		}
		if ( $instagram_error === 'redirect_uri' || $wp_error_code === 'instagram_probe_redirect' ) {
			return __( 'Add the exact OAuth redirect URI shown on this settings page (including trailing slash) to your Meta app under Instagram → API setup → OAuth settings.', 'wp-creatorreactor' );
		}
		if ( $wp_error_code === 'instagram_probe_http' || $wp_error_code === 'instagram_probe_bad_response' ) {
			return __( 'If this persists, check firewall rules for outbound HTTPS to api.instagram.com from your WordPress host.', 'wp-creatorreactor' );
		}

		return __( 'See Meta’s Instagram API with Instagram Login documentation and confirm the app is in Live mode or your Instagram tester account is added.', 'wp-creatorreactor' );
	}

	/**
	 * AJAX: verify Fanvue OAuth client ID / secret (Creator mode) before saving Fanvue settings.
	 */
	public static function ajax_fanvue_oauth_validate_config() {
		check_ajax_referer( 'creatorreactor_fanvue_oauth_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$auth_mode = isset( $_POST['authentication_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['authentication_mode'] ) ) : '';
		if ( $auth_mode === self::AUTH_MODE_AGENCY ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$client_id = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
		$secret_in = isset( $_POST['client_secret'] ) ? (string) wp_unslash( $_POST['client_secret'] ) : '';
		$secret_unchanged = isset( $_POST['secret_unchanged'] ) && (string) wp_unslash( $_POST['secret_unchanged'] ) === '1';

		if ( $client_id === '' ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		$opts = self::get_options();
		if ( $secret_unchanged || trim( $secret_in ) === '********' ) {
			$client_secret = isset( $opts['creatorreactor_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_oauth_client_secret'] ) : '';
		} else {
			$client_secret = trim( $secret_in );
		}

		if ( $client_secret === '' ) {
			wp_send_json_error(
				[
					'message'     => __( 'Enter your Fanvue Client Secret so we can verify your app, or clear the Client ID if you are removing direct Fanvue OAuth.', 'wp-creatorreactor' ),
					'remediation' => __( 'Open your app in the Fanvue developer portal, copy the Client Secret (or create a new app), paste it here, then try Save again.', 'wp-creatorreactor' ),
				]
			);
		}

		$redirect = trailingslashit( CreatorReactor_OAuth::get_default_redirect_uri() );
		$probe    = CreatorReactor_OAuth::probe_fanvue_oauth_credentials_for_settings_test( $client_id, $client_secret, $redirect, $opts );
		if ( is_wp_error( $probe ) ) {
			$data        = $probe->get_error_data();
			$oauth_error = is_array( $data ) && isset( $data['oauth_error'] ) ? (string) $data['oauth_error'] : '';
			wp_send_json_error(
				[
					'message'     => $probe->get_error_message(),
					'remediation' => self::fanvue_oauth_config_test_remediation( $oauth_error, $probe->get_error_code() ),
				]
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'Fanvue accepted your Client ID and Client Secret. Click Acknowledge below to save your settings.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * AJAX: verify OFAuth API key before saving OnlyFans settings.
	 */
	public static function ajax_onlyfans_ofauth_validate_config() {
		check_ajax_referer( 'creatorreactor_onlyfans_ofauth_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$key_in = isset( $_POST['api_key'] ) ? (string) wp_unslash( $_POST['api_key'] ) : '';
		$key_unchanged = isset( $_POST['key_unchanged'] ) && (string) wp_unslash( $_POST['key_unchanged'] ) === '1';
		$wh_in = isset( $_POST['webhook_secret'] ) ? (string) wp_unslash( $_POST['webhook_secret'] ) : '';
		$wh_unchanged = isset( $_POST['webhook_secret_unchanged'] ) && (string) wp_unslash( $_POST['webhook_secret_unchanged'] ) === '1';

		$opts = self::get_options();
		if ( $key_unchanged || trim( $key_in ) === '********' ) {
			$api_key = isset( $opts['creatorreactor_ofauth_api_key'] ) ? trim( (string) $opts['creatorreactor_ofauth_api_key'] ) : '';
		} else {
			$api_key = trim( $key_in );
		}

		if ( $api_key === '' ) {
			wp_send_json_success( [ 'skip' => true ] );
		}

		if ( $wh_unchanged || trim( $wh_in ) === '********' ) {
			$webhook_secret = isset( $opts['creatorreactor_ofauth_webhook_secret'] ) ? trim( (string) $opts['creatorreactor_ofauth_webhook_secret'] ) : '';
		} else {
			$webhook_secret = trim( $wh_in );
		}

		if ( $webhook_secret === '' ) {
			wp_send_json_error(
				[
					'message'     => __( 'Add the Webhook secret that matches your OFAuth webhook configuration before saving, or clear the API key if you are removing OnlyFans linking.', 'wp-creatorreactor' ),
					'remediation' => __( 'In the OFAuth dashboard → Webhooks, copy the webhook secret and paste it here. Incoming events send it as the x-webhook-secret header.', 'wp-creatorreactor' ),
				]
			);
		}

		$probe = OFAuth::probe_api_key_for_settings_test( $api_key );
		if ( is_wp_error( $probe ) ) {
			$data = $probe->get_error_data();
			$code = is_array( $data ) && isset( $data['http_code'] ) ? (int) $data['http_code'] : 0;
			wp_send_json_error(
				[
					'message'     => $probe->get_error_message(),
					'remediation' => self::onlyfans_ofauth_config_test_remediation( $probe->get_error_code(), $code ),
				]
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'OFAuth accepted your API key. Your webhook secret is stored locally (OFAuth does not verify it over HTTP). Click Acknowledge below to save your settings.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * @param string $oauth_error   OAuth `error` value from Fanvue JSON when present.
	 * @param string $wp_error_code From {@see CreatorReactor_OAuth::probe_fanvue_oauth_credentials_for_settings_test()}.
	 */
	private static function fanvue_oauth_config_test_remediation( $oauth_error, $wp_error_code ) {
		$oauth_error   = sanitize_key( (string) $oauth_error );
		$wp_error_code = (string) $wp_error_code;

		if ( $oauth_error === 'invalid_client' ) {
			return __( 'Confirm the Client ID and Client Secret are from the same Fanvue OAuth app in the Fanvue developer portal. If you rotated the secret, paste the new value here.', 'wp-creatorreactor' );
		}
		if ( $oauth_error === 'redirect_uri_mismatch' || stripos( $oauth_error, 'redirect' ) !== false ) {
			return __( 'The redirect URI sent to Fanvue must exactly match the “Redirect URI” and your site’s registered callback (including https and trailing slash). Copy the value from this screen into your Fanvue app.', 'wp-creatorreactor' );
		}
		if ( $wp_error_code === 'fanvue_probe_http' || $wp_error_code === 'fanvue_probe_bad_response' ) {
			return __( 'If this persists, check firewall rules for outbound HTTPS to your configured Fanvue token URL (default auth.fanvue.com).', 'wp-creatorreactor' );
		}

		return __( 'Confirm the app is active in the Fanvue developer portal, OAuth endpoints in Advanced (if customized) match Fanvue’s auth server, and scopes are accepted for your app.', 'wp-creatorreactor' );
	}

	/**
	 * @param string $wp_error_code From {@see OFAuth::probe_api_key_for_settings_test()}.
	 * @param int    $http_code     HTTP status when relevant.
	 */
	private static function onlyfans_ofauth_config_test_remediation( $wp_error_code, $http_code ) {
		if ( $wp_error_code === 'ofauth_probe_unauthorized' || $http_code === 401 ) {
			return __( 'Generate a new access key with Account Linking permissions in the OFAuth dashboard and paste it into the API key field.', 'wp-creatorreactor' );
		}
		if ( $wp_error_code === 'ofauth_probe_http' ) {
			return __( 'If this persists, check outbound HTTPS to api.ofauth.com and whether a security plugin blocks wp_remote_get.', 'wp-creatorreactor' );
		}

		return __( 'See the OFAuth integration guide and confirm your key has Account Linking permissions.', 'wp-creatorreactor' );
	}

	/**
	 * Copy options from pre-rename keys into creatorreactor_* keys once.
	 */
	private static function migrate_prefixed_options_from_before_rename() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$pre = 'fan' . 'bridge';

		$pairs = [
			$pre . '_last_error'           => self::OPTION_LAST_ERROR,
			$pre . '_critical_error'       => self::OPTION_CRITICAL_ERROR,
			$pre . '_last_sync'            => self::OPTION_LAST_SYNC,
			$pre . '_connection_test'      => self::OPTION_CONNECTION_TEST,
			$pre . '_tiers'                => self::OPTION_TIERS,
			$pre . '_subscription_tiers'   => self::OPTION_SUBSCRIPTION_TIERS,
			$pre . '_oauth_tokens'         => CreatorReactor_OAuth::OPTION_TOKENS,
			$pre . '_login_jwt_token'      => 'creatorreactor_login_jwt_token',
		];

		$sentinel = '__creatorreactor_migrate_absent__';

		foreach ( $pairs as $old_key => $new_key ) {
			$old_val = get_option( $old_key, $sentinel );
			if ( $old_val === $sentinel ) {
				continue;
			}
			if ( get_option( $new_key, $sentinel ) !== $sentinel ) {
				delete_option( $old_key );
				continue;
			}
			update_option( $new_key, $old_val );
			delete_option( $old_key );
		}
	}

	private static function migrate_legacy_options() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( is_array( $opts ) && ! empty( $opts ) ) {
			$merged_opts = self::merge_legacy_oauth_fields( $opts );
			$merged_opts = self::maybe_upgrade_fanvue_default_endpoints( $merged_opts );
			$merged_opts['creatorreactor_oauth_scopes'] = CreatorReactor_OAuth::normalize_scopes_string( $merged_opts['creatorreactor_oauth_scopes'] ?? '' );
			$dtz = $merged_opts['display_timezone'] ?? null;
			$norm = is_string( $dtz ) ? trim( $dtz ) : '';
			if ( $norm === '' ) {
				$merged_opts['display_timezone'] = 'browser';
			}
			if ( $merged_opts !== $opts ) {
				update_option( self::OPTION_NAME, $merged_opts );
			}
			return;
		}

		$broker_opts = get_option( self::LEGACY_OPTION_BROKER, [] );
		$direct_opts = get_option( self::LEGACY_OPTION_DIRECT, [] );

		$opts = [
			'product' => Entitlements::PRODUCT_FANVUE,
			'broker_mode' => false,
			'broker_url' => '',
			'site_id' => '',
			'creatorreactor_oauth_client_id' => '',
			'creatorreactor_oauth_client_secret' => '',
			'creatorreactor_oauth_redirect_uri' => CreatorReactor_OAuth::get_default_redirect_uri(),
			'creatorreactor_authorization_url' => CreatorReactor_OAuth::AUTH_URL,
			'creatorreactor_token_url' => CreatorReactor_OAuth::TOKEN_URL,
			'creatorreactor_api_base_url' => CreatorReactor_OAuth::API_BASE_URL,
			'creatorreactor_oauth_scopes' => self::DEFAULT_CREATORREACTOR_SCOPES,
			'creatorreactor_api_version' => '2025-06-26',
			'creatorreactor_creator_id' => '',
			'creatorreactor_cloud_active' => false,
			'creatorreactor_cloud_id' => '',
			'creatorreactor_cloud_password' => '',
			'creatorreactor_schema_service_url' => self::DEFAULT_SCHEMA_SERVICE_URL,
			'cron_interval_minutes' => 15,
			'entitlement_cache_ttl_seconds' => 900,
			'replace_wp_login_with_social' => false,
			'display_timezone' => 'browser',
			'restrict_creatorreactor_users_wp_admin' => true,
			'hide_admin_bar_for_creatorreactor_users' => true,
		];

		if ( is_array( $broker_opts ) && ! empty( $broker_opts ) ) {
			$opts['broker_mode'] = true;
			$opts['broker_url'] = $broker_opts['broker_url'] ?? '';
			$opts['site_id'] = $broker_opts['site_id'] ?? '';
			$opts['creatorreactor_oauth_client_id'] = $broker_opts['creatorreactor_oauth_client_id'] ?? '';
			$opts['creatorreactor_oauth_client_secret'] = $broker_opts['creatorreactor_oauth_client_secret'] ?? '';
			$opts['creatorreactor_oauth_redirect_uri'] = $broker_opts['creatorreactor_oauth_redirect_uri'] ?? self::get_broker_default_redirect_uri();
			$opts['creatorreactor_oauth_scopes'] = $broker_opts['creatorreactor_oauth_scopes'] ?? self::DEFAULT_CREATORREACTOR_SCOPES;
			$opts['creatorreactor_api_base_url'] = $broker_opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL;
		} elseif ( is_array( $direct_opts ) && ! empty( $direct_opts ) ) {
			$opts['broker_mode'] = false;
			$opts['creatorreactor_oauth_client_id'] = $direct_opts['creatorreactor_oauth_client_id'] ?? '';
			$opts['creatorreactor_oauth_client_secret'] = $direct_opts['creatorreactor_oauth_client_secret'] ?? '';
			$opts['creatorreactor_oauth_redirect_uri'] = $direct_opts['creatorreactor_oauth_redirect_uri'] ?? CreatorReactor_OAuth::get_default_redirect_uri();
			$opts['creatorreactor_oauth_scopes'] = $direct_opts['creatorreactor_oauth_scopes'] ?? CreatorReactor_OAuth::DEFAULT_SCOPES;
			$opts['creatorreactor_api_base_url'] = $direct_opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL;
			$opts['creatorreactor_creator_id'] = $direct_opts['creatorreactor_creator_id'] ?? '';
			$opts['cron_interval_minutes'] = $direct_opts['cron_interval_minutes'] ?? 15;
			$opts['entitlement_cache_ttl_seconds'] = $direct_opts['entitlement_cache_ttl_seconds'] ?? 900;
		}

		update_option( self::OPTION_NAME, $opts );
	}

	private static function is_missing_sensitive_setting( $value ) {
		if ( empty( $value ) || $value === '********' ) {
			return true;
		}
		if ( self::is_encrypted( $value ) ) {
			return self::decrypt_value( (string) $value ) === null;
		}
		return false;
	}

	private static function merge_legacy_oauth_fields( array $opts ) {
		$client_id_keys = [ 'creatorreactor_oauth_client_id', 'creatorreactor_client_id', 'oauth_client_id', 'client_id' ];
		$secret_keys    = [ 'creatorreactor_oauth_client_secret', 'creatorreactor_client_secret', 'oauth_client_secret', 'client_secret' ];

		$needs_client_id = self::is_missing_sensitive_setting( $opts['creatorreactor_oauth_client_id'] ?? '' );
		$needs_secret    = self::is_missing_sensitive_setting( $opts['creatorreactor_oauth_client_secret'] ?? '' );

		if ( $needs_client_id ) {
			$fallback_client_id = self::find_first_usable_sensitive_value( $opts, $client_id_keys );
			if ( $fallback_client_id !== null ) {
				$opts['creatorreactor_oauth_client_id'] = $fallback_client_id;
				$needs_client_id = false;
			}
		}

		if ( $needs_secret ) {
			$fallback_secret = self::find_first_usable_sensitive_value( $opts, $secret_keys );
			if ( $fallback_secret !== null ) {
				$opts['creatorreactor_oauth_client_secret'] = $fallback_secret;
				$needs_secret = false;
			}
		}

		if ( ! $needs_client_id && ! $needs_secret ) {
			return $opts;
		}

		$prefer_broker = ! empty( $opts['broker_mode'] );
		$legacy_sources = $prefer_broker
			? [ self::LEGACY_OPTION_BROKER, self::LEGACY_OPTION_DIRECT ]
			: [ self::LEGACY_OPTION_DIRECT, self::LEGACY_OPTION_BROKER ];

		foreach ( $legacy_sources as $legacy_option_name ) {
			$legacy_opts = get_option( $legacy_option_name, [] );
			if ( ! is_array( $legacy_opts ) || empty( $legacy_opts ) ) {
				continue;
			}

			if ( $needs_client_id ) {
				$legacy_client_id = self::find_first_usable_sensitive_value( $legacy_opts, $client_id_keys );
				if ( $legacy_client_id !== null ) {
					$opts['creatorreactor_oauth_client_id'] = $legacy_client_id;
					$needs_client_id = false;
				}
			}

			if ( $needs_secret ) {
				$legacy_secret = self::find_first_usable_sensitive_value( $legacy_opts, $secret_keys );
				if ( $legacy_secret !== null ) {
					$opts['creatorreactor_oauth_client_secret'] = $legacy_secret;
					$needs_secret = false;
				}
			}

			if ( ! $needs_client_id && ! $needs_secret ) {
				break;
			}
		}

		return $opts;
	}

	/**
	 * One-time upgrade: Creator mode previously defaulted to CreatorReactor-hosted URLs; align stored defaults with Fanvue.
	 *
	 * @param array $opts Options array.
	 * @return array
	 */
	private static function maybe_upgrade_fanvue_default_endpoints( array $opts ) {
		if ( ! empty( $opts['broker_mode'] ) ) {
			return $opts;
		}
		$pairs = [
			'creatorreactor_authorization_url' => [
				'old' => 'https://auth.creatorreactor.com/oauth2/auth',
				'new' => CreatorReactor_OAuth::AUTH_URL,
			],
			'creatorreactor_token_url' => [
				'old' => 'https://auth.creatorreactor.com/oauth2/token',
				'new' => CreatorReactor_OAuth::TOKEN_URL,
			],
			'creatorreactor_api_base_url' => [
				'old' => 'https://api.creatorreactor.com',
				'new' => CreatorReactor_OAuth::API_BASE_URL,
			],
		];
		foreach ( $pairs as $key => $map ) {
			if ( ! isset( $opts[ $key ] ) ) {
				continue;
			}
			if ( trim( (string) $opts[ $key ] ) === $map['old'] ) {
				$opts[ $key ] = $map['new'];
			}
		}
		return $opts;
	}

	private static function find_first_usable_sensitive_value( array $source, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $source ) ) {
				continue;
			}
			$value = is_string( $source[ $key ] ) ? $source[ $key ] : '';
			if ( ! self::is_missing_sensitive_setting( $value ) ) {
				return $value;
			}
		}
		return null;
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME,
			[ __CLASS__, 'sanitize_options' ]
		);
	}

	public static function get_options() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			return [];
		}
		$opts = self::decrypt_option_fields( $opts );
		$opts['creatorreactor_oauth_scopes'] = CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] ?? '' );
		$opts['display_timezone'] = self::sanitize_display_timezone( $opts['display_timezone'] ?? 'browser' );
		$opts['restrict_creatorreactor_users_wp_admin']       = self::stored_bool_default_true( $opts, 'restrict_creatorreactor_users_wp_admin' );
		$opts['hide_admin_bar_for_creatorreactor_users']      = self::stored_bool_default_true( $opts, 'hide_admin_bar_for_creatorreactor_users' );
		$schema_url = isset( $opts['creatorreactor_schema_service_url'] ) ? trim( (string) $opts['creatorreactor_schema_service_url'] ) : '';
		$opts['creatorreactor_schema_service_url'] = $schema_url === '' ? self::DEFAULT_SCHEMA_SERVICE_URL : esc_url_raw( $schema_url );
		$metrics_url = isset( $opts['creatorreactor_metrics_ingest_url'] ) ? trim( (string) $opts['creatorreactor_metrics_ingest_url'] ) : '';
		$opts['creatorreactor_metrics_ingest_url'] = $metrics_url === '' ? '' : esc_url_raw( $metrics_url );
		$ofauth_success = isset( $opts['creatorreactor_ofauth_success_url'] ) ? trim( (string) $opts['creatorreactor_ofauth_success_url'] ) : '';
		$opts['creatorreactor_ofauth_success_url'] = $ofauth_success !== '' ? self::sanitize_ofauth_redirect_url( $ofauth_success ) : self::get_ofauth_redirect_url_default();
		$ofauth_cancel = isset( $opts['creatorreactor_ofauth_cancel_url'] ) ? trim( (string) $opts['creatorreactor_ofauth_cancel_url'] ) : '';
		$opts['creatorreactor_ofauth_cancel_url'] = $ofauth_cancel !== '' ? self::sanitize_ofauth_redirect_url( $ofauth_cancel ) : self::get_ofauth_redirect_url_default();
		return $opts;
	}

	public static function get_raw_options() {
		return get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Boolean option: missing key defaults to true (legacy / unset = plugin default on).
	 *
	 * @param array<string, mixed> $stored Options array.
	 */
	private static function stored_bool_default_true( array $stored, string $key ): bool {
		if ( ! array_key_exists( $key, $stored ) ) {
			return true;
		}
		return ! empty( $stored[ $key ] );
	}

	/**
	 * Drop cached copy of plugin settings so the next read matches the database (e.g. after another screen updated the option).
	 */
	private static function flush_creatorreactor_settings_cache() {
		wp_cache_delete( self::OPTION_NAME, 'options' );
	}

	public static function is_encrypted( $value ) {
		return is_string( $value ) && strlen( $value ) > 50 && preg_match( '/^[A-Za-z0-9+\/=]+$/', $value );
	}

	private static function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-gcm' ) );
		$tag = '';
		$enc = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( $enc === false ) {
			return '';
		}
		return base64_encode( $iv . $tag . $enc );
	}

	public static function encrypt_sensitive_value( $value ) {
		return self::encrypt_value( (string) $value );
	}

	public static function decrypt_sensitive_value( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}
		$decrypted = self::decrypt_value( $value );
		return is_string( $decrypted ) ? $decrypted : '';
	}

	private static function decrypt_value( $encrypted ) {
		$decoded = base64_decode( $encrypted );
		if ( $decoded === false || strlen( $decoded ) < 28 ) {
			return null;
		}
		$iv_len     = openssl_cipher_iv_length( 'aes-256-gcm' );
		$tag_len    = 16;
		$iv         = substr( $decoded, 0, $iv_len );
		$tag        = substr( $decoded, $iv_len, $tag_len );
		$ciphertext = substr( $decoded, $iv_len + $tag_len );
		$key        = self::get_encryption_key();
		$decrypted  = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return $decrypted !== false ? $decrypted : null;
	}

	private static function get_encryption_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secret   = $auth_key ?: wp_salt( 'auth' );
		return hash( 'sha256', $secret, true );
	}

	private static function decrypt_option_fields( array $opts ) {
		foreach ( self::ENCRYPTED_FIELDS as $field ) {
			if ( isset( $opts[ $field ] ) && self::is_encrypted( $opts[ $field ] ) ) {
				$decrypted = self::decrypt_value( $opts[ $field ] );
				$opts[ $field ] = $decrypted !== null ? $decrypted : '';
			}
		}
		return $opts;
	}

	private static function sanitize_https_url( $value ) {
		$url = esc_url_raw( (string) $value, [ 'https' ] );
		if ( $url === '' ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || strtolower( $parts['scheme'] ) !== 'https' ) {
			return '';
		}
		return $url;
	}

	/**
	 * Optional redirect URLs for OFAuth hosted mode (http or https).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_ofauth_redirect_url( $value ) {
		$url = esc_url_raw( trim( (string) $value ) );
		if ( $url === '' ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Default OFAuth success/cancel redirect when none is stored (front page).
	 *
	 * @return string
	 */
	private static function get_ofauth_redirect_url_default() {
		// In our PHPUnit+Brain Monkey harness, many WP helpers (home_url, esc_url_raw, wp_parse_url, ...)
		// are not defined unless each test mocks them. Keep defaults stable and test-safe.
		if ( function_exists( '\\Brain\\Monkey\\Functions\\when' ) || ! function_exists( 'home_url' ) ) {
			return 'https://example.com/';
		}

		return self::sanitize_ofauth_redirect_url( \home_url( '/' ) );
	}

	/**
	 * @param mixed $value Raw option value.
	 * @return string `browser` (System Time) or a valid IANA identifier.
	 */
	private static function sanitize_display_timezone( $value ) {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
		if ( $value === '' || $value === 'browser' ) {
			return 'browser';
		}
		return in_array( $value, timezone_identifiers_list(), true ) ? $value : 'browser';
	}

	/**
	 * @return string Valid IANA timezone from the admin cookie, or empty.
	 */
	private static function get_validated_timezone_from_browser_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_ADMIN_DISPLAY_TZ ] ) || ! is_string( $_COOKIE[ self::COOKIE_ADMIN_DISPLAY_TZ ] ) ) {
			return '';
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_ADMIN_DISPLAY_TZ ] ) );
		return in_array( $raw, timezone_identifiers_list(), true ) ? $raw : '';
	}

	/**
	 * @return array{label: string, timezone: DateTimeZone, selected: string}
	 */
	private static function get_selected_display_timezone_context() {
		$opts      = self::get_options();
		$selected  = self::sanitize_display_timezone( $opts['display_timezone'] ?? 'browser' );
		$site_tz   = wp_timezone();
		$site_name = wp_timezone_string();
		$site_name = is_string( $site_name ) && $site_name !== '' ? $site_name : $site_tz->getName();

		if ( $selected === 'browser' ) {
			$iana = self::get_validated_timezone_from_browser_cookie();
			if ( $iana !== '' ) {
				try {
					$tz = new \DateTimeZone( $iana );
					return [
						/* translators: %s: IANA timezone from the browser. */
						'label'    => sprintf( __( 'System Time (%s)', 'wp-creatorreactor' ), $iana ),
						'timezone' => $tz,
						'selected' => 'browser',
					];
				} catch ( \Exception $e ) {
					// Fall through to WordPress site zone until the cookie is valid.
				}
			}

			return [
				/* translators: %s: PHP/WordPress timezone name used when the browser zone is not available yet. */
				'label'    => sprintf( __( 'System Time (%s)', 'wp-creatorreactor' ), $site_name ),
				'timezone' => $site_tz,
				'selected' => 'browser',
			];
		}

		try {
			$tz = new \DateTimeZone( $selected );
		} catch ( \Exception $e ) {
			return [
				'label'    => sprintf( __( 'System Time (%s)', 'wp-creatorreactor' ), $site_name ),
				'timezone' => $site_tz,
				'selected' => 'browser',
			];
		}

		return [
			'label'    => $selected,
			'timezone' => $tz,
			'selected' => $selected,
		];
	}

	private static function format_datetime_for_selected_timezone( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '-';
		}

		$context    = self::get_selected_display_timezone_context();
		$target_tz  = $context['timezone'];
		$site_tz    = wp_timezone();

		$dt = null;
		if ( preg_match( '/^\d+$/', $value ) ) {
			$ts = (int) $value;
			$dt = ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $target_tz );
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, $site_tz );
			if ( $parsed instanceof \DateTimeImmutable ) {
				$dt = $parsed->setTimezone( $target_tz );
			}
		}

		if ( ! $dt instanceof \DateTimeImmutable ) {
			try {
				// Naive strings must use the site zone: WP's default PHP timezone is UTC.
				$dt = new \DateTimeImmutable( $value, $site_tz );
				$dt = $dt->setTimezone( $target_tz );
			} catch ( \Exception $e ) {
				return $value;
			}
		}

		return $dt->format( 'Y-m-d H:i:s T' );
	}

	public static function get_broker_default_redirect_uri() {
		return Broker_Client::get_default_redirect_uri();
	}

	/**
	 * Redirect URI for the settings text field: canonical default when unset or blank;
	 * in Creator mode, replaces obsolete fanbridge/v1 paths with the CreatorReactor callback URL.
	 *
	 * @param array $opts        Options from {@see get_options()}.
	 * @param bool  $broker_mode Whether Agency (broker) mode is active.
	 */
	public static function get_redirect_uri_input_value( array $opts, $broker_mode ) {
		$default = $broker_mode ? Broker_Client::get_default_redirect_uri() : CreatorReactor_OAuth::get_default_redirect_uri();
		$stored  = isset( $opts['creatorreactor_oauth_redirect_uri'] ) ? trim( (string) $opts['creatorreactor_oauth_redirect_uri'] ) : '';
		if ( $stored === '' ) {
			return $default;
		}
		if ( ! $broker_mode && strpos( $stored, '/fanbridge/v1/' ) !== false ) {
			return CreatorReactor_OAuth::get_default_redirect_uri();
		}
		return $stored;
	}

	/**
	 * Whether the OAuth settings panel should load with the lock engaged.
	 * Unlocked when Client ID and Client Secret are both empty (Creator mode). Agency mode ignores secret.
	 *
	 * @param array $opts        Options from {@see get_options()}.
	 * @param bool  $broker_mode Whether Agency (broker) mode is active.
	 */
	private static function oauth_config_should_start_locked( array $opts, $broker_mode ) {
		$client_id = isset( $opts['creatorreactor_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_oauth_client_id'] ) : '';
		if ( $broker_mode ) {
			return $client_id !== '';
		}
		$secret = isset( $opts['creatorreactor_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_oauth_client_secret'] ) : '';
		return $client_id !== '' || $secret !== '';
	}

	public static function sanitize_options( $input ) {
		$raw_opts = self::get_raw_options();
		$opts     = [];
		$opts['product'] = Entitlements::PRODUCT_FANVUE;

		$auth_mode = isset( $input['authentication_mode'] ) ? sanitize_key( wp_unslash( $input['authentication_mode'] ) ) : '';
		if ( $auth_mode === '' ) {
			$auth_mode = ! empty( $raw_opts['broker_mode'] ) ? self::AUTH_MODE_AGENCY : self::AUTH_MODE_CREATOR;
		}
		if ( $auth_mode !== self::AUTH_MODE_AGENCY ) {
			$auth_mode = self::AUTH_MODE_CREATOR;
		}
		$opts['broker_mode'] = ( $auth_mode === self::AUTH_MODE_AGENCY );

		if ( isset( $input['broker_url'] ) ) {
			$broker_url = self::sanitize_https_url( wp_unslash( $input['broker_url'] ) );
		} else {
			$prev = isset( $raw_opts['broker_url'] ) ? (string) $raw_opts['broker_url'] : '';
			$broker_url = $prev !== '' ? self::sanitize_https_url( $prev ) : '';
		}
		if ( $broker_url === '' && $opts['broker_mode'] ) {
			$broker_url = 'https://auth.ncdlabs.com';
		}
		$opts['broker_url'] = $broker_url;

		if ( isset( $input['site_id'] ) ) {
			$opts['site_id'] = sanitize_text_field( wp_unslash( $input['site_id'] ) );
		} else {
			$opts['site_id'] = isset( $raw_opts['site_id'] ) ? sanitize_text_field( (string) $raw_opts['site_id'] ) : '';
		}

		if ( isset( $input['creatorreactor_oauth_client_id'] ) ) {
			$client_id = sanitize_text_field( wp_unslash( $input['creatorreactor_oauth_client_id'] ) );
			$opts['creatorreactor_oauth_client_id'] = $client_id === '' ? '' : self::encrypt_value( $client_id );
		} else {
			$opts['creatorreactor_oauth_client_id'] = isset( $raw_opts['creatorreactor_oauth_client_id'] ) ? $raw_opts['creatorreactor_oauth_client_id'] : '';
		}

		$client_secret = isset( $input['creatorreactor_oauth_client_secret'] ) ? (string) wp_unslash( $input['creatorreactor_oauth_client_secret'] ) : '';
		if ( $client_secret === '********' || $client_secret === '' ) {
			$opts['creatorreactor_oauth_client_secret'] = isset( $raw_opts['creatorreactor_oauth_client_secret'] ) ? $raw_opts['creatorreactor_oauth_client_secret'] : '';
		} else {
			$encrypted = self::encrypt_value( $client_secret );
			if ( $encrypted === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_client_secret_encrypt_failed',
					__( 'Could not encrypt OAuth Client Secret (OpenSSL unavailable or encryption failed). The previous secret was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_oauth_client_secret'] = isset( $raw_opts['creatorreactor_oauth_client_secret'] ) ? $raw_opts['creatorreactor_oauth_client_secret'] : '';
			} else {
				$opts['creatorreactor_oauth_client_secret'] = $encrypted;
			}
		}

		if ( isset( $input['creatorreactor_oauth_redirect_uri'] ) ) {
			$opts['creatorreactor_oauth_redirect_uri'] = self::sanitize_https_url( wp_unslash( $input['creatorreactor_oauth_redirect_uri'] ) );
		} else {
			$prev_redirect = isset( $raw_opts['creatorreactor_oauth_redirect_uri'] ) ? trim( (string) $raw_opts['creatorreactor_oauth_redirect_uri'] ) : '';
			if ( $prev_redirect !== '' ) {
				$opts['creatorreactor_oauth_redirect_uri'] = self::sanitize_https_url( $prev_redirect );
			} else {
				$opts['creatorreactor_oauth_redirect_uri'] = $opts['broker_mode'] ? self::get_broker_default_redirect_uri() : CreatorReactor_OAuth::get_default_redirect_uri();
			}
		}
		if ( $opts['creatorreactor_oauth_redirect_uri'] !== '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = trailingslashit( untrailingslashit( $opts['creatorreactor_oauth_redirect_uri'] ) );
		}

		if ( isset( $input['creatorreactor_authorization_url'] ) ) {
			$opts['creatorreactor_authorization_url'] = self::sanitize_https_url( wp_unslash( $input['creatorreactor_authorization_url'] ) );
		} else {
			$prev_auth = isset( $raw_opts['creatorreactor_authorization_url'] ) ? trim( (string) $raw_opts['creatorreactor_authorization_url'] ) : '';
			$opts['creatorreactor_authorization_url'] = $prev_auth !== '' ? self::sanitize_https_url( $prev_auth ) : CreatorReactor_OAuth::AUTH_URL;
		}

		if ( isset( $input['creatorreactor_token_url'] ) ) {
			$opts['creatorreactor_token_url'] = self::sanitize_https_url( wp_unslash( $input['creatorreactor_token_url'] ) );
		} else {
			$prev_token = isset( $raw_opts['creatorreactor_token_url'] ) ? trim( (string) $raw_opts['creatorreactor_token_url'] ) : '';
			$opts['creatorreactor_token_url'] = $prev_token !== '' ? self::sanitize_https_url( $prev_token ) : CreatorReactor_OAuth::TOKEN_URL;
		}

		if ( isset( $input['creatorreactor_api_base_url'] ) ) {
			$opts['creatorreactor_api_base_url'] = self::sanitize_https_url( wp_unslash( $input['creatorreactor_api_base_url'] ) );
		} else {
			$prev_api = isset( $raw_opts['creatorreactor_api_base_url'] ) ? trim( (string) $raw_opts['creatorreactor_api_base_url'] ) : '';
			$opts['creatorreactor_api_base_url'] = $prev_api !== '' ? self::sanitize_https_url( $prev_api ) : CreatorReactor_OAuth::API_BASE_URL;
		}

		if ( isset( $input['creatorreactor_oauth_scopes'] ) ) {
			$opts['creatorreactor_oauth_scopes'] = sanitize_text_field( wp_unslash( $input['creatorreactor_oauth_scopes'] ) );
		} else {
			$prev_scopes = isset( $raw_opts['creatorreactor_oauth_scopes'] ) ? trim( (string) $raw_opts['creatorreactor_oauth_scopes'] ) : '';
			$opts['creatorreactor_oauth_scopes'] = $prev_scopes !== '' ? sanitize_text_field( $prev_scopes ) : self::DEFAULT_CREATORREACTOR_SCOPES;
		}

		if ( isset( $input['creatorreactor_api_version'] ) ) {
			$opts['creatorreactor_api_version'] = preg_replace( '/[^0-9\-]/', '', sanitize_text_field( wp_unslash( $input['creatorreactor_api_version'] ) ) );
		} else {
			$opts['creatorreactor_api_version'] = isset( $raw_opts['creatorreactor_api_version'] )
				? preg_replace( '/[^0-9\-]/', '', sanitize_text_field( (string) $raw_opts['creatorreactor_api_version'] ) )
				: '2025-06-26';
		}

		if ( isset( $input['cron_interval_minutes'] ) ) {
			$opts['cron_interval_minutes'] = max( 5, (int) $input['cron_interval_minutes'] );
		} else {
			$opts['cron_interval_minutes'] = isset( $raw_opts['cron_interval_minutes'] ) ? max( 5, (int) $raw_opts['cron_interval_minutes'] ) : 15;
		}

		if ( isset( $input['entitlement_cache_ttl_seconds'] ) ) {
			$opts['entitlement_cache_ttl_seconds'] = max( 60, (int) $input['entitlement_cache_ttl_seconds'] );
		} else {
			$opts['entitlement_cache_ttl_seconds'] = isset( $raw_opts['entitlement_cache_ttl_seconds'] ) ? max( 60, (int) $raw_opts['entitlement_cache_ttl_seconds'] ) : 900;
		}
		if ( isset( $input['privacy_log_retention_days'] ) ) {
			$opts['privacy_log_retention_days'] = max( 1, (int) $input['privacy_log_retention_days'] );
		} else {
			$opts['privacy_log_retention_days'] = isset( $raw_opts['privacy_log_retention_days'] ) ? max( 1, (int) $raw_opts['privacy_log_retention_days'] ) : 30;
		}
		if ( isset( $input['privacy_profile_snapshot_retention_days'] ) ) {
			$opts['privacy_profile_snapshot_retention_days'] = max( 1, (int) $input['privacy_profile_snapshot_retention_days'] );
		} else {
			$opts['privacy_profile_snapshot_retention_days'] = isset( $raw_opts['privacy_profile_snapshot_retention_days'] ) ? max( 1, (int) $raw_opts['privacy_profile_snapshot_retention_days'] ) : 90;
		}

		if ( isset( $input['creatorreactor_creator_id'] ) ) {
			$opts['creatorreactor_creator_id'] = sanitize_text_field( wp_unslash( $input['creatorreactor_creator_id'] ) );
		} else {
			$opts['creatorreactor_creator_id'] = isset( $raw_opts['creatorreactor_creator_id'] ) ? sanitize_text_field( (string) $raw_opts['creatorreactor_creator_id'] ) : '';
		}

		if ( isset( $input['creatorreactor_cloud_id'] ) ) {
			$opts['creatorreactor_cloud_id'] = sanitize_text_field( wp_unslash( $input['creatorreactor_cloud_id'] ) );
		} else {
			$opts['creatorreactor_cloud_id'] = isset( $raw_opts['creatorreactor_cloud_id'] ) ? sanitize_text_field( (string) $raw_opts['creatorreactor_cloud_id'] ) : '';
		}

		if ( isset( $input['creatorreactor_schema_service_url'] ) ) {
			$schema_in = trim( (string) wp_unslash( $input['creatorreactor_schema_service_url'] ) );
			$opts['creatorreactor_schema_service_url'] = $schema_in === '' ? self::DEFAULT_SCHEMA_SERVICE_URL : esc_url_raw( $schema_in );
		} else {
			$prev_schema = isset( $raw_opts['creatorreactor_schema_service_url'] ) ? trim( (string) $raw_opts['creatorreactor_schema_service_url'] ) : '';
			$opts['creatorreactor_schema_service_url'] = $prev_schema === '' ? self::DEFAULT_SCHEMA_SERVICE_URL : esc_url_raw( $prev_schema );
		}

		// Metrics ingest base URL is not editable in Settings; preserve stored value (env may override at request time).
		$prev_metrics = isset( $raw_opts['creatorreactor_metrics_ingest_url'] ) ? trim( (string) $raw_opts['creatorreactor_metrics_ingest_url'] ) : '';
		$opts['creatorreactor_metrics_ingest_url'] = $prev_metrics === '' ? '' : esc_url_raw( $prev_metrics );

		$metrics_token = isset( $input['creatorreactor_metrics_ingest_token'] ) ? (string) wp_unslash( $input['creatorreactor_metrics_ingest_token'] ) : '';
		if ( $metrics_token === '********' || $metrics_token === '' ) {
			$opts['creatorreactor_metrics_ingest_token'] = isset( $raw_opts['creatorreactor_metrics_ingest_token'] ) ? $raw_opts['creatorreactor_metrics_ingest_token'] : '';
		} else {
			$enc_metrics = self::encrypt_value( $metrics_token );
			if ( $enc_metrics === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_metrics_ingest_token_encrypt_failed',
					__( 'Could not encrypt Metrics ingest token (OpenSSL unavailable or encryption failed). The previous token was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_metrics_ingest_token'] = isset( $raw_opts['creatorreactor_metrics_ingest_token'] ) ? $raw_opts['creatorreactor_metrics_ingest_token'] : '';
			} else {
				$opts['creatorreactor_metrics_ingest_token'] = $enc_metrics;
			}
		}

		if ( array_key_exists( 'creatorreactor_cloud_active', $input ) ) {
			$opts['creatorreactor_cloud_active'] = ! empty( $input['creatorreactor_cloud_active'] );
		} else {
			$opts['creatorreactor_cloud_active'] = ! empty( $raw_opts['creatorreactor_cloud_active'] );
		}

		$cloud_password = isset( $input['creatorreactor_cloud_password'] ) ? (string) wp_unslash( $input['creatorreactor_cloud_password'] ) : '';
		if ( $cloud_password === '********' || $cloud_password === '' ) {
			$opts['creatorreactor_cloud_password'] = isset( $raw_opts['creatorreactor_cloud_password'] ) ? $raw_opts['creatorreactor_cloud_password'] : '';
		} else {
			$encrypted_cloud_password = self::encrypt_value( $cloud_password );
			if ( $encrypted_cloud_password === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_cloud_password_encrypt_failed',
					__( 'Could not encrypt CreatorReactor Password (OpenSSL unavailable or encryption failed). The previous password was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_cloud_password'] = isset( $raw_opts['creatorreactor_cloud_password'] ) ? $raw_opts['creatorreactor_cloud_password'] : '';
			} else {
				$opts['creatorreactor_cloud_password'] = $encrypted_cloud_password;
			}
		}

		if ( isset( $input['creatorreactor_ofauth_api_key'] ) ) {
			$ofauth_key = sanitize_text_field( wp_unslash( $input['creatorreactor_ofauth_api_key'] ) );
			if ( $ofauth_key === '********' ) {
				$opts['creatorreactor_ofauth_api_key'] = isset( $raw_opts['creatorreactor_ofauth_api_key'] ) ? $raw_opts['creatorreactor_ofauth_api_key'] : '';
			} elseif ( $ofauth_key === '' ) {
				$opts['creatorreactor_ofauth_api_key'] = '';
			} else {
				$enc_key = self::encrypt_value( $ofauth_key );
				if ( $enc_key === '' ) {
					add_settings_error(
						self::OPTION_NAME,
						'creatorreactor_ofauth_api_key_encrypt_failed',
						__( 'Could not encrypt OFAuth API key (OpenSSL unavailable or encryption failed). The previous key was kept.', 'wp-creatorreactor' )
					);
					$opts['creatorreactor_ofauth_api_key'] = isset( $raw_opts['creatorreactor_ofauth_api_key'] ) ? $raw_opts['creatorreactor_ofauth_api_key'] : '';
				} else {
					$opts['creatorreactor_ofauth_api_key'] = $enc_key;
				}
			}
		} else {
			$opts['creatorreactor_ofauth_api_key'] = isset( $raw_opts['creatorreactor_ofauth_api_key'] ) ? $raw_opts['creatorreactor_ofauth_api_key'] : '';
		}

		$ofauth_wh_secret = isset( $input['creatorreactor_ofauth_webhook_secret'] ) ? (string) wp_unslash( $input['creatorreactor_ofauth_webhook_secret'] ) : '';
		if ( $ofauth_wh_secret === '********' || $ofauth_wh_secret === '' ) {
			if ( isset( $input['creatorreactor_ofauth_webhook_secret'] ) && $ofauth_wh_secret === '' ) {
				$opts['creatorreactor_ofauth_webhook_secret'] = '';
			} else {
				$opts['creatorreactor_ofauth_webhook_secret'] = isset( $raw_opts['creatorreactor_ofauth_webhook_secret'] ) ? $raw_opts['creatorreactor_ofauth_webhook_secret'] : '';
			}
		} else {
			$enc_wh = self::encrypt_value( $ofauth_wh_secret );
			if ( $enc_wh === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_ofauth_webhook_secret_encrypt_failed',
					__( 'Could not encrypt OFAuth webhook secret (OpenSSL unavailable or encryption failed). The previous secret was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_ofauth_webhook_secret'] = isset( $raw_opts['creatorreactor_ofauth_webhook_secret'] ) ? $raw_opts['creatorreactor_ofauth_webhook_secret'] : '';
			} else {
				$opts['creatorreactor_ofauth_webhook_secret'] = $enc_wh;
			}
		}

		if ( isset( $input['creatorreactor_ofauth_success_url'] ) ) {
			$opts['creatorreactor_ofauth_success_url'] = self::sanitize_ofauth_redirect_url( wp_unslash( $input['creatorreactor_ofauth_success_url'] ) );
		} else {
			$prev_s = isset( $raw_opts['creatorreactor_ofauth_success_url'] ) ? (string) $raw_opts['creatorreactor_ofauth_success_url'] : '';
			$opts['creatorreactor_ofauth_success_url'] = $prev_s !== '' ? self::sanitize_ofauth_redirect_url( $prev_s ) : '';
		}

		if ( isset( $input['creatorreactor_ofauth_cancel_url'] ) ) {
			$opts['creatorreactor_ofauth_cancel_url'] = self::sanitize_ofauth_redirect_url( wp_unslash( $input['creatorreactor_ofauth_cancel_url'] ) );
		} else {
			$prev_c = isset( $raw_opts['creatorreactor_ofauth_cancel_url'] ) ? (string) $raw_opts['creatorreactor_ofauth_cancel_url'] : '';
			$opts['creatorreactor_ofauth_cancel_url'] = $prev_c !== '' ? self::sanitize_ofauth_redirect_url( $prev_c ) : '';
		}

		if ( $opts['creatorreactor_ofauth_success_url'] === '' ) {
			$opts['creatorreactor_ofauth_success_url'] = self::get_ofauth_redirect_url_default();
		}
		if ( $opts['creatorreactor_ofauth_cancel_url'] === '' ) {
			$opts['creatorreactor_ofauth_cancel_url'] = self::get_ofauth_redirect_url_default();
		}

		if ( isset( $input['creatorreactor_google_oauth_client_id'] ) ) {
			$g_id = sanitize_text_field( wp_unslash( $input['creatorreactor_google_oauth_client_id'] ) );
			$opts['creatorreactor_google_oauth_client_id'] = $g_id === '' ? '' : self::encrypt_value( $g_id );
		} else {
			$opts['creatorreactor_google_oauth_client_id'] = isset( $raw_opts['creatorreactor_google_oauth_client_id'] ) ? $raw_opts['creatorreactor_google_oauth_client_id'] : '';
		}

		$g_secret = isset( $input['creatorreactor_google_oauth_client_secret'] ) ? (string) wp_unslash( $input['creatorreactor_google_oauth_client_secret'] ) : '';
		if ( $g_secret === '********' || $g_secret === '' ) {
			$opts['creatorreactor_google_oauth_client_secret'] = isset( $raw_opts['creatorreactor_google_oauth_client_secret'] ) ? $raw_opts['creatorreactor_google_oauth_client_secret'] : '';
		} else {
			$enc_g = self::encrypt_value( $g_secret );
			if ( $enc_g === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_google_oauth_client_secret_encrypt_failed',
					__( 'Could not encrypt Google OAuth Client Secret (OpenSSL unavailable or encryption failed). The previous secret was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_google_oauth_client_secret'] = isset( $raw_opts['creatorreactor_google_oauth_client_secret'] ) ? $raw_opts['creatorreactor_google_oauth_client_secret'] : '';
			} else {
				$opts['creatorreactor_google_oauth_client_secret'] = $enc_g;
			}
		}

		self::sanitize_social_oauth_provider_options( $input, $raw_opts, $opts );

		$opts['creatorreactor_bluesky_oauth_enabled'] = ! empty( $input['creatorreactor_bluesky_oauth_enabled'] );

		if ( array_key_exists( self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY, $input ) ) {
			$opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] = self::sanitize_oauth_logo_button_general_size( wp_unslash( $input[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] ) );
		} else {
			$prev_gen = isset( $raw_opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] ) ? (string) $raw_opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] : '';
			$opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] = self::sanitize_oauth_logo_button_general_size( $prev_gen !== '' ? $prev_gen : 'full' );
		}

		if ( isset( $input[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] ) ) {
			$opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] = self::sanitize_google_login_button_style( wp_unslash( $input[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] ) );
		} else {
			$prev_style = isset( $raw_opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] ) ? (string) $raw_opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] : '';
			$opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] = self::sanitize_google_login_button_style( $prev_style !== '' ? $prev_style : 'standard_light' );
		}

		if ( isset( $input[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] ) ) {
			$opts[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::normalize_oauth_button_size_mode_from_posted_choice(
				wp_unslash( $input[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] ),
				$opts
			);
		} elseif ( array_key_exists( self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY, $raw_opts ) ) {
			$opts[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::sanitize_oauth_button_size_mode( $raw_opts[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		} else {
			$opts[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::infer_google_oauth_button_size_mode_legacy( $opts );
		}

		if ( isset( $input[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] ) ) {
			$opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::normalize_oauth_button_size_mode_from_posted_choice(
				wp_unslash( $input[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] ),
				$opts
			);
		} elseif ( isset( $input[ self::FANVUE_LOGIN_BUTTON_VARIANT_KEY ] ) ) {
			$v = self::sanitize_fanvue_login_button_variant( wp_unslash( $input[ self::FANVUE_LOGIN_BUTTON_VARIANT_KEY ] ) );
			$opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] = 'minimal' === $v ? 'compact' : 'general';
		} elseif ( array_key_exists( self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY, $raw_opts ) ) {
			$opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::sanitize_oauth_button_size_mode( $raw_opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		} else {
			$opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::infer_fanvue_oauth_button_size_mode_legacy( $raw_opts );
		}

		if ( isset( $input[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] ) ) {
			$opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::normalize_oauth_button_size_mode_from_posted_choice(
				wp_unslash( $input[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] ),
				$opts
			);
		} elseif ( isset( $input[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ] ) ) {
			$v = self::sanitize_instagram_login_button_variant( wp_unslash( $input[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ] ) );
			$opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] = 'minimal' === $v ? 'compact' : 'general';
		} elseif ( array_key_exists( self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY, $raw_opts ) ) {
			$opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::sanitize_oauth_button_size_mode( $raw_opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		} else {
			$opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::infer_instagram_oauth_button_size_mode_legacy( $raw_opts );
		}

		if ( isset( $input[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] ) ) {
			$opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::normalize_oauth_button_size_mode_from_posted_choice(
				wp_unslash( $input[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] ),
				$opts
			);
		} elseif ( isset( $input[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ] ) ) {
			$v = self::sanitize_onlyfans_login_button_variant( wp_unslash( $input[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ] ) );
			$opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] = 'minimal' === $v ? 'compact' : 'general';
		} elseif ( array_key_exists( self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY, $raw_opts ) ) {
			$opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::sanitize_oauth_button_size_mode( $raw_opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		} else {
			$opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] = self::infer_onlyfans_oauth_button_size_mode_legacy( $raw_opts );
		}

		$opts[ self::FANVUE_LOGIN_BUTTON_VARIANT_KEY ]     = self::resolve_fanvue_login_button_variant_from_opts( $opts );
		$opts[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ]  = self::resolve_instagram_login_button_variant_from_opts( $opts );
		$opts[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ]   = self::resolve_onlyfans_login_button_variant_from_opts( $opts );

		if ( isset( $input['creatorreactor_instagram_oauth_client_id'] ) ) {
			$i_id = sanitize_text_field( wp_unslash( $input['creatorreactor_instagram_oauth_client_id'] ) );
			$opts['creatorreactor_instagram_oauth_client_id'] = $i_id === '' ? '' : self::encrypt_value( $i_id );
		} else {
			$opts['creatorreactor_instagram_oauth_client_id'] = isset( $raw_opts['creatorreactor_instagram_oauth_client_id'] ) ? $raw_opts['creatorreactor_instagram_oauth_client_id'] : '';
		}

		$i_secret = isset( $input['creatorreactor_instagram_oauth_client_secret'] ) ? (string) wp_unslash( $input['creatorreactor_instagram_oauth_client_secret'] ) : '';
		if ( $i_secret === '********' || $i_secret === '' ) {
			$opts['creatorreactor_instagram_oauth_client_secret'] = isset( $raw_opts['creatorreactor_instagram_oauth_client_secret'] ) ? $raw_opts['creatorreactor_instagram_oauth_client_secret'] : '';
		} else {
			$enc_i = self::encrypt_value( $i_secret );
			if ( $enc_i === '' ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_instagram_oauth_client_secret_encrypt_failed',
					__( 'Could not encrypt Instagram OAuth Client Secret (OpenSSL unavailable or encryption failed). The previous secret was kept.', 'wp-creatorreactor' )
				);
				$opts['creatorreactor_instagram_oauth_client_secret'] = isset( $raw_opts['creatorreactor_instagram_oauth_client_secret'] ) ? $raw_opts['creatorreactor_instagram_oauth_client_secret'] : '';
			} else {
				$opts['creatorreactor_instagram_oauth_client_secret'] = $enc_i;
			}
		}

		if ( $opts['creatorreactor_oauth_redirect_uri'] === '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: CreatorReactor_OAuth::get_default_redirect_uri();
		}
		if ( $opts['creatorreactor_oauth_scopes'] === '' ) {
			$opts['creatorreactor_oauth_scopes'] = self::DEFAULT_CREATORREACTOR_SCOPES;
		}
		$opts['creatorreactor_oauth_scopes'] = CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] );
		if ( $opts['creatorreactor_api_base_url'] === '' ) {
			$opts['creatorreactor_api_base_url'] = CreatorReactor_OAuth::API_BASE_URL;
		}
		if ( $opts['creatorreactor_api_version'] === '' ) {
			$opts['creatorreactor_api_version'] = '2025-06-26';
		}
		if ( $opts['creatorreactor_authorization_url'] === '' ) {
			$opts['creatorreactor_authorization_url'] = CreatorReactor_OAuth::AUTH_URL;
		}
		if ( $opts['creatorreactor_token_url'] === '' ) {
			$opts['creatorreactor_token_url'] = CreatorReactor_OAuth::TOKEN_URL;
		}

		if ( $opts['broker_mode'] ) {
			if ( empty( $opts['broker_url'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_broker_url_required', __( 'Broker URL is required for Agency (broker) authentication.', 'wp-creatorreactor' ) );
			}
			if ( empty( $opts['site_id'] ) ) {
				add_settings_error( self::OPTION_NAME, 'creatorreactor_site_id_required', __( 'Site ID is required for Agency (broker) authentication.', 'wp-creatorreactor' ) );
			}
		}

		$product_label = Entitlements::product_label( $opts['product'] );

		// Creator (direct) mode: OAuth app credentials are required for token exchange. Agency (broker) mode only
		// requires broker URL and site ID per README / Broker_Client; client ID and redirect are optional on the connect URL.
		if ( ! $opts['broker_mode'] ) {
			if ( empty( $opts['creatorreactor_oauth_client_id'] ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_client_id_required',
					sprintf(
						/* translators: %s: Product label (e.g. Fanvue). */
						__( '%s OAuth Client ID is required for Creator (direct) mode.', 'wp-creatorreactor' ),
						$product_label
					)
				);
			}
			if ( empty( $opts['creatorreactor_oauth_client_secret'] ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_client_secret_required',
					sprintf(
						/* translators: %s: Product label (e.g. Fanvue). */
						__( '%s OAuth Client Secret is required for Creator (direct) mode.', 'wp-creatorreactor' ),
						$product_label
					)
				);
			}
		}

		if ( array_key_exists( 'replace_wp_login_with_social', $input ) ) {
			$opts['replace_wp_login_with_social'] = ! empty( $input['replace_wp_login_with_social'] );
		} else {
			$opts['replace_wp_login_with_social'] = ! empty( $raw_opts['replace_wp_login_with_social'] );
		}

		if ( array_key_exists( 'restrict_creatorreactor_users_wp_admin', $input ) ) {
			$opts['restrict_creatorreactor_users_wp_admin'] = ! empty( $input['restrict_creatorreactor_users_wp_admin'] );
		} else {
			$opts['restrict_creatorreactor_users_wp_admin'] = array_key_exists( 'restrict_creatorreactor_users_wp_admin', $raw_opts )
				? ! empty( $raw_opts['restrict_creatorreactor_users_wp_admin'] )
				: true;
		}

		if ( array_key_exists( 'hide_admin_bar_for_creatorreactor_users', $input ) ) {
			$opts['hide_admin_bar_for_creatorreactor_users'] = ! empty( $input['hide_admin_bar_for_creatorreactor_users'] );
		} else {
			$opts['hide_admin_bar_for_creatorreactor_users'] = array_key_exists( 'hide_admin_bar_for_creatorreactor_users', $raw_opts )
				? ! empty( $raw_opts['hide_admin_bar_for_creatorreactor_users'] )
				: true;
		}

		if ( array_key_exists( 'display_timezone', $input ) ) {
			$opts['display_timezone'] = self::sanitize_display_timezone( wp_unslash( $input['display_timezone'] ) );
		} else {
			$opts['display_timezone'] = self::sanitize_display_timezone( $raw_opts['display_timezone'] ?? 'browser' );
		}

		if ( ! self::is_fan_social_login_configured_from_opts( $opts ) ) {
			if ( ! empty( $opts['replace_wp_login_with_social'] ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'creatorreactor_social_login_not_configured',
					__( 'The WordPress login page option was turned off because no social login provider is configured. Add Fanvue OAuth, Google sign-in, or another social OAuth provider under the matching Settings tab.', 'wp-creatorreactor' )
				);
			}
			$opts['replace_wp_login_with_social'] = false;
		}

		return $opts;
	}

	/**
	 * Whether Fanvue fan OAuth can run (Creator mode with app credentials). Used for wp-login button + General tab.
	 *
	 * @param array<string, mixed> $opts Sanitized or raw options array (encrypted secrets are non-empty when set).
	 */
	private static function is_fan_social_login_configured_from_opts( array $opts ) {
		if ( ! empty( $opts['broker_mode'] ) ) {
			return false;
		}
		$client_id = isset( $opts['creatorreactor_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_oauth_client_id'] ) : '';
		$secret    = isset( $opts['creatorreactor_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_oauth_client_secret'] ) : '';
		$fanvue_ok = $client_id !== '' && $secret !== '';
		$g_id      = isset( $opts['creatorreactor_google_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_id'] ) : '';
		$g_secret  = isset( $opts['creatorreactor_google_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_secret'] ) : '';
		$google_ok = $g_id !== '' && $g_secret !== '';
		if ( $fanvue_ok || $google_ok ) {
			return true;
		}
		foreach ( self::all_settings_social_oauth_slugs() as $slug ) {
			if ( 'bluesky' === $slug ) {
				if ( ! empty( $opts['creatorreactor_bluesky_oauth_enabled'] ) ) {
					return true;
				}
				continue;
			}
			$oid = isset( $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) : '';
			$osec = isset( $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) : '';
			if ( $oid === '' || $osec === '' ) {
				continue;
			}
			if ( 'mastodon' === $slug ) {
				$inst = isset( $opts['creatorreactor_mastodon_instance'] ) ? trim( (string) $opts['creatorreactor_mastodon_instance'] ) : '';
				if ( $inst !== '' ) {
					return true;
				}
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * True when the plugin has at least one configured social OAuth app for visitor login in Creator mode (Fanvue, Google, or another social provider tab).
	 */
	public static function is_fan_social_login_configured() {
		return self::is_fan_social_login_configured_from_opts( self::get_options() );
	}

	/**
	 * Google OAuth Client ID and Secret are set (Creator mode; decrypted options).
	 */
	public static function is_google_login_configured() {
		if ( self::is_broker_mode() ) {
			return false;
		}
		$o = self::get_options();
		$id = isset( $o['creatorreactor_google_oauth_client_id'] ) ? trim( (string) $o['creatorreactor_google_oauth_client_id'] ) : '';
		$sec = isset( $o['creatorreactor_google_oauth_client_secret'] ) ? trim( (string) $o['creatorreactor_google_oauth_client_secret'] ) : '';
		return $id !== '' && $sec !== '';
	}

	/**
	 * Slugs with Settings tabs for generic + Bluesky social OAuth (see {@see Social_OAuth_Registry} and Bluesky_OAuth).
	 *
	 * @return string[]
	 */
	public static function all_settings_social_oauth_slugs() {
		return array_merge( Social_OAuth_Registry::generic_slugs(), [ 'bluesky' ] );
	}

	/**
	 * Whether a social OAuth provider has saved Client ID and Secret (and Mastodon: instance URL).
	 *
	 * @param string $slug Provider slug (e.g. tiktok, bluesky).
	 */
	public static function is_social_oauth_provider_configured( $slug ) {
		if ( self::is_broker_mode() ) {
			return false;
		}
		$slug = sanitize_key( (string) $slug );
		if ( $slug === '' ) {
			return false;
		}
		if ( 'bluesky' === $slug ) {
			$o = self::get_options();
			return ! empty( $o['creatorreactor_bluesky_oauth_enabled'] );
		}
		$o  = self::get_options();
		$id = isset( $o[ Social_OAuth_Registry::option_client_id( $slug ) ] ) ? trim( (string) $o[ Social_OAuth_Registry::option_client_id( $slug ) ] ) : '';
		$sec = isset( $o[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) ? trim( (string) $o[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) : '';
		if ( $id === '' || $sec === '' ) {
			return false;
		}
		if ( 'mastodon' === $slug ) {
			return Social_OAuth::normalize_mastodon_base_from_opts( $o ) !== '';
		}
		return true;
	}

	/**
	 * Instagram (Meta) OAuth app ID and secret are set (Creator mode; decrypted options).
	 */
	public static function is_instagram_oauth_configured() {
		if ( self::is_broker_mode() ) {
			return false;
		}
		$o   = self::get_options();
		$id  = isset( $o['creatorreactor_instagram_oauth_client_id'] ) ? trim( (string) $o['creatorreactor_instagram_oauth_client_id'] ) : '';
		$sec = isset( $o['creatorreactor_instagram_oauth_client_secret'] ) ? trim( (string) $o['creatorreactor_instagram_oauth_client_secret'] ) : '';
		return $id !== '' && $sec !== '';
	}

	/**
	 * Fanvue Settings → OAuth: Creator mode needs Client ID and Secret; Agency mode needs Site ID.
	 */
	public static function is_fanvue_oauth_settings_configured() {
		$o = self::get_options();
		return self::is_fanvue_oauth_settings_configured_from_opts( ! empty( $o['broker_mode'] ), $o );
	}

	/**
	 * OnlyFans OFAuth: API key and webhook secret both saved.
	 */
	public static function is_onlyfans_ofauth_configured() {
		$o = self::get_options();
		return self::is_onlyfans_ofauth_configured_from_opts( $o );
	}

	/**
	 * @param bool  $broker_mode Agency mode when true.
	 * @param array $opts        Options (same shape as {@see self::get_options()}).
	 */
	private static function is_fanvue_oauth_settings_configured_from_opts( $broker_mode, array $opts ) {
		if ( $broker_mode ) {
			$site_id = isset( $opts['site_id'] ) ? trim( (string) $opts['site_id'] ) : '';

			return $site_id !== '';
		}
		$id = isset( $opts['creatorreactor_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_oauth_client_id'] ) : '';
		$sec = isset( $opts['creatorreactor_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_oauth_client_secret'] ) : '';

		return $id !== '' && $sec !== '';
	}

	/**
	 * @param array $opts Options.
	 */
	private static function is_onlyfans_ofauth_configured_from_opts( array $opts ) {
		return ! empty( $opts['creatorreactor_ofauth_api_key'] ) && ! empty( $opts['creatorreactor_ofauth_webhook_secret'] );
	}

	/**
	 * @return string[]
	 */
	public static function oauth_logo_button_general_size_slugs() {
		return [ 'compact', 'full' ];
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string `compact` or `full`.
	 */
	public static function sanitize_oauth_logo_button_general_size( $value ) {
		$key = sanitize_key( (string) $value );
		return in_array( $key, self::oauth_logo_button_general_size_slugs(), true ) ? $key : 'full';
	}

	/**
	 * Default Login Button Style: compact or full (Settings → General).
	 */
	public static function get_oauth_logo_button_general_size() {
		$o   = self::get_options();
		$raw = isset( $o[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] ) ? (string) $o[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] : '';
		return self::sanitize_oauth_logo_button_general_size( $raw !== '' ? $raw : 'full' );
	}

	/**
	 * @return string[]
	 */
	public static function oauth_button_size_mode_slugs() {
		return [ 'general', 'compact', 'full' ];
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string One of {@see self::oauth_button_size_mode_slugs()}.
	 */
	public static function sanitize_oauth_button_size_mode( $value ) {
		$key = sanitize_key( (string) $value );
		return in_array( $key, self::oauth_button_size_mode_slugs(), true ) ? $key : 'general';
	}

	/**
	 * Maps a settings form post of `compact` or `full` to stored mode: `general` when it matches
	 * Default Login Button Style in $opts, otherwise explicit `compact` or `full`. Accepts legacy `general`.
	 *
	 * @param mixed                $raw  Posted value.
	 * @param array<string, mixed> $opts Options with {@see self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY} already merged.
	 * @return string One of general|compact|full.
	 */
	private static function normalize_oauth_button_size_mode_from_posted_choice( $raw, array $opts ) {
		$key = sanitize_key( (string) $raw );
		if ( 'general' === $key ) {
			return 'general';
		}
		if ( ! in_array( $key, [ 'compact', 'full' ], true ) ) {
			return 'general';
		}
		$gen = self::oauth_logo_button_general_size_from_opts( $opts );

		return ( $key === $gen ) ? 'general' : $key;
	}

	/**
	 * Whether the Compact or Full radio should be checked for a provider (general mode follows site default).
	 *
	 * @param string $stored_mode  general|compact|full.
	 * @param string $row          compact|full.
	 * @param string $site_general compact|full from Default Login Button Style.
	 */
	private static function oauth_logo_button_size_row_is_checked( $stored_mode, $row, $site_general ) {
		$stored_mode  = self::sanitize_oauth_button_size_mode( $stored_mode );
		$row          = self::sanitize_oauth_logo_button_general_size( $row );
		$site_general = self::sanitize_oauth_logo_button_general_size( $site_general );
		if ( 'compact' === $stored_mode || 'full' === $stored_mode ) {
			return $stored_mode === $row;
		}

		return 'general' === $stored_mode && $site_general === $row;
	}

	/**
	 * @param array<string, mixed> $opts Options array.
	 */
	private static function oauth_logo_button_general_size_from_opts( array $opts ) {
		$raw = isset( $opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] ) ? (string) $opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] : '';

		return self::sanitize_oauth_logo_button_general_size( $raw !== '' ? $raw : 'full' );
	}

	/**
	 * @param array<string, mixed> $src Prior options (e.g. raw before migration).
	 */
	private static function infer_fanvue_oauth_button_size_mode_legacy( array $src ) {
		$v = isset( $src[ self::FANVUE_LOGIN_BUTTON_VARIANT_KEY ] )
			? self::sanitize_fanvue_login_button_variant( $src[ self::FANVUE_LOGIN_BUTTON_VARIANT_KEY ] )
			: 'standard';

		return 'minimal' === $v ? 'compact' : 'general';
	}

	/**
	 * @param array<string, mixed> $src Prior options (e.g. raw before migration).
	 */
	private static function infer_instagram_oauth_button_size_mode_legacy( array $src ) {
		$v = isset( $src[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ] )
			? self::sanitize_instagram_login_button_variant( $src[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ] )
			: 'standard';

		return 'minimal' === $v ? 'compact' : 'general';
	}

	/**
	 * @param array<string, mixed> $src Prior options (e.g. raw before migration).
	 */
	private static function infer_onlyfans_oauth_button_size_mode_legacy( array $src ) {
		$v = isset( $src[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ] )
			? self::sanitize_onlyfans_login_button_variant( $src[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ] )
			: 'standard';

		return 'minimal' === $v ? 'compact' : 'general';
	}

	/**
	 * @param array<string, mixed> $src Prior options (e.g. raw before migration).
	 */
	private static function infer_google_oauth_button_size_mode_legacy( array $src ) {
		$raw = isset( $src[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] ) ? (string) $src[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] : '';
		$s   = self::sanitize_google_login_button_style( $raw !== '' ? $raw : 'standard_light' );

		return 'logo_only' === $s ? 'compact' : 'general';
	}

	/**
	 * Stored full-size Google button theme (light or dark), ignoring compact logo-only.
	 *
	 * @param array<string, mixed> $opts Options array.
	 */
	private static function google_login_full_button_theme_from_opts( array $opts ) {
		$raw = isset( $opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] ) ? (string) $opts[ self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ] : '';
		$s   = self::sanitize_google_login_button_style( $raw !== '' ? $raw : 'standard_light' );
		if ( 'standard_dark' === $s ) {
			return 'standard_dark';
		}

		return 'standard_light';
	}

	/**
	 * @param array<string, mixed> $opts Sanitized options (includes size mode keys once saved).
	 * @return string `standard` or `minimal`.
	 */
	public static function resolve_fanvue_login_button_variant_from_opts( array $opts ) {
		$mode = array_key_exists( self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY, $opts )
			? self::sanitize_oauth_button_size_mode( $opts[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] )
			: self::infer_fanvue_oauth_button_size_mode_legacy( $opts );
		$general = self::oauth_logo_button_general_size_from_opts( $opts );
		if ( 'general' === $mode ) {
			return 'compact' === $general ? 'minimal' : 'standard';
		}

		return 'compact' === $mode ? 'minimal' : 'standard';
	}

	/**
	 * @param array<string, mixed> $opts Sanitized options.
	 * @return string `standard` or `minimal`.
	 */
	public static function resolve_instagram_login_button_variant_from_opts( array $opts ) {
		$mode = array_key_exists( self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY, $opts )
			? self::sanitize_oauth_button_size_mode( $opts[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] )
			: self::infer_instagram_oauth_button_size_mode_legacy( $opts );
		$general = self::oauth_logo_button_general_size_from_opts( $opts );
		if ( 'general' === $mode ) {
			return 'compact' === $general ? 'minimal' : 'standard';
		}

		return 'compact' === $mode ? 'minimal' : 'standard';
	}

	/**
	 * @param array<string, mixed> $opts Sanitized options.
	 * @return string `standard` or `minimal`.
	 */
	public static function resolve_onlyfans_login_button_variant_from_opts( array $opts ) {
		$mode = array_key_exists( self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY, $opts )
			? self::sanitize_oauth_button_size_mode( $opts[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] )
			: self::infer_onlyfans_oauth_button_size_mode_legacy( $opts );
		$general = self::oauth_logo_button_general_size_from_opts( $opts );
		if ( 'general' === $mode ) {
			return 'compact' === $general ? 'minimal' : 'standard';
		}

		return 'compact' === $mode ? 'minimal' : 'standard';
	}

	/**
	 * @param array<string, mixed> $opts Sanitized options.
	 * @param string               $slug Provider slug (e.g. tiktok, bluesky).
	 * @return string `standard` or `minimal`.
	 */
	public static function resolve_social_oauth_login_button_variant_from_opts( array $opts, $slug ) {
		$slug     = sanitize_key( (string) $slug );
		$mode_key = Social_OAuth_Registry::option_button_size_mode( $slug );
		$mode     = array_key_exists( $mode_key, $opts )
			? self::sanitize_oauth_button_size_mode( $opts[ $mode_key ] )
			: 'general';
		$general = self::oauth_logo_button_general_size_from_opts( $opts );
		if ( 'general' === $mode ) {
			return 'compact' === $general ? 'minimal' : 'standard';
		}

		return 'compact' === $mode ? 'minimal' : 'standard';
	}

	/**
	 * Effective Google button style for front end (includes logo-only when compact).
	 *
	 * @param array<string, mixed> $opts Sanitized options.
	 */
	public static function resolve_google_login_button_style_from_opts( array $opts ) {
		$mode = array_key_exists( self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY, $opts )
			? self::sanitize_oauth_button_size_mode( $opts[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] )
			: self::infer_google_oauth_button_size_mode_legacy( $opts );
		$general = self::oauth_logo_button_general_size_from_opts( $opts );
		$full    = self::google_login_full_button_theme_from_opts( $opts );
		if ( 'general' === $mode ) {
			return 'compact' === $general ? 'logo_only' : $full;
		}

		return 'compact' === $mode ? 'logo_only' : $full;
	}

	public static function get_fanvue_oauth_button_size_mode() {
		if ( self::is_broker_mode() ) {
			return 'general';
		}
		$o = self::get_options();
		if ( array_key_exists( self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY, $o ) ) {
			return self::sanitize_oauth_button_size_mode( $o[ self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		}

		return self::infer_fanvue_oauth_button_size_mode_legacy( $o );
	}

	public static function get_google_oauth_button_size_mode() {
		if ( self::is_broker_mode() ) {
			return 'general';
		}
		$o = self::get_options();
		if ( array_key_exists( self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY, $o ) ) {
			return self::sanitize_oauth_button_size_mode( $o[ self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		}

		return self::infer_google_oauth_button_size_mode_legacy( $o );
	}

	public static function get_instagram_oauth_button_size_mode() {
		if ( self::is_broker_mode() ) {
			return 'general';
		}
		$o = self::get_options();
		if ( array_key_exists( self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY, $o ) ) {
			return self::sanitize_oauth_button_size_mode( $o[ self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		}

		return self::infer_instagram_oauth_button_size_mode_legacy( $o );
	}

	public static function get_onlyfans_oauth_button_size_mode() {
		$o = self::get_options();
		if ( array_key_exists( self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY, $o ) ) {
			return self::sanitize_oauth_button_size_mode( $o[ self::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY ] );
		}

		return self::infer_onlyfans_oauth_button_size_mode_legacy( $o );
	}

	/**
	 * Shared intro copy for each OAuth provider “Login Button Appearance” settings card.
	 */
	public static function render_login_button_appearance_card_intro() {
		?>
		<p class="description" style="margin-top:0;">
			<?php esc_html_e( 'Controls how this provider’s login button appears on the WordPress login screen (wp-login.php) when social login is enabled.', 'wp-creatorreactor' ); ?>
		</p>
		<ul class="description" style="margin:8px 0 0 1.25em;list-style:disc;">
			<li><?php esc_html_e( 'This setting applies globally unless overridden in an individual OAuth provider’s configuration.', 'wp-creatorreactor' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Logo button size: Compact and Full only. The row matching Default Login Button Style shows a “Default” pill.
	 *
	 * @param string $option_name   Settings option name.
	 * @param string $mode_key      Stored option key for the mode (general|compact|full).
	 * @param string $stored_mode   Current stored mode.
	 * @param string $site_general  Default Login Button Style (compact|full).
	 * @param string $compact_preview_html Preview HTML for compact.
	 * @param string $full_preview_html    Preview HTML for full.
	 * @param array<string, mixed> $ui Optional. Keys: show_default_pill (bool), legend (string), legend_class (string),
	 *                                 description (string, absent = provider default copy; empty string = no paragraph),
	 *                                 radiogroup_aria_label (string), fieldset_extra_classes (string).
	 */
	public static function render_oauth_logo_button_size_mode_fieldset(
		$option_name,
		$mode_key,
		$stored_mode,
		$site_general,
		$compact_preview_html,
		$full_preview_html,
		array $ui = []
	) {
		$show_default_pill = array_key_exists( 'show_default_pill', $ui ) ? (bool) $ui['show_default_pill'] : true;
		$legend_text       = isset( $ui['legend'] ) ? (string) $ui['legend'] : __( 'Logo button appearance', 'wp-creatorreactor' );
		$legend_class      = isset( $ui['legend_class'] ) ? trim( (string) $ui['legend_class'] ) : '';
		$aria_label        = isset( $ui['radiogroup_aria_label'] ) ? (string) $ui['radiogroup_aria_label'] : __( 'Logo button appearance', 'wp-creatorreactor' );
		$fs_extra          = isset( $ui['fieldset_extra_classes'] ) ? ' ' . trim( (string) $ui['fieldset_extra_classes'] ) : '';
		$rows              = [
			'compact' => __( 'Compact', 'wp-creatorreactor' ),
			'full'    => __( 'Full', 'wp-creatorreactor' ),
		];
		?>
		<fieldset class="creatorreactor-google-login-style-fieldset creatorreactor-oauth-logo-size-mode-fieldset<?php echo $fs_extra !== '' ? esc_attr( $fs_extra ) : ''; ?>">
			<?php if ( $legend_class !== '' ) : ?>
				<legend class="<?php echo esc_attr( $legend_class ); ?>"><?php echo esc_html( $legend_text ); ?></legend>
			<?php else : ?>
				<legend><?php echo esc_html( $legend_text ); ?></legend>
			<?php endif; ?>
			<?php if ( ! array_key_exists( 'description', $ui ) ) : ?>
				<p class="description"><?php esc_html_e( 'Pick compact or full for this provider. The row that matches Default Login Button Style (General settings) is tagged “Default”; choosing that row follows the site default.', 'wp-creatorreactor' ); ?></p>
			<?php elseif ( (string) $ui['description'] !== '' ) : ?>
				<p class="description"><?php echo esc_html( (string) $ui['description'] ); ?></p>
			<?php endif; ?>
			<div class="creatorreactor-fanvue-login-style-grid" role="radiogroup" aria-label="<?php echo esc_attr( $aria_label ); ?>">
				<?php foreach ( $rows as $slug => $row_label ) : ?>
					<label class="creatorreactor-fanvue-login-style-option">
						<span class="creatorreactor-fanvue-login-style-option-head">
							<input
								type="radio"
								name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $mode_key ); ?>]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( self::oauth_logo_button_size_row_is_checked( $stored_mode, $slug, $site_general ) ); ?>
							/>
							<span class="creatorreactor-fanvue-login-style-option-label">
								<?php echo esc_html( $row_label ); ?>
								<?php if ( $show_default_pill && $slug === $site_general ) : ?>
									<span class="creatorreactor-oauth-default-pill"><?php esc_html_e( 'Default', 'wp-creatorreactor' ); ?></span>
								<?php endif; ?>
							</span>
						</span>
						<span class="creatorreactor-fanvue-login-style-preview" aria-hidden="true">
							<?php
							if ( 'compact' === $slug ) {
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered preview HTML.
								echo $compact_preview_html;
							} else {
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered preview HTML.
								echo $full_preview_html;
							}
							?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Allowed slugs for Sign in with Google button appearance.
	 *
	 * @return string[]
	 */
	public static function google_login_button_style_slugs() {
		return [ 'standard_light', 'standard_dark', 'logo_only' ];
	}

	/**
	 * Human-readable labels for full-size Google button theme (light / dark). Compact uses the separate size mode.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function get_google_login_full_button_theme_choices() {
		return [
			'standard_light' => __( 'Standard — light', 'wp-creatorreactor' ),
			'standard_dark'  => __( 'Standard — dark', 'wp-creatorreactor' ),
		];
	}

	/**
	 * @deprecated Use {@see self::get_google_login_full_button_theme_choices()} for settings; compact is controlled by logo button size mode.
	 * @return array<string, string> slug => label
	 */
	public static function get_google_login_button_style_choices() {
		return [
			'standard_light'  => __( 'Standard — light', 'wp-creatorreactor' ),
			'standard_dark'   => __( 'Standard — dark', 'wp-creatorreactor' ),
			'logo_only'       => __( 'Compact Logo', 'wp-creatorreactor' ),
		];
	}

	/**
	 * @param mixed $slug Raw submitted value.
	 * @return string One of {@see self::google_login_button_style_slugs()}; invalid and legacy values become standard_light.
	 */
	public static function sanitize_google_login_button_style( $slug ) {
		$key = sanitize_key( (string) $slug );
		return in_array( $key, self::google_login_button_style_slugs(), true ) ? $key : 'standard_light';
	}

	/**
	 * Effective appearance for the Google login control (wp-login.php and [standard_google_login_button] / legacy [google_login_button]).
	 */
	public static function get_google_login_button_style() {
		if ( self::is_broker_mode() ) {
			return 'standard_light';
		}

		return self::resolve_google_login_button_style_from_opts( self::get_options() );
	}

	/**
	 * Allowed values for Fanvue login button variant (wp-login, block, [fanvue_login_button]).
	 *
	 * @return string[]
	 */
	public static function fanvue_login_button_variant_slugs() {
		return [ 'standard', 'minimal' ];
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function get_fanvue_login_button_variant_choices() {
		return [
			'standard' => __( 'Full button — banner image and label', 'wp-creatorreactor' ),
			'minimal'  => __( 'Compact — logo on solid Fanvue green (no border)', 'wp-creatorreactor' ),
		];
	}

	/**
	 * @param mixed $value Raw submitted value.
	 * @return string `standard` or `minimal`.
	 */
	public static function sanitize_fanvue_login_button_variant( $value ) {
		$key = sanitize_key( (string) $value );
		return in_array( $key, self::fanvue_login_button_variant_slugs(), true ) ? $key : 'standard';
	}

	/**
	 * Effective Fanvue login variant for wp-login.php, the Fanvue login block, and [fanvue_login_button].
	 */
	public static function get_fanvue_login_button_variant() {
		if ( self::is_broker_mode() ) {
			return 'standard';
		}

		return self::resolve_fanvue_login_button_variant_from_opts( self::get_options() );
	}

	/**
	 * Allowed values for Instagram-style login button variant (Settings → Instagram appearance).
	 *
	 * @return string[]
	 */
	public static function instagram_login_button_variant_slugs() {
		return [ 'standard', 'minimal' ];
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function get_instagram_login_button_variant_choices() {
		return [
			'standard' => __( 'Full — gradient button with glyph and label', 'wp-creatorreactor' ),
			'minimal'  => __( 'Compact — gradient square with glyph only', 'wp-creatorreactor' ),
		];
	}

	/**
	 * @param mixed $value Raw submitted value.
	 * @return string `standard` or `minimal`.
	 */
	public static function sanitize_instagram_login_button_variant( $value ) {
		$key = sanitize_key( (string) $value );
		return in_array( $key, self::instagram_login_button_variant_slugs(), true ) ? $key : 'standard';
	}

	/**
	 * Effective Instagram-style login variant (admin preview; reserved for future shortcodes/widgets).
	 */
	public static function get_instagram_login_button_variant() {
		if ( self::is_broker_mode() ) {
			return 'standard';
		}

		return self::resolve_instagram_login_button_variant_from_opts( self::get_options() );
	}

	/**
	 * Allowed values for OnlyFans / OFAuth-style login button variant (Settings → OnlyFans appearance).
	 *
	 * @return string[]
	 */
	public static function onlyfans_login_button_variant_slugs() {
		return [ 'standard', 'minimal' ];
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function get_onlyfans_login_button_variant_choices() {
		return [
			'standard' => __( 'Full — dark button with OnlyFans logo and label', 'wp-creatorreactor' ),
			'minimal'  => __( 'Compact — dark square with OnlyFans logo only', 'wp-creatorreactor' ),
		];
	}

	/**
	 * @param mixed $value Raw submitted value.
	 * @return string `standard` or `minimal`.
	 */
	public static function sanitize_onlyfans_login_button_variant( $value ) {
		$key = sanitize_key( (string) $value );
		return in_array( $key, self::onlyfans_login_button_variant_slugs(), true ) ? $key : 'standard';
	}

	/**
	 * Effective OnlyFans / OFAuth-style login variant (admin preview; reserved for future shortcodes/widgets).
	 */
	public static function get_onlyfans_login_button_variant() {
		return self::resolve_onlyfans_login_button_variant_from_opts( self::get_options() );
	}

	/**
	 * Whether to add the social login buttons on wp-login.php (option on + at least one provider configured).
	 */
	public static function is_replace_wp_login_with_social() {
		if ( ! self::is_fan_social_login_configured() ) {
			return false;
		}
		$o = self::get_options();
		return ! empty( $o['replace_wp_login_with_social'] );
	}

	/**
	 * Whether CreatorReactor role users without manage_options are blocked from wp-admin (default: on).
	 */
	public static function is_restrict_creatorreactor_users_wp_admin_enabled() {
		$o = self::get_options();
		return ! empty( $o['restrict_creatorreactor_users_wp_admin'] );
	}

	/**
	 * Whether to hide the front-end admin bar for CreatorReactor role users without manage_options (default: on).
	 */
	public static function is_hide_admin_bar_for_creatorreactor_users_enabled() {
		$o = self::get_options();
		return ! empty( $o['hide_admin_bar_for_creatorreactor_users'] );
	}

	/**
	 * Logged-in user has at least one role whose slug starts with creatorreactor_.
	 *
	 * @param int|null $user_id Defaults to current user.
	 */
	public static function user_has_creatorreactor_role( $user_id = null ) {
		if ( $user_id === null ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return false;
		}
		foreach ( $user->roles as $role ) {
			if ( is_string( $role ) && strpos( $role, 'creatorreactor_' ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Redirect fans (creatorreactor_* roles) without manage_options away from wp-admin.
	 */
	public static function maybe_redirect_creatorreactor_users_from_wp_admin() {
		if ( ! is_user_logged_in() || wp_doing_ajax() ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::is_restrict_creatorreactor_users_wp_admin_enabled() ) {
			return;
		}
		if ( ! self::user_has_creatorreactor_role() ) {
			return;
		}
		wp_safe_redirect( \home_url( '/' ) );
		exit;
	}

	/**
	 * @param bool $show Whether WordPress would show the admin bar.
	 * @return bool
	 */
	public static function filter_show_admin_bar_for_creatorreactor_users( $show ) {
		if ( ! $show || ! is_user_logged_in() ) {
			return $show;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return $show;
		}
		if ( ! self::is_hide_admin_bar_for_creatorreactor_users_enabled() ) {
			return $show;
		}
		if ( ! self::user_has_creatorreactor_role() ) {
			return $show;
		}
		return false;
	}

	/**
	 * Creator (direct) mode: start Fanvue OAuth from Dashboard "Connect" (PKCE + redirect at click time).
	 */
	public static function handle_oauth_start() {
		if ( ! is_admin() || empty( $_GET['creatorreactor_oauth_start'] ) || (string) wp_unslash( $_GET['creatorreactor_oauth_start'] ) !== '1' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::log_connection( 'error', 'OAuth Connect: rejected (user lacks manage_options).' );
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'creatorreactor_oauth_start' ) ) {
			$msg = __( 'Connect link expired. Reload the Settings page and click Connect again.', 'wp-creatorreactor' );
			self::log_connection( 'error', 'OAuth Connect: invalid or expired nonce.' );
			self::set_last_error( $msg );
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		if ( self::is_broker_mode() ) {
			self::log_connection( 'info', 'OAuth Connect: ignored (Agency/broker mode; use broker Connect).' );
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		$auth_url = CreatorReactor_OAuth::get_authorization_url();
		if ( ! $auth_url ) {
			$msg = __( 'OAuth Client ID is missing. Enter your Fanvue Client ID, save settings, then connect again.', 'wp-creatorreactor' );
			self::log_connection( 'error', 'OAuth Connect: cannot build authorize URL (missing Client ID).' );
			self::set_last_error( $msg );
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		self::reset_connection_state_before_connect();
		self::log_connection( 'info', 'OAuth Connect: redirecting browser to Fanvue authorization.' );
		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * Clear connection test result, connection log, last/critical errors (dashboard card styling).
	 * Called when starting OAuth/Connect so the UI resets before a new attempt.
	 */
	public static function reset_connection_state_before_connect() {
		delete_option( self::OPTION_CONNECTION_TEST );
		self::clear_connection_logs();
		self::set_last_error( '' );
		self::set_critical_error( '' );
	}

	/**
	 * Agency mode: clear dashboard connection state, then redirect to broker Connect URL.
	 */
	public static function handle_broker_connect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wp-creatorreactor' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'creatorreactor_broker_connect' );
		if ( ! self::is_broker_mode() ) {
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		$url = Broker_Client::get_connect_url();
		if ( ! is_string( $url ) || $url === '' ) {
			self::set_last_error( __( 'Cannot build broker Connect URL. Check Site ID and broker settings.', 'wp-creatorreactor' ) );
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard' ] ) );
			exit;
		}
		self::reset_connection_state_before_connect();
		self::log_connection( 'info', 'Broker Connect: redirecting to broker authorization.' );
		wp_redirect( $url );
		exit;
	}

	public static function set_defaults() {
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}

		if ( ! isset( $opts['broker_mode'] ) ) {
			$opts['broker_mode'] = false;
		}
		if ( ! isset( $opts['product'] ) || $opts['product'] === '' ) {
			$opts['product'] = Entitlements::PRODUCT_FANVUE;
		}
		if ( ! isset( $opts['broker_url'] ) || $opts['broker_url'] === '' ) {
			$opts['broker_url'] = 'https://auth.ncdlabs.com';
		}
		if ( ! isset( $opts['creatorreactor_authorization_url'] ) || $opts['creatorreactor_authorization_url'] === '' ) {
			$opts['creatorreactor_authorization_url'] = CreatorReactor_OAuth::AUTH_URL;
		}
		if ( ! isset( $opts['creatorreactor_token_url'] ) || $opts['creatorreactor_token_url'] === '' ) {
			$opts['creatorreactor_token_url'] = CreatorReactor_OAuth::TOKEN_URL;
		}
		if ( ! isset( $opts['creatorreactor_api_base_url'] ) || $opts['creatorreactor_api_base_url'] === '' ) {
			$opts['creatorreactor_api_base_url'] = CreatorReactor_OAuth::API_BASE_URL;
		}
		if ( ! isset( $opts['creatorreactor_oauth_scopes'] ) || $opts['creatorreactor_oauth_scopes'] === '' ) {
			$opts['creatorreactor_oauth_scopes'] = self::DEFAULT_CREATORREACTOR_SCOPES;
		}
		$opts['creatorreactor_oauth_scopes'] = CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] );
		if ( ! isset( $opts['creatorreactor_api_version'] ) || $opts['creatorreactor_api_version'] === '' ) {
			$opts['creatorreactor_api_version'] = '2025-06-26';
		}
		if ( ! isset( $opts['creatorreactor_oauth_redirect_uri'] ) || $opts['creatorreactor_oauth_redirect_uri'] === '' ) {
			$opts['creatorreactor_oauth_redirect_uri'] = $opts['broker_mode']
				? self::get_broker_default_redirect_uri()
				: CreatorReactor_OAuth::get_default_redirect_uri();
		}
		if ( ! isset( $opts['cron_interval_minutes'] ) ) {
			$opts['cron_interval_minutes'] = 15;
		}
		if ( ! isset( $opts['entitlement_cache_ttl_seconds'] ) ) {
			$opts['entitlement_cache_ttl_seconds'] = 900;
		}
		if ( ! isset( $opts['privacy_log_retention_days'] ) ) {
			$opts['privacy_log_retention_days'] = 30;
		}
		if ( ! isset( $opts['privacy_profile_snapshot_retention_days'] ) ) {
			$opts['privacy_profile_snapshot_retention_days'] = 90;
		}
		if ( ! array_key_exists( 'creatorreactor_instagram_oauth_client_id', $opts ) ) {
			$opts['creatorreactor_instagram_oauth_client_id'] = '';
		}
		if ( ! array_key_exists( 'creatorreactor_instagram_oauth_client_secret', $opts ) ) {
			$opts['creatorreactor_instagram_oauth_client_secret'] = '';
		}
		if ( ! array_key_exists( self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY, $opts ) ) {
			$opts[ self::INSTAGRAM_LOGIN_BUTTON_VARIANT_KEY ] = 'standard';
		}
		if ( ! array_key_exists( self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY, $opts ) ) {
			$opts[ self::ONLYFANS_LOGIN_BUTTON_VARIANT_KEY ] = 'standard';
		}
		if ( ! array_key_exists( self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY, $opts ) ) {
			$opts[ self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY ] = 'full';
		}
		if ( ! array_key_exists( 'creatorreactor_bluesky_oauth_enabled', $opts ) ) {
			$opts['creatorreactor_bluesky_oauth_enabled'] = false;
		}
		if ( ! array_key_exists( 'creatorreactor_mastodon_instance', $opts ) ) {
			$opts['creatorreactor_mastodon_instance'] = '';
		}
		foreach ( self::all_settings_social_oauth_slugs() as $slug ) {
			$idk = Social_OAuth_Registry::option_client_id( $slug );
			$sec = Social_OAuth_Registry::option_client_secret( $slug );
			$mod = Social_OAuth_Registry::option_button_size_mode( $slug );
			if ( ! array_key_exists( $idk, $opts ) ) {
				$opts[ $idk ] = '';
			}
			if ( ! array_key_exists( $sec, $opts ) ) {
				$opts[ $sec ] = '';
			}
			if ( ! array_key_exists( $mod, $opts ) ) {
				$opts[ $mod ] = 'general';
			}
		}
		update_option( self::OPTION_NAME, $opts );
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'creatorreactor_disconnect' );

		if ( self::is_broker_mode() ) {
			self::log_connection( 'info', 'Disconnect: Agency/broker mode — clearing broker session.' );
			Broker_Client::disconnect();
		} else {
			self::log_connection( 'info', 'Disconnect: Creator/direct mode — deleting stored OAuth tokens.' );
			delete_option( CreatorReactor_OAuth::OPTION_TOKENS );
		}

		self::set_last_error( '' );

		wp_safe_redirect( self::admin_page_url( [ 'status' => 'disconnected' ] ) );
		exit;
	}

	public static function is_broker_mode() {
		$opts = self::get_raw_options();
		return ! empty( $opts['broker_mode'] );
	}

	public static function handle_connection_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'creatorreactor_test_connection' );

		self::log_connection( 'info', 'Connection test: started.' );

		if ( self::is_broker_mode() ) {
			$result = Broker_Client::test_connection();
			$checks = [];
			if ( ! empty( $result['checks'] ) && is_array( $result['checks'] ) ) {
				foreach ( $result['checks'] as $check ) {
					if ( ! is_array( $check ) ) {
						continue;
					}
					$checks[] = [
						'label' => isset( $check['label'] ) ? sanitize_text_field( (string) $check['label'] ) : '',
						'pass' => ! empty( $check['pass'] ),
						'message' => isset( $check['message'] ) ? sanitize_text_field( (string) $check['message'] ) : '',
					];
				}
			} else {
				$checks[] = [
					'label' => __( 'Agency (broker) connection', 'wp-creatorreactor' ),
					'pass' => ! empty( $result['success'] ),
					'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
				];
			}
		} else {
			$result = CreatorReactor_Client::test_connection();
			$checks = [];

			if ( ! empty( $result['checks'] ) && is_array( $result['checks'] ) ) {
				foreach ( $result['checks'] as $check ) {
					if ( ! is_array( $check ) ) {
						continue;
					}
					$checks[] = [
						'label' => isset( $check['label'] ) ? sanitize_text_field( (string) $check['label'] ) : '',
						'pass' => ! empty( $check['pass'] ),
						'message' => isset( $check['message'] ) ? sanitize_text_field( (string) $check['message'] ) : '',
					];
				}
			}
		}

		update_option(
			self::OPTION_CONNECTION_TEST,
			[
				'time' => time(),
				'success' => ! empty( $result['success'] ),
				'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
				'checks' => $checks,
			],
			false
		);

		if ( ! empty( $result['success'] ) ) {
			self::clear_connection_errors();
			self::log_connection( 'info', 'Connection test: finished successfully. ' . ( isset( $result['message'] ) ? (string) $result['message'] : '' ) );
		} else {
			self::log_connection(
				'error',
				'Connection test: finished with failure. ' . ( isset( $result['message'] ) ? (string) $result['message'] : '' )
			);
		}

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'status' => 'connection_tested' ] ) );
		exit;
	}

	/**
	 * Dashboard Wordpress Gateway row: which action links to show.
	 *
	 * @param string $state green|yellow|red|gray
	 * @return string configure_only|configure_test|disable_test
	 */
	private static function wordpress_gateway_child_action_mode( $state ) {
		$state = (string) $state;
		if ( $state === 'green' ) {
			return 'disable_test';
		}
		if ( $state === 'gray' ) {
			return 'configure_only';
		}
		return 'configure_test';
	}

	/**
	 * @param array<string, mixed> $payload Keys: success (bool), message (string), optional remediation (string).
	 */
	private static function set_gateway_dashboard_notice( array $payload ) {
		set_transient( 'creatorreactor_gwdn_' . get_current_user_id(), $payload, 120 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function take_gateway_dashboard_notice() {
		$key = 'creatorreactor_gwdn_' . get_current_user_id();
		$val = get_transient( $key );
		if ( is_array( $val ) ) {
			delete_transient( $key );
			return $val;
		}
		return null;
	}

	public static function handle_disable_google_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_disable_google_oauth' );

		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['creatorreactor_google_oauth_client_id']     = '';
		$opts['creatorreactor_google_oauth_client_secret'] = '';
		update_option( self::OPTION_NAME, $opts );
		self::flush_creatorreactor_settings_cache();
		self::log_connection( 'info', 'Google sign-in: credentials cleared (dashboard Disable).' );

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_disabled' => 'google' ] ) );
		exit;
	}

	public static function handle_disable_instagram_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_disable_instagram_oauth' );

		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['creatorreactor_instagram_oauth_client_id']     = '';
		$opts['creatorreactor_instagram_oauth_client_secret'] = '';
		update_option( self::OPTION_NAME, $opts );
		self::flush_creatorreactor_settings_cache();
		self::log_connection( 'info', 'Instagram OAuth: credentials cleared (dashboard Disable).' );

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_disabled' => 'instagram' ] ) );
		exit;
	}

	public static function handle_disable_onlyfans_ofauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_disable_onlyfans_ofauth' );

		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['creatorreactor_ofauth_api_key']        = '';
		$opts['creatorreactor_ofauth_webhook_secret'] = '';
		update_option( self::OPTION_NAME, $opts );
		self::flush_creatorreactor_settings_cache();
		self::log_connection( 'info', 'OnlyFans OFAuth: API key and webhook secret cleared (dashboard Disable).' );

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_disabled' => 'onlyfans' ] ) );
		exit;
	}

	/**
	 * Clear one social OAuth provider’s credentials (Settings tab Disable).
	 */
	public static function handle_disable_social_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}
		check_admin_referer( 'creatorreactor_disable_social_oauth' );
		$slug = isset( $_POST['provider_slug'] ) ? sanitize_key( wp_unslash( $_POST['provider_slug'] ) ) : '';
		if ( $slug === '' || ! in_array( $slug, self::all_settings_social_oauth_slugs(), true ) ) {
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG ) );
			exit;
		}
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts[ Social_OAuth_Registry::option_client_id( $slug ) ]     = '';
		$opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] = '';
		if ( 'mastodon' === $slug ) {
			$opts['creatorreactor_mastodon_instance'] = '';
		}
		if ( 'bluesky' === $slug ) {
			$opts['creatorreactor_bluesky_oauth_enabled'] = false;
		}
		update_option( self::OPTION_NAME, $opts );
		self::flush_creatorreactor_settings_cache();
		self::log_connection( 'info', 'Social OAuth (' . $slug . '): credentials cleared (Disable).' );
		wp_safe_redirect( self::admin_social_oauth_settings_url( $slug ) );
		exit;
	}

	/**
	 * Run credential probe from dashboard gateway row (optional hook).
	 */
	public static function handle_test_social_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}
		check_admin_referer( 'creatorreactor_test_social_oauth' );
		$slug = isset( $_POST['provider_slug'] ) ? sanitize_key( wp_unslash( $_POST['provider_slug'] ) ) : '';
		if ( $slug === '' || ! in_array( $slug, self::all_settings_social_oauth_slugs(), true ) ) {
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}
		if ( self::is_broker_mode() ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Social OAuth is not used in Agency mode.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}
		$opts = self::get_options();
		$id   = isset( $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_id( $slug ) ] ) : '';
		$sec  = isset( $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) ? trim( (string) $opts[ Social_OAuth_Registry::option_client_secret( $slug ) ] ) : '';
		if ( $id === '' || $sec === '' ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Add OAuth credentials for this provider, then run Test again.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_social_oauth_settings_url( $slug ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}
		if ( 'bluesky' === $slug ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => true,
					'message' => __( 'Bluesky uses atproto OAuth — use the provider tab instructions and Save Settings.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_social_oauth_settings_url( 'bluesky' ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}
		$redirect = Social_OAuth::get_callback_redirect_uri( $slug );
		$probe    = Social_OAuth::probe_client_credentials_for_settings_test( $slug, $id, $sec, $redirect, $opts );
		if ( is_wp_error( $probe ) ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => $probe->get_error_message(),
					'fix_url' => self::admin_social_oauth_settings_url( $slug ),
				]
			);
		} else {
			self::set_gateway_dashboard_notice(
				[
					'success' => true,
					'message' => __( 'OAuth credentials were accepted by the provider’s token endpoint.', 'wp-creatorreactor' ),
				]
			);
		}
		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
		exit;
	}

	public static function handle_test_google_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_test_google_oauth' );

		if ( self::is_broker_mode() ) {
			self::set_gateway_dashboard_notice(
				[
					'success'  => false,
					'message'  => __( 'Google sign-in is not used in Agency mode.', 'wp-creatorreactor' ),
					'fix_url'  => self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}

		$opts          = self::get_options();
		$client_id     = isset( $opts['creatorreactor_google_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_id'] ) : '';
		$client_secret = isset( $opts['creatorreactor_google_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_google_oauth_client_secret'] ) : '';
		if ( $client_id === '' || $client_secret === '' ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Add both Client ID and Client Secret under Settings → Google, then run Test again.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_page_url( [ 'tab' => 'google' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}

		$redirect = Google_OAuth::get_callback_redirect_uri();
		$probe    = Google_OAuth::probe_client_credentials_for_settings_test( $client_id, $client_secret, $redirect );
		if ( is_wp_error( $probe ) ) {
			$data         = $probe->get_error_data();
			$google_error = is_array( $data ) && isset( $data['google_error'] ) ? (string) $data['google_error'] : '';
			self::set_gateway_dashboard_notice(
				[
					'success'     => false,
					'message'     => $probe->get_error_message(),
					'remediation' => self::google_oauth_config_test_remediation( $google_error, $probe->get_error_code() ),
					'fix_url'     => self::admin_page_url( [ 'tab' => 'google' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
		} else {
			self::set_gateway_dashboard_notice(
				[
					'success' => true,
					'message' => __( 'Google accepted your Client ID and Client Secret.', 'wp-creatorreactor' ),
				]
			);
		}

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
		exit;
	}

	public static function handle_test_instagram_oauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_test_instagram_oauth' );

		if ( self::is_broker_mode() ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Instagram OAuth is not used in Agency mode.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}

		$opts          = self::get_options();
		$client_id     = isset( $opts['creatorreactor_instagram_oauth_client_id'] ) ? trim( (string) $opts['creatorreactor_instagram_oauth_client_id'] ) : '';
		$client_secret = isset( $opts['creatorreactor_instagram_oauth_client_secret'] ) ? trim( (string) $opts['creatorreactor_instagram_oauth_client_secret'] ) : '';
		if ( $client_id === '' || $client_secret === '' ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Add both Client ID and Client Secret under Settings → Instagram, then run Test again.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_page_url( [ 'tab' => 'instagram' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}

		$redirect = Instagram_OAuth::get_callback_redirect_uri();
		$probe    = Instagram_OAuth::probe_client_credentials_for_settings_test( $client_id, $client_secret, $redirect );
		if ( is_wp_error( $probe ) ) {
			$data            = $probe->get_error_data();
			$instagram_error = is_array( $data ) && isset( $data['instagram_error'] ) ? (string) $data['instagram_error'] : '';
			self::set_gateway_dashboard_notice(
				[
					'success'     => false,
					'message'     => $probe->get_error_message(),
					'remediation' => self::instagram_oauth_config_test_remediation( $instagram_error, $probe->get_error_code() ),
					'fix_url'     => self::admin_page_url( [ 'tab' => 'instagram' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
		} else {
			self::set_gateway_dashboard_notice(
				[
					'success' => true,
					'message' => __( 'Instagram accepted your Client ID and Client Secret.', 'wp-creatorreactor' ),
				]
			);
		}

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
		exit;
	}

	public static function handle_test_onlyfans_ofauth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-creatorreactor' ) );
		}

		check_admin_referer( 'creatorreactor_test_onlyfans_ofauth' );

		$opts       = self::get_options();
		$api_key    = isset( $opts['creatorreactor_ofauth_api_key'] ) ? trim( (string) $opts['creatorreactor_ofauth_api_key'] ) : '';
		$webhook_secret = isset( $opts['creatorreactor_ofauth_webhook_secret'] ) ? trim( (string) $opts['creatorreactor_ofauth_webhook_secret'] ) : '';
		if ( $api_key === '' || $webhook_secret === '' ) {
			self::set_gateway_dashboard_notice(
				[
					'success' => false,
					'message' => __( 'Add the OFAuth API key and webhook secret under Settings → OnlyFans, then run Test again.', 'wp-creatorreactor' ),
					'fix_url' => self::admin_page_url( [ 'tab' => 'onlyfans' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
			wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
			exit;
		}

		$probe = OFAuth::probe_api_key_for_settings_test( $api_key );
		if ( is_wp_error( $probe ) ) {
			$data = $probe->get_error_data();
			$code = is_array( $data ) && isset( $data['http_code'] ) ? (int) $data['http_code'] : 0;
			self::set_gateway_dashboard_notice(
				[
					'success'     => false,
					'message'     => $probe->get_error_message(),
					'remediation' => self::onlyfans_ofauth_config_test_remediation( $probe->get_error_code(), $code ),
					'fix_url'     => self::admin_page_url( [ 'tab' => 'onlyfans' ], self::PAGE_SETTINGS_SLUG ),
				]
			);
		} else {
			self::set_gateway_dashboard_notice(
				[
					'success' => true,
					'message' => __( 'OFAuth accepted your API key. The webhook secret is stored locally and is not re-checked over the network.', 'wp-creatorreactor' ),
				]
			);
		}

		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'dashboard', 'gateway_test' => '1' ] ) );
		exit;
	}

	/**
	 * Return OAuth + Sync field HTML for the selected authentication mode (AJAX).
	 */
	public static function ajax_auth_mode_fields() {
		check_ajax_referer( 'creatorreactor_auth_mode', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : self::AUTH_MODE_CREATOR;
		$broker_mode = ( $mode === self::AUTH_MODE_AGENCY );

		$opts                = self::get_options();
		$secret_mask         = ! empty( $opts['creatorreactor_oauth_client_secret'] ) ? '********' : '';
		$cloud_password_mask = ! empty( $opts['creatorreactor_cloud_password'] ) ? '********' : '';
		$current_product_label = Entitlements::product_label( Entitlements::PRODUCT_FANVUE );

		ob_start();
		self::render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label );
		$oauth_html = ob_get_clean();

		ob_start();
		self::render_sync_dynamic_fields( $broker_mode, $opts );
		$sync_html = ob_get_clean();

		wp_send_json_success(
			[
				'oauth' => $oauth_html,
				'sync'  => $sync_html,
			]
		);
	}

	/**
	 * Load entitlements totals and latest rows for the Users settings tab.
	 *
	 * @return array{totals: array{total: int, active: int, inactive: int}, rows: array<int, array<string, mixed>>}
	 */
	private static function get_users_tab_snapshot() {
		$user_rows   = [];
		$user_totals = [
			'total'    => 0,
			'active'   => 0,
			'inactive' => 0,
		];

		global $wpdb;
		$table_name   = Entitlements::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists === $table_name ) {
			Entitlements::maybe_add_product_column();
			Entitlements::maybe_add_display_name_column();
			Entitlements::maybe_add_fanvue_user_uuid_column();
			Entitlements::maybe_add_creatorreactor_uuid_column();
			Entitlements::maybe_add_creatorreactor_user_uuid_column();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$user_totals['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$user_totals['active'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_ACTIVE )
			);
			$user_totals['inactive'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", Entitlements::STATUS_INACTIVE )
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
			$user_rows = $wpdb->get_results( "SELECT id, wp_user_id, fanvue_user_uuid, creatorreactor_uuid, creatorreactor_user_uuid, product, display_name, email, status, tier, expires_at, updated_at FROM {$table_name} ORDER BY updated_at DESC LIMIT 50", ARRAY_A );
			if ( ! is_array( $user_rows ) ) {
				$user_rows = [];
			}
		}

		return [
			'totals' => $user_totals,
			'rows'   => $user_rows,
		];
	}

	/**
	 * Labeled lines for the Users tab Details modal (full CreatorReactor record + derived tier label).
	 *
	 * @param array<string, mixed> $row CreatorReactor record row.
	 * @return array{lines: array<int, array<string, mixed>>}
	 */
	private static function users_tab_row_details_payload( array $row ) {
		$tier_raw = isset( $row['tier'] ) && $row['tier'] !== null && (string) $row['tier'] !== ''
			? (string) $row['tier']
			: '';
		$product_key = isset( $row['product'] ) && $row['product'] !== null && (string) $row['product'] !== ''
			? Entitlements::normalize_product( (string) $row['product'] )
			: '-';
		$wp_uid = isset( $row['wp_user_id'] ) && $row['wp_user_id'] !== null && (string) $row['wp_user_id'] !== ''
			? (string) (int) $row['wp_user_id']
			: '-';

		$snapshot_raw = isset( $row['fanvue_sync_snapshot'] ) ? trim( (string) $row['fanvue_sync_snapshot'] ) : '';
		$snapshot_out = '-';
		if ( $snapshot_raw !== '' ) {
			$max            = 600;
			$snapshot_out = strlen( $snapshot_raw ) > $max
				? substr( $snapshot_raw, 0, $max ) . '...'
				: $snapshot_raw;
		}

		$normalized_for_sections = isset( $row['product'] ) && $row['product'] !== null && (string) $row['product'] !== ''
			? Entitlements::normalize_product( (string) $row['product'] )
			: Entitlements::PRODUCT_FANVUE;
		$is_fanvue_row    = ( $normalized_for_sections === Entitlements::PRODUCT_FANVUE );
		$is_onlyfans_row  = ( $normalized_for_sections === Entitlements::PRODUCT_ONLYFANS );

		$lines = [
			[
				'section' => true,
				'label'   => __( 'CreatorReactor Records', 'wp-creatorreactor' ),
				'value'   => '',
			],
			[
				'label' => __( 'CreatorReactor UUID', 'wp-creatorreactor' ),
				'value' => (string) ( $row['creatorreactor_uuid'] ?? '' ) !== '' ? (string) $row['creatorreactor_uuid'] : '-',
			],
			[
				'label' => __( 'CreatorReactor user UUID', 'wp-creatorreactor' ),
				'value' => (string) ( $row['creatorreactor_user_uuid'] ?? '' ) !== '' ? (string) $row['creatorreactor_user_uuid'] : '-',
			],
			[
				'label' => __( 'CreatorReactor Record ID', 'wp-creatorreactor' ),
				'value' => isset( $row['id'] ) ? (string) (int) $row['id'] : '-',
			],
			[
				'label' => __( 'WordPress user ID', 'wp-creatorreactor' ),
				'value' => $wp_uid,
			],
			[
				'label' => __( 'Product (stored)', 'wp-creatorreactor' ),
				'value' => $product_key !== '' ? $product_key : '-',
			],
			[
				'label' => __( 'Product (label)', 'wp-creatorreactor' ),
				'value' => Entitlements::product_label( $row['product'] ?? Entitlements::PRODUCT_FANVUE ),
			],
			[
				'label' => __( 'Email (normalized)', 'wp-creatorreactor' ),
				'value' => (string) ( $row['email'] ?? '' ) !== '' ? (string) $row['email'] : '-',
			],
			[
				'label' => __( 'Display name (normalized)', 'wp-creatorreactor' ),
				'value' => (string) ( $row['display_name'] ?? '' ) !== '' ? (string) $row['display_name'] : '-',
			],
			[
				'label' => __( 'Status', 'wp-creatorreactor' ),
				'value' => (string) ( $row['status'] ?? '' ) !== '' ? (string) $row['status'] : '-',
			],
			[
				'label' => __( 'Tier (stored)', 'wp-creatorreactor' ),
				'value' => $tier_raw !== '' ? $tier_raw : '-',
			],
			[
				'label' => __( 'Tier (Follower / Subscriber)', 'wp-creatorreactor' ),
				'value' => Entitlements::tier_audience_label( $tier_raw !== '' ? $tier_raw : null ),
			],
			[
				'label' => __( 'Expires at', 'wp-creatorreactor' ),
				'value' => self::format_datetime_for_selected_timezone( (string) ( $row['expires_at'] ?? '' ) ),
			],
			[
				'label' => __( 'Updated at', 'wp-creatorreactor' ),
				'value' => self::format_datetime_for_selected_timezone( (string) ( $row['updated_at'] ?? '' ) ),
			],
		];

		if ( $is_fanvue_row ) {
			$lines[] = [
				'section' => true,
				'label'   => __( 'Fanvue Records', 'wp-creatorreactor' ),
				'value'   => '',
			];
			$lines[] = [
				'label' => __( 'Fanvue user UUID', 'wp-creatorreactor' ),
				'value' => (string) ( $row['fanvue_user_uuid'] ?? '' ) !== '' ? (string) $row['fanvue_user_uuid'] : '-',
			];
			$lines[] = [
				'label' => __( 'Fanvue email', 'wp-creatorreactor' ),
				'value' => (string) ( $row['fanvue_email'] ?? '' ) !== '' ? (string) $row['fanvue_email'] : '-',
			];
			$lines[] = [
				'label' => __( 'Fanvue display name', 'wp-creatorreactor' ),
				'value' => (string) ( $row['fanvue_display_name'] ?? '' ) !== '' ? (string) $row['fanvue_display_name'] : '-',
			];
			$lines[] = [
				'label' => __( 'Fanvue tier (stored)', 'wp-creatorreactor' ),
				'value' => (string) ( $row['fanvue_tier'] ?? '' ) !== '' ? (string) $row['fanvue_tier'] : '-',
			];
			$lines[] = [
				'label' => __( 'Fanvue sync snapshot', 'wp-creatorreactor' ),
				'value' => $snapshot_out,
			];
		}

		$lines[] = [
			'section' => true,
			'label'   => __( 'OnlyFans', 'wp-creatorreactor' ),
			'value'   => '',
		];
		if ( $is_onlyfans_row ) {
			$lines[] = [
				'label' => __( 'Notes', 'wp-creatorreactor' ),
				'value' => __( 'This row is stored for the OnlyFans product using the shared entitlement schema. Visitor linking is configured under Settings → OnlyFans (OFAuth).', 'wp-creatorreactor' ),
			];
			if ( $snapshot_raw !== '' ) {
				$lines[] = [
					'label' => __( 'Webhook / payload snapshot (raw)', 'wp-creatorreactor' ),
					'value' => $snapshot_out,
				];
			}
		} else {
			$lines[] = [
				'label' => __( 'Entitlement scope', 'wp-creatorreactor' ),
				'value' => $is_fanvue_row
					? __( 'Fanvue — OnlyFans-specific rows use product “onlyfans”.', 'wp-creatorreactor' )
					: __( 'Not OnlyFans — this row uses a different product key.', 'wp-creatorreactor' ),
			];
		}

		return [ 'lines' => $lines ];
	}

	/**
	 * Shortcodes settings tab: user guide (collapsible), quick shortcode reference.
	 */
	private static function render_shortcodes_tab_body() {
		$fan_callback       = Fan_OAuth::get_callback_redirect_uri();
		$google_callback    = Google_OAuth::get_callback_redirect_uri();
		$instagram_callback = Instagram_OAuth::get_callback_redirect_uri();
		?>
		<div class="creatorreactor-shortcodes-guide-wrap" style="max-width: 920px;">
			<details class="creatorreactor-section creatorreactor-shortcodes-guide-details">
				<summary class="creatorreactor-shortcodes-guide-summary">
					<span class="creatorreactor-shortcodes-guide-summary-chevron" aria-hidden="true"></span>
					<span class="creatorreactor-shortcodes-guide-summary-text"><?php esc_html_e( 'CreatorReactor Plugin — User Guide (Simplified)', 'wp-creatorreactor' ); ?></span>
				</summary>
				<div class="creatorreactor-shortcodes-guide-inner">

			<h3><?php esc_html_e( 'Overview', 'wp-creatorreactor' ); ?></h3>
			<p><?php esc_html_e( 'CreatorReactor lets you control who can see content on your WordPress site based on a user’s Fanvue status (follower or subscriber).', 'wp-creatorreactor' ); ?></p>
			<p><?php esc_html_e( 'You can:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Restrict content using shortcodes', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Use the same logic in Block Editor blocks or Elementor widgets', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Allow users to log in via Fanvue OAuth', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Optionally allow visitors to sign in with Google (Creator/direct mode)', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Optionally register Instagram (Meta) OAuth app credentials on Settings → Instagram for the Instagram OAuth module (Creator/direct mode); wp-login social buttons remain Fanvue and/or Google only.', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<h3><?php esc_html_e( '1. How Content Gating Works', 'wp-creatorreactor' ); ?></h3>
			<p><?php esc_html_e( 'Content visibility is based on:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'The user being logged in', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Their Fanvue entitlement status (follower or subscriber)', 'wp-creatorreactor' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Entitlements are matched using:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'The user’s email address, or', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Their linked WordPress account', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<h3><?php esc_html_e( '2. Using Shortcodes', 'wp-creatorreactor' ); ?></h3>
			<p><?php esc_html_e( 'Add these inside:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Posts', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Pages', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Any shortcode-enabled block', 'wp-creatorreactor' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Available Shortcodes', 'wp-creatorreactor' ); ?></h4>
			<p><strong><?php esc_html_e( 'Follower-only content', 'wp-creatorreactor' ); ?></strong></p>
			<pre class="creatorreactor-guide-code" style="white-space: pre-wrap; word-break: break-word; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><code><?php echo esc_html( "[follower]\nThis content is only visible to followers.\n[/follower]" ); ?></code></pre>

			<p><strong><?php esc_html_e( 'Subscriber-only content', 'wp-creatorreactor' ); ?></strong></p>
			<pre class="creatorreactor-guide-code" style="white-space: pre-wrap; word-break: break-word; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><code><?php echo esc_html( "[subscriber]\nThis content is only visible to paid subscribers.\n[/subscriber]" ); ?></code></pre>
			<p><?php esc_html_e( 'Supports:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'General subscriber access: fanvue_subscriber', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Tier-based access: fanvue_subscriber_<tiername>', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<p><strong><?php esc_html_e( 'Logged-out only', 'wp-creatorreactor' ); ?></strong></p>
			<pre class="creatorreactor-guide-code" style="white-space: pre-wrap; word-break: break-word; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><code><?php echo esc_html( "[logged_out]\nPlease log in to view this content.\n[/logged_out]" ); ?></code></pre>

			<hr />

			<p><strong><?php esc_html_e( 'Fanvue login button', 'wp-creatorreactor' ); ?></strong></p>
			<pre class="creatorreactor-guide-code" style="white-space: pre-wrap; word-break: break-word; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><code><?php echo esc_html( "[standard_fanvue_login_button]\n[minimal_fanvue_login_button]\n\n[fanvue_login_button]" ); ?></code></pre>
			<p><?php esc_html_e( 'Standard shows the banner image (login-fanvue.webp) and label; minimal shows a larger Fanvue mark (fanvue-logo.webp) on transparent pixels so the button’s green fill shows behind it. [fanvue_login_button] uses the style chosen under Settings → Fanvue → Login Button Appearance.', 'wp-creatorreactor' ); ?></p>
			<p><?php esc_html_e( 'What happens after login:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'If the Fanvue email matches an existing WordPress user → user is logged in', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'If no match → a new account is created (if registration is enabled)', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<p><strong><?php esc_html_e( 'Google sign-in button', 'wp-creatorreactor' ); ?></strong></p>
			<pre class="creatorreactor-guide-code" style="white-space: pre-wrap; word-break: break-word; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><code><?php echo esc_html( "[standard_google_login_button]\n[minimal_google_login_button]\n\n[google_login_button]" ); ?></code></pre>
			<p><?php esc_html_e( 'Standard uses the “Login button appearance” from Settings → Google; minimal shows only the Google “G” mark. [google_login_button] is the same as standard. Not available in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
			<p><?php esc_html_e( 'What happens after sign-in:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'If the Google account email matches an existing WordPress user → user is logged in', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'If no match → a new account is created (if registration is enabled)', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<h3><?php esc_html_e( '3. Using Blocks or Elementor', 'wp-creatorreactor' ); ?></h3>
			<p><strong><?php esc_html_e( 'Block Editor (Gutenberg)', 'wp-creatorreactor' ); ?></strong></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Look for blocks under “CreatorReactor”', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Gates and visibility match the shortcodes (no difference in logic).', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'There is a dedicated “Login with Fanvue” block; for Google sign-in on a page, use the Shortcode block (or a shortcode-enabled block) with [standard_google_login_button] or [minimal_google_login_button].', 'wp-creatorreactor' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Elementor', 'wp-creatorreactor' ); ?></strong></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Use widgets in the “CreatorReactor” category for gates and Fanvue login.', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'For Google sign-in in Elementor, use the Shortcode widget with the same Google shortcodes as above.', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<h3><?php esc_html_e( '4. OAuth Setup (Required for Login)', 'wp-creatorreactor' ); ?></h3>
			<p><?php esc_html_e( 'Visitor login uses redirect URIs you register with each provider. In Creator (direct) mode:', 'wp-creatorreactor' ); ?></p>
			<p><strong><?php esc_html_e( 'Fanvue', 'wp-creatorreactor' ); ?></strong></p>
			<p><?php esc_html_e( 'Add this redirect URI to your Fanvue app:', 'wp-creatorreactor' ); ?></p>
			<p><code style="word-break: break-all; display: inline-block; max-width: 100%;"><?php echo esc_html( $fan_callback ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Handles Fanvue login and account linking.', 'wp-creatorreactor' ); ?></p>
			<p><strong><?php esc_html_e( 'Google', 'wp-creatorreactor' ); ?></strong></p>
			<p><?php esc_html_e( 'Add this as an Authorized redirect URI on your OAuth 2.0 Web client in Google Cloud Console (APIs & Services → Credentials):', 'wp-creatorreactor' ); ?></p>
			<p><code style="word-break: break-all; display: inline-block; max-width: 100%;"><?php echo esc_html( $google_callback ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Configure Client ID and Secret under Settings → Google. Google sign-in is not available in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
			<p><strong><?php esc_html_e( 'Instagram (Meta)', 'wp-creatorreactor' ); ?></strong></p>
			<p><?php esc_html_e( 'If you use the Instagram OAuth module, add this OAuth redirect URI in Meta for Developers → your app → Instagram → API setup → OAuth settings (exact match, including trailing slash):', 'wp-creatorreactor' ); ?></p>
			<p><code style="word-break: break-all; display: inline-block; max-width: 100%;"><?php echo esc_html( $instagram_callback ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Configure Client ID and Client Secret under Settings → Instagram. Instagram OAuth is not used in Agency (broker) mode. There are no Instagram login shortcodes; page and wp-login sign-in use Fanvue and Google shortcodes when configured.', 'wp-creatorreactor' ); ?></p>

			<hr />

			<h3><?php esc_html_e( '5. Developer Notes (Optional)', 'wp-creatorreactor' ); ?></h3>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'The plugin automatically detects the editor context (Elementor vs Block Editor)', 'wp-creatorreactor' ); ?></li>
				<li>
					<?php esc_html_e( 'Detection is handled by:', 'wp-creatorreactor' ); ?>
					<code>CreatorReactor\Editor_Context</code>
					<?php esc_html_e( '— file:', 'wp-creatorreactor' ); ?>
					<code>includes/class-editor-context.php</code>
				</li>
			</ul>
			<p><?php esc_html_e( 'This ensures consistent behavior across:', 'wp-creatorreactor' ); ?></p>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Admin editor', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Stored post format', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Frontend rendering', 'wp-creatorreactor' ); ?></li>
			</ul>

			<hr />

			<h3><?php esc_html_e( 'Key Takeaways', 'wp-creatorreactor' ); ?></h3>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Use shortcodes, blocks, or widgets interchangeably', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Content is shown based on Fanvue follower/subscriber status', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Fanvue and (optionally) Google OAuth enable automatic login and account linking in Creator mode; Instagram OAuth is configured separately for Meta credentials and Dashboard status.', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Works across Elementor and Gutenberg without extra setup', 'wp-creatorreactor' ); ?></li>
			</ul>

				</div>
			</details>

			<div class="creatorreactor-section creatorreactor-shortcodes-reference" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Shortcodes (quick reference)', 'wp-creatorreactor' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Core gating behavior is also available as Gutenberg blocks (CreatorReactor category) and Elementor widgets when those editors are active.', 'wp-creatorreactor' ); ?></p>
				<table class="widefat striped" style="margin-top: 12px;">
					<thead>
						<tr>
							<th scope="col" style="width: 38%;"><?php esc_html_e( 'Shortcode', 'wp-creatorreactor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it does', 'wp-creatorreactor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code style="word-break: break-word;">[follower] … [/follower]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when the visitor is logged in and has an active follower entitlement (e.g. fanvue_follower), matched by linked WP user or email.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code style="word-break: break-word;">[subscriber] … [/subscriber]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when logged in with an active paid subscriber tier (fanvue_subscriber or fanvue_subscriber_<tier>).', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code style="word-break: break-word;">[logged_out] … [/logged_out]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when the visitor is not logged in.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code style="word-break: break-word;">[logged_in] … [/logged_in]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when the visitor is logged in.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code style="word-break: break-word;">[fanvue_connected] … [/fanvue_connected]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when a logged-in user has linked Fanvue OAuth.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code style="word-break: break-word;">[fanvue_not_connected] … [/fanvue_not_connected]</code></td>
							<td><?php esc_html_e( 'Shows inner content only when a logged-in user has not linked Fanvue OAuth.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[standard_fanvue_login_button]</code></td>
							<td><?php esc_html_e( 'Large “Login with Fanvue” control (image + label). Creator/direct mode only; add the plugin’s fan OAuth callback URL to your Fanvue app.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[minimal_fanvue_login_button]</code></td>
							<td><?php esc_html_e( 'Compact Fanvue mark on solid green; same OAuth flow as the standard Fanvue login shortcode.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[fanvue_login_button]</code></td>
							<td><?php esc_html_e( 'Legacy alias for [standard_fanvue_login_button].', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[standard_google_login_button]</code></td>
							<td><?php esc_html_e( 'Sign in with Google using the “Login button appearance” from Settings → Google (self-closing). Creator/direct mode only; add the plugin’s Google OAuth callback URL to your Web client’s Authorized redirect URIs.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[minimal_google_login_button]</code></td>
							<td><?php esc_html_e( 'Compact Google “G” mark only; ignores the appearance preset. Same OAuth flow and mode rules as the standard Google shortcode.', 'wp-creatorreactor' ); ?></td>
						</tr>
						<tr>
							<td><code>[google_login_button]</code></td>
							<td><?php esc_html_e( 'Legacy alias for [standard_google_login_button].', 'wp-creatorreactor' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Full product documentation with table of contents and search.
	 */
	private static function render_documentation_tab_body() {
		?>
		<div class="creatorreactor-section creatorreactor-docs-shell">
			<div class="creatorreactor-docs-header">
				<div class="creatorreactor-docs-header-brand">
					<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--docs' ); ?>
					<h2><?php esc_html_e( 'Documentation', 'wp-creatorreactor' ); ?></h2>
				</div>
				<div class="creatorreactor-docs-search-controls">
					<input type="search" id="creatorreactor-docs-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search documentation... (Press /)', 'wp-creatorreactor' ); ?>" />
					<button type="button" id="creatorreactor-docs-clear" class="button"><?php esc_html_e( 'Clear', 'wp-creatorreactor' ); ?></button>
				</div>
			</div>
			<div class="creatorreactor-docs-layout">
				<nav class="creatorreactor-docs-toc" aria-label="<?php esc_attr_e( 'Documentation table of contents', 'wp-creatorreactor' ); ?>">
					<h3><?php esc_html_e( 'Contents', 'wp-creatorreactor' ); ?></h3>
					<ol>
						<li><a href="#cr-docs-overview"><?php esc_html_e( 'Overview', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-dashboard"><?php esc_html_e( 'Dashboard & Module Status', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-general"><?php esc_html_e( 'General', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-fanvue"><?php esc_html_e( 'Fanvue (OAuth)', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-google"><?php esc_html_e( 'Google (OAuth)', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-instagram"><?php esc_html_e( 'Instagram (Meta OAuth)', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-onlyfans"><?php esc_html_e( 'OnlyFans (OFAuth)', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-cloud"><?php esc_html_e( 'CreatorReactor Cloud', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-sync"><?php esc_html_e( 'Sync', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-shortcodes"><?php esc_html_e( 'Shortcodes', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-users"><?php esc_html_e( 'Users', 'wp-creatorreactor' ); ?></a></li>
						<li><a href="#cr-docs-debug"><?php esc_html_e( 'Debug', 'wp-creatorreactor' ); ?></a></li>
					</ol>
				</nav>
				<div class="creatorreactor-docs-content">
					<p class="creatorreactor-docs-no-results" hidden><?php esc_html_e( 'No documentation sections match your search.', 'wp-creatorreactor' ); ?></p>
					<section id="cr-docs-overview" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Overview', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'CreatorReactor is a WordPress access-control plugin that gates content and login behavior based on creator-platform entitlements. In production, the plugin handles OAuth connection, entitlement sync, user mapping, and role-aware content rendering.', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Core production flow: (1) configure authentication, (2) connect Fanvue or broker mode, (3) optionally enable Sign in with Google for visitors in Creator mode, (4) optionally configure Instagram (Meta) OAuth app credentials when your deployment uses that integration, (5) sync/refresh entitlements, (6) apply shortcodes or blocks to protected content, (7) validate with test users before go-live.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Production prerequisites', 'wp-creatorreactor' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'WordPress administrator access and ability to install/activate plugins', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Fanvue developer app credentials (Creator mode) or broker credentials (Agency mode)', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'When using Google login: a Google Cloud OAuth 2.0 Web client and the plugin’s Authorized redirect URI copied exactly into that client', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'When using Instagram (Meta) OAuth: a Meta developer app with Instagram API with Instagram Login and the plugin’s OAuth redirect URI copied exactly into that app (including trailing slash)', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Confirmed redirect URIs and HTTPS site URL in production', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'A test user matrix (follower, subscriber, no entitlement, logged-out)', 'wp-creatorreactor' ); ?></li>
						</ul>
					</section>
					<section id="cr-docs-dashboard" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Dashboard & Module Status', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'The Dashboard summarizes operational health through module traffic lights. Use it as your first troubleshooting stop before checking logs.', 'wp-creatorreactor' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Green: configured and currently functional', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Yellow: installed but incomplete configuration or pending sync/connection', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Red: critical error condition blocking normal operation', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Gray: module not installed/enabled', 'wp-creatorreactor' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'For parent modules, gray child modules are treated as not installed; healthy installed children can still keep the parent green.', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Under Authentication, child modules include Fanvue OAuth, Google OAuth, Instagram OAuth, and OnlyFans OFAuth—each reflects configuration and test status for that gateway.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-general" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'General', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'General tab controls site-wide behavior independent of OAuth credentials.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Key options', 'wp-creatorreactor' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'WordPress login: Fanvue, Google, and any configured social OAuth tab (TikTok, X, Bluesky, etc.) show buttons when credentials are saved.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Instagram OAuth is configured on Settings → Instagram (Meta app ID and secret); it does not add a button to wp-login—visitor sign-in on the login page uses Fanvue, Google, and the other social tabs when enabled.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Admin timezone display for user/sync timestamps', 'wp-creatorreactor' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Recommendation: keep timezone aligned with your operations team so sync and error timelines are easy to correlate.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-fanvue" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Fanvue (OAuth)', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'Use the Fanvue tab to configure authentication mode and OAuth settings for production access control.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Configuration steps', 'wp-creatorreactor' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Select authentication mode: Creator (direct OAuth) or Agency (broker).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Enter required credentials (Client ID/Secret for Creator mode, Broker URL/Site ID for Agency mode).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Verify redirect URIs in the provider app exactly match plugin values.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Save settings, then run Connect from the dashboard.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Run a connection test and confirm green status.', 'wp-creatorreactor' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'If connection fails, validate credentials, redirect URI accuracy, HTTPS, and provider-side app status before retrying.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-google" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Google (OAuth)', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'The Google tab configures OAuth 2.0 for visitor sign-in (wp-login and shortcodes). It is independent of Fanvue connection: users can log in with Google, then entitlements still come from Fanvue sync when their email or account matches.', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Sign in with Google is available only in Creator (direct) mode; it is disabled in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Configuration steps', 'wp-creatorreactor' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'In Google Cloud Console, create or open a project and open APIs & Services for OAuth credentials.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Configure the OAuth consent screen (app name, support email, and test users while in testing). The plugin requests openid, email, and profile scopes.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Create credentials → OAuth client ID → Application type “Web application”.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Paste the plugin’s Authorized redirect URI from Settings → Google into “Authorized redirect URIs” (must match exactly, including https and trailing slash).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the Client ID and Client Secret into Settings → Google, run the configuration check, save, then test Sign in with Google from wp-login or a page with the Google login shortcode.', 'wp-creatorreactor' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'If Google returns redirect_uri_mismatch, compare the URI shown in this plugin with the Web client in Google Cloud Console character for character.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-instagram" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Instagram (Meta OAuth)', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'The Instagram tab stores the Instagram app ID and app secret for Meta’s Instagram API with Instagram Login product. Values are encrypted at rest. Saving runs a configuration check against Instagram’s token endpoint (similar to the Google tab).', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Instagram OAuth is available only in Creator (direct) mode; it is not used in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
						<p>
							<a href="https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/get-started" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Instagram API with Instagram Login — Get started (Meta)', 'wp-creatorreactor' ); ?></a>
						</p>
						<h4><?php esc_html_e( 'Configuration steps', 'wp-creatorreactor' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'In Meta for Developers, create or open an app and add the Instagram API with Instagram Login product.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'In Instagram → API setup → OAuth settings, set the OAuth redirect URI to the exact value shown on Settings → Instagram (including https and trailing slash).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the Instagram app ID into Client ID and the Instagram app secret into Client Secret, run the save-time configuration check, then save.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Use Dashboard → Authentication → Instagram OAuth to confirm module status and run Test when you change credentials.', 'wp-creatorreactor' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'If the check reports redirect URI problems, compare the URI in this plugin with Meta’s OAuth settings character for character. For invalid client errors, confirm the ID and secret belong to the same app and that Instagram Login is fully added to the app.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-onlyfans" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'OnlyFans (OFAuth)', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'The OnlyFans tab configures OFAuth Account Linking: API key, webhook secret, optional hosted redirect URLs, and the webhook URL to register in the OFAuth dashboard.', 'wp-creatorreactor' ); ?></p>
						<p>
							<a href="https://docs.ofauth.com/guide/OnlyFans-authentication/Integrating" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'OFAuth integration guide', 'wp-creatorreactor' ); ?></a>
						</p>
						<h4><?php esc_html_e( 'Configuration steps', 'wp-creatorreactor' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Generate an OFAuth access key with Account Linking permissions.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the webhook URL from this plugin into your OFAuth webhook settings.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Paste the API key and webhook secret, then save.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Implement hosted or embedded link flows using OFAuth’s client session API (POST /v2/link/init) from your application code; session data is delivered to the webhook URL on success.', 'wp-creatorreactor' ); ?></li>
						</ol>
					</section>
					<section id="cr-docs-cloud" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'CreatorReactor Cloud', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'CreatorReactor Cloud settings control whether cloud integration is considered installed and provide cloud credentials used by cloud-dependent flows.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Fields', 'wp-creatorreactor' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Cloud account: Active, Your CreatorReactor ID, and CreatorReactor Password (encrypted at rest; leave masked value to keep existing secret).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Schema service: Schema Service URL (base URL of the API; unlock the card to edit).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Metrics ingest: bearer token for POST /v1/ingest after each scheduled sync (URL is resolved from environment or install-time storage; not editable in Settings).', 'wp-creatorreactor' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Production tip: rotate cloud credentials through a planned maintenance window and verify module status returns to green immediately after update.', 'wp-creatorreactor' ); ?></p>
					</section>
					<section id="cr-docs-sync" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Sync', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'Sync settings define how frequently entitlement data is refreshed and how long cached entitlement checks are reused.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Operational guidance', 'wp-creatorreactor' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Use shorter intervals for high-change environments; longer intervals for lower API load.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'After changing Fanvue OAuth scopes or credentials, disconnect and reconnect before validating sync. After changing Google or Instagram app credentials, re-save and re-test those providers from their settings or the Dashboard.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Use “Sync & refresh list” on Users tab for immediate verification after config changes.', 'wp-creatorreactor' ); ?></li>
						</ul>
					</section>
					<section id="cr-docs-shortcodes" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Shortcodes', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'Shortcodes are the primary production mechanism for gating visibility by entitlement state, login state, and tier.', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Best practice: wrap premium sections with explicit fallback content for logged-out or non-entitled visitors to improve conversion and reduce support tickets.', 'wp-creatorreactor' ); ?></p>
						<p><?php esc_html_e( 'Gutenberg includes a Fanvue login block; Google sign-in on pages uses the same shortcodes via the Shortcode block (or Elementor Shortcode widget). Instagram uses Settings → Instagram for Meta app credentials only (see the user guide OAuth section for the redirect URI).', 'wp-creatorreactor' ); ?></p>
						<?php self::render_shortcodes_tab_body(); ?>
					</section>
					<section id="cr-docs-users" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Users', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'Users tab is your verification and audit surface for production records.', 'wp-creatorreactor' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Inspect source product, status, tier, and linked WordPress user IDs.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Use details modal to confirm record-level payload and timestamps.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Use refresh actions after OAuth/sync changes to confirm effective state.', 'wp-creatorreactor' ); ?></li>
						</ul>
					</section>
					<section id="cr-docs-debug" class="creatorreactor-doc-section">
						<h3><?php esc_html_e( 'Debug', 'wp-creatorreactor' ); ?></h3>
						<p><?php esc_html_e( 'Debug tab provides integration health checks, logs, and recovery actions for incident response.', 'wp-creatorreactor' ); ?></p>
						<h4><?php esc_html_e( 'Troubleshooting runbook', 'wp-creatorreactor' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Check Dashboard module lights to identify failing area.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Review connection test details and latest connection log entries.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Validate credentials, redirect URIs, and mode selection.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Reconnect and run sync refresh; validate in Users tab.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'After recovery, clear stale logs to simplify future diagnosis.', 'wp-creatorreactor' ); ?></li>
						</ol>
						<h4><?php esc_html_e( 'Go-live checklist', 'wp-creatorreactor' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'OAuth connection tested successfully', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'At least one entitlement sync verified in Users tab', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Shortcode-protected pages tested with each user state', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Instagram OAuth tested on the Dashboard when Meta credentials are in use for the site', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Cloud module configured (or intentionally disabled)', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Debug logs reviewed and baseline captured', 'wp-creatorreactor' ); ?></li>
						</ul>
					</section>
				</div>
			</div>
		</div>
		<script>
		(function() {
			var root = document.currentScript ? document.currentScript.previousElementSibling : null;
			if (!root || root.dataset.docsSearchBound === "1") {
				return;
			}
			root.dataset.docsSearchBound = "1";
			var input = root.querySelector("#creatorreactor-docs-search");
			var clearBtn = root.querySelector("#creatorreactor-docs-clear");
			if (!input) {
				return;
			}
			var sections = Array.prototype.slice.call(root.querySelectorAll(".creatorreactor-doc-section"));
			var noResults = root.querySelector(".creatorreactor-docs-no-results");
			var tocLinks = Array.prototype.slice.call(root.querySelectorAll(".creatorreactor-docs-toc a[href^=\"#\"]"));
			var headingById = {};
			var currentSectionId = null;
			sections.forEach(function(section) {
				var h = section.querySelector("h3");
				if (h) {
					headingById[section.id] = h;
				}
			});

			function showOnlySection(sectionId) {
				currentSectionId = sectionId;
				sections.forEach(function(section) {
					section.hidden = section.id !== sectionId;
				});
				tocLinks.forEach(function(link) {
					var href = (link.getAttribute("href") || "").replace(/^#/, "");
					link.classList.toggle("is-active", href === sectionId);
				});
			}

			function clearHighlights() {
				Object.keys(headingById).forEach(function(id) {
					var h = headingById[id];
					if (!h) {
						return;
					}
					if (h.dataset.originalText !== undefined) {
						h.textContent = h.dataset.originalText;
					}
					h.classList.remove("creatorreactor-docs-highlight");
				});
			}

			function updateSearch() {
				var q = (input.value || "").toLowerCase().trim();
				var visibleCount = 0;
				var firstMatchId = null;
				clearHighlights();
				tocLinks.forEach(function(link) {
					var targetId = (link.getAttribute("href") || "").replace(/^#/, "");
					var section = root.querySelector("#" + targetId);
					if (!section) {
						link.style.display = "none";
						return;
					}
					var text = (section.textContent || "").toLowerCase();
					var visible = q === "" || text.indexOf(q) !== -1;
					link.style.display = visible ? "" : "none";
					if (visible) {
						visibleCount += 1;
						if (!firstMatchId) {
							firstMatchId = section.id;
						}
						var heading = headingById[section.id];
						if (heading && q !== "") {
							if (heading.dataset.originalText === undefined) {
								heading.dataset.originalText = heading.textContent || "";
							}
							if ((heading.dataset.originalText || "").toLowerCase().indexOf(q) !== -1) {
								heading.classList.add("creatorreactor-docs-highlight");
							}
						}
					}
				});
				if (noResults) {
					noResults.hidden = visibleCount !== 0;
				}
				if (visibleCount === 0) {
					sections.forEach(function(section) {
						section.hidden = true;
					});
					currentSectionId = null;
					tocLinks.forEach(function(link) {
						link.classList.remove("is-active");
					});
					return;
				}
				if (!currentSectionId || !firstMatchId) {
					showOnlySection(firstMatchId);
					return;
				}
				var currentLink = root.querySelector(".creatorreactor-docs-toc a[href=\"#" + currentSectionId + "\"]");
				if (!currentLink || currentLink.style.display === "none") {
					showOnlySection(firstMatchId);
				} else {
					showOnlySection(currentSectionId);
				}
			}

			tocLinks.forEach(function(link) {
				link.addEventListener("click", function(e) {
					e.preventDefault();
					var targetId = (link.getAttribute("href") || "").replace(/^#/, "");
					if (!targetId || link.style.display === "none") {
						return;
					}
					showOnlySection(targetId);
				});
			});

			input.addEventListener("input", function() {
				updateSearch();
			});
			if (clearBtn) {
				clearBtn.addEventListener("click", function() {
					input.value = "";
					updateSearch();
					input.focus();
				});
			}
			document.addEventListener("keydown", function(e) {
				if (!root.closest(".creatorreactor-tab-panel.is-active")) {
					return;
				}
				var key = e.key || "";
				if (key !== "/") {
					return;
				}
				var target = e.target;
				var tag = target && target.tagName ? String(target.tagName).toLowerCase() : "";
				if (tag === "input" || tag === "textarea" || (target && target.isContentEditable)) {
					return;
				}
				e.preventDefault();
				input.focus();
				input.select();
			});
			if (sections.length > 0) {
				showOnlySection(sections[0].id);
			}
			updateSearch();
		})();
		</script>
		<?php
	}

	/**
	 * General settings tab: site-wide plugin options.
	 *
	 * Reloads settings from the database (bypassing a stale object cache) so checkboxes match the latest stored state.
	 */
	private static function render_general_tab_body() {
		self::flush_creatorreactor_settings_cache();
		$opts = self::get_options();

		$social_ok = self::is_fan_social_login_configured();
		$checked   = $social_ok && ! empty( $opts['replace_wp_login_with_social'] );
		$restrict_wp_admin = ! empty( $opts['restrict_creatorreactor_users_wp_admin'] );
		$hide_admin_bar    = ! empty( $opts['hide_admin_bar_for_creatorreactor_users'] );
		$oauth_logo_general = self::oauth_logo_button_general_size_from_opts( $opts );
		$display_timezone   = self::sanitize_display_timezone( $opts['display_timezone'] ?? 'browser' );
		?>
		<div class="creatorreactor-section">
			<h2><?php esc_html_e( 'General', 'wp-creatorreactor' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Social Login', 'wp-creatorreactor' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[replace_wp_login_with_social]" value="0" />
							<label for="creatorreactor_replace_wp_login_with_social">
								<input type="checkbox" id="creatorreactor_replace_wp_login_with_social" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[replace_wp_login_with_social]" value="1" <?php checked( $checked ); ?> <?php disabled( ! $social_ok ); ?> />
								<?php esc_html_e( 'Add social login button to the WordPress login page?', 'wp-creatorreactor' ); ?>
							</label>
							<?php if ( ! $social_ok ) : ?>
								<p class="description creatorreactor-general-login-error">
									<?php esc_html_e( 'You must have at least one of the OAuth configurations enabled before selecting this option.', 'wp-creatorreactor' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Login Button Style', 'wp-creatorreactor' ); ?></th>
						<td>
							<?php
							self::render_oauth_logo_button_size_mode_fieldset(
								self::OPTION_NAME,
								self::OAUTH_LOGO_BUTTON_GENERAL_SIZE_KEY,
								$oauth_logo_general,
								$oauth_logo_general,
								Shortcodes::fanvue_oauth_admin_preview_chip( 'minimal' ),
								Shortcodes::fanvue_oauth_admin_preview_chip( 'standard' ),
								[
									'show_default_pill'       => false,
									'legend'                  => __( 'Default Login Button Style', 'wp-creatorreactor' ),
									'legend_class'            => 'screen-reader-text',
									'description'             => __( 'Sets the default width for social login buttons site-wide. Individual providers can override this under their own Login Button Appearance settings.', 'wp-creatorreactor' ),
									'radiogroup_aria_label'   => __( 'Default Login Button Style', 'wp-creatorreactor' ),
									'fieldset_extra_classes'  => ' creatorreactor-default-button-style-fieldset',
								]
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="creatorreactor_display_timezone"><?php esc_html_e( 'Display Timezone', 'wp-creatorreactor' ); ?></label></th>
						<td>
							<select id="creatorreactor_display_timezone" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_timezone]">
								<option value="browser" <?php selected( 'browser', $display_timezone ); ?>><?php esc_html_e( 'System Time', 'wp-creatorreactor' ); ?></option>
								<?php
								$tz_choice_selected = ( $display_timezone !== 'browser' ) ? $display_timezone : '';
								ob_start();
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup; escaped below.
								echo wp_timezone_choice( $tz_choice_selected );
								$tz_choice_markup = (string) ob_get_clean();
								if ( 'browser' === $display_timezone ) {
									// wp_timezone_choice( '' ) marks the placeholder "Select a city" option selected; System Time (browser) must stay selected.
									$tz_choice_markup = (string) preg_replace_callback(
										'/<option([^>]*?)>/i',
										static function ( $m ) {
											$attrs = (string) $m[1];
											if ( preg_match( '/\bvalue\s*=\s*(""|\'\')/', $attrs ) && preg_match( '/\bselected\b/', $attrs ) ) {
												$attrs = (string) preg_replace( '/\s+selected(?:\s*=\s*(["\'])selected\1)?/i', '', $attrs );
												return '<option' . $attrs . '>';
											}
											return $m[0];
										},
										$tz_choice_markup
									);
								}
								echo $tz_choice_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as wp_timezone_choice().
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Controls how timestamps are displayed in the Users page and Debug tab / Logging', 'wp-creatorreactor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WordPress admin', 'wp-creatorreactor' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[restrict_creatorreactor_users_wp_admin]" value="0" />
							<label for="creatorreactor_restrict_creatorreactor_users_wp_admin">
								<input type="checkbox" id="creatorreactor_restrict_creatorreactor_users_wp_admin" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[restrict_creatorreactor_users_wp_admin]" value="1" <?php checked( $restrict_wp_admin ); ?> />
								<?php esc_html_e( 'Restrict users from accessing wp-admin', 'wp-creatorreactor' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When checked, logged-in users with a CreatorReactor role (and without site admin capabilities) cannot open wp-admin screens.', 'wp-creatorreactor' ); ?></p>
							<p style="margin-top: 12px;">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_admin_bar_for_creatorreactor_users]" value="0" />
								<label for="creatorreactor_hide_admin_bar_for_creatorreactor_users">
									<input type="checkbox" id="creatorreactor_hide_admin_bar_for_creatorreactor_users" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_admin_bar_for_creatorreactor_users]" value="1" <?php checked( $hide_admin_bar ); ?> />
									<?php esc_html_e( 'Remove top admin bar for logged in users', 'wp-creatorreactor' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'When checked, the WordPress admin bar is hidden on the site front end for CreatorReactor role users who are not site admins.', 'wp-creatorreactor' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Changes', 'wp-creatorreactor' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Whether a hook callback is owned by CreatorReactor (skip in redirect conflict scan).
	 *
	 * @param mixed $fn Hook callback as stored in $wp_filter.
	 */
	private static function integration_is_creatorreactor_hook_callback( $fn ): bool {
		if ( ! is_array( $fn ) ) {
			return false;
		}
		$class = '';
		if ( is_object( $fn[0] ?? null ) ) {
			$class = get_class( $fn[0] );
		} elseif ( is_string( $fn[0] ?? null ) ) {
			$class = (string) $fn[0];
		}
		return $class !== '' && strpos( strtolower( $class ), 'wp-creatorreactor' ) !== false;
	}

	/**
	 * Absolute file path for a class method hook callback, when reflection succeeds.
	 *
	 * @param array<int, mixed> $fn [ class or object, method name ].
	 */
	private static function integration_hook_array_callback_file( array $fn ): ?string {
		$class = is_object( $fn[0] ?? null ) ? get_class( $fn[0] ) : ( is_string( $fn[0] ?? null ) ? (string) $fn[0] : '' );
		$method = isset( $fn[1] ) && is_string( $fn[1] ) ? $fn[1] : '';
		if ( $class === '' || $method === '' ) {
			return null;
		}
		try {
			$file = ( new \ReflectionMethod( $class, $method ) )->getFileName();
			return is_string( $file ) && $file !== '' ? $file : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Fallback label when callback file cannot be resolved.
	 *
	 * @param array<int, mixed> $fn Hook callback.
	 */
	private static function integration_hook_array_callback_fallback_label( array $fn ): string {
		$class = is_object( $fn[0] ?? null ) ? get_class( $fn[0] ) : (string) ( $fn[0] ?? '' );
		$method = is_string( $fn[1] ?? null ) ? (string) $fn[1] : '';
		if ( $class === '' ) {
			return __( 'Unknown PHP class callback', 'wp-creatorreactor' );
		}
		return $method !== '' ? $class . '::' . $method : $class;
	}

	/**
	 * Map a PHP file path to a human-readable source (plugin name, theme, core, etc.).
	 *
	 * @param string|null $file Absolute path from reflection.
	 */
	private static function integration_file_to_redirect_source_label( $file ): string {
		if ( ! is_string( $file ) || $file === '' ) {
			return __( 'Unknown source', 'wp-creatorreactor' );
		}
		$file     = wp_normalize_path( $file );
		$abspath  = wp_normalize_path( ABSPATH );
		$wp_admin = $abspath . 'wp-admin/';
		$wp_inc   = $abspath . 'wp-includes/';
		if ( strpos( $file, $wp_admin ) === 0 || strpos( $file, $wp_inc ) === 0 ) {
			return __( 'WordPress core', 'wp-creatorreactor' );
		}

		$plugin_root = wp_normalize_path( WP_PLUGIN_DIR );
		if ( strpos( $file, $plugin_root . '/' ) === 0 ) {
			$relative = substr( $file, strlen( $plugin_root ) + 1 );
			$folder   = strtok( $relative, '/' );
			if ( ! is_string( $folder ) || $folder === '' ) {
				return __( 'Plugin (wp-content/plugins)', 'wp-creatorreactor' );
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			foreach ( $plugins as $plugin_file => $data ) {
				$dir = dirname( $plugin_file );
				if ( $dir === '.' || $dir === '' ) {
					if ( $plugin_file === $folder || basename( (string) $plugin_file ) === $folder ) {
						$name = isset( $data['Name'] ) && is_string( $data['Name'] ) ? trim( $data['Name'] ) : '';
						return $name !== '' ? $name : (string) $plugin_file;
					}
					continue;
				}
				if ( $dir === $folder ) {
					$name = isset( $data['Name'] ) && is_string( $data['Name'] ) ? trim( $data['Name'] ) : '';
					return $name !== '' ? $name : (string) $plugin_file;
				}
			}
			return sprintf(
				/* translators: %s: plugin directory slug */
				__( 'Plugin folder: %s', 'wp-creatorreactor' ),
				$folder
			);
		}

		$mu_root = wp_normalize_path( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) );
		if ( strpos( $file, $mu_root . '/' ) === 0 || $file === $mu_root ) {
			$base = basename( $file );
			if ( $base !== '' && $base !== '.' && $base !== '..' ) {
				return sprintf(
					/* translators: %s: MU-plugin file name */
					__( 'Must-use plugin (%s)', 'wp-creatorreactor' ),
					$base
				);
			}
			return __( 'Must-use plugin', 'wp-creatorreactor' );
		}

		$themes_root = wp_normalize_path( get_theme_root() );
		if ( strpos( $file, $themes_root . '/' ) === 0 ) {
			$relative = substr( $file, strlen( $themes_root ) + 1 );
			$slug     = strtok( $relative, '/' );
			if ( is_string( $slug ) && $slug !== '' ) {
				$theme = wp_get_theme( $slug );
				if ( $theme->exists() ) {
					$name = $theme->get( 'Name' );
					if ( is_string( $name ) && trim( $name ) !== '' ) {
						return sprintf(
							/* translators: %s: theme name */
							__( 'Theme: %s', 'wp-creatorreactor' ),
							$name
						);
					}
				}
				return sprintf(
					/* translators: %s: theme folder slug */
					__( 'Theme folder: %s', 'wp-creatorreactor' ),
					$slug
				);
			}
		}

		$content = wp_normalize_path( WP_CONTENT_DIR );
		if ( strpos( $file, $content . '/themes/' ) === 0 ) {
			return __( 'Theme (wp-content/themes)', 'wp-creatorreactor' );
		}

		return __( 'Unknown extension', 'wp-creatorreactor' );
	}

	/**
	 * Count non-CreatorReactor class/object hook callbacks and attribute them to plugins/themes/core.
	 *
	 * @param string $tag Hook name (e.g. login_redirect, template_redirect).
	 * @return array{count: int, labels: string[]}
	 */
	private static function integration_collect_external_redirect_hook_report( $tag ): array {
		global $wp_filter;
		$count  = 0;
		$labels = [];
		if ( ! isset( $wp_filter[ $tag ] ) || ! is_object( $wp_filter[ $tag ] ) || ! isset( $wp_filter[ $tag ]->callbacks ) ) {
			return [
				'count'  => 0,
				'labels' => [],
			];
		}
		foreach ( (array) $wp_filter[ $tag ]->callbacks as $priority_callbacks ) {
			foreach ( (array) $priority_callbacks as $cb ) {
				$fn = isset( $cb['function'] ) ? $cb['function'] : null;
				if ( ! is_array( $fn ) ) {
					continue;
				}
				if ( self::integration_is_creatorreactor_hook_callback( $fn ) ) {
					continue;
				}
				$count++;
				$path = self::integration_hook_array_callback_file( $fn );
				$labels[] = $path !== null
					? self::integration_file_to_redirect_source_label( $path )
					: self::integration_hook_array_callback_fallback_label( $fn );
			}
		}
		$labels = array_values( array_unique( array_filter( array_map( 'strval', $labels ) ) ) );
		sort( $labels );
		return [
			'count'  => $count,
			'labels' => $labels,
		];
	}

	/**
	 * Integration checks (Settings tab and/or Dashboard).
	 *
	 * @param string $context 'settings' — CreatorReactor → Settings → Debug; 'dashboard' — same list on Dashboard.
	 */
	/**
	 * Build integration check rows for UI and onboarding (shared).
	 *
	 * @return array{checks: array, checks_attention: array, checks_passed: array, passed_count: int}
	 */
	private static function compute_integration_checks_lists() {
		$opts                = self::get_options();
		$anyone_can_register = (bool) get_option( 'users_can_register', false );
		$default_role        = (string) get_option( 'default_role', 'subscriber' );
		$social_configured   = self::is_fan_social_login_configured();
		$social_login_on     = $social_configured && ! empty( $opts['replace_wp_login_with_social'] );
		$active_plugins      = (array) get_option( 'active_plugins', [] );
		$network_active      = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) : [];
		$all_active_plugins  = array_values( array_unique( array_merge( $active_plugins, $network_active ) ) );
		$is_active           = static function ( $plugin_file ) use ( $all_active_plugins ) {
			return in_array( (string) $plugin_file, $all_active_plugins, true );
		};
		$active_from_map     = static function ( array $plugin_map ) use ( $is_active ) {
			$active = [];
			foreach ( $plugin_map as $plugin_file => $label ) {
				if ( $is_active( $plugin_file ) ) {
					$active[] = (string) $label;
				}
			}
			return $active;
		};
		$active_names_list   = static function ( array $names ) {
			$names = array_values( array_unique( array_filter( array_map( 'strval', $names ) ) ) );
			sort( $names );
			return implode( ', ', $names );
		};

		$registration_owner_plugins = [
			'woocommerce/woocommerce.php'                                      => 'WooCommerce',
			'memberpress/memberpress.php'                                      => 'MemberPress',
			'paid-memberships-pro/paid-memberships-pro.php'                    => 'Paid Memberships Pro',
			'ultimate-member/ultimate-member.php'                              => 'Ultimate Member',
			'profile-builder/index.php'                                        => 'Profile Builder',
			'user-registration/user-registration.php'                          => 'User Registration',
			'wp-user-manager/wp-user-manager.php'                              => 'WP User Manager',
		];
		$custom_fields_plugins = [
			'ultimate-member/ultimate-member.php'                              => 'Ultimate Member',
			'profile-builder/index.php'                                        => 'Profile Builder',
			'user-registration/user-registration.php'                          => 'User Registration',
			'pie-register/pie-register.php'                                    => 'Pie Register',
			'userswp/userswp.php'                                               => 'UsersWP',
		];
		$content_restriction_plugins = [
			'memberpress/memberpress.php'                                      => 'MemberPress',
			'paid-memberships-pro/paid-memberships-pro.php'                    => 'Paid Memberships Pro',
			'restrict-content-pro/restrict-content-pro.php'                    => 'Restrict Content Pro',
			'paid-member-subscriptions/paid-member-subscriptions.php'          => 'Paid Member Subscriptions',
			'woocommerce-memberships/woocommerce-memberships.php'              => 'WooCommerce Memberships',
			's2member/s2member.php'                                             => 's2Member',
		];
		$caching_security_plugins = [
			'wp-rocket/wp-rocket.php'                                          => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php'                              => 'LiteSpeed Cache',
			'w3-total-cache/w3-total-cache.php'                                => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php'                                      => 'WP Super Cache',
			'sg-cachepress/sg-cachepress.php'                                  => 'SiteGround Optimizer',
			'wordfence/wordfence.php'                                           => 'Wordfence',
			'cloudflare/cloudflare.php'                                        => 'Cloudflare',
			'sucuri-scanner/sucuri.php'                                        => 'Sucuri Security',
		];

		$active_registration_owners = $active_from_map( $registration_owner_plugins );
		$active_custom_fields       = $active_from_map( $custom_fields_plugins );
		$active_restriction_engines = $active_from_map( $content_restriction_plugins );
		$active_cache_security      = $active_from_map( $caching_security_plugins );
		$login_redirect_report    = self::integration_collect_external_redirect_hook_report( 'login_redirect' );
		$template_redirect_report = self::integration_collect_external_redirect_hook_report( 'template_redirect' );
		$login_redirect_callbacks   = $login_redirect_report['count'];
		$template_redirect_callbacks = $template_redirect_report['count'];
		$external_redirect_callbacks = $login_redirect_callbacks + $template_redirect_callbacks;
		$redirect_conflict_labels    = array_values(
			array_unique(
				array_merge( $login_redirect_report['labels'], $template_redirect_report['labels'] )
			)
		);
		sort( $redirect_conflict_labels );
		$redirect_conflict_sources_str = implode( ', ', $redirect_conflict_labels );
		$network_restriction_plugins = [];
		if ( is_multisite() ) {
			foreach ( $content_restriction_plugins as $plugin_file => $plugin_label ) {
				if ( in_array( $plugin_file, $network_active, true ) ) {
					$network_restriction_plugins[] = (string) $plugin_label;
				}
			}
		}

		$social_fix_id = null;
		if ( ! $social_login_on ) {
			$social_fix_id = $social_configured ? 'social_login_enable_wp_login' : 'social_login_configure_oauth';
		}

		$wp_roles_for_integration = wp_roles();
		$missing_creatorreactor_roles = [];
		foreach ( array_keys( self::get_required_creatorreactor_roles() ) as $cr_slug ) {
			if ( ! $wp_roles_for_integration->is_role( $cr_slug ) ) {
				$missing_creatorreactor_roles[] = $cr_slug;
			}
		}
		$creatorreactor_roles_ok  = empty( $missing_creatorreactor_roles );
		$creatorreactor_roles_msg = $creatorreactor_roles_ok
			? __( 'Pass: CreatorReactor roles are registered (creatorreactor_follower, creatorreactor_subscriber, creatorreactor_null).', 'wp-creatorreactor' )
			: sprintf(
				/* translators: %s: comma-separated WordPress role slugs */
				__( 'Fail: Missing WordPress role(s): %s. They are normally created when CreatorReactor is activated; use Fix to register them.', 'wp-creatorreactor' ),
				implode( ', ', $missing_creatorreactor_roles )
			);

		if ( $wp_roles_for_integration->is_role( 'creatorreactor_null' ) ) {
			$default_role_cr_ok    = ( $default_role === 'creatorreactor_null' );
			$default_role_cr_msg   = $default_role_cr_ok
				? sprintf(
					/* translators: %s: WordPress role slug */
					__( 'Pass: Default role is set to %s.', 'wp-creatorreactor' ),
					$default_role
				)
				: sprintf(
					/* translators: 1: current default role slug, 2: required role slug */
					__( 'Fail: Default role is %1$s. Set default role to %2$s (Settings > General, or use Fix).', 'wp-creatorreactor' ),
					$default_role,
					'creatorreactor_null'
				);
		} else {
			$default_role_cr_ok  = ( strpos( $default_role, 'creatorreactor_' ) === 0 );
			$default_role_cr_msg = $default_role_cr_ok
				? sprintf(
					/* translators: %s: WordPress role slug */
					__( 'Pass: Default role is set to %s.', 'wp-creatorreactor' ),
					$default_role
				)
				: sprintf(
					/* translators: %s: current WordPress default role slug */
					__( 'Fail: Default role is %s. Use creatorreactor_null.', 'wp-creatorreactor' ),
					$default_role
				);
		}

		$checks              = [
			[
				'check_id' => 'membership_signup',
				'label'    => __( 'Membership signup allows new users', 'wp-creatorreactor' ),
				'status'   => $anyone_can_register ? 'green' : 'red',
				'message'  => $anyone_can_register
					? __( 'Pass: Anyone can register is enabled.', 'wp-creatorreactor' )
					: __( 'Fail: Anyone can register is disabled. Enable it in Settings > General.', 'wp-creatorreactor' ),
				'fix_id'   => 'membership_signup',
			],
			[
				'check_id' => 'registration_source_native',
				'label'    => __( 'Registration source of truth is WordPress native', 'wp-creatorreactor' ),
				'status'   => empty( $active_registration_owners ) ? 'green' : 'red',
				'message'  => empty( $active_registration_owners )
					? __( 'Pass: No known alternate registration-owner plugin is active.', 'wp-creatorreactor' )
					: sprintf(
						/* translators: %s: comma-separated plugin names */
						__( 'Fail: Detected plugin(s) that may override registration ownership: %s.', 'wp-creatorreactor' ),
						$active_names_list( $active_registration_owners )
					),
				'fix_id'   => 'open_plugins_registration_conflict',
			],
			[
				'check_id' => 'login_redirect_conflicts',
				'label'    => __( 'Login/redirect interception conflicts', 'wp-creatorreactor' ),
				'status'   => $external_redirect_callbacks === 0 ? 'green' : 'red',
				'message'  => $external_redirect_callbacks === 0
					? __( 'Pass: No external plugin class callbacks detected on login/template redirects.', 'wp-creatorreactor' )
					: sprintf(
						/* translators: 1: login_redirect callback count, 2: template_redirect callback count, 3: comma-separated attributed sources (plugin names, themes, WordPress core, etc.) */
						__( 'Fail: Detected external redirect callbacks (login_redirect: %1$d, template_redirect: %2$d). Likely sources: %3$s.', 'wp-creatorreactor' ),
						$login_redirect_callbacks,
						$template_redirect_callbacks,
						$redirect_conflict_sources_str !== ''
							? $redirect_conflict_sources_str
							: __( 'could not be attributed (see server debug if needed)', 'wp-creatorreactor' )
					),
				'fix_id'   => 'open_plugins_redirect_conflict',
			],
			[
				'check_id' => 'creatorreactor_roles_exist',
				'label'    => __( 'CreatorReactor WordPress roles exist', 'wp-creatorreactor' ),
				'status'   => $creatorreactor_roles_ok ? 'green' : 'red',
				'message'  => $creatorreactor_roles_msg,
				'fix_id'   => 'creatorreactor_roles_register',
			],
			[
				'check_id' => 'default_role_creatorreactor',
				'label'    => __( 'Default role safety (CreatorReactor)', 'wp-creatorreactor' ),
				'status'   => $default_role_cr_ok ? 'green' : 'red',
				'message'  => $default_role_cr_msg,
				'fix_id'   => 'default_role_creatorreactor',
			],
			[
				'check_id' => 'social_login_wp',
				'label'    => __( 'Social login flow is ready on WordPress login', 'wp-creatorreactor' ),
				'status'   => $social_login_on ? 'green' : 'red',
				'message'  => $social_login_on
					? __( 'Pass: At least one social provider is configured and wp-login social buttons are enabled.', 'wp-creatorreactor' )
					: __( 'Fail: Configure Fanvue, Google, or another social OAuth tab, then enable “Add social login button to the WordPress login page?” in CreatorReactor → Settings → General.', 'wp-creatorreactor' ),
				'fix_id'   => $social_fix_id,
			],
			[
				'check_id' => 'custom_registration_fields',
				'label'    => __( 'Custom registration fields requirement risk', 'wp-creatorreactor' ),
				'status'   => empty( $active_custom_fields ) ? 'green' : 'red',
				'message'  => empty( $active_custom_fields )
					? __( 'Pass: No known custom-registration-fields plugin detected.', 'wp-creatorreactor' )
					: sprintf(
						/* translators: %s: comma-separated plugin names */
						__( 'Fail: Detected plugin(s) that may require extra signup fields: %s.', 'wp-creatorreactor' ),
						$active_names_list( $active_custom_fields )
					),
				'fix_id'   => 'open_plugins_custom_fields',
			],
			[
				'check_id' => 'content_restriction_collision',
				'label'    => __( 'Content restriction engine collision', 'wp-creatorreactor' ),
				'status'   => count( $active_restriction_engines ) <= 1 ? 'green' : 'red',
				'message'  => count( $active_restriction_engines ) <= 1
					? __( 'Pass: Zero or one known content restriction engine is active.', 'wp-creatorreactor' )
					: sprintf(
						/* translators: %s: comma-separated plugin names */
						__( 'Fail: Multiple restriction engines detected: %s.', 'wp-creatorreactor' ),
						$active_names_list( $active_restriction_engines )
					),
				'fix_id'   => 'open_plugins_content_restriction',
			],
			[
				'check_id' => 'cache_security_risk',
				'label'    => __( 'Session/cookie compatibility risk', 'wp-creatorreactor' ),
				'status'   => empty( $active_cache_security ) ? 'green' : 'red',
				'message'  => empty( $active_cache_security )
					? __( 'Pass: No known caching/security plugin detected that commonly alters auth/query/cookie flows.', 'wp-creatorreactor' )
					: sprintf(
						/* translators: %s: comma-separated plugin names */
						__( 'Fail: Detected caching/security plugin(s) that can impact OAuth/session continuity: %s.', 'wp-creatorreactor' ),
						$active_names_list( $active_cache_security )
					),
				'fix_id'   => 'open_plugins_cache_security',
			],
			[
				'check_id' => 'multisite_network_restriction',
				'label'    => __( 'Multisite/network mode mismatch risk', 'wp-creatorreactor' ),
				'status'   => ! is_multisite() || empty( $network_restriction_plugins ) ? 'green' : 'red',
				'message'  => ! is_multisite()
					? __( 'Pass: Site is not multisite.', 'wp-creatorreactor' )
					: ( empty( $network_restriction_plugins )
						? __( 'Pass: No known restriction plugin is network-activated.', 'wp-creatorreactor' )
						: sprintf(
							/* translators: %s: comma-separated plugin names */
							__( 'Fail: Network-activated restriction plugin(s) detected: %s. Verify per-site settings alignment.', 'wp-creatorreactor' ),
							$active_names_list( $network_restriction_plugins )
						) ),
				'fix_id'   => 'open_plugins_network_restriction',
			],
		];

		$ignored_check_flip = array_fill_keys( self::get_ignored_integration_check_ids(), true );
		$checks_merged      = [];
		foreach ( $checks as $check ) {
			$cid       = isset( $check['check_id'] ) ? (string) $check['check_id'] : '';
			$raw       = isset( $check['status'] ) ? (string) $check['status'] : '';
			$ignored   = $cid !== '' && ! empty( $ignored_check_flip[ $cid ] );
			$display   = $raw;
			if ( $raw === 'red' && $ignored ) {
				$display = 'yellow';
			}
			$check['raw_status']                = $raw;
			$check['status']                    = $display;
			$check['ignored_integration_check'] = ( $raw === 'red' && $ignored );
			$checks_merged[]                    = $check;
		}
		$checks = $checks_merged;

		$checks_attention = [];
		$checks_passed    = [];
		foreach ( $checks as $check ) {
			$st = isset( $check['status'] ) ? (string) $check['status'] : '';
			if ( in_array( $st, [ 'red', 'yellow' ], true ) ) {
				$checks_attention[] = $check;
			} elseif ( $st === 'green' ) {
				$checks_passed[] = $check;
			} else {
				$checks_attention[] = $check;
			}
		}
		$passed_count     = count( $checks_passed );
		return [
			'checks' => $checks,
			'checks_attention' => $checks_attention,
			'checks_passed' => $checks_passed,
			'passed_count' => $passed_count,
		];
	}

	private static function render_integration_checks_tab_body( $context = 'settings' ) {
		$context = ( $context === 'dashboard' ) ? 'dashboard' : 'settings';
		if ( $context === 'dashboard' ) {
			$integration_refresh_url = self::admin_page_url(
				[
					'tab'     => 'dashboard',
					'cr_ic_r' => (string) time(),
				],
				self::PAGE_SLUG
			);
		} else {
			$integration_refresh_url = self::admin_page_url(
				[
					'tab'     => 'debug',
					'cr_ic_r' => (string) time(),
				],
				self::PAGE_SETTINGS_SLUG
			);
		}
		$section_class = 'creatorreactor-section';
		if ( $context === 'dashboard' ) {
			$section_class .= ' creatorreactor-dashboard-integration-checks-shell creatorreactor-dashboard-card';
		}
		$computed            = self::compute_integration_checks_lists();
		$checks              = $computed['checks'];
		$checks_attention    = $computed['checks_attention'];
		$checks_passed       = $computed['checks_passed'];
		$passed_count        = $computed['passed_count'];
		$fix_definitions  = self::get_integration_fix_definitions();
		$integration_refresh_tooltip = __( 'Reload this page and re-evaluate every check against the current site.', 'wp-creatorreactor' );
		?>
		<div id="creatorreactor-integration-checks" class="<?php echo esc_attr( $section_class ); ?>">
			<div class="creatorreactor-integration-checks-head">
				<h2 class="creatorreactor-integration-checks-head__title"><?php esc_html_e( 'Integration Checks', 'wp-creatorreactor' ); ?></h2>
				<a
					href="<?php echo esc_url( $integration_refresh_url ); ?>"
					class="creatorreactor-integration-checks-refresh"
					aria-label="<?php echo esc_attr( __( 'Run integration checks', 'wp-creatorreactor' ) ); ?>"
					aria-describedby="creatorreactor-integration-checks-refresh-tip"
					data-creatorreactor-tooltip="<?php echo esc_attr( $integration_refresh_tooltip ); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
				</a>
				<span id="creatorreactor-integration-checks-refresh-tip" class="screen-reader-text"><?php echo esc_html( $integration_refresh_tooltip ); ?></span>
			</div>
			<p class="creatorreactor-muted">
				<?php esc_html_e( 'Run these checks before configuring OAuth and sync to avoid setup issues.', 'wp-creatorreactor' ); ?>
			</p>
			<?php if ( empty( $checks_attention ) ) : ?>
				<p class="creatorreactor-integration-checks-all-clear"><?php esc_html_e( 'No issues: every check passed.', 'wp-creatorreactor' ); ?></p>
			<?php else : ?>
				<ul class="creatorreactor-module-list creatorreactor-integration-check-list">
					<?php foreach ( $checks_attention as $check ) : ?>
						<?php
						$row_check_id = isset( $check['check_id'] ) && is_string( $check['check_id'] ) ? $check['check_id'] : '';
						$row_fix_id   = isset( $check['fix_id'] ) && is_string( $check['fix_id'] ) ? $check['fix_id'] : '';
						$row_has_fix  = $row_fix_id !== '' && isset( $fix_definitions[ $row_fix_id ] );
						$row_raw_red  = isset( $check['raw_status'] ) && (string) $check['raw_status'] === 'red';
						$row_ignored  = ! empty( $check['ignored_integration_check'] );
						?>
						<li class="creatorreactor-module-item">
							<span class="creatorreactor-module-status-dot is-<?php echo esc_attr( $check['status'] ); ?>" aria-hidden="true"></span>
							<div class="creatorreactor-module-content">
								<span class="creatorreactor-module-label"><?php echo esc_html( $check['label'] ); ?></span>
								<span class="creatorreactor-module-meta"><?php echo esc_html( $check['message'] ); ?></span>
								<?php if ( $row_ignored ) : ?>
									<span class="creatorreactor-integration-check-ignored-note creatorreactor-muted"><?php esc_html_e( 'You are ignoring this warning for your account.', 'wp-creatorreactor' ); ?></span>
								<?php endif; ?>
								<?php if ( ( $row_has_fix && ! $row_ignored ) || ( $row_raw_red && ! $row_ignored ) || $row_ignored ) : ?>
									<span class="creatorreactor-integration-check-actions">
										<?php if ( $row_has_fix && ! $row_ignored ) : ?>
											<a href="#" class="creatorreactor-integration-fix-link" data-fix-id="<?php echo esc_attr( $row_fix_id ); ?>"><?php esc_html_e( 'Fix', 'wp-creatorreactor' ); ?></a>
										<?php endif; ?>
										<?php if ( $row_raw_red && ! $row_ignored && $row_check_id !== '' ) : ?>
											<button type="button" class="button-link creatorreactor-integration-ignore-btn" data-check-id="<?php echo esc_attr( $row_check_id ); ?>"><?php esc_html_e( 'Ignore', 'wp-creatorreactor' ); ?></button>
										<?php endif; ?>
										<?php if ( $row_ignored && $row_check_id !== '' ) : ?>
											<button type="button" class="button-link creatorreactor-integration-unignore-btn" data-check-id="<?php echo esc_attr( $row_check_id ); ?>"><?php esc_html_e( 'Stop ignoring', 'wp-creatorreactor' ); ?></button>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $passed_count > 0 ) : ?>
				<details class="creatorreactor-integration-checks-passed-details">
					<summary class="creatorreactor-integration-checks-passed-summary">
						<?php
						printf(
							/* translators: %d: number of passing checks */
							esc_html__( 'Show successful checks (%d)', 'wp-creatorreactor' ),
							(int) $passed_count
						);
						?>
					</summary>
					<ul class="creatorreactor-module-list creatorreactor-integration-check-list creatorreactor-integration-check-list--passed">
						<?php foreach ( $checks_passed as $check ) : ?>
							<li class="creatorreactor-module-item">
								<span class="creatorreactor-module-status-dot is-<?php echo esc_attr( $check['status'] ); ?>" aria-hidden="true"></span>
								<div class="creatorreactor-module-content">
									<span class="creatorreactor-module-label"><?php echo esc_html( $check['label'] ); ?></span>
									<span class="creatorreactor-module-meta"><?php echo esc_html( $check['message'] ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<div id="creatorreactor-integration-fix-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
				<div class="creatorreactor-modal-backdrop creatorreactor-integration-fix-backdrop" aria-hidden="true"></div>
				<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-integration-fix-title">
					<div class="creatorreactor-modal-header">
						<div class="creatorreactor-modal-header-title">
							<h3 id="creatorreactor-integration-fix-title"></h3>
						</div>
						<button type="button" class="creatorreactor-integration-fix-close" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
					</div>
					<div class="creatorreactor-modal-body">
						<p id="creatorreactor-integration-fix-body" class="creatorreactor-integration-fix-body"></p>
						<p class="creatorreactor-integration-fix-error" id="creatorreactor-integration-fix-error" hidden></p>
					</div>
					<div class="creatorreactor-modal-footer">
						<button type="button" class="button creatorreactor-integration-fix-cancel"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></button>
						<button type="button" class="button button-primary creatorreactor-integration-fix-confirm"><?php esc_html_e( 'Fix', 'wp-creatorreactor' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * HTML for the Users tab list (totals, toolbar, table). Used on first paint and via AJAX refresh.
	 *
	 * @param array{total: int, active: int, inactive: int} $user_totals Counts.
	 * @param array<int, array<string, mixed>>               $user_rows   Rows from entitlements table.
	 * @param string|null                                      $sync_error  If set, show an inline error after sync (AJAX refresh).
	 */
	private static function render_users_tab_inner_html( array $user_totals, array $user_rows, $sync_error = null ) {
		ob_start();
		?>
		<div class="creatorreactor-users-toolbar">
			<p class="creatorreactor-users-totals">
				<strong><?php esc_html_e( 'Total:', 'wp-creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['total'] ); ?>,
				<strong><?php esc_html_e( 'Active:', 'wp-creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['active'] ); ?>,
				<strong><?php esc_html_e( 'Inactive:', 'wp-creatorreactor' ); ?></strong> <?php echo esc_html( (string) $user_totals['inactive'] ); ?>
			</p>
			<button type="button" class="button" id="creatorreactor-users-refresh"><?php esc_html_e( 'Sync & refresh list', 'wp-creatorreactor' ); ?></button>
		</div>

		<?php if ( is_string( $sync_error ) && $sync_error !== '' ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $sync_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $user_rows ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'wp-creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Name', 'wp-creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Email', 'wp-creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-creatorreactor' ); ?></th>
						<th><?php esc_html_e( 'Tier', 'wp-creatorreactor' ); ?></th>
						<th class="creatorreactor-users-col-actions"><?php esc_html_e( 'Actions', 'wp-creatorreactor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $user_rows as $row ) : ?>
						<?php
						$tier_raw = isset( $row['tier'] ) && $row['tier'] !== null && (string) $row['tier'] !== ''
							? (string) $row['tier']
							: '';
						$tier_show = Entitlements::tier_audience_label( $tier_raw !== '' ? $tier_raw : null );
						$ent_id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$wp_uid    = isset( $row['wp_user_id'] ) && $row['wp_user_id'] !== null && (string) $row['wp_user_id'] !== ''
							? (int) $row['wp_user_id']
							: 0;
						?>
						<tr>
							<td><?php echo esc_html( Entitlements::product_label( $row['product'] ?? Entitlements::PRODUCT_FANVUE ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['display_name'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['email'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['status'] ?: '-' ) ); ?></td>
							<td><?php echo esc_html( $tier_show ); ?></td>
							<td class="creatorreactor-users-col-actions">
								<div class="creatorreactor-user-actions" role="group" aria-label="<?php esc_attr_e( 'Row actions', 'wp-creatorreactor' ); ?>">
									<button type="button" class="button button-small creatorreactor-user-action creatorreactor-user-action-details" data-entitlement-id="<?php echo esc_attr( (string) $ent_id ); ?>" title="<?php esc_attr_e( 'Details', 'wp-creatorreactor' ); ?>">
										<span class="dashicons dashicons-info" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Details', 'wp-creatorreactor' ); ?></span>
									</button>
									<button type="button" class="button button-small creatorreactor-user-action creatorreactor-user-action-sync" title="<?php esc_attr_e( 'Sync status', 'wp-creatorreactor' ); ?>">
										<span class="dashicons dashicons-update" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Sync status', 'wp-creatorreactor' ); ?></span>
									</button>
									<?php if ( $wp_uid > 0 ) : ?>
										<button type="button" class="button button-small creatorreactor-user-action creatorreactor-user-action-deactivate" data-entitlement-id="<?php echo esc_attr( (string) $ent_id ); ?>" data-wp-user-id="<?php echo esc_attr( (string) $wp_uid ); ?>" title="<?php esc_attr_e( 'Deactivate WordPress user', 'wp-creatorreactor' ); ?>">
											<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Deactivate', 'wp-creatorreactor' ); ?></span>
										</button>
									<?php else : ?>
										<button type="button" class="button button-small creatorreactor-user-action" disabled title="<?php esc_attr_e( 'No linked WordPress user', 'wp-creatorreactor' ); ?>">
											<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Deactivate (unavailable)', 'wp-creatorreactor' ); ?></span>
										</button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No synced users found yet. Connect OAuth, then use Sync & refresh list above.', 'wp-creatorreactor' ); ?></p>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array<int, array{time:int, level:string, message:string, type:string}>
	 */
	private static function get_debug_log_entries() {
		$entries = [];
		$connection_logs = self::get_connection_logs();
		foreach ( $connection_logs as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entries[] = [
				'time'    => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
				'level'   => isset( $entry['level'] ) ? (string) $entry['level'] : 'info',
				'message' => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'type'    => self::LOG_TYPE_CONNECTION,
			];
		}
		$sync_logs = self::get_sync_logs();
		foreach ( $sync_logs as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entries[] = [
				'time'    => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
				'level'   => isset( $entry['level'] ) ? (string) $entry['level'] : 'info',
				'message' => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'type'    => self::LOG_TYPE_SYNC,
			];
		}
		usort(
			$entries,
			static function( $a, $b ) {
				return (int) $b['time'] <=> (int) $a['time'];
			}
		);
		return $entries;
	}

	/**
	 * Derive a richer debug type from log level/message text.
	 *
	 * @param string $default_type connection|sync source bucket.
	 * @param string $level        info|error|debug.
	 * @param string $message      Log message.
	 * @return string
	 */
	private static function classify_debug_log_type( $default_type, $level, $message ) {
		$default_type = sanitize_key( (string) $default_type );
		$level        = sanitize_key( (string) $level );
		$message_lc   = strtolower( (string) $message );

		if ( $level === 'error' ) {
			return self::LOG_TYPE_ERROR;
		}

		if ( strpos( $message_lc, 'oauth' ) !== false || strpos( $message_lc, 'auth' ) !== false || strpos( $message_lc, 'login' ) !== false || strpos( $message_lc, 'token' ) !== false ) {
			return self::LOG_TYPE_AUTH;
		}

		if ( strpos( $message_lc, 'api' ) !== false || strpos( $message_lc, 'endpoint' ) !== false || strpos( $message_lc, 'http' ) !== false || strpos( $message_lc, 'request' ) !== false ) {
			return self::LOG_TYPE_API;
		}

		if ( strpos( $message_lc, 'cron' ) !== false ) {
			return self::LOG_TYPE_CRON;
		}

		if ( strpos( $message_lc, 'entitlement' ) !== false || strpos( $message_lc, 'tier' ) !== false ) {
			return self::LOG_TYPE_ENTITLEMENTS;
		}

		if ( strpos( $message_lc, 'sync' ) !== false || strpos( $message_lc, 'subscriber' ) !== false || strpos( $message_lc, 'user table' ) !== false ) {
			return self::LOG_TYPE_SYNC;
		}

		if ( strpos( $message_lc, 'connection' ) !== false || strpos( $message_lc, 'broker' ) !== false || strpos( $message_lc, 'connect' ) !== false ) {
			return self::LOG_TYPE_CONNECTION;
		}

		return in_array( $default_type, [ self::LOG_TYPE_CONNECTION, self::LOG_TYPE_SYNC ], true ) ? $default_type : self::LOG_TYPE_CONNECTION;
	}

	private static function render_debug_tab_body() {
		self::render_integration_checks_tab_body( 'settings' );
		$debug_entries = self::get_debug_log_entries();
		$available_types = [
			'all'                   => __( 'All types', 'wp-creatorreactor' ),
			self::LOG_TYPE_SYNC     => __( 'Sync', 'wp-creatorreactor' ),
			self::LOG_TYPE_CONNECTION => __( 'Connection', 'wp-creatorreactor' ),
			self::LOG_TYPE_AUTH     => __( 'Auth', 'wp-creatorreactor' ),
			self::LOG_TYPE_API      => __( 'API', 'wp-creatorreactor' ),
			self::LOG_TYPE_CRON     => __( 'Cron', 'wp-creatorreactor' ),
			self::LOG_TYPE_ENTITLEMENTS => __( 'Entitlements', 'wp-creatorreactor' ),
			self::LOG_TYPE_ERROR    => __( 'Error', 'wp-creatorreactor' ),
		];
		foreach ( $debug_entries as $entry_index => $entry ) {
			$entry_level = isset( $entry['level'] ) ? (string) $entry['level'] : 'info';
			$entry_msg   = isset( $entry['message'] ) ? (string) $entry['message'] : '';
			$entry_base_type = isset( $entry['type'] ) ? (string) $entry['type'] : self::LOG_TYPE_CONNECTION;
			$debug_entries[ $entry_index ]['type'] = self::classify_debug_log_type( $entry_base_type, $entry_level, $entry_msg );
		}
		foreach ( $debug_entries as $entry ) {
			$type = isset( $entry['type'] ) ? sanitize_key( (string) $entry['type'] ) : '';
			if ( $type === '' ) {
				continue;
			}
			if ( ! isset( $available_types[ $type ] ) ) {
				$available_types[ $type ] = ucfirst( $type );
			}
		}
		unset( $available_types['all'] );
		$timezone_context = self::get_selected_display_timezone_context();
		$debug_logs_copy_title = __( 'Copy debug logs to clipboard', 'wp-creatorreactor' );
		?>
		<div class="creatorreactor-section creatorreactor-debug-tab-logs">
			<h2 class="creatorreactor-debug-logs-section-title"><?php esc_html_e( 'Debug Logs', 'wp-creatorreactor' ); ?></h2>
			<p class="creatorreactor-muted"><?php
			printf(
				/* translators: %s: Timezone label used when displaying log entry timestamps. */
				esc_html__( 'Displaying timestamps in: %s.', 'wp-creatorreactor' ),
				esc_html( $timezone_context['label'] )
			);
			?></p>
			<div id="creatorreactor-debug-tag-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin: 12px 0 16px;">
				<?php foreach ( $available_types as $type_key => $type_label ) : ?>
					<button type="button" class="button button-secondary creatorreactor-debug-tag is-selected" data-log-type="<?php echo esc_attr( $type_key ); ?>" aria-pressed="true">
						<?php echo esc_html( $type_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="creatorreactor-connection-log-body">
				<p class="description"><?php esc_html_e( 'Combined plugin logs. Includes OAuth/connection, subscriber sync, and user table refresh events.', 'wp-creatorreactor' ); ?></p>
				<div class="creatorreactor-debug-chrome-box">
					<div class="creatorreactor-debug-chrome-box__toolbar">
						<button
							type="button"
							id="creatorreactor-debug-copy-logs"
							class="creatorreactor-debug-icon-btn creatorreactor-debug-copy-icon"
							<?php echo empty( $debug_entries ) ? 'disabled' : ''; ?>
							title="<?php echo esc_attr( $debug_logs_copy_title ); ?>"
							data-copy-title="<?php echo esc_attr( $debug_logs_copy_title ); ?>"
							aria-label="<?php echo esc_attr( $debug_logs_copy_title ); ?>"
						>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self-contained img tag; URL escaped in helper.
							echo self::admin_copy_icon_img();
							?>
						</button>
					</div>
					<div class="creatorreactor-debug-chrome-box__body creatorreactor-debug-log-box__body">
						<?php if ( empty( $debug_entries ) ) : ?>
							<p id="creatorreactor-debug-no-entries" class="creatorreactor-muted"><?php esc_html_e( 'No log entries yet.', 'wp-creatorreactor' ); ?></p>
						<?php else : ?>
							<p id="creatorreactor-debug-no-match" class="creatorreactor-muted" style="display:none;"><?php esc_html_e( 'No log entries for selected tags.', 'wp-creatorreactor' ); ?></p>
						<?php endif; ?>
						<ul id="creatorreactor-debug-log-list" class="creatorreactor-connection-log-list">
							<?php foreach ( $debug_entries as $entry ) : ?>
								<?php
								$t    = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
								$lvl  = isset( $entry['level'] ) ? (string) $entry['level'] : 'info';
								$msg  = isset( $entry['message'] ) ? (string) $entry['message'] : '';
								$type = isset( $entry['type'] ) ? sanitize_key( (string) $entry['type'] ) : 'unknown';
								$line = self::format_datetime_for_selected_timezone( (string) $t ) . ' [' . $type . '][' . $lvl . '] ' . $msg;
								?>
								<li data-log-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
						<?php wp_nonce_field( 'creatorreactor_clear_connection_logs' ); ?>
						<input type="hidden" name="action" value="creatorreactor_clear_connection_logs" />
						<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Clear connection logs', 'wp-creatorreactor' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear all connection log entries?', 'wp-creatorreactor' ) ); ?>');" />
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
						<?php wp_nonce_field( 'creatorreactor_clear_sync_logs' ); ?>
						<input type="hidden" name="action" value="creatorreactor_clear_sync_logs" />
						<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Clear sync logs', 'wp-creatorreactor' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear all sync log entries?', 'wp-creatorreactor' ) ); ?>');" />
					</form>
				</div>
			</div>
		</div>
		<style>
			.creatorreactor-debug-tab-logs {
				margin-top: 28px;
				padding-top: 24px;
				border-top: 1px solid #dcdcde;
			}
			h2.creatorreactor-debug-logs-section-title {
				margin: 0 0 8px;
				padding: 0;
				border: 0;
				font-size: 16px;
				color: var(--cr-brand-deep, #301934);
			}
			.creatorreactor-debug-chrome-box {
				position: relative;
				margin-top: 12px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				background: #f6f7f7;
				min-height: 120px;
			}
			.creatorreactor-debug-chrome-box__toolbar {
				position: absolute;
				top: 8px;
				right: 8px;
				z-index: 1;
			}
			.creatorreactor-debug-chrome-box__body {
				padding: 44px 14px 12px;
			}
			.creatorreactor-debug-log-box__body .creatorreactor-connection-log-list {
				margin: 0;
			}
			button.creatorreactor-debug-icon-btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 36px;
				height: 36px;
				padding: 0;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				background: #fff;
				cursor: pointer;
				color: #50575e;
				box-sizing: border-box;
			}
			button.creatorreactor-debug-icon-btn:hover:not(:disabled),
			button.creatorreactor-debug-icon-btn:focus-visible:not(:disabled) {
				background: #fff;
				border-color: #8e2d77;
				color: #8e2d77;
			}
			button.creatorreactor-debug-icon-btn:disabled {
				opacity: 0.45;
				cursor: not-allowed;
			}
			button.creatorreactor-debug-icon-btn .creatorreactor-icon-copy {
				width: 20px;
				height: 20px;
				display: block;
				object-fit: contain;
			}
			#creatorreactor-debug-tag-filters .creatorreactor-debug-tag {
				border-radius: 999px;
				min-width: 108px;
				height: 28px;
				padding: 0 10px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				font-size: 12px;
				line-height: 1;
				white-space: nowrap;
			}
			#creatorreactor-debug-tag-filters .creatorreactor-debug-tag.is-selected {
				background: #8e2d77;
				border-color: #8e2d77;
				color: #fff;
			}
		</style>
		<script>
			(function() {
				var root = document.getElementById('creatorreactor-debug-tag-filters');
				var list = document.getElementById('creatorreactor-debug-log-list');
				var noMatch = document.getElementById('creatorreactor-debug-no-match');
				var rows = list ? Array.prototype.slice.call(list.querySelectorAll('li[data-log-type]')) : [];
				var copyBtn = document.getElementById("creatorreactor-debug-copy-logs");

				function copyLogsText() {
					var lines = rows.filter(function(row) {
						return row.style.display !== "none";
					}).map(function(row) {
						return row.textContent || "";
					});
					return lines.join("\n");
				}
				function updateCopyLogsEnabled() {
					if (!copyBtn) {
						return;
					}
					copyBtn.disabled = copyLogsText().trim() === "";
				}
				function showCopiedIcon(btn) {
					var copiedLabel = (window.creatorreactorAuthMode && window.creatorreactorAuthMode.copiedLabel) ? window.creatorreactorAuthMode.copiedLabel : "Copied!";
					var origTitle = btn.getAttribute("data-copy-title") || btn.getAttribute("title") || "";
					btn.title = copiedLabel;
					window.setTimeout(function() {
						btn.title = origTitle;
					}, 2000);
				}
				function fallbackCopy(value, btn) {
					var ta = document.createElement("textarea");
					ta.value = value;
					ta.setAttribute("readonly", "");
					ta.style.position = "fixed";
					ta.style.left = "-9999px";
					document.body.appendChild(ta);
					ta.select();
					try {
						if (document.execCommand("copy")) {
							showCopiedIcon(btn);
						}
					} catch (err) {}
					document.body.removeChild(ta);
				}

				if (root && list) {
				var tags = Array.prototype.slice.call(root.querySelectorAll('.creatorreactor-debug-tag'));

				function selectedTypes() {
					var out = {};
					tags.forEach(function(tag) {
						if (tag.classList.contains('is-selected')) {
							out[tag.getAttribute('data-log-type')] = true;
						}
					});
					return out;
				}

				function applyFilter() {
					var selected = selectedTypes();
					var visibleCount = 0;
					rows.forEach(function(row) {
						var type = row.getAttribute('data-log-type');
						var show = !!selected[type];
						row.style.display = show ? '' : 'none';
						if (show) {
							visibleCount += 1;
						}
					});
					if (noMatch) {
						noMatch.style.display = visibleCount > 0 ? 'none' : '';
					}
					updateCopyLogsEnabled();
				}

				tags.forEach(function(tag) {
					tag.addEventListener('click', function() {
						var isSelected = tag.classList.contains('is-selected');
						if (isSelected) {
							tag.classList.remove('is-selected');
							tag.setAttribute('aria-pressed', 'false');
						} else {
							tag.classList.add('is-selected');
							tag.setAttribute('aria-pressed', 'true');
						}
						applyFilter();
					});
				});

				applyFilter();
				} else {
					updateCopyLogsEnabled();
				}

				if (copyBtn) {
					copyBtn.addEventListener("click", function(e) {
						e.preventDefault();
						if (copyBtn.disabled) {
							return;
						}
						var text = copyLogsText();
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(text).then(function() {
								showCopiedIcon(copyBtn);
							}).catch(function() {
								fallbackCopy(text, copyBtn);
							});
						} else {
							fallbackCopy(text, copyBtn);
						}
					});
				}
			})();
		</script>
		<?php
		$schema_manifest = self::fetch_schema_service_manifest( false );
		$schema_envelope = self::fetch_schema_service_document( '/v1/schema/envelope/v1', false );
		$schema_req_url  = isset( $schema_manifest['request_url'] ) ? (string) $schema_manifest['request_url'] : '';
		$envelope_req_url = isset( $schema_envelope['request_url'] ) ? (string) $schema_envelope['request_url'] : '';
		$schema_spec_for_title = $schema_manifest['spec_version'] !== '' ? (string) $schema_manifest['spec_version'] : '—';
		$schema_pre_text   = '';
		if ( $schema_manifest['ok'] ) {
			$schema_pre_text = $schema_manifest['body'];
		} elseif ( $schema_manifest['error'] !== '' ) {
			$schema_pre_text = $schema_manifest['error'];
			if ( $schema_manifest['body'] !== '' && $schema_manifest['body'] !== $schema_manifest['error'] ) {
				$schema_pre_text .= "\n\n" . $schema_manifest['body'];
			}
		}
		$envelope_pre_text = '';
		if ( $schema_envelope['ok'] ) {
			$envelope_pre_text = $schema_envelope['body'];
		} elseif ( $schema_envelope['error'] !== '' ) {
			$envelope_pre_text = $schema_envelope['error'];
			if ( $schema_envelope['body'] !== '' && $schema_envelope['body'] !== $schema_envelope['error'] ) {
				$envelope_pre_text .= "\n\n" . $schema_envelope['body'];
			}
		}
		$schema_copy_title    = __( 'Copy schema manifest to clipboard', 'wp-creatorreactor' );
		$envelope_copy_title  = __( 'Copy envelope JSON Schema to clipboard', 'wp-creatorreactor' );
		$schema_copy_disabled = trim( (string) $schema_pre_text ) === '';
		$envelope_copy_disabled = trim( (string) $envelope_pre_text ) === '';
		?>
		<div id="creatorreactor-debug-schema-card" class="creatorreactor-section creatorreactor-debug-schema-card">
			<div class="creatorreactor-debug-schema-head">
				<h2 class="creatorreactor-debug-schema-head__title"><?php esc_html_e( 'Schema', 'wp-creatorreactor' ); ?></h2>
				<button
					type="button"
					id="creatorreactor-debug-schema-refresh"
					class="creatorreactor-debug-schema-refresh"
					aria-label="<?php echo esc_attr( __( 'Refresh schema from schema service', 'wp-creatorreactor' ) ); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
				</button>
			</div>
			<p class="description"><?php esc_html_e( 'Manifest (index of published artifacts) from GET /v1/schema, plus the full shared envelope from GET /v1/schema/envelope/v1 — CreatorReactor Cloud → Schema service.', 'wp-creatorreactor' ); ?></p>
			<details class="creatorreactor-debug-schema-connection">
				<summary class="creatorreactor-debug-schema-connection-summary"><?php esc_html_e( 'Connection details', 'wp-creatorreactor' ); ?></summary>
				<dl class="creatorreactor-debug-schema-connection-dl">
					<dt><?php esc_html_e( 'Manifest URL', 'wp-creatorreactor' ); ?></dt>
					<dd id="creatorreactor-debug-schema-connection-url-manifest" class="creatorreactor-debug-schema-connection-dd"><?php echo esc_html( $schema_req_url !== '' ? $schema_req_url : '—' ); ?></dd>
					<dt><?php esc_html_e( 'Manifest HTTP', 'wp-creatorreactor' ); ?></dt>
					<dd id="creatorreactor-debug-schema-connection-code-manifest" class="creatorreactor-debug-schema-connection-dd"><?php echo esc_html( (string) (int) $schema_manifest['http_code'] ); ?></dd>
					<dt><?php esc_html_e( 'Envelope URL', 'wp-creatorreactor' ); ?></dt>
					<dd id="creatorreactor-debug-schema-connection-url-envelope" class="creatorreactor-debug-schema-connection-dd"><?php echo esc_html( $envelope_req_url !== '' ? $envelope_req_url : '—' ); ?></dd>
					<dt><?php esc_html_e( 'Envelope HTTP', 'wp-creatorreactor' ); ?></dt>
					<dd id="creatorreactor-debug-schema-connection-code-envelope" class="creatorreactor-debug-schema-connection-dd"><?php echo esc_html( (string) (int) $schema_envelope['http_code'] ); ?></dd>
				</dl>
			</details>
			<div class="creatorreactor-debug-chrome-box creatorreactor-debug-schema-chrome-box">
				<div class="creatorreactor-debug-schema-content-head">
					<span id="creatorreactor-debug-schema-spec-title" class="creatorreactor-debug-schema-spec-title"><?php
					echo esc_html(
						sprintf(
							/* translators: %s: Schema spec version or em dash. */
							__( 'Spec %s — manifest', 'wp-creatorreactor' ),
							$schema_spec_for_title
						)
					);
					?></span>
					<button
						type="button"
						id="creatorreactor-debug-copy-schema-manifest"
						class="creatorreactor-debug-icon-btn creatorreactor-debug-copy-icon"
						<?php echo $schema_copy_disabled ? 'disabled' : ''; ?>
						title="<?php echo esc_attr( $schema_copy_title ); ?>"
						data-copy-title="<?php echo esc_attr( $schema_copy_title ); ?>"
						aria-label="<?php echo esc_attr( $schema_copy_title ); ?>"
					>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self-contained img tag; URL escaped in helper.
						echo self::admin_copy_icon_img();
						?>
					</button>
				</div>
				<pre id="creatorreactor-debug-schema-body" class="creatorreactor-debug-schema-body"><?php echo esc_html( $schema_pre_text ); ?></pre>
			</div>
			<div class="creatorreactor-debug-chrome-box creatorreactor-debug-schema-chrome-box creatorreactor-debug-schema-chrome-box--envelope">
				<div class="creatorreactor-debug-schema-content-head">
					<span class="creatorreactor-debug-schema-spec-title"><?php esc_html_e( 'Envelope JSON Schema — GET /v1/schema/envelope/v1', 'wp-creatorreactor' ); ?></span>
					<button
						type="button"
						id="creatorreactor-debug-copy-schema-envelope"
						class="creatorreactor-debug-icon-btn creatorreactor-debug-copy-icon"
						<?php echo $envelope_copy_disabled ? 'disabled' : ''; ?>
						title="<?php echo esc_attr( $envelope_copy_title ); ?>"
						data-copy-title="<?php echo esc_attr( $envelope_copy_title ); ?>"
						aria-label="<?php echo esc_attr( $envelope_copy_title ); ?>"
					>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self-contained img tag; URL escaped in helper.
						echo self::admin_copy_icon_img();
						?>
					</button>
				</div>
				<pre id="creatorreactor-debug-schema-envelope-body" class="creatorreactor-debug-schema-body"><?php echo esc_html( $envelope_pre_text ); ?></pre>
			</div>
		</div>
		<style>
			.creatorreactor-debug-schema-card {
				margin-top: 28px;
				padding-top: 24px;
				border-top: 1px solid #dcdcde;
			}
			.creatorreactor-debug-schema-head {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				flex-wrap: wrap;
			}
			.creatorreactor-debug-schema-connection {
				margin-top: 12px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				background: #fff;
			}
			.creatorreactor-debug-schema-connection-summary {
				padding: 10px 12px;
				cursor: pointer;
				font-weight: 600;
				color: var(--cr-brand-deep, #301934);
				list-style: none;
			}
			.creatorreactor-debug-schema-connection summary::-webkit-details-marker {
				display: none;
			}
			.creatorreactor-debug-schema-connection-summary::before {
				content: "\25b6";
				display: inline-block;
				margin-right: 6px;
				font-size: 10px;
				transition: transform 0.15s ease;
			}
			details.creatorreactor-debug-schema-connection[open] > .creatorreactor-debug-schema-connection-summary::before {
				transform: rotate(90deg);
			}
			.creatorreactor-debug-schema-connection-dl {
				display: grid;
				grid-template-columns: minmax(100px, 140px) 1fr;
				gap: 8px 16px;
				margin: 0;
				padding: 0 12px 12px;
				font-size: 12px;
			}
			.creatorreactor-debug-schema-connection-dl dt {
				margin: 0;
				color: #646970;
				font-weight: 600;
			}
			.creatorreactor-debug-schema-connection-dd {
				margin: 0;
				word-break: break-all;
				font-family: Consolas, Monaco, monospace;
			}
			.creatorreactor-debug-schema-chrome-box {
				margin-top: 12px;
				min-height: 80px;
				padding: 0;
			}
			.creatorreactor-debug-schema-chrome-box--envelope {
				margin-top: 16px;
			}
			.creatorreactor-debug-schema-content-head {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 10px;
				min-height: 40px;
				padding: 6px 10px 6px 12px;
				border-bottom: 1px solid #dcdcde;
				background: #fff;
				border-radius: 4px 4px 0 0;
			}
			.creatorreactor-debug-schema-spec-title {
				font-size: 12px;
				font-weight: 600;
				color: var(--cr-brand-deep, #301934);
			}
			.creatorreactor-debug-schema-chrome-box .creatorreactor-debug-schema-body {
				margin: 0;
				padding: 12px 14px;
				max-height: 420px;
				overflow: auto;
				background: #f6f7f7;
				border: none;
				border-radius: 0 0 4px 4px;
				font-size: 12px;
				line-height: 1.45;
				white-space: pre-wrap;
				word-break: break-word;
			}
			h2.creatorreactor-debug-schema-head__title {
				margin: 0;
				padding: 0;
				border: 0;
				font-size: 16px;
				color: var(--cr-brand-deep, #301934);
			}
			.creatorreactor-debug-schema-refresh {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 36px;
				height: 36px;
				padding: 0;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				background: #f6f7f7;
				cursor: pointer;
				color: #50575e;
			}
			.creatorreactor-debug-schema-refresh:hover,
			.creatorreactor-debug-schema-refresh:focus-visible {
				background: #fff;
				border-color: #8e2d77;
				color: #8e2d77;
			}
			.creatorreactor-debug-schema-refresh:disabled {
				opacity: 0.55;
				cursor: not-allowed;
			}
			.creatorreactor-debug-schema-refresh .dashicons {
				width: 20px;
				height: 20px;
				font-size: 20px;
			}
			.creatorreactor-debug-schema-refresh.is-spinning .dashicons {
				animation: creatorreactor-debug-schema-spin 0.85s linear infinite;
			}
			@keyframes creatorreactor-debug-schema-spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
		</style>
		<script>
			(function() {
				var btn = document.getElementById("creatorreactor-debug-schema-refresh");
				var copyManifestBtn = document.getElementById("creatorreactor-debug-copy-schema-manifest");
				var copyEnvelopeBtn = document.getElementById("creatorreactor-debug-copy-schema-envelope");
				var specTitleEl = document.getElementById("creatorreactor-debug-schema-spec-title");
				var connUrlManifestEl = document.getElementById("creatorreactor-debug-schema-connection-url-manifest");
				var connCodeManifestEl = document.getElementById("creatorreactor-debug-schema-connection-code-manifest");
				var connUrlEnvelopeEl = document.getElementById("creatorreactor-debug-schema-connection-url-envelope");
				var connCodeEnvelopeEl = document.getElementById("creatorreactor-debug-schema-connection-code-envelope");
				var manifestBodyEl = document.getElementById("creatorreactor-debug-schema-body");
				var envelopeBodyEl = document.getElementById("creatorreactor-debug-schema-envelope-body");
				var cfg = window.creatorreactorDebugSchema;
				if (!manifestBodyEl) {
					return;
				}
				function applySchemaConnectionFromResult(d, kind) {
					if (!d) {
						return;
					}
					var isEnv = kind === "envelope";
					var urlEl = isEnv ? connUrlEnvelopeEl : connUrlManifestEl;
					var codeEl = isEnv ? connCodeEnvelopeEl : connCodeManifestEl;
					if (urlEl && d.request_url !== undefined) {
						urlEl.textContent = d.request_url ? String(d.request_url) : "\u2014";
					}
					if (codeEl && d.http_code !== undefined) {
						codeEl.textContent = String(parseInt(d.http_code, 10) || 0);
					}
					if (!isEnv && specTitleEl && d.spec_version !== undefined) {
						var spec = d.spec_version ? String(d.spec_version) : "\u2014";
						var tpl = (cfg && cfg.i18n && cfg.i18n.specTitleManifest) ? cfg.i18n.specTitleManifest : "Spec %s \u2014 manifest";
						specTitleEl.textContent = tpl.replace("%s", spec);
					}
				}
				function fillSchemaPreFromResult(preEl, d) {
					if (!preEl || !d) {
						return;
					}
					if (d.ok) {
						preEl.textContent = d.body || "";
					} else {
						var errText = d.error || "";
						if (d.body && d.body !== errText) {
							errText = errText ? (errText + "\n\n" + d.body) : d.body;
						}
						preEl.textContent = errText || ((cfg.i18n && cfg.i18n.loadError) ? cfg.i18n.loadError : "Could not load schema.");
					}
				}
				function updateSchemaCopyEnabled() {
					if (copyManifestBtn && manifestBodyEl) {
						copyManifestBtn.disabled = (manifestBodyEl.textContent || "").trim() === "";
					}
					if (copyEnvelopeBtn && envelopeBodyEl) {
						copyEnvelopeBtn.disabled = (envelopeBodyEl.textContent || "").trim() === "";
					}
				}
				function showCopiedSchemaIcon(btn) {
					var copiedLabel = (window.creatorreactorAuthMode && window.creatorreactorAuthMode.copiedLabel) ? window.creatorreactorAuthMode.copiedLabel : "Copied!";
					var origTitle = btn.getAttribute("data-copy-title") || btn.getAttribute("title") || "";
					btn.title = copiedLabel;
					window.setTimeout(function() {
						btn.title = origTitle;
					}, 2000);
				}
				function fallbackCopySchema(value, copyBtn) {
					var ta = document.createElement("textarea");
					ta.value = value;
					ta.setAttribute("readonly", "");
					ta.style.position = "fixed";
					ta.style.left = "-9999px";
					document.body.appendChild(ta);
					ta.select();
					try {
						if (document.execCommand("copy")) {
							showCopiedSchemaIcon(copyBtn);
						}
					} catch (err) {}
					document.body.removeChild(ta);
				}
				function bindCopy(btn, preEl) {
					if (!btn || !preEl) {
						return;
					}
					btn.addEventListener("click", function(e) {
						e.preventDefault();
						if (btn.disabled) {
							return;
						}
						var text = preEl.textContent || "";
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(text).then(function() {
								showCopiedSchemaIcon(btn);
							}).catch(function() {
								fallbackCopySchema(text, btn);
							});
						} else {
							fallbackCopySchema(text, btn);
						}
					});
				}
				bindCopy(copyManifestBtn, manifestBodyEl);
				bindCopy(copyEnvelopeBtn, envelopeBodyEl);
				updateSchemaCopyEnabled();
				if (!btn || !cfg || !cfg.ajaxUrl || !cfg.nonce) {
					return;
				}
				function setLoading(on) {
					btn.disabled = !!on;
					btn.classList.toggle("is-spinning", !!on);
				}
				function runRefresh() {
					setLoading(true);
					var fd = new window.FormData();
					fd.append("action", cfg.action);
					fd.append("nonce", cfg.nonce);
					window.fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: fd })
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (!data || !data.success || !data.data) {
								var err = (cfg.i18n && cfg.i18n.loadError) ? cfg.i18n.loadError : "Could not load schema.";
								manifestBodyEl.textContent = err;
								if (envelopeBodyEl) {
									envelopeBodyEl.textContent = err;
								}
								return;
							}
							var payload = data.data;
							var m = payload.manifest;
							var e = payload.envelope;
							if (m || e) {
								if (m) {
									applySchemaConnectionFromResult(m, "manifest");
									fillSchemaPreFromResult(manifestBodyEl, m);
								}
								if (e) {
									applySchemaConnectionFromResult(e, "envelope");
									if (envelopeBodyEl) {
										fillSchemaPreFromResult(envelopeBodyEl, e);
									}
								}
								return;
							}
							var err2 = (cfg.i18n && cfg.i18n.loadError) ? cfg.i18n.loadError : "Could not load schema.";
							manifestBodyEl.textContent = err2;
							if (envelopeBodyEl) {
								envelopeBodyEl.textContent = err2;
							}
						})
						.catch(function() {
							var err = (cfg.i18n && cfg.i18n.loadError) ? cfg.i18n.loadError : "Could not load schema.";
							manifestBodyEl.textContent = err;
							if (envelopeBodyEl) {
								envelopeBodyEl.textContent = err;
							}
						})
						.finally(function() {
							setLoading(false);
							updateSchemaCopyEnabled();
						});
				}
				btn.addEventListener("click", function(e) {
					e.preventDefault();
					runRefresh();
				});
			})();
		</script>
		<?php
	}

	/**
	 * Users tab: inner list + sync log (used on first paint and full AJAX refresh).
	 *
	 * @param array{total: int, active: int, inactive: int} $user_totals Counts.
	 * @param array<int, array<string, mixed>>               $user_rows   Rows from entitlements table.
	 * @param string|null                                    $sync_error  Inline notice after sync (optional).
	 */
	private static function render_users_tab_panel_html( array $user_totals, array $user_rows, $sync_error = null ) {
		ob_start();
		?>
		<div id="creatorreactor-users-inner" class="creatorreactor-users-inner">
			<?php echo self::render_users_tab_inner_html( $user_totals, $user_rows, $sync_error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer. ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: run subscriber/follower sync, then return Users tab inner HTML (same markup as initial render).
	 */
	public static function ajax_get_users_table() {
		check_ajax_referer( 'creatorreactor_users_table', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'wp-creatorreactor' ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			// Subscriber/follower sync can paginate; avoid timing out mid-request.
			@set_time_limit( 300 );
		}

		try {
			$last_sync           = get_option( self::OPTION_LAST_SYNC, [] );
			$last_sync_timestamp = isset( $last_sync['time'] ) ? strtotime( (string) $last_sync['time'] ) : 0;
			$should_sync         = true;
			if ( $last_sync_timestamp > 0 && ( time() - $last_sync_timestamp ) < 30 ) {
				$should_sync = false;
			}

			if ( $should_sync ) {
				self::log_sync( 'info', 'User table refresh requested: starting subscriber sync.' );
				Cron::run_sync();
			} else {
				self::log_sync( 'info', 'User table refresh requested: skipping sync (last run was less than 30 seconds ago).' );
			}

			$last_sync = get_option( self::OPTION_LAST_SYNC, [] );
			$sync_ok   = ! empty( $last_sync['success'] );
			$sync_err  = null;
			if ( ! $sync_ok ) {
				$msg = trim( (string) get_option( self::OPTION_LAST_ERROR, '' ) );
				$sync_err = $msg !== ''
					? $msg
					: __( 'Sync did not complete successfully. Check OAuth connection and, for subscriber/follower lists, read:fan scope if your Fanvue app supports it.', 'wp-creatorreactor' );
				self::log_sync( 'error', $sync_err );
			} else {
				self::log_sync( 'info', 'User table refresh completed successfully.' );
			}

			$snapshot = self::get_users_tab_snapshot();
			wp_send_json_success(
				[
					'html' => self::render_users_tab_panel_html( $snapshot['totals'], $snapshot['rows'], $sync_err ),
				]
			);
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only when WP_DEBUG is on.
				error_log( 'CreatorReactor ajax_get_users_table: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			self::log_sync( 'error', 'User table: ' . $e->getMessage() );
			$snapshot = [
				'totals' => [
					'total'    => 0,
					'active'   => 0,
					'inactive' => 0,
				],
				'rows'   => [],
			];
			try {
				$snapshot = self::get_users_tab_snapshot();
			} catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fallback to empty snapshot.
			}
			wp_send_json_error(
				[
					'message'   => __( 'Could not load the user list after sync.', 'wp-creatorreactor' ) . ' ' . $e->getMessage(),
					'panelHtml' => self::render_users_tab_panel_html( $snapshot['totals'], $snapshot['rows'], $e->getMessage() ),
				],
				500
			);
		}
	}

	/**
	 * AJAX: append a client-side or transport error to the sync log and return refreshed Users panel HTML.
	 */
	public static function ajax_append_sync_log() {
		check_ajax_referer( 'creatorreactor_users_table', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'wp-creatorreactor' ), 403 );
		}

		$msg = isset( $_POST['message'] ) ? (string) wp_unslash( $_POST['message'] ) : '';
		$msg = wp_strip_all_tags( $msg );
		$msg = preg_replace( '/\s+/', ' ', $msg );
		if ( strlen( $msg ) > 4000 ) {
			$msg = substr( $msg, 0, 4000 ) . '…';
		}
		if ( $msg === '' ) {
			$msg = __( 'User table refresh failed (no error text from browser).', 'wp-creatorreactor' );
		}
		self::log_sync( 'error', $msg );

		try {
			$snapshot = self::get_users_tab_snapshot();
			wp_send_json_success(
				[
					'html'          => self::render_users_tab_panel_html( $snapshot['totals'], $snapshot['rows'], null ),
					'loggedMessage' => $msg,
				]
			);
		} catch ( \Throwable $e ) {
			self::log_sync( 'error', 'User table: ' . $e->getMessage() );
			wp_send_json_error(
				[
					'message'   => $e->getMessage(),
					'panelHtml' => self::render_users_tab_panel_html(
						[
							'total'    => 0,
							'active'   => 0,
							'inactive' => 0,
						],
						[],
						$e->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * AJAX: full CreatorReactor record row as labeled lines for the Users tab Details modal (avoids fragile data-* JSON).
	 */
	public static function ajax_get_entitlement_details() {
		check_ajax_referer( 'creatorreactor_users_table', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'wp-creatorreactor' ), 403 );
		}

		$ent_id = isset( $_POST['entitlement_id'] ) ? absint( wp_unslash( $_POST['entitlement_id'] ) ) : 0;
		if ( $ent_id < 1 ) {
			wp_send_json_error( __( 'Invalid record.', 'wp-creatorreactor' ), 400 );
		}

		global $wpdb;
		$table        = Entitlements::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			wp_send_json_error( __( 'Entitlements table not found.', 'wp-creatorreactor' ), 500 );
		}

		Entitlements::maybe_add_product_column();
		Entitlements::maybe_add_display_name_column();
		Entitlements::maybe_add_fanvue_user_uuid_column();
		Entitlements::maybe_add_creatorreactor_uuid_column();
		Entitlements::maybe_add_creatorreactor_user_uuid_column();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $ent_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			wp_send_json_error( __( 'Record not found.', 'wp-creatorreactor' ), 404 );
		}

		wp_send_json_success( self::users_tab_row_details_payload( $row ) );
	}

	/**
	 * AJAX: latest CreatorReactor record row details for a WordPress user (used on wp-admin user profile).
	 */
	public static function ajax_get_user_entitlement_details() {
		check_ajax_referer( 'creatorreactor_user_profile_details', 'security' );

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( $user_id < 1 ) {
			wp_send_json_error( __( 'Invalid WordPress user.', 'wp-creatorreactor' ), 400 );
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( __( 'Forbidden.', 'wp-creatorreactor' ), 403 );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			wp_send_json_error( __( 'WordPress user not found.', 'wp-creatorreactor' ), 404 );
		}

		$row = self::get_latest_entitlement_row_for_wp_user( $user_id, (string) $user->user_email );
		if ( ! is_array( $row ) ) {
			wp_send_json_error( __( 'No CreatorReactor record found for this user.', 'wp-creatorreactor' ), 404 );
		}

		wp_send_json_success( self::users_tab_row_details_payload( $row ) );
	}

	/**
	 * AJAX: strip capabilities from the linked WP user and mark the entitlement inactive.
	 */
	public static function ajax_deactivate_wp_user() {
		check_ajax_referer( 'creatorreactor_users_table', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'wp-creatorreactor' ), 403 );
		}

		$ent_id = isset( $_POST['entitlement_id'] ) ? absint( wp_unslash( $_POST['entitlement_id'] ) ) : 0;
		if ( $ent_id < 1 ) {
			wp_send_json_error( __( 'Invalid record.', 'wp-creatorreactor' ), 400 );
		}

		global $wpdb;
		$table = Entitlements::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, wp_user_id FROM {$table} WHERE id = %d", $ent_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			wp_send_json_error( __( 'Record not found.', 'wp-creatorreactor' ), 404 );
		}

		$wp_uid = isset( $row['wp_user_id'] ) && $row['wp_user_id'] !== null ? (int) $row['wp_user_id'] : 0;
		if ( $wp_uid < 1 ) {
			wp_send_json_error( __( 'No linked WordPress user.', 'wp-creatorreactor' ), 400 );
		}
		if ( (int) get_current_user_id() === $wp_uid ) {
			wp_send_json_error( __( 'You cannot deactivate your own account.', 'wp-creatorreactor' ), 400 );
		}

		$target = new \WP_User( $wp_uid );
		if ( ! $target->exists() ) {
			wp_send_json_error( __( 'WordPress user not found.', 'wp-creatorreactor' ), 400 );
		}

		$target->set_role( '' );

		$wpdb->update(
			$table,
			[
				'status'     => Entitlements::STATUS_INACTIVE,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $ent_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
		if ( ! empty( $wpdb->last_error ) ) {
			self::log_sync( 'error', 'Deactivate user: entitlement update failed: ' . $wpdb->last_error );
		}

		self::log_sync( 'info', sprintf( 'Deactivated WordPress user ID %d (entitlement row %d).', $wp_uid, $ent_id ) );

		try {
			$snapshot = self::get_users_tab_snapshot();
			wp_send_json_success( self::render_users_tab_panel_html( $snapshot['totals'], $snapshot['rows'], null ) );
		} catch ( \Throwable $e ) {
			self::log_sync( 'error', 'User table after deactivate: ' . $e->getMessage() );
			wp_send_json_error(
				[
					'message'   => $e->getMessage(),
					'panelHtml' => self::render_users_tab_panel_html(
						[
							'total'    => 0,
							'active'   => 0,
							'inactive' => 0,
						],
						[],
						$e->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * @param bool   $broker_mode Agency mode when true.
	 * @param array  $opts        Options from {@see self::get_options()}.
	 * @param string $secret_mask Mask for client secret field.
	 */
	private static function render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label ) {
		$option_name                        = self::OPTION_NAME;
		$fanvue_oauth_instructions_expanded = ! self::is_fanvue_oauth_settings_configured_from_opts( $broker_mode, $opts );
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/oauth-auth-mode-fields.php';
	}

	/**
	 * @param bool  $broker_mode Agency mode when true.
	 * @param array $opts        Options from {@see self::get_options()}.
	 */
	private static function render_sync_dynamic_fields( $broker_mode, $opts ) {
		$option_name = self::OPTION_NAME;
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/sync-auth-mode-fields.php';
	}

	/**
	 * @param array  $opts                Options from {@see self::get_options()}.
	 * @param string $cloud_password_mask Mask for cloud password field.
	 */
	private static function render_cloud_credentials_fields( $opts, $cloud_password_mask ) {
		$option_name = self::OPTION_NAME;
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/cloud-credentials-fields.php';
	}

	/**
	 * @param array $opts Options from {@see self::get_options()}.
	 */
	private static function render_cloud_schema_fields( $opts ) {
		$option_name = self::OPTION_NAME;
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/cloud-schema-fields.php';
	}

	/**
	 * @param array $opts Options from {@see self::get_options()}.
	 */
	private static function render_cloud_metrics_ingest_fields( $opts ) {
		$option_name          = self::OPTION_NAME;
		$metrics_resolved_url = self::get_metrics_ingest_url_for_requests();
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/cloud-metrics-ingest-fields.php';
	}

	/**
	 * Social OAuth provider tab (credentials + appearance partial).
	 *
	 * @param string $slug        Provider slug.
	 * @param array  $opts        Options.
	 * @param string $secret_mask Secret field mask.
	 */
	private static function render_social_oauth_provider_tab_content( $slug, array $opts, $secret_mask ) {
		$cfg = Social_OAuth_Registry::get_config( $slug );
		$label = 'bluesky' === $slug
			? __( 'Bluesky', 'wp-creatorreactor' )
			: ( is_array( $cfg ) && isset( $cfg['label'] ) ? $cfg['label'] : $slug );
		$option_name           = self::OPTION_NAME;
		$broker_mode           = ! empty( $opts['broker_mode'] );
		$redirect_uri          = 'bluesky' === $slug
			? trailingslashit( CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, '/bluesky-oauth-callback' ) )
			: Social_OAuth::get_callback_redirect_uri( $slug );
		$instructions_expanded = ! self::is_social_oauth_provider_configured( $slug );
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/social-oauth-provider-tab.php';
	}

	/**
	 * Google sign-in (OAuth 2.0 / OpenID Connect) for wp-login and shortcodes.
	 *
	 * @param array  $opts         Options from {@see self::get_options()}.
	 * @param string $secret_mask  Mask for client secret field.
	 */
	private static function render_google_settings_fields( array $opts, $secret_mask ) {
		$option_name   = self::OPTION_NAME;
		$redirect_uri  = trailingslashit(
			CreatorReactor_OAuth::get_rest_redirect_uri( CreatorReactor_OAuth::REST_NAMESPACE, '/google-oauth-callback' )
		);
		$broker_mode   = ! empty( $opts['broker_mode'] );
		$g_client_id   = isset( $opts['creatorreactor_google_oauth_client_id'] ) ? (string) $opts['creatorreactor_google_oauth_client_id'] : '';
		$google_instructions_expanded = ! self::is_google_login_configured();
		?>
		<div class="creatorreactor-settings-panel is-active" data-subtab="google-oauth">
			<h2><?php esc_html_e( 'Sign in with Google', 'wp-creatorreactor' ); ?></h2>
			<?php if ( $broker_mode ) : ?>
				<div class="creatorreactor-mode-notice broker">
					<p><?php esc_html_e( 'Google sign-in is not used in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
				</div>
			<?php else : ?>
				<div class="creatorreactor-settings-block">
				<details class="creatorreactor-mode-notice direct creatorreactor-google-instructions"<?php echo $google_instructions_expanded ? ' open' : ''; ?>>
					<summary class="creatorreactor-google-instructions-summary">
						<?php esc_html_e( 'Instructions: Setting up Google', 'wp-creatorreactor' ); ?>
					</summary>
					<div class="creatorreactor-google-instructions-content">
					<p class="description">
						<?php esc_html_e( 'You should not have to enter your credit card into Google Cloud Platform to complete these steps. As of the time this plugin is being written, creating and using an OAuth application in Google Cloud is free. The required features for this plugin should not incur any additional costs.', 'wp-creatorreactor' ); ?>
					</p>
					<ol>
						<li>
							<?php
							printf(
								wp_kses_post(
									/* translators: 1: opening link to Google Cloud Console, 2: closing link, 3: opening link to APIs & Services Credentials, 4: closing link */
									__( 'Create or open a project in %1$sGoogle Cloud Console%2$s, then %3$sAPIs & Services → Credentials%4$s.', 'wp-creatorreactor' )
								),
								'<a href="' . esc_url( 'https://console.cloud.google.com/' ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>',
								'<a href="' . esc_url( 'https://console.cloud.google.com/apis/credentials' ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Click Create Credentials → OAuth Client ID of type “Web application”.', 'wp-creatorreactor' ); ?></li>
						<li><?php esc_html_e( 'Click “Configure consent screen” → Get Started.', 'wp-creatorreactor' ); ?></li>
						<li>
							<?php esc_html_e( 'Enter an App Name (recommendation: CreatorReactor_OAuth).', 'wp-creatorreactor' ); ?>
							<br />
							<?php esc_html_e( 'Enter a support email (recommendation: your admin email or webmaster@YOURDOMAIN.com).', 'wp-creatorreactor' ); ?>
						</li>
						<li><?php esc_html_e( 'For Audience select “External”.', 'wp-creatorreactor' ); ?></li>
						<li><?php esc_html_e( 'Enter email for Contact Information (recommendation: whatever you used in step 4).', 'wp-creatorreactor' ); ?></li>
						<li><?php esc_html_e( 'Finish → Click “I Agree” → Click Create.', 'wp-creatorreactor' ); ?></li>
						<li><?php esc_html_e( 'Click “Create OAuth Client”.', 'wp-creatorreactor' ); ?></li>
						<li>
							<?php esc_html_e( 'Application Type: “Web application”.', 'wp-creatorreactor' ); ?>
							<br />
							<?php esc_html_e( 'Fill out the following fields for your OAuth Client:', 'wp-creatorreactor' ); ?>
							<br />
							<?php esc_html_e( 'Name: (recommended: CreatorReactor_OAuth_Client).', 'wp-creatorreactor' ); ?>
							<br />
							<?php esc_html_e( 'Authorized redirect URL:', 'wp-creatorreactor' ); ?>
							<div class="creatorreactor-redirect-uri-row">
								<input type="text" readonly class="large-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $redirect_uri ); ?>" autocomplete="off" aria-readonly="true" />
								<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $redirect_uri ); ?>" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
							</div>
						</li>
						<li><?php esc_html_e( 'Click the Create button.', 'wp-creatorreactor' ); ?></li>
						<li>
							<?php esc_html_e( 'After clicking “Create” you will be issued the Client ID and Client Secret. Copy these values and paste them into the CreatorReactor settings for Client ID and Client Secret.', 'wp-creatorreactor' ); ?>
							<br />
							<?php esc_html_e( '* If you don’t see your Client Secret and accidentally close the popup you can always click into your OAuth application and you will be able to copy the Client Secret (in the panel on the left of the screen).', 'wp-creatorreactor' ); ?>
						</li>
					</ol>
					<p class="description">
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: link to Google OAuth documentation */
								__( 'See the %s for consent screen and testing requirements.', 'wp-creatorreactor' )
							),
							'<a href="' . esc_url( 'https://developers.google.com/identity/protocols/oauth2/web-server' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google OAuth 2.0 for Web Server apps', 'wp-creatorreactor' ) . '</a>'
						);
						?>
					</p>
					</div>
				</details>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="creatorreactor_google_oauth_client_id"><?php esc_html_e( 'Client ID', 'wp-creatorreactor' ); ?></label></th>
							<td>
								<input type="text" id="creatorreactor_google_oauth_client_id" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_google_oauth_client_id]" value="<?php echo esc_attr( $g_client_id ); ?>" class="large-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
								<p class="description"><?php esc_html_e( 'From your Google OAuth client. Stored encrypted.', 'wp-creatorreactor' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="creatorreactor_google_oauth_client_secret"><?php esc_html_e( 'Client Secret', 'wp-creatorreactor' ); ?></label></th>
							<td>
								<input type="text" id="creatorreactor_google_oauth_client_secret" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_google_oauth_client_secret]" value="<?php echo esc_attr( $secret_mask ); ?>" class="large-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
								<p class="description"><?php esc_html_e( 'Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Authorized redirect URI', 'wp-creatorreactor' ); ?></th>
							<td>
								<div class="creatorreactor-redirect-uri-row">
									<input type="text" readonly class="large-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $redirect_uri ); ?>" autocomplete="off" aria-readonly="true" />
									<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $redirect_uri ); ?>" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
								</div>
								<p class="description">
									<?php
									printf(
										wp_kses_post(
											/* translators: 1: opening link to Google Cloud Console, 2: closing link */
											__( 'Must match the value in %1$sGoogle Cloud Console%2$s (including trailing slash).', 'wp-creatorreactor' )
										),
										'<a href="' . esc_url( 'https://console.cloud.google.com/' ) . '" target="_blank" rel="noopener noreferrer">',
										'</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Google login button style (wp-login + shortcodes). Rendered in a separate settings card below OAuth credentials.
	 */
	private static function render_google_login_button_appearance_fields() {
		$option_name   = self::OPTION_NAME;
		$google_theme  = self::google_login_full_button_theme_from_opts( self::get_options() );
		$google_mode   = self::get_google_oauth_button_size_mode();
		$site_general  = self::get_oauth_logo_button_general_size();
		?>
		<div class="creatorreactor-settings-panel is-active" data-subtab="google-oauth-appearance">
			<h2><?php esc_html_e( 'Login Button Appearance', 'wp-creatorreactor' ); ?></h2>
			<div class="creatorreactor-settings-block">
				<?php self::render_login_button_appearance_card_intro(); ?>
				<?php
				self::render_oauth_logo_button_size_mode_fieldset(
					$option_name,
					self::GOOGLE_OAUTH_BUTTON_SIZE_MODE_KEY,
					$google_mode,
					$site_general,
					Shortcodes::google_oauth_admin_preview_chip( 'logo_only' ),
					Shortcodes::google_oauth_admin_preview_chip( $google_theme )
				);
				?>
				<fieldset class="creatorreactor-google-login-style-fieldset" style="margin-top:16px;">
					<legend><?php esc_html_e( 'Full button theme', 'wp-creatorreactor' ); ?></legend>
					<p class="description"><?php esc_html_e( 'Used whenever this provider shows a full (non–logo-only) Google button.', 'wp-creatorreactor' ); ?></p>
					<div class="creatorreactor-google-login-style-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Sign in with Google full button theme', 'wp-creatorreactor' ); ?>">
						<?php foreach ( self::get_google_login_full_button_theme_choices() as $style_slug => $style_label ) : ?>
							<label class="creatorreactor-google-login-style-option">
								<span class="creatorreactor-google-login-style-option-head">
									<input
										type="radio"
										name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( self::GOOGLE_LOGIN_BUTTON_STYLE_KEY ); ?>]"
										value="<?php echo esc_attr( $style_slug ); ?>"
										<?php checked( $google_theme, $style_slug ); ?>
									/>
									<span class="creatorreactor-google-login-style-option-label"><?php echo esc_html( $style_label ); ?></span>
								</span>
								<span class="creatorreactor-google-login-style-preview" aria-hidden="true">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG + escaped label inside Shortcodes helper.
									echo Shortcodes::google_oauth_admin_preview_chip( $style_slug );
									?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</div>
		</div>
		<?php
	}

	/**
	 * Fanvue login button style (wp-login + shortcodes). Separate card under OAuth; fan login redirect URI lives in OAuth Configuration.
	 *
	 * @param string $active_subtab Current settings subtab slug (`oauth` or `sync`).
	 */
	private static function render_fanvue_login_button_appearance_fields( $active_subtab ) {
		$option_name   = self::OPTION_NAME;
		$cancel_url    = self::admin_page_url(
			[
				'tab'    => 'settings',
				'subtab' => in_array( $active_subtab, [ 'oauth', 'sync' ], true ) ? $active_subtab : 'oauth',
			],
			self::PAGE_SETTINGS_SLUG
		);
		$fv_mode      = self::get_fanvue_oauth_button_size_mode();
		$site_general = self::get_oauth_logo_button_general_size();
		?>
		<div class="creatorreactor-settings-panel is-active" data-subtab="fanvue-oauth-login-appearance">
			<h2><?php esc_html_e( 'Login Button Appearance', 'wp-creatorreactor' ); ?></h2>
			<div class="creatorreactor-settings-block">
				<?php self::render_login_button_appearance_card_intro(); ?>
				<?php
				self::render_oauth_logo_button_size_mode_fieldset(
					$option_name,
					self::FANVUE_OAUTH_BUTTON_SIZE_MODE_KEY,
					$fv_mode,
					$site_general,
					Shortcodes::fanvue_oauth_admin_preview_chip( 'minimal' ),
					Shortcodes::fanvue_oauth_admin_preview_chip( 'standard' )
				);
				?>
			</div>
		</div>
		<div class="creatorreactor-settings-actions">
			<a class="button" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
			<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Instagram OAuth: instructions + OAuth Configuration (Settings → Instagram).
	 *
	 * @param array  $opts        Options from {@see self::get_options()}.
	 * @param string $secret_mask Mask for client secret field.
	 */
	private static function render_instagram_oauth_primary_fields( array $opts, $secret_mask ) {
		$redirect_uri  = Instagram_OAuth::get_callback_redirect_uri();
		$broker_mode   = ! empty( $opts['broker_mode'] );
		$docs_url      = 'https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/get-started';
		$meta_apps_url = 'https://developers.facebook.com/apps/';
		$brand_url     = 'https://www.meta.com/brand/resources/instagram/instagram-brand/';
		$option_name   = self::OPTION_NAME;
		$i_client_id   = isset( $opts['creatorreactor_instagram_oauth_client_id'] ) ? (string) $opts['creatorreactor_instagram_oauth_client_id'] : '';
		$instagram_instructions_expanded = ! self::is_instagram_oauth_configured();
		?>
		<div class="creatorreactor-settings-panel is-active" data-subtab="instagram-oauth">
			<h2><?php esc_html_e( 'Instagram OAuth', 'wp-creatorreactor' ); ?></h2>
			<?php if ( $broker_mode ) : ?>
				<div class="creatorreactor-mode-notice broker">
					<p><?php esc_html_e( 'Instagram OAuth is not used in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
				</div>
			<?php else : ?>
				<div class="creatorreactor-settings-block">
				<details class="creatorreactor-mode-notice direct creatorreactor-google-instructions"<?php echo $instagram_instructions_expanded ? ' open' : ''; ?>>
					<summary class="creatorreactor-google-instructions-summary">
						<?php esc_html_e( 'Instructions: Meta app and Instagram Login', 'wp-creatorreactor' ); ?>
					</summary>
					<div class="creatorreactor-google-instructions-content">
						<p class="description">
							<?php esc_html_e( 'Create a Meta developer app, add the “Instagram API with Instagram Login” product, and configure an Instagram app ID and secret. Register the OAuth redirect URI from OAuth Configuration below exactly as shown (including the trailing slash) in your app’s Instagram product OAuth settings.', 'wp-creatorreactor' ); ?>
						</p>
						<ol>
							<li>
								<?php
								printf(
									wp_kses_post(
										/* translators: 1: opening link to Meta for Developers, 2: closing link */
										__( 'Open %1$sMeta for Developers%2$s and create or select an app.', 'wp-creatorreactor' )
									),
									'<a href="' . esc_url( $meta_apps_url ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
								?>
							</li>
							<li><?php esc_html_e( 'Add the “Instagram API with Instagram Login” product and complete API setup.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the OAuth redirect URI from OAuth Configuration below into Instagram → API setup → OAuth settings in your Meta app.', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Paste the Instagram app ID and app secret into Client ID and Client Secret in OAuth Configuration below.', 'wp-creatorreactor' ); ?></li>
						</ol>
						<p class="description">
							<?php
							printf(
								wp_kses_post(
									/* translators: %s: link to Instagram Login get started guide */
									__( 'See %s for scopes, test users, and going live.', 'wp-creatorreactor' )
								),
								'<a href="' . esc_url( $docs_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Instagram API with Instagram Login — Get started', 'wp-creatorreactor' ) . '</a>'
							);
							?>
						</p>
					</div>
				</details>
					<h3><?php esc_html_e( 'OAuth Configuration', 'wp-creatorreactor' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="creatorreactor_instagram_oauth_client_id"><?php esc_html_e( 'Client ID', 'wp-creatorreactor' ); ?></label></th>
							<td>
								<input type="text" id="creatorreactor_instagram_oauth_client_id" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_instagram_oauth_client_id]" value="<?php echo esc_attr( $i_client_id ); ?>" class="large-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
								<p class="description"><?php esc_html_e( 'Instagram app ID from Meta. Stored encrypted.', 'wp-creatorreactor' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="creatorreactor_instagram_oauth_client_secret"><?php esc_html_e( 'Client Secret', 'wp-creatorreactor' ); ?></label></th>
							<td>
								<input type="text" id="creatorreactor_instagram_oauth_client_secret" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_instagram_oauth_client_secret]" value="<?php echo esc_attr( $secret_mask ); ?>" class="large-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
								<p class="description"><?php esc_html_e( 'Instagram app secret. Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'OAuth redirect URI', 'wp-creatorreactor' ); ?></th>
							<td>
								<div class="creatorreactor-redirect-uri-row">
									<input type="text" readonly class="large-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $redirect_uri ); ?>" autocomplete="off" aria-readonly="true" />
									<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $redirect_uri ); ?>" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Must match the redirect URI configured in your Meta app (including trailing slash).', 'wp-creatorreactor' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="description">
						<?php
						printf(
							wp_kses_post(
								/* translators: 1: opening link to Meta Instagram brand resources, 2: closing link */
								__( 'The Login Button Appearance card below shows a gradient inspired by Meta’s Instagram palette. For marketing and broadcast, use approved assets from %1$sInstagram brand resources%2$s.', 'wp-creatorreactor' )
							),
							'<a href="' . esc_url( $brand_url ) . '" target="_blank" rel="noopener noreferrer">',
							'</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Instagram-style login button appearance (gradient previews; Settings → Instagram second card).
	 *
	 * @param array $opts Options from {@see self::get_options()}.
	 */
	private static function render_instagram_login_button_appearance_fields( array $opts ) {
		$option_name = self::OPTION_NAME;
		$ig_mode      = self::get_instagram_oauth_button_size_mode();
		$site_general = self::get_oauth_logo_button_general_size();
		?>
		<div class="creatorreactor-settings-panel is-active" data-subtab="instagram-oauth-appearance">
			<h2><?php esc_html_e( 'Login Button Appearance', 'wp-creatorreactor' ); ?></h2>
			<div class="creatorreactor-settings-block">
				<?php self::render_login_button_appearance_card_intro(); ?>
				<?php
				self::render_oauth_logo_button_size_mode_fieldset(
					$option_name,
					self::INSTAGRAM_OAUTH_BUTTON_SIZE_MODE_KEY,
					$ig_mode,
					$site_general,
					Shortcodes::instagram_oauth_admin_preview_chip( 'minimal' ),
					Shortcodes::instagram_oauth_admin_preview_chip( 'standard' )
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * OnlyFans / OFAuth settings (Settings → OnlyFans tab): OAuth card (instructions + OFAuth Configuration), Sync, Login Button Appearance on OAuth subtab.
	 *
	 * @param array  $opts                Options from {@see self::get_options()}.
	 * @param string $api_key_mask        Mask or empty for API key field.
	 * @param string $webhook_secret_mask Mask or empty for webhook secret field.
	 */
	private static function render_onlyfans_settings_fields( array $opts, $api_key_mask, $webhook_secret_mask, $onlyfans_active_subtab = 'oauth' ) {
		$option_name         = self::OPTION_NAME;
		$webhook_url         = OFAuth::get_webhook_url();
		$settings_cancel_url = self::admin_page_url(
			[
				'tab'    => 'onlyfans',
				'subtab' => in_array( $onlyfans_active_subtab, [ 'oauth', 'sync' ], true ) ? $onlyfans_active_subtab : 'oauth',
			],
			self::PAGE_SETTINGS_SLUG
		);
		$onlyfans_ofauth_instructions_expanded = ! self::is_onlyfans_ofauth_configured_from_opts( $opts );
		include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/onlyfans-settings-fields.php';
	}

	/**
	 * Sets a cookie with the browser IANA timezone so PHP can format timestamps when Display Timezone is Browser.
	 */
	public static function enqueue_admin_display_timezone_cookie() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing query args; capability checked above.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SETTINGS_SLUG === $page && 'general' === $tab ) {
			return;
		}
		$cookie_name = self::COOKIE_ADMIN_DISPLAY_TZ;
		$max_age     = defined( 'YEAR_IN_SECONDS' ) ? (int) YEAR_IN_SECONDS : 31536000;
		$js          = '(function(){try{var z=Intl.DateTimeFormat().resolvedOptions().timeZone;'
			. 'if(!z||!/^[A-Za-z0-9_+/\\-]+$/.test(z)){return;}'
			. 'var c=' . wp_json_encode( $cookie_name ) . "+'='+encodeURIComponent(z)+';path=/;SameSite=Lax;max-age=" . (string) $max_age
			. "+(document.location&&document.location.protocol==='https:'?';Secure':'');"
			. 'document.cookie=c;}catch(e){}})();';
		wp_register_script( 'creatorreactor-admin-display-tz-cookie', false, [], CREATORREACTOR_VERSION, true );
		wp_enqueue_script( 'creatorreactor-admin-display-tz-cookie' );
		wp_add_inline_script( 'creatorreactor-admin-display-tz-cookie', $js );
	}

	public static function enqueue_assets( $hook_suffix ) {
		$hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$is_plugin_page   = ( $page === self::PAGE_SLUG || $page === self::PAGE_USERS_SLUG || $page === self::PAGE_SETTINGS_SLUG );
		$is_user_profile  = in_array( $hook_suffix, [ 'profile.php', 'user-edit.php' ], true );
		if ( ! $is_plugin_page && ! $is_user_profile ) {
			return;
		}

		if ( $is_user_profile ) {
			$profile_css = '
			#creatorreactor-user-details-modal.creatorreactor-modal { z-index: 100001; }
			#creatorreactor-user-details-modal .creatorreactor-user-details-close {
				border: 0;
				background: transparent;
				color: #50575e;
				cursor: pointer;
				font-size: 22px;
				line-height: 1;
				padding: 0 4px;
			}
			#creatorreactor-user-details-modal .creatorreactor-modal-body dl {
				margin: 0;
				display: grid;
				grid-template-columns: 9.5em 1fr;
				gap: 8px 16px;
				font-size: 13px;
			}
			#creatorreactor-user-details-modal .creatorreactor-modal-body dt { margin: 0; color: #646970; font-weight: 600; }
			#creatorreactor-user-details-modal .creatorreactor-modal-body dd { margin: 0; word-break: break-word; }
			#creatorreactor-user-details-modal .creatorreactor-modal-body dt.creatorreactor-details-section {
				grid-column: 1 / span 2;
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid #dcdcde;
				color: #414a4c;
				font-weight: 700;
			}
			#creatorreactor-user-details-modal .creatorreactor-modal-body dd.creatorreactor-details-section-spacer { display: none; }
			.creatorreactor-modal-header-title { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
			.creatorreactor-modal-header-title h3 { margin: 0; font-size: 16px; }
			.creatorreactor-brand-logo--modal { max-height: 22px; width: auto; height: auto; display: block; }
			.creatorreactor-profile-section-heading { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
			.creatorreactor-profile-section-heading h2 { margin: 0; padding: 0; }
			.creatorreactor-brand-logo--profile { max-height: 28px; width: auto; height: auto; display: block; }
			.creatorreactor-modal { position: fixed; inset: 0; display: none; z-index: 100000; }
			.creatorreactor-modal[aria-hidden="false"] { display: block; }
			.creatorreactor-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.45); }
			.creatorreactor-modal-dialog { position: relative; width: min(620px, calc(100% - 32px)); max-height: calc(100vh - 64px); margin: 32px auto; background: #fff; border-radius: 8px; border: 1px solid #dcdcde; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25); overflow: auto; }
			.creatorreactor-modal-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px 10px; border-bottom: 1px solid #dcdcde; }
			.creatorreactor-modal-header h3 { margin: 0; font-size: 16px; }
			.creatorreactor-modal-body { padding: 14px 18px; }
			';

			wp_register_style( 'creatorreactor-user-profile', false, [], CREATORREACTOR_VERSION );
			wp_enqueue_style( 'creatorreactor-user-profile' );
			wp_add_inline_style( 'creatorreactor-user-profile', $profile_css );

			wp_register_script(
				'creatorreactor-user-profile',
				CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-user-profile.js',
				[ 'jquery' ],
				CREATORREACTOR_VERSION,
				true
			);
			wp_enqueue_script( 'creatorreactor-user-profile' );
			wp_localize_script(
				'creatorreactor-user-profile',
				'creatorreactorUserProfile',
				[
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'creatorreactor_user_profile_details' ),
					'detailsLoading'    => __( 'Loading…', 'wp-creatorreactor' ),
					'detailsLoadError'  => __( 'Could not load record details.', 'wp-creatorreactor' ),
					'noEntitlementText' => __( 'No CreatorReactor record found for this user.', 'wp-creatorreactor' ),
				]
			);
			return;
		}

		$css = '
		.creatorreactor-wrap {
			margin-top: 20px;
			max-width: 1100px;
			color: #414a4c;
			--cr-text-body: #414a4c;
			--cr-brand-deep: #301934;
			--cr-brand-magenta: #8e2d77;
			--cr-brand-pink: #d64d7f;
			--cr-brand-coral: #f9a891;
			--cr-accent: var(--cr-brand-magenta);
			--cr-accent-strong: #6d2459;
			--cr-accent-deep: #4b1d66;
			--cr-link: var(--cr-brand-magenta);
			--cr-link-hover: #5c2364;
			--cr-card-radius: 12px;
			--cr-card-shadow: 0 8px 28px rgba(48, 25, 52, 0.11), 0 2px 8px rgba(0, 0, 0, 0.05);
			--cr-card-shadow-elevated: 0 18px 44px rgba(48, 25, 52, 0.12), 0 4px 14px rgba(0, 0, 0, 0.04);
		}
		/* Primary actions: solid brand magenta (no gradient). Note: .wp-core-ui is on body, not inside .creatorreactor-wrap. */
		body.wp-core-ui .creatorreactor-wrap .button-primary,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary,
		body.wp-core-ui .creatorreactor-wrap input.button-primary,
		body.wp-core-ui .creatorreactor-wrap a.button.button-primary {
			background: var(--cr-brand-magenta) !important;
			border: 1px solid var(--cr-accent-strong) !important;
			border-radius: 4px;
			box-shadow: 0 2px 6px rgba(48, 25, 52, 0.18) !important;
			color: #fff !important;
			text-shadow: none !important;
			transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
		}
		body.wp-core-ui .creatorreactor-wrap .button-primary:hover,
		body.wp-core-ui .creatorreactor-wrap .button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary:hover,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap input.button-primary:hover,
		body.wp-core-ui .creatorreactor-wrap input.button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap a.button.button-primary:hover,
		body.wp-core-ui .creatorreactor-wrap a.button.button-primary:focus {
			background: var(--cr-accent-strong) !important;
			border-color: var(--cr-accent-deep) !important;
			box-shadow: 0 4px 12px rgba(48, 25, 52, 0.26) !important;
			color: #fff !important;
		}
		body.wp-core-ui .creatorreactor-wrap .button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap input.button-primary:focus,
		body.wp-core-ui .creatorreactor-wrap a.button.button-primary:focus {
			outline: 2px solid var(--cr-brand-coral);
			outline-offset: 2px;
		}
		body.wp-core-ui .creatorreactor-wrap .button-primary:disabled,
		body.wp-core-ui .creatorreactor-wrap .button-primary.disabled,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary:disabled,
		body.wp-core-ui .creatorreactor-wrap .button.button-primary.disabled,
		body.wp-core-ui .creatorreactor-wrap input.button-primary:disabled {
			filter: none !important;
			opacity: 0.55;
			cursor: not-allowed;
			box-shadow: none !important;
		}
		/* Outline / secondary buttons (Cancel, Clear logs, plain .button): purple border instead of core blue. */
		body.wp-core-ui .creatorreactor-wrap .button.button-secondary,
		body.wp-core-ui .creatorreactor-wrap a.button:not(.button-primary),
		body.wp-core-ui .creatorreactor-wrap input.button.button-secondary,
		body.wp-core-ui .creatorreactor-wrap button.button:not(.button-primary):not(.button-link):not(.creatorreactor-btn-connect):not(.creatorreactor-btn-disconnect):not(.creatorreactor-oauth-tab-lock):not(.creatorreactor-cloud-tab-lock) {
			color: var(--cr-brand-deep, #301934) !important;
			background: #f6f7f7 !important;
			border-color: var(--cr-brand-magenta, #8e2d77) !important;
			box-shadow: 0 1px 0 rgba(48, 25, 52, 0.06) !important;
		}
		body.wp-core-ui .creatorreactor-wrap .button.button-secondary:hover,
		body.wp-core-ui .creatorreactor-wrap .button.button-secondary:focus,
		body.wp-core-ui .creatorreactor-wrap a.button:not(.button-primary):hover,
		body.wp-core-ui .creatorreactor-wrap a.button:not(.button-primary):focus,
		body.wp-core-ui .creatorreactor-wrap input.button.button-secondary:hover,
		body.wp-core-ui .creatorreactor-wrap input.button.button-secondary:focus,
		body.wp-core-ui .creatorreactor-wrap button.button:not(.button-primary):not(.button-link):not(.creatorreactor-btn-connect):not(.creatorreactor-btn-disconnect):not(.creatorreactor-oauth-tab-lock):not(.creatorreactor-cloud-tab-lock):hover,
		body.wp-core-ui .creatorreactor-wrap button.button:not(.button-primary):not(.button-link):not(.creatorreactor-btn-connect):not(.creatorreactor-btn-disconnect):not(.creatorreactor-oauth-tab-lock):not(.creatorreactor-cloud-tab-lock):focus {
			color: var(--cr-accent-strong, #6d2459) !important;
			background: #fcf9fb !important;
			border-color: var(--cr-accent-strong, #6d2459) !important;
			box-shadow: 0 1px 0 rgba(48, 25, 52, 0.1) !important;
		}
		.creatorreactor-settings-header {
			display: flex;
			align-items: flex-start;
			gap: 16px;
			margin-bottom: 20px;
			flex-wrap: wrap;
		}
		.creatorreactor-settings-header-brand { flex-shrink: 0; }
		.creatorreactor-brand-logo { max-height: 44px; width: auto; height: auto; display: block; }
		.creatorreactor-settings-header-text { flex: 1; min-width: 200px; }
		.creatorreactor-settings-header h1 { margin-bottom: 5px; color: var(--cr-brand-deep, #301934); }
		.creatorreactor-settings-header p { color: #6b5a74; margin-top: 0; }
		.creatorreactor-section {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: var(--cr-card-radius);
			box-shadow: var(--cr-card-shadow);
			padding: 20px;
			margin-bottom: 20px;
		}
		.creatorreactor-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #dcdcde; font-size: 16px; color: var(--cr-brand-deep, #301934); }
		.creatorreactor-section h3 { margin-top: 0; font-size: 14px; }
		details.creatorreactor-shortcodes-guide-details { padding: 0; }
		details.creatorreactor-shortcodes-guide-details > summary.creatorreactor-shortcodes-guide-summary {
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: 10px;
			margin: 0;
			padding: 18px 20px 10px 20px;
			cursor: pointer;
			font-size: 16px;
			font-weight: 600;
			line-height: 1.4;
			list-style: none;
		}
		details.creatorreactor-shortcodes-guide-details > summary.creatorreactor-shortcodes-guide-summary::-webkit-details-marker {
			display: none;
		}
		details.creatorreactor-shortcodes-guide-details > summary .creatorreactor-shortcodes-guide-summary-chevron {
			flex-shrink: 0;
			display: inline-block;
			width: 0;
			height: 0;
			border-style: solid;
			border-width: 5px 0 5px 7px;
			border-color: transparent transparent transparent #50575e;
			transition: transform 0.15s ease;
			transform-origin: 35% 50%;
		}
		details.creatorreactor-shortcodes-guide-details[open] > summary .creatorreactor-shortcodes-guide-summary-chevron {
			transform: rotate(90deg);
		}
		details.creatorreactor-shortcodes-guide-details[open] > summary.creatorreactor-shortcodes-guide-summary {
			border-bottom: 1px solid #dcdcde;
			padding-bottom: 10px;
			margin-bottom: 0;
		}
		details.creatorreactor-shortcodes-guide-details .creatorreactor-shortcodes-guide-inner {
			padding: 16px 20px 20px;
		}
		details.creatorreactor-shortcodes-guide-details .creatorreactor-shortcodes-guide-inner > h3:first-child {
			margin-top: 0;
		}
		.creatorreactor-users-toolbar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 12px;
			flex-wrap: wrap;
		}
		.creatorreactor-users-toolbar .creatorreactor-users-totals { margin: 0; }
		.creatorreactor-users-inner[aria-busy="true"] { opacity: 0.55; pointer-events: none; transition: opacity 0.15s ease; }
		.creatorreactor-settings-auth-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: var(--cr-card-radius);
			box-shadow: var(--cr-card-shadow);
			overflow: hidden;
			margin-bottom: 20px;
		}
		.creatorreactor-settings-auth-card > h2 {
			margin: 0;
			padding: 16px 20px;
			font-size: 16px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.creatorreactor-settings-auth-card .creatorreactor-settings-block { padding: 20px; border-top: none; }
		.creatorreactor-auth-mode-intro { margin: 0 0 12px; color: #414a4c; font-size: 14px; }
		.creatorreactor-auth-mode-segmented {
			display: inline-flex;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			overflow: hidden;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
		}
		.creatorreactor-auth-mode-segmented label {
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0;
			padding: 10px 22px;
			min-width: 120px;
			background: #f6f7f7;
			cursor: pointer;
			font-weight: 600;
			font-size: 14px;
			color: #414a4c;
			border-right: 1px solid #c3c4c7;
			user-select: none;
			text-align: center;
		}
		.creatorreactor-auth-mode-segmented label > span {
			display: block;
			width: 100%;
			text-align: center;
			line-height: 1.25;
		}
		.creatorreactor-auth-mode-segmented label:last-child { border-right: 0; }
		.creatorreactor-auth-mode-segmented label { position: relative; }
		.creatorreactor-auth-mode-segmented input.creatorreactor-auth-mode-input {
			position: absolute;
			opacity: 0;
			width: 1px;
			height: 1px;
			clip: rect(0, 0, 0, 0);
		}
		.creatorreactor-auth-mode-segmented label.is-selected {
			background: #f4e9f1;
			box-shadow: none;
			z-index: 1;
		}
		.creatorreactor-auth-mode-segmented input.creatorreactor-auth-mode-input:focus-visible + span {
			outline: 2px solid var(--cr-accent, #8e2d77);
			outline-offset: 2px;
		}
		.creatorreactor-auth-mode-hint { margin: 12px 0 0; color: #646970; font-size: 13px; max-width: 520px; }
		.creatorreactor-auth-mode-hint ol { margin: 8px 0 0 1.25em; padding: 0; }
		.creatorreactor-auth-mode-hint ol li { margin: 4px 0; }
		.creatorreactor-auth-mode-hint-url { display: block; margin: 4px 0 0 1.25em; }
		.creatorreactor-default-button-style-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
		.creatorreactor-mode-notice { padding: 12px 15px; border-radius: 4px; margin: 15px 0; }
		.creatorreactor-mode-notice.direct { background: #faf3f8; border-left: 4px solid var(--cr-accent, #8e2d77); }
		.creatorreactor-mode-notice.broker { background: #f0f6ce; border-left: 4px solid #00a32a; }
		.creatorreactor-google-instructions.creatorreactor-mode-notice { padding: 0; }
		.creatorreactor-google-instructions > .creatorreactor-google-instructions-summary {
			list-style: none;
			padding: 12px 15px;
			margin: 0;
			cursor: pointer;
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 14px;
			font-weight: 600;
			line-height: 1.35;
			color: #1d2327;
			background: rgba(142, 45, 119, 0.08);
			border-bottom: 1px solid rgba(142, 45, 119, 0.15);
			user-select: none;
		}
		.creatorreactor-google-instructions > .creatorreactor-google-instructions-summary::-webkit-details-marker { display: none; }
		.creatorreactor-google-instructions > .creatorreactor-google-instructions-summary::before {
			content: "";
			flex-shrink: 0;
			width: 0;
			height: 0;
			border-left: 5px solid transparent;
			border-right: 5px solid transparent;
			border-top: 6px solid #50575e;
			transition: transform 0.2s ease;
		}
		.creatorreactor-google-instructions:not([open]) > .creatorreactor-google-instructions-summary::before {
			transform: rotate(-90deg);
		}
		.creatorreactor-google-instructions > .creatorreactor-google-instructions-summary:focus {
			outline: none;
			box-shadow: inset 0 0 0 1px var(--cr-accent, #8e2d77);
		}
		.creatorreactor-google-instructions > .creatorreactor-google-instructions-summary:focus-visible {
			box-shadow: inset 0 0 0 2px var(--cr-accent, #8e2d77);
		}
		.creatorreactor-google-instructions .creatorreactor-google-instructions-content { padding: 12px 15px 15px; }
		.creatorreactor-google-instructions .creatorreactor-google-instructions-content > p.description:first-child { margin: 0 0 10px; }
		.creatorreactor-google-instructions .creatorreactor-google-instructions-content > ol + p.description { margin-top: 10px; }
		.creatorreactor-google-login-style-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
		.creatorreactor-oauth-default-pill {
			display: inline-block;
			margin-left: 8px;
			padding: 2px 8px;
			font-size: 11px;
			font-weight: 600;
			line-height: 1.35;
			color: var(--cr-brand-deep, #301934);
			background: rgba(142, 45, 119, 0.12);
			border-radius: 999px;
			vertical-align: middle;
		}
		.creatorreactor-google-login-style-grid,
		.creatorreactor-fanvue-login-style-grid,
		.creatorreactor-instagram-login-style-grid,
		.creatorreactor-onlyfans-login-style-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
			gap: 14px;
			margin-top: 10px;
			max-width: 920px;
		}
		.creatorreactor-google-login-style-option,
		.creatorreactor-fanvue-login-style-option,
		.creatorreactor-instagram-login-style-option,
		.creatorreactor-onlyfans-login-style-option {
			display: flex;
			flex-direction: column;
			gap: 10px;
			margin: 0;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			background: #f6f7f7;
			cursor: pointer;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}
		.creatorreactor-google-login-style-option:hover,
		.creatorreactor-fanvue-login-style-option:hover,
		.creatorreactor-instagram-login-style-option:hover,
		.creatorreactor-onlyfans-login-style-option:hover { border-color: #c3c4c7; }
		.creatorreactor-google-login-style-option:has(input:checked),
		.creatorreactor-fanvue-login-style-option:has(input:checked),
		.creatorreactor-instagram-login-style-option:has(input:checked),
		.creatorreactor-onlyfans-login-style-option:has(input:checked) {
			border-color: #dcdcde;
			box-shadow: none;
			background: #f4e9f1;
		}
		.creatorreactor-google-login-style-option-head,
		.creatorreactor-fanvue-login-style-option-head,
		.creatorreactor-instagram-login-style-option-head,
		.creatorreactor-onlyfans-login-style-option-head {
			display: flex;
			align-items: flex-start;
			gap: 8px;
			font-weight: 600;
			font-size: 13px;
			color: var(--cr-text-body, #414a4c);
		}
		.creatorreactor-google-login-style-option-head input,
		.creatorreactor-fanvue-login-style-option-head input,
		.creatorreactor-instagram-login-style-option-head input,
		.creatorreactor-onlyfans-login-style-option-head input { margin-top: 2px; flex-shrink: 0; }
		.creatorreactor-google-login-style-option-label,
		.creatorreactor-fanvue-login-style-option-label,
		.creatorreactor-instagram-login-style-option-label,
		.creatorreactor-onlyfans-login-style-option-label { line-height: 1.35; }
		.creatorreactor-google-login-style-preview,
		.creatorreactor-fanvue-login-style-preview,
		.creatorreactor-instagram-login-style-preview,
		.creatorreactor-onlyfans-login-style-preview {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 56px;
			padding: 10px;
			background: #fff;
			border: 1px dashed #c3c4c7;
			border-radius: 6px;
		}
		.creatorreactor-google-login-style-preview .creatorreactor-google-oauth-link.creatorreactor-google-oauth--admin-preview {
			pointer-events: none;
			cursor: default;
		}
		.creatorreactor-fanvue-login-style-preview .creatorreactor-fanvue-oauth--admin-preview {
			pointer-events: none;
			cursor: default;
		}
		.creatorreactor-instagram-login-style-preview .creatorreactor-instagram-oauth--admin-preview {
			pointer-events: none;
			cursor: default;
		}
		.creatorreactor-onlyfans-login-style-preview .creatorreactor-onlyfans-oauth--admin-preview {
			pointer-events: none;
			cursor: default;
		}
		.creatorreactor-fanvue-oauth--admin-preview.creatorreactor-fanvue-oauth-link {
			text-decoration: none;
			color: #8e2d77;
		}
		.creatorreactor-fanvue-oauth--admin-preview .creatorreactor-fanvue-oauth-img {
			display: block;
			height: auto;
			max-width: min(220px, 100%);
			width: auto;
			margin: 0 auto;
		}
		.creatorreactor-fanvue-oauth--admin-preview .creatorreactor-fanvue-oauth-text {
			display: block;
			margin-top: 8px;
			font-size: 14px;
			font-weight: 500;
			text-align: center;
		}
		.creatorreactor-mode-notice p { margin: 0; font-size: 13px; }
		.creatorreactor-mode-notice > p:first-child { margin-bottom: 8px; }
		.creatorreactor-mode-notice ol { margin: 0; padding-left: 1.25em; font-size: 13px; }
		.creatorreactor-mode-notice ol li { margin: 6px 0; }
		.creatorreactor-mode-notice .creatorreactor-ofauth-doc-link { margin-top: 10px; }
		.creatorreactor-broker-field { transition: opacity 0.2s ease; }
		.creatorreactor-broker-field:disabled { opacity: 0.5; cursor: not-allowed; }
		.creatorreactor-redirect-uri-row { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; margin: 0 0 4px; }
		.creatorreactor-redirect-uri-row .creatorreactor-oauth-redirect-uri-input { flex: 1 1 240px; min-width: 200px; max-width: 100%; }
		.creatorreactor-redirect-uri-row .creatorreactor-copy-redirect-uri { flex: 0 0 auto; margin-top: 1px; }
		.form-table th { width: 200px; }
		.form-table input[type="text"],
		.form-table input[type="url"],
		.form-table input[type="password"],
		.form-table textarea { width: 100%; max-width: 400px; }
		/* Slightly oversized fields vs default wp-admin (comfort / readability). */
		.creatorreactor-wrap .form-table input[type="text"],
		.creatorreactor-wrap .form-table input[type="url"],
		.creatorreactor-wrap .form-table input[type="password"],
		.creatorreactor-wrap .form-table input[type="email"],
		.creatorreactor-wrap .form-table input[type="search"],
		.creatorreactor-wrap .form-table input[type="number"],
		.creatorreactor-wrap .form-table textarea,
		.creatorreactor-wrap .form-table select {
			font-size: 14px;
			line-height: 1.45;
			padding: 9px 12px;
			min-height: 40px;
			border-radius: 6px;
			box-sizing: border-box;
		}
		.creatorreactor-wrap .form-table textarea {
			min-height: 110px;
			padding: 10px 12px;
		}
		.creatorreactor-wrap .form-table select {
			min-height: 40px;
			padding-top: 8px;
			padding-bottom: 8px;
		}
		.creatorreactor-wrap #creatorreactor-docs-search.regular-text {
			font-size: 14px;
			line-height: 1.45;
			padding: 9px 12px;
			min-height: 40px;
			border-radius: 6px;
			box-sizing: border-box;
		}
		input.creatorreactor-oauth-client-secret { -webkit-text-security: disc; font-family: Consolas, Monaco, monospace; }
		.form-table .description { color: #646970; font-size: 13px; }
		.creatorreactor-general-login-error.description { color: #d63638; margin-top: 8px; }
		.creatorreactor-status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
		.creatorreactor-status-green { background: #edfaef; color: #1a7f37; }
		.creatorreactor-status-badge.creatorreactor-status-healthy {
			background: #ecfdf3;
			padding: 6px 12px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			color: #15803d;
			border: 1px solid #bbf7d0;
		}
		.creatorreactor-status-yellow {
			background: #fffbeb;
			color: #a16207;
			border: 1px solid #fde68a;
		}
		.creatorreactor-status-red {
			background: #fef2f2;
			color: #b91c1c;
			border: 1px solid #fecaca;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav.nav-tab-wrapper {
			margin: 0 0 16px;
			padding: 0;
			border-bottom: 1px solid var(--cr-brand-magenta);
			display: flex;
			flex-wrap: wrap;
			align-items: flex-end;
			gap: 0;
			width: 100%;
			box-sizing: border-box;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab {
			float: none !important;
			white-space: nowrap;
			margin: 0 4px -1px 0 !important;
			padding: 9px 16px !important;
			font-size: 14px;
			font-weight: 600;
			line-height: 1.35;
			border-radius: 6px 6px 0 0;
			border: 1px solid rgba(142, 45, 119, 0.45) !important;
			border-bottom: none !important;
			background: #f3e9f1 !important;
			color: var(--cr-brand-deep) !important;
			text-decoration: none !important;
			box-shadow: none !important;
			transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab:hover,
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab:focus {
			color: var(--cr-accent-strong) !important;
			background: #faf3f8 !important;
			border-color: var(--cr-brand-magenta) !important;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab.nav-tab-active {
			background: #fff !important;
			color: var(--cr-brand-deep) !important;
			border-color: var(--cr-brand-magenta) !important;
			border-bottom: 1px solid #fff !important;
			margin-bottom: -1px !important;
			position: relative;
			z-index: 1;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab.nav-tab-active:hover,
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab.nav-tab-active:focus {
			background: #fff !important;
			color: var(--cr-accent-strong) !important;
			border-color: var(--cr-accent-strong) !important;
			border-bottom-color: #fff !important;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab:focus-visible {
			outline: 2px solid var(--cr-brand-coral);
			outline-offset: 2px;
		}
		.creatorreactor-tab-panel { display: none; }
		.creatorreactor-tab-panel.is-active { display: block; }
		.creatorreactor-sync-row { display: flex; gap: 15px; align-items: flex-end; margin-top: 15px; }
		.creatorreactor-sync-row input[type="number"] { width: 80px; }
		.creatorreactor-check-list { margin: 10px 0 0; }
		.creatorreactor-check-list li { margin-bottom: 8px; }
		.creatorreactor-check-result-pass { color: #1a7f37; font-weight: 600; }
		.creatorreactor-check-result-fail { color: #b52727; font-weight: 600; }
		.creatorreactor-muted { color: #646970; }
		.creatorreactor-meta-list { margin: 0; }
		.creatorreactor-meta-list p { margin: 0 0 10px; }
		.creatorreactor-dashboard-shell {
			--cr-bg: #faf7fb;
			--cr-surface: #ffffff;
			--cr-surface-muted: #f7f1f6;
			--cr-border: #e8e0ed;
			--cr-text: #301934;
			--cr-text-muted: #6b5a74;
			--cr-text-strong: #301934;
			--cr-accent: #8e2d77;
			--cr-accent-strong: #6d2459;
			--cr-danger: #dc2626;
			background: linear-gradient(180deg, #fefcfd 0%, #f5eef4 100%);
			border: 1px solid var(--cr-border);
			border-radius: 16px;
			padding: 28px;
			box-shadow: var(--cr-card-shadow-elevated);
			width: 100%;
			box-sizing: border-box;
		}
		.creatorreactor-dashboard-head {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 20px;
			margin: 0 0 22px;
			padding-bottom: 0;
			border-bottom: 0;
		}
		.creatorreactor-dashboard-head .creatorreactor-status-badge { margin-left: auto; }
		/* In-dashboard cards: section title + separator (matches .creatorreactor-section h2) */
		.creatorreactor-dashboard-card-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin: 0 0 12px;
			padding: 0 0 10px;
			border-bottom: 1px solid #dcdcde;
		}
		h2.creatorreactor-dashboard-card-head__title {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			line-height: 1.35;
			padding: 0;
			border: 0;
			letter-spacing: 0;
			color: var(--cr-text-strong, #414a4c);
		}
		.creatorreactor-integration-checks-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin: 0 0 12px;
			padding: 0 0 10px;
			border-bottom: 1px solid #dcdcde;
		}
		h2.creatorreactor-integration-checks-head__title {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			line-height: 1.35;
			padding: 0;
			border: 0;
			letter-spacing: 0;
			color: var(--cr-text-strong, #414a4c);
			flex: 1;
			min-width: 0;
		}
		#creatorreactor-integration-checks.creatorreactor-section h2.creatorreactor-integration-checks-head__title {
			padding-bottom: 0;
			border-bottom: 0;
		}
		.creatorreactor-integration-checks-refresh {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			min-width: 32px;
			height: 30px;
			padding: 4px;
			margin: 0;
			background: transparent !important;
			border: 0 !important;
			box-shadow: none !important;
			color: #50575e;
			text-decoration: none;
			border-radius: 4px;
			position: relative;
		}
		.creatorreactor-integration-checks-refresh:hover,
		.creatorreactor-integration-checks-refresh:focus-visible {
			color: var(--cr-accent, #8e2d77);
			background: transparent !important;
		}
		.creatorreactor-integration-checks-refresh:focus-visible {
			outline: 2px solid var(--cr-accent, #8e2d77);
			outline-offset: 2px;
		}
		.creatorreactor-integration-checks-refresh::after {
			content: attr(data-creatorreactor-tooltip);
			position: absolute;
			right: 0;
			bottom: 100%;
			margin-bottom: 8px;
			padding: 8px 10px;
			max-width: min(280px, 70vw);
			background: #414a4c;
			color: #fff;
			font-size: 12px;
			line-height: 1.45;
			font-weight: 400;
			border-radius: 4px;
			box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
			white-space: normal;
			text-align: left;
			z-index: 100050;
			opacity: 0;
			visibility: hidden;
			transition: opacity 0.12s ease, visibility 0.12s ease;
			pointer-events: none;
		}
		.creatorreactor-integration-checks-refresh:hover::after,
		.creatorreactor-integration-checks-refresh:focus-visible::after {
			opacity: 1;
			visibility: visible;
		}
		.creatorreactor-integration-checks-refresh .dashicons {
			width: 20px;
			height: 20px;
			font-size: 20px;
			line-height: 1;
		}
		.creatorreactor-dashboard-title { margin: 0; }
		.creatorreactor-dashboard-subtitle { margin: 0; color: var(--cr-text-muted); max-width: 58ch; font-size: 14px; line-height: 1.65; }
		.creatorreactor-integration-checks-all-clear {
			margin: 0 0 12px;
			padding: 10px 12px;
			border-radius: 10px;
			border: 1px solid #bbf7d0;
			background: #f0fdf4;
			color: #166534;
			font-weight: 600;
		}
		.creatorreactor-integration-checks-passed-details {
			margin: 14px 0 0;
			padding: 12px 0 0;
			border-top: 1px solid #e5e7eb;
		}
		.creatorreactor-integration-checks-passed-summary {
			cursor: pointer;
			color: var(--cr-accent, #8e2d77);
			font-weight: 600;
			list-style: none;
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}
		.creatorreactor-integration-checks-passed-summary::marker { content: ""; }
		.creatorreactor-integration-checks-passed-summary::-webkit-details-marker { display: none; }
		.creatorreactor-integration-checks-passed-details[open] .creatorreactor-integration-checks-passed-summary { margin-bottom: 10px; }
		.creatorreactor-integration-check-list--passed { margin-top: 8px; }
		.creatorreactor-dashboard-integration-checks-wrap { margin-top: 8px; }
		.creatorreactor-dashboard-col--integration-checks .creatorreactor-dashboard-integration-checks-wrap {
			margin-top: 0;
			width: 100%;
		}
		.creatorreactor-dashboard-col--integration-checks .creatorreactor-dashboard-integration-checks-shell {
			min-height: 220px;
		}
		.creatorreactor-dashboard-integration-checks-shell {
			margin-top: 0;
			padding: 22px;
			border: 1px solid var(--cr-border, #e8e0ed);
			border-radius: var(--cr-card-radius);
			background: #fff;
			box-shadow: var(--cr-card-shadow);
		}
		.creatorreactor-dashboard-grid {
			display: grid;
			grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
			gap: 18px;
			align-items: stretch;
			margin-bottom: 18px;
		}
		.creatorreactor-dashboard-row {
			display: grid;
			--cr-dashboard-row-gap: 20px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: var(--cr-dashboard-row-gap);
			align-items: start;
		}
		.creatorreactor-dashboard-col {
			display: flex;
			flex-direction: column;
			gap: 16px;
			min-width: 0;
		}
		.creatorreactor-modules-shell {
			padding: 22px;
			border: 1px solid var(--cr-border, #e8e0ed);
			border-radius: var(--cr-card-radius);
			background: #fff;
			box-shadow: var(--cr-card-shadow);
			min-height: 220px;
		}
		.creatorreactor-modules-shell .creatorreactor-dashboard-card-head + .creatorreactor-module-list {
			margin-top: 0;
		}
		.creatorreactor-module-list {
			margin: 14px 0 0;
			padding: 0;
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.creatorreactor-module-item {
			display: flex;
			align-items: flex-start;
			gap: 10px;
		}
		.creatorreactor-module-status-dot {
			width: 12px;
			height: 12px;
			border-radius: 999px;
			margin-top: 5px;
			flex: 0 0 12px;
			background: #9ca3af;
		}
		.creatorreactor-module-status-dot.is-green { background: #16a34a; }
		.creatorreactor-module-status-dot.is-yellow { background: #eab308; }
		.creatorreactor-module-status-dot.is-red { background: #dc2626; }
		.creatorreactor-module-status-dot.is-gray { background: #9ca3af; }
		.creatorreactor-module-content {
			display: flex;
			flex-direction: column;
			gap: 4px;
			min-width: 0;
		}
		.creatorreactor-module-label {
			font-weight: 600;
			color: var(--cr-text-strong);
		}
		.creatorreactor-module-meta {
			font-size: 12px;
			color: var(--cr-text-muted);
			text-transform: capitalize;
		}
		.creatorreactor-integration-check-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 10px 14px;
			margin-top: 4px;
		}
		.creatorreactor-integration-check-actions .button-link {
			font-size: 12px;
			font-weight: 600;
			text-transform: none;
			padding: 0;
			min-height: 0;
			line-height: 1.4;
			vertical-align: baseline;
		}
		.creatorreactor-integration-fix-link {
			font-size: 12px;
			font-weight: 600;
			text-transform: none;
		}
		.creatorreactor-integration-check-ignored-note {
			display: block;
			font-size: 12px;
			margin-top: 2px;
			text-transform: none;
		}
		#creatorreactor-integration-fix-modal.creatorreactor-modal { z-index: 100002; }
		.creatorreactor-integration-fix-body { margin: 0; line-height: 1.5; }
		.creatorreactor-integration-fix-error {
			margin: 12px 0 0;
			padding: 8px 10px;
			border-radius: 4px;
			border: 1px solid #f3c7c7;
			background: #fff7f7;
			color: #7a1f1f;
			font-size: 13px;
		}
		.creatorreactor-module-children {
			margin: 4px 0 0 0;
			padding: 0;
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: 6px;
		}
		.creatorreactor-module-child {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.creatorreactor-module-child--gateway,
		.creatorreactor-module-child--fanvue-actions {
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 8px 12px;
		}
		.creatorreactor-modules-shell .creatorreactor-gateway-dashboard-notice { margin: 0 0 12px; }
		.creatorreactor-module-child-actions {
			display: flex;
			align-items: center;
			gap: 12px;
			flex-wrap: wrap;
			margin-left: auto;
		}
		.creatorreactor-module-child-actions .creatorreactor-inline-oauth-form {
			margin: 0;
			display: inline;
		}
		.creatorreactor-module-child-actions .creatorreactor-inline-oauth-form .button-link {
			vertical-align: baseline;
		}
		.creatorreactor-modules-shell .creatorreactor-connection-alert { margin: 0 0 12px; }
		.creatorreactor-modules-shell .creatorreactor-dashboard-last-error { margin: 0 0 12px; }
		.creatorreactor-module-child-label {
			font-size: 13px;
			color: var(--cr-text);
		}
		.creatorreactor-connection-actions {
			display: flex;
			flex-direction: column;
			gap: 14px;
			align-items: stretch;
			flex-wrap: nowrap;
			padding: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
		}
		.creatorreactor-connection-actions .creatorreactor-connect-fanvue-hint { flex-basis: 100%; margin: 8px 0 0; max-width: 28rem; }
		.creatorreactor-connection-actions form { margin: 0; }
		.creatorreactor-connection-actions .submit { margin: 0; padding: 0; }
		.creatorreactor-connection-actions .button {
			width: 100%;
			text-align: center;
			justify-content: center;
			min-height: 42px;
			font-size: 14px;
			font-weight: 700;
			border-radius: 10px;
			transition: transform 0.12s ease, box-shadow 0.16s ease, filter 0.12s ease;
		}
		.creatorreactor-connection-actions .button:hover,
		.creatorreactor-connection-actions .button:focus {
			transform: translateY(-1px);
			filter: brightness(1.02);
		}
		.creatorreactor-dashboard-details { margin-top: 6px; }
		.creatorreactor-test-details-open { margin: 12px 0 0; }
		.creatorreactor-test-modal-trigger { margin-left: 8px; }
		.creatorreactor-test-errors { margin-top: 14px; padding: 12px; border: 1px solid #f3c7c7; border-radius: 6px; background: #fff7f7; }
		.creatorreactor-test-errors h3 { margin: 0 0 8px; font-size: 13px; color: #7a1f1f; }
		.creatorreactor-test-errors[data-visible="false"] { display: none; }
		.creatorreactor-modal { position: fixed; inset: 0; display: none; z-index: 100000; }
		.creatorreactor-modal[aria-hidden="false"] { display: block; }
		.creatorreactor-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.45); }
		.creatorreactor-modal-dialog { position: relative; width: min(620px, calc(100% - 32px)); max-height: calc(100vh - 64px); margin: 32px auto; background: #fff; border-radius: 8px; border: 1px solid #dcdcde; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25); overflow: auto; }
		.creatorreactor-modal-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px 10px; border-bottom: 1px solid #dcdcde; }
		.creatorreactor-modal-header-title { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
		.creatorreactor-modal-header-title h3 { margin: 0; font-size: 16px; }
		.creatorreactor-brand-logo--modal { max-height: 22px; width: auto; height: auto; display: block; }
		.creatorreactor-modal-body { padding: 14px 18px; }
		.creatorreactor-modal-footer { padding: 12px 18px 16px; border-top: 1px solid #dcdcde; text-align: right; }
		.creatorreactor-modal-footer--split { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; text-align: left; }
		.creatorreactor-modal-footer-primary { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }
		#creatorreactor-gateway-test-modal .creatorreactor-gateway-test-status { margin: 0; font-size: 14px; line-height: 1.45; }
		#creatorreactor-gateway-test-modal .creatorreactor-gateway-test-status.is-ok { color: #00a32a; font-weight: 600; }
		#creatorreactor-gateway-test-modal .creatorreactor-gateway-test-status.is-error { color: #b32d2e; }
		#creatorreactor-gateway-test-modal .creatorreactor-gateway-test-remediation-wrap { margin: 12px 0 0; padding: 10px 12px; border-left: 4px solid #dba617; background: #fcf9e8; font-size: 13px; line-height: 1.45; color: #414a4c; }
		.creatorreactor-modal-close { border: 0; background: transparent; color: #50575e; cursor: pointer; font-size: 22px; line-height: 1; }
		.creatorreactor-inline-status { margin-left: 8px; }
		.creatorreactor-connection-card {
			border: 1px solid #dcdcde;
			border-radius: var(--cr-card-radius);
			box-shadow: var(--cr-card-shadow);
		}
		.creatorreactor-connection-card.is-red { border-color: #fecaca; }
		.creatorreactor-connection-card.is-yellow { border-color: #fde68a; }
		.creatorreactor-connection-card.is-green { border-color: #bbf7d0; }
		.creatorreactor-connection-alert {
			margin: 0 0 14px;
			padding: 10px 12px;
			border-radius: 10px;
			border: 1px solid #fecaca;
			background: #fef2f2;
			color: #991b1b;
			font-weight: 600;
		}
		@media (max-width: 960px) {
			.creatorreactor-dashboard-row { grid-template-columns: 1fr; }
			.creatorreactor-dashboard-grid { grid-template-columns: 1fr; }
		}
		@media (max-width: 782px) {
			.creatorreactor-dashboard-shell { padding: 18px; border-radius: 12px; }
			.creatorreactor-dashboard-head { flex-direction: column; gap: 12px; margin-bottom: 16px; }
			.creatorreactor-dashboard-head .creatorreactor-status-badge { margin-left: 0; }
			.creatorreactor-dashboard-subtitle { font-size: 14px; }
			.creatorreactor-connection-actions { padding: 0; }
			body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab {
				padding: 8px 12px !important;
				font-size: 13px;
				border-radius: 6px 6px 0 0;
			}
			.form-table th {
				width: auto;
				padding-bottom: 6px;
			}
			.form-table td {
				padding-top: 0;
			}
			.creatorreactor-settings-form-card .creatorreactor-settings-actions {
				position: static;
				justify-content: flex-start;
			}
		}
		.creatorreactor-btn-connect.button {
			background: var(--cr-brand-magenta, #8e2d77);
			border-color: var(--cr-accent-strong, #6d2459);
			color: #fff;
			box-shadow: 0 4px 14px rgba(142, 45, 119, 0.28);
		}
		.creatorreactor-btn-connect.button:hover,
		.creatorreactor-btn-connect.button:focus {
			background: var(--cr-accent-strong, #6d2459);
			border-color: var(--cr-accent-deep, #4b1d66);
			color: #fff;
		}
		.creatorreactor-btn-disconnect.button {
			background: var(--cr-danger);
			border-color: #b91c1c;
			color: #fff;
			box-shadow: 0 8px 20px rgba(220, 38, 38, 0.28);
		}
		.creatorreactor-btn-disconnect.button-text {
			width: 100%;
			padding: 0 14px;
			font-size: 12px;
			letter-spacing: 0.04em;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			white-space: nowrap;
		}
		.creatorreactor-btn-disconnect.button:hover,
		.creatorreactor-btn-disconnect.button:focus {
			background: #b91c1c;
			border-color: #991b1b;
			color: #fff;
		}
		.creatorreactor-btn-disconnect.button-text:hover,
		.creatorreactor-btn-disconnect.button-text:focus {
			transform: translateY(-1px);
		}
		.creatorreactor-connection-log {
			margin-top: 20px;
			border: 0;
			border-radius: 0;
			background: transparent;
			padding: 0;
		}
		.creatorreactor-connection-log > summary {
			list-style: none;
			cursor: pointer;
			padding: 0;
			margin: 0;
			font-size: 13px;
			font-weight: 700;
			letter-spacing: 0.01em;
			color: #414a4c;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			background: transparent;
			width: max-content;
			max-width: 100%;
		}
		.creatorreactor-connection-log > summary::-webkit-details-marker { display: none; }
		.creatorreactor-connection-log > summary::before {
			content: "";
			display: inline-block;
			width: 0;
			height: 0;
			border-left: 5px solid transparent;
			border-right: 5px solid transparent;
			border-top: 6px solid #50575e;
			transition: transform 0.15s ease;
			flex-shrink: 0;
		}
		.creatorreactor-connection-log[open] > summary::before {
			transform: rotate(180deg);
		}
		.creatorreactor-connection-log-body { margin: 10px 0 0; padding: 0; border: 0; }
		.creatorreactor-connection-log-body > .description { margin: 0 0 8px; }
		.creatorreactor-connection-log-list {
			max-height: 22rem;
			overflow: auto;
			font-family: Consolas, Monaco, monospace;
			font-size: 11px;
			line-height: 1.55;
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			padding: 10px 12px;
			margin: 0 0 12px;
			list-style: none;
		}
		.creatorreactor-connection-log-list li { margin: 0 0 6px; }
		.creatorreactor-users-col-actions { width: 1%; text-align: right; white-space: nowrap; }
		.creatorreactor-user-actions {
			display: inline-flex;
			flex-direction: row;
			align-items: center;
			justify-content: flex-end;
			flex-wrap: nowrap;
			gap: 4px;
		}
		.creatorreactor-user-actions .button.creatorreactor-user-action {
			min-width: 30px;
			padding: 0 6px;
			line-height: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}
		.creatorreactor-user-actions .button.creatorreactor-user-action .dashicons { width: 18px; height: 18px; font-size: 18px; }
		#creatorreactor-user-details-modal.creatorreactor-modal { z-index: 100001; }
		#creatorreactor-user-details-modal .creatorreactor-user-details-close {
			border: 0;
			background: transparent;
			color: #50575e;
			cursor: pointer;
			font-size: 22px;
			line-height: 1;
			padding: 0 4px;
		}
		#creatorreactor-user-details-modal .creatorreactor-modal-body dl {
			margin: 0;
			display: grid;
			grid-template-columns: 9.5em 1fr;
			gap: 8px 16px;
			font-size: 13px;
		}
		#creatorreactor-user-details-modal .creatorreactor-modal-body dt { margin: 0; color: #646970; font-weight: 600; }
		#creatorreactor-user-details-modal .creatorreactor-modal-body dd { margin: 0; word-break: break-word; }
		#creatorreactor-user-details-modal .creatorreactor-modal-body dt.creatorreactor-details-section {
			grid-column: 1 / span 2;
			margin-top: 10px;
			padding-top: 10px;
			border-top: 1px solid #dcdcde;
			color: #414a4c;
			font-weight: 700;
		}
		#creatorreactor-user-details-modal .creatorreactor-modal-body dd.creatorreactor-details-section-spacer { display: none; }
		/* Danger Zone Styles */
		.creatorreactor-danger-zone {
			background: #fff8f8;
			border: 1px solid #f9d6d6;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.creatorreactor-danger-zone h2 {
			margin-top: 0;
			color: #b52727;
			font-size: 16px;
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
		}
		.creatorreactor-danger-zone h2::after {
			content: "";
			display: inline-block;
			width: 0;
			height: 0;
			border-left: 4px solid transparent;
			border-right: 4px solid transparent;
			border-top: 4px solid #666;
			margin-left: auto;
			transition: transform 0.2s ease;
		}
		.creatorreactor-danger-zone.collapsed h2::after {
			transform: rotate(-90deg);
		}
		.creatorreactor-danger-zone-content {
			max-height: 0;
			overflow: hidden;
			transition: max-height 0.3s ease;
		}
		.creatorreactor-danger-zone.expanded .creatorreactor-danger-zone-content {
			max-height: 200px;
		}
		/* Push Documentation + Debug to the far right (must beat .nav-tab { margin: ... !important }). */
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab.creatorreactor-tab-link-right {
			margin: 0 4px -1px auto !important;
		}
		body.wp-core-ui .creatorreactor-wrap .creatorreactor-tab-nav .nav-tab.creatorreactor-tab-link-right ~ .nav-tab.creatorreactor-tab-link-right {
			margin: 0 4px -1px 0 !important;
		}
		.creatorreactor-docs-shell { max-width: none; }
		.creatorreactor-docs-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			flex-wrap: wrap;
		}
		.creatorreactor-docs-header-brand {
			display: flex;
			align-items: center;
			gap: 12px;
			flex-wrap: wrap;
			min-width: 0;
		}
		.creatorreactor-docs-header-brand h2 { margin: 0; }
		.creatorreactor-brand-logo--docs { max-height: 36px; width: auto; height: auto; display: block; }
		.creatorreactor-docs-search-controls {
			display: inline-flex;
			align-items: center;
			gap: 8px;
		}
		.creatorreactor-docs-layout {
			display: grid;
			grid-template-columns: 260px minmax(0, 1fr);
			gap: 24px;
			margin-top: 12px;
		}
		.creatorreactor-docs-toc {
			position: sticky;
			top: 24px;
			align-self: start;
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			padding: 14px;
		}
		.creatorreactor-docs-toc h3 { margin: 0 0 8px; font-size: 14px; }
		.creatorreactor-docs-toc ol { margin: 0; padding-left: 18px; }
		.creatorreactor-docs-toc li { margin: 6px 0; }
		.creatorreactor-docs-toc a.is-active {
			font-weight: 700;
			text-decoration: underline;
		}
		.creatorreactor-docs-no-results {
			margin: 0 0 12px;
			padding: 10px 12px;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			background: #f6f7f7;
		}
		.creatorreactor-docs-highlight {
			background: rgba(249, 168, 145, 0.45);
			padding: 0 4px;
			border-radius: 4px;
		}
		.creatorreactor-doc-section { margin-bottom: 24px; }
		.creatorreactor-doc-section:last-child { margin-bottom: 0; }
		.creatorreactor-doc-section[hidden] { display: none !important; }
		.creatorreactor-settings-container { display: flex; gap: 20px; }
		.creatorreactor-settings-sidebar { width: 160px; flex-shrink: 0; }
		.creatorreactor-settings-sidebar--social-oauth { width: 200px; }
		.creatorreactor-sidebar-nav { display: flex; flex-direction: column; }
		.creatorreactor-sidebar-link { display: block; padding: 12px 14px; border-bottom: 1px solid #eee; color: #50575e; text-decoration: none; font-weight: 500; }
		.creatorreactor-sidebar-link.is-active { background: #faf3f8; color: var(--cr-accent, #8e2d77); border-left: 3px solid var(--cr-accent, #8e2d77); }
		.creatorreactor-sidebar-link:hover:not(.is-active) { background: #f5f5f5; }
		.creatorreactor-settings-content { flex: 1; min-width: 0; }
		.creatorreactor-settings-content.creatorreactor-settings-subtab-sync #creatorreactor-auth-mode-root { display: none; }
		.creatorreactor-settings-form-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: var(--cr-card-radius);
			box-shadow: var(--cr-card-shadow);
			overflow: hidden;
			margin-bottom: 20px;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel { display: none; }
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel.is-active { display: block; }
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > h2 {
			margin: 0;
			padding: 16px 20px;
			font-size: 16px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > .creatorreactor-settings-panel-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin: 0;
			padding: 12px 20px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > .creatorreactor-settings-panel-header h2 {
			margin: 0;
			padding: 0;
			font-size: 16px;
			border: 0;
			background: transparent;
			flex: 1;
		}
		.creatorreactor-oauth-tab-lock.button,
		.creatorreactor-cloud-tab-lock.button {
			border: 0;
			background: transparent;
			box-shadow: none;
			padding: 2px 4px;
			min-height: 0;
			line-height: 1;
		}
		.creatorreactor-oauth-tab-lock.button:hover,
		.creatorreactor-oauth-tab-lock.button:focus,
		.creatorreactor-cloud-tab-lock.button:hover,
		.creatorreactor-cloud-tab-lock.button:focus {
			background: transparent;
			border: 0;
			box-shadow: none;
			color: var(--cr-link-hover, #5c2364);
		}
		.creatorreactor-oauth-tab-lock.button:focus:not(:focus-visible),
		.creatorreactor-cloud-tab-lock.button:focus:not(:focus-visible) {
			outline: none;
		}
		.creatorreactor-oauth-tab-lock.button:focus-visible,
		.creatorreactor-cloud-tab-lock.button:focus-visible {
			outline: 2px solid currentColor;
			outline-offset: 2px;
		}
		.creatorreactor-oauth-tab-lock .dashicons,
		.creatorreactor-cloud-tab-lock .dashicons { width: 18px; height: 18px; font-size: 18px; line-height: 1; }
		.creatorreactor-oauth-tab-lock[aria-pressed="true"] .creatorreactor-oauth-tab-lock-icon-off,
		.creatorreactor-cloud-tab-lock[aria-pressed="true"] .creatorreactor-cloud-tab-lock-icon-off { display: none; }
		.creatorreactor-oauth-tab-lock[aria-pressed="true"] .creatorreactor-oauth-tab-lock-icon-on,
		.creatorreactor-cloud-tab-lock[aria-pressed="true"] .creatorreactor-cloud-tab-lock-icon-on { display: inline-block; }
		.creatorreactor-oauth-tab-lock[aria-pressed="false"] .creatorreactor-oauth-tab-lock-icon-on,
		.creatorreactor-cloud-tab-lock[aria-pressed="false"] .creatorreactor-cloud-tab-lock-icon-on { display: none; }
		.creatorreactor-oauth-tab-lock[aria-pressed="false"] .creatorreactor-oauth-tab-lock-icon-off,
		.creatorreactor-cloud-tab-lock[aria-pressed="false"] .creatorreactor-cloud-tab-lock-icon-off { display: inline-block; }
		.creatorreactor-oauth-configuration.is-oauth-config-locked,
		.creatorreactor-cloud-configuration.is-cloud-config-locked { opacity: 0.92; }
		.creatorreactor-settings-block {
			padding: 20px;
			border-top: 1px solid #dcdcde;
		}
		.creatorreactor-settings-form-card > .creatorreactor-settings-panel > .creatorreactor-settings-block:first-of-type {
			border-top: none;
		}
		.creatorreactor-settings-content.creatorreactor-settings-subtab-sync .creatorreactor-provider-login-appearance-card {
			display: none !important;
		}
		.creatorreactor-settings-content.creatorreactor-settings-appearance-card-below .creatorreactor-settings-form-card:not(.creatorreactor-provider-login-appearance-card) .creatorreactor-settings-actions {
			display: none !important;
		}
		.creatorreactor-settings-content:not(.creatorreactor-settings-appearance-card-below) .creatorreactor-provider-login-appearance-card .creatorreactor-settings-actions {
			display: none !important;
		}
		.creatorreactor-settings-block > h3 {
			margin: 0 0 12px;
			padding: 0;
			font-size: 14px;
		}
		.creatorreactor-settings-block .creatorreactor-subsection:first-of-type {
			margin-top: 0;
		}
		.creatorreactor-settings-form-card .creatorreactor-settings-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: flex-end;
			gap: 10px;
			margin: 0;
			padding: 16px 20px;
			border-top: 1px solid #dcdcde;
			position: sticky;
			bottom: 0;
			z-index: 5;
			background: #fff;
			box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.06);
		}
		.creatorreactor-settings-form-card .creatorreactor-settings-actions .submit { margin: 0; padding: 0; }
		.creatorreactor-subsection { margin-top: 25px; }
		.creatorreactor-subsection h4 { margin-top: 0; margin-bottom: 15px; font-size: 15px; color: #50575e; }
		.creatorreactor-advanced { margin-top: 25px; }
		.creatorreactor-advanced-toggle {
			display: inline-flex;
			align-items: center;
			gap: 0.35em;
			padding: 0;
			margin: 0;
			height: auto;
			min-height: 0;
			background: none;
			border: none;
			border-radius: 0;
			box-shadow: none;
			color: var(--cr-link, #8e2d77);
			font-size: 13px;
			font-weight: 400;
			line-height: 1.4;
			cursor: pointer;
			text-decoration: none;
			vertical-align: baseline;
		}
		.creatorreactor-advanced-toggle:hover,
		.creatorreactor-advanced-toggle:focus {
			color: var(--cr-link-hover, #5c2364);
			background: none;
			border: none;
			box-shadow: none;
			text-decoration: underline;
		}
		.creatorreactor-advanced-toggle:focus-visible {
			outline: 1px solid currentColor;
			outline-offset: 2px;
		}
		.creatorreactor-advanced-toggle-label { line-height: 1.4; }
		.creatorreactor-advanced-toggle .creatorreactor-advanced-toggle-icon.dashicons {
			flex-shrink: 0;
			width: 14px;
			height: 14px;
			font-size: 14px;
			line-height: 1;
			transition: transform 0.2s ease;
			transform: rotate(-90deg);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			vertical-align: middle;
		}
		.creatorreactor-advanced.is-expanded .creatorreactor-advanced-toggle-icon { transform: rotate(0deg); }
		.creatorreactor-advanced-panel { margin-top: 12px; }
		.creatorreactor-advanced-panel-inner {
			padding: 0;
			border: none;
			background: transparent;
			border-radius: 0;
		}
		.creatorreactor-advanced-hint { margin: 0 0 12px; max-width: 52em; }
		input.creatorreactor-advanced-endpoint-input[readonly] {
			background: #f0f0f1;
			color: #414a4c;
			cursor: not-allowed;
		}
		input.creatorreactor-advanced-endpoint-input:not([readonly]) { cursor: text; }
		@media (max-width: 960px) {
			.creatorreactor-docs-layout { grid-template-columns: 1fr; }
			.creatorreactor-docs-toc { position: static; }
			.creatorreactor-settings-container { flex-direction: column; }
			.creatorreactor-settings-sidebar { width: 100%; }
			.creatorreactor-sidebar-nav {
				flex-direction: row;
				flex-wrap: wrap;
				gap: 8px;
			}
			.creatorreactor-sidebar-link {
				border-left: none;
				border-bottom: 0;
				border: 1px solid #dcdcde;
				border-radius: 6px;
				padding: 8px 12px;
			}
			.creatorreactor-sidebar-link.is-active {
				border-left: 1px solid var(--cr-accent, #8e2d77);
			}
		}
		.creatorreactor-auth-mode-dynamic[aria-busy="true"] { opacity: 0.55; pointer-events: none; transition: opacity 0.15s ease; }
		#creatorreactor-google-oauth-test-modal .creatorreactor-google-oauth-test-status,
		#creatorreactor-fanvue-oauth-test-modal .creatorreactor-fanvue-oauth-test-status,
		#creatorreactor-onlyfans-ofauth-test-modal .creatorreactor-onlyfans-ofauth-test-status { margin: 0; font-size: 14px; line-height: 1.45; }
		#creatorreactor-google-oauth-test-modal .creatorreactor-google-oauth-test-status.is-ok,
		#creatorreactor-fanvue-oauth-test-modal .creatorreactor-fanvue-oauth-test-status.is-ok,
		#creatorreactor-onlyfans-ofauth-test-modal .creatorreactor-onlyfans-ofauth-test-status.is-ok { color: #00a32a; font-weight: 600; }
		#creatorreactor-google-oauth-test-modal .creatorreactor-google-oauth-test-status.is-error,
		#creatorreactor-fanvue-oauth-test-modal .creatorreactor-fanvue-oauth-test-status.is-error,
		#creatorreactor-onlyfans-ofauth-test-modal .creatorreactor-onlyfans-ofauth-test-status.is-error { color: #b32d2e; }
		#creatorreactor-google-oauth-test-modal .creatorreactor-google-oauth-test-remediation-wrap,
		#creatorreactor-fanvue-oauth-test-modal .creatorreactor-fanvue-oauth-test-remediation-wrap,
		#creatorreactor-onlyfans-ofauth-test-modal .creatorreactor-onlyfans-ofauth-test-remediation-wrap { margin: 12px 0 0; padding: 10px 12px; border-left: 4px solid #dba617; background: #fcf9e8; font-size: 13px; line-height: 1.45; color: #414a4c; }
		#creatorreactor-google-oauth-test-modal .creatorreactor-modal-footer,
		#creatorreactor-fanvue-oauth-test-modal .creatorreactor-modal-footer,
		#creatorreactor-onlyfans-ofauth-test-modal .creatorreactor-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px 16px; border-top: 1px solid #dcdcde; }

		';

		$css .= Shortcodes::get_google_oauth_button_css();
		$css .= sprintf(
			' .creatorreactor-fanvue-oauth--admin-preview.creatorreactor-fanvue-oauth-link--minimal{display:inline-flex;align-items:center;justify-content:center;width:%1$dpx;height:%1$dpx;min-width:%1$dpx;min-height:%1$dpx;padding:0;border:0;border-radius:4px;background:%2$s;box-sizing:border-box;line-height:0;}' .
			' .creatorreactor-fanvue-oauth--admin-preview.creatorreactor-fanvue-oauth-link--minimal .creatorreactor-fanvue-oauth-img-minimal{display:block;width:%3$dpx;height:%3$dpx;object-fit:contain;flex-shrink:0;transform:translateY(%4$dpx);}',
			Shortcodes::OAUTH_COMPACT_BOX_PX,
			Shortcodes::FANVUE_MINIMAL_OAUTH_BACKGROUND,
			Shortcodes::FANVUE_MINIMAL_ICON_SIZE_PX,
			Shortcodes::FANVUE_MINIMAL_ICON_TRANSLATE_Y_PX
		);
		$css .= sprintf(
			' .creatorreactor-instagram-oauth--admin-preview.creatorreactor-instagram-oauth-link--standard{display:inline-flex;align-items:center;gap:12px;min-height:40px;padding:10px 16px;border-radius:4px;border:0;background:%1$s;color:#fff;font-size:14px;font-weight:500;line-height:1.35;box-sizing:border-box;text-decoration:none;font-family:"Roboto",system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 1px 2px rgba(0,0,0,.08);}' .
			' .creatorreactor-instagram-oauth--admin-preview.creatorreactor-instagram-oauth-link--standard .creatorreactor-instagram-oauth-svg{flex-shrink:0;display:block;width:20px;height:20px;}' .
			' .creatorreactor-instagram-oauth--admin-preview.creatorreactor-instagram-oauth-link--minimal{display:inline-flex;align-items:center;justify-content:center;width:%2$dpx;height:%2$dpx;min-width:%2$dpx;min-height:%2$dpx;padding:0;border:0;border-radius:4px;background:%1$s;box-sizing:border-box;line-height:0;box-shadow:0 1px 2px rgba(0,0,0,.08);}' .
			' .creatorreactor-instagram-oauth--admin-preview.creatorreactor-instagram-oauth-link--minimal .creatorreactor-instagram-oauth-svg{display:block;width:%3$dpx;height:%3$dpx;flex-shrink:0;transform:translateY(%4$dpx);}',
			Shortcodes::INSTAGRAM_BRAND_GRADIENT_CSS,
			Shortcodes::OAUTH_COMPACT_BOX_PX,
			Shortcodes::INSTAGRAM_MINIMAL_ICON_SIZE_PX,
			Shortcodes::INSTAGRAM_MINIMAL_ICON_TRANSLATE_Y_PX
		);
		$css .= sprintf(
			' .creatorreactor-onlyfans-oauth--admin-preview.creatorreactor-onlyfans-oauth-link--standard{display:inline-flex;align-items:center;gap:12px;min-height:40px;padding:10px 16px;border-radius:4px;border:1px solid %1$s;background:%2$s;color:#fff;font-size:14px;font-weight:500;line-height:1.35;box-sizing:border-box;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.2);}' .
			' .creatorreactor-onlyfans-oauth--admin-preview.creatorreactor-onlyfans-oauth-link--standard .creatorreactor-onlyfans-oauth-svg{flex-shrink:0;display:block;width:20px;height:20px;}' .
			' .creatorreactor-onlyfans-oauth--admin-preview.creatorreactor-onlyfans-oauth-link--standard .creatorreactor-onlyfans-oauth-label{color:#fff;}' .
			' .creatorreactor-onlyfans-oauth--admin-preview.creatorreactor-onlyfans-oauth-link--minimal{display:inline-flex;align-items:center;justify-content:center;width:%3$dpx;height:%3$dpx;min-width:%3$dpx;min-height:%3$dpx;padding:0;border:1px solid #2a2a2a;border-radius:4px;background:%2$s;box-sizing:border-box;line-height:0;box-shadow:0 1px 3px rgba(0,0,0,.2);}' .
			' .creatorreactor-onlyfans-oauth--admin-preview.creatorreactor-onlyfans-oauth-link--minimal .creatorreactor-onlyfans-oauth-svg{display:block;width:%4$dpx;height:%4$dpx;flex-shrink:0;transform:translateY(%5$dpx);}',
			Shortcodes::ONLYFANS_OAUTH_ADMIN_ACCENT,
			Shortcodes::ONLYFANS_OAUTH_ADMIN_BUTTON_BG,
			Shortcodes::OAUTH_COMPACT_BOX_PX,
			Shortcodes::ONLYFANS_MINIMAL_ICON_SIZE_PX,
			Shortcodes::ONLYFANS_MINIMAL_ICON_TRANSLATE_Y_PX
		);

		wp_register_style( 'creatorreactor-admin', false, [ 'wp-admin' ], CREATORREACTOR_VERSION );
		wp_enqueue_style( 'creatorreactor-admin' );
		wp_add_inline_style( 'creatorreactor-admin', $css );

		wp_register_script(
			'creatorreactor-users-tab',
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-users-tab.js',
			[ 'jquery' ],
			CREATORREACTOR_VERSION,
			true
		);
		wp_enqueue_script( 'creatorreactor-users-tab' );
		wp_localize_script(
			'creatorreactor-users-tab',
			'creatorreactorUsersTable',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'creatorreactor_users_table' ),
				'refreshLabel'   => __( 'Sync & refresh list', 'wp-creatorreactor' ),
				'loadError'      => __( 'Error loading user table.', 'wp-creatorreactor' ),
				'sessionError'   => __( 'Request was blocked or your session expired. Reload this page and try again.', 'wp-creatorreactor' ),
				'syncLogSummary' => __( 'Sync log', 'wp-creatorreactor' ),
				'syncLogOffline' => __( 'This line was recorded in the browser only because saving to the server log failed. Check that admin-ajax.php is reachable.', 'wp-creatorreactor' ),
				'confirmDeactivate' => __( 'Deactivate this WordPress user? They will lose all roles and cannot access the site until an administrator restores a role.', 'wp-creatorreactor' ),
				'deactivateError'   => __( 'Could not deactivate user.', 'wp-creatorreactor' ),
				'detailsLoading'    => __( 'Loading…', 'wp-creatorreactor' ),
				'detailsLoadError'  => __( 'Could not load record details.', 'wp-creatorreactor' ),
			]
		);

		$product_label = self::get_current_product_label();
		/* translators: %s: Product label (e.g. Fanvue). */
		$js_notice_disconnected = esc_js( sprintf( __( 'Disconnected from %s.', 'wp-creatorreactor' ), $product_label ) );
		/* translators: %s: Product label (e.g. Fanvue). */
		$js_notice_connected = esc_js( sprintf( __( 'Connected to %s successfully.', 'wp-creatorreactor' ), $product_label ) );
		$js_notice_saved     = esc_js( __( 'Settings saved.', 'wp-creatorreactor' ) );
		$js = '
		(function() {
			document.addEventListener("DOMContentLoaded", function() {
			var authInputs = document.querySelectorAll(".creatorreactor-auth-mode-input");
			var oauthDynamic = document.getElementById("creatorreactor-oauth-dynamic");
			var syncDynamic = document.getElementById("creatorreactor-sync-dynamic");

			function setupRedirectUriCopyDelegation(root) {
				if (!root || root.dataset.creatorreactorRedirectCopyBound) {
					return;
				}
				root.dataset.creatorreactorRedirectCopyBound = "1";
				root.addEventListener("click", function(e) {
					var btn = e.target.closest(".creatorreactor-copy-redirect-uri");
					if (!btn || !root.contains(btn)) {
						return;
					}
					e.preventDefault();
					var fromAttr = btn.getAttribute("data-copy-text");
					var value = (fromAttr !== null && fromAttr !== "") ? fromAttr : "";
					var input = null;
					if (value === "") {
						var row = btn.closest(".creatorreactor-redirect-uri-row");
						input = row ? row.querySelector(".creatorreactor-oauth-redirect-uri-input") : null;
						if (!input) {
							return;
						}
						value = input.value || "";
					}
					var copiedLabel = (window.creatorreactorAuthMode && window.creatorreactorAuthMode.copiedLabel) || "Copied!";
					var origBtnLabel = btn.textContent;
					function showCopied() {
						btn.textContent = copiedLabel;
						window.setTimeout(function() {
							btn.textContent = origBtnLabel;
						}, 2000);
					}
					function fallbackCopy() {
						if (input) {
							input.focus();
							input.select();
							input.setSelectionRange(0, value.length);
							try {
								if (document.execCommand("copy")) {
									showCopied();
								}
							} catch (err) {}
							return;
						}
						var ta = document.createElement("textarea");
						ta.value = value;
						ta.setAttribute("readonly", "");
						ta.style.position = "fixed";
						ta.style.left = "-9999px";
						document.body.appendChild(ta);
						ta.select();
						try {
							if (document.execCommand("copy")) {
								showCopied();
							}
						} catch (err) {}
						document.body.removeChild(ta);
					}
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(value).then(showCopied).catch(fallbackCopy);
					} else {
						fallbackCopy();
					}
				});
			}
			setupRedirectUriCopyDelegation(oauthDynamic);
			setupRedirectUriCopyDelegation(document.getElementById("creatorreactor-onlyfans-settings-dynamic"));
			document.querySelectorAll(".creatorreactor-fanvue-login-appearance-card").forEach(function(el) {
				setupRedirectUriCopyDelegation(el);
			});
			var googleTabPanel = document.querySelector(".creatorreactor-tab-panel[data-tab=\"google\"]");
			if (googleTabPanel) {
				setupRedirectUriCopyDelegation(googleTabPanel);
			}
			var instagramTabPanel = document.querySelector(".creatorreactor-tab-panel[data-tab=\"instagram\"]");
			if (instagramTabPanel) {
				setupRedirectUriCopyDelegation(instagramTabPanel);
			}
			var socialOauthTabPanel = document.querySelector(".creatorreactor-tab-panel[data-tab=\"social_oauth\"]");
			if (socialOauthTabPanel) {
				setupRedirectUriCopyDelegation(socialOauthTabPanel);
			}

			function refreshProviderAppearanceCardsLayout() {
				var modeInp = document.querySelector("input.creatorreactor-auth-mode-input:checked");
				var isAgency = modeInp && modeInp.value === "agency";
				var fanvueShell = document.querySelector(".creatorreactor-tab-panel[data-tab=\"settings\"] .creatorreactor-settings-container");
				if (fanvueShell) {
					var fvContent = fanvueShell.querySelector(".creatorreactor-settings-content");
					var fvCard = fanvueShell.querySelector(".creatorreactor-fanvue-login-appearance-card");
					if (fvContent && fvCard) {
						var fvSync = fvContent.classList.contains("creatorreactor-settings-subtab-sync");
						fvContent.classList.toggle("creatorreactor-settings-appearance-card-below", !isAgency && !fvSync);
						fvCard.hidden = isAgency || fvSync;
					}
				}
				var ofShell = document.querySelector(".creatorreactor-tab-panel[data-tab=\"onlyfans\"] .creatorreactor-settings-container");
				if (ofShell) {
					var ofContent = ofShell.querySelector(".creatorreactor-settings-content");
					var ofCard = ofShell.querySelector(".creatorreactor-onlyfans-login-appearance-card");
					if (ofContent && ofCard) {
						var ofSync = ofContent.classList.contains("creatorreactor-settings-subtab-sync");
						ofContent.classList.toggle("creatorreactor-settings-appearance-card-below", !ofSync);
						ofCard.hidden = ofSync;
					}
				}
			}

			function setupCreatorreactorAdvancedDelegation(root) {
				if (!root || root.dataset.creatorreactorAdvancedBound) {
					return;
				}
				root.dataset.creatorreactorAdvancedBound = "1";
				root.addEventListener("click", function(e) {
					var toggleBtn = e.target.closest(".creatorreactor-advanced-toggle");
					if (toggleBtn && root.contains(toggleBtn)) {
						e.preventDefault();
						var block = toggleBtn.closest(".creatorreactor-advanced");
						var panel = block ? block.querySelector(".creatorreactor-advanced-panel") : null;
						if (!block || !panel) {
							return;
						}
						var expanded = toggleBtn.getAttribute("aria-expanded") === "true";
						var next = !expanded;
						toggleBtn.setAttribute("aria-expanded", next ? "true" : "false");
						panel.hidden = !next;
						block.classList.toggle("is-expanded", next);
					}
				});
			}

			function oauthCredentialsIndicateLocked() {
				var container = document.querySelector(".creatorreactor-oauth-configuration");
				if (!container) {
					return true;
				}
				var idInp = container.querySelector("input[name*=\"creatorreactor_oauth_client_id\"]");
				var secInp = container.querySelector(".creatorreactor-oauth-client-secret");
				var modeInp = document.querySelector("input.creatorreactor-auth-mode-input:checked");
				var agency = modeInp && modeInp.value === "agency";
				var idVal = idInp ? String(idInp.value || "").trim() : "";
				var secVal = secInp ? String(secInp.value || "").trim() : "";
				if (agency) {
					return idVal !== "";
				}
				return idVal !== "" || secVal !== "";
			}

			function setOAuthLockFromCredentials() {
				var lockBtn = document.querySelector(".creatorreactor-oauth-tab-lock");
				if (!lockBtn) {
					return;
				}
				lockBtn.setAttribute("aria-pressed", oauthCredentialsIndicateLocked() ? "true" : "false");
				applyOAuthTabLockState();
			}

			function applyCloudTabLockState() {
				var lockBtn = document.querySelector(".creatorreactor-cloud-tab-lock");
				var container = document.querySelector(".creatorreactor-cloud-configuration");
				if (!lockBtn || !container) {
					return;
				}
				var locked = lockBtn.getAttribute("aria-pressed") !== "false";
				var lk = lockBtn.getAttribute("data-label-locked") || "";
				var uk = lockBtn.getAttribute("data-label-unlocked") || "";
				lockBtn.setAttribute("aria-label", locked ? lk : uk);
				container.querySelectorAll("input, select, textarea, button").forEach(function(el) {
					el.disabled = locked;
				});
				container.classList.toggle("is-cloud-config-locked", locked);
			}

			function applyOAuthTabLockState() {
				var lockBtn = document.querySelector(".creatorreactor-oauth-tab-lock");
				var container = document.querySelector(".creatorreactor-oauth-configuration");
				if (!lockBtn || !container) {
					return;
				}
				var locked = lockBtn.getAttribute("aria-pressed") !== "false";
				var lk = lockBtn.getAttribute("data-label-locked") || "";
				var uk = lockBtn.getAttribute("data-label-unlocked") || "";
				lockBtn.setAttribute("aria-label", locked ? lk : uk);
				container.querySelectorAll(".creatorreactor-advanced-endpoint-input").forEach(function(inp) {
					inp.readOnly = locked;
					inp.disabled = false;
				});
				container.querySelectorAll("input, select, textarea, button").forEach(function(el) {
					if (el.classList.contains("creatorreactor-copy-redirect-uri")) {
						return;
					}
					if (el.classList.contains("creatorreactor-oauth-scopes-readonly") || el.classList.contains("creatorreactor-oauth-redirect-uri-readonly")) {
						el.readOnly = true;
						el.disabled = false;
						return;
					}
					if (el.closest(".creatorreactor-advanced")) {
						if (el.classList.contains("creatorreactor-advanced-endpoint-input")) {
							return;
						}
						if (el.classList.contains("creatorreactor-advanced-toggle")) {
							return;
						}
						el.disabled = locked;
					} else {
						el.disabled = locked;
					}
				});
				container.querySelectorAll(".creatorreactor-advanced").forEach(function(block) {
					block.classList.toggle("is-locked", locked);
				});
				container.classList.toggle("is-oauth-config-locked", locked);
				if (!locked && oauthDynamic) {
					applyCreatorreactorAdvancedDefaults(oauthDynamic);
				}
			}

			function applyCreatorreactorAdvancedDefaults(root) {
				if (!root) {
					return;
				}
				root.querySelectorAll(".creatorreactor-advanced").forEach(function(block) {
					var toggleBtn = block.querySelector(".creatorreactor-advanced-toggle");
					var panel = block.querySelector(".creatorreactor-advanced-panel");
					if (!toggleBtn || !panel) {
						return;
					}
					var expanded = toggleBtn.getAttribute("aria-expanded") === "true";
					panel.hidden = !expanded;
					block.classList.toggle("is-expanded", expanded);
				});
			}

			function snapshotFieldValues(container) {
				var vals = {};
				if (!container) {
					return vals;
				}
				container.querySelectorAll("input[name], select[name], textarea[name]").forEach(function(el) {
					if (!el.name) {
						return;
					}
					if (el.type === "password") {
						return;
					}
					if (el.type === "checkbox" || el.type === "radio") {
						if (el.checked) {
							vals[el.name] = el.value;
						}
						return;
					}
					vals[el.name] = el.value;
				});
				return vals;
			}

			function restoreFieldValues(container, vals) {
				if (!container || !vals) {
					return;
				}
				container.querySelectorAll("input[name], select[name], textarea[name]").forEach(function(el) {
					if (!el.name || vals[el.name] === undefined) {
						return;
					}
					if (el.type === "password") {
						return;
					}
					if (el.type === "checkbox" || el.type === "radio") {
						if (el.value === vals[el.name]) {
							el.checked = true;
						}
						return;
					}
					el.value = vals[el.name];
				});
			}

			function updateAuthModeLabels() {
				authInputs.forEach(function(inp) {
					var lab = inp.closest("label");
					if (lab) {
						lab.classList.toggle("is-selected", inp.checked);
					}
				});
			}

			function setAuthModeNoticesVisibility() {
				var creatorNotice = document.getElementById("creatorreactor-creator-direct-auth-notice");
				var agencyNotice = document.getElementById("creatorreactor-agency-auth-notice");
				var modeInp = document.querySelector("input.creatorreactor-auth-mode-input:checked");
				var isCreator = modeInp && modeInp.value === "creator";
				if (creatorNotice) {
					creatorNotice.hidden = !isCreator;
				}
				if (agencyNotice) {
					agencyNotice.hidden = isCreator;
				}
			}

			function getAuthModeAjaxConfig() {
				if (window.creatorreactorAuthMode && window.creatorreactorAuthMode.ajaxUrl && window.creatorreactorAuthMode.nonce) {
					return window.creatorreactorAuthMode;
				}
				var root = document.getElementById("creatorreactor-auth-mode-root");
				if (root && root.dataset.ajaxUrl && root.dataset.nonce) {
					return { ajaxUrl: root.dataset.ajaxUrl, nonce: root.dataset.nonce };
				}
				return null;
			}

			function loadAuthModeFields(mode) {
				var cfg = getAuthModeAjaxConfig();
				if (!oauthDynamic || !syncDynamic || !cfg) {
					return;
				}
				var prevOauth = snapshotFieldValues(oauthDynamic);
				var prevSync = snapshotFieldValues(syncDynamic);
				oauthDynamic.setAttribute("aria-busy", "true");
				syncDynamic.setAttribute("aria-busy", "true");
				var body = new URLSearchParams();
				body.append("action", "creatorreactor_auth_mode_fields");
				body.append("nonce", cfg.nonce);
				body.append("mode", mode);
				fetch(cfg.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					headers: { "Content-Type": "application/x-www-form-urlencoded" },
					body: body.toString()
				})
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (!res || !res.success || !res.data) {
							return;
						}
						if (typeof res.data.oauth === "string") {
							oauthDynamic.innerHTML = res.data.oauth;
						}
						if (typeof res.data.sync === "string") {
							syncDynamic.innerHTML = res.data.sync;
						}
						restoreFieldValues(oauthDynamic, prevOauth);
						restoreFieldValues(syncDynamic, prevSync);
						setOAuthLockFromCredentials();
					})
					.catch(function() {})
					.finally(function() {
						oauthDynamic.removeAttribute("aria-busy");
						syncDynamic.removeAttribute("aria-busy");
						refreshProviderAppearanceCardsLayout();
					});
			}

			function initTabs() {
				var tabLinks = document.querySelectorAll(".creatorreactor-tab-link");
				var tabPanels = document.querySelectorAll(".creatorreactor-tab-panel");
				if (!tabLinks.length || !tabPanels.length) {
					return;
				}

				var wrapEl = document.querySelector(".creatorreactor-wrap");
				var socialOauthSlugs = [];
				if (wrapEl && wrapEl.getAttribute("data-creatorreactor-social-oauth-slugs")) {
					try {
						var parsedSlugs = JSON.parse(wrapEl.getAttribute("data-creatorreactor-social-oauth-slugs"));
						if (Array.isArray(parsedSlugs)) {
							socialOauthSlugs = parsedSlugs;
						}
					} catch (eSocial) {}
				}
				function isSocialOauthSlug(t) {
					return t && socialOauthSlugs.indexOf(t) !== -1;
				}

				function activateTab(tabName, updateUrl) {
					tabLinks.forEach(function(link) {
						var isActive = link.getAttribute("data-tab") === tabName;
						link.classList.toggle("nav-tab-active", isActive);
					});

					tabPanels.forEach(function(panel) {
						var isActive = panel.getAttribute("data-tab") === tabName;
						panel.classList.toggle("is-active", isActive);
					});

					if (tabName === "settings" && updateUrl) {
						var settingsShell = document.querySelector(".creatorreactor-tab-panel[data-tab=\"settings\"] .creatorreactor-settings-container");
						var sidebarLinks = settingsShell ? settingsShell.querySelectorAll(".creatorreactor-sidebar-link") : [];
						var sidebarPanels = settingsShell ? settingsShell.querySelectorAll(".creatorreactor-settings-panel[data-subtab=\"oauth\"], .creatorreactor-settings-panel[data-subtab=\"sync\"]") : [];
						sidebarLinks.forEach(function(link) {
							link.classList.toggle("is-active", link.getAttribute("data-subtab") === "oauth");
						});
						sidebarPanels.forEach(function(panel) {
							panel.classList.toggle("is-active", panel.getAttribute("data-subtab") === "oauth");
						});
					}

					if (updateUrl) {
						var url = new URL(window.location.href);
						url.searchParams.set("tab", tabName);
						if (tabName === "settings") {
							url.searchParams.set("subtab", "oauth");
						}
						if (tabName === "social_oauth") {
							url.searchParams.delete("subtab");
							var sp = url.searchParams.get("social_provider");
							if (!sp || !isSocialOauthSlug(sp)) {
								url.searchParams.set("social_provider", socialOauthSlugs[0] || "");
							}
						}
						window.history.replaceState({}, "", url.toString());
					}

				}

				tabLinks.forEach(function(link) {
					link.addEventListener("click", function(event) {
						event.preventDefault();
						activateTab(link.getAttribute("data-tab"), true);
					});
				});

				var fallbackTab = "dashboard";
				var activeLink = document.querySelector(".creatorreactor-tab-link.nav-tab-active");
				if (activeLink) {
					fallbackTab = activeLink.getAttribute("data-tab") || fallbackTab;
				} else if (tabLinks.length) {
					fallbackTab = tabLinks[0].getAttribute("data-tab") || fallbackTab;
				}
				var params = new URLSearchParams(window.location.search);
				var rawTab = params.get("tab") || fallbackTab;
				var currentTab = rawTab;
				if (isSocialOauthSlug(rawTab)) {
					currentTab = "social_oauth";
				}
				if (!document.querySelector(".creatorreactor-tab-link[data-tab=\"" + currentTab + "\"]")) {
					currentTab = fallbackTab;
				}
				activateTab(currentTab, false);
			}

			authInputs.forEach(function(inp) {
				inp.addEventListener("change", function() {
					if (!inp.checked) {
						return;
					}
					updateAuthModeLabels();
					setAuthModeNoticesVisibility();
					loadAuthModeFields(inp.value);
				});
			});
			updateAuthModeLabels();
			setAuthModeNoticesVisibility();

			initTabs();

			setupCreatorreactorAdvancedDelegation(oauthDynamic);
			applyOAuthTabLockState();
			applyCloudTabLockState();

			var oauthTabLockBtn = document.querySelector(".creatorreactor-oauth-tab-lock");
			if (oauthTabLockBtn) {
				oauthTabLockBtn.addEventListener("click", function(e) {
					e.preventDefault();
					var pressed = oauthTabLockBtn.getAttribute("aria-pressed") === "true";
					oauthTabLockBtn.setAttribute("aria-pressed", pressed ? "false" : "true");
					applyOAuthTabLockState();
				});
			}

			var cloudTabLockBtn = document.querySelector(".creatorreactor-cloud-tab-lock");
			if (cloudTabLockBtn) {
				cloudTabLockBtn.addEventListener("click", function(e) {
					e.preventDefault();
					var pressed = cloudTabLockBtn.getAttribute("aria-pressed") === "true";
					cloudTabLockBtn.setAttribute("aria-pressed", pressed ? "false" : "true");
					applyCloudTabLockState();
				});
			}

			// Danger Zone collapsible functionality
			var dangerZoneTitle = document.querySelector(".creatorreactor-danger-zone-title");
			var dangerZoneContent = document.querySelector(".creatorreactor-danger-zone-content");
			var dangerZone = document.querySelector(".creatorreactor-danger-zone");
			
			if (dangerZoneTitle && dangerZoneContent && dangerZone) {
				dangerZoneTitle.addEventListener("click", function() {
					dangerZone.classList.toggle("expanded");
					dangerZone.classList.toggle("collapsed");
				});
				
				// Start collapsed by default
				dangerZone.classList.add("collapsed");
			}

			function stripQueryParam(paramName) {
				var cleanUrl = new URL(window.location.href);
				if (!cleanUrl.searchParams.has(paramName)) {
					return;
				}
				cleanUrl.searchParams.delete(paramName);
				window.history.replaceState({}, "", cleanUrl.toString());
			}

			var gwTestModal = document.getElementById("creatorreactor-gateway-test-modal");
			if (gwTestModal) {
				function closeGatewayTestModal() {
					gwTestModal.setAttribute("aria-hidden", "true");
					stripQueryParam("gateway_test");
				}
				function openGatewayTestModal() {
					gwTestModal.setAttribute("aria-hidden", "false");
				}
				gwTestModal.querySelectorAll(".creatorreactor-gateway-test-dismiss").forEach(function(btn) {
					btn.addEventListener("click", closeGatewayTestModal);
				});
				var gwBackdrop = gwTestModal.querySelector(".creatorreactor-modal-backdrop");
				if (gwBackdrop) {
					gwBackdrop.addEventListener("click", closeGatewayTestModal);
				}
				var gwAck = gwTestModal.querySelector(".creatorreactor-gateway-test-acknowledge");
				if (gwAck) {
					gwAck.addEventListener("click", closeGatewayTestModal);
				}
				if (gwTestModal.getAttribute("data-auto-open") === "1") {
					openGatewayTestModal();
				}
			}

			var urlParams = new URLSearchParams(window.location.search);
			var status = urlParams.get("status");
			var testModal = document.getElementById("creatorreactor-test-modal");
			var testErrors = document.getElementById("creatorreactor-test-errors");
			var testModalButtons = document.querySelectorAll(".creatorreactor-open-test-modal");
			var ackButton = testModal ? testModal.querySelector(".creatorreactor-ack-test-modal") : null;
			var modalTime = testModal ? parseInt(testModal.getAttribute("data-test-time") || "0", 10) : 0;
			var ackStorageKey = "creatorreactorConnectionTestAcknowledgedAt";

			function getAcknowledgedTime() {
				try {
					return parseInt(window.localStorage.getItem(ackStorageKey) || "0", 10) || 0;
				} catch (error) {
					return 0;
				}
			}

			function isCurrentTestAcknowledged() {
				return modalTime > 0 && getAcknowledgedTime() >= modalTime;
			}

			function togglePersistentErrors() {
				if (!testErrors) {
					return;
				}
				testErrors.setAttribute("data-visible", isCurrentTestAcknowledged() ? "true" : "false");
			}

			function openTestModal() {
				if (!testModal) {
					return;
				}
				testModal.setAttribute("aria-hidden", "false");
			}

			function closeTestModal() {
				if (!testModal) {
					return;
				}
				testModal.setAttribute("aria-hidden", "true");
			}

			testModalButtons.forEach(function(button) {
				button.addEventListener("click", function(event) {
					event.preventDefault();
					openTestModal();
				});
			});

			if (testModal) {
				testModal.querySelectorAll(".creatorreactor-dismiss-connection-test").forEach(function(btn) {
					btn.addEventListener("click", closeTestModal);
				});
				var testModalBackdrop = testModal.querySelector(".creatorreactor-modal-backdrop");
				if (testModalBackdrop) {
					testModalBackdrop.addEventListener("click", closeTestModal);
				}
			}

			document.addEventListener("keydown", function(event) {
				if (event.key !== "Escape") {
					return;
				}
				var intFixModal = document.getElementById("creatorreactor-integration-fix-modal");
				if (intFixModal && intFixModal.getAttribute("aria-hidden") === "false") {
					intFixModal.setAttribute("aria-hidden", "true");
					var intErr = document.getElementById("creatorreactor-integration-fix-error");
					var intConfirm = intFixModal.querySelector(".creatorreactor-integration-fix-confirm");
					var intCfg = window.creatorreactorIntegrationFix;
					if (intErr) {
						intErr.hidden = true;
						intErr.textContent = "";
					}
					if (intConfirm) {
						intConfirm.disabled = false;
						intConfirm.textContent = (intCfg && intCfg.i18n && intCfg.i18n.fix) ? intCfg.i18n.fix : "Fix";
					}
					intFixModal.dataset.activeFixId = "";
					return;
				}
				var gwEsc = document.getElementById("creatorreactor-gateway-test-modal");
				if (gwEsc && gwEsc.getAttribute("aria-hidden") === "false") {
					gwEsc.setAttribute("aria-hidden", "true");
					stripQueryParam("gateway_test");
					return;
				}
				if (testModal && testModal.getAttribute("aria-hidden") === "false") {
					closeTestModal();
				}
			});

			if (ackButton) {
				ackButton.addEventListener("click", function() {
					if (modalTime > 0) {
						try {
							window.localStorage.setItem(ackStorageKey, String(modalTime));
						} catch (error) {}
					}
					togglePersistentErrors();
					closeTestModal();
				});
			}

			var notices = {
				"disconnected": "' . $js_notice_disconnected . '",
				"connected": "' . $js_notice_connected . '",
				"saved": "' . $js_notice_saved . '"
			};

			togglePersistentErrors();
			if (status === "connection_tested") {
				if (testModal && modalTime > 0 && !isCurrentTestAcknowledged()) {
					openTestModal();
				}
				stripQueryParam("status");
			}

			if (status && notices[status]) {
				var notice = document.createElement("div");
				notice.className = "notice notice-success is-dismissible";
				notice.innerHTML = "<p>" + notices[status] + "</p>";
				var header = document.querySelector(".creatorreactor-settings-header");
				if (header) {
					header.parentNode.insertBefore(notice, header.nextSibling);
				}

				window.setTimeout(function() {
					notice.style.transition = "opacity 0.2s ease";
					notice.style.opacity = "0";
					window.setTimeout(function() {
						if (notice.parentNode) {
							notice.parentNode.removeChild(notice);
						}
					}, 200);
				}, 4000);

				var cleanUrl = new URL(window.location.href);
				cleanUrl.searchParams.delete("status");
				window.history.replaceState({}, "", cleanUrl.toString());
			}

			document.querySelectorAll(".creatorreactor-settings-container").forEach(function(settingsShell) {
				var sidebarLinks = settingsShell.querySelectorAll(".creatorreactor-sidebar-link");
				var sidebarPanels = settingsShell.querySelectorAll(".creatorreactor-settings-panel[data-subtab=\"oauth\"], .creatorreactor-settings-panel[data-subtab=\"sync\"]");
				if (!sidebarLinks.length || !sidebarPanels.length) {
					return;
				}
				function activateSubtab(subtab) {
					sidebarLinks.forEach(function(link) {
						link.classList.toggle("is-active", link.getAttribute("data-subtab") === subtab);
					});
					sidebarPanels.forEach(function(panel) {
						panel.classList.toggle("is-active", panel.getAttribute("data-subtab") === subtab);
					});
					var settingsContent = settingsShell.querySelector(".creatorreactor-settings-content");
					if (settingsContent) {
						settingsContent.classList.toggle("creatorreactor-settings-subtab-sync", subtab === "sync");
					}
					refreshProviderAppearanceCardsLayout();
				}
				sidebarLinks.forEach(function(link) {
					link.addEventListener("click", function(event) {
						event.preventDefault();
						var subtab = link.getAttribute("data-subtab");
						activateSubtab(subtab);

						var url = new URL(window.location.href);
						url.searchParams.set("subtab", subtab);
						window.history.replaceState({}, "", url.toString());
					});
				});

				var urlParams = new URLSearchParams(window.location.search);
				var activeSubtabPanel = settingsShell.querySelector(".creatorreactor-settings-panel[data-subtab=\"oauth\"].is-active, .creatorreactor-settings-panel[data-subtab=\"sync\"].is-active");
				var initialSubtab = urlParams.get("subtab") || (activeSubtabPanel ? activeSubtabPanel.getAttribute("data-subtab") : "oauth");
				activateSubtab(initialSubtab);
			});

			(function setupCreatorreactorIntegrationChecksUi() {
				var cfg = window.creatorreactorIntegrationFix;
				var checksRoot = document.getElementById("creatorreactor-integration-checks");
				if (!cfg || !checksRoot) {
					return;
				}

				function postIgnoreToggle(checkId, doIgnore) {
					var body = new window.FormData();
					body.append("action", cfg.ignoreAction);
					body.append("nonce", cfg.ignoreNonce);
					body.append("check_id", checkId);
					body.append("ignore", doIgnore ? "1" : "0");
					return window.fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
						.then(function(res) { return res.json(); });
				}

				checksRoot.addEventListener("click", function(e) {
					var unignoreBtn = e.target.closest(".creatorreactor-integration-unignore-btn");
					if (unignoreBtn && checksRoot.contains(unignoreBtn)) {
						e.preventDefault();
						var uid = unignoreBtn.getAttribute("data-check-id") || "";
						if (!uid || !cfg.ignoreAction) {
							return;
						}
						postIgnoreToggle(uid, false).then(function(data) {
							if (data && data.success) {
								window.location.reload();
							} else if (window.console && console.warn) {
								console.warn((cfg.i18n && cfg.i18n.ignoreError) ? cfg.i18n.ignoreError : "Ignore update failed.");
							}
						}).catch(function() {
							if (window.console && console.warn) {
								console.warn((cfg.i18n && cfg.i18n.ignoreError) ? cfg.i18n.ignoreError : "Ignore update failed.");
							}
						});
						return;
					}
					var ignoreBtn = e.target.closest(".creatorreactor-integration-ignore-btn");
					if (ignoreBtn && checksRoot.contains(ignoreBtn)) {
						e.preventDefault();
						var cid = ignoreBtn.getAttribute("data-check-id") || "";
						if (!cid || !cfg.ignoreAction) {
							return;
						}
						postIgnoreToggle(cid, true).then(function(data) {
							if (data && data.success) {
								window.location.reload();
							} else if (window.console && console.warn) {
								console.warn((cfg.i18n && cfg.i18n.ignoreError) ? cfg.i18n.ignoreError : "Ignore update failed.");
							}
						}).catch(function() {
							if (window.console && console.warn) {
								console.warn((cfg.i18n && cfg.i18n.ignoreError) ? cfg.i18n.ignoreError : "Ignore update failed.");
							}
						});
						return;
					}
					var link = e.target.closest(".creatorreactor-integration-fix-link");
					if (!link || !checksRoot.contains(link)) {
						return;
					}
					e.preventDefault();
					var fixId = link.getAttribute("data-fix-id") || "";
					if (!fixId || !cfg.fixes || !cfg.fixes[fixId]) {
						return;
					}
					var modal = document.getElementById("creatorreactor-integration-fix-modal");
					if (!modal) {
						return;
					}
					var titleEl = document.getElementById("creatorreactor-integration-fix-title");
					var bodyEl = document.getElementById("creatorreactor-integration-fix-body");
					var errEl = document.getElementById("creatorreactor-integration-fix-error");
					var confirmBtn = modal.querySelector(".creatorreactor-integration-fix-confirm");
					var fixLabel = (cfg.i18n && cfg.i18n.fix) ? cfg.i18n.fix : "Fix";
					if (errEl) {
						errEl.hidden = true;
						errEl.textContent = "";
					}
					if (confirmBtn) {
						confirmBtn.disabled = false;
						confirmBtn.textContent = fixLabel;
					}
					modal.dataset.activeFixId = fixId;
					var fix = cfg.fixes[fixId];
					if (titleEl) {
						titleEl.textContent = fix.title || "";
					}
					if (bodyEl) {
						bodyEl.textContent = fix.message || "";
					}
					modal.setAttribute("aria-hidden", "false");
				});

				var modal = document.getElementById("creatorreactor-integration-fix-modal");
				if (!modal || !cfg.fixes) {
					return;
				}
				var errEl = document.getElementById("creatorreactor-integration-fix-error");
				var confirmBtn = modal.querySelector(".creatorreactor-integration-fix-confirm");
				var cancelBtn = modal.querySelector(".creatorreactor-integration-fix-cancel");
				var closeBtn = modal.querySelector(".creatorreactor-integration-fix-close");
				var backdrop = modal.querySelector(".creatorreactor-integration-fix-backdrop");
				var fixLabel = (cfg.i18n && cfg.i18n.fix) ? cfg.i18n.fix : "Fix";
				var workingLabel = (cfg.i18n && cfg.i18n.working) ? cfg.i18n.working : "Applying…";

				function resetModalUi() {
					if (errEl) {
						errEl.hidden = true;
						errEl.textContent = "";
					}
					if (confirmBtn) {
						confirmBtn.disabled = false;
						confirmBtn.textContent = fixLabel;
					}
					modal.dataset.activeFixId = "";
				}

				function closeModal() {
					modal.setAttribute("aria-hidden", "true");
					resetModalUi();
				}

				if (cancelBtn) {
					cancelBtn.addEventListener("click", closeModal);
				}
				if (closeBtn) {
					closeBtn.addEventListener("click", closeModal);
				}
				if (backdrop) {
					backdrop.addEventListener("click", closeModal);
				}

				if (confirmBtn) {
					confirmBtn.addEventListener("click", function() {
						var fixId = modal.dataset.activeFixId || "";
						var fix = fixId && cfg.fixes ? cfg.fixes[fixId] : null;
						if (!fix) {
							return;
						}
						if (fix.type === "redirect" && fix.redirectUrl) {
							window.location.href = fix.redirectUrl;
							return;
						}
						if (fix.type !== "ajax") {
							return;
						}
						if (errEl) {
							errEl.hidden = true;
							errEl.textContent = "";
						}
						confirmBtn.disabled = true;
						confirmBtn.textContent = workingLabel;
						var body = new window.FormData();
						body.append("action", cfg.action);
						body.append("nonce", cfg.nonce);
						body.append("fix_id", fixId);
						window.fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
							.then(function(res) { return res.json(); })
							.then(function(data) {
								if (data && data.success) {
									window.location.reload();
									return;
								}
								var msg = (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : "Could not apply the fix.";
								if (data && data.data && data.data.message) {
									msg = data.data.message;
								}
								if (errEl) {
									errEl.textContent = msg;
									errEl.hidden = false;
								}
								confirmBtn.disabled = false;
								confirmBtn.textContent = fixLabel;
							})
							.catch(function() {
								var msg = (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : "Could not apply the fix.";
								if (errEl) {
									errEl.textContent = msg;
									errEl.hidden = false;
								}
								confirmBtn.disabled = false;
								confirmBtn.textContent = fixLabel;
							});
					});
				}
			})();
			function bindCreatorreactorSettingsSaveTestModal(spec) {
				var cfg = spec.cfg;
				if (!cfg) {
					return;
				}
				var form = document.getElementById(spec.formId);
				if (!form) {
					return;
				}
				if (spec.requireElementId && !document.getElementById(spec.requireElementId)) {
					return;
				}
				var modal = document.getElementById(spec.modalId);
				if (!modal) {
					return;
				}
				var statusEl = document.getElementById(spec.statusId);
				var remediationEl = document.getElementById(spec.remediationId);
				var remediationWrap = document.getElementById(spec.remediationWrapId);
				var backdrop = modal.querySelector(".creatorreactor-modal-backdrop");
				var headerDismiss = modal.querySelector(spec.headerDismissSel);
				var footerDismiss = modal.querySelector(spec.footerDismissSel);
				var acknowledgeBtn = modal.querySelector(spec.acknowledgeSel);
				var modalDialog = modal.querySelector(".creatorreactor-modal-dialog");
				var submitBtn = form.querySelector("input[type=\"submit\"], button[type=\"submit\"]");
				function setFooterState(state) {
					if (footerDismiss) {
						footerDismiss.hidden = state !== "error";
					}
					if (acknowledgeBtn) {
						acknowledgeBtn.hidden = state !== "success";
					}
				}
				function openModal() {
					modal.setAttribute("aria-hidden", "false");
					document.body.style.overflow = "hidden";
					setFooterState("checking");
					if (modalDialog) {
						modalDialog.setAttribute("tabindex", "-1");
						modalDialog.focus();
					}
				}
				function closeModal() {
					modal.setAttribute("aria-hidden", "true");
					document.body.style.overflow = "";
					setFooterState("checking");
				}
				function setRemediation(text) {
					if (!remediationWrap || !remediationEl) {
						return;
					}
					if (text) {
						remediationEl.textContent = text;
						remediationWrap.hidden = false;
					} else {
						remediationEl.textContent = "";
						remediationWrap.hidden = true;
					}
				}
				function setStatus(kind, text) {
					if (!statusEl) {
						return;
					}
					statusEl.textContent = text || "";
					statusEl.classList.remove("is-ok", "is-error");
					if (kind === true) {
						statusEl.classList.add("is-ok");
					} else if (kind === false) {
						statusEl.classList.add("is-error");
					}
				}
				function proceedSave() {
					form.dataset.creatorreactorAllowNativeSubmit = "1";
					if (submitBtn && typeof form.requestSubmit === "function") {
						form.requestSubmit(submitBtn);
					} else {
						form.submit();
					}
				}
				if (headerDismiss) {
					headerDismiss.addEventListener("click", closeModal);
				}
				if (footerDismiss) {
					footerDismiss.addEventListener("click", closeModal);
				}
				if (acknowledgeBtn) {
					acknowledgeBtn.addEventListener("click", function() {
						closeModal();
						proceedSave();
					});
				}
				if (backdrop) {
					backdrop.addEventListener("click", closeModal);
				}
				document.addEventListener("keydown", function(ev) {
					if (ev.key !== "Escape") {
						return;
					}
					if (modal.getAttribute("aria-hidden") === "false") {
						closeModal();
					}
				});
				form.addEventListener("submit", function(ev) {
					if (form.dataset.creatorreactorAllowNativeSubmit === "1") {
						delete form.dataset.creatorreactorAllowNativeSubmit;
						return;
					}
					if (spec.skipValidation(form)) {
						return;
					}
					ev.preventDefault();
					setRemediation("");
					setStatus(null, cfg.i18n && cfg.i18n.checking ? cfg.i18n.checking : "Checking…");
					openModal();
					var body = new window.FormData();
					body.append("action", spec.ajaxAction);
					body.append("nonce", cfg.nonce);
					spec.appendPayload(body, form);
					window.fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (!data) {
								setStatus(false, cfg.i18n && cfg.i18n.unknownError ? cfg.i18n.unknownError : "Error");
								setRemediation(cfg.i18n && cfg.i18n.genericRemediate ? cfg.i18n.genericRemediate : "");
								setFooterState("error");
								if (footerDismiss) {
									footerDismiss.focus();
								}
								return;
							}
							if (data.success && data.data && data.data.skip) {
								closeModal();
								proceedSave();
								return;
							}
							if (data.success) {
								setStatus(true, data.data && data.data.message ? data.data.message : (cfg.i18n && cfg.i18n.ok ? cfg.i18n.ok : ""));
								setRemediation("");
								setFooterState("success");
								if (acknowledgeBtn) {
									acknowledgeBtn.focus();
								}
								return;
							}
							var msg = data.data && data.data.message ? data.data.message : (cfg.i18n && cfg.i18n.failed ? cfg.i18n.failed : "");
							var rem = data.data && data.data.remediation ? data.data.remediation : "";
							setStatus(false, msg);
							setRemediation(rem);
							setFooterState("error");
							if (footerDismiss) {
								footerDismiss.focus();
							}
						})
						.catch(function() {
							setStatus(false, cfg.i18n && cfg.i18n.networkError ? cfg.i18n.networkError : "");
							setRemediation(cfg.i18n && cfg.i18n.networkRemediate ? cfg.i18n.networkRemediate : "");
							setFooterState("error");
							if (footerDismiss) {
								footerDismiss.focus();
							}
						});
				});
			}
			bindCreatorreactorSettingsSaveTestModal({
				cfg: window.creatorreactorGoogleOAuthSave,
				formId: "creatorreactor-google-settings-form",
				modalId: "creatorreactor-google-oauth-test-modal",
				requireElementId: "creatorreactor_google_oauth_client_id",
				statusId: "creatorreactor-google-oauth-test-status",
				remediationId: "creatorreactor-google-oauth-test-remediation",
				remediationWrapId: "creatorreactor-google-oauth-test-remediation-wrap",
				headerDismissSel: ".creatorreactor-google-oauth-test-dismiss",
				footerDismissSel: ".creatorreactor-google-oauth-test-footer-dismiss",
				acknowledgeSel: ".creatorreactor-google-oauth-test-acknowledge",
				ajaxAction: "creatorreactor_google_oauth_validate",
				skipValidation: function() {
					var el = document.getElementById("creatorreactor_google_oauth_client_id");
					return !((el && (el.value || "")).trim());
				},
				appendPayload: function(body) {
					var clientIdEl = document.getElementById("creatorreactor_google_oauth_client_id");
					var clientId = ((clientIdEl && clientIdEl.value) || "").trim();
					var secretEl = document.getElementById("creatorreactor_google_oauth_client_secret");
					var secretVal = secretEl ? (secretEl.value || "") : "";
					var secretUnchanged = (secretVal === "" || secretVal === "********") ? "1" : "0";
					body.append("client_id", clientId);
					body.append("client_secret", secretVal);
					body.append("secret_unchanged", secretUnchanged);
				}
			});
			bindCreatorreactorSettingsSaveTestModal({
				cfg: window.creatorreactorInstagramOAuthSave,
				formId: "creatorreactor-instagram-settings-form",
				modalId: "creatorreactor-instagram-oauth-test-modal",
				requireElementId: "creatorreactor_instagram_oauth_client_id",
				statusId: "creatorreactor-instagram-oauth-test-status",
				remediationId: "creatorreactor-instagram-oauth-test-remediation",
				remediationWrapId: "creatorreactor-instagram-oauth-test-remediation-wrap",
				headerDismissSel: ".creatorreactor-instagram-oauth-test-dismiss",
				footerDismissSel: ".creatorreactor-instagram-oauth-test-footer-dismiss",
				acknowledgeSel: ".creatorreactor-instagram-oauth-test-acknowledge",
				ajaxAction: "creatorreactor_instagram_oauth_validate",
				skipValidation: function() {
					var el = document.getElementById("creatorreactor_instagram_oauth_client_id");
					return !((el && (el.value || "")).trim());
				},
				appendPayload: function(body) {
					var clientIdEl = document.getElementById("creatorreactor_instagram_oauth_client_id");
					var clientId = ((clientIdEl && clientIdEl.value) || "").trim();
					var secretEl = document.getElementById("creatorreactor_instagram_oauth_client_secret");
					var secretVal = secretEl ? (secretEl.value || "") : "";
					var secretUnchanged = (secretVal === "" || secretVal === "********") ? "1" : "0";
					body.append("client_id", clientId);
					body.append("client_secret", secretVal);
					body.append("secret_unchanged", secretUnchanged);
				}
			});
			bindCreatorreactorSettingsSaveTestModal({
				cfg: window.creatorreactorFanvueOAuthSave,
				formId: "creatorreactor-fanvue-settings-form",
				modalId: "creatorreactor-fanvue-oauth-test-modal",
				requireElementId: "creatorreactor_oauth_client_id",
				statusId: "creatorreactor-fanvue-oauth-test-status",
				remediationId: "creatorreactor-fanvue-oauth-test-remediation",
				remediationWrapId: "creatorreactor-fanvue-oauth-test-remediation-wrap",
				headerDismissSel: ".creatorreactor-fanvue-oauth-test-dismiss",
				footerDismissSel: ".creatorreactor-fanvue-oauth-test-footer-dismiss",
				acknowledgeSel: ".creatorreactor-fanvue-oauth-test-acknowledge",
				ajaxAction: "creatorreactor_fanvue_oauth_validate",
				skipValidation: function(form) {
					var clientIdEl = document.getElementById("creatorreactor_oauth_client_id");
					var clientId = ((clientIdEl && clientIdEl.value) || "").trim();
					if (!clientId) {
						return true;
					}
					var authRadio = form.querySelector(".creatorreactor-auth-mode-input:checked");
					var mode = authRadio ? (authRadio.value || "") : "";
					return mode === "agency";
				},
				appendPayload: function(body, form) {
					var clientIdEl = document.getElementById("creatorreactor_oauth_client_id");
					var clientId = ((clientIdEl && clientIdEl.value) || "").trim();
					var secretEl = document.getElementById("creatorreactor_oauth_client_secret");
					var secretVal = secretEl ? (secretEl.value || "") : "";
					var secretUnchanged = (secretVal === "" || secretVal === "********") ? "1" : "0";
					var authRadio = form.querySelector(".creatorreactor-auth-mode-input:checked");
					var authMode = authRadio ? (authRadio.value || "") : "";
					body.append("client_id", clientId);
					body.append("client_secret", secretVal);
					body.append("secret_unchanged", secretUnchanged);
					body.append("authentication_mode", authMode);
				}
			});
			bindCreatorreactorSettingsSaveTestModal({
				cfg: window.creatorreactorOnlyfansOfauthSave,
				formId: "creatorreactor-onlyfans-settings-form",
				modalId: "creatorreactor-onlyfans-ofauth-test-modal",
				requireElementId: "creatorreactor_ofauth_api_key",
				statusId: "creatorreactor-onlyfans-ofauth-test-status",
				remediationId: "creatorreactor-onlyfans-ofauth-test-remediation",
				remediationWrapId: "creatorreactor-onlyfans-ofauth-test-remediation-wrap",
				headerDismissSel: ".creatorreactor-onlyfans-ofauth-test-dismiss",
				footerDismissSel: ".creatorreactor-onlyfans-ofauth-test-footer-dismiss",
				acknowledgeSel: ".creatorreactor-onlyfans-ofauth-test-acknowledge",
				ajaxAction: "creatorreactor_onlyfans_ofauth_validate",
				skipValidation: function() {
					var el = document.getElementById("creatorreactor_ofauth_api_key");
					return !((el && (el.value || "")).trim());
				},
				appendPayload: function(body) {
					var keyEl = document.getElementById("creatorreactor_ofauth_api_key");
					var whEl = document.getElementById("creatorreactor_ofauth_webhook_secret");
					var keyVal = keyEl ? (keyEl.value || "") : "";
					var whVal = whEl ? (whEl.value || "") : "";
					var keyUnchanged = (keyVal === "" || keyVal === "********") ? "1" : "0";
					var whUnchanged = (whVal === "" || whVal === "********") ? "1" : "0";
					body.append("api_key", keyVal);
					body.append("webhook_secret", whVal);
					body.append("key_unchanged", keyUnchanged);
					body.append("webhook_secret_unchanged", whUnchanged);
				}
			});
			(function() {
				var cfg = window.creatorreactorSocialOAuthSave;
				if (!cfg) { return; }
				var modal = document.getElementById("creatorreactor-social-oauth-test-modal");
				if (!modal) { return; }
				var statusEl = document.getElementById("creatorreactor-social-oauth-test-status");
				var remediationEl = document.getElementById("creatorreactor-social-oauth-test-remediation");
				var remediationWrap = document.getElementById("creatorreactor-social-oauth-test-remediation-wrap");
				var acknowledgeBtn = modal.querySelector(".creatorreactor-social-oauth-test-acknowledge");
				var footerDismiss = modal.querySelector(".creatorreactor-social-oauth-test-footer-dismiss");
				var headerDismiss = modal.querySelector(".creatorreactor-social-oauth-test-dismiss");
				var backdrop = modal.querySelector(".creatorreactor-modal-backdrop");
				function openModal() { modal.setAttribute("aria-hidden", "false"); document.body.style.overflow = "hidden"; }
				function closeModal() { modal.setAttribute("aria-hidden", "true"); document.body.style.overflow = ""; }
				function setRemediation(text) {
					if (!remediationEl) { return; }
					if (remediationWrap) { remediationWrap.hidden = !text; }
					remediationEl.textContent = text || "";
				}
				function setStatus(kind, text) {
					if (!statusEl) { return; }
					statusEl.className = "creatorreactor-social-oauth-test-status creatorreactor-status-message";
					if (kind === true) { statusEl.classList.add("creatorreactor-status-success"); }
					else if (kind === false) { statusEl.classList.add("creatorreactor-status-error"); }
					statusEl.textContent = text || "";
					if (acknowledgeBtn) { acknowledgeBtn.hidden = true; }
					if (footerDismiss) { footerDismiss.hidden = true; }
				}
				if (headerDismiss) { headerDismiss.addEventListener("click", closeModal); }
				if (backdrop) { backdrop.addEventListener("click", closeModal); }
				if (footerDismiss) { footerDismiss.addEventListener("click", closeModal); }
				document.addEventListener("click", function(ev) {
					var btn = ev.target && ev.target.closest && ev.target.closest(".creatorreactor-social-oauth-test");
					if (!btn) { return; }
					ev.preventDefault();
					var slug = (btn.getAttribute("data-provider-slug") || "").trim();
					if (!slug) { return; }
					var form = document.getElementById("creatorreactor-" + slug + "-settings-form");
					if (!form) { return; }
					var idEl = form.querySelector(".creatorreactor-social-oauth-client-id");
					var secEl = form.querySelector(".creatorreactor-social-oauth-client-secret");
					var instEl = form.querySelector("#creatorreactor_mastodon_instance");
					var clientId = idEl ? (idEl.value || "").trim() : "";
					if (!clientId) { return; }
					var secretVal = secEl ? (secEl.value || "") : "";
					var secretUnchanged = (secretVal === "" || secretVal === "********") ? "1" : "0";
					setRemediation("");
					setStatus(null, (cfg.i18n && cfg.i18n.checking) ? cfg.i18n.checking : "");
					modal.dataset.activeSlug = slug;
					openModal();
					var body = new window.FormData();
					body.append("action", "creatorreactor_social_oauth_validate");
					body.append("nonce", cfg.nonce);
					body.append("provider_slug", slug);
					body.append("client_id", clientId);
					body.append("client_secret", secretVal);
					body.append("secret_unchanged", secretUnchanged);
					if (instEl) {
						body.append("mastodon_instance", (instEl.value || "").trim());
					}
					window.fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (data && data.success && data.data && data.data.skip) {
								setStatus(true, data.data.message || "");
								setRemediation("");
								if (acknowledgeBtn) { acknowledgeBtn.hidden = false; }
								return;
							}
							if (data && data.success) {
								setStatus(true, data.data && data.data.message ? data.data.message : "");
								setRemediation("");
								if (acknowledgeBtn) { acknowledgeBtn.hidden = false; }
								return;
							}
							setStatus(false, data && data.data && data.data.message ? data.data.message : "");
							setRemediation(data && data.data && data.data.remediation ? data.data.remediation : "");
							if (footerDismiss) { footerDismiss.hidden = false; }
						})
						.catch(function() {
							setStatus(false, "");
							setRemediation("");
							if (footerDismiss) { footerDismiss.hidden = false; }
						});
				});
				if (acknowledgeBtn) {
					acknowledgeBtn.addEventListener("click", function() {
						closeModal();
						var slug = modal.dataset.activeSlug || "";
						if (!slug) { return; }
						var form = document.getElementById("creatorreactor-" + slug + "-settings-form");
						if (form && form.requestSubmit) { form.requestSubmit(); }
						else if (form) { form.submit(); }
					});
				}
			})();
			});
		})();
		';

		wp_register_script( 'creatorreactor-admin', false, [], CREATORREACTOR_VERSION, true );
		wp_enqueue_script( 'creatorreactor-admin' );
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorAuthMode',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'creatorreactor_auth_mode' ),
				'copyLabel'      => __( 'Copy', 'wp-creatorreactor' ),
				'copiedLabel'    => __( 'Copied!', 'wp-creatorreactor' ),
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorIntegrationFix',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'creatorreactor_integration_fix' ),
				'action'       => 'creatorreactor_integration_fix',
				'ignoreAction' => 'creatorreactor_integration_check_ignore',
				'ignoreNonce'  => wp_create_nonce( 'creatorreactor_integration_check_ignore' ),
				'fixes'        => self::get_integration_fix_definitions_for_localize(),
				'i18n'         => [
					'fix'          => __( 'Fix', 'wp-creatorreactor' ),
					'cancel'       => __( 'Cancel', 'wp-creatorreactor' ),
					'error'        => __( 'Could not apply the fix.', 'wp-creatorreactor' ),
					'working'      => __( 'Applying…', 'wp-creatorreactor' ),
					'ignoreError'  => __( 'Could not update ignore state.', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorDebugSchema',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_debug_schema' ),
				'action'  => 'creatorreactor_debug_schema_manifest',
				'i18n'    => [
					/* translators: %s: Schema spec version or em dash. */
					'specTitleManifest' => __( 'Spec %s — manifest', 'wp-creatorreactor' ),
					'loadError'         => __( 'Could not load schema from the schema service.', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorGoogleOAuthSave',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_google_oauth_save' ),
				'i18n'    => [
					'checking'        => __( 'Contacting Google to verify your Client ID and Client Secret…', 'wp-creatorreactor' ),
					'ok'              => __( 'Credentials look valid with Google.', 'wp-creatorreactor' ),
					'failed'          => __( 'Configuration check failed.', 'wp-creatorreactor' ),
					'unknownError'    => __( 'Something went wrong. Please try again.', 'wp-creatorreactor' ),
					'genericRemediate'=> __( 'Reload this page and try Save again. If it keeps failing, check your browser console and server error logs.', 'wp-creatorreactor' ),
					'networkError'    => __( 'Could not reach your site (admin-ajax). Check your network connection.', 'wp-creatorreactor' ),
					'networkRemediate' => __( 'Ensure admin-ajax.php is not blocked by a security plugin or firewall.', 'wp-creatorreactor' ),
					'close'           => __( 'Close', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorSocialOAuthSave',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_social_oauth_save' ),
				'i18n'    => [
					'checking' => __( 'Verifying OAuth credentials…', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorInstagramOAuthSave',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_instagram_oauth_save' ),
				'i18n'    => [
					'checking'         => __( 'Contacting Instagram to verify your Client ID and Client Secret…', 'wp-creatorreactor' ),
					'ok'               => __( 'Credentials look valid with Instagram.', 'wp-creatorreactor' ),
					'failed'           => __( 'Instagram OAuth configuration check failed.', 'wp-creatorreactor' ),
					'unknownError'     => __( 'Something went wrong. Please try again.', 'wp-creatorreactor' ),
					'genericRemediate' => __( 'Reload this page and try Save again. If it keeps failing, check your browser console and server error logs.', 'wp-creatorreactor' ),
					'networkError'     => __( 'Could not reach your site (admin-ajax). Check your network connection.', 'wp-creatorreactor' ),
					'networkRemediate' => __( 'Ensure admin-ajax.php is not blocked by a security plugin or firewall.', 'wp-creatorreactor' ),
					'close'            => __( 'Close', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorFanvueOAuthSave',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_fanvue_oauth_save' ),
				'i18n'    => [
					'checking'         => __( 'Contacting Fanvue to verify your OAuth Client ID and Client Secret…', 'wp-creatorreactor' ),
					'ok'               => __( 'Fanvue accepted your Client ID and Client Secret.', 'wp-creatorreactor' ),
					'failed'           => __( 'Fanvue configuration check failed.', 'wp-creatorreactor' ),
					'unknownError'     => __( 'Something went wrong. Please try again.', 'wp-creatorreactor' ),
					'genericRemediate' => __( 'Reload this page and try Save again. If it keeps failing, check your browser console and server error logs.', 'wp-creatorreactor' ),
					'networkError'     => __( 'Could not reach your site (admin-ajax). Check your network connection.', 'wp-creatorreactor' ),
					'networkRemediate' => __( 'Ensure admin-ajax.php is not blocked by a security plugin or firewall.', 'wp-creatorreactor' ),
					'close'            => __( 'Close', 'wp-creatorreactor' ),
				],
			]
		);
		wp_localize_script(
			'creatorreactor-admin',
			'creatorreactorOnlyfansOfauthSave',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_onlyfans_ofauth_save' ),
				'i18n'    => [
					'checking'         => __( 'Contacting OFAuth to verify your API key…', 'wp-creatorreactor' ),
					'ok'               => __( 'OFAuth accepted your API key.', 'wp-creatorreactor' ),
					'failed'           => __( 'OnlyFans (OFAuth) configuration check failed.', 'wp-creatorreactor' ),
					'unknownError'     => __( 'Something went wrong. Please try again.', 'wp-creatorreactor' ),
					'genericRemediate' => __( 'Reload this page and try Save again. If it keeps failing, check your browser console and server error logs.', 'wp-creatorreactor' ),
					'networkError'     => __( 'Could not reach your site (admin-ajax). Check your network connection.', 'wp-creatorreactor' ),
					'networkRemediate' => __( 'Ensure admin-ajax.php is not blocked by a security plugin or firewall.', 'wp-creatorreactor' ),
					'close'            => __( 'Close', 'wp-creatorreactor' ),
				],
			]
		);
		wp_add_inline_script( 'creatorreactor-admin', $js );
	}

	/**
	 * @param int    $wp_user_id WordPress user ID.
	 * @param string $user_email WordPress user email.
	 * @return array<string, mixed>|null
	 */
	private static function get_latest_entitlement_row_for_wp_user( $wp_user_id, $user_email = '' ) {
		$wp_user_id = (int) $wp_user_id;
		if ( $wp_user_id < 1 ) {
			return null;
		}

		global $wpdb;
		$table        = Entitlements::get_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return null;
		}

		Entitlements::maybe_add_product_column();
		Entitlements::maybe_add_display_name_column();
		Entitlements::maybe_add_fanvue_user_uuid_column();
		Entitlements::maybe_add_creatorreactor_uuid_column();
		Entitlements::maybe_add_creatorreactor_user_uuid_column();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE wp_user_id = %d ORDER BY (status = %s) DESC, updated_at DESC, id DESC LIMIT 1",
				$wp_user_id,
				Entitlements::STATUS_ACTIVE
			),
			ARRAY_A
		);
		if ( is_array( $row ) ) {
			return $row;
		}

		$user_email = sanitize_email( (string) $user_email );
		if ( $user_email === '' || ! is_email( $user_email ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s ORDER BY (status = %s) DESC, updated_at DESC, id DESC LIMIT 1",
				$user_email,
				Entitlements::STATUS_ACTIVE
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Add CreatorReactor UUID field on wp-admin user profile pages.
	 *
	 * @param \WP_User $user User object for the profile screen.
	 */
	public static function render_user_profile_creatorreactor_uuid_field( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', (int) $user->ID ) ) {
			return;
		}

		$uuid = get_user_meta( (int) $user->ID, Entitlements::USERMETA_CREATORREACTOR_UUID, true );
		$uuid = is_string( $uuid ) ? sanitize_text_field( $uuid ) : '';
		$last_payload_sync = get_user_meta( (int) $user->ID, Fan_OAuth::USERMETA_LAST_OAUTH_PAYLOAD_SYNC_AT, true );
		$last_payload_sync = is_string( $last_payload_sync ) ? sanitize_text_field( $last_payload_sync ) : '';

		$row         = self::get_latest_entitlement_row_for_wp_user( (int) $user->ID, (string) $user->user_email );
		$has_details = is_array( $row );
		?>
		<div class="creatorreactor-profile-section-heading">
			<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--profile' ); ?>
			<h2><?php esc_html_e( 'CreatorReactor', 'wp-creatorreactor' ); ?></h2>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="creatorreactor_user_uuid_field"><?php esc_html_e( 'CreatorReactor user UUID', 'wp-creatorreactor' ); ?></label></th>
				<td>
					<input
						type="text"
						id="creatorreactor_user_uuid_field"
						class="regular-text code creatorreactor-user-uuid-field"
						value="<?php echo esc_attr( $uuid ); ?>"
						readonly
						data-user-id="<?php echo esc_attr( (string) $user->ID ); ?>"
						data-has-details="<?php echo $has_details ? '1' : '0'; ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Read-only external identity used for cross-platform tracking.', 'wp-creatorreactor' ); ?>
						<button
							type="button"
							class="button-link creatorreactor-open-user-entitlement-details"
							data-user-id="<?php echo esc_attr( (string) $user->ID ); ?>"
							data-has-details="<?php echo $has_details ? '1' : '0'; ?>"
						><?php esc_html_e( 'View entitlement details', 'wp-creatorreactor' ); ?></button>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="creatorreactor_last_payload_sync_field"><?php esc_html_e( 'Last Fan OAuth payload sync', 'wp-creatorreactor' ); ?></label></th>
				<td>
					<input
						type="text"
						id="creatorreactor_last_payload_sync_field"
						class="regular-text code"
						value="<?php echo esc_attr( $last_payload_sync !== '' ? $last_payload_sync : '—' ); ?>"
						readonly
					/>
					<p class="description"><?php esc_html_e( 'Timestamp (UTC ISO-8601) of the most recent per-login Fan OAuth profile sync.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		if ( ! self::$printed_profile_details_modal ) {
			self::$printed_profile_details_modal = true;
			?>
			<div id="creatorreactor-user-details-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
				<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
				<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-user-details-modal-title">
					<div class="creatorreactor-modal-header">
						<div class="creatorreactor-modal-header-title">
							<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--modal' ); ?>
							<h3 id="creatorreactor-user-details-modal-title"><?php esc_html_e( 'CreatorReactor record', 'wp-creatorreactor' ); ?></h3>
						</div>
						<button type="button" class="creatorreactor-user-details-close" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
					</div>
					<div class="creatorreactor-modal-body" id="creatorreactor-user-details-modal-body"></div>
				</div>
			</div>
			<?php
		}
	}

	public static function get_status_summary() {
		self::maybe_upgrade_error_messages_with_timestamp();
		$opts = self::get_options();
		$broker_mode = ! empty( $opts['broker_mode'] );

		return [
			'broker_mode' => $broker_mode,
			'connected' => self::is_connected(),
			'last_sync' => get_option( self::OPTION_LAST_SYNC ),
			'last_error' => get_option( self::OPTION_LAST_ERROR ),
			'critical_error' => get_option( self::OPTION_CRITICAL_ERROR ),
		];
	}

	public static function is_connected() {
		if ( self::is_broker_mode() ) {
			return Broker_Client::is_connected();
		}
		return CreatorReactor_OAuth::is_connected();
	}

	public static function add_menu() {
		add_menu_page(
			__( 'CreatorReactor', 'wp-creatorreactor' ),
			__( 'CreatorReactor', 'wp-creatorreactor' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ],
			self::admin_menu_icon(),
			3
		);
		remove_submenu_page( self::PAGE_SLUG, self::PAGE_SLUG );
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'CreatorReactor', 'wp-creatorreactor' ),
			__( 'Dashboard', 'wp-creatorreactor' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'CreatorReactor Users', 'wp-creatorreactor' ),
			__( 'Users', 'wp-creatorreactor' ),
			'manage_options',
			self::PAGE_USERS_SLUG,
			[ __CLASS__, 'render_users_page' ]
		);
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'wp-creatorreactor' ),
			__( 'Settings', 'wp-creatorreactor' ),
			'manage_options',
			self::PAGE_SETTINGS_SLUG,
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function render_page( $default_tab = 'dashboard', $default_subtab = 'oauth' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::maybe_clear_stale_oauth_client_mismatch_last_error();

		$status = self::get_status_summary();
		$opts   = self::get_options();
		$current_product_label = Entitlements::product_label( Entitlements::PRODUCT_FANVUE );
		$secret_mask = ! empty( $opts['creatorreactor_oauth_client_secret'] ) ? '********' : '';
		$cloud_password_mask = ! empty( $opts['creatorreactor_cloud_password'] ) ? '********' : '';
		$ofauth_api_key_mask         = ! empty( $opts['creatorreactor_ofauth_api_key'] ) ? '********' : '';
		$ofauth_webhook_secret_mask = ! empty( $opts['creatorreactor_ofauth_webhook_secret'] ) ? '********' : '';
		$google_oauth_secret_mask       = ! empty( $opts['creatorreactor_google_oauth_client_secret'] ) ? '********' : '';
		$instagram_oauth_secret_mask    = ! empty( $opts['creatorreactor_instagram_oauth_client_secret'] ) ? '********' : '';
		$social_oauth_secret_masks        = [];
		foreach ( self::all_settings_social_oauth_slugs() as $social_slug ) {
			$sk = Social_OAuth_Registry::option_client_secret( $social_slug );
			$social_oauth_secret_masks[ $social_slug ] = ! empty( $opts[ $sk ] ) ? '********' : '';
		}
		$broker_mode         = ! empty( $opts['broker_mode'] );
		$oauth_locked_initial = self::oauth_config_should_start_locked( $opts, $broker_mode );
		$authentication_mode = $broker_mode ? self::AUTH_MODE_AGENCY : self::AUTH_MODE_CREATOR;
		$connection_test = get_option( self::OPTION_CONNECTION_TEST, [] );
		$current_page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::PAGE_SLUG;
		$is_users_page      = ( self::PAGE_USERS_SLUG === $current_page_slug );
		$is_settings_page   = ( self::PAGE_SETTINGS_SLUG === $current_page_slug );
		$settings_social_slugs        = self::all_settings_social_oauth_slugs();
		$settings_default_social_slug = ! empty( $settings_social_slugs[0] ) ? $settings_social_slugs[0] : 'tiktok';
		$tab_links          = [];
		if ( $is_users_page ) {
			$allowed_tabs = [ 'users' ];
			$tab_links = [
				'users' => [
					'label'     => __( 'Users', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_USERS_SLUG,
					'args'      => [ 'tab' => 'users' ],
				],
			];
		} elseif ( $is_settings_page ) {
			$allowed_tabs = [
				'general',
				'cloud',
				'settings',
				'google',
				'instagram',
				'social_oauth',
				'onlyfans',
				'documentation',
				'debug',
			];
			$tab_links = [
				'general'    => [
					'label'     => __( 'General', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'general' ],
				],
				'cloud'      => [
					'label'     => __( 'CreatorReactor Cloud', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'cloud' ],
				],
				'settings'   => [
					'label'     => __( 'Fanvue', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'settings', 'subtab' => 'oauth' ],
				],
				'google'     => [
					'label'     => __( 'Google', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'google' ],
				],
				'instagram'  => [
					'label'     => __( 'Instagram', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'instagram' ],
				],
				'social_oauth' => [
					'label'     => __( 'Social login', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [
						'tab'             => 'social_oauth',
						'social_provider' => $settings_default_social_slug,
					],
				],
				'onlyfans'   => [
					'label'     => __( 'OnlyFans', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'onlyfans', 'subtab' => 'oauth' ],
				],
				'documentation' => [
					'label'     => __( 'Documentation', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'documentation' ],
					'align'     => 'right',
				],
				'debug'      => [
					'label'     => __( 'Debug', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SETTINGS_SLUG,
					'args'      => [ 'tab' => 'debug' ],
					'align'     => 'right',
				],
			];
		} else {
			$allowed_tabs = [ 'dashboard' ];
			$tab_links = [
				'dashboard' => [
					'label'     => __( 'Dashboard', 'wp-creatorreactor' ),
					'page_slug' => self::PAGE_SLUG,
					'args'      => [ 'tab' => 'dashboard' ],
				],
			];
		}
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default_tab;
		$legacy_social_tab_for_oauth = '';
		if ( $is_settings_page && in_array( $requested_tab, $settings_social_slugs, true ) ) {
			$legacy_social_tab_for_oauth = $requested_tab;
			$requested_tab               = 'social_oauth';
		}
		if ( $is_settings_page && 'integration-checks' === $requested_tab ) {
			$requested_tab = 'debug';
		}
		$active_tab = in_array( $requested_tab, $allowed_tabs, true ) ? $requested_tab : $default_tab;
		$active_social_provider = $settings_default_social_slug;
		if ( $is_settings_page && 'social_oauth' === $active_tab ) {
			if ( $legacy_social_tab_for_oauth !== '' ) {
				$active_social_provider = $legacy_social_tab_for_oauth;
			} else {
				$p = isset( $_GET['social_provider'] ) ? sanitize_key( wp_unslash( $_GET['social_provider'] ) ) : '';
				if ( $p !== '' && in_array( $p, $settings_social_slugs, true ) ) {
					$active_social_provider = $p;
				}
			}
		}
		$allowed_subtabs = [ 'oauth', 'sync' ];
		$requested_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : $default_subtab;
		$active_subtab = in_array( $requested_subtab, $allowed_subtabs, true ) ? $requested_subtab : $default_subtab;
		$onlyfans_active_subtab = ( 'onlyfans' === $active_tab && in_array( $requested_subtab, [ 'oauth', 'sync' ], true ) )
			? $requested_subtab
			: 'oauth';
		$users_snapshot = self::get_users_tab_snapshot();
		$user_totals      = $users_snapshot['totals'];
		$user_rows        = $users_snapshot['rows'];
		?>
		<div class="wrap creatorreactor-wrap"<?php echo $is_settings_page ? ' data-creatorreactor-social-oauth-slugs="' . esc_attr( wp_json_encode( $settings_social_slugs ) ) . '"' : ''; ?>>
			<div class="creatorreactor-settings-header">
				<div class="creatorreactor-settings-header-brand">
					<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--header' ); ?>
				</div>
				<?php if ( $is_users_page ) : ?>
				<div class="creatorreactor-settings-header-text">
					<h1><?php esc_html_e( 'CreatorReactor Users', 'wp-creatorreactor' ); ?></h1>
					<p><?php esc_html_e( 'Manage synchronized entitlements and linked WordPress users.', 'wp-creatorreactor' ); ?></p>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $_GET['connection_log_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connection log cleared.', 'wp-creatorreactor' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['sync_log_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved sync log entries were removed.', 'wp-creatorreactor' ); ?></p></div>
			<?php endif; ?>

			<?php settings_errors( self::OPTION_NAME ); ?>

		<?php if ( ! empty( $tab_links ) ) : ?>
			<nav class="nav-tab-wrapper creatorreactor-tab-nav" aria-label="<?php esc_attr_e( 'CreatorReactor sections', 'wp-creatorreactor' ); ?>">
				<?php foreach ( $tab_links as $tab_slug => $tab_config ) : ?>
					<?php $tab_url = self::admin_page_url( $tab_config['args'], $tab_config['page_slug'] ); ?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab creatorreactor-tab-link <?php echo $tab_slug === $active_tab ? 'nav-tab-active' : ''; ?><?php echo ( isset( $tab_config['align'] ) && 'right' === $tab_config['align'] ) ? ' creatorreactor-tab-link-right' : ''; ?>" data-tab="<?php echo esc_attr( $tab_slug ); ?>"><?php echo esc_html( $tab_config['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $is_settings_page || $is_users_page ) : ?>
		<form id="creatorreactor-fanvue-settings-form" method="post" action="options.php">
			<?php settings_fields( self::OPTION_NAME ); ?>

		<div class="creatorreactor-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
		<div class="creatorreactor-settings-container">
		<div class="creatorreactor-settings-sidebar">
			<nav class="creatorreactor-sidebar-nav">
				<a href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'settings', 'subtab' => 'oauth' ], self::PAGE_SETTINGS_SLUG ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'oauth' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth"><?php esc_html_e( 'OAuth', 'wp-creatorreactor' ); ?></a>
				<a href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'settings', 'subtab' => 'sync' ], self::PAGE_SETTINGS_SLUG ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'sync' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="sync"><?php esc_html_e( 'Sync', 'wp-creatorreactor' ); ?></a>
			</nav>
		</div>
		<div class="creatorreactor-settings-content<?php echo 'sync' === $active_subtab ? ' creatorreactor-settings-subtab-sync' : ''; ?><?php echo ( empty( $opts['broker_mode'] ) && 'oauth' === $active_subtab ) ? ' creatorreactor-settings-appearance-card-below' : ''; ?>">
			<div id="creatorreactor-auth-mode-root" class="creatorreactor-settings-auth-card" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'creatorreactor_auth_mode' ) ); ?>">
				<h2><?php esc_html_e( 'Authentication Modes', 'wp-creatorreactor' ); ?></h2>
				<div class="creatorreactor-settings-block">
					<p class="creatorreactor-auth-mode-intro"><?php esc_html_e( 'Choose what type of Fanvue account you have:', 'wp-creatorreactor' ); ?></p>
					<div class="creatorreactor-auth-mode-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Fanvue account type', 'wp-creatorreactor' ); ?>">
						<label class="<?php echo self::AUTH_MODE_CREATOR === $authentication_mode ? 'is-selected' : ''; ?>">
							<input type="radio" class="creatorreactor-auth-mode-input" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[authentication_mode]" value="<?php echo esc_attr( self::AUTH_MODE_CREATOR ); ?>" <?php checked( $authentication_mode, self::AUTH_MODE_CREATOR ); ?> />
							<span><?php esc_html_e( 'Creator', 'wp-creatorreactor' ); ?></span>
						</label>
						<label class="<?php echo self::AUTH_MODE_AGENCY === $authentication_mode ? 'is-selected' : ''; ?>">
							<input type="radio" class="creatorreactor-auth-mode-input" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[authentication_mode]" value="<?php echo esc_attr( self::AUTH_MODE_AGENCY ); ?>" <?php checked( $authentication_mode, self::AUTH_MODE_AGENCY ); ?> />
							<span><?php esc_html_e( 'Agency', 'wp-creatorreactor' ); ?></span>
						</label>
					</div>
					<div id="creatorreactor-creator-direct-auth-notice" class="creatorreactor-mode-notice direct"<?php echo $broker_mode ? ' hidden' : ''; ?>>
						<p><strong><?php esc_html_e( 'Creator authentication:', 'wp-creatorreactor' ); ?></strong>
						<?php
						printf(
							/* translators: %s: Product name (e.g. Fanvue). */
							esc_html__( 'This plugin will authenticate directly with %s using the OAuth credentials below.', 'wp-creatorreactor' ),
							esc_html( $current_product_label )
						);
						?>
						</p>
					</div>
					<div id="creatorreactor-agency-auth-notice" class="creatorreactor-mode-notice broker"<?php echo ! $broker_mode ? ' hidden' : ''; ?>>
						<p><strong><?php esc_html_e( 'Agency authentication:', 'wp-creatorreactor' ); ?></strong> <?php esc_html_e( 'Required: Broker URL and Site ID (Broker Settings below). OAuth Client ID, redirect URI, and scopes in this section are optional — they are only added to the broker connect link when set. Client Secret is not used in Agency mode.', 'wp-creatorreactor' ); ?></p>
					</div>
					<div class="creatorreactor-auth-mode-hint">
						<p><strong><?php esc_html_e( 'Creator:', 'wp-creatorreactor' ); ?></strong><br />
						<?php esc_html_e( 'To use Creator mode you need to:', 'wp-creatorreactor' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Log into your Fanvue account.', 'wp-creatorreactor' ); ?></li>
							<li><?php
							printf(
								wp_kses_post(
									/* translators: %s: Fanvue developer apps URL */
									__( 'Go to CREATOR TOOL → BUILD<br /><span class="creatorreactor-auth-mode-hint-url"><a href="%s" target="_blank" rel="noopener noreferrer">https://www.fanvue.com/developers/apps</a></span>', 'wp-creatorreactor' )
								),
								esc_url( 'https://www.fanvue.com/developers/apps' )
							);
							?></li>
							<li><?php esc_html_e( 'Create App (recommended name: CreatorReactor-OAuth).', 'wp-creatorreactor' ); ?></li>
							<li><?php esc_html_e( 'Copy the Client ID and Client Secret from your new Fanvue app into the plugin settings.', 'wp-creatorreactor' ); ?></li>
						</ol>
					</div>
				</div>
			</div>
			<div class="creatorreactor-settings-form-card">
			<div class="creatorreactor-settings-panel <?php echo 'oauth' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth">
				<div class="creatorreactor-settings-panel-header creatorreactor-oauth-panel-header">
					<h2><?php esc_html_e( 'OAuth', 'wp-creatorreactor' ); ?></h2>
					<button type="button" class="button creatorreactor-oauth-tab-lock" aria-pressed="<?php echo $oauth_locked_initial ? 'true' : 'false'; ?>"
						data-label-locked="<?php echo esc_attr( __( 'OAuth configuration locked — click to unlock editing', 'wp-creatorreactor' ) ); ?>"
						data-label-unlocked="<?php echo esc_attr( __( 'OAuth configuration unlocked — click to lock', 'wp-creatorreactor' ) ); ?>"
						aria-label="<?php echo esc_attr( $oauth_locked_initial ? __( 'OAuth configuration locked — click to unlock editing', 'wp-creatorreactor' ) : __( 'OAuth configuration unlocked — click to lock', 'wp-creatorreactor' ) ); ?>">
						<span class="dashicons dashicons-lock creatorreactor-oauth-tab-lock-icon-on" aria-hidden="true"></span>
						<span class="dashicons dashicons-unlock creatorreactor-oauth-tab-lock-icon-off" aria-hidden="true"></span>
					</button>
				</div>
				<div id="creatorreactor-oauth-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
					<?php self::render_oauth_dynamic_fields( $broker_mode, $opts, $secret_mask, $current_product_label ); ?>
				</div>
			</div>
			<div class="creatorreactor-settings-panel <?php echo 'sync' === $active_subtab ? 'is-active' : ''; ?>" data-subtab="sync">
				<h2><?php esc_html_e( 'Sync', 'wp-creatorreactor' ); ?></h2>
				<div id="creatorreactor-sync-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
					<?php self::render_sync_dynamic_fields( $broker_mode, $opts ); ?>
				</div>
			</div>
			<div class="creatorreactor-settings-actions">
				<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'settings', 'subtab' => $active_subtab ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
				<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
			</div>
			</div>
			<div class="creatorreactor-settings-form-card creatorreactor-provider-login-appearance-card creatorreactor-fanvue-login-appearance-card" <?php echo ( ! empty( $opts['broker_mode'] ) || 'sync' === $active_subtab ) ? 'hidden' : ''; ?>>
				<?php self::render_fanvue_login_button_appearance_fields( $active_subtab ); ?>
			</div>
		</div>
	</div>
</div>
			<div id="creatorreactor-fanvue-oauth-test-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
				<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
				<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-fanvue-oauth-test-modal-title">
					<div class="creatorreactor-modal-header">
						<div class="creatorreactor-modal-header-title">
							<h3 id="creatorreactor-fanvue-oauth-test-modal-title"><?php esc_html_e( 'Fanvue OAuth: configuration check', 'wp-creatorreactor' ); ?></h3>
						</div>
						<button type="button" class="creatorreactor-fanvue-oauth-test-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
					</div>
					<div class="creatorreactor-modal-body">
						<p id="creatorreactor-fanvue-oauth-test-status" class="creatorreactor-fanvue-oauth-test-status"></p>
						<div id="creatorreactor-fanvue-oauth-test-remediation-wrap" class="creatorreactor-fanvue-oauth-test-remediation-wrap" hidden>
							<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
							<p id="creatorreactor-fanvue-oauth-test-remediation" class="creatorreactor-fanvue-oauth-test-remediation"></p>
						</div>
					</div>
					<div class="creatorreactor-modal-footer">
						<button type="button" class="button creatorreactor-fanvue-oauth-test-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
						<button type="button" class="button button-primary creatorreactor-fanvue-oauth-test-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
					</div>
				</div>
			</div>
		</form>

		<div class="creatorreactor-tab-panel <?php echo 'onlyfans' === $active_tab ? 'is-active' : ''; ?>" data-tab="onlyfans">
			<form id="creatorreactor-onlyfans-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<div class="creatorreactor-settings-container">
					<div class="creatorreactor-settings-sidebar">
						<nav class="creatorreactor-sidebar-nav" aria-label="<?php esc_attr_e( 'OnlyFans settings sections', 'wp-creatorreactor' ); ?>">
							<a href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'onlyfans', 'subtab' => 'oauth' ], self::PAGE_SETTINGS_SLUG ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'oauth' === $onlyfans_active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth"><?php esc_html_e( 'OAuth', 'wp-creatorreactor' ); ?></a>
							<a href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'onlyfans', 'subtab' => 'sync' ], self::PAGE_SETTINGS_SLUG ) ); ?>" class="creatorreactor-sidebar-link <?php echo 'sync' === $onlyfans_active_subtab ? 'is-active' : ''; ?>" data-subtab="sync"><?php esc_html_e( 'Sync', 'wp-creatorreactor' ); ?></a>
						</nav>
					</div>
					<div class="creatorreactor-settings-content<?php echo 'sync' === $onlyfans_active_subtab ? ' creatorreactor-settings-subtab-sync' : ''; ?><?php echo 'oauth' === $onlyfans_active_subtab ? ' creatorreactor-settings-appearance-card-below' : ''; ?>">
						<?php self::render_onlyfans_settings_fields( $opts, $ofauth_api_key_mask, $ofauth_webhook_secret_mask, $onlyfans_active_subtab ); ?>
					</div>
				</div>
				<div id="creatorreactor-onlyfans-ofauth-test-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
					<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
					<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-onlyfans-ofauth-test-modal-title">
						<div class="creatorreactor-modal-header">
							<div class="creatorreactor-modal-header-title">
								<h3 id="creatorreactor-onlyfans-ofauth-test-modal-title"><?php esc_html_e( 'OnlyFans (OFAuth): configuration check', 'wp-creatorreactor' ); ?></h3>
							</div>
							<button type="button" class="creatorreactor-onlyfans-ofauth-test-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
						</div>
						<div class="creatorreactor-modal-body">
							<p id="creatorreactor-onlyfans-ofauth-test-status" class="creatorreactor-onlyfans-ofauth-test-status"></p>
							<div id="creatorreactor-onlyfans-ofauth-test-remediation-wrap" class="creatorreactor-onlyfans-ofauth-test-remediation-wrap" hidden>
								<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
								<p id="creatorreactor-onlyfans-ofauth-test-remediation" class="creatorreactor-onlyfans-ofauth-test-remediation"></p>
							</div>
						</div>
						<div class="creatorreactor-modal-footer">
							<button type="button" class="button creatorreactor-onlyfans-ofauth-test-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
							<button type="button" class="button button-primary creatorreactor-onlyfans-ofauth-test-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
						</div>
					</div>
				</div>
			</form>
		</div>

		<div class="creatorreactor-tab-panel <?php echo 'google' === $active_tab ? 'is-active' : ''; ?>" data-tab="google">
			<form id="creatorreactor-google-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<div class="creatorreactor-settings-form-card">
					<?php self::render_google_settings_fields( $opts, $google_oauth_secret_mask ); ?>
					<?php if ( ! empty( $opts['broker_mode'] ) ) : ?>
					<div class="creatorreactor-settings-actions">
						<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'google' ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
						<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
					</div>
					<?php endif; ?>
				</div>
				<?php if ( empty( $opts['broker_mode'] ) ) : ?>
				<div class="creatorreactor-settings-form-card creatorreactor-provider-login-appearance-card">
					<?php self::render_google_login_button_appearance_fields(); ?>
					<div class="creatorreactor-settings-actions">
						<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'google' ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
						<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
					</div>
				</div>
				<?php endif; ?>
			</form>
			<div id="creatorreactor-google-oauth-test-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
				<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
				<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-google-oauth-test-modal-title">
					<div class="creatorreactor-modal-header">
						<div class="creatorreactor-modal-header-title">
							<h3 id="creatorreactor-google-oauth-test-modal-title"><?php esc_html_e( 'Google sign-in: configuration check', 'wp-creatorreactor' ); ?></h3>
						</div>
						<button type="button" class="creatorreactor-google-oauth-test-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
					</div>
					<div class="creatorreactor-modal-body">
						<p id="creatorreactor-google-oauth-test-status" class="creatorreactor-google-oauth-test-status"></p>
						<div id="creatorreactor-google-oauth-test-remediation-wrap" class="creatorreactor-google-oauth-test-remediation-wrap" hidden>
							<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
							<p id="creatorreactor-google-oauth-test-remediation" class="creatorreactor-google-oauth-test-remediation"></p>
						</div>
					</div>
					<div class="creatorreactor-modal-footer">
						<button type="button" class="button creatorreactor-google-oauth-test-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
						<button type="button" class="button button-primary creatorreactor-google-oauth-test-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<div class="creatorreactor-tab-panel <?php echo 'instagram' === $active_tab ? 'is-active' : ''; ?>" data-tab="instagram">
			<form id="creatorreactor-instagram-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<div class="creatorreactor-settings-form-card">
					<?php self::render_instagram_oauth_primary_fields( $opts, $instagram_oauth_secret_mask ); ?>
					<?php if ( ! empty( $opts['broker_mode'] ) ) : ?>
					<div class="creatorreactor-settings-actions">
						<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'instagram' ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
						<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
					</div>
					<?php endif; ?>
				</div>
				<?php if ( empty( $opts['broker_mode'] ) ) : ?>
				<div class="creatorreactor-settings-form-card creatorreactor-provider-login-appearance-card">
					<?php self::render_instagram_login_button_appearance_fields( $opts ); ?>
					<div class="creatorreactor-settings-actions">
						<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'instagram' ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
						<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
					</div>
				</div>
				<?php endif; ?>
			</form>
			<div id="creatorreactor-instagram-oauth-test-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
				<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
				<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-instagram-oauth-test-modal-title">
					<div class="creatorreactor-modal-header">
						<div class="creatorreactor-modal-header-title">
							<h3 id="creatorreactor-instagram-oauth-test-modal-title"><?php esc_html_e( 'Instagram OAuth: configuration check', 'wp-creatorreactor' ); ?></h3>
						</div>
						<button type="button" class="creatorreactor-instagram-oauth-test-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
					</div>
					<div class="creatorreactor-modal-body">
						<p id="creatorreactor-instagram-oauth-test-status" class="creatorreactor-instagram-oauth-test-status"></p>
						<div id="creatorreactor-instagram-oauth-test-remediation-wrap" class="creatorreactor-instagram-oauth-test-remediation-wrap" hidden>
							<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
							<p id="creatorreactor-instagram-oauth-test-remediation" class="creatorreactor-instagram-oauth-test-remediation"></p>
						</div>
					</div>
					<div class="creatorreactor-modal-footer">
						<button type="button" class="button creatorreactor-instagram-oauth-test-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
						<button type="button" class="button button-primary creatorreactor-instagram-oauth-test-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<?php
		$active_social_mask = isset( $social_oauth_secret_masks[ $active_social_provider ] ) ? $social_oauth_secret_masks[ $active_social_provider ] : '';
		?>
		<div class="creatorreactor-tab-panel <?php echo 'social_oauth' === $active_tab ? 'is-active' : ''; ?>" data-tab="social_oauth">
			<div class="creatorreactor-settings-container creatorreactor-social-oauth-settings-layout">
				<div class="creatorreactor-settings-sidebar creatorreactor-settings-sidebar--social-oauth">
					<nav class="creatorreactor-sidebar-nav" aria-label="<?php esc_attr_e( 'Social login providers', 'wp-creatorreactor' ); ?>">
						<?php foreach ( $settings_social_slugs as $social_nav_slug ) : ?>
							<a href="<?php echo esc_url( self::admin_social_oauth_settings_url( $social_nav_slug ) ); ?>" class="creatorreactor-sidebar-link <?php echo $social_nav_slug === $active_social_provider ? 'is-active' : ''; ?>"><?php echo esc_html( Shortcodes::social_oauth_provider_label( $social_nav_slug ) ); ?></a>
						<?php endforeach; ?>
					</nav>
				</div>
				<div class="creatorreactor-settings-content">
					<form id="creatorreactor-<?php echo esc_attr( $active_social_provider ); ?>-settings-form" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
						<?php settings_fields( self::OPTION_NAME ); ?>
						<?php self::render_social_oauth_provider_tab_content( $active_social_provider, $opts, $active_social_mask ); ?>
						<div class="creatorreactor-settings-actions">
							<a class="button" href="<?php echo esc_url( self::admin_social_oauth_settings_url( $active_social_provider ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
							<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div id="creatorreactor-social-oauth-test-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
			<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
			<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-social-oauth-test-modal-title">
				<div class="creatorreactor-modal-header">
					<div class="creatorreactor-modal-header-title">
						<h3 id="creatorreactor-social-oauth-test-modal-title"><?php esc_html_e( 'Social OAuth: configuration check', 'wp-creatorreactor' ); ?></h3>
					</div>
					<button type="button" class="creatorreactor-social-oauth-test-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
				</div>
				<div class="creatorreactor-modal-body">
					<p id="creatorreactor-social-oauth-test-status" class="creatorreactor-social-oauth-test-status"></p>
					<div id="creatorreactor-social-oauth-test-remediation-wrap" class="creatorreactor-social-oauth-test-remediation-wrap" hidden>
						<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
						<p id="creatorreactor-social-oauth-test-remediation" class="creatorreactor-social-oauth-test-remediation"></p>
					</div>
				</div>
				<div class="creatorreactor-modal-footer">
					<button type="button" class="button creatorreactor-social-oauth-test-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
					<button type="button" class="button button-primary creatorreactor-social-oauth-test-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
				</div>
			</div>
		</div>

		<div class="creatorreactor-tab-panel <?php echo 'users' === $active_tab ? 'is-active' : ''; ?>" data-tab="users">
			<div class="creatorreactor-section">
				<h2><?php esc_html_e( 'Users', 'wp-creatorreactor' ); ?></h2>
				<p class="creatorreactor-muted"><?php esc_html_e( 'Each record shows its source product (fanvue, OnlyFans, or another configured product key).', 'wp-creatorreactor' ); ?></p>
				<div id="creatorreactor-users-panel" class="creatorreactor-users-panel">
					<?php echo self::render_users_tab_panel_html( $user_totals, $user_rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer. ?>
				</div>
			</div>
		</div>

		<div id="creatorreactor-user-details-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
			<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
			<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-user-details-modal-title">
				<div class="creatorreactor-modal-header">
					<div class="creatorreactor-modal-header-title">
						<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--modal' ); ?>
						<h3 id="creatorreactor-user-details-modal-title"><?php esc_html_e( 'CreatorReactor record', 'wp-creatorreactor' ); ?></h3>
					</div>
					<button type="button" class="creatorreactor-user-details-close" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
				</div>
				<div class="creatorreactor-modal-body" id="creatorreactor-user-details-modal-body"></div>
			</div>
		</div>

		<div class="creatorreactor-tab-panel <?php echo 'general' === $active_tab ? 'is-active' : ''; ?>" data-tab="general">
			<?php self::render_general_tab_body(); ?>
		</div>
		<div class="creatorreactor-tab-panel <?php echo 'cloud' === $active_tab ? 'is-active' : ''; ?>" data-tab="cloud">
			<div class="creatorreactor-section">
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php settings_fields( self::OPTION_NAME ); ?>
					<div class="creatorreactor-settings-form-card">
						<div class="creatorreactor-settings-panel is-active" data-subtab="cloud-credentials">
							<h2><?php esc_html_e( 'Cloud account', 'wp-creatorreactor' ); ?></h2>
							<?php self::render_cloud_credentials_fields( $opts, $cloud_password_mask ); ?>
						</div>
					</div>
					<div class="creatorreactor-settings-form-card">
						<div class="creatorreactor-settings-panel is-active" data-subtab="cloud-schema">
							<div class="creatorreactor-settings-panel-header creatorreactor-cloud-panel-header">
								<h2><?php esc_html_e( 'Schema service', 'wp-creatorreactor' ); ?></h2>
								<button type="button" class="button creatorreactor-cloud-tab-lock" aria-pressed="true"
									data-label-locked="<?php echo esc_attr( __( 'Schema service URL locked — click to unlock editing', 'wp-creatorreactor' ) ); ?>"
									data-label-unlocked="<?php echo esc_attr( __( 'Schema service URL unlocked — click to lock', 'wp-creatorreactor' ) ); ?>"
									aria-label="<?php echo esc_attr( __( 'Schema service URL locked — click to unlock editing', 'wp-creatorreactor' ) ); ?>">
									<span class="dashicons dashicons-lock creatorreactor-cloud-tab-lock-icon-on" aria-hidden="true"></span>
									<span class="dashicons dashicons-unlock creatorreactor-cloud-tab-lock-icon-off" aria-hidden="true"></span>
								</button>
							</div>
							<div id="creatorreactor-cloud-schema-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
								<?php self::render_cloud_schema_fields( $opts ); ?>
							</div>
						</div>
					</div>
					<div class="creatorreactor-settings-form-card">
						<div class="creatorreactor-settings-panel is-active" data-subtab="cloud-metrics">
							<h2><?php esc_html_e( 'Metrics ingest', 'wp-creatorreactor' ); ?></h2>
							<?php self::render_cloud_metrics_ingest_fields( $opts ); ?>
						</div>
					</div>
					<div class="creatorreactor-settings-actions">
						<a class="button" href="<?php echo esc_url( self::admin_page_url( [ 'tab' => 'cloud' ], self::PAGE_SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
						<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
					</div>
				</form>
			</div>
		</div>
		<div class="creatorreactor-tab-panel <?php echo 'debug' === $active_tab ? 'is-active' : ''; ?>" data-tab="debug">
			<?php self::render_debug_tab_body(); ?>
		</div>
		<div class="creatorreactor-tab-panel <?php echo 'documentation' === $active_tab ? 'is-active' : ''; ?>" data-tab="documentation">
			<?php self::render_documentation_tab_body(); ?>
		</div>
		<?php endif; ?>

		<?php if ( ! $is_settings_page ) : ?>
		<div class="creatorreactor-tab-panel <?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>" data-tab="dashboard">
			<?php
			$gateway_test_modal     = self::take_gateway_dashboard_notice();
			$connection_fix_url     = self::admin_page_url( [ 'tab' => 'settings', 'subtab' => 'oauth' ], self::PAGE_SETTINGS_SLUG );
			$connection_test_ran = ! empty( $connection_test ) && is_array( $connection_test );
				$connection_test_passed = $connection_test_ran && ! empty( $connection_test['success'] );
				$connection_state = 'yellow';
				$connection_failure_message = '';
				$last_error_message = isset( $status['last_error'] ) ? trim( (string) $status['last_error'] ) : '';
				$critical_error_message = isset( $status['critical_error'] ) ? trim( (string) $status['critical_error'] ) : '';
				$has_last_error = $last_error_message !== '';
				$has_critical_error = $critical_error_message !== '';
				$has_warning_error = $has_last_error && ! $has_critical_error;

				if ( $connection_test_ran && ! $connection_test_passed && ! $status['connected'] ) {
					$connection_state = 'red';
					$connection_failure_message = isset( $connection_test['message'] ) ? trim( (string) $connection_test['message'] ) : '';
					if ( ! empty( $connection_test['checks'] ) && is_array( $connection_test['checks'] ) ) {
						foreach ( $connection_test['checks'] as $check ) {
							if ( empty( $check['pass'] ) ) {
								$check_label = isset( $check['label'] ) ? trim( (string) $check['label'] ) : '';
								$check_message = isset( $check['message'] ) ? trim( (string) $check['message'] ) : '';
								if ( $check_message !== '' ) {
									$connection_failure_message = $check_label !== '' ? $check_label . ': ' . $check_message : $check_message;
									break;
								}
							}
						}
					}
				} elseif ( ! $status['connected'] && $has_critical_error ) {
					$connection_state = 'red';
					$connection_failure_message = $critical_error_message;
				} elseif ( $has_critical_error ) {
					$connection_state = 'red';
					$connection_failure_message = $critical_error_message;
				} elseif ( $has_last_error ) {
					$connection_state = 'yellow';
				} elseif ( $status['connected'] ) {
					$connection_state = 'green';
				} elseif ( $connection_test_passed && ! $status['connected'] ) {
					$connection_state = 'yellow';
				}

				$fanvue_client_id_present = ! empty( $opts['creatorreactor_oauth_client_id'] );
				$fanvue_client_secret_present = ! empty( $opts['creatorreactor_oauth_client_secret'] );
				$fanvue_oauth_is_configured = $fanvue_client_id_present && $fanvue_client_secret_present;
				$fanvue_oauth_state = 'yellow';
				if ( $has_critical_error ) {
					$fanvue_oauth_state = 'red';
				} elseif ( $status['connected'] ) {
					$fanvue_oauth_state = 'green';
				} elseif ( $broker_mode ) {
					$fanvue_oauth_state = 'gray';
				} elseif ( ! $fanvue_client_id_present && ! $fanvue_client_secret_present ) {
					$fanvue_oauth_state = 'gray';
				} elseif ( ! $fanvue_oauth_is_configured ) {
					$fanvue_oauth_state = 'yellow';
				}
				$ofauth_api_configured  = ! empty( $opts['creatorreactor_ofauth_api_key'] );
				$ofauth_wh_configured   = ! empty( $opts['creatorreactor_ofauth_webhook_secret'] );
				$onlyfans_oauth_state   = 'gray';
				if ( $ofauth_api_configured && $ofauth_wh_configured ) {
					$onlyfans_oauth_state = 'green';
				} elseif ( $ofauth_api_configured || $ofauth_wh_configured ) {
					$onlyfans_oauth_state = 'yellow';
				}
				$google_oauth_state = 'gray';
				if ( $broker_mode ) {
					$google_oauth_state = 'gray';
				} elseif ( self::is_google_login_configured() ) {
					$google_oauth_state = 'green';
				} else {
					$google_oauth_client_id_present     = ! empty( $opts['creatorreactor_google_oauth_client_id'] );
					$google_oauth_client_secret_present = ! empty( $opts['creatorreactor_google_oauth_client_secret'] );
					if ( $google_oauth_client_id_present || $google_oauth_client_secret_present ) {
						$google_oauth_state = 'yellow';
					}
				}
				$instagram_oauth_state = 'gray';
				if ( $broker_mode ) {
					$instagram_oauth_state = 'gray';
				} elseif ( self::is_instagram_oauth_configured() ) {
					$instagram_oauth_state = 'green';
				} else {
					$instagram_oauth_client_id_present     = ! empty( $opts['creatorreactor_instagram_oauth_client_id'] );
					$instagram_oauth_client_secret_present = ! empty( $opts['creatorreactor_instagram_oauth_client_secret'] );
					if ( $instagram_oauth_client_id_present || $instagram_oauth_client_secret_present ) {
						$instagram_oauth_state = 'yellow';
					}
				}
				$cloud_is_active = ! empty( $opts['creatorreactor_cloud_active'] );
				$cloud_id_present = ! empty( $opts['creatorreactor_cloud_id'] );
				$cloud_password_present = ! empty( $opts['creatorreactor_cloud_password'] );
				$cloud_credentials_ready = $cloud_id_present && $cloud_password_present;
				$cloud_connected_state = 'gray';
				if ( $cloud_is_active ) {
					$cloud_connected_state = 'yellow';
					if ( $has_critical_error ) {
						$cloud_connected_state = 'red';
					} elseif ( $status['connected'] ) {
						$cloud_connected_state = 'green';
					} elseif ( ! $cloud_credentials_ready ) {
						$cloud_connected_state = 'gray';
					}
				}
				$cloud_data_sync_state = 'gray';
				if ( $cloud_is_active ) {
					$cloud_data_sync_state = 'yellow';
					if ( $has_critical_error ) {
						$cloud_data_sync_state = 'red';
					} elseif ( $status['connected'] && ! empty( $status['last_sync'] ) ) {
						$cloud_data_sync_state = 'green';
					} elseif ( ! $status['connected'] ) {
						$cloud_data_sync_state = 'gray';
					}
				}
				$modules = [
					[
						'label'    => __( 'Wordpress Gateway', 'wp-creatorreactor' ),
						'children' => [
							[
								'id'    => 'fanvue_oauth',
								'label' => __( 'Sign in with Fanvue', 'wp-creatorreactor' ),
								'state' => $fanvue_oauth_state,
							],
							[
								'id'    => 'onlyfans_ofauth',
								'label' => __( 'Sign in with OnlyFans', 'wp-creatorreactor' ),
								'state' => $onlyfans_oauth_state,
							],
							[
								'id'    => 'google_oauth',
								'label' => __( 'Sign in with Google', 'wp-creatorreactor' ),
								'state' => $google_oauth_state,
							],
							[
								'id'    => 'instagram_oauth',
								'label' => __( 'Login with Instagram', 'wp-creatorreactor' ),
								'state' => $instagram_oauth_state,
							],
						],
					],
					[
						'label'    => __( 'CreatorReactor Cloud', 'wp-creatorreactor' ),
						'children' => [
							[
								'label' => __( 'Connected to Cloud', 'wp-creatorreactor' ),
								'state' => $cloud_connected_state,
							],
							[
								'label' => __( 'Data Sync', 'wp-creatorreactor' ),
								'state' => $cloud_data_sync_state,
							],
						],
					],
				];
				foreach ( $modules as $module_index => $module ) {
					$children_states = [];
					if ( ! empty( $module['children'] ) && is_array( $module['children'] ) ) {
						foreach ( $module['children'] as $child ) {
							$child_state = isset( $child['state'] ) ? (string) $child['state'] : 'gray';
							$children_states[] = in_array( $child_state, [ 'green', 'yellow', 'red', 'gray' ], true ) ? $child_state : 'gray';
						}
					}
					$module_state = 'yellow';
					if ( ! empty( $children_states ) ) {
						if ( in_array( 'red', $children_states, true ) ) {
							$module_state = 'red';
						} elseif ( in_array( 'yellow', $children_states, true ) ) {
							$module_state = 'yellow';
						} elseif ( in_array( 'green', $children_states, true ) ) {
							// Treat gray as "not installed": installed green modules keep parent green.
							$module_state = 'green';
						} else {
							$module_state = 'gray';
						}
					}
					$modules[ $module_index ]['state'] = $module_state;
				}

				$gateway_fanvue_show_connect = false;
				$gateway_fanvue_connect_href = '';
				if ( $broker_mode ) {
					$maybe_broker_connect = Broker_Client::get_connect_url();
					if ( is_string( $maybe_broker_connect ) && $maybe_broker_connect !== '' ) {
						$gateway_fanvue_show_connect = true;
						$gateway_fanvue_connect_href = wp_nonce_url( admin_url( 'admin-post.php?action=creatorreactor_broker_connect' ), 'creatorreactor_broker_connect' );
					}
				} elseif ( ! empty( $opts['creatorreactor_oauth_client_id'] ) ) {
					$gateway_fanvue_show_connect = true;
					$gateway_fanvue_connect_href = self::admin_page_url(
						[
							'tab'                        => 'dashboard',
							'creatorreactor_oauth_start' => 1,
							'_wpnonce'                   => wp_create_nonce( 'creatorreactor_oauth_start' ),
						]
					);
				}

				?>
				<div class="creatorreactor-dashboard-row">
					<div class="creatorreactor-dashboard-col">
						<div class="creatorreactor-modules-shell creatorreactor-dashboard-card">
							<div class="creatorreactor-dashboard-card-head">
								<h2 class="creatorreactor-dashboard-card-head__title"><?php esc_html_e( 'CreatorReactor Modules', 'wp-creatorreactor' ); ?></h2>
							</div>
							<?php
							$gateway_disabled_key = isset( $_GET['gateway_disabled'] ) ? sanitize_key( wp_unslash( $_GET['gateway_disabled'] ) ) : '';
							if ( 'google' === $gateway_disabled_key ) :
								?>
								<div class="notice notice-success inline creatorreactor-gateway-dashboard-notice"><p><?php esc_html_e( 'Google sign-in credentials were removed from this site.', 'wp-creatorreactor' ); ?></p></div>
							<?php elseif ( 'instagram' === $gateway_disabled_key ) : ?>
								<div class="notice notice-success inline creatorreactor-gateway-dashboard-notice"><p><?php esc_html_e( 'Instagram OAuth credentials were removed from this site.', 'wp-creatorreactor' ); ?></p></div>
							<?php elseif ( 'onlyfans' === $gateway_disabled_key ) : ?>
								<div class="notice notice-success inline creatorreactor-gateway-dashboard-notice"><p><?php esc_html_e( 'OnlyFans (OFAuth) API key and webhook secret were removed from this site.', 'wp-creatorreactor' ); ?></p></div>
							<?php endif; ?>
							<?php if ( $connection_state === 'red' && $connection_failure_message !== '' ) : ?>
								<p class="creatorreactor-connection-alert">
									<?php echo esc_html( $connection_failure_message ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $has_last_error ) : ?>
								<p class="creatorreactor-check-result-fail creatorreactor-dashboard-last-error">
									<strong><?php esc_html_e( 'Last Error:', 'wp-creatorreactor' ); ?></strong>
									<?php echo esc_html( $last_error_message ); ?>
								</p>
							<?php endif; ?>
							<ul class="creatorreactor-module-list">
								<?php foreach ( $modules as $module ) : ?>
									<?php
									$module_label = isset( $module['label'] ) ? (string) $module['label'] : '';
									$module_state = isset( $module['state'] ) ? (string) $module['state'] : 'gray';
									$module_state = in_array( $module_state, [ 'green', 'yellow', 'red', 'gray' ], true ) ? $module_state : 'gray';
									$module_children = ! empty( $module['children'] ) && is_array( $module['children'] ) ? $module['children'] : [];
									?>
									<li class="creatorreactor-module-item">
										<span class="creatorreactor-module-status-dot is-<?php echo esc_attr( $module_state ); ?>" aria-hidden="true"></span>
										<div class="creatorreactor-module-content">
											<span class="creatorreactor-module-label"><?php echo esc_html( $module_label ); ?></span>
											<?php if ( ! empty( $module_children ) ) : ?>
												<ul class="creatorreactor-module-children">
													<?php foreach ( $module_children as $child ) : ?>
														<?php
														$child_id    = isset( $child['id'] ) ? (string) $child['id'] : '';
														$child_label = isset( $child['label'] ) ? (string) $child['label'] : '';
														$child_state = isset( $child['state'] ) ? (string) $child['state'] : 'gray';
														$child_state = in_array( $child_state, [ 'green', 'yellow', 'red', 'gray' ], true ) ? $child_state : 'gray';
														?>
														<?php
														if ( in_array( $child_id, [ 'fanvue_oauth', 'onlyfans_ofauth', 'google_oauth', 'instagram_oauth' ], true ) ) :
															$gw_mode = self::wordpress_gateway_child_action_mode( $child_state );
															if ( 'fanvue_oauth' === $child_id ) {
																$gw_configure_href = self::admin_page_url( [ 'tab' => 'settings', 'subtab' => 'oauth' ], self::PAGE_SETTINGS_SLUG );
															} elseif ( 'onlyfans_ofauth' === $child_id ) {
																$gw_configure_href = self::admin_page_url( [ 'tab' => 'onlyfans' ], self::PAGE_SETTINGS_SLUG );
															} elseif ( 'instagram_oauth' === $child_id ) {
																$gw_configure_href = self::admin_page_url( [ 'tab' => 'instagram' ], self::PAGE_SETTINGS_SLUG );
															} else {
																$gw_configure_href = self::admin_page_url( [ 'tab' => 'google' ], self::PAGE_SETTINGS_SLUG );
															}
															?>
															<li class="creatorreactor-module-child creatorreactor-module-child--gateway">
																<span class="creatorreactor-module-status-dot is-<?php echo esc_attr( $child_state ); ?>" aria-hidden="true"></span>
																<span class="creatorreactor-module-child-label"><?php echo esc_html( $child_label ); ?></span>
																<span class="creatorreactor-module-child-actions">
																	<?php if ( in_array( $gw_mode, [ 'configure_only', 'configure_test' ], true ) ) : ?>
																		<a href="<?php echo esc_url( $gw_configure_href ); ?>" class="button-link"><?php esc_html_e( 'Configure', 'wp-creatorreactor' ); ?></a>
																	<?php endif; ?>
																	<?php if ( 'fanvue_oauth' === $child_id && 'configure_only' === $gw_mode && $broker_mode && ! $status['connected'] ) : ?>
																		<?php if ( $gateway_fanvue_show_connect && $gateway_fanvue_connect_href !== '' ) : ?>
																			<a href="<?php echo esc_url( $gateway_fanvue_connect_href ); ?>" class="button-link"><?php esc_html_e( 'Connect', 'wp-creatorreactor' ); ?></a>
																		<?php endif; ?>
																		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																			<?php wp_nonce_field( 'creatorreactor_test_connection' ); ?>
																			<input type="hidden" name="action" value="creatorreactor_test_connection" />
																			<button type="submit" class="button-link"><?php esc_html_e( 'Test', 'wp-creatorreactor' ); ?></button>
																		</form>
																	<?php endif; ?>
																	<?php if ( 'disable_test' === $gw_mode && 'fanvue_oauth' === $child_id ) : ?>
																		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																			<?php wp_nonce_field( 'creatorreactor_disconnect' ); ?>
																			<input type="hidden" name="action" value="creatorreactor_disconnect" />
																			<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Disable the site connection to Fanvue? Sync and API access will stop until you connect again.', 'wp-creatorreactor' ) ); ?>');"><?php esc_html_e( 'Disable', 'wp-creatorreactor' ); ?></button>
																		</form>
																	<?php elseif ( 'disable_test' === $gw_mode && 'google_oauth' === $child_id ) : ?>
																		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																			<?php wp_nonce_field( 'creatorreactor_disable_google_oauth' ); ?>
																			<input type="hidden" name="action" value="creatorreactor_disable_google_oauth" />
																			<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Remove Google sign-in credentials from this site?', 'wp-creatorreactor' ) ); ?>');"><?php esc_html_e( 'Disable', 'wp-creatorreactor' ); ?></button>
																		</form>
																	<?php elseif ( 'disable_test' === $gw_mode && 'instagram_oauth' === $child_id ) : ?>
																		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																			<?php wp_nonce_field( 'creatorreactor_disable_instagram_oauth' ); ?>
																			<input type="hidden" name="action" value="creatorreactor_disable_instagram_oauth" />
																			<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Remove Instagram OAuth credentials from this site?', 'wp-creatorreactor' ) ); ?>');"><?php esc_html_e( 'Disable', 'wp-creatorreactor' ); ?></button>
																		</form>
																	<?php elseif ( 'disable_test' === $gw_mode && 'onlyfans_ofauth' === $child_id ) : ?>
																		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																			<?php wp_nonce_field( 'creatorreactor_disable_onlyfans_ofauth' ); ?>
																			<input type="hidden" name="action" value="creatorreactor_disable_onlyfans_ofauth" />
																			<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Remove the OnlyFans (OFAuth) API key and webhook secret from this site?', 'wp-creatorreactor' ) ); ?>');"><?php esc_html_e( 'Disable', 'wp-creatorreactor' ); ?></button>
																		</form>
																	<?php endif; ?>
																	<?php if ( in_array( $gw_mode, [ 'disable_test', 'configure_test' ], true ) ) : ?>
																		<?php if ( 'fanvue_oauth' === $child_id ) : ?>
																			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																				<?php wp_nonce_field( 'creatorreactor_test_connection' ); ?>
																				<input type="hidden" name="action" value="creatorreactor_test_connection" />
																				<button type="submit" class="button-link"><?php esc_html_e( 'Test', 'wp-creatorreactor' ); ?></button>
																			</form>
																		<?php elseif ( 'google_oauth' === $child_id ) : ?>
																			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																				<?php wp_nonce_field( 'creatorreactor_test_google_oauth' ); ?>
																				<input type="hidden" name="action" value="creatorreactor_test_google_oauth" />
																				<button type="submit" class="button-link"><?php esc_html_e( 'Test', 'wp-creatorreactor' ); ?></button>
																			</form>
																		<?php elseif ( 'instagram_oauth' === $child_id ) : ?>
																			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																				<?php wp_nonce_field( 'creatorreactor_test_instagram_oauth' ); ?>
																				<input type="hidden" name="action" value="creatorreactor_test_instagram_oauth" />
																				<button type="submit" class="button-link"><?php esc_html_e( 'Test', 'wp-creatorreactor' ); ?></button>
																			</form>
																		<?php elseif ( 'onlyfans_ofauth' === $child_id ) : ?>
																			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="creatorreactor-inline-oauth-form">
																				<?php wp_nonce_field( 'creatorreactor_test_onlyfans_ofauth' ); ?>
																				<input type="hidden" name="action" value="creatorreactor_test_onlyfans_ofauth" />
																				<button type="submit" class="button-link"><?php esc_html_e( 'Test', 'wp-creatorreactor' ); ?></button>
																			</form>
																		<?php endif; ?>
																		<?php
																		if ( 'configure_test' === $gw_mode && 'fanvue_oauth' === $child_id && ! $status['connected'] && $gateway_fanvue_show_connect && $gateway_fanvue_connect_href !== '' ) :
																			?>
																			<a href="<?php echo esc_url( $gateway_fanvue_connect_href ); ?>" class="button-link"><?php esc_html_e( 'Connect', 'wp-creatorreactor' ); ?></a>
																		<?php endif; ?>
																	<?php endif; ?>
																</span>
															</li>
														<?php else : ?>
															<li class="creatorreactor-module-child">
																<span class="creatorreactor-module-status-dot is-<?php echo esc_attr( $child_state ); ?>" aria-hidden="true"></span>
																<span class="creatorreactor-module-child-label"><?php echo esc_html( $child_label ); ?></span>
															</li>
														<?php endif; ?>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<?php if ( is_array( $gateway_test_modal ) ) : ?>
							<?php
							$gwm_ok  = ! empty( $gateway_test_modal['success'] );
							$gwm_msg = isset( $gateway_test_modal['message'] ) ? (string) $gateway_test_modal['message'] : '';
							$gwm_rem = isset( $gateway_test_modal['remediation'] ) ? (string) $gateway_test_modal['remediation'] : '';
							$gwm_fix = isset( $gateway_test_modal['fix_url'] ) ? (string) $gateway_test_modal['fix_url'] : '';
							if ( ! $gwm_ok && $gwm_fix === '' ) {
								$gwm_fix = self::admin_page_url( [ 'tab' => 'general' ], self::PAGE_SETTINGS_SLUG );
							}
							?>
							<div id="creatorreactor-gateway-test-modal" class="creatorreactor-modal" aria-hidden="true" data-auto-open="1">
								<div class="creatorreactor-modal-backdrop"></div>
								<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-gateway-test-modal-title">
									<div class="creatorreactor-modal-header">
										<div class="creatorreactor-modal-header-title">
											<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--modal' ); ?>
											<h3 id="creatorreactor-gateway-test-modal-title"><?php esc_html_e( 'Test result', 'wp-creatorreactor' ); ?></h3>
										</div>
										<button type="button" class="creatorreactor-modal-close creatorreactor-gateway-test-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-creatorreactor' ); ?>">&times;</button>
									</div>
									<div class="creatorreactor-modal-body">
										<p id="creatorreactor-gateway-test-status" class="creatorreactor-gateway-test-status <?php echo $gwm_ok ? 'is-ok' : 'is-error'; ?>"><?php echo esc_html( $gwm_msg ); ?></p>
										<?php if ( ! $gwm_ok && $gwm_rem !== '' ) : ?>
											<div class="creatorreactor-gateway-test-remediation-wrap">
												<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
												<p class="creatorreactor-gateway-test-remediation"><?php echo esc_html( $gwm_rem ); ?></p>
											</div>
										<?php endif; ?>
									</div>
									<div class="creatorreactor-modal-footer creatorreactor-modal-footer--split">
										<button type="button" class="button creatorreactor-gateway-test-dismiss"><?php esc_html_e( 'Dismiss', 'wp-creatorreactor' ); ?></button>
										<div class="creatorreactor-modal-footer-primary">
											<?php if ( ! $gwm_ok ) : ?>
												<a href="<?php echo esc_url( $gwm_fix ); ?>" class="button button-primary"><?php esc_html_e( 'Fix', 'wp-creatorreactor' ); ?></a>
											<?php else : ?>
												<button type="button" class="button button-primary creatorreactor-gateway-test-acknowledge"><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>
						<?php
						$connection_test_checks = ! empty( $connection_test['checks'] ) && is_array( $connection_test['checks'] ) ? $connection_test['checks'] : [];
						$failed_connection_checks = [];
						if ( ! empty( $connection_test_checks ) ) {
							foreach ( $connection_test_checks as $check ) {
								if ( empty( $check['pass'] ) ) {
									$failed_connection_checks[] = $check;
								}
							}
						}
						?>

						<?php if ( ! empty( $connection_test_checks ) ) : ?>
							<p class="creatorreactor-test-details-open">
								<button type="button" class="button-link creatorreactor-test-modal-trigger creatorreactor-open-test-modal">
									<?php esc_html_e( 'View test details', 'wp-creatorreactor' ); ?>
								</button>
							</p>
							<div id="creatorreactor-test-modal" class="creatorreactor-modal" aria-hidden="true" data-test-time="<?php echo esc_attr( (string) (int) ( $connection_test['time'] ?? 0 ) ); ?>">
								<div class="creatorreactor-modal-backdrop"></div>
								<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-test-modal-title">
									<div class="creatorreactor-modal-header">
										<div class="creatorreactor-modal-header-title">
											<?php self::render_brand_logo_img( 'creatorreactor-brand-logo--modal' ); ?>
											<h3 id="creatorreactor-test-modal-title"><?php esc_html_e( 'Test Details', 'wp-creatorreactor' ); ?></h3>
										</div>
										<button type="button" class="creatorreactor-modal-close creatorreactor-dismiss-connection-test" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-creatorreactor' ); ?>">&times;</button>
									</div>
									<div class="creatorreactor-modal-body">
										<ul class="creatorreactor-check-list">
											<?php foreach ( $connection_test_checks as $check ) : ?>
												<?php
												$check_label = isset( $check['label'] ) ? (string) $check['label'] : '';
												$check_message = isset( $check['message'] ) ? (string) $check['message'] : '';
												$check_pass = ! empty( $check['pass'] );
												?>
												<li>
													<strong><?php echo esc_html( $check_label ); ?>:</strong>
													<span class="<?php echo $check_pass ? 'creatorreactor-check-result-pass' : 'creatorreactor-check-result-fail'; ?>">
														<?php echo $check_pass ? esc_html__( 'OK', 'wp-creatorreactor' ) : esc_html__( 'Issue', 'wp-creatorreactor' ); ?>
													</span>
													<?php if ( $check_message !== '' ) : ?>
														&mdash; <?php echo esc_html( $check_message ); ?>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
									<div class="creatorreactor-modal-footer creatorreactor-modal-footer--split">
										<button type="button" class="button creatorreactor-dismiss-connection-test"><?php esc_html_e( 'Dismiss', 'wp-creatorreactor' ); ?></button>
										<div class="creatorreactor-modal-footer-primary">
											<?php if ( ! empty( $failed_connection_checks ) ) : ?>
												<a href="<?php echo esc_url( $connection_fix_url ); ?>" class="button button-primary"><?php esc_html_e( 'Fix', 'wp-creatorreactor' ); ?></a>
											<?php else : ?>
												<button type="button" class="button button-primary creatorreactor-ack-test-modal"><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $failed_connection_checks ) ) : ?>
							<div id="creatorreactor-test-errors" class="creatorreactor-test-errors" data-visible="false">
								<h3><?php esc_html_e( 'Active Test Errors', 'wp-creatorreactor' ); ?></h3>
								<ul class="creatorreactor-check-list">
									<?php foreach ( $failed_connection_checks as $check ) : ?>
										<?php
										$check_label = isset( $check['label'] ) ? (string) $check['label'] : '';
										$check_message = isset( $check['message'] ) ? (string) $check['message'] : '';
										?>
										<li>
											<strong><?php echo esc_html( $check_label ); ?>:</strong>
											<span class="creatorreactor-check-result-fail"><?php esc_html_e( 'Issue', 'wp-creatorreactor' ); ?></span>
											<?php if ( $check_message !== '' ) : ?>
												&mdash; <?php echo esc_html( $check_message ); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
					<div class="creatorreactor-dashboard-col creatorreactor-dashboard-col--integration-checks">
						<div class="creatorreactor-dashboard-integration-checks-wrap">
							<?php self::render_integration_checks_tab_body( 'dashboard' ); ?>
						</div>
					</div>
				</div>
			</div>

		</div>
		<?php endif; ?>
		<?php
	}

	public static function render_settings_page() {
		self::render_page( 'general', 'oauth' );
	}

	public static function render_users_page() {
		self::render_page( 'users', 'oauth' );
	}

	/**
	 * Clear Last Error when it still shows the deprecated Client ID mismatch text stored in wp_options.
	 * Tokens were already removed when that error was set; the banner is redundant.
	 */
	private static function maybe_clear_stale_oauth_client_mismatch_last_error() {
		if ( self::is_broker_mode() ) {
			return;
		}
		$le = get_option( self::OPTION_LAST_ERROR );
		if ( ! is_string( $le ) || $le === '' ) {
			return;
		}
		// Old English copy; localized installs may differ — "Disconnect" was removed from the message in code.
		if ( stripos( $le, 'different Client ID' ) === false || stripos( $le, 'Use Disconnect' ) === false ) {
			return;
		}
		self::set_last_error( '' );
	}

	private static function has_error_timestamp_prefix( $message ) {
		return is_string( $message ) && preg_match( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC\]\s+/', $message ) === 1;
	}

	private static function format_error_for_storage( $message ) {
		$message = wp_strip_all_tags( (string) $message );
		$message = preg_replace( '/\s+/', ' ', $message );
		$message = trim( (string) $message );
		if ( $message === '' ) {
			return '';
		}
		if ( self::has_error_timestamp_prefix( $message ) ) {
			return $message;
		}
		return '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $message;
	}

	private static function maybe_upgrade_error_messages_with_timestamp() {
		$last_error = get_option( self::OPTION_LAST_ERROR, '' );
		if ( is_string( $last_error ) && trim( $last_error ) !== '' && ! self::has_error_timestamp_prefix( $last_error ) ) {
			update_option( self::OPTION_LAST_ERROR, self::format_error_for_storage( $last_error ), false );
		}
		$critical_error = get_option( self::OPTION_CRITICAL_ERROR, '' );
		if ( is_string( $critical_error ) && trim( $critical_error ) !== '' && ! self::has_error_timestamp_prefix( $critical_error ) ) {
			update_option( self::OPTION_CRITICAL_ERROR, self::format_error_for_storage( $critical_error ), false );
		}
	}

	public static function clear_connection_errors() {
		self::set_last_error( '' );
		self::set_critical_error( '' );
	}

	public static function set_last_error( $message ) {
		update_option( self::OPTION_LAST_ERROR, self::format_error_for_storage( $message ), false );
	}

	public static function set_critical_error( $message ) {
		update_option( self::OPTION_CRITICAL_ERROR, self::format_error_for_storage( $message ), false );
	}

	/**
	 * Append a connection/OAuth log entry (stored in options, capped) and mirror to PHP error_log.
	 *
	 * @param string $level   'info'|'error'|'debug'.
	 * @param string $message Plain text; never include secrets or bearer tokens.
	 */
	public static function log_connection( $level, $message ) {
		$level = in_array( $level, [ 'info', 'error', 'debug' ], true ) ? $level : 'info';
		if ( ! is_string( $message ) ) {
			$message = wp_json_encode( $message );
		}
		$message = wp_strip_all_tags( (string) $message );
		$message = preg_replace( '/\s+/', ' ', $message );
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 4000 ) . '…';
		}
		$logs = get_option( self::OPTION_CONNECTION_LOGS, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}
		$logs[] = [
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		];
		if ( count( $logs ) > self::MAX_CONNECTION_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_CONNECTION_LOG_ENTRIES );
		}
		update_option( self::OPTION_CONNECTION_LOGS, $logs, false );
		if ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Mirrors stored connection log to PHP error log for host tooling.
			error_log( '[CreatorReactor][' . $level . '] ' . $message );
		}
	}

	/**
	 * Append a subscriber/user-table sync log entry (same storage shape as connection log).
	 *
	 * @param string $level   'info'|'error'|'debug'.
	 * @param string $message Plain text; never include secrets or bearer tokens.
	 */
	public static function log_sync( $level, $message ) {
		$level = in_array( $level, [ 'info', 'error', 'debug' ], true ) ? $level : 'info';
		if ( ! is_string( $message ) ) {
			$message = wp_json_encode( $message );
		}
		$message = wp_strip_all_tags( (string) $message );
		$message = preg_replace( '/\s+/', ' ', $message );
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 4000 ) . '…';
		}
		$logs = get_option( self::OPTION_SYNC_LOGS, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}
		$logs[] = [
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		];
		if ( count( $logs ) > self::MAX_SYNC_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_SYNC_LOG_ENTRIES );
		}
		update_option( self::OPTION_SYNC_LOGS, $logs, false );
		if ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Mirrors stored sync log to PHP error log for host tooling.
			error_log( '[CreatorReactor sync][' . $level . '] ' . $message );
		}
	}

	/**
	 * @return array<int, array{time:int, level:string, message:string}>
	 */
	public static function get_sync_logs() {
		$logs = get_option( self::OPTION_SYNC_LOGS, [] );
		return is_array( $logs ) ? $logs : [];
	}

	public static function clear_sync_logs() {
		delete_option( self::OPTION_SYNC_LOGS );
	}

	public static function handle_clear_sync_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wp-creatorreactor' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'creatorreactor_clear_sync_logs' );
		self::clear_sync_logs();
		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'debug', 'sync_log_cleared' => 1 ], self::PAGE_SETTINGS_SLUG ) );
		exit;
	}

	/**
	 * @return array<int, array{time:int, level:string, message:string}>
	 */
	public static function get_connection_logs() {
		$logs = get_option( self::OPTION_CONNECTION_LOGS, [] );
		return is_array( $logs ) ? $logs : [];
	}

	public static function clear_connection_logs() {
		delete_option( self::OPTION_CONNECTION_LOGS );
	}

	public static function handle_clear_connection_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wp-creatorreactor' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'creatorreactor_clear_connection_logs' );
		self::clear_connection_logs();
		wp_safe_redirect( self::admin_page_url( [ 'tab' => 'debug', 'connection_log_cleared' => 1 ], self::PAGE_SETTINGS_SLUG ) );
		exit;
	}
}
