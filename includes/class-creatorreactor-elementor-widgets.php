<?php
/**
 * Elementor widget classes (loaded from {@see Elementor_Integration::boot()} only).
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
 * Base widget: inner HTML wrapped in a paired shortcode.
 */
abstract class Elementor_Widget_Shortcode_Wrap extends \Elementor\Widget_Base {

	abstract protected function shortcode_tag(): string;

	public function get_categories() {
		return [ 'creatorreactor' ];
	}

	public function get_icon() {
		return 'eicon-shortcode';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'inner_content',
			[
				'label'       => __( 'Visible content', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::WYSIWYG,
				'default'     => '',
				'description' => __( 'Shown only when the visitor matches this block’s visibility rules (same as the matching shortcode).', 'creatorreactor' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$inner = isset( $s['inner_content'] ) ? $s['inner_content'] : '';
		$tag   = $this->shortcode_tag();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode/HTML output (same as post content).
		echo do_shortcode( '[' . $tag . ']' . $inner . '[/' . $tag . ']' );
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Follower extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_follower';
	}

	public function get_title() {
		return __( 'CreatorReactor: Follower', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'follower', 'fanvue', 'tier', 'entitlement' ];
	}

	protected function shortcode_tag(): string {
		return 'follower';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Subscriber extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_subscriber';
	}

	public function get_title() {
		return __( 'CreatorReactor: Subscriber', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'subscriber', 'fanvue', 'tier', 'entitlement' ];
	}

	protected function shortcode_tag(): string {
		return 'subscriber';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Not_Logged_In extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_logged_in_no_role';
	}

	public function get_title() {
		return __( 'CreatorReactor: Logged in no role', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'logged in', 'no role', 'entitlement' ];
	}

	protected function shortcode_tag(): string {
		return 'logged_in_no_role';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Logged_Out extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_logged_out';
	}

	public function get_title() {
		return __( 'CreatorReactor: Logged out', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'guest', 'visitor', 'logged out' ];
	}

	protected function shortcode_tag(): string {
		return 'logged_out';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Logged_In extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_logged_in';
	}

	public function get_title() {
		return __( 'CreatorReactor: Logged in', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'member', 'logged in', 'user' ];
	}

	protected function shortcode_tag(): string {
		return 'logged_in';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Has_Tier extends \Elementor\Widget_Base {

	public function get_name() {
		return 'creatorreactor_has_tier';
	}

	public function get_title() {
		return __( 'CreatorReactor: Has tier', 'creatorreactor' );
	}

	public function get_categories() {
		return [ 'creatorreactor' ];
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'tier', 'subscriber', 'entitlement' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Tier conditions', 'creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'tier',
			[
				'label'       => __( 'Tier', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'premium', 'creatorreactor' ),
				'description' => __( 'Leave empty to match any active tier.', 'creatorreactor' ),
			]
		);

		$this->add_control(
			'product',
			[
				'label'       => __( 'Product', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'fanvue', 'creatorreactor' ),
				'description' => __( 'Leave empty to match across products.', 'creatorreactor' ),
			]
		);

		$this->add_control(
			'inner_content',
			[
				'label'       => __( 'Visible content', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::WYSIWYG,
				'default'     => '',
				'description' => __( 'Shown only when the visitor matches these tier conditions.', 'creatorreactor' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$inner    = isset( $s['inner_content'] ) ? (string) $s['inner_content'] : '';
		$tier     = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
		$product  = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo Shortcodes::has_tier(
			[
				'tier'    => $tier,
				'product' => $product,
			],
			$inner
		);
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Fanvue_Connected extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_fanvue_connected';
	}

	public function get_title() {
		return __( 'CreatorReactor: Fanvue connected', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'fanvue', 'connected', 'oauth' ];
	}

	protected function shortcode_tag(): string {
		return 'fanvue_connected';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Fanvue_Not_Connected extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_fanvue_not_connected';
	}

	public function get_title() {
		return __( 'CreatorReactor: Fanvue not connected', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'fanvue', 'not connected', 'oauth' ];
	}

	protected function shortcode_tag(): string {
		return 'fanvue_not_connected';
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Fanvue_Oauth extends \Elementor\Widget_Base {

	public function get_name() {
		return 'creatorreactor_fanvue_oauth';
	}

	public function get_title() {
		return __( 'CreatorReactor: Login with Fanvue', 'creatorreactor' );
	}

	public function get_categories() {
		return [ 'creatorreactor' ];
	}

	public function get_icon() {
		return 'eicon-lock';
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'fanvue', 'oauth', 'login' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_note',
			[
				'label' => __( 'About', 'creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'note',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Outputs the same “Login with Fanvue” link as the [fanvue_login_button] shortcode (Creator/direct mode only).', 'creatorreactor' ) . '</p>',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo Shortcodes::fanvue_oauth( [] );
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Onboarding extends \Elementor\Widget_Base {

	public function get_name() {
		return 'creatorreactor_onboarding';
	}

	public function get_title() {
		return __( 'CreatorReactor: Fan onboarding', 'creatorreactor' );
	}

	public function get_categories() {
		return [ 'creatorreactor' ];
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'fanvue', 'onboarding', 'signup' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_note',
			[
				'label' => __( 'About', 'creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'note',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Fan onboarding is disabled. This widget mirrors the [creatorreactor_onboarding] shortcode status message.', 'creatorreactor' ) . '</p>',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo do_shortcode( '[creatorreactor_onboarding]' );
	}
}
