/**
 * Wicket Gravity Forms API Data Bind - ORGSS Binding
 *
 * Handles live updates for API Data Bind fields bound to ORGSS field selections.
 * When a user selects an organization in an ORGSS field, this script fetches
 * the organization data and updates bound API Data Bind fields.
 */

const WicketGFApiDataBind = {
    /**
     * Debug logging control
     * Set to false for production to disable all logging
     */
    enableLogging: true,

    /**
     * Track which forms have been processed to avoid duplicate initialization
     */
    processedForms: new Set(),

    /**
     * Store field configurations keyed by field ID
     * Structure: { fieldId: { formId, dataSource, fieldPath, orgssFieldId, displayMode, fallback } }
     */
    fieldConfigs: new Map(),

    /**
     * Initialize the API Data Bind system
     */
    init() {
        this.log('Initializing WicketGFApiDataBind');

        // Set up Gravity Forms hooks
        this.setupGFHooks();

        // Set up ORGSS event listeners
        this.setupOrgssEventListeners();

        // Set up MutationObserver for dynamic content changes
        this.setupMutationObserver();

        // Process all forms on the page
        this.processAllForms();

        this.log('WicketGFApiDataBind initialized successfully');
    },

    /**
     * Set up Gravity Forms native hooks
     */
    setupGFHooks() {
        // Hook into form post-render (handles AJAX submissions and multi-page forms)
        if (typeof gform !== 'undefined' && gform.addAction) {
            gform.addAction('gform_post_render', (formId) => {
                this.log('GF post render event for form', formId);
                this.processForm(formId);
            });
        }
    },

    /**
     * Set up ORGSS event listeners
     */
    setupOrgssEventListeners() {
        // Listen for ORGSS selection events
        window.addEventListener('orgss-selection-made', (event) => {
            this.log('ORGSS selection made', event.detail);
            this.handleOrgssSelection(event.detail);
        });

        this.log('ORGSS event listeners set up');
    },

    /**
     * Set up MutationObserver to detect dynamic content changes
     */
    setupMutationObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        // Check if this is a form wrapper or contains form fields
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if this node contains any API Data Bind fields
                            const bindFields = node.nodeType === Node.ELEMENT_NODE ?
                                node.querySelectorAll('.wicket-gf-api-data-bind-target') : [];

                            if (bindFields.length > 0) {
                                // Find the form ID for this field
                                let formElement = node.closest('form[id^="gform_"]');
                                if (formElement) {
                                    const formIdMatch = formElement.id.match(/gform_(\d+)/);
                                    if (formIdMatch) {
                                        this.log('Detected new API Data Bind fields, reprocessing form', formIdMatch[1]);
                                        this.processForm(formIdMatch[1]);
                                    }
                                }
                            }
                        }
                    });
                }
            });
        });

        // Start observing the entire document for changes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        this.log('MutationObserver set up');
    },

    /**
     * Process all forms on the page
     */
    processAllForms() {
        const forms = document.querySelectorAll('.gform_wrapper');
        this.log('Found forms on page', forms.length);

        forms.forEach((formWrapper) => {
            const formElement = formWrapper.querySelector('form');
            if (formElement) {
                const formIdMatch = formElement.id.match(/gform_(\d+)/);
                if (formIdMatch) {
                    const formId = formIdMatch[1];
                    this.processForm(formId);
                }
            }
        });
    },

    /**
     * Process a single form and register all API Data Bind fields
     *
     * @param {string|number} formId The form ID
     */
    processForm(formId) {
        this.log('Processing form', formId);

        // Find all API Data Bind target fields in this form
        const formElement = document.getElementById('gform_' + formId);
        if (!formElement) {
            this.warn('Form element not found for ID', formId);
            return;
        }

        const bindFields = formElement.querySelectorAll('.wicket-gf-api-data-bind-target');
        this.log('Found API Data Bind fields in form', bindFields.length);

        // Clear existing field configs for this form to handle multi-step scenarios
        const fieldsToRemove = [];
        this.fieldConfigs.forEach((config, fieldId) => {
            if (config.formId === formId.toString()) {
                fieldsToRemove.push(fieldId);
            }
        });
        fieldsToRemove.forEach(fieldId => this.fieldConfigs.delete(fieldId));

        bindFields.forEach((field) => {
            this.registerField(field);
        });

        // Mark this form as processed
        this.processedForms.add(formId.toString());
        this.log('Form processed successfully', formId);
    },

    /**
     * Register a field and store its configuration
     *
     * @param {HTMLElement} field The field element
     */
    registerField(field) {
        const fieldId = field.id;

        // Extract configuration from data attributes
        const config = {
            formId: field.dataset.apiBindFormId,
            dataSource: field.dataset.apiBindDataSource,
            fieldPath: field.dataset.apiBindFieldPath,
            orgssFieldId: field.dataset.apiBindOrgssFieldId,
            displayMode: field.dataset.apiBindDisplayMode || 'hidden',
            fallback: field.dataset.apiBindFallback || ''
        };

        // Validate configuration
        if (!config.dataSource || !config.fieldPath || !config.orgssFieldId) {
            this.warn('Invalid field configuration, skipping', fieldId, config);
            return;
        }

        // Store configuration
        this.fieldConfigs.set(fieldId, config);
        this.log('Field registered', fieldId, config);

        // Check if there's stored organization data for this form (for multi-step forms)
        this.checkAndPopulateStoredData(fieldId, config);
    },

    /**
     * Handle ORGSS selection event
     *
     * @param {object} detail Event detail containing { uuid, orgDetails, formId }
     */
    handleOrgssSelection(detail) {
        const { uuid: orgUuid, formId } = detail;

        if (!orgUuid) {
            this.warn('No org UUID in ORGSS selection event');
            return;
        }

        this.log('Handling ORGSS selection for form', formId, 'org UUID', orgUuid);

        // Store the selected organization info for persistence across form steps
        this.storeOrgSelection(formId, orgUuid, detail);

        // Find all fields bound to ORGSS fields in this form
        this.fieldConfigs.forEach((config, fieldId) => {
            // Check if this field is in the same form
            if (config.formId === formId.toString()) {
                this.log('Updating field', fieldId, 'with org UUID', orgUuid);
                this.fetchAndUpdateField(fieldId, orgUuid, config);
            }
        });
    },

    /**
     * Store organization selection for persistence across form steps
     */
    storeOrgSelection(formId, orgUuid, detail) {
        try {
            const storageKey = `wicket_gf_api_bind_org_${formId}`;
            const selectionData = {
                orgUuid: orgUuid,
                formId: formId,
                timestamp: Date.now(),
                orgDetails: detail.orgDetails
            };
            sessionStorage.setItem(storageKey, JSON.stringify(selectionData));
            this.log('Stored org selection for form', formId, 'UUID', orgUuid);
        } catch (error) {
            this.warn('Failed to store org selection:', error);
        }
    },

    /**
     * Retrieve stored organization selection for this form
     */
    getStoredOrgSelection(formId) {
        try {
            const storageKey = `wicket_gf_api_bind_org_${formId}`;
            const stored = sessionStorage.getItem(storageKey);
            if (stored) {
                const data = JSON.parse(stored);
                // Only return if less than 30 minutes old
                if (Date.now() - data.timestamp < 30 * 60 * 1000) {
                    this.log('Retrieved stored org selection for form', formId);
                    return data;
                } else {
                    sessionStorage.removeItem(storageKey);
                }
            }
        } catch (error) {
            this.warn('Failed to retrieve stored org selection:', error);
        }
        return null;
    },

    /**
     * Check for stored organization data and populate field if found
     */
    checkAndPopulateStoredData(fieldId, config) {
        const storedData = this.getStoredOrgSelection(config.formId);
        if (storedData && storedData.orgUuid) {
            this.log('Found stored organization data, populating field', fieldId);
            this.fetchAndUpdateField(fieldId, storedData.orgUuid, config);
        }
    },

    /**
     * Fetch organization data and update field value
     *
     * @param {string} fieldId The field ID
     * @param {string} orgUuid The organization UUID
     * @param {object} config Field configuration
     */
    async fetchAndUpdateField(fieldId, orgUuid, config) {
        this.log('Fetching data for field', fieldId);

        // Show loading state
        this.setLoadingState(fieldId, true);

        try {
            // Validate wicketGfApiDataBindConfig is available
            if (typeof wicketGfApiDataBindConfig === 'undefined') {
                throw new Error('wicketGfApiDataBindConfig not defined');
            }

            // Make AJAX request
            const response = await fetch(wicketGfApiDataBindConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gf_wicket_api_data_bind_fetch_value',
                    nonce: wicketGfApiDataBindConfig.nonce,
                    org_uuid: orgUuid,
                    field_path: config.fieldPath,
                }),
            });

            const data = await response.json();

            if (data.success) {
                this.log('Data fetched successfully', data.data);
                this.updateFieldValue(fieldId, data.data, config);
            } else {
                this.warn('API fetch failed', data);
                this.updateFieldValue(fieldId, config.fallback, config);
            }
        } catch (error) {
            this.warn('Error fetching data', error);
            this.updateFieldValue(fieldId, config.fallback, config);
        } finally {
            this.setLoadingState(fieldId, false);
        }
    },

    /**
     * Update field value based on display mode
     *
     * @param {string} fieldId The field ID
     * @param {string} value The value to set
     * @param {object} config Field configuration
     */
    updateFieldValue(fieldId, value, config) {
        const field = document.getElementById(fieldId);
        if (!field) {
            this.warn('Field not found', fieldId);
            return;
        }

        const displayValue = value || config.fallback || '';
        this.log('Updating field value', fieldId, displayValue);

        // Update based on display mode
        switch (config.displayMode) {
            case 'hidden':
            case 'editable':
            case 'readonly':
                // Input field - set value using both vanilla JS and jQuery for compatibility
                field.value = displayValue;

                // Also try jQuery for better GF compatibility
                if (typeof jQuery !== 'undefined') {
                    jQuery(field).val(displayValue);
                }

                // Set the HTML attribute as well for inspector visibility
                field.setAttribute('value', displayValue);

                this.log('Set field value using multiple methods', fieldId, displayValue);
                console.log('[DEBUG] Field value after setting:', {
                    fieldId: fieldId,
                    domValue: field.value,
                    attrValue: field.getAttribute('value'),
                    jqueryValue: typeof jQuery !== 'undefined' ? jQuery(field).val() : 'N/A'
                });
                break;

            case 'static':
                // Static display - set text content
                if (displayValue) {
                    field.textContent = displayValue;
                } else {
                    field.innerHTML = '<em>No data available</em>';
                }
                break;

            default:
                this.warn('Unknown display mode', config.displayMode);
                field.value = displayValue;
        }

        // Trigger conditional logic
        this.triggerConditionalLogic(field, config.formId);
    },

    /**
     * Trigger Gravity Forms conditional logic for the field
     *
     * @param {HTMLElement} field The field element
     * @param {string} formId The form ID
     */
    triggerConditionalLogic(field, formId) {
        // Trigger change event
        const changeEvent = new Event('change', { bubbles: true });
        field.dispatchEvent(changeEvent);

        // GF native API (newer versions)
        if (typeof gform !== 'undefined' && gform.doAction) {
            gform.doAction('gform_input_change', field, field.value, '');
            this.log('Triggered GF conditional logic (native API)');
        }

        // Legacy GF support (older versions)
        if (typeof gf_apply_rules === 'function') {
            setTimeout(() => {
                gf_apply_rules(formId, [], false);
                this.log('Triggered GF conditional logic (legacy)');
            }, 10);
        }
    },

    /**
     * Set loading state for a field
     *
     * @param {string} fieldId The field ID
     * @param {boolean} isLoading Whether the field is loading
     */
    setLoadingState(fieldId, isLoading) {
        const field = document.getElementById(fieldId);
        if (!field) {
            return;
        }

        if (isLoading) {
            field.classList.add('wicket-gf-loading');
            // For input fields, optionally disable during load
            if (field.tagName === 'INPUT') {
                field.setAttribute('data-original-disabled', field.disabled);
                field.disabled = true;
            }
        } else {
            field.classList.remove('wicket-gf-loading');
            // Restore original disabled state
            if (field.tagName === 'INPUT' && field.hasAttribute('data-original-disabled')) {
                field.disabled = field.getAttribute('data-original-disabled') === 'true';
                field.removeAttribute('data-original-disabled');
            }
        }
    },

    /**
     * Log a message (only if logging enabled)
     *
     * @param {...any} args Arguments to log
     */
    log(...args) {
        if (this.enableLogging) {
            console.log('[WicketGFApiDataBind]', ...args);
        }
    },

    /**
     * Log a warning message (only if logging enabled)
     *
     * @param {...any} args Arguments to log
     */
    warn(...args) {
        if (this.enableLogging) {
            console.warn('[WicketGFApiDataBind]', ...args);
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        WicketGFApiDataBind.init();
    });
} else {
    // DOM already loaded
    WicketGFApiDataBind.init();
}
