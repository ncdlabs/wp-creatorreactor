<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Traits;

use Brain\Monkey\Functions;

/**
 * Shared Brain Monkey stubs for shortcode regression tests.
 */
trait ShortcodesRegressionBrainMonkeyTrait
{
    protected function stubShortcodesRegressionBrainMonkey(): void
    {
        Functions\when('shortcode_atts')->alias(
            static function (array $pairs, $atts, $shortcode = ''): array {
                $atts = (array) $atts;
                $out  = $pairs;
                foreach (array_keys($pairs) as $name) {
                    if (array_key_exists($name, $atts)) {
                        $out[$name] = $atts[$name];
                    }
                }

                return $out;
            }
        );
        Functions\when('sanitize_key')->alias(
            static fn ($value): string => strtolower(trim((string) $value))
        );
        Functions\when('sanitize_text_field')->alias(
            static fn ($value): string => trim((string) $value)
        );
        Functions\when('wp_parse_url')->alias(
            static fn ($url) => parse_url((string) $url)
        );
        Functions\when('get_option')->alias(
            static function ($key, $default = false) {
                if ($key === 'creatorreactor_settings') {
                    return [
                        'creatorreactor_oauth_scopes' => 'openid offline_access',
                        'display_timezone' => 'system',
                    ];
                }

                return $default;
            }
        );
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('get_userdata')->justReturn(false);
    }
}
