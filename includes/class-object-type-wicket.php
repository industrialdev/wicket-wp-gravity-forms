<?php

class GPPA_Object_Type_Wicket extends GPPA_Object_Type
{
    private static $max_results = 50;
    private $client;
    private $language = 'en';

    public function __construct($id)
    {
        parent::__construct($id);

        $this->client = wicket_api_client();

        // TODO: Uncomment and test further - queries on Fr sites
        // appeared to be returning empty strings in GF, so forcing En
        // for now
        // if( defined( 'ICL_LANGUAGE_CODE' ) ) {
        // 	$this->language = ICL_LANGUAGE_CODE;
        // } else {
        // 	if( str_contains( get_locale(), 'fr' ) ) {
        // 		$this->language = 'fr';
        // 	}
        // }
    }

    public function get_object_id($object, $primary_property_value = null)
    {
        return self::get_object_prop_value($object, 'uuid');
    }

    public function get_label()
    {
        return esc_html__('Wicket', 'gp-populate-anything');
    }

    public function get_primary_property()
    {
        return [
            'id'       => 'entity',
            'label'    => esc_html__('Entity', 'gp-populate-anything'),
            'callable' => [$this, 'get_entities'],
        ];
    }

    public function get_entities()
    {
        // TODO: Add more entities here, which will reflect the types of data
        // that can be pulled via the API, such as Additional Info, Connections/Relationships,
        // touchpoints, groups, etc.

        return [
            'person' => 'Person',
            'org'		 => 'Organization',
        ];
    }

