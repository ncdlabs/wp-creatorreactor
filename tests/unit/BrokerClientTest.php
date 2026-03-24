<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Admin_Settings;
use CreatorReactor\Broker_Client;
use CreatorReactor\Tests\BaseTestCase;

final class BrokerClientTest extends BaseTestCase
{
    public function testGetJwtTokenDecryptsEncryptedStoredToken(): void
    {
        Functions\when('wp_salt')->justReturn('test-salt');

        $encrypted = Admin_Settings::encrypt_sensitive_value('jwt-secret-token');

        Functions\when('get_option')->alias(
            static function ($key, $default = []) use ($encrypted) {
                if ($key === Admin_Settings::OPTION_NAME) {
                    return ['jwt_token' => $encrypted];
                }
                return $default;
            }
        );

        self::assertSame('jwt-secret-token', Broker_Client::get_jwt_token());
    }

    public function testGetJwtTokenSanitizesPlaintextToken(): void
    {
        Functions\when('get_option')->alias(
            static function ($key, $default = []) {
                if ($key === Admin_Settings::OPTION_NAME) {
                    return ['jwt_token' => '  raw-token<script>  '];
                }
                return $default;
            }
        );
        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim(strip_tags((string) $value))
        );

        self::assertSame('raw-token', Broker_Client::get_jwt_token());
    }
}
