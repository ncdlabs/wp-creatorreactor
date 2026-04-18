<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

require_once dirname( __DIR__, 2 ) . '/includes/class-nav-menu-gate.php';

use CreatorReactor\Nav_Menu_Gate;
use CreatorReactor\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NavMenuGateTest extends BaseTestCase
{
    /**
     * @return iterable<string, array{0: list<string>, 1: bool, 2: bool, 3: bool, 4: bool}>
     */
    public static function visibilityCases(): iterable
    {
        yield 'no_gate_classes_guest' => [ [ 'menu-item' ], false, false, false, true ];

        yield 'follower_class_guest' => [
            [ Nav_Menu_Gate::CLASS_FOLLOWER ],
            false,
            false,
            false,
            false,
        ];

        yield 'subscriber_class_guest' => [
            [ Nav_Menu_Gate::CLASS_SUBSCRIBER ],
            false,
            false,
            false,
            false,
        ];

        yield 'logged_in_class_guest' => [
            [ Nav_Menu_Gate::CLASS_LOGGED_IN ],
            false,
            false,
            false,
            false,
        ];

        yield 'follower_class_subscriber_user' => [
            [ Nav_Menu_Gate::CLASS_FOLLOWER ],
            true,
            true,
            false,
            false,
        ];

        yield 'follower_class_follower_exclusive' => [
            [ Nav_Menu_Gate::CLASS_FOLLOWER ],
            true,
            false,
            true,
            true,
        ];

        yield 'subscriber_class_follower_exclusive' => [
            [ Nav_Menu_Gate::CLASS_SUBSCRIBER ],
            true,
            false,
            true,
            false,
        ];

        yield 'subscriber_class_subscriber' => [
            [ Nav_Menu_Gate::CLASS_SUBSCRIBER ],
            true,
            true,
            false,
            true,
        ];

        yield 'follower_or_subscriber_classes_subscriber' => [
            [ Nav_Menu_Gate::CLASS_FOLLOWER, Nav_Menu_Gate::CLASS_SUBSCRIBER ],
            true,
            true,
            false,
            true,
        ];

        yield 'follower_or_subscriber_classes_follower_exclusive' => [
            [ Nav_Menu_Gate::CLASS_FOLLOWER, Nav_Menu_Gate::CLASS_SUBSCRIBER ],
            true,
            false,
            true,
            true,
        ];

        yield 'logged_in_any_tier' => [
            [ Nav_Menu_Gate::CLASS_LOGGED_IN ],
            true,
            false,
            false,
            true,
        ];
    }

    /**
     * @param list<string> $classes
     */
    #[DataProvider('visibilityCases')]
    public function test_item_visible_for_creatorreactor_nav_classes(
        array $classes,
        bool $eff_in,
        bool $has_sub,
        bool $has_fol_exclusive,
        bool $expected
    ): void {
        self::assertSame(
            $expected,
            Nav_Menu_Gate::item_visible_for_creatorreactor_nav_classes(
                $classes,
                $eff_in,
                $has_sub,
                $has_fol_exclusive
            )
        );
    }
}
