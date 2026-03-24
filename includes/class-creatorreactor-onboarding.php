<?php
/**
 * First-time social (Fanvue) OAuth onboarding: form, rewrite route, user meta, ToS modal.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Onboarding {

	const QUERY_VAR = 'creatorreactor_onboarding';

	const META_COMPLETE         = 'creatorreactor_onboarding_complete';
	const META_PHONE            = 'creatorreactor_phone';
	const META_ADDRESS          = 'creatorreactor_address';
	const META_COUNTRY          = 'creatorreactor_country';
	const META_CONTACT_PREF     = 'creatorreactor_contact_preference';
	const META_OPT_OUT_EMAILS   = 'creatorreactor_opt_out_emails';
	/** String `1` when the user has acknowledged ToS (idempotent; do not clear without legal review). */
	const META_TOS_ACCEPTED     = 'creatorreactor_tos_accepted';
	const META_TOS_ACCEPTED_AT  = 'creatorreactor_tos_accepted_at';
	/** Set after a successful Fanvue fan OAuth callback (first or repeat). Used to scope onboarding to social sign-in only. */
	const META_FANVUE_OAUTH_LINKED = 'creatorreactor_fanvue_oauth_linked';
	/** JSON: last Fanvue /me (or wrapped) payload from visitor OAuth, for linkage and diagnostics. */
	const META_FANVUE_PROFILE_SNAPSHOT = 'creatorreactor_fanvue_profile_snapshot';
	const ACTION_SUBMIT         = 'creatorreactor_onboarding_submit';
	/** Query arg: first-time Fanvue visitor completes onboarding before WP user is created. */
	const QUERY_FAN_PENDING = 'cr_fan_pending';
	/** Server-side session for pre-account onboarding (Fanvue identity + redirect target). */
	const FAN_PENDING_TTL = 1800;
	/** HttpOnly cookie backup when hosts strip query args on redirect from Fanvue OAuth. */
	const COOKIE_FAN_PENDING = 'creatorreactor_fp';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_rewrite' ], 5 );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_var' ] );
		add_action( 'init', [ __CLASS__, 'maybe_flush_onboarding_rewrites' ], 99 );
		add_action( 'template_redirect', [ __CLASS__, 'template_redirect' ], 5 );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, [ __CLASS__, 'handle_submit_nopriv' ] );
		add_shortcode( 'creatorreactor_onboarding', [ __CLASS__, 'shortcode' ] );
	}

	public static function activate_flush_rewrite_rules() {
		self::register_rewrite();
		flush_rewrite_rules( false );
		update_option( 'creatorreactor_ob_rewrite_ver', '3', false );
	}

	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::QUERY_FAN_PENDING;
		return $vars;
	}

	public static function register_rewrite() {
		add_rewrite_rule(
			'^creatorreactor-onboarding/p/([A-Za-z0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::QUERY_FAN_PENDING . '=$matches[1]',
			'top'
		);
		add_rewrite_rule( '^creatorreactor-onboarding/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * One-time flush after adding /creatorreactor-onboarding/p/{token}/ rewrite.
	 */
	public static function maybe_flush_onboarding_rewrites() {
		if ( get_option( 'creatorreactor_ob_rewrite_ver' ) === '3' ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'creatorreactor_ob_rewrite_ver', '3', false );
	}

	/**
	 * Whether the current request is the dedicated onboarding URL/screen.
	 */
	public static function is_onboarding_screen() {
		if ( (int) get_query_var( self::QUERY_VAR ) === 1 ) {
			return true;
		}
		if ( isset( $_GET[ self::QUERY_VAR ] ) && (string) wp_unslash( $_GET[ self::QUERY_VAR ] ) === '1' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		return self::is_onboarding_request_uri();
	}

	/**
	 * Path part of REQUEST_URI relative to home URL (leading slash).
	 */
	private static function request_path_relative_to_home() {
		if ( empty( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return '';
		}
		$uri_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_string( $uri_path ) ) {
			return '';
		}
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? untrailingslashit( $home_path ) : '';
		if ( $home_path !== '' && $home_path !== '/' && strpos( $uri_path, $home_path ) === 0 ) {
			$rel = (string) substr( $uri_path, strlen( $home_path ) );
		} else {
			$rel = $uri_path;
		}
		return '/' . ltrim( $rel, '/' );
	}

	/**
	 * True when the URL path is /creatorreactor-onboarding or /creatorreactor-onboarding/p/{token} (rewrites may be missing).
	 */
	private static function is_onboarding_request_uri() {
		$rel = self::request_path_relative_to_home();
		if ( $rel === '' || $rel === '/' ) {
			return false;
		}
		return (bool) preg_match( '#^/creatorreactor-onboarding(?:/p/[A-Za-z0-9]+)?/?$#', $rel );
	}

	/**
	 * Pending token parsed from pretty path when query vars were not set by rewrite rules.
	 */
	private static function pending_token_from_request_uri() {
		$rel = self::request_path_relative_to_home();
		if ( preg_match( '#/creatorreactor-onboarding/p/([A-Za-z0-9]+)/?$#', $rel, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Public onboarding URL (pretty permalinks when configured, else query arg).
	 *
	 * @param string $redirect_to After completion or skip target (validated on use).
	 */
	public static function get_onboarding_url( $redirect_to = '' ) {
		// Query form always hits index.php — pretty /creatorreactor-onboarding/ often 404s when rewrites are blocked or misconfigured.
		$url = add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
		$redirect_to = is_string( $redirect_to ) ? trim( $redirect_to ) : '';
		if ( $redirect_to !== '' ) {
			$url = add_query_arg( 'redirect_to', wp_validate_redirect( $redirect_to, home_url( '/' ) ), $url );
		}
		return $url;
	}

	/**
	 * Onboarding URL with a pending Fanvue registration token (user not logged in yet).
	 *
	 * @param string $token       Opaque token from {@see self::store_pending_fanvue_registration()}.
	 * @param string $redirect_to Validated post-onboarding destination.
	 */
	public static function get_onboarding_url_with_pending( $token, $redirect_to = '' ) {
		$token = is_string( $token ) ? preg_replace( '/[^a-zA-Z0-9]/', '', $token ) : '';
		if ( $token === '' ) {
			return self::get_onboarding_url( $redirect_to );
		}
		$redirect_to = is_string( $redirect_to ) ? trim( $redirect_to ) : '';
		$r_val       = $redirect_to !== '' ? wp_validate_redirect( $redirect_to, home_url( '/' ) ) : '';
		$url         = add_query_arg(
			[
				self::QUERY_VAR         => '1',
				self::QUERY_FAN_PENDING => $token,
			],
			home_url( '/' )
		);
		if ( $r_val !== '' ) {
			$url = add_query_arg( 'redirect_to', $r_val, $url );
		}
		return $url;
	}

	/**
	 * Pending token from path/query or cookie (set on Fanvue OAuth redirect).
	 */
	public static function get_request_pending_token() {
		$p = get_query_var( self::QUERY_FAN_PENDING, '' );
		if ( is_string( $p ) && $p !== '' ) {
			return preg_replace( '/[^a-zA-Z0-9]/', '', $p );
		}
		if ( isset( $_GET[ self::QUERY_FAN_PENDING ] ) && is_string( $_GET[ self::QUERY_FAN_PENDING ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_GET[ self::QUERY_FAN_PENDING ] ) );
		}
		$from_uri = self::pending_token_from_request_uri();
		if ( $from_uri !== '' ) {
			return $from_uri;
		}
		if ( ! empty( $_COOKIE[ self::COOKIE_FAN_PENDING ] ) && is_string( $_COOKIE[ self::COOKIE_FAN_PENDING ] ) ) {
			return preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_COOKIE[ self::COOKIE_FAN_PENDING ] ) );
		}
		return '';
	}

	/**
	 * Set short-lived cookie so onboarding works if the redirect URL loses query args.
	 *
	 * @param string $token Alphanumeric pending token.
	 */
	public static function set_fan_pending_cookie( $token ) {
		$token = is_string( $token ) ? preg_replace( '/[^a-zA-Z0-9]/', '', $token ) : '';
		if ( $token === '' ) {
			return;
		}
		$exp  = time() + self::FAN_PENDING_TTL;
		// Path must be / so the cookie is sent to /?creatorreactor_onboarding=… (COOKIEPATH is often /wp-admin).
		$opts = [
			'expires'  => $exp,
			'path'     => '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE_FAN_PENDING, $token, $opts );
		} else {
			setcookie( self::COOKIE_FAN_PENDING, $token, $exp, '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	public static function clear_fan_pending_cookie() {
		$opts = [
			'expires'  => time() - 3600,
			'path'     => '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE_FAN_PENDING, '', $opts );
		} else {
			setcookie( self::COOKIE_FAN_PENDING, '', time() - 3600, '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	/**
	 * Save Fanvue identity after OAuth; visitor completes the onboarding form, then we create the WP user.
	 *
	 * @param array{email?: string, uuid?: string, display?: string}     $identity From Fanvue /me (may be empty if the API omits fields after a successful OAuth).
	 * @param string                                                       $redirect_to Where to send them after setup.
	 * @param array{access_token?: string, refresh_token?: string, expires_at?: int}|null $oauth_tokens Token row from Fanvue exchange (encrypted into payload).
	 * @param array<string, mixed>|null                                    $profile_raw Full profile JSON from Fanvue (stored as JSON string, size-capped).
	 * @return string Empty on failure, else opaque token for {@see self::QUERY_FAN_PENDING}.
	 */
	public static function store_pending_fanvue_registration( array $identity, $redirect_to, $oauth_tokens = null, $profile_raw = null ) {
		$email = isset( $identity['email'] ) ? sanitize_email( (string) $identity['email'] ) : '';
		if ( $email !== '' && ! is_email( $email ) ) {
			$email = '';
		}
		$uuid = isset( $identity['uuid'] ) && is_string( $identity['uuid'] ) ? sanitize_text_field( $identity['uuid'] ) : '';
		$token = wp_generate_password( 48, false, false );
		$payload = [
			'email'            => $email,
			'uuid'             => $uuid,
			'display'          => isset( $identity['display'] ) && is_string( $identity['display'] ) ? sanitize_text_field( $identity['display'] ) : '',
			'redirect_to'      => is_string( $redirect_to ) ? wp_validate_redirect( $redirect_to, home_url( '/' ) ) : home_url( '/' ),
			'fan_oauth_linked' => '1',
		];
		if ( is_array( $oauth_tokens ) ) {
			$sealed = CreatorReactor_OAuth::seal_fan_oauth_token_row( $oauth_tokens );
			if ( $sealed !== '' ) {
				$payload['fan_oauth_tokens_sealed'] = $sealed;
			}
		}
		if ( is_array( $profile_raw ) ) {
			$json = wp_json_encode( $profile_raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( is_string( $json ) && $json !== '' && strlen( $json ) <= 100000 ) {
				$payload['fanvue_profile_json'] = $json;
			}
		}
		$name = self::fan_pending_option_name( $token );
		if ( $name === '' ) {
			return '';
		}
		update_option(
			$name,
			[
				'exp'  => time() + self::FAN_PENDING_TTL,
				'data' => $payload,
			],
			false
		);
		return $token;
	}

	/**
	 * @param string $token Alphanumeric pending token.
	 * @return array<string, string>|null
	 */
	public static function get_pending_fanvue_registration( $token ) {
		$token = is_string( $token ) ? preg_replace( '/[^a-zA-Z0-9]/', '', $token ) : '';
		if ( $token === '' ) {
			return null;
		}
		$name = self::fan_pending_option_name( $token );
		if ( $name === '' ) {
			return null;
		}
		$row = get_option( $name, null );
		if ( ! is_array( $row ) || ! isset( $row['exp'], $row['data'] ) || ! is_array( $row['data'] ) ) {
			return null;
		}
		if ( (int) $row['exp'] < time() ) {
			delete_option( $name );
			return null;
		}
		return $row['data'];
	}

	/**
	 * @param string $token Alphanumeric pending token.
	 */
	public static function delete_pending_fanvue_registration( $token ) {
		$token = is_string( $token ) ? preg_replace( '/[^a-zA-Z0-9]/', '', $token ) : '';
		if ( $token === '' ) {
			return;
		}
		$name = self::fan_pending_option_name( $token );
		if ( $name !== '' ) {
			delete_option( $name );
		}
	}

	/**
	 * Nonce action for onboarding submit while registration is pending (not logged in).
	 */
	public static function nonce_action_pending( $token ) {
		return self::ACTION_SUBMIT . '_fanpend_' . $token;
	}

	/**
	 * DB option name for pending registration (autoload=off; survives non-shared object cache).
	 *
	 * @param string $token Alphanumeric pending token.
	 */
	private static function fan_pending_option_name( $token ) {
		if ( ! is_string( $token ) || $token === '' || ! preg_match( '/^[a-zA-Z0-9]+$/', $token ) ) {
			return '';
		}
		return 'creatorreactor_fv_pen_' . $token;
	}

	/**
	 * @return string
	 */
	private static function read_post_pending_token() {
		if ( ! isset( $_POST['creatorreactor_fan_pending'] ) || ! is_string( $_POST['creatorreactor_fan_pending'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		return preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_POST['creatorreactor_fan_pending'] ) );
	}

	/**
	 * After successful Fanvue OAuth: send new/incomplete users to onboarding first.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $redirect_to Intended destination after onboarding.
	 */
	public static function get_post_oauth_redirect( $user_id, $redirect_to ) {
		$redirect_to = is_string( $redirect_to ) ? wp_validate_redirect( $redirect_to, home_url( '/' ) ) : home_url( '/' );
		if ( self::user_needs_onboarding( (int) $user_id ) ) {
			return self::get_onboarding_url( $redirect_to );
		}
		return self::strip_onboarding_args_from_redirect_url( $redirect_to );
	}

	/**
	 * Remove onboarding query args so completed users are not sent back to the setup screen.
	 *
	 * @param string $url Validated absolute URL.
	 * @return string
	 */
	public static function strip_onboarding_args_from_redirect_url( $url ) {
		$url = is_string( $url ) ? $url : '';
		if ( $url === '' ) {
			return home_url( '/' );
		}
		return remove_query_arg( [ self::QUERY_VAR, self::QUERY_FAN_PENDING, 'cr_ob_err' ], $url );
	}

	/**
	 * @param int|null $user_id Default current user.
	 */
	public static function user_needs_onboarding( $user_id = null ) {
		$user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$complete = get_user_meta( $user_id, self::META_COMPLETE, true );
		if ( $complete === '1' || $complete === 1 || $complete === true ) {
			return false;
		}
		if ( is_string( $complete ) && trim( $complete ) === '1' ) {
			return false;
		}
		$linked = get_user_meta( $user_id, self::META_FANVUE_OAUTH_LINKED, true );
		if ( $linked !== '1' && $linked !== 1 ) {
			return (bool) apply_filters( 'creatorreactor_user_needs_onboarding', false, $user_id );
		}
		return (bool) apply_filters( 'creatorreactor_user_needs_onboarding', true, $user_id );
	}

	public static function enqueue_assets() {
		$ver = defined( 'CREATORREACTOR_VERSION' ) ? CREATORREACTOR_VERSION : '1.0.0';
		wp_enqueue_style(
			'creatorreactor-onboarding',
			CREATORREACTOR_PLUGIN_URL . 'css/creatorreactor-onboarding.css',
			[],
			$ver
		);
		wp_enqueue_script(
			'creatorreactor-onboarding',
			CREATORREACTOR_PLUGIN_URL . 'js/creatorreactor-onboarding.js',
			[],
			$ver,
			true
		);
		wp_localize_script(
			'creatorreactor-onboarding',
			'creatorreactorOnboarding',
			[
				'tosError' => __( 'You must agree to the Terms of Service to continue.', 'creatorreactor' ),
			]
		);
	}

	public static function template_redirect() {
		if ( ! self::is_onboarding_screen() ) {
			return;
		}
		if ( Admin_Settings::is_broker_mode() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( ! is_user_logged_in() ) {
			$pend = self::get_request_pending_token();
			if ( $pend !== '' && self::get_pending_fanvue_registration( $pend ) ) {
				nocache_headers();
				self::enqueue_assets();
				$tpl = CREATORREACTOR_PLUGIN_DIR . 'templates/onboarding-shell.php';
				if ( is_readable( $tpl ) ) {
					require $tpl;
				} else {
					status_header( 500 );
					echo esc_html__( 'Onboarding template is missing.', 'creatorreactor' );
				}
				exit;
			}
			if ( $pend !== '' ) {
				self::clear_fan_pending_cookie();
				wp_safe_redirect( wp_login_url( add_query_arg( 'creatorreactor_fanvue', 'pending_expired', home_url( '/' ) ) ) );
				exit;
			}
			wp_safe_redirect( wp_login_url( self::get_onboarding_url( self::get_redirect_to_from_request() ) ) );
			exit;
		}
		if ( ! self::user_needs_onboarding() ) {
			$dest = self::strip_onboarding_args_from_redirect_url( self::get_redirect_to_from_request() );
			wp_safe_redirect( wp_validate_redirect( $dest, home_url( '/' ) ) );
			exit;
		}
		nocache_headers();
		self::enqueue_assets();
		$tpl = CREATORREACTOR_PLUGIN_DIR . 'templates/onboarding-shell.php';
		if ( is_readable( $tpl ) ) {
			require $tpl;
		} else {
			status_header( 500 );
			echo esc_html__( 'Onboarding template is missing.', 'creatorreactor' );
		}
		exit;
	}

	/**
	 * @return string Validated redirect URL from query/body.
	 */
	public static function get_redirect_to_from_request() {
		$raw = '';
		if ( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wp_unslash( $_GET['redirect_to'] );
		}
		if ( $raw === '' && isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wp_unslash( $_POST['redirect_to'] );
		}
		$raw = is_string( $raw ) ? rawurldecode( trim( $raw ) ) : '';
		return $raw !== '' ? wp_validate_redirect( $raw, home_url( '/' ) ) : home_url( '/' );
	}

	public static function handle_submit_nopriv() {
		$pt = self::read_post_pending_token();
		if ( $pt !== '' ) {
			self::handle_submit_pending_fanvue( $pt );
			return;
		}
		wp_safe_redirect( wp_login_url( self::get_onboarding_url() ) );
		exit;
	}

	public static function handle_submit() {
		$pend = self::read_post_pending_token();
		if ( $pend !== '' ) {
			self::handle_submit_pending_fanvue( $pend );
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION_SUBMIT ) ) {
			wp_die( esc_html__( 'Invalid request.', 'creatorreactor' ), '', [ 'response' => 403 ] );
		}
		if ( ! is_user_logged_in() ) {
			self::handle_submit_nopriv();
		}
		$uid = get_current_user_id();
		if ( ! self::user_needs_onboarding( $uid ) ) {
			$dest = self::strip_onboarding_args_from_redirect_url( self::get_redirect_to_from_request() );
			wp_safe_redirect( wp_validate_redirect( $dest, home_url( '/' ) ) );
			exit;
		}

		$email = isset( $_POST['creatorreactor_email'] ) ? sanitize_email( wp_unslash( $_POST['creatorreactor_email'] ) ) : '';
		if ( $email === '' || ! is_email( $email ) ) {
			self::redirect_with_error( 'invalid_email' );
		}

		$tos = isset( $_POST['creatorreactor_tos_accept'] ) ? (string) wp_unslash( $_POST['creatorreactor_tos_accept'] ) : '';
		if ( $tos !== '1' ) {
			self::redirect_with_error( 'tos_required' );
		}

		$other = email_exists( $email );
		if ( $other && (int) $other !== (int) $uid ) {
			self::redirect_with_error( 'email_in_use' );
		}

		$display = isset( $_POST['creatorreactor_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_display_name'] ) ) : '';
		$phone   = isset( $_POST['creatorreactor_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_phone'] ) ) : '';
		$address = isset( $_POST['creatorreactor_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['creatorreactor_address'] ) ) : '';
		$country = isset( $_POST['creatorreactor_country'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_country'] ) ) : '';

		$pref = isset( $_POST['creatorreactor_contact_preference'] ) ? sanitize_key( wp_unslash( $_POST['creatorreactor_contact_preference'] ) ) : 'email';
		if ( ! in_array( $pref, [ 'email', 'sms', 'both' ], true ) ) {
			$pref = 'email';
		}

		$opt_out = isset( $_POST['creatorreactor_opt_out_emails'] ) && (string) wp_unslash( $_POST['creatorreactor_opt_out_emails'] ) === '1';

		$update = [
			'ID'         => $uid,
			'user_email' => $email,
		];
		if ( $display !== '' ) {
			$update['display_name'] = $display;
		}
		$u = wp_update_user( $update );
		if ( is_wp_error( $u ) ) {
			self::redirect_with_error( 'profile_update' );
		}

		update_user_meta( $uid, self::META_PHONE, $phone );
		update_user_meta( $uid, self::META_ADDRESS, $address );
		update_user_meta( $uid, self::META_COUNTRY, $country );
		update_user_meta( $uid, self::META_CONTACT_PREF, $pref );
		if ( $opt_out ) {
			update_user_meta( $uid, self::META_OPT_OUT_EMAILS, '1' );
		} else {
			delete_user_meta( $uid, self::META_OPT_OUT_EMAILS );
		}
		self::persist_tos_acceptance_idempotent( $uid );
		update_user_meta( $uid, self::META_COMPLETE, '1' );

		/**
		 * After onboarding form saved (user meta + profile).
		 *
		 * @param int $uid User ID.
		 */
		do_action( 'creatorreactor_onboarding_completed', $uid );

		Entitlements::refresh_social_entitlements_user_meta( $uid, 'onboarding' );

		$dest = self::strip_onboarding_args_from_redirect_url( self::get_redirect_to_from_request() );
		wp_safe_redirect( wp_validate_redirect( $dest, home_url( '/' ) ) );
		exit;
	}

	/**
	 * Complete first-time Fanvue registration: create WP user from pending session + form fields, then log in.
	 *
	 * @param string $token Pending registration token from POST.
	 */
	private static function handle_submit_pending_fanvue( $token ) {
		$token = is_string( $token ) ? preg_replace( '/[^a-zA-Z0-9]/', '', $token ) : '';
		if ( $token === '' ) {
			wp_die( esc_html__( 'Invalid request.', 'creatorreactor' ), '', [ 'response' => 403 ] );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::nonce_action_pending( $token ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'creatorreactor' ), '', [ 'response' => 403 ] );
		}
		$session = self::get_pending_fanvue_registration( $token );
		if ( ! is_array( $session ) ) {
			wp_safe_redirect( wp_login_url( add_query_arg( 'creatorreactor_fanvue', 'pending_expired', home_url( '/' ) ) ) );
			exit;
		}
		$session_email = isset( $session['email'] ) ? sanitize_email( (string) $session['email'] ) : '';
		if ( $session_email !== '' && ! is_email( $session_email ) ) {
			$session_email = '';
		}
		$session_uuid = isset( $session['uuid'] ) && is_string( $session['uuid'] ) ? sanitize_text_field( $session['uuid'] ) : '';
		if ( ! empty( $session['fanvue_profile_json'] ) && is_string( $session['fanvue_profile_json'] ) ) {
			$prof_decoded = json_decode( $session['fanvue_profile_json'], true );
			if ( is_array( $prof_decoded ) ) {
				$extra_id = Fan_OAuth::identity_from_profile( $prof_decoded );
				if ( $session_uuid === '' && isset( $extra_id['uuid'] ) && is_string( $extra_id['uuid'] ) && $extra_id['uuid'] !== '' ) {
					$session_uuid = sanitize_text_field( $extra_id['uuid'] );
				}
				if ( $session_email === '' && isset( $extra_id['email'] ) && is_string( $extra_id['email'] ) && is_email( $extra_id['email'] ) ) {
					$session_email = sanitize_email( $extra_id['email'] );
				}
			}
		}
		$from_fan_oauth = isset( $session['fan_oauth_linked'] ) && (string) $session['fan_oauth_linked'] === '1';
		$legacy_pending = ( $session_email !== '' && is_email( $session_email ) ) || $session_uuid !== '';
		if ( ! $from_fan_oauth && ! $legacy_pending ) {
			wp_safe_redirect( wp_login_url( add_query_arg( 'creatorreactor_fanvue', 'pending_expired', home_url( '/' ) ) ) );
			exit;
		}

		$email = isset( $_POST['creatorreactor_email'] ) ? sanitize_email( wp_unslash( $_POST['creatorreactor_email'] ) ) : '';
		if ( $email === '' || ! is_email( $email ) ) {
			self::redirect_with_error( 'invalid_email' );
		}
		if ( $session_email !== '' && strtolower( $email ) !== strtolower( $session_email ) ) {
			self::redirect_with_error( 'invalid_email' );
		}

		$tos = isset( $_POST['creatorreactor_tos_accept'] ) ? (string) wp_unslash( $_POST['creatorreactor_tos_accept'] ) : '';
		if ( $tos !== '1' ) {
			self::redirect_with_error( 'tos_required' );
		}

		if ( email_exists( $email ) ) {
			self::redirect_with_error( 'email_in_use' );
		}

		if ( ! get_option( 'users_can_register' ) ) {
			self::redirect_with_error( 'registration_closed' );
		}

		$display = isset( $_POST['creatorreactor_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_display_name'] ) ) : '';
		$phone   = isset( $_POST['creatorreactor_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_phone'] ) ) : '';
		$address = isset( $_POST['creatorreactor_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['creatorreactor_address'] ) ) : '';
		$country = isset( $_POST['creatorreactor_country'] ) ? sanitize_text_field( wp_unslash( $_POST['creatorreactor_country'] ) ) : '';

		$pref = isset( $_POST['creatorreactor_contact_preference'] ) ? sanitize_key( wp_unslash( $_POST['creatorreactor_contact_preference'] ) ) : 'email';
		if ( ! in_array( $pref, [ 'email', 'sms', 'both' ], true ) ) {
			$pref = 'email';
		}

		$opt_out = isset( $_POST['creatorreactor_opt_out_emails'] ) && (string) wp_unslash( $_POST['creatorreactor_opt_out_emails'] ) === '1';

		$identity = [
			'email'   => $email,
			'uuid'    => $session_uuid,
			'display' => $display !== '' ? $display : ( isset( $session['display'] ) ? (string) $session['display'] : '' ),
		];

		$uid = Fan_OAuth::insert_wp_user_from_fanvue_identity( $identity );
		if ( is_wp_error( $uid ) ) {
			self::redirect_with_error( 'profile_update' );
		}
		$uid = (int) $uid;

		if ( $identity['uuid'] !== '' ) {
			update_user_meta( $uid, Entitlements::USERMETA_CREATORREACTOR_UUID, $identity['uuid'] );
		}
		update_user_meta( $uid, self::META_FANVUE_OAUTH_LINKED, '1' );

		if ( ! empty( $session['fan_oauth_tokens_sealed'] ) && is_string( $session['fan_oauth_tokens_sealed'] ) ) {
			$row = CreatorReactor_OAuth::unseal_fan_oauth_token_row( $session['fan_oauth_tokens_sealed'] );
			if ( is_array( $row ) ) {
				CreatorReactor_OAuth::save_fan_oauth_tokens_to_user( $uid, $row );
			}
		}
		$profile_payload = null;
		if ( ! empty( $session['fanvue_profile_json'] ) && is_string( $session['fanvue_profile_json'] ) ) {
			$pj = $session['fanvue_profile_json'];
			if ( strlen( $pj ) <= 100000 ) {
				update_user_meta( $uid, self::META_FANVUE_PROFILE_SNAPSHOT, $pj );
			}
			$decoded_profile = json_decode( $pj, true );
			if ( is_array( $decoded_profile ) ) {
				$profile_payload = $decoded_profile;
			}
		}

		$display_for_sync = $display !== '' ? $display : ( isset( $session['display'] ) && $session['display'] !== '' ? (string) $session['display'] : '' );
		if ( $display !== '' ) {
			wp_update_user(
				[
					'ID'           => $uid,
					'display_name' => $display,
				]
			);
		}

		CreatorReactor_Client::sync_entitlement_for_fan_after_login(
			$identity['uuid'],
			$uid,
			$email,
			is_string( $display_for_sync ) ? $display_for_sync : ''
		);
		if ( is_array( $profile_payload ) ) {
			Fan_OAuth::sync_entitlement_from_oauth_profile( $uid, $identity, $profile_payload );
		}

		update_user_meta( $uid, self::META_PHONE, $phone );
		update_user_meta( $uid, self::META_ADDRESS, $address );
		update_user_meta( $uid, self::META_COUNTRY, $country );
		update_user_meta( $uid, self::META_CONTACT_PREF, $pref );
		if ( $opt_out ) {
			update_user_meta( $uid, self::META_OPT_OUT_EMAILS, '1' );
		} else {
			delete_user_meta( $uid, self::META_OPT_OUT_EMAILS );
		}
		self::persist_tos_acceptance_idempotent( $uid );
		update_user_meta( $uid, self::META_COMPLETE, '1' );

		$user_obj = get_user_by( 'id', $uid );
		if ( ! $user_obj ) {
			self::redirect_with_error( 'profile_update' );
		}

		self::delete_pending_fanvue_registration( $token );
		self::clear_fan_pending_cookie();

		wp_set_auth_cookie( $uid, true );
		wp_set_current_user( $uid );
		do_action( 'wp_login', $user_obj->user_login, $user_obj );

		/**
		 * After onboarding form saved (user meta + profile).
		 *
		 * @param int $uid User ID.
		 */
		do_action( 'creatorreactor_onboarding_completed', $uid );

		Entitlements::refresh_social_entitlements_user_meta( $uid, 'onboarding' );

		$dest = self::get_redirect_to_from_request();
		if ( $dest === home_url( '/' ) && ! empty( $session['redirect_to'] ) ) {
			$dest = wp_validate_redirect( (string) $session['redirect_to'], home_url( '/' ) );
		}
		$dest = self::strip_onboarding_args_from_redirect_url( $dest );
		wp_safe_redirect( wp_validate_redirect( $dest, home_url( '/' ) ) );
		exit;
	}

	/**
	 * Record ToS acknowledgment once (does not overwrite existing acceptance timestamp).
	 *
	 * @param int $user_id User ID.
	 */
	private static function persist_tos_acceptance_idempotent( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$accepted = get_user_meta( $user_id, self::META_TOS_ACCEPTED, true );
		if ( $accepted === '1' || $accepted === 1 || $accepted === true ) {
			return;
		}
		update_user_meta( $user_id, self::META_TOS_ACCEPTED, '1' );
		update_user_meta( $user_id, self::META_TOS_ACCEPTED_AT, gmdate( 'c' ) );
	}

	private static function redirect_with_error( $code ) {
		$pend = self::read_post_pending_token();
		if ( $pend !== '' ) {
			$rto = home_url( '/' );
			if ( isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$rto = wp_validate_redirect( rawurldecode( trim( wp_unslash( $_POST['redirect_to'] ) ) ), home_url( '/' ) );
			}
			$ref = self::get_onboarding_url_with_pending( $pend, $rto );
		} else {
			$ref = wp_get_referer();
			if ( ! is_string( $ref ) || $ref === '' ) {
				$ref = self::get_onboarding_url();
			}
		}
		wp_safe_redirect( add_query_arg( 'cr_ob_err', rawurlencode( (string) $code ), $ref ) );
		exit;
	}

	/**
	 * Markup for gated content when the user must finish onboarding.
	 */
	public static function incomplete_gate_notice() {
		$url = esc_url( self::get_onboarding_url( self::current_request_url() ) );
		return '<div class="creatorreactor-onboarding-gate-notice" role="alert"><p>'
			. esc_html__( 'Please complete a quick one-time setup to access this content.', 'creatorreactor' )
			. '</p><p><a class="button" href="' . $url . '">' . esc_html__( 'Complete setup', 'creatorreactor' ) . '</a></p></div>';
	}

	private static function current_request_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return home_url( '/' );
		}
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return esc_url_raw( $scheme . '://' . $host . $uri );
	}

	public static function shortcode() {
		if ( Admin_Settings::is_broker_mode() ) {
			return '<p class="creatorreactor-onboarding-unavailable">' . esc_html__( 'Fan onboarding is not available in Agency (broker) mode.', 'creatorreactor' ) . '</p>';
		}
		if ( ! is_user_logged_in() ) {
			$pend = self::get_request_pending_token();
			if ( $pend !== '' && self::get_pending_fanvue_registration( $pend ) ) {
				self::enqueue_assets();
				ob_start();
				echo '<div class="creatorreactor-onboarding-wrap creatorreactor-onboarding-shortcode">';
				self::render_form();
				echo '</div>';
				return (string) ob_get_clean();
			}
			return '<p class="creatorreactor-onboarding-login-hint">' . esc_html__( 'Log in to continue setup.', 'creatorreactor' ) . '</p>';
		}
		if ( ! self::user_needs_onboarding() ) {
			return '<p class="creatorreactor-onboarding-done">' . esc_html__( 'Your setup is already complete.', 'creatorreactor' ) . '</p>';
		}
		self::enqueue_assets();
		ob_start();
		echo '<div class="creatorreactor-onboarding-wrap creatorreactor-onboarding-shortcode">';
		self::render_form();
		echo '</div>';
		return (string) ob_get_clean();
	}

	public static function render_form() {
		$pending_token = self::get_request_pending_token();
		$pending_data  = ( $pending_token !== '' ) ? self::get_pending_fanvue_registration( $pending_token ) : null;

		if ( is_array( $pending_data ) ) {
			$redirect_to = self::get_redirect_to_from_request();
			$err         = isset( $_GET['cr_ob_err'] ) ? sanitize_key( wp_unslash( $_GET['cr_ob_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$email   = isset( $pending_data['email'] ) ? (string) $pending_data['email'] : '';
			$display = isset( $pending_data['display'] ) ? (string) $pending_data['display'] : '';

			$registration_closed = ! get_option( 'users_can_register' );

			$phone          = '';
			$address        = '';
			$country        = '';
			$contact_pref   = 'email';
			$opt_out_emails = false;

			$terms_html = apply_filters( 'creatorreactor_terms_of_service_html', self::get_embedded_terms_html() );
			$terms_html = is_string( $terms_html ) ? wp_kses( $terms_html, self::tos_wp_kses_allowed() ) : '';

			include CREATORREACTOR_PLUGIN_DIR . 'templates/onboarding-form.php';
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		$pending_token       = '';
		$registration_closed = false;
		$redirect_to   = self::get_redirect_to_from_request();
		$err           = isset( $_GET['cr_ob_err'] ) ? sanitize_key( wp_unslash( $_GET['cr_ob_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$email   = $user->user_email;
		$display = $user->display_name;

		$phone          = (string) get_user_meta( $user->ID, self::META_PHONE, true );
		$address        = (string) get_user_meta( $user->ID, self::META_ADDRESS, true );
		$country        = (string) get_user_meta( $user->ID, self::META_COUNTRY, true );
		$contact_pref   = (string) get_user_meta( $user->ID, self::META_CONTACT_PREF, true );
		if ( ! in_array( $contact_pref, [ 'email', 'sms', 'both' ], true ) ) {
			$contact_pref = 'email';
		}
		$opt_out_emails = get_user_meta( $user->ID, self::META_OPT_OUT_EMAILS, true ) === '1' || get_user_meta( $user->ID, self::META_OPT_OUT_EMAILS, true ) === 1;

		$terms_html = apply_filters( 'creatorreactor_terms_of_service_html', self::get_embedded_terms_html() );
		$terms_html = is_string( $terms_html ) ? wp_kses( $terms_html, self::tos_wp_kses_allowed() ) : '';

		include CREATORREACTOR_PLUGIN_DIR . 'templates/onboarding-form.php';
	}

	/**
	 * Allowed HTML in embedded / filtered ToS (modal body).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function tos_wp_kses_allowed() {
		return [
			'section' => [ 'class' => true, 'id' => true ],
			'h2'      => [ 'class' => true, 'id' => true ],
			'h3'      => [ 'class' => true, 'id' => true ],
			'h4'      => [ 'class' => true, 'id' => true ],
			'p'       => [ 'class' => true ],
			'ul'      => [ 'class' => true ],
			'ol'      => [ 'class' => true ],
			'li'      => [ 'class' => true ],
			'strong'  => [],
			'em'      => [],
			'br'      => [],
			'hr'      => [ 'class' => true ],
			'a'       => [
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			],
		];
	}

	/**
	 * Load self-contained ToS HTML from the plugin (sanitized).
	 */
	public static function get_embedded_terms_html() {
		$path = CREATORREACTOR_PLUGIN_DIR . 'templates/onboarding-tos-embedded.php';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$html = include $path;
		if ( ! is_string( $html ) ) {
			return '';
		}
		return wp_kses( trim( $html ), self::tos_wp_kses_allowed() );
	}
}
