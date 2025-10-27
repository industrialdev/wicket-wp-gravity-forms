// ORGSS auto-advance on selection
if (WicketGfPluginData.shouldAutoAdvance) {
    // Wait for Gravity Forms to be fully initialized
    document.addEventListener("gform/post_init", function (event) {
        // Wait for the next buttons to be present on screen
        let tries = 0;
        var existCondition = setInterval(function () {
            if (jQuery("[id^=gform_next_button_]").length) {
                clearInterval(existCondition);
                wicketGf_FindActiveNextButton();
            } else if (tries > 30) {
                clearInterval(existCondition);
            }
            tries++;
        }, 100); // check every 100ms

        // Also listen for Gravity Forms post render events to re-identify the next button
        jQuery(document).on(
            "gform_post_render",
            function (event, formId, currentPage) {
                wicketGf_FindActiveNextButton();
            }
        );
    });

    // Note: Auto-advance logic has been moved to nonce-handling.js for better nonce validation
}

// Separate function to execute the auto-advance logic
function wicketGf_ExecuteAutoAdvance() {
    // This function is now handled by nonce-handling.js
}

// On page load, find out active next button, make note of it, and hide it
function wicketGf_FindActiveNextButton() {
    let next_buttons = jQuery("[id^=gform_next_button_]");
    let active_next_button = null;

    // First try to find by ID pattern
    next_buttons.each(function (index) {
        if (jQuery(this).is(":visible")) {
            active_next_button = jQuery(this);
        }
    });

    // If not found, try to find by class
    if (!active_next_button || active_next_button.length === 0) {
        active_next_button = jQuery(".gform_next_button:visible").first();
    }

    // If still not found, try to find input with type submit that has gform_next_button class
    if (!active_next_button || active_next_button.length === 0) {
        active_next_button = jQuery(
            "input[type='submit'].gform_next_button:visible"
        ).first();
    }

    if (!active_next_button || active_next_button.length === 0) {
        return;
    }

    window.activeNextButtonId = active_next_button.attr("id");

    // If no ID, create one
    if (!window.activeNextButtonId) {
        window.activeNextButtonId =
            "wicket-gf-auto-advance-button-" + Date.now();
        active_next_button.attr("id", window.activeNextButtonId);
    }

    // Don't hide next button as this would currently apply to all pages -
    // Hide with a specific ID rule in Code Chest or stylesheet instead
    //active_next_button.css('opacity', 0);
}

document.addEventListener("gform/post_render", (event) => {
    // If class .wicket-theme-v2 DOES NOT exists
    if (!document.body.classList.contains("wicket-theme-v2")) {
        // Only if .gform_wrapper exists
        if (document.querySelector(".gform_wrapper")) {
            // Next button .gform_next_button, add class: .button--primary
            document
                .querySelectorAll(".gform_next_button")
                .forEach((button) => button.classList.add("button--primary"));

            // Previous button .gform_previous_button, add class: .button--secondary
            document
                .querySelectorAll(".gform_previous_button")
                .forEach((button) => button.classList.add("button--secondary"));

            // Submit button .gform_button.button[type="submit"], add class: .button--primary
            document
                .querySelectorAll('.gform_button.button[type="submit"]')
                .forEach((button) => button.classList.add("button--primary"));
        }
    }
});
