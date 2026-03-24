<?php
/**
 * Elementor integration loader (widgets load only after Elementor is ready).
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
 * Registers the CreatorReactor category and widgets with Elementor.
 */
final class Elementor_Integration {

	public static function init() {
		add_action( 'elementor/init', [ __CLASS__, 'boot' ], 5 );
	}

	public static function boot() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-elementor-widgets.php';

		$elements = \Elementor\Plugin::instance()->elements_manager;
		if ( method_exists( $elements, 'add_category' ) ) {
			$elements->add_category(
				'creatorreactor',
				[
					'title' => esc_html__( 'CreatorReactor', 'creatorreactor' ),
					'icon'  => 'eicon-shortcode',
				]
			);
		}

		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		$widgets = [
			new Elementor_Widget_Follower(),
			new Elementor_Widget_Subscriber(),
			new Elementor_Widget_Not_Logged_In(),
			new Elementor_Widget_Fanvue_Oauth(),
			new Elementor_Widget_Onboarding(),
		];

		foreach ( $widgets as $widget ) {
			if ( method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( $widget );
			} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
				$widgets_manager->register_widget_type( $widget );
			}
		}
	}
}
