<?php
/**
 * Optional per-post wrap of the full front-end body (after Elementor) in a CreatorReactor gate shortcode.
 *
 * Use when Elementor content is not inside CreatorReactor gate widgets (e.g. only Image/Text widgets).
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
 * Post meta + {@see 'the_content'} filter for document-wide gating.
 */
final class Entire_Post_Content_Gate {

	const META_KEY = '_creatorreactor_document_gate';

	/** @var bool */
	private static $in_filter = false;

	/**
	 * Shortcode tags allowed for document-wide gating (must match registered shortcodes).
	 *
	 * @var list<string>
	 */
	private const ALLOWED_TAGS = [
		'subscriber',
		'follower',
		'logged_in',
		'logged_out',
		'logged_in_no_role',
		'fanvue_connected',
		'fanvue_not_connected',
	];

	/**
	 * Run after Elementor replaces post content (Elementor uses a low {@see 'the_content'} priority).
	 */
	private const CONTENT_FILTER_PRIORITY = 9999999;

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 2 );
		add_filter( 'the_content', [ __CLASS__, 'filter_the_content' ], self::CONTENT_FILTER_PRIORITY, 1 );
	}

	/**
	 * @return list<string>
	 */
	public static function post_types_with_meta_box(): array {
		$types = [ 'post', 'page' ];
		/**
		 * Post types that show the CreatorReactor “gate entire post” meta box.
		 *
		 * @param list<string> $types
		 */
		$filtered = apply_filters( 'creatorreactor_document_gate_post_types', $types );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'sanitize_key', $filtered ) ) ) : $types;
	}

	/**
	 * @param string $post_type Post type slug.
	 */
	public static function post_type_uses_document_gate_meta( string $post_type ): bool {
		return in_array( $post_type, self::post_types_with_meta_box(), true );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string '' or one of {@see ALLOWED_TAGS}
	 */
	public static function get_gate_for_post( int $post_id ): string {
		if ( $post_id < 1 ) {
			return '';
		}
		$v = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_string( $v ) || $v === '' ) {
			return '';
		}
		$v = sanitize_key( $v );
		return in_array( $v, self::ALLOWED_TAGS, true ) ? $v : '';
	}

	/**
	 * @return void
	 */
	public static function add_meta_box() {
		foreach ( self::post_types_with_meta_box() as $pt ) {
			if ( post_type_exists( $pt ) ) {
				add_meta_box(
					'creatorreactor_document_gate',
					__( 'CreatorReactor: gate entire post', 'creatorreactor' ),
					[ __CLASS__, 'render_meta_box' ],
					$pt,
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * @param \WP_Post $post Post object.
	 */
	public static function render_meta_box( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_nonce_field( 'creatorreactor_save_document_gate', 'creatorreactor_document_gate_nonce' );
		$current = self::get_gate_for_post( (int) $post->ID );
		?>
		<p class="description" style="margin-top:0;">
			<?php esc_html_e( 'Wraps the full front-end post body (including Elementor output) in the chosen gate. Use when content is not inside CreatorReactor Elementor widgets.', 'creatorreactor' ); ?>
		</p>
		<p>
			<label for="creatorreactor_document_gate" class="screen-reader-text"><?php esc_html_e( 'Gate', 'creatorreactor' ); ?></label>
			<select name="creatorreactor_document_gate" id="creatorreactor_document_gate" style="width:100%;">
				<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'None', 'creatorreactor' ); ?></option>
				<option value="subscriber" <?php selected( $current, 'subscriber' ); ?>><?php esc_html_e( 'Subscriber only', 'creatorreactor' ); ?></option>
				<option value="follower" <?php selected( $current, 'follower' ); ?>><?php esc_html_e( 'Follower only', 'creatorreactor' ); ?></option>
				<option value="logged_in" <?php selected( $current, 'logged_in' ); ?>><?php esc_html_e( 'Any logged-in user', 'creatorreactor' ); ?></option>
				<option value="logged_out" <?php selected( $current, 'logged_out' ); ?>><?php esc_html_e( 'Logged-out visitors only', 'creatorreactor' ); ?></option>
				<option value="logged_in_no_role" <?php selected( $current, 'logged_in_no_role' ); ?>><?php esc_html_e( 'Logged in, no entitlement', 'creatorreactor' ); ?></option>
				<option value="fanvue_connected" <?php selected( $current, 'fanvue_connected' ); ?>><?php esc_html_e( 'Fanvue linked', 'creatorreactor' ); ?></option>
				<option value="fanvue_not_connected" <?php selected( $current, 'fanvue_not_connected' ); ?>><?php esc_html_e( 'Fanvue not linked', 'creatorreactor' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_post( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['creatorreactor_document_gate_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['creatorreactor_document_gate_nonce'] ) ), 'creatorreactor_save_document_gate' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! self::post_type_uses_document_gate_meta( $post->post_type ) ) {
			return;
		}
		$raw = isset( $_POST['creatorreactor_document_gate'] ) ? sanitize_key( wp_unslash( $_POST['creatorreactor_document_gate'] ) ) : '';
		if ( $raw === '' || ! in_array( $raw, self::ALLOWED_TAGS, true ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}
		update_post_meta( $post_id, self::META_KEY, $raw );
	}

	/**
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public static function filter_the_content( $content ) {
		if ( self::$in_filter ) {
			return $content;
		}
		if ( ! is_string( $content ) || $content === '' ) {
			return $content;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}
		if ( wp_is_json_request() || is_feed() ) {
			return $content;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::is_elementor_preview_request() ) {
			return $content;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return $content;
		}
		$tag = self::get_gate_for_post( $post_id );
		if ( $tag === '' ) {
			return $content;
		}

		self::$in_filter = true;
		try {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as post content after shortcodes.
			return do_shortcode( '[' . $tag . ']' . $content . '[/' . $tag . ']' );
		} finally {
			self::$in_filter = false;
		}
	}
}
