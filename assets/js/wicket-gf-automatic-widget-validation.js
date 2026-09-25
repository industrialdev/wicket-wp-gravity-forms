/**
 * Wicket MDP Automatic Widget Detection and Validation
 *
 * Automatically detects MDP widgets on the page, reads their required fields,
 * and prevents Gravity Forms progression when required fields are incomplete.
 * Uses the official MDP widget API for dynamic field detection.
 */

// Field keys the widget reports as incomplete but the user cannot edit inside
// the widget. Mirrors ignoredIncompleteFieldKeys in the widget-profile-org
// component and VALIDATION_IGNORED_HIDDEN_FIELDS in WidgetProfileOrg.php.
const NON_EDITABLE_INCOMPLETE_KEYS = ['type'];

const WicketMDPAutoValidation = {

    /**
     * Track active widgets and their validation state
     */
    activeWidgets: new Map(),

    /**
     * Current validation state across all widgets
     */
    currentValidationState: {
        hasRequiredFields: false,
        incompleteRequiredFields: [],
        incompleteRequiredResources: [],
        widgetsReady: false,
        hasVisibleWidgetOnCurrentStep: false
    },

    /**
     * Debug logging control - set to true to enable comprehensive debug logging
     */
    enableLogging: false,

    /**
     * Translatable strings, overridden from WicketMDPAutoValidationConfig.i18n (PHP-side __())
     */
    i18n: {
        requiredFieldsHeading: 'Please complete the required fields',
        requiredFieldsFallback: 'Please complete all required fields before continuing.'
    },

    /**
     * Centralized logging method
     */
    log(message, data = null) {
        if (!this.enableLogging) return;

        if (data !== null) {
            console.log(`[WGF-AUTOVALID] ${message}:`, data);
        } else {
            console.log(`[WGF-AUTOVALID] ${message}`);
        }
    },

    /**
     * Initialize the automatic validation system
     */
    init() {
        const config = window.WicketMDPAutoValidationConfig || {};
        const forceLoggingFromQuery = window.location.search.indexOf('wicketGfDebug=1') !== -1;
        this.enableLogging = Boolean(forceLoggingFromQuery);
        this.i18n = Object.assign({}, this.i18n, config.i18n || {});

        this.log('Initializing automatic MDP widget validation');
        this.log('Configuration:', {
            enableLogging: this.enableLogging,
            autoDetectionEnabled: true,
            config
        });

        this.detectWicketPresence();
        this.setupValidationSystem();
    },

    /**
     * Check if Wicket SDK is available and widgets might be present
     */
    detectWicketPresence() {
        if (typeof window.Wicket !== 'undefined') {
            this.log('Wicket SDK detected, setting up widget listeners');
            this.setupWidgetDetection();
            return true;
        } else {
            this.log('Wicket SDK not found, automatic validation disabled');
            return false;
        }
    },

    /**
     * Setup widget detection and monitoring
     */
    setupWidgetDetection() {
        const self = this;

        // Use Wicket.ready to ensure SDK is fully loaded
        if (window.Wicket && window.Wicket.ready) {
            window.Wicket.ready(() => {
                self.log('Wicket SDK ready, monitoring for widgets');
                self.startWidgetMonitoring();
            });
        } else {
            // Fallback: try to detect widgets periodically
            this.startWidgetMonitoring();
        }
    },

    /**
     * Start monitoring for widget creation and events
     */
    startWidgetMonitoring() {
        const self = this;

        // Listen for widget-related events
        const widgetEvents = [
            // Widget creation events
            'wwidget-component-common-loaded',
            'wwidget-component-profile-ind-loaded',
            'wwidget-component-profile-org-loaded',
            'wwidget-component-additional-info-loaded',
            'wwidget-component-prefs-person-loaded',

            // Widget state change events
            'wwidget-component-common-state-changed',
            'wwidget-component-profile-ind-state-changed',
            'wwidget-component-profile-org-state-changed',
            'wwidget-component-additional-info-state-changed',
            'wwidget-component-prefs-person-state-changed',

            // Widget save/delete/validation events (include validation data)
            'wwidget-component-profile-ind-save-success',
            'wwidget-component-profile-org-save-success',
            'wwidget-component-additional-info-save-success',
            'wwidget-component-prefs-person-save-success',
            'wwidget-component-profile-ind-delete-success',
            'wwidget-component-profile-org-delete-success',
            'wwidget-component-additional-info-delete-success',
            'wwidget-component-prefs-person-delete-success',
            'wwidget-component-profile-ind-validation-error',
            'wwidget-component-profile-org-validation-error',
            'wwidget-component-additional-info-validation-error',
            'wwidget-component-prefs-person-validation-error',

            // Legacy events for compatibility
            'wicket_current_person_data_updated',
            'wicket_current_org_data_updated'
        ];

        widgetEvents.forEach(eventType => {
            window.addEventListener(eventType, (event) => {
                self.handleWidgetEvent(eventType, event);
            });
        });

        this.log('Listening for widget events:', widgetEvents);

        // Also scan DOM periodically for widgets that might not emit events
        this.startDOMWidgetScanning();
    },

    /**
     * Handle widget events and extract validation information
     */
    handleWidgetEvent(eventType, event) {
        const widgetData = event.detail;
        if (!widgetData) {
            return;
        }

        this.log(`Widget event: ${eventType}`, widgetData.incompleteRequiredFields);

        // Extract validation information from the event payload
        const validationInfo = this.extractValidationInfo(widgetData);

        // Store the widget's latest payload first so the state derivation in
        // updateValidationState() sees this event
        this.trackWidget(eventType, widgetData, validationInfo);

        if (validationInfo) {
            this.updateValidationState(eventType);
            // Update hidden form fields with current widget data
            this.updateHiddenFormFields(widgetData);
        }
    },

    /**
     * Update hidden form fields with current widget data using documented MDP APIs
     */
    updateHiddenFormFields(widgetData) {
        // Use Wicket.ready to ensure we're working with the official API
        if (typeof window.Wicket !== 'undefined' && window.Wicket.ready) {
            window.Wicket.ready(() => {
                // Find all hidden inputs that might contain widget data, but EXCLUDE data bind fields.
                // API Data Bind targets (wicket-gf-api-data-bind-target) carry their own fetched value;
                // merging widget payloads into them corrupts the entry (e.g. NJBIA form 146 Employee Count).
                const hiddenInputs = document.querySelectorAll('input[type="hidden"][name*="wicket"]:not(.wicket-gf-hidden-data-bind-target):not(.wicket-gf-api-data-bind-target), input[type="hidden"][class*="wicket"]:not(.wicket-gf-hidden-data-bind-target):not(.wicket-gf-api-data-bind-target)');

                if (hiddenInputs.length > 0) {
                    this.log(`Updating ${hiddenInputs.length} hidden fields`, widgetData.incompleteRequiredFields);

                    hiddenInputs.forEach((input) => {
                        try {
                            // Try to parse existing data
                            let existingData = {};
                            try {
                                existingData = JSON.parse(input.value || '{}');
                            } catch (e) {
                                existingData = {};
                            }

                            // Update with current widget data from documented event payload
                            if (widgetData.incompleteRequiredFields !== undefined) {
                                existingData.incompleteRequiredFields = widgetData.incompleteRequiredFields;
                            }
                            if (widgetData.incompleteRequiredResources !== undefined) {
                                existingData.incompleteRequiredResources = widgetData.incompleteRequiredResources;
                            }
                            if (widgetData.notFound !== undefined) {
                                existingData.notFound = widgetData.notFound;
                            }
                            if (widgetData.validation) {
                                existingData.validation = widgetData.validation;
                            }

                            // Update the hidden field value
                            const newValue = JSON.stringify(existingData);
                            input.value = newValue;
                        } catch (error) {
                            this.log(`Error updating hidden field ${input.name}:`, error);
                        }
                    });
                }
            });
        }
    },

    /**
     * Extract validation information from widget event payload
     */
    extractValidationInfo(widgetData) {
        // Look for standard MDP widget validation properties
        const validationInfo = {
            incompleteRequiredFields: widgetData.incompleteRequiredFields || [],
            incompleteRequiredResources: widgetData.incompleteRequiredResources || [],
            notFound: widgetData.notFound || [],
            resource: widgetData.resource || null,
            validation: widgetData.validation || []
        };

        // Check if this widget has any required fields to validate
        const hasValidationData = validationInfo.incompleteRequiredFields.length > 0 ||
                                 validationInfo.incompleteRequiredResources.length > 0 ||
                                 validationInfo.notFound.length > 0;

        // A payload that explicitly carries the validation keys asserts
        // completeness even when every list is empty (complete save). Without
        // this check a completing save could not register and the old blocked
        // state would persist (WWID-2641).
        const hasValidationKeys = ['incompleteRequiredFields', 'incompleteRequiredResources', 'notFound']
            .some(key => Object.prototype.hasOwnProperty.call(widgetData, key));

        if (hasValidationData || hasValidationKeys) {
            this.log('Validation data found:', validationInfo);
            return validationInfo;
        }

        // For person/organization profile widgets, check if there are any fields at all
        if (widgetData.attributes || widgetData.addresses || widgetData.emails ||
            widgetData.phones || widgetData.webAddresses) {
            // This appears to be a profile widget, assume it might have required fields
            return {
                ...validationInfo,
                hasProfileData: true
            };
        }

        return null;
    },

    /**
     * Track active widgets and their states
     */
    /**
     * Map an event type to its widget kind slot. Every event from one widget
     * component describes the same form field, so they must share one slot:
     * save-success events carry the saved resource's id (an address, an email),
     * not the widget's, so keying by resource id accumulated stale one-shot
     * entries that permanently poisoned the derived blocked set (WWID-2641).
     */
    widgetKindForEvent(eventType) {
        if (eventType.indexOf('profile-org') !== -1 || eventType === 'wicket_current_org_data_updated') return 'profile-org';
        if (eventType.indexOf('profile-ind') !== -1 || eventType === 'wicket_current_person_data_updated') return 'profile-ind';
        if (eventType.indexOf('additional-info') !== -1) return 'additional-info';
        if (eventType.indexOf('prefs-person') !== -1) return 'prefs-person';
        if (eventType.indexOf('common') !== -1) return 'common';
        return eventType;
    },

    /**
     * Widget kind for a rendered widget container, matching slot names used
     * by widgetKindForEvent; null for containers we do not track.
     */
    widgetKindForElement(widget) {
        if (widget.classList.contains('wicket__widgets--organization')) return 'profile-org';
        if (widget.classList.contains('wicket__widgets--person')) return 'profile-ind';
        if (widget.classList.contains('wicket__widgets--additional-info')) return 'additional-info';
        if (widget.classList.contains('wicket__widgets--preferences')) return 'prefs-person';
        return null;
    },

    /**
     * Refresh widget slots from the components' own hidden payloads.
     *
     * The component re-serializes its live model into input_<id> as the user
     * saves resources, so that payload is fresher than save-success event
     * snapshots, which can lag a save by a refetch (observed live: the event
     * still reported all four incomplete after the address had committed).
     * The server validates this same field, so anchoring the click-time
     * decision on it keeps the red box and the server in agreement (WWID-2641).
     */
    syncFromHiddenPayloads() {
        let synced = false;
        document.querySelectorAll('.wicket__widgets').forEach(widget => {
            if (!this.isWidgetVisible(widget)) return;
            const kind = this.widgetKindForElement(widget);
            if (!kind) {
                this.log('Unmapped widget container kind; hidden-payload sync skipped', widget.className);
                return;
            }

            // The component's hidden inputs are GF-field siblings of the
            // widget container, not children — scope to the field wrapper.
            const fieldWrapper = widget.closest('.gfield') || widget.parentElement;
            if (!fieldWrapper) return;

            fieldWrapper.querySelectorAll('input[type="hidden"]').forEach(input => {
                if (!/^input_\d+$/.test(input.name) || !input.value) return;
                let data;
                try { data = JSON.parse(input.value); } catch (e) { return; }
                if (!data || typeof data !== 'object') return;
                if (data.incompleteRequiredResources === undefined && data.incompleteRequiredFields === undefined) return;

                const info = {
                    incompleteRequiredFields: Array.isArray(data.incompleteRequiredFields) ? data.incompleteRequiredFields : [],
                    incompleteRequiredResources: Array.isArray(data.incompleteRequiredResources) ? data.incompleteRequiredResources : [],
                    notFound: Array.isArray(data.notFound) ? data.notFound : []
                };
                this.trackWidget(kind, { resource: { id: input.name } }, info);
                synced = true;
            });
        });

        // Re-derive after the slots changed; without this the refreshed Map
        // values never reach currentValidationState, because only real widget
        // events trigger the derivation (WWID-2641 round-3 review).
        if (synced) {
            this.updateValidationState('hidden-payload-sync');
        }
    },

    trackWidget(eventType, widgetData, validationInfo = null) {
        const kind = this.widgetKindForEvent(eventType);
        const previous = this.activeWidgets.get(kind);

        this.activeWidgets.set(kind, {
            eventType,
            data: widgetData,
            instanceId: widgetData.resource?.id || (previous ? previous.instanceId : null),
            // Payloads flagged hasProfileData assert nothing about completeness
            // (extractValidationInfo fallback); keep the last-known payload so
            // a data-less event cannot make a widget look complete
            validationInfo: (validationInfo && !validationInfo.hasProfileData)
                ? validationInfo
                : (previous ? previous.validationInfo : null),
            lastUpdate: Date.now()
        });

        this.log(`Tracking widget slot: ${kind}, total slots: ${this.activeWidgets.size}`);
    },

    /**
     * Derive the overall validation state from each widget slot's LATEST
     * payload (one slot per widget kind, see widgetKindForEvent).
     *
     * Deriving from current state (instead of union-merging every event
     * payload across time) lets a saved resource unblock navigation
     * immediately. The old merge only cleared when one save reported a fully
     * complete org, so an org that loaded incomplete stayed blocked in the red
     * box even after the user filled everything (WWID-2641).
     */
    updateValidationState(eventType) {
        const fields = new Set();
        const resources = new Set();

        this.activeWidgets.forEach((widget, kind) => {
            const info = widget.validationInfo;
            if (!info) return;

            // The 'common' component mirrors person/org state the dedicated
            // slots and hidden payloads already cover, and its snapshots go
            // stale between events; counting it re-blocks completed orgs
            // (WWID-2641 live repro).
            if (kind === 'common') return;

            info.incompleteRequiredFields.forEach((field) => {
                if (!NON_EDITABLE_INCOMPLETE_KEYS.includes(field)) {
                    fields.add(field);
                }
            });

            info.incompleteRequiredResources.forEach((resource) => resources.add(resource));
        });

        this.currentValidationState.incompleteRequiredFields = [...fields];
        this.currentValidationState.incompleteRequiredResources = [...resources];
        this.currentValidationState.hasRequiredFields = true;
        this.currentValidationState.widgetsReady = true;

        this.log(`Validation updated on ${eventType}: ${fields.size} incomplete fields, ${resources.size} incomplete resources`, {
            incompleteRequiredFields: this.currentValidationState.incompleteRequiredFields,
            incompleteRequiredResources: this.currentValidationState.incompleteRequiredResources
        });

        // Every widget now reports complete, so drop the banner. The old code
        // only hid it from the clear-on-complete path of a save-success event.
        if (fields.size === 0 && resources.size === 0) {
            this.hideValidationErrors();
        }
    },

    /**
     * Start periodic DOM scanning for widgets
     */
    startDOMWidgetScanning() {
        const self = this;

        // Scan every 2 seconds for widget elements
        setInterval(() => {
            self.scanForWidgetElements();
        }, 2000);

        // Initial scan
        setTimeout(() => {
            self.scanForWidgetElements();
        }, 1000);
    },

    /**
     * Scan DOM for MDP widget elements and validate using HTML parsing
     */
    scanForWidgetElements() {
        // Look for common MDP widget selectors
        const widgetSelectors = [
            '[id*="profile-"]',  // Based on the HTML example: id="profile-2153092545028218887"
            '.wicket__widgets',
            '.wicket-person-profile',
            '.wicket-org-profile',
            '.wicket-additional-info',
            '.wicket-preferences'
        ];

        let foundWidgets = false;
        let visibleWidgets = 0;

        widgetSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                elements.forEach((element) => {
                    // Only validate widgets that are visible on the current form step
                    if (this.isWidgetVisible(element)) {
                        visibleWidgets++;
                        foundWidgets = true;
                        this.validateWidgetFromHTML(element);
                    }
                });
            }
        });
        if (visibleWidgets > 0) {
            this.log(`Found ${visibleWidgets} visible widgets to validate`);
        }

        if (foundWidgets && !this.currentValidationState.widgetsReady) {
            this.currentValidationState.widgetsReady = true;
        }
    },

    /**
     * Check if a widget is visible on the current form step
     */
    isWidgetVisible(widgetElement) {
        // Check if the element itself is visible
        const style = window.getComputedStyle(widgetElement);
        const isVisible = style.display !== 'none' &&
                         style.visibility !== 'hidden' &&
                         style.opacity !== '0' &&
                         widgetElement.offsetWidth > 0 &&
                         widgetElement.offsetHeight > 0;

        if (!isVisible) {
            return false;
        }

        // Check if the widget is within a visible Gravity Form page/step
        const gfPage = widgetElement.closest('.gform_page');
        if (gfPage) {
            const gfPageStyle = window.getComputedStyle(gfPage);
            const isGfPageVisible = gfPageStyle.display !== 'none' &&
                                   gfPage.offsetWidth > 0 &&
                                   gfPage.offsetHeight > 0;
            return isGfPageVisible;
        }

        // Check if the widget is within any container that might be hidden
        const hiddenContainer = widgetElement.closest('[style*="display: none"], [style*="visibility: hidden"], .gform_hidden, .gf_step_hidden, .gform_page_fields:has(> .gform_hidden)');
        if (hiddenContainer) {
            return false;
        }

        // Check if the widget is in the current viewport or at least in the current form step
        const rect = widgetElement.getBoundingClientRect();
        const isInViewport = rect.top < window.innerHeight && rect.bottom > 0;

        // For Gravity Forms multi-step, widgets on non-current steps might still be in DOM but not visible
        // We'll consider a widget visible if it's not explicitly hidden and is reasonably close to viewport
        const isNearViewport = rect.top < window.innerHeight + 1000 && rect.bottom > -500;

        return isInViewport || isNearViewport;
    },

    /**
     * Validate widget by parsing its HTML structure to find required fields
     */
    validateWidgetFromHTML(widgetElement) {
        // Find all required field indicators within this widget
        const requiredIndicators = widgetElement.querySelectorAll('.required-symbol');
        const incompleteFields = [];
        const existingIncompleteResources = Array.isArray(this.currentValidationState.incompleteRequiredResources)
            ? this.currentValidationState.incompleteRequiredResources
            : [];

        requiredIndicators.forEach((indicator) => {
            // Same read-only skip as getIncompleteFieldsFromHTML: this scanner
            // also writes field-level state and must not flag display fields
            // the user cannot edit (WWID-2641)
            if (indicator.closest('.InputStatic')) {
                return;
            }

            const label = this.findFieldLabel(indicator);
            if (label) {
                const fieldValue = this.getFieldValue(indicator);
                if (!fieldValue || fieldValue.trim() === '') {
                    incompleteFields.push(label);
                }
            }
        });

        // Update field-level validation state based on HTML parsing.
        // Preserve resource-level validation from widget events.
        if (incompleteFields.length > 0) {
            this.currentValidationState.incompleteRequiredFields = incompleteFields;
            this.currentValidationState.hasRequiredFields = true;
        } else {
            this.currentValidationState.incompleteRequiredFields = [];
            this.currentValidationState.hasRequiredFields = existingIncompleteResources.length > 0 || this.hasAnyRequiredFields();
        }
    },

    /**
     * Find the field label associated with a required indicator
     */
    findFieldLabel(requiredIndicator) {
        // Look for the label element that contains this required indicator
        let labelElement = requiredIndicator.closest('.label');
        if (labelElement) {
            // Get the text content, removing the required indicator and extra whitespace
            const labelText = labelElement.textContent.replace(/\*\s*$/, '').trim();
            return labelText;
        }

        // Fallback: look for a control-label that contains this indicator
        labelElement = requiredIndicator.closest('.control-label');
        if (labelElement) {
            const labelText = labelElement.textContent.replace(/\*\s*$/, '').trim();
            return labelText;
        }

        // Additional fallback: try to find parent with form-group
        const formGroup = requiredIndicator.closest('.form-group');
        if (formGroup) {
            const groupLabel = formGroup.querySelector('label');
            if (groupLabel) {
                const labelText = groupLabel.textContent.replace(/\*\s*$/, '').trim();
                return labelText;
            }
        }

        return null;
    },

    /**
     * Get the actual value of a field based on its required indicator
     */
    getFieldValue(requiredIndicator) {
        // Find the parent container that holds both the label and the value
        const container = requiredIndicator.closest('.InputStatic, .TypeableResource, .form-group');
        if (!container) {
            return null;
        }

        // For InputStatic fields (read-only display fields)
        const valueElement = container.querySelector('.value, .TypeableResource__content-value, .form-control');
        if (valueElement) {
            const value = valueElement.textContent.trim();
            if (value) return value; // It's possible to get a false positive on the value element (i.e. datePicker). Instead of being hyper specific on the query selector, allow it to fall through to other methods.
        }

        // For editable fields that might have input elements
        const inputElement = container.querySelector('input, select, textarea');
        if (inputElement) {
            const value = inputElement.value.trim();
            if (value) return value; // It's possible to get a false positive on the value element (i.e. datePicker). Instead of being hyper specific on the query selector, allow it to fall through to other methods.
        }


        // Try additional selectors that might contain the field value
        const additionalSelectors = [
            '.InputStatic__value',
            '.TypeableResource__value',
            '[data-value]',
            '.field-value',
            '.static-value'
        ];

        for (const selector of additionalSelectors) {
            const additionalElement = container.querySelector(selector);
            if (additionalElement) {
                const value = additionalElement.textContent.trim() || additionalElement.getAttribute('data-value') || '';
                if (value) {
                    return value;
                }
            }
        }

        return null;
    },

    /**
     * Setup the validation system and form interception
     */
    setupValidationSystem() {
        this.createValidationErrorContainer();
        this.setupNextButtonInterception();
        this.setupFormSubmissionInterception();
    },

    /**
     * Create validation error container
     */
    createValidationErrorContainer() {
        if (document.getElementById('wicket-mdp-auto-validation-errors')) {
            return;
        }

        const errorContainer = document.createElement('div');
        errorContainer.id = 'wicket-mdp-auto-validation-errors';
        errorContainer.className = 'wicket-mdp-auto-validation-errors';

        // Set a flag to indicate automatic validation is active
        window.wicketMDPAutoValidationActive = true;
        errorContainer.style.cssText = `
            background-color: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            position: relative;
            z-index: 1000;
            border-left: 4px solid #a71d2a;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
        `;

        const targetLocation = document.querySelector('.gform_wrapper') ||
                              document.querySelector('main') ||
                              document.querySelector('#main');

        if (targetLocation) {
            targetLocation.parentNode.insertBefore(errorContainer, targetLocation);
        } else {
            document.body.insertBefore(errorContainer, document.body.firstChild);
        }
    },

    /**
     * Show validation errors
     */
    showValidationErrors(message) {
        const errorContainer = document.getElementById('wicket-mdp-auto-validation-errors');
        if (!errorContainer) return;

        const errorHtml = `
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="font-size: 20px; line-height: 1; margin-top: 2px;">⚠️</div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 16px; margin-bottom: 8px;">
                        ${this.i18n.requiredFieldsHeading}
                    </div>
                    <div style="font-size: 14px; line-height: 1.5;">
                        ${message}
                    </div>
                </div>
            </div>
        `;

        errorContainer.innerHTML = errorHtml;
        errorContainer.style.display = 'block';
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Error message now persists until another error needs to be shown
        // Removed auto-hide timeout
    },

    /**
     * Hide validation errors
     */
    hideValidationErrors() {
        const errorContainer = document.getElementById('wicket-mdp-auto-validation-errors');
        if (errorContainer) {
            errorContainer.style.display = 'none';
        }
    },

    /**
     * Setup next button click interception
     */
    setupNextButtonInterception() {
        const self = this;

        document.addEventListener('click', function(event) {
            const target = event.target;
            const nextButton = target.closest('.gform_next_button, [id^="gform_next_button_"]');

            if (nextButton) {
                // Perform a fresh HTML scan before validation
                self.updateValidationFromHTML();
                // Sync hidden payload + GF validation flags for this transition.
                self.updateAllWidgetDataBeforeSubmit();

                if (self.shouldBlockNavigation()) {
                    event.preventDefault();
                    event.stopPropagation();

                    const errorMessage = self.buildErrorMessage();
                    self.log('Next navigation blocked', {
                        currentValidationState: self.getValidationState(),
                        errorMessage
                    });
                    self.showValidationErrors(errorMessage);

                    return false;
                } else {
                    self.log('Next navigation allowed', {
                        currentValidationState: self.getValidationState()
                    });
                    self.hideValidationErrors();
                }
            }
        }, true);
    },

    /**
     * Setup form submission interception
     */
    setupFormSubmissionInterception() {
        const self = this;

        document.addEventListener('submit', function(event) {
            const form = event.target;

            if (form.id && form.id.startsWith('gform_')) {
                self.log('Gravity Form submission detected, checking widget validation');

                // Recompute from current DOM before mutating hidden fields.
                self.updateValidationFromHTML();

                // First, update hidden fields with latest widget data before validation
                self.updateAllWidgetDataBeforeSubmit();

                // Check if we should block navigation
                const shouldBlock = self.shouldBlockNavigation();

                if (shouldBlock) {
                    self.log('Form submission validation failed, preventing submit');
                    event.preventDefault();
                    event.stopPropagation();

                    const errorMessage = self.buildErrorMessage();
                    self.log('Submit blocked', {
                        currentValidationState: self.getValidationState(),
                        errorMessage
                    });
                    self.showValidationErrors(errorMessage);

                    return false;
                } else {
                    self.log('Submit allowed', {
                        currentValidationState: self.getValidationState()
                    });
                }
            }
        }, true);
    },

    /**
     * Update all widget data before form submission using documented MDP APIs
     */
    updateAllWidgetDataBeforeSubmit() {
        // Use Wicket.ready to ensure we're working with the official API
        if (typeof window.Wicket !== 'undefined' && window.Wicket.ready) {
            window.Wicket.ready(() => {
                this.log('Updating all widget data before form submission');

                // Find all hidden inputs that might contain widget data, but EXCLUDE data bind fields.
                // API Data Bind targets (wicket-gf-api-data-bind-target) carry their own fetched value;
                // merging widget payloads into them corrupts the entry (e.g. NJBIA form 146 Employee Count).
                const hiddenInputs = document.querySelectorAll('input[type="hidden"][name*="wicket"]:not(.wicket-gf-hidden-data-bind-target):not(.wicket-gf-api-data-bind-target), input[type="hidden"][class*="wicket"]:not(.wicket-gf-hidden-data-bind-target):not(.wicket-gf-api-data-bind-target)');

                hiddenInputs.forEach((input) => {
                    try {
                        // Try to parse existing data
                        let existingData = {};
                        try {
                            existingData = JSON.parse(input.value || '{}');
                        } catch (e) {
                            existingData = {};
                        }

                        // Update incomplete fields from current validation state
                        const incompleteFields = this.currentValidationState.incompleteRequiredFields;
                        const incompleteResources = this.currentValidationState.incompleteRequiredResources;

                        if (incompleteFields.length > 0) {
                            existingData.incompleteRequiredFields = incompleteFields;
                        } else {
                            existingData.incompleteRequiredFields = [];
                        }

                        if (incompleteResources.length > 0) {
                            existingData.incompleteRequiredResources = incompleteResources;
                        } else {
                            existingData.incompleteRequiredResources = [];
                        }

                        // Update the hidden field value
                        const newValue = JSON.stringify(existingData);
                        input.value = newValue;

                        this.log(`Updated hidden field ${input.name} before submission`);
                    } catch (error) {
                        this.log(`Error updating hidden field ${input.name} before submission:`, error);
                    }
                });
            });
        }

        if (!this.currentValidationState.hasVisibleWidgetOnCurrentStep) {
            this.log('Skipping hidden validation flag sync: no visible widget on current step');
            return;
        }

        // Keep GF per-field validation flags aligned with the latest computed state.
        const hasIncomplete = this.currentValidationState.incompleteRequiredFields.length > 0 ||
            this.currentValidationState.incompleteRequiredResources.length > 0;
        const validationValue = hasIncomplete ? 'false' : 'true';
        const validationInputs = document.querySelectorAll('input[type="hidden"][name^="input_"][name$="_validation"]');

        validationInputs.forEach((input) => {
            input.value = validationValue;
        });

        this.log('Updated GF validation flags before submission', {
            validationInputsCount: validationInputs.length,
            validationValue,
            incompleteRequiredFields: this.currentValidationState.incompleteRequiredFields,
            incompleteRequiredResources: this.currentValidationState.incompleteRequiredResources
        });
    },

    /**
     * Check if navigation should be blocked based on widget validation
     */
    shouldBlockNavigation() {
        // Validate/block only when widget is visible on the current GF step.
        if (!this.currentValidationState.hasVisibleWidgetOnCurrentStep) {
            return false;
        }

        if (!this.currentValidationState.widgetsReady) {
            return false;
        }

        if (!this.currentValidationState.hasRequiredFields) {
            return false;
        }

        // Block if there are incomplete required fields
        const incompleteFields = this.currentValidationState.incompleteRequiredFields;
        const incompleteResources = this.currentValidationState.incompleteRequiredResources;
        const hasIncompleteFields = incompleteFields.length > 0 || incompleteResources.length > 0;
        this.log('shouldBlockNavigation evaluated', {
            widgetsReady: this.currentValidationState.widgetsReady,
            hasRequiredFields: this.currentValidationState.hasRequiredFields,
            incompleteRequiredFields: incompleteFields,
            incompleteRequiredResources: incompleteResources,
            result: hasIncompleteFields
        });

        // Special debug for "job level" field
        const jobLevelFields = incompleteFields.filter(field =>
            field.toLowerCase().includes('job') || field.toLowerCase().includes('level')
        );
        if (jobLevelFields.length > 0) {
            this.log('🚨 Job level field blocking navigation:', jobLevelFields);
        }

        return hasIncompleteFields;
    },

    /**
     * Update validation state by parsing HTML of all widgets
     */
    updateValidationFromHTML() {
        // Anchor the blocked set on the components' live hidden payloads first,
        // then let the DOM scan refresh the field-level list on top.
        this.syncFromHiddenPayloads();

        const widgetSelectors = [
            '[id*="profile-"]',
            '.wicket__widgets',
            '.wicket-person-profile',
            '.wicket-org-profile',
            '.wicket-additional-info',
            '.wicket-preferences'
        ];

        let allIncompleteFields = [];
        let widgetsFound = false;
        let visibleWidgetsCount = 0;

        widgetSelectors.forEach((selector) => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                elements.forEach((element) => {
                    // Only process visible widgets
                    if (this.isWidgetVisible(element)) {
                        visibleWidgetsCount++;
                        widgetsFound = true;

                        const incompleteFields = this.getIncompleteFieldsFromHTML(element);
                        allIncompleteFields.push(...incompleteFields);
                    }
                });
            }
        });

        // Update the global validation state
        if (widgetsFound) {
            const existingIncompleteResources = Array.isArray(this.currentValidationState.incompleteRequiredResources)
                ? this.currentValidationState.incompleteRequiredResources
                : [];

            this.currentValidationState.widgetsReady = true;
            this.currentValidationState.hasVisibleWidgetOnCurrentStep = true;
            this.currentValidationState.hasRequiredFields =
                allIncompleteFields.length > 0 ||
                existingIncompleteResources.length > 0 ||
                this.hasAnyRequiredFields();
            this.currentValidationState.incompleteRequiredFields = allIncompleteFields;

            if (allIncompleteFields.length > 0) {
                this.log(`Found ${allIncompleteFields.length} incomplete fields in visible widgets`, allIncompleteFields);
            }
        } else {
            const existingIncompleteFields = Array.isArray(this.currentValidationState.incompleteRequiredFields)
                ? this.currentValidationState.incompleteRequiredFields
                : [];
            const existingIncompleteResources = Array.isArray(this.currentValidationState.incompleteRequiredResources)
                ? this.currentValidationState.incompleteRequiredResources
                : [];
            const hasExistingValidationFailures = existingIncompleteFields.length > 0 || existingIncompleteResources.length > 0;

            // No visible widget on the current GF step: do not block navigation on this step.
            // Keep last-known arrays for diagnostics but mark this step as non-widget.
            this.currentValidationState.widgetsReady = hasExistingValidationFailures;
            this.currentValidationState.hasVisibleWidgetOnCurrentStep = false;
            this.currentValidationState.hasRequiredFields = false;
            this.currentValidationState.incompleteRequiredFields = existingIncompleteFields;
            this.currentValidationState.incompleteRequiredResources = existingIncompleteResources;
        }
    },

    /**
     * Get incomplete fields from a specific widget element
     */
    getIncompleteFieldsFromHTML(widgetElement) {
        const incompleteFields = [];

        // Find all required field indicators within this widget
        const requiredIndicators = widgetElement.querySelectorAll('.required-symbol');

        requiredIndicators.forEach((indicator) => {
            // Read-only display fields cannot be edited in the widget, so
            // listing them traps the step (WWID-2641: "Type" on existing orgs
            // missing it). The widget's own events report editable-field gaps.
            if (indicator.closest('.InputStatic')) {
                return;
            }

            const label = this.findFieldLabel(indicator);
            if (label) {
                const fieldValue = this.getFieldValue(indicator);
                if (!fieldValue || fieldValue.trim() === '') {
                    incompleteFields.push(label);

                    // Special logging for job level related fields
                    if (label.toLowerCase().includes('job') || label.toLowerCase().includes('level')) {
                        this.log(`🚨 Job level field incomplete: "${label}" = "${fieldValue}"`);
                    }
                }
            }
        });

        return incompleteFields;
    },

    /**
     * Check if any visible widget has required fields at all
     */
    hasAnyRequiredFields() {
        const widgetSelectors = [
            '[id*="profile-"]',
            '.wicket__widgets',
            '.wicket-person-profile',
            '.wicket-org-profile',
            '.wicket-additional-info',
            '.wicket-preferences'
        ];

        for (const selector of widgetSelectors) {
            const elements = document.querySelectorAll(selector);
            for (const element of elements) {
                // Only check visible widgets
                if (this.isWidgetVisible(element)) {
                    const requiredIndicators = element.querySelectorAll('.required-symbol');
                    if (requiredIndicators.length > 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    },

    /**
     * Build user-friendly error message
     */
    buildErrorMessage() {
        const fields = this.currentValidationState.incompleteRequiredFields;
        const resources = this.currentValidationState.incompleteRequiredResources;

        // Combine all missing items into a single array
        const missingItems = [];

        if (fields.length > 0) {
            missingItems.push(...fields);
        }

        if (resources.length > 0) {
            missingItems.push(...resources);
        }

        if (missingItems.length === 0) {
            return this.i18n.requiredFieldsFallback;
        }

        // Format as a bulleted list
        const bulletList = missingItems
            .map(item => `<li>${this.formatFieldName(item)}</li>`)
            .join('');

        return `<ul style="margin: 0 0 0 16px; padding: 0; list-style-type: disc;">${bulletList}</ul>`;
    },

    /**
     * Format field names to be more user-friendly
     */
    formatFieldName(fieldName) {
        // Convert snake_case to Title Case
        const formatted = fieldName
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());

        // Common field name mappings
        const fieldMappings = {
            'Honorific Prefix': 'Salutation',
            'Given Name': 'First Name',
            'Family Name': 'Last Name',
            'Birth Date': 'Date of Birth',
            'Primary Address': 'Address',
            'Primary Email': 'Email Address',
            'Primary Phone': 'Phone Number'
        };

        return fieldMappings[formatted] || formatted;
    },

    /**
     * Public method to manually trigger validation check
     */
    validateNow() {
        return !this.shouldBlockNavigation();
    },

    /**
     * Public method to get current validation state
     */
    getValidationState() {
        return { ...this.currentValidationState };
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        WicketMDPAutoValidation.init();
    }, 500);
});

// Also initialize when Gravity Forms is ready
document.addEventListener('gform/post_render', function() {
    setTimeout(() => {
        WicketMDPAutoValidation.init();
    }, 100);
});

// Export for external access
window.WicketMDPAutoValidation = WicketMDPAutoValidation;
