<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Social_OAuth;
use CreatorReactor\Tests\BaseTestCase;

/**
 * @covers \CreatorReactor\Social_OAuth::normalize_mastodon_base_from_opts
 */
final class SocialOAuthMastodonNormalizeTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('wp_parse_url')->alias(
            static fn ($url, $component = -1) => parse_url((string) $url, $component)
        );
    }

    public function testNormalizeMastodonBaseAddsHttpsScheme(): void
    {
        self::assertSame(
            'https://mastodon.social',
            Social_OAuth::normalize_mastodon_base_from_opts( [ 'creatorreactor_mastodon_instance' => 'mastodon.social' ] )
        );
    }

    public function testNormalizeMastodonBasePreservesHttpsUrl(): void
    {
        self::assertSame(
            'https://example.org',
            Social_OAuth::normalize_mastodon_base_from_opts( [ 'creatorreactor_mastodon_instance' => 'https://example.org' ] )
        );
    }

    public function testNormalizeMastodonBaseReturnsEmptyWhenMissing(): void
    {
        self::assertSame( '', Social_OAuth::normalize_mastodon_base_from_opts( [] ) );
    }
}
