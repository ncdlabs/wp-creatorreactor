<?php
/**
 * Administrator-only preview: treat gated content as a chosen creatorreactor_* role.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Role_Impersonation {

	const COOKIE_NAME = 'creatorreactor_imp_role';

	/** Cookie lifetime in seconds (sliding refresh on each set). */
	const COOKIE_LIFETIME = 604800;

	/** Reject cookie values larger than this (base64 payload) to bound decode work. */
	const COOKIE_VALUE_MAX_BYTES = 512;

	/**
	 * Cookie / AJAX value: preview gates as a logged-out visitor (administrators only).
	 * Not a WordPress role; must not collide with {@see get_role()} slugs.
	 */
	const IMPERSONATION_LOGGED_OUT_SLUG = 'cr_imp_logged_out';

	public static function init() {
		// Authenticated AJAX only — never register wp_ajax_nopriv_* for this action.
		\add_action( 'wp_ajax_creatorreactor_impersonate_role', [ __CLASS__, 'ajax_impersonate_role' ] );
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ], 30 );
		\add_action( 'wp_logout', [ __CLASS__, 'on_wp_logout_clear_cookie' ], 1 );
	}

	/**
	 * Whether this user is a real WordPress `administrator` on the current site.
	 *
	 * Uses the authoritative capabilities usermeta for the current blog (same source as core cap checks),
	 * not only {@see \WP_User::$roles}, so filtered user objects cannot grant impersonation.
	 */
	public static function user_is_wp_administrator( \WP_User $user ): bool {
		$id = (int) $user->ID;
		if ( $id < 1 ) {
			return false;
		}
		return self::user_id_has_administrator_role_in_meta( $id );
	}

	/**
	 * @param int $user_id WordPress user ID.
	 */
	public static function user_id_has_administrator_role_in_meta( int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}
		global $wpdb;
		$key  = $wpdb->get_blog_prefix() . 'capabilities';
		$caps = \get_user_meta( $user_id, $key, true );
		if ( ! is_array( $caps ) ) {
			return false;
		}
		// Role membership is stored as role slug => true (same shape as core).
		return isset( $caps['administrator'] ) && (bool) $caps['administrator'];
	}

	/**
	 * Whether CreatorReactor gates should treat the visitor as logged in (WordPress session + not “logged out” preview).
	 */
	public static function effective_is_logged_in_for_creatorreactor_gates(): bool {
		if ( ! \is_user_logged_in() ) {
			return false;
		}
		$uid = (int) \get_current_user_id();
		if ( $uid < 1 ) {
			return false;
		}
		$user = \get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			// Tests may stub {@see get_userdata()} with a plain object; treat as “no logged-out preview”.
			return true;
		}
		$imp = self::get_valid_impersonation_role_for_user( $user, $uid );
		return $imp !== self::IMPERSONATION_LOGGED_OUT_SLUG;
	}

	/**
	 * Human-readable label for the impersonation widget (role slug or {@see IMPERSONATION_LOGGED_OUT_SLUG}).
	 */
	public static function display_label_for_impersonation_choice( string $slug ): string {
		$slug = \sanitize_key( $slug );
		if ( $slug === self::IMPERSONATION_LOGGED_OUT_SLUG ) {
			return __( 'Logged Out', 'wp-creatorreactor' );
		}
		$labels = self::widget_labels_for_creatorreactor_roles();
		if ( isset( $labels[ $slug ] ) ) {
			return $labels[ $slug ];
		}
		$role = \get_role( $slug );
		if ( $role && isset( $role->name ) ) {
			return \translate_user_role( $role->name );
		}
		return $slug;
	}

	/**
	 * Fixed widget labels for standard CreatorReactor roles (other creatorreactor_* fall back to core role name).
	 *
	 * @return array<string, string> slug => label
	 */
	public static function widget_labels_for_creatorreactor_roles(): array {
		return [
			'creatorreactor_follower'   => __( 'Follower', 'wp-creatorreactor' ),
			'creatorreactor_subscriber' => __( 'Subscriber', 'wp-creatorreactor' ),
			'creatorreactor_null'       => __( 'No Role', 'wp-creatorreactor' ),
		];
	}

	/**
	 * Registered roles whose slug starts with creatorreactor_, sorted by slug.
	 *
	 * @return array<string, string> slug => display name
	 */
	public static function get_all_creatorreactor_roles(): array {
		$wp_roles = \wp_roles();
		if ( ! $wp_roles || ! is_array( $wp_roles->roles ) ) {
			return [];
		}
		$out = [];
		foreach ( array_keys( $wp_roles->roles ) as $slug ) {
			$slug = \sanitize_key( (string) $slug );
			if ( strpos( $slug, 'creatorreactor_' ) !== 0 ) {
				continue;
			}
			$role_obj = \get_role( $slug );
			if ( ! $role_obj || ! isset( $role_obj->name ) ) {
				continue;
			}
			$out[ $slug ] = \translate_user_role( $role_obj->name );
		}
		ksort( $out, SORT_STRING );
		return $out;
	}

	/**
	 * Role slugs used for CreatorReactor gates when impersonation is active; otherwise real user roles.
	 *
	 * @param \WP_User|object $user User object with {@see \WP_User::$roles} shape; must be {@see \WP_User} for admin impersonation.
	 * @return list<string>
	 */
	public static function get_effective_role_slugs_for_user( object $user ): array {
		$imp = null;
		if ( $user instanceof \WP_User && $user->ID ) {
			$imp = self::get_valid_impersonation_role_for_user( $user, (int) $user->ID );
		}
		if ( $imp === self::IMPERSONATION_LOGGED_OUT_SLUG ) {
			return [];
		}
		if ( $imp !== null ) {
			return [ $imp ];
		}
		$roles = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : [];
		return array_values( array_map( 'sanitize_key', $roles ) );
	}

	/**
	 * Comma-separated effective roles for gate markers (logged-in visitors only).
	 */
	public static function get_effective_roles_csv_for_logged_in_user(): string {
		if ( ! self::effective_is_logged_in_for_creatorreactor_gates() ) {
			return '';
		}
		$uid = (int) \get_current_user_id();
		if ( $uid < 1 ) {
			return '';
		}
		$user = \get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			return '';
		}
		return implode( ',', self::get_effective_role_slugs_for_user( $user ) );
	}

	/**
	 * @param int|null $verified_user_id If set, must match {@see \WP_User::$ID} (avoids trusting object ID alone).
	 * @return string|null Impersonated role slug, {@see IMPERSONATION_LOGGED_OUT_SLUG}, or null.
	 */
	public static function get_valid_impersonation_role_for_user( \WP_User $user, ?int $verified_user_id = null ): ?string {
		$uid = (int) $user->ID;
		if ( $uid < 1 ) {
			return null;
		}
		if ( $verified_user_id !== null && (int) $verified_user_id !== $uid ) {
			return null;
		}
		if ( ! self::user_id_has_administrator_role_in_meta( $uid ) ) {
			return null;
		}
		$parsed = self::parse_cookie();
		if ( $parsed === null ) {
			return null;
		}
		if ( (int) $parsed['user_id'] !== $uid ) {
			return null;
		}
		$role = \sanitize_key( (string) $parsed['role'] );
		if ( ! self::is_allowed_impersonation_role( $role ) ) {
			return null;
		}
		return $role;
	}

	/**
	 * @return void
	 */
	public static function ajax_impersonate_role() {
		if ( ! \wp_doing_ajax() ) {
			\wp_die( '', '', [ 'response' => 403 ] );
		}
		\check_ajax_referer( 'creatorreactor_role_impersonate', 'nonce' );
		if ( ! \is_user_logged_in() ) {
			\wp_send_json_error( [ 'message' => __( 'Not logged in.', 'wp-creatorreactor' ) ], 403 );
		}
		$user = \wp_get_current_user();
		$uid  = (int) ( $user ? $user->ID : 0 );
		if ( $uid < 1 || ! self::user_id_has_administrator_role_in_meta( $uid ) ) {
			\wp_send_json_error( [ 'message' => __( 'Forbidden.', 'wp-creatorreactor' ) ], 403 );
		}
		$role = isset( $_POST['role'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['role'] ) ) : '';
		if ( $role === '' ) {
			self::clear_impersonation_cookie();
			\wp_send_json_success(
				[
					'impersonating' => false,
					'role'          => '',
				]
			);
		}
		if ( ! self::is_allowed_impersonation_role( $role ) ) {
			\wp_send_json_error( [ 'message' => __( 'Invalid role.', 'wp-creatorreactor' ) ], 400 );
		}
		self::set_impersonation_cookie( $uid, $role );
		\wp_send_json_success(
			[
				'impersonating' => true,
				'role'          => $role,
			]
		);
	}

	/**
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( \is_admin() ) {
			return;
		}
		// Elementor live preview/editor canvas requests use a special front-end shape
		// (e.g. `?elementor-preview=...`) and must not show this UI panel.
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::is_elementor_preview_request() ) {
			return;
		}
		if ( ! \is_user_logged_in() ) {
			return;
		}
		$user = \wp_get_current_user();
		$uid  = (int) ( $user ? $user->ID : 0 );
		if ( $uid < 1 || ! self::user_id_has_administrator_role_in_meta( $uid ) ) {
			return;
		}
		$version = defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0';
		$handle  = 'creatorreactor-role-impersonation';

		\wp_enqueue_style(
			$handle,
			CREATORREACTOR_PLUGIN_URL . 'assets/css/creatorreactor-role-impersonation.css',
			[],
			$version
		);

		\wp_enqueue_script(
			$handle,
			CREATORREACTOR_PLUGIN_URL . 'assets/js/creatorreactor-role-impersonation.js',
			[],
			$version,
			true
		);

		$roles_map = self::get_all_creatorreactor_roles();
		$roles_ui  = [];
		foreach ( array_keys( $roles_map ) as $slug ) {
			$roles_ui[] = [
				'slug'  => $slug,
				'label' => self::display_label_for_impersonation_choice( $slug ),
			];
		}
		$roles_ui[] = [
			'slug'  => self::IMPERSONATION_LOGGED_OUT_SLUG,
			'label' => self::display_label_for_impersonation_choice( self::IMPERSONATION_LOGGED_OUT_SLUG ),
		];

		$current = self::get_valid_impersonation_role_for_user( $user, $uid );

		\wp_localize_script(
			$handle,
			'CreatorReactorRoleImpersonation',
			[
				'ajaxUrl'       => \admin_url( 'admin-ajax.php' ),
				'nonce'         => \wp_create_nonce( 'creatorreactor_role_impersonate' ),
				'loggedOutSlug' => self::IMPERSONATION_LOGGED_OUT_SLUG,
				'roles'         => $roles_ui,
				'current'       => $current,
				'currentLabel'  => ( $current !== null && $current !== '' ) ? self::display_label_for_impersonation_choice( $current ) : '',
				'i18n'         => [
					'title'             => __( 'Impersonate Role', 'wp-creatorreactor' ),
					'selectPlaceholder' => __( 'Select role…', 'wp-creatorreactor' ),
					'impersonate'       => __( 'Impersonate', 'wp-creatorreactor' ),
					'stop'              => __( 'Stop impersonating', 'wp-creatorreactor' ),
					'viewingAs'         => __( 'Viewing as:', 'wp-creatorreactor' ),
					'error'             => __( 'Something went wrong.', 'wp-creatorreactor' ),
				],
			]
		);
	}

	/**
	 * Expire the impersonation cookie on logout so shared browsers do not keep a stale token.
	 *
	 * @return void
	 */
	public static function on_wp_logout_clear_cookie() {
		self::clear_impersonation_cookie();
	}

	/**
	 * Derive HMAC key from multiple salts so a single leaked constant does not forge cookies.
	 */
	private static function hmac_key_material(): string {
		return \wp_salt( 'logged_in' ) . \wp_salt( 'auth' ) . '|creatorreactor_role_imp_v2';
	}

	private static function is_allowed_impersonation_role( string $role ): bool {
		if ( $role === '' ) {
			return false;
		}
		if ( $role === self::IMPERSONATION_LOGGED_OUT_SLUG ) {
			return true;
		}
		if ( strpos( $role, 'creatorreactor_' ) !== 0 ) {
			return false;
		}
		return (bool) \get_role( $role );
	}

	/**
	 * @return array{user_id: int, role: string, exp: int}|null
	 */
	private static function parse_cookie(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_string( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}
		$raw = \wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		if ( strlen( $raw ) > self::COOKIE_VALUE_MAX_BYTES ) {
			return null;
		}
		$bin = base64_decode( $raw, true );
		if ( $bin === false || $bin === '' ) {
			return null;
		}
		$parts = explode( '|', $bin, 4 );
		if ( count( $parts ) !== 4 ) {
			return null;
		}
		$uid = (int) $parts[0];
		$role = \sanitize_key( (string) $parts[1] );
		$exp  = (int) $parts[2];
		$sig  = (string) $parts[3];
		if ( $uid < 1 || $role === '' || $exp < 1 || $sig === '' ) {
			return null;
		}
		$payload = $uid . '|' . $role . '|' . $exp;
		$expect  = hash_hmac( 'sha256', $payload, self::hmac_key_material() );
		if ( ! hash_equals( $expect, $sig ) ) {
			return null;
		}
		if ( $exp < time() ) {
			return null;
		}
		return [
			'user_id' => $uid,
			'role'    => $role,
			'exp'     => $exp,
		];
	}

	private static function set_impersonation_cookie( int $user_id, string $role ): void {
		$exp     = time() + self::COOKIE_LIFETIME;
		$payload = $user_id . '|' . $role . '|' . $exp;
		$sig     = hash_hmac( 'sha256', $payload, self::hmac_key_material() );
		$value   = base64_encode( $payload . '|' . $sig );
		self::send_cookie( $value, $exp );
	}

	private static function clear_impersonation_cookie(): void {
		self::send_cookie( 'deleted', time() - YEAR_IN_SECONDS );
	}

	/**
	 * @param string $value Cookie value.
	 * @param int    $expires Unix timestamp.
	 */
	private static function send_cookie( string $value, int $expires ): void {
		$path   = defined( 'COOKIEPATH' ) && is_string( COOKIEPATH ) ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';
		\setcookie(
			self::COOKIE_NAME,
			$value === 'deleted' ? '' : $value,
			[
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => \is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}
}
