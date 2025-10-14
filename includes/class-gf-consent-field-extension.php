<?php

/**
 * Class to extend Gravity Forms consent field functionality.
 *
 * This class adds a new option to consent fields in Gravity Forms
 * and provides methods to act when that option is enabled.
 */
class GFWicket_Consent_Field_Extension
{
    /**
     * Instance of this class.
     * @var GFWicket_Consent_Field_Extension|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     * @return GFWicket_Consent_Field_Extension
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor. Hooks into WordPress and Gravity Forms.
     */
    private function __construct()
    {
        // Hook into Gravity Forms tooltips to add our tooltip
        add_filter('gform_tooltips', [$this, 'add_field_tooltip']);

        // Hook into form submission to process our custom functionality
        add_action('gform_after_submission', [$this, 'process_consent_extension'], 10, 2);
    }

    /**
     * Add custom setting to consent fields.
     * This follows the same pattern as other Wicket field custom settings.
     *
     * @param int $position The position where settings should be added
     * @param int $form_id The ID of the current form
     */
    public static function custom_settings($position, $form_id)
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
                    <?php esc_html_e('Enable this option to automatically update the user\'s communication preferences in the MDP system when consent is given.', 'wicket-gf'); ?>
                </div>
            </li>
        <?php
        }
    }

    /**
     * Add tooltip for our custom setting.
     *
     * @param array $tooltips Existing tooltips
     * @return array Modified tooltips
     */
    public function add_field_tooltip($tooltips)
    {
        $tooltips['form_field_wicket_consent_extension_tooltip'] = __(
            'When enabled, this option will automatically update the user\'s communication preferences in the MDP system, enabling all email communications and subscription lists when the consent field is checked.',
            'wicket-gf'
        );

        return $tooltips;
    }

    /**
     * Process the custom consent extension functionality.
     *
     * This method is called after form submission and will only
     * execute the custom logic if our extension option is enabled
     * and the consent field was checked.
     *
     * @param array $entry The submitted entry data
     * @param array $form The form configuration
     */
    public function process_consent_extension($entry, $form)
    {
        $logger = wc_get_logger();

        // Loop through all fields in the form
        foreach ($form['fields'] as $field) {
            // Check if this is a consent field with our extension enabled
            if (
                $field->type === 'consent' &&
                isset($field->wicketConsentExtensionEnabled) &&
                $field->wicketConsentExtensionEnabled === true
            ) {

                // Get the field value from the entry - try both methods for consent fields
                $field_value = rgar($entry, $field->id);
                $field_value_alt = rgar($entry, $field->id . '.1'); // Consent checkbox format

                // Check if consent was given (try both methods)
                $consent_given = !empty($field_value) || !empty($field_value_alt);

                if ($consent_given) {
                    $logger->info('MDP sync triggered for consent field ' . $field->id . ', entry ' . $entry['id'], ['source' => 'wicket-gf']);
                    // Execute our custom functionality
                    $this->execute_custom_functionality($entry, $form, $field);
                }
            }
        }
    }

    /**
     * Execute the custom functionality when enabled consent field is checked.
     *
     * This method updates the user's communication preferences in the MDP system
     * when the consent field with "Sync option to MDP" enabled is checked.
     *
     * @param array $entry The submitted entry data
     * @param array $form The form configuration
     * @param GF_Field $field The consent field object
     */
    private function execute_custom_functionality($entry, $form, $field)
    {
        $logger = wc_get_logger();

        // Get the current user's UUID from WordPress session
        $person_uuid = wicket_current_person_uuid();

        if (empty($person_uuid)) {
            $logger->error('MDP sync failed: No valid user UUID found', ['source' => 'wicket-gf']);

            return;
        }

        // Check if the communication helper functions exist
        if (!function_exists('wicket_person_enable_all_communications')) {
            $logger->error('MDP sync failed: Communication helper functions not available', ['source' => 'wicket-gf']);

            return;
        }

        try {
            // Enable all communication preferences for the user (will use default sublists and current user UUID)
            $result = wicket_person_enable_all_communications();

            if ($result) {
                $logger->info('MDP sync successful for user ' . $person_uuid, ['source' => 'wicket-gf']);

                // Add custom entry meta to track the sync
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_success', true, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_person_uuid', $person_uuid, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_timestamp', current_time('mysql'), $form['id']);
            } else {
                $logger->error('MDP sync failed for user ' . $person_uuid, ['source' => 'wicket-gf']);

                // Add entry meta to track the failure
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_failed', true, $form['id']);
                gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_error_timestamp', current_time('mysql'), $form['id']);
            }
        } catch (Exception $e) {
            $logger->error('MDP sync exception: ' . $e->getMessage(), ['source' => 'wicket-gf']);

            // Add entry meta to track the exception
            gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_exception', true, $form['id']);
            gform_add_meta($entry['id'], 'wicket_consent_mdp_sync_exception_message', $e->getMessage(), $form['id']);
        }
    }

    /**
     * Output field editor script to handle the consent field setting visibility.
     * This follows the same pattern as other Wicket field editor scripts.
     */
    public static function editor_script()
    {
        ?>
        <script type="text/javascript">
            // Use jQuery-based GF editor events for reliability (matches working field patterns)
            jQuery(document).ready(function($) {
                // When settings panel loads for a field
                $(document).on('gform_load_field_settings', function(event, field) {
                    if (field.type === 'consent') {
                        // Show the consent extension setting for consent fields
                        $('.wicket_consent_extension_setting').show();

                        // Load the current setting value
                        var checkbox = document.getElementById('wicket_consent_extension_enabled');
                        if (checkbox) {
                            checkbox.checked = field.wicketConsentExtensionEnabled || false;
                        }
                    } else {
                        // Hide the setting for non-consent fields
                        $('.wicket_consent_extension_setting').hide();
                    }
                });
            });
        </script>
<?php
    }
}

// Initialize the extension
GFWicket_Consent_Field_Extension::get_instance();
