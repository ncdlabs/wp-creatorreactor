<?php
/**
 * Post-activation: prompt admins to confirm display timezone (IANA list + browser default).
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activation_Timezone_Modal {

	const OPTION_PENDING = 'creatorreactor_activation_timezone_pending';

	public static function on_activation() {
		update_option( self::OPTION_PENDING, '1', false );
	}

	/**
	 * Whether the first-load timezone confirmation is still required.
	 */
	public static function is_pending() {
		return get_option( self::OPTION_PENDING, '' ) === '1';
	}

	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ __CLASS__, 'render_modal' ] );
		add_action( 'wp_ajax_creatorreactor_activation_timezone_confirm', [ __CLASS__, 'ajax_confirm' ] );
	}

	private static function should_show() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return false;
		}
		return self::is_pending();
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( ! self::should_show() ) {
			return;
		}

		$handle = 'creatorreactor-activation-timezone-modal';
		wp_register_style( $handle, false, [], CREATORREACTOR_VERSION );
		wp_add_inline_style(
			$handle,
			'#creatorreactor-activation-timezone-modal.creatorreactor-modal{position:fixed;inset:0;display:none;z-index:100002}'
				. '#creatorreactor-activation-timezone-modal.creatorreactor-modal[aria-hidden="false"]{display:block}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-backdrop{position:absolute;inset:0;background:rgba(48,25,52,.55)}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-dialog{position:relative;width:min(520px,calc(100% - 32px));max-height:calc(100vh - 48px);margin:24px auto;background:#fff;border-radius:10px;border:1px solid #e8e0ed;box-shadow:0 16px 48px rgba(48,25,52,.28);overflow:hidden;display:flex;flex-direction:column}'
				. '#creatorreactor-activation-timezone-modal .cr-activation-tz-brandbar{height:4px;background:linear-gradient(90deg,#301934 0%,#8e2d77 35%,#d64d7f 65%,#f9a891 100%);flex-shrink:0}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-header{display:flex;justify-content:flex-start;align-items:flex-start;gap:14px;padding:14px 20px 10px;border-bottom:1px solid #e8e0ed;background:linear-gradient(165deg,#fefcfd 0%,#fff 55%,#fff8f5 100%);flex-shrink:0}'
				. '#creatorreactor-activation-timezone-modal .cr-activation-tz-header-main{flex:1;min-width:0}'
				. '#creatorreactor-activation-timezone-modal .cr-activation-tz-logo{display:block;max-height:40px;width:auto;height:auto;margin:0 0 8px}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-header h2{margin:0;font-size:18px;line-height:1.25;color:#301934;font-weight:700}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-body{padding:16px 20px;line-height:1.55;color:#414a4c;flex:1;min-height:0;overflow:auto}'
				. '#creatorreactor-activation-timezone-modal .cr-activation-tz-detected{margin:0 0 12px;font-size:13px;color:#6b5a74}'
				. '#creatorreactor-activation-timezone-modal .cr-activation-tz-detected strong{color:#301934}'
				. '#creatorreactor-activation-timezone-modal label.cr-activation-tz-label{display:block;font-weight:600;margin:0 0 6px;color:#301934}'
				. '#creatorreactor-activation-timezone-modal select.cr-activation-tz-select{width:100%;max-width:100%;box-sizing:border-box}'
				. '#creatorreactor-activation-timezone-modal .creatorreactor-modal-footer{padding:12px 20px 18px;border-top:1px solid #e8e0ed;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;flex-shrink:0;background:#fcfcfc}'
				. '#creatorreactor-activation-timezone-modal .button.button-primary.cr-activation-tz-confirm{background:#8e2d77!important;border-color:#8e2d77!important;color:#fff!important;box-shadow:none!important}'
				. '#creatorreactor-activation-timezone-modal .button.button-primary.cr-activation-tz-confirm:hover{background:#6d2459!important;border-color:#6d2459!important;color:#fff!important}'
				. '#creatorreactor-activation-timezone-modal .button.button-primary.cr-activation-tz-confirm:focus-visible{outline:2px solid #f9a891;outline-offset:2px}'
		);
		wp_enqueue_style( $handle );

		wp_enqueue_script(
			$handle,
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-activation-timezone-modal.js',
			[],
			CREATORREACTOR_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'creatorreactorActivationTz',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_activation_timezone' ),
				'siteTz'  => function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '',
				'strings' => [
					'detectedEmpty' => __( 'We could not read a timezone from your browser; choose the closest city below.', 'wp-creatorreactor' ),
					/* translators: %s: IANA timezone name from the browser (e.g. America/New_York). */
					'detected'      => __( 'Detected from your browser: %s', 'wp-creatorreactor' ),
					'saveError'     => __( 'Could not save your timezone. Please try again or set it under CreatorReactor → Settings → General.', 'wp-creatorreactor' ),
				],
			]
		);
	}

	public static function ajax_confirm() {
		check_ajax_referer( 'creatorreactor_activation_timezone', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}

		$tz = isset( $_POST['display_timezone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['display_timezone'] ) ) : '';
		$sanitized_opts = Admin_Settings::sanitize_options( [ 'display_timezone' => $tz ] );
		update_option( Admin_Settings::OPTION_NAME, $sanitized_opts );
		delete_option( self::OPTION_PENDING );

		wp_send_json_success(
			[
				'saved' => Admin_Settings::get_options()['display_timezone'] ?? 'browser',
			]
		);
	}

	public static function render_modal() {
		if ( ! self::should_show() ) {
			return;
		}

		$logo_url = CREATORREACTOR_PLUGIN_URL . 'img/cr-logo.png';
		if ( defined( 'CREATORREACTOR_VERSION' ) ) {
			$logo_url = add_query_arg( 'ver', CREATORREACTOR_VERSION, $logo_url );
		}

		ob_start();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_timezone_choice() returns escaped <option> markup.
		echo wp_timezone_choice( '', get_user_locale() );
		$tz_select_inner = ob_get_clean();

		?>
		<div id="creatorreactor-activation-timezone-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
			<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
			<div class="creatorreactor-modal-dialog" role="dialog" tabindex="-1" aria-modal="true" aria-labelledby="creatorreactor-activation-tz-title">
				<div class="cr-activation-tz-brandbar" aria-hidden="true"></div>
				<div class="creatorreactor-modal-header">
					<div class="cr-activation-tz-header-main">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( __( 'CreatorReactor', 'wp-creatorreactor' ) ); ?>" class="cr-activation-tz-logo" width="180" height="40" loading="eager" decoding="async" />
						<h2 id="creatorreactor-activation-tz-title"><?php esc_html_e( 'Confirm your timezone', 'wp-creatorreactor' ); ?></h2>
					</div>
				</div>
				<div class="creatorreactor-modal-body">
					<p class="cr-activation-tz-detected" id="creatorreactor-activation-tz-detected" aria-live="polite"></p>
					<label class="cr-activation-tz-label" for="creatorreactor-activation-tz-select"><?php esc_html_e( 'Display timezone', 'wp-creatorreactor' ); ?></label>
					<select class="cr-activation-tz-select" id="creatorreactor-activation-tz-select" name="creatorreactor_activation_tz" autocomplete="off">
						<?php echo $tz_select_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper markup. ?>
					</select>
				</div>
				<div class="creatorreactor-modal-footer">
					<button type="button" class="button button-primary cr-activation-tz-confirm"><?php esc_html_e( 'Confirm and continue', 'wp-creatorreactor' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
