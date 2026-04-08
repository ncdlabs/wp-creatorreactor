<?php

declare(strict_types=1);

namespace CreatorReactor\Tests\Unit;

use Brain\Monkey\Functions;
use CreatorReactor\Activation_Timezone_Modal;
use CreatorReactor\Editor_Blocks_Prompt;
use CreatorReactor\Tests\BaseTestCase;

final class ActivationTimezoneModalTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('CREATORREACTOR_PLUGIN_DIR')) {
            define('CREATORREACTOR_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
        }
        require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-editor-context.php';
        require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-activation-timezone-modal.php';
        require_once CREATORREACTOR_PLUGIN_DIR . 'includes/class-editor-blocks-prompt.php';
    }

    public function test_editor_prompt_suppressed_while_timezone_pending(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('get_option')->alias(static function ($key, $default = false) {
            if ($key === Activation_Timezone_Modal::OPTION_PENDING) {
                return '1';
            }
            if ($key === Editor_Blocks_Prompt::OPTION_HAS_ELEMENTOR) {
                return '1';
            }
            if ($key === Editor_Blocks_Prompt::OPTION_HAS_GUTENBERG) {
                return '0';
            }

            return $default;
        });

        self::assertFalse(Editor_Blocks_Prompt::should_show_modal());
    }
}
