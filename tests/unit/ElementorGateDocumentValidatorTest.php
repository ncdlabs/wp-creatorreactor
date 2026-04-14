<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

require_once dirname( __DIR__, 2 ) . '/includes/class-elementor-gate-document-validator.php';

use CreatorReactor\Elementor_Gate_Document_Validator;
use PHPUnit\Framework\TestCase;

final class ElementorGateDocumentValidatorTest extends TestCase {

	public function test_empty_tree_has_no_violations(): void {
		self::assertSame( [], Elementor_Gate_Document_Validator::scan_elements_tree( [] ) );
	}

	public function test_single_gate_per_container_ok(): void {
		$tree = [
			[
				'id'       => 'c1',
				'elType'   => 'container',
				'elements' => [
					[
						'id'          => 'w1',
						'elType'      => 'widget',
						'widgetType'  => 'creatorreactor_follower',
					],
				],
			],
		];
		self::assertSame( [], Elementor_Gate_Document_Validator::scan_elements_tree( $tree ) );
	}

	public function test_two_gates_same_container_is_violation(): void {
		$tree = [
			[
				'id'       => 'c1',
				'elType'   => 'container',
				'elements' => [
					[
						'id'         => 'w1',
						'elType'     => 'widget',
						'widgetType' => 'creatorreactor_follower',
					],
					[
						'id'         => 'w2',
						'elType'     => 'widget',
						'widgetType' => 'creatorreactor_logged_out',
					],
				],
			],
		];
		$v = Elementor_Gate_Document_Validator::scan_elements_tree( $tree );
		self::assertCount( 1, $v );
		self::assertSame( 'c1', $v[0]['id'] );
		self::assertSame( 2, $v[0]['gates'] );
	}

	public function test_fanvue_oauth_does_not_count_as_gate(): void {
		$tree = [
			[
				'id'       => 'c1',
				'elType'   => 'container',
				'elements' => [
					[
						'id'         => 'w1',
						'elType'     => 'widget',
						'widgetType' => 'creatorreactor_logged_out',
					],
					[
						'id'         => 'w2',
						'elType'     => 'widget',
						'widgetType' => 'creatorreactor_fanvue_oauth',
					],
				],
			],
		];
		self::assertSame( [], Elementor_Gate_Document_Validator::scan_elements_tree( $tree ) );
	}
}
