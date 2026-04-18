<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

/**
 * End-to-end regression coverage for {@see Gate_Frontend_Output::strip_unauthorized_gated_regions()}.
 *
 * Prevents repeat of: (1) Elementor fragments without {@see main} skipping guest logged-out column strip,
 * (2) {@see e_con_full} / flex columns without {@see e_con_inner} leaking sibling widgets next to gate widgets.
 *
 * Run via: composer run test:unit
 */
require_once dirname( __DIR__, 2 ) . '/includes/class-role-impersonation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-editor-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-creatorreactor-shortcodes.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-gate-frontend-output.php';

use Brain\Monkey\Functions;
use CreatorReactor\Gate_Frontend_Output;
use CreatorReactor\Tests\BaseTestCase;

final class GateFrontendOutputStripEndToEndRegressionTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        unset( $_GET['elementor-preview'] );
        parent::tearDown();
    }

    private static function stub_front_gate_strip_context(): void
    {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'wp_doing_ajax' )->justReturn( false );
        Functions\when( 'wp_is_json_request' )->justReturn( false );
        Functions\when( 'is_user_logged_in' )->justReturn( false );
        Functions\when( 'get_current_user_id' )->justReturn( 0 );
        Functions\when( 'sanitize_key' )->alias( static fn ( $v ): string => strtolower( (string) $v ) );
        Functions\when( '__' )->alias( static fn ( $t, $d = null ): string => (string) $t );
        Functions\when( 'apply_filters' )->alias(
            static function ( $tag, $value = null, ...$args ) {
                return $value;
            }
        );
        Functions\when( 'do_shortcode' )->alias( static fn ( $c ): string => (string) $c );
    }

    public function test_guest_sees_no_sibling_widgets_in_e_con_full_follower_column(): void
    {
        self::stub_front_gate_strip_context();

        $html = <<<'HTML'
<div data-elementor-type="wp-page" class="elementor elementor-197">
<div class="elementor-element e-con-full e-flex e-con e-child" data-id="tier-col">
<div class="elementor-element elementor-widget elementor-widget-creatorreactor_follower" data-widget_type="creatorreactor_follower.default"></div>
<div class="elementor-element elementor-widget elementor-widget-heading" data-id="leak">
<h2 class="elementor-heading-title">GATED_HEADING_LEAK</h2>
</div>
</div>
</div>
HTML;

        $out = Gate_Frontend_Output::strip_unauthorized_gated_regions( $html );
        self::assertStringNotContainsString( 'GATED_HEADING_LEAK', $out );
        self::assertStringContainsString( 'data-id="tier-col"', $out );
        self::assertStringNotContainsString( 'elementor-widget-heading', $out );
    }

    public function test_guest_logged_out_strip_clears_non_logged_out_e_con_inner_in_elementor_fragment_without_main(): void
    {
        self::stub_front_gate_strip_context();

        $html = <<<'HTML'
<div data-elementor-type="wp-page" class="elementor elementor-525">
<div class="e-con-inner"><div class="elementor-widget elementor-widget-creatorreactor_logged_out"></div><p>KEEP_LOGGED_OUT_COPY</p></div>
<div class="e-con-inner"><div class="elementor-widget elementor-widget-nav-menu">NAV_COLUMN_LEAK</div></div>
</div>
HTML;

        $out = Gate_Frontend_Output::strip_unauthorized_gated_regions( $html );
        self::assertStringContainsString( 'KEEP_LOGGED_OUT_COPY', $out );
        self::assertStringNotContainsString( 'NAV_COLUMN_LEAK', $out );
    }
}
