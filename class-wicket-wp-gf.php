<?php

/**
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Gravity Forms
 * Plugin URI:        https://wicket.io
 * Description:       Adds Wicket functionality to Gravity Forms.
 * Version:           2.2.19
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

define('WICKET_WP_GF_VERSION', get_plugin_data(plugin_dir_path(__FILE__))['Version']);

/**
 * The main Wicket Gravity Forms class.
 */
class Wicket_Gf_Main
{
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
        $this->plugin_url = plugins_url('/', __FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->load_language('wicket-gf');

        $this->project_includes();

        // Load includes that depend on the Wicket base plugin
        add_action('init', [$this, 'project_includes_after_base'], 99);

        // Other plugin initialization
        add_action('plugins_loaded', [$this, 'init']);

        // Hook for shortcode
        add_shortcode('wicket_gravityform', [$this, 'shortcode']);

        // Initialize Wicket Org Validation
        $Wicket_Gf_Validation = new Wicket_Gf_Validation();

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
        add_action('admin_menu', ['Wicket_Gf_Admin', 'register_options_page'], 20);
        add_action('admin_init', ['Wicket_Gf_Admin', 'register_settings']);

        // Add settings link to plugins page listing
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", ['Wicket_Gf_Admin', 'add_settings_link']);

        // Allow all tags in gform fields that WP's wp_kses_post() allows
        add_filter('gform_allowable_tags', '__return_true');
        add_filter('wp_kses_allowed_html', [$this, 'update_kses_tags'], 1);

        // Modifying GF Entry screens
        add_action('gform_entries_first_column', [$this, 'entries_list_first_column_content'], 10, 5);
        add_filter('gform_get_field_value', [$this, 'gf_change_user_name'], 3);
        add_filter('gform_entry_detail_meta_boxes', ['Wicket_Gf_Admin', 'register_meta_box'], 10, 3);

        // Register scripts for conditional logic
        $this->register_conditional_logic_scripts();

        // Conditionally enqueue live update script for Wicket Hidden Data Bind fields
        add_action('gform_enqueue_scripts', [$this, 'conditionally_enqueue_live_update_script'], 10, 2);

        // Admin footer script for debugging
        add_action('admin_footer', [$this, 'output_wicket_event_debugger_script']);

        // Add form pre-render hook for pagination sidebar layout and other dynamic features
        add_filter('gform_pre_render', [$this, 'gf_custom_pre_render'], 50, 1);
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
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Handle form pre-render for pagination sidebar layout and other dynamic features.
     * @param array $form
     * @return array
     */
    public function gf_custom_pre_render($form)
    {
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

        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-mapping-addon.php';

        GFAddOn::register('GFWicketMappingAddOn');

        // handle displaying content for our custom menu when selected
        add_action('gform_form_settings_page_wicketmap', ['GFWicketMappingAddOn', 'addon_custom_ui'], 20);
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
        //require_once plugin_dir_path(__FILE__) . 'includes/class-debug-gravityforms-state.php';

        require_once plugin_dir_path(__FILE__) . 'admin/class-wicket-gf-admin.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gw-update-posts.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-wicket-gf-validation.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-wicket-gf-nonce-handler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-consent-field-extension.php';
    }

    /**
     * Register custom fields with Gravity Forms.
     *
     * @return void
     */
    public function register_custom_fields()
    {
        // Include the custom field classes now that Gravity Forms is loaded
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-org-search-select.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-user-mdp-tags.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-data-bind-hidden.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-api-data-bind.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile-org.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-additional-info.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-prefs.php';

        // Register the custom fields
        GF_Fields::register(new GFWicketFieldOrgSearchSelect());
        GF_Fields::register(new GFWicketFieldUserMdpTags());
        GF_Fields::register(new GFWicketFieldWidgetProfile());
        GF_Fields::register(new GFDataBindHiddenField());
        GF_Fields::register(new GFApiDataBindField());
        GF_Fields::register(new GFWicketFieldWidgetProfileOrg());
        GF_Fields::register(new GFWicketFieldWidgetAdditionalInfo());
        GF_Fields::register(new GFWicketFieldWidgetPrefs());
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
            require_once plugin_dir_path(__FILE__) . 'includes/class-object-type-wicket.php';
            gp_populate_anything()->register_object_type('wicket', 'GPPA_Object_Type_Wicket');
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
            $plugin_url = plugin_dir_url(__FILE__);
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
                'shouldAutoAdvance' => get_option('wicket_gf_orgss_auto_advance', true),
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
        if (class_exists('GFWicketFieldOrgSearchSelect')) {
            GFWicketFieldOrgSearchSelect::custom_settings($position, $form_id);
        }

        if (class_exists('GFDataBindHiddenField')) {
            GFDataBindHiddenField::custom_settings($position, $form_id);
        }
        if (class_exists('GFApiDataBindField')) {
            GFApiDataBindField::custom_settings($position, $form_id);
        }

        if (class_exists('GFWicketFieldWidgetProfileOrg')) {
            GFWicketFieldWidgetProfileOrg::custom_settings($position, $form_id);
        }

        if (class_exists('GFWicketFieldWidgetAdditionalInfo')) {
            GFWicketFieldWidgetAdditionalInfo::custom_settings($position, $form_id);
        }

        if (class_exists('GFWicketFieldWidgetPrefs')) {
            GFWicketFieldWidgetPrefs::custom_settings($position, $form_id);
        }

        // Add consent field extension settings
        if (class_exists('GFWicket_Consent_Field_Extension')) {
            GFWicket_Consent_Field_Extension::custom_settings($position, $form_id);
        }
    }

    /**
     * Output field-specific editor scripts.
     *
     * @return void
     */
    private function output_field_editor_scripts()
    {
        // Call each field's editor scripts
        if (class_exists('GFWicketFieldOrgSearchSelect')) {
            GFWicketFieldOrgSearchSelect::editor_script();
        }

        if (class_exists('GFDataBindHiddenField')) {
            GFDataBindHiddenField::editor_script();
        }

        if (class_exists('GFWicketFieldWidgetProfileOrg')) {
            GFWicketFieldWidgetProfileOrg::editor_script();
        }

        if (class_exists('GFWicketFieldWidgetAdditionalInfo')) {
            GFWicketFieldWidgetAdditionalInfo::editor_script();
        }

        if (class_exists('GFWicketFieldWidgetPrefs')) {
            GFWicketFieldWidgetPrefs::editor_script();
        }

        if (class_exists('GFWicket_Consent_Field_Extension')) {
            GFWicket_Consent_Field_Extension::editor_script();
        }
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

        <?php echo ob_get_clean();
        }
    }

    public function gf_editor_script()
    {
        // Action to inject supporting script to the form editor page to populate our custom settings
        ?>
        <script type='text/javascript'>
            // Binding to the load field settings event to initialize the checkbox
            jQuery(document).on('gform_load_field_settings', function(event, field, form){
                jQuery( '#hide_label' ).prop( 'checked', Boolean( rgar( field, 'hide_label' ) ) );
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

        return '<div class="container wicket-gf-shortcode">' .
            do_shortcode(
                "[gravityform id='" . $form_id . "' title='" . $title . "' description='" . $description . "' ajax='" . $ajax . "' tabindex='" . $tabindex . "' field_values='" . $field_values . "' theme='" . $theme . "']"
            ) .
            '</div>';
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

// General Helpers
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/tweaks.php';
