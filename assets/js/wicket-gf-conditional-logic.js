(function ($) {
    "use strict";

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
    });
})(jQuery);
