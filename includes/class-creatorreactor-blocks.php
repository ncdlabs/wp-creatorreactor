<?php
/**
 * Block editor (Gutenberg) blocks for tier gates and Fanvue login.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ], 9 );
		add_filter( 'block_categories_all', [ __CLASS__, 'register_category' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_gates_inheritance_assets' ], 20 );
	}

	/**
	 * Enqueue the front-end JS that hides Gutenberg containers based on gate markers.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_gates_inheritance_assets() {
		if ( is_admin() ) {
			return;
		}
		$version = defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0';

		wp_enqueue_style(
			'creatorreactor-gates-fouc',
			CREATORREACTOR_PLUGIN_URL . 'assets/css/creatorreactor-gates-fouc.css',
			[],
			$version
		);

		wp_enqueue_script(
			'creatorreactor-gates-inheritance-core',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-gates-inheritance-core.js',
			[],
			$version,
			true
		);

		wp_enqueue_script(
			'creatorreactor-gutenberg-gates-inheritance',
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-gutenberg-gates-inheritance.js',
			[ 'creatorreactor-gates-inheritance-core' ],
			$version,
			true
		);

		$viewer_state_bootstrap = Viewer_State::bootstrap_for_inline_script();
		wp_add_inline_script(
			'creatorreactor-gutenberg-gates-inheritance',
			'window.CreatorReactorViewerState = ' . wp_json_encode( $viewer_state_bootstrap ) . ';',
			'before'
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $categories Block categories.
	 * @param \WP_Block_Editor_Context          $_context   Editor context (unused).
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_category( $categories, $_context ) {
		if ( ! is_array( $categories ) ) {
			$categories = [];
		}
		$slug = 'creatorreactor';
		foreach ( $categories as $cat ) {
			if ( is_array( $cat ) && isset( $cat['slug'] ) && $cat['slug'] === $slug ) {
				return $categories;
			}
		}
		$categories[] = [
			'slug'  => $slug,
			'title' => esc_html__( 'CreatorReactor', 'wp-creatorreactor' ),
			'icon'  => null,
		];
		return $categories;
	}

	public static function register() {
		$editor_css = '.creatorreactor-block-hint{font-size:12px;color:#6b5a74;margin:0 0 10px;padding:6px 8px;background:#faf3f8;border-radius:4px;border:1px solid #e8e0ed}.creatorreactor-block-hint strong{color:#301934};'
        . '.wp-block-creatorreactor-follower,'
        . '.wp-block-creatorreactor-subscriber,'
        . '.wp-block-creatorreactor-logged-out,'
        . '.wp-block-creatorreactor-logged-in,'
        . '.wp-block-creatorreactor-has-tier,'
        . '.wp-block-creatorreactor-fanvue-connected,'
        . '.wp-block-creatorreactor-fanvue-not-connected,'
        . '.wp-block-creatorreactor-fanvue-oauth {'
        . 'border: 2px dashed #d8c8df;'
        . 'padding: 10px;'
        . 'margin: 10px 0;'
        . '}';

		wp_register_style(
			'creatorreactor-blocks-editor',
			false,
			[],
			defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0'
		);
		wp_add_inline_style( 'creatorreactor-blocks-editor', $editor_css );

		wp_register_script(
			'creatorreactor-blocks-editor',
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-blocks-editor.js',
			[
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
			],
			defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0',
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'creatorreactor-blocks-editor', 'wp-creatorreactor', CREATORREACTOR_PLUGIN_DIR . 'languages' );
		}

		$inner_shared = [
			'api_version'     => 3,
			'category'        => 'creatorreactor',
			'editor_script'   => 'creatorreactor-blocks-editor',
			'editor_style'    => 'creatorreactor-blocks-editor',
			'supports'        => [
				'html'   => false,
				'anchor' => true,
			],
		];

		register_block_type(
			'creatorreactor/follower',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Follower', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to users with an active follower entitlement.', 'wp-creatorreactor' ),
					'icon'            => 'groups',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_follower' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/subscriber',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Subscriber', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to users with an active subscriber entitlement.', 'wp-creatorreactor' ),
					'icon'            => 'star-filled',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_subscriber' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/logged-out',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Logged out', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to visitors who are not logged in.', 'wp-creatorreactor' ),
					'icon'            => 'visibility',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_logged_out' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/logged-in',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Logged in', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users.', 'wp-creatorreactor' ),
					'icon'            => 'admin-users',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_logged_in' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/has-tier',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Has tier', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only when a logged-in user has an active tier (optional product).', 'wp-creatorreactor' ),
					'icon'            => 'awards',
					'attributes'      => [
						'tier'    => [
							'type'    => 'string',
							'default' => '',
						],
						'product' => [
							'type'    => 'string',
							'default' => '',
						],
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_has_tier' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-connected',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Fanvue connected', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users with Fanvue OAuth linked.', 'wp-creatorreactor' ),
					'icon'            => 'admin-links',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_fanvue_connected' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-not-connected',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Fanvue not connected', 'wp-creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users without Fanvue OAuth linked.', 'wp-creatorreactor' ),
					'icon'            => 'editor-unlink',
					'attributes'     => [
						'container_logic' => [
							'type'    => 'string',
							'default' => 'and',
						],
					],
					'render_callback' => [ __CLASS__, 'render_fanvue_not_connected' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-oauth',
			[
				'api_version'     => 3,
				'title'           => __( 'CreatorReactor: Login with Fanvue', 'wp-creatorreactor' ),
				'description'     => __( 'Renders the Fanvue OAuth login control using Login Button Appearance under Settings → Fanvue (Creator/direct mode).', 'wp-creatorreactor' ),
				'category'        => 'creatorreactor',
				'icon'            => 'admin-network',
				'editor_script'   => 'creatorreactor-blocks-editor',
				'editor_style'    => 'creatorreactor-blocks-editor',
				'supports'        => [
					'html'   => false,
					'anchor' => true,
				],
				'render_callback' => [ __CLASS__, 'render_fanvue_oauth' ],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_follower( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$uid = get_current_user_id();
			// Strict role-only behavior:
			// - subscriber role should not receive follower content
			// - follower role receives follower content
			$user  = get_userdata( $uid );
			$roles = ( $user instanceof \WP_User ) ? Role_Impersonation::get_effective_role_slugs_for_user( $user ) : [];
			$has_subscriber_role = in_array( 'creatorreactor_subscriber', $roles, true );
			$has_follower_role   = in_array( 'creatorreactor_follower', $roles, true );

			if ( $has_follower_role && ! $has_subscriber_role ) {
				$out = self::render_inner_content( $content );
			}
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'follower', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_subscriber( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$uid = get_current_user_id();
			// Strict role-only behavior:
			// - subscriber role receives subscriber content
			// - follower role should not receive subscriber content
			$user  = get_userdata( $uid );
			$roles = ( $user instanceof \WP_User ) ? Role_Impersonation::get_effective_role_slugs_for_user( $user ) : [];
			$has_subscriber_role = in_array( 'creatorreactor_subscriber', $roles, true );

			if ( $has_subscriber_role ) {
				$out = self::render_inner_content( $content );
			}
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'subscriber', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_logged_out( $attributes, $content, $_block ) {
		$out = '';
		if ( ! Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$out = self::render_inner_content( $content );
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'logged_out', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_logged_in( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$out = self::render_inner_content( $content );
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'logged_in', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_has_tier( $attributes, $content, $_block ) {
		// Deprecated: visibility logic is role-based only for now.
		$out = '';

		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'has_tier', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_onboarding_incomplete( $attributes, $content, $_block ) {
		$out = '';
		// Onboarding visibility gates are disabled; never render "incomplete" content.
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'onboarding_incomplete', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_onboarding_complete( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			// Onboarding visibility gates are disabled; treat logged-in users as complete.
			$out = self::render_inner_content( $content );
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'onboarding_complete', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_fanvue_connected( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
			if ( $linked === '1' || $linked === 1 || $linked === true ) {
				$out = self::render_inner_content( $content );
			}
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'fanvue_connected', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_fanvue_not_connected( $attributes, $content, $_block ) {
		$out = '';
		if ( Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
			if ( $linked !== '1' && $linked !== 1 && $linked !== true ) {
				$out = self::render_inner_content( $content );
			}
		}
		$matched = trim( (string) $out ) !== '';
		$logic   = self::resolve_container_logic( $attributes );
		return self::render_gate_marker( 'fanvue_not_connected', $matched, $logic ) . ( $out !== '' ? (string) $out : '' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_fanvue_oauth( $attributes, $content, $_block ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		return Shortcodes::fanvue_oauth( [] );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Unused.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_onboarding( $attributes, $content, $_block ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		return do_shortcode( '[creatorreactor_onboarding]' );
	}

	/**
	 * @param string $content Serialized inner blocks / HTML.
	 */
	private static function render_inner_content( $content ) {
		$content = is_string( $content ) ? trim( $content ) : '';
		if ( $content === '' ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed of trusted inner block output.
		return do_blocks( $content );
	}

	/**
	 * @param mixed $attributes Block attributes.
	 * @return 'and'|'or'
	 */
	private static function resolve_container_logic( $attributes ): string {
		if ( ! is_array( $attributes ) ) {
			return 'and';
		}
		$logic = isset( $attributes['container_logic'] ) ? sanitize_key( (string) $attributes['container_logic'] ) : 'and';
		return ( $logic === 'or' ) ? 'or' : 'and';
	}

	/**
	 * @param string $gate
	 * @param bool   $matched
	 * @param string $logic
	 * @return string
	 */
	private static function render_gate_marker( string $gate, bool $matched, string $logic ): string {
		$logic = $logic === 'or' ? 'or' : 'and';
		$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();

		return Gate_Marker::render_gutenberg_span( $gate, $matched, $logic, $roles );
	}
}
