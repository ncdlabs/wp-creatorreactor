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

        Functions\when('home_url')->alias(
            static fn ($path = '/'): string => 'https://example.com' . $path
        );
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
            static function ($key, $value, $url): string {
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
    }

    protected function tearDown(): void
    {
        unset($_GET['redirect_to'], $_POST['redirect_to']);
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

    public function testGetPostOauthRedirectSendsIncompleteLinkedUserToOnboardingWithRedirect(): void
    {
        $dest = Onboarding::get_post_oauth_redirect(42, 'https://example.com/protected-content');

        self::assertSame(
            'https://example.com/?creatorreactor_onboarding=1&redirect_to=https%3A%2F%2Fexample.com%2Fprotected-content',
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
            ->with('https://example.com/wp-login.php?redirect_to=https%3A%2F%2Fexample.com%2F%3Fcreatorreactor_onboarding%3D1');
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
}
