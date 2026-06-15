<?php

/**
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Gravity Forms
 * Plugin URI:        https://wicket.io
 * Description:       Adds Wicket functionality to Gravity Forms.
 * Version:           2.4.10
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        https://wicket.io
 * Support:           https://wicket.io
 * Domain Path:       /languages
 * Text Domain:       wicket-gf
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: wicket-wp-base-plugin, gravityforms
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!in_array('gravityforms/gravityforms.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
    /**
     * Show Required Plugin Notice.
     */
    function wicket_gf_admin_notice()
    {
        // Deactivate the plugin.
        deactivate_plugins(__FILE__);

        $wicket_gf_plugin_check = '<div id="message" class="error">
            <p><strong>Wicket Gravity Forms plugin is inactive.</strong> The <a href="https://www.gravityforms.com/">Gravity Forms plugin</a> must be active for this plugin to be used. Please install &amp; activate Gravity Forms »</p></div>';
        echo wp_kses_post($wicket_gf_plugin_check);
    }

    add_action('admin_notices', 'wicket_gf_admin_notice');
}

// Plugin constants
define('WICKET_GF_VERSION', get_file_data(__FILE__, ['Version' => 'Version'], false)['Version']);
define('WICKET_GF_PATH', plugin_dir_path(__FILE__));
define('WICKET_GF_URL', plugin_dir_url(__FILE__));
define('WICKET_GF_BASENAME', plugin_basename(__FILE__));

// Back-compat alias used by includes/ and mapping addon
if (!defined('WICKET_WP_GF_VERSION')) {
    define('WICKET_WP_GF_VERSION', WICKET_GF_VERSION);
}

// Composer autoloader
if (file_exists(WICKET_GF_PATH . 'vendor/autoload.php')) {
    require_once WICKET_GF_PATH . 'vendor/autoload.php';
}

use WicketGF\Admin;
use WicketGF\Fields\ApiDataBind;
use WicketGF\Fields\ConsentFieldExtension;
use WicketGF\Fields\DataBindHidden;
use WicketGF\Fields\OrgSearchSelect;
use WicketGF\Fields\UserMdpTags;
use WicketGF\Fields\WidgetAdditionalInfo;
use WicketGF\Fields\WidgetPrefs;
use WicketGF\Fields\WidgetProfile;
use WicketGF\Fields\WidgetProfileOrg;
use WicketGF\MappingAddOn;
use WicketGF\MdpFieldDiscovery;
use WicketGF\MdpSyncEngine;
use WicketGF\MdpSyncLogger;
use WicketGF\MdpSyncLogsPage;
use WicketGF\MdpTypeCompatibility;
use WicketGF\NonceHandler;
use WicketGF\ObjectTypeWicket;
use WicketGF\SecureUploads;
use WicketGF\Validation;

/**
 * The main Wicket Gravity Forms class.
 */
class Wicket_Gf_Main
{
    private const CONFIRMATION_TYPE_SELF_REDIRECT = 'self_redirect';
    private const CONFIRMATION_TYPE_WC_CART_REDIRECT = 'wc_cart_redirect';
    private const CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT = 'wc_checkout_link_redirect';
    private const LEGACY_CONFIRMATION_FIELD_SELF_QUERY_STRING = 'wicket_self_query_string';

    /**
     * Plugin instance.
     * @var Wicket_Gf_Main|null
     */
    protected static $instance = null;

    /**
     * URL to this plugin's directory.
     * @var string
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory.
     * @var string
     */
    public $plugin_path = '';

    private static bool $live_update_script_enqueued = false;

    /**
     * MDP field discovery service.
     * @var MdpFieldDiscovery|null
     */
    protected ?MdpFieldDiscovery $mdp_discovery = null;

    /**
     * MDP sync engine.
     * @var MdpSyncEngine|null
     */
    protected ?MdpSyncEngine $mdp_sync = null;

    /**
     * MDP sync logger.
     * @var MdpSyncLogger|null
     */
    protected ?MdpSyncLogger $mdp_logger = null;

    /**
     * MDP sync logs admin page.
     * @var MdpSyncLogsPage|null
     */
    protected ?MdpSyncLogsPage $mdp_logs_page = null;

    /**
     * MDP type compatibility checker.
     * @var MdpTypeCompatibility|null
     */
    protected ?MdpTypeCompatibility $mdp_compat = null;

    /**
     * Class variables.
     */
    private static $wicket_current_person;
    private static $wicket_client;

    /**
     * Constructor. Intentionally left empty and public.
     */
    public function __construct() {}

