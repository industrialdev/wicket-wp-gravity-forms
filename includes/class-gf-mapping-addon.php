<?php

GFForms::include_feed_addon_framework();

// GF Addon Framework Documentation: https://docs.gravityforms.com/category/developers/php-api/add-on-framework/

class GFWicketMappingAddOn extends GFFeedAddOn {

	public $_async_feed_processing = false; // Makes this an async feed so the form can submit while this processes in the background

	protected $_version = WICKET_WP_GF_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'wicketmap';
	protected $_path = 'wicketmap/wicketmap.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms to Wicket Member Mapping';
	protected $_short_title = 'Wicket Member';

	private static $_instance = null;

	// Togglable debug categories
	private $_debug_schema_changes_before_and_after = false;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFWicketMappingAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFWicketMappingAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();

		// Uncomment to see field map choice details for the addon screen you're viewing
		// add_filter( 'gform_field_map_choices', function( $fields, $form_id, $field_type, $exclude_field_types ) {
		// 	wicket_gf_write_log( $fields, true );
		
		// 	return $fields;
		// }, 10, 4 );
	}

	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed( $feed, $entry, $form ) {

		// NOTE: Per Terry, watch out for these scenarios that could cause save-to-MDP issues:
				/* Any field that is marked required (this can be at any level of nesting)
				 * Repeater entries
				 * oneOf fields - these are how we handle conditionals which means based one value in the section, a different set of fields could be provided.
				 * Fields expecting a certain format or validation. */


		//wicket_gf_write_log( $feed, true );
		$feedName  = $feed['meta']['feedName'];
		//wicket_gf_write_log( get_option( 'wicket_gf_member_fields') );

		// $metaData = $this->get_field_map_fields( $feed, 'mappedFields' ); // Method for the other field map type
		$metaData = $this->get_dynamic_field_map_fields( $feed, 'wicketFieldMaps' );
		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ( $metaData as $name => $field_id ) {

			// Get the field value for the specified field id
			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}
		wicket_gf_write_log( "The mappings:" );
		wicket_gf_write_log( $merge_vars );

		// Loop through the field mappings to organize them by same type (e.g. profile) and same schema to batch API calls
		$grouped_updates = array();
		foreach( $merge_vars as $member_field => $incoming_value ) {
			$path_to_save = explode( ':', $member_field );

			$grouped_updates[ $path_to_save[0] ][] = array(
				'field'         => $member_field,
				'value'         => $incoming_value,
			);
		}

		if( $this->_debug_schema_changes_before_and_after ) {
			wicket_gf_write_log( 'The organized array:' );
			wicket_gf_write_log( $grouped_updates );
		}
		

		// Loop through the grouped array to make the API calls
		foreach( $grouped_updates as $group_type => $items ) {
			self::member_api_update( $group_type, $items );
		}

	}

	private function member_api_update( $schema, $fields, $person_uuid = '', $org_uuid = '' ) {
		$wicket_client = wicket_api_client();
    
		if( !empty( $person_uuid ) ) {
			$wicket_person = wicket_get_person_by_id($person_uuid);
		} else {
			$wicket_person = wicket_current_person();
		}

		$count_dashes = explode( '-', $schema );
		if( count( $count_dashes ) >= 4 ) {
			// This is a schema with a save path

			// Grab the current schema data for either current UUID or mapped UUID
			$current_schema_values = self::get_ai_field_from_data_fields($wicket_person->data_fields, $schema);

			if( $this->_debug_schema_changes_before_and_after ) {
				wicket_gf_write_log('Current schema values:');
				wicket_gf_write_log($current_schema_values);
			}

			// Apply those changes to the current info array
			$new_schema_values = self::apply_changes_to_schema( $current_schema_values, $fields );

			// Validate the new schema values to be written
			$new_schema_values_validation = self::validate_schema_changes( $schema, $new_schema_values );
		
			// Call the API

		} else {
			// This is a field for a specific data type (e.g. profile)
			switch ($schema) {
				case 'profile':
						// Grab the current profile info for either current UUID or mapped UUID

						// Loop through the pending changes
						foreach( $fields as $field ) {
							// Grab the actual field ID
							$actual_field_id_array = explode( '/', $field['field'] );
							$actual_field_id = $actual_field_id_array[ count( $actual_field_id_array ) - 1 ];

							// Apply those changes to the current info array

							// Call the API
						}
					
						
						
						break;
				case 'more':
						//
						break;
			}
		}
	}

	/**
	 * Applies a batch of field changes to a schema values array
	 * 
	 * @param Array   $current_schema_values Array likely pulled via get_ai_field_from_data_fields()
	 * @param Array   $changes_to_apply An array of field changes to apply
	 * @param integer $repeater_index_to_update (optional) If provided, tells the function to update an existing
	 * repeater entry at that index rather than creating a new repeater entry.
	 * 
	 * @return Array Returns an updated version of the originally supplied $current_schema_values.
	 */
	private function apply_changes_to_schema( $current_schema_values, $changes_to_apply, $repeater_index_to_update = null ) {
		if( $this->_debug_schema_changes_before_and_after ) {
			wicket_gf_write_log( 'Current schema values:' );
			wicket_gf_write_log($current_schema_values);
			wicket_gf_write_log('Changes to apply:');
			wicket_gf_write_log($changes_to_apply);
		}

		$new_schema_entry = empty( $current_schema_values );

		// TODO: Handle when the current_schema_values are empty (i.e. nothing's been saved in the MDP for that schema yet)

		$expanded_changes_to_apply = self::expand_form_mappings( $changes_to_apply );
		if( $this->_debug_schema_changes_before_and_after ) {
			wicket_gf_write_log("Expanded changes to apply:");
			wicket_gf_write_log($expanded_changes_to_apply);
		}
		
		// Loop through the pending changes
		foreach( $expanded_changes_to_apply as $field ) {
			$path_to_save_to = explode( '/', $field['path_to_field'] );

			$temp = &$current_schema_values; // Create reference
			$new_repeater_entry_created = false;
			$new_repeater_index = 0;
			$current_key = '';
			foreach($path_to_save_to as $step) {
				$count_dashes = explode( '-', $step );
				$step_is_schema_uuid = count( $count_dashes ) >= 4;
				$current_key = $step;

				if( $step == 'properties' ) {
					// Skip any path steps that aren't used to save in data_fields, such as properties
					continue;
				} else if( isset( $temp[$step] ) && !$new_repeater_entry_created && !$new_schema_entry ) {
					$temp = &$temp[$step];
				} else if( $step == 'items' && isset( $temp[ 0 ] ) && !$new_repeater_entry_created  && !$new_schema_entry) {
					$new_repeater_index = count( $temp );
					$temp = &$temp[ $new_repeater_index ];
					$new_repeater_entry_created = true;
				} else if( $new_repeater_entry_created && !$new_schema_entry ) {
					$temp = &$temp[$step];
				} 
				// Handle cases when we're creating a fresh entry for an unused schema
				else if( $new_schema_entry && ( $step_is_schema_uuid || $step == 'attributes' || $step == 'schema' ) ) {
					// Skip values that very likely won't be used to save in data_fields
					continue;
				} else if( $new_schema_entry && $step != 'items' ) {
					$temp = &$temp[$step];
				} else if( $new_schema_entry && $step == 'items' ) {
					$temp = &$temp[ 0 ];
				}
			}
			
			// If we didn't end on the name of this sub-field, create it under the last key found
			if( $current_key != $field['name'] ) {
				$temp[ $field['name'] ] = $field['value'];
			} else {
				$temp = $field['value'];
			}
			unset($temp); // Unlink reference
		}
		if( $this->_debug_schema_changes_before_and_after ) {
			wicket_gf_write_log('Updated schema:');
			wicket_gf_write_log($current_schema_values);
		}

		return $current_schema_values;
	}

	private function expand_form_mappings( $mappings ) {
		$output = array();
		$wicket_member_data_fields = get_option('wicket_gf_member_fields');

		foreach( $mappings as $mapping ) {
			$split_by_schema = explode( ':', $mapping['field'] );
			$schema_name = $split_by_schema[0];
			$field_data = self::follow_trail_down_array( $split_by_schema[1], '/', $wicket_member_data_fields ) ?? array();
			$field_data['value'] = $mapping['value'];
			$output[] = $field_data;
		}

		return $output;
	}

	// Credit: https://stackoverflow.com/a/9628276
	private function follow_trail_down_array( $breadcrumbs, $divider, $array ) {
		$exploded = explode( $divider, $breadcrumbs );

		$temp = &$array;
		foreach($exploded as $key) {
			$temp = &$temp[$key];
		}
		$value = $temp;
		unset($temp);

		return $value;
	}

	private function get_ai_field_from_data_fields($data_fields, $key) {
		foreach( $data_fields as $field ) {
			if( isset( $field['$schema'] ) ) {
				if( str_contains( $field['$schema'], $key ) ) {
					if( isset( $field['value'] ) ) {
						return $field['value'];
					}
				}
			}
		}
	
		// User doesn't have this data field saved yet
		return array();
	}

	private function validate_schema_changes( $schema, $new_schema_values ) {
		// TODO: This function needs to check all fields stored in the db for the provided
		// schema and ensure nothing that is required is going to go unprovided

		wicket_gf_write_log("Validate schema changes called:");
		wicket_gf_write_log($schema);
		wicket_gf_write_log($new_schema_values);

		$wicket_member_data_fields = get_option('wicket_gf_member_fields');
		$schema_fields = array();
		foreach( $wicket_member_data_fields as $schema_type ) {
			if( $schema_type['schema_id'] == $schema ) {
				$schema_fields = $schema_type;
			}
		}
		wicket_gf_write_log('This schema\'s fields:');
		wicket_gf_write_log($schema_fields);
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		return parent::scripts();
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
		return parent::styles();
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Wicket Member Mapping Settings', 'wicket-gf' ),
				'fields' => array(
					array(
									'label'   => esc_html__( 'Feed name', 'wicket-gf' ),
									'type'    => 'text',
									'name'    => 'feedName',
									'tooltip' => esc_html__( 'This is the tooltip', 'wicket-gf' ),
									'class'   => 'small',
					),
					array(
						// Documentation for dynamic_field: https://docs.gravityforms.com/dynamic_field_map-field/
						'name'                => 'wicketFieldMaps',
						'label'               => esc_html__( 'Wicket Fields', 'wicket-gf' ),
						'description' 			  => esc_html__( 'Note that by default changes will be made to the UUID of the currently logged in user, otherwise a person\'s UUID can be mapped to update a person record, or an org UUID can be mapped to update an org record.', 'wicket-gf' ),
						'type'                => 'dynamic_field_map',
						'field_map'           => $this::get_member_key_options(),
						'enable_custom_key'	  => false,
						'tooltip'             => '<h6>' . esc_html__( 'Wicket Fields', 'wicket_plugin' ) . '</h6>' . esc_html__( 'Map your GF fields to Wicket', 'wicket_plugin' ),
					),
					array(
									'name'           => 'condition',
									'label'          => esc_html__( 'Condition', 'wicket-gf' ),
									'type'           => 'feed_condition',
									'checkbox_label' => esc_html__( 'Enable Condition', 'wicket-gf' ),
									'instructions'   => esc_html__( 'Process this simple feed if', 'wicket-gf' ),
					),
				),
			),
		);
	}

	public function get_member_key_options() {
		$wicket_member_data_fields = get_option('wicket_gf_member_fields');
		$fields_to_return = array();
		foreach( $wicket_member_data_fields as $schema_index => $schema ) {
			$choices = array();
			foreach( $schema['child_fields'] as $child_field_index => $child_field ) {
				$value_array = array();

				// When we were building a file path instead of referencing the db array location:
					// The value is set in a way where it can be split by the / characters when we process the feed, and 
					// know exactly where to save that data in the Wicket Member schemas
					// if( isset( $schema['schema_id'] ) && !empty( $schema['schema_id'] )) {
					// 	$value .= $schema['schema_id'] . '/';
					// }
					// if( isset( $child_field['path_to_field'] ) && !empty( $child_field['path_to_field'] ) ) {
					// 	$value .= $child_field['path_to_field'] . '/';
					// }
					// $value .= $child_field['name'];

				// Reference the db array location for later use:
				$value_array[] = $schema_index;
				$value_array[] = 'child_fields';
				$value_array[] = $child_field_index;
				$value         = implode( '/', $value_array ); 

				// Prefix with schema or type so we can easily group later:
				if( isset( $schema['schema_id'] ) && !empty( $schema['schema_id'] )) {
					$value = $schema['schema_id'] . ":" . $value;
				}

				$label = $child_field['label_en'];
				if( isset( $child_field['required'] ) ) {
					if( $child_field['required'] ) {
						$label .= '*';
					}
				}

				$choices[] = array(
					'label'    => $label,
					'value'    => $value,
				);
			}

			$fields_to_return[] = array(
				'label'     => $schema['name_en'],
				'choices'   => $choices,
			);
		}

		return $fields_to_return;
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__( 'Name', 'wicket-gf' ),
		);
	}
	

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$key = rgar( $settings, 'apiKey' );

		return true;
	}

	/**
	 * Set the icon
	 */
	public function get_menu_icon() {
		return file_get_contents( dirname( __FILE__ ) . '/../assets/wicket_icon_black.svg' );
	}

	// # CUSTOM SETTIGNS ON ADDON SETTINGS PAGE -------------------------------------------------------------------------

	public static function addon_custom_ui() {

		$show_debug_info = true; // Change as desired

		if( isset( $_GET['subview'] ) && isset( $_GET['fid'] ) ) {
			
			echo '
					<div class="gform-settings__wrapper custom">
						<div class="">
							<a 
								aria-label="' . __( 'Re-Sync Wicket Member Fields', 'wicket-gf' ) . '"  
								href="javascript:void(0)" 
								class="preview-form gform-button gform-button--white" 
								target="_self" 
								rel="noopener"
								id="wicket-gf-addon-resync-fields-button"
							>'. __( 'Re-Sync Wicket Member Fields', 'wicket-gf' ) . '</a>
						</div>
					</div>
			';

			if($show_debug_info) { 
				echo '
					<pre>
						'. wicket_gf_write_log( get_option('wicket_gf_member_fields'), true ) . '
					</pre>
				';
			}
		}
	}

	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * The feedback callback for the 'mytextbox' setting on the plugin settings page and the 'mytext' setting on the form settings page.
	 *
	 * @param string $value The setting value.
	 *
	 * @return bool
	 */
	public function is_valid_setting( $value ) {
		return strlen( $value ) < 10;
	}

}
