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
                        'data-type' => 'wicket_widget_profile',
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
                        'value'     => __('Profile Org W.', 'wicket-gf'),
                    ],
                    [
                        'class'     => 'button',
                        'data-type' => 'wicket_widget_ai',
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
        });

        // Bootstrap the GF Addon for field mapping
        add_action('gform_loaded', [$this, 'gf_mapping_addon_load'], 5);

        // Register Custom GF fields
        add_action('gform_loaded', [$this, 'register_custom_fields'], 5);

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

        // Conditionally enqueue live update script
        add_action('gform_pre_render', function ($form, $is_ajax) {
            $this->conditionally_enqueue_live_update_script($form, $is_ajax);
        }, 10, 2);

        // Conditionally enqueue live update script for Wicket Hidden Data Bind fields
        add_action('gform_enqueue_scripts', [$this, 'conditionally_enqueue_live_update_script'], 10, 2);

        add_action('admin_footer', [$this, 'output_wicket_event_debugger_script']);
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

    public static function gf_editor_global_custom_scripts()
    {
        ?>
        <!-- Alpine and other editor scripts in a webpacked script -->
        <script src="<?php echo plugin_dir_url(__FILE__) . 'js/gf_editor/dist/main.js'; ?>" defer></script>
        <?php
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

    public static function gf_custom_pre_render($form)
    {
        // Store what we want to add
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
                                        .gform_wrapper.gravity-theme label[for="input_' . $field['formId'] . '_' . $field['id'] . '"].gfield_label {
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

        // Return the form untouched
        return $form;
    }

    // TODO: Use a more specific hook so this doesn't run on every page,
    // maybe add a hook to the base plugin
    // public function store_data_after_plugins_loaded() {
    //     if(wicket_person_has_uuid()) {
    //         self::$wicket_current_person = wicket_current_person();
    //         self::$wicket_client = wicket_api_client();
    //     } else {
    //         // This is not a CAS-authenticated user
    //     }
    // }

    public function enqueue_scripts_styles($screen)
    {
        if ($screen == 'toplevel_page_gf_edit_forms') {
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
            'WicketGfPluginData', // This will be the global object in JS
            [
                'shouldAutoAdvance' => get_option('wicket_gf_orgss_auto_advance', true),
            ]
        );
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
                //return current_user_can('edit_posts');
                return true;
            },
        ]);
    }

    public static function resync_wicket_member_fields()
    {
        $to_return = [];

        // --------------------------------
        // --------- UUID Options ---------
        // --------------------------------
        $to_return[] = [
            'schema_id'     => '',
            'key'           => 'uuid_options',
            'name_en'       => '-- UUID Options --',
            'name_fr'       => '-- Options UUID --',
            'is_repeater'   => false,
            'child_fields'  => [
                [
                    'name'           => 'person_uuid',
                    'label_en'       => 'Person UUID',
                    'label_fr'       => 'Personne UUID',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'org_uuid',
                    'label_en'       => 'Organization UUID',
                    'label_fr'       => 'Organisation UUID',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
            ],
        ];

        // --------------------------------
        // --------- Profile Data ---------
        // --------------------------------

        // Add standard fields that don't change
        $to_return[] = [
            'schema_id'     => 'profile',
            'key'           => 'profile_options',
            'name_en'       => '-- Profile Options --',
            'name_fr'       => '-- Options Profil --',
            'is_repeater'   => false,
            'child_fields'  => [
                [
                    'name'           => 'given_name',
                    'label_en'       => 'Given/First Name',
                    'label_fr'       => 'Prénom',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'family_name',
                    'label_en'       => 'Family/Last Name',
                    'label_fr'       => 'Nom de famille',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'additional_name',
                    'label_en'       => 'Additional Name',
                    'label_fr'       => 'Nom supplémentaire',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'alternate_name',
                    'label_en'       => 'Alternate Name',
                    'label_fr'       => 'Nom alternatif',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'full_name',
                    'label_en'       => 'Full Name',
                    'label_fr'       => 'Nom et prénom',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'identifying_number',
                    'label_en'       => 'Identifying Number',
                    'label_fr'       => 'Numéro d\'identification',
                    'type'           => 'number',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'slug',
                    'label_en'       => 'Slug',
                    'label_fr'       => 'Slug',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'gender',
                    'label_en'       => 'Gender',
                    'label_fr'       => 'Genre',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'honorific_prefix',
                    'label_en'       => 'Prefix',
                    'label_fr'       => 'Préfixe',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'honorific_suffix',
                    'label_en'       => 'Suffix',
                    'label_fr'       => 'Suffixe',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'preferred_pronoun',
                    'label_en'       => 'Preferred Pronoun',
                    'label_fr'       => 'Pronom préféré',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'job_title',
                    'label_en'       => 'Job Title',
                    'label_fr'       => 'Titre d\'emploi',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'birth_date',
                    'label_en'       => 'Birth Date',
                    'label_fr'       => 'Date de naissance',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'language',
                    'label_en'       => 'Language',
                    'label_fr'       => 'Langue',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'languages_spoken',
                    'label_en'       => 'Languages Spoken',
                    'label_fr'       => 'Langues parlées',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'languages_written',
                    'label_en'       => 'Languages Written',
                    'label_fr'       => 'Langues écrites',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
            ],
        ];

        // TODO: Resume work on this when store_data_after_plugins_loaded() has been updated
        // to use a more specific hook
        // Add data_fields which are custom per tenant
        // $current_user_data_fields = wicket_current_person();
        // if( !empty( self::$wicket_current_person ) ) {
        //     if( !empty( self::$wicket_current_person->data_fields ) ) {
        //         // TODO: Add handling of data_fields
        //     }
        // }

        // --------------------------------
        // ------- Additional Info --------
        // --------------------------------

        // Add a header
        $to_return[] = [
            'schema_id'     => '',
            'key'           => 'header_additional_info',
            'name_en'       => '-- Additional Info: --',
            'name_fr'       => '-- Information additionnelle: --',
            'is_repeater'   => false,
            'child_fields'  => [
                [
                    'name'           => '',
                    'label_en'       => '',
                    'label_fr'       => '',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
            ],
        ];

        // Get all Additional Info Schemas
        $all_schemas = wicket_get_schemas();

        foreach ($all_schemas['data'] as $schema) {
            // Ensure needed attributes are present before adding to array
            if (isset($schema['id']) && isset($schema['attributes'])) {
                if (isset($schema['attributes']['key'])) {

                    $items_array = self::wicket_schema_get_items_sub_array($schema);

                    if (!$items_array['is_repeater']) {
                        $child_fields = [];
                        if (isset($schema['attributes']['schema'])) {

                            $required_fields = [];
                            if (isset($schema['attributes']['schema']['required'])) {
                                $required_fields = $schema['attributes']['schema']['required'];
                            }

                            if (isset($schema['attributes']['schema']['properties'])) {
                                foreach ($schema['attributes']['schema']['properties'] as $property_name => $property_data) {
                                    // TODO: Add field required status

                                    $labels = self::wicket_schema_get_label_by_property_name($schema, $property_name);
                                    $label_en = $labels['en'];
                                    $label_fr = $labels['fr'];

                                    if (empty($label_en)) {
                                        $label_en = $property_name;
                                    }
                                    if (empty($label_fr)) {
                                        $label_fr = $property_name;
                                    }

                                    $label_en = $label_en;
                                    $label_fr = $label_fr;

                                    $is_required = in_array($property_name, $required_fields);

                                    $child_fields[] = [
                                        'name'           => $property_name,
                                        'label_en'       => $label_en,
                                        'label_fr'       => $label_fr,
                                        'type'           => $property_data['type'] ?? '',
                                        'default'        => $property_data['default'] ?? '',
                                        'required'       => $is_required,
                                        'maximum'        => $property_data['maximum'] ?? '',
                                        'minimum'        => $property_data['minimum'] ?? '',
                                        'enum'           => $property_data['enum'] ?? [],
                                        'path_to_field'  => 'attributes/schema/properties',
                                    ];
                                }

                                $to_return[] = [
                                    'schema_id'     => $schema['id'],
                                    'key'           => $schema['attributes']['key'] ?? '',
                                    'name_en'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['en'] ?? '',
                                    'name_fr'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['fr'] ?? '',
                                    'is_repeater'   => false,
                                    'child_fields'  => $child_fields,
                                ];
                            }
                        }
                    } else {
                        // If it IS a repeater
                        if ($schema['attributes']['key'] == 'education_details') {
                        }
                        // TODO: Pack more information about objects into the array and possibly reference the array position in the GF mapping
                        // value instead of the breadcrumbs

                        $repeater_fields = [];
                        if (isset($items_array['items']['properties'])) {
                            foreach ($items_array['items']['properties'] as $property_name => $property_data) {

                                $labels = self::wicket_schema_get_label_by_property_name($schema, $property_name, $items_array);
                                $label_en = $labels['en'];
                                $label_fr = $labels['fr'];

                                if (empty($label_en)) {
                                    $label_en = $property_name;
                                }
                                if (empty($label_fr)) {
                                    $label_fr = $property_name;
                                }

                                $label_en = $label_en;
                                $label_fr = $label_fr;

                                if ($property_data['type'] == 'object') {
                                    $object_data = self::expand_field_object($schema, $property_name, $property_data);

                                    if (isset($object_data['oneOf'])) {
                                        foreach ($object_data['oneOf'] as $object_field_name => $object_field_data) {
                                            $required_fields = [];
                                            if (isset($object_field_data['required'])) {
                                                $required_fields = $object_field_data['required'];
                                            }
                                            if (isset($object_field_data['properties'])) {
                                                if (is_array($object_field_data['properties'])) {
                                                    foreach ($object_field_data['properties'] as $object_field_prop_name => $object_field_prop_data) {
                                                        $is_required = in_array($object_field_prop_name, $required_fields);

                                                        $repeater_fields[] = [
                                                            'name'           => $object_field_prop_name,
                                                            'label_en'       => $label_en . ' | ' . $object_field_name . ' | ' . $object_field_prop_name,
                                                            'label_fr'       => $label_fr . ' | ' . $object_field_prop_name,
                                                            'type'           => $object_field_prop_data['type'] ?? '',
                                                            'default'        => $object_field_prop_data['default'] ?? '',
                                                            'required'       => $is_required,
                                                            'maximum'        => $object_field_prop_data['maximum'] ?? '',
                                                            'minimum'        => $object_field_prop_data['minimum'] ?? '',
                                                            'enum'           => $object_field_prop_data['enum'] ?? [],
                                                            'path_to_field'  => $items_array['path_to_items'] . '/' . $property_name,
                                                        ];
                                                    }
                                                }
                                            } else {
                                                // TODO: Handle if this is a possible scenario
                                                // $repeater_fields[] = [
                                                //     'name'           => $property_name,
                                                //     'label_en'       => $label_en . ' | ' . ,
                                                //     'label_fr'       => $label_fr,
                                                //     'type'           => $property_data['type'] ?? '',
                                                //     'default'        => $property_data['default'] ?? '',
                                                //     'maximum'        => $property_data['maximum'] ?? '',
                                                //     'minimum'        => $property_data['minimum'] ?? '',
                                                //     'enum'           => $property_data['enum'] ?? array(),
                                                //     'path_to_field'  => $items_array['path_to_items'] . '/' . $property_name,
                                                // ];
                                            }
                                        }
                                    }
                                } else {
                                    $required_fields = [];
                                    if (isset($property_data['required'])) {
                                        $required_fields = $property_data['required'];
                                    }

                                    $is_required = in_array($property_name, $required_fields);

                                    $repeater_fields[] = [
                                        'name'           => $property_name,
                                        'label_en'       => $label_en,
                                        'label_fr'       => $label_fr,
                                        'type'           => $property_data['type'] ?? '',
                                        'default'        => $property_data['default'] ?? '',
                                        'required'       => $is_required,
                                        'maximum'        => $property_data['maximum'] ?? '',
                                        'minimum'        => $property_data['minimum'] ?? '',
                                        'enum'           => $property_data['enum'] ?? [],
                                        'path_to_field'  => $items_array['path_to_items'] . '/properties',
                                    ];
                                }
                            }
                        }

                        $to_return[] = [
                            'schema_id'     => $schema['id'],
                            'key'           => $schema['attributes']['key'] ?? '',
                            'name_en'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['en'] ?? '',
                            'name_fr'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['fr'] ?? '',
                            'is_repeater'   => true,
                            'child_fields'  => $repeater_fields,
                        ];
                    }
                }
            }
        }

        // --------------------------------
        // ----------- Org Data -----------
        // --------------------------------

        // Add standard fields that don't change
        $to_return[] = [
            'schema_id'     => '',
            'key'           => 'org_options',
            'name_en'       => '-- Organization Options --',
            'name_fr'       => '-- Options Organisation --',
            'is_repeater'   => false,
            'child_fields'  => [
                [
                    'name'           => 'type',
                    'label_en'       => 'Type',
                    'label_fr'       => 'Type',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'legal_name',
                    'label_en'       => 'Legal Name',
                    'label_fr'       => 'Legal Name',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'legal_name_en',
                    'label_en'       => 'Legal Name (En)',
                    'label_fr'       => 'Legal Name (En)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'legal_name_fr',
                    'label_en'       => 'Legal Name (Fr)',
                    'label_fr'       => 'Legal Name (Fr)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'legal_name_es',
                    'label_en'       => 'Legal Name (Es)',
                    'label_fr'       => 'Legal Name (Es)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'alternate_name',
                    'label_en'       => 'Alternate Name',
                    'label_fr'       => 'Nom alternatif',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'alternate_name_en',
                    'label_en'       => 'Alternate Name (En)',
                    'label_fr'       => 'Nom alternatif (En)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'alternate_name_fr',
                    'label_en'       => 'Alternate Name (Fr)',
                    'label_fr'       => 'Nom alternatif (Fr)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'alternate_name_es',
                    'label_en'       => 'Alternate Name (Es)',
                    'label_fr'       => 'Nom alternatif (Es)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'description',
                    'label_en'       => 'Description',
                    'label_fr'       => 'Description',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'description_en',
                    'label_en'       => 'Description (En)',
                    'label_fr'       => 'Description (En)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'description_fr',
                    'label_en'       => 'Description (Fr)',
                    'label_fr'       => 'Description (Fr)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'description_es',
                    'label_en'       => 'Description (Es)',
                    'label_fr'       => 'Description (Es)',
                    'type'           => 'text',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
                [
                    'name'           => 'identifying_number',
                    'label_en'       => 'Identifying Number',
                    'label_fr'       => 'Numéro d\'identification',
                    'type'           => 'number',
                    'default'        => '',
                    'maximum'        => '',
                    'minimum'        => '',
                    'enum'           => [],
                    'path_to_field'  => '',
                ],
            ],
        ];

        // TODO: Continue work on this when store_data_after_plugins_loaded()
        // has been updated to use a more specific hook
        // Add data_fields which are custom per tenant
        // $example_org = self::$wicket_client->get('organizations?page_number=1&page_size=1');
        // $org_data_fields = array();
        // if( isset( $example_org['data'] ) ) {
        //     if( is_array( $example_org['data'] ) ) {
        //         if( isset( $example_org['data'][0]['attributes'] ) ) {
        //             if( isset( $example_org['data'][0]['attributes'] ) ) {
        //                 if( isset( $example_org['data'][0]['attributes']['data_fields'] ) ) {
        //                     $org_data_fields = $example_org['data'][0]['attributes']['data_fields'];
        //                 }
        //             }
        //         }
        //     }
        // }

        // --------------------------------
        // ---------- Membership ----------
        // --------------------------------

        // --------------------------------
        // -------- Relationships ---------
        // --------------------------------

        // --------------------------------
        // ------------ Groups ------------
        // --------------------------------

        // --------------------------------
        // ---------- Touchpoints ---------
        // --------------------------------

        // --------------------------------
        // ---------- Preferences ---------
        // --------------------------------

        // --------------------------------
        // ----------- Messages -----------
        // --------------------------------

        // --------------------------------
        // ----------- Security -----------
        // --------------------------------

        update_option('wicket_gf_member_fields', $to_return);
        wp_send_json_success();
    }

    public static function wicket_schema_get_label_by_property_name($schema, $property_name, $repeater_items_array = [])
    {

        $label_en = '';
        $label_fr = '';

        if (!empty($repeater_items_array)) {
            if (isset($repeater_items_array['items_ui'][$property_name])) {
                if (isset($repeater_items_array['items_ui'][$property_name]['ui:i18n'])) {
                    if (isset($repeater_items_array['items_ui'][$property_name]['ui:i18n']['label'])) {
                        if (isset($repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['en'])) {
                            $label_en = $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['en'];
                        }
                        if (isset($repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['fr'])) {
                            $label_fr = $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['fr'];
                        }
                    }
                }
            }
        } else {
            // Is not a repeater
            if (isset($schema['attributes']['ui_schema'])) {
                if (isset($schema['attributes']['ui_schema'][$property_name])) {
                    if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n'])) {
                        if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label'])) {
                            if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['en'])) {
                                $label_en = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['en'];
                            }
                            if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['fr'])) {
                                $label_fr = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['fr'];
                            }
                        } elseif (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description'])) {
                            if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['en'])) {
                                $label_en = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['en'];
                            }
                            if (isset($schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['fr'])) {
                                $label_fr = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['fr'];
                            }
                        }
                    }
                }
            }
        }

        return [
            'en' => $label_en,
            'fr' => $label_fr,
        ];
    }

    public static function wicket_schema_get_items_sub_array($schema)
    {
        if (isset($schema['attributes'])) {
            if (isset($schema['attributes']['ui_schema'])) {
                foreach ($schema['attributes']['ui_schema'] as $key => $data) {
                    // Note: wp_list_pluck() could be used, except that we need to build the path_to_items as we go
                    if ($key == 'items') {
                        if (!isset($schema['attributes']['schema']['properties'])) {
                            $items = $schema['attributes']['schema']['items']['properties'][$key];
                            // Note: for some reason one field is giving "Undefined array key "items"" even
                            // though items is indeed in the array. Maybe misconfigured in the MDP or has a space somewhere
                            if (empty($items)) {
                            }

                            return [
                                'is_repeater'     => true,
                                'repeater_depth'  => 1,
                                'items'           => $items,
                                'items_ui'        => $data,
                                'path_to_items'   => 'attributes/schema/properties/' . $key,
                            ];
                        } else {
                            return [
                                'is_repeater'     => true,
                                'repeater_depth'  => 1,
                                'items'           => $schema['attributes']['schema']['properties'][$key],
                                'items_ui'        => $data,
                                'path_to_items'   => 'attributes/schema/properties/' . $key,
                            ];
                        }
                    }
                    if (is_array($data)) {
                        foreach ($data as $key2 => $data2) {
                            if ($key2 == 'items') {
                                return [
                                    'is_repeater'     => true,
                                    'repeater_depth'  => 2,
                                    'items'           => $schema['attributes']['schema']['properties'][$key][$key2],
                                    'items_ui'        => $data2,
                                    'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2,
                                ];
                            }
                            if (is_array($data2)) {
                                foreach ($data2 as $key3 => $data3) {
                                    if ($key3 == 'items') {
                                        return [
                                            'is_repeater'     => true,
                                            'repeater_depth'  => 3,
                                            'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3],
                                            'items_ui'        => $data3,
                                            'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3,
                                        ];
                                    }
                                    if (is_array($data3)) {
                                        foreach ($data3 as $key4 => $data4) {
                                            if ($key4 == 'items') {
                                                return [
                                                    'is_repeater'     => true,
                                                    'repeater_depth'  => 4,
                                                    'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3][$key4],
                                                    'items_ui'        => $data4,
                                                    'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3 . '/' . $key4,
                                                ];
                                            }
                                            if (is_array($data4)) {
                                                foreach ($data4 as $key5 => $data5) {
                                                    if ($key5 == 'items') {
                                                        return [
                                                            'is_repeater'     => true,
                                                            'repeater_depth'  => 5,
                                                            'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3][$key4][$key5],
                                                            'items_ui'        => $data5,
                                                            'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3 . '/' . $key4 . '/' . $key5,
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'is_repeater'     => false,
            'repeater_depth'  => 0,
            'items'           => [],
            'items_ui'        => [],
        ];
    }

    public static function wicket_schema_get_definition($schema, $term, $follow_refs = false)
    {
        if (isset($schema['attributes'])) {
            if (isset($schema['attributes']['schema'])) {
                if (isset($schema['attributes']['schema']['definitions'])) {
                    if (isset($schema['attributes']['schema']['definitions'][$term])) {
                        $the_term = $schema['attributes']['schema']['definitions'][$term];
                        if (!$follow_refs) {
                            return $the_term;
                        } else {
                            if (isset($the_term['properties'])) {
                                foreach ($the_term['properties'] as $sub_term_key => $sub_term_val) {
                                    if (is_array($sub_term_val)) {
                                        foreach ($sub_term_val as $sub_sub_term_key => $sub_sub_term_val) {
                                            if ($sub_sub_term_key == '$ref') {
                                                // Get the next needed definition
                                                $needed_def = self::get_end_of_string_by($sub_sub_term_val, '/');
                                                $definition = self::wicket_schema_get_definition($schema, $needed_def, true);
                                                $the_term['properties'][$sub_term_key] = $definition;

                                                // TODO: Potentially handle further nested definitions
                                            }
                                        }
                                    }
                                }
                            }

                            return $the_term;
                        }
                    }
                }
            }
        }
    }

    public static function expand_field_object($schema, $property_name, $property_data)
    {
        if (isset($property_data['oneOf'])) {
            $i = 0;
            foreach ($property_data['oneOf'] as $one_of) {
                foreach ($one_of as $one_of_key => $one_of_val) {
                    if ($one_of_key == '$ref') {
                        $needed_def = self::get_end_of_string_by($one_of_val, '/');
                        $definition = self::wicket_schema_get_definition($schema, $needed_def, true);
                        $property_data['oneOf'][$needed_def] = $definition;
                    }
                }
                $i++;
            }
        }
        // TODO: Handle potential other field object cases that use a different structure than 'oneOf'

        return $property_data;
    }

    public static function get_end_of_string_by($string, $delimiter)
    {
        $array = explode($delimiter, $string);

        return $array[count($array) - 1];
    }

    // Credit: https://stackoverflow.com/a/263621
    public static function array_depth($array)
    {
        $max_indentation = 1;

        $array_str = print_r($array, true);
        $lines = explode("\n", $array_str);

        foreach ($lines as $line) {
            $indentation = (strlen($line) - strlen(ltrim($line))) / 4;

            if ($indentation > $max_indentation) {
                $max_indentation = $indentation;
            }
        }

        return (int) ceil(($max_indentation - 1) / 2) + 1;
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
        // Only enqueue if we're on a form page
        // if (!GFCommon::is_form_page()) {
        //     return;
        // }

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
                ['jquery', 'gform_gravityforms'], // Assuming Wicket SDK is globally available or enqueued elsewhere
                WICKET_WP_GF_VERSION,
                true
            );
            self::$live_update_script_enqueued = true;
        }
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
        // Include the custom field classes now that Gravity Forms is loaded
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-org-search-select.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-user-mdp-tags.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-data-bind-hidden.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-profile-org.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-ai.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gf-field-widget-prefs.php';

        // Now, register the custom fields
        GF_Fields::register(new GFWicketFieldOrgSearchSelect());
        GF_Fields::register(new GFWicketFieldUserMdpTags());
        GF_Fields::register(new GFWicketFieldWidgetProfile());
        GF_Fields::register(new GFDataBindHiddenField());
        GF_Fields::register(new GFWicketFieldWidgetProfileOrg());
        GF_Fields::register(new GFWicketFieldWidgetAi());
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

                        /*window.addEventListener('wwidget-component-additional-info-loaded', function(e) {
                            wicketLogWidgetEvent('LOADED', e);
                        });*/

                        // Listen for save success event
                        /*window.addEventListener('wwidget-component-additional-info-save-success', function(e) {
                            wicketLogWidgetEvent('SAVE_SUCCESS', e);
                        });*/

                        // Listen for delete success event
                        /*window.addEventListener('wwidget-component-additional-info-delete-success', function(e) {
                            wicketLogWidgetEvent('DELETE_SUCCESS', e);
                        });*/
                    }

                    // Check for Wicket and initialize
                    if (typeof window.Wicket !== 'undefined') {
                        window.Wicket.ready(initializeWidgetListeners);
                    } else {
                        // console.error('Wicket is not loaded yet');
                    }
                });
            </script>
<?php
        }
    }
}

add_action(
    'plugins_loaded',
    [Wicket_Gf_Main::get_instance(), 'plugin_setup'],
    11
);

// General Helpers
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
