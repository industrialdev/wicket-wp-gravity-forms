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

            require_once( plugin_dir_path( __FILE__ ) . 'admin/class-wicket-gf-admin.php' );

            // Add Options Page for plugin
            add_action('admin_menu', array('Wicket_Gf_Admin','register_options_page'), 20 );
            add_action('admin_init', array('Wicket_Gf_Admin','register_settings') );

            // Add settings link to plugins page listing
            $plugin = plugin_basename(__FILE__);
            add_filter("plugin_action_links_$plugin", array('Wicket_Gf_Admin', 'add_settings_link') );
        }

        public static function gf_mapping_addon_load() {
            if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
                return;
            }
    
            require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-mapping-addon.php' );
    
            GFAddOn::register( 'GFSimpleAddOn' );
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
        return GFSimpleAddOn::get_instance();
    }
}