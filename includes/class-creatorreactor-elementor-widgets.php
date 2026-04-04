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

		$this->add_control(
			'container_logic',
			[
				'label'       => __( 'Container visibility logic', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'and',
				'options'     => [
					'and' => __( 'AND (current): hide container if any gate fails', 'creatorreactor' ),
					'or'  => __( 'OR: show container if any gate passes', 'creatorreactor' ),
				],
				'description' => __( 'Block editor: used when multiple gate blocks share a layout container. Elementor: each gate only affects its own widget on the front end.', 'creatorreactor' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$inner = isset( $s['inner_content'] ) ? $s['inner_content'] : '';
		$tag   = $this->shortcode_tag();
		$container_logic = isset( $s['container_logic'] ) ? sanitize_key( (string) $s['container_logic'] ) : 'and';
		if ( $container_logic !== 'and' && $container_logic !== 'or' ) {
			$container_logic = 'and';
		}
		$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();
		// Determine gate-match independently from the widget's enclosed content.
		// This ensures container inheritance works even if the widget's content is empty.
		$probe   = Shortcodes::apply_enclosing_gate( $tag, '__creatorreactor_gate_probe__' );
		$matched          = trim( (string) $probe ) !== '';
		$match_str        = $matched ? '1' : '0';

		echo '<span class="creatorreactor-elementor-gate-marker"'
			. ' data-creatorreactor-gate="' . esc_attr( $tag ) . '"'
			. ' data-creatorreactor-gate-match="' . esc_attr( $match_str ) . '"'
			. ' data-creatorreactor-gate-logic="' . esc_attr( $container_logic ) . '"'
			. ' data-creatorreactor-user-roles="' . esc_attr( $roles ) . '"'
			. ' style="display:none" aria-hidden="true"></span>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode/HTML output (same as post content).
		echo Shortcodes::apply_enclosing_gate( $tag, $inner );
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
final class Elementor_Widget_Subscriber_Legacy extends Elementor_Widget_Shortcode_Wrap {

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
						'_title'        => __( 'Gated content', 'creatorreactor' ),
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
			return __( 'Content #%d', 'creatorreactor' );
		}

		protected function get_default_children_container_placeholder_selector() {
			return '.creatorreactor-nested-gate__slot';
		}

		protected function register_gate_slot_controls() {
			$this->start_controls_section(
				'section_content',
				[
					'label' => __( 'Content', 'creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$repeater = new \Elementor\Repeater();
			$repeater->add_control(
				'slot_title',
				[
					'label'       => __( 'Area label', 'creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => __( 'Gated content', 'creatorreactor' ),
					'label_block' => true,
				]
			);

			$this->add_control(
				'gate_slots',
				[
					'label'       => __( 'Gated content area', 'creatorreactor' ),
					'type'        => \Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater::CONTROL_TYPE,
					'fields'      => $repeater->get_controls(),
					'default'     => [
						[
							'slot_title' => __( 'Gated content', 'creatorreactor' ),
						],
					],
					'title_field' => '{{{ slot_title }}}',
					'button_text' => __( 'Add area', 'creatorreactor' ),
					'min_items'   => 1,
					'max_items'   => 1,
				]
			);

			$this->add_control(
				'gate_hint',
				[
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Add widgets inside this gate widget (nested content area). Images or other widgets placed as separate elements below the gate are not wrapped by the shortcode; move them into this area so they are omitted from the HTML when the visitor does not match. Front-end output matches the matching shortcode.', 'creatorreactor' ) . '</p>',
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

		abstract protected function shortcode_tag(): string;

		protected function register_controls() {
			$this->register_gate_slot_controls();

			$this->start_controls_section(
				'section_container_logic',
				[
					'label' => __( 'Container visibility', 'creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_control(
				'container_logic',
				[
					'label'       => __( 'Container visibility logic', 'creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'and',
					'options'     => [
						'and' => __( 'AND (current): hide container if any gate fails', 'creatorreactor' ),
						'or'  => __( 'OR: show container if any gate passes', 'creatorreactor' ),
					],
					'description' => __( 'Block editor: used when multiple gate blocks share a layout container. Elementor: each gate only affects its own widget on the front end.', 'creatorreactor' ),
				]
			);

			$this->end_controls_section();
		}

		protected function render() {
			$s = $this->get_settings_for_display();
			$container_logic = isset( $s['container_logic'] ) ? sanitize_key( (string) $s['container_logic'] ) : 'and';
			if ( $container_logic !== 'and' && $container_logic !== 'or' ) {
				$container_logic = 'and';
			}
			$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();

			$tag   = $this->shortcode_tag();
			// Determine gate-match independently from the nested slot's enclosed content.
			// This ensures container inheritance works even if nested slot content is empty.
			$probe   = Shortcodes::apply_enclosing_gate( $tag, '__creatorreactor_gate_probe__' );
			$matched          = trim( (string) $probe ) !== '';
			$match_str        = $matched ? '1' : '0';

			echo '<span class="creatorreactor-elementor-gate-marker"'
				. ' data-creatorreactor-gate="' . esc_attr( $tag ) . '"'
				. ' data-creatorreactor-gate-match="' . esc_attr( $match_str ) . '"'
				. ' data-creatorreactor-gate-logic="' . esc_attr( $container_logic ) . '"'
				. ' data-creatorreactor-user-roles="' . esc_attr( $roles ) . '"'
				. ' style="display:none" aria-hidden="true"></span>';

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
			return __( 'CreatorReactor: Follower', 'creatorreactor' );
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
			return __( 'CreatorReactor: Subscriber', 'creatorreactor' );
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
			return __( 'CreatorReactor: Logged out', 'creatorreactor' );
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
			return __( 'CreatorReactor: Logged in', 'creatorreactor' );
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
			return __( 'CreatorReactor: Fanvue connected', 'creatorreactor' );
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
			return __( 'CreatorReactor: Fanvue not connected', 'creatorreactor' );
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

		public function get_name() {
			return 'creatorreactor_has_tier';
		}

		public function get_title() {
			return __( 'CreatorReactor: Has tier', 'creatorreactor' );
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

			$this->end_controls_section();

			$this->start_controls_section(
				'section_container_logic',
				[
					'label' => __( 'Container visibility', 'creatorreactor' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_control(
				'container_logic',
				[
					'label'       => __( 'Container visibility logic', 'creatorreactor' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'and',
					'options'     => [
						'and' => __( 'AND (current): hide container if any gate fails', 'creatorreactor' ),
						'or'  => __( 'OR: show container if any gate passes', 'creatorreactor' ),
					],
					'description' => __( 'Block editor: used when multiple gate blocks share a layout container. Elementor: each gate only affects its own widget on the front end.', 'creatorreactor' ),
				]
			);

			$this->end_controls_section();

			$this->register_gate_slot_controls();
		}

		protected function render() {
			$s       = $this->get_settings_for_display();
			$inner   = $this->render_inner_html();
			$tier    = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
			$product = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';
			$container_logic = isset( $s['container_logic'] ) ? sanitize_key( (string) $s['container_logic'] ) : 'and';
			if ( $container_logic !== 'and' && $container_logic !== 'or' ) {
				$container_logic = 'and';
			}
			$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();
			// Determine match independently from the nested slot's enclosed content.
			// This ensures container inheritance works even if slot content is empty.
			$probe = Shortcodes::has_tier(
				[
					'tier'    => $tier,
					'product' => $product,
				],
				'__creatorreactor_gate_probe__'
			);

			$matched   = trim( (string) $probe ) !== '';
			$match_str = $matched ? '1' : '0';
			echo '<span class="creatorreactor-elementor-gate-marker"'
				. ' data-creatorreactor-gate="has_tier"'
				. ' data-creatorreactor-gate-match="' . esc_attr( $match_str ) . '"'
				. ' data-creatorreactor-gate-logic="' . esc_attr( $container_logic ) . '"'
				. ' data-creatorreactor-user-roles="' . esc_attr( $roles ) . '"'
				. ' style="display:none" aria-hidden="true"></span>';

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
}

/**
 * @package CreatorReactor
 */
final class Elementor_Widget_Logged_Out_Legacy extends Elementor_Widget_Shortcode_Wrap {

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
final class Elementor_Widget_Logged_In_Legacy extends Elementor_Widget_Shortcode_Wrap {

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
final class Elementor_Widget_Has_Tier_Legacy extends \Elementor\Widget_Base {

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

		$this->add_control(
			'container_logic',
			[
				'label'       => __( 'Container visibility logic', 'creatorreactor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'and',
				'options'     => [
					'and' => __( 'AND (current): hide container if any gate fails', 'creatorreactor' ),
					'or'  => __( 'OR: show container if any gate passes', 'creatorreactor' ),
				],
				'description' => __( 'Block editor: used when multiple gate blocks share a layout container. Elementor: each gate only affects its own widget on the front end.', 'creatorreactor' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$inner    = isset( $s['inner_content'] ) ? (string) $s['inner_content'] : '';
		$tier     = isset( $s['tier'] ) ? trim( sanitize_text_field( (string) $s['tier'] ) ) : '';
		$product  = isset( $s['product'] ) ? trim( sanitize_text_field( (string) $s['product'] ) ) : '';
		$container_logic = isset( $s['container_logic'] ) ? sanitize_key( (string) $s['container_logic'] ) : 'and';
		if ( $container_logic !== 'and' && $container_logic !== 'or' ) {
			$container_logic = 'and';
		}
		$roles = Role_Impersonation::get_effective_roles_csv_for_logged_in_user();

		// Determine match independently from the widget's enclosed content.
		// This ensures container inheritance works even if widget content is empty.
		$probe = Shortcodes::has_tier(
			[
				'tier'    => $tier,
				'product' => $product,
			],
			'__creatorreactor_gate_probe__'
		);

		$matched   = trim( (string) $probe ) !== '';
		$match_str = $matched ? '1' : '0';
		echo '<span class="creatorreactor-elementor-gate-marker"'
			. ' data-creatorreactor-gate="has_tier"'
			. ' data-creatorreactor-gate-match="' . esc_attr( $match_str ) . '"'
			. ' data-creatorreactor-gate-logic="' . esc_attr( $container_logic ) . '"'
			. ' data-creatorreactor-user-roles="' . esc_attr( $roles ) . '"'
			. ' style="display:none" aria-hidden="true"></span>';

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
final class Elementor_Widget_Fanvue_Connected_Legacy extends Elementor_Widget_Shortcode_Wrap {

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
final class Elementor_Widget_Fanvue_Not_Connected_Legacy extends Elementor_Widget_Shortcode_Wrap {

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
				'raw'  => '<p class="elementor-descriptor">' . esc_html__( 'Outputs the same “Login with Fanvue” control as [standard_fanvue_login_button] / [fanvue_login_button] (Creator/direct mode only). For Google sign-in, use the Shortcode widget with [standard_google_login_button] or [minimal_google_login_button].', 'creatorreactor' ) . '</p>',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from shortcode renderer.
		echo Shortcodes::fanvue_oauth( [] );
	}
}
