<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Entitlements;
use CreatorReactor\Tests\BaseTestCase;

final class EntitlementsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('sanitize_key')->alias(
            static fn ($value): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value))
        );
        Functions\when('__')->alias(
            static fn ($text, $domain = null): string => (string) $text
        );
    }

    public function testNormalizeProductFallsBackToFanvueForEmptyInput(): void
    {
        self::assertSame('fanvue', Entitlements::normalize_product(''));
        self::assertSame('fanvue', Entitlements::normalize_product('   '));
    }

    public function testNormalizeProductMapsLegacyCreatorreactorToFanvue(): void
    {
        self::assertSame('fanvue', Entitlements::normalize_product('creatorreactor'));
        self::assertSame('fanvue', Entitlements::normalize_product('  CreatorReactor  '));
    }

    public function testProductLabelReturnsExpectedDisplayNames(): void
    {
        self::assertSame('fanvue', Entitlements::product_label('fanvue'));
        self::assertSame('OnlyFans', Entitlements::product_label('onlyfans'));
        self::assertSame('My Platform', Entitlements::product_label('my_platform'));
    }

    public function testTierStoredForFollowerUsesNormalizedProductPrefix(): void
    {
        self::assertSame('fanvue_follower', Entitlements::tier_stored_for_follower('creatorreactor'));
        self::assertSame('onlyfans_follower', Entitlements::tier_stored_for_follower('OnlyFans'));
    }

    public function testTierStoredForSubscriberHandlesTierSlugsAndLengthLimit(): void
    {
        self::assertSame('fanvue_subscriber', Entitlements::tier_stored_for_subscriber('fanvue'));
        self::assertSame('fanvue_subscriber_vip', Entitlements::tier_stored_for_subscriber('fanvue', 'VIP'));

        $longTier = str_repeat('a', 180);
        $stored = Entitlements::tier_stored_for_subscriber('fanvue', $longTier);

        self::assertSame(100, strlen($stored));
        self::assertStringStartsWith('fanvue_subscriber_', $stored);
    }

    public function testTierStoredTypeClassifiersHandleFollowerVsSubscriber(): void
    {
        self::assertTrue(Entitlements::tier_stored_is_follower(Entitlements::TIER_FOLLOWER));
        self::assertTrue(Entitlements::tier_stored_is_follower('fanvue_follower'));
        self::assertFalse(Entitlements::tier_stored_is_follower('fanvue_subscriber_vip'));

        self::assertTrue(Entitlements::tier_stored_is_subscriber('fanvue_subscriber_vip'));
        self::assertFalse(Entitlements::tier_stored_is_subscriber(Entitlements::TIER_FOLLOWER));
    }
}
