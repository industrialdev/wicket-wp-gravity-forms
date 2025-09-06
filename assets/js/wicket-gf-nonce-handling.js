/**
 * Handle nonce timeout issues in Gravity Forms
 * Provides automatic form refresh and user notifications
 */

jQuery(document).ready(function($) {
    // Monitor for nonce timeout errors
    $(document).on('gform_post_render', function(event, form_id, current_page) {
        // Check if form has validation errors that might indicate nonce timeout
        var $form = $('#gform_' + form_id);
        var $errorMessages = $form.find('.validation_error, .gfield_error');

        $errorMessages.each(function() {
            var errorText = $(this).text().toLowerCase();
            if (errorText.includes('expired') || errorText.includes('session')) {
                // Add refresh button for nonce timeout
                if (!$form.find('.nonce-timeout-refresh').length) {
                    var refreshButton = $('<button type="button" class="button nonce-timeout-refresh" style="margin: 10px 0;">Refresh Form</button>');
                    refreshButton.on('click', function() {
                        location.reload();
                    });
                    $(this).after(refreshButton);
                }
            }
        });
    });

    // Auto-refresh form if user has been inactive for extended period
    var formInactivityTimer;
    var INACTIVITY_WARNING_TIME = 20 * 60 * 1000; // 20 minutes

    function resetInactivityTimer() {
        clearTimeout(formInactivityTimer);
        formInactivityTimer = setTimeout(function() {
            if (confirm('Your session will expire soon. Would you like to refresh the form to continue?')) {
                location.reload();
            }
        }, INACTIVITY_WARNING_TIME);
    }

    // Reset timer on user interaction
    $(document).on('click keypress change', '.gform_wrapper input, .gform_wrapper select, .gform_wrapper textarea', function() {
        resetInactivityTimer();
    });

    // Start timer
    resetInactivityTimer();

    // Handle org search field specifically
    $(document).on('change', '[id^="input_"][id$="_org_search_select"]', function() {
        var $field = $(this);
        var fieldId = $field.attr('id');

        // Store selected value to prevent loss during refresh
        if ($field.val()) {
            sessionStorage.setItem('wicket_gf_org_selection_' + fieldId, $field.val());
            sessionStorage.setItem('wicket_gf_org_selection_text_' + fieldId, $field.find('option:selected').text());
        }
    });

    // Restore org selection after page refresh
    $('[id^="input_"][id$="_org_search_select"]').each(function() {
        var $field = $(this);
        var fieldId = $field.attr('id');
        var savedValue = sessionStorage.getItem('wicket_gf_org_selection_' + fieldId);

        if (savedValue && !$field.val()) {
            $field.val(savedValue);
            // Trigger change to update related fields
            $field.trigger('change');
        }
    });

    // Enhanced auto-advance with modal confirmation support
    if (typeof WicketGfPluginData !== 'undefined' && WicketGfPluginData.shouldAutoAdvance) {
        // Override the auto-advance logic to handle confirmation modals
        $(window).off('orgss-selection-made').on('orgss-selection-made', function(event) {
            if (!event.detail || !event.detail.uuid) {
                return;
            }

            setTimeout(function() {
                // Check if the form is visible
                if (!$('.gform_wrapper:visible').length) {
                    return;
                }

                // Check if there's a confirmation modal present (specifically the active membership alert)
                // Be more specific about modal detection to avoid false positives
                var $activeMembershipModal = $('.component-org-search-select__active-membership-alert');
                var $confirmationPopup = $('.component-org-search-select__confirmation-popup');

                var hasModal = false;

                // Check active membership alert modal
                if ($activeMembershipModal.length > 0) {
                    var isActiveMembershipVisible = $activeMembershipModal.is(':visible') &&
                                                  $activeMembershipModal.css('opacity') !== '0' &&
                                                  $activeMembershipModal.css('pointer-events') !== 'none';
                    if (isActiveMembershipVisible) {
                        console.log('Active membership modal detected');
                        hasModal = true;
                    }
                }

                // Check confirmation popup
                if (!hasModal && $confirmationPopup.length > 0) {
                    var isConfirmationVisible = $confirmationPopup.is(':visible') &&
                                              $confirmationPopup.css('opacity') !== '0' &&
                                              $confirmationPopup.css('pointer-events') !== 'none';
                    if (isConfirmationVisible) {
                        console.log('Confirmation popup detected');
                        hasModal = true;
                    }
                }

                if (hasModal) {
                    // Don't auto-advance if there's a modal - wait for user confirmation
                    console.log('Confirmation modal detected. Waiting for user confirmation before auto-advance.');

                    // Set up a listener for when the modal is closed/confirmed
                    var modalCheckInterval = setInterval(function() {
                        var modalStillVisible = false;

                        // Re-check both modals with the same logic
                        if ($activeMembershipModal.length > 0) {
                            modalStillVisible = $activeMembershipModal.is(':visible') &&
                                              $activeMembershipModal.css('opacity') !== '0' &&
                                              $activeMembershipModal.css('pointer-events') !== 'none';
                        }

                        if (!modalStillVisible && $confirmationPopup.length > 0) {
                            modalStillVisible = $confirmationPopup.is(':visible') &&
                                              $confirmationPopup.css('opacity') !== '0' &&
                                              $confirmationPopup.css('pointer-events') !== 'none';
                        }

                        if (!modalStillVisible) {
                            clearInterval(modalCheckInterval);

                            // Check if the organization was actually selected (UUID should be in hidden field)
                            var orgSelected = false;
                            $('[name^="input_"][name*="_org_search"], [name^="input_"][value!=""]').each(function() {
                                if ($(this).val() && $(this).val().length > 0) {
                                    orgSelected = true;
                                    return false; // break loop
                                }
                            });                            if (orgSelected) {
                                console.log('Modal confirmed and organization selected. Proceeding with auto-advance.');
                                executeAutoAdvance();
                            } else {
                                console.log('Modal closed but no organization selected. Skipping auto-advance.');
                            }
                        }
                    }, 500); // Check every 500ms

                    // Timeout after 30 seconds to prevent infinite waiting
                    setTimeout(function() {
                        clearInterval(modalCheckInterval);
                        console.log('Modal confirmation timeout. Skipping auto-advance.');
                    }, 30000);
                } else {
                    // No modal, proceed with immediate auto-advance
                    executeAutoAdvance();
                }
            }, 1000); // Initial delay to let UI update
        });
    }

    function executeAutoAdvance() {
        var nextButton = null;

        // Try multiple methods to find the next button
        if (window.activeNextButtonId) {
            nextButton = $('#' + window.activeNextButtonId);
        }

        if (!nextButton || nextButton.length === 0) {
            nextButton = $('.gform_next_button:visible').first();
        }

        if (!nextButton || nextButton.length === 0) {
            nextButton = $('[id^=gform_next_button_]:visible').first();
        }

        if (!nextButton || nextButton.length === 0) {
            nextButton = $('input[type="submit"].gform_next_button:visible').first();
        }

        if (nextButton && nextButton.length > 0 && !nextButton.is(':disabled')) {
            nextButton.trigger('click');
        }
    }
});
