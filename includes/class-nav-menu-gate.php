<?php
/**
 * Hides WordPress nav menu items for guests or wrong CreatorReactor tier when marked with CSS classes.
 *
 * Add classes under Appearance → Menus → Screen Options → CSS Classes:
 * - cr-nav-logged-in — visible only when effective visitor is logged in for gates
 * - cr-nav-follower — same visibility as [creatorreactor_follower] (follower role, not subscriber)
 * - cr-nav-subscriber — same visibility as [creatorreactor_subscriber] (subscriber role only)
 *
 * If multiple of these classes are present on one item, the item is shown when any predicate matches (OR).
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nav_Menu_Gate {

	public const CLASS_LOGGED_IN = 'cr-nav-logged-in';

	public const CLASS_SUBSCRIBER = 'cr-nav-subscriber';

	public const CLASS_FOLLOWER = 'cr-nav-follower';

	public static function init(): void {
		add_filter( 'wp_nav_menu_objects', [ __CLASS__, 'filter_nav_menu_objects' ], 20, 2 );
	}

	/**
	 * Whether a menu item with these CSS classes should appear for the current effective visitor.
	 *
	 * @param list<string> $classes                  Raw classes from `$item->classes` (sanitized here).
	 * @param bool         $effective_logged_in      From {@see Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates()}.
	 * @param bool         $has_subscriber_role      Effective user has `creatorreactor_subscriber`.
	 * @param bool         $has_follower_exclusive   Effective user has `creatorreactor_follower` and not subscriber (matches follower shortcode).
	 */
	public static function item_visible_for_creatorreactor_nav_classes(
		array $classes,
		bool $effective_logged_in,
		bool $has_subscriber_role,
		bool $has_follower_exclusive
	): bool {
		$classes = array_map( 'sanitize_html_class', $classes );

		$predicates = [];
		if ( in_array( self::CLASS_LOGGED_IN, $classes, true ) ) {
			$predicates[] = 'logged_in';
		}
		if ( in_array( self::CLASS_SUBSCRIBER, $classes, true ) ) {
			$predicates[] = 'subscriber';
		}
		if ( in_array( self::CLASS_FOLLOWER, $classes, true ) ) {
			$predicates[] = 'follower';
		}

		if ( $predicates === [] ) {
			return true;
		}

		foreach ( $predicates as $p ) {
			if ( $p === 'logged_in' && $effective_logged_in ) {
				return true;
			}
			if ( $p === 'subscriber' && $effective_logged_in && $has_subscriber_role ) {
				return true;
			}
			if ( $p === 'follower' && $effective_logged_in && $has_follower_exclusive ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int|string, \WP_Post|object> $items Menu items keyed by post ID.
	 * @param object|null                          $args  Nav menu args (stdClass from {@see wp_nav_menu()}).
	 * @return array<int|string, \WP_Post|object>
	 */
	public static function filter_nav_menu_objects( array $items, $args = null ): array {
		if ( $items === [] ) {
			return $items;
		}

		/**
		 * Disable CreatorReactor nav menu tier filtering.
		 *
		 * @param bool                                 $enabled Whether filtering runs.
		 * @param array<int|string, \WP_Post|object>   $items   Menu items.
		 * @param object|null                          $args    Nav menu args.
		 */
		if ( ! apply_filters( 'creatorreactor_nav_menu_gate_enabled', true, $items, $args ) ) {
			return $items;
		}

		$eff_in = Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates();
		$has_sub                = false;
		$has_follower_exclusive = false;
		if ( $eff_in ) {
			$user = wp_get_current_user();
			if ( $user instanceof \WP_User && $user->ID ) {
				$roles = Role_Impersonation::get_effective_role_slugs_for_user( $user );
				$has_sub = in_array( 'creatorreactor_subscriber', $roles, true );
				$has_fol = in_array( 'creatorreactor_follower', $roles, true );
				$has_follower_exclusive = $has_fol && ! $has_sub;
			}
		}

		foreach ( $items as $id => $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$classes = isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : [];

			if ( ! self::item_visible_for_creatorreactor_nav_classes( $classes, $eff_in, $has_sub, $has_follower_exclusive ) ) {
				unset( $items[ $id ] );
			}
		}

		return $items;
	}
}
