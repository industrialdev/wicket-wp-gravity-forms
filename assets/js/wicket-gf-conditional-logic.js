(function ($) {
    "use strict";

    // Initialize ORGSS fields for conditional logic compatibility
    function initOrgssConditionalLogic() {
        // Find all org search/select field hidden inputs
        $("input.gf_org_search_select_input").each(function () {
            var $input = $(this);
            var inputId = $input.attr("id");
            var fieldId = inputId ? inputId.split("_").pop() : null;
            var formId = inputId ? inputId.split("_")[1] : null;

            // Get the container field element (the .gfield that wraps this input)
            var $fieldContainer = $input.closest(".gfield");

            if ($fieldContainer.length && fieldId && formId) {
                // Check if this field should be hidden based on current conditional logic
                if (window.gf_form_conditional_logic && window.gf_form_conditional_logic[formId]) {
                    // Use try-catch in case gf_check_field_rule is not available
                    try {
                        var shouldShow = gf_check_field_rule(formId, fieldId, true);

                        // Special handling for ORGSS fields based on field 21 selection
                        var field21Value = $('input[name="input_21"]:checked').val();

                        // Force hide ORGSS fields if no radio option is selected in field 21
                        if (!field21Value || field21Value === '') {
                            $fieldContainer.hide();
                        } else if (shouldShow === "hide") {
                            $fieldContainer.hide();
                        } else {
                            $fieldContainer.show();
                        }
                    } catch (err) {
                        // Fallback: hide ORGSS fields if no selection in field 21
                        var field21Value = $('input[name="input_21"]:checked').val();
                        if (!field21Value || field21Value === '') {
                            $fieldContainer.hide();
                        } else {
                            $fieldContainer.show();
                        }
                    }
                } else {
                    // If no standard GF conditional logic is configured, check for Advanced Conditional Logic
                    if (window.GFACL && window.GFACL.logic && window.GFACL.logic[formId]) {
                        // Advanced Conditional Logic is active, we need to trigger rule evaluation
                        try {
                            // Create a dummy rule to trigger evaluation
                            var dummyRule = {
                                fieldId: "__adv_cond_logic",
                                operator: "is",
                                value: "field_" + fieldId
                            };

                            // Apply the Advanced Conditional Logic rule pre-evaluation
                            if (window.gform && window.gform.applyFilters) {
                                var processedRule = window.gform.applyFilters('gform_rule_pre_evaluation', dummyRule, formId, {});

                                if (processedRule && processedRule.value === "__return_true") {
                                    $fieldContainer.show();
                                } else if (processedRule && processedRule.value === "__return_false") {
                                    $fieldContainer.hide();
                                } else {
                                    // Fallback: show the field if we can't determine the rule
                                    $fieldContainer.show();
                                }
                            } else {
                                $fieldContainer.show();
                            }
                        } catch (err) {
                            $fieldContainer.show();
                        }
                    } else {
                        // If no conditional logic is configured, show the field
                        $fieldContainer.show();
                    }
                }
            }
        });
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
        setTimeout(function() {
            initOrgssConditionalLogic();
        }, 200); // Delay to ensure all scripts and components are loaded

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

        // Set up radio button change listeners for field 21 (Renewal Type)
        $(document).on('change', 'input[name="input_21"]', function() {
            var formId = $(this).closest('form').find('input[name="form_id"]').val() ||
                         $(this).closest('.gform_wrapper').attr('id').replace('gform_', '');

            if (formId) {
                // Trigger conditional logic re-evaluation for the entire form
                setTimeout(function() {
                    try {
                        // Re-initialize ORGSS fields visibility
                        initOrgssConditionalLogic();

                        // Handle Advanced Conditional Logic first
                        if (window.GFACL && window.GFACL.logic && window.GFACL.logic[formId]) {
                            // Advanced Conditional Logic is active, trigger rule re-evaluation

                            // Get all ORGSS field IDs to re-evaluate
                            var orgssFieldIds = [];
                            $("input.gf_org_search_select_input").each(function() {
                                var inputId = $(this).attr("id");
                                var fieldId = inputId ? inputId.split("_").pop() : null;
                                if (fieldId) {
                                    orgssFieldIds.push(fieldId);
                                }
                            });

                            // Trigger conditional logic for ORGSS fields
                            if (orgssFieldIds.length > 0 && typeof gf_apply_rules === 'function') {
                                gf_apply_rules(formId, orgssFieldIds, false);
                            }
                        } else {
                            // Standard GF conditional logic
                            if (window.gform && typeof window.gform.doAction === 'function') {
                                // Use GF's action system if available
                                $(document).trigger('gform_post_conditional_logic', [formId, null, null]);
                            } else if (typeof gf_apply_rules === 'function') {
                                // Fallback to older GF method
                                var $form = $("#gform_" + formId);
                                if ($form.length) {
                                    var $formFields = $form.find(".gfield");
                                    if ($formFields.length) {
                                        gf_apply_rules(formId, $formFields);
                                    }
                                }
                            }
                        }
                    } catch (err) {
                        // Error handling - fail silently
                    }
                }, 100); // Small delay to ensure the radio value is updated
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
                setTimeout(initOrgssConditionalLogic, 100);
            });
        }

        // Additional initialization after a delay to catch any missed components
        setTimeout(function() {
            initOrgssConditionalLogic();
        }, 500);

        // Even more aggressive fallback - run after everything is loaded
        setTimeout(function() {
            initOrgssConditionalLogic();
        }, 1000);
    });
})(jQuery);
