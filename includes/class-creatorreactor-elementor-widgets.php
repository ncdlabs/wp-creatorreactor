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
 * Shared Elementor control: container AND/OR visibility (matches Gutenberg gate blocks).
 */
trait Elementor_Widget_Container_Logic_Control_Trait {

	protected function add_elementor_container_logic_control() {
		$this->add_control(
			'container_logic',
			[
				'label'       => __( 'Container visibility logic', 'wp-creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'and',
				'options'     => [
					'and' => __( 'AND (current): hide container if any gate fails', 'wp-creatorreactor' ),
					'or'  => __( 'OR: show container if any gate passes', 'wp-creatorreactor' ),
				],
				'description' => __( 'Block editor: used when multiple gate blocks share a layout container. Elementor: each gate only affects its own widget on the front end.', 'wp-creatorreactor' ),
			]
		);
	}
}

/**
 * Base widget: inner HTML wrapped in a paired shortcode.
 */
abstract class Elementor_Widget_Shortcode_Wrap extends \Elementor\Widget_Base {

	use Elementor_Widget_Container_Logic_Control_Trait;

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
				'label' => __( 'Content', 'wp-creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'inner_content',
			[
				'label'       => __( 'Visible content', 'wp-creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::WYSIWYG,
				'default'     => '',
				'description' => __( 'Shown only when the visitor matches this block’s visibility rules (same as the matching shortcode).', 'wp-creatorreactor' ),
			]
		);

		$this->add_elementor_container_logic_control();

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$inner = isset( $s['inner_content'] ) ? $s['inner_content'] : '';
		$tag   = $this->shortcode_tag();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode/HTML output (same as post content).
		echo Shortcodes::apply_enclosing_gate( $tag, $inner );
	}

	/**
	 * Must stay public: {@see \Elementor\Element_Base::before_render()} is public; a protected override triggers E_COMPILE_ERROR.
	 */
	public function before_render() {
		parent::before_render();
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Follower_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_follower';
	}

	public function get_title() {
		return __( 'CreatorReactor: Follower', 'wp-creatorreactor' );
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
final class Elementor_Widget_Subscriber_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_subscriber';
	}

	public function get_title() {
		return __( 'CreatorReactor: Subscriber', 'wp-creatorreactor' );
	}

	public function get_keywords() {
		return [ 'creatorreactor', 'subscriber', 'fanvue', 'tier', 'entitlement' ];
	}

