/**
 * Wicket Gravity Forms Live Update - GF Native Version
 *
 * Handles live updates for GFWicketDataHiddenField fields from Wicket Widgets on the same page.
 * Uses Gravity Forms native hooks for better integration and reliability.
 */

/**
 * Global field mapping configuration
 * Maps PHP field names (snake_case) to JavaScript object properties (camelCase)
 * from the MDP API when used in JS widgets
 */
const WICKET_FIELD_MAPPINGS = {
    // Address fields
    addresses: {
        country_code: 'countryCode',
        country_name: 'countryName',
        state_name: 'stateName',
        zip_code: 'zipCode',
        address_1: 'address1',
        address_2: 'address2',
        company_name: 'companyName',
        type_external_id: 'typeExternalId',
        created_at: 'createdAt',
        updated_at: 'updatedAt',
        deleted_at: 'deletedAt',
        formatted_address_label: 'formattedAddressLabel',
        consent_directory: 'consentDirectory',
        consent_third_party: 'consentThirdParty'
    },

    // Person attributes
    attributes: {
        given_name: 'givenName',
        family_name: 'familyName',
        additional_name: 'additionalName',
        alternate_name: 'alternateName',
        maiden_name: 'maidenName',
        full_name: 'fullName',
        honorific_prefix: 'honorificPrefix',
        honorific_suffix: 'honorificSuffix',
        preferred_pronoun: 'preferredPronoun',
        job_title: 'jobTitle',
        job_function: 'jobFunction',
        job_level: 'jobLevel',
        birth_date: 'birthDate',
        created_at: 'createdAt',
        updated_at: 'updatedAt',
        deleted_at: 'deletedAt',
        languages_spoken: 'languagesSpoken',
        languages_written: 'languagesWritten',
        membership_action: 'membershipAction',
        membership_began_on: 'membershipBeganOn',
        membership_number: 'membershipNumber',
        person_type: 'personType',
        person_type_external_id: 'personTypeExternalId',
        resource_type_id: 'resourceTypeId',
        identifying_number: 'identifyingNumber',
        segment_tags: 'segmentTags',
        role_names: 'roleNames',
        primary_email_address: 'primaryEmailAddress',
        current_membership_summary: 'currentMembershipSummary',
        previous_membership_summary: 'previousMembershipSummary',
        available_membership_summaries: 'availableMembershipSummaries',
        previous_membership_summaries: 'previousMembershipSummaries',
        data_fields: 'dataFields'
    },

    // Email fields
    emails: {
        local_part: 'localpart',
        type_external_id: 'typeExternalId',
        created_at: 'createdAt',
        updated_at: 'updatedAt',
        deleted_at: 'deletedAt',
        consent_directory: 'consentDirectory',
        consent_third_party: 'consentThirdParty'
    },

    // Phone fields
    phones: {
        country_code_number: 'countryCodeNumber',
        number_national_format: 'numberNationalFormat',
        number_international_format: 'numberInternationalFormat',
        primary_sms: 'primarySms',
        type_external_id: 'typeExternalId',
        created_at: 'createdAt',
        updated_at: 'updatedAt',
        deleted_at: 'deletedAt',
        consent_directory: 'consentDirectory',
        consent_third_party: 'consentThirdParty'
    },

    // Web address fields
    web_addresses: {
        type_external_id: 'typeExternalId',
        created_at: 'createdAt',
        updated_at: 'updatedAt',
        deleted_at: 'deletedAt',
        consent_directory: 'consentDirectory',
        consent_third_party: 'consentThirdParty'
    }
};

/**
 * Wicket GF Live Update Controller
 * Uses Gravity Forms native hooks for better integration
 */
