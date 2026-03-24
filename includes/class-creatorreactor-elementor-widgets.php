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
		return 'creatorreactor_not_logged_in';
	}

	public function get_title() {
		return __( 'CreatorReactor: Not logged in', 'creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'guest', 'logout', 'visitor' ];
	}

	protected function shortcode_tag(): string {
		return 'not_logged_in';
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
				'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Outputs the same first-time Fanvue setup form as the [creatorreactor_onboarding] shortcode. After social login, users are redirected to /creatorreactor-onboarding/ until they complete it.', 'creatorreactor' ) . '</p>',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo do_shortcode( '[creatorreactor_onboarding]' );
	}
}
