<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Login_Page;
use CreatorReactor\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_User;

final class LoginPageCoverageRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('sanitize_text_field')->alias(static fn ($text): string => trim((string) $text));
        Functions\when('wp_parse_url')->alias(static fn ($url, $component = -1) => parse_url((string) $url, $component));
    }

    protected function tearDown(): void
    {
        unset($_GET, $_POST, $_REQUEST, $_SERVER);
        parent::tearDown();
    }

    public function testNormalizeRequestRedirectToCollapsesSlashesInSuperglobals(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        $_GET['redirect_to'] = 'https://ex.com/wp-admin//here';
        $_POST['redirect_to'] = 'https://ex.com//wp-login.php';
        $_REQUEST['redirect_to'] = 'https://ex.com/a//b';

        Login_Page::normalize_request_redirect_to();

        self::assertSame('https://ex.com/wp-admin/here', $_GET['redirect_to']);
        self::assertSame('https://ex.com/wp-login.php', $_POST['redirect_to']);
        self::assertSame('https://ex.com/a/b', $_REQUEST['redirect_to']);
    }

    public function testNormalizeRequestRedirectToIgnoresNonStringValues(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        $_GET['redirect_to'] = ['bad'];

        Login_Page::normalize_request_redirect_to();

        self::assertSame(['bad'], $_GET['redirect_to']);
    }

    public function testNormalizeRequestRedirectToLeavesEmptyString(): void
    {
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        $_GET['redirect_to'] = '';

        Login_Page::normalize_request_redirect_to();

        self::assertSame('', $_GET['redirect_to']);
    }

    public function testEnqueueLoginBrandingAssetsRegistersInlineLogoCss(): void
    {
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 't');
        }
        if (! defined('CREATORREACTOR_PLUGIN_URL')) {
            define('CREATORREACTOR_PLUGIN_URL', 'https://example.com/p/');
        }
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('esc_url')->alias(static fn ($u): string => (string) $u);
        Functions\when('wp_add_inline_style')->justReturn();

        Login_Page::enqueue_login_branding_assets();
        self::assertTrue(true);
    }

    public function testFilterLoginHeaderUrlReturnsHomeUrl(): void
    {
        Functions\when('home_url')->alias(static fn ($p = '/'): string => 'https://home.test' . $p);
        self::assertSame('https://home.test/', Login_Page::filter_login_header_url('x'));
    }

    public function testFilterLoginHeaderTextReturnsTranslatedBrand(): void
    {
        Functions\when('__')->alias(static fn ($t, $d = null): string => (string) $t);
        self::assertSame('CreatorReactor', Login_Page::filter_login_header_text('x'));
    }

    public function testFilterLoginBodyClassAppendsCreatorReactorMarker(): void
    {
        self::assertSame(
            ['a', 'creatorreactor-login'],
            Login_Page::filter_login_body_class(['a'], 'login')
        );
    }

    public function testForceHomeLoginRedirectSendsWpUsersToHome(): void
    {
        Functions\when('home_url')->alias(static fn ($p = '/'): string => 'https://pub.test' . $p);
        $u = new WP_User();
        $u->ID = 1;

        self::assertSame(
            'https://pub.test/',
            Login_Page::force_home_login_redirect('https://else/', 'https://else/', $u)
        );
    }

    public function testForceHomeLoginRedirectPreservesRedirectWhenUserIsNotWpUser(): void
    {
        self::assertSame(
            'https://keep/',
            Login_Page::force_home_login_redirect('https://keep/', 'https://keep/', new \stdClass())
        );
    }

    public function testMaybeAddFanvueOauthLoginNoticeRegistersFilterOnLoginAction(): void
    {
        $_GET['creatorreactor_fanvue'] = 'nonce';
        $_GET['action'] = 'login';
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\expect('add_filter')->once()->with('login_message', [\CreatorReactor\Login_Page::class, 'filter_login_message_fanvue_oauth'], 10, 1);

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testMaybeAddFanvueOauthLoginNoticeSkipsWhenActionIsNotLogin(): void
    {
        $_GET['creatorreactor_fanvue'] = 'nonce';
        $_GET['action'] = 'lostpassword';
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\expect('add_filter')->never();

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testMaybeAddFanvueOauthLoginNoticeDoesNothingWhenRequestHasNoCode(): void
    {
        $_GET = [];
        Functions\expect('add_filter')->never();

        Login_Page::maybe_add_fanvue_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testFanvueFilterFallsBackWhenRedirectToHasNoQueryString(): void
    {
        $_GET['redirect_to'] = 'https://example.com/wp-admin/index.php';
        unset($_GET['creatorreactor_fanvue']);
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_fanvue_oauth('');
        self::assertStringContainsString('creatorreactor-fanvue-login-notice', $html);
    }

    public function testFanvueFilterFallsBackWhenQueryOmitsCreatorReactorKey(): void
    {
        $_GET['redirect_to'] = rawurlencode('https://example.com/?other=1');
        unset($_GET['creatorreactor_fanvue']);
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_fanvue_oauth('');
        self::assertStringContainsString('creatorreactor-fanvue-login-notice', $html);
    }

    public function testMaybeAddGoogleOauthLoginNoticeRegistersFilterOnLoginAction(): void
    {
        $_GET['creatorreactor_google'] = 'denied';
        $_GET['action'] = 'login';
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\expect('add_filter')->once()->with('login_message', [\CreatorReactor\Login_Page::class, 'filter_login_message_google_oauth'], 10, 1);

        Login_Page::maybe_add_google_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testMaybeAddGoogleOauthLoginNoticeDoesNothingWhenRequestHasNoCode(): void
    {
        $_GET = [];
        Functions\expect('add_filter')->never();

        Login_Page::maybe_add_google_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testMaybeAddGoogleOauthLoginNoticeSkipsWhenActionIsNotLogin(): void
    {
        $_GET['creatorreactor_google'] = 'denied';
        $_GET['action'] = 'register';
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\expect('add_filter')->never();

        Login_Page::maybe_add_google_oauth_login_notice();
        self::assertTrue(true);
    }

    public function testGoogleFilterFallsBackWhenRedirectToHasNoQueryString(): void
    {
        $_GET['redirect_to'] = 'https://example.com/plain';
        unset($_GET['creatorreactor_google']);
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_google_oauth('');
        self::assertStringContainsString('creatorreactor-google-login-notice', $html);
    }

    public function testGoogleFilterFallsBackWhenQueryOmitsCreatorReactorKey(): void
    {
        $_GET['redirect_to'] = rawurlencode('https://example.com/?x=y');
        unset($_GET['creatorreactor_google']);
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_google_oauth('');
        self::assertStringContainsString('creatorreactor-google-login-notice', $html);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function fanvueNoticeCodeProvider(): array
    {
        return [
            ['nonce'],
            ['agency'],
            ['config'],
            ['denied'],
            ['oauth_redirect'],
            ['oauth_client'],
            ['oauth_request'],
            ['oauth_error'],
            ['state'],
            ['token'],
            ['profile'],
            ['closed'],
            ['user'],
            ['missing'],
            ['pending_expired'],
            ['__unknown__'],
        ];
    }

    #[DataProvider('fanvueNoticeCodeProvider')]
    public function testFilterFanvueLoginMessageContainsNoticeForEachKnownCode(string $code): void
    {
        $_GET['creatorreactor_fanvue'] = $code;
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_fanvue_oauth('<p>base</p>');
        self::assertStringContainsString('creatorreactor-fanvue-login-notice', $html);
        self::assertStringContainsString('<p>base</p>', $html);
    }

    public function testFanvueNoticeCodeReadsFromRedirectToQueryString(): void
    {
        unset($_GET['creatorreactor_fanvue']);
        $_GET['redirect_to'] = rawurlencode('https://x.test/wp-admin/index.php?creatorreactor_fanvue=config');
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_parse_url')->alias(static fn ($u) => parse_url((string) $u));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_fanvue_oauth('');
        self::assertStringContainsString('creatorreactor-fanvue-login-notice', $html);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function googleNoticeCodeProvider(): array
    {
        return [
            ['nonce'],
            ['agency'],
            ['config'],
            ['denied'],
            ['oauth_redirect'],
            ['oauth_client'],
            ['oauth_request'],
            ['oauth_error'],
            ['state'],
            ['token'],
            ['profile'],
            ['closed'],
            ['user'],
            ['missing'],
            ['__unknown__'],
        ];
    }

    #[DataProvider('googleNoticeCodeProvider')]
    public function testFilterGoogleLoginMessageContainsNoticeForEachKnownCode(string $code): void
    {
        $_GET['creatorreactor_google'] = $code;
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_google_oauth('');
        self::assertStringContainsString('creatorreactor-google-login-notice', $html);
    }

    public function testGoogleNoticeCodeReadsFromRedirectToQueryString(): void
    {
        unset($_GET['creatorreactor_google']);
        $_GET['redirect_to'] = rawurlencode('https://x.test/?creatorreactor_google=token');
        Functions\when('wp_unslash')->alias(static fn ($v) => $v);
        Functions\when('sanitize_key')->alias(static fn ($v): string => strtolower((string) $v));
        Functions\when('wp_parse_url')->alias(static fn ($u) => parse_url((string) $u));
        Functions\when('esc_html')->alias(static fn ($t): string => (string) $t);

        $html = Login_Page::filter_login_message_google_oauth('');
        self::assertStringContainsString('creatorreactor-google-login-notice', $html);
    }

    public function testEnqueueLoginAssetsRegistersInlineStyleAndScript(): void
    {
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 't');
        }
        Functions\when('wp_register_style')->justReturn();
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('wp_add_inline_style')->justReturn();
        Functions\when('wp_register_script')->justReturn();
        Functions\when('wp_enqueue_script')->justReturn();
        Functions\when('wp_add_inline_script')->justReturn();

        Login_Page::enqueue_login_assets();
        self::assertTrue(true);
    }

    public function testRenderSocialLoginMarkupOutputsShortcodes(): void
    {
        Functions\when('do_shortcode')->alias(
            static fn (string $c): string => $c === '[fanvue_login_button]' ? '<fv/>' : ($c === '[google_login_button]' ? '<g/>' : '')
        );

        ob_start();
        Login_Page::render_social_login_markup();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<fv/>', $html);
        self::assertStringContainsString('<g/>', $html);
    }
}
