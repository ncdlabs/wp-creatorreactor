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
		if ( ! is_admin() ) {
			return;
		}

		$users_can_register = get_option( 'users_can_register' );
		$is_registration_enabled = ( $users_can_register === 1 || $users_can_register === '1' );
		if ( $is_registration_enabled ) {
			return;
		}

		$is_dismissed = isset( $_SESSION['creatorreactor_oauth_banner_dismissed'] ) && $_SESSION['creatorreactor_oauth_banner_dismissed'];
		if ( $is_dismissed ) {
			return;
		}
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
		check_ajax_referer( 'creatorreactor_oauth_dismiss_banner_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Forbidden.', 'creatorreactor' ), 403 );
		}

		if ( session_id() == '' && ! headers_sent() ) {
			session_start();
		}

		$_SESSION['creatorreactor_oauth_banner_dismissed'] = true;

		wp_send_json_success();
	}
}