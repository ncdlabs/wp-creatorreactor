<?php
/**
 * After plugin activation: detect Elementor / block editor usage and prompt admins with a modal.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor_Blocks_Prompt {

	const OPTION_SCAN_PENDING = 'creatorreactor_editor_prompt_scan_pending';
	const OPTION_INTEGRATION_CHECKS_PENDING = 'creatorreactor_integration_checks_pending';

	const OPTION_HAS_ELEMENTOR = 'creatorreactor_editor_prompt_has_elementor';

	const OPTION_HAS_GUTENBERG = 'creatorreactor_editor_prompt_has_gutenberg';

	const USER_META_DISMISSED = 'creatorreactor_dismissed_editor_prompt';

	public static function on_activation() {
		update_option( self::OPTION_SCAN_PENDING, '1', false );
		update_option( self::OPTION_INTEGRATION_CHECKS_PENDING, '1', false );
		delete_option( self::OPTION_HAS_ELEMENTOR );
		delete_option( self::OPTION_HAS_GUTENBERG );
	}

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_to_integration_checks' ], 4 );
		add_action( 'admin_init', [ __CLASS__, 'run_activation_scan' ], 5 );
		add_action( 'admin_init', [ __CLASS__, 'handle_ack_redirect' ], 6 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_modal_assets' ] );
		add_action( 'admin_footer', [ __CLASS__, 'render_modal' ] );
		add_action( 'wp_ajax_creatorreactor_dismiss_editor_prompt', [ __CLASS__, 'ajax_dismiss' ] );
	}

	/**
	 * After activation, send admins to the CreatorReactor Dashboard (integration checks block).
	 */
	public static function maybe_redirect_to_integration_checks() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( get_option( self::OPTION_INTEGRATION_CHECKS_PENDING, '' ) !== '1' ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( Admin_Settings::PAGE_SLUG === $page && 'dashboard' === $tab ) {
			delete_option( self::OPTION_INTEGRATION_CHECKS_PENDING );
			return;
		}
		if ( Admin_Settings::PAGE_SETTINGS_SLUG === $page && 'integration-checks' === $tab ) {
			delete_option( self::OPTION_INTEGRATION_CHECKS_PENDING );
			return;
		}

		delete_option( self::OPTION_INTEGRATION_CHECKS_PENDING );
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . Admin_Settings::PAGE_SLUG . '&tab=dashboard#creatorreactor-integration-checks' )
		);
		exit;
	}

	/**
	 * One-time scan on first admin request after activation.
	 */
	public static function run_activation_scan() {
		if ( ! is_admin() ) {
			return;
		}
		if ( get_option( self::OPTION_SCAN_PENDING, '' ) !== '1' ) {
			return;
		}

		delete_option( self::OPTION_SCAN_PENDING );

		$elementor = Editor_Context::is_elementor_plugin_active();
		$gutenberg = Editor_Context::site_uses_block_editor();
		$has_e = $elementor ? '1' : '0';
		$has_g = $gutenberg ? '1' : '0';
		update_option( self::OPTION_HAS_ELEMENTOR, $has_e, false );
		update_option( self::OPTION_HAS_GUTENBERG, $has_g, false );
	}

	/**
	 * Dismiss prompt after opening Shortcodes tab via modal link.
	 */
	public static function handle_ack_redirect() {
		if ( ! is_admin() || empty( $_GET['creatorreactor_editor_prompt_ack'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'creatorreactor_editor_prompt_ack', 'creatorreactor_editor_prompt_nonce' );
		update_user_meta( get_current_user_id(), self::USER_META_DISMISSED, '1' );
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . Admin_Settings::PAGE_SETTINGS_SLUG . '&tab=shortcodes' )
		);
		exit;
	}

	public static function ajax_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'creatorreactor_editor_prompt', 'nonce' );
		update_user_meta( get_current_user_id(), self::USER_META_DISMISSED, '1' );
		wp_send_json_success();
	}

	/**
	 * Site was detected as using at least one builder (options set by scan).
	 */
	private static function site_targets_any_builder() {
		return get_option( self::OPTION_HAS_ELEMENTOR, '0' ) === '1'
			|| get_option( self::OPTION_HAS_GUTENBERG, '0' ) === '1';
	}

	public static function should_show_modal() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( get_user_meta( get_current_user_id(), self::USER_META_DISMISSED, true ) === '1' ) {
			return false;
		}
		return self::site_targets_any_builder();
	}

	public static function enqueue_modal_assets( $hook_suffix ) {
		if ( ! self::should_show_modal() ) {
			return;
		}

		wp_register_style( 'creatorreactor-editor-prompt-modal', false, [], CREATORREACTOR_VERSION );
		wp_add_inline_style(
			'creatorreactor-editor-prompt-modal',
			'#creatorreactor-editor-prompt-modal.creatorreactor-modal{position:fixed;inset:0;display:none;z-index:100000}'
				. '#creatorreactor-editor-prompt-modal.creatorreactor-modal[aria-hidden="false"]{display:block}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-dialog{position:relative;width:min(520px,calc(100% - 32px));max-height:calc(100vh - 64px);margin:32px auto;background:#fff;border-radius:8px;border:1px solid #dcdcde;box-shadow:0 12px 32px rgba(0,0,0,.25);overflow:auto}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-header{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 18px 10px;border-bottom:1px solid #dcdcde}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-header h2{margin:0;font-size:16px}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-body{padding:14px 18px;line-height:1.5}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-footer{padding:12px 18px 16px;border-top:1px solid #dcdcde;text-align:right;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-close{border:0;background:transparent;color:#50575e;cursor:pointer;font-size:22px;line-height:1;padding:4px}'
		);
		wp_enqueue_style( 'creatorreactor-editor-prompt-modal' );

		wp_enqueue_script(
			'creatorreactor-editor-prompt-modal',
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-editor-prompt-modal.js',
			[],
			CREATORREACTOR_VERSION,
			true
		);

		wp_localize_script(
			'creatorreactor-editor-prompt-modal',
			'creatorreactorEditorPrompt',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'creatorreactor_editor_prompt' ),
			]
		);
	}

	public static function render_modal() {
		if ( ! self::should_show_modal() ) {
			return;
		}

		$has_e = get_option( self::OPTION_HAS_ELEMENTOR, '0' ) === '1';
		$has_g = get_option( self::OPTION_HAS_GUTENBERG, '0' ) === '1';

		if ( $has_e && $has_g ) {
			$body = __( 'We detected Elementor and the WordPress block editor (Gutenberg) on this site. CreatorReactor already includes Elementor widgets and block editor blocks.', 'creatorreactor' )
				. ' '
				. __( 'Would you like to install them into your workflow? Open the Shortcodes tab for a full reference (widgets appear in the CreatorReactor category in Elementor; blocks in the block inserter).', 'creatorreactor' );
		} elseif ( $has_e ) {
			$body = __( 'We detected Elementor on this site. CreatorReactor includes Elementor editor widgets in the CreatorReactor category.', 'creatorreactor' )
				. ' '
				. __( 'Would you like to install the Elementor editor widgets? Open the Shortcodes tab to see how to use them.', 'creatorreactor' );
		} else {
			$body = __( 'We detected the WordPress block editor (Gutenberg) on this site. CreatorReactor includes block editor blocks in the CreatorReactor category.', 'creatorreactor' )
				. ' '
				. __( 'Would you like to install the Gutenberg editor blocks? Open the Shortcodes tab to see how to use them.', 'creatorreactor' );
		}

		$shortcodes_url = wp_nonce_url(
			admin_url(
				'admin.php?page=' . Admin_Settings::PAGE_SETTINGS_SLUG
				. '&tab=shortcodes&creatorreactor_editor_prompt_ack=1'
			),
			'creatorreactor_editor_prompt_ack',
			'creatorreactor_editor_prompt_nonce'
		);

		?>
		<div id="creatorreactor-editor-prompt-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
			<div class="creatorreactor-modal-backdrop" aria-hidden="true"></div>
			<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="creatorreactor-editor-prompt-title">
				<div class="creatorreactor-modal-header">
					<h2 id="creatorreactor-editor-prompt-title"><?php esc_html_e( 'CreatorReactor editor blocks', 'creatorreactor' ); ?></h2>
					<button type="button" class="creatorreactor-modal-close creatorreactor-editor-prompt-close" aria-label="<?php esc_attr_e( 'Close', 'creatorreactor' ); ?>">&times;</button>
				</div>
				<div class="creatorreactor-modal-body">
					<p><?php echo esc_html( $body ); ?></p>
				</div>
				<div class="creatorreactor-modal-footer">
					<button type="button" class="button creatorreactor-editor-prompt-dismiss"><?php esc_html_e( 'Not now', 'creatorreactor' ); ?></button>
					<a class="button button-primary" href="<?php echo esc_url( $shortcodes_url ); ?>"><?php esc_html_e( 'Yes, open Shortcodes tab', 'creatorreactor' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}
}
