<?php

declare(strict_types=1);

namespace WicketGF;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Wicket Gravity Forms settings and management.
 */
class Admin
{
    public function __construct() {}

    public static function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=wicket_gf">' . __('Settings') . '</a>';
        array_push($links, $settings_link);

        return $links;
    }

    public static function register_settings()
    {
        add_option('wicket_gf_slug_mapping', '');
        register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', ['sanitize_callback' => [self::class, 'sanitize_slug_mapping']]);
        register_setting('wicket_gf_options_group', 'wicket_gf_pagination_sidebar_layout', null);
    }

    public static function register_options_page()
    {
        add_submenu_page('gf_edit_forms', __('Wicket Settings', 'wicket-gf'), __('Wicket Settings', 'wicket-gf'), 'manage_options', 'wicket_gf', [self::class, 'options_page']);
    }

    public static function options_page()
    { ?>
<div class="wrap">
    <style>
        .wicket-gf-mapping-row {
            display: flex;
            margin-bottom: 8px;
            align-items: center;
        }
        .wicket-gf-mapping-row input[type="text"] {
            margin-right: 8px;
            flex: 1;
            max-width: 200px;
        }
        .wicket-gf-mapping-row button {
            margin-right: 4px;
            min-width: 30px;
        }
        .wicket_pagination_settings {
            margin-bottom: 15px;
        }
        .wicket_pagination_settings label {
            margin-left: 8px;
        }
        #validation-message {
            color: #d63638;
            font-weight: 600;
            margin-bottom: 15px;
        }
        #mapping-ui h3 {
            margin-top: 25px;
            margin-bottom: 10px;
        }
        #mapping-ui p {
            margin-bottom: 10px;
        }
        #mapping-ui form {
            margin-top: 20px;
        }
    </style>

    <h1><?php _e('Wicket Gravity Forms', 'wicket-gf'); ?></h1>

    <h2><?php _e('Form Slug ID Mapping', 'wicket-gf'); ?></h2>

    <p>
        <?php _e('The mappings below tell the rest of the site which form slugs correspond to which Gravity
                        Form IDs, allowing you to import and update forms easily by simply changing the ID here.', 'wicket-gf'); ?>
    </p>

    <p>
        <?php _e('This makes it easy to reference forms by their slug in coding using the <code>wicket_gf_get_form_id_by_slug()</code> function.', 'wicket-gf'); ?>
    </p>

        <?php
        $current_mappings_json = get_option('wicket_gf_slug_mapping');

        $current_mappings = json_decode($current_mappings_json, true);
        if (!is_array($current_mappings) || (!empty($current_mappings) && array_keys($current_mappings) === range(0, count($current_mappings) - 1))) {
            $current_mappings = ['example-form-slug' => '0'];
        }
        $sanitized_mappings = [];
        foreach ($current_mappings as $key => $value) {
            $newKey = strtolower(str_replace(' ', '-', $key));
            $newKey = preg_replace('/[^a-z0-9\-]/', '', $newKey);
            $sanitized_mappings[$newKey] = $value;
        }
        $current_mappings = $sanitized_mappings;

        if (empty($current_mappings)) {
            $current_mappings = ['' => ''];
        }
        ?>

    <div id="mapping-ui">
        <form id="wicket-gf-settings-form" method="post" action="options.php">
            <?php settings_fields('wicket_gf_options_group'); ?>

            <div id="mapping-rows"></div>

            <input hidden type="text" id="wicket_gf_slug_mapping" name="wicket_gf_slug_mapping"
                value="<?php echo esc_attr(json_encode($current_mappings)); ?>" />

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    window.MappingUI = {
                        mappings: <?php echo json_encode($current_mappings); ?> ,
                        isValid: true,

                        init: function() {
                            this.renderRows();
                            this.updateHiddenFormField();
                            this.validateMappings();
                        },

                        renderRows: function() {
                            const container = document.getElementById('mapping-rows');
                            container.innerHTML = '';

                            for (const slug in this.mappings) {
                                const id = this.mappings[slug];
                                const row = this.createRow(slug, id);
                                container.appendChild(row);
                            }
                        },

                        createRow: function(slug, id) {
                            const row = document.createElement('div');
                            row.className = 'wicket-gf-mapping-row';

                            const slugId = 'wicket_gf_slug_' + Math.random().toString(36).substr(2, 9);
                            const formIdId = 'wicket_gf_form_id_' + Math.random().toString(36).substr(2, 9);

                            row.innerHTML = `
                                        <input class="wicket-gf-mapping-row-key" type="text"
                                               id="${slugId}" name="wicket_gf_mapping_slug[]" data-slug="${slug}"
                                               value="${slug}" placeholder="<?php _e('Slug', 'wicket-gf'); ?>"
                                               aria-label="<?php _e('Form Slug', 'wicket-gf'); ?>" />
                                        <input class="wicket-gf-mapping-row-val" type="text"
                                               id="${formIdId}" name="wicket_gf_mapping_form_id[]" data-slug="${slug}"
                                               value="${id}" placeholder="<?php _e('Form ID', 'wicket-gf'); ?>"
                                               aria-label="<?php _e('Gravity Form ID', 'wicket-gf'); ?>" />
                                        <button type="button" class="button add-row-btn"
                                                aria-label="<?php _e('Add new mapping row', 'wicket-gf'); ?>">+</button>
                                        <button type="button" class="button remove-row-btn" data-slug="${slug}"
                                                aria-label="<?php _e('Remove this mapping row', 'wicket-gf'); ?>"
                                                ${Object.keys(this.mappings).length <= 1 ? 'disabled' : ''}>-</button>
                                    `;

                            const slugInput = row.querySelector('.wicket-gf-mapping-row-key');
                            const idInput = row.querySelector('.wicket-gf-mapping-row-val');
                            const addBtn = row.querySelector('.add-row-btn');
                            const removeBtn = row.querySelector('.remove-row-btn');

                            const self = this;

                            slugInput.addEventListener('input', function(e) {
                                const currentSlug = e.target.dataset.slug || slug;
                                self.updateSlug(e, currentSlug);
                            });

                            idInput.addEventListener('input', function(e) {
                                const currentSlug = e.target.dataset.slug || slug;
                                self.updateId(e, currentSlug);
                            });

                            addBtn.addEventListener('click', function() {
                                self.addRow();
                            });

                            removeBtn.addEventListener('click', function() {
                                const currentSlug = this.dataset.slug || slug;
                                self.removeRow(currentSlug);
                            });

                            return row;
                        },

                        updateSlug: function(event, oldSlug) {
                            let newSlug = event.target.value;
                            newSlug = newSlug.replace(/[^-,^a-zA-Z0-9 ]/g, '');
                            newSlug = newSlug.replace(/\s+/g, '-').toLowerCase();
                            event.target.value = newSlug;

                            if (newSlug !== oldSlug && oldSlug in this.mappings) {
                                if (newSlug !== '' && this.mappings.hasOwnProperty(newSlug)) {
                                    alert(
                                        '<?php _e('Slug already exists. Please choose a unique slug.', 'wicket-gf'); ?>'
                                    );
                                    event.target.value = oldSlug;
                                    return;
                                }

                                const newMappings = {
                                    ...this.mappings
                                };
                                const id = newMappings[oldSlug];
                                delete newMappings[oldSlug];
                                newMappings[newSlug] = id;
                                this.mappings = newMappings;

                                const row = event.target.closest('.wicket-gf-mapping-row');
                                if (row) {
                                    const inputs = row.querySelectorAll('input, button');
                                    inputs.forEach(element => {
                                        element.dataset.slug = newSlug;
                                    });
                                }

                                this.updateHiddenFormField();
                                this.validateMappings();
                            }
                        },

                        updateId: function(event, slug) {
                            const newId = event.target.value;
                            if (this.mappings[slug] !== newId) {
                                this.mappings[slug] = newId;
                                this.updateHiddenFormField();
                                this.validateMappings();
                            }
                        },

                        addRow: function() {
                            let newSlugBase = 'new-slug';
                            let newSlug = newSlugBase;
                            let counter = 1;
                            while (this.mappings.hasOwnProperty(newSlug)) {
                                newSlug = `${newSlugBase}-${counter}`;
                                counter++;
                            }
                            this.mappings = {
                                ...this.mappings,
                                [newSlug]: ''
                            };
                            this.updateHiddenFormField();
                            this.validateMappings();
                            this.renderRows();
                        },

                        removeRow: function(slugToRemove) {
                            if (Object.keys(this.mappings).length > 1) {
                                const newMappings = {
                                    ...this.mappings
                                };
                                delete newMappings[slugToRemove];
                                this.mappings = newMappings;
                                this.updateHiddenFormField();
                                this.validateMappings();
                                this.renderRows();
                            }
                        },

                        updateHiddenFormField: function() {
                            const hiddenField = document.querySelector('#wicket_gf_slug_mapping');
                            if (hiddenField) {
                                hiddenField.value = JSON.stringify(this.mappings);
                            } else {
                            }
                        },

                        validateMappings: function() {
                            this.isValid = true;
                            for (const slug in this.mappings) {
                                const id = this.mappings[slug];
                                const slugIsEmpty = (slug === '' || slug === null);
                                const idIsEmpty = (id === '' || id === null || id ===
                                    '0');

                                if (!slugIsEmpty && idIsEmpty) {
                                    this.isValid = false;
                                    break;
                                }
                                if (slugIsEmpty && !idIsEmpty) {
                                    this.isValid = false;
                                    break;
                                }
                            }

                            const submitButton = document.querySelector(
                                '#wicket-gf-settings-form input[type="submit"]');
                            if (submitButton) {
                                submitButton.disabled = !this.isValid;
                            }

                            const validationMessage = document.getElementById('validation-message');
                            if (validationMessage) {
                                validationMessage.style.display = this.isValid ? 'none' : 'block';
                            }
                        }
                    };

                    window.MappingUI.init();
                });
            </script>

            <p id="validation-message" style="display: none;">
                <?php _e('Please ensure no rows have empty fields.', 'wicket-gf'); ?>
            </p>

            <h3>
                <?php _e('General Gravity Forms Settings', 'wicket-gf'); ?>
            </h3>

            <div class="wicket_pagination_settings">
                <input type="checkbox" name="wicket_gf_pagination_sidebar_layout"
                    id="wicket_gf_pagination_sidebar_layout"
                    <?php checked(get_option('wicket_gf_pagination_sidebar_layout'), 'on'); ?>>
                <label for="wicket_gf_pagination_sidebar_layout" class="inline">Use Sidebar Pagination Layout</label>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
