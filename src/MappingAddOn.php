<?php

declare(strict_types=1);

namespace WicketGF;

if (!defined('ABSPATH')) {
    exit;
}

\GFForms::include_feed_addon_framework();

// GF Addon Framework Documentation: https://docs.gravityforms.com/category/developers/php-api/add-on-framework/

class MappingAddOn extends \GFFeedAddOn
{
    public $_async_feed_processing = false;

    protected $_version = WICKET_GF_VERSION;
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'wicketmap';
    protected $_path = 'wicketmap/wicketmap.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms to Wicket Member Mapping';
    protected $_short_title = 'Wicket Member';

    private static $_instance = null;

    private $_debug_schema_changes_before_and_after = false;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function init()
    {
        parent::init();
    }

    public function process_feed($feed, $entry, $form)
    {
        $feedName = $feed['meta']['feedName'];
        $metaData = $this->get_dynamic_field_map_fields($feed, 'wicketFieldMaps');

        $merge_vars = [];
        foreach ($metaData as $name => $field_id) {
            $merge_vars[$name] = $this->get_field_value($form, $entry, $field_id);
        }

        $merge_vars = apply_filters('wicket_gf_process_feed_merge_vars', $merge_vars, $form, $entry, $feed);

        $grouped_updates = [];
        foreach ($merge_vars as $member_field => $incoming_value) {
            $path_to_save = explode(':', $member_field);

            $grouped_updates[$path_to_save[0]][] = [
                'field'         => $member_field,
                'value'         => $incoming_value,
            ];
        }

        if ($this->_debug_schema_changes_before_and_after) {
        }

        foreach ($grouped_updates as $group_type => $items) {
            self::member_api_update($group_type, $items);
        }
    }

    private function member_api_update($schema, $fields, $person_uuid = '', $org_uuid = '')
    {
        $wicket_client = wicket_api_client();

        if (!empty($person_uuid)) {
            $wicket_person = wicket_get_person_by_id($person_uuid);
        } else {
            $wicket_person = wicket_current_person();
        }

        $count_dashes = explode('-', $schema);
        if (count($count_dashes) >= 4) {
            $current_schema_values = self::get_ai_field_from_data_fields($wicket_person->data_fields, $schema);

            if ($this->_debug_schema_changes_before_and_after) {
            }

            $new_schema_values = self::apply_changes_to_schema($current_schema_values, $fields);

            $new_schema_values_validation = self::validate_schema_changes($schema, $new_schema_values);

        } else {
            switch ($schema) {
                case 'profile':
                    foreach ($fields as $field) {
                        $actual_field_id_array = explode('/', $field['field']);
                        $actual_field_id = $actual_field_id_array[count($actual_field_id_array) - 1];
                    }

                    break;
                case 'more':
                    break;
            }
        }
    }

    private function apply_changes_to_schema($current_schema_values, $changes_to_apply, $repeater_index_to_update = null)
    {
        if ($this->_debug_schema_changes_before_and_after) {
        }

        $new_schema_entry = empty($current_schema_values);

        $expanded_changes_to_apply = self::expand_form_mappings($changes_to_apply);
        if ($this->_debug_schema_changes_before_and_after) {
        }

        foreach ($expanded_changes_to_apply as $field) {
            $path_to_save_to = explode('/', $field['path_to_field']);

            $temp = &$current_schema_values;
            $new_repeater_entry_created = false;
            $new_repeater_index = 0;
            $current_key = '';
            foreach ($path_to_save_to as $step) {
                $count_dashes = explode('-', $step);
                $step_is_schema_uuid = count($count_dashes) >= 4;
                $current_key = $step;

                if ($step == 'properties') {
                    continue;
                } elseif (isset($temp[$step]) && !$new_repeater_entry_created && !$new_schema_entry) {
                    $temp = &$temp[$step];
                } elseif ($step == 'items' && isset($temp[0]) && !$new_repeater_entry_created && !$new_schema_entry) {
                    $new_repeater_index = count($temp);
                    $temp = &$temp[$new_repeater_index];
                    $new_repeater_entry_created = true;
                } elseif ($new_repeater_entry_created && !$new_schema_entry) {
                    $temp = &$temp[$step];
                } elseif ($new_schema_entry && ($step_is_schema_uuid || $step == 'attributes' || $step == 'schema')) {
                    continue;
                } elseif ($new_schema_entry && $step != 'items') {
                    $temp = &$temp[$step];
                } elseif ($new_schema_entry && $step == 'items') {
                    $temp = &$temp[0];
                }
            }

            if ($current_key != $field['name']) {
                $temp[$field['name']] = $field['value'];
            } else {
                $temp = $field['value'];
            }
            unset($temp);
        }
        if ($this->_debug_schema_changes_before_and_after) {
        }

        return $current_schema_values;
    }

    private function expand_form_mappings($mappings)
    {
        $output = [];
        $wicket_member_data_fields = get_option('wicket_gf_member_fields');

        foreach ($mappings as $mapping) {
            $split_by_schema = explode(':', $mapping['field']);
            $schema_name = $split_by_schema[0];
            $field_data = self::follow_trail_down_array($split_by_schema[1], '/', $wicket_member_data_fields) ?? [];
            $field_data['value'] = $mapping['value'];
            $output[] = $field_data;
        }

        return $output;
    }