	protected function shortcode_tag(): string {
		return 'subscriber';
	}
}

if ( class_exists( '\Elementor\Modules\NestedElements\Base\Widget_Nested_Base' ) ) {

	/**
	 * One nested container slot; shared by shortcode gates and has_tier.
	 *
	 * @package CreatorReactor
	 */
	abstract class Elementor_Widget_Nested_Single_Slot_Base extends \Elementor\Modules\NestedElements\Base\Widget_Nested_Base {

		public function get_categories() {
			return [ 'creatorreactor' ];
		}

		public function get_icon() {
			return 'eicon-inner-section';
		}

		public function show_in_panel(): bool {
			if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
				return true;
			}
			return \Elementor\Plugin::$instance->experiments->is_feature_active( 'nested-elements' );
		}

		protected function get_default_children_elements() {
			return [
				[
					'elType'   => 'container',
					'settings' => [
						'_title'        => __( 'Gated content', 'wp-creatorreactor' ),
						'content_width' => 'full',
					],
				],
			];
		}

		protected function get_default_repeater_title_setting_key() {
			return 'slot_title';
		}

		protected function get_default_children_title() {
			/* translators: %d: Content area index (1-based). */
			return __( 'Content #%d', 'wp-creatorreactor' );
		}

		protected function get_default_children_container_placeholder_selector() {
			return '.creatorreactor-nested-gate__slot';
		}

		protected function register_gate_slot_controls() {
			$this->start_controls_section(
				'section_content',
				[
					'label' => __( 'Content', 'wp-creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$repeater = new \Elementor\Repeater();
			$repeater->add_control(
				'slot_title',
				[
					'label'       => __( 'Area label', 'wp-creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => __( 'Gated content', 'wp-creatorreactor' ),
					'label_block' => true,
				]
			);

			$this->add_control(
				'gate_slots',
				[
					'label'       => __( 'Gated content area', 'wp-creatorreactor' ),
					'type'        => \Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater::CONTROL_TYPE,
					'fields'      => $repeater->get_controls(),
					'default'     => [
						[
							'slot_title' => __( 'Gated content', 'wp-creatorreactor' ),
						],
					],
					'title_field' => '{{{ slot_title }}}',
					'button_text' => __( 'Add area', 'wp-creatorreactor' ),
					'min_items'   => 1,
					'max_items'   => 1,
				]
			);

			$this->add_control(
				'gate_hint',
				[
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Add widgets inside this gate widget (nested content area). Images or other widgets placed as separate elements below the gate are not wrapped by the shortcode; move them into this area so they are omitted from the HTML when the visitor does not match. Front-end output matches the matching shortcode.', 'wp-creatorreactor' ) . '</p>',
				]
			);

			$this->end_controls_section();
		}

		protected function render_inner_html(): string {
			ob_start();
			foreach ( $this->get_children() as $index => $_child ) {
				$this->print_child( (int) $index );
			}
			$inner = ob_get_clean();
			if ( '' === trim( (string) $inner ) ) {
				$s = $this->get_settings_for_display();
				if ( ! empty( $s['inner_content'] ) ) {
					$inner = (string) $s['inner_content'];
				}
			}
			return $inner;
		}

		protected function content_template() {
			?>
			<div class="creatorreactor-nested-gate">
				<# if ( settings.gate_slots ) { #>
					<# _.each( settings.gate_slots, function() { #>
						<div class="creatorreactor-nested-gate__slot"></div>
					<# } ); #>
				<# } #>
			</div>
			<?php
		}
	}

	/**
	 * Nested gate: wraps {@see render_inner_html()} in a paired shortcode.
	 *
	 * @package CreatorReactor
	 */
	abstract class Elementor_Widget_Nested_Shortcode_Gate_Base extends Elementor_Widget_Nested_Single_Slot_Base {

		use Elementor_Widget_Container_Logic_Control_Trait;

		abstract protected function shortcode_tag(): string;

		protected function register_controls() {
			$this->register_gate_slot_controls();

			$this->start_controls_section(
				'section_container_logic',
				[
					'label' => __( 'Container visibility', 'wp-creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_elementor_container_logic_control();

			$this->end_controls_section();
		}

		protected function render() {
			$tag   = $this->shortcode_tag();
			$inner = $this->render_inner_html();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode/HTML output (same as post content).
			echo Shortcodes::apply_enclosing_gate( $tag, $inner );
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Follower_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_follower';
		}

		public function get_title() {
			return __( 'CreatorReactor: Follower', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'follower', 'fanvue', 'tier', 'entitlement', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'follower';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Subscriber_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_subscriber';
		}

		public function get_title() {
			return __( 'CreatorReactor: Subscriber', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'subscriber', 'fanvue', 'tier', 'entitlement', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'subscriber';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Logged_Out_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_logged_out';
		}

		public function get_title() {
			return __( 'CreatorReactor: Logged out', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'guest', 'visitor', 'logged out', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'logged_out';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Logged_In_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_logged_in';
		}

		public function get_title() {
			return __( 'CreatorReactor: Logged in', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'member', 'logged in', 'user', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'logged_in';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Fanvue_Connected_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_fanvue_connected';
		}

		public function get_title() {
			return __( 'CreatorReactor: Fanvue connected', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'fanvue', 'connected', 'oauth', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'fanvue_connected';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Fanvue_Not_Connected_Nested extends Elementor_Widget_Nested_Shortcode_Gate_Base {

		public function get_name() {
			return 'creatorreactor_fanvue_not_connected';
		}

		public function get_title() {
			return __( 'CreatorReactor: Fanvue not connected', 'wp-creatorreactor' );
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'fanvue', 'not connected', 'oauth', 'container', 'nested' ];
		}

		protected function shortcode_tag(): string {
			return 'fanvue_not_connected';
		}
	}

	/**
	 * @package CreatorReactor
	 */
	final class Elementor_Widget_Has_Tier_Nested extends Elementor_Widget_Nested_Single_Slot_Base {

		use Elementor_Widget_Container_Logic_Control_Trait;

		public function get_name() {
			return 'creatorreactor_has_tier';
		}

		public function get_title() {
			return __( 'CreatorReactor: Has tier', 'wp-creatorreactor' );
		}

		public function get_icon() {
			return 'eicon-lock-user';
		}

		public function get_keywords() {
			return [ 'creatorreactor', 'tier', 'subscriber', 'entitlement', 'container', 'nested' ];
		}

		protected function register_controls() {
			$this->start_controls_section(
				'section_tier',
				[
					'label' => __( 'Tier conditions', 'wp-creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_control(
				'tier',
				[
					'label'       => __( 'Tier', 'wp-creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'premium', 'wp-creatorreactor' ),
					'description' => __( 'Leave empty to match any active tier.', 'wp-creatorreactor' ),
				]
			);

			$this->add_control(
				'product',
				[
					'label'       => __( 'Product', 'wp-creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'fanvue', 'wp-creatorreactor' ),
					'description' => __( 'Leave empty to match across products.', 'wp-creatorreactor' ),
				]
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'section_container_logic',
				[
					'label' => __( 'Container visibility', 'wp-creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_elementor_container_logic_control();

			$this->end_controls_section();

			$this->register_gate_slot_controls();
		}

		protected function render() {
			$s       = $this->get_settings_for_display();
			$inner   = $this->render_inner_html();
			$tier    = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
			$product = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcodes::has_tier returns kses-filtered HTML (post-content parity).
			echo Shortcodes::has_tier(
				[
					'tier'    => $tier,
					'product' => $product,
				],
				$inner
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Logged_Out_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_logged_out';
	}

	public function get_title() {
		return __( 'CreatorReactor: Logged out', 'wp-creatorreactor' );
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
final class Elementor_Widget_Logged_In_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_logged_in';
	}

	public function get_title() {
		return __( 'CreatorReactor: Logged in', 'wp-creatorreactor' );
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
final class Elementor_Widget_Has_Tier_Legacy extends \Elementor\Widget_Base {

	use Elementor_Widget_Container_Logic_Control_Trait;

	public function get_name() {
		return 'creatorreactor_has_tier';
	}

	public function get_title() {
		return __( 'CreatorReactor: Has tier', 'wp-creatorreactor' );
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
				'label' => __( 'Tier conditions', 'wp-creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'tier',
			[
				'label'       => __( 'Tier', 'wp-creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'premium', 'wp-creatorreactor' ),
				'description' => __( 'Leave empty to match any active tier.', 'wp-creatorreactor' ),
			]
		);

		$this->add_control(
			'product',
			[
				'label'       => __( 'Product', 'wp-creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'fanvue', 'wp-creatorreactor' ),
				'description' => __( 'Leave empty to match across products.', 'wp-creatorreactor' ),
			]
		);

		$this->add_control(
			'inner_content',
			[
				'label'       => __( 'Visible content', 'wp-creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::WYSIWYG,
				'default'     => '',
				'description' => __( 'Shown only when the visitor matches these tier conditions.', 'wp-creatorreactor' ),
			]
		);

		$this->add_elementor_container_logic_control();

		$this->end_controls_section();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$inner    = isset( $s['inner_content'] ) ? (string) $s['inner_content'] : '';
		$tier     = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
		$product  = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcodes::has_tier returns kses-filtered HTML (post-content parity).
		echo Shortcodes::has_tier(
			[
				'tier'    => $tier,
				'product' => $product,
			],
			$inner
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Fanvue_Connected_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_fanvue_connected';
	}

	public function get_title() {
		return __( 'CreatorReactor: Fanvue connected', 'wp-creatorreactor' );
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
final class Elementor_Widget_Fanvue_Not_Connected_Legacy extends Elementor_Widget_Shortcode_Wrap {

	public function get_name() {
		return 'creatorreactor_fanvue_not_connected';
	}

	public function get_title() {
		return __( 'CreatorReactor: Fanvue not connected', 'wp-creatorreactor' );
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
		return __( 'CreatorReactor: Login with Fanvue', 'wp-creatorreactor' );
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
				'label' => __( 'About', 'wp-creatorreactor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'note',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Outputs the “Login with Fanvue” control using the style from Settings → Fanvue → Login Button Appearance (same as [fanvue_login_button]; Creator/direct mode only). For Google sign-in, use the Shortcode widget with [standard_google_login_button] or [minimal_google_login_button]. Instagram (Meta) OAuth credentials live under Settings → Instagram (OAuth Configuration); Login Button Appearance there stores a gradient preview style for future use—there is no Instagram login widget yet.', 'wp-creatorreactor' ) . '</p>',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo Shortcodes::fanvue_oauth( [] );
	}
}
