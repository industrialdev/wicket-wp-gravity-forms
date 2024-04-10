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
		 * Constructor
		 */
		public function __construct() {
            add_action( 'plugins_loaded', array($this, 'conditionally_include_pa_object') );

            // Hook for shortcode
            add_shortcode('wicket_gravityform', array($this,'shortcode')); 

            // Bootstrap the GF Addon for field mapping
            add_action( 'gform_loaded', array( $this, 'gf_mapping_addon_load' ), 5 );

            // Enqueue scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

            // Register Rest Routes
          	add_action('rest_api_init', array($this, 'register_rest_routes') );

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
                'name_en'       => 'UUID Options',
                'name_fr'       => 'Options UUID',
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
            // ------- Additional Info --------
            // --------------------------------

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

                                    $repeater_fields[] = [
                                        'name'           => $property_name,
                                        'label_en'       => $label_en,
                                        'label_fr'       => $label_fr,
                                        'type'           => $property_data['type'] ?? '',
                                        'default'        => $property_data['default'] ?? '',
                                        'maximum'        => $property_data['maximum'] ?? '',
                                        'minimum'        => $property_data['minimum'] ?? '',
                                        'enum'           => $property_data['enum'] ?? array(),
                                        'path_to_field'  => $items_array['path_to_items'] . '/properties',
                                    ];
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
            // --------- Profile Data ---------
            // --------------------------------

            // --------------------------------
            // ----------- Org Data -----------
            // --------------------------------

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
            wp_send_json_success( $to_return );
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
                        if( $key == 'items' ) {
                            if( !isset( $schema['attributes']['schema']['properties'] ) ) {
                                $items = $schema['attributes']['schema']['items']['properties'][$key];
                                // Note: for some reason one field is giving "Undefined array key "items"" even
                                // though items is indeed in the array. Maybe misconfigured in the MDP or has a space somewhere
                                if( empty($items) ) {
                                    wicket_write_log("No items for some reason:");
                                    wicket_write_log($schema);
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