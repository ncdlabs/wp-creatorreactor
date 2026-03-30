<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Role_Impersonation;
use CreatorReactor\Tests\BaseTestCase;

final class RoleImpersonationRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'is_user_logged_in' )->justReturn( true );
    }

    protected function tearDown(): void
    {
        unset( $_GET['elementor-preview'] );
        parent::tearDown();
    }

    public function testEnqueueFrontendAssetsIsSkippedDuringElementorPreviewRequest(): void
    {
        // Elementor preview URL shape used by `Editor_Context::is_elementor_preview_request()`.
        $_GET['elementor-preview'] = 'abc123nonce';

        Functions\expect( 'wp_enqueue_style' )->never();
        Functions\expect( 'wp_enqueue_script' )->never();
        Functions\expect( 'wp_localize_script' )->never();

        Role_Impersonation::enqueue_frontend_assets();

        self::assertTrue( true );
    }
}

