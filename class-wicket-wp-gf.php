<?php

/**
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Gravity Forms
 * Plugin URI:        https://wicket.io
 * Description:       Adds Wicket powers to Gravity Forms and related helpful tools.
 * Version:           2.0.41
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        https://wicket.io
 * Support:           https://wicket.io
 * Domain Path:       /languages
 * Text Domain:       wicket-gf
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: wicket-wp-base-plugin, gravity-forms
 * Requires Plugins: gravityforms
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WICKET_WP_GF_VERSION', '2.0.26');

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
        wc_get_logger()->debug('Wicket Gravity Forms plugin setup started.', ['source' => 'wicket-gf']);

        $this->plugin_url = plugins_url('/', __FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->load_language('wicket-gf');

        $this->project_includes();

        add_action('plugins_loaded', [$this, 'project_includes_after_base'], 11);

        // Hook for shortcode
        add_shortcode('wicket_gravityform', [$this, 'shortcode']);

        // Add a custom field group for Wicket fields
        add_filter('gform_add_field_buttons', function ($field_groups) {
            $field_groups[] = [
                'name'   => 'wicket_fields',
                'label'  => __('Wicket', 'wicket-gf'),
                'fields' => [
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_org_search_select',
                        'value'     => __('Org Search', 'wicket-gf'),
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
                        'value'     => __('Data Bind', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_profile_org',
                        'value'     => __('Org Profile W.', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_additional_info',
                        'value'     => __('Add Info W.', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_prefs',
                        'value'     => __('Preferences', 'wicket-gf'),
                    ],
                ],
            ];

            return $field_groups;
        });        // Bootstrap the GF Addon for field mapping
        if (class_exists('GFForms') && method_exists('GFForms', 'include_feed_addon_framework')) {
            // Gravity Forms is already loaded, call immediately
            $this->gf_mapping_addon_load();
        } else {
            // Gravity Forms not loaded yet, hook into the event
            add_action('gform_loaded', [$this, 'gf_mapping_addon_load'], 5);
        }

        // Register Custom GF fields
        if (class_exists('GF_Fields')) {
            // Gravity Forms is already loaded, register fields immediately
            wc_get_logger()->debug('GF_Fields exists, calling register_custom_fields immediately', ['source' => 'wicket-gf']);
            $this->register_custom_fields();
        } else {
            // Gravity Forms not loaded yet, hook into the event
            wc_get_logger()->debug('GF_Fields not available, hooking into gform_loaded', ['source' => 'wicket-gf']);
            add_action('gform_loaded', [$this, 'register_custom_fields'], 5);
        }

        // Debug: Check if Gravity Forms classes exist
        wc_get_logger()->debug('GF_Fields class exists: ' . (class_exists('GF_Fields') ? 'YES' : 'NO'), ['source' => 'wicket-gf']);
        wc_get_logger()->debug('GF_Field class exists: ' . (class_exists('GF_Field') ? 'YES' : 'NO'), ['source' => 'wicket-gf']);

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
        add_action('gform_print_entry_header', [$this, 'custom_entry_header'], 10, 2);
        add_filter('gform_get_field_value', [$this, 'gf_change_user_name'], 3);
        add_filter('gform_entry_detail_meta_boxes', ['Wicket_Gf_Admin', 'register_meta_box'], 10, 3);

        // Register scripts for conditional logic
        $this->register_conditional_logic_scripts();

        // Add custom field settings hooks
        add_action('gform_field_standard_settings', [$this, 'register_field_settings'], 25, 2);
        add_action('gform_tooltips', [$this, 'register_tooltips']);

        // Conditionally enqueue live update script for Wicket Hidden Data Bind fields
        add_action('gform_enqueue_scripts', [$this, 'conditionally_enqueue_live_update_script'], 10, 2);

        add_action('admin_footer', [$this, 'output_wicket_event_debugger_script']);

        // Add a filter to modify the form object before it is rendered
        add_filter('gform_admin_pre_render', [$this, 'migrate_legacy_field_data']);
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
     * Register tooltips for custom field settings
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
        require_once plugin_dir_path(__FILE__) . 'admin/class-wicket-gf-admin.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gw-update-posts.php';
    }

    /**
     * Register custom fields with Gravity Forms.
     *
     * @return void
     */
    public function register_custom_fields()
    {
        // Simple test log
        wc_get_logger()->debug('register_custom_fields() method called!', ['source' => 'wicket-gf']);

        $logger = wc_get_logger();
        $logger->debug('register_custom_fields() called', ['source' => 'wicket-gf']);

        // Include the custom field classes now that Gravity Forms is loaded
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-org-search-select.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-user-mdp-tags.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-data-bind-hidden.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile-org.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-additional-info.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-prefs.php';

        $logger->debug('Field classes included, now registering...', ['source' => 'wicket-gf']);

        // Now, register the custom fields
        GF_Fields::register(new GFWicketFieldOrgSearchSelect());
        GF_Fields::register(new GFWicketFieldUserMdpTags());
        GF_Fields::register(new GFWicketFieldWidgetProfile());
        GF_Fields::register(new GFDataBindHiddenField());
        GF_Fields::register(new GFWicketFieldWidgetProfileOrg());
        GF_Fields::register(new GFWicketFieldWidgetAdditionalInfo());
        GF_Fields::register(new GFWicketFieldWidgetPrefs());

        $logger->debug('All custom fields registered successfully', ['source' => 'wicket-gf']);

        // Log all registered field types for verification
        $all_fields = GF_Fields::get_all();
        $field_types = array_map(function($field) { return $field->type; }, $all_fields);
        $logger->debug('All field types after registration: ' . print_r($field_types, true), ['source' => 'wicket-gf']);
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

    public function enqueue_scripts_styles($screen)
    {
        // Enqueue scripts on all GF admin pages
        if ($screen == 'toplevel_page_gf_edit_forms') {
            // Always enqueue core admin styles and scripts for custom field functionality
            wp_enqueue_style('wicket-gf-admin-style', plugins_url('css/wicket_gf_admin_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

            // Enqueue our main editor script (without Alpine.js)
            wp_enqueue_script(
                'wicket-gf-editor-main',
                plugins_url('js/wicket_gf_editor.js', __FILE__),
                ['jquery', 'gform_gravityforms_admin_vendors'],
                WICKET_WP_GF_VERSION,
                true
            );

            // Enqueue mapping-specific scripts when on the mapping subview
            if (isset($_GET['subview']) && isset($_GET['fid'])) {
                if ($_GET['subview'] == 'wicketmap') {
                    wp_enqueue_style('wicket-gf-addon-style', plugins_url('css/wicket_gf_addon_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');
                    wp_enqueue_script('wicket-gf-addon-script', plugins_url('js/wicket_gf_addon_script.js', __FILE__), ['jquery'], null, true);
                }
            }
        }
    }

    public function enqueue_frontend_scripts_styles()
    {
        wp_enqueue_style('wicket-gf-widget-style', plugins_url('css/wicket_gf_widget_style_helpers.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

        // General Wicket GF Styles
        wp_enqueue_style('wicket-gf-general-style', plugins_url('css/wicket_gf_styles.css', __FILE__), [], WICKET_WP_GF_VERSION, 'all');

        // General Wicket GF Scripts
        wp_enqueue_script('wicket-gf-general-script', plugins_url('js/wicket_gf_script.js', __FILE__), ['jquery'], WICKET_WP_GF_VERSION, true);

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
     * Register custom field settings for all Wicket fields
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
            GFDataBindHiddenField::render_wicket_live_update_settings($position, $form_id);
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

        // Load JavaScript on the last position to ensure all fields are rendered
        if ($position == 550) {
            $this->output_field_editor_scripts();
        }
    }

    /**
     * Output field-specific editor scripts
     *
     * @return void
     */
    private function output_field_editor_scripts()
    {
        // Call each field's editor script (only fields that have them)
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
                <input type="checkbox" id="hide_label" onclick="SetFieldProperty('hide_label', this.checked);" onkeypress="SetFieldProperty('hide_label', this.checked);">
                <label for="hide_label" class="inline">Hide Label</label>
            </li>

        <?php echo ob_get_clean();
        }
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

    public function custom_entry_header($form, $entry)
    {
        // Custom entry header logic if needed
    }

    public static function resync_wicket_member_fields()
    {
        // Implementation for resyncing member fields
        update_option('wicket_gf_member_fields', []);
        wp_send_json_success();
    }

    /**
     * Migrate legacy field data to the new format.
     *
     * @param array $form
     * @return array
     */
    public function migrate_legacy_field_data($form)
    {
        $logger = wc_get_logger();
        foreach ($form['fields'] as &$field) {
            // Log the field type and its inputs property before any changes
            $logger->debug('Migrating field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id, 'inputs_before' => print_r($field->inputs, true)]);

            // Ensure 'inputs' property is always an array for all custom fields
            if (!isset($field->inputs) || !is_array($field->inputs)) {
                $field->inputs = [];
                $logger->debug('Initialized inputs for field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id]);
            }

            // Ensure 'choices' property is always an array for all custom fields
            if (!isset($field->choices) || !is_array($field->choices)) {
                $field->choices = [];
                $logger->debug('Initialized choices for field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id]);
            }

            // Ensure 'conditionalLogic' property is always an object with a 'rules' array
            if (!isset($field->conditionalLogic) || !is_object($field->conditionalLogic)) {
                $field->conditionalLogic = new stdClass();
                $field->conditionalLogic->rules = [];
                $logger->debug('Initialized conditionalLogic for field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id]);
            } elseif (!isset($field->conditionalLogic->rules) || !is_array($field->conditionalLogic->rules)) {
                $field->conditionalLogic->rules = [];
                $logger->debug('Initialized conditionalLogic->rules for field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id]);
            }

            // Initialize properties for wicket_widget_additional_info
            if ($field->type == 'wicket_widget_additional_info') {
                if (!isset($field->wwidget_ai_type)) {
                    $field->wwidget_ai_type = 'people';
                    $logger->debug('Initialized wwidget_ai_type for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_org_uuid)) {
                    $field->wwidget_ai_org_uuid = '';
                    $logger->debug('Initialized wwidget_ai_org_uuid for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_schemas) || !is_array($field->wwidget_ai_schemas)) {
                    $field->wwidget_ai_schemas = [[]];
                    $logger->debug('Initialized wwidget_ai_schemas for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_use_slugs)) {
                    $field->wwidget_ai_use_slugs = false;
                    $logger->debug('Initialized wwidget_ai_use_slugs for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                // Crucial: Initialize the setting properties themselves
                if (!isset($field->wwidget_ai_type_setting)) {
                    $field->wwidget_ai_type_setting = '';
                    $logger->debug('Initialized wwidget_ai_type_setting for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_org_uuid_setting)) {
                    $field->wwidget_ai_org_uuid_setting = '';
                    $logger->debug('Initialized wwidget_ai_org_uuid_setting for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_schemas_setting)) {
                    $field->wwidget_ai_schemas_setting = '';
                    $logger->debug('Initialized wwidget_ai_schemas_setting for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
                if (!isset($field->wwidget_ai_use_slugs_setting)) {
                    $field->wwidget_ai_use_slugs_setting = '';
                    $logger->debug('Initialized wwidget_ai_use_slugs_setting for field: ' . $field->id, ['source' => 'wicket-gf-migration']);
                }
            }
            // Initialize properties for wicket_org_search_select
            if ($field->type == 'wicket_org_search_select') {
                if (!isset($field->orgss_search_mode)) {
                    $field->orgss_search_mode = 'org';
                }
                if (!isset($field->orgss_search_org_type)) {
                    $field->orgss_search_org_type = '';
                }
                if (!isset($field->orgss_relationship_type_upon_org_creation)) {
                    $field->orgss_relationship_type_upon_org_creation = 'employee';
                }
                if (!isset($field->orgss_relationship_mode)) {
                    $field->orgss_relationship_mode = 'person_to_organization';
                }
                if (!isset($field->orgss_new_org_type_override)) {
                    $field->orgss_new_org_type_override = '';
                }
                if (!isset($field->orgss_org_term_singular)) {
                    $field->orgss_org_term_singular = 'Organization';
                }
                if (!isset($field->orgss_org_term_plural)) {
                    $field->orgss_org_term_plural = 'Organizations';
                }
                if (!isset($field->orgss_no_results_message)) {
                    $field->orgss_no_results_message = '';
                }
                if (!isset($field->orgss_checkbox_id_new_org)) {
                    $field->orgss_checkbox_id_new_org = '';
                }
                if (!isset($field->orgss_disable_org_creation)) {
                    $field->orgss_disable_org_creation = false;
                }
                if (!isset($field->orgss_disable_selecting_orgs_with_active_membership)) {
                    $field->orgss_disable_selecting_orgs_with_active_membership = false;
                }
                if (!isset($field->orgss_active_membership_alert_title)) {
                    $field->orgss_active_membership_alert_title = '';
                }
                if (!isset($field->orgss_active_membership_alert_body)) {
                    $field->orgss_active_membership_alert_body = '';
                }
                if (!isset($field->orgss_active_membership_alert_button_1_text)) {
                    $field->orgss_active_membership_alert_button_1_text = '';
                }
                if (!isset($field->orgss_active_membership_alert_button_1_url)) {
                    $field->orgss_active_membership_alert_button_1_url = '';
                }
                if (!isset($field->orgss_active_membership_alert_button_1_style)) {
                    $field->orgss_active_membership_alert_button_1_style = 'primary';
                }
                if (!isset($field->orgss_active_membership_alert_button_1_new_tab)) {
                    $field->orgss_active_membership_alert_button_1_new_tab = false;
                }
                if (!isset($field->orgss_active_membership_alert_button_2_text)) {
                    $field->orgss_active_membership_alert_button_2_text = '';
                }
                if (!isset($field->orgss_active_membership_alert_button_2_url)) {
                    $field->orgss_active_membership_alert_button_2_url = '';
                }
                if (!isset($field->orgss_active_membership_alert_button_2_style)) {
                    $field->orgss_active_membership_alert_button_2_style = 'secondary';
                }
                if (!isset($field->orgss_active_membership_alert_button_2_new_tab)) {
                    $field->orgss_active_membership_alert_button_2_new_tab = false;
                }
                if (!isset($field->orgss_grant_roster_man_on_purchase)) {
                    $field->orgss_grant_roster_man_on_purchase = false;
                }
                if (!isset($field->orgss_grant_org_editor_on_select)) {
                    $field->orgss_grant_org_editor_on_select = false;
                }
                if (!isset($field->orgss_grant_org_editor_on_purchase)) {
                    $field->orgss_grant_org_editor_on_purchase = false;
                }
                if (!isset($field->orgss_hide_remove_buttons)) {
                    $field->orgss_hide_remove_buttons = false;
                }
                if (!isset($field->orgss_hide_select_buttons)) {
                    $field->orgss_hide_select_buttons = false;
                }
                if (!isset($field->orgss_display_removal_alert_message)) {
                    $field->orgss_display_removal_alert_message = false;
                }
                // Crucial: Initialize the setting property itself
                if (!isset($field->wicket_orgss_setting)) {
                    $field->wicket_orgss_setting = '';
                }
            }
            // For wicket_widget_profile_org
            if ($field->type == 'wicket_widget_profile_org') {
                if (!isset($field->wwidget_org_profile_uuid)) {
                    $field->wwidget_org_profile_uuid = '';
                }
                // Crucial: Initialize the setting property itself
                if (!isset($field->wicket_widget_org_profile_setting)) {
                    $field->wicket_widget_org_profile_setting = '';
                }
            }
            // For wicket_widget_prefs
            if ($field->type == 'wicket_widget_prefs') {
                if (!isset($field->wwidget_prefs_hide_comm)) {
                    $field->wwidget_prefs_hide_comm = false;
                }
                // Crucial: Initialize the setting property itself
                if (!isset($field->wicket_widget_person_prefs_setting)) {
                    $field->wicket_widget_person_prefs_setting = '';
                }
            }
            // Log the field type and its inputs property after changes
            $logger->debug('Migrated field: ' . $field->type, ['source' => 'wicket-gf-migration', 'field_id' => $field->id, 'inputs_after' => print_r($field->inputs, true)]);
            $logger->debug('Full field object after migration for field: ' . $field->id, ['source' => 'wicket-gf-migration', 'field_object' => print_r($field, true)]);
        }

        return $form;
    }

    
}

add_action(
    'plugins_loaded',
    [Wicket_Gf_Main::get_instance(), 'plugin_setup'],
    11
);

// General Helpers
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';