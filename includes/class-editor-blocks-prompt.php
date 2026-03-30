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
		add_action( 'admin_init', [ __CLASS__, 'maybe_run_deferred_activation_no_builder_site' ], 7 );
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
			Admin_Settings::run_deferred_activation_roles_only();
			return;
		}
		if ( Admin_Settings::PAGE_SETTINGS_SLUG === $page && 'debug' === $tab ) {
			delete_option( self::OPTION_INTEGRATION_CHECKS_PENDING );
			Admin_Settings::run_deferred_activation_roles_only();
			return;
		}

		delete_option( self::OPTION_INTEGRATION_CHECKS_PENDING );
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . Admin_Settings::PAGE_SLUG . '&tab=dashboard#creatorreactor-integration-checks' )
		);
		exit;
	}

	/**
	 * Sites with no block editor / Elementor never see the onboarding modals; register roles after scan.
	 */
	public static function maybe_run_deferred_activation_no_builder_site() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( Admin_Settings::OPTION_DEFERRED_ACTIVATION_PENDING, '' ) !== '1' ) {
			return;
		}
		if ( get_option( self::OPTION_SCAN_PENDING, '' ) === '1' ) {
			return;
		}
		if ( self::site_targets_any_builder() ) {
			return;
		}
		Admin_Settings::run_deferred_activation_roles_only();
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

	public static function ajax_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'creatorreactor_editor_prompt', 'nonce' );
		$ack = isset( $_POST['acknowledge_integration'] ) && (string) wp_unslash( $_POST['acknowledge_integration'] ) === '1';
		if ( $ack ) {
			Admin_Settings::run_deferred_activation_roles_only();
		}
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
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-footer{padding:12px 18px 16px;border-top:1px solid #dcdcde;text-align:right;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;align-items:center}'
				. '#creatorreactor-editor-prompt-modal .creatorreactor-modal-close{border:0;background:transparent;color:#50575e;cursor:pointer;font-size:22px;line-height:1;padding:4px}'
				. '#creatorreactor-onboarding-integration-modal.creatorreactor-modal{position:fixed;inset:0;display:none;z-index:100001}'
				. '#creatorreactor-onboarding-integration-modal.creatorreactor-modal[aria-hidden="false"]{display:block}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-dialog{position:relative;display:flex;flex-direction:column;width:min(560px,calc(100% - 32px));max-height:calc(100vh - 48px);margin:24px auto;background:#fff;border-radius:8px;border:1px solid #dcdcde;box-shadow:0 12px 32px rgba(0,0,0,.25);overflow:hidden;outline:none}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-dialog:focus-visible{box-shadow:0 12px 32px rgba(0,0,0,.25),0 0 0 2px #8e2d77}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:16px 18px 12px;border-bottom:1px solid #dcdcde}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-close{border:0;background:transparent;color:#50575e;cursor:pointer;font-size:22px;line-height:1;padding:4px;flex-shrink:0}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-header-text{flex:1;min-width:0;padding-right:8px}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-header-text h2{margin:0 0 4px;font-size:18px;line-height:1.25}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-integration-subtitle{margin:0;font-size:13px;font-weight:600;color:#50575e;line-height:1.35}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-body{flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;padding:14px 18px;line-height:1.5}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-intro{flex-shrink:0;margin:0 0 10px}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-check-scroll{flex:1;min-height:120px;max-height:min(52vh,480px);overflow-y:auto;-webkit-overflow-scrolling:touch;border:1px solid #dcdcde;border-radius:6px;padding:10px 12px;background:#fcfcfc}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-check-list{margin:0;padding-left:18px}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-onboarding-check-list li{margin:8px 0}'
				. '#creatorreactor-onboarding-integration-modal .creatorreactor-modal-footer{flex-shrink:0;padding:12px 18px 16px;border-top:1px solid #dcdcde;text-align:right;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;align-items:center}'
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
				'onboarding' => [
					'nonce'            => wp_create_nonce( 'creatorreactor_onboarding_integration' ),
					'applyFixesAction' => 'creatorreactor_onboarding_apply_fixes',
					'ignoreAction'     => 'creatorreactor_onboarding_ignore_checks',
				],
				'strings' => [
					'applyError' => __( 'Could not apply all automatic fixes. You can still ignore warnings or adjust settings manually.', 'creatorreactor' ),
				],
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
			$body = __( 'We detected Elementor and the WordPress block editor (Gutenberg) on this site. CreatorReactor includes Elementor widgets in the CreatorReactor category in Elementor, and block editor blocks in the CreatorReactor category in the block inserter.', 'creatorreactor' )
				. ' '
				. __( 'The Gutenberg blocks and Elementor widgets will be installed for your site. To see what is available and how to use it, go to CreatorReactor → Settings → Documentation → Gutenberg Blocks and CreatorReactor → Settings → Documentation → Elementor widgets.', 'creatorreactor' );
		} elseif ( $has_e ) {
			$body = __( 'We detected Elementor on this site. CreatorReactor includes Elementor widgets in the CreatorReactor category.', 'creatorreactor' )
				. ' '
				. __( 'The Elementor widgets will be installed for your site. To see what is available and how to use it, go to CreatorReactor → Settings → Documentation → Elementor widgets.', 'creatorreactor' );
		} else {
			$body = __( 'We detected the WordPress block editor (Gutenberg) on this site. CreatorReactor includes block editor blocks in the CreatorReactor category.', 'creatorreactor' )
				. ' '
				. __( 'The Gutenberg blocks will be installed for your site. To see what is available and how to use it, go to CreatorReactor → Settings → Documentation → Gutenberg Blocks.', 'creatorreactor' );
		}

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
					<button type="button" class="button button-primary creatorreactor-editor-prompt-next"><?php esc_html_e( 'Next', 'creatorreactor' ); ?></button>
				</div>
			</div>
		</div>

		<?php
		$onboarding_data = [
			'remediable_rows' => [],
			'other_red_count' => 0,
		];
		try {
			$onboarding_data = Admin_Settings::get_integration_onboarding_modal_data();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CreatorReactor editor prompt integration list: ' . $e->getMessage() );
			}
		}
		$remediable_rows = isset( $onboarding_data['remediable_rows'] ) && is_array( $onboarding_data['remediable_rows'] )
			? $onboarding_data['remediable_rows']
			: [];
		$other_red_count = isset( $onboarding_data['other_red_count'] ) ? (int) $onboarding_data['other_red_count'] : 0;
		?>
		<div id="creatorreactor-onboarding-integration-modal" class="creatorreactor-modal" aria-hidden="true" role="presentation">
			<div class="creatorreactor-modal-backdrop creatorreactor-onboarding-integration-backdrop" aria-hidden="true"></div>
			<div
				id="creatorreactor-onboarding-integration-dialog"
				class="creatorreactor-modal-dialog"
				role="dialog"
				tabindex="-1"
				aria-modal="true"
				aria-labelledby="creatorreactor-onboarding-integration-title"
				aria-describedby="creatorreactor-onboarding-integration-subtitle creatorreactor-onboarding-integration-intro"
			>
				<div class="creatorreactor-modal-header">
					<div class="creatorreactor-onboarding-header-text">
						<h2 id="creatorreactor-onboarding-integration-title"><?php esc_html_e( 'CreatorReactor Setup', 'creatorreactor' ); ?></h2>
						<p id="creatorreactor-onboarding-integration-subtitle" class="creatorreactor-onboarding-integration-subtitle"><?php esc_html_e( 'Integration Checks', 'creatorreactor' ); ?></p>
					</div>
					<button type="button" class="creatorreactor-modal-close creatorreactor-onboarding-integration-close" aria-label="<?php esc_attr_e( 'Close', 'creatorreactor' ); ?>">&times;</button>
				</div>
				<div class="creatorreactor-modal-body">
					<?php if ( ! empty( $remediable_rows ) ) : ?>
						<p id="creatorreactor-onboarding-integration-intro" class="creatorreactor-onboarding-intro"><?php esc_html_e( 'The following conditions that may cause CreatorReactor to function improperly have been detected. Click Next to auto-repair the issues.', 'creatorreactor' ); ?></p>
						<div class="creatorreactor-onboarding-check-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Issues that will be auto-repaired', 'creatorreactor' ); ?>">
							<ul class="creatorreactor-onboarding-check-list">
								<?php foreach ( $remediable_rows as $row ) : ?>
									<li>
										<strong><?php echo esc_html( $row['label'] ); ?></strong>
										<span class="description"> — <?php echo esc_html( $row['message'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php elseif ( $other_red_count > 0 ) : ?>
						<p id="creatorreactor-onboarding-integration-intro" class="creatorreactor-onboarding-intro"><?php esc_html_e( 'Some integration checks are still failing, but none of them can be auto-repaired from this step. Open CreatorReactor → Settings → Debug → Integration Checks to review them, or use Ignore checks and proceed to continue.', 'creatorreactor' ); ?></p>
					<?php else : ?>
						<p id="creatorreactor-onboarding-integration-intro" class="creatorreactor-onboarding-intro"><?php esc_html_e( 'No failing checks were detected. You can continue to finish setup.', 'creatorreactor' ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $remediable_rows ) && $other_red_count > 0 ) : ?>
						<p class="description" style="margin:12px 0 0;flex-shrink:0;">
							<?php
							printf(
								/* translators: %d: number of additional failing checks not auto-repaired here */
								esc_html( _n(
									'Note: %d additional issue still appears in Integration Checks and must be addressed separately.',
									'Note: %d additional issues still appear in Integration Checks and must be addressed separately.',
									$other_red_count,
									'creatorreactor'
								) ),
								$other_red_count
							);
							?>
						</p>
					<?php endif; ?>
				</div>
				<div class="creatorreactor-modal-footer">
					<button type="button" class="button creatorreactor-onboarding-integration-cancel"><?php esc_html_e( 'Cancel', 'creatorreactor' ); ?></button>
					<button type="button" class="button creatorreactor-onboarding-integration-ignore"><?php esc_html_e( 'Ignore checks and proceed', 'creatorreactor' ); ?></button>
					<button type="button" class="button button-primary creatorreactor-onboarding-integration-next"><?php esc_html_e( 'Next', 'creatorreactor' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
