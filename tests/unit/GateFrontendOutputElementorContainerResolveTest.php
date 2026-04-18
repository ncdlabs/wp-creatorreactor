<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

require_once dirname( __DIR__, 2 ) . '/includes/class-gate-frontend-output.php';

use CreatorReactor\Gate_Frontend_Output;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GateFrontendOutputElementorContainerResolveTest extends TestCase
{
    private static function resolve_elementor_container_for_wrapper_gate( \DOMElement $widget ): ?\DOMElement {
        $ref = new ReflectionClass( Gate_Frontend_Output::class );
        $m   = $ref->getMethod( 'resolve_elementor_container_for_wrapper_gate' );
        $m->setAccessible( true );
        $out = $m->invoke( null, $widget );

        return $out instanceof \DOMElement ? $out : null;
    }

    public function test_e_con_full_column_without_e_con_inner(): void
    {
        $html = <<<'HTML'
<div id="wrap">
<div class="elementor-element e-con-full e-flex e-con e-child" id="col">
<div class="elementor-element elementor-widget elementor-widget-creatorreactor_follower" id="gate"><span>inner</span></div>
<div class="elementor-element elementor-widget-heading" id="sib"><h2>leak</h2></div>
</div>
</div>
HTML;

        $dom = new \DOMDocument();
        $dom->encoding = 'UTF-8';
        self::assertTrue(
            $dom->loadHTML(
                '<?xml encoding="utf-8"?><div id="creatorreactor-test-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            )
        );
        $gate = $dom->getElementById( 'gate' );
        self::assertInstanceOf( \DOMElement::class, $gate );
        $container = self::resolve_elementor_container_for_wrapper_gate( $gate );
        self::assertNotNull( $container );
        self::assertSame( 'col', $container->getAttribute( 'id' ) );
    }

    public function test_e_con_inner_still_preferred_over_outer_e_child(): void
    {
        $html = <<<'HTML'
<div class="elementor-element e-con e-child" id="outer">
<div class="e-con-inner" id="inner">
<div class="elementor-element elementor-widget elementor-widget-creatorreactor_follower" id="gate"></div>
<div class="elementor-element elementor-widget-heading" id="sib"></div>
</div>
</div>
HTML;

        $dom = new \DOMDocument();
        $dom->encoding = 'UTF-8';
        self::assertTrue(
            $dom->loadHTML(
                '<?xml encoding="utf-8"?><div id="creatorreactor-test-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            )
        );
        $gate = $dom->getElementById( 'gate' );
        self::assertInstanceOf( \DOMElement::class, $gate );
        $container = self::resolve_elementor_container_for_wrapper_gate( $gate );
        self::assertNotNull( $container );
        self::assertSame( 'inner', $container->getAttribute( 'id' ) );
    }
}
