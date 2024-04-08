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
                if( isset( $_GET['subview'] ) ) {
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
            wicket_write_log($_POST);

            $to_return = array();

            // Get all Additinoal Info Schemas
            $all_schemas = wicket_get_schemas();
            //wicket_write_log($all_schemas, true);

            // Return their keys for initial POC
            foreach( $all_schemas['data'] as $schema ) {
                // Ensure needed attributes are present before adding to array
                if( isset( $schema['id'] ) && isset( $schema['attributes'] ) ) {
                    if( isset( $schema['attributes']['key'] ) ) {

                        $is_repeater = false;
                        $repeater_depth_mode = 0; // 0 for not found, 1 for atts->schema->items, 2 for atts->schema->props->entries->items

                        // Check if this schema is a repeater
                        if( isset( $schema['attributes']['schema'] ) ) {
                            if( isset( $schema['attributes']['schema']['items'] ) ) {
                                $is_repeater = true;
                                $repeater_depth_mode = 1;
                            }
                            if( isset( $schema['attributes']['schema']['properties'] ) ) {
                                if( isset( $schema['attributes']['schema']['properties']['entries'] ) ) {
                                    if( isset( $schema['attributes']['schema']['properties']['entries']['items'] ) ) {
                                        $is_repeater = true;
                                        $repeater_depth_mode = 2;
                                    }
                                }
                            }
                        }

                        if( !$is_repeater ) {
                            $child_fields = array();
                            if( isset( $schema['attributes']['schema'] ) ) {
                                if( isset( $schema['attributes']['schema']['properties'] ) ) {
                                    foreach( $schema['attributes']['schema']['properties'] as $property_name => $property_data ) {
                                        // TODO: Add field required status
                                        // TODO: Add friendly name
                                        $child_fields[] = [
                                            'name'           => $property_name,
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
                            if( isset( $schema['attributes']['schema'] ) ) {
                                if( $repeater_depth_mode == 1 ) {
                                    if( isset( $schema['attributes']['schema']['items']['properties'] ) ) {
                                        foreach( $schema['attributes']['schema']['items']['properties'] as $property_name => $property_data ) {
                                            $repeater_fields[] = [
                                                'name'           => $property_name,
                                                'type'           => $property_data['type'] ?? '',
                                                'default'        => $property_data['default'] ?? '',
                                                'maximum'        => $property_data['maximum'] ?? '',
                                                'minimum'        => $property_data['minimum'] ?? '',
                                                'enum'           => $property_data['enum'] ?? array(),
                                                'path_to_field'  => 'attributes/schema/items/properties',
                                            ];
                                        }
                                    }
                                } else if( $repeater_depth_mode == 2 ) {
                                    if( isset( $schema['attributes']['schema']['properties']['entries']['items']['properties'] ) ) {
                                        foreach( $schema['attributes']['schema']['properties']['entries']['items']['properties'] as $property_name => $property_data ) {
                                            $repeater_fields[] = [
                                                'name'           => $property_name,
                                                'type'           => $property_data['type'] ?? '',
                                                'default'        => $property_data['default'] ?? '',
                                                'maximum'        => $property_data['maximum'] ?? '',
                                                'minimum'        => $property_data['minimum'] ?? '',
                                                'enum'           => $property_data['enum'] ?? array(),
                                                'path_to_field'  => 'attributes/schema/properties/entries/items/properties',
                                            ];
                                        }
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

            wicket_write_log($to_return);

            update_option( 'wicket_gf_member_fields', $to_return );
            wp_send_json_success( $to_return );
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