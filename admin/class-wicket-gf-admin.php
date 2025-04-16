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
				register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', ['sanitize_callback' => [__CLASS__, 'sanitize_slug_mapping']] );
				register_setting('wicket_gf_options_group', 'wicket_gf_pagination_sidebar_layout', null);
				register_setting('wicket_gf_options_group', 'wicket_gf_orgss_auto_advance', null);
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

						<p class="wgf-mb-2"><?php _e('This makes it easy to reference forms by their slug in coding using the <code>wicket_gf_get_form_id_by_slug()</code> function.', 'wicket-gf'); ?>

						<?php 
						$current_mappings_json = get_option('wicket_gf_slug_mapping');

						// --- DEBUGGING START ---
						// echo "<pre>Raw get_option('wicket_gf_slug_mapping'):\n";
						// var_dump($current_mappings_json); // Use var_dump for more detail on type/value
						// echo "</pre>";
						// --- DEBUGGING END ---

						$current_mappings = json_decode( $current_mappings_json, true );
						// Ensure we have a valid associative array, default if not
						if ( !is_array($current_mappings) || ( !empty($current_mappings) && array_keys($current_mappings) === range(0, count($current_mappings) - 1) ) ) {
								$current_mappings = [ 'example-form-slug' => '0' ];
						}
						// Ensure keys are properly slugified if loaded from old data
						$sanitized_mappings = [];
						foreach ($current_mappings as $key => $value) {
								$newKey = strtolower(str_replace(' ', '-', $key)); // Replace spaces, lowercase
								$newKey = preg_replace('/[^a-z0-9\-]/', '', $newKey); // Remove invalid chars (allow lowercase letters, numbers, hyphen)
								$sanitized_mappings[$newKey] = $value;
						}
						$current_mappings = $sanitized_mappings;

						// If mappings are empty after loading and sanitizing, add a default empty row
						if (empty($current_mappings)) {
								$current_mappings = ['' => '']; // Use empty key/value for a new row
						}

						// --- DEBUGGING START ---
						// echo "<pre>PHP \$current_mappings (before passing to Alpine):\n";
						// print_r($current_mappings);
						// echo "</pre>";
						// --- DEBUGGING END ---
						?>

						<div x-data='mappingUi(<?php echo json_encode($current_mappings); ?>)'>
							<form id="wicket-gf-settings-form" method="post" action="options.php" class="wgf-mt-4">
								<?php settings_fields('wicket_gf_options_group'); ?>

								<template x-for="(id, slug, index) in mappings" :key="slug">
									<div class="wicket-gf-mapping-row wgf-flex wgf-mb-1">
										<input 
											class="wicket-gf-mapping-row-key wgf-w-50 wgf-mr-2" 
											type="text" 
											:value="slug" 
											x-on:input="updateSlug($event, slug)" 
											placeholder="<?php _e('Slug', 'wicket-gf');?>" 
										/> 
										<input 
											class="wicket-gf-mapping-row-val wgf-w-50 wgf-mr-2" 
											type="text" 
											:value="id" 
											x-on:input="updateId($event, slug)" 
											placeholder="<?php _e('Form ID', 'wicket-gf');?>" 
										/>
										<button 
											type="button"
											class="button wgf-mr-1"
											x-on:click="addRow()"
										>+</button>
										<button 
											class="button warning"
											x-on:click="removeRow(slug)"
											:disabled="Object.keys(mappings).length <= 1"
										>-</button>
									</div>
								</template>

								<input hidden type="text" id="wicket_gf_slug_mapping" name="wicket_gf_slug_mapping" value="<?php echo esc_attr(json_encode($current_mappings)); ?>" />

								<script>
									function mappingUi(initialMappings) {
										// --- DEBUGGING START ---
										// console.log('Alpine: Initial Mappings:', initialMappings);
										// --- DEBUGGING END ---
										return {
												mappings: initialMappings,
												isValid: true,
												validationMessage: '',
										
												updateSlug(event, oldSlug) {
														let newSlug = event.target.value;
														// Basic sanitization - remove special chars, replace spaces with dashes, lowercase
														newSlug = newSlug.replace(/[^-,^a-zA-Z0-9 ]/g, ''); 
														newSlug = newSlug.replace(/\s+/g, '-').toLowerCase();
														event.target.value = newSlug; // Update input field with sanitized value

														if (newSlug === oldSlug || newSlug === '') return; // No change or empty slug

														// Check if the new slug already exists
														if (this.mappings.hasOwnProperty(newSlug)) {
																alert('<?php _e('Slug already exists. Please choose a unique slug.', 'wicket-gf'); ?>');
																event.target.value = oldSlug; // Revert input field
																return;
														}

														const newMappings = { ...this.mappings };
														const id = newMappings[oldSlug];
														delete newMappings[oldSlug];
														newMappings[newSlug] = id;
														this.mappings = newMappings;
														this.updateHiddenFormField();
														this.validateMappings();
												},

												updateId(event, slug) {
														const newId = event.target.value;
														if (this.mappings[slug] !== newId) {
																this.mappings[slug] = newId;
																this.updateHiddenFormField();
																this.validateMappings();
														}
												},

												addRow() {
														let newSlugBase = 'new-slug';
														let newSlug = newSlugBase;
														let counter = 1;
														// Ensure the new slug is unique
														while (this.mappings.hasOwnProperty(newSlug)) {
																newSlug = `${newSlugBase}-${counter}`;
																counter++;
														}
														// Add new entry immutably
														this.mappings = {...this.mappings, [newSlug]: ''};
														this.updateHiddenFormField(); // Update hidden field immediately
														this.validateMappings();
												},

												removeRow(slugToRemove) {
														if (Object.keys(this.mappings).length > 1) {
																const newMappings = { ...this.mappings };
																delete newMappings[slugToRemove];
																this.mappings = newMappings;
																this.updateHiddenFormField();
																this.validateMappings();
														}
												},

												updateHiddenFormField() {
														// --- DEBUGGING START ---
														// console.log('Alpine: Updating hidden field with mappings:', JSON.parse(JSON.stringify(this.mappings))); // Clone for logging
														// --- DEBUGGING END ---
														let hiddenField = document.querySelector('#wicket_gf_slug_mapping');
														if (hiddenField) { // Check if field exists
																hiddenField.value = JSON.stringify(this.mappings);
														} else {
																// console.error('Hidden field #wicket_gf_slug_mapping not found.');
														}
												},

												validateMappings() {
													this.isValid = true; // Assume valid initially
													// this.validationMessage = ''; // Message is static now
													for (const slug in this.mappings) {
														const id = this.mappings[slug];
														const slugIsEmpty = (slug === '' || slug === null);
														const idIsEmpty = (id === '' || id === null || id === '0'); // Treat '0' as empty for validation

														if (!slugIsEmpty && idIsEmpty) {
															this.isValid = false;
															// this.validationMessage = '<?php _e("A slug is defined but the Form ID is missing or zero.", "wicket-gf"); ?>';
															break;
														}
														if (slugIsEmpty && !idIsEmpty) {
															this.isValid = false;
															// Message is now static below
															break;
														}
													}

													// Disable/enable the submit button
													let submitButton = document.querySelector('#wicket-gf-settings-form input[type="submit"]');
													if (submitButton) {
															submitButton.disabled = !this.isValid;
													}
												},

												init() {
														// Ensure the hidden field is populated on initial load
														this.$nextTick(() => {
															this.updateHiddenFormField();
															this.validateMappings(); // Validate on load
														}); 
												}
										}
								}
								document.addEventListener('alpine:init', () => {
										Alpine.data('mappingUi', mappingUi);
								});
								</script>

								<p class="wgf-text-red-600 wgf-mb-2" x-show="!isValid"><?php _e('Please ensure no rows have empty fields.', 'wicket-gf'); ?></p>

								<h3 class="wgf-text-xl wgf-font-semibold wgf-mb-2"><?php _e('General Gravity Forms Settings', 'wicket-gf'); ?></h3>
						
								<div class="wicket_pagination_settings" style="">
									<input 
										type="checkbox" 
										name="wicket_gf_pagination_sidebar_layout" 
										id="wicket_gf_pagination_sidebar_layout"
										<?php checked( get_option('wicket_gf_pagination_sidebar_layout'), 'on' ); ?>
									>
									<label for="wicket_gf_pagination_sidebar_layout" class="inline">Use Sidebar Pagination Layout</label>					
								</div>

								<div class="wicket_orgss_auto_advance" style="">
									<input 
										type="checkbox" 
										name="wicket_gf_orgss_auto_advance" 
										id="wicket_gf_orgss_auto_advance"
										<?php checked( get_option('wicket_gf_orgss_auto_advance', true), 'on' ); ?>
									>
									<label for="wicket_gf_orgss_auto_advance" class="inline">Auto-advance to next page on org selection in the Org Search & Select</label>					
								</div>
								
								<?php submit_button(); ?>
							</form> 
						</div>
				</div>
		<?php
		}

		/**
		 * Sanitize the slug mapping input before saving.
		 *
		 * @param string $input Raw JSON string from the form.
		 * @return string Sanitized JSON string to be saved.
		 */
		public static function sanitize_slug_mapping( $input ) {
				$decoded = json_decode( stripslashes( $input ), true ); // Use stripslashes as WP adds them

				// -- DEBUGGING --
				// error_log('Raw input to sanitize_slug_mapping: ' . $input);
				// error_log('Stripslashed input: ' . stripslashes($input));
				// error_log('Decoded input: ' . print_r($decoded, true));
				// -- /DEBUGGING --

				if ( $decoded === null && json_last_error() !== JSON_ERROR_NONE ) {
						// If JSON decoding failed
						// error_log('JSON decode failed in sanitize_slug_mapping. Error: ' . json_last_error_msg() . '. Resetting.');
						add_settings_error(
							'wicket_gf_slug_mapping',
							'invalid_json',
							__('Failed to save mappings due to invalid data format.', 'wicket-gf'),
							'error'
						);
						return get_option('wicket_gf_slug_mapping'); // Return old value
				}

				// Handle case where input was valid JSON but not an array (e.g., empty string submitted)
				if (!is_array($decoded)) {
						$decoded = [];
				}

				$sanitized_mappings = [];
				$is_data_valid = true;
				$validation_error_message = '';

				foreach ($decoded as $key => $value) {
						// Sanitize key (slug)
						$newKey = strtolower(str_replace(' ', '-', $key)); // Replace spaces, lowercase
						$newKey = preg_replace('/[^a-z0-9\-]/', '', $newKey); // Remove invalid chars (allow lowercase letters, numbers, hyphen)

						// Sanitize value (form ID - ensure it's numeric or empty)
						$newValue = preg_replace( '/[^0-9]/', '', $value ); // Remove non-numeric characters

						// Validation Check
						$slugIsEmpty = empty($newKey);
						$idIsEmpty = empty($newValue) || $newValue === '0'; // Treat 0 as empty for this validation

						if (!$slugIsEmpty && $idIsEmpty) {
							$is_data_valid = false;
							$validation_error_message = __('A slug was defined but the Form ID was missing or zero.', 'wicket-gf');
							break;
						}
						if ($slugIsEmpty && !$idIsEmpty) {
							$is_data_valid = false;
							$validation_error_message = __('A Form ID was defined but the slug was missing.', 'wicket-gf');
							break;
						}

						// Allow empty keys for now, maybe add validation later if needed
						// if( !empty($newKey) ) { 
								$sanitized_mappings[$newKey] = $newValue;
						// }
				}

				// If validation failed, return the old value and show an error
				if (!$is_data_valid) {
					// error_log('Wicket GF: Slug mapping validation failed: ' . $validation_error_message . ' Input: ' . $input);
					add_settings_error(
						'wicket_gf_slug_mapping',
						'invalid_mapping',
						__('Failed to save mappings: ', 'wicket-gf') . $validation_error_message,
						'error'
					);
					return get_option('wicket_gf_slug_mapping'); // Return old value
				}

				// error_log('Sanitized mappings to be saved: ' . print_r($sanitized_mappings, true));

				return json_encode( $sanitized_mappings );
		}
	}
}