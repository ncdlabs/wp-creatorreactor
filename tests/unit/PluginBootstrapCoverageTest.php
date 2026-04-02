<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Admin_Settings;
use CreatorReactor\Entitlements;
use CreatorReactor\Plugin;
use CreatorReactor\Tests\BaseTestCase;

/**
 * Executes {@see Plugin::bootstrap()} so coverage includes registration requires and inits.
 */
final class PluginBootstrapCoverageTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('CREATORREACTOR_PLUGIN_DIR')) {
            define('CREATORREACTOR_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
        }
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 'test-coverage');
        }
        if (! defined('CREATORREACTOR_PLUGIN_URL')) {
            define('CREATORREACTOR_PLUGIN_URL', 'https://example.com/wp-content/plugins/creatorreactor/');
        }
        if (! defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', '');
        }

        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';

            public string $posts = 'wp_posts';

            public function get_blog_prefix(): string
            {
                return 'wp_';
            }

            public function get_var($q)
            {
                return null;
            }

            public function prepare($query, ...$args): string
            {
                return (string) $query;
            }

            public function query($q)
            {
                return true;
            }

            public function esc_like(string $text): string
            {
                return addcslashes($text, '_%\\');
            }
        };

        Functions\when('add_action')->justReturn(null);
        Functions\when('add_filter')->justReturn(null);
        Functions\when('get_option')->alias(static function ($key, $default = false) {
            $key = (string) $key;
            if ($key === Entitlements::OPTION_FANVUE_PRODUCT_KEY_MIGRATED || $key === Entitlements::OPTION_TIER_FOLLOWER_FORMAT_MIGRATED) {
                return '1';
            }
            // Non-empty settings so Admin_Settings::migrate_legacy_options() returns before OAuth redirect URI defaults.
            if ($key === Admin_Settings::OPTION_NAME) {
                return [
                    'product'                     => 'fanvue',
                    'broker_mode'                 => false,
                    'creatorreactor_oauth_scopes' => 'read',
                ];
            }

            return $default;
        });
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('plugin_basename')->alias(static fn (string $path): string => basename($path));
        Functions\when('wp_parse_url')->alias(static fn ($url, $component = -1) => parse_url((string) $url, $component));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function testBootstrapLoadsAndRegistersPluginSubsystems(): void
    {
        Plugin::bootstrap();

        self::assertSame(
            'https://example.com/wp-admin/x',
            Plugin::filter_admin_url_normalize_path_slashes('https://example.com/wp-admin//x', 'x', null)
        );
    }

}
