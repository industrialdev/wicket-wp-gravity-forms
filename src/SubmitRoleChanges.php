<?php

declare(strict_types=1);

namespace WicketGF;

// No direct access
defined('ABSPATH') || exit;

/**
 * Submit Role Changes.
 *
 * Per-form opt-ins on the Wicket settings tab that remove and add WP roles
 * for the logged-in submitter after a form submission. The change lasts
 * until the next role sync from the MDP (CAS login), which resets all roles.
 *
 * Only roles with no more capabilities than Subscriber can be selected or
 * applied, so a form editor can never grant or revoke privileged access.
 *
 * @see atlas://plans/feature-gf-add-roles-on-submit.md
 */
class SubmitRoleChanges
{
    /**
     * Form meta keys. Saved forms store these, so they must not change.
     */
    public const ADD_ENABLED = 'wicket_submit_add_roles_enabled';
    public const ADD_ROLES = 'wicket_submit_add_roles';
    public const REMOVE_ENABLED = 'wicket_submit_remove_roles_enabled';
    public const REMOVE_ROLES = 'wicket_submit_remove_roles';

    /**
     * Register the submission hook.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('gform_after_submission', [$this, 'apply_role_changes'], 10, 2);
    }

    /**
     * Remove, then add, the form's selected roles for the logged-in submitter.
     *
     * Saved roles are filtered again here because a role can be deleted or
     * gain capabilities after the form was saved.
     *
     * @param array<string, mixed> $entry The submitted entry.
     * @param array<string, mixed> $form  The submitted form.
     *
     * @return void
     */
    public function apply_role_changes($entry, $form): void
    {
        unset($entry);

        if (!is_array($form) || !is_user_logged_in()) {
            return;
        }

        $remove = self::get_enabled_roles($form, self::REMOVE_ENABLED, self::REMOVE_ROLES);
        $add = self::get_enabled_roles($form, self::ADD_ENABLED, self::ADD_ROLES);
        if ($remove === [] && $add === []) {
            return;
        }

        $user = wp_get_current_user();
        foreach ($remove as $role) {
            $user->remove_role($role);
        }
        foreach ($add as $role) {
            $user->add_role($role);
        }
    }

    /**
     * Roles that may be added or removed: no more capabilities than Subscriber.
     *
     * MDP-synced roles qualify because the role sync clones them from Subscriber.
     *
     * @return array<string, string> Role slug => translated role name.
     */
    public static function get_allowed_roles(): array
    {
        $subscriber = get_role('subscriber');
        if (!$subscriber) {
            return [];
        }

        $base_caps = array_keys(array_filter((array) $subscriber->capabilities));
        $allowed = [];
        foreach (wp_roles()->roles as $slug => $role) {
            $caps = array_keys(array_filter((array) ($role['capabilities'] ?? [])));
            if (array_diff($caps, $base_caps) === []) {
                $allowed[(string) $slug] = translate_user_role((string) ($role['name'] ?? $slug));
            }
        }

        return $allowed;
    }

    /**
     * Keep only the slugs that are allowed roles.
     *
     * @param mixed $roles Role slugs from form meta or the settings POST.
     *
     * @return string[] Unique allowed role slugs.
     */
    public static function filter_allowed_roles($roles): array
    {
        if (!is_array($roles)) {
            return [];
        }

        $allowed = self::get_allowed_roles();
        $slugs = array_filter($roles, fn ($role) => is_string($role) && isset($allowed[$role]));

        return array_values(array_unique($slugs));
    }

    /**
     * Save the role settings from the Wicket settings tab POST to the form.
     *
     * A disabled opt-in saves no roles, so a hidden list cannot trigger the
     * overlap check. When a role is in both lists, nothing saves and an error
     * names the roles.
     *
     * @param array<string, mixed> $form The form being edited.
     * @param array<string, mixed> $post Unslashed POST data.
     *
     * @return array<string, mixed> The form, updated when the save succeeds.
     */
    public static function save_settings(array $form, array $post): array
    {
        $settings = [];
        foreach ([self::ADD_ENABLED => self::ADD_ROLES, self::REMOVE_ENABLED => self::REMOVE_ROLES] as $enabled_key => $roles_key) {
            $enabled = !empty($post[$enabled_key]);
            $settings[$enabled_key] = $enabled ? '1' : '0';
            $settings[$roles_key] = $enabled ? self::filter_allowed_roles($post[$roles_key] ?? []) : [];
        }

        $overlap = array_intersect($settings[self::ADD_ROLES], $settings[self::REMOVE_ROLES]);
        if ($overlap !== []) {
            $names = array_intersect_key(self::get_allowed_roles(), array_flip($overlap));
            \GFCommon::add_error_message(sprintf(
                esc_html__('Role settings not saved. A role cannot be in both the add and remove lists: %s.', 'wicket-gf'),
                esc_html(implode(', ', $names))
            ));

            return $form;
        }

        $form = array_merge($form, $settings);
        \GFAPI::update_form($form);

        return $form;
    }

