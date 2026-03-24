<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Regression;

use Brain\Monkey\Functions;
use CreatorReactor\Editor_Context;
use CreatorReactor\Tests\BaseTestCase;

final class EditorContextRegressionTest extends BaseTestCase
{
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
        Functions\when('get_current_screen')->justReturn(new class {
            public function is_block_editor(): bool
            {
                return true;
            }
        });

        $_GET['action'] = 'elementor';
        self::assertSame('elementor', Editor_Context::current_admin_editor_ui());
        unset($_GET['action']);
    }
}
