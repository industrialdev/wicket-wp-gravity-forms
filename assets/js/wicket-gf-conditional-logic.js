(function ($) {
    "use strict";

    // Debug logging configuration - completely self-contained
    var ORGSS_DEBUG_MODE = false; // Set to false to disable debugging

    function orgssDebugLog() {
        if (ORGSS_DEBUG_MODE) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[WGF-ORGSS]');
            console.log.apply(console, args);
        }
    }

    // Check if a field has conditional logic configured
    function hasConditionalLogicForField(formId, fieldId) {
        orgssDebugLog('Checking conditional logic for form', formId, 'field', fieldId);

        // Debug: Log available GF conditional logic data
        if (window.gf_form_conditional_logic) {
            orgssDebugLog('gf_form_conditional_logic available:', Object.keys(window.gf_form_conditional_logic));
        } else {
            orgssDebugLog('gf_form_conditional_logic not available');
        }

        // Check standard Gravity Forms conditional logic
        if (window.gf_form_conditional_logic && window.gf_form_conditional_logic[formId]) {
            var conditionalLogic = window.gf_form_conditional_logic[formId];
            orgssDebugLog('Form conditional logic structure:', Object.keys(conditionalLogic));

            // Check if this field is a target of conditional logic
            if (conditionalLogic.animation && conditionalLogic.animation[fieldId]) {
                orgssDebugLog('Found conditional logic in animation for field', fieldId);
                return true;
            }

            // Check if this field has dependency rules
            if (conditionalLogic.dependency && conditionalLogic.dependency[fieldId]) {
                orgssDebugLog('Found conditional logic in dependency for field', fieldId);
                return true;
            }

            // Check rules directly for this specific field
            if (conditionalLogic.rules && conditionalLogic.rules[fieldId]) {
                orgssDebugLog('Found conditional logic in rules for field', fieldId, 'rules count:', conditionalLogic.rules[fieldId].length);
                return conditionalLogic.rules[fieldId].length > 0;
            }

            // Try checking if field is mentioned anywhere in the conditional logic
            var logicString = JSON.stringify(conditionalLogic);
            if (logicString.includes('"' + fieldId + '"') || logicString.includes('"field_' + fieldId + '"')) {
                orgssDebugLog('Found field', fieldId, 'mentioned in conditional logic string');
                return true;
            }
        }

        // Check Advanced Conditional Logic
        if (window.GFACL && window.GFACL.logic && window.GFACL.logic[formId]) {
            var gfAclLogic = window.GFACL.logic[formId];
            orgssDebugLog('Advanced Conditional Logic available for form', formId);
            orgssDebugLog('GFACL structure:', Object.keys(gfAclLogic));
            if (gfAclLogic.rules) {
                orgssDebugLog('GFACL rules keys:', Object.keys(gfAclLogic.rules));
            }
            // Check if this field is a target in Advanced Conditional Logic
            if (gfAclLogic.rules && gfAclLogic.rules[fieldId]) {
                orgssDebugLog('Found Advanced Conditional Logic rules for field', fieldId);
                return gfAclLogic.rules[fieldId].length > 0;
            }
        }

        orgssDebugLog('No conditional logic found for field', fieldId);
        return false;
    }

    // Initialize ORGSS fields for conditional logic compatibility
    function initOrgssConditionalLogic() {
        orgssDebugLog('Starting initialization');

        // Find all org search/select field hidden inputs
        $("input.gf_org_search_select_input").each(function () {
            var $input = $(this);
            var inputId = $input.attr("id");
            var fieldId = inputId ? inputId.split("_").pop() : null;
            var formId = inputId ? inputId.split("_")[1] : null;

            orgssDebugLog('Processing field - inputId:', inputId, 'fieldId:', fieldId, 'formId:', formId);

            // Get the container field element (the .gfield that wraps this input)
            var $fieldContainer = $input.closest(".gfield");

            if ($fieldContainer.length && fieldId && formId) {
                // Check if this field has conditional logic configured
                var hasConditionalLogic = hasConditionalLogicForField(formId, fieldId);
                orgssDebugLog('Field', fieldId, 'has conditional logic:', hasConditionalLogic);

                // IMPORTANT: Hide all fields with conditional logic immediately, before evaluation
                if (hasConditionalLogic) {
                    orgssDebugLog('Hiding field with conditional logic before evaluation:', fieldId);
                    $fieldContainer.hide();
                }

                if (hasConditionalLogic) {
                    // Use Gravity Forms' built-in conditional logic evaluation
                    try {
                        if (typeof gf_check_field_rule === 'function') {
                            var shouldShow = gf_check_field_rule(formId, fieldId, true);
                            orgssDebugLog('gf_check_field_rule result for field', fieldId, ':', shouldShow);

                            // Additional safety check: For any field with conditional logic, check if the logic depends on other fields
                            // If GF says to show but no dependencies are satisfied, hide for safety
                            if (shouldShow !== "hide") {
                                var dependencyFields = [];
                                if (window.gf_form_conditional_logic && window.gf_form_conditional_logic[formId] && window.gf_form_conditional_logic[formId].logic) {
                                    var formLogic = window.gf_form_conditional_logic[formId].logic;
                                    orgssDebugLog('Form logic structure for field', fieldId, ':', formLogic[fieldId]);
                                    if (formLogic[fieldId]) {
                                        // Check if it's an array or object with different structure
                                        if (Array.isArray(formLogic[fieldId])) {
                                            // Find all dependency fields for this field
                                            formLogic[fieldId].forEach(function(rule) {
                                                if (rule.fieldId && dependencyFields.indexOf(rule.fieldId) === -1) {
                                                    dependencyFields.push(rule.fieldId);
                                                }
                                            });
                                        } else if (typeof formLogic[fieldId] === 'object') {
                                            // Handle GF object structure: {field: {rules}, nextButton: null, section: null}
                                            if (formLogic[fieldId].field && formLogic[fieldId].field.rules) {
                                                // Parse the rules array within the field object
                                                if (Array.isArray(formLogic[fieldId].field.rules)) {
                                                    formLogic[fieldId].field.rules.forEach(function(rule) {
                                                        if (rule && rule.fieldId && dependencyFields.indexOf(rule.fieldId) === -1) {
                                                            dependencyFields.push(rule.fieldId);
                                                        }
                                                    });
                                                }
                                            } else {
                                                orgssDebugLog('No rules found in formLogic for field', fieldId);
                                            }
                                        } else {
                                            orgssDebugLog('Unexpected formLogic structure for field', fieldId, ':', typeof formLogic[fieldId]);
                                        }
                                    }
                                }

                                if (dependencyFields.length > 0) {
                                    orgssDebugLog('Field', fieldId, 'depends on fields:', dependencyFields);
                                    // Check if any dependencies have values
                                    var hasDependencyValues = false;
                                    dependencyFields.forEach(function(depFieldId) {
                                        var $depField = $('input[name="input_' + depFieldId + '"]');
                                        if ($depField.length) {
                                            if ($depField.attr('type') === 'checkbox' || $depField.attr('type') === 'radio') {
                                                if ($depField.is(':checked')) {
                                                    hasDependencyValues = true;
                                                    orgssDebugLog('Dependency field', depFieldId, 'has checked value:', $depField.val());
                                                }
                                            } else if ($depField.val()) {
                                                hasDependencyValues = true;
                                                orgssDebugLog('Dependency field', depFieldId, 'has value:', $depField.val());
                                            }
                                        }
                                    });

                                    if (!hasDependencyValues) {
                                        orgssDebugLog('No dependency values found for field', fieldId, '- hiding for safety');
                                        $fieldContainer.hide();
                                    } else {
                                        orgssDebugLog('Dependency values found for field', fieldId, '- showing field');
                                        $fieldContainer.show();
                                    }
                                } else {
                                    // No dependencies found, respect GF decision
                                    orgssDebugLog('No dependencies found for field', fieldId, '- respecting GF decision to show');
                                    $fieldContainer.show();
                                }
                            } else {
                                orgssDebugLog('Hiding field', fieldId);
                                $fieldContainer.hide();
                            }
                        } else if (window.gform && window.gform.doAction) {
                            // For newer GF versions, use the action system
                            orgssDebugLog('Using gform.doAction for field', fieldId);
                            window.gform.doAction('gform_conditional_logic', formId, fieldId, $fieldContainer);
                        } else {
                            // Fallback: check the current form state
                            var $form = $("#gform_" + formId);
                            if ($form.length && typeof gf_apply_rules === 'function') {
                                orgssDebugLog('Using gf_apply_rules fallback for field', fieldId);
                                gf_apply_rules(formId, [$fieldContainer]);
                            } else {
                                // Final fallback: show the field
                                orgssDebugLog('Final fallback - showing field', fieldId);
                                $fieldContainer.show();
                            }
                        }
                    } catch (err) {
                        orgssDebugLog('Conditional logic evaluation error for field', fieldId, ':', err);
                        // Fallback: show the field if we can't determine conditional logic
                        $fieldContainer.show();
                    }
                } else {
                    // If no conditional logic is configured, show the field
                    orgssDebugLog('No conditional logic for field', fieldId, '- showing by default');
                    $fieldContainer.show();
                }
            } else {
                orgssDebugLog('Invalid field data - inputId:', inputId, 'fieldId:', fieldId, 'formId:', formId);
            }
        });

        orgssDebugLog('Initialization complete');
    }

    // Handle ORGSS field visibility when GF conditional logic runs
    function handleOrgssVisibility(formId, affectedFields) {
        if (!formId || !affectedFields || !Array.isArray(affectedFields)) {
            return;
        }

        // Check if any ORGSS fields are affected
        var orgssFieldsAffected = false;
        for (var i = 0; i < affectedFields.length; i++) {
            var fieldId = affectedFields[i];
            var $orgssInput = $("input.gf_org_search_select_input").filter(function() {
                var inputId = $(this).attr("id");
                var inputFieldId = inputId ? inputId.split("_").pop() : null;
                return inputFieldId == fieldId;
            });

            if ($orgssInput.length) {
                orgssFieldsAffected = true;
                break;
            }
        }

        if (orgssFieldsAffected) {
            // Re-initialize all ORGSS fields visibility
            setTimeout(function() {
                initOrgssConditionalLogic();
            }, 50);
        }
    }

    // Listen for the org selection event dispatched by the component
    window.addEventListener("orgss-selection-made", function (event) {
        if (!event || !event.detail || !event.detail.uuid) {
            return;
        }

        // Find all org search/select field hidden inputs
        $("input.gf_org_search_select_input").each(function () {
            var $input = $(this);
            var inputId = $input.attr("id");

            // Set the value
            $input.val(event.detail.uuid);

            // Trigger change for Gravity Forms conditional logic
            $input.trigger("change");

            // Get the form ID
            var formId = inputId ? inputId.split("_")[1] : null;

            // Fire the Gravity Forms conditional logic refresh - safely
            if (formId && typeof formId === "string") {
                try {
                    // First attempt to get the form
                    var $form = $("#gform_" + formId);

                    if (
                        window.gform &&
                        typeof window.gform.doAction === "function"
                    ) {
                        // For newer Gravity Forms versions
                        var fieldId = inputId ? inputId.split("_").pop() : null;
                        if (fieldId) {
                            // The input ID format should be input_{formId}_{fieldId}
                            window.gform.doAction(
                                "gform_input_change",
                                $input,
                                formId,
                                fieldId
                            );
                        } else {
                            // If we can't extract the field ID, just trigger general form change
                            $(document).trigger(
                                "gform_post_conditional_logic",
                                [formId, null, null]
                            );
                        }
                    } else if (
                        typeof gf_apply_rules === "function" &&
                        $form.length
                    ) {
                        // For older Gravity Forms versions
                        var $formFields = $form.find(".gfield");
                        if ($formFields.length) {
                            gf_apply_rules(formId, $formFields);
                        }
                    }
                } catch (err) {}
            }
        });
    });

    // Set up a MutationObserver to catch initial field population
    $(document).ready(function () {
        // Initialize ORGSS fields for conditional logic
        initOrgssConditionalLogic();

        // Find all hidden inputs for org search fields
        $("input.gf_org_search_select_input").each(function () {
            var $input = $(this);
            var $component = $input.closest("div.component-org-search-select");

            if ($component.length) {
                // Check if there's already a value in the component's hidden field
                var $componentInput = $component.find(
                    'input[type="hidden"]:not(.gf_org_search_select_input)'
                );
                if ($componentInput.length && $componentInput.val()) {
                    $input.val($componentInput.val());
                    $input.trigger("change");

                    // Also trigger form conditional logic if we have a value
                    var inputId = $input.attr("id");
                    var formId = inputId ? inputId.split("_")[1] : null;

                    if (formId && typeof formId === "string") {
                        // Trigger conditional logic update (safely)
                        try {
                            $(document).trigger(
                                "gform_post_conditional_logic",
                                [formId, null, null]
                            );
                        } catch (err) {}
                    }
                }
            }
        });

        // Set up change listeners for all form inputs that could affect ORGSS fields
        $(document).on('change', 'input[type="radio"], input[type="checkbox"], select, input[type="text"]', function() {
            var changedElement = $(this);
            var changedElementId = changedElement.attr('id') || 'unknown';
            var changedElementName = changedElement.attr('name') || 'unknown';
            var changedElementType = changedElement.attr('type') || 'unknown';
            var changedElementValue = changedElement.val() || (changedElement.is(':checked') ? 'checked' : 'unchecked');

            orgssDebugLog('Change detected on element:', changedElementId, 'name:', changedElementName, 'type:', changedElementType, 'value:', changedElementValue);

            // Get the form ID from the gform_wrapper or hidden input
            var $gformWrapper = $(this).closest('.gform_wrapper');
            var formId = $gformWrapper.find('input[name="form_id"]').val() ||
                         ($gformWrapper.attr('id') || '').replace('gform_', '').replace('wrapper_', '') ||
                         $gformWrapper.find('form').attr('id')?.replace('gform_', '');

            orgssDebugLog('Detected form ID:', formId);

            if (formId) {
                // Always check all ORGSS fields and their conditional logic status
                var allOrgssFieldIds = [];
                var orgssFieldIds = [];

                // Collect all ORGSS field IDs first
                $("input.gf_org_search_select_input").each(function() {
                    var inputId = $(this).attr("id");
                    var fieldId = inputId ? inputId.split("_").pop() : null;
                    if (fieldId) {
                        allOrgssFieldIds.push(fieldId);
                        if (hasConditionalLogicForField(formId, fieldId)) {
                            orgssFieldIds.push(fieldId);
                            orgssDebugLog('Found ORGSS field with conditional logic:', fieldId);
                        }
                    }
                });

                orgssDebugLog('All ORGSS fields:', allOrgssFieldIds);
                orgssDebugLog('ORGSS fields with conditional logic:', orgssFieldIds);

                // Always trigger re-evaluation for all ORGSS fields for testing
                orgssDebugLog('Triggering conditional logic re-evaluation for all ORGSS fields:', allOrgssFieldIds);
                setTimeout(function() {
                    try {
                        // Re-initialize ORGSS fields visibility
                        initOrgssConditionalLogic();

                        // Trigger Gravity Forms conditional logic
                        if (window.gform && typeof window.gform.doAction === 'function') {
                            orgssDebugLog('Using GF action system');
                            // Use GF's action system if available
                            $(document).trigger('gform_post_conditional_logic', [formId, allOrgssFieldIds, null]);
                        } else if (typeof gf_apply_rules === 'function') {
                            var $form = $("#gform_" + formId);
                            if ($form.length) {
                                orgssDebugLog('Applying GF rules to entire form');
                                try {
                                    // Apply rules to the entire form to ensure all dependencies are evaluated
                                    gf_apply_rules(formId, $form.find('.gfield'));
                                } catch (err) {
                                    orgssDebugLog('GF apply_rules error, falling back to field-specific evaluation:', err);
                                    // Fallback: evaluate each ORGSS field individually
                                    allOrgssFieldIds.forEach(function(fieldId) {
                                        try {
                                            var shouldShow = gf_check_field_rule(formId, fieldId, true);
                                            var $fieldContainer = $("#input_" + formId + "_" + fieldId).closest('.gfield');
                                            if ($fieldContainer.length) {
                                                if (shouldShow === "hide") {
                                                    $fieldContainer.hide();
                                                } else {
                                                    $fieldContainer.show();
                                                }
                                            }
                                        } catch (fieldErr) {
                                            orgssDebugLog('Error evaluating field', fieldId, ':', fieldErr);
                                        }
                                    });
                                }
                            }
                        } else {
                            orgssDebugLog('No GF conditional logic functions available');
                        }
                    } catch (err) {
                        orgssDebugLog('Error in conditional logic re-evaluation:', err);
                    }
                }, 50); // Small delay to ensure the field value is updated
            } else {
                orgssDebugLog('Could not determine form ID for changed element');
            }
        });

        // Hook into GF conditional logic events
        $(document).on('gform_post_conditional_logic', function(event, formId, fields, isInit) {
            handleOrgssVisibility(formId, fields);
        });

        // Also hook into older GF conditional logic events
        $(document).on('gform_post_conditional_logic_field', function(event, formId, fieldId) {
            handleOrgssVisibility(formId, [fieldId]);
        });

        // Re-initialize ORGSS visibility when Alpine.js components are ready
        if (window.Alpine && window.Alpine.on) {
            window.Alpine.on('components-initialized', function() {
                setTimeout(function() {
                    orgssDebugLog('Alpine.js components initialized, re-evaluating ORGSS conditional logic');
                    initOrgssConditionalLogic();
                }, 100);
            });
        }

        // Additional initialization after a delay to catch any missed components
        setTimeout(function() {
            orgssDebugLog('Delayed initialization - re-evaluating ORGSS conditional logic');
            initOrgssConditionalLogic();
        }, 500);
    });
})(jQuery);