    /**
     * Render the role settings panel on the Wicket settings tab.
     *
     * @param array<string, mixed> $form The form being edited.
     *
     * @return void
     */
    public static function render_settings(array $form): void
    {
        $allowed = self::get_allowed_roles();
        $sections = [
            [
                'enabled_key' => self::ADD_ENABLED,
                'roles_key' => self::ADD_ROLES,
                'label' => __('Add roles on submission', 'wicket-gf'),
            ],
            [
                'enabled_key' => self::REMOVE_ENABLED,
                'roles_key' => self::REMOVE_ROLES,
                'label' => __('Remove roles on submission', 'wicket-gf'),
            ],
        ];
        ?>
        <fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title" id="wicket-submit-roles">
            <legend class="gform-settings-panel__title gform-settings-panel__title--header"><?php esc_html_e('Submission Roles', 'wicket-gf'); ?></legend>
            <div class="gform-settings-panel__content">
                <?php foreach ($sections as $section):
                    $enabled = ($form[$section['enabled_key']] ?? '') === '1';
                    $selected = self::filter_allowed_roles($form[$section['roles_key']] ?? []);
                    ?>
                <div class="gform-settings-field">
                    <label>
                        <input type="checkbox" class="wicket-submit-roles-toggle" name="<?php echo esc_attr($section['enabled_key']); ?>" value="1" <?php echo $enabled ? 'checked' : ''; ?> />
                        <?php echo esc_html($section['label']); ?>
                    </label>
                    <div class="wicket-submit-roles-options" <?php echo $enabled ? '' : 'hidden'; ?> style="margin-top:8px;">
                        <p class="notice notice-warning inline" style="margin:0 0 8px;padding:8px 12px;"><?php esc_html_e("Roles change only for the current user's login session. If CAS login is on, the next login resets the roles to the roles from the MDP.", 'wicket-gf'); ?></p>
                        <select class="wicket-submit-roles-select" name="<?php echo esc_attr($section['roles_key']); ?>[]" multiple>
                            <?php foreach ($allowed as $slug => $name): ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php echo in_array($slug, $selected, true) ? 'selected' : ''; ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
        // Gravity Forms registers Chosen (tag select with type-ahead) and
        // whitelists its own handles in no-conflict mode.
        wp_enqueue_style('gform_chosen');
        wp_enqueue_script('gform_chosen');
        wp_add_inline_script('gform_chosen', str_replace(
            '__PLACEHOLDER__',
            wp_json_encode(__('Type or select roles', 'wicket-gf')),
            <<<'JS'
                jQuery(function ($) {
                    var $panel = $('#wicket-submit-roles');
                    var $selects = $panel.find('.wicket-submit-roles-select');

                    // A role picked in one list is disabled in the other list. A list
                    // that is switched off saves no roles, so it blocks nothing.
                    function syncDisabled() {
                        $selects.each(function (i) {
                            var active = !$(this).closest('.wicket-submit-roles-options').prop('hidden');
                            var picked = active ? ($(this).val() || []) : [];
                            $selects.eq(1 - i).find('option').each(function () {
                                this.disabled = picked.indexOf(this.value) !== -1;
                            });
                        });
                        $selects.trigger('chosen:updated');
                    }

                    $selects.chosen({ width: '100%', search_contains: true, placeholder_text_multiple: __PLACEHOLDER__ })
                        .on('change', syncDisabled);
                    $panel.find('.wicket-submit-roles-toggle').on('change', function () {
                        $(this).closest('.gform-settings-field').find('.wicket-submit-roles-options').prop('hidden', !this.checked);
                        syncDisabled();
                    });
                    syncDisabled();
                });
                JS
        ));
    }

    /**
     * Allowed roles for one opt-in, or none when the opt-in is off.
     *
     * @param array<string, mixed> $form        The form.
     * @param string               $enabled_key Form meta key of the checkbox.
     * @param string               $roles_key   Form meta key of the role list.
     *
     * @return string[] Role slugs.
     */
    private static function get_enabled_roles(array $form, string $enabled_key, string $roles_key): array
    {
        if (($form[$enabled_key] ?? '') !== '1') {
            return [];
        }

        return self::filter_allowed_roles($form[$roles_key] ?? []);
    }
}
