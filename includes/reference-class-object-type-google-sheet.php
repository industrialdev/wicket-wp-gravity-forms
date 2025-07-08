<?php

/**
 * @copyright Copyright (c) 2021-2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gp-populate-anything-google-sheets
 */
if (!defined('ABSPATH')) {
    die();
}

class GP_Google_Sheets_GPPA_Object_Type_Google_Sheet extends GPPA_Object_Type
{
    const ROW_NUMBER_ID = 'Row Number';

    public $sheet_values_runtime_cache;

    public function __construct($id)
    {
        parent::__construct($id);

        add_action('gppa_pre_object_type_query_gpgs_sheet', [$this, 'add_filter_hooks']);
    }

    public function add_filter_hooks()
    {
        add_filter('gppa_object_type_gpgs_sheet_filter', [$this, 'process_filter_default'], 10, 2);
    }

    public function get_object_id($object, $primary_property_value = null)
    {
        return $object[self::ROW_NUMBER_ID];
    }

    public function get_label()
    {
        return esc_html__('Google Sheet', 'gp-populate-anything');
    }

    public function get_primary_property()
    {
        return [
            'id'       => 'sheet',
            'label'    => esc_html__('Sheet', 'gp-populate-anything'),
            'callable' => [$this, 'get_sheets'],
        ];
    }

    /**
     * Tell Populate Anything that this Object Type uses PHP filtering that way Populate Anything doesn't set the
     * query limit to 1 when populating values.
     */
    public function uses_php_filtering()
    {
        return true;
    }

    /**
     * Gets an array of sheets for all spreadsheets as a simple associative array to be used as property values.
     *
     * @return array
     */
    public function get_sheets()
    {
        $transient_key = 'gpgs_gppa_sheets';
        $cached = get_transient($transient_key);

        if (!empty($cached)) {
            return $cached;
        }

        $spreadsheets_drive_files = gp_google_sheets()->get_available_spreadsheets();

        /**
         * @var GP_Google_Sheets\Dependencies\Google\Service\Sheets\Sheet[][] $sheets
         */
        $sheets = [];

        $service = GP_Google_Sheets_Authenticator::create_service([]);
        $spreadsheets = GP_Google_Sheets_Authenticator::get_spreadsheets_resource($service);

        foreach ($spreadsheets_drive_files as $spreadsheets_drive_file) {
            try {
                // Ensure the file type is a Google spreadsheet.
                if ($spreadsheets_drive_file->getMimeType() !== 'application/vnd.google-apps.spreadsheet') {
                    continue;
                }

                $spreadsheet = $spreadsheets->get($spreadsheets_drive_file->id);

                $sheets[$spreadsheets_drive_file->id] = $spreadsheet->getSheets();
            } catch (Exception $e) {
                // Do nothing
            }
        }

        $sheet_options = [];

        foreach ($sheets as $spreadsheet_drive_file_id => $spreadsheet_sheets) {
            foreach ($spreadsheet_sheets as $sheet) {
                /**
                 * @var GP_Google_Sheets\Dependencies\Google\Service\Drive\DriveFile|null $spreadsheet_drive_file
                 */
                $spreadsheet_drive_file = rgar($spreadsheets_drive_files, $spreadsheet_drive_file_id);

                if (!$spreadsheet_drive_file) {
                    continue;
                }

                $option_value = $spreadsheet_drive_file_id . '|' . $sheet->getProperties()->getSheetId();
                $option_label = $spreadsheet_drive_file->getName() . ' - ' . $sheet->getProperties()->title;

                $sheet_options[$option_value] = $option_label;
            }
        }

        $expiration_time = apply_filters('gpgs_gppa_cache_sheets_expiration', 15);
        set_transient($transient_key, $sheet_options, $expiration_time);

        return $sheet_options;
    }

    /**
     * We store the primary property in the format of `spreadsheet_id|sheet_id` so we need a way to easily extract them.
     *
     * @param $primary_property
     *
     * @return array
     */
    public function get_ids_from_primary_property($primary_property)
    {
        $sheet_parts = explode('|', $primary_property);

        return [
            'spreadsheet_id' => rgar($sheet_parts, 0),
            'sheet_id'       => (int) rgar($sheet_parts, 1),
        ];
    }

    public function get_groups()
    {
        return [];
    }

