<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

require_once dirname( __DIR__, 2 ) . '/includes/class-role-impersonation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-gate-frontend-output.php';

use Brain\Monkey\Functions;
use CreatorReactor\Gate_Frontend_Output;
use CreatorReactor\Tests\BaseTestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression: guest main strip must clear Elementor e-con-inner columns that lack Logged out widget.
 */
final class GateFrontendOutputGuestMainStripTest extends BaseTestCase
{
    private static function strip_guest_main( string $html ): string {
        $ref = new ReflectionClass( Gate_Frontend_Output::class );
        $m   = $ref->getMethod( 'strip_guest_main_inners_without_logged_out_gate' );
        $m->setAccessible( true );

        return (string) $m->invoke( null, $html );
    }

    public function test_guest_strip_removes_nav_inner_when_logged_out_column_is_sibling(): void
    {
        Functions\when( 'is_user_logged_in' )->justReturn( false );
        Functions\when( 'get_current_user_id' )->justReturn( 0 );
        Functions\when( 'apply_filters' )->alias(
            static function ( string $tag, $value = null, ...$args ) {
                return $value;
            }
        );

        $html = <<<'HTML'
<main id="content" class="site-main">
<div class="e-con-inner"></div>
<div class="e-con-inner">
<div class="elementor-widget elementor-widget-creatorreactor_logged_out"></div>
<p>logged out copy</p>
</div>
<div class="e-con-inner">
<div class="elementor-widget elementor-widget-nav-menu">NAV_SHOULD_GO</div>
</div>
</main>
HTML;

        $out = self::strip_guest_main( $html );
        self::assertStringContainsString( 'logged out copy', $out );
        self::assertStringNotContainsString( 'NAV_SHOULD_GO', $out );
    }

    /**
     * Mirrors Elementor {@see 'elementor/frontend/the_content'}: fragment without theme {@see main}.
     */
    public function test_guest_strip_removes_nav_inner_without_main_wrapper(): void
    {
        Functions\when( 'is_user_logged_in' )->justReturn( false );
        Functions\when( 'get_current_user_id' )->justReturn( 0 );
        Functions\when( 'apply_filters' )->alias(
            static function ( string $tag, $value = null, ...$args ) {
                return $value;
            }
        );

        $html = <<<'HTML'
<div data-elementor-type="wp-page" class="elementor elementor-525">
<div class="e-con-inner"></div>
<div class="e-con-inner">
<div class="elementor-widget elementor-widget-creatorreactor_logged_out"></div>
<p>logged out copy</p>
</div>
<div class="e-con-inner">
<div class="elementor-widget elementor-widget-nav-menu">NAV_SHOULD_GO</div>
</div>
</div>
HTML;

        $out = self::strip_guest_main( $html );
        self::assertStringContainsString( 'logged out copy', $out );
        self::assertStringNotContainsString( 'NAV_SHOULD_GO', $out );
    }
}
