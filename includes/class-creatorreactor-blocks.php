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
			'title' => esc_html__( 'CreatorReactor', 'creatorreactor' ),
			'icon'  => null,
		];
		return $categories;
	}

	public static function register() {
		$editor_css = '.creatorreactor-block-hint{font-size:12px;color:#757575;margin:0 0 10px;padding:6px 8px;background:#f0f0f1;border-radius:4px}.creatorreactor-block-hint strong{color:#1d2327};'
        . '.wp-block-creatorreactor-follower,'
        . '.wp-block-creatorreactor-subscriber,'
        . '.wp-block-creatorreactor-not-logged-in,'
        . '.wp-block-creatorreactor-logged-out,'
        . '.wp-block-creatorreactor-logged-in,'
        . '.wp-block-creatorreactor-has-tier,'
        . '.wp-block-creatorreactor-onboarding-incomplete,'
        . '.wp-block-creatorreactor-onboarding-complete,'
        . '.wp-block-creatorreactor-fanvue-connected,'
        . '.wp-block-creatorreactor-fanvue-not-connected,'
        . '.wp-block-creatorreactor-fanvue-oauth,'
        . '.wp-block-creatorreactor-onboarding {'
        . 'border: 2px dashed #ccc;'
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
			wp_set_script_translations( 'creatorreactor-blocks-editor', 'creatorreactor', CREATORREACTOR_PLUGIN_DIR . 'languages' );
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
					'title'           => __( 'CreatorReactor: Follower', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to users with an active follower entitlement.', 'creatorreactor' ),
					'icon'            => 'groups',
					'render_callback' => [ __CLASS__, 'render_follower' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/subscriber',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Subscriber', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to users with an active subscriber entitlement.', 'creatorreactor' ),
					'icon'            => 'star-filled',
					'render_callback' => [ __CLASS__, 'render_subscriber' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/not-logged-in',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Logged in no role', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in visitors with no specific role/entitlement.', 'creatorreactor' ),
					'icon'            => 'visibility',
					'render_callback' => [ __CLASS__, 'render_logged_in_no_role' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/logged-out',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Logged out', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to visitors who are not logged in.', 'creatorreactor' ),
					'icon'            => 'visibility',
					'render_callback' => [ __CLASS__, 'render_logged_out' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/logged-in',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Logged in', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users.', 'creatorreactor' ),
					'icon'            => 'admin-users',
					'render_callback' => [ __CLASS__, 'render_logged_in' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/has-tier',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Has tier', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only when a logged-in user has an active tier (optional product).', 'creatorreactor' ),
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
					],
					'render_callback' => [ __CLASS__, 'render_has_tier' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/onboarding-incomplete',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Onboarding incomplete', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users who still need onboarding.', 'creatorreactor' ),
					'icon'            => 'welcome-learn-more',
					'render_callback' => [ __CLASS__, 'render_onboarding_incomplete' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/onboarding-complete',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Onboarding complete', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users who completed onboarding.', 'creatorreactor' ),
					'icon'            => 'yes-alt',
					'render_callback' => [ __CLASS__, 'render_onboarding_complete' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-connected',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Fanvue connected', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users with Fanvue OAuth linked.', 'creatorreactor' ),
					'icon'            => 'admin-links',
					'render_callback' => [ __CLASS__, 'render_fanvue_connected' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-not-connected',
			array_merge(
				$inner_shared,
				[
					'title'           => __( 'CreatorReactor: Fanvue not connected', 'creatorreactor' ),
					'description'     => __( 'Inner blocks visible only to logged-in users without Fanvue OAuth linked.', 'creatorreactor' ),
					'icon'            => 'editor-unlink',
					'render_callback' => [ __CLASS__, 'render_fanvue_not_connected' ],
				]
			)
		);

		register_block_type(
			'creatorreactor/fanvue-oauth',
			[
				'api_version'     => 3,
				'title'           => __( 'CreatorReactor: Login with Fanvue', 'creatorreactor' ),
				'description'     => __( 'Renders the Fanvue OAuth login link (Creator/direct mode).', 'creatorreactor' ),
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

		register_block_type(
			'creatorreactor/onboarding',
			[
				'api_version'     => 3,
				'title'           => __( 'CreatorReactor: Fan onboarding', 'creatorreactor' ),
				'description'     => __( 'First-time Fanvue login setup form (or use /creatorreactor-onboarding/).', 'creatorreactor' ),
				'category'        => 'creatorreactor',
				'icon'            => 'welcome-learn-more',
				'editor_script'   => 'creatorreactor-blocks-editor',
				'editor_style'    => 'creatorreactor-blocks-editor',
				'supports'        => [
					'html'   => false,
					'anchor' => true,
				],
				'render_callback' => [ __CLASS__, 'render_onboarding' ],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_follower( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}
		if ( ! Entitlements::wp_user_has_active_follower_entitlement( $uid ) ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_subscriber( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}
		if ( ! Entitlements::wp_user_has_active_subscriber_entitlement( $uid ) ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_logged_in_no_role( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( self::user_has_any_active_entitlement( get_current_user_id() ) ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_logged_out( $attributes, $content, $_block ) {
		if ( is_user_logged_in() ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_logged_in( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_has_tier( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( Onboarding::user_needs_onboarding( $uid ) ) {
			return Onboarding::incomplete_gate_notice();
		}

		$attributes = is_array( $attributes ) ? $attributes : [];
		$tier       = isset( $attributes['tier'] ) ? trim( sanitize_text_field( (string) $attributes['tier'] ) ) : '';
		$product    = isset( $attributes['product'] ) ? trim( sanitize_text_field( (string) $attributes['product'] ) ) : '';

		$has_entitlement = Entitlements::check_user_entitlement(
			$uid,
			$tier !== '' ? $tier : null,
			$product !== '' ? $product : null
		);
		if ( ! $has_entitlement ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_onboarding_incomplete( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( ! Onboarding::user_needs_onboarding( get_current_user_id() ) ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_onboarding_complete( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( Onboarding::user_needs_onboarding( get_current_user_id() ) ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_fanvue_connected( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked !== '1' && $linked !== 1 && $linked !== true ) {
			return '';
		}
		return self::render_inner_content( $content );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks markup.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render_fanvue_not_connected( $attributes, $content, $_block ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$linked = get_user_meta( get_current_user_id(), Onboarding::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked === '1' || $linked === 1 || $linked === true ) {
			return '';
		}
		return self::render_inner_content( $content );
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
	 * @param int $user_id WordPress user ID.
	 */
	private static function user_has_any_active_entitlement( $user_id ) {
		$rows = Entitlements::get_active_entitlement_rows_for_wp_user( (int) $user_id );
		return ! empty( $rows );
	}
}
