<?php

declare(strict_types=1);

/**
 * Minimal global stand-ins for WordPress functions referenced from namespaced plugin code.
 * Values are driven by $GLOBALS keys set inside individual tests.
 */

namespace {
    if (! function_exists('is_plugin_active')) {
        function is_plugin_active($plugin)
        {
            $fn = $GLOBALS['__cr_wp_stub_is_plugin_active'] ?? null;

            return is_callable($fn) ? (bool) $fn($plugin) : false;
        }
    }

    if (! function_exists('is_multisite')) {
        function is_multisite()
        {
            return (bool) ($GLOBALS['__cr_wp_stub_is_multisite'] ?? false);
        }
    }

    if (! function_exists('is_plugin_active_for_network')) {
        function is_plugin_active_for_network($plugin)
        {
            $fn = $GLOBALS['__cr_wp_stub_is_plugin_active_for_network'] ?? null;

            return is_callable($fn) ? (bool) $fn($plugin) : false;
        }
    }

    if (! function_exists('use_block_editor_for_post_type')) {
        function use_block_editor_for_post_type($post_type)
        {
            $fn = $GLOBALS['__cr_wp_stub_use_block_editor_for_post_type'] ?? null;

            return is_callable($fn) ? (bool) $fn($post_type) : false;
        }
    }

    if (! function_exists('get_current_screen')) {
        function get_current_screen()
        {
            return $GLOBALS['__cr_wp_stub_get_current_screen'] ?? null;
        }
    }

    if (! function_exists('post_type_exists')) {
        function post_type_exists($post_type)
        {
            $fn = $GLOBALS['__cr_wp_stub_post_type_exists'] ?? null;

            return is_callable($fn) ? (bool) $fn($post_type) : false;
        }
    }

    if (! function_exists('sanitize_html_class')) {
        /**
         * @param string $classname
         * @param string $fallback
         */
        function sanitize_html_class($classname, $fallback = '')
        {
            $classname = (string) $classname;
            if ($classname === '') {
                return $fallback;
            }
            $out = preg_replace('|[^a-zA-Z0-9_-]|', '', $classname);

            return $out !== '' ? $out : $fallback;
        }
    }
}
