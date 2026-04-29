<?php

/**
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Gravity Forms
 * Plugin URI:        https://wicket.io
 * Description:       Adds Wicket functionality to Gravity Forms.
 * Version:           2.4.0
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
use WicketGF\NonceHandler;
use WicketGF\ObjectTypeWicket;
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
     * Class variables.
     */
    private static $wicket_current_person;
    private static $wicket_client;

    /**
     * Constructor. Intentionally left empty and public.
     */
    public function __construct() {}

    /**
     * Access this plugin’s working instance.
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

        // Add Options Page for plugin
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
        if ($position == 25) {
            ob_start(); ?>

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
                    /* Override GF's field_setting hiding for our global Wicket MDP settings */
                    .wicket_global_custom_settings_enable_mdp_mapping,
                    .wicket_global_custom_settings_mdp_target_object,
                    .wicket_global_custom_settings_mdp_target_field {
                        display: block !important;
                    }
                </style>
                <input type="checkbox" id="wicket_enable_mdp_mapping"
                    onclick="SetFieldProperty('wicket_enable_mdp_mapping', this.checked);"
                    onkeypress="SetFieldProperty('wicket_enable_mdp_mapping', this.checked);">
                <label for="wicket_enable_mdp_mapping" class="inline"><?php esc_html_e('Enable MDP Mapping', 'wicket-gf'); ?></label>
            </li>

            <li class="wicket_global_custom_settings wicket_global_custom_settings_mdp_target_object field_setting" style="display: none !important;">
                <label for="wicket_mdp_target_object"><?php esc_html_e('Target Object', 'wicket-gf'); ?></label>
                <select id="wicket_mdp_target_object" onchange="SetFieldProperty('wicket_mdp_target_object', this.value);">
                    <option value=""><?php esc_html_e('— Select —', 'wicket-gf'); ?></option>
                    <option value="person_profile"><?php esc_html_e('Person Profile', 'wicket-gf'); ?></option>
                    <option value="additional_info"><?php esc_html_e('Additional Info', 'wicket-gf'); ?></option>
                    <option value="preferences"><?php esc_html_e('Preferences', 'wicket-gf'); ?></option>
                    <option value="org_profile"><?php esc_html_e('Org Profile', 'wicket-gf'); ?></option>
                </select>
            </li>

            <li class="wicket_global_custom_settings wicket_global_custom_settings_mdp_target_field field_setting" style="display: none !important;">
                <label for="wicket_mdp_target_field"><?php esc_html_e('Target Field', 'wicket-gf'); ?></label>
                <select id="wicket_mdp_target_field" onchange="SetFieldProperty('wicket_mdp_target_field', this.value);">
                    <option value=""><?php esc_html_e('— Select —', 'wicket-gf'); ?></option>
                </select>
            </li>

        <?php echo ob_get_clean();
        }
    }

    public function gf_editor_script()
    {
        // Action to inject supporting script to the form editor page to populate our custom settings
        ?>
        <script type='text/javascript'>
            // Static field definitions per target object (sourced from MDP API PATCH schema).
            // Additional Info and Preferences are schema-driven; Task 2.1 will replace those with dynamic discovery.
            var wicketMdpTargetFields = {
                person_profile: [
                    { value: 'attributes.given_name',       label: '<?php esc_html_e('First Name', 'wicket-gf'); ?>' },
                    { value: 'attributes.family_name',      label: '<?php esc_html_e('Last Name', 'wicket-gf'); ?>' },
                    { value: 'attributes.additional_name',  label: '<?php esc_html_e('Additional Name', 'wicket-gf'); ?>' },
                    { value: 'attributes.alternate_name',   label: '<?php esc_html_e('Alternate Name', 'wicket-gf'); ?>' },
                    { value: 'attributes.maiden_name',      label: '<?php esc_html_e('Maiden Name', 'wicket-gf'); ?>' },
                    { value: 'attributes.gender',           label: '<?php esc_html_e('Gender', 'wicket-gf'); ?>' },
                    { value: 'attributes.honorific_prefix', label: '<?php esc_html_e('Honorific Prefix', 'wicket-gf'); ?>' },
                    { value: 'attributes.honorific_suffix', label: '<?php esc_html_e('Honorific Suffix', 'wicket-gf'); ?>' },
                    { value: 'attributes.preferred_pronoun',label: '<?php esc_html_e('Preferred Pronoun', 'wicket-gf'); ?>' },
                    { value: 'attributes.job_title',        label: '<?php esc_html_e('Job Title', 'wicket-gf'); ?>' },
                    { value: 'attributes.birth_date',       label: '<?php esc_html_e('Birth Date', 'wicket-gf'); ?>' },
                    { value: 'attributes.language',         label: '<?php esc_html_e('Language', 'wicket-gf'); ?>' },
                    { value: 'attributes.nickname',         label: '<?php esc_html_e('Nickname', 'wicket-gf'); ?>' },
                    { value: 'attributes.job_function',     label: '<?php esc_html_e('Job Function', 'wicket-gf'); ?>' },
                    { value: 'attributes.job_level',        label: '<?php esc_html_e('Job Level', 'wicket-gf'); ?>' }
                ],
                additional_info: [],
                preferences:     [],
                org_profile: [
                    { value: 'attributes.legal_name', label: '<?php esc_html_e('Legal Name', 'wicket-gf'); ?>' }
                ]
            };

            // Toggle a conditional MDP setting row. Uses setProperty('important') so it beats
            // the 'display: block !important' class rule that keeps these rows visible to GF.
            function wicketMdpToggle($el, show) {
                if (show) {
                    $el[0].style.removeProperty('display');
                } else {
                    $el[0].style.setProperty('display', 'none', 'important');
                }
            }

            function wicketPopulateMdpTargetFields(targetObject, selectedValue) {
                var $select = jQuery('#wicket_mdp_target_field');
                var $row    = jQuery('.wicket_global_custom_settings_mdp_target_field');
                var fields  = wicketMdpTargetFields[targetObject] || [];

                $select.empty().append('<option value=""><?php esc_html_e('— Select —', 'wicket-gf'); ?></option>');
                jQuery.each(fields, function(i, f) {
                    $select.append('<option value="' + f.value + '">' + f.label + '</option>');
                });
                if (selectedValue) {
                    $select.val(selectedValue);
                }
                wicketMdpToggle($row, fields.length > 0);
            }

            jQuery(document).on('gform_load_field_settings', function(event, field, form){
                jQuery('#hide_label').prop('checked', Boolean(rgar(field, 'hide_label')));

                var mdpEnabled   = Boolean(rgar(field, 'wicket_enable_mdp_mapping'));
                var targetObject = rgar(field, 'wicket_mdp_target_object') || '';
                var targetField  = rgar(field, 'wicket_mdp_target_field')  || '';

                jQuery('#wicket_enable_mdp_mapping').prop('checked', mdpEnabled);
                wicketMdpToggle(jQuery('.wicket_global_custom_settings_mdp_target_object'), mdpEnabled);

                jQuery('#wicket_mdp_target_object').val(targetObject);
                if (mdpEnabled && targetObject) {
                    wicketPopulateMdpTargetFields(targetObject, targetField);
                } else {
                    wicketMdpToggle(jQuery('.wicket_global_custom_settings_mdp_target_field'), false);
                }
            });

            jQuery(document).on('change', '#wicket_enable_mdp_mapping', function(){
                var enabled = this.checked;
                wicketMdpToggle(jQuery('.wicket_global_custom_settings_mdp_target_object'), enabled);
                if (!enabled) {
                    jQuery('#wicket_mdp_target_object').val('');
                    SetFieldProperty('wicket_mdp_target_object', '');
                    jQuery('#wicket_mdp_target_field').val('');
                    SetFieldProperty('wicket_mdp_target_field', '');
                    wicketMdpToggle(jQuery('.wicket_global_custom_settings_mdp_target_field'), false);
                }
            });

            jQuery(document).on('change', '#wicket_mdp_target_object', function(){
                jQuery('#wicket_mdp_target_field').val('');
                SetFieldProperty('wicket_mdp_target_field', '');
                var val = this.value;
                if (val) {
                    wicketPopulateMdpTargetFields(val, '');
                } else {
                    wicketMdpToggle(jQuery('.wicket_global_custom_settings_mdp_target_field'), false);
                }
            });
        </script>
        <?php
    }

    public function entries_list_first_column_content($form_id, $field_id, $value, $entry, $query_string)
    {
        echo 'Sample text.';
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
