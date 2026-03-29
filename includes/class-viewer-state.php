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
	 * Whether the front-end admin bar would show after `show_admin_bar` filters (not {@see is_admin_bar_showing()},
	 * which is always true in wp-admin).
	 *
	 * @return bool
	 */
	public static function expected_show_admin_bar_on_front(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$base = function_exists( '_get_admin_bar_pref' ) ? _get_admin_bar_pref() : true;
		return (bool) apply_filters( 'show_admin_bar', $base );
	}

	/**
	 * Whether CreatorReactor would redirect the current user away from wp-admin (fan roles, restriction enabled).
	 *
	 * @return bool
	 */
	public static function creatorreactor_redirect_blocks_current_user_from_wp_admin(): bool {
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! Admin_Settings::is_restrict_creatorreactor_users_wp_admin_enabled() ) {
			return false;
		}
		return Admin_Settings::user_has_creatorreactor_role();
	}

	/**
	 * Server-side viewer flags for gate inheritance scripts and AJAX refresh.
	 *
	 * `can_access_wp_admin` uses {@see current_user_can( 'edit_posts' )} (Authors and up can use Posts in wp-admin).
	 * `skip_client_gate_hiding` is true for non-Administrator staff (toolbar + {@see current_user_can( 'edit_posts' )})
	 * so Authors/Editors can preview layout without client-side hiding. Users with {@see current_user_can( 'manage_options' )}
	 * never skip: they see real gate behavior until they use role impersonation.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_payload(): array {
		$eff_in = Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates();
		$roles  = [];
		if ( $eff_in ) {
			$user = wp_get_current_user();
			if ( $user && $user->ID ) {
				$roles = Role_Impersonation::get_effective_role_slugs_for_user( $user );
			}
		}

		$wp_logged_in          = is_user_logged_in();
		$admin_bar_showing     = $wp_logged_in && function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing();
		$can_access_wp_admin   = $wp_logged_in && current_user_can( 'edit_posts' );
		$impersonation_active  = false;
		if ( $wp_logged_in ) {
			$user = wp_get_current_user();
			if ( $user && $user->ID && Role_Impersonation::user_is_wp_administrator( $user ) ) {
				$imp                  = Role_Impersonation::get_valid_impersonation_role_for_user( $user, (int) $user->ID );
				$impersonation_active = ( null !== $imp );
			}
		}
		$is_administrator_cap = $wp_logged_in && current_user_can( 'manage_options' );
		$skip_client_gate_hiding = ! $impersonation_active && ! $is_administrator_cap && (
			$admin_bar_showing && $can_access_wp_admin
		);

		return [
			'logged_in'               => $eff_in,
			'roles'                   => $roles,
			'skip_client_gate_hiding' => $skip_client_gate_hiding,
			'admin_bar_showing'       => $admin_bar_showing,
			'can_access_wp_admin'     => $can_access_wp_admin,
		];
	}

	/**
	 * Inline bootstrap for `window.CreatorReactorViewerState` (camelCase keys).
	 *
	 * @return array<string, mixed>
	 */
	public static function bootstrap_for_inline_script(): array {
		$p = self::build_payload();

		return [
			'loggedIn'             => (bool) $p['logged_in'],
			'roles'                => $p['roles'],
			'skipClientGateHiding' => (bool) $p['skip_client_gate_hiding'],
			'adminBarShowing'      => (bool) $p['admin_bar_showing'],
			'canAccessWpAdmin'     => (bool) $p['can_access_wp_admin'],
		];
	}

	/**
	 * @return void
	 */
	public static function ajax_viewer_state() {
		wp_send_json_success( self::build_payload() );
	}
}

