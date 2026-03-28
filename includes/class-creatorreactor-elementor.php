<?php
/**
 * Elementor integration loader (widgets load only after Elementor is ready).
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
 * Registers the CreatorReactor category and widgets with Elementor.
 */
final class Elementor_Integration {

	/**
	 * Whether gated-content widgets can use nested containers (Elementor Nested Elements + Container).
	 */
	public static function nested_gate_widgets_supported(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! $plugin || ! isset( $plugin->experiments ) ) {
			return false;
		}
		$experiments = $plugin->experiments;

		return class_exists( '\Elementor\Modules\NestedElements\Base\Widget_Nested_Base' )
			&& $experiments->is_feature_active( 'nested-elements' )
			&& $experiments->is_feature_active( 'container' );
	}

	public static function init() {
		add_action( 'elementor/init', [ __CLASS__, 'boot' ], 5 );
	}

	public static function boot() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-elementor-widgets.php';

		$elements = \Elementor\Plugin::instance()->elements_manager;
		if ( method_exists( $elements, 'add_category' ) ) {
			$elements->add_category(
				'creatorreactor',
				[
					'title' => esc_html__( 'CreatorReactor', 'creatorreactor' ),
					'icon'  => 'eicon-shortcode',
				]
			);
		}

		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_gates_inheritance_assets' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_gates_editor_constraint_assets' ], 20 );
	}

	/**
	 * @return void
	 */
	public static function enqueue_frontend_gates_inheritance_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::is_elementor_preview_request() ) {
			return;
		}
		$version = defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0';

		wp_enqueue_script(
			'creatorreactor-elementor-gates-inheritance',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-elementor-gates-inheritance.js',
			[],
			$version,
			false
		);

		$roles = [];
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
				$roles = array_values( array_map( 'sanitize_key', $user->roles ) );
			}
		}
		$viewer_state_bootstrap = [
			'loggedIn' => is_user_logged_in(),
			'roles'    => $roles,
		];
		wp_add_inline_script(
			'creatorreactor-elementor-gates-inheritance',
			'window.CreatorReactorViewerState = window.CreatorReactorViewerState || ' . wp_json_encode( $viewer_state_bootstrap ) . ';',
			'before'
		);
	}

	/**
	 * Elementor editor: enforce "1 gate widget per container" so the layout doesn't
	 * become ambiguous for inheritance logic.
	 *
	 * This is only loaded on Elementor builder requests: post.php?action=elementor.
	 *
	 * @return void
	 */
	public static function enqueue_elementor_gates_editor_constraint_assets() {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! class_exists( __NAMESPACE__ . '\\Editor_Context' ) ) {
			return;
		}
		if ( ! Editor_Context::is_elementor_edit_request() ) {
			return;
		}

		$version = defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0';

		wp_enqueue_script(
			'creatorreactor-elementor-gates-editor-constraint',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-elementor-gates-editor-constraint.js',
			[],
			$version,
			true
		);

		wp_localize_script(
			'creatorreactor-elementor-gates-editor-constraint',
			'CreatorReactorElementorGateEditorConstraint',
			[
				'warningText' => __( 'Only one CreatorReactor content gate widget per container is allowed.', 'creatorreactor' ),
			]
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		$use_nested_gates = self::nested_gate_widgets_supported()
			&& class_exists( __NAMESPACE__ . '\\Elementor_Widget_Follower_Nested' );

		$widgets = [
			$use_nested_gates ? new Elementor_Widget_Follower_Nested() : new Elementor_Widget_Follower_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Subscriber_Nested() : new Elementor_Widget_Subscriber_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Not_Logged_In_Nested() : new Elementor_Widget_Not_Logged_In_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Logged_Out_Nested() : new Elementor_Widget_Logged_Out_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Logged_In_Nested() : new Elementor_Widget_Logged_In_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Has_Tier_Nested() : new Elementor_Widget_Has_Tier_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Fanvue_Connected_Nested() : new Elementor_Widget_Fanvue_Connected_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Fanvue_Not_Connected_Nested() : new Elementor_Widget_Fanvue_Not_Connected_Legacy(),
			new Elementor_Widget_Fanvue_Oauth(),
		];

		foreach ( $widgets as $widget ) {
			if ( method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( $widget );
			} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
				$widgets_manager->register_widget_type( $widget );
			}
		}
	}
}
