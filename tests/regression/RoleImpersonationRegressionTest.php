<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Role_Impersonation;
use CreatorReactor\Tests\BaseTestCase;
use Mockery;
use WP_User;

final class RoleImpersonationRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }
        if (! defined('YEAR_IN_SECONDS')) {
            define('YEAR_IN_SECONDS', 86400 * 365);
        }
        if (! defined('CREATORREACTOR_VERSION')) {
            define('CREATORREACTOR_VERSION', 'test-version');
        }
        if (! defined('CREATORREACTOR_PLUGIN_URL')) {
            define('CREATORREACTOR_PLUGIN_URL', 'https://example.com/wp-content/plugins/creatorreactor/');
        }

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
    }

    protected function tearDown(): void
    {
        unset(
            $_GET['elementor-preview'],
            $_POST['role'],
            $_COOKIE[Role_Impersonation::COOKIE_NAME],
            $GLOBALS['wpdb']
        );
        parent::tearDown();
    }

    public function testEnqueueFrontendAssetsIsSkippedDuringElementorPreviewRequest(): void
    {
        $_GET['elementor-preview'] = 'abc123nonce';

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_localize_script')->never();

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue(true);
    }

    public function testEnqueueFrontendAssetsIsSkippedInAdmin(): void
    {
        Functions\when('is_admin')->justReturn(true);

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_localize_script')->never();

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue(true);
    }

    public function testEnqueueFrontendAssetsIsSkippedWhenNotLoggedIn(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_localize_script')->never();

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue(true);
    }

    public function testEnqueueFrontendAssetsIsSkippedWhenUserIsNotAdministratorInCapabilitiesMeta(): void
    {
        $this->stubWpdbBlogPrefix();
        Functions\when('get_user_meta')->alias(
            static fn (int $uid, string $key, bool $single = false): array => $uid === 1 && str_ends_with($key, 'capabilities')
                ? ['editor' => true]
                : []
        );
        $user = new WP_User();
        $user->ID = 1;
        Functions\when('wp_get_current_user')->justReturn($user);

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_localize_script')->never();

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue(true);
    }

    public function testEnqueueFrontendAssetsLocalizesLoggedOutSlugForAdministrator(): void
    {
        $this->stubAdministratorContext(1);
        Functions\when('wp_roles')->justReturn((object) ['roles' => []]);
        Functions\when('get_role')->justReturn(false);
        Functions\when('is_ssl')->justReturn(false);
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('wp_enqueue_script')->justReturn();
        Functions\when('wp_create_nonce')->justReturn('nonce-imp');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-ajax.php');

        Functions\expect('wp_localize_script')
            ->once()
            ->with(
                'creatorreactor-role-impersonation',
                'CreatorReactorRoleImpersonation',
                Mockery::on(static function (array $data): bool {
                    return ($data['loggedOutSlug'] ?? null) === Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG
                        && isset($data['ajaxUrl'], $data['nonce'], $data['roles'], $data['i18n'])
                        && $data['nonce'] === 'nonce-imp';
                })
            );

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue(true);
    }

    public function testDisplayLabelForImpersonationChoiceUsesLoggedOutLabelForPseudoSlug(): void
    {
        self::assertSame(
            'Logged Out',
            Role_Impersonation::display_label_for_impersonation_choice(Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG)
        );
    }

    public function testGetEffectiveRoleSlugsForUserReturnsEmptyWhenImpersonatingLoggedOut(): void
    {
        $this->stubAdministratorContext(1);
        $this->setValidImpersonationCookie(1, Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG);

        $user = new WP_User();
        $user->ID = 1;
        $user->roles = ['administrator'];

        self::assertSame(
            [],
            Role_Impersonation::get_effective_role_slugs_for_user($user)
        );
    }

    public function testEffectiveIsLoggedInForCreatorreactorGatesFalseWhenImpersonatingLoggedOut(): void
    {
        Functions\when('get_current_user_id')->justReturn(1);
        $this->stubAdministratorContext(1);
        $this->setValidImpersonationCookie(1, Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG);

        $user = new WP_User();
        $user->ID = 1;
        Functions\when('get_userdata')->justReturn($user);

        self::assertFalse(Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates());
    }

    public function testAjaxImpersonateRoleCallsWpDieWhenNotAjax(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('stop-wp-die'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-wp-die');

        Role_Impersonation::ajax_impersonate_role();
    }

    public function testAjaxImpersonateRoleSendsErrorWhenNotLoggedIn(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\expect('wp_send_json_error')
            ->once()
            ->with(Mockery::type('array'), 403)
            ->andThrow(new \RuntimeException('stop-json'));

        $this->expectException(\RuntimeException::class);

        Role_Impersonation::ajax_impersonate_role();
    }

    public function testAjaxImpersonateRoleSendsErrorWhenUserIsNotAdministrator(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        $this->stubWpdbBlogPrefix();
        Functions\when('get_user_meta')->alias(
            static fn (int $uid, string $key, bool $single = false): array => []
        );
        $user = new WP_User();
        $user->ID = 2;
        Functions\when('wp_get_current_user')->justReturn($user);

        Functions\expect('wp_send_json_error')
            ->once()
            ->with(Mockery::type('array'), 403)
            ->andThrow(new \RuntimeException('stop-forbidden'));

        $this->expectException(\RuntimeException::class);

        Role_Impersonation::ajax_impersonate_role();
    }

    public function testAjaxImpersonateRoleClearsCookieAndSendsSuccessWhenRoleEmpty(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(true);
        $this->stubAdministratorContext(1);
        $_POST['role'] = '';
        Functions\when('is_ssl')->justReturn(false);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with(
                Mockery::on(static function (array $data): bool {
                    return ($data['impersonating'] ?? null) === false && ($data['role'] ?? null) === '';
                })
            )
            ->andThrow(new \RuntimeException('stop-success'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-success');

        Role_Impersonation::ajax_impersonate_role();
    }

    public function testAjaxImpersonateRoleSendsErrorForInvalidRoleSlug(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(true);
        $this->stubAdministratorContext(1);
        $_POST['role'] = 'subscriber';

        Functions\expect('wp_send_json_error')
            ->once()
            ->with(Mockery::type('array'), 400)
            ->andThrow(new \RuntimeException('stop-invalid'));

        $this->expectException(\RuntimeException::class);

        Role_Impersonation::ajax_impersonate_role();
    }

    public function testAjaxImpersonateRoleAcceptsLoggedOutPseudoRole(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(true);
        $this->stubAdministratorContext(1);
        $_POST['role'] = Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG;

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('wp_salt')->alias(static fn (string $scheme): string => $scheme === 'logged_in' ? 'L' : 'A');

        Functions\expect('wp_send_json_success')
            ->once()
            ->with(
                Mockery::on(static function (array $data): bool {
                    return ($data['impersonating'] ?? null) === true
                        && ($data['role'] ?? null) === Role_Impersonation::IMPERSONATION_LOGGED_OUT_SLUG;
                })
            )
            ->andThrow(new \RuntimeException('stop-success'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop-success');

        Role_Impersonation::ajax_impersonate_role();
    }

    private function stubWpdbBlogPrefix(): void
    {
        $GLOBALS['wpdb'] = new class {
            public function get_blog_prefix(): string
            {
                return 'wp_';
            }
        };
    }

    private function stubAdministratorContext(int $userId): void
    {
        $this->stubWpdbBlogPrefix();
        Functions\when('get_user_meta')->alias(
            static function (int $uid, string $key, bool $single = false) use ($userId): array {
                if ($uid !== $userId || ! str_ends_with($key, 'capabilities')) {
                    return [];
                }

                return ['administrator' => true];
            }
        );
        $user = new WP_User();
        $user->ID = $userId;
        $user->roles = ['administrator'];
        Functions\when('wp_get_current_user')->justReturn($user);
    }

    /**
     * Build a cookie value that {@see Role_Impersonation::get_valid_impersonation_role_for_user()} will accept.
     */
    private function setValidImpersonationCookie(int $userId, string $role): void
    {
        Functions\when('wp_salt')->alias(static fn (string $scheme): string => $scheme === 'logged_in' ? 'L' : 'A');
        $exp = time() + 3600;
        $payload = $userId . '|' . $role . '|' . $exp;
        $keyMaterial = \wp_salt('logged_in') . \wp_salt('auth') . '|creatorreactor_role_imp_v2';
        $sig = hash_hmac('sha256', $payload, $keyMaterial);
        $_COOKIE[Role_Impersonation::COOKIE_NAME] = base64_encode($payload . '|' . $sig);
    }
}
