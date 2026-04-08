<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (! defined('CREATORREACTOR_TABLE_ENTITLEMENTS')) {
    define('CREATORREACTOR_TABLE_ENTITLEMENTS', 'creatorreactor_entitlements');
}
if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (! defined('CREATORREACTOR_PLUGIN_DIR')) {
    define('CREATORREACTOR_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (! defined('CREATORREACTOR_VERSION')) {
    define('CREATORREACTOR_VERSION', 'test');
}
if (! defined('CREATORREACTOR_PLUGIN_URL')) {
    define('CREATORREACTOR_PLUGIN_URL', 'https://example.org/wp-content/plugins/wp-creatorreactor/');
}

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/wp-core-test-stubs.php';

// Provide a minimal \WP_User stub for unit tests.
// We intentionally avoid importing the full wordpress-stubs package here because
// it defines a lot of WP functions that conflict with Brain Monkey / Patchwork.
if ( ! class_exists( '\\WP_User' ) ) {
    /**
     * Minimal WP_User stand-in for role checks in plugin code.
     */
    class WP_User {
        /** @var int */
        public $ID = 0;
        /** @var array<string, mixed> */
        public $roles = [];
        /** @var string */
        public $user_email = '';
    }
}

require_once __DIR__ . '/../includes/class-entitlements.php';
require_once __DIR__ . '/../includes/class-editor-context.php';
require_once __DIR__ . '/../includes/class-creatorreactor-oauth.php';
require_once __DIR__ . '/../includes/class-admin-settings.php';
require_once __DIR__ . '/../includes/class-bluesky-oauth.php';
require_once __DIR__ . '/../includes/class-broker-client.php';
require_once __DIR__ . '/../includes/class-creatorreactor-banner.php';
require_once __DIR__ . '/../includes/class-creatorreactor.php';
require_once __DIR__ . '/../includes/class-creatorreactor-onboarding.php';
require_once __DIR__ . '/../includes/class-role-impersonation.php';
require_once __DIR__ . '/../includes/class-creatorreactor-shortcodes.php';
require_once __DIR__ . '/../includes/class-creatorreactor-client.php';
require_once __DIR__ . '/../includes/class-creatorreactor-fan-oauth.php';
require_once __DIR__ . '/../includes/class-creatorreactor-wp-login.php';
