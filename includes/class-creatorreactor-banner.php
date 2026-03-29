<?php
/**
 * CreatorReactor Banner for Registration Status
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
 * Handles displaying a banner when WordPress registration is disabled.
 */
class Banner {

	/**
	 * Initialize the banner functionality.
	 */
	public static function init() {
		\add_action( 'init', [ __CLASS__, 'maybe_start_session' ] );
		\add_action( 'admin_head', [ __CLASS__, 'maybe_print_critical_banner_css' ], 99 );
		\add_action( 'in_admin_header', [ __CLASS__, 'maybe_show_banner' ], 20 );
		\add_filter( 'admin_body_class', [ __CLASS__, 'filter_admin_body_class' ] );
		\add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		\add_action( 'wp_ajax_creatorreactor_oauth_dismiss_banner', [ __CLASS__, 'ajax_dismiss_banner' ] );
	}

	/**
	 * Start PHP session if not already started.
	 */
	public static function maybe_start_session() {
		if ( session_id() == '' && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * True when the banner should show (registration off, not dismissed this session).
	 */
	public static function should_show_registration_banner() {
		if ( ! \is_admin() ) {
			return false;
		}
		$users_can_register       = \get_option( 'users_can_register' );
		$is_registration_enabled = ( $users_can_register === 1 || $users_can_register === '1' );
		if ( $is_registration_enabled ) {
			return false;
		}
		$is_dismissed = isset( $_SESSION['creatorreactor_oauth_banner_dismissed'] ) && $_SESSION['creatorreactor_oauth_banner_dismissed'];
		return ! $is_dismissed;
	}

	/**
	 * True when banner chrome (CSS/JS) should load for the current user.
	 */
	private static function should_load_banner_assets() {
		if ( ! \is_admin() || ! \current_user_can( 'manage_options' ) ) {
			return false;
		}
		$users_can_register = \get_option( 'users_can_register' );
		if ( $users_can_register === 1 || $users_can_register === '1' ) {
			return false;
		}
		return self::should_show_registration_banner();
	}

	/**
	 * Inline CSS: in-flow full-width strip under the toolbar; single-line row where space allows.
	 * z-index above #adminmenuwrap (9990) and flyouts (9999), below #wpadminbar (99999).
	 */
	private static function get_banner_layout_css() {
		return '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global{'
			. 'display:block!important;'
			. 'position:relative!important;'
			. 'z-index:10001!important;'
			. 'width:100%!important;'
			. 'max-width:none!important;'
			. 'margin:0!important;'
			. 'padding:0!important;'
			. 'box-sizing:border-box!important;'
			. 'clear:both!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-registration-alert{'
			. 'border:0!important;border-bottom:2px solid #d63638!important;'
			. 'border-radius:0!important;'
			. 'background:linear-gradient(180deg,#fff8f8 0%,#fcf0f1 100%)!important;'
			. 'padding:6px 14px!important;margin:0!important;'
			. 'box-shadow:none!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-oauth-banner-inner{'
			. 'display:flex!important;align-items:center!important;gap:10px!important;flex-wrap:nowrap!important;'
			. 'max-width:100%;margin:0;min-height:0;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-brand-logo--banner{'
			. 'max-height:22px!important;width:auto!important;height:auto!important;display:block!important;flex-shrink:0!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-oauth-banner-message{'
			. 'flex:1 1 auto!important;min-width:0!important;margin:0!important;'
			. 'font-size:13px!important;line-height:1.35!important;font-weight:500!important;color:#1d2327!important;'
			. 'white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-oauth-banner-actions{'
			. 'flex-shrink:0!important;display:flex!important;align-items:center!important;gap:6px!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-oauth-banner-dismiss{'
			. 'margin:0!important;line-height:1.2!important;min-height:0!important;padding:2px 10px!important;font-size:12px!important;'
			. '}'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-registration-alert-fix{font-weight:600;}'
			. '@media screen and (max-width:782px){'
			. '#creatorreactor-oauth-banner.creatorreactor-registration-alert-wrap--global .creatorreactor-oauth-banner-message{'
			. 'white-space:normal!important;display:-webkit-box!important;-webkit-line-clamp:2!important;'
			. '-webkit-box-orient:vertical!important;overflow:hidden!important;'
			. '}'
			. '}';
	}

	/**
	 * Print layout rules in head so the first paint matches the in-flow strip.
	 */
	public static function maybe_print_critical_banner_css() {
		if ( ! self::should_load_banner_assets() ) {
			return;
		}
		echo '<style id="creatorreactor-reg-banner-critical">' . self::get_banner_layout_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS only.
	}

	/**
	 * Body class for banner-active admin screens (layout scoped in CSS).
	 *
	 * @param string $classes Space-separated class names.
	 * @return string
	 */
	public static function filter_admin_body_class( $classes ) {
		if ( ! self::should_show_registration_banner() ) {
			return $classes;
		}
		return $classes . ' creatorreactor-registration-banner-active';
	}

	/**
	 * Shared body copy with “Fix” link (AJAX when Integration Fix config exists; otherwise navigates to General).
	 */
	private static function render_banner_message() {
		echo \wp_kses(
			\sprintf(
				/* translators: 1: opening <strong>, 2: closing </strong>, 3: opening <a> for Fix, 4: closing </a>. */
				\__( 'This system is set to not accept new users! Please go to Settings → General then click %1$s"Anyone can register"%2$s, or click %3$sFix%4$s to configure now.', 'creatorreactor' ),
				'<strong>',
				'</strong>',
				'<a href="#" class="creatorreactor-registration-alert-fix">',
				'</a>'
			),
			[
				'strong' => [],
				'a'      => [
					'href'  => [],
					'class' => [],
				],
			]
		);
	}

	/**
	 * Move banner to body before #wpwrap so it spans the full admin width and #wpwrap stacks beneath it (in-flow, no overlay).
	 */
	private static function render_banner_mount_script() {
		?>
		<script>
		(function () {
			var w = document.getElementById('creatorreactor-oauth-banner');
			var wpwrap = document.getElementById('wpwrap');
			if (!w || !wpwrap || wpwrap.previousElementSibling === w) {
				return;
			}
			document.body.insertBefore(w, wpwrap);
		})();
		</script>
		<?php
	}

	/**
	 * Full-width in-flow strip under the admin toolbar; compact single-row layout.
	 */
	private static function render_banner_markup() {
		?>
		<div class="creatorreactor-registration-alert-wrap creatorreactor-registration-alert-wrap--global" id="creatorreactor-oauth-banner" role="alert">
			<div class="creatorreactor-registration-alert">
				<div class="creatorreactor-oauth-banner-inner">
					<img
						src="<?php echo \esc_url( CREATORREACTOR_PLUGIN_URL . 'img/cr-logo.png' ); ?>"
						alt="<?php echo \esc_attr( \__( 'CreatorReactor', 'creatorreactor' ) ); ?>"
						class="creatorreactor-brand-logo creatorreactor-brand-logo--banner"
						loading="lazy"
						decoding="async"
					/>
					<p class="creatorreactor-oauth-banner-message"><?php self::render_banner_message(); ?></p>
					<div class="creatorreactor-oauth-banner-actions">
						<button type="button" class="button button-small creatorreactor-oauth-banner-dismiss"><?php \esc_html_e( 'Dismiss', 'creatorreactor' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		self::render_banner_mount_script();
	}

	/**
	 * Output the registration banner on every admin screen when applicable.
	 */
	public static function maybe_show_banner() {
		if ( ! self::should_show_registration_banner() ) {
			return;
		}
		self::render_banner_markup();
	}

	/**
	 * Enqueue admin scripts for banner dismissal and Fix action.
	 */
	public static function enqueue_assets() {
		if ( ! self::should_load_banner_assets() ) {
			return;
		}

		\wp_register_style( 'creatorreactor-oauth-banner', false, [], CREATORREACTOR_VERSION );
		\wp_enqueue_style( 'creatorreactor-oauth-banner' );
		\wp_add_inline_style( 'creatorreactor-oauth-banner', self::get_banner_layout_css() );

		\wp_enqueue_script(
			'creatorreactor-oauth-banner',
			\plugin_dir_url( __DIR__ . '/../creatorreactor.php' ) . 'assets/js/creatorreactor-banner.js',
			[ 'jquery' ],
			CREATORREACTOR_VERSION,
			true
		);

		\wp_localize_script(
			'creatorreactor-oauth-banner',
			'creatorreactor_oauth_banner',
			[
				'nonce'               => \wp_create_nonce( 'creatorreactor_oauth_dismiss_banner_nonce' ),
				'settingsGeneralUrl'  => \admin_url( 'options-general.php' ),
				'fixError'            => \__( 'Could not apply the fix. Try Settings → General or Integration Checks.', 'creatorreactor' ),
			]
		);
	}

	/**
	 * Handle AJAX request to dismiss banner.
	 */
	public static function ajax_dismiss_banner() {
		\check_ajax_referer( 'creatorreactor_oauth_dismiss_banner_nonce', 'security' );
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \__( 'Forbidden.', 'creatorreactor' ), 403 );
		}

		if ( session_id() == '' && ! headers_sent() ) {
			session_start();
		}

		$_SESSION['creatorreactor_oauth_banner_dismissed'] = true;

		\wp_send_json_success();
	}
}
