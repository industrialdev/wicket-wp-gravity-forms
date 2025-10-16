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
            console.log(`Wicket MDP Auto Validation: ${message}`, data);
        } else {
            console.log(`Wicket MDP Auto Validation: ${message}`);
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

        const widgetData = event.detail;
        if (!widgetData) {
            this.log('No widget data in event');
            return;
        }

        // Extract validation information from the event payload
        const validationInfo = this.extractValidationInfo(widgetData);

        if (validationInfo) {
            this.updateValidationState(eventType, validationInfo);
        }

        // Store widget reference for tracking
        this.trackWidget(eventType, widgetData);
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
        // Merge validation data from all widgets
        this.currentValidationState.incompleteRequiredFields = [
            ...new Set([...this.currentValidationState.incompleteRequiredFields, ...validationInfo.incompleteRequiredFields])
        ];

        this.currentValidationState.incompleteRequiredResources = [
            ...new Set([...this.currentValidationState.incompleteRequiredResources, ...validationInfo.incompleteRequiredResources])
        ];

        this.currentValidationState.hasRequiredFields = true;
        this.currentValidationState.widgetsReady = true;

        this.log('Updated validation state:', this.currentValidationState);

        // If this was a save-success event, the widget might be complete
        if (eventType.includes('save-success')) {
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
     * Scan DOM for MDP widget elements
     */
    scanForWidgetElements() {
        // Look for common MDP widget selectors
        const widgetSelectors = [
            '[id*="wicket-widget"]',
            '[class*="wicket-widget"]',
            '[data-wicket-widget]',
            '.wicket-person-profile',
            '.wicket-org-profile',
            '.wicket-additional-info',
            '.wicket-preferences'
        ];

        let foundWidgets = false;

        widgetSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                this.log(`Found ${elements.length} widget elements with selector: ${selector}`);
                foundWidgets = true;
            }
        });

        if (foundWidgets && !this.currentValidationState.widgetsReady) {
            this.currentValidationState.widgetsReady = true;
            this.log('Widget elements detected in DOM, validation system ready');
        }
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

        setTimeout(() => {
            errorContainer.style.display = 'none';
        }, 10000);
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

                if (self.shouldBlockNavigation()) {
                    self.log('Widget validation failed, preventing navigation');
                    event.preventDefault();
                    event.stopPropagation();

                    const errorMessage = self.buildErrorMessage();
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

                if (self.shouldBlockNavigation()) {
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
     * Check if navigation should be blocked based on widget validation
     */
    shouldBlockNavigation() {
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
        const hasIncompleteFields = this.currentValidationState.incompleteRequiredFields.length > 0 ||
                                   this.currentValidationState.incompleteRequiredResources.length > 0;

        this.log('Navigation block check:', {
            widgetsReady: this.currentValidationState.widgetsReady,
            hasRequiredFields: this.currentValidationState.hasRequiredFields,
            incompleteFields: this.currentValidationState.incompleteRequiredFields,
            incompleteResources: this.currentValidationState.incompleteRequiredResources,
            shouldBlock: hasIncompleteFields
        });

        return hasIncompleteFields;
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