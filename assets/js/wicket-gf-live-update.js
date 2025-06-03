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
 * Get the correct field name for a given context and target key
 * @param {string} context - The data context (addresses, emails, phones, etc.)
 * @param {string} targetKey - The field name to look up
 * @returns {string} The correct field name to use
 */
function getFieldName(context, targetKey) {
    const contextMapping = WICKET_FIELD_MAPPINGS[context];
    if (contextMapping && contextMapping[targetKey]) {
        return contextMapping[targetKey];
    }

    // Try automatic camelCase conversion as fallback
    const camelCaseKey = targetKey.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase());
    return camelCaseKey;
}

/**
 * Wicket GF Live Update Controller
 * Uses Gravity Forms native hooks for better integration
 */
const WicketGFLiveUpdate = {

    /**
     * Initialize the live update system
     */
    init() {
        console.log('Wicket GF Live Update: Initializing GF-native version');

        this.setupGFHooks();
        this.setupWicketEventListeners();
        this.populateFieldsOnInit();
    },

    /**
     * Setup Gravity Forms native hooks
     */
    setupGFHooks() {
        // Use GF's native hooks if available, fallback to jQuery events
        if (typeof gform !== 'undefined' && gform.addAction) {
            // GF 2.5+ native hooks
            gform.addAction('gform/post_init', (formId) => {
                console.log(`Wicket GF: Form ${formId} initialized via gform/post_init`);
                this.processForm(formId);
            });

            gform.addAction('gform/post_render', (formId, currentPage) => {
                console.log(`Wicket GF: Form ${formId} rendered, page ${currentPage} via gform/post_render`);
                this.processForm(formId);
            });

            gform.addAction('gform/page_loaded', (formId, currentPage) => {
                console.log(`Wicket GF: Form ${formId} page ${currentPage} loaded via gform/page_loaded`);
                this.processForm(formId);
            });
        } else {
            // Fallback to jQuery events for older GF versions
            console.log('Wicket GF: Using jQuery fallback events');

            $(document).on('gform_post_render', (event, formId, currentPage) => {
                console.log(`Wicket GF: Form ${formId} rendered, page ${currentPage} via gform_post_render`);
                this.processForm(formId);
            });
        }
    },

    /**
     * Process a specific form for wicket fields
     */
    processForm(formId) {
        const $form = $(`#gform_${formId}`);
        const $wicketFields = $form.find('.wicket-gf-live-update-target');

        if ($wicketFields.length === 0) {
            return;
        }

        console.log(`Wicket GF: Found ${$wicketFields.length} wicket fields in form ${formId}`);

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
                console.log('Wicket GF: Event intercepted:', event.type);

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
        console.log(`Wicket GF: Processing event ${event.type} with data:`, event.detail);

        // Ensure jQuery is available
        if (typeof jQuery === 'undefined') {
            console.warn('Wicket GF: jQuery not available, skipping field updates');
            return;
        }

        // Process all wicket fields on all forms
        jQuery('.wicket-gf-live-update-target').each((index, element) => {
            const $field = jQuery(element);
            this.updateFieldFromPayload($field, event.detail, event.type);
        });
    },

    /**
     * Update a single field from payload data
     */
    updateFieldFromPayload($field, payload, eventType) {
        const dataSource = $field.data('live-update-data-source');
        const schemaSlug = $field.data('live-update-schema-slug');
        const valueKey = $field.data('live-update-value-key');
        const fieldId = $field.attr('id');

        console.log(`Wicket GF: Processing field ${fieldId} for ${dataSource}.${schemaSlug}.${valueKey}`);

        const extractedValue = this.extractValue(payload, dataSource, schemaSlug, valueKey);

        if (typeof extractedValue !== 'undefined') {
            this.updateFieldValue($field, extractedValue, eventType);
        }
    },

    /**
     * Simplified value extraction with clear separation by data source
     */
    extractValue(payload, dataSource, schemaSlug, valueKey) {
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
        const mappedField = getFieldName('attributes', valueKey);
        return attributes[mappedField] !== undefined ? attributes[mappedField] : attributes[valueKey];
    },

    /**
     * Extract from relationship data (addresses, emails, etc.)
     */
    extractFromRelationship(payload, schemaSlug, valueKey) {
        const relationshipType = schemaSlug.replace('profile_', '');

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
            const mappedField = getFieldName(mappingContext, valueKey);

            return itemData[mappedField] !== undefined ? itemData[mappedField] : itemData[valueKey];
        }

        return undefined;
    },

    /**
     * Extract from primary address
     */
    extractFromPrimaryAddress(payload, valueKey) {
        const addresses = payload.addresses || [];
        const primaryAddress = addresses.find(addr => {
            const attrs = addr.attributes || addr;
            return attrs.primary && attrs.active;
        });

        if (primaryAddress) {
            const addressData = primaryAddress.attributes || primaryAddress;

            if (valueKey === '_self') return addressData;

            const mappedField = getFieldName('addresses', valueKey);
            return addressData[mappedField] !== undefined ? addressData[mappedField] : addressData[valueKey];
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

            console.log(`Wicket GF: Field ${$field.attr('id')} updated to '${newValue}' from ${source}`);
        }
    },

    /**
     * Populate fields on initial load
     */
    populateFieldsOnInit() {
        // Check for global data and populate immediately
        if (window.wicketCurrentPersonData && typeof jQuery !== 'undefined') {
            console.log('Wicket GF: Populating fields from wicketCurrentPersonData');
            jQuery('.wicket-gf-live-update-target').each((index, element) => {
                this.updateFieldFromPayload(jQuery(element), window.wicketCurrentPersonData, 'initial_load');
            });
        }
    },

    /**
     * Populate specific fields
     */
    populateFields($fields) {
        if (window.wicketCurrentPersonData) {
            console.log('Wicket GF: Populating form fields from wicketCurrentPersonData');
            $fields.each((index, element) => {
                this.updateFieldFromPayload(jQuery(element), window.wicketCurrentPersonData, 'form_init');
            });
        }
    }
};

// Initialize when DOM is ready
jQuery(document).ready(function ($) {
    'use strict';

    console.log('Wicket GF Live Update: DOM ready, checking for Wicket SDK');

    // Initialize when Wicket is ready
    if (typeof Wicket !== 'undefined' && Wicket.ready) {
        Wicket.ready(() => {
            console.log('Wicket GF Live Update: Wicket SDK ready, initializing');
            WicketGFLiveUpdate.init();
        });
    } else {
        // Fallback if Wicket isn't available
        console.log('Wicket GF Live Update: Wicket SDK not found, initializing anyway');
        WicketGFLiveUpdate.init();
    }
});