    /**
     * @param $primary_property
     *
     * @return GP_Google_Sheets\Dependencies\Google\Service\Sheets\Sheet|null
     */
    public function get_selected_sheet($primary_property)
    {
        $service = GP_Google_Sheets_Authenticator::create_service([]);
        $spreadsheets = GP_Google_Sheets_Authenticator::get_spreadsheets_resource($service);

        /** @var string */
        $spreadsheet_id = null;

        /** @var int */
        $sheet_id = null;

        $ids = $this->get_ids_from_primary_property($primary_property);

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($ids);

        $spreadsheet_sheets = $spreadsheets->get($spreadsheet_id)->getSheets();

        // Find the sheet with the matching ID using $sheet->getProperties()->getSheetId().
        foreach ($spreadsheet_sheets as $spreadsheet_sheet) {
            if ($spreadsheet_sheet->getProperties()->getSheetId() === $sheet_id) {
                return $spreadsheet_sheet;
            }
        }

        return null;
    }

    /**
     * @param string $primary_property
     * @param string $range
     *
     * @return array
     */
    public function get_sheet_raw_values($primary_property, $range = '1:1000')
    {
        if (!empty($this->sheet_values_runtime_cache[$primary_property . $range])) {
            return $this->sheet_values_runtime_cache[$primary_property . $range];
        }

        $transient_key = 'gpgs_gppa_raw_values_' . $primary_property . '_' . $range;
        $cached = get_transient($transient_key);

        if (!empty($cached)) {
            return $cached;
        }

        try {
            $service = GP_Google_Sheets_Authenticator::create_service([]);
            $spreadsheets_values = GP_Google_Sheets_Authenticator::get_spreadsheets_values_resource($service);

            /** @var string */
            $spreadsheet_id = null;

            /** @var int */
            $sheet_id = null;

            $ids = $this->get_ids_from_primary_property($primary_property);

            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract($ids);

            $sheet = $this->get_selected_sheet($primary_property);

            if (!$sheet) {
                return [];
            }

            $sheet_name = $sheet->getProperties()->title;

            // @todo figure out a way to better limit the number of rows we fetch or customize the range used.
            $response = $spreadsheets_values->get($spreadsheet_id, $sheet_name . '!' . $range);
            $values = $response->getValues();

            $matches = [];
            preg_match('/^[a-zA-Z]*([0-9]+):[a-zA-Z]*[0-9]+$/', $range, $matches);

            $current_row = 1;
            if (!empty($matches[1])) {
                $current_row = (int) $matches[1];
            }

            foreach ($values as $value_index => $value) {
                if ($value_index === 0 && $current_row === 1) {
                    // If the range starts at "1", then we need to process the first row as a column header row.
                    array_unshift($values[$value_index], self::ROW_NUMBER_ID);
                } else {
                    /*
                     * Add row number to values to serve as the ID of the object.
                     *
                     * Note that we subtract 1 from $current_row, as we do not count the actual first row in
                     * the sheet since that contains the column headers.
                     */
                    array_unshift($values[$value_index], $current_row - 1);
                }

                $current_row++;
            }

            $this->sheet_values_runtime_cache[$primary_property . $range] = $values;

            $expiration_time = apply_filters('gpgs_gppa_cache_raw_values_expiration', 60);
            set_transient($transient_key, $values, $expiration_time);

            return $values;
        } catch (Exception $e) {
            error_log('Unable to fetch from Google Sheets: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Get columns from first row of sheet.
     *
     * @param string $primary_property
     *
     * @return string[]
     */
    public function get_sheet_columns($primary_property)
    {
        $transient_key = 'gpgs_gppa_columns_' . sanitize_key($primary_property);
        $cached = get_transient($transient_key);

        if (!empty($cached)) {
            return $cached;
        }

        $values = $this->get_sheet_raw_values($primary_property, '1:1');

        if (empty($values)) {
            return [];
        }

        $columns = $values[0];

        $expiration_time = apply_filters('gpgs_gppa_cache_columns_expiration', 15);
        set_transient($transient_key, $columns, $expiration_time);

        return $columns;
    }

    /**
     * Combine row data with columns and return associative array.
     *
     * @param string $primary_property
     * @param int $limit
     *
     * @return array
     */
    public function get_sheet_rows($primary_property, $limit)
    {
        $transient_key = 'gpgs_gppa_rows_' . sanitize_key($primary_property);
        $cached = get_transient($transient_key);

        if (!empty($cached)) {
            return $cached;
        }

        $range = '2:' . ($limit + 1); // We skip the first row as it's the header row.
        $values = $this->get_sheet_raw_values($primary_property, $range);
        $columns = $this->get_sheet_columns($primary_property);
        $column_count = count($columns);

        $sheet_rows = array_map(function ($row) use ($columns, $column_count) {
            return array_combine($columns, array_slice(array_pad($row, $column_count, ''), 0, $column_count));
        }, $values);

        /**
         * Filter the number of seconds that row data should be cached for. Defaults to `60` seconds.
         *
         * @param int $expiration_time Number of seconds to cache row data for. Defaults to `60`.
         * @param string $sheet The spreadsheet and sheet name. It will be in the format of `DRIVE_FILE_ID|SHEET_ID`.
         *
         * @since 1.0-beta-2.1
         */
        $expiration_time = apply_filters('gpgs_gppa_cache_rows_expiration', 60, $primary_property);
        set_transient($transient_key, $sheet_rows, $expiration_time);

        return $sheet_rows;
    }

    /**
     * @param string $primary_property
     *
     * @return array
     */
    public function get_properties($primary_property = null)
    {
        $properties = [];

        if (!$primary_property) {
            return [$properties];
        }

        $columns = $this->get_sheet_columns($primary_property);

        if (!$columns) {
            return [$properties];
        }

        /*
         * Extract column names from the first row.
         */
        foreach ($columns as $column) {
            $properties[$column] = [
                'label'     => $column,
                'value'     => $column,
                'orderby'   => true,
                'callable'  => '__return_empty_array',
                'operators' => [
                    'is',
                    'isnot',
                    '>',
                    '>=',
                    '<',
                    '<=',
                    'contains',
                    'is_in',
                    'is_not_in',
                ],
            ];
        }

        return $properties;
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

    /**
     * @param array $var
     * @param array $search
     *
     * @return bool
     */
    public function perform_search($var, $search)
    {

        $var_value = is_array($var[$search['property']]) ? array_map('strtolower', $var[$search['property']])
            : strtolower($var[$search['property']]);

        $search_value = is_array($search['value']) ? array_map('strtolower', $search['value']) : strtolower($search['value']);

        switch ($search['operator']) {
            case 'is':
                // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                return $var_value == $search_value;

            case 'isnot':
                // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                return $var_value != $search_value;

            case 'is_in':
                // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
                return in_array($var_value, $search_value, false);

            case 'is_not_in':
                // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
                return !in_array($var_value, $search_value, false);

            case 'contains':
                return strpos($var_value, $search_value) !== false;

            case '>':
                return $var_value > $search_value;

            case '>=':
                return $var_value >= $search_value;

            case '<':
                return $var_value < $search_value;

            case '<=':
                return $var_value <= $search_value;

                // Invalid operator provided, just return false.
            default:
                return false;
        }

    }

    /**
     * @param $var
     * @param $search_params
     *
     * @return bool
     * @todo Move PHP based filtering and ordering into an Object Type that can be easily extended.
     *
     * Each search group is an OR
     *
     * If everything matches in one group, we can immediately bail out as we have a positive match.
     */
    public function search($var, $search_params)
    {
        foreach ($search_params as $search_group) {
            if (is_array($search_group)) {
                foreach ($search_group as $search) {
                    $matches_group = $this->perform_search($var, $search);

                    if (!$matches_group) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function query($args)
    {
        /** @var string */
        $primary_property_value = null;

        /** @var array */
        $ordering = null;

        /** @var GF_Field */
        $field = null;

        /** @var int */
        $limit = null;

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($args);

        // Only GPPA 2.0+ has 'limit' in $args. If it's not present, we still need to populate it.
        if (empty($limit)) {
            $limit = gp_populate_anything()->get_query_limit($this, $field);
        }

        $results = $this->get_sheet_rows($primary_property_value, $limit);
        $search_params = $this->process_filter_groups($args);

        if (!empty($search_params)) {
            $results = array_values(array_filter($results, function ($var) use ($search_params) {
                return $this->search($var, $search_params);
            }));
        }

        $orderby = rgar($ordering, 'orderby');
        $order = strtolower(rgar($ordering, 'order', 'ASC'));

        if (!empty($orderby) && count($results) && array_key_exists($orderby, $results[0])) {
            if ($order === 'rand') {
                shuffle($results);
            } else {
                // @phpstan-ignore-next-line
                array_multisort(array_column($results, $orderby), $order === 'desc' ? SORT_DESC : SORT_ASC, $results);
            }
        }

        return $results;

    }

    public function get_object_prop_value($object, $prop)
    {

        if (!isset($object[$prop])) {
            return null;
        }

        return $object[$prop];

    }
}
