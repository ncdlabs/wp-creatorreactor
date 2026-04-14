<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

require_once dirname( __DIR__, 2 ) . '/includes/class-gate-frontend-output.php';

use CreatorReactor\Gate_Frontend_Output;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class GateFrontendOutputContainerLogicTest extends TestCase {

	private static function container_should_show_content( array $markers ): bool {
		$ref = new ReflectionClass( Gate_Frontend_Output::class );
		$m   = $ref->getMethod( 'container_should_show_content' );
		$m->setAccessible( true );

		return (bool) $m->invoke( null, $markers );
	}

	public function test_single_marker_and_requires_match_one(): void {
		self::assertTrue( self::container_should_show_content( [ [ 'match' => '1', 'logic' => 'and' ] ] ) );
		self::assertFalse( self::container_should_show_content( [ [ 'match' => '0', 'logic' => 'and' ] ] ) );
	}

	public function test_multi_marker_and_uses_any_match_semantics(): void {
		$markers = [
			[ 'match' => '0', 'logic' => 'and' ],
			[ 'match' => '1', 'logic' => 'and' ],
		];
		self::assertTrue( self::container_should_show_content( $markers ) );
	}

	public function test_multi_marker_and_all_fail_strips(): void {
		$markers = [
			[ 'match' => '0', 'logic' => 'and' ],
			[ 'match' => '0', 'logic' => 'and' ],
		];
		self::assertFalse( self::container_should_show_content( $markers ) );
	}

	public function test_or_logic_unchanged(): void {
		self::assertTrue( self::container_should_show_content( [
			[ 'match' => '0', 'logic' => 'or' ],
			[ 'match' => '1', 'logic' => 'or' ],
		] ) );
		self::assertFalse( self::container_should_show_content( [
			[ 'match' => '0', 'logic' => 'or' ],
			[ 'match' => '0', 'logic' => 'or' ],
		] ) );
	}
}
