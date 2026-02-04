<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class AbstractTestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Track registered shortcodes
        static $shortcodes = [];

        // Stub essential WordPress/Gravity Forms functions BEFORE loading plugin files
        Monkey\Functions\stubs([
            'add_action' => null,
            'add_filter' => null,
            '__',
            'esc_html__',
            'esc_attr__',
            'esc_html',
            'esc_attr',
            'esc_url',
            'esc_url_raw',
            'apply_filters' => function ($tag, $value) { return $value; },
            'get_option' => function ($option, $default = false) {
                if ($option === 'active_plugins') {
                    return ['gravityforms/gravityforms.php'];
                }

                return $default;
            },
            'plugins_url' => '',
            'plugin_dir_path' => function ($file) { return dirname($file) . '/'; },
            'plugin_basename' => function ($file) { return basename(dirname($file)) . '/' . basename($file); },
            'get_plugin_data' => ['Version' => '2.3.3'],
            'add_shortcode' => function ($tag, $callback) use (&$shortcodes) {
                $shortcodes[$tag] = $callback;

                return true;
            },
            'do_shortcode' => function ($content) use (&$shortcodes) {
                // Simple shortcode parser
                $pattern = '/\[(\w+)([^\]]*)\]/';

                return preg_replace_callback($pattern, function ($matches) use (&$shortcodes) {
                    $tag = $matches[1];
                    $attrs_string = trim($matches[2]);

                    // Parse attributes
                    $attrs = [];
                    if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $attrs_string, $attr_matches, PREG_SET_ORDER)) {
                        foreach ($attr_matches as $attr) {
                            $attrs[$attr[1]] = $attr[2];
                        }
                    }

                    // Call the shortcode handler if registered
                    if (isset($shortcodes[$tag])) {
                        $callback = $shortcodes[$tag];
                        $result = call_user_func($callback, $attrs);

                        // Preserve null returns (convert to empty string for regex)
                        return $result === null ? '' : $result;
                    }

                    return $matches[0]; // Return unchanged if not registered
                }, $content);
            },
            'shortcode_exists' => function ($tag) use (&$shortcodes) {
                return isset($shortcodes[$tag]);
            },
            'shortcode_atts' => function ($defaults, $atts) {
                if (!is_array($atts)) {
                    return $defaults;
                }

                return array_merge($defaults, $atts);
            },
            'wp_kses_post' => function ($text) { return $text; },
            'load_plugin_textdomain' => null,
        ]);

        // Load plugin files after Brain Monkey and essential functions are set up
        // This ensures WordPress functions are mocked before plugin code executes
        static $pluginFilesLoaded = false;
        if (!$pluginFilesLoaded) {
            require_once dirname(__DIR__, 2) . '/class-wicket-wp-gf.php';
            require_once dirname(__DIR__, 2) . '/includes/class-wicket-gf-validation.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-org-search-select.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-user-mdp-tags.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-widget-profile.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-data-bind-hidden.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-api-data-bind.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-widget-profile-org.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-widget-additional-info.php';
            require_once dirname(__DIR__, 2) . '/includes/class-gf-field-widget-prefs.php';

            // Initialize the main plugin class
            // This simulates WordPress's plugins_loaded action
            if (class_exists('\Wicket_Gf_Main')) {
                \Wicket_Gf_Main::get_instance()->plugin_setup();
            }

            $pluginFilesLoaded = true;
        }

        // Set up global $wpdb mock
        global $wpdb;
        if (!isset($wpdb)) {
            $wpdb = new class {
                public $prefix = 'wp_';
                public $options = 'wp_options';

                public function get_col($query)
                {
                    return [];
                }

                public function prepare($query, ...$args)
                {
                    return $query;
                }

                public function esc_like($text)
                {
                    return addcslashes($text, '_%\\');
                }
            };
            $GLOBALS['wpdb'] = $wpdb;
        }

        // Additional WordPress/Gravity Forms mocks (essentials were stubbed earlier)
        Monkey\Functions\stubs([
            'is_email' => true,
            'sanitize_text_field',
            'wp_unslash',
            'wp_safe_redirect',
            'home_url',
            'get_current_user_id' => 0,
            'get_user_meta',
            'wp_set_current_user',
            'update_user_meta',
            'delete_user_meta',
            'is_ssl' => false,
            'wp_doing_ajax' => false,
            'get_userdata',
            'add_query_arg',
            'wp_date',
            'get_bloginfo',
            'get_theme_mod',
            'admin_url',
            'wp_mail',
            'COOKIEPATH' => '/',
            'COOKIE_DOMAIN' => 'localhost',
            'DAY_IN_SECONDS' => 86400,
            'MINUTE_IN_SECONDS' => 60,
            'HOUR_IN_SECONDS' => 3600,
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
