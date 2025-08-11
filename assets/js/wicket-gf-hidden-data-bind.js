/**
 * Wicket Gravity Forms Live Update - GF Native Version
 *
 * Handles live updates for GFWicketDataHiddenField fields from Wicket Widgets on the same page.
 * Uses Gravity Forms native hooks for better integration and reliability.
 * Updated to be less aggressive with conditional logic triggering to prevent conflicts.
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
     * Track which forms have been processed to avoid duplicate triggers
     */
    processedForms: new Set(),

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
        this.log('Live Update: Initializing GF-native version with reduced conditional logic triggering');
        this.log('Current page URL:', window.location.href);
        this.log('Available Wicket fields on page:', jQuery('.wicket-gf-hidden-data-bind-target').length);

        this.setupGFHooks();
        this.setupWicketEventListeners();
        this.setupConditionalLogicProtection();
        this.populateFieldsOnInit();

        // Add manual testing capability for debugging
        if (this.enableLogging) {
            window.wicketGFTest = {
                testUpdate: (testData) => {
                    this.log('Manual test update triggered with data:', testData);
                    const testEvent = new CustomEvent('wicket_current_person_data_updated', {
                        detail: testData || {
                            attributes: {
                                state_name: 'Test State Value'
                            },
                            addresses: [{
                                attributes: {
                                    state_name: 'Test Address State'
                                }
                            }]
                        }
                    });
                    document.dispatchEvent(testEvent);
                },
                listFields: () => {
                    const fields = jQuery('.wicket-gf-hidden-data-bind-target');
                    fields.each((i, el) => {
                        const $el = jQuery(el);
                        this.log(`Field ${i}:`, {
                            id: $el.attr('id'),
                            dataSource: $el.data('hidden-data-bind-data-source'),
                            schemaSlug: $el.data('hidden-data-bind-schema-slug'),
                            valueKey: $el.data('hidden-data-bind-value-key'),
                            currentValue: $el.val()
                        });
                    });
                }
            };
            this.log('Manual testing functions available: window.wicketGFTest.testUpdate() and window.wicketGFTest.listFields()');
        }
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
            // GF 2.5+ native hooks - be more selective about when we process
            gform.addAction('gform/post_init', (formId) => {
                this.log(`Form ${formId} initialized via gform/post_init`);
                this.processForm(formId);
            });

            gform.addAction('gform/post_render', (formId, currentPage) => {
                this.log(`Form ${formId} rendered, page ${currentPage} via gform/post_render`);
                // Only process if we haven't already processed this form
                if (!this.processedForms.has(formId)) {
                    this.processForm(formId);
                }
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
                if (!this.processedForms.has(formId)) {
                    this.processForm(formId);
                }
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
     * Process a specific form
     */
    processForm(formId) {
        if (typeof jQuery === 'undefined') {
            return;
        }

        const $form = jQuery(`#gform_${formId}`);
        const $wicketFields = $form.find('.wicket-gf-hidden-data-bind-target');

        this.log(`Processing form ${formId}, found ${$wicketFields.length} wicket fields`);

        if ($wicketFields.length > 0) {
            this.populateFields($wicketFields);
            this.processedForms.add(formId);
        }
    },

    /**
     * Setup Wicket widget event listeners
     */
    setupWicketEventListeners() {
        // Listen for all Wicket widget events
        const eventTypes = [
            // Load events
            'wwidget-component-common-loaded',
            'wwidget-component-profile-ind-loaded',
            'wwidget-component-profile-org-loaded',
            'wwidget-component-additional-info-loaded',
            'wwidget-component-prefs-person-loaded',

            // Save events (these are the missing ones!)
            'wwidget-component-profile-ind-save-success',
            'wwidget-component-profile-org-save-success',
            'wwidget-component-additional-info-save-success',
            'wwidget-component-prefs-person-save-success',

            // Delete events
            'wwidget-component-profile-ind-delete-success',
            'wwidget-component-profile-org-delete-success',
            'wwidget-component-additional-info-delete-success',
            'wwidget-component-prefs-person-delete-success',

            // Legacy/other event patterns
            'wicket_current_person_data_updated',
            'wicket_current_org_data_updated',
            'wicket_org_selected'
        ];

        eventTypes.forEach(eventType => {
            window.addEventListener(eventType, (event) => {
                this.log(`Received Wicket event: ${eventType}`);
                this.handleWicketEvent(event);
            });
        });

        this.log('Wicket event listeners registered for:', eventTypes.join(', '));

        // Add a catch-all listener for debugging to see what events are actually firing
        if (this.enableLogging) {
            document.addEventListener('wicket_current_person_data_updated', (event) => {
                this.log('CATCH-ALL: Detected wicket_current_person_data_updated event', event.detail);
            });

            // Listen for any custom events to help debug
            const originalDispatchEvent = document.dispatchEvent;
            document.dispatchEvent = function(event) {
                if (WicketGFLiveUpdate.enableLogging && event.type.includes('wicket')) {
                    WicketGFLiveUpdate.log('Custom event detected:', event.type, event.detail);
                }
                return originalDispatchEvent.call(this, event);
            };
        }
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

        if ($allFields.length === 0) {
            this.log(`No wicket fields found on page`);
        }

        $allFields.each((index, element) => {
            const $field = jQuery(element);

            // Skip fields disabled by conditional logic, but NOT HTML hidden inputs
            // Hidden inputs (type="hidden") should still be processed
            const isHiddenInput = $field.attr('type') === 'hidden';
            const isDisabled = $field.prop('disabled');
            const isConditionallyHidden = !isHiddenInput && $field.is(':hidden');

            this.log(`Processing field ${$field.attr('id')} - type: ${$field.attr('type')}, hidden input: ${isHiddenInput}, disabled: ${isDisabled}, conditionally hidden: ${isConditionallyHidden}`);

            if (isDisabled || isConditionallyHidden) {
                this.log(`Skipping field ${$field.attr('id')} - disabled: ${isDisabled}, conditionally hidden: ${isConditionallyHidden}`);
                return;
            }

            this.updateFieldFromPayload($field, event.detail, event.type);
        });
    },

    /**
     * Update field from payload data
     */
    updateFieldFromPayload($field, payload, eventType) {
        if (!$field.length || !payload) {
            return;
        }

        const fieldId = $field.attr('id');
        if (!fieldId) {
            return;
        }

        // Get field configuration from data attributes (using the correct hyphenated format)
        const dataSource = $field.data('hidden-data-bind-data-source') || 'attributes';
        const schemaSlug = $field.data('hidden-data-bind-schema-slug') || '';
        const valueKey = $field.data('hidden-data-bind-value-key') || '';

        this.log(`Updating field ${fieldId} with source: ${dataSource}, schema: ${schemaSlug}, key: ${valueKey}`);
        this.log(`Field data attributes:`, {
            'data-hidden-data-bind-data-source': $field.data('hidden-data-bind-data-source'),
            'data-hidden-data-bind-schema-slug': $field.data('hidden-data-bind-schema-slug'),
            'data-hidden-data-bind-value-key': $field.data('hidden-data-bind-value-key'),
            'data-hidden-data-bind-enabled': $field.data('hidden-data-bind-enabled')
        });
        this.log(`Event payload:`, payload);

        // Extract value from payload
        const value = this.extractValue(payload, dataSource, schemaSlug, valueKey);

        this.log(`Extracted value for field ${fieldId}:`, value);

        if (value !== null && value !== undefined) {
            this.updateFieldValue($field, value, eventType);
        } else {
            this.log(`No value found for field ${fieldId} - dataSource: ${dataSource}, schemaSlug: ${schemaSlug}, valueKey: ${valueKey}`);
        }
    },

    /**
     * Simplified value extraction with clear separation by data source
     */
    extractValue(payload, dataSource, schemaSlug, valueKey) {
        this.log(`extractValue called with:`, { dataSource, schemaSlug, valueKey });

        // Add null/undefined checks for all parameters
        if (!payload || !dataSource || !schemaSlug || !valueKey) {
            this.log(`extractValue: Missing required parameters`, { payload: !!payload, dataSource, schemaSlug, valueKey });
            return undefined;
        }

        let result;
        switch (dataSource) {
            case 'person_addinfo':
                this.log(`extractValue: Using person_addinfo path`);
                result = this.extractFromDataFields(payload, schemaSlug, valueKey);
                break;

            case 'person_profile':
                this.log(`extractValue: Using person_profile path`);
                if (schemaSlug === 'profile_attributes') {
                    this.log(`extractValue: Using attributes extraction`);
                    result = this.extractFromAttributes(payload, valueKey);
                } else if (schemaSlug.startsWith('profile_')) {
                    this.log(`extractValue: Using relationship extraction`);
                    result = this.extractFromRelationship(payload, schemaSlug, valueKey);
                }
                break;

            case 'organization':
                this.log(`extractValue: Using organization path`);
                result = this.extractFromOrganization(payload, schemaSlug, valueKey);
                break;

            case 'organization_profile':
                this.log(`extractValue: Using organization_profile path`);
                if (schemaSlug === 'profile_attributes') {
                    this.log(`extractValue: Using organization attributes extraction`);
                    result = this.extractFromOrganizationAttributes(payload, valueKey);
                } else if (schemaSlug.startsWith('profile_')) {
                    this.log(`extractValue: Using organization relationship extraction`);
                    result = this.extractFromOrganizationRelationship(payload, schemaSlug, valueKey);
                }
                break;

            default:
                this.log(`extractValue: Unknown dataSource: ${dataSource}`);
                break;
        }

        this.log(`extractValue result:`, result);
        return result;
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
     * Extract from organization attributes
     */
    extractFromOrganizationAttributes(payload, valueKey) {
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
     * Extract from organization relationship data (addresses, emails, etc.)
     */
    extractFromOrganizationRelationship(payload, schemaSlug, valueKey) {
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
     * Update field value and trigger minimal conditional logic
     */
    updateFieldValue($field, value, source) {
        const oldValue = $field.val();
        const fieldId = $field.attr('id');

        // Update the field value
        $field.val(value);
        this.log(`Updated field ${fieldId} from "${oldValue}" to "${value}" (source: ${source})`);

        // Only trigger conditional logic if the value actually changed
        if (oldValue !== value) {
            this.triggerMinimalFormUpdate($field);
        }
    },

    /**
     * Trigger minimal form update using GF native patterns
     * This is much less aggressive than the previous implementation
     */
    triggerMinimalFormUpdate($field) {
        const $form = $field.closest('form');

        if ($form.length === 0) {
            return;
        }

        const formId = $form.attr('id')?.replace('gform_', '');

        if (!formId) {
            return;
        }

        this.log(`Triggering minimal form update for form ${formId} after field ${$field.attr('id')} update`);

        // Use GF's native API to trigger a single, targeted conditional logic evaluation
        if (typeof gform !== 'undefined') {
            // Use the standard GF input change event - this is what GF expects
            if (gform.doAction) {
                gform.doAction('gform_input_change', $field[0], $field.val(), '');
            }

            // Trigger a simple field change event
            setTimeout(() => {
                $field.trigger('change');

                // Apply conditional logic rules only once, without force flag to prevent conflicts
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [], false); // false = don't force, let GF decide
                }

                this.log(`Completed minimal conditional logic triggers for form ${formId}`);
            }, 10); // Very short delay
        } else {
            // Fallback for older GF versions - just trigger change
            setTimeout(() => {
                $field.trigger('change');
            }, 10);
        }

        this.log(`Triggered minimal form update for form ${formId}`);
    },

    /**
     * Setup protection against aggressive conditional logic
     */
    setupConditionalLogicProtection() {
        // Use GF's native hooks to protect fields from being incorrectly hidden
        if (typeof gform !== 'undefined' && gform.addFilter) {
            // Hook into the conditional logic evaluation to prevent conflicts
            gform.addFilter('gform_abort_conditional_logic_do_action', (abort, action, targetId, useAnimation, defaultValues, isInit, formId, callback) => {
                // Allow Data Bind fields to be hidden/shown normally
                if (targetId && targetId.includes('wicket_data_hidden')) {
                    return abort;
                }

                // Protect custom Wicket fields from being incorrectly hidden by Data Bind field updates
                if (action === 'hide' && targetId) {
                    const $targetField = jQuery(targetId);

                    // Check if this is a Wicket field that should not be hidden
                    if ($targetField.length > 0) {
                        const fieldType = $targetField.find('input, select, textarea').first().attr('class') || '';

                        // Protect specific Wicket field types from being incorrectly hidden
                        if (fieldType.includes('gf_org_search_select_input') ||
                            fieldType.includes('wicket-widget-') ||
                            $targetField.hasClass('wicket-field-protected')) {

                            this.log(`Protecting field ${targetId} from being hidden by conditional logic`);
                            return true; // Abort the hide action
                        }
                    }
                }

                return abort;
            });

            // Hook into post conditional logic to ensure protected fields stay visible
            gform.addAction('gform_post_conditional_logic_field_action', (formId, action, targetId, defaultValues, isInit) => {
                if (action === 'hide' && targetId) {
                    const $targetField = jQuery(targetId);

                    if ($targetField.length > 0) {
                        const fieldType = $targetField.find('input, select, textarea').first().attr('class') || '';

                        // Re-show fields that were incorrectly hidden
                        if (fieldType.includes('gf_org_search_select_input') ||
                            fieldType.includes('wicket-widget-') ||
                            $targetField.hasClass('wicket-field-protected')) {

                            setTimeout(() => {
                                if ($targetField.hasClass('gform_hidden') && !$targetField.attr('data-conditionally-hidden')) {
                                    $targetField.removeClass('gform_hidden').show();
                                    this.log(`Restored visibility for protected field ${targetId}`);
                                }
                            }, 20);
                        }
                    }
                }
            });
        }

        // Fallback protection using periodic checks
        this.setupPeriodicVisibilityCheck();
    },

    /**
     * Setup periodic visibility check as fallback protection
     */
    setupPeriodicVisibilityCheck() {
        // Check every few seconds for incorrectly hidden fields
        setInterval(() => {
            if (typeof jQuery !== 'undefined') {
                // Check for Org Search fields that are incorrectly hidden
                jQuery('.gf_org_search_select_input').each((index, element) => {
                    const $input = jQuery(element);
                    const $fieldContainer = $input.closest('.gfield');

                    if ($fieldContainer.hasClass('gform_hidden') && !$fieldContainer.attr('data-conditionally-hidden')) {
                        $fieldContainer.removeClass('gform_hidden').show();
                        this.log(`Periodic check: Restored Org Search field visibility`);
                    }
                });

                // Check for other protected Wicket fields
                jQuery('.wicket-field-protected').each((index, element) => {
                    const $field = jQuery(element);

                    if ($field.hasClass('gform_hidden') && !$field.attr('data-conditionally-hidden')) {
                        $field.removeClass('gform_hidden').show();
                        this.log(`Periodic check: Restored protected field visibility`);
                    }
                });
            }
        }, 3000); // Check every 3 seconds
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
     * Populate specific fields with bulk conditional logic update
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

            // Trigger a single, gentle form update for all affected forms
            setTimeout(() => {
                formsToUpdate.forEach(formId => {
                    this.triggerGentleBulkFormUpdate(formId);
                });
            }, 50);
        }
    },

    /**
     * Trigger a gentle bulk form update - much less aggressive than before
     */
    triggerGentleBulkFormUpdate(formId) {
        this.log(`Triggering gentle bulk form update for form ${formId}`);

        const $form = jQuery(`#gform_${formId}`);

        if ($form.length === 0) {
            return;
        }

        // Use GF's native API for a single, gentle update
        if (typeof gform !== 'undefined') {
            // Trigger conditional logic for entire form once
            if (gform.doAction) {
                gform.doAction('gform_post_conditional_logic', formId, [], false); // false = don't force
            }

            // Apply conditional logic rules gently
            setTimeout(() => {
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [], false); // false = don't force
                }

                this.log(`Completed gentle conditional logic for form ${formId}`);
            }, 50);
        } else {
            // Fallback for older GF versions - minimal approach
            setTimeout(() => {
                if (window.gf_apply_rules) {
                    window.gf_apply_rules(formId, [], false);
                }
            }, 50);
        }

        this.log(`Completed gentle bulk form update for form ${formId}`);
    }
};

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
