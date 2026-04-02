<?php
/**
 * Detect Elementor vs block editor (Gutenberg): admin screen, stored post data, and front-end view.
 *
 * Heuristics only — hybrid setups (e.g. Elementor + shortcodes) can blur boundaries.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor_Context {

	/**
	 * Whether Elementor is active for this site (class loaded and/or plugin active, including network activation).
	 */
	public static function is_elementor_plugin_active() {
		if ( class_exists( '\Elementor\Plugin', false ) ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @codeCoverageIgnoreStart
			if ( ! defined( 'ABSPATH' ) ) {
				return false;
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'elementor/elementor.php' ) ) {
			return true;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' )
			&& is_plugin_active_for_network( 'elementor/elementor.php' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Site uses the block editor: enabled for post or page, or published content contains block markup.
	 */
	public static function site_uses_block_editor() {
		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			foreach ( [ 'post', 'page' ] as $pt ) {
				if ( post_type_exists( $pt ) && use_block_editor_for_post_type( $pt ) ) {
					return true;
				}
			}
		}
		global $wpdb;
		$pattern = $wpdb->esc_like( '<!-- wp:' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$wpdb->posts} is a trusted core table name.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} WHERE post_status = %s AND post_content LIKE %s LIMIT 1",
				'publish',
				$pattern
			)
		);
		return $found !== null && $found !== '';
	}

	/**
	 * Admin: this HTTP request is opening Elementor’s editor for a post (post.php?action=elementor).
	 */
	public static function is_elementor_edit_request() {
		if ( ! is_admin() ) {
			return false;
		}
		if ( empty( $_GET['action'] ) ) {
			return false;
		}
		$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		return $action === 'elementor';
	}

	/**
	 * Front-end request is Elementor’s live preview (editor canvas iframe), not a normal visitor view.
	 * Gate visibility scripts must not run here or they hide gated containers and block drag-and-drop.
	 *
	 * Only the `elementor-preview` query parameter is used. Elementor’s `is_preview_mode()` /
	 * `is_edit_mode()` can be true on ordinary front-end requests in some versions, which would
	 * incorrectly skip scripts and break gated layout for visitors.
	 */
	public static function is_elementor_preview_request() {
		if ( is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence-only probe for preview URL shape.
		return isset( $_GET['elementor-preview'] );
	}

	/**
	 * Admin: current screen is the block editor (post editor with blocks).
	 */
	public static function is_block_editor_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! method_exists( $screen, 'is_block_editor' ) ) {
			return false;
		}
		return (bool) $screen->is_block_editor();
	}

	/**
	 * Resolve post ID from argument or current loop / queried object.
	 */
	private static function resolve_post_id( $post_id ) {
		if ( $post_id !== null && $post_id !== '' ) {
			return (int) $post_id;
		}
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}
		$maybe = get_the_ID();
		return $maybe ? (int) $maybe : 0;
	}

	/**
	 * Post is stored as an Elementor-built document (_elementor_edit_mode = builder).
	 */
	public static function post_uses_elementor_storage( $post_id = null ) {
		$pid = self::resolve_post_id( $post_id );
		if ( $pid < 1 ) {
			return false;
		}
		$mode = get_post_meta( $pid, '_elementor_edit_mode', true );
		return is_string( $mode ) && $mode === 'builder';
	}

	/**
	 * Post content string contains block markup (Gutenberg).
	 *
	 * @param string|null $content Raw post_content; null loads the post by ID.
	 */
	public static function content_has_blocks( $content = null, $post_id = null ) {
		if ( $content !== null ) {
			return function_exists( 'has_blocks' ) ? has_blocks( (string) $content ) : ( strpos( (string) $content, '<!-- wp:' ) !== false );
		}
		$pid = self::resolve_post_id( $post_id );
		if ( $pid < 1 ) {
			return false;
		}
		$post = get_post( $pid );
		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}
		return function_exists( 'has_blocks' ) ? has_blocks( $post->post_content ) : ( strpos( $post->post_content, '<!-- wp:' ) !== false );
	}

	/**
	 * Front end: singular view of a post that Elementor marks as built with Elementor (body class check).
	 */
	public static function frontend_view_is_elementor_page() {
		if ( is_admin() ) {
			return false;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$classes = get_body_class();
		return is_array( $classes ) && in_array( 'elementor-page', $classes, true );
	}

	/**
	 * How the post’s main stored body is best described (for picking blocks vs Elementor widgets in docs/tools).
	 *
	 * @return string 'elementor'|'blocks'|'empty'|'html'
	 */
	public static function post_primary_storage( $post_id = null ) {
		if ( self::post_uses_elementor_storage( $post_id ) ) {
			return 'elementor';
		}
		if ( self::content_has_blocks( null, $post_id ) ) {
			return 'blocks';
		}
		$pid = self::resolve_post_id( $post_id );
		if ( $pid < 1 ) {
			return 'empty';
		}
		$post = get_post( $pid );
		if ( ! $post || ! is_string( $post->post_content ) ) {
			return 'empty';
		}
		return trim( $post->post_content ) === '' ? 'empty' : 'html';
	}

	/**
	 * Admin: which editor UI is active for this request (if any).
	 *
	 * @return string 'elementor'|'block'|'other'
	 */
	public static function current_admin_editor_ui() {
		if ( self::is_elementor_edit_request() ) {
			return 'elementor';
		}
		if ( self::is_block_editor_screen() ) {
			return 'block';
		}
		return 'other';
	}
}
