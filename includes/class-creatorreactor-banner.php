<?php
/**
 * CreatorReactor Banner for Registration Status
 *
 * @package CreatorReactor
 * @author  ncdLabs
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
		add_action( 'init', [ __CLASS__, 'maybe_start_session' ] );
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_banner' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_creatorreactor_oauth_dismiss_banner', [ __CLASS__, 'ajax_dismiss_banner' ] );
		
		// Debug: Log that init was called
		error_log('CreatorReactor Banner: init() called');
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
	 * Check if registration is disabled and show banner if not dismissed in current session.
	 */
	public static function maybe_show_banner() {
		// Only show in admin
		if ( ! is_admin() ) {
			error_log('CreatorReactor Banner: Not showing banner - not in admin');
			return;
		}

		// Get the registration setting
		$users_can_register = get_option( 'users_can_register' );
		error_log('CreatorReactor Banner: users_can_register = ' . var_export($users_can_register, true) . ' (type: ' . gettype($users_can_register) . ')');
		
		// In WordPress:
		// - users_can_register = 1 or '1' means registration is ENABLED (anyone can register)
		// - users_can_register = 0 or '0' means registration is DISABLED
		$is_registration_enabled = ( $users_can_register === 1 || $users_can_register === '1' );
		error_log('CreatorReactor Banner: is_registration_enabled = ' . var_export($is_registration_enabled, true));
		
		// If registration is ENABLED, don't show banner (we only show when DISABLED)
		if ( $is_registration_enabled ) {
			error_log('CreatorReactor Banner: Not showing banner - registration is ENABLED');
			return;
		}
		
		// If we get here, registration is DISABLED (0 or '0')
		// Check if banner has been dismissed in current session
		$is_dismissed = isset( $_SESSION['creatorreactor_oauth_banner_dismissed'] ) && $_SESSION['creatorreactor_oauth_banner_dismissed'];
		error_log('CreatorReactor Banner: is_dismissed = ' . var_export($is_dismissed, true));
		
		if ( $is_dismissed ) {
			error_log('CreatorReactor Banner: Not showing banner - dismissed in session');
			return;
		}
		
		// Show the banner
		error_log('CreatorReactor Banner: Showing banner - registration is DISABLED and not dismissed');
		?>
		<div class="notice notice-warning is-dismissible" id="creatorreactor-oauth-banner">
			<p><?php esc_html_e( 'This system is set to not accept new users! CreatorReactor OAuth creates local Wordpress users based on their OAuth login. Please go to Settings -> General then click "Anyone can register".', 'creatorreactor' ); ?></p>
			<button type="button" class="button" data-action="creatorreactor_oauth_dismiss_banner"><?php esc_html_e( 'Dismiss', 'creatorreactor' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts for banner dismissal.
	 */
	public static function enqueue_assets() {
		error_log('CreatorReactor Banner: Enqueueing assets');
	wp_enqueue_script(
		'creatorreactor-oauth-banner',
		plugin_dir_url( __DIR__ . '/../creatorreactor.php' ) . 'assets/js/creatorreactor-banner.js',
		array( 'jquery' ),
		CREATORREACTOR_VERSION,
		true
	);

		// Pass the nonce to the script
		wp_localize_script(
			'creatorreactor-oauth-banner',
			'creatorreactor_oauth_banner',
			array(
				'nonce' => wp_create_nonce( 'creatorreactor_oauth_dismiss_banner_nonce' ),
			)
		);
	}

	/**
	 * Handle AJAX request to dismiss banner.
	 */
	public static function ajax_dismiss_banner() {
		error_log('CreatorReactor Banner: AJAX dismiss called');
		check_ajax_referer( 'creatorreactor_oauth_dismiss_banner_nonce', 'security' );

		// Ensure session is started
		if ( session_id() == '' ) {
			session_start();
		}

		$_SESSION['creatorreactor_oauth_banner_dismissed'] = true;
		error_log('CreatorReactor Banner: Banner dismissed in session');

		wp_send_json_success();
	}
}