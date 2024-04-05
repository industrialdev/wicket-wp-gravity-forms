<?php

GFForms::include_feed_addon_framework();

// GF Addon Framework Documentation: https://docs.gravityforms.com/category/developers/php-api/add-on-framework/

class GFWicketMappingAddOn extends GFFeedAddOn {

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
		//wicket_gf_write_log( $feed, true );
		$feedName  = $feed['meta']['feedName'];

		// $metaData = $this->get_field_map_fields( $feed, 'mappedFields' ); // Method for the other field map type
		$metaData = $this->get_dynamic_field_map_fields( $feed, 'wicketFieldMaps' );
		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ( $metaData as $name => $field_id ) {

			// Get the field value for the specified field id
			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}
		wicket_gf_write_log( "The mappings:", true );
		wicket_gf_write_log( $merge_vars, true );

	}

	/**
	 * Custom format the phone type field values before they are returned by $this->get_field_value().
	 *
	 * @param array $entry The Entry currently being processed.
	 * @param string $field_id The ID of the Field currently being processed.
	 * @param GF_Field_Phone $field The Field currently being processed.
	 *
	 * @return string
	 */
	public function get_phone_field_value( $entry, $field_id, $field ) {

		// Get the field value from the Entry Object.
		$field_value = rgar( $entry, $field_id );

		// If there is a value and the field phoneFormat setting is set to standard reformat the value.
		if ( ! empty( $field_value ) && $field->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
			$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
		}

		return $field_value;
	}


	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		// $scripts = array(
		// 	array(
		// 		'handle'  => 'my_script_js',
		// 		'src'     => $this->get_base_url() . '/js/my_script.js',
		// 		'version' => $this->_version,
		// 		'deps'    => array( 'jquery' ),
		// 		'strings' => array(
		// 			'first'  => esc_html__( 'First Choice', 'wicket-gf' ),
		// 			'second' => esc_html__( 'Second Choice', 'wicket-gf' ),
		// 			'third'  => esc_html__( 'Third Choice', 'wicket-gf' )
		// 		),
		// 		'enqueue' => array(
		// 			array(
		// 				'admin_page' => array( 'form_settings' ),
		// 				'tab'        => 'wicketmap'
		// 			)
		// 		)
		// 	),

		// );

		// return array_merge( parent::scripts(), $scripts );
		return parent::scripts();
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
		// $styles = array(
		// 	array(
		// 		'handle'  => 'my_styles_css',
		// 		'src'     => $this->get_base_url() . '/css/my_styles.css',
		// 		'version' => $this->_version,
		// 		'enqueue' => array(
		// 			array( 'field_types' => array( 'poll' ) )
		// 		)
		// 	)
		// );

		// return array_merge( parent::styles(), $styles );
		return parent::styles();
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
		echo 'This page appears in the Forms menu';
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Wicket Member Mapping Settings', 'wicket-gf' ),
				'fields' => array(
					array(
						'name'              => 'mytextbox',
						'tooltip'           => esc_html__( 'This is the tooltip', 'wicket-gf' ),
						'label'             => esc_html__( 'This is the label', 'wicket-gf' ),
						'type'              => 'text',
						'class'             => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					)
				)
			)
		);
	}

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
					// array(
					// 	'name'      => 'mappedFields',
					// 	'label'     => esc_html__( 'Map Fields', 'wicket-gf' ),
					// 	'type'      => 'field_map',
					// 	'field_map' => array(
					// 		array(
					// 			'name'       => 'custom',
					// 			'label'      => esc_html__( 'Custom', 'wicket-gf' ),
					// 			'required'   => 0,
					// 			'field_type' => 'my_custom_field_type',
					// 			'tooltip'    => esc_html__( 'This is the tooltip', 'wicket-gf' ),
					// 		),
					// 		array(
					// 			'name'     => 'name',
					// 			'label'    => esc_html__( 'Name', 'wicket-gf' ),
					// 			'required' => 0,
					// 		),
					// 		array(
					// 			'name'       => 'phone',
					// 			'label'      => esc_html__( 'Phone', 'wicket-gf' ),
					// 			'required'   => 0,
					// 			'field_type' => 'phone',
					// 		),
					// 	),
					// ),
					array(
						// Documentation for dynamic_field: https://docs.gravityforms.com/dynamic_field_map-field/
						'name'                => 'wicketFieldMaps',
						'label'               => esc_html__( 'Wicket Fields', 'wicket_plugin' ),
						'type'                => 'dynamic_field_map',
						'field_map'           => array(
							array(
								'label'						=> 'Example 1',
								'value'						=> 'example-1'
							),
							array(
								'label'						=> 'Example 2',
								'value'						=> 'example-2'
							),
							array(
								'label'						=> 'Example 3',
								'value'						=> 'example-3'
							),
							array(
								'label'						=> 'Option Group',
								'choices'					=> array(
									array(
										'label'						=> 'Example 4',
										'value'						=> 'example-4'
									),
									array(
										'label'						=> 'Example 5',
										'value'						=> 'example-5'
									),
									array(
										'label'						=> 'Example 6',
										'value'						=> 'example-6'
									),
								),
							),
						),
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

	public function settings_my_custom_field_type() {
     
    $this->settings_text(
        array(
            'label'         => 'Item 1',
            'name'          => 'my_custom[1]',
            'default_value' => 'Item 1'
        )
    );
    $this->settings_text(
        array(
            'label'         => 'Item 2',
            'name'          => 'my_custom[2]',
            'default_value' => 'Item 2'
        )
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
