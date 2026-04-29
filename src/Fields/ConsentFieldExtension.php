<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extends GF consent fields with an "Sync option to MDP" setting
 * that updates communication preferences in the MDP on consent.
 */
class ConsentFieldExtension
{
    private static ?self $instance = null;

    public static function get_instance(): static
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('gform_tooltips', [$this, 'add_field_tooltip']);
        add_action('gform_after_submission', [$this, 'process_consent_extension'], 10, 2);
    }

    public static function custom_settings(int $position, int $form_id): void
    {
        if ($position == 25) {
            ?>
            <li class="wicket_consent_extension_setting field_setting" style="display:none;">
                <input type="checkbox" id="wicket_consent_extension_enabled"
                    onchange="SetFieldProperty('wicketConsentExtensionEnabled', this.checked);" />
                <label for="wicket_consent_extension_enabled" class="inline">
                    <?php esc_html_e('Sync option to MDP', 'wicket-gf'); ?>
                    <?php gform_tooltip('form_field_wicket_consent_extension_tooltip'); ?>
                </label>
                <div class="wicket-consent-extension-description" style="margin-top: 8px; font-size: 12px; color: #666;">
                    <?php esc_html_e("Enable this option to automatically update the user's communication preferences in the MDP system when consent is given.", 'wicket-gf'); ?>
                </div>
            </li>
            <?php
        }
    }

    public function add_field_tooltip(array $tooltips): array
    {
        $tooltips['form_field_wicket_consent_extension_tooltip'] = __(
            "When enabled, this option will automatically update the user's communication preferences in the MDP system, enabling all email communications and subscription lists when the consent field is checked.",
            'wicket-gf'
        );

        return $tooltips;
    }

    public function process_consent_extension(array $entry, array $form): void
    {
        foreach ($form['fields'] as $field) {
            if (
                $field->type === 'consent'
                && isset($field->wicketConsentExtensionEnabled)
                && $field->wicketConsentExtensionEnabled === true
            ) {
                $consent_given = !empty(rgar($entry, $field->id)) || !empty(rgar($entry, $field->id . '.1'));

                if ($consent_given) {
                    Wicket()->log()->info('MDP sync triggered for consent field ' . $field->id . ', entry ' . $entry['id'], ['source' => 'wicket-gf']);
                    $this->execute_custom_functionality($entry, $form, $field);
                }
            }
        }
    }

    private function execute_custom_functionality(array $entry, array $form, $field): void
    {
        $person_uuid = wicket_current_person_uuid();

        if (empty($person_uuid)) {
            Wicket()->log()->error('MDP sync failed: No valid user UUID found', ['source' => 'wicket-gf']);

            return;
        }

        if (!function_exists('wicket_person_enable_all_communications')) {
            Wicket()->log()->error('MDP sync failed: Communication helper functions not available', ['source' => 'wicket-gf']);

            return;
        }

        try {
            $result = wicket_person_enable_all_communications();

            if ($result) {
                Wicket()->log()->info('MDP sync successful for user ' . $person_uuid, ['source' => 'wicket-gf']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_success', true, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_person_uuid', $person_uuid, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_timestamp', current_time('mysql'), $form['id']);
            } else {
                Wicket()->log()->error('MDP sync failed for user ' . $person_uuid, ['source' => 'wicket-gf']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_failed', true, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_error_timestamp', current_time('mysql'), $form['id']);
            }
        } catch (\Exception $e) {
            Wicket()->log()->error('MDP sync exception: ' . $e->getMessage(), ['source' => 'wicket-gf']);
            gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_exception', true, $form['id']);
            gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_exception_message', $e->getMessage(), $form['id']);
        }
    }

    public static function editor_script(): void
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('gform_load_field_settings', function(event, field) {
                    if (field.type === 'consent') {
                        $('.wicket_consent_extension_setting').show();
                        var checkbox = document.getElementById('wicket_consent_extension_enabled');
                        if (checkbox) {
                            checkbox.checked = field.wicketConsentExtensionEnabled || false;
                        }
                    } else {
                        $('.wicket_consent_extension_setting').hide();
                    }
                });
            });
        </script>
        <?php
    }
}
