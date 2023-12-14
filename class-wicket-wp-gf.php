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
            add_action( 'plugins_loaded', [$this, 'conditionally_include_pa_object'] );

            // Add Options Page for plugin
            add_action('admin_menu', array($this,'register_options_page'), 20 );
            add_action('admin_init', array($this,'register_settings') );

            // Add settings link to plugins page listing
            $plugin = plugin_basename(__FILE__);
            add_filter("plugin_action_links_$plugin", array($this, 'add_settings_link') );

            // Hook for shortcode
            add_shortcode('wicket_gravityform', array($this,'shortcode')); 
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

        // Settings link on plugin page
        public static function add_settings_link($links)
        {
            $settings_link = '<a href="options-general.php?page=wicket_gf">' . __('Settings') . '</a>';
            array_push($links, $settings_link);
            return $links;
        }

        // Register Settings For a Plugin so they are grouped together
        public static function register_settings()
        {
            add_option('wicket_gf_slug_mapping', '');
            register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', null);
        }

        // Create an options page
        public static function register_options_page()
        {
            //add_options_page('Wicket Gravity Forms Settings', 'Wicket Gravity Forms Settings', 'manage_options', 'wicket_gf', array('Wicket_Gf_Main','options_page'));
            add_submenu_page( 'gf_edit_forms', __('Wicket Gravity Forms Settings', 'wicket-gf'), __('Wicket Settings', 'wicket-gf'), 'manage_options', 'wicket_gf', array('Wicket_Gf_Main','options_page') );
        }

        // Display Settings on Options Page
        public static function options_page()
        { ?>
            <div>
                <?php // TODO: Move this to conditional admin enqueues if not already present in admin ?>
                <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
                <script src="https://cdn.tailwindcss.com"></script>
                <script>
                    tailwind.config = {
                        prefix: 'wgf-',
                        important: true,
                    }
                </script>

                <h2 class="wgf-text-2xl wgf-font-bold wgf-mb-2"><?php _e('Wicket Gravity Forms', 'wicket-gf'); ?></h2>

                <h3 class="wgf-text-xl wgf-font-semibold wgf-mb-2"><?php _e('Form Slug ID Mapping', 'wicket-gf'); ?></h3>

                <p class="wgf-mb-2"><?php _e('The mappings below tell the rest of the site which form slugs correspond to which Gravity 
                Form IDs, allowing you to import and update forms easily by simply changing the ID here.', 'wicket-gf'); ?>

                <?php 
                $current_mappings = get_option('wicket_gf_slug_mapping');
                if ( empty( $current_mappings ) ) {
                    $current_mappings = [ 'example-form-slug' => '0' ];
                } else {
                    $current_mappings = json_decode( $current_mappings, true );
                }
                //wicket_gf_write_log($current_mappings, true); 
                ?>

                <div x-data='mappingUi'>
                    <pre x-effect="console.log(mappings)"></pre>
                    <template x-for="(mapping, index) in mappings">
                        <div class="wicket-gf-mapping-row wgf-flex wgf-mb-1">
                            <input class="wicket-gf-mapping-row-key wgf-w-50 wgf-mr-2" type="text" x-effect="$el.value = index" x-on:blur="updateMappings" placeholder="<?php _e('Slug', 'wicket-gf');?>" /> 
                            <input class="wicket-gf-mapping-row-val wgf-w-50 wgf-mr-2" type="text" x-effect="$el.value = mapping" x-on:blur="updateMappings" placeholder="<?php _e('Form ID', 'wicket-gf');?>" />
                            <button 
                                class="button wgf-mr-1"
                                x-on:click="mappings = {...mappings, '':''}"
                            >+</button>
                            <button 
                                class="button warning"
                                x-on:click="removeRow(index)"
                            >-</button>
                        </div>
                    </template>
                </div >

                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('mappingUi', () => ({
                            mappings: <?php echo json_encode($current_mappings); ?>,
                        
                            updateMappings() {
                                let keys = document.querySelectorAll(".wicket-gf-mapping-row .wicket-gf-mapping-row-key");
                                let vals = document.querySelectorAll(".wicket-gf-mapping-row .wicket-gf-mapping-row-val");
                                let newMappings = {};
                                for (i = 0; i < keys.length; ++i) {
                                    let keyInput = keys[i];
                                    let valInput = vals[i];

                                    let newKey = keyInput.value;
                                    newKey = newKey.replace(/[^-,^a-zA-Z0-9 ]/g, ''); // Remove special characters
                                    newKey = newKey.replace(/\s+/g, '-').toLowerCase();
                                    
                                    newMappings[newKey] = valInput.value;
                                }
                                this.mappings = newMappings;
                                this.updateHiddenFormField();
                            },

                            removeRow(index) {
                                if( Object.keys(this.mappings).length > 1 ) {
                                    delete this.mappings[index];
                                    this.updateHiddenFormField();
                                }
                            },

                            updateHiddenFormField() {
                                let hiddenField = document.querySelector('#wicket_gf_slug_mapping');
                                hiddenField.value = JSON.stringify(this.mappings);
                            },
                        }))
                    })
                </script>
                

                <form method="post" action="options.php"> 
                    <?php settings_fields('wicket_gf_options_group'); ?>
                    
                    <input hidden type="text" id="wicket_gf_slug_mapping" name="wicket_gf_slug_mapping" value="<?php echo get_option('wicket_gf_slug_mapping'); ?>" style="width:40%;" />

                    <?php submit_button(); ?>
                </form> 
            </div>
        <?php
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