    private function follow_trail_down_array($breadcrumbs, $divider, $array)
    {
        $exploded = explode($divider, $breadcrumbs);

        $temp = &$array;
        foreach ($exploded as $key) {
            $temp = &$temp[$key];
        }
        $value = $temp;
        unset($temp);

        return $value;
    }

    private function get_ai_field_from_data_fields($data_fields, $key)
    {
        foreach ($data_fields as $field) {
            if (isset($field['$schema'])) {
                if (str_contains($field['$schema'], $key)) {
                    if (isset($field['value'])) {
                        return $field['value'];
                    }
                }
            }
        }

        return [];
    }

    private function validate_schema_changes($schema, $new_schema_values)
    {
        $wicket_member_data_fields = get_option('wicket_gf_member_fields');
        $schema_fields = [];
        foreach ($wicket_member_data_fields as $schema_type) {
            if ($schema_type['schema_id'] == $schema) {
                $schema_fields = $schema_type;
            }
        }
    }

    public function scripts()
    {
        return parent::scripts();
    }

    public function styles()
    {
        return parent::styles();
    }

    public function feed_settings_fields()
    {
        return [
            [
                'title'  => esc_html__('Wicket Member Mapping Settings', 'wicket-gf'),
                'fields' => [
                    [
                        'label'   => esc_html__('Feed name', 'wicket-gf'),
                        'type'    => 'text',
                        'name'    => 'feedName',
                        'tooltip' => esc_html__('This is the tooltip', 'wicket-gf'),
                        'class'   => 'small',
                    ],
                    [
                        'name'                => 'wicketFieldMaps',
                        'label'               => esc_html__('Wicket Fields', 'wicket-gf'),
                        'description'               => esc_html__('Note that by default changes will be made to the UUID of the currently logged in user, otherwise a person\'s UUID can be mapped to update a person record, or an org UUID can be mapped to update an org record.', 'wicket-gf'),
                        'type'                => 'dynamic_field_map',
                        'field_map'           => self::get_member_key_options(),
                        'enable_custom_key'      => false,
                        'tooltip'             => '<h6>' . esc_html__('Wicket Fields', 'wicket_plugin') . '</h6>' . esc_html__('Map your GF fields to Wicket', 'wicket_plugin'),
                    ],
                    [
                        'name'           => 'condition',
                        'label'          => esc_html__('Condition', 'wicket-gf'),
                        'type'           => 'feed_condition',
                        'checkbox_label' => esc_html__('Enable Condition', 'wicket-gf'),
                        'instructions'   => esc_html__('Process this simple feed if', 'wicket-gf'),
                    ],
                ],
            ],
        ];
    }

    public function get_member_key_options()
    {
        $wicket_member_data_fields = get_option('wicket_gf_member_fields');
        $fields_to_return = [];
        foreach ($wicket_member_data_fields as $schema_index => $schema) {
            $choices = [];
            foreach ($schema['child_fields'] as $child_field_index => $child_field) {
                $value_array = [];

                $value_array[] = $schema_index;
                $value_array[] = 'child_fields';
                $value_array[] = $child_field_index;
                $value = implode('/', $value_array);

                if (isset($schema['schema_id']) && !empty($schema['schema_id'])) {
                    $value = $schema['schema_id'] . ':' . $value;
                }

                $label = $child_field['label_en'];
                if (isset($child_field['required'])) {
                    if ($child_field['required']) {
                        $label .= '*';
                    }
                }

                $choices[] = [
                    'label'    => $label,
                    'value'    => $value,
                ];
            }

            $fields_to_return[] = [
                'label'     => $schema['name_en'],
                'choices'   => $choices,
            ];
        }

        return $fields_to_return;
    }

    public function feed_list_columns()
    {
        return [
            'feedName'  => esc_html__('Name', 'wicket-gf'),
        ];
    }

    public function can_create_feed()
    {
        $settings = $this->get_plugin_settings();
        $key = rgar($settings, 'apiKey');

        return true;
    }

    public function get_menu_icon()
    {
        return file_get_contents(WICKET_GF_PATH . 'assets/images/wicket_icon_black.svg');
    }

    public static function addon_custom_ui()
    {
        $show_debug_info = false;

        if (isset($_GET['subview']) && isset($_GET['fid'])) {

            echo '
                    <div class="gform-settings__wrapper custom">
                        <div class="">
                            <a
                                aria-label="' . __('Re-Sync Wicket Member Fields', 'wicket-gf') . '"
                                href="javascript:void(0)"
                                class="preview-form gform-button gform-button--white"
                                target="_self"
                                rel="noopener"
                                id="wicket-gf-addon-resync-fields-button"
                            >' . __('Re-Sync Wicket Member Fields', 'wicket-gf') . '</a>
                        </div>
                    </div>
            ';
        }
    }

    public function is_valid_setting($value)
    {
        return strlen($value) < 10;
    }
}
