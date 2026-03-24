<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Fan_OAuth;
use CreatorReactor\Tests\BaseTestCase;

if (! class_exists('\WP_User')) {
    class_alias(\stdClass::class, 'WP_User');
}

final class FanOauthProfileMappingTest extends BaseTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $metaWrites = [];

    /** @var array<int, array<string, mixed>> */
    private array $userUpdates = [];

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('sanitize_textarea_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('esc_url_raw')->alias(
            static fn ($value): string => (string) $value
        );

        Functions\when('update_user_meta')->alias(function ($userId, $key, $value): bool {
            $this->metaWrites[] = [
                'user_id' => (int) $userId,
                'key' => (string) $key,
                'value' => $value,
            ];
            return true;
        });
        Functions\when('wp_update_user')->alias(function (array $args): int {
            $this->userUpdates[] = $args;
            return (int) ($args['ID'] ?? 0);
        });
    }

    public function testMapsProvidedFieldsFromOauthPayloadToWpProfileAndMeta(): void
    {
        $method = new \ReflectionMethod(Fan_OAuth::class, 'sync_wp_profile_fields_from_oauth_profile');
        $method->setAccessible(true);

        $profile = [
            'id' => 'fan-123',
            'bio' => 'fan bio',
            'displayName' => 'Fan Display',
            'isCreator' => false,
            'createdAt' => '2026-03-22T07:45:17.582Z',
            'updatedAt' => '2026-03-24T13:37:08.394Z',
            'avatarUrl' => 'https://cdn.example.com/avatar.png',
            'bannerUrl' => 'https://cdn.example.com/banner.png',
        ];

        $method->invoke(null, 101, $profile);

        self::assertContains(['ID' => 101, 'description' => 'fan bio'], $this->userUpdates);
        self::assertContains(['ID' => 101, 'display_name' => 'Fan Display'], $this->userUpdates);

        self::assertTrue($this->hasMetaWrite(101, 'fanvue_id', 'fan-123'));
        self::assertTrue($this->hasMetaWrite(101, 'isFanvueCreator', '0'));
        self::assertTrue($this->hasMetaWrite(101, 'fanvueAccountCreatedAt', '2026-03-22T07:45:17.582Z'));
        self::assertTrue($this->hasMetaWrite(101, 'fanvueAccountUpdatedAt', '2026-03-24T13:37:08.394Z'));
        self::assertTrue($this->hasMetaWrite(101, 'avatarUrl', 'https://cdn.example.com/avatar.png'));
        self::assertTrue($this->hasMetaWrite(101, 'bannerUrl', 'https://cdn.example.com/banner.png'));
    }

    public function testSkipsNullOrMissingMappedFields(): void
    {
        $method = new \ReflectionMethod(Fan_OAuth::class, 'sync_wp_profile_fields_from_oauth_profile');
        $method->setAccessible(true);

        $profile = [
            'data' => [
                'id' => null,
                'bio' => null,
                'displayName' => null,
                'isCreator' => null,
                'createdAt' => null,
                'updatedAt' => null,
                'avatarUrl' => null,
                'bannerUrl' => null,
            ],
        ];

        $method->invoke(null, 202, $profile);

        self::assertSame([], $this->metaWrites);
        self::assertSame([], $this->userUpdates);
    }

    public function testReadsStoredOauthProfileSnapshotWhenJsonIsValid(): void
    {
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single = true): string => '{"data":{"id":"fan-42","displayName":"Snapshot Name"}}'
        );

        $method = new \ReflectionMethod(Fan_OAuth::class, 'get_stored_profile_snapshot_for_user');
        $method->setAccessible(true);

        $decoded = $method->invoke(null, 42);
        self::assertIsArray($decoded);
        self::assertSame('fan-42', $decoded['data']['id'] ?? null);
        self::assertSame('Snapshot Name', $decoded['data']['displayName'] ?? null);
    }

    public function testReturnsNullWhenStoredOauthProfileSnapshotIsInvalid(): void
    {
        Functions\when('get_user_meta')->alias(
            static fn ($userId, $key, $single = true): string => '{bad-json'
        );

        $method = new \ReflectionMethod(Fan_OAuth::class, 'get_stored_profile_snapshot_for_user');
        $method->setAccessible(true);

        self::assertNull($method->invoke(null, 42));
    }

    private function hasMetaWrite(int $userId, string $key, $value): bool
    {
        foreach ($this->metaWrites as $write) {
            if ($write['user_id'] === $userId && $write['key'] === $key && $write['value'] === $value) {
                return true;
            }
        }
        return false;
    }
}
