<?php

declare(strict_types=1);

namespace WicketGF;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles nonce timeout issues and form session management
 * for Wicket Gravity Forms plugin.
 */
class NonceHandler
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [static::class, 'enqueue_nonce_handling_script']);
        add_action('wp_ajax_wicket_gf_validate_nonce', [static::class, 'ajax_validate_nonce']);
        add_action('wp_ajax_nopriv_wicket_gf_validate_nonce', [static::class, 'ajax_validate_nonce']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('init', [static::class, 'add_debug_hooks']);
        }
    }

    public static function enqueue_nonce_handling_script(): void
    {
        if (!class_exists('GFForms')) {
            return;
        }

        wp_enqueue_script(
            'wicket-gf-nonce-handling',
            WICKET_GF_URL . 'assets/js/wicket-gf-nonce-handling.js',
            ['jquery'],
            WICKET_GF_VERSION,
            true
        );

        wp_localize_script('wicket-gf-nonce-handling', 'WicketGfNonce', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wicket_gf_nonce_validation'),
        ]);
    }

    public static function ajax_validate_nonce(): void
    {
        $form_id = intval($_POST['form_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

        if (empty($form_id) || empty($nonce)) {
            wp_send_json_error('Missing required parameters');

            return;
        }

        $is_valid = wp_verify_nonce($nonce, 'gform_submit_' . $form_id);

        if ($is_valid) {
            wp_send_json_success(['valid' => true]);
        } else {
            wp_send_json_error(['valid' => false, 'message' => 'Nonce expired']);
        }
    }

    public static function add_debug_hooks(): void
    {
        add_filter('wp_verify_nonce', function ($result, $nonce, $action) {
            if ($action && strpos($action, 'gform') !== false) {
                Wicket()->log()->debug(sprintf(
                    'Action: %s, Result: %s, Nonce: %s, User: %d, Time: %s',
                    $action,
                    $result ? 'PASS' : 'FAIL',
                    $nonce,
                    get_current_user_id(),
                    current_time('mysql')
                ), ['source' => 'wicket-gf-nonce']);

                if (!$result) {
                    Wicket()->log()->warning('Nonce verification failed. Check session timeout settings.', ['source' => 'wicket-gf-nonce']);
                }
            }

            return $result;
        }, 10, 3);

        add_action('gform_validation', function ($validation_result) {
            if (!$validation_result['is_valid']) {
                Wicket()->log()->info('Form validation failed for form ID: ' . $validation_result['form']['id'], ['source' => 'wicket-gf-nonce']);
                foreach ($validation_result['form']['fields'] as $field) {
                    if (isset($field->failed_validation) && $field->failed_validation) {
                        Wicket()->log()->debug('Field validation failed - ID: ' . $field->id . ', Message: ' . ($field->validation_message ?? 'Unknown error'), ['source' => 'wicket-gf-nonce']);
                    }
                }
            }

            return $validation_result;
        });

        add_action('admin_init', function () {
            if (current_user_can('manage_options') && isset($_GET['wicket_gf_debug_session'])) {
                Wicket()->log()->debug('session.gc_maxlifetime: ' . ini_get('session.gc_maxlifetime'), ['source' => 'wicket-gf-nonce']);
                Wicket()->log()->debug('session.cookie_lifetime: ' . ini_get('session.cookie_lifetime'), ['source' => 'wicket-gf-nonce']);
                Wicket()->log()->debug('WordPress nonce lifetime: ' . (wp_nonce_tick() * DAY_IN_SECONDS), ['source' => 'wicket-gf-nonce']);
                wp_die('Session debug info logged. Check WooCommerce logs.');
            }
        });
    }
}
