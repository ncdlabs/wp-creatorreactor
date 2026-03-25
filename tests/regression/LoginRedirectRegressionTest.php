<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Login_Page;
use CreatorReactor\Tests\BaseTestCase;

require_once __DIR__ . '/../../includes/class-creatorreactor-wp-login.php';

if (! class_exists('\WP_User')) {
    class_alias(\stdClass::class, 'WP_User');
}
if (! class_exists('\WP_Error')) {
    class_alias(\Exception::class, 'WP_Error');
}

final class LoginRedirectRegressionTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        unset(
            $_GET['action'],
            $_GET['creatorreactor_fanvue'],
            $_GET['redirect_to'],
            $_POST['redirect_to'],
            $_REQUEST['redirect_to'],
            $_COOKIE[\CreatorReactor\Onboarding::COOKIE_FAN_PENDING]
        );
        parent::tearDown();
    }

    public function testInitRegistersLoginRedirectFilter(): void
    {
        Functions\expect('add_action')->atLeast()->once();
        Functions\expect('add_filter')
            ->once()
            ->with('login_redirect', [Login_Page::class, 'force_home_login_redirect'], 20, 3);

        Login_Page::init();
        self::assertTrue(true);
    }

    public function testForceHomeLoginRedirectReturnsHomepageForAuthenticatedUser(): void
    {
        Functions\when('home_url')->alias(
            static fn ($path = '/'): string => 'https://example.com' . $path
        );

        $user = new \WP_User();
        self::assertSame(
            'https://example.com/',
            Login_Page::force_home_login_redirect(
                'https://example.com/wp-admin/',
                'https://example.com/wp-admin/',
                $user
            )
        );
    }

    public function testForceHomeLoginRedirectLeavesOriginalRedirectForAuthErrors(): void
    {
        $error = new \WP_Error();
        self::assertSame(
            'https://example.com/wp-admin/',
            Login_Page::force_home_login_redirect(
                'https://example.com/wp-admin/',
                'https://example.com/wp-admin/',
                $error
            )
        );
    }

    public function testNormalizeRequestRedirectToRewritesGetPostAndRequestValues(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('wp_parse_url')->alias(static fn ($url) => parse_url((string) $url));

        $_GET['redirect_to'] = 'https://example.com/wp-admin//index.php';
        $_POST['redirect_to'] = 'https://example.com/wp-admin//profile.php';
        $_REQUEST['redirect_to'] = 'https://example.com/wp-admin//edit.php';

        Login_Page::normalize_request_redirect_to();

        self::assertSame('https://example.com/wp-admin/index.php', $_GET['redirect_to']);
        self::assertSame('https://example.com/wp-admin/profile.php', $_POST['redirect_to']);
        self::assertSame('https://example.com/wp-admin/edit.php', $_REQUEST['redirect_to']);
    }

    public function testNormalizeRequestRedirectToLeavesNonStringValuesUnchanged(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);

        $_GET['redirect_to'] = ['https://example.com/not-a-string'];
        $_POST['redirect_to'] = 404;
        $_REQUEST['redirect_to'] = false;

        Login_Page::normalize_request_redirect_to();

        self::assertSame(['https://example.com/not-a-string'], $_GET['redirect_to']);
        self::assertSame(404, $_POST['redirect_to']);
        self::assertFalse($_REQUEST['redirect_to']);
    }

    public function testMaybeAddFanvueOauthLoginNoticeReadsCodeFromRedirectToQuery(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\when('wp_parse_url')->alias(
            static fn ($url, $component = -1) => parse_url((string) $url, $component)
        );
        Functions\expect('add_filter')
            ->once()
            ->with('login_message', [Login_Page::class, 'filter_login_message_fanvue_oauth'], 10, 1);

        unset($_GET['creatorreactor_fanvue']);
        $_GET['redirect_to'] = rawurlencode('https://example.com/wp-login.php?creatorreactor_fanvue=oauth_error');
        $_GET['action'] = 'login';

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testMaybeOfferPendingFanvueResumeReturnsEarlyWhenAlreadyLoggedIn(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\expect('add_filter')->never();

        Login_Page::maybe_offer_pending_fanvue_resume();
        self::assertTrue(true);
    }

    public function testMaybeAddFanvueOauthLoginNoticeAddsFilterForLoginActionAndKnownCode(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\expect('add_filter')
            ->once()
            ->with('login_message', [Login_Page::class, 'filter_login_message_fanvue_oauth'], 10, 1);

        $_GET['creatorreactor_fanvue'] = 'nonce';
        $_GET['action'] = 'login';

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testFilterLoginMessageFanvueOauthAppendsAlertBoxForUnknownCode(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('esc_html')->alias(static fn ($text): string => (string) $text);

        $_GET['creatorreactor_fanvue'] = 'something_unknown';
        $out = Login_Page::filter_login_message_fanvue_oauth('<p>base</p>');

        self::assertStringContainsString('<p>base</p>', $out);
        self::assertStringContainsString('Fanvue sign-in did not finish', $out);
    }

    public function testOnLoginFormLoginRegistersAssetsAndMarkupHooksWhenSocialLoginEnabled(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'broker_mode' => false,
                        'creatorreactor_oauth_client_id' => 'id',
                        'creatorreactor_oauth_client_secret' => 'secret',
                        'replace_wp_login_with_social' => true,
                    ];
                }
                return $default;
            }
        );
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\expect('add_action')
            ->once()
            ->with('login_enqueue_scripts', [Login_Page::class, 'enqueue_login_assets']);
        Functions\expect('add_action')
            ->once()
            ->with('login_form', [Login_Page::class, 'render_social_login_markup'], 0);

        Login_Page::on_login_form_login();
        self::assertTrue(true);
    }

    public function testOnLoginFormLoginDoesNothingWhenSocialLoginDisabled(): void
    {
        Functions\when('get_option')->alias(
            static fn ($key, $default = false) => $key === 'creatorreactor_settings' ? ['broker_mode' => true] : $default
        );
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\expect('add_action')->never();

        Login_Page::on_login_form_login();
        self::assertTrue(true);
    }

    public function testFilterLoginMessagePendingFanvueResumeReturnsOriginalMessageWhenCookieMissing(): void
    {
        $out = Login_Page::filter_login_message_pending_fanvue_resume('<p>base</p>');
        self::assertSame('<p>base</p>', $out);
    }

    public function testFilterLoginMessagePendingFanvueResumeAppendsResumeBoxWhenPendingExists(): void
    {
        $pendingOption = [];
        $token = 'TokenABC123';
        $pendingOption['creatorreactor_fv_pen_' . $token] = [
            'exp' => time() + 600,
            'data' => ['email' => 'fan@example.com', 'fan_oauth_linked' => '1'],
        ];

        Functions\when('get_option')->alias(
            static function ($key, $default = false) use (&$pendingOption) {
                return $pendingOption[$key] ?? $default;
            }
        );
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('home_url')->alias(static fn ($path = '/'): string => 'https://example.com' . $path);
        Functions\when('wp_validate_redirect')->alias(static fn ($url, $fallback = ''): string => (string) $url ?: (string) $fallback);
        Functions\when('esc_html__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);
        Functions\when('add_query_arg')->alias(
            static function ($key, $value = null, $url = null): string {
                if (is_array($key)) {
                    $baseUrl = (string) $value;
                    $pairs = [];
                    foreach ($key as $k => $v) {
                        $pairs[] = (string) $k . '=' . rawurlencode((string) $v);
                    }
                    $sep = str_contains($baseUrl, '?') ? '&' : '?';
                    return $baseUrl . $sep . implode('&', $pairs);
                }
                $sep = str_contains((string) $url, '?') ? '&' : '?';
                return (string) $url . $sep . (string) $key . '=' . rawurlencode((string) $value);
            }
        );

        $_COOKIE[\CreatorReactor\Onboarding::COOKIE_FAN_PENDING] = $token;
        $_GET['redirect_to'] = rawurlencode('https://example.com/members');
        $out = Login_Page::filter_login_message_pending_fanvue_resume('<p>base</p>');

        self::assertStringContainsString('Continue account setup', $out);
        self::assertStringContainsString('<p>base</p>', $out);
    }

    public function testRenderSocialLoginMarkupOutputsShortcodeHtml(): void
    {
        Functions\expect('do_shortcode')
            ->once()
            ->with('[fanvue_login_button]')
            ->andReturn('<a>Fanvue</a>');

        ob_start();
        Login_Page::render_social_login_markup();
        $out = (string) ob_get_clean();

        self::assertStringContainsString('creatorreactor-wp-login-social', $out);
        self::assertStringContainsString('<a>Fanvue</a>', $out);
    }

    public function testMaybeOfferPendingFanvueResumeReturnsEarlyWhenActionIsNotLogin(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\expect('add_filter')->never();

        $_GET['action'] = 'lostpassword';
        Login_Page::maybe_offer_pending_fanvue_resume();
        self::assertTrue(true);
    }

    public function testMaybeOfferClearsCookieWhenPendingTokenInvalid(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\when('get_option')->justReturn([]);
        if (! defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', '');
        }
        Functions\when('is_ssl')->justReturn(false);
        Functions\expect('add_filter')->never();

        $_GET['action'] = 'login';
        $_COOKIE[\CreatorReactor\Onboarding::COOKIE_FAN_PENDING] = 'notavalidstoredtoken';

        Login_Page::maybe_offer_pending_fanvue_resume();
        self::assertTrue(true);
    }

    public function testMaybeAddFanvueOauthLoginNoticeSkipsWhenActionIsNotLogin(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\expect('add_filter')->never();

        $_GET['creatorreactor_fanvue'] = 'denied';
        $_GET['action'] = 'lostpassword';

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testFilterLoginMessageFanvueOauthUsesKnownMapEntry(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('esc_html')->alias(static fn ($text): string => (string) $text);

        $_GET['creatorreactor_fanvue'] = 'denied';
        $out = Login_Page::filter_login_message_fanvue_oauth('<p>base</p>');

        self::assertStringContainsString('<p>base</p>', $out);
        self::assertStringContainsString('cancelled or denied', $out);
    }

    public function testEnqueueLoginAssetsRegistersInlineStyleAndScript(): void
    {
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 'test-version');
        }
        Functions\expect('wp_register_style')->once();
        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_add_inline_style')->once();
        Functions\expect('wp_register_script')->once();
        Functions\expect('wp_enqueue_script')->once();
        Functions\expect('wp_add_inline_script')->once();

        Login_Page::enqueue_login_assets();
        self::assertTrue(true);
    }
}
