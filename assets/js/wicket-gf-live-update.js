/**
 * Wicket Gravity Forms Live Update
 *
 * Handles live updates for GFWicketDataHiddenField fields from Wicket Widgets on the same page.
 */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof Wicket === 'undefined' || typeof Wicket.ready === 'undefined') {
        // console.warn('Wicket SDK not ready, live updates for GF Wicket fields will not initialize.');
        return;
    }

    Wicket.ready(function () {
        // console.log('Wicket GF Live Update: Wicket ready, initializing listeners.');

        const $hiddenFields = $('.wicket-gf-live-update-target');
        if (!$hiddenFields.length) {
            // console.log('Wicket GF Live Update: No live update target fields found on this page.');
            return;
        }

        const wicketInstances = (typeof Wicket.widgets !== 'undefined' && typeof Wicket.widgets.getInstances === 'function')
                                ? Wicket.widgets.getInstances()
                                : [];

        if (!wicketInstances.length) {
            // console.warn('Wicket GF Live Update: No Wicket widget instances found on this page.');
            return;
        }

        $hiddenFields.each(function () {
            const $hiddenField = $(this);
            const formId = $hiddenField.closest('form').attr('id'); // Get form ID for context
            const fieldId = $hiddenField.attr('id');
            const targetWidgetSource = $hiddenField.data('live-update-widget-source'); // e.g., 'additional_info', 'profile'
            const targetSchemaKey = $hiddenField.data('live-update-schema-key');
            const targetValueKey = $hiddenField.data('live-update-value-key');

            if (!targetWidgetSource || !targetSchemaKey) {
                // console.warn(`Wicket GF Live Update: Missing data attributes for live update on field ID ${fieldId} in form ${formId}.`);
                return; // Skip this hidden field
            }

            // console.log(`Wicket GF Live Update: Field ${fieldId} (form ${formId}) listening for ${targetWidgetSource} -> ${targetSchemaKey} -> ${targetValueKey || '[direct value]'}`);

            wicketInstances.forEach(function (widgetInstance) {
                if (!widgetInstance || typeof widgetInstance.listen !== 'function' || !widgetInstance.eventTypes || !widgetInstance.eventTypes.SAVE_SUCCESS) {
                    return; // Skip if instance doesn't support listening or SAVE_SUCCESS
                }

                // --- Attempt to determine Wicket widget type ---
                // This is the most speculative part and might need adjustment based on Wicket SDK specifics.
                // Common patterns: instance.widgetType, instance.type, instance.config.widgetType, instance.config.type
                // Or, it might be part of the element's class or a data attribute on instance.element.
                let instanceType = '';
                if (widgetInstance.widgetType) { // Direct property
                    instanceType = widgetInstance.widgetType;
                } else if (widgetInstance.type) { // Another common direct property
                    instanceType = widgetInstance.type;
                } else if (widgetInstance.config && widgetInstance.config.widgetType) { // In config object
                    instanceType = widgetInstance.config.widgetType;
                } else if (widgetInstance.config && widgetInstance.config.type) { // In config object
                    instanceType = widgetInstance.config.type;
                } else if (widgetInstance.element && widgetInstance.element.dataset && widgetInstance.element.dataset.widgetType) { // From data attribute
                    instanceType = widgetInstance.element.dataset.widgetType;
                } else if (widgetInstance.constructor && widgetInstance.constructor.name) {
                    // Attempt to infer from constructor name (e.g., 'WicketEditAdditionalInfo')
                    // This is fragile and highly dependent on Wicket's internal class naming.
                    const constructorName = widgetInstance.constructor.name.toLowerCase();
                    if (constructorName.includes('additionalinfo')) {
                        instanceType = 'additional_info';
                    } else if (constructorName.includes('profile')) {
                        instanceType = 'profile';
                    }
                    // Add more inferences as needed
                }
                // --- End attempt to determine Wicket widget type ---

                if (instanceType === targetWidgetSource) {
                    // console.log(`Wicket GF Live Update: Matched target ${targetWidgetSource} with Wicket instance. Attaching SAVE_SUCCESS listener.`);
                    widgetInstance.listen(widgetInstance.eventTypes.SAVE_SUCCESS, function (payload) {
                        // console.log(`Wicket GF Live Update: SAVE_SUCCESS from ${instanceType} (Field ${fieldId}):`, payload);

                        if (payload.dataField && payload.dataField.key === targetSchemaKey) {
                            let extractedValue;
                            if (targetValueKey && typeof payload.dataField.value === 'object' && payload.dataField.value !== null) {
                                if (payload.dataField.value.hasOwnProperty(targetValueKey)) {
                                    extractedValue = payload.dataField.value[targetValueKey];
                                } else {
                                    // console.warn(`Wicket GF Live Update: Value key '${targetValueKey}' not found in payload for schema '${targetSchemaKey}' from ${instanceType} widget.`);
                                    return; // Value key specified but not found in object
                                }
                            } else if (!targetValueKey || targetValueKey === 'value') {
                                // If no targetValueKey, or it's literally 'value', use dataField.value directly.
                                // This handles cases where dataField.value is a scalar or the entire object is desired.
                                extractedValue = payload.dataField.value;
                            } else {
                                // console.warn(`Wicket GF Live Update: Cannot extract value for schema '${targetSchemaKey}' with valueKey '${targetValueKey}' from non-object payload value from ${instanceType} widget.`);
                                return; // Cannot extract with targetValueKey if payload.dataField.value is not an object.
                            }

                            const currentValue = $hiddenField.val();
                            const newValueStr = (extractedValue === null || typeof extractedValue === 'undefined') ? '' : String(extractedValue);

                            if (currentValue !== newValueStr) {
                                $hiddenField.val(newValueStr).trigger('change'); // Update and trigger GF refresh
                                // console.log(`Wicket GF Live Update: Field ${fieldId} (form ${formId}) updated to: '${newValueStr}' from ${instanceType} widget.`);
                            }
                        }
                    });
                }
            });
        });
    });
  });
