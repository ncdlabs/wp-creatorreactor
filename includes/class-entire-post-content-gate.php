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
	 * Elementor document controls + save sync (registered from {@see creatorreactor_register_elementor_integration()} on `plugins_loaded`).
	 *
	 * @return void
	 */
	public static function register_elementor_document_hooks() {
		add_action( 'elementor/documents/register_controls', [ __CLASS__, 'elementor_register_controls' ], 20 );
		add_action( 'elementor/document/save', [ __CLASS__, 'elementor_document_save' ], 10, 2 );
	}

	/**
	 * @param mixed $document Elementor document instance.
	 */
	public static function elementor_register_controls( $document ) {
		if ( ! is_object( $document ) || ! class_exists( '\Elementor\Core\DocumentTypes\PageBase' ) ) {
			return;
		}
		if ( ! $document instanceof \Elementor\Core\DocumentTypes\PageBase || ! $document::get_property( 'has_elements' ) ) {
			return;
		}
		if ( ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}
		$post_id = (int) $document->get_main_id();
		$document->start_controls_section(
			'creatorreactor_document_gate_section',
			[
				'label' => esc_html__( 'CreatorReactor', 'creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
			]
		);
		$document->add_control(
			'creatorreactor_document_gate',
			[
				'label'       => esc_html__( 'Gate entire page', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'description' => esc_html__( 'Hides the full page body for visitors who do not match (same as wrapping everything in the matching shortcode).', 'creatorreactor' ),
				'options'     => [
					''                    => esc_html__( 'None', 'creatorreactor' ),
					'subscriber'          => esc_html__( 'Subscriber only', 'creatorreactor' ),
					'follower'            => esc_html__( 'Follower only', 'creatorreactor' ),
					'logged_in'           => esc_html__( 'Any logged-in user', 'creatorreactor' ),
					'logged_out'          => esc_html__( 'Logged-out visitors only', 'creatorreactor' ),
					'logged_in_no_role'   => esc_html__( 'Logged in, no entitlement', 'creatorreactor' ),
					'fanvue_connected'    => esc_html__( 'Fanvue linked', 'creatorreactor' ),
					'fanvue_not_connected' => esc_html__( 'Fanvue not linked', 'creatorreactor' ),
				],
				'default'     => self::get_gate_for_post( $post_id ),
				'label_block' => true,
			]
		);
		$document->end_controls_section();
	}

	/**
	 * Persist Elementor Page Settings control into {@see META_KEY} for front-end and WP meta box.
	 *
	 * @param mixed $document Elementor document.
	 * @param mixed $data     Optional save payload.
	 */
	public static function elementor_document_save( $document, $data = null ) {
		if ( ! is_object( $document ) || ! class_exists( '\Elementor\Core\DocumentTypes\PageBase' ) ) {
			return;
		}
		if ( ! $document instanceof \Elementor\Core\DocumentTypes\PageBase ) {
			return;
		}
		if ( ! method_exists( $document, 'get_main_id' ) || ! method_exists( $document, 'get_settings' ) ) {
			return;
		}
		$post_id = (int) $document->get_main_id();
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$settings = $document->get_settings();
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings = array_merge( $settings, $data['settings'] );
		}
		$raw = isset( $settings['creatorreactor_document_gate'] ) ? sanitize_key( (string) $settings['creatorreactor_document_gate'] ) : '';
		if ( $raw === '' || ! in_array( $raw, self::ALLOWED_TAGS, true ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}
		update_post_meta( $post_id, self::META_KEY, $raw );
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
		if ( ! is_singular() ) {
			return $content;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return $content;
		}
		// Hello Elementor and some templates call {@see the_content()} outside the main query; still require the main queried post.
		$current_id = (int) get_the_ID();
		if ( $current_id > 0 && $current_id !== $post_id ) {
			return $content;
		}
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::is_elementor_preview_request() ) {
			return $content;
		}
		/**
		 * Skip document-wide gate (e.g. theme conflicts).
		 *
		 * @param bool   $apply   Default true.
		 * @param string $content HTML.
		 * @param int    $post_id Post ID.
		 */
		if ( ! apply_filters( 'creatorreactor_apply_document_gate_to_content', true, $content, $post_id ) ) {
			return $content;
		}
		$tag = self::get_gate_for_post( $post_id );
		if ( $tag === '' ) {
			return $content;
		}

		self::$in_filter = true;
		try {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as post content after shortcodes.
			return Shortcodes::apply_enclosing_gate( $tag, $content );
		} finally {
			self::$in_filter = false;
		}
	}
}
