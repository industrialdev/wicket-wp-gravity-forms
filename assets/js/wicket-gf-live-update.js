/**
 * Wicket Gravity Forms Live Update
 *
 * Handles live updates for GFWicketDataHiddenField fields from Wicket Widgets on the same page.
 */
jQuery(document).ready(function ($) {
    'use strict';

    // --- Start Helper Functions ---
    function extractValueFromPayload(payload, targetSchemaKey, targetValueKey, logContextSource) {
        if (!payload || !payload.dataField || payload.dataField.key !== targetSchemaKey) {
            return undefined;
        }
        let extractedValue;
        if (targetValueKey && typeof payload.dataField.value === 'object' && payload.dataField.value !== null) {
            if (payload.dataField.value.hasOwnProperty(targetValueKey)) {
                extractedValue = payload.dataField.value[targetValueKey];
            } else {
                //console.warn(`Wicket GF Live Update: Value key '${targetValueKey}' not found in payload for schema '${targetSchemaKey}' from ${logContextSource}.`);
                return undefined;
            }
        } else if (!targetValueKey || targetValueKey === 'value') {
            extractedValue = payload.dataField.value;
        } else {
            //console.warn(`Wicket GF Live Update: Cannot extract value for schema '${targetSchemaKey}' with valueKey '${targetValueKey}' from non-object payload value from ${logContextSource}.`);
            return undefined;
        }
        return extractedValue;
    }

    function updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, logContextSource) {
        const currentValue = $hiddenField.val();
        const newValueStr = (extractedValue === null || typeof extractedValue === 'undefined') ? '' : String(extractedValue);
        if (currentValue !== newValueStr) {
            $hiddenField.val(newValueStr).trigger('change');
            //console.log(`Wicket GF Live Update: Field ${fieldId} (form ${formId}) updated to: '${newValueStr}' from ${logContextSource}.`);
        }
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

        // --- Global listener for 'additional_info' ---
        let hasAdditionalInfoTargetsOnPage = false;
        $pageHiddenFields.each(function () {
            if ($(this).data('live-update-widget-source') === 'additional_info') {
                hasAdditionalInfoTargetsOnPage = true;
                return false; // break
            }
        });

        if (hasAdditionalInfoTargetsOnPage && !additionalInfoListenerAttached) {
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
                    const targetWidgetSource = $hiddenField.data('live-update-widget-source');

                    if (targetWidgetSource === 'additional_info') {
                        const formId = $hiddenField.closest('form').attr('id');
                        const fieldId = $hiddenField.attr('id');
                        const targetSchemaKey = $hiddenField.data('live-update-schema-key');
                        const targetValueKey = $hiddenField.data('live-update-value-key');

                        if (targetSchemaKey === payload.dataField.key) {
                            //console.log(`Wicket GF Live Update: Global listener: Match for field ${fieldId}, schema ${targetSchemaKey}`);
                            const extractedValue = extractValueFromPayload(payload, targetSchemaKey, targetValueKey, 'additional_info (global event)');
                            if (typeof extractedValue !== 'undefined') {
                                updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, 'additional_info (global event)');
                            }
                        }
                    }
                });
            });
            additionalInfoListenerAttached = true;
            //console.log('Wicket GF Live Update: Global listener for additional_info ATTACHED.');
        } else if (hasAdditionalInfoTargetsOnPage && additionalInfoListenerAttached) {
            //console.log('Wicket GF Live Update: Global listener for additional_info already attached, active for current fields.');
        }

        // --- Instance-based listeners (for 'profile', etc.) ---
        const wicketInstances = (typeof Wicket.widgets !== 'undefined' && typeof Wicket.widgets.getInstances === 'function')
            ? Wicket.widgets.getInstances() : [];

        if (!wicketInstances.length && !hasAdditionalInfoTargetsOnPage) {
        //console.warn('Wicket GF Live Update: No Wicket widget instances found and no additional_info targets for global listener on this page/step.');
            return;
        }

        $pageHiddenFields.each(function () {
            const $hiddenField = $(this);
            const targetWidgetSource = $hiddenField.data('live-update-widget-source');

            if (targetWidgetSource === 'additional_info') {
                return; // continue, handled globally
            }

            if ($hiddenField.data('instance-listener-attached-' + targetWidgetSource)) {
                //console.log(`Wicket GF Live Update: Instance listener for ${targetWidgetSource} on field ${$hiddenField.attr('id')} already attached.`);
                return; // Already attached for this specific source type
            }

            const formId = $hiddenField.closest('form').attr('id');
            const fieldId = $hiddenField.attr('id');
            const targetSchemaKey = $hiddenField.data('live-update-schema-key');
            const targetValueKey = $hiddenField.data('live-update-value-key');

            if (!targetWidgetSource || !targetSchemaKey) {
                //console.warn(`Wicket GF Live Update: Missing data attributes for live update on field ID ${fieldId} in form ${formId} for source ${targetWidgetSource}.`);
                return;
            }

            let listenerAttachedForThisFieldSource = false;
            wicketInstances.forEach(function (widgetInstance) {
                if (listenerAttachedForThisFieldSource) return;

                if (!widgetInstance || typeof widgetInstance.listen !== 'function' || !widgetInstance.eventTypes || !widgetInstance.eventTypes.SAVE_SUCCESS) {
                    return;
                }

                let instanceType = '';
                if (widgetInstance.widgetType) { instanceType = widgetInstance.widgetType; }
                else if (widgetInstance.type) { instanceType = widgetInstance.type; }
                else if (widgetInstance.config && widgetInstance.config.widgetType) { instanceType = widgetInstance.config.widgetType; }
                else if (widgetInstance.config && widgetInstance.config.type) { instanceType = widgetInstance.config.type; }
                else if (widgetInstance.element && widgetInstance.element.dataset && widgetInstance.element.dataset.widgetType) { instanceType = widgetInstance.element.dataset.widgetType; }
                else if (widgetInstance.constructor && widgetInstance.constructor.name) {
                    const constructorName = widgetInstance.constructor.name.toLowerCase();
                    if (constructorName.includes('additionalinfo')) { instanceType = 'additional_info'; }
                    else if (constructorName.includes('profile')) { instanceType = 'profile'; }
                }

                if (instanceType === targetWidgetSource) {
                    //console.log(`Wicket GF Live Update: Matched target ${targetWidgetSource} with Wicket instance. Attaching SAVE_SUCCESS listener for field ${fieldId}.`);
                    widgetInstance.listen(widgetInstance.eventTypes.SAVE_SUCCESS, function (payload) {
                        //console.log(`Wicket GF Live Update: SAVE_SUCCESS from ${instanceType} (Field ${fieldId}):`, payload);
                        const extractedValue = extractValueFromPayload(payload, targetSchemaKey, targetValueKey, instanceType);
                        if (typeof extractedValue !== 'undefined') {
                            updateHiddenFieldValue($hiddenField, extractedValue, fieldId, formId, instanceType);
                        }
                    });
                    $hiddenField.data('instance-listener-attached-' + targetWidgetSource, true); // Mark as attached for this specific source
                    listenerAttachedForThisFieldSource = true;
                }
            });
        });
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
