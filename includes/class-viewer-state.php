<?php
/**
 * Lightweight runtime viewer state endpoint for front-end gate scripts.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Viewer_State {

	public static function init() {
		add_action( 'wp_ajax_creatorreactor_viewer_state', [ __CLASS__, 'ajax_viewer_state' ] );
		add_action( 'wp_ajax_nopriv_creatorreactor_viewer_state', [ __CLASS__, 'ajax_viewer_state' ] );
	}

	/**
	 * @return void
	 */
	public static function ajax_viewer_state() {
		$roles = [];
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
				$roles = array_values( array_map( 'sanitize_key', $user->roles ) );
			}
		}

		wp_send_json_success(
			[
				'logged_in' => is_user_logged_in(),
				'roles'     => $roles,
			]
		);
	}
}

