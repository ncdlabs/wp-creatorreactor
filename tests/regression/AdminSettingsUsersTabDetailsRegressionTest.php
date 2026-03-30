<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Admin_Settings;
use CreatorReactor\Tests\BaseTestCase;

/**
 * Regression: Users tab / profile “CreatorReactor record” modal payload (CreatorReactor vs Fanvue sections).
 */
final class AdminSettingsUsersTabDetailsRegressionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('__')->alias(static fn ($text, $domain = null): string => (string) $text);
        Functions\when('sanitize_text_field')->alias(static fn ($value): string => trim((string) $value));
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>}
     */
    private function invokeUsersTabRowDetailsPayload(array $row): array
    {
        $method = new \ReflectionMethod(Admin_Settings::class, 'users_tab_row_details_payload');

        return $method->invoke(null, $row);
    }

    public function testDetailsModalSectionsAreCreatorReactorThenFanvueThenOnlyFans(): void
    {
        $row = [
            'id'                       => 42,
            'wp_user_id'               => 7,
            'creatorreactor_uuid'      => 'cr-site-uuid',
            'creatorreactor_user_uuid' => 'cr-user-uuid',
            'fanvue_user_uuid'         => 'fv-user-uuid',
            'fanvue_email'             => 'fan@fanvue.test',
            'fanvue_display_name'      => 'Fanvue Display',
            'fanvue_tier'              => 'fanvue_follower',
            'fanvue_sync_snapshot'     => '{"synced":true}',
            'product'                  => 'fanvue',
            'email'                    => 'normalized@example.com',
            'display_name'             => 'Normalized Name',
            'status'                   => 'active',
            'tier'                     => 'fanvue_follower',
            'expires_at'               => '',
            'updated_at'               => '',
        ];

        $payload = $this->invokeUsersTabRowDetailsPayload($row);
        self::assertArrayHasKey('lines', $payload);
        $lines = $payload['lines'];

        $sectionLabels = [];
        foreach ($lines as $line) {
            if (! empty($line['section'])) {
                $sectionLabels[] = (string) ($line['label'] ?? '');
            }
        }

        self::assertSame(
            [
                'CreatorReactor Records',
                'Fanvue Records',
                'OnlyFans Records (coming soon)',
            ],
            $sectionLabels
        );
    }

    public function testFanvueSectionContainsFanvueColumnsAndValues(): void
    {
        $row = [
            'id'                       => 1,
            'wp_user_id'               => 2,
            'creatorreactor_uuid'      => 'cr-entitlement-uuid',
            'creatorreactor_user_uuid' => '',
            'fanvue_user_uuid'         => 'uuid-fv-99',
            'fanvue_email'             => 'e@fv.com',
            'fanvue_display_name'      => 'FV Name',
            'fanvue_tier'              => 'fanvue_subscriber_tier',
            'fanvue_sync_snapshot'     => '{}',
            'product'                  => 'fanvue',
            'email'                    => 'e@example.com',
            'display_name'             => 'WP Name',
            'status'                   => 'active',
            'tier'                     => 'fanvue_subscriber_tier',
            'expires_at'               => '',
            'updated_at'               => '',
        ];

        $lines = $this->invokeUsersTabRowDetailsPayload($row)['lines'];

        $byLabel = [];
        foreach ($lines as $line) {
            if (empty($line['section']) && isset($line['label'])) {
                $byLabel[(string) $line['label']] = (string) ($line['value'] ?? '');
            }
        }

        self::assertSame('uuid-fv-99', $byLabel['Fanvue user UUID']);
        self::assertSame('e@fv.com', $byLabel['Fanvue email']);
        self::assertSame('FV Name', $byLabel['Fanvue display name']);
        self::assertSame('fanvue_subscriber_tier', $byLabel['Fanvue tier (stored)']);
        self::assertSame('{}', $byLabel['Fanvue sync snapshot']);
        self::assertSame('e@example.com', $byLabel['Email (normalized)']);
        self::assertSame('cr-entitlement-uuid', $byLabel['CreatorReactor UUID']);
    }

    public function testCreatorReactorUuidShowsDashWhenEmpty(): void
    {
        $row = [
            'id'                       => 1,
            'wp_user_id'               => 0,
            'creatorreactor_uuid'      => '',
            'creatorreactor_user_uuid' => '',
            'fanvue_user_uuid'         => '',
            'fanvue_email'             => '',
            'fanvue_display_name'      => '',
            'fanvue_tier'              => '',
            'fanvue_sync_snapshot'     => '',
            'product'                  => 'fanvue',
            'email'                    => '',
            'display_name'             => '',
            'status'                   => 'inactive',
            'tier'                     => '',
            'expires_at'               => '',
            'updated_at'               => '',
        ];

        $lines = $this->invokeUsersTabRowDetailsPayload($row)['lines'];
        $byLabel = [];
        foreach ($lines as $line) {
            if (empty($line['section']) && isset($line['label'])) {
                $byLabel[(string) $line['label']] = (string) ($line['value'] ?? '');
            }
        }

        self::assertSame('-', $byLabel['CreatorReactor UUID']);
        self::assertSame('-', $byLabel['Fanvue user UUID']);
        self::assertSame('-', $byLabel['Fanvue sync snapshot']);
    }

    public function testFanvueSyncSnapshotIsTruncatedWhenLong(): void
    {
        $long = str_repeat('Z', 650);
        $row = [
            'id'                       => 1,
            'wp_user_id'               => 1,
            'creatorreactor_uuid'      => 'x',
            'creatorreactor_user_uuid' => 'y',
            'fanvue_user_uuid'         => 'z',
            'fanvue_email'             => 'a@b.c',
            'fanvue_display_name'      => 'N',
            'fanvue_tier'              => 'fanvue_follower',
            'fanvue_sync_snapshot'     => $long,
            'product'                  => 'fanvue',
            'email'                    => 'a@b.c',
            'display_name'             => 'N',
            'status'                   => 'active',
            'tier'                     => 'fanvue_follower',
            'expires_at'               => '',
            'updated_at'               => '',
        ];

        $lines = $this->invokeUsersTabRowDetailsPayload($row)['lines'];
        $snapshotValue = null;
        foreach ($lines as $line) {
            if (empty($line['section']) && ($line['label'] ?? '') === 'Fanvue sync snapshot') {
                $snapshotValue = (string) ($line['value'] ?? '');
                break;
            }
        }

        self::assertNotNull($snapshotValue);
        self::assertStringEndsWith('...', $snapshotValue);
        self::assertSame(603, strlen($snapshotValue));
    }
}