</div>
<?php
    }

    public static function register_meta_box($meta_boxes, $entry, $form)
    {
        return $meta_boxes;
    }

    public static function render_custom_meta_box($entry, $form)
    {
        echo '<div class="wicket-gf-admin__custom-meta inside gf_entry_wrap" style="margin-bottom:1em;">';
        echo '<strong>Entry ID:</strong> ' . esc_html($entry['id']);
        echo '</div>';
        echo '<div class="inside gf_entry_wrap" style="max-height:400px; overflow:auto; background:#fafbfc; border:1px solid #e5e5e5; border-radius:4px; padding:10px; font-size:13px; font-family:Menlo,Monaco,Consolas,monospace;">';
        echo '<strong>Full Entry Array (detailed):</strong>';
        echo '<pre style="margin:0; white-space:pre;">' . var_dump($entry) . '</pre>';
        echo '</div>';
    }

    public static function sanitize_slug_mapping($input)
    {
        $decoded = json_decode(stripslashes($input), true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error(
                'wicket_gf_slug_mapping',
                'invalid_json',
                __('Failed to save mappings due to invalid data format.', 'wicket-gf'),
                'error'
            );

            return get_option('wicket_gf_slug_mapping');
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $sanitized_mappings = [];
        $is_data_valid = true;
        $validation_error_message = '';

        foreach ($decoded as $key => $value) {
            $newKey = strtolower(str_replace(' ', '-', $key));
            $newKey = preg_replace('/[^a-z0-9\-]/', '', $newKey);

            $newValue = preg_replace('/[^0-9]/', '', $value);

            $slugIsEmpty = empty($newKey);
            $idIsEmpty = empty($newValue) || $newValue === '0';

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

            $sanitized_mappings[$newKey] = $newValue;
        }

        if (!$is_data_valid) {
            add_settings_error(
                'wicket_gf_slug_mapping',
                'invalid_mapping',
                __('Failed to save mappings: ', 'wicket-gf') . $validation_error_message,
                'error'
            );

            return get_option('wicket_gf_slug_mapping');
        }

        return json_encode($sanitized_mappings);
    }
}
