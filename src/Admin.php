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
        $settings_link = '<a href="admin.php?page=gf_settings&subview=wicket">' . __('Settings') . '</a>';
        array_push($links, $settings_link);

        return $links;
    }

    public static function register_settings()
    {
        add_option('wicket_gf_slug_mapping', '');
        register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', ['sanitize_callback' => [self::class, 'sanitize_slug_mapping']]);
        register_setting('wicket_gf_options_group', 'wicket_gf_pagination_sidebar_layout', null);
        // DISABLED: DB-backed MdpSyncLogger retention setting (sync logging now uses Wicket()->log())
        // register_setting('wicket_gf_options_group', \WicketGF\MdpSyncLogger::get_retention_option_key(), [
        //     'sanitize_callback' => 'absint',
        //     'default' => 30,
        // ]);
    }

    public static function register_options_page()
    {
        add_submenu_page('gf_edit_forms', __('Wicket Slugs', 'wicket-gf'), __('Wicket Slugs', 'wicket-gf'), 'manage_options', 'wicket_gf', [self::class, 'options_page_redirect']);
    }

    /**
     * Redirect the legacy Wicket Slugs page to the GF Settings Wicket tab.
     */
    public static function options_page_redirect()
    {
        wp_safe_redirect(admin_url('admin.php?page=gf_settings&subview=wicket'));
        exit;
    }

    /**
     * Add the Wicket tab to Gravity Forms global settings navigation.
     *
     * @param array $setting_tabs Existing settings tabs.
     * @return array Modified tabs.
     */
    public static function register_gf_settings_tab($setting_tabs)
    {
        $setting_tabs['19'] = [
            'name'  => 'wicket',
            'label' => __('Wicket', 'wicket-gf'),
            'icon'  => 'gform-icon--cog',
        ];

        return $setting_tabs;
    }

    /**
     * Render the Wicket settings page inside GF global settings.
     */
    public static function render_gf_settings_page()
    { ?>
<form id="gform-settings" class="gform_settings_form" method="post" action="options.php">
    <?php settings_fields('wicket_gf_options_group'); ?>

    <fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title">
        <legend class="gform-settings-panel__title gform-settings-panel__title--header"><?php esc_html_e('Wicket Settings', 'wicket-gf'); ?></legend>
        <div class="gform-settings-panel__content">

        <style>
            .wicket-gf-mapping-row {
                display: flex;
                margin-bottom: 8px;
                align-items: center;
                gap: 6px;
            }
            .wicket-gf-mapping-row input[type="text"] {
                flex: 1;
                max-width: 220px;
                min-width: 120px;
            }
            .wicket-gf-mapping-row .button {
                min-width: 30px;
                flex-shrink: 0;
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

        <h3><?php esc_html_e('Form Slug ID Mapping', 'wicket-gf'); ?></h3>

        <div class="notice notice-info inline" style="margin: 10px 0; padding: 10px 12px; border-left-color: #72aee6;">
            <p><strong><?php esc_html_e('Form slugs can also be set per-form.', 'wicket-gf'); ?></strong></p>
            <p><?php esc_html_e('Go to any form → Settings → Wicket Settings and set the Form Slug field. Both this page and the per-form settings read and write the same storage, so changes are always in sync.', 'wicket-gf'); ?></p>
        </div>

        <p>
            <?php esc_html_e('The mappings below tell the rest of the site which form slugs correspond to which Gravity Form IDs. Changes made here or in a form’s Wicket Settings tab update the same mapping.', 'wicket-gf'); ?>
        </p>

        <p>
            <?php esc_html_e('Reference forms by slug in code using the', 'wicket-gf'); ?> <code>wicket_gf_get_form_id_by_slug()</code> <?php esc_html_e('function.', 'wicket-gf'); ?>
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

                            const submitButton = document.querySelector('#gform-settings-save');
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

            <?php
            // DISABLED: DB-backed MDP Sync Logging UI (sync logging now uses Wicket()->log())
            /*
            <h3><?php _e('MDP Sync Logging', 'wicket-gf'); ?></h3>
            <div class="wicket_pagination_settings">
                <label for="<?php echo esc_attr(\WicketGF\MdpSyncLogger::get_retention_option_key()); ?>"><?php _e('Log Retention (days, 0 = keep forever)', 'wicket-gf'); ?></label>
                <input type="number" name="<?php echo esc_attr(\WicketGF\MdpSyncLogger::get_retention_option_key()); ?>"
                    id="<?php echo esc_attr(\WicketGF\MdpSyncLogger::get_retention_option_key()); ?>"
                    value="<?php echo esc_attr(get_option(\WicketGF\MdpSyncLogger::get_retention_option_key(), 30)); ?>"
                    min="0" max="365" class="small-text" />
            </div>
            */
        ?>

        </div>
    </fieldset>

    <div class="gform-settings-save-container">
        <button type="submit" id="gform-settings-save" class="primary button large">
            <?php esc_html_e('Save Settings', 'gravityforms'); ?> &nbsp;&rarr;
        </button>
    </div>
</form>
<?php
    }

    public static function register_meta_box($meta_boxes, $entry, $form)
    {
        $meta_boxes[] = [
            'title'    => __('MDP Sync Status', 'wicket-gf'),
            'callback' => [self::class, 'render_mdp_sync_status_meta_box'],
            'context'  => 'side',
            'priority' => 'high',
        ];

        return $meta_boxes;
    }

    /**
     * Render the MDP Sync Status meta box on the entry detail page.
     *
     * @param array $entry GF entry object.
     * @param array $form  GF form object.
     */
    public static function render_mdp_sync_status_meta_box($entry, $form): void
    {
        $entry_id = (int) ($entry['id'] ?? 0);
        if ($entry_id <= 0) {
            echo '<p>' . esc_html__('No sync data available.', 'wicket-gf') . '</p>';

            return;
        }

        // Retrieve sync status from entry meta
        $meta = gform_get_meta($entry_id, MdpSyncEngine::get_meta_key());

        if (empty($meta) || !is_array($meta)) {
            echo '<p>' . esc_html__('No MDP sync record found for this entry.', 'wicket-gf') . '</p>';

            return;
        }

        $status = esc_html($meta['status'] ?? 'unknown');
        $message = esc_html($meta['message'] ?? '');
        $time = esc_html($meta['timestamp'] ?? '');
        $objects = $meta['objects'] ?? [];

        // Status badge colors
        $colors = [
            'success' => '#2ea043',
            'failed'  => '#d63638',
            'pending' => '#dba617',
            'skipped' => '#6c757d',
        ];
        $color = $colors[$status] ?? '#6c757d';

        echo '<div style="padding:8px 0;">';

        // Status badge
        printf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:3px;color:#fff;background:%s;font-weight:600;font-size:12px;">%s</span>',
            esc_attr($color),
            strtoupper($status)
        );

        // Timestamp
        if ($time !== '') {
            printf('<p style="margin:6px 0 0;font-size:12px;color:#666;">%s</p>', $time);
        }

        // Message
        if ($message !== '') {
            printf('<p style="margin:6px 0 0;font-size:13px;">%s</p>', $message);
        }

        // Objects synced
        if (!empty($objects) && is_array($objects)) {
            echo '<ul style="margin:6px 0 0;padding-left:16px;font-size:12px;">';
            foreach ($objects as $obj => $ok) {
                $icon = $ok ? '✓' : '✗';
                $obj_color = $ok ? '#2ea043' : '#d63638';
                printf(
                    '<li><span style="color:%s;">%s</span> %s</li>',
                    esc_attr($obj_color),
                    $icon,
                    esc_html($obj)
                );
            }
            echo '</ul>';
        }

        echo '</div>';
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
