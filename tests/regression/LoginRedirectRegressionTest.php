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
}