const WicketGFLiveUpdate = {

    /**
     * Debug logging control
     * Set to false for production to disable all logging
     */
    enableLogging: false,

    /**
     * Centralized logging method
     * @param {string} message - Log message
     * @param {*} data - Optional data to log
     */
    log(message, data = null) {
        if (!this.enableLogging) {
            return;
        }

        if (data !== null) {
            console.log(`Wicket GF: ${message}`, data);
        } else {
            console.log(`Wicket GF: ${message}`);
        }
    },

    /**
     * Centralized warning method
     * @param {string} message - Warning message
     */
    warn(message) {
        if (!this.enableLogging) {
            return;
        }
        console.warn(`Wicket GF: ${message}`);
    },

    /**
     * Initialize the live update system
     */
    init() {
        this.log('Live Update: Initializing GF-native version');

        this.setupGFHooks();
        this.setupWicketEventListeners();
        this.populateFieldsOnInit();
    },

    /**
     * Get the correct field name for a given context and target key
     * @param {string} context - The data context (addresses, emails, phones, etc.)
     * @param {string} targetKey - The field name to look up
     * @returns {string} The correct field name to use
     */
    getFieldName(context, targetKey) {
        const contextMapping = WICKET_FIELD_MAPPINGS[context];
        if (contextMapping && contextMapping[targetKey]) {
            return contextMapping[targetKey];
        }

        // Try automatic camelCase conversion as fallback
        const camelCaseKey = targetKey.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase());
        return camelCaseKey;
    },

    /**
     * Setup Gravity Forms native hooks
     */
    setupGFHooks() {
        // Ensure jQuery is available for fallback
        if (typeof jQuery === 'undefined') {
            this.warn('jQuery not available for form hooks');
            return;
        }

        // Use GF's native hooks if available, fallback to jQuery events
        if (typeof gform !== 'undefined' && gform.addAction) {
            // GF 2.5+ native hooks
            gform.addAction('gform/post_init', (formId) => {
                this.log(`Form ${formId} initialized via gform/post_init`);
                this.processForm(formId);
            });

            gform.addAction('gform/post_render', (formId, currentPage) => {
                this.log(`Form ${formId} rendered, page ${currentPage} via gform/post_render`);
                this.processForm(formId);
            });

            gform.addAction('gform/page_loaded', (formId, currentPage) => {
                this.log(`Form ${formId} page ${currentPage} loaded via gform/page_loaded`);
                this.processForm(formId);
            });
        } else {
            // Fallback to jQuery events for older GF versions
            this.log('Using jQuery fallback events');

            jQuery(document).on('gform_post_render', (event, formId, currentPage) => {
                this.log(`Form ${formId} rendered, page ${currentPage} via gform_post_render`);
                this.processForm(formId);
            });
        }

        // Also process forms immediately if they already exist
        this.processAllForms();
    },

    /**
     * Process all existing forms on page load
     */
    processAllForms() {
        if (typeof jQuery === 'undefined') {
            this.warn('jQuery not available for form processing');
            return;
        }

        // Find all GF forms and process them
        jQuery('form[id^="gform_"]').each((index, formElement) => {
            const $form = jQuery(formElement);
            const formId = $form.attr('id').replace('gform_', '');
            this.log(`Processing existing form ${formId}`);
            this.processForm(formId);
        });

        // Also look for fields directly if no forms found
        const $allWicketFields = jQuery('.wicket-gf-hidden-data-bind-target');
        if ($allWicketFields.length > 0) {
            this.log(`Found ${$allWicketFields.length} wicket fields outside form processing`);
            this.populateFields($allWicketFields);
        }
    },

    /**
     * Process a specific form for wicket fields
     */
    processForm(formId) {
        if (typeof jQuery === 'undefined') {
            return;
        }

        const $form = jQuery(`#gform_${formId}`);
        const $wicketFields = $form.find('.wicket-gf-hidden-data-bind-target');

        if ($wicketFields.length === 0) {
            return;
        }

        this.log(`Found ${$wicketFields.length} wicket fields in form ${formId}`);

        // Populate fields with current data if available
        this.populateFields($wicketFields);
    },

    /**
     * Setup Wicket widget event listeners using simplified approach
     */
    setupWicketEventListeners() {
        // Simplified event interception - catch all wicket events
        const originalDispatchEvent = window.dispatchEvent;
        window.dispatchEvent = function(event) {
            if (event.type && (event.type.includes('wicket') || event.type.includes('wwidget'))) {
                WicketGFLiveUpdate.log('Event intercepted:', event.type);

                if (event.detail && (event.detail.dataFields || event.detail.attributes || event.detail.addresses)) {
                    WicketGFLiveUpdate.handleWicketEvent(event);
                }
            }

            return originalDispatchEvent.call(this, event);
        };
    },

    /**
     * Handle wicket widget events
     */
    handleWicketEvent(event) {
        this.log(`Processing event ${event.type} with data:`, event.detail);

        // Ensure jQuery is available
        if (typeof jQuery === 'undefined') {
            this.warn('jQuery not available, skipping field updates');
            return;
        }

        // Process all wicket fields on all forms
        const $allFields = jQuery('.wicket-gf-hidden-data-bind-target');
        this.log(`Found ${$allFields.length} total wicket fields to process`);

        $allFields.each((index, element) => {
            const $field = jQuery(element);

            // Skip fields disabled by conditional logic, but NOT HTML hidden inputs
            // Hidden inputs (type="hidden") should still be processed
            const isHiddenInput = $field.attr('type') === 'hidden';
            const isDisabled = $field.prop('disabled');
            const isConditionallyHidden = !isHiddenInput && $field.is(':hidden');

            if (isDisabled || isConditionallyHidden) {
                this.log(`Skipping field ${$field.attr('id')} - disabled: ${isDisabled}, conditionally hidden: ${isConditionallyHidden}`);
                return;
            }

            this.updateFieldFromPayload($field, event.detail, event.type);
        });
    },

    /**
     * Update a single field from payload data
     */
    updateFieldFromPayload($field, payload, eventType) {
        const dataSource = $field.data('hidden-data-bind-data-source');
        const schemaSlug = $field.data('hidden-data-bind-schema-slug');
        const valueKey = $field.data('hidden-data-bind-value-key');
        const fieldId = $field.attr('id');

        // Skip fields without proper configuration
        if (!dataSource || !schemaSlug || !valueKey) {
            this.log(`Skipping field ${fieldId} - missing configuration (dataSource: ${dataSource}, schemaSlug: ${schemaSlug}, valueKey: ${valueKey})`);
            return;
        }

        this.log(`Processing field ${fieldId} for ${dataSource}.${schemaSlug}.${valueKey}`);

        const extractedValue = this.extractValue(payload, dataSource, schemaSlug, valueKey);

        if (typeof extractedValue !== 'undefined') {
            this.updateFieldValue($field, extractedValue, eventType);
        } else {
            this.log(`No value extracted for field ${fieldId}`);
        }
    },

    /**
     * Simplified value extraction with clear separation by data source
     */
    extractValue(payload, dataSource, schemaSlug, valueKey) {
        // Add null/undefined checks for all parameters
        if (!payload || !dataSource || !schemaSlug || !valueKey) {
            return undefined;
        }

        switch (dataSource) {
            case 'person_addinfo':
                return this.extractFromDataFields(payload, schemaSlug, valueKey);

            case 'person_profile':
                if (schemaSlug === 'profile_attributes') {
                    return this.extractFromAttributes(payload, valueKey);
                }
                if (schemaSlug.startsWith('profile_')) {
                    return this.extractFromRelationship(payload, schemaSlug, valueKey);
                }
                break;

            case 'organization':
                return this.extractFromOrganization(payload, schemaSlug, valueKey);
        }

        return undefined;
    },

    /**
     * Extract from dataFields array (person_addinfo)
     */
    extractFromDataFields(payload, schemaSlug, valueKey) {
        // Check legacy format first
        if (payload.dataField && payload.dataField.key === schemaSlug) {
            const value = payload.dataField.value;
            return valueKey && typeof value === 'object' ? value[valueKey] : value;
        }

        // Check modern format (dataFields array)
        const dataFields = payload.dataFields || payload.attributes?.dataFields || [];
        const matchingField = dataFields.find(field =>
            field.key === schemaSlug || field.schema_slug === schemaSlug
        );

        if (matchingField?.value) {
            return valueKey ? matchingField.value[valueKey] : matchingField.value;
        }

        return undefined;
    },

    /**
     * Extract from person attributes
     */
    extractFromAttributes(payload, valueKey) {
        const attributes = payload.attributes || payload;

        // Handle special cases
        if (valueKey === '_self') return attributes;
        if (valueKey?.startsWith('user_')) {
            const userField = valueKey.replace('user_', '');
            return attributes.user?.[userField];
        }

        // Use field mapping
        const mappedField = this.getFieldName('attributes', valueKey);
        return attributes[mappedField] !== undefined ? attributes[mappedField] : attributes[valueKey];
    },

    /**
     * Extract from relationship data (addresses, emails, etc.)
     */
    extractFromRelationship(payload, schemaSlug, valueKey) {
        const relationshipType = schemaSlug.replace('profile_', '');

        // Debug logging for address extraction
        if (relationshipType === 'addresses') {
            this.log('Debug: Extracting from addresses', {
                relationshipType,
                valueKey,
                payload: payload,
                addresses: payload.addresses,
                addressesLength: payload.addresses?.length
            });
        }

        // Handle primary address specifically
        if (relationshipType === 'addresses' || relationshipType === 'primary_address') {
            return this.extractFromPrimaryAddress(payload, valueKey);
        }

        // Handle other relationships
        const relationshipData = payload[relationshipType] || [];
        if (relationshipData.length > 0) {
            const firstItem = relationshipData[0];
            const itemData = firstItem.attributes || firstItem;

            if (valueKey === '_self') return itemData;

            // Use field mapping
            const mappingContext = this.getMappingContext(relationshipType);
            const mappedField = this.getFieldName(mappingContext, valueKey);

            return itemData[mappedField] !== undefined ? itemData[mappedField] : itemData[valueKey];
        }

        return undefined;
    },

    /**
     * Extract from primary address
     */
    extractFromPrimaryAddress(payload, valueKey) {
        const addresses = payload.addresses || [];

        this.log('Debug: Primary address extraction', {
            valueKey,
            addressesCount: addresses.length,
            addresses: addresses
        });

        // Try to find primary address first
        let primaryAddress = addresses.find(addr => {
            const attrs = addr.attributes || addr;
            return attrs.primary === true;
        });

        // If no primary address found, try to find any active address
        if (!primaryAddress) {
            primaryAddress = addresses.find(addr => {
                const attrs = addr.attributes || addr;
                return attrs.active === true;
            });
        }

        // If still no address found, use first address
        if (!primaryAddress && addresses.length > 0) {
            primaryAddress = addresses[0];
        }

        this.log('Debug: Selected address', primaryAddress);

        if (primaryAddress) {
            const addressData = primaryAddress.attributes || primaryAddress;

            if (valueKey === '_self') return addressData;

            // Try direct field access first
            if (addressData[valueKey] !== undefined) {
                this.log('Debug: Found direct field', valueKey, addressData[valueKey]);
                return addressData[valueKey];
            }

            // Try mapped field name
            const mappedField = this.getFieldName('addresses', valueKey);
            if (addressData[mappedField] !== undefined) {
                this.log('Debug: Found mapped field', mappedField, addressData[mappedField]);
                return addressData[mappedField];
            }

            this.log('Debug: Field not found', { valueKey, mappedField, availableFields: Object.keys(addressData) });
        }

        return undefined;
    },

    /**
     * Extract from organization data
     */
    extractFromOrganization(payload, schemaSlug, valueKey) {
        if (!payload.organization) return undefined;

        const [schemaKey, propertyKey] = schemaSlug.split('.');
        if (schemaKey && propertyKey && payload.organization.data_fields) {
            const schemaData = payload.organization.data_fields.find(field =>
                field.schema_slug === schemaKey || field.key === schemaKey
            );
            if (schemaData?.value && typeof schemaData.value === 'object') {
                return schemaData.value[propertyKey];
            }
        }

        return undefined;
    },

    /**
     * Get mapping context for relationship type
     */
    getMappingContext(relationshipType) {
        const contextMap = {
            'addresses': 'addresses',
            'emails': 'emails',
            'phones': 'phones',
            'webAddresses': 'web_addresses'
        };

        return contextMap[relationshipType] || relationshipType;
    },

    /**
     * Update field value using GF-compatible method
     */
    updateFieldValue($field, value, source) {
        const currentValue = $field.val();
        const newValue = String(value || '');

        if (currentValue !== newValue) {
            $field.val(newValue);

            // Trigger GF's input change event if available
            if (typeof gform !== 'undefined' && gform.doAction) {
                gform.doAction('gform_input_change', $field[0], newValue, currentValue);
            } else {
                $field.trigger('change');
            }

            this.log(`Field ${$field.attr('id')} updated to '${newValue}' from ${source}`);

            // Trigger form-wide updates after field update
            this.triggerFormUpdate($field);
        }
    },

    /**
     * Trigger Gravity Forms conditional logic and validation after field updates
     */
    triggerFormUpdate($field) {
        const $form = $field.closest('form');

        if ($form.length === 0) {
            return;
        }

        const formId = $form.attr('id')?.replace('gform_', '');

        if (!formId) {
            return;
        }

        this.log(`Triggering form update for form ${formId} after field ${$field.attr('id')} update`);

        // Use GF's native API to trigger conditional logic
        if (typeof gform !== 'undefined') {
            // Trigger conditional logic evaluation immediately
            if (gform.doAction) {
                gform.doAction('gform_post_conditional_logic', formId, [], true);
            }

            // Also trigger input change for the specific field
            if (gform.doAction) {
                gform.doAction('gform_input_change', $field[0], $field.val(), '');
            }

            // Trigger form validation and re-render
            setTimeout(() => {
                $form.trigger('change');
                $field.trigger('change');

                // Apply conditional logic rules with force flag
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [], true);
                }

                // Also try the newer conditional logic function if available
                if (window.gf_conditional_logic) {
                    window.gf_conditional_logic();
                }

                // Force re-evaluation of all conditional fields
                $form.find('[data-conditional-logic]').each(function() {
                    const $conditionalField = jQuery(this);
                    $conditionalField.trigger('gform_conditional_logic');
                });

                this.log(`Completed conditional logic triggers for form ${formId}`);
            }, 50);
        } else {
            // Fallback for older GF versions
            setTimeout(() => {
                $form.trigger('change');
                $field.trigger('change');

                // Try legacy conditional logic function
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [], true);
                }

                // Also try the newer conditional logic function if available
                if (window.gf_conditional_logic) {
                    window.gf_conditional_logic();
                }
            }, 50);
        }

        this.log(`Triggered form update for form ${formId}`);
    },

    /**
     * Populate fields on initial load
     */
    populateFieldsOnInit() {
        // Check for global data and populate immediately
        if (window.wicketCurrentPersonData && typeof jQuery !== 'undefined') {
            this.log('Populating fields from wicketCurrentPersonData');
            jQuery('.wicket-gf-hidden-data-bind-target').each((index, element) => {
                this.updateFieldFromPayload(jQuery(element), window.wicketCurrentPersonData, 'initial_load');
            });
        }
    },

    /**
     * Populate specific fields
     */
    populateFields($fields) {
        if (window.wicketCurrentPersonData) {
            this.log('Populating form fields from wicketCurrentPersonData');

            // Track which forms need updates
            const formsToUpdate = new Set();

            $fields.each((index, element) => {
                const $field = jQuery(element);
                this.updateFieldFromPayload($field, window.wicketCurrentPersonData, 'form_init');

                // Track form for bulk update
                const $form = $field.closest('form');
                if ($form.length > 0) {
                    const formId = $form.attr('id')?.replace('gform_', '');
                    if (formId) {
                        formsToUpdate.add(formId);
                    }
                }
            });

            // Trigger form updates for all affected forms
            setTimeout(() => {
                formsToUpdate.forEach(formId => {
                    this.triggerBulkFormUpdate(formId);
                    // Also force comprehensive conditional logic evaluation
                    this.forceConditionalLogicEvaluation(formId);
                });
            }, 100);
        }
    },

    /**
     * Trigger form update after bulk field population
     */
    triggerBulkFormUpdate(formId) {
        this.log(`Triggering bulk form update for form ${formId}`);

        const $form = jQuery(`#gform_${formId}`);

        if ($form.length === 0) {
            return;
        }

        // Use GF's native API for bulk updates
        if (typeof gform !== 'undefined') {
            // Trigger conditional logic for entire form
            if (gform.doAction) {
                gform.doAction('gform_post_conditional_logic', formId, [], true);
                gform.doAction('gform_post_render', formId, 1);
            }

            // Apply conditional logic rules
            if (window.gf_apply_rules) {
                window.gf_apply_rules(formId, [], true);
            }

            // Additional comprehensive conditional logic triggers
            setTimeout(() => {
                // Trigger change events on all fields to ensure conditional logic
                $form.find('input, select, textarea').trigger('change');

                // Force conditional logic re-evaluation
                if (window.gf_conditional_logic) {
                    window.gf_conditional_logic();
                }

                // Re-evaluate all conditional fields
                $form.find('[data-conditional-logic]').each(function() {
                    const $conditionalField = jQuery(this);
                    $conditionalField.trigger('gform_conditional_logic');

                    // Also trigger custom event for manual re-evaluation
                    $conditionalField.trigger('gform_field_conditional_logic');
                });

                // Try alternative conditional logic functions
                if (window.gformInitConditionalLogic) {
                    window.gformInitConditionalLogic();
                }

                this.log(`Completed comprehensive conditional logic for form ${formId}`);
            }, 150);
        } else {
            // Fallback for older GF versions
            $form.trigger('change');

            if (window.gf_apply_rules) {
                window.gf_apply_rules(formId, [], true);
            }

            // Additional fallback triggers
            setTimeout(() => {
                $form.find('input, select, textarea').trigger('change');

                if (window.gf_conditional_logic) {
                    window.gf_conditional_logic();
                }
            }, 100);
        }

        this.log(`Completed bulk form update for form ${formId}`);
    },

    /**
     * Force conditional logic evaluation using all available GF methods
     */
    forceConditionalLogicEvaluation(formId) {
        this.log(`Force evaluating conditional logic for form ${formId}`);

        const $form = jQuery(`#gform_${formId}`);

        if ($form.length === 0) {
            return;
        }

        // Method 1: Use gf_apply_rules (most reliable)
        if (window.gf_apply_rules) {
            window.gf_apply_rules(formId, [], true);
            this.log(`Applied gf_apply_rules for form ${formId}`);
        }

        // Method 2: Trigger specific field events that drive conditional logic
        $form.find('input[type="hidden"].wicket-gf-hidden-data-bind-target').each(function() {
            const $hiddenField = jQuery(this);
            $hiddenField.trigger('change');
            $hiddenField.trigger('input');
            $hiddenField.trigger('keyup');
        });

        // Method 3: Force evaluation of conditional fields
        $form.find('[data-conditional-logic]').each(function() {
            const $conditionalField = jQuery(this);

            // Get field ID and trigger specific GF conditional logic
            const fieldId = $conditionalField.attr('id');
            if (fieldId) {
                const fieldNumber = fieldId.replace('field_' + formId + '_', '');

                // Call GF's conditional logic for this specific field
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [fieldNumber], true);
                }
            }
        });

        // Method 4: Global conditional logic functions
        setTimeout(() => {
            if (window.gf_conditional_logic) {
                window.gf_conditional_logic();
            }

            if (window.gformInitConditionalLogic) {
                window.gformInitConditionalLogic();
            }

            // Try to find and call form-specific conditional logic
            if (window['gform_conditional_logic_' + formId]) {
                window['gform_conditional_logic_' + formId]();
            }

            this.log(`Completed forced conditional logic evaluation for form ${formId}`);
        }, 100);
    }
};

// Initialize when DOM is ready
// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Use centralized logging for initialization
    WicketGFLiveUpdate.log('Live Update: DOM ready, checking for Wicket SDK');

    // Initialize when Wicket is ready
    if (typeof Wicket !== 'undefined' && Wicket.ready) {
        Wicket.ready(() => {
            WicketGFLiveUpdate.log('Live Update: Wicket SDK ready, initializing');
            WicketGFLiveUpdate.init();
        });
    } else {
        // Fallback if Wicket isn't available
        WicketGFLiveUpdate.log('Live Update: Wicket SDK not found, initializing anyway');
        WicketGFLiveUpdate.init();
    }
});
