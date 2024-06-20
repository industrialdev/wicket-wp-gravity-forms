<?php
/**
 *
 * @package  wicket-gravity-forms
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Gravity Forms
 * Plugin URI:        https://wicket.io
 * Description:       Adds Wicket powers to Gravity Forms and related helpful tools.
 * Version:           1.0.0
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        https://wicket.io
 * Support:           https://wicket.io
 * Domain Path:       /languages
 * Text Domain:       wicket-gf
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WICKET_WP_GF_VERSION', '1.0.0' );

if ( ! in_array( 'gravityforms/gravityforms.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	/**
	 * Show Required Plugin Notice
	 */
	function wicket_gf_admin_notice() {
		// Deactivate the plugin.
		deactivate_plugins( __FILE__ );

		$wicket_gf_plugin_check = '<div id="message" class="error">
            <p><strong>Wicket Gravity Forms plugin is inactive.</strong> The <a href="https://www.gravityforms.com/">Gravity Forms plugin</a> must be active for this plugin to be used. Please install &amp; activate Gravity Forms »</p></div>';
		echo wp_kses_post( $wicket_gf_plugin_check );
	}

	add_action( 'admin_notices', 'wicket_gf_admin_notice' );
}

if ( ! class_exists( 'Wicket_Gf_Main' ) ) {
	/**
	 * The main Wicket Gravity Forms class
	 */
	class Wicket_Gf_Main {

         /**
		 * Class variables
		 */
        private static $wicket_current_person;
        private static $wicket_client;


        /**
		 * Constructor
		 */
		public function __construct() {
            add_action( 'plugins_loaded', array($this, 'conditionally_include_pa_object') );

            // Hook for shortcode
            add_shortcode('wicket_gravityform', array($this,'shortcode')); 

            // Bootstrap the GF Addon for field mapping
            add_action( 'gform_loaded', array( $this, 'gf_mapping_addon_load' ), 5 );

            // Custom GF fields
            add_action( 'gform_loaded', array( $this, 'gf_load_custom_fields' ), 5 );

            // Enqueue scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

            // Register Rest Routes
          	add_action('rest_api_init', array($this, 'register_rest_routes') );

            // Grab user info *after the necessary WP info loads
            add_action( 'plugins_loaded', array( $this, 'store_data_after_plugins_loaded' ) );

            require_once( plugin_dir_path( __FILE__ ) . 'admin/class-wicket-gf-admin.php' );

            // Add Options Page for plugin
            add_action('admin_menu', array('Wicket_Gf_Admin','register_options_page'), 20 );
            add_action('admin_init', array('Wicket_Gf_Admin','register_settings') );

            // Add settings link to plugins page listing
            $plugin = plugin_basename(__FILE__);
            add_filter("plugin_action_links_$plugin", array('Wicket_Gf_Admin', 'add_settings_link') );
        }

        public static function gf_mapping_addon_load() {
            if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
                return;
            }
    
            require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-mapping-addon.php' );
    
            GFAddOn::register( 'GFWicketMappingAddOn' );

            // handle displaying content for our custom menu when selected
            add_action( 'gform_form_settings_page_wicketmap', array( 'GFWicketMappingAddOn', 'addon_custom_ui' ), 20 );
        }

        public static function gf_load_custom_fields() {
            // Load global editor scripts
            add_action( 'gform_editor_js', ['Wicket_Gf_Main','gf_editor_global_custom_scripts'] );

            // Custom field: org search/select
            require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-field-org-search-select.php' );
            add_action( 'gform_field_standard_settings', ['GFWicketFieldOrgSearchSelect','custom_settings'], 10, 2 );
            add_action( 'gform_editor_js', ['GFWicketFieldOrgSearchSelect','editor_script'] );

            // Custom field: individual profile widget
            require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-field-widget-profile.php' );

            // Custom field: additional info widget
            require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-field-widget-ai.php' );
            add_action( 'gform_field_standard_settings', ['GFWicketFieldWidgetAi','custom_settings'], 10, 2 );
            add_action( 'gform_editor_js', ['GFWicketFieldWidgetAi','editor_script'] );

            // Apply pre-form-render actions based on our settings above as needed
            add_filter( 'gform_pre_render', ['Wicket_Gf_Main','gf_custom_pre_render'] );
        }

        public static function gf_editor_global_custom_scripts() {
            ?>

            <!-- Include Alpine here for easier development of custom JS in the GF editor -->
            <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

            <script>
                // Check if we're currently looking at one of our elements, and if so show the settings for it,
                // otherwise hide the settings
                let orgss_settings_panes = document.querySelectorAll('.wicket_orgss_setting');
                let wwidget_ai_settings = document.querySelectorAll('.wicket_widget_ai_setting');
                let gf_fields_wrapper = document.querySelector('#gform_fields');
                let gf_edit_field_button = document.querySelector('.gfield-field-action.gfield-edit');

                jQuery(document).on('gform_load_field_settings', conditionallyShowElementControls);
                gf_fields_wrapper.addEventListener('click', conditionallyShowElementControls);
                gf_edit_field_button.addEventListener('click', conditionallyShowElementControls);

                function conditionallyShowElementControls (event) {
                    let selectedField = GetSelectedField(); // GF editor function
                    //console.log(event.target);
                    //console.log(selectedField);

                    // Org search/select
                    if( selectedField.type == "wicket_org_search_select" ) {
                        for (let orgss_settings_pane of orgss_settings_panes) {
                            orgss_settings_pane.style.display = "block";
                        }
                    } else {
                        for (let orgss_settings_pane of orgss_settings_panes) {
                            orgss_settings_pane.style.display = "none";
                        }
                    }
                    // AI widget
                    if( selectedField.type == "wicket_widget_ai" ) {
                        for (let wwidget_ai_setting of wwidget_ai_settings) {
                            wwidget_ai_setting.style.display = "block";
                        }
                    } else {
                        for (let wwidget_ai_setting of wwidget_ai_settings) {
                            wwidget_ai_setting.style.display = "none";
                        }
                    }

                }
            </script>

            <?php
        }

        public static function gf_custom_pre_render( $form ) {
            // Echo what we want to add
            if( get_option('wicket_gf_pagination_sidebar_layout') ) {
                ob_start(); ?>

                <script>
                    window.addEventListener('load', function () {
                    if (document.querySelector('body') !== null) {

                        // Check and see if the page is using the steps version of pagination,
                        // and if so re-format it
                        let paginationStepsCheck = document.querySelector('.gf_page_steps');
                        if( paginationStepsCheck != null ) {
                            document.head.insertAdjacentHTML("beforeend", `
                            <style>
                                form[id^=gform_] {
                                    display: flex;
                                }
                                .gf_page_steps {
                                    display: flex;
                                    flex-direction: column;
                                    min-width: 250px;
                                }
                                .gf_page_steps .gf_step_active {
                                    background: #efefef;
                                    padding: 5px;
                                    border-radius: 999px;
                                    margin-left: -5px !important;
                                }
                                .gform_body {
                                    flex-grow: 1;
                                }
                            </style>`);
                        }
                    }
                    });

                </script>

                <?php echo ob_get_clean();
            }

            // Return the form untouched
            return $form;
        }

        public function conditionally_include_pa_object() {
            // Only initialize this plugin if Wicket Helper plugin is active
            if( function_exists( 'wicket_api_client' ) ) {
                
                // Only initialize this plugin if Populate Anything plugin is active
                if ( class_exists( 'GP_Populate_Anything' ) && class_exists( 'GPPA_Object_Type' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'includes/class-object-type-wicket.php';
                    gp_populate_anything()->register_object_type( 'wicket', 'GPPA_Object_Type_Wicket' );
                }
            }
        }

        public function store_data_after_plugins_loaded() {
            self::$wicket_current_person = wicket_current_person();
            self::$wicket_client = wicket_api_client();
        }

        public function enqueue_scripts_styles($screen) {
            if( $screen == 'toplevel_page_gf_edit_forms' ) {
                if( isset( $_GET['subview'] ) && isset( $_GET['fid'] ) ) {
                    if( $_GET['subview'] == 'wicketmap' ) {
                        wp_enqueue_style( 'wicket-gf-addon-style', plugins_url( 'css/wicket_gf_addon_styles.css', __FILE__ ), array(), WICKET_WP_GF_VERSION, 'all');
                        wp_enqueue_script( 'wicket-gf-addon-script', plugins_url( 'js/wicket_gf_addon_script.js', __FILE__ ), array( 'jquery' ), null, true );
                    }
                }
            }
            return;
        }

		public static function shortcode($atts) {
            // override default attributes with user attributes
            $a = shortcode_atts([
                    "slug"         => "",
                    "title"        => true,
                    "description"  => true,
                    "ajax"         => "",
                    "tabindex"     => "",
                    "field_values" => "",
                    "theme"        => ""
            ], $atts);

            if( empty( $a['slug'] ) ) {
                    return;
            }

            $form_id = wicket_gf_get_form_id_by_slug( $a['slug'] );
            $title = $a['title'];
            $description = $a['description'];
            $ajax = $a['ajax'];
            $tabindex = $a['tabindex'];
            $field_values = $a['field_values'];
            $theme = $a['theme'];

            return do_shortcode(
                    "[gravityform id='".$form_id."' title='".$title."' description='".$description."' ajax='".$ajax."' tabindex='".$tabindex."' field_values='".$field_values."' theme='".$theme."']"
            );
        }

        public static function register_rest_routes() {
          register_rest_route( 'wicket-gf/v1', 'resync-member-fields',array(
            'methods'  => 'POST',
            'callback' => array( 'Wicket_Gf_Main', 'resync_wicket_member_fields' ),
            'permission_callback' => function() {
              //return current_user_can('edit_posts');
              return true;
            }
          ));
        }

        public static function resync_wicket_member_fields() {
            //wicket_write_log($_POST);

            $to_return = array();

            // --------------------------------
            // --------- UUID Options ---------
            // --------------------------------
            $to_return[] = array(
                'schema_id'     => '',
                'key'           => 'uuid_options',
                'name_en'       => '-- UUID Options --',
                'name_fr'       => '-- Options UUID --',
                'is_repeater'   => false,
                'child_fields'  => array(
                    array( 
                        'name'           => 'person_uuid',
                        'label_en'       => 'Person UUID',
                        'label_fr'       => 'Personne UUID',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'org_uuid',
                        'label_en'       => 'Organization UUID',
                        'label_fr'       => 'Organisation UUID',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                ),
            );

            $child_fields[] = [
                'name'           => $property_name,
                'label_en'       => $label_en,
                'label_fr'       => $label_fr,
                'type'           => $property_data['type'] ?? '',
                'default'        => $property_data['default'] ?? '',
                'maximum'        => $property_data['maximum'] ?? '',
                'minimum'        => $property_data['minimum'] ?? '',
                'enum'           => $property_data['enum'] ?? array(),
                'path_to_field'  => 'attributes/schema/properties',
            ];

            // --------------------------------
            // --------- Profile Data ---------
            // --------------------------------

            // Add standard fields that don't change
            $to_return[] = array(
                'schema_id'     => 'profile',
                'key'           => 'profile_options',
                'name_en'       => '-- Profile Options --',
                'name_fr'       => '-- Options Profil --',
                'is_repeater'   => false,
                'child_fields'  => array(
                    array( 
                        'name'           => 'given_name',
                        'label_en'       => 'Given/First Name',
                        'label_fr'       => 'Prénom',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'family_name',
                        'label_en'       => 'Family/Last Name',
                        'label_fr'       => 'Nom de famille',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'additional_name',
                        'label_en'       => 'Additional Name',
                        'label_fr'       => 'Nom supplémentaire',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'alternate_name',
                        'label_en'       => 'Alternate Name',
                        'label_fr'       => 'Nom alternatif',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'full_name',
                        'label_en'       => 'Full Name',
                        'label_fr'       => 'Nom et prénom',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'identifying_number',
                        'label_en'       => 'Identifying Number',
                        'label_fr'       => 'Numéro d\'identification',
                        'type'           => 'number',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'slug',
                        'label_en'       => 'Slug',
                        'label_fr'       => 'Slug',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'gender',
                        'label_en'       => 'Gender',
                        'label_fr'       => 'Genre',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'honorific_prefix',
                        'label_en'       => 'Prefix',
                        'label_fr'       => 'Préfixe',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'honorific_suffix',
                        'label_en'       => 'Suffix',
                        'label_fr'       => 'Suffixe',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'preferred_pronoun',
                        'label_en'       => 'Preferred Pronoun',
                        'label_fr'       => 'Pronom préféré',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'job_title',
                        'label_en'       => 'Job Title',
                        'label_fr'       => 'Titre d\'emploi',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'birth_date',
                        'label_en'       => 'Birth Date',
                        'label_fr'       => 'Date de naissance',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'language',
                        'label_en'       => 'Language',
                        'label_fr'       => 'Langue',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'languages_spoken',
                        'label_en'       => 'Languages Spoken',
                        'label_fr'       => 'Langues parlées',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'languages_written',
                        'label_en'       => 'Languages Written',
                        'label_fr'       => 'Langues écrites',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                ),
            );

            // Add data_fields which are custom per tenant
            $current_user_data_fields = wicket_current_person();
            if( !empty( self::$wicket_current_person ) ) {
                if( !empty( self::$wicket_current_person->data_fields ) ) {
                    // wicket_write_log('Data fields:', true);
                    // wicket_write_log(self::$wicket_current_person->data_fields, true);

                    // TODO: Add handling of data_fields
                }
            }

            // --------------------------------
            // ------- Additional Info --------
            // --------------------------------

            // Add a header
            $to_return[] = array(
                'schema_id'     => '',
                'key'           => 'header_additional_info',
                'name_en'       => '-- Additional Info: --',
                'name_fr'       => '-- Information additionnelle: --',
                'is_repeater'   => false,
                'child_fields'  => array(
                    array( 
                        'name'           => '',
                        'label_en'       => '',
                        'label_fr'       => '',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                ),
            );


            // Get all Additional Info Schemas
            $all_schemas = wicket_get_schemas();
            //wicket_write_log($all_schemas);

            foreach( $all_schemas['data'] as $schema ) {
                // Ensure needed attributes are present before adding to array
                if( isset( $schema['id'] ) && isset( $schema['attributes'] ) ) {
                    if( isset( $schema['attributes']['key'] ) ) {

                        $items_array = self::wicket_schema_get_items_sub_array( $schema );

                        if( !$items_array['is_repeater']) {
                            $child_fields = array();
                            if( isset( $schema['attributes']['schema'] ) ) {

                                $required_fields = array();
                                if( isset( $schema['attributes']['schema']['required'] ) ) {
                                    $required_fields = $schema['attributes']['schema']['required'];
                                }

                                if( isset( $schema['attributes']['schema']['properties'] ) ) {
                                    foreach( $schema['attributes']['schema']['properties'] as $property_name => $property_data ) {
                                        // TODO: Add field required status
                                        
                                        $labels = self::wicket_schema_get_label_by_property_name( $schema, $property_name );
                                        $label_en = $labels['en'];
                                        $label_fr = $labels['fr'];
                                        
                                        if( empty( $label_en ) ) {
                                            $label_en = $property_name;
                                        }
                                        if( empty( $label_fr ) ) {
                                            $label_fr = $property_name;
                                        }

                                        $label_en = $label_en;
                                        $label_fr = $label_fr;

                                        $is_required = in_array( $property_name, $required_fields );

                                        // wicket_write_log($property_name . ':');
                                        // wicket_write_log($property_data);

                                        $child_fields[] = [
                                            'name'           => $property_name,
                                            'label_en'       => $label_en,
                                            'label_fr'       => $label_fr,
                                            'type'           => $property_data['type'] ?? '',
                                            'default'        => $property_data['default'] ?? '',
                                            'required'       => $is_required,
                                            'maximum'        => $property_data['maximum'] ?? '',
                                            'minimum'        => $property_data['minimum'] ?? '',
                                            'enum'           => $property_data['enum'] ?? array(),
                                            'path_to_field'  => 'attributes/schema/properties',
                                        ];
                                    }

                                    $to_return[] = array(
                                        'schema_id'     => $schema['id'],
                                        'key'           => $schema['attributes']['key'] ?? '',
                                        'name_en'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['en'] ?? '',
                                        'name_fr'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['fr'] ?? '',
                                        'is_repeater'   => false,
                                        'child_fields'  => $child_fields,
                                    );
                                }
                            }
                        } else {
                            // If it IS a repeater
                            if( $schema['attributes']['key'] == 'education_details' ) {
                                //wicket_write_log("Education details:");
                                //wicket_write_log($schema);
                            }
                            // TODO: Pack more information about objects into the array and possibly reference the array position in the GF mapping
                            // value instead of the breadcrumbs

                            $repeater_fields = array();
                            if( isset( $items_array['items']['properties'] ) ) {
                                foreach( $items_array['items']['properties'] as $property_name => $property_data ) {
                                            
                                    $labels = self::wicket_schema_get_label_by_property_name( $schema, $property_name, $items_array );
                                    $label_en = $labels['en'];
                                    $label_fr = $labels['fr'];

                                    if( empty( $label_en ) ) {
                                        $label_en = $property_name;
                                    }
                                    if( empty( $label_fr ) ) {
                                        $label_fr = $property_name;
                                    }

                                    $label_en = $label_en;
                                    $label_fr = $label_fr;

                                    if( $property_data['type'] == 'object' ) {
                                        wicket_write_log('Object detected: ' . $property_name);
                                        $object_data = self::expand_field_object( $schema, $property_name, $property_data );

                                        if( isset( $object_data['oneOf'] ) ) {
                                            foreach( $object_data['oneOf'] as $object_field_name => $object_field_data ) {
                                                wicket_write_log("Object field received:");
                                                wicket_write_log($object_field_name);
                                                wicket_write_log($object_field_data);
                                                $required_fields = array();
                                                if( isset( $object_field_data['required'] ) ) {
                                                    $required_fields = $object_field_data['required'];
                                                }
                                                if( isset( $object_field_data['properties'] ) ) {
                                                    if( is_array( $object_field_data['properties'] ) ) {
                                                        foreach( $object_field_data['properties'] as $object_field_prop_name => $object_field_prop_data ) {
                                                            $is_required = in_array( $object_field_prop_name, $required_fields );
                                                            
                                                            $repeater_fields[] = [
                                                                'name'           => $object_field_prop_name,
                                                                'label_en'       => $label_en . ' | ' . $object_field_name . ' | ' . $object_field_prop_name,
                                                                'label_fr'       => $label_fr . ' | ' . $object_field_prop_name,
                                                                'type'           => $object_field_prop_data['type'] ?? '',
                                                                'default'        => $object_field_prop_data['default'] ?? '',
                                                                'required'       => $is_required,
                                                                'maximum'        => $object_field_prop_data['maximum'] ?? '',
                                                                'minimum'        => $object_field_prop_data['minimum'] ?? '',
                                                                'enum'           => $object_field_prop_data['enum'] ?? array(),
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
                                        $required_fields = array();
                                        if( isset( $property_data['required'] ) ) {
                                            $required_fields = $property_data['required'];
                                        }

                                        $is_required = in_array( $property_name, $required_fields );

                                        $repeater_fields[] = [
                                            'name'           => $property_name,
                                            'label_en'       => $label_en,
                                            'label_fr'       => $label_fr,
                                            'type'           => $property_data['type'] ?? '',
                                            'default'        => $property_data['default'] ?? '',
                                            'required'       => $is_required,
                                            'maximum'        => $property_data['maximum'] ?? '',
                                            'minimum'        => $property_data['minimum'] ?? '',
                                            'enum'           => $property_data['enum'] ?? array(),
                                            'path_to_field'  => $items_array['path_to_items'] . '/properties',
                                        ];
                                    }
                                }
                            }

                            $to_return[] = array(
                                'schema_id'     => $schema['id'],
                                'key'           => $schema['attributes']['key'] ?? '',
                                'name_en'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['en'] ?? '',
                                'name_fr'       => $schema['attributes']['ui_schema']['ui:i18n']['title']['fr'] ?? '',
                                'is_repeater'   => true,
                                'child_fields'  => $repeater_fields,
                            );
                        }
                    }
                }
            }

            // --------------------------------
            // ----------- Org Data -----------
            // --------------------------------

            // Add standard fields that don't change
            $to_return[] = array(
                'schema_id'     => '',
                'key'           => 'org_options',
                'name_en'       => '-- Organization Options --',
                'name_fr'       => '-- Options Organisation --',
                'is_repeater'   => false,
                'child_fields'  => array(
                    array( 
                        'name'           => 'type',
                        'label_en'       => 'Type',
                        'label_fr'       => 'Type',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'legal_name',
                        'label_en'       => 'Legal Name',
                        'label_fr'       => 'Legal Name',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'legal_name_en',
                        'label_en'       => 'Legal Name (En)',
                        'label_fr'       => 'Legal Name (En)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'legal_name_fr',
                        'label_en'       => 'Legal Name (Fr)',
                        'label_fr'       => 'Legal Name (Fr)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'legal_name_es',
                        'label_en'       => 'Legal Name (Es)',
                        'label_fr'       => 'Legal Name (Es)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'alternate_name',
                        'label_en'       => 'Alternate Name',
                        'label_fr'       => 'Nom alternatif',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'alternate_name_en',
                        'label_en'       => 'Alternate Name (En)',
                        'label_fr'       => 'Nom alternatif (En)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'alternate_name_fr',
                        'label_en'       => 'Alternate Name (Fr)',
                        'label_fr'       => 'Nom alternatif (Fr)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'alternate_name_es',
                        'label_en'       => 'Alternate Name (Es)',
                        'label_fr'       => 'Nom alternatif (Es)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'description',
                        'label_en'       => 'Description',
                        'label_fr'       => 'Description',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'description_en',
                        'label_en'       => 'Description (En)',
                        'label_fr'       => 'Description (En)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'description_fr',
                        'label_en'       => 'Description (Fr)',
                        'label_fr'       => 'Description (Fr)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'description_es',
                        'label_en'       => 'Description (Es)',
                        'label_fr'       => 'Description (Es)',
                        'type'           => 'text',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                    array( 
                        'name'           => 'identifying_number',
                        'label_en'       => 'Identifying Number',
                        'label_fr'       => 'Numéro d\'identification',
                        'type'           => 'number',
                        'default'        => '',
                        'maximum'        => '',
                        'minimum'        => '',
                        'enum'           => array(),
                        'path_to_field'  => '',
                    ),
                ),
            );

            // Add data_fields which are custom per tenant
            $example_org = self::$wicket_client->get('organizations?page_number=1&page_size=1');
            $org_data_fields = array();
            if( isset( $example_org['data'] ) ) {
                if( is_array( $example_org['data'] ) ) {
                    if( isset( $example_org['data'][0]['attributes'] ) ) {
                        if( isset( $example_org['data'][0]['attributes'] ) ) {
                            if( isset( $example_org['data'][0]['attributes']['data_fields'] ) ) {
                                $org_data_fields = $example_org['data'][0]['attributes']['data_fields'];
                            }
                        }
                    }
                }
            }
            wicket_write_log($org_data_fields);
            // TODO: Add handling of org data_fields

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

            update_option( 'wicket_gf_member_fields', $to_return );
            wp_send_json_success();
        }

        public static function wicket_schema_get_label_by_property_name( $schema, $property_name, $repeater_items_array = array() ) {

            $label_en = '';
            $label_fr = '';

            if( !empty( $repeater_items_array  ) ) {
                if( isset( $repeater_items_array['items_ui'][$property_name] ) ) {
                    if( isset( $repeater_items_array['items_ui'][$property_name]['ui:i18n'] ) ) {
                        if( isset( $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label'] ) ) {
                            if( isset( $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['en'] ) ) {
                                $label_en = $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['en'];
                            }
                            if( isset( $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['fr'] ) ) {
                                $label_fr = $repeater_items_array['items_ui'][$property_name]['ui:i18n']['label']['fr'];
                            }
                        }
                    }
                }
            } else {
                // Is not a repeater
                if( isset( $schema['attributes']['ui_schema'] ) ) {
                    if( isset( $schema['attributes']['ui_schema'][$property_name] ) ) {
                        if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n'] ) ) {
                            if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label'] ) ) {
                                if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['en'] ) ) {
                                    $label_en = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['en'];
                                }
                                if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['fr'] ) ) {
                                    $label_fr = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['label']['fr'];
                                }
                            } else if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description'] ) ) {
                                if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['en'] ) ) {
                                    $label_en = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['en'];
                                }
                                if( isset( $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['fr'] ) ) {
                                    $label_fr = $schema['attributes']['ui_schema'][$property_name]['ui:i18n']['description']['fr'];
                                }
                            }
                        }
                    }
                }
            }

            return array(
                'en' => $label_en,
                'fr' => $label_fr
            );
        }

        public static function wicket_schema_get_items_sub_array( $schema ) {
            if( isset( $schema['attributes'] ) ) {
                if( isset( $schema['attributes']['ui_schema'] ) ) {
                    foreach( $schema['attributes']['ui_schema'] as $key => $data ) {
                        // Note: wp_list_pluck() could be used, except that we need to build the path_to_items as we go
                        if( $key == 'items' ) {
                            if( !isset( $schema['attributes']['schema']['properties'] ) ) {
                                $items = $schema['attributes']['schema']['items']['properties'][$key];
                                // Note: for some reason one field is giving "Undefined array key "items"" even
                                // though items is indeed in the array. Maybe misconfigured in the MDP or has a space somewhere
                                if( empty($items) ) {
                                    // wicket_write_log("No items for some reason:");
                                    // wicket_write_log($schema);
                                }
                                return array(
                                    'is_repeater'     => true,
                                    'repeater_depth'  => 1,
                                    'items'           => $items,
                                    'items_ui'        => $data,
                                    'path_to_items'   => 'attributes/schema/properties/' . $key,
                                );
                            } else {
                                return array(
                                    'is_repeater'     => true,
                                    'repeater_depth'  => 1,
                                    'items'           => $schema['attributes']['schema']['properties'][$key],
                                    'items_ui'        => $data,
                                    'path_to_items'   => 'attributes/schema/properties/' . $key,
                                );
                            }
                        }
                        if( is_array( $data ) ) {
                            foreach( $data as $key2 => $data2 ) {
                                if( $key2 == 'items' ) {
                                    return array(
                                        'is_repeater'     => true,
                                        'repeater_depth'  => 2,
                                        'items'           => $schema['attributes']['schema']['properties'][$key][$key2],
                                        'items_ui'        => $data2,
                                        'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2,
                                    );
                                }
                                if( is_array( $data2 ) ) {
                                    foreach( $data2 as $key3 => $data3 ) {
                                        if( $key3 == 'items' ) {
                                            return array(
                                                'is_repeater'     => true,
                                                'repeater_depth'  => 3,
                                                'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3],
                                                'items_ui'        => $data3,
                                                'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3,
                                            );
                                        }
                                        if( is_array( $data3 ) ) {
                                            foreach( $data3 as $key4 => $data4 ) {
                                                if( $key4 == 'items' ) {
                                                    return array(
                                                        'is_repeater'     => true,
                                                        'repeater_depth'  => 4,
                                                        'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3][$key4],
                                                        'items_ui'        => $data4,
                                                        'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3 . '/' . $key4,
                                                    );
                                                }
                                                if( is_array( $data4 ) ) {
                                                    foreach( $data4 as $key5 => $data5 ) {
                                                        if( $key5 == 'items' ) {
                                                            return array(
                                                                'is_repeater'     => true,
                                                                'repeater_depth'  => 5,
                                                                'items'           => $schema['attributes']['schema']['properties'][$key][$key2][$key3][$key4][$key5],
                                                                'items_ui'        => $data5,
                                                                'path_to_items'   => 'attributes/schema/properties/' . $key . '/' . $key2 . '/' . $key3 . '/' . $key4 . '/' . $key5,
                                                            );
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

            return array(
                'is_repeater'     => false,
                'repeater_depth'  => 0,
                'items'           => array(),
                'items_ui'        => array()
            );
        }

        public static function wicket_schema_get_definition( $schema, $term, $follow_refs = false ) {
            if( isset( $schema['attributes'] ) ) {
                if( isset( $schema['attributes']['schema'] ) ) {
                    if( isset( $schema['attributes']['schema']['definitions'] ) ) {
                        if( isset( $schema['attributes']['schema']['definitions'][$term] ) ) {
                            $the_term = $schema['attributes']['schema']['definitions'][$term];
                            if( !$follow_refs ) {
                                return $the_term ;
                            } else {
                                if( isset( $the_term['properties'] ) ) {
                                    foreach( $the_term['properties'] as $sub_term_key => $sub_term_val  ) {
                                        if( is_array( $sub_term_val ) ) {
                                            foreach( $sub_term_val as $sub_sub_term_key => $sub_sub_term_val ) {
                                                if( $sub_sub_term_key == '$ref' ) {
                                                    // Get the next needed definition
                                                    $needed_def = self::get_end_of_string_by( $sub_sub_term_val, '/' ); 
                                                    $definition = self::wicket_schema_get_definition( $schema, $needed_def, true );
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

        public static function expand_field_object( $schema, $property_name, $property_data ) {
            if( isset( $property_data['oneOf'] ) ) {
                $i = 0;
                foreach( $property_data['oneOf'] as $one_of ) {
                    foreach( $one_of as $one_of_key => $one_of_val ) {
                        if( $one_of_key == '$ref' ) {
                            $needed_def = self::get_end_of_string_by( $one_of_val, '/' );
                            $definition = self::wicket_schema_get_definition( $schema, $needed_def, true );
                            $property_data['oneOf'][$needed_def] = $definition;
                        }
                    }
                    $i++;
                }
            }
            // TODO: Handle potential other field object cases that use a different structure than 'oneOf'

            return $property_data;
        }

        public static function get_end_of_string_by( $string, $delimiter ) {
            $array = explode( $delimiter, $string );
            return $array[ count( $array ) - 1 ];
        }

        // Credit: https://stackoverflow.com/a/263621
        public static function array_depth($array) {
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


    }
    new Wicket_Gf_Main();
}

/**
 * The generally-available Wicket Gravity Forms functions
 */

if( !function_exists( 'wicket_gf_write_log' ) ) {
    function wicket_gf_write_log( $log, $print_to_page = false ) {
        if( $print_to_page ) {
            print_r("<pre>");
            print_r($log);
            print_r("</pre>");
        } else {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}

if( !function_exists( 'wicket_gf_get_form_id_by_slug' ) ) {
    function wicket_gf_get_form_id_by_slug( $slug ) {
        $current_mappings = get_option('wicket_gf_slug_mapping');
        if ( empty( $current_mappings ) ) {
            return false;
        } else {
            $current_mappings = json_decode( $current_mappings, true );

            if( isset( $current_mappings[$slug] ) ) {
                return $current_mappings[$slug];
            } else {
                return false;
            }
        }
    }
}

if( !function_exists( 'wicket_get_gf_mapping_addon' ) ) {
    function wicket_get_gf_mapping_addon() {
        return GFWicketMappingAddOn::get_instance();
    }
}