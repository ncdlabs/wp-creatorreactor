<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Shortcodes;
use CreatorReactor\Tests\BaseTestCase;

final class ShortcodesWpdbStub
{
    public string $prefix = 'wp_';
    private array $results;
    private $row;

    public function __construct(array $results, $row)
    {
        $this->results = $results;
        $this->row = $row;
    }

    public function prepare($query, $args = null)
    {
        return (string) $query;
    }

    public function get_var($query)
    {
        return 'wp_creatorreactor_entitlements';
    }

    public function get_results($query, $output = null)
    {
        return $this->results;
    }

    public function get_row($query)
    {
        return $this->row;
    }

    public function esc_like($text): string
    {
        return addcslashes((string) $text, '_%\\');
    }
}

final class ShortcodesRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('sanitize_key')->alias(
            static fn ($value): string => strtolower(trim((string) $value))
        );
    }

    private function mockWpdb(array $results = [], $row = null): void
    {
        $GLOBALS['wpdb'] = new ShortcodesWpdbStub($results, $row);
    }

    public function testRegisterAddsExpectedShortcodeTags(): void
    {
        $expect = [
            'follower'               => 'follower',
            'subscriber'             => 'subscriber',
            'logged_out'             => 'logged_out',
            'logged_in'              => 'logged_in',
            'logged_in_no_role'      => 'logged_in_no_role',
            'has_tier'               => 'has_tier',
            'onboarding_incomplete'  => 'onboarding_incomplete',
            'onboarding_complete'    => 'onboarding_complete',
            'fanvue_connected'       => 'fanvue_connected',
            'fanvue_not_connected'   => 'fanvue_not_connected',
            'fanvue_login_button'    => 'fanvue_oauth',
        ];

        foreach ($expect as $tag => $method) {
            Functions\expect('add_shortcode')
                ->once()
                ->with($tag, [Shortcodes::class, $method]);
        }

        Shortcodes::register();
        self::assertCount(11, $expect);
    }

    public function testLoggedOutRendersOnlyForGuests(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\expect('do_shortcode')->once()->with('guest content')->andReturn('rendered guest content');

        self::assertSame('rendered guest content', Shortcodes::logged_out([], 'guest content'));
    }

    public function testLoggedOutReturnsEmptyWhenUserIsLoggedIn(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\expect('do_shortcode')->never();

        self::assertSame('', Shortcodes::logged_out([], 'guest content'));
    }

    public function testLoggedInRendersOnlyForAuthenticatedUsers(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\expect('do_shortcode')->once()->with('member content')->andReturn('rendered member content');

        self::assertSame('rendered member content', Shortcodes::logged_in([], 'member content'));
    }

    public function testFanvueConnectedRendersOnlyWhenUserMetaLinked(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(99);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $userId === 99 ? '1' : ''
        );
        Functions\expect('do_shortcode')->once()->with('connected content')->andReturn('rendered connected content');

        self::assertSame('rendered connected content', Shortcodes::fanvue_connected([], 'connected content'));
    }

    public function testFanvueNotConnectedRendersOnlyWhenUserMetaMissing(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(99);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => ''
        );
        Functions\expect('do_shortcode')->once()->with('not connected content')->andReturn('rendered not connected content');

        self::assertSame('rendered not connected content', Shortcodes::fanvue_not_connected([], 'not connected content'));
    }

    public function testFollowerReturnsOnboardingGateNoticeWhenUserNeedsOnboarding(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(55);
        Functions\when('get_user_meta')->alias(
            static function ($userId, $key, $single) {
                if ($userId !== 55) {
                    return '';
                }
                if ($key === 'creatorreactor_onboarding_complete') {
                    return '';
                }
                if ($key === 'creatorreactor_fanvue_oauth_linked') {
                    return '1';
                }
                return '';
            }
        );
        Functions\when('apply_filters')->alias(static fn ($tag, $value, ...$rest) => $value);
        Functions\when('home_url')->alias(static fn ($path = '/') => 'https://example.com' . $path);
        Functions\when('add_query_arg')->alias(
            static fn ($key, $value, $url) => $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value
        );
        Functions\when('wp_validate_redirect')->alias(static fn ($url, $fallback = '') => (string) $url);
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);
        Functions\expect('esc_html__')->atLeast()->once()->andReturnUsing(static fn ($text, $domain = null) => (string) $text);
        Functions\expect('do_shortcode')->never();

        $out = Shortcodes::follower([], 'premium content');
        self::assertStringContainsString('creatorreactor-onboarding-gate-notice', $out);
        self::assertStringContainsString('Complete setup', $out);
    }

    public function testSubscriberReturnsOnboardingGateNoticeWhenUserNeedsOnboarding(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(77);
        Functions\when('get_user_meta')->alias(
            static function ($userId, $key, $single) {
                if ($userId !== 77) {
                    return '';
                }
                if ($key === 'creatorreactor_onboarding_complete') {
                    return '';
                }
                if ($key === 'creatorreactor_fanvue_oauth_linked') {
                    return '1';
                }
                return '';
            }
        );
        Functions\when('apply_filters')->alias(static fn ($tag, $value, ...$rest) => $value);
        Functions\when('home_url')->alias(static fn ($path = '/') => 'https://example.com' . $path);
        Functions\when('add_query_arg')->alias(
            static fn ($key, $value, $url) => $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value
        );
        Functions\when('wp_validate_redirect')->alias(static fn ($url, $fallback = '') => (string) $url);
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);
        Functions\expect('esc_html__')->atLeast()->once()->andReturnUsing(static fn ($text, $domain = null) => (string) $text);
        Functions\expect('do_shortcode')->never();

        $out = Shortcodes::subscriber([], 'subscriber content');
        self::assertStringContainsString('creatorreactor-onboarding-gate-notice', $out);
    }

    public function testHasTierReturnsOnboardingGateNoticeWhenUserNeedsOnboarding(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(88);
        Functions\when('get_user_meta')->alias(
            static function ($userId, $key, $single) {
                if ($userId !== 88) {
                    return '';
                }
                if ($key === 'creatorreactor_onboarding_complete') {
                    return '';
                }
                if ($key === 'creatorreactor_fanvue_oauth_linked') {
                    return '1';
                }
                return '';
            }
        );
        Functions\when('apply_filters')->alias(static fn ($tag, $value, ...$rest) => $value);
        Functions\when('home_url')->alias(static fn ($path = '/') => 'https://example.com' . $path);
        Functions\when('add_query_arg')->alias(
            static fn ($key, $value, $url) => $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value
        );
        Functions\when('wp_validate_redirect')->alias(static fn ($url, $fallback = '') => (string) $url);
        Functions\when('esc_url')->alias(static fn ($url): string => (string) $url);
        Functions\expect('esc_html__')->atLeast()->once()->andReturnUsing(static fn ($text, $domain = null) => (string) $text);
        Functions\expect('do_shortcode')->never();

        $out = Shortcodes::has_tier(['tier' => 'premium', 'product' => 'fanvue'], 'tier content');
        self::assertStringContainsString('creatorreactor-onboarding-gate-notice', $out);
    }

    public function testFollowerRendersContentWhenOnboardingCompleteAndFollowerEntitled(): void
    {
        $this->mockWpdb([['tier' => 'fanvue_follower', 'status' => 'active']]);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(101);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'fan@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\expect('do_shortcode')->once()->with('follower-only')->andReturn('rendered follower-only');

        self::assertSame('rendered follower-only', Shortcodes::follower([], 'follower-only'));
    }

    public function testFollowerReturnsEmptyWhenOnboardingCompleteButNoFollowerEntitlement(): void
    {
        $this->mockWpdb([]);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(102);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'fan@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\expect('do_shortcode')->never();

        self::assertSame('', Shortcodes::follower([], 'follower-only'));
    }

    public function testSubscriberRendersContentWhenOnboardingCompleteAndSubscriberEntitled(): void
    {
        $this->mockWpdb([['tier' => 'fanvue_subscriber_vip', 'status' => 'active']]);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(103);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'sub@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\expect('do_shortcode')->once()->with('subscriber-only')->andReturn('rendered subscriber-only');

        self::assertSame('rendered subscriber-only', Shortcodes::subscriber([], 'subscriber-only'));
    }

    public function testSubscriberReturnsEmptyWhenOnboardingCompleteButNoSubscriberEntitlement(): void
    {
        $this->mockWpdb([]);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(104);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'sub@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\expect('do_shortcode')->never();

        self::assertSame('', Shortcodes::subscriber([], 'subscriber-only'));
    }

    public function testHasTierRendersContentWhenOnboardingCompleteAndTierMatches(): void
    {
        $this->mockWpdb([], (object) ['id' => 1]);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(105);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'tier@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\when('shortcode_atts')->alias(
            static fn ($pairs, $atts, $shortcode = '') => array_merge((array) $pairs, (array) $atts)
        );
        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('sanitize_key')->alias(
            static fn ($value): string => strtolower(trim((string) $value))
        );
        Functions\expect('do_shortcode')->once()->with('tier-only')->andReturn('rendered tier-only');

        self::assertSame(
            'rendered tier-only',
            Shortcodes::has_tier(['tier' => 'premium', 'product' => 'fanvue'], 'tier-only')
        );
    }

    public function testHasTierReturnsEmptyWhenOnboardingCompleteButTierDoesNotMatch(): void
    {
        $this->mockWpdb([], null);

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(106);
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single) => $key === 'creatorreactor_onboarding_complete' ? '1' : ''
        );
        Functions\when('get_userdata')->alias(
            static fn ($userId) => (object) ['user_email' => 'tier@example.com']
        );
        Functions\when('current_time')->justReturn('2026-03-24 00:00:00');
        Functions\when('shortcode_atts')->alias(
            static fn ($pairs, $atts, $shortcode = '') => array_merge((array) $pairs, (array) $atts)
        );
        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('sanitize_key')->alias(
            static fn ($value): string => strtolower(trim((string) $value))
        );
        Functions\expect('do_shortcode')->never();

        self::assertSame(
            '',
            Shortcodes::has_tier(['tier' => 'premium', 'product' => 'fanvue'], 'tier-only')
        );
    }

    public function testLoggedInReturnsEmptyWhenContentIsNullOrEmpty(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\expect('do_shortcode')->never();

        self::assertSame('', Shortcodes::logged_in([], null));
        self::assertSame('', Shortcodes::logged_in([], ''));
    }

    public function testLoggedOutSupportsNestedShortcodeContentRendering(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\expect('do_shortcode')
            ->once()
            ->with('[inner_shortcode foo="bar"]nested[/inner_shortcode]')
            ->andReturn('rendered nested output');

        self::assertSame(
            'rendered nested output',
            Shortcodes::logged_out([], '[inner_shortcode foo="bar"]nested[/inner_shortcode]')
        );
    }
}
