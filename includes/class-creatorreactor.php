<?php
/**
 * Core plugin bootstrap (loaded from creatorreactor.php).
 * Supports both Direct and Broker modes.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	const MODE_BROKER = 'broker';
	const MODE_DIRECT = 'direct';

	public static function bootstrap() {
		try {
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-entitlements.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-admin-settings.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-oauth.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-client.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-cron.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-broker-client.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-fan-oauth.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-onboarding.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-shortcodes.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-wp-login.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-elementor.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-blocks.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-editor-context.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-gated-content-cache.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-role-impersonation.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-viewer-state.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-editor-blocks-prompt.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-creatorreactor-banner.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-privacy.php';
			require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-metrics-ingest.php';

			Entitlements::maybe_migrate_fanvue_product_key();
			Entitlements::maybe_migrate_legacy_follower_tier_stored();
			Entitlements::init();

			CreatorReactor_OAuth::init();
			Fan_OAuth::init();
			Onboarding::init();
			Cron::init();
			Admin_Settings::init();
			Shortcodes::init();
			Login_Page::init();
			Blocks::init();
			Editor_Blocks_Prompt::init();
			Gated_Content_Cache::init();
			Role_Impersonation::init();
			Viewer_State::init();
			Elementor_Integration::init();
			Banner::init();
			Privacy::init();
			Metrics_Ingest::init();

			add_filter( 'admin_url', [ __CLASS__, 'filter_admin_url_normalize_path_slashes' ], 5, 3 );

			// Broker REST callback must register in all modes so OAuth redirects to
			// .../broker-callback never hit rest_no_route (e.g. Fanvue app URI vs mode mismatch).
			Broker_Client::init();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CreatorReactor bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			try {
				Admin_Settings::set_critical_error(
					__( 'Bootstrap error:', 'creatorreactor' ) . ' ' . $e->getMessage()
				);
			} catch ( \Throwable $inner ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CreatorReactor error logging failed: ' . $inner->getMessage() );
				}
			}
		}
	}

	public static function is_broker_mode() {
		return Admin_Settings::is_broker_mode();
	}

	public static function is_direct_mode() {
		return ! self::is_broker_mode();
	}

	public static function is_connected() {
		return Admin_Settings::is_connected();
	}

	public static function get_profile() {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_profile();
		}
		$client = new CreatorReactor_Client();
		return $client->get_profile();
	}

	public static function get_subscribers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_subscribers( $page, $size );
		}
		$client = new CreatorReactor_Client();
		return $client->list_subscribers( $page, $size );
	}

	public static function get_followers( $page = 1, $size = 50 ) {
		if ( self::is_broker_mode() ) {
			return Broker_Client::get_followers( $page, $size );
		}
		$client = new CreatorReactor_Client();
		return $client->list_followers( $page, $size );
	}

	/**
	 * Collapse repeated slashes in the path of an absolute URL (fixes .../wp-admin// when siteurl has a trailing slash).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url_path_slashes( $url ) {
		$url = (string) $url;
		if ( $url === '' ) {
			return $url;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! isset( $parts['path'] ) || $parts['path'] === '' ) {
			return $url;
		}
		$path = preg_replace( '#/+#', '/', $parts['path'] );
		if ( ! is_string( $path ) || $path === $parts['path'] ) {
			return $url;
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$user   = isset( $parts['user'] ) ? $parts['user'] : '';
		$pass   = isset( $parts['pass'] ) ? ':' . $parts['pass'] : '';
		$auth   = ( $user !== '' || $pass !== '' ) ? $user . $pass . '@' : '';
		$host   = $parts['host'];
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$frag   = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		return $scheme . $auth . $host . $port . $path . $query . $frag;
	}

	/**
	 * @param string      $url     Admin URL.
	 * @param string      $path    Path segment passed to admin_url().
	 * @param int|null    $blog_id Blog ID.
	 * @return string
	 */
	public static function filter_admin_url_normalize_path_slashes( $url, $path, $blog_id ) {
		return self::normalize_url_path_slashes( $url );
	}
}