    /**
     * Access this plugin's working instance.
     * @return Wicket_Gf_Main
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Used for regular plugin work.
     * @wp-hook plugins_loaded
     * @return void
     */
    public function plugin_setup()
    {
        $this->plugin_url = WICKET_GF_URL;
        $this->plugin_path = WICKET_GF_PATH;
        $this->load_language('wicket-gf');

        $this->project_includes();

        // Load includes that depend on the Wicket base plugin
        add_action('init', [$this, 'project_includes_after_base'], 99);

        // Other plugin initialization
        add_action('plugins_loaded', [$this, 'init']);

        // Hook for shortcode
        add_shortcode('wicket_gravityform', [$this, 'shortcode']);

        // Prevent WCS subscription metabox fatals by using safe callbacks
        add_action('add_meta_boxes', [$this, 'override_subscription_metabox_callbacks_on_add'], 1000, 2);

        // Initialize Wicket Org Validation
        new Validation();

        // Add a custom field group for Wicket fields
        add_filter('gform_add_field_buttons', function ($field_groups) {
            $field_groups[] = [
                'name'   => 'wicket_fields',
                'label'  => __('Wicket', 'wicket-gf'),
                'fields' => [
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_org_search_select',
                        'value'     => __('Org. Search', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_user_mdp_tags',
                        'value'     => __('MDP Tags', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_profile_individual',
                        'value'     => __('Profile Widget', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_data_hidden',
                        'value'     => __('JS Data Bind', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_api_data_bind',
                        'value'     => __('API Data Bind', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_profile_org',
                        'value'     => __('Org. Profile W.', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_ai',
                        'value'     => __('Add. Info. W.', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_prefs',
                        'value'     => __('Preferences', 'wicket-gf'),
                    ],
                ],
            ];

            return $field_groups;
        });

        // Register Custom GF fields
        if (class_exists('GF_Fields')) {
            // Gravity Forms is already loaded, register fields immediately
            $this->register_custom_fields();
        } else {
            // Gravity Forms not loaded yet, hook into the event
            add_action('gform_loaded', [$this, 'register_custom_fields'], 5);
        }
        add_action('gform_field_standard_settings', [$this, 'register_field_settings'], 25, 2);
        add_action('gform_editor_js', [$this, 'gf_editor_script']);
        add_action('gform_tooltips', [$this, 'register_tooltips']);
        add_filter('gform_form_settings_menu', [$this, 'register_wicket_settings_tab']);
        add_action('gform_form_settings_page_wicket_settings', [$this, 'render_wicket_settings_page']);
        add_filter('gform_form_settings_fields', [$this, 'register_form_settings_fields'], 10, 2);
        add_filter('gform_pre_form_settings_save', [$this, 'sanitize_mdp_form_settings']);
        add_filter('gform_form_update_meta', [$this, 'sanitize_mdp_field_mappings_on_save'], 10, 3);

        // MDP Sync Engine: push mapped field values after submission
        $this->get_mdp_sync()->register();

        // NOTE: DB-backed MdpSyncLogger and MdpSyncLogsPage are disabled.
        // Sync logging now uses Wicket()->log() (see MdpSyncEngine::write_log()).

        // Bootstrap the GF Addon for field mapping
        if (class_exists('GFForms') && method_exists('GFForms', 'include_feed_addon_framework')) {
            // Gravity Forms is already loaded, call immediately
            $this->gf_mapping_addon_load();
        } else {
            // Gravity Forms not loaded yet, hook into the event
            add_action('gform_loaded', [$this, 'gf_mapping_addon_load'], 5);
        }

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts_styles']);

        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts_styles']);

        // Register Rest Routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Flush slug transient cache when any form is saved
        add_action('gform_after_save_form', [$this, 'flush_slug_cache_on_save'], 10, 2);

        // Clear slug collisions on form import
        add_action('gform_after_import_form', [$this, 'clear_imported_slug_collision']);

        // AJAX handler for field slug validation
        add_action('wp_ajax_wicket_gf_validate_field_slug', [$this, 'ajax_validate_field_slug']);

        // Register Wicket tab in GF global settings navigation
        add_filter('gform_settings_menu', [Admin::class, 'register_gf_settings_tab']);
        add_action('gform_settings_wicket', [Admin::class, 'render_gf_settings_page']);

        // Add Options Page for plugin (legacy URL redirects to GF settings)
        add_action('admin_menu', [Admin::class, 'register_options_page'], 20);
        add_action('admin_init', [Admin::class, 'register_settings']);

        // Add settings link to plugins page listing
        add_filter('plugin_action_links_' . WICKET_GF_BASENAME, [Admin::class, 'add_settings_link']);

        // Allow all tags in gform fields that WP's wp_kses_post() allows
        add_filter('gform_allowable_tags', '__return_true');
        add_filter('wp_kses_allowed_html', [$this, 'update_kses_tags'], 1);

        // Modifying GF Entry screens
        add_action('gform_entries_first_column', [$this, 'entries_list_first_column_content'], 10, 5);
        add_filter('gform_get_field_value', [$this, 'gf_change_user_name'], 3);
        add_filter('gform_entry_detail_meta_boxes', [Admin::class, 'register_meta_box'], 10, 3);
        add_filter('gform_confirmation_settings_fields', [$this, 'extend_confirmation_settings_fields'], 10, 3);
        add_filter('gform_pre_confirmation_save', [$this, 'save_self_redirect_confirmation'], 10, 3);
        add_filter('gform_confirmation', [$this, 'handle_self_redirect_confirmation'], 10, 4);

        // Register scripts for conditional logic
        $this->register_conditional_logic_scripts();

        // Conditionally enqueue live update script for Wicket Hidden Data Bind fields
        add_action('gform_enqueue_scripts', [$this, 'conditionally_enqueue_live_update_script'], 10, 2);

        // Conditionally enqueue API Data Bind script for ORGSS binding
        add_action('gform_enqueue_scripts', [$this, 'conditionally_enqueue_api_data_bind_script'], 10, 2);

        // Admin footer script for debugging
        add_action('gform_footer_script', [$this, 'output_wicket_event_debugger_script']);

        // Add form pre-render hook for pagination sidebar layout and other dynamic features
        add_filter('gform_pre_render', [$this, 'gf_custom_pre_render'], 50, 1);

        // Inject field slug pills into GF preview mode
        add_filter('gform_field_content', [$this, 'inject_preview_slug_pill'], 10, 2);

        // Add data-slug attribute to field wrapper div on all frontend renders
        add_filter('gform_field_container', [$this, 'inject_field_slug_data_attr'], 10, 6);

        // Support dynamic population parameter: user_mdp_tags
        add_filter('gform_field_value_user_mdp_tags', [$this, 'populate_user_mdp_tags_dynamic_parameter']);
    }

    /**
     * Loads translation file.
     * @param string $domain
     */
    public function load_language($domain)
    {
        load_plugin_textdomain(
            $domain,
            false,
            dirname(WICKET_GF_BASENAME) . '/languages'
        );
    }

    /**
     * Override WCS metabox callbacks after all meta boxes are registered.
     *
     * @param string               $post_type The post type being edited.
     * @param WP_Post|WC_Order|null $post      The post object.
     * @return void
     */
    public function override_subscription_metabox_callbacks_on_add($post_type, $post)
    {
        $screen_id = function_exists('wcs_get_page_screen_id') ? wcs_get_page_screen_id('shop_subscription') : 'shop_subscription';
        if ($post_type !== 'shop_subscription' && $post_type !== $screen_id) {
            return;
        }

        $this->override_subscription_metabox_callbacks($screen_id);
    }

    /**
     * Replace WCS metabox callbacks with safe wrappers to avoid fatals.
     *
     * @param string $screen_id The screen ID to update.
     * @return void
     */
    private function override_subscription_metabox_callbacks($screen_id)
    {
        global $wp_meta_boxes;

        if (!isset($wp_meta_boxes) || !is_array($wp_meta_boxes)) {
            return;
        }

        $targets = [$screen_id, 'shop_subscription'];
        $map = [
            'woocommerce-subscription-schedule' => [$this, 'safe_subscription_schedule_metabox'],
            'woocommerce-subscription-data' => [$this, 'safe_subscription_data_metabox'],
        ];

        foreach ($targets as $target) {
            if (!isset($wp_meta_boxes[$target]) || !is_array($wp_meta_boxes[$target])) {
                continue;
            }
            foreach ($wp_meta_boxes[$target] as $context => $priorities) {
                if (!is_array($priorities)) {
                    continue;
                }
                foreach ($priorities as $priority => $boxes) {
                    if (!is_array($boxes)) {
                        continue;
                    }
                    foreach ($map as $id => $callback) {
                        if (isset($wp_meta_boxes[$target][$context][$priority][$id])) {
                            $wp_meta_boxes[$target][$context][$priority][$id]['callback'] = $callback;
                        }
                    }
                }
            }
        }
    }

    /**
     * Safe wrapper for the subscription schedule metabox.
     *
     * @param WP_Post|WC_Subscription $post The current post object.
     * @return void
     */
    public function safe_subscription_schedule_metabox($post)
    {
        if (!function_exists('wcs_get_subscription')) {
            return;
        }

        $subscription = null;

        if ($post instanceof WC_Subscription) {
            $subscription = $post;
        } elseif (is_object($post) && !empty($post->ID)) {
            if (isset($post->post_type) && $post->post_type !== 'shop_subscription') {
                $subscription = null;
            } else {
                $subscription = wcs_get_subscription($post->ID);
            }
        } elseif (is_numeric($post)) {
            $subscription = wcs_get_subscription(absint($post));
        }

        if (!$subscription) {
            $fallback_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
            if ($fallback_id) {
                $subscription = wcs_get_subscription($fallback_id);
            }
        }

        if ($subscription) {
            $GLOBALS['the_subscription'] = $subscription;
            $GLOBALS['post'] = get_post($subscription->get_id());
            WCS_Meta_Box_Schedule::output($subscription);
        }
    }

    /**
     * Safe wrapper for the subscription data metabox.
     *
     * @param WP_Post|WC_Subscription $post The current post object.
     * @return void
     */
    public function safe_subscription_data_metabox($post)
    {
        if (!function_exists('wcs_get_subscription')) {
            return;
        }

        $subscription = null;

        if ($post instanceof WC_Subscription) {
            $subscription = $post;
        } elseif (is_object($post) && !empty($post->ID)) {
            if (isset($post->post_type) && $post->post_type !== 'shop_subscription') {
                $subscription = null;
            } else {
                $subscription = wcs_get_subscription($post->ID);
            }
        } elseif (is_numeric($post)) {
            $subscription = wcs_get_subscription(absint($post));
        }

        if (!$subscription) {
            $fallback_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
            if ($fallback_id) {
                $subscription = wcs_get_subscription($fallback_id);
            }
        }

        if ($subscription) {
            $GLOBALS['the_subscription'] = $subscription;
            $GLOBALS['post'] = get_post($subscription->get_id());
            WCS_Meta_Box_Subscription_Data::output($subscription);
        }
    }

    /**
     * Inject field slug pill next to label in GF preview mode.
     *
     * Only active when GFCommon::is_preview() is true.
     * Renders a small pill matching the GF editor sidebar style.
     *
     * @param string   $content The field HTML content.
     * @param GF_Field $field   The Gravity Forms field object.
     * @return string Modified field content.
     */
    public function inject_preview_slug_pill($content, $field)
    {
        $slug = $field->wicket_field_slug ?? '';
        if ($slug === '') {
            return $content;
        }

        if (!GFCommon::is_preview()) {
            return $content;
        }

        // Inject copy-to-clipboard script once
        static $script_injected = false;
        if (!$script_injected) {
            $script_injected = true;
            $content .= '<script>'
                . '(function(){'
                . 'function wicketCopySlug(el,slug){'
                    . 'navigator.clipboard.writeText(slug).then(function(){'
                        . 'var orig=el.style.backgroundColor;'
                        . 'el.style.backgroundColor="#d4edda";'
                        . 'el.style.borderColor="#28a745";'
                        . 'var tip=document.createElement("span");'
                        . 'tip.textContent="Copied!";'
                        . 'tip.style.cssText="font-size:11px;color:#28a745;margin-left:4px;vertical-align:middle;font-weight:600;";'
                        . 'el.parentNode.insertBefore(tip,el.nextSibling);'
                        . 'setTimeout(function(){'
                            . 'el.style.backgroundColor=orig;'
                            . 'el.style.borderColor="#d5d7e9";'
                            . 'tip.remove();'
                        . '},1500);'
                    . '});'
                . '}'
                . 'window.wicketCopySlug=wicketCopySlug;'
                . '})();'
                . '</script>';
        }

        $pill = sprintf(
            '<span onclick="wicketCopySlug(this,\'%1$s\')" style="display:inline-block;background-color:#ecedf8;border:1px solid #d5d7e9;border-radius:40px;font-size:.6875rem;font-weight:600;padding:.1125rem .4625rem;font-family:monospace;margin-left:6px;vertical-align:middle;cursor:pointer;" title="Click to copy slug: %1$s">Slug: %1$s</span>',
            esc_js($slug),
            esc_attr($slug),
            esc_html($slug)
        );

        // Inject right before the </label> closing tag so it stays inline
        $content = preg_replace(
            '/(<\/label>)/i',
            $pill . '$1',
            $content,
            1
        );

        return $content;
    }

    /**
     * Add data-slug attribute to the field wrapper div on all frontend renders.
     *
     * Uses gform_field_container filter which has access to the outer <div> wrapper.
     *
     * @param string   $field_container The field container HTML.
     * @param GF_Field $field           The field object.
     * @param array    $form            The form object.
     * @param string   $css_class       CSS classes.
     * @param string   $style           Inline style.
     * @param string   $field_content   The field content HTML.
     * @return string Modified container HTML.
     */
    public function inject_field_slug_data_attr($field_container, $field, $form, $css_class, $style, $field_content)
    {
        $slug = $field->wicket_field_slug ?? '';
        if ($slug === '') {
            return $field_container;
        }

        $slug_attr = esc_attr($slug);

        return preg_replace(
            '/(<div[^>]*)(>)/i',
            '$1 data-slug="' . $slug_attr . '"$2',
            $field_container,
            1
        );
    }

    /**
     * Handle form pre-render for pagination sidebar layout and other dynamic features.
     * @param array $form
     * @return array
     */
    public function gf_custom_pre_render($form)
    {
        // Ensure fields array exists to prevent null errors
        if (!isset($form['fields']) || !is_array($form['fields'])) {
            $form['fields'] = [];
        }

        // Add sidebar layout styles if toggled in Wicket GF options
        if (get_option('wicket_gf_pagination_sidebar_layout')) {
            ob_start(); ?>

            <div class="wicket-gf-dynamic-hidden-html">
                <script>
                    window.addEventListener('load', function() {
                        if (document.querySelector('body') !== null) {

                            // Check and see if the page is using the steps version of pagination,
                            // and if so re-format it
                            let paginationStepsCheck = document.querySelector('.gf_page_steps');
                            if (paginationStepsCheck != null) {
                                document.head.insertAdjacentHTML("beforeend", `
                        <style>
                            @media(min-width:768px) {
                                form[id^=gform_] {
                                    display: flex;
                                }
                                .gf_page_steps {
                                    display: flex;
                                    flex-direction: column;
                                    min-width: 250px;
                                }
                                .gform_body {
                                    flex-grow: 1;
                                }

                                body.wicket-theme-v2 form[id^=gform_] {
                                    gap: var(--space-200);
                                }
                            }
                            @media(max-width:767px) {
                                .gf_page_steps .gf_step {
                                    margin-top: 0px !important;
                                    margin-bottom: 0px !important;
                                    margin-right: 5px !important;
                                }
                            }
                            .gf_page_steps .gf_step {
                                border-radius: var(--interactive-corner-radius-lg, 999px);
                            }
                            .gf_page_steps .gf_step:not(.gf_step_active) {
                                padding-left: var(--space-100, 5px);
                                padding-right: var(--space-100, 5px);
                            }
                            .gf_page_steps .gf_step_active {
                                background: var(--highlight-light, #efefef);
                                padding: var(--space-100, 5px);
                                margin-left: -5px !important;
                            }
                            body.wicket-theme-v2 .gf_page_steps .gf_step_active {
                                margin-left: 0px !important;
                            }
                            .gform_wrapper .gf_page_steps .gf_step .gf_step_label {
                                padding-left: var(--space-100, 16px);
                                font-size: var(--body-md-font-size, 14px);
                                line-height: var(--body-md-line-height, 16px);
                                font-weight: bold;
                                color: var(--text-content, inherit);
                            }
                            .gform_wrapper .gf_page_steps .gf_step .gf_step_number {
                                font-weight: bold;
                                color: var(--text-content, #585e6a);
                            }
                            .gform_wrapper .gf_page_steps .gf_step_active .gf_step_number {
                                background: var(--interactive, #cfd3d9);
                                border-color: var(--interactive, #cfd3d9);
                                color: var(--text-content-reversed, #607382);
                                border-width: var(--border-interactive-md, 2px);
                            }
                            body.wicket-theme-v2 .gf_page_steps .gf_step_completed {
                                position: relative;
                            }
                            body.wicket-theme-v2 .gf_page_steps .gf_step_completed .gf_step_number {
                                background: var(--highlight-dark, var(--gf-local-bg-color, #000) );
                                position: relative;
                            }
                            .gform_wrapper .gf_page_steps .gf_step_completed .gf_step_number:before {
                                background: var(--highlight-light, #607382);
                                border-color: var(--highlight-dark, #607382);
                                border-width: var(--border-interactive-md, 2px);
                            }
                            .gform_wrapper .gf_page_steps .gf_step_completed .gf_step_number:after {
                                color: var(--highlight-dark, #ffffff);
                                background-color: var(--highlight-light, #607382);
                            }
                            body.wicket-theme-v2 .gf_page_steps .gf_step_completed .gf_step_number:after {
                                left: 0px;
                                top: 0px;
                                border-color: var(--highlight-dark);
                                border-radius: 20px;
                            }
                            /* Orbital theme compatibility fix */
                            body.wicket-theme-v2 .gform-theme--orbital .gf_page_steps .gf_step_completed .gf_step_number:after {
                                left: -2px;
                                top: -2px;
                            }
                            .gform_wrapper .gf_page_steps .gf_step_pending .gf_step_number {
                                border-width: var(--border-interactive-md, 2px);
                                border-color: var(--border-interactive, #cfd3d9);
                            }
                        </style>`);
                            }
                        }
                    });
                </script>
            </div>

        <?php $output = ob_get_clean();

            // Dynamically create and add this HTML form field on render
            $props = [
                'id'      => 3000,
                'label'   => 'Dynamic Styles - Do Not Edit',
                'type'    => 'html',
                'content' => $output,
            ];
            $field = GF_Fields::create($props);
            array_push($form['fields'], $field);
        }

        // Loop fields and hide label if toggled with our custom checkbox
        $hide_label_i = 1;
        foreach ($form['fields'] as $field) {
            if (isset($field['hide_label'])) {
                if ($field['hide_label']) {
                    // Dynamically create and add this HTML form field on render
                    $props = [
                        'id'      => (3000 + $hide_label_i),
                        'label'   => 'Dynamic Styles - Do Not Edit',
                        'type'    => 'html',
                        'content' => '
                            <div class="wicket-gf-dynamic-hidden-html">
                            <style>
                                .gform_wrapper label[for="input_' . $field['formId'] . '_' . $field['id'] . '"].gfield_label {
                                    display: none;
                                }
                            </style>
                            </div>
                            ',
                    ];
                    $field = GF_Fields::create($props);
                    array_push($form['fields'], $field);
                }
            }
            $hide_label_i++;
        }

        $org_uuid = '';

        // Find the org_uuid from the POST data of the previous page
        foreach ($form['fields'] as $field) {
            if ($field->type == 'wicket_org_search_select') {
                // Use standard GF naming convention
                $field_name = 'input_' . $field->id;

                if (!empty($_POST[$field_name])) {
                    $org_uuid = sanitize_text_field($_POST[$field_name]);
                    break;
                }
            }
        }

        if (!empty($org_uuid)) {
            // Loop through the fields again to populate the widgets that need the org_uuid
            foreach ($form['fields'] as &$field) {
                // The Org Profile Edit Widget
                if ($field->type == 'wicket_widget_profile_org') {
                    $field->wwidget_org_profile_uuid = $org_uuid;
                }

                // The Additional Info Widget
                if ($field->type == 'wicket_widget_ai') {
                    $field->org_uuid = $org_uuid;
                }

                // The Individual Profile Widget
                if ($field->type == 'wicket_widget_profile_individual') {
                    $field->org_uuid = $org_uuid;
                }
            }
        }

        return $form;
    }

    public static function gf_mapping_addon_load()
    {
        if (!method_exists('GFForms', 'include_feed_addon_framework')) {
            return;
        }

        GFAddOn::register('WicketGF\\MappingAddOn');

        add_action('gform_form_settings_page_wicketmap', [MappingAddOn::class, 'addon_custom_ui'], 20);
    }

    public function register_form_settings_fields($fields, $form)
    {
        // Wicket settings moved to dedicated "Wicket" tab in settings navigation.
        // Keep the filter registered so nothing breaks, but inject no sections.
        return $fields;
    }

    /**
     * Register the "Wicket" tab in the form settings navigation.
     *
     * @param array $tabs Existing settings tabs.
     * @return array Modified tabs.
     */
    public function register_wicket_settings_tab(array $tabs): array
    {
        $tabs['50'] = [
            'name'         => 'wicket_settings',
            'label'        => __('Wicket', 'wicket-gf'),
            'icon'         => 'dashicons-admin-generic',
            'query'        => ['fid' => null],
            'capabilities' => ['gravityforms_edit_forms'],
        ];

        return $tabs;
    }

    /**
     * Render the Wicket settings page as a read-only summary of MDP field mappings.
     */
    public function render_wicket_settings_page(): void
    {
        $form_id = absint(rgget('id'));
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            echo '<p>' . esc_html__('Form not found.', 'wicket-gf') . '</p>';

            return;
        }

        $entity_type = $form['wicket_mdp_entity_type'] ?? '';

        // Resolve current slug from global option (reverse lookup)
        $current_slug = '';
        $current_mappings_json = get_option('wicket_gf_slug_mapping', '');
        $current_mappings = json_decode($current_mappings_json, true);
        if (is_array($current_mappings)) {
            $form_id_str = (string) $form_id;
            foreach ($current_mappings as $mapped_slug => $mapped_id) {
                if ((string) $mapped_id === $form_id_str) {
                    $current_slug = $mapped_slug;
                    break;
                }
            }
        }

        // Handle slug save from inline form
        if (isset($_POST['wicket_save_slug']) && check_admin_referer('update-options')) {
            $new_slug = sanitize_title((string) ($_POST['wicket_mdp_form_slug'] ?? ''));
            $mappings_json = get_option('wicket_gf_slug_mapping', '');
            $mappings = json_decode($mappings_json, true);
            if (!is_array($mappings)) {
                $mappings = [];
            }
            $form_id_str = (string) $form_id;
            // Remove old entry for this form
            foreach ($mappings as $existing_slug => $existing_id) {
                if ((string) $existing_id === $form_id_str) {
                    unset($mappings[$existing_slug]);
                }
            }
            // Remove collision if another form owns the target slug
            if ($new_slug !== '' && isset($mappings[$new_slug])) {
                GFCommon::add_error_message(sprintf(
                    esc_html__('Form slug "%s" is already in use by another form.', 'wicket-gf'),
                    esc_html($new_slug)
                ));
                $new_slug = '';
            }
            if ($new_slug !== '') {
                $mappings[$new_slug] = $form_id_str;
            }
            update_option('wicket_gf_slug_mapping', json_encode($mappings));
            wicket_gf_flush_slug_cache();
            $current_slug = $new_slug;
        }

        // Build the mapped fields list
        $mapped_fields = [];
        $fields = $form['fields'] ?? [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $mdp_enabled = false;
                $field_id = '';
                $field_label = '';

                if (is_object($field)) {
                    $mdp_enabled = !empty($field->wicket_enable_mdp_mapping);
                    $field_id = (string) ($field->id ?? '');
                    $field_label = $field->label ?? $field->adminLabel ?? '';
                } elseif (is_array($field)) {
                    $mdp_enabled = !empty($field['wicket_enable_mdp_mapping']);
                    $field_id = (string) ($field['id'] ?? '');
                    $field_label = $field['label'] ?? $field['adminLabel'] ?? '';
                }

                if (!$mdp_enabled) {
                    continue;
                }

                $target_object = '';
                $target_field = '';
                if (is_object($field)) {
                    $target_object = $field->wicket_mdp_target_object ?? '';
                    $target_field = $field->wicket_mdp_target_field ?? '';
                } elseif (is_array($field)) {
                    $target_object = $field['wicket_mdp_target_object'] ?? '';
                    $target_field = $field['wicket_mdp_target_field'] ?? '';
                }

                if ($target_object === '' || $target_field === '') {
                    continue;
                }

                // Resolve human-readable labels
                $object_labels = [
                    'person_profile' => 'Person Profile',
                    'additional_info' => 'Additional Info',
                    'preferences' => 'Preferences',
                    'org_profile' => 'Org Profile',
                ];

                $target_object_label = $object_labels[$target_object] ?? $target_object;
                $target_field_label = $target_field;

                // Try to resolve field label from discovery
                $discovery_fields = $this->get_mdp_target_fields($target_object);
                foreach ($discovery_fields as $df) {
                    if ($df['value'] === $target_field) {
                        $target_field_label = $df['label'];
                        break;
                    }
                }

                $mapped_fields[] = [
                    'field_id' => $field_id,
                    'field_label' => $field_label ?: sprintf('Field %s', $field_id),
                    'target_object_label' => $target_object_label,
                    'target_field_label' => $target_field_label,
                ];
            }
        }

        GFFormSettings::page_header(__('Wicket Settings', 'wicket-gf'));
        ?>
        <div class="gform-settings__content">
            <form id="gform-settings" class="gform_settings_form" method="post" enctype="multipart/form-data" novalidate="">

                <!-- Form Slug Section -->
                <fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title">
                    <legend class="gform-settings-panel__title gform-settings-panel__title--header"><?php esc_html_e('Form Slug', 'wicket-gf'); ?></legend>
                    <div class="gform-settings-panel__content">
                        <div class="gform-settings-field gform-settings-field__text">
                            <div class="gform-settings-field__header">
                                <label class="gform-settings-label" for="wicket_mdp_form_slug"><?php esc_html_e('Slug', 'wicket-gf'); ?></label>
                            </div>
                            <span class="gform-settings-input__container">
                                <input type="text" id="wicket_mdp_form_slug" name="wicket_mdp_form_slug" value="<?php echo esc_attr($current_slug); ?>" />
                            </span>
                            <p class="gform-settings-description"><?php esc_html_e('Unique identifier used in code via wicket_gf_get_form_id_by_slug(). Changing it may break shortcode references.', 'wicket-gf'); ?></p>
                        </div>
                    </div>
                </fieldset>

                <!-- MDP Configuration Section -->
                <?php if ($entity_type): ?>
                <fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title">
                    <legend class="gform-settings-panel__title gform-settings-panel__title--header"><?php esc_html_e('MDP Configuration', 'wicket-gf'); ?></legend>
                    <div class="gform-settings-panel__content">
                        <div class="gform-settings-field gform-settings-field__text" style="display:flex;gap:24px;flex-wrap:wrap;">
                            <?php if ($entity_type): ?>
                            <div>
                                <div class="gform-settings-field__header"><label class="gform-settings-label"><?php esc_html_e('Entity Type', 'wicket-gf'); ?></label></div>
                                <span style="color:#2271b1;font-weight:500;"><?php echo esc_html($entity_type === 'person' ? 'Person' : ($entity_type === 'organization' ? 'Organization' : $entity_type)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </fieldset>
                <?php endif; ?>

                <!-- Mapped Fields Section -->
                <fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title">
                    <legend class="gform-settings-panel__title gform-settings-panel__title--header"><?php esc_html_e('Mapped Fields', 'wicket-gf'); ?></legend>
                    <div class="gform-settings-panel__content">
                        <?php if (empty($mapped_fields)): ?>
                            <p style="color:#646970;"><?php esc_html_e('No fields have MDP mapping enabled yet. Enable MDP Mapping on any field in the form editor to configure mappings.', 'wicket-gf'); ?></p>
                        <?php else: ?>
                            <table class="gform-settings-panel__table" style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #dcdcde;font-weight:600;color:#646970;"><?php esc_html_e('Field', 'wicket-gf'); ?></th>
                                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #dcdcde;font-weight:600;color:#646970;"><?php esc_html_e('Target Object', 'wicket-gf'); ?></th>
                                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #dcdcde;font-weight:600;color:#646970;"><?php esc_html_e('Target Field', 'wicket-gf'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mapped_fields as $mf): ?>
                                        <tr>
                                            <td style="padding:8px 12px;border-bottom:1px solid #dcdcde;">
                                                <a href="<?php echo esc_url('?page=gf_edit_forms&id=' . $form_id . '&fid=' . $form_id . '#gf-field-' . $mf['field_id']); ?>">
                                                    <?php echo esc_html($mf['field_label']); ?>
                                                </a>
                                            </td>
                                            <td style="padding:8px 12px;border-bottom:1px solid #dcdcde;"><?php echo esc_html($mf['target_object_label']); ?></td>
                                            <td style="padding:8px 12px;border-bottom:1px solid #dcdcde;"><?php echo esc_html($mf['target_field_label']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <div class="gform-settings-save-container">
                    <button type="submit" name="wicket_save_slug" value="save" class="primary button large"><?php esc_html_e('Save Settings', 'wicket-gf'); ?> &nbsp;→</button>
                </div>

            <?php wp_nonce_field('update-options'); ?>
            </form>
        </div>
        <?php
        GFFormSettings::page_footer();
    }

    public function sanitize_mdp_form_settings($form)
    {
        // Form slugs are stored in wicket_gf_slug_mapping, not on the form object.
        // This filter runs on the standard GF settings save path; the Wicket Settings
        // tab uses its own save_callback. Strip any slug that leaks through from
        // other save paths so it doesn't write to form display_meta.
        if (isset($form['wicket_mdp_form_slug'])) {
            $slug = sanitize_title((string) $form['wicket_mdp_form_slug']);
            if ($slug !== '') {
                // Sync to canonical storage (unless save_callback already handled it)
                $mappings_json = get_option('wicket_gf_slug_mapping', '');
                $mappings = json_decode($mappings_json, true);
                if (!is_array($mappings)) {
                    $mappings = [];
                }
                $form_id_str = (string) $form['id'];
                // Remove existing entry for this form
                foreach ($mappings as $existing_slug => $existing_id) {
                    if ((string) $existing_id === $form_id_str) {
                        unset($mappings[$existing_slug]);
                    }
                }
                if (!isset($mappings[$slug])) {
                    $mappings[$slug] = $form_id_str;
                    update_option('wicket_gf_slug_mapping', json_encode($mappings));
                    wicket_gf_flush_slug_cache();
                }
            }
            $form['wicket_mdp_form_slug'] = '';
        }

        $entity_type = isset($form['wicket_mdp_entity_type']) ? (string) $form['wicket_mdp_entity_type'] : '';
        if (!in_array($entity_type, ['', 'person', 'organization'], true)) {
            $form['wicket_mdp_entity_type'] = '';
        }

        return $form;
    }

    /**
     * Server-side safety net: sanitize field-level MDP mapping properties
     * before the form is saved to the database.
     *
     * Mirrors the JS-side gform_pre_form_editor_save filter.
     * Handles edge cases where JS validation may be bypassed (import, API).
     *
     * @param mixed  $meta      Form meta (form object) being saved.
     * @param int    $form_id   The form ID.
     * @param string $meta_name The type of meta ('display_meta', 'notifications', 'confirmations').
     * @return mixed Sanitized form meta.
     */
    public function sanitize_mdp_field_mappings_on_save($meta, $form_id, $meta_name)
    {
        unset($form_id);

        if ($meta_name !== 'display_meta') {
            return $meta;
        }

        if (!is_array($meta) || empty($meta['fields']) || !is_array($meta['fields'])) {
            return $meta;
        }

        foreach ($meta['fields'] as $field) {
            if (!is_object($field)) {
                continue;
            }

            $mdp_enabled = isset($field->wicket_enable_mdp_mapping)
                && $field->wicket_enable_mdp_mapping;

            if (!$mdp_enabled) {
                // Strip MDP properties from disabled fields
                $field->wicket_mdp_target_object = '';
                $field->wicket_mdp_target_field = '';
                continue;
            }

            $target_object = isset($field->wicket_mdp_target_object)
                ? (string) $field->wicket_mdp_target_object
                : '';

            // Get available target fields for this object
            $available_fields = $this->get_mdp_target_field_values($target_object);

            // If target object is empty, strip any stale target field
            if ($target_object === '') {
                $field->wicket_mdp_target_field = '';
                continue;
            }

            // If target object is unsupported (no fields), strip
            if (empty($available_fields)) {
                $field->wicket_mdp_target_object = '';
                $field->wicket_mdp_target_field = '';
                continue;
            }

            // If target field is invalid for the object, strip it
            $target_field = isset($field->wicket_mdp_target_field)
                ? (string) $field->wicket_mdp_target_field
                : '';

            if ($target_field !== '' && !in_array($target_field, $available_fields, true)) {
                $field->wicket_mdp_target_field = '';
            }
        }

        // Slug dedup: if multiple fields share a slug, strip from all but the first
        if (isset($meta['fields'])) {
            $seen_slugs = [];
            foreach ($meta['fields'] as $field) {
                $slug = $field->wicket_field_slug ?? '';
                if ($slug === '') {
                    continue;
                }
                $slug = sanitize_title($slug);
                $field->wicket_field_slug = $slug;
                if (isset($seen_slugs[$slug])) {
                    $field->wicket_field_slug = '';
                } else {
                    $seen_slugs[$slug] = true;
                }
            }
        }

        return $meta;
    }

    /**
     * Return the list of valid target field values for a given target object.
     *
     * @param string $target_object The target object key (e.g. 'person_profile').
     * @return string[] Array of valid field value strings.
     */
    /**
     * Get the MDP field discovery service (lazy-loaded).
     *
     * @return MdpFieldDiscovery
     */
    protected function get_mdp_discovery(): MdpFieldDiscovery
    {
        if ($this->mdp_discovery === null) {
            $this->mdp_discovery = new MdpFieldDiscovery();
        }

        return $this->mdp_discovery;
    }

    /**
     * Get the MDP sync engine (lazy-loaded).
     *
     * @return MdpSyncEngine
     */
    protected function get_mdp_sync(): MdpSyncEngine
    {
        if ($this->mdp_sync === null) {
            $this->mdp_sync = new MdpSyncEngine($this->get_mdp_discovery());
        }

        return $this->mdp_sync;
    }

    /**
     * Get the MDP sync logger (lazy-loaded).
     *
     * DISABLED: DB-backed logger is dormant. Sync logging uses Wicket()->log().
     * Kept for potential future re-enablement.
     *
     * @return MdpSyncLogger
     */
    protected function get_mdp_logger(): MdpSyncLogger
    {
        if ($this->mdp_logger === null) {
            $this->mdp_logger = new MdpSyncLogger();
            // Intentionally not calling register() - DB logging disabled.
        }

        return $this->mdp_logger;
    }

    /**
     * Get the MDP sync logs admin page (lazy-loaded).
     *
     * DISABLED: Admin logs list page is dormant. Sync logging uses Wicket()->log().
     * Kept for potential future re-enablement.
     *
     * @return MdpSyncLogsPage
     */
    protected function get_mdp_logs_page(): MdpSyncLogsPage
    {
        if ($this->mdp_logs_page === null) {
            $this->mdp_logs_page = new MdpSyncLogsPage($this->get_mdp_logger());
            // Intentionally not calling register() - admin logs page disabled.
        }

        return $this->mdp_logs_page;
    }

    /**
     * Get the MDP type compatibility checker (lazy-loaded).
     *
     * @return MdpTypeCompatibility
     */
    protected function get_mdp_compat(): MdpTypeCompatibility
    {
        if ($this->mdp_compat === null) {
            $this->mdp_compat = new MdpTypeCompatibility();
        }

        return $this->mdp_compat;
    }

    protected function get_mdp_target_field_values($target_object)
    {
        return $this->get_mdp_discovery()->getTargetFieldValues($target_object);
    }

    /**
     * Return the target field definitions for a given target object.
     * Delegates to MdpFieldDiscovery for dynamic field discovery.
     *
     * @param string $target_object The target object key.
     * @return array[] Array of ['value' => string, 'label' => string].
     */
    protected function get_mdp_target_fields($target_object)
    {
        return $this->get_mdp_discovery()->getTargetFields($target_object);
    }

    /**
     * Return all target field definitions grouped by target object.
     * Used as single source of truth for JS injection.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    protected function get_all_mdp_target_fields()
    {
        return $this->get_mdp_discovery()->getAllTargetFields();
    }

    protected function get_mdp_target_object_choices($entity_type = '')
    {
        $choices = [
            'person' => [
                [
                    'label' => esc_html__('Person Profile', 'wicket-gf'),
                    'value' => 'person_profile',
                ],
                [
                    'label' => esc_html__('Additional Info', 'wicket-gf'),
                    'value' => 'additional_info',
                ],
                [
                    'label' => esc_html__('Preferences', 'wicket-gf'),
                    'value' => 'preferences',
                ],
            ],
            'organization' => [
                [
                    'label' => esc_html__('Org Profile', 'wicket-gf'),
                    'value' => 'org_profile',
                ],
            ],
        ];

        if ($entity_type === '') {
            return $choices;
        }

        return $choices[$entity_type] ?? [];
    }

    /**
     * Register tooltips for custom field settings.
     *
     * @param array $tooltips
     * @return array
     */
    public function register_tooltips($tooltips)
    {
        $tooltips['live_update_enable_setting'] = __('Enable data binding to automatically populate this field with data from Wicket profiles or organizations.', 'wicket-gf');
        $tooltips['live_update_data_source_setting'] = __('Choose the data source for binding. Person data comes from the current logged-in user, while organization data requires a UUID.', 'wicket-gf');
        $tooltips['live_update_organization_uuid_setting'] = __('Enter the UUID of the organization whose data you want to bind to this field.', 'wicket-gf');
        $tooltips['live_update_schema_slug_setting'] = __('Select the schema or data category to bind from the chosen data source.', 'wicket-gf');
        $tooltips['live_update_value_key_setting'] = __('Choose the specific field/property to bind from the selected schema.', 'wicket-gf');

        return $tooltips;
    }

    /**
     * Includes required files.
     *
     * @return void
     */
    private function project_includes()
    {
        // Debug
        //require_once WICKET_GF_PATH . 'includes/class-debug-gravityforms-state.php';

        require_once WICKET_GF_PATH . 'src/state-fix.php';
        require_once WICKET_GF_PATH . 'src/helpers.php';
        require_once WICKET_GF_PATH . 'src/tweaks.php';
        NonceHandler::init();
        SecureUploads::init();
        ConsentFieldExtension::get_instance();
    }

    /**
     * Register custom fields with Gravity Forms.
     *
     * @return void
     */
    public function register_custom_fields()
    {
        // Register the custom fields
        GF_Fields::register(new OrgSearchSelect());
        GF_Fields::register(new UserMdpTags());
        GF_Fields::register(new WidgetProfile());
        GF_Fields::register(new DataBindHidden());
        GF_Fields::register(new ApiDataBind());
        GF_Fields::register(new WidgetProfileOrg());
        GF_Fields::register(new WidgetAdditionalInfo());
        GF_Fields::register(new WidgetPrefs());

        WidgetProfile::init();
        WidgetProfileOrg::init();
        WidgetAdditionalInfo::init();
        WidgetPrefs::init();
    }

    /**
     * Conditionally include files after the Wicket Base Plugin has loaded.
     *
     * @return void
     */
    public function project_includes_after_base()
    {
        // Only initialize this plugin if Populate Anything plugin is active and the wicket_api_client() function exists
        if (class_exists('GP_Populate_Anything') && class_exists('GPPA_Object_Type') && function_exists('wicket_api_client')) {
            \gp_populate_anything()->register_object_type('wicket', ObjectTypeWicket::class);
        }
    }

    /**
     * Register scripts for conditional logic support.
     */
    public function register_conditional_logic_scripts()
    {
        // Add a script to handle conditional logic updates for our custom fields
        add_action('gform_enqueue_scripts', [$this, 'enqueue_conditional_logic_script']);
    }

    /**
     * Enqueue script for conditional logic support.
     */
    public function enqueue_conditional_logic_script()
    {
        wp_enqueue_script(
            'wicket-gf-conditional-logic',
            plugins_url('assets/js/wicket-gf-conditional-logic.js', __FILE__),
            ['jquery', 'gform_conditional_logic'],
            WICKET_WP_GF_VERSION,
            true
        );
    }

    public function conditionally_enqueue_live_update_script($form, $is_ajax): void
    {
        if (self::$live_update_script_enqueued || is_admin()) {
            return;
        }

        if (empty($form) || !isset($form['fields']) || !is_array($form['fields'])) {
            return;
        }

        $should_enqueue = false;
        foreach ($form['fields'] as $field) {
            // Check if $field is an object and has the necessary properties
            if (is_object($field) && isset($field->type) && $field->type === 'wicket_data_hidden' && !empty($field->liveUpdateEnabled)) {
                $should_enqueue = true;
                break;
            }
        }

        if ($should_enqueue) {
            $plugin_url = WICKET_GF_URL;
            wp_enqueue_script(
                'wicket-gf-hidden-data-bind',
                $plugin_url . 'assets/js/wicket-gf-hidden-data-bind.js',
                ['jquery', 'gform_gravityforms'],
                WICKET_WP_GF_VERSION,
                true
            );
            self::$live_update_script_enqueued = true;
        }
    }

    /**
     * Conditionally enqueue API Data Bind script for ORGSS binding.
     *
     * @param array $form The form object
     * @param bool $is_ajax Whether this is an AJAX request
     * @return void
     */
    public function conditionally_enqueue_api_data_bind_script($form, $is_ajax): void
    {
        if (is_admin()) {
            return;
        }

        if (empty($form) || !isset($form['fields']) || !is_array($form['fields'])) {
            return;
        }

        // Check if any API Data Bind fields need the frontend script
        if (ApiDataBind::should_enqueue_frontend_js($form)) {
            $plugin_url = WICKET_GF_URL;

            wp_enqueue_script(
                'wicket-gf-api-data-bind',
                $plugin_url . 'assets/js/wicket-gf-api-data-bind.js',
                ['jquery', 'gform_gravityforms'],
                WICKET_WP_GF_VERSION,
                true
            );

            // Localize script with AJAX configuration
            wp_localize_script('wicket-gf-api-data-bind', 'wicketGfApiDataBindConfig', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wicket_gf_api_data_bind'),
            ]);
        }
    }

    /**
     * Enqueue all the scripts and styles.
     */
    public function enqueue_scripts_styles($screen)
    {
        // Check if we are on a Gravity Forms admin page
        if (method_exists('GFForms', 'is_gravity_page') && GFForms::is_gravity_page()) {
            // Always enqueue core admin styles and scripts for custom field functionality
            wp_enqueue_style('wicket-gf-admin-style', plugins_url('assets/css/wicket_gf_admin_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

            // Enqueue mapping-specific scripts when on the mapping subview
            if (isset($_GET['subview']) && isset($_GET['fid'])) {
                if ($_GET['subview'] == 'wicketmap') {
                    wp_enqueue_style('wicket-gf-addon-style', plugins_url('assets/css/wicket_gf_addon_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');
                    wp_enqueue_script('wicket-gf-addon-script', plugins_url('assets/js/wicket_gf_addon_script.js', __FILE__), ['jquery'], null, true);
                }
            }
        }
    }

    public function enqueue_frontend_scripts_styles()
    {
        wp_enqueue_style('wicket-gf-widget-style', plugins_url('assets/css/wicket_gf_widget_style_helpers.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

        // General Wicket GF Styles
        wp_enqueue_style('wicket-gf-general-style', plugins_url('assets/css/wicket_gf_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

        // General Wicket GF Scripts
        wp_enqueue_script('wicket-gf-general-script', plugins_url('assets/js/wicket_gf_script.js', __FILE__), ['jquery'], WICKET_WP_GF_VERSION, true);

        // Include styles only if current theme name doesn't begin with: wicket-
        $theme = wp_get_theme();
        if (!str_starts_with($theme->get('Name'), 'wicket-')) {
            wp_enqueue_style('wicket-gf', plugins_url('assets/css/wicket_gf.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');
        }

        // Pass data to the script
        wp_localize_script(
            'wicket-gf-general-script',
            'WicketGfPluginData',
            [
                // Deprecated global flag retained for backwards compatibility.
                'shouldAutoAdvance' => false,
            ]
        );
    }

    /**
     * Register custom field settings for all Wicket fields.
     *
     * @param int $position
     * @param int $form_id
     * @return void
     */
    public function register_field_settings($position, $form_id)
    {
        // Call global custom fields (like Hide Label)
        self::gf_editor_global_custom_fields($position, $form_id);

        // Call each field's custom settings (only fields that have them)
        OrgSearchSelect::custom_settings($position, $form_id);

        DataBindHidden::custom_settings($position, $form_id);

        ApiDataBind::custom_settings($position, $form_id);

        UserMdpTags::custom_settings($position, $form_id);

        WidgetProfile::custom_settings($position, $form_id);

        WidgetProfileOrg::custom_settings($position, $form_id);

        WidgetAdditionalInfo::custom_settings($position, $form_id);

        WidgetPrefs::custom_settings($position, $form_id);

        // Add consent field extension settings
        ConsentFieldExtension::custom_settings($position, $form_id);
    }

    /**
     * Output field-specific editor scripts.
     *
     * @return void
     */
    private function output_field_editor_scripts()
    {
        // Call each field's editor scripts
        OrgSearchSelect::editor_script();

        DataBindHidden::editor_script();

        ApiDataBind::editor_script();

        UserMdpTags::editor_script();

        WidgetProfile::editor_script();

        WidgetProfileOrg::editor_script();

        WidgetAdditionalInfo::editor_script();

        WidgetPrefs::editor_script();

        ConsentFieldExtension::editor_script();
    }

    public static function gf_editor_global_custom_fields($position, $form_id)
    {
        // Create settings on position 25 (right after Field Label)
        if ($position === 25) {
            ob_start(); ?>

            <li class="wicket_global_custom_settings wicket_global_custom_settings_field_slug field_setting">
                <style>
                    .wicket_global_custom_settings_field_slug { display: block !important; }
                    .wicket-field-slug-view { display: flex; align-items: center; gap: 6px; margin-top: 4px; width: 100%; }
                    .wicket-field-slug-display { font-family: monospace; padding: 3px 8px; border-radius: 3px; font-size: 12px; border: 1px solid; flex: 1; }
                    .wicket-field-slug-empty { color: #999; background: #f6f7f7; border-color: #dcdcde; font-style: italic; }
                    .wicket-field-slug-set { color: #007a1e; background: #e6f5e8; border-color: #68bb6c; }
                    .wicket-field-slug-edit-btn { cursor: pointer; background: none; border: none; color: #2271b1; padding: 2px; line-height: 1; vertical-align: middle; }
                    .wicket-field-slug-edit-btn:hover { color: #135e96; }
                    .wicket-field-slug-edit-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }
                    .wicket-field-slug-copy-btn { cursor: pointer; background: none; border: none; color: #2271b1; padding: 2px; line-height: 1; vertical-align: middle; }
                    .wicket-field-slug-copy-btn:hover { color: #135e96; }
                    .wicket-field-slug-copy-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }
                    .wicket-field-slug-edit { display: none; align-items: center; gap: 4px; margin-top: 4px; }
                    .wicket-field-slug-edit.active { display: flex; }
                    .wicket-field-slug-input { flex: 1; font-family: monospace; font-size: 12px; padding: 3px 6px; min-width: 120px; }
                    .wicket-field-slug-status { font-size: 12px; display: inline-flex; align-items: center; gap: 2px; }
                    .wicket-field-slug-status.valid { color: #00a32a; }
                    .wicket-field-slug-status.invalid { color: #d63638; }
                    .wicket-field-slug-status.checking { color: #dba617; }
                    .wicket-field-slug-actions .button { padding: 0 4px; line-height: 22px; min-height: 22px; }
                    .wicket-field-slug-actions .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 22px; }
                </style>
                <label for="wicket_field_slug_input"><?php esc_html_e('Field Slug', 'wicket-gf'); ?></label>
                <div class="wicket-field-slug-view">
                    <span class="wicket-field-slug-display wicket-field-slug-empty" id="wicket_field_slug_display">-</span>
                    <button type="button" class="wicket-field-slug-copy-btn" title="<?php esc_attr_e('Copy slug', 'wicket-gf'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button type="button" class="wicket-field-slug-edit-btn" title="<?php esc_attr_e('Edit field slug', 'wicket-gf'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
                <div class="wicket-field-slug-edit" id="wicket_field_slug_edit">
                    <input type="text" id="wicket_field_slug_input" class="wicket-field-slug-input"
                           placeholder="<?php esc_attr_e('e.g. first-name', 'wicket-gf'); ?>" />
                    <div class="wicket-field-slug-actions">
                        <button type="button" class="button button-small wicket-field-slug-confirm" title="<?php esc_attr_e('Confirm', 'wicket-gf'); ?>">
                            <span class="dashicons dashicons-yes"></span>
                        </button>
                        <button type="button" class="button button-small wicket-field-slug-cancel" title="<?php esc_attr_e('Cancel', 'wicket-gf'); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <span class="wicket-field-slug-status" id="wicket_field_slug_status"></span>
                </div>
            </li>

            <li class="wicket_global_custom_settings wicket_global_custom_settings_hide_label field_setting">
                <style>
                    .wicket_global_custom_settings_hide_label {
                        display: block !important;
                    }
                </style>
                <input type="checkbox" id="hide_label" onclick="SetFieldProperty('hide_label', this.checked);"
                    onkeypress="SetFieldProperty('hide_label', this.checked);">
                <label for="hide_label" class="inline">Hide Label</label>
            </li>

            <li class="wicket_global_custom_settings wicket_global_custom_settings_enable_mdp_mapping field_setting">
                <style>
                    .wicket_global_custom_settings_enable_mdp_mapping {
                        display: block !important;
                    }

                    .wicket-mdp-config-row {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        margin-bottom: 6px;
                    }
                    .wicket-mdp-config-row label {
                        min-width: 95px;
                        font-weight: 600;
                        font-size: 12px;
                    }
                    .wicket-mdp-config-row select {
                        flex: 1;
                        max-width: 300px;
                    }

                    .wicket-mdp-config-section {
                        border-left: 2px solid #dcdcde;
                        padding-left: 10px;
                        margin-top: 8px;
                    }
                    .wicket-mdp-type-warning {
                        color: #b32d2e;
                        font-size: 12px;
                        margin: 6px 0 0;
                    }
                </style>
                <input type="checkbox" id="wicket_enable_mdp_mapping"
                    onclick="SetFieldProperty('wicket_enable_mdp_mapping', this.checked);"
                    onkeypress="SetFieldProperty('wicket_enable_mdp_mapping', this.checked);">
                <label for="wicket_enable_mdp_mapping" class="inline"><?php esc_html_e('Enable MDP Mapping', 'wicket-gf'); ?></label>

                <div class="wicket-mdp-config-section" id="wicket_mdp_config_section" style="display:none;">
                    <div class="wicket-mdp-config-row">
                        <label for="wicket_mdp_entity_type"><?php esc_html_e('Entity Type', 'wicket-gf'); ?></label>
                        <select id="wicket_mdp_entity_type">
                            <option value=""><?php esc_html_e('- Select -', 'wicket-gf'); ?></option>
                            <option value="person"><?php esc_html_e('Person', 'wicket-gf'); ?></option>
                            <option value="organization"><?php esc_html_e('Organization', 'wicket-gf'); ?></option>
                        </select>
                    </div>
                    <div class="wicket-mdp-config-row">
                        <label for="wicket_mdp_target_object"><?php esc_html_e('Target Object', 'wicket-gf'); ?></label>
                        <select id="wicket_mdp_target_object" onchange="SetFieldProperty('wicket_mdp_target_object', this.value);">
                            <option value=""><?php esc_html_e('- Select -', 'wicket-gf'); ?></option>
                        </select>
                    </div>
                    <div class="wicket-mdp-config-row" id="wicket_mdp_target_field_row" style="display:none;">
                        <label for="wicket_mdp_target_field"><?php esc_html_e('Target Field', 'wicket-gf'); ?></label>
                        <select id="wicket_mdp_target_field" onchange="SetFieldProperty('wicket_mdp_target_field', this.value);">
                            <option value=""><?php esc_html_e('- Select -', 'wicket-gf'); ?></option>
                        </select>
                    </div>
                    <div id="wicket_mdp_type_warning_row" style="display:none;">
                        <span class="wicket-mdp-type-warning"></span>
                    </div>
                </div>
            </li>

        <?php echo ob_get_clean();
        }
    }

    public function gf_editor_script()
    {
        // Action to inject supporting script to the form editor page to populate our custom settings
        ?>
        <script type='text/javascript'>
            var wicketMdpTargetObjects = <?php echo json_encode($this->get_mdp_target_object_choices()); ?>;

            // Field definitions sourced from PHP single source of truth (get_mdp_target_fields).
            // Task 2.1 will replace additional_info/preferences with dynamic discovery.
            var wicketMdpTargetFields = <?php echo json_encode($this->get_all_mdp_target_fields()); ?>;

            // Type compatibility matrix: GF field type → array of incompatible target field values.
            var wicketMdpTypeCompat = <?php echo json_encode($this->get_mdp_compat()->buildJsMatrix($this->get_mdp_discovery())); ?>;

            // Field Slug Configuration
            <?php
            $editor_form_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $editor_form_slug = '';
        if ($editor_form_id > 0) {
            $editor_mappings_json = get_option('wicket_gf_slug_mapping', '');
            $editor_mappings = json_decode($editor_mappings_json, true);
            if (is_array($editor_mappings)) {
                $editor_form_id_str = (string) $editor_form_id;
                foreach ($editor_mappings as $mapped_slug => $mapped_id) {
                    if ((string) $mapped_id === $editor_form_id_str) {
                        $editor_form_slug = $mapped_slug;
                        break;
                    }
                }
            }
        }
        ?>
            var wicketFieldSlugConfig = {
                ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo wp_create_nonce('wicket_gf_field_slug'); ?>',
                formId: <?php echo $editor_form_id; ?>,
                formSlug: <?php echo json_encode($editor_form_slug); ?>
            };

            var wicketSlugUI = {
                load: function(slug) {
                    slug = slug || '';
                    var $display = jQuery('#wicket_field_slug_display');
                    if (slug) {
                        $display.text(slug).removeClass('wicket-field-slug-empty').addClass('wicket-field-slug-set');
                    } else {
                        $display.text('\u2014').removeClass('wicket-field-slug-set').addClass('wicket-field-slug-empty');
                    }
                    this.hideEdit();
                },
                showEdit: function() {
                    var field = GetSelectedField();
                    var currentSlug = rgar(field, 'wicket_field_slug') || '';
                    jQuery('.wicket-field-slug-view').hide();
                    jQuery('#wicket_field_slug_edit').addClass('active');
                    jQuery('#wicket_field_slug_input').val(currentSlug).focus();
                    jQuery('#wicket_field_slug_status').text('');
                },
                hideEdit: function() {
                    jQuery('.wicket-field-slug-view').show();
                    jQuery('#wicket_field_slug_edit').removeClass('active');
                    jQuery('#wicket_field_slug_status').text('');
                },
                confirm: function() {
                    var self = this;
                    var slug = jQuery('#wicket_field_slug_input').val().trim();
                    var field = GetSelectedField();
                    var $status = jQuery('#wicket_field_slug_status');

                    if (!slug) {
                        SetFieldProperty('wicket_field_slug', '');
                        this.load('');
                        return;
                    }

                    // Normalize: lowercase, hyphens only
                    slug = slug.toLowerCase().replace(/[^a-z0-9\-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                    jQuery('#wicket_field_slug_input').val(slug);

                    if (!slug) {
                        SetFieldProperty('wicket_field_slug', '');
                        this.load('');
                        return;
                    }

                    // JS-side uniqueness check (against in-memory form)
                    var form = window.form || {};
                    if (form.fields) {
                        for (var i = 0; i < form.fields.length; i++) {
                            var f = form.fields[i];
                            if (f.id === field.id) continue;
                            if (f.wicket_field_slug === slug) {
                                $status.removeClass('valid checking').addClass('invalid').text('<?php esc_html_e('Already in use', 'wicket-gf'); ?>');
                                return;
                            }
                        }
                    }

                    // AJAX validation against DB
                    $status.removeClass('valid invalid').addClass('checking').text('<?php esc_html_e('Checking...', 'wicket-gf'); ?>');

                    jQuery.post(wicketFieldSlugConfig.ajaxUrl, {
                        action: 'wicket_gf_validate_field_slug',
                        nonce: wicketFieldSlugConfig.nonce,
                        form_id: wicketFieldSlugConfig.formId,
                        field_id: field.id,
                        slug: slug
                    }, function(response) {
                        if (response.success && response.data.valid) {
                            SetFieldProperty('wicket_field_slug', slug);
                            self.load(slug);
                        } else {
                            $status.removeClass('valid checking').addClass('invalid').text(response.data.message || '<?php esc_html_e('Already in use', 'wicket-gf'); ?>');
                        }
                    }).fail(function() {
                        // AJAX failed - trust JS-side validation, set with caveat
                        SetFieldProperty('wicket_field_slug', slug);
                        self.load(slug);
                        $status.removeClass('invalid checking').addClass('valid').text('<?php esc_html_e('Saved (server check unavailable)', 'wicket-gf'); ?>');
                    });
                },
                init: function() {
                    var slugUI = this;
                    // Direct bindings (not delegated) - GF stops click propagation on .field_setting <li>s
                    jQuery('.wicket-field-slug-copy-btn').on('click', function(e) {
                        e.stopPropagation();
                        var slug = jQuery('#wicket_field_slug_display').text();
                        if (slug && slug !== '\u2014') {
                            navigator.clipboard.writeText(slug).then(function() {
                                var $icon = jQuery('.wicket-field-slug-copy-btn .dashicons');
                                $icon.removeClass('dashicons-admin-page').addClass('dashicons-yes');
                                setTimeout(function() { $icon.removeClass('dashicons-yes').addClass('dashicons-admin-page'); }, 1500);
                            });
                        }
                    });
                    jQuery('.wicket-field-slug-edit-btn').on('click', function(e) {
                        e.stopPropagation();
                        slugUI.showEdit();
                    });
                    jQuery('.wicket-field-slug-confirm').on('click', function(e) {
                        e.stopPropagation();
                        slugUI.confirm();
                    });
                    jQuery('.wicket-field-slug-cancel').on('click', function(e) {
                        e.stopPropagation();
                        slugUI.hideEdit();
                    });
                    jQuery('#wicket_field_slug_input').on('keydown', function(e) {
                        if (e.which === 13) { e.preventDefault(); slugUI.confirm(); }
                        if (e.which === 27) { slugUI.hideEdit(); }
                    });
                    jQuery('#wicket_field_slug_input').on('input', function() {
                        var val = jQuery(this).val();
                        jQuery(this).val(val.toLowerCase().replace(/[^a-z0-9\-]/g, '-').replace(/-+/g, '-'));
                    });
                }
            };

            wicketSlugUI.init();

            // Move slug setting to sit right after Field Label, before Description
            var $slugLi = jQuery('.wicket_global_custom_settings_field_slug');
            var $labelLi = jQuery('.label_setting');
            if ($slugLi.length && $labelLi.length) {
                $slugLi.detach().insertAfter($labelLi);
            }

            /**
             * Inject field slug pill into the GF editor sidebar, next to the ID pill.
             * GF's SelectField calls .text() on #sidebar_field_label which wipes children,
             * so we insert AFTER #sidebar_field_label (between it and #sidebar_field_text)
             * to survive GF's DOM resets.
             */
            (function() {
                var $label = jQuery('#sidebar_field_label');
                if (!$label.length) return;

                // Inject styles once
                jQuery('<style>' +
                    '.wicket-sidebar-slug { display: block; clear: both; margin-bottom: 4px; }' +
                    '.wicket-sidebar-slug__pill { float: right; display: inline-block; background-color: #e6f5e8; border: 1px solid #68bb6c; border-radius: 40px; font-size: .6875rem; font-weight: 600; padding: .1125rem .4625rem; font-family: monospace; cursor: default; user-select: all; }' +
                    '.wicket-sidebar-slug__pill--empty { background: #f6f7f7; border-color: #dcdcde; color: #999; }' +
                    '.wicket-sidebar-slug__pill--set { background-color: #ecedf8; border-color: #d5d7e9; color: #2e3192; margin-top: 5px; }' +
                    '.wicket-sidebar-slug__prefix { font-weight: 400; opacity: 0.7; margin-right: 2px; }' +
                    '</style>').appendTo('head');

                // Create the container + pill, insert after #sidebar_field_label
                var $container = jQuery('<div class="wicket-sidebar-slug"><span class="wicket-sidebar-slug__pill wicket-sidebar-slug__pill--empty"><span class="wicket-sidebar-slug__prefix">slug:</span><span class="wicket-sidebar-slug__value">\u2014</span></span></div>');
                $label.after($container);

                // Update function
                function updateSidebarSlug(slug) {
                    var $pill = $container.find('.wicket-sidebar-slug__pill');
                    var $value = $pill.find('.wicket-sidebar-slug__value');
                    if (slug) {
                        $value.text(slug);
                        $pill.removeClass('wicket-sidebar-slug__pill--empty').addClass('wicket-sidebar-slug__pill--set');
                        $pill.attr('title', '<?php esc_attr_e('Field slug', 'wicket-gf'); ?>: ' + slug);
                    } else {
                        $value.text('\u2014');
                        $pill.removeClass('wicket-sidebar-slug__pill--set').addClass('wicket-sidebar-slug__pill--empty');
                        $pill.attr('title', '<?php esc_attr_e('No field slug set', 'wicket-gf'); ?>');
                    }
                }

                // Hook into GF field selection action
                if (window.gform && gform.addAction) {
                    gform.addAction('gform_editor_js_set_field_properties', function(field) {
                        var slug = rgar(field, 'wicket_field_slug') || '';
                        updateSidebarSlug(slug);
                    });
                }

                // Also update when slug changes via the wicketSlugUI widget
                var origLoad = wicketSlugUI.load.bind(wicketSlugUI);
                wicketSlugUI.load = function(slug) {
                    origLoad(slug);
                    updateSidebarSlug(slug);
                };
            })();

            /**
             * Inject form slug badge into the editor toolbar buttons area.
             * Shows slug badge for quick copy-paste + link to Settings.
             */
            (function() {
                var slug = wicketFieldSlugConfig.formSlug || '';
                var $container = jQuery('#gf_toolbar_buttons_container');
                if (!$container.length) return;

                // Inject styles once
                jQuery('<style>' +
                    '.wicket-toolbar-slug { display: inline-flex; align-items: center; gap: 4px; margin-right: 10px; padding: 4px 10px; border: 1px solid #dcdcde; border-radius: 4px; font-family: monospace; font-size: 11px; line-height: 1.4; vertical-align: middle; }' +
                    '.wicket-toolbar-slug--empty { background: #fff8e5; border-color: #dba617; cursor: pointer; }' +
                    '.wicket-toolbar-slug--set { background: #f0f0f1; }' +
                    '.wicket-toolbar-slug__label { color: #999; font-weight: 400; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; margin-right: 2px; }' +
                    '.wicket-toolbar-slug__value { color: #1d2327; user-select: all; cursor: text; }' +
                    '.wicket-toolbar-slug__empty { color: #906800; font-style: italic; }' +
                    '.wicket-toolbar-slug__link { color: #646970; text-decoration: none; line-height: 1; margin-left: 2px; }' +
                    '.wicket-toolbar-slug__link:hover { color: #2271b1; }' +
                    '.wicket-toolbar-slug__link .dashicons { font-size: 14px; width: 14px; height: 14px; }' +
                    '.wicket-toolbar-slug__copy { color: #646970; cursor: pointer; text-decoration: none; line-height: 1; margin-left: 2px; }' +
                    '.wicket-toolbar-slug__copy:hover { color: #2271b1; }' +
                    '.wicket-toolbar-slug__copy .dashicons { font-size: 14px; width: 14px; height: 14px; }' +
                    '</style>').appendTo('head');

                var settingsUrl = '?page=gf_edit_forms&view=settings&subview=wicket_settings&id=' + wicketFieldSlugConfig.formId;
                var $badge;
                if (slug) {
                    $badge = jQuery(
                        '<span class="wicket-toolbar-slug wicket-toolbar-slug--set" title="Form Slug (click value to select)">' +
                        '<span class="wicket-toolbar-slug__label">slug:</span>' +
                        '<span class="wicket-toolbar-slug__value">' + slug + '</span>' +
                        '<a href="#" class="wicket-toolbar-slug__copy" title="Copy slug to clipboard">' +
                        '<span class="dashicons dashicons-admin-page"></span></a>' +
                        '<a href="' + settingsUrl + '" class="wicket-toolbar-slug__link" title="Edit slug in Settings">' +
                        '<span class="dashicons dashicons-edit"></span></a>' +
                        '</span>'
                    );
                    $badge.on('click', '.wicket-toolbar-slug__copy', function(e) {
                        e.preventDefault();
                        var $icon = jQuery(this).find('.dashicons');
                        navigator.clipboard.writeText(slug).then(function() {
                            $icon.removeClass('dashicons-admin-page').addClass('dashicons-yes');
                            setTimeout(function() { $icon.removeClass('dashicons-yes').addClass('dashicons-admin-page'); }, 1500);
                        });
                    });
                } else {
                    $badge = jQuery(
                        '<a href="' + settingsUrl + '" class="wicket-toolbar-slug wicket-toolbar-slug--empty" title="Click to set a form slug">' +
                        '<span class="wicket-toolbar-slug__empty">no slug set</span>' +
                        '<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;color:#906800;"></span>' +
                        '</a>'
                    );
                }
                $container.prepend($badge);
            })();

            // Toggle a conditional MDP setting row. Uses setProperty('important') so it beats
            // the 'display: block !important' class rule that keeps these rows visible to GF.
            function wicketMdpToggle($el, show) {
                if (!$el.length) {
                    return;
                }

                if (show) {
                    $el[0].style.removeProperty('display');
                } else {
                    $el[0].style.setProperty('display', 'none', 'important');
                }
            }

            function wicketGetCurrentFormObject(currentForm) {
                return currentForm || window.form || {};
            }

            function wicketGetMdpFormConfig(currentForm) {
                currentForm = wicketGetCurrentFormObject(currentForm);

                return {
                    entityType: rgar(currentForm, 'wicket_mdp_entity_type') || ''
                };
            }

            function wicketPopulateMdpTargetObjects(entityType, selectedValue) {
                var $select = jQuery('#wicket_mdp_target_object');
                var choices = wicketMdpTargetObjects[entityType] || [];
                var hasSelectedValue = false;

                $select.empty().append('<option value=""><?php esc_html_e('- Select -', 'wicket-gf'); ?></option>');
                jQuery.each(choices, function(i, choice) {
                    $select.append('<option value="' + choice.value + '">' + choice.label + '</option>');
                });

                if (selectedValue && choices.some(function(choice) { return choice.value === selectedValue; })) {
                    $select.val(selectedValue);
                    hasSelectedValue = true;
                }

                return {
                    hasChoices: choices.length > 0,
                    hasSelectedValue: hasSelectedValue
                };
            }

            function wicketPopulateMdpTargetFields(targetObject, selectedValue) {
                var $select = jQuery('#wicket_mdp_target_field');
                var $row    = jQuery('#wicket_mdp_target_field_row');
                var fields  = wicketMdpTargetFields[targetObject] || [];
                var hasSelectedValue = false;

                $select.empty().append('<option value=""><?php esc_html_e('- Select -', 'wicket-gf'); ?></option>');
                jQuery.each(fields, function(i, f) {
                    $select.append('<option value="' + f.value + '">' + f.label + '</option>');
                });
                if (selectedValue && fields.some(function(field) { return field.value === selectedValue; })) {
                    $select.val(selectedValue);
                    hasSelectedValue = true;
                }
                wicketMdpToggle($row, fields.length > 0);

                return {
                    hasChoices: fields.length > 0,
                    hasSelectedValue: hasSelectedValue
                };
            }

            /**
             * Update form-level entity type on the form object.
             */
            function wicketSetFormEntityType(value) {
                var form = wicketGetCurrentFormObject();
                form['wicket_mdp_entity_type'] = value;
            }

            function wicketRefreshMdpFieldSettings(field, currentForm) {
                var $configSection = jQuery('#wicket_mdp_config_section');
                var $targetFieldRow  = jQuery('#wicket_mdp_target_field_row');
                var $warningRow      = jQuery('#wicket_mdp_type_warning_row');
                var mdpEnabled       = Boolean(rgar(field, 'wicket_enable_mdp_mapping'));
                var targetObject     = rgar(field, 'wicket_mdp_target_object') || '';
                var targetField      = rgar(field, 'wicket_mdp_target_field') || '';
                var formConfig       = wicketGetMdpFormConfig(currentForm);

                // Show/hide entire config section
                wicketMdpToggle($configSection, mdpEnabled);

                if (mdpEnabled) {
                    var $etSelect = jQuery('#wicket_mdp_entity_type');
                    if ($etSelect.val() !== formConfig.entityType) {
                        $etSelect.val(formConfig.entityType || '');
                    }
                }

                var targetObjectState = wicketPopulateMdpTargetObjects(formConfig.entityType, targetObject);
                // Target Object row is always visible inside the section when enabled

                if (!mdpEnabled || !targetObjectState.hasSelectedValue) {
                    if (targetObject && !targetObjectState.hasSelectedValue) {
                        jQuery('#wicket_mdp_target_object').val('');
                        SetFieldProperty('wicket_mdp_target_object', '');
                    }

                    if (targetField) {
                        jQuery('#wicket_mdp_target_field').val('');
                        SetFieldProperty('wicket_mdp_target_field', '');
                    }

                    wicketMdpToggle($targetFieldRow, false);
                    wicketMdpToggle($warningRow, false);
                    return;
                }

                var targetFieldState = wicketPopulateMdpTargetFields(targetObject, targetField);
                if (!targetFieldState.hasSelectedValue && targetField) {
                    jQuery('#wicket_mdp_target_field').val('');
                    SetFieldProperty('wicket_mdp_target_field', '');
                    targetField = '';
                }

                // Task 5.1: Type compatibility warning
                wicketShowTypeWarning(field, targetField);
            }

            /**
             * Show a type compatibility warning when a multi-value GF field
             * is mapped to a boolean target (e.g. checkbox → communications.email).
             */
            function wicketShowTypeWarning(field, targetField) {
                var $warningRow = jQuery('#wicket_mdp_type_warning_row');
                var $warningSpan = $warningRow.find('.wicket-mdp-type-warning');

                if (!targetField || !wicketMdpTypeCompat) {
                    wicketMdpToggle($warningRow, false);
                    return;
                }

                var fieldType = rgar(field, 'type') || '';
                var incompatible = wicketMdpTypeCompat[fieldType] || [];
                var hasWarning = incompatible.indexOf(targetField) !== -1;

                if (hasWarning) {
                    $warningSpan.text('<?php esc_html_e('Warning: Multi-value field mapped to a boolean target. Only the first selected value will be sent.', 'wicket-gf'); ?>');
                    wicketMdpToggle($warningRow, true);
                } else {
                    $warningSpan.text('');
                    wicketMdpToggle($warningRow, false);
                }
            }

            jQuery(document).on('gform_load_field_settings', function(event, field, form){
                wicketSlugUI.load(rgar(field, 'wicket_field_slug') || '');
                jQuery('#hide_label').prop('checked', Boolean(rgar(field, 'hide_label')));
                jQuery('#wicket_enable_mdp_mapping').prop('checked', Boolean(rgar(field, 'wicket_enable_mdp_mapping')));
                wicketRefreshMdpFieldSettings(field, form);
            });

            jQuery(document).on('change', '#wicket_enable_mdp_mapping', function(){
                var field = GetSelectedField();

                if (!this.checked) {
                    jQuery('#wicket_mdp_target_object').val('');
                    SetFieldProperty('wicket_mdp_target_object', '');
                    jQuery('#wicket_mdp_target_field').val('');
                    SetFieldProperty('wicket_mdp_target_field', '');
                }

                wicketRefreshMdpFieldSettings(field, wicketGetCurrentFormObject());
            });

            /**
             * Entity Type change — updates form-level meta.
             */
            jQuery(document).on('change', '#wicket_mdp_entity_type', function(){
                var val = jQuery(this).val();
                wicketSetFormEntityType(val);

                var field = GetSelectedField();
                wicketRefreshMdpFieldSettings(field, wicketGetCurrentFormObject());
            });


            jQuery(document).on('change', '#wicket_mdp_target_object', function(){
                var field = GetSelectedField();

                jQuery('#wicket_mdp_target_field').val('');
                SetFieldProperty('wicket_mdp_target_field', '');
                wicketRefreshMdpFieldSettings(field, wicketGetCurrentFormObject());
            });

            jQuery(document).on('change', '#wicket_mdp_target_field', function(){
                var field = GetSelectedField();
                wicketRefreshMdpFieldSettings(field, wicketGetCurrentFormObject());
            });

            /**
             * Task 1.6: Validate MDP field mappings before saving the form.
             * Returns an error string to block save, or empty string to allow.
             */
            gform.addFilter('gform_validation_error_form_editor', function(error, form) {
                var mdpErrors = [];
                var formConfig = wicketGetMdpFormConfig(form);

                if (!form || !form.fields) {
                    return error;
                }

                for (var i = 0; i < form.fields.length; i++) {
                    var field = form.fields[i];
                    var mdpEnabled = Boolean(rgar(field, 'wicket_enable_mdp_mapping'));

                    if (!mdpEnabled) {
                        continue;
                    }

                    var fieldLabel = rgar(field, 'label') || '<?php esc_html_e('Unknown field', 'wicket-gf'); ?>';
                    var fieldAdminLabel = rgar(field, 'adminLabel') || '';
                    var displayName = fieldAdminLabel || fieldLabel;
                    var targetObject = rgar(field, 'wicket_mdp_target_object') || '';
                    var targetField  = rgar(field, 'wicket_mdp_target_field') || '';

                    // Rule 1: MDP enabled but form has no entity type
                    if (!formConfig.entityType) {
                        mdpErrors.push(displayName + ': <?php esc_html_e('Select an Entity Type in the MDP Mapping section to enable mapping.', 'wicket-gf'); ?>');
                        continue;
                    }

                    // Rule 2: MDP enabled but no Target Object selected
                    if (!targetObject) {
                        mdpErrors.push(displayName + ': <?php esc_html_e('Select a Target Object or disable MDP mapping.', 'wicket-gf'); ?>');
                        continue;
                    }

                    // Rule 3: Target Object has no available fields (unsupported)
                    var availableFields = wicketMdpTargetFields[targetObject] || [];
                    if (availableFields.length === 0) {
                        mdpErrors.push(displayName + ': <?php esc_html_e('The selected Target Object is not yet supported. Disable MDP mapping or choose a different Target Object.', 'wicket-gf'); ?>');
                        continue;
                    }

                    // Rule 4: Target Object selected but no Target Field
                    if (targetObject && !targetField) {
                        mdpErrors.push(displayName + ': <?php esc_html_e('Select a Target Field or disable MDP mapping.', 'wicket-gf'); ?>');
                        continue;
                    }

                    // Rule 5: Target Field value is not in available fields for selected object
                    if (targetField) {
                        var fieldFound = false;
                        for (var j = 0; j < availableFields.length; j++) {
                            if (availableFields[j].value === targetField) {
                                fieldFound = true;
                                break;
                            }
                        }
                        if (!fieldFound) {
                            mdpErrors.push(displayName + ': <?php esc_html_e('The selected Target Field is not valid for the chosen Target Object. Please re-select.', 'wicket-gf'); ?>');
                        }
                    }
                }

                if (mdpErrors.length > 0) {
                    var mdpMessage = '<?php esc_html_e('MDP Mapping Errors:', 'wicket-gf'); ?>\n\n' + mdpErrors.join('\n');
                    error = error ? error + '\n\n' + mdpMessage : mdpMessage;
                }

                return error;
            });

            /**
             * Task 1.6: Auto-clear invalid field mappings before saving.
             * If a field has an unsupported config, strip it so the form data is clean.
             */
            gform.addFilter('gform_pre_form_editor_save', function(form) {
                if (!form || !form.fields) {
                    return form;
                }

                var formConfig = wicketGetMdpFormConfig(form);

                for (var i = 0; i < form.fields.length; i++) {
                    var field = form.fields[i];
                    var mdpEnabled = Boolean(rgar(field, 'wicket_enable_mdp_mapping'));

                    if (!mdpEnabled) {
                        // Strip MDP properties from disabled fields
                        field.wicket_mdp_target_object = '';
                        field.wicket_mdp_target_field  = '';
                        continue;
                    }

                    // No UUID source stripping — UUID handled at sync time
                    var targetObject = rgar(field, 'wicket_mdp_target_object') || '';
                    var availableFields = wicketMdpTargetFields[targetObject] || [];

                    // If target object is unsupported (no fields), strip
                    if (targetObject && availableFields.length === 0) {
                        field.wicket_mdp_target_object = '';
                        field.wicket_mdp_target_field  = '';
                        continue;
                    }

                    // If target field is invalid for the object, strip it
                    var targetField = rgar(field, 'wicket_mdp_target_field') || '';
                    if (targetField && availableFields.length > 0) {
                        var fieldFound = false;
                        for (var j = 0; j < availableFields.length; j++) {
                            if (availableFields[j].value === targetField) {
                                fieldFound = true;
                                break;
                            }
                        }
                        if (!fieldFound) {
                            field.wicket_mdp_target_field = '';
                        }
                    }
                }

                // Slug dedup: if multiple fields share a slug, strip from all but the first
                var seenSlugs = {};
                for (var k = 0; k < form.fields.length; k++) {
                    var s = form.fields[k].wicket_field_slug;
                    if (!s) continue;
                    if (seenSlugs[s]) {
                        form.fields[k].wicket_field_slug = '';
                    } else {
                        seenSlugs[s] = true;
                    }
                }

                return form;
            });
        </script>
        <?php
    }

    public function entries_list_first_column_content($form_id, $field_id, $value, $entry, $query_string)
    {
        unset($form_id, $field_id, $value, $entry, $query_string);
    }

    public function gf_change_user_name($value)
    {
        return $value;
    }

    /**
     * Dynamic population callback for Gravity Forms parameter "user_mdp_tags".
     *
     * Default source: combined.
     * Override via filter:
     * add_filter('wicket_gf_user_mdp_tags_default_source', fn() => 'combined');
     *
     * @param string $value
     * @return string
     */
    public function populate_user_mdp_tags_dynamic_parameter($value = ''): string
    {
        if (!class_exists(UserMdpTags::class)) {
            return '';
        }

        $default_source = apply_filters('wicket_gf_user_mdp_tags_default_source', 'combined');
        $allowed_sources = ['segment_tags', 'tags', 'combined'];

        if (!in_array($default_source, $allowed_sources, true)) {
            $default_source = 'combined';
        }

        return UserMdpTags::get_user_tags_by_source((string) $default_source);
    }

    public static function update_kses_tags($allowedposttags)
    {
        $allowed_atts = [
            'align'      => [],
            'class'      => [],
            'type'       => [],
            'id'         => [],
            'dir'        => [],
            'lang'       => [],
            'style'      => [],
            'xml:lang'   => [],
            'src'        => [],
            'alt'        => [],
            'href'       => [],
            'rel'        => [],
            'rev'        => [],
            'target'     => [],
            'novalidate' => [],
            'type'       => [],
            'value'      => [],
            'name'       => [],
            'tabindex'   => [],
            'action'     => [],
            'method'     => [],
            'for'        => [],
            'width'      => [],
            'height'     => [],
            'data'       => [],
            'title'      => [],
        ];

        // Enable our desired tag types
        $allowedposttags['script'] = $allowed_atts;
        $allowedposttags['style'] = $allowed_atts;
        $allowedposttags['iframe'] = $allowed_atts;

        return $allowedposttags;
    }

    /**
     * Extend Gravity Forms confirmation settings with a self redirect option.
     *
     * @param array $fields
     * @param array $confirmation
     * @param array $form
     * @return array
     */
    public function extend_confirmation_settings_fields(array $fields, array $confirmation, array $form): array
    {
        unset($confirmation, $form);
        $is_woocommerce_active = $this->is_woocommerce_active();

        foreach ($fields as &$section) {
            if (empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $index => &$field) {
                unset($index);
                if (($field['name'] ?? '') === 'type' && !empty($field['choices']) && is_array($field['choices'])) {
                    $field['choices'][] = [
                        'label' => __('Same Page redirect', 'wicket-gf'),
                        'value' => self::CONFIRMATION_TYPE_SELF_REDIRECT,
                    ];

                    if ($is_woocommerce_active) {
                        $field['choices'][] = [
                            'label' => __('Cart redirect', 'wicket-gf'),
                            'value' => self::CONFIRMATION_TYPE_WC_CART_REDIRECT,
                        ];
                        $field['choices'][] = [
                            'label' => __('Checkout Link redirect', 'wicket-gf'),
                            'value' => self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT,
                            'description' => sprintf(
                                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                                'https://developer.woocommerce.com/docs/best-practices/urls-and-routing/checkout-urls/',
                                __('Learn how to use checkout links', 'wicket-gf')
                            ),
                        ];
                    }
                }

                if (($field['name'] ?? '') === 'queryString') {
                    if (!empty($field['dependency']['fields']) && is_array($field['dependency']['fields'])) {
                        foreach ($field['dependency']['fields'] as &$dependency_field) {
                            if (($dependency_field['field'] ?? '') !== 'type') {
                                continue;
                            }

                            $dependency_values = $dependency_field['values'] ?? [];
                            if (!is_array($dependency_values)) {
                                $dependency_values = [];
                            }

                            if (!in_array(self::CONFIRMATION_TYPE_SELF_REDIRECT, $dependency_values, true)) {
                                $dependency_values[] = self::CONFIRMATION_TYPE_SELF_REDIRECT;
                            }

                            if ($is_woocommerce_active && !in_array(self::CONFIRMATION_TYPE_WC_CART_REDIRECT, $dependency_values, true)) {
                                $dependency_values[] = self::CONFIRMATION_TYPE_WC_CART_REDIRECT;
                            }

                            if ($is_woocommerce_active && !in_array(self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT, $dependency_values, true)) {
                                $dependency_values[] = self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT;
                            }

                            $dependency_field['values'] = $dependency_values;
                        }
                        unset($dependency_field);
                    }

                }
            }
            unset($field);

            break;
        }
        unset($section);

        return $fields;
    }

    /**
     * Preserve the custom confirmation type on save.
     *
     * @param array $confirmation
     * @param array $form
     * @param bool $is_new_confirmation
     * @return array
     */
    public function save_self_redirect_confirmation(array $confirmation, array $form, bool $is_new_confirmation): array
    {
        unset($form, $is_new_confirmation);

        $selected_type = sanitize_text_field((string) rgpost('_gform_setting_type'));
        $query_string = sanitize_text_field((string) rgpost('_gform_setting_queryString', rgpost('queryString', '')));

        if ($selected_type === self::CONFIRMATION_TYPE_SELF_REDIRECT) {
            $confirmation['type'] = self::CONFIRMATION_TYPE_SELF_REDIRECT;
            $confirmation['queryString'] = $query_string;

            return $confirmation;
        }

        if ($selected_type === self::CONFIRMATION_TYPE_WC_CART_REDIRECT && $this->is_woocommerce_active()) {
            $confirmation['type'] = self::CONFIRMATION_TYPE_WC_CART_REDIRECT;
            $confirmation['queryString'] = $query_string;

            return $confirmation;
        }

        if ($selected_type === self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT && $this->is_woocommerce_active()) {
            $confirmation['type'] = self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT;
            $confirmation['queryString'] = $query_string;
        }

        return $confirmation;
    }

    /**
     * Handle runtime redirect for the self redirect confirmation type.
     *
     * @param string|array $confirmation
     * @param array $form
     * @param array $entry
     * @param bool $ajax
     * @return string|array
     */
    public function handle_self_redirect_confirmation($confirmation, array $form, array $entry, bool $ajax)
    {
        unset($ajax);

        $active_confirmation = rgar($form, 'confirmation');
        if (!is_array($active_confirmation)) {
            return $confirmation;
        }

        $confirmation_type = rgar($active_confirmation, 'type');
        if ($confirmation_type === self::CONFIRMATION_TYPE_SELF_REDIRECT) {
            $source_url = rgar($entry, 'source_url');
            if (empty($source_url)) {
                $source_url = GFFormsModel::get_current_page_url();
            }

            if (empty($source_url)) {
                return $confirmation;
            }

            $active_confirmation['url'] = $source_url;
            $active_confirmation['queryString'] = rgar(
                $active_confirmation,
                'queryString',
                rgar($active_confirmation, self::LEGACY_CONFIRMATION_FIELD_SELF_QUERY_STRING, '')
            );
        } elseif ($confirmation_type === self::CONFIRMATION_TYPE_WC_CART_REDIRECT) {
            if (!$this->is_woocommerce_active()) {
                return $confirmation;
            }

            $cart_url = wc_get_cart_url();
            if (empty($cart_url)) {
                return $confirmation;
            }

            $active_confirmation['url'] = $cart_url;
            $active_confirmation['queryString'] = rgar($active_confirmation, 'queryString', '');
        } elseif ($confirmation_type === self::CONFIRMATION_TYPE_WC_CHECKOUT_LINK_REDIRECT) {
            if (!$this->is_woocommerce_active()) {
                return $confirmation;
            }

            $checkout_link_url = $this->get_wc_checkout_link_url();
            if (empty($checkout_link_url)) {
                return $confirmation;
            }

            $active_confirmation['url'] = $checkout_link_url;
            $active_confirmation['queryString'] = rgar($active_confirmation, 'queryString', '');
        } else {
            return $confirmation;
        }

        $redirect_url = GFFormDisplay::get_confirmation_url($active_confirmation, $form, $entry);

        return [
            'redirect' => $redirect_url,
        ];
    }

    /**
     * Determine whether WooCommerce is active and cart helpers are available.
     *
     * @return bool
     */
    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_cart_url');
    }

    /**
     * Resolve the WooCommerce checkout-link URL.
     * WooCommerce Blocks registers 'checkout-link' as a rewrite rule (not a page),
     * so home_url() is the correct resolver. We guard on the CheckoutLink class
     * to ensure the feature is actually present before redirecting there.
     *
     * @return string Empty string when the WooCommerce Blocks checkout-link feature is unavailable.
     */
    private function get_wc_checkout_link_url(): string
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Domain\Services\CheckoutLink')) {
            return '';
        }

        return home_url('/checkout-link/');
    }

    public static function shortcode($atts)
    {
        // override default attributes with user attributes
        $a = shortcode_atts([
            'slug'         => '',
            'title'        => true,
            'description'  => true,
            'ajax'         => '',
            'tabindex'     => '',
            'field_values' => '',
            'theme'        => '',
        ], $atts);

        if (empty($a['slug'])) {
            return;
        }

        $form_id = wicket_gf_get_form_id_by_slug($a['slug']);
        $title = $a['title'];
        $description = $a['description'];
        $ajax = $a['ajax'];
        $tabindex = $a['tabindex'];
        $field_values = $a['field_values'];
        $theme = $a['theme'];

        return '<div class="container wicket-gf-shortcode">'
            . do_shortcode(
                "[gravityform id='" . $form_id . "' title='" . $title . "' description='" . $description . "' ajax='" . $ajax . "' tabindex='" . $tabindex . "' field_values='" . $field_values . "' theme='" . $theme . "']"
            )
            . '</div>';
    }

    public static function register_rest_routes()
    {
        register_rest_route('wicket-gf/v1', 'resync-member-fields', [
            'methods'  => 'POST',
            'callback' => ['Wicket_Gf_Main', 'resync_wicket_member_fields'],
            'permission_callback' => function () {
                return true;
            },
        ]);
    }

    /**
     * AJAX handler: validate a field slug for uniqueness within a form.
     *
     * Checks the database version of the form (not the editor's in-memory state)
     * so it catches conflicts from concurrent edits or imports.
     *
     * @return void Sends JSON response.
     */
    public function ajax_validate_field_slug(): void
    {
        check_ajax_referer('wicket_gf_field_slug', 'nonce');

        if (!current_user_can('gravityforms_edit_forms')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wicket-gf')]);
        }

        $form_id = absint($_POST['form_id'] ?? 0);
        $field_id = absint($_POST['field_id'] ?? 0);
        $slug = sanitize_title($_POST['slug'] ?? '');

        if ($form_id <= 0) {
            wp_send_json_error(['message' => __('Invalid form ID.', 'wicket-gf')]);
        }

        if ($slug === '') {
            wp_send_json_success(['valid' => true]);
        }

        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_send_json_error(['message' => __('Form not found.', 'wicket-gf')]);
        }

        foreach ($form['fields'] as $field) {
            if ((int) $field->id === $field_id) {
                continue;
            }
            if (isset($field->wicket_field_slug) && $field->wicket_field_slug === $slug) {
                wp_send_json_error([
                    'message' => __('This slug is already used by another field in this form.', 'wicket-gf'),
                ]);
            }
        }

        wp_send_json_success(['valid' => true, 'slug' => $slug]);
    }

    /**
     * Flush the slug transient cache when a form is saved.
     *
     * @param array $form The form that was saved.
     * @param int   $form_id The form ID.
     */
    public function flush_slug_cache_on_save(array $form, bool $is_new_form): void
    {
        wicket_gf_flush_slug_cache();
    }

    /**
     * Clear the form slug on imported forms if it collides with an existing form.
     *
     * GF import copies display_meta verbatim, including wicket_mdp_form_slug.
     * Without this, two forms would share the same slug and lookup becomes undefined.
     *
     * @param int $form_id The ID of the newly imported form.
     */
    public function clear_imported_slug_collision(int $form_id): void
    {
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            return;
        }

        // Form slugs live in wicket_gf_slug_mapping, not on the form object.
        // Strip wicket_mdp_form_slug from imported form display_meta so it
        // doesn't accidentally act as a per-form slug.
        if (isset($form['wicket_mdp_form_slug']) && $form['wicket_mdp_form_slug'] !== '') {
            $form['wicket_mdp_form_slug'] = '';
            GFAPI::update_form($form, $form_id);
        }
    }

    public function output_wicket_event_debugger_script(): void
    {
        // Output field editor scripts for Gravity Forms form editor
        $this->output_field_editor_scripts();

        // Check if WP_ENV is defined and is 'development' or 'staging'
        if (defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true)) {
            ?>
            <script type="text/javascript" id="wicket-gf-event-debugger">
                document.addEventListener("DOMContentLoaded", function() {
                    function wicketLogWidgetEvent(eventName, e) {
                        //console.log('Full Event Detail:', e.detail);

                        if (e.detail) {
                            if (e.detail.dataFields) {
                                //console.log('Data Fields:', e.detail.dataFields);
                            } else {
                                //console.log('Event Detail:', e.detail);
                            }

                            if (e.detail.resource) {
                                //console.log('Resource:', e.detail.resource);
                            }
                            if (e.detail.validation) {
                                //console.log('Validation:', e.detail.validation);
                            }
                        }
                    }

                    function initializeWidgetListeners() {
                        // Listen for widget loaded event
                        window.addEventListener('wwidget-component-common-loaded', function(e) {
                            wicketLogWidgetEvent('LOADED', e);
                        });
                    }

                    // Check for Wicket and initialize
                    if (typeof window.Wicket !== 'undefined') {
                        window.Wicket.ready(initializeWidgetListeners);
                    }
                });
            </script>
<?php
        }
    }

    public static function resync_wicket_member_fields()
    {
        // Implementation for resyncing member fields
        update_option('wicket_gf_member_fields', []);
        wp_send_json_success();
    }
}

add_action(
    'plugins_loaded',
    [Wicket_Gf_Main::get_instance(), 'plugin_setup'],
    11
);

/*
 * Test cleanup endpoint for browser tests.
 * Only available in development/staging environments.
 *
 * Visit: /wicket-test-cleanup/ (while logged in)
 * This removes all org connections for the current user.
 */
add_action('init', function () {
    // Only enable in development/staging
    if (!defined('WP_ENV') || !in_array(WP_ENV, ['development', 'staging'], true)) {
        return;
    }

    add_rewrite_rule('^wicket-test-cleanup/?$', 'index.php?wicket_test_cleanup=1', 'top');
    add_filter('query_vars', function ($vars) {
        $vars[] = 'wicket_test_cleanup';

        return $vars;
    });
});

add_action('template_redirect', function () {
    // Only enable in development/staging
    if (!defined('WP_ENV') || !in_array(WP_ENV, ['development', 'staging'], true)) {
        return;
    }

    if (!get_query_var('wicket_test_cleanup')) {
        return;
    }

    // Require login
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Authentication required'], 401);
        exit;
    }

    // Remove all org connections for the current user
    $removed = 0;

    if (function_exists('wicket_get_person_connections') && function_exists('wicket_remove_connection')) {
        $connections = wicket_get_person_connections();

        if (is_array($connections) && isset($connections['data'])) {
            foreach ($connections['data'] as $connection) {
                $connectionType = $connection['attributes']['connection_type'] ?? '';

                // Only remove person_to_organization connections
                if ($connectionType === 'person_to_organization') {
                    $connectionId = $connection['id'] ?? '';
                    if ($connectionId !== '' && wicket_remove_connection($connectionId)) {
                        $removed++;
                    }
                }
            }
        }
    }

    wp_send_json_success([
        'message' => "Removed {$removed} org connection(s)",
        'removed' => $removed,
    ]);
    exit;
});
