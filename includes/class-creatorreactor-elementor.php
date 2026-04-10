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
		// The nested gate widgets should be registered whenever Elementor's NestedElements
		// base widget exists. Relying on experiment flags can differ between editor and
		// front-end and prevents Elementor from instantiating saved nested widget types.
		return class_exists( '\Elementor\Modules\NestedElements\Base\Widget_Nested_Base' );
	}

	public static function init() {
		// Register widgets as early as possible so `elementor/widgets/register` is not fired
		// before we hook `register_widgets()`.
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ], 0 );
		add_action( 'elementor/init', [ __CLASS__, 'boot' ], 0 );
	}

	public static function boot() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-gate-registry.php';
		require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-elementor-widgets.php';

		$elements = \Elementor\Plugin::instance()->elements_manager;
		if ( method_exists( $elements, 'add_category' ) ) {
			$elements->add_category(
				'creatorreactor',
				[
					'title' => esc_html__( 'CreatorReactor', 'wp-creatorreactor' ),
					'icon'  => 'eicon-shortcode',
				]
			);
		}

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_gates_inheritance_assets' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_gates_editor_constraint_assets' ], 20 );
		// After {@see \Elementor\Element_Base::add_render_attributes()} — not `elementor/frontend/before_render`,
		// which runs earlier and would lose attrs when Elementor re-inits `_wrapper`.
		add_action( 'elementor/element/after_add_attributes', [ __CLASS__, 'inject_creatorreactor_gate_wrapper_attributes' ], 10, 1 );
	}

	/**
	 * Gate metadata on the widget wrapper — Elementor drops inner HTML when a widget renders nothing,
	 * which removed hidden spans and broke client/server gate stripping for sibling widgets in the same column.
	 *
	 * @param \Elementor\Element_Base $element Elementor element instance.
	 */
	public static function inject_creatorreactor_gate_wrapper_attributes( $element ) {
		if ( ! $element instanceof \Elementor\Widget_Base ) {
			return;
		}
		$name = $element->get_name();
		if ( strpos( $name, 'creatorreactor_' ) !== 0 || $name === 'creatorreactor_fanvue_oauth' ) {
			return;
		}

		$s               = $element->get_settings_for_display();
		$container_logic = isset( $s['container_logic'] ) ? sanitize_key( (string) $s['container_logic'] ) : 'and';
		if ( $container_logic !== 'and' && $container_logic !== 'or' ) {
			$container_logic = 'and';
		}
		$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();

		if ( $name === 'creatorreactor_has_tier' ) {
			$tier    = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
			$product = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';
			$probe   = Shortcodes::has_tier(
				[
					'tier'    => $tier,
					'product' => $product,
				],
				'__creatorreactor_gate_probe__'
			);
			$matched   = trim( (string) $probe ) !== '';
			$match_str = $matched ? '1' : '0';
			$gate      = 'has_tier';
		} else {
			$tag = Gate_Registry::elementor_widget_shortcode_gate_slug( $name );
			if ( $tag === null ) {
				return;
			}
			$probe     = Shortcodes::apply_enclosing_gate( $tag, '__creatorreactor_gate_probe__' );
			$matched   = trim( (string) $probe ) !== '';
			$match_str = $matched ? '1' : '0';
			$gate      = $tag;
		}

		$element->add_render_attribute(
			'_wrapper',
			[
				'data-creatorreactor-gate'       => $gate,
				'data-creatorreactor-gate-match' => $match_str,
				'data-creatorreactor-gate-logic' => $container_logic,
				'data-creatorreactor-user-roles' => $roles,
			]
		);
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

		wp_enqueue_style(
			'creatorreactor-gates-fouc',
			CREATORREACTOR_PLUGIN_URL . 'assets/css/creatorreactor-gates-fouc.css',
			[],
			$version
		);

		if ( ! class_exists( __NAMESPACE__ . '\\Gate_Registry' ) ) {
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-gate-registry.php';
		}

		wp_enqueue_script(
			'creatorreactor-gates-inheritance-core',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-gates-inheritance-core.js',
			[],
			$version,
			true
		);

		wp_enqueue_script(
			'creatorreactor-elementor-gates-inheritance',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-elementor-gates-inheritance.js',
			[ 'creatorreactor-gates-inheritance-core' ],
			$version,
			true
		);

		wp_localize_script(
			'creatorreactor-elementor-gates-inheritance',
			'CreatorReactorElementorGatesInheritance',
			[
				'widgetNameToGate' => Gate_Registry::elementor_widget_name_to_gate_slug_for_js(),
			]
		);

		$viewer_state_bootstrap = Viewer_State::bootstrap_for_inline_script();
		wp_add_inline_script(
			'creatorreactor-elementor-gates-inheritance',
			'window.CreatorReactorViewerState = ' . wp_json_encode( $viewer_state_bootstrap ) . ';',
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
				'warningText' => __( 'Only one CreatorReactor content gate widget per container is allowed.', 'wp-creatorreactor' ),
			]
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		// In case `boot()` didn't run early enough, ensure widget classes are loaded before registration.
		if ( ! class_exists( __NAMESPACE__ . '\\Elementor_Widget_Follower_Legacy' ) ) {
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-elementor-widgets.php';
		}

		$use_nested_gates = self::nested_gate_widgets_supported()
			&& class_exists( __NAMESPACE__ . '\\Elementor_Widget_Follower_Nested' );

		$widgets = [
			$use_nested_gates ? new Elementor_Widget_Follower_Nested() : new Elementor_Widget_Follower_Legacy(),
			$use_nested_gates ? new Elementor_Widget_Subscriber_Nested() : new Elementor_Widget_Subscriber_Legacy(),
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
