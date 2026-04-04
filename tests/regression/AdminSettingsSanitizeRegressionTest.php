<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Admin_Settings;
use CreatorReactor\Tests\BaseTestCase;

final class AdminSettingsSanitizeRegressionTest extends BaseTestCase
{
    private array $settingsErrors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsErrors = [];
        $GLOBALS['creatorreactor_test_raw_options'] = [];

        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === Admin_Settings::OPTION_NAME) {
                    return $GLOBALS['creatorreactor_test_raw_options'] ?? [];
                }
                return $default;
            }
        );
        Functions\when('wp_unslash')->alias(static fn($value) => $value);
        Functions\when('sanitize_key')->alias(static fn($value): string => strtolower(trim((string) $value)));
        Functions\when('sanitize_text_field')->alias(static fn($value): string => trim((string) $value));
        Functions\when('esc_url_raw')->alias(
            static function ($value, $protocols = null): string {
                $url = trim((string) $value);
                if ($url === '') {
                    return '';
                }
                return str_starts_with(strtolower($url), 'https://') ? $url : '';
            }
        );
        Functions\when('wp_parse_url')->alias(static fn($url) => parse_url((string) $url));
        Functions\when('trailingslashit')->alias(static fn($value): string => rtrim((string) $value, '/') . '/');
        Functions\when('untrailingslashit')->alias(static fn($value): string => rtrim((string) $value, '/'));
        Functions\when('__')->alias(static fn($text, $domain = null): string => (string) $text);
        Functions\when('add_settings_error')->alias(function ($option, $code, $message): void {
            $this->settingsErrors[] = (string) $code;
        });
        Functions\when('rest_url')->alias(
            static fn($path = ''): string => 'https://example.com/wp-json/' . ltrim((string) $path, '/')
        );
        Functions\when('wp_salt')->justReturn('test-salt');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['creatorreactor_test_raw_options']);
        parent::tearDown();
    }

    public function testCreatorModeRequiresOauthCredentialsAndDisablesSocialLoginWhenUnconfigured(): void
    {
        $GLOBALS['creatorreactor_test_raw_options'] = [
            'broker_mode' => false,
            'replace_wp_login_with_social' => false,
        ];

        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
            'replace_wp_login_with_social' => '1',
        ]);

        self::assertFalse($opts['broker_mode']);
        self::assertFalse($opts['replace_wp_login_with_social']);
        self::assertContains('creatorreactor_client_id_required', $this->settingsErrors);
        self::assertContains('creatorreactor_client_secret_required', $this->settingsErrors);
        self::assertContains('creatorreactor_social_login_not_configured', $this->settingsErrors);
    }

    public function testAgencyModeDefaultsBrokerUrlAndOnlyRequiresSiteId(): void
    {
        $GLOBALS['creatorreactor_test_raw_options'] = [
            'broker_mode' => true,
            'creatorreactor_oauth_client_id' => 'stored-client-id',
            'creatorreactor_oauth_client_secret' => 'stored-client-secret',
        ];

        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'agency',
            'broker_url' => '',
            'site_id' => '',
            'creatorreactor_oauth_client_secret' => '********',
        ]);

        self::assertTrue($opts['broker_mode']);
        self::assertSame('https://auth.ncdlabs.com', $opts['broker_url']);
        self::assertContains('creatorreactor_site_id_required', $this->settingsErrors);
        self::assertNotContains('creatorreactor_client_id_required', $this->settingsErrors);
        self::assertNotContains('creatorreactor_client_secret_required', $this->settingsErrors);
    }

    public function testMaskedSecretInputsPreserveStoredEncryptedValues(): void
    {
        $GLOBALS['creatorreactor_test_raw_options'] = [
            'broker_mode' => false,
            'creatorreactor_oauth_client_secret' => 'stored-secret',
            'creatorreactor_cloud_password' => 'stored-cloud-password',
        ];

        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
            'creatorreactor_oauth_client_secret' => '********',
            'creatorreactor_cloud_password' => '********',
        ]);

        self::assertSame('stored-secret', $opts['creatorreactor_oauth_client_secret']);
        self::assertSame('stored-cloud-password', $opts['creatorreactor_cloud_password']);
    }

    public function testRedirectUriIsNormalizedWithTrailingSlash(): void
    {
        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
            'creatorreactor_oauth_redirect_uri' => 'https://example.com/custom-callback',
        ]);

        self::assertSame('https://example.com/custom-callback/', $opts['creatorreactor_oauth_redirect_uri']);
    }

    public function testCreatorClientIdIsEncryptedWhenSubmittedInPlaintext(): void
    {
        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
            'creatorreactor_oauth_client_id' => 'client-id-123',
            'creatorreactor_oauth_client_secret' => 'secret-123',
        ]);

        self::assertNotSame('client-id-123', $opts['creatorreactor_oauth_client_id']);
        self::assertNotSame('secret-123', $opts['creatorreactor_oauth_client_secret']);
        self::assertNotSame('', $opts['creatorreactor_oauth_client_id']);
        self::assertNotSame('', $opts['creatorreactor_oauth_client_secret']);
    }

    public function testGoogleLoginButtonStyleSanitizesUnknownToTextOutline(): void
    {
        self::assertSame('text_outline', Admin_Settings::sanitize_google_login_button_style('not-a-style'));
        self::assertSame('standard_light', Admin_Settings::sanitize_google_login_button_style('standard_light'));
    }

    public function testSanitizeOptionsStoresGoogleLoginButtonStyleWhenSubmitted(): void
    {
        $GLOBALS['creatorreactor_test_raw_options'] = [
            'broker_mode' => false,
            'creatorreactor_oauth_client_id' => 'x',
            'creatorreactor_oauth_client_secret' => 'y',
        ];

        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
            Admin_Settings::GOOGLE_LOGIN_BUTTON_STYLE_KEY => 'standard_dark',
        ]);

        self::assertSame('standard_dark', $opts[Admin_Settings::GOOGLE_LOGIN_BUTTON_STYLE_KEY]);
    }

    public function testSanitizeOptionsPreservesGoogleLoginButtonStyleWhenFieldAbsent(): void
    {
        $GLOBALS['creatorreactor_test_raw_options'] = [
            'broker_mode' => false,
            'creatorreactor_oauth_client_id' => 'x',
            'creatorreactor_oauth_client_secret' => 'y',
            Admin_Settings::GOOGLE_LOGIN_BUTTON_STYLE_KEY => 'logo_only',
        ];

        $opts = Admin_Settings::sanitize_options([
            'authentication_mode' => 'creator',
        ]);

        self::assertSame('logo_only', $opts[Admin_Settings::GOOGLE_LOGIN_BUTTON_STYLE_KEY]);
    }
}
