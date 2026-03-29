<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Banner;
use CreatorReactor\Plugin;
use CreatorReactor\Tests\BaseTestCase;

require_once __DIR__ . '/../../includes/class-creatorreactor-client.php';

final class BannerAndPluginRegressionTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }

    public function testBannerInitRegistersHooks(): void
    {
        Functions\expect('add_action')->times(5);
        Functions\expect('add_filter')->once();
        Banner::init();
        self::assertTrue(true);
    }

    public function testMaybeShowBannerReturnsEarlyOutsideAdmin(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\expect('get_option')->never();

        ob_start();
        Banner::maybe_show_banner();
        $out = (string) ob_get_clean();

        self::assertSame('', trim($out));
    }

    public function testMaybeShowBannerReturnsEarlyWhenRegistrationEnabled(): void
    {
        $_SESSION = [];
        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_option')->alias(
            static fn ($key, $default = null) => $key === 'users_can_register' ? 1 : $default
        );
        Functions\expect('esc_html_e')->never();

        ob_start();
        Banner::maybe_show_banner();
        $out = (string) ob_get_clean();

        self::assertSame('', trim($out));
    }

    public function testMaybeShowBannerReturnsEarlyWhenDismissedInCurrentSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        Banner::maybe_start_session();
        $_SESSION['creatorreactor_oauth_banner_dismissed'] = true;

        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_option')->alias(
            static fn ($key, $default = null) => $key === 'users_can_register' ? 0 : $default
        );
        Functions\expect('esc_html_e')->never();

        ob_start();
        Banner::maybe_show_banner();
        $out = (string) ob_get_clean();

        self::assertSame('', trim($out));
    }

    public function testMaybeStartSessionStartsWhenHeadersNotYetSent(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        Banner::maybe_start_session();
        self::assertSame(PHP_SESSION_ACTIVE, session_status());

        session_destroy();
    }

    public function testMaybeShowBannerRendersWhenAdminRegistrationDisabledAndNotDismissed(): void
    {
        $_SESSION = [];
        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_option')->alias(
            static fn ($key, $default = null) => $key === 'users_can_register' ? 0 : $default
        );
        Functions\when('esc_html_e')->alias(
            static function ($text, $domain = null): void {
                echo (string) $text;
            }
        );
        Functions\when('esc_url')->alias(static fn ($url) => (string) $url);
        Functions\when('esc_attr')->alias(static fn ($text) => (string) $text);
        Functions\when('__')->alias(static fn ($text) => (string) $text);
        Functions\when('wp_kses')->alias(static fn ($html, $allowed) => (string) $html);
        if (! defined('CREATORREACTOR_PLUGIN_URL')) {
            define('CREATORREACTOR_PLUGIN_URL', 'https://example.com/wp-content/plugins/creatorreactor/');
        }

        ob_start();
        Banner::maybe_show_banner();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('creatorreactor-oauth-banner', $out);
        self::assertStringContainsString('creatorreactor-registration-alert-wrap--global', $out);
        self::assertStringContainsString('creatorreactor-registration-alert', $out);
        self::assertStringContainsString('Dismiss', $out);
        self::assertStringContainsString('cr-logo.png', $out);
        self::assertStringContainsString('creatorreactor-registration-alert-fix', $out);
    }

    public function testEnqueueAssetsRegistersScriptAndNoncePayload(): void
    {
        $_SESSION = [];
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 'test-version');
        }
        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(
            static fn ($key, $default = null) => $key === 'users_can_register' ? 0 : $default
        );
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/creatorreactor/');
        Functions\when('wp_register_style')->justReturn();
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('wp_add_inline_style')->justReturn();
        Functions\expect('wp_enqueue_script')->once();
        Functions\when('wp_create_nonce')->justReturn('nonce-123');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/options-general.php');
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\expect('wp_localize_script')
            ->once()
            ->with(
                'creatorreactor-oauth-banner',
                'creatorreactor_oauth_banner',
                \Mockery::on(static function ($data): bool {
                    return isset($data['nonce'], $data['settingsGeneralUrl'], $data['fixError'])
                        && $data['nonce'] === 'nonce-123'
                        && $data['settingsGeneralUrl'] === 'https://example.com/wp-admin/options-general.php';
                })
            );

        Banner::enqueue_assets();
        self::assertTrue(true);
    }

    public function testAjaxDismissBannerRequiresManageOptionsCapability(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('creatorreactor_oauth_dismiss_banner_nonce', 'security');
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('__')->once()->andReturn('Forbidden.');
        Functions\expect('wp_send_json_error')
            ->once()
            ->with('Forbidden.', 403)
            ->andThrow(new \RuntimeException('stop'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop');
        Banner::ajax_dismiss_banner();
    }

    public function testAjaxDismissBannerSendsSuccessWhenUserCanManageOptions(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('creatorreactor_oauth_dismiss_banner_nonce', 'security');
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('wp_send_json_success')->once()->andThrow(new \RuntimeException('stop-success'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-success');

        Banner::ajax_dismiss_banner();
    }

    public function testNormalizeUrlPathSlashesCollapsesDuplicatePathSeparators(): void
    {
        Functions\when('wp_parse_url')->alias(
            static fn ($url) => parse_url((string) $url)
        );

        $url = 'https://example.com/wp-admin//admin.php?page=creatorreactor';
        self::assertSame(
            'https://example.com/wp-admin/admin.php?page=creatorreactor',
            Plugin::normalize_url_path_slashes($url)
        );
    }

    public function testNormalizeUrlPathSlashesReturnsInputWhenEmpty(): void
    {
        self::assertSame('', Plugin::normalize_url_path_slashes(''));
    }

    public function testNormalizeUrlPathSlashesReturnsInputWhenPathMissing(): void
    {
        Functions\when('wp_parse_url')->alias(
            static fn ($url) => ['scheme' => 'https', 'host' => 'example.com']
        );

        $url = 'https://example.com';
        self::assertSame($url, Plugin::normalize_url_path_slashes($url));
    }

    public function testFilterAdminUrlNormalizePathSlashesDelegatesToNormalizer(): void
    {
        Functions\when('wp_parse_url')->alias(
            static fn ($url) => parse_url((string) $url)
        );

        $url = 'https://example.com/wp-admin//admin.php?page=creatorreactor';
        self::assertSame(
            'https://example.com/wp-admin/admin.php?page=creatorreactor',
            Plugin::filter_admin_url_normalize_path_slashes($url, 'admin.php', null)
        );
    }

    public function testIsBrokerModeReflectsAdminSettingsRawOption(): void
    {
        Functions\when('get_option')->alias(
            static fn ($key, $default = false) => $key === 'creatorreactor_settings' ? ['broker_mode' => true] : $default
        );

        self::assertTrue(Plugin::is_broker_mode());
        self::assertFalse(Plugin::is_direct_mode());
    }

    public function testIsConnectedUsesOauthPathWhenNotBrokerModeAndNoDecryptableToken(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => false];
                }
                if ($key === 'creatorreactor_oauth_tokens') {
                    return ['access_token' => 'token-123'];
                }
                return $default;
            }
        );

        self::assertFalse(Plugin::is_connected());
    }

    public function testIsConnectedUsesBrokerPathWhenBrokerMode(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => true, 'jwt_token' => 'jwt-abc'];
                }
                return $default;
            }
        );

        self::assertTrue(Plugin::is_connected());
    }

    public function testGetProfileReturnsNullInDirectModeWithoutAccessToken(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => false];
                }
                if ($key === 'creatorreactor_oauth_tokens') {
                    return '';
                }
                return $default;
            }
        );

        self::assertNull(Plugin::get_profile());
    }

    public function testGetSubscribersReturnsNullInDirectModeWithoutAccessToken(): void
    {
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_strip_all_tags')->alias(static fn ($text): string => (string) $text);
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => false];
                }
                if ($key === 'creatorreactor_oauth_tokens') {
                    return '';
                }
                return $default;
            }
        );

        self::assertNull(Plugin::get_subscribers(1, 50));
    }

    public function testGetFollowersReturnsNullInDirectModeWithoutAccessToken(): void
    {
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_strip_all_tags')->alias(static fn ($text): string => (string) $text);
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => false];
                }
                if ($key === 'creatorreactor_oauth_tokens') {
                    return '';
                }
                return $default;
            }
        );

        self::assertNull(Plugin::get_followers(1, 50));
    }

    public function testGetProfileDelegatesToBrokerClientWhenBrokerModeAndJwtOk(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'broker_mode' => true,
                        'broker_url' => 'https://broker.example.com',
                        'site_id' => 'site-1',
                        'jwt_token' => 'jwt-test',
                        'creatorreactor_api_version' => '2025-06-26',
                    ];
                }
                return $default;
            }
        );
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('add_query_arg')->alias(
            static function ($key, $value = null, $url = null): string {
                if (is_array($key)) {
                    $base = (string) $value;
                    $q = [];
                    foreach ($key as $k => $v) {
                        $q[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
                    }
                    return $base . (str_contains($base, '?') ? '&' : '?') . implode('&', $q);
                }
                return (string) $url . (str_contains((string) $url, '?') ? '&' : '?') . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        );
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('wp_remote_get')->once()->andReturn(['mock' => 'response']);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"displayName":"Broker User","handle":"@bro"}');

        $profile = Plugin::get_profile();
        self::assertIsArray($profile);
        self::assertSame('Broker User', $profile['displayName']);
    }

    public function testGetSubscribersDelegatesToBrokerClientWhenBrokerMode(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'broker_mode' => true,
                        'broker_url' => 'https://broker.example.com',
                        'site_id' => 'site-1',
                        'jwt_token' => 'jwt-test',
                        'creatorreactor_api_version' => '2025-06-26',
                    ];
                }
                return $default;
            }
        );
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('add_query_arg')->alias(
            static function ($key, $value = null, $url = null): string {
                if (is_array($key)) {
                    $base = (string) $value;
                    $pairs = [];
                    foreach ($key as $k => $v) {
                        $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
                    }
                    return $base . (str_contains($base, '?') ? '&' : '?') . implode('&', $pairs);
                }
                return (string) $url . (str_contains((string) $url, '?') ? '&' : '?') . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        );
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('wp_remote_get')->once()->andReturn(['mock' => 'response']);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(
            '{"data":[{"uuid":"u1"}],"pagination":{"page":1,"size":1,"hasMore":false}}'
        );

        $out = Plugin::get_subscribers(2, 25);
        self::assertIsArray($out);
        self::assertArrayHasKey('data', $out);
    }

    public function testGetFollowersDelegatesToBrokerClientWhenBrokerMode(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'broker_mode' => true,
                        'broker_url' => 'https://broker.example.com',
                        'site_id' => 'site-1',
                        'jwt_token' => 'jwt-test',
                        'creatorreactor_api_version' => '2025-06-26',
                    ];
                }
                return $default;
            }
        );
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('add_query_arg')->alias(
            static function ($key, $value = null, $url = null): string {
                if (is_array($key)) {
                    $base = (string) $value;
                    $pairs = [];
                    foreach ($key as $k => $v) {
                        $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
                    }
                    return $base . (str_contains($base, '?') ? '&' : '?') . implode('&', $pairs);
                }
                return (string) $url . (str_contains((string) $url, '?') ? '&' : '?') . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        );
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('wp_remote_get')->once()->andReturn(['mock' => 'response']);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(
            '{"data":[],"pagination":{"page":1,"size":0,"hasMore":false}}'
        );

        $out = Plugin::get_followers(1, 50);
        self::assertIsArray($out);
        self::assertSame([], $out['data']);
    }
}
