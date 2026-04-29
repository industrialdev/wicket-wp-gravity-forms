<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom GF field that stores the current user's MDP tags as a comma-separated hidden value.
 */
class UserMdpTags extends \GF_Field
{
    public $type = 'wicket_user_mdp_tags';

    public function __construct($data = [])
    {
        parent::__construct($data);

        if (empty($this->label)) {
            $this->label = 'Wicket User Tags (Hidden)';
        }
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket User Tags', 'wicket-gf');
    }

    public function get_form_editor_button()
    {
        return [
            'group' => 'wicket_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function get_form_editor_field_settings()
    {
        return [
            'label_setting',
            'admin_label_setting',
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'visibility_setting',
            'wicket_mdp_tag_source_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.mdpTagSource = 'combined';
                field.mdpTagDisplayMode = 'hidden';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public function get_form_editor_field_description()
    {
        return esc_attr__("Automatically retrieves the current user's tags from Wicket and stores them as a comma-separated list.", 'wicket-gf');
    }

    public static function custom_settings($position, $form_id): void
    {
        if ($position !== 25) {
            return;
        }
        ?>
        <li class="wicket_mdp_tag_source_setting field_setting" style="display:none;">
            <label for="mdpTagSource" class="section_label">
                <?php esc_html_e('MDP Tag Source', 'wicket-gf'); ?>
            </label>
            <select id="mdpTagSource" onchange="SetFieldProperty('mdpTagSource', this.value);">
                <option value="combined"><?php esc_html_e('Combined (segment_tags + tags) (Default)', 'wicket-gf'); ?></option>
                <option value="segment_tags"><?php esc_html_e('Active/Segment Tags', 'wicket-gf'); ?></option>
                <option value="tags"><?php esc_html_e('Standard Tags (tags)', 'wicket-gf'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Select which tag list is stored in this field.', 'wicket-gf'); ?></p>
            <p class="description" style="margin-top: 8px;">
                <strong><?php esc_html_e('Note:', 'wicket-gf'); ?></strong>
                <?php esc_html_e('Changing this source changes which tags are available to conditional logic. If rules unexpectedly stop matching, switch back to Combined or align your rules with the selected source.', 'wicket-gf'); ?>
            </p>
        </li>
        <li class="wicket_mdp_tag_source_setting field_setting" style="display:none;">
            <label for="mdpTagDisplayMode" class="section_label">
                <?php esc_html_e('Display Mode', 'wicket-gf'); ?>
            </label>
            <select id="mdpTagDisplayMode" onchange="SetFieldProperty('mdpTagDisplayMode', this.value);">
                <option value="hidden"><?php esc_html_e('Hidden (Default)', 'wicket-gf'); ?></option>
                <option value="debug_disabled"><?php esc_html_e('Debug: Show Disabled Input', 'wicket-gf'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Use Debug mode temporarily to visualize pulled tags on the frontend. Keep Hidden in production.', 'wicket-gf'); ?></p>
        </li>
        <script type="text/javascript">
            jQuery(document).on('gform_load_field_settings', function(event, field){
                if (field.type !== 'wicket_user_mdp_tags') {
                    return;
                }
                jQuery('#mdpTagSource').val(field.mdpTagSource || 'combined');
                jQuery('#mdpTagDisplayMode').val(field.mdpTagDisplayMode || 'hidden');
            });

            if (window.gform && gform.addAction) {
                gform.addAction('gform_editor_js_set_field_properties', function(field) {
                    if (field.type !== 'wicket_user_mdp_tags') {
                        return;
                    }
                    if (!field.mdpTagSource) { field.mdpTagSource = 'combined'; }
                    if (!field.mdpTagDisplayMode) { field.mdpTagDisplayMode = 'hidden'; }
                });
            }
        </script>
        <?php
    }

    public static function editor_script(): void
    {
        // JavaScript embedded in custom_settings()
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<div style="color: #666; font-size: 12px; margin-top: 5px;">Hidden field - will contain user\'s MDP tags on form load</div>';
        }

        $source = $this->mdpTagSource ?? 'combined';
        $value = self::get_user_tags_by_source((string) $source);
        $id = (int) $this->id;
        $field_id = $form['id'] . '_' . $id;

        $hidden_input = sprintf(
            "<input name='input_%d' id='input_%s' type='hidden' value='%s' class='gform_hidden'/>",
            $id,
            $field_id,
            esc_attr($value)
        );

        if (($this->mdpTagDisplayMode ?? 'hidden') === 'debug_disabled') {
            $debug_summary = self::build_debug_summary((string) $source, $value);

            return sprintf(
                "<div class='ginput_container ginput_container_text wicket_mdp_tags_debug'>%s<input id='input_%s_debug' type='text' value='%s' class='medium' disabled='disabled' readonly='readonly' /><div style='margin-top:6px;font-size:12px;color:#666;'><code>%s</code></div></div>",
                $hidden_input,
                esc_attr($field_id),
                esc_attr($value),
                esc_html($debug_summary)
            );
        }

        return sprintf(
            "<style>.gform_wrapper.gravity-theme label[for='input_%s'].gfield_label { display: none; }</style><div style='display:none;' class='ginput_container ginput_container_hidden'>%s</div>",
            $field_id,
            $hidden_input
        );
    }

    public function is_conditional_logic_supported(): bool
    {
        return true;
    }

    public static function get_user_tags_by_source(string $source = 'combined'): string
    {
        $person = self::get_current_person_resource();
        if (!$person) {
            return '';
        }

        $segment_tags = self::extract_tag_array($person, 'segment_tags');
        $tags = self::extract_tag_array($person, 'tags');

        $selected = match ($source) {
            'tags'        => $tags,
            'combined'    => array_values(array_unique(array_merge($segment_tags, $tags))),
            default       => $segment_tags,
        };

        return implode(',', $selected);
    }

    private static function get_current_person_resource(): mixed
    {
        if (function_exists('wicket_current_person_uuid') && function_exists('wicket_get_person_by_id')) {
            $uuid = wicket_current_person_uuid();
            if (!empty($uuid)) {
                $person = wicket_get_person_by_id($uuid);
                if ($person) {
                    return $person;
                }
            }
        }

        if (function_exists('wicket_current_person')) {
            $person = wicket_current_person();
            if ($person) {
                return $person;
            }
        }

        if (function_exists('wicket_current_person_uuid') && function_exists('wicket_get_person_profile')) {
            $uuid = wicket_current_person_uuid();
            if (!empty($uuid)) {
                $profile = wicket_get_person_profile($uuid);
                if (!empty($profile)) {
                    return $profile;
                }
            }
        }

        return null;
    }

    private static function extract_tag_array(mixed $person, string $key): array
    {
        $raw = null;

        if (is_object($person)) {
            if (method_exists($person, 'getAttribute')) {
                $raw = $person->getAttribute($key);
            } elseif (property_exists($person, $key)) {
                $raw = $person->{$key};
            } elseif (property_exists($person, 'attributes') && is_array($person->attributes) && array_key_exists($key, $person->attributes)) {
                $raw = $person->attributes[$key];
            } elseif (method_exists($person, 'toJsonAPI')) {
                try {
                    $r = $person->toJsonAPI();
                    $raw = $r['data']['attributes'][$key] ?? $r['attributes'][$key] ?? null;
                } catch (\Exception $e) {
                    $raw = null;
                }
            }
        } elseif (is_array($person)) {
            $raw = $person['data']['attributes'][$key] ?? $person['attributes'][$key] ?? $person[$key] ?? null;
        }

        if (!is_array($raw)) {
            return [];
        }

        $tags = [];
        foreach ($raw as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }
            $normalized = trim((string) $tag);
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }

        return array_values(array_unique($tags));
    }

    private static function build_debug_summary(string $source, string $resolved_value): string
    {
        $uuid = function_exists('wicket_current_person_uuid') ? (string) wicket_current_person_uuid() : '';
        $by_id = ($uuid !== '' && function_exists('wicket_get_person_by_id')) ? wicket_get_person_by_id($uuid) : null;
        $current = function_exists('wicket_current_person') ? wicket_current_person() : null;
        $profile = ($uuid !== '' && function_exists('wicket_get_person_profile')) ? wicket_get_person_profile($uuid) : null;

        return implode(' | ', [
            'src=' . $source,
            'uuid=' . ($uuid !== '' ? $uuid : 'none'),
            'resolved=' . ($resolved_value !== '' ? $resolved_value : '(empty)'),
            'by_id(t=' . count(self::extract_tag_array($by_id, 'tags')) . ',s=' . count(self::extract_tag_array($by_id, 'segment_tags')) . ')',
            'current(t=' . count(self::extract_tag_array($current, 'tags')) . ',s=' . count(self::extract_tag_array($current, 'segment_tags')) . ')',
            'profile(t=' . count(self::extract_tag_array($profile, 'tags')) . ',s=' . count(self::extract_tag_array($profile, 'segment_tags')) . ')',
        ]);
    }
}