    public function get_properties($primary_property = null)
    {

        // TODO: Add more properties here

        if ($primary_property == 'person') {
            return [
                'uuid' => [
                    'label'     => esc_html__('UUID', 'wicket-gf'),
                    'value'     => 'uuid',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
                'given_name' => [
                    'label'     => esc_html__('First Name', 'wicket-gf'),
                    'value'     => 'given_name',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
                'family_name' => [
                    'label'     => esc_html__('Last Name', 'wicket-gf'),
                    'value'     => 'family_name',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ], 'full_name' => [
                    'label'     => esc_html__('Full Name', 'wicket-gf'),
                    'value'     => 'full_name',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
            ];
        } elseif ($primary_property == 'org') {
            return [
                'uuid' => [
                    'label'     => esc_html__('UUID', 'wicket-gf'),
                    'value'     => 'uuid',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
                'legal_name' => [
                    'label'     => esc_html__('Legal Name', 'wicket-gf'),
                    'value'     => 'legal_name',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
                'type' => [
                    'label'     => esc_html__('Type', 'wicket-gf'),
                    'value'     => 'type',
                    'callable'  => '__return_empty_array',
                    'orderby'   => true,
                    'operators' => $this->supported_operators(),
                ],
            ];
        }
    }

    public function query($args)
    {

        // Call get_special_values to ensure filter values have been converted to usable values
        $filter_special_values = $this->get_special_values($args);

        /**
         * Build filters for query based on PA selections.
         */
        $filters = [];
        if (isset($args['filter_groups']) && !empty($args['filter_groups'])) {
            // Filter example
            // ['uuid_in' => $autocomplete_person_uuids,
            // 'connections_organization_uuid_eq' => $org_id]

            // The parent filter groups that represent OR conditions
            foreach ($args['filter_groups'] as $parent_filter_group) {

                // TODO: Handle parent OR conditions by sending multiple Wicket queries with those
                // groups of filters, and then combining them before returning (likely with a nested array)

                // The child filter groups that represent AND conditions
                $i = 0;
                foreach ($parent_filter_group as $child_filter_group) {
                    //for( $i = 0; $i < count( $parent_filter_group ); $i++ ) {
                    //$child_filter_group = $parent_filter_group[$i];

                    // Value
                    $value = $filter_special_values[$i]['filter_value'];
                    // $value = $child_filter_group['value'];
                    // if( str_contains( $child_filter_group['value'], 'gf_custom' ) ) {
                    // 	$value_array = explode( ':', $child_filter_group['value'] );
                    // 	$value = $value_array[1];
                    // }

                    // Property, and adjust any one-off properties
                    $property = $child_filter_group['property'];
                    if ($property == 'legal_name') {
                        $property = 'legal_name_' . $this->language;
                    }

                    // Add filters
                    if ($child_filter_group['operator'] == 'contains') {
                        $filters[$property . '_cont'] = $value;
                    } elseif ($child_filter_group['operator'] == 'is') {
                        $filters[$property . '_eq'] = $value;
                    } elseif ($child_filter_group['operator'] == 'isnot') {
                        $filters[$property . '_not_eq'] = $value;
                    } elseif ($child_filter_group['operator'] == 'does_not_contain') {
                        $filters[$property . '_not_cont'] = $value;
                    } elseif ($child_filter_group['operator'] == 'starts_with') {
                        $filters[$property . '_start'] = $value;
                    } elseif ($child_filter_group['operator'] == 'ends_with') {
                        $filters[$property . '_end'] = $value;
                    } elseif ($child_filter_group['operator'] == 'like') {
                        $filters[$property . '_matches'] = $value;
                    } elseif ($child_filter_group['operator'] == 'is_in') {
                        $filters[$property . '_in'] = $value;
                    } elseif ($child_filter_group['operator'] == 'is_not_in') {
                        $filters[$property . '_not_in'] = $value;
                    } elseif ($child_filter_group['operator'] == '>') {
                        $filters[$property . '_gt'] = $value;
                    } elseif ($child_filter_group['operator'] == '>=') {
                        $filters[$property . '_gteq'] = $value;
                    } elseif ($child_filter_group['operator'] == '<') {
                        $filters[$property . '_lt'] = $value;
                    } elseif ($child_filter_group['operator'] == '<=') {
                        $filters[$property . '_lteq'] = $value;
                    }

                    $i++;
                }  // end foreach( $parent_filter_group as $child_filter_group )
            }
        }

        /*
         * Query Wicket based on the selected entity type and pass through built filters
         */
        if ($args['primary_property_value'] == 'person') {
            $people_query = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query([
                'include' => 'emails,roles',
                'fields' => [
                    'people' => 'uuid,given_name,family_name,full_name,primary_email_address',
                ],
                'filter' => $filters,
                'page' => [
                    'size' => self::$max_results,
                ],
            ]));

            try {
                $get_people = $this->client->get('/people', ['query' => $people_query]);
                $get_people = new Wicket\ResponseHelper($get_people);
            } catch (Exception $e) {
                return [];
            }

            $people = [];
            if (isset($get_people->data) && !empty($get_people->data)) {
                foreach ($get_people->data as $person) {
                    $tmp = [];
                    $tmp['uuid'] = $person['attributes']['uuid'];
                    $tmp['given_name'] = $person['attributes']['given_name'];
                    $tmp['family_name'] = $person['attributes']['family_name'];
                    $tmp['full_name'] = $person['attributes']['full_name'];
                    $tmp['email'] = $person['attributes']['primary_email_address'];
                    $people[] = $tmp;
                }
            }

            return $people;
        } elseif ($args['primary_property_value'] == 'org') {
            $org_query = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query([
                'fields' => [
                    'organizations' => 'legal_name_en,legal_name_fr,type',
                ],
                'filter' => $filters,
                'page' => [
                    'size' => self::$max_results,
                ],
            ]));

            try {
                $get_orgs = $this->client->get('/organizations', ['query' => $org_query]);
                $get_orgs = new Wicket\ResponseHelper($get_orgs);
            } catch (Exception $e) {
                return [];
            }

            $orgs = [];
            if (isset($get_orgs->data) && !empty($get_orgs->data)) {
                foreach ($get_orgs->data as $org) {
                    $tmp = [];
                    $tmp['uuid'] = $org['id'];
                    $tmp['legal_name'] = $org['attributes']['legal_name_' . $this->language];
                    $tmp['type'] = $org['attributes']['type'];
                    $orgs[] = $tmp;
                }
            }

            return $orgs;
        }
    }

    public function get_object_prop_value($object, $prop)
    {

        // If the $object is an object
        if (is_object($object)) {
            if (!isset($object->$prop)) {
                return null;
            }

            return $object->$prop;
        }

        // If the $object is an array
        if (!isset($object[$prop])) {
            return null;
        }

        return $object[$prop];

    }

    /**
     * This is a modified version of process_query_args() from the base class-object-type.php.
     * The original function was meant to perform more specific search prep logic that we didn't need,
     * so this version simply helps convert the filter values to their actual formats (e.g. converting
     * "special_value:advanced_select_search_value" to "Sam").
     */
    public function get_special_values($args, $processed_filter_groups = [])
    {

        /** @var string */
        $populate = null;

        /** @var array */
        $filter_groups = null;

        /** @var array */
        $ordering = null;

        /** @var array */
        $templates = null;

        /** @var string */
        $primary_property_value = null;

        /** @var array */
        $field_values = null;

        /** @var GF_Field */
        $field = null;

        /** @var bool */
        $unique = null;

        /** @var int|null */
        $page = null;

        /** @var int */
        $limit = null;

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($args);

        if (!$this->can_skip_loading_properties_during_query()) {
            $properties = $this->get_properties_filtered($primary_property_value);
        }

        gf_do_action(['gppa_pre_object_type_query', $this->id], $processed_filter_groups, $args);

        if (!is_array($filter_groups)) {
            return $processed_filter_groups;
        }

        $return_array = [];
        foreach ($filter_groups as $filter_group_index => $filter_group) {
            foreach ($filter_group as $filter) {
                $filter_value = gp_populate_anything()->extract_custom_value($filter['value']);

                if (is_scalar($filter_value)) {
                    $filter_value = GFCommon::replace_variables_prepopulate($filter_value, false, false, true);
                }

                if (!$filter['value'] || !$filter['property']) {
                    continue;
                }

                if (!$this->can_skip_loading_properties_during_query()) {
                    $property = rgar($properties, $filter['property']);

                    if (!$property) {
                        continue;
                    }
                } else {
                    $property = [
                        'value' => $this->get_property_value_from_property_id($filter['property']),
                        'group' => method_exists($this, 'get_property_value_from_property_id') ? $this->get_group_from_property_id($filter['property']) : null,
                    ];
                }

                $filter_value = apply_filters('gppa_replace_filter_value_variables_' . $this->id, $filter_value, $field_values, $primary_property_value, $filter, $ordering, $field, $property);
                $wp_filter_name = 'gppa_object_type_' . $this->id . '_filter_' . $filter['property'];

                $group = rgar($property, 'group');

                if (!has_filter($wp_filter_name) && $group) {
                    $wp_filter_name = 'gppa_object_type_' . $this->id . '_filter_group_' . $group;
                }

                if (!has_filter($wp_filter_name)) {
                    $wp_filter_name = 'gppa_object_type_' . $this->id . '_filter';
                }

                array_push($return_array, [
                    'filter_value'           => $filter_value,
                    'filter'                 => $filter,
                    'field'                  => $field,
                    'filter_group'           => $filter_group,
                    'filter_group_index'     => $filter_group_index,
                    'primary_property_value' => $primary_property_value,
                    'property'               => $property,
                    'property_id'            => $filter['property'],
                ]);
            }
        }

        return $return_array;
    }

    /** -------------------------------------------------------
     * Functions that can be left as-is from Populate Anything:
     *  ------------------------------------------------------- */
    public function get_groups()
    {
        return [];
    }

    public function supported_operators()
    {
        return array_merge(gp_populate_anything()->get_default_operators(), ['is_in', 'is_not_in']);
    }

    /**
     * Tell Populate Anything that this Object Type uses PHP filtering that way Populate Anything doesn't set the query limit to 1 when populating values.
     */
    public function uses_php_filtering()
    {
        return true;
    }

    public function get_selected_entity($primary_property)
    {
        return null;
    }

    public function process_filter_default($search, $args)
    {

        /** @var string */
        $filter_value = null;

        /** @var array */
        $filter = null;

        /** @var int */
        $filter_group_index = null;

        /** @var string */
        $property_id = null;

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($args);

        $search[$filter_group_index][] = [
            'property' => $property_id,
            'operator' => $filter['operator'],
            'value'    => $filter_value,
        ];

        return $search;

    }
}
