<?php
/**
 * Prevent full-page HTML caching for pages that use CreatorReactor gates.
 *
 * Without this, a cache hit can serve HTML generated for a logged-in subscriber to guests
 * (wrong data-creatorreactor-gate-match and visible gated content).
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
 * Sets cache-control constants early so LiteSpeed / WP Super Cache / similar skip caching.
 */
final class Gated_Content_Cache {

	public static function init() {
		add_action( 'wp', [ __CLASS__, 'maybe_disable_full_page_cache' ], 0 );
	}

	/**
	 * @return void
	 */
	public static function maybe_disable_full_page_cache() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$post_id = self::resolve_singular_post_id();
		if ( $post_id < 1 ) {
			return;
		}
		if ( ! self::post_has_creatorreactor_gates( $post_id ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		// LiteSpeed Cache (Hostinger and many stacks).
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}

		// Hard stop: set explicit headers so even caches that don't honor the constants
		// (or evaluate them too late) cannot serve a user-specific gated response.
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			// Some CDNs (Fastly/Akamai) look for this header too.
			header( 'Surrogate-Control: no-store' );
		}
	}

	/**
	 * @return int Post ID or 0.
	 */
	private static function resolve_singular_post_id() {
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}
		if ( is_front_page() && ! is_singular() ) {
			$on_front = (int) get_option( 'page_on_front', 0 );
			return $on_front > 0 ? $on_front : 0;
		}
		return 0;
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function post_has_creatorreactor_gates( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		if ( class_exists( __NAMESPACE__ . '\\Entire_Post_Content_Gate' )
			&& Entire_Post_Content_Gate::get_gate_for_post( (int) $post->ID ) !== '' ) {
			return true;
		}
		$content = (string) $post->post_content;
		if ( strpos( $content, 'wp-block-creatorreactor-' ) !== false ) {
			return true;
		}
		if ( preg_match( '/\[\s*(subscriber|follower|has_tier|logged_in|logged_out|logged_in_no_role|fanvue_connected|fanvue_not_connected)\b/', $content ) ) {
			return true;
		}
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::post_uses_elementor_storage( $post->ID ) ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $data ) && strpos( $data, 'creatorreactor_' ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
