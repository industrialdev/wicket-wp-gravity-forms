/**
 * Wicket MDP Automatic Widget Detection and Validation
 *
 * Automatically detects MDP widgets on the page, reads their required fields,
 * and prevents Gravity Forms progression when required fields are incomplete.
 * Uses the official MDP widget API for dynamic field detection.
 */

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
        widgetsReady: false
    },

    /**
     * Debug logging control
     */
    enableLogging: true,

    /**
     * Centralized logging method
     */
    log(message, data = null) {
        if (!this.enableLogging) return;

        if (data !== null) {
            console.log(`[WIDGET-DEBUG] ${message}:`, data);
        } else {
            console.log(`[WIDGET-DEBUG] ${message}`);
        }
    },

    /**
     * Initialize the automatic validation system
     */
    init() {
        // Override configuration with PHP values if available
        if (typeof window.WicketMDPAutoValidationConfig !== 'undefined') {
            const config = window.WicketMDPAutoValidationConfig;
            if (config.enableLogging !== undefined) {
                this.enableLogging = config.enableLogging;
            }
            if (config.enableAutoDetection !== undefined && !config.enableAutoDetection) {
                this.log('Automatic widget detection disabled by configuration');
                return;
            }
        }


        this.log('Initializing automatic MDP widget validation');
        this.log('Configuration:', {
            enableLogging: this.enableLogging,
            autoDetectionEnabled: true
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

            // Widget save events (includes validation data)
            'wwidget-component-profile-ind-save-success',
            'wwidget-component-profile-org-save-success',
            'wwidget-component-additional-info-save-success',
            'wwidget-component-prefs-person-save-success',

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
        this.log(`Widget event received: ${eventType}`);
        this.log('Full event object:', event);
        this.log('Event detail:', event.detail);

        const widgetData = event.detail;
        if (!widgetData) {
            this.log('No widget data in event');
            return;
        }

        this.log('Widget data structure:', Object.keys(widgetData));
        this.log('Incomplete required fields from event:', widgetData.incompleteRequiredFields);
        this.log('Incomplete required resources from event:', widgetData.incompleteRequiredResources);
        this.log('Not found fields from event:', widgetData.notFound);
        this.log('Validation data from event:', widgetData.validation);

        // Extract validation information from the event payload
        const validationInfo = this.extractValidationInfo(widgetData);

        if (validationInfo) {
            this.log('Validation info extracted:', validationInfo);
            this.updateValidationState(eventType, validationInfo);
            // Update hidden form fields with current widget data
            this.updateHiddenFormFields(widgetData);
        } else {
            this.log('No validation info could be extracted from widget data');
        }

        // Store widget reference for tracking
        this.trackWidget(eventType, widgetData);
    },

    /**
     * Update hidden form fields with current widget data using documented MDP APIs
     */
    updateHiddenFormFields(widgetData) {
        this.log('Starting updateHiddenFormFields');

        // Use Wicket.ready to ensure we're working with the official API
        if (typeof window.Wicket !== 'undefined' && window.Wicket.ready) {
            window.Wicket.ready(() => {
                this.log('Wicket.ready callback executed in updateHiddenFormFields');

                // Find all hidden inputs that might contain widget data, but EXCLUDE data bind fields
                const hiddenInputs = document.querySelectorAll('input[type="hidden"][name*="wicket"]:not(.wicket-gf-hidden-data-bind-target), input[type="hidden"][class*="wicket"]:not(.wicket-gf-hidden-data-bind-target)');

                this.log(`Found ${hiddenInputs.length} hidden wicket fields to update`);

                hiddenInputs.forEach((input, index) => {
                    this.log(`Processing hidden field ${index + 1}: name=${input.name}, current value=${input.value}`);

                    try {
                        // Try to parse existing data
                        let existingData = {};
                        try {
                            existingData = JSON.parse(input.value || '{}');
                            this.log(`Parsed existing data from field ${input.name}:`, existingData);
                        } catch (e) {
                            // If parsing fails, start fresh
                            this.log(`Failed to parse existing data from field ${input.name}, starting fresh. Error:`, e);
                            existingData = {};
                        }

                        // Update with current widget data from documented event payload
                        if (widgetData.incompleteRequiredFields !== undefined) {
                            this.log(`Updating incompleteRequiredFields in field ${input.name} from [${existingData.incompleteRequiredFields || 'none'}] to [${widgetData.incompleteRequiredFields}]`);
                            existingData.incompleteRequiredFields = widgetData.incompleteRequiredFields;
                        }
                        if (widgetData.incompleteRequiredResources !== undefined) {
                            this.log(`Updating incompleteRequiredResources in field ${input.name} from [${existingData.incompleteRequiredResources || 'none'}] to [${widgetData.incompleteRequiredResources}]`);
                            existingData.incompleteRequiredResources = widgetData.incompleteRequiredResources;
                        }
                        if (widgetData.notFound !== undefined) {
                            this.log(`Updating notFound in field ${input.name} from [${existingData.notFound || 'none'}] to [${widgetData.notFound}]`);
                            existingData.notFound = widgetData.notFound;
                        }
                        if (widgetData.validation) {
                            this.log(`Updating validation in field ${input.name}:`, widgetData.validation);
                            existingData.validation = widgetData.validation;
                        }

                        // Update the hidden field value
                        const newValue = JSON.stringify(existingData);
                        input.value = newValue;
                        this.log(`Updated hidden field ${input.name} with new value: ${newValue}`);
                    } catch (error) {
                        this.log(`Error updating hidden field ${input.name}:`, error);
                    }
                });
            });
        } else {
            this.log('Wicket SDK not available for hidden field update');
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

        if (hasValidationData) {
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
    trackWidget(eventType, widgetData) {
        const widgetId = widgetData.resource?.id || eventType;

        this.activeWidgets.set(widgetId, {
            eventType,
            data: widgetData,
            lastUpdate: Date.now()
        });

        this.log(`Tracking widget: ${widgetId}, total widgets: ${this.activeWidgets.size}`);
    },

    /**
     * Update the overall validation state based on widget events
     */
    updateValidationState(eventType, validationInfo) {
        this.log(`Starting updateValidationState for event: ${eventType}`);
        this.log('Current validation state before update:', this.currentValidationState);
        this.log('Incoming validation info:', validationInfo);

        // Merge validation data from all widgets
        const oldIncompleteFields = [...this.currentValidationState.incompleteRequiredFields];
        const oldIncompleteResources = [...this.currentValidationState.incompleteRequiredResources];

        this.currentValidationState.incompleteRequiredFields = [
            ...new Set([...this.currentValidationState.incompleteRequiredFields, ...validationInfo.incompleteRequiredFields])
        ];

        this.currentValidationState.incompleteRequiredResources = [
            ...new Set([...this.currentValidationState.incompleteRequiredResources, ...validationInfo.incompleteRequiredResources])
        ];

        this.currentValidationState.hasRequiredFields = true;
        this.currentValidationState.widgetsReady = true;

        this.log('Validation state update details:');
        this.log(`- Incomplete fields changed from [${oldIncompleteFields}] to [${this.currentValidationState.incompleteRequiredFields}]`);
        this.log(`- Incomplete resources changed from [${oldIncompleteResources}] to [${this.currentValidationState.incompleteRequiredResources}]`);
        this.log(`- hasRequiredFields set to: ${this.currentValidationState.hasRequiredFields}`);
        this.log(`- widgetsReady set to: ${this.currentValidationState.widgetsReady}`);
        this.log('Full updated validation state:', this.currentValidationState);

        // If this was a save-success event, the widget might be complete
        if (eventType.includes('save-success')) {
            this.log('Detected save-success event, checking widget completion');
            this.validateWidgetCompletion(eventType, validationInfo);
        }
    },

    /**
     * Validate if a widget is complete after a save event
     */
    validateWidgetCompletion(eventType, validationInfo) {
        const isComplete = validationInfo.incompleteRequiredFields.length === 0 &&
                         validationInfo.incompleteRequiredResources.length === 0 &&
                         validationInfo.notFound.length === 0;

        this.log(`Widget ${eventType} completion check:`, { isComplete, validationInfo });

        if (isComplete) {
            // Remove this widget's fields from incomplete list
            this.clearWidgetIncompleteFields(eventType);
        }
    },

    /**
     * Clear incomplete fields for a specific widget
     */
    clearWidgetIncompleteFields(eventType) {
        // This is a simplified approach - in practice you might want to track
        // which fields belong to which widget more precisely
        this.log(`Clearing incomplete fields for widget: ${eventType}`);

        // Reset validation state to trigger fresh validation
        this.currentValidationState.incompleteRequiredFields = [];
        this.currentValidationState.incompleteRequiredResources = [];
        this.hideValidationErrors();
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
        this.log('Starting scanForWidgetElements');

        // Look for common MDP widget selectors
        const widgetSelectors = [
            '[id*="profile-"]',  // Based on the HTML example: id="profile-2153092545028218887"
            '.wicket__widgets',
            '.wicket-person-profile',
            '.wicket-org-profile',
            '.wicket-additional-info',
            '.wicket-preferences'
        ];

        this.log('Scanning DOM with selectors:', widgetSelectors);

        let foundWidgets = false;

        widgetSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                this.log(`Found ${elements.length} widget elements with selector: ${selector}`);
                elements.forEach((element, index) => {
                    this.log(`Widget element ${index + 1} details:`, {
                        tagName: element.tagName,
                        id: element.id,
                        className: element.className,
                        innerHTML: element.innerHTML.substring(0, 200) + '...'
                    });
                });
                foundWidgets = true;

                // Validate each widget using HTML parsing
                elements.forEach(element => {
                    this.validateWidgetFromHTML(element);
                });
            } else {
                this.log(`No widgets found with selector: ${selector}`);
            }
        });

        if (foundWidgets && !this.currentValidationState.widgetsReady) {
            this.currentValidationState.widgetsReady = true;
            this.log('Widget elements detected in DOM, validation system ready');
        } else if (!foundWidgets) {
            this.log('No widget elements found in DOM during scan');
        }
    },

    /**
     * Validate widget by parsing its HTML structure to find required fields
     */
    validateWidgetFromHTML(widgetElement) {
        this.log('Starting validateWidgetFromHTML for element:', widgetElement);

        // Find all required field indicators within this widget
        const requiredIndicators = widgetElement.querySelectorAll('.required-symbol');
        this.log(`Found ${requiredIndicators.length} required field indicators in widget`);

        const incompleteFields = [];

        requiredIndicators.forEach((indicator, index) => {
            this.log(`Processing required indicator ${index + 1}:`, indicator);

            // Find the associated field label
            const label = this.findFieldLabel(indicator);
            this.log(`Found label for indicator ${index + 1}: "${label}"`);

            if (label) {
                // Check if the field has a value
                const fieldValue = this.getFieldValue(indicator);
                this.log(`Field value for "${label}": "${fieldValue}" (empty: ${!fieldValue || fieldValue.trim() === ''})`);

                if (!fieldValue || fieldValue.trim() === '') {
                    this.log(`Field "${label}" is incomplete, adding to list`);
                    incompleteFields.push(label);
                } else {
                    this.log(`Field "${label}" has a value, considered complete`);
                }
            } else {
                this.log(`Could not find label for indicator ${index + 1}`);
            }
        });

        // Update validation state based on HTML parsing
        if (incompleteFields.length > 0) {
            this.log(`Updating validation state - found ${incompleteFields.length} incomplete fields: [${incompleteFields}]`);
            this.currentValidationState.incompleteRequiredFields = incompleteFields;
            this.currentValidationState.incompleteRequiredResources = [];
            this.currentValidationState.hasRequiredFields = true;
        } else {
            this.log('Updating validation state - all required fields appear complete');
            this.currentValidationState.incompleteRequiredFields = [];
            this.currentValidationState.incompleteRequiredResources = [];
        }

        this.log('HTML-based validation result:', {
            incompleteFields: incompleteFields,
            totalRequired: requiredIndicators.length,
            isComplete: incompleteFields.length === 0,
            widgetElementId: widgetElement.id,
            widgetElementClass: widgetElement.className
        });
    },

    /**
     * Find the field label associated with a required indicator
     */
    findFieldLabel(requiredIndicator) {
        this.log('Finding field label for indicator:', requiredIndicator);

        // Look for the label element that contains this required indicator
        let labelElement = requiredIndicator.closest('.label');
        this.log(`Looking for .label container: ${labelElement ? 'found' : 'not found'}`);

        if (labelElement) {
            // Get the text content, removing the required indicator and extra whitespace
            const labelText = labelElement.textContent.replace(/\*\s*$/, '').trim();
            this.log(`Found label from .label container: "${labelText}"`);
            return labelText;
        }

        // Fallback: look for a control-label that contains this indicator
        labelElement = requiredIndicator.closest('.control-label');
        this.log(`Looking for .control-label container: ${labelElement ? 'found' : 'not found'}`);

        if (labelElement) {
            const labelText = labelElement.textContent.replace(/\*\s*$/, '').trim();
            this.log(`Found label from .control-label container: "${labelText}"`);
            return labelText;
        }

        // Additional fallback: try to find parent with form-group
        const formGroup = requiredIndicator.closest('.form-group');
        if (formGroup) {
            const groupLabel = formGroup.querySelector('label');
            if (groupLabel) {
                const labelText = groupLabel.textContent.replace(/\*\s*$/, '').trim();
                this.log(`Found label from form-group: "${labelText}"`);
                return labelText;
            }
        }

        this.log('Could not find any label for the required indicator');
        return null;
    },

    /**
     * Get the actual value of a field based on its required indicator
     */
    getFieldValue(requiredIndicator) {
        this.log('Getting field value for indicator:', requiredIndicator);

        // Find the parent container that holds both the label and the value
        const container = requiredIndicator.closest('.InputStatic, .TypeableResource, .form-group');
        this.log(`Found container: ${container ? container.className : 'none'}`);

        if (!container) {
            this.log('No container found for field value extraction');
            return null;
        }

        // For InputStatic fields (read-only display fields)
        const valueElement = container.querySelector('.value, .TypeableResource__content-value, .form-control');
        this.log(`Found value element: ${valueElement ? (valueElement.className || valueElement.tagName) : 'none'}`);

        if (valueElement) {
            const value = valueElement.textContent.trim();
            this.log(`Extracted value from textContent: "${value}"`);
            return value;
        }

        // For editable fields that might have input elements
        const inputElement = container.querySelector('input, select, textarea');
        this.log(`Found input element: ${inputElement ? (inputElement.type || inputElement.tagName) : 'none'}`);

        if (inputElement) {
            const value = inputElement.value.trim();
            this.log(`Extracted value from input: "${value}"`);
            return value;
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
                    this.log(`Found additional value with selector ${selector}: "${value}"`);
                    return value;
                }
            }
        }

        this.log('Could not find any field value element');
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
                        Please complete the required fields
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
                self.log('Next button clicked, checking widget validation');
                self.log('Button element:', nextButton);
                self.log('Button text:', nextButton.textContent);
                self.log('Current validation state before check:', self.currentValidationState);

                // Perform a fresh HTML scan before validation
                self.log('Performing fresh HTML scan before validation check');
                self.updateValidationFromHTML();

                const shouldBlock = self.shouldBlockNavigation();
                self.log('Should block navigation result:', shouldBlock);

                if (shouldBlock) {
                    self.log('Widget validation failed, preventing navigation');
                    self.log('Validation state that caused block:', self.currentValidationState);
                    event.preventDefault();
                    event.stopPropagation();

                    const errorMessage = self.buildErrorMessage();
                    self.log('Built error message:', errorMessage);
                    self.showValidationErrors(errorMessage);

                    return false;
                } else {
                    self.log('Widget validation passed, allowing navigation');
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

                // First, update hidden fields with latest widget data before validation
                self.updateAllWidgetDataBeforeSubmit();

                // Check if we should block navigation
                const shouldBlock = self.shouldBlockNavigation();

                if (shouldBlock) {
                    self.log('Form submission validation failed, preventing submit');
                    event.preventDefault();
                    event.stopPropagation();

                    const errorMessage = self.buildErrorMessage();
                    self.showValidationErrors(errorMessage);

                    return false;
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

                // Find all hidden inputs that might contain widget data, but EXCLUDE data bind fields
                const hiddenInputs = document.querySelectorAll('input[type="hidden"][name*="wicket"]:not(.wicket-gf-hidden-data-bind-target), input[type="hidden"][class*="wicket"]:not(.wicket-gf-hidden-data-bind-target)');

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
    },

    /**
     * Check if navigation should be blocked based on widget validation
     */
    shouldBlockNavigation() {
        this.log('Starting shouldBlockNavigation check');
        this.log('Current validation state at start:', this.currentValidationState);

        // First, update validation state using HTML parsing
        this.log('Updating validation from HTML before block check');
        this.updateValidationFromHTML();
        this.log('Validation state after HTML update:', this.currentValidationState);

        // Don't block if no widgets are detected
        if (!this.currentValidationState.widgetsReady) {
            this.log('No widgets detected, allowing navigation');
            return false;
        }

        // Don't block if no required fields are detected
        if (!this.currentValidationState.hasRequiredFields) {
            this.log('No required fields detected in widgets, allowing navigation');
            return false;
        }

        // Block if there are incomplete required fields
        const incompleteFields = this.currentValidationState.incompleteRequiredFields;
        const incompleteResources = this.currentValidationState.incompleteRequiredResources;
        const hasIncompleteFields = incompleteFields.length > 0 || incompleteResources.length > 0;

        this.log('Navigation block check:', {
            widgetsReady: this.currentValidationState.widgetsReady,
            hasRequiredFields: this.currentValidationState.hasRequiredFields,
            incompleteFields: this.currentValidationState.incompleteRequiredFields,
            incompleteResources: this.currentValidationState.incompleteRequiredResources,
            hasIncompleteFields: hasIncompleteFields,
            incompleteFieldsCount: incompleteFields.length,
            incompleteResourcesCount: incompleteResources.length
        });

        // Special debug for "job level" field
        const jobLevelFields = incompleteFields.filter(field =>
            field.toLowerCase().includes('job') || field.toLowerCase().includes('level')
        );
        if (jobLevelFields.length > 0) {
            this.log('🚨 FOUND JOB LEVEL RELATED INCOMPLETE FIELDS:', jobLevelFields);
            this.log('🚨 This might be the source of the user reported issue!');
        }

        return hasIncompleteFields;
    },

    /**
     * Update validation state by parsing HTML of all widgets
     */
    updateValidationFromHTML() {
        this.log('Starting updateValidationFromHTML');

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

        this.log('Checking widgets with selectors:', widgetSelectors);

        widgetSelectors.forEach((selector, selectorIndex) => {
            const elements = document.querySelectorAll(selector);
            this.log(`Selector ${selectorIndex + 1} "${selector}" found ${elements.length} elements`);

            if (elements.length > 0) {
                widgetsFound = true;

                elements.forEach((element, elementIndex) => {
                    this.log(`Processing element ${elementIndex + 1} for selector "${selector}":`, {
                        id: element.id,
                        className: element.className
                    });

                    const incompleteFields = this.getIncompleteFieldsFromHTML(element);
                    this.log(`Element ${elementIndex + 1} incomplete fields:`, incompleteFields);
                    allIncompleteFields.push(...incompleteFields);
                });
            }
        });

        // Update the global validation state
        if (widgetsFound) {
            this.currentValidationState.widgetsReady = true;
            this.currentValidationState.hasRequiredFields = allIncompleteFields.length > 0 || this.hasAnyRequiredFields();
            this.currentValidationState.incompleteRequiredFields = allIncompleteFields;
            this.currentValidationState.incompleteRequiredResources = []; // Clear resources as we're using HTML parsing

            this.log('Validation state updated from HTML:', {
                widgetsFound: true,
                widgetsReady: this.currentValidationState.widgetsReady,
                hasRequiredFields: this.currentValidationState.hasRequiredFields,
                incompleteFields: this.currentValidationState.incompleteRequiredFields,
                totalIncompleteFields: allIncompleteFields.length
            });

            // Special check for job level fields
            const jobLevelFields = allIncompleteFields.filter(field =>
                field.toLowerCase().includes('job') || field.toLowerCase().includes('level')
            );
            if (jobLevelFields.length > 0) {
                this.log('🚨 JOB LEVEL FIELDS DETECTED IN HTML SCAN:', jobLevelFields);
            }
        } else {
            this.log('No widgets found during HTML validation scan');
        }
    },

    /**
     * Get incomplete fields from a specific widget element
     */
    getIncompleteFieldsFromHTML(widgetElement) {
        this.log(`Getting incomplete fields from element: ${widgetElement.id || widgetElement.className}`);

        const incompleteFields = [];

        // Find all required field indicators within this widget
        const requiredIndicators = widgetElement.querySelectorAll('.required-symbol');
        this.log(`Found ${requiredIndicators.length} required indicators in element`);

        requiredIndicators.forEach((indicator, index) => {
            this.log(`Processing indicator ${index + 1}:`, indicator);

            const label = this.findFieldLabel(indicator);
            this.log(`Label for indicator ${index + 1}: "${label}"`);

            if (label) {
                const fieldValue = this.getFieldValue(indicator);
                this.log(`Field value for "${label}": "${fieldValue}" (empty: ${!fieldValue || fieldValue.trim() === ''})`);

                if (!fieldValue || fieldValue.trim() === '') {
                    incompleteFields.push(label);
                    this.log(`Added "${label}" to incomplete fields list`);

                    // Special logging for job level related fields
                    if (label.toLowerCase().includes('job') || label.toLowerCase().includes('level')) {
                        this.log(`🚨 JOB LEVEL FIELD DETECTED AS INCOMPLETE: "${label}"`);
                        this.log(`🚨 Field value: "${fieldValue}"`);
                        this.log(`🚨 Field container HTML:`, indicator.closest('.InputStatic, .TypeableResource, .form-group')?.innerHTML.substring(0, 200));
                    }
                } else {
                    this.log(`Field "${label}" is complete, not adding to incomplete list`);
                }
            } else {
                this.log(`No label found for indicator ${index + 1}`);
            }
        });

        this.log(`Final incomplete fields for this element: [${incompleteFields}]`);
        return incompleteFields;
    },

    /**
     * Check if any widget has required fields at all
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
                const requiredIndicators = element.querySelectorAll('.required-symbol');
                if (requiredIndicators.length > 0) {
                    return true;
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
            return 'Please complete all required fields before continuing.';
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
    console.log('[WIDGET-DEBUG] DOM content loaded, initializing WicketMDPAutoValidation in 500ms');
    setTimeout(() => {
        console.log('[WIDGET-DEBUG] Starting initialization from DOMContentLoaded');
        WicketMDPAutoValidation.init();
    }, 500);
});

// Also initialize when Gravity Forms is ready
document.addEventListener('gform/post_render', function() {
    console.log('[WIDGET-DEBUG] Gravity Forms post_render event detected, initializing in 100ms');
    setTimeout(() => {
        console.log('[WIDGET-DEBUG] Starting initialization from gform/post_render');
        WicketMDPAutoValidation.init();
    }, 100);
});

// Export for external access
window.WicketMDPAutoValidation = WicketMDPAutoValidation;
