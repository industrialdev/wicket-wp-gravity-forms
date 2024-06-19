<?php
/**
 * Admin file for Wicket Gravity Forms
 *
 * @package  wicket-gravity-forms
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Wicket_Gf_Admin' ) ) {
	/**
	 * Admin class of module
	 */
	class Wicket_Gf_Admin {
    /**
		 * Constructor of class
		 */
		public function __construct() {}

		// Settings link on plugin page
		public static function add_settings_link($links)
		{
				$settings_link = '<a href="admin.php?page=wicket_gf">' . __('Settings') . '</a>';
				array_push($links, $settings_link);
				return $links;
		}

		// Register Settings For a Plugin so they are grouped together
		public static function register_settings()
		{
				add_option('wicket_gf_slug_mapping', '');
				register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', null);
				register_setting('wicket_gf_options_group', 'wicket_gf_pagination_sidebar_layout', null);
		}

		// Create an options page
		public static function register_options_page()
		{
				//add_options_page('Wicket Gravity Forms Settings', 'Wicket Gravity Forms Settings', 'manage_options', 'wicket_gf', array('Wicket_Gf_Main','options_page'));
				add_submenu_page( 'gf_edit_forms', __('Wicket Gravity Forms Settings', 'wicket-gf'), __('Wicket Settings', 'wicket-gf'), 'manage_options', 'wicket_gf', array('Wicket_Gf_Admin','options_page') );
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

						<h2 class="wgf-text-2xl wgf-font-bold wgf-mb-4"><?php _e('Wicket Gravity Forms', 'wicket-gf'); ?></h2>

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
						

						<form method="post" action="options.php" class="wgf-mt-4"> 
								<?php settings_fields('wicket_gf_options_group'); ?>
								
								<input hidden type="text" id="wicket_gf_slug_mapping" name="wicket_gf_slug_mapping" value="<?php echo get_option('wicket_gf_slug_mapping'); ?>" />

								<h3 class="wgf-text-xl wgf-font-semibold wgf-mb-2"><?php _e('General Gravity Forms Settings', 'wicket-gf'); ?></h3>
						
								<div class="wicket_pagination_settings" style="">
									<input 
										type="checkbox" 
										name="wicket_gf_pagination_sidebar_layout" 
										id="wicket_gf_pagination_sidebar_layout"
										<?php if(get_option('wicket_gf_pagination_sidebar_layout')){echo 'checked';} ?>
									>
									<label for="wicket_gf_pagination_sidebar_layout" class="inline">Use Sidebar Pagination Layout</label>					
								</div>
								
								<?php submit_button(); ?>
						</form> 
				</div>
		<?php
		}		
  }
}