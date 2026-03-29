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

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/wp-core-test-stubs.php';

require_once __DIR__ . '/../includes/class-entitlements.php';
require_once __DIR__ . '/../includes/class-editor-context.php';
require_once __DIR__ . '/../includes/class-creatorreactor-oauth.php';
require_once __DIR__ . '/../includes/class-admin-settings.php';
require_once __DIR__ . '/../includes/class-broker-client.php';
require_once __DIR__ . '/../includes/class-creatorreactor-banner.php';
require_once __DIR__ . '/../includes/class-creatorreactor.php';
require_once __DIR__ . '/../includes/class-creatorreactor-onboarding.php';
require_once __DIR__ . '/../includes/class-role-impersonation.php';
require_once __DIR__ . '/../includes/class-creatorreactor-shortcodes.php';
require_once __DIR__ . '/../includes/class-creatorreactor-client.php';
require_once __DIR__ . '/../includes/class-creatorreactor-fan-oauth.php';
