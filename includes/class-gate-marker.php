<?php
/**
 * HTML markers for client/server gated layout (Gutenberg blocks).
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gate_Marker {

	public static function gutenberg_marker_class(): string {
		return 'creatorreactor-gutenberg-gate-marker';
	}

	/**
	 * Hidden span emitted inside gated blocks for inheritance + stripping logic.
	 *
	 * @param string $gate      Gate slug (e.g. follower).
	 * @param bool   $matched   Whether the current visitor matches.
	 * @param string $logic     and|or container logic.
	 * @param string $roles_csv Effective roles for the current viewer.
	 */
	public static function render_gutenberg_span( string $gate, bool $matched, string $logic, string $roles_csv ): string {
		$match_str = $matched ? '1' : '0';
		$logic     = $logic === 'or' ? 'or' : 'and';

		return '<span class="' . esc_attr( self::gutenberg_marker_class() ) . '"'
			. ' data-creatorreactor-gate="' . esc_attr( $gate ) . '"'
			. ' data-creatorreactor-gate-match="' . esc_attr( $match_str ) . '"'
			. ' data-creatorreactor-gate-logic="' . esc_attr( $logic ) . '"'
			. ' data-creatorreactor-user-roles="' . esc_attr( $roles_csv ) . '"'
			. ' style="display:none" aria-hidden="true"></span>';
	}
}
