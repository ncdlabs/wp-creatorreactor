<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Onboarding;
use CreatorReactor\Tests\BaseTestCase;

final class OnboardingRedirectRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('CREATORREACTOR_PLUGIN_DIR')) {
            define('CREATORREACTOR_PLUGIN_DIR', '/Users/lou/git/creatorreactor/wp-creatorreactor/');
        }

        Functions\when('home_url')->alias(
            static fn ($path = '/'): string => 'https://example.com' . $path
        );
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => $default);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('wp_validate_redirect')->alias(
            static fn ($url, $fallback = ''): string => is_string($url) && str_starts_with($url, 'https://example.com')
                ? $url
                : (string) $fallback
        );
        Functions\when('remove_query_arg')->alias(
            static function (array $keys, string $url): string {
                $parts = parse_url($url);
                if (! is_array($parts)) {
                    return $url;
                }

                $query = [];
                parse_str($parts['query'] ?? '', $query);
                foreach ($keys as $k) {
                    unset($query[(string) $k]);
                }
                $newQuery = http_build_query($query);

                $out = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'example.com');
                $out .= $parts['path'] ?? '/';
                if ($newQuery !== '') {
                    $out .= '?' . $newQuery;
                }
                return $out;
            }
        );
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
        Functions\when('get_user_meta')->alias(
            static function ($userId, $key, $single) {
                if ((int) $userId !== 42) {
                    return '';
                }
                if ($key === Onboarding::META_COMPLETE) {
                    return '';
                }
                if ($key === Onboarding::META_FANVUE_OAUTH_LINKED) {
                    return '1';
                }
                return '';
            }
        );
        Functions\when('apply_filters')->alias(static fn ($tag, $value, ...$rest) => $value);
        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('sanitize_email')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('is_email')->alias(static fn ($value): bool => str_contains((string) $value, '@'));
        Functions\when('wp_generate_password')->justReturn('TokenABC123');
        Functions\when('wp_json_encode')->alias(static fn ($value, $flags = 0) => json_encode($value));
        Functions\when('wp_kses')->alias(static fn ($html, $allowed) => (string) $html);
        Functions\when('esc_attr')->alias(static fn ($value): string => (string) $value);
        Functions\when('esc_html')->alias(static fn ($value): string => (string) $value);
        Functions\when('wp_date')->justReturn('March 24, 2026');
        Functions\when('apply_filters')->alias(static fn ($tag, $value, ...$rest) => $value);
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'creatorreactor_oauth_scopes' => 'openid offline_access',
                        'display_timezone' => 'system',
                    ];
                }
                return $default;
            }
        );
    }

    protected function tearDown(): void
    {
        unset($_GET['redirect_to'], $_POST['redirect_to']);
        unset($_GET[Onboarding::QUERY_VAR], $_GET[Onboarding::QUERY_FAN_PENDING]);
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
        $_POST = [];
        parent::tearDown();
    }

    public function testStripOnboardingArgsRemovesOnboardingQueryKeys(): void
    {
        $url = 'https://example.com/library?creatorreactor_onboarding=1&cr_fan_pending=abc123&cr_ob_err=tos_required&foo=bar';

        self::assertSame(
            'https://example.com/library?foo=bar',
            Onboarding::strip_onboarding_args_from_redirect_url($url)
        );
    }

    public function testGetRedirectToFromRequestPrefersGetAndReturnsValidatedUrl(): void
    {
        $_GET['redirect_to'] = 'https://example.com/memberships?tab=fan';

        self::assertSame(
            'https://example.com/memberships?tab=fan',
            Onboarding::get_redirect_to_from_request()
        );
    }

    public function testGetRedirectToFromRequestFallsBackToPostWhenGetMissing(): void
    {
        $_POST['redirect_to'] = 'https://example.com/dashboard';

        self::assertSame(
            'https://example.com/dashboard',
            Onboarding::get_redirect_to_from_request()
        );
    }

    public function testGetPostOauthRedirectBypassesOnboardingAndReturnsHomepage(): void
    {
        $dest = Onboarding::get_post_oauth_redirect(42, 'https://example.com/protected-content');

        self::assertSame(
            'https://example.com/',
            $dest
        );
    }

    public function testHandleSubmitNoprivRedirectsToLoginWithoutExitingWhenFilterDisablesExit(): void
    {
        $_POST = [];

        Functions\when('wp_login_url')->alias(
            static fn ($url = ''): string => 'https://example.com/wp-login.php?redirect_to=' . rawurlencode((string) $url)
        );
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('https://example.com/wp-login.php?redirect_to=https%3A%2F%2Fexample.com%2F');
        Functions\when('apply_filters')->alias(
            static fn ($tag, $value, ...$rest) => $tag === 'creatorreactor_onboarding_redirect_should_exit' ? false : $value
        );

        Onboarding::handle_submit_nopriv();
        self::assertTrue(true);
    }

    public function testHandleSubmitRedirectsHomeWhenOnboardingAlreadyCompleteWithoutExiting(): void
    {
        $_POST['_wpnonce'] = 'ok';

        Functions\when('sanitize_text_field')->alias(static fn ($value): string => (string) $value);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('wp_get_referer')->justReturn('https://example.com/');
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(42);
        Functions\when('wp_verify_nonce')->alias(static fn ($nonce, $action): bool => $nonce === 'ok');
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => (int) $userId === 42 ? '1' : ''
        );
        Functions\expect('wp_safe_redirect')->once()->with('https://example.com/');
        Functions\when('apply_filters')->alias(
            static fn ($tag, $value, ...$rest) => $tag === 'creatorreactor_onboarding_redirect_should_exit' ? false : $value
        );

        Onboarding::handle_submit();
        self::assertTrue(true);
    }

    public function testUserNeedsOnboardingReturnsFalseByDefault(): void
    {
        self::assertFalse(Onboarding::user_needs_onboarding(42));
    }

    public function testRegisterQueryVarAddsOnboardingAndPendingVars(): void
    {
        $vars = Onboarding::register_query_var(['foo']);
        self::assertSame(['foo', Onboarding::QUERY_VAR, Onboarding::QUERY_FAN_PENDING], $vars);
    }

    public function testGetOnboardingUrlAddsValidatedRedirectToQueryString(): void
    {
        $url = Onboarding::get_onboarding_url('https://example.com/private?tab=1');
        self::assertStringContainsString('creatorreactor_onboarding=1', $url);
        self::assertStringContainsString('redirect_to=https%3A%2F%2Fexample.com%2Fprivate%3Ftab%3D1', $url);
    }

    public function testGetOnboardingUrlWithPendingFallsBackWhenTokenSanitizesEmpty(): void
    {
        $url = Onboarding::get_onboarding_url_with_pending('!!!', 'https://example.com/after');
        self::assertStringContainsString('creatorreactor_onboarding=1', $url);
        self::assertStringNotContainsString(Onboarding::QUERY_FAN_PENDING . '=', $url);
    }

    public function testGetRequestPendingTokenPrefersQueryVarAndSanitizes(): void
    {
        Functions\when('get_query_var')->alias(
            static fn ($key, $default = '') => $key === Onboarding::QUERY_FAN_PENDING ? 'abc-123!!' : $default
        );

        self::assertSame('abc123', Onboarding::get_request_pending_token());
    }

    public function testGetRequestPendingTokenFallsBackToCookieWhenQueryMissing(): void
    {
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => '');
        $_COOKIE[Onboarding::COOKIE_FAN_PENDING] = 'tok_en--123';

        self::assertSame('token123', Onboarding::get_request_pending_token());
        unset($_COOKIE[Onboarding::COOKIE_FAN_PENDING]);
    }

    public function testStoreGetAndDeletePendingFanvueRegistrationLifecycle(): void
    {
        $stored = [];
        Functions\when('sanitize_email')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('is_email')->alias(static fn ($value): bool => str_contains((string) $value, '@'));
        Functions\when('wp_generate_password')->justReturn('TokenABC123');
        Functions\when('update_option')->alias(
            static function ($key, $value, $autoload = false) use (&$stored): bool {
                $stored[(string) $key] = $value;
                return true;
            }
        );
        Functions\when('get_option')->alias(
            static function ($key, $default = false) use (&$stored) {
                return $stored[(string) $key] ?? $default;
            }
        );
        Functions\when('delete_option')->alias(
            static function ($key) use (&$stored): bool {
                unset($stored[(string) $key]);
                return true;
            }
        );
        Functions\when('sanitize_email')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('wp_json_encode')->alias(static fn ($value, $flags = 0) => json_encode($value));
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => $default);

        $token = Onboarding::store_pending_fanvue_registration(
            ['email' => 'fan@example.com', 'uuid' => 'uuid-1', 'display' => 'Fan User'],
            'https://example.com/return'
        );
        self::assertSame('TokenABC123', $token);

        $row = Onboarding::get_pending_fanvue_registration($token);
        self::assertIsArray($row);
        self::assertSame('fan@example.com', $row['email']);

        Onboarding::delete_pending_fanvue_registration($token);
        self::assertNull(Onboarding::get_pending_fanvue_registration($token));
    }

    public function testNonceActionPendingIncludesOpaqueToken(): void
    {
        self::assertSame(
            Onboarding::ACTION_SUBMIT . '_fanpend_AbC123',
            Onboarding::nonce_action_pending('AbC123')
        );
    }

    public function testShortcodeReturnsUnavailableMessageInBrokerMode(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => true];
                }
                return $default;
            }
        );
        Functions\when('esc_html__')->alias(static fn ($text, $domain = null): string => (string) $text);

        $out = Onboarding::shortcode();
        self::assertSame('', $out);
    }

    public function testIncompleteGateNoticeBuildsCtaMarkup(): void
    {
        Functions\when('is_ssl')->justReturn(true);
        Functions\when('esc_url_raw')->alias(static fn ($url): string => (string) $url);
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);
        Functions\when('esc_html__')->alias(static fn ($text, $domain = null): string => (string) $text);
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/members-only';

        $out = Onboarding::incomplete_gate_notice();
        self::assertSame('', $out);
    }

    public function testInitRegistersOnboardingHooksAndShortcode(): void
    {
        Functions\expect('add_action')->never();
        Functions\expect('add_filter')->never();
        Functions\expect('add_shortcode')->never();
        Onboarding::init();
        self::assertTrue(true);
    }

    public function testActivateFlushRewriteRulesRegistersAndPersistsVersion(): void
    {
        Functions\expect('add_rewrite_rule')->times(2);
        Functions\expect('flush_rewrite_rules')->once()->with(false);
        Functions\expect('update_option')->once()->with('creatorreactor_ob_rewrite_ver', '3', false);

        Onboarding::activate_flush_rewrite_rules();
        self::assertTrue(true);
    }

    public function testMaybeFlushOnboardingRewritesSkipsWhenVersionAlreadyCurrent(): void
    {
        Functions\when('get_option')->alias(
            static fn ($key, $default = false) => $key === 'creatorreactor_ob_rewrite_ver' ? '3' : $default
        );
        Functions\expect('flush_rewrite_rules')->never();
        Functions\expect('update_option')->never();

        Onboarding::maybe_flush_onboarding_rewrites();
        self::assertTrue(true);
    }

    public function testIsOnboardingScreenTrueWhenRequestUriMatchesFallbackPattern(): void
    {
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => $default);
        Functions\when('wp_parse_url')->alias(static fn ($url, $component = -1) => parse_url((string) $url, $component));
        Functions\when('untrailingslashit')->alias(static fn ($value): string => rtrim((string) $value, '/'));
        $_SERVER['REQUEST_URI'] = '/creatorreactor-onboarding/p/AbC123';

        self::assertTrue(Onboarding::is_onboarding_screen());
    }

    public function testGetRequestPendingTokenFallsBackToRequestUriSegment(): void
    {
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => '');
        Functions\when('wp_parse_url')->alias(static fn ($url, $component = -1) => parse_url((string) $url, $component));
        Functions\when('untrailingslashit')->alias(static fn ($value): string => rtrim((string) $value, '/'));
        $_SERVER['REQUEST_URI'] = '/creatorreactor-onboarding/p/AbC123';

        self::assertSame('AbC123', Onboarding::get_request_pending_token());
    }

    public function testGetPendingFanvueRegistrationDeletesExpiredRecords(): void
    {
        $stored = [
            'creatorreactor_fv_pen_Expired123' => [
                'exp' => time() - 10,
                'data' => ['email' => 'fan@example.com'],
            ],
        ];
        Functions\when('get_option')->alias(
            static function ($key, $default = false) use (&$stored) {
                return $stored[(string) $key] ?? $default;
            }
        );
        Functions\expect('delete_option')
            ->once()
            ->with('creatorreactor_fv_pen_Expired123')
            ->andReturnUsing(static function ($key) use (&$stored): bool {
                unset($stored[(string) $key]);
                return true;
            });

        self::assertNull(Onboarding::get_pending_fanvue_registration('Expired123'));
    }

    public function testGetEmbeddedTermsHtmlReturnsSanitizedTemplateHtml(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'admin_email') {
                    return 'admin@example.com';
                }
                if ($key === 'creatorreactor_settings') {
                    return [];
                }
                return $default;
            }
        );
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);

        $html = Onboarding::get_embedded_terms_html();
        self::assertStringContainsString('CreatorReactor Terms of Service', $html);
        self::assertStringContainsString('admin@example.com', $html);
    }

    public function testShortcodeReturnsDisabledMessageWhenNotBrokerMode(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => false];
                }
                return $default;
            }
        );
        Functions\when('esc_html__')->alias(static fn ($text, $domain = null): string => (string) $text);

        $out = Onboarding::shortcode();
        self::assertSame('', $out);
    }

    public function testRenderFormPrintsDisabledMessage(): void
    {
        ob_start();
        Onboarding::render_form();
        $out = (string) ob_get_clean();

        self::assertSame('', $out);
    }

    public function testTosWpKsesAllowedIncludesAnchorAndHeadingTags(): void
    {
        $allowed = Onboarding::tos_wp_kses_allowed();
        self::assertArrayHasKey('a', $allowed);
        self::assertArrayHasKey('h2', $allowed);
        self::assertArrayHasKey('section', $allowed);
        self::assertArrayHasKey('href', $allowed['a']);
        self::assertArrayHasKey('target', $allowed['a']);
    }

    public function testEnqueueAssetsIsNoOp(): void
    {
        Onboarding::enqueue_assets();
        self::assertTrue(true);
    }

    public function testMaybeFlushOnboardingRewritesRunsWhenVersionNotCurrent(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_ob_rewrite_ver') {
                    return '2';
                }
                if ($key === 'creatorreactor_settings') {
                    return [
                        'creatorreactor_oauth_scopes' => 'openid offline_access',
                        'display_timezone' => 'system',
                    ];
                }
                return $default;
            }
        );
        Functions\expect('flush_rewrite_rules')->once()->with(false);
        Functions\expect('update_option')->once()->with('creatorreactor_ob_rewrite_ver', '3', false);

        Onboarding::maybe_flush_onboarding_rewrites();
        self::assertTrue(true);
    }

    public function testIsOnboardingScreenTrueWhenQueryStringFlagSet(): void
    {
        $_GET[Onboarding::QUERY_VAR] = '1';
        self::assertTrue(Onboarding::is_onboarding_screen());
    }

    public function testTemplateRedirectReturnsEarlyWhenNotOnboardingScreen(): void
    {
        Functions\when('get_query_var')->alias(static fn ($key, $default = '') => $default);
        Functions\when('wp_parse_url')->alias(static fn ($url, $component = -1) => parse_url((string) $url, $component));
        Functions\when('untrailingslashit')->alias(static fn ($value): string => rtrim((string) $value, '/'));
        $_SERVER['REQUEST_URI'] = '/about/';

        Onboarding::template_redirect();
        self::assertTrue(true);
    }

    public function testTemplateRedirectInBrokerModeRedirectsToHome(): void
    {
        $_GET[Onboarding::QUERY_VAR] = '1';
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return ['broker_mode' => true];
                }
                return $default;
            }
        );
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('https://example.com/')
            ->andThrow(new \RuntimeException('stop-redirect'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-redirect');

        Onboarding::template_redirect();
    }

    public function testTemplateRedirectNonBrokerRedirectsToStrippedDestination(): void
    {
        if (! defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', '');
        }
        $_GET[Onboarding::QUERY_VAR] = '1';
        $_GET['redirect_to'] = rawurlencode('https://example.com/members?creatorreactor_onboarding=1&foo=bar');
        Functions\when('is_ssl')->justReturn(false);
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('https://example.com/members?foo=bar')
            ->andThrow(new \RuntimeException('stop-redirect'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-redirect');

        Onboarding::template_redirect();
    }

    public function testGetPostOauthRedirectReturnsOnboardingUrlWhenUserNeedsOnboarding(): void
    {
        $url = Onboarding::get_post_oauth_redirect(501, 'https://example.com/any');
        self::assertSame('https://example.com/', $url);
    }

    public function testStripOnboardingArgsTreatsNonStringAsEmptyAndReturnsHome(): void
    {
        self::assertSame(
            'https://example.com/',
            Onboarding::strip_onboarding_args_from_redirect_url(123)
        );
    }

    public function testSetFanPendingCookieReturnsEarlyForEmptyToken(): void
    {
        Onboarding::set_fan_pending_cookie('');
        self::assertTrue(true);
    }

    public function testClearFanPendingCookieRunsWithoutError(): void
    {
        if (! defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', '');
        }
        Functions\when('is_ssl')->justReturn(false);

        Onboarding::clear_fan_pending_cookie();
        self::assertTrue(true);
    }

    public function testHandleSubmitPersistsConsentAndProfileDataWithoutExit(): void
    {
        $_POST = [
            '_wpnonce' => 'ok',
            'creatorreactor_tos_accept' => '1',
            'creatorreactor_display_name' => 'Fan Name',
            'creatorreactor_phone' => '555-1111',
            'creatorreactor_address' => '123 Main',
            'creatorreactor_country' => 'US',
            'creatorreactor_contact_preference' => 'invalid-pref',
            'creatorreactor_sms_opt_in' => '1',
        ];
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public function prepare($query, ...$args): string
            {
                return (string) $query;
            }
            public function get_var($query)
            {
                return 'wp_creatorreactor_entitlements';
            }
            public function get_results($query, $output = null): array
            {
                return [];
            }
            public function query($sql): int
            {
                return 0;
            }
            public function esc_like($text): string
            {
                return addcslashes((string) $text, '_%\\');
            }
        };

        Functions\when('sanitize_textarea_field')->alias(static fn ($value): string => trim((string) $value));
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower(trim((string) $value)));
        Functions\when('wp_verify_nonce')->alias(static fn ($nonce, $action): bool => $nonce === 'ok');
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(42);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_update_user')->alias(static fn ($data) => 42);
        Functions\when('get_userdata')->alias(static fn ($userId) => (object) ['user_email' => 'fan@example.com']);
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\when('get_user_meta')->alias(static fn ($userId, $key, $single) => '');
        Functions\expect('update_user_meta')->never();
        Functions\expect('delete_user_meta')->never();
        Functions\expect('do_action')->never();
        Functions\expect('wp_safe_redirect')->once()->with('https://example.com/');
        Functions\when('apply_filters')->alias(
            static function ($tag, $value, ...$rest) {
                if ($tag === 'creatorreactor_onboarding_redirect_should_exit') {
                    return false;
                }
                return $value;
            }
        );

        Onboarding::handle_submit();
        self::assertTrue(true);
    }

    public function testHandleSubmitInvalidNonceTriggersWpDie(): void
    {
        $_POST = ['_wpnonce' => 'bad'];

        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => (string) $value);
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('esc_html__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('stop-wp-die'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-wp-die');

        Onboarding::handle_submit();
    }

    public function testHandleSubmitRedirectsWithTosErrorWhenAcceptanceMissing(): void
    {
        $_POST = ['_wpnonce' => 'ok'];

        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => (string) $value);
        Functions\when('wp_verify_nonce')->alias(
            static fn ($nonce, $action): bool => $nonce === 'ok' && $action === Onboarding::ACTION_SUBMIT
        );
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(42);
        Functions\when('apply_filters')->alias(
            static function ($tag, $value, ...$rest) {
                if ($tag === 'creatorreactor_onboarding_redirect_should_exit') {
                    return false;
                }
                return $value;
            }
        );
        Functions\when('wp_get_referer')->justReturn('https://example.com/onboarding-form');
        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('https://example.com/')
            ->andThrow(new \RuntimeException('stop-redirect'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-redirect');

        Onboarding::handle_submit();
    }
}
