<?php

/**
 * Handles nonce timeout issues and form session management
 * for Wicket Gravity Forms plugin.
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Wicket_Gf_Nonce_Handler
{
    /**
     * Initialize the nonce handler.
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_nonce_handling_script']);
        add_action('wp_ajax_wicket_gf_validate_nonce', [__CLASS__, 'ajax_validate_nonce']);
        add_action('wp_ajax_nopriv_wicket_gf_validate_nonce', [__CLASS__, 'ajax_validate_nonce']);

        // Add debugging if in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('init', [__CLASS__, 'add_debug_hooks']);
        }
    }

    /**
     * Enqueue nonce handling JavaScript.
     */
    public static function enqueue_nonce_handling_script()
    {
        if (!class_exists('GFForms')) {
            return;
        }

        wp_enqueue_script(
            'wicket-gf-nonce-handling',
            plugins_url('assets/js/wicket-gf-nonce-handling.js', dirname(__FILE__)),
            ['jquery'],
            WICKET_WP_GF_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script('wicket-gf-nonce-handling', 'WicketGfNonce', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wicket_gf_nonce_validation'),
        ]);
    }

    /**
     * AJAX handler to validate Gravity Forms nonce before auto-advance.
     */
    public static function ajax_validate_nonce()
    {
        $form_id = intval($_POST['form_id'] ?? 0);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');

        if (empty($form_id) || empty($nonce)) {
            wp_send_json_error('Missing required parameters');

            return;
        }

        // Validate the Gravity Forms nonce
        $is_valid = wp_verify_nonce($nonce, 'gform_submit_' . $form_id);

        if ($is_valid) {
            wp_send_json_success(['valid' => true]);
        } else {
            wp_send_json_error(['valid' => false, 'message' => 'Nonce expired']);
        }
    }

    /**
     * Add debug hooks for nonce and session issues.
     */
    public static function add_debug_hooks()
    {
        // Log nonce verification attempts
        add_filter('wp_verify_nonce', function ($result, $nonce, $action) {
            if ($action && strpos($action, 'gform') !== false) {
                $logger = wc_get_logger();
                $logger->debug(sprintf(
                    'Action: %s, Result: %s, Nonce: %s, User: %d, Time: %s',
                    $action,
                    $result ? 'PASS' : 'FAIL',
                    $nonce,
                    get_current_user_id(),
                    current_time('mysql')
                ), ['source' => 'wicket-gf-nonce']);

                if (!$result) {
                    $logger->warning('Nonce verification failed. Check session timeout settings.', ['source' => 'wicket-gf-nonce']);
                }
            }

            return $result;
        }, 10, 3);

        // Log Gravity Forms validation errors
        add_action('gform_validation', function ($validation_result) {
            if (!$validation_result['is_valid']) {
                $logger = wc_get_logger();
                $logger->info('Form validation failed for form ID: ' . $validation_result['form']['id'], ['source' => 'wicket-gf-nonce']);
                foreach ($validation_result['form']['fields'] as $field) {
                    if (isset($field->failed_validation) && $field->failed_validation) {
                        $logger->debug('Field validation failed - ID: ' . $field->id . ', Message: ' . ($field->validation_message ?? 'Unknown error'), ['source' => 'wicket-gf-nonce']);
                    }
                }
            }

            return $validation_result;
        });

        // Check PHP session configuration
        add_action('admin_init', function () {
            if (current_user_can('manage_options') && isset($_GET['wicket_gf_debug_session'])) {
                $logger = wc_get_logger();
                $logger->debug('session.gc_maxlifetime: ' . ini_get('session.gc_maxlifetime'), ['source' => 'wicket-gf-nonce']);
                $logger->debug('session.cookie_lifetime: ' . ini_get('session.cookie_lifetime'), ['source' => 'wicket-gf-nonce']);
                $logger->debug('WordPress nonce lifetime: ' . (wp_nonce_tick() * DAY_IN_SECONDS), ['source' => 'wicket-gf-nonce']);
                wp_die('Session debug info logged. Check WooCommerce logs.');
            }
        });
    }
}

// Initialize the nonce handler
Wicket_Gf_Nonce_Handler::init();
