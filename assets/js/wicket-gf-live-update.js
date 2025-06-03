/**
 * Wicket Gravity Forms Live Update
 *
 * Handles live updates for GFWicketDataHiddenField fields from Wicket Widgets on the same page.
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

jQuery(document).ready(function ($) {
    'use strict';

    console.log('Wicket GF Debug: Script loaded and jQuery ready');
    console.log('Wicket GF Debug: Looking for fields with class:', $('.wicket-gf-live-update-target').length);

    // IMMEDIATE EVENT INTERCEPTOR - Listen for ALL events immediately
    console.log('Wicket GF Debug: Setting up immediate event interceptor');

    // Override dispatchEvent immediately to catch everything
    const originalDispatchEvent = window.dispatchEvent;
    window.dispatchEvent = function(event) {
        if (event.type && (event.type.includes('wicket') || event.type.includes('wwidget'))) {
            console.log('Wicket GF Debug: IMMEDIATE INTERCEPTOR - Event:', event.type);
            console.log('Wicket GF Debug: IMMEDIATE INTERCEPTOR - Detail:', event.detail);

            if (event.detail) {
                console.log('Wicket GF Debug: IMMEDIATE INTERCEPTOR - Has dataFields?', !!event.detail.dataFields);
                console.log('Wicket GF Debug: IMMEDIATE INTERCEPTOR - Has attributes?', !!event.detail.attributes);

                // Immediately try to process person_addinfo fields if dataFields are present
                if (event.detail.dataFields) {
                    console.log('Wicket GF Debug: IMMEDIATE INTERCEPTOR - Processing dataFields:', event.detail.dataFields);

                    $('.wicket-gf-live-update-target').each(function () {
                        const $hiddenField = $(this);
                        const targetDataSource = $hiddenField.data('live-update-data-source');

                        if (targetDataSource === 'person_addinfo') {
                            const fieldId = $hiddenField.attr('id');
                            const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                            const targetValueKey = $hiddenField.data('live-update-value-key');

                            console.log(`Wicket GF Debug: IMMEDIATE - Processing person_addinfo field ${fieldId} for schema ${targetSchemaSlug}`);

                            const extractedValue = extractValueFromPayload(event.detail, targetDataSource, targetSchemaSlug, targetValueKey, `immediate interceptor (${event.type})`);
                            console.log(`Wicket GF Debug: IMMEDIATE - Extracted value:`, extractedValue);

                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, 'form', `immediate interceptor (${event.type})`);
                            }
                        }
                    });
                }
            }
        }

        return originalDispatchEvent.call(this, event);
    };

    // --- Start Helper Functions ---
    function extractValueFromPayload(payload, dataSource, targetSchemaSlug, targetValueKey, logContextSource) {
        console.log(`Wicket GF Debug: extractValueFromPayload called with dataSource: ${dataSource}, schema: ${targetSchemaSlug}, key: ${targetValueKey}, context: ${logContextSource}`);

        // Handle person_addinfo (legacy format)
        if (dataSource === 'person_addinfo') {
            console.log(`Wicket GF Debug: Processing person_addinfo extraction`);
            console.log(`Wicket GF Debug: Payload keys:`, Object.keys(payload || {}));

            // First try the legacy event payload format
            if (payload && payload.dataField && payload.dataField.key === targetSchemaSlug) {
                console.log(`Wicket GF Debug: Found legacy dataField format`);
                let extractedValue;
                if (targetValueKey && typeof payload.dataField.value === 'object' && payload.dataField.value !== null) {
                    if (payload.dataField.value.hasOwnProperty(targetValueKey)) {
                        extractedValue = payload.dataField.value[targetValueKey];
                        console.log(`Wicket GF Debug: Extracted from legacy format:`, extractedValue);
                    } else {
                        console.log(`Wicket GF Debug: Legacy format missing targetValueKey: ${targetValueKey}`);
                        return undefined;
                    }
                } else if (!targetValueKey || targetValueKey === 'value') {
                    extractedValue = payload.dataField.value;
                    console.log(`Wicket GF Debug: Extracted full value from legacy format:`, extractedValue);
                } else {
                    console.log(`Wicket GF Debug: Legacy format incompatible with extraction requirements`);
                    return undefined;
                }
                return extractedValue;
            }

            // Also try to extract from profile widget payload (dataFields array)
            if (payload && (payload.attributes || payload.dataFields)) {
                const personData = payload.person || payload;
                const dataFields = personData.dataFields || personData.attributes?.dataFields || [];

                console.log(`Wicket GF Debug: Looking for dataField with key '${targetSchemaSlug}' in:`, dataFields);
                console.log(`Wicket GF Debug: Available dataField keys:`, dataFields.map(f => f.key || f.schema_slug));

                const matchingDataField = dataFields.find(field =>
                    field.key === targetSchemaSlug || field.schema_slug === targetSchemaSlug
                );

                if (matchingDataField && matchingDataField.value) {
                    console.log(`Wicket GF Debug: Found dataField:`, matchingDataField);

                    if (targetValueKey && typeof matchingDataField.value === 'object') {
                        const value = matchingDataField.value[targetValueKey];
                        console.log(`Wicket GF Debug: Extracted ${targetValueKey}:`, value);
                        return value;
                    } else if (!targetValueKey || targetValueKey === 'value') {
                        console.log(`Wicket GF Debug: Returning full value:`, matchingDataField.value);
                        return matchingDataField.value;
                    }
                } else {
                    console.log(`Wicket GF Debug: No matching dataField found for schema '${targetSchemaSlug}'`);
                    console.log(`Wicket GF Debug: Available schemas:`, dataFields.map(f => ({key: f.key, schema_slug: f.schema_slug})));
                }
            } else {
                console.log(`Wicket GF Debug: No dataFields structure found in payload for person_addinfo`);
                console.log(`Wicket GF Debug: Payload keys:`, Object.keys(payload || {}));
            }

            return undefined;
        }

        // Handle person_profile and organization data sources
        if (dataSource === 'person_profile' || dataSource === 'organization') {
            if (!payload) {
                console.log(`Wicket GF Debug: No payload for ${dataSource}`);
                return undefined;
            }

            console.log(`Wicket GF Debug: Processing ${dataSource}, targetSchemaSlug: ${targetSchemaSlug}, targetValueKey: ${targetValueKey}`);
            console.log('Wicket GF Debug: Payload structure:', {
                hasPersonProperty: !!payload.person,
                hasAttributesProperty: !!payload.attributes,
                hasAddressesProperty: !!payload.addresses,
                payloadKeys: Object.keys(payload)
            });

            // For profile attributes, extract from person data
            if (targetSchemaSlug === 'profile_attributes') {
                // Handle both payload.person.attribute and payload.attributes structures
                const personData = payload.person || payload;
                const attributes = personData.attributes || personData;

                if (targetValueKey === '_self') {
                    return attributes;
                }
                if (targetValueKey && targetValueKey.startsWith('user_')) {
                    const userField = targetValueKey.replace('user_', '');
                    return attributes.user?.[userField];
                }

                // Use the mapping system to find the correct field name
                const mappedFieldName = getFieldName('attributes', targetValueKey);

                if (attributes[mappedFieldName] !== undefined) {
                    return attributes[mappedFieldName];
                }

                // Try direct match as fallback
                if (attributes[targetValueKey] !== undefined) {
                    return attributes[targetValueKey];
                }

                return undefined;
            }

            // For relationship collections (organizations, addresses, etc.)
            if (targetSchemaSlug.startsWith('profile_')) {
                const relationshipType = targetSchemaSlug.replace('profile_', '');
                const personData = payload.person || payload;

                console.log(`Wicket GF Debug: Processing ${relationshipType}, personData:`, personData);

                // Handle primary address specifically
                if (relationshipType === 'primary_address') {
                    const addresses = personData.addresses || [];
                    console.log(`Wicket GF Debug: Looking for primary address in:`, addresses);

                    const primaryAddress = addresses.find(addr => {
                        const addrAttrs = addr.attributes || addr;
                        return addrAttrs.primary === true && addrAttrs.active === true;
                    });

                    if (primaryAddress) {
                        const addressData = primaryAddress.attributes || primaryAddress;
                        console.log(`Wicket GF Debug: Found primary address:`, addressData);

                        if (targetValueKey === '_self') {
                            return addressData;
                        }

                        console.log(`Wicket GF Debug: Looking for ${targetValueKey} in address data:`, addressData);
                        console.log(`Wicket GF Debug: Available address keys:`, Object.keys(addressData));

                        // Use the mapping system to find the correct field name
                        const mappedFieldName = getFieldName('addresses', targetValueKey);

                        if (addressData[mappedFieldName] !== undefined) {
                            console.log(`Wicket GF Debug: Found mapped field ${targetValueKey} -> ${mappedFieldName}:`, addressData[mappedFieldName]);
                            return addressData[mappedFieldName];
                        }

                        // Try direct match as fallback
                        if (addressData[targetValueKey] !== undefined) {
                            console.log(`Wicket GF Debug: Found direct match for ${targetValueKey}:`, addressData[targetValueKey]);
                            return addressData[targetValueKey];
                        }

                        console.log(`Wicket GF Debug: No match found for ${targetValueKey} in address data`);
                        return undefined;
                    }
                    return undefined;
                }

                // Handle other relationship collections
                const relationshipData = personData[relationshipType] || [];
                if (Array.isArray(relationshipData) && relationshipData.length > 0) {
                    const firstItem = relationshipData[0];
                    const itemData = firstItem.attributes || firstItem;

                    // Return first item or specific field from first item
                    if (targetValueKey === '_self') {
                        return itemData;
                    }

                    // Determine the correct context for mapping
                    let mappingContext = relationshipType;
                    if (relationshipType === 'addresses') mappingContext = 'addresses';
                    else if (relationshipType === 'emails') mappingContext = 'emails';
                    else if (relationshipType === 'phones') mappingContext = 'phones';
                    else if (relationshipType === 'webAddresses') mappingContext = 'web_addresses';

                    // Use the mapping system to find the correct field name
                    const mappedFieldName = getFieldName(mappingContext, targetValueKey);

                    if (itemData[mappedFieldName] !== undefined) {
                        return itemData[mappedFieldName];
                    }

                    // Try direct match as fallback
                    if (itemData[targetValueKey] !== undefined) {
                        return itemData[targetValueKey];
                    }

                    return undefined;
                }

                // Debug: Log what we're looking for vs what's available
                console.log(`Wicket GF Debug: Looking for ${relationshipType} in personData:`, personData);
                console.log(`Wicket GF Debug: Available keys in personData:`, Object.keys(personData || {}));
            }

            // For organization data
            if (dataSource === 'organization' && payload.organization) {
                // Handle organization.schema_slug.value_key format
                const [schemaKey, propertyKey] = targetSchemaSlug.split('.');
                if (schemaKey && propertyKey && payload.organization.data_fields) {
                    const schemaData = payload.organization.data_fields.find(field =>
                        field.schema_slug === schemaKey || field.key === schemaKey
                    );
                    if (schemaData?.value && typeof schemaData.value === 'object') {
                        return schemaData.value[propertyKey];
                    }
                }
            }
        }

        return undefined;
    }

    function updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, logContextSource) {
        const currentValue = $hiddenField.val();
        const newValueStr = (extractedValue === null || typeof extractedValue === 'undefined') ? '' : String(extractedValue);
        if (currentValue !== newValueStr) {
            $hiddenField.val(newValueStr).trigger('change');
            console.log(`Wicket GF Live Update: Field ${fieldId} (form ${formId}) updated to: '${newValueStr}' from ${logContextSource}.`);
        }
    }

    /**
     * Populate fields with current person data on page load
     */    function populateFieldsOnLoad() {
        const $pageHiddenFields = $('.wicket-gf-live-update-target');

        $pageHiddenFields.each(function() {
            const $hiddenField = $(this);
            const targetDataSource = $hiddenField.data('live-update-data-source');

            // Handle person_profile, organization, and person_addinfo fields that are currently empty
            if ((targetDataSource === 'person_profile' || targetDataSource === 'organization' || targetDataSource === 'person_addinfo') && !$hiddenField.val()) {
                const fieldId = $hiddenField.attr('id');
                const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                const targetValueKey = $hiddenField.data('live-update-value-key');

                console.log(`Wicket GF Live Update: Attempting to populate ${targetDataSource} field ${fieldId} on load`);

                // Check if we have current data available globally
                if (typeof window.wicketCurrentPersonData !== 'undefined') {
                    const extractedValue = extractValueFromPayload(window.wicketCurrentPersonData, targetDataSource, targetSchemaSlug, targetValueKey, 'initial load (global data)');
                    if (typeof extractedValue !== 'undefined') {
                        updateHiddenFieldValue($hiddenField, extractedValue, fieldId, 'form', 'initial load (global data)');
                    }
                } else {
                    // Also check for data in window.Wicket.currentUser or similar
                    if (typeof window.Wicket !== 'undefined' && window.Wicket.currentUser) {
                        const extractedValue = extractValueFromPayload(window.Wicket.currentUser, targetDataSource, targetSchemaSlug, targetValueKey, 'initial load (Wicket.currentUser)');
                        if (typeof extractedValue !== 'undefined') {
                            updateHiddenFieldValue($hiddenField, extractedValue, fieldId, 'form', 'initial load (Wicket.currentUser)');
                        }
                    }
                }
            }
        });
    }
    // --- End Helper Functions ---

    if (typeof Wicket === 'undefined' || typeof Wicket.ready === 'undefined') {
        //console.warn('Wicket SDK not ready, live updates for GF Wicket fields will not initialize.');
        return;
    }

    let additionalInfoListenerAttached = false;

    function initializeWicketGFSupport() {
        //console.log('Wicket GF Live Update: Initializing/Re-initializing Wicket GF Support.');
        // No need to store $currentHiddenFields globally if the global listener re-queries DOM.

        const $pageHiddenFields = $('.wicket-gf-live-update-target'); // Fields currently on page/step

        if (!$pageHiddenFields.length) {
    //console.log('Wicket GF Live Update: No live update target fields found on this page/step.');
            return;
        }
        //console.log(`Wicket GF Live Update: Found ${$pageHiddenFields.length} target field(s) on this page/step.`);

        // --- Global listener for 'person_addinfo' (legacy compatibility) ---
        let hasPersonAddinfoTargetsOnPage = false;
        $pageHiddenFields.each(function () {
            if ($(this).data('live-update-data-source') === 'person_addinfo') {
                hasPersonAddinfoTargetsOnPage = true;
                return false; // break
            }
        });

        if (hasPersonAddinfoTargetsOnPage && !additionalInfoListenerAttached) {
            window.addEventListener('wwidget-component-additional-info-save-success', function (event) {
                const payload = event.detail;
                //console.log('Wicket GF Live Update: Global wwidget-component-additional-info-save-success event:', payload);

                if (!payload || !payload.dataField || !payload.dataField.key) {
                    //console.warn('Wicket GF Live Update: Invalid or missing payload in event.');
                    return;
                }

                // Iterate over the fields present *at the time of the event* in the current DOM scope
                $('.wicket-gf-live-update-target').each(function () {
                    const $hiddenField = $(this);
                    const targetDataSource = $hiddenField.data('live-update-data-source');

                    if (targetDataSource === 'person_addinfo') {
                        const formId = $hiddenField.closest('form').attr('id');
                        const fieldId = $hiddenField.attr('id');
                        const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                        const targetValueKey = $hiddenField.data('live-update-value-key');

                        if (targetSchemaSlug === payload.dataField.key) {
                            //console.log(`Wicket GF Live Update: Global listener: Match for field ${fieldId}, schema ${targetSchemaSlug}`);
                            const extractedValue = extractValueFromPayload(payload, 'person_addinfo', targetSchemaSlug, targetValueKey, 'person_addinfo (global event)');
                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, 'person_addinfo (global event)');
                            }
                        }
                    }
                });
            });
            additionalInfoListenerAttached = true;
            //console.log('Wicket GF Live Update: Global listener for person_addinfo ATTACHED.');
        } else if (hasPersonAddinfoTargetsOnPage && additionalInfoListenerAttached) {
            //console.log('Wicket GF Live Update: Global listener for person_addinfo already attached, active for current fields.');
        }

        // --- Global listener for 'person_profile' ---
        let hasPersonProfileTargetsOnPage = false;
        $pageHiddenFields.each(function () {
            if ($(this).data('live-update-data-source') === 'person_profile') {
                hasPersonProfileTargetsOnPage = true;
                return false; // break
            }
        });

        // Check if we have person_addinfo targets too since they use the same events
        let hasPersonAddinfoTargetsOnPage2 = false;
        $pageHiddenFields.each(function () {
            if ($(this).data('live-update-data-source') === 'person_addinfo') {
                hasPersonAddinfoTargetsOnPage2 = true;
                return false; // break
            }
        });

        if (hasPersonProfileTargetsOnPage || hasPersonAddinfoTargetsOnPage2) {
            console.log('Wicket GF Live Update: Setting up profile/addinfo listeners');
            console.log('Wicket GF Debug: hasPersonProfileTargetsOnPage:', hasPersonProfileTargetsOnPage);
            console.log('Wicket GF Debug: hasPersonAddinfoTargetsOnPage2:', hasPersonAddinfoTargetsOnPage2);

            // Debug: Listen for ALL events to see what's actually firing
            const originalAddEventListener = window.addEventListener;
            window.addEventListener = function(type, listener, options) {
                if (type.includes('wicket') || type.includes('wwidget')) {
                    console.log('Wicket GF Debug: Event listener added for:', type);
                }
                return originalAddEventListener.call(this, type, listener, options);
            };

            // Store the last person data we see in any event
            let lastPersonData = null;            // Add a universal listener to catch any event with person or organization data
            const originalDispatchEvent = window.dispatchEvent;
            window.dispatchEvent = function(event) {
                // Log ALL wicket events to see what's firing
                if (event.type && (event.type.includes('wicket') || event.type.includes('wwidget'))) {
                    console.log('Wicket GF Debug: ALL WICKET EVENT:', event.type, event.detail);

                    // Show what properties the event has
                    if (event.detail) {
                        console.log('Wicket GF Debug: Event detail keys:', Object.keys(event.detail));
                        console.log('Wicket GF Debug: Has attributes?', !!event.detail.attributes);
                        console.log('Wicket GF Debug: Has addresses?', !!event.detail.addresses);
                        console.log('Wicket GF Debug: Has organization?', !!event.detail.organization);
                        console.log('Wicket GF Debug: Has dataFields?', !!event.detail.dataFields);
                    }
                }

                // Check if this event has person, organization, or dataFields data
                if (event.detail && (event.detail.attributes || event.detail.addresses || event.detail.organization || event.detail.dataFields)) {
                    console.log('Wicket GF Debug: MATCHED EVENT with data:', event.type, event.detail);
                    lastPersonData = event.detail;

                    // Try to populate fields immediately
                    $('.wicket-gf-live-update-target').each(function () {
                        const $hiddenField = $(this);
                        const targetDataSource = $hiddenField.data('live-update-data-source');

                        console.log(`Wicket GF Debug: Found field with data-source: ${targetDataSource}`);

                        if (targetDataSource === 'person_profile' || targetDataSource === 'organization' || targetDataSource === 'person_addinfo') {
                            const formId = $hiddenField.closest('form').attr('id');
                            const fieldId = $hiddenField.attr('id');
                            const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                            const targetValueKey = $hiddenField.data('live-update-value-key');
                            const currentValue = $hiddenField.val();

                            console.log(`Wicket GF Debug: Processing field ${fieldId} from dispatched event ${event.type}`);
                            console.log(`Wicket GF Debug: Schema: ${targetSchemaSlug}, Key: ${targetValueKey}, Current value: '${currentValue}'`);
                            console.log(`Wicket GF Debug: Data source: ${targetDataSource}`);

                            if (targetDataSource === 'person_addinfo') {
                                console.log(`Wicket GF Debug: Processing person_addinfo field with payload:`, event.detail);
                                console.log(`Wicket GF Debug: Looking for schema '${targetSchemaSlug}' and key '${targetValueKey}'`);

                                if (event.detail.dataFields) {
                                    console.log(`Wicket GF Debug: Found dataFields in event.detail:`, event.detail.dataFields);
                                } else if (event.detail.attributes && event.detail.attributes.dataFields) {
                                    console.log(`Wicket GF Debug: Found dataFields in event.detail.attributes:`, event.detail.attributes.dataFields);
                                } else {
                                    console.log(`Wicket GF Debug: No dataFields found in payload structure`);
                                }
                            }

                            const extractedValue = extractValueFromPayload(event.detail, targetDataSource, targetSchemaSlug, targetValueKey, `dispatched event (${event.type})`);
                            console.log(`Wicket GF Debug: Extracted value:`, extractedValue);

                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, `dispatched event (${event.type})`);
                            } else {
                                console.warn(`Wicket GF Debug: No value extracted for field ${fieldId}`);
                            }
                        }
                    });
                }
                return originalDispatchEvent.call(this, event);
            };            // Add a watcher for when wicketCurrentPersonData or wicketCurrentOrgData gets set
            let wicketDataWatcher;
            if (typeof window.wicketCurrentPersonData === 'undefined' && typeof window.wicketCurrentOrgData === 'undefined') {
                wicketDataWatcher = setInterval(function() {
                    if (typeof window.wicketCurrentPersonData !== 'undefined' || typeof window.wicketCurrentOrgData !== 'undefined') {
                        console.log('Wicket GF Debug: Wicket data detected, populating fields');
                        populateFieldsOnLoad();
                        clearInterval(wicketDataWatcher);
                    }
                }, 100);

                // Clear watcher after 10 seconds to avoid infinite checks
                setTimeout(function() {
                    if (wicketDataWatcher) {
                        clearInterval(wicketDataWatcher);
                    }
                }, 10000);
            }

            // Listen for multiple possible profile widget events (both loaded and save)
            const profileEvents = [
                // Individual profile widget events
                'wwidget-component-profile-ind-save-success',
                'wwidget-component-profile-ind-loaded',

                // Preferences widget events
                'wwidget-component-prefs-person-save-success',
                'wwidget-component-prefs-person-loaded',

                // Organization widget events (assuming similar pattern)
                'wwidget-component-profile-org-save-success',
                'wwidget-component-profile-org-loaded',

                // Generic/legacy events
                'wicket-profile-save-success',
                'wwidget-component-profile-save-success',
                'wwidget-component-common-loaded',
                'wicket-person-save-success',
                'wicket-address-save-success',
                'wicket-address-updated',
                'wicket-profile-updated'
            ];

            profileEvents.forEach(eventName => {
                console.log(`Wicket GF Debug: Setting up listener for ${eventName}`);

                // jQuery event listener
                $(document).off(`${eventName}.gf-live-update`).on(`${eventName}.gf-live-update`, function (event, payload) {
                    console.log(`Wicket GF Live Update: ${eventName} jQuery event:`, payload);

                    $('.wicket-gf-live-update-target').each(function () {
                        const $hiddenField = $(this);
                        const targetDataSource = $hiddenField.data('live-update-data-source');

                        if (targetDataSource === 'person_profile') {
                            const formId = $hiddenField.closest('form').attr('id');
                            const fieldId = $hiddenField.attr('id');
                            const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                            const targetValueKey = $hiddenField.data('live-update-value-key');

                            console.log(`Wicket GF Live Update: Processing field ${fieldId}, schema ${targetSchemaSlug}, key ${targetValueKey}`);
                            const extractedValue = extractValueFromPayload(payload, 'person_profile', targetSchemaSlug, targetValueKey, `person_profile (${eventName})`);
                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, `person_profile (${eventName})`);
                            } else {
                                console.warn(`Wicket GF Live Update: No value extracted for field ${fieldId}`);
                            }
                        }
                    });
                });

                // Native DOM event listener
                window.addEventListener(eventName, function (event) {
                    console.log(`Wicket GF Live Update: ${eventName} native event:`, event.detail);

                    $('.wicket-gf-live-update-target').each(function () {
                        const $hiddenField = $(this);
                        const targetDataSource = $hiddenField.data('live-update-data-source');

                        if (targetDataSource === 'person_profile') {
                            const formId = $hiddenField.closest('form').attr('id');
                            const fieldId = $hiddenField.attr('id');
                            const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                            const targetValueKey = $hiddenField.data('live-update-value-key');

                            console.log(`Wicket GF Live Update: Processing field ${fieldId} from native ${eventName}`);
                            const extractedValue = extractValueFromPayload(event.detail, 'person_profile', targetSchemaSlug, targetValueKey, `person_profile (native ${eventName})`);
                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, `person_profile (native ${eventName})`);
                            }
                        }
                    });
                });
            });
        }

        // --- Global listener for 'organization' ---
        let hasOrganizationTargetsOnPage = false;
        $pageHiddenFields.each(function () {
            if ($(this).data('live-update-data-source') === 'organization') {
                hasOrganizationTargetsOnPage = true;
                return false; // break
            }
        });

        if (hasOrganizationTargetsOnPage) {
            // Listen for organization widget save success events
            $(document).off('wicket-organization-save-success.gf-live-update').on('wicket-organization-save-success.gf-live-update', function (event, payload) {
                //console.log('Wicket GF Live Update: Organization save success event:', payload);

                $('.wicket-gf-live-update-target').each(function () {
                    const $hiddenField = $(this);
                    const targetDataSource = $hiddenField.data('live-update-data-source');
                    const orgUuid = $hiddenField.data('live-update-organization-uuid');

                    if (targetDataSource === 'organization' && orgUuid && payload.organization && payload.organization.uuid === orgUuid) {
                        const formId = $hiddenField.closest('form').attr('id');
                        const fieldId = $hiddenField.attr('id');
                        const targetSchemaSlug = $hiddenField.data('live-update-schema-slug');
                        const targetValueKey = $hiddenField.data('live-update-value-key');

                        //console.log(`Wicket GF Live Update: Organization listener: Processing field ${fieldId}, schema ${targetSchemaSlug}, key ${targetValueKey}`);
                        const extractedValue = extractValueFromPayload(payload, 'organization', targetSchemaSlug, targetValueKey, 'organization (global event)');
                        if (typeof extractedValue !== 'undefined') {
                            updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, 'organization (global event)');
                        }
                    }
                });
            });
        }

        // Try to populate fields on load with any available data
        populateFieldsOnLoad();
    } // End of initializeWicketGFSupport

    Wicket.ready(function () {
        //console.log('Wicket GF Live Update: Wicket ready, initial setup.');
        initializeWicketGFSupport(); // Initial call
    });

    $(document).on('gform_post_render', function (event, form_id, current_page) {
        //console.log(`Wicket GF Live Update: gform_post_render event for form ${form_id}, page ${current_page}. Re-initializing.`);
        if (typeof Wicket !== 'undefined' && typeof Wicket.ready === 'function') { // Wicket should be ready by now
            initializeWicketGFSupport();
        } else {
            //console.warn('Wicket GF Live Update: Wicket SDK not found on gform_post_render.');
        }
    });
});
