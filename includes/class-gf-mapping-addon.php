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
		// 	wicket_write_log( $fields, true );
		
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
		//wicket_write_log( $feed, true );
		$feedName  = $feed['meta']['feedName'];

		// $metaData = $this->get_field_map_fields( $feed, 'mappedFields' ); // Method for the other field map type
		$metaData = $this->get_dynamic_field_map_fields( $feed, 'wicketFieldMaps' );
		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ( $metaData as $name => $field_id ) {

			// Get the field value for the specified field id
			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}
		wicket_write_log( "The mappings:" );
		wicket_write_log( $merge_vars );

		// TODO: watch out for fields that need to be processed together to sync properly, per note from Terry

		// Loop through the field mappings to organize them by same type (e.g. profile) and same schema to batch API calls
		$grouped_updates = array();
		foreach( $merge_vars as $member_field => $incoming_value ) {
			$path_to_save = explode( '/', $member_field );

			$grouped_updates[ $path_to_save[0] ][] = array(
				'field'         => $member_field,
				'value'         => $incoming_value,
			);
		}

		wicket_write_log( 'The organized array:' );
		wicket_write_log( $grouped_updates );

		// Loop through the grouped array to make the API calls
		foreach( $grouped_updates as $group_type => $items ) {
			self::member_api_update( $group_type, $items );
		}

	}

	private function member_api_update( $schema, $fields ) {
		$count_dashes = explode( '-', $schema );
		if( count( $count_dashes ) >= 4 ) {
			// This is a schema with a save path

			// Grab the current schema data for either current UUID or mapped UUID

			// Loop through the pending changes
			foreach( $fields as $field ) {
				$path_to_save_to = explode( '/', $field['field'] );
				wicket_write_log('Schema path to save to:');
				wicket_write_log($path_to_save_to);
			}

			// Apply those changes to the current info array

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
							wicket_write_log('Field ID: ' . $actual_field_id);

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
		foreach( $wicket_member_data_fields as $schema ) {
			$choices = array();
			foreach( $schema['child_fields'] as $child_field ) {
				// The value is set in a way where it can be split by the / characters when we process the feed, and 
				// know exactly where to save that data in the Wicket Member schemas
				$value = '';
				if( isset( $schema['schema_id'] ) && !empty( $schema['schema_id'] )) {
					$value .= $schema['schema_id'] . '/';
				}
				if( isset( $child_field['path_to_field'] ) && !empty( $child_field['path_to_field'] ) ) {
					$value .= $child_field['path_to_field'] . '/';
				}
				$value .= $child_field['name'];
				$choices[] = array(
					'label'    => $child_field['label_en'],
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

	public static function addon_custom_ui () {

		if( isset( $_GET['subview'] ) && isset( $_GET['fid'] ) ):
		?>                
		
		<div class="gform-settings__wrapper custom">
			<div class="">
				<a 
					aria-label="<?php _e( 'Re-Sync Wicket Member Fields', 'wicket-gf' ); ?>" 
					href="javascript:void(0)" 
					class="preview-form gform-button gform-button--white" 
					target="_self" 
					rel="noopener"
					id="wicket-gf-addon-resync-fields-button"
				><?php _e( 'Re-Sync Wicket Member Fields', 'wicket-gf' ); ?></a>
			</div>
		</div>
		
		<?
		endif;
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
