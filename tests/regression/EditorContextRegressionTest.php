<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Editor_Context;
use CreatorReactor\Tests\BaseTestCase;

final class EditorContextRegressionTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__cr_wp_stub_is_plugin_active'],
            $GLOBALS['__cr_wp_stub_is_multisite'],
            $GLOBALS['__cr_wp_stub_is_plugin_active_for_network'],
            $GLOBALS['__cr_wp_stub_use_block_editor_for_post_type'],
            $GLOBALS['__cr_wp_stub_get_current_screen'],
            $GLOBALS['__cr_wp_stub_post_type_exists']
        );
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('get_the_ID')->justReturn(0);
        Functions\when('get_queried_object_id')->justReturn(0);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post')->justReturn(null);
        Functions\when('get_body_class')->justReturn([]);
    }

    public function testContentHasBlocksUsesStringFallbackWhenHasBlocksFunctionUnavailable(): void
    {
        self::assertTrue(Editor_Context::content_has_blocks('before <!-- wp:paragraph --> after'));
        self::assertFalse(Editor_Context::content_has_blocks('plain html only'));
    }

    public function testPostPrimaryStorageReturnsElementorWhenElementorMetaIsBuilder(): void
    {
        Functions\when('get_post_meta')->alias(
            static fn ($postId, $key, $single): string => $postId === 42 && $key === '_elementor_edit_mode' ? 'builder' : ''
        );

        self::assertSame('elementor', Editor_Context::post_primary_storage(42));
    }

    public function testPostPrimaryStorageReturnsBlocksWhenPostContentHasBlocks(): void
    {
        Functions\when('get_post')->alias(
            static fn ($postId): ?object => $postId === 55 ? (object) ['post_content' => '<!-- wp:image -->'] : null
        );

        self::assertSame('blocks', Editor_Context::post_primary_storage(55));
    }

    public function testPostPrimaryStorageReturnsHtmlWhenNoBlocksAndContentExists(): void
    {
        Functions\when('get_post')->alias(
            static fn ($postId): ?object => $postId === 77 ? (object) ['post_content' => '<p>Hello</p>'] : null
        );

        self::assertSame('html', Editor_Context::post_primary_storage(77));
    }

    public function testCurrentAdminEditorUiPrefersElementorActionOverBlockEditor(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        $GLOBALS['__cr_wp_stub_get_current_screen'] = new class {
            public function is_block_editor(): bool
            {
                return true;
            }
        };

        $_GET['action'] = 'elementor';
        self::assertSame('elementor', Editor_Context::current_admin_editor_ui());
        unset($_GET['action']);
    }

    public function testSiteUsesBlockEditorReturnsTrueWhenPublishedBlockContentExists(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $posts = 'wp_posts';
            public function esc_like($text): string
            {
                return addcslashes((string) $text, '_%\\');
            }
            public function prepare($query, ...$args): string
            {
                return (string) $query;
            }
            public function get_var($query)
            {
                return '1';
            }
        };

        self::assertTrue(Editor_Context::site_uses_block_editor());
    }

    public function testSiteUsesBlockEditorReturnsFalseWhenNoPublishedBlockContent(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $posts = 'wp_posts';
            public function esc_like($text): string
            {
                return addcslashes((string) $text, '_%\\');
            }
            public function prepare($query, ...$args): string
            {
                return (string) $query;
            }
            public function get_var($query)
            {
                return '';
            }
        };

        self::assertFalse(Editor_Context::site_uses_block_editor());
    }

    public function testIsElementorEditRequestReturnsFalseOutsideAdmin(): void
    {
        Functions\when('is_admin')->justReturn(false);
        $_GET['action'] = 'elementor';
        self::assertFalse(Editor_Context::is_elementor_edit_request());
        unset($_GET['action']);
    }

    public function testIsElementorEditRequestReturnsFalseWithoutAction(): void
    {
        Functions\when('is_admin')->justReturn(true);
        unset($_GET['action']);
        self::assertFalse(Editor_Context::is_elementor_edit_request());
    }

    public function testIsBlockEditorScreenReturnsTrueWhenScreenSupportsBlockEditor(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $GLOBALS['__cr_wp_stub_get_current_screen'] = new class {
            public function is_block_editor(): bool
            {
                return true;
            }
        };

        self::assertTrue(Editor_Context::is_block_editor_screen());
    }

    public function testFrontendViewIsElementorPageReturnsTrueForSingularElementorClass(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_body_class')->justReturn(['single', 'elementor-page']);

        self::assertTrue(Editor_Context::frontend_view_is_elementor_page());
    }

    public function testPostPrimaryStorageReturnsEmptyWhenNoPostResolved(): void
    {
        Functions\when('is_singular')->justReturn(false);
        Functions\when('get_the_ID')->justReturn(0);

        self::assertSame('empty', Editor_Context::post_primary_storage(null));
    }

    public function testCurrentAdminEditorUiReturnsBlockWhenBlockEditorActive(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $GLOBALS['__cr_wp_stub_get_current_screen'] = new class {
            public function is_block_editor(): bool
            {
                return true;
            }
        };
        unset($_GET['action']);

        self::assertSame('block', Editor_Context::current_admin_editor_ui());
    }

    public function testCurrentAdminEditorUiReturnsOtherWhenNoKnownEditorDetected(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $GLOBALS['__cr_wp_stub_get_current_screen'] = new class {
            public function is_block_editor(): bool
            {
                return false;
            }
        };
        unset($_GET['action']);

        self::assertSame('other', Editor_Context::current_admin_editor_ui());
    }

    public function testIsElementorPluginActiveTrueWhenPluginActive(): void
    {
        $GLOBALS['__cr_wp_stub_is_plugin_active'] = static fn ($plugin): bool => $plugin === 'elementor/elementor.php';
        $GLOBALS['__cr_wp_stub_is_multisite'] = false;

        self::assertTrue(Editor_Context::is_elementor_plugin_active());
    }

    public function testIsElementorPluginActiveFalseWhenNotActive(): void
    {
        $GLOBALS['__cr_wp_stub_is_plugin_active'] = static fn (): bool => false;
        $GLOBALS['__cr_wp_stub_is_multisite'] = false;

        self::assertFalse(Editor_Context::is_elementor_plugin_active());
    }

    public function testSiteUsesBlockEditorTrueWhenCoreFunctionReportsEnabled(): void
    {
        $GLOBALS['__cr_wp_stub_use_block_editor_for_post_type'] = static fn ($postType): bool => true;
        $GLOBALS['__cr_wp_stub_post_type_exists'] = static fn ($postType): bool => true;

        self::assertTrue(Editor_Context::site_uses_block_editor());
    }

    public function testIsElementorEditRequestTrueInAdminWithElementorAction(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_unslash')->alias(static fn ($value) => $value);
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower((string) $value));
        $_GET['action'] = 'elementor';

        self::assertTrue(Editor_Context::is_elementor_edit_request());

        unset($_GET['action']);
    }

    public function testIsBlockEditorScreenFalseWhenCurrentScreenMissing(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $GLOBALS['__cr_wp_stub_get_current_screen'] = null;

        self::assertFalse(Editor_Context::is_block_editor_screen());
    }

    public function testIsBlockEditorScreenFalseWhenScreenHasNoBlockEditorMethod(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $GLOBALS['__cr_wp_stub_get_current_screen'] = new \stdClass();

        self::assertFalse(Editor_Context::is_block_editor_screen());
    }

    public function testPostUsesElementorStorageFalseWhenMetaNotBuilder(): void
    {
        Functions\when('get_post_meta')->justReturn('');

        self::assertFalse(Editor_Context::post_uses_elementor_storage(300));
    }

    public function testContentHasBlocksLoadsPostByIdWhenContentNull(): void
    {
        Functions\when('get_post')->alias(
            static fn ($postId): ?object => $postId === 301 ? (object) ['post_content' => '<!-- wp:heading -->'] : null
        );

        self::assertTrue(Editor_Context::content_has_blocks(null, 301));
    }

    public function testFrontendViewIsElementorPageFalseWhenNotSingular(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);

        self::assertFalse(Editor_Context::frontend_view_is_elementor_page());
    }

    public function testFrontendViewIsElementorPageFalseInAdmin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('is_singular')->justReturn(true);

        self::assertFalse(Editor_Context::frontend_view_is_elementor_page());
    }

    public function testIsElementorPreviewRequestTrueWhenElementorPreviewQueryVar(): void
    {
        Functions\when('is_admin')->justReturn(false);
        $_GET['elementor-preview'] = 'abc123nonce';
        self::assertTrue(Editor_Context::is_elementor_preview_request());
        unset($_GET['elementor-preview']);
    }

    public function testIsElementorPreviewRequestFalseInAdminEvenWithQueryVar(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $_GET['elementor-preview'] = 'x';
        self::assertFalse(Editor_Context::is_elementor_preview_request());
        unset($_GET['elementor-preview']);
    }

    public function testIsElementorPluginActiveTrueWhenNetworkActiveOnly(): void
    {
        $GLOBALS['__cr_wp_stub_is_plugin_active'] = static fn (): bool => false;
        $GLOBALS['__cr_wp_stub_is_multisite'] = true;
        $GLOBALS['__cr_wp_stub_is_plugin_active_for_network'] = static fn (string $plugin): bool => $plugin === 'elementor/elementor.php';

        self::assertTrue(Editor_Context::is_elementor_plugin_active());
    }

    public function testIsBlockEditorScreenFalseWhenNotInAdmin(): void
    {
        Functions\when('is_admin')->justReturn(false);

        self::assertFalse(Editor_Context::is_block_editor_screen());
    }

    public function testPostPrimaryStorageUsesSingularQueriedPostHtml(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_queried_object_id')->justReturn(900);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post')->alias(
            static fn (int $id): ?object => $id === 900 ? (object) ['post_content' => '<p>Hi</p>'] : null
        );
        Functions\when('get_the_ID')->justReturn(0);

        self::assertSame('html', Editor_Context::post_primary_storage(null));
    }

    public function testContentHasBlocksFalseWhenPostObjectHasNoContentKey(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_queried_object_id')->justReturn(902);
        Functions\when('get_post')->alias(
            static fn (int $id): ?object => $id === 902 ? (object) [] : null
        );
        Functions\when('has_blocks')->justReturn(false);

        self::assertFalse(Editor_Context::content_has_blocks(null, null));
    }

    public function testPostPrimaryStorageEmptyWhenPostContentIsNotString(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_queried_object_id')->justReturn(903);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post')->alias(
            static fn (int $id): ?object => $id === 903 ? (object) ['post_content' => null] : null
        );

        self::assertSame('empty', Editor_Context::post_primary_storage(null));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIsElementorPluginActiveTrueWhenElementorCoreClassAlreadyLoaded(): void
    {
        if (! class_exists('\Elementor\Plugin', false)) {
            eval(<<<'PHP'
namespace Elementor;
class Plugin {}
PHP);
        }

        self::assertTrue(Editor_Context::is_elementor_plugin_active());
    }
}
