<?php
/**
 * Single source of truth for Elementor gate widget names ↔ gate slugs (PHP + localized JS).
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
 * Gate metadata shared by Elementor wrapper injection and front-end inheritance script.
 */
final class Gate_Registry {

	/**
	 * Widgets that wrap {@see Shortcodes::apply_enclosing_gate()} (excludes has_tier).
	 *
	 * @var array<string, string>
	 */
	private const ELEMENTOR_WIDGET_SHORTCODE_GATE = [
		'creatorreactor_follower'             => 'follower',
		'creatorreactor_subscriber'         => 'subscriber',
		'creatorreactor_logged_out'         => 'logged_out',
		'creatorreactor_logged_in'          => 'logged_in',
		'creatorreactor_fanvue_connected'   => 'fanvue_connected',
		'creatorreactor_fanvue_not_connected' => 'fanvue_not_connected',
	];

	/**
	 * Map for client-side resolution (includes has_tier; excludes login-only widgets).
	 *
	 * @return array<string, string>
	 */
	public static function elementor_widget_name_to_gate_slug_for_js(): array {
		return array_merge(
			self::ELEMENTOR_WIDGET_SHORTCODE_GATE,
			[ 'creatorreactor_has_tier' => 'has_tier' ]
		);
	}

	/**
	 * Shortcode tag for enclosing gate probe (null when the widget is not a shortcode gate).
	 */
	public static function elementor_widget_shortcode_gate_slug( string $widget_name ): ?string {
		return self::ELEMENTOR_WIDGET_SHORTCODE_GATE[ $widget_name ] ?? null;
	}
}
