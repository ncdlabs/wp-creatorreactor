<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Banner;
use CreatorReactor\Plugin;
use CreatorReactor\Tests\BaseTestCase;

final class BannerAndPluginRegressionTest extends BaseTestCase
{
    public function testAjaxDismissBannerRequiresManageOptionsCapability(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('creatorreactor_oauth_dismiss_banner_nonce', 'security');
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('__')->once()->andReturn('Forbidden.');
        Functions\expect('wp_send_json_error')
            ->once()
            ->with('Forbidden.', 403)
            ->andThrow(new \RuntimeException('stop'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stop');
        Banner::ajax_dismiss_banner();
    }

    public function testNormalizeUrlPathSlashesCollapsesDuplicatePathSeparators(): void
    {
        Functions\when('wp_parse_url')->alias(
            static fn ($url) => parse_url((string) $url)
        );

        $url = 'https://example.com/wp-admin//admin.php?page=creatorreactor';
        self::assertSame(
            'https://example.com/wp-admin/admin.php?page=creatorreactor',
            Plugin::normalize_url_path_slashes($url)
        );
    }
}
