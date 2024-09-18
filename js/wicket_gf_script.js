// ORGSS auto-advance on selection

// TODO: Put this in a higher-level conditional so it won't pull indefinitely on non OB pages
// Wait for the next buttons to be present on screen
jQuery(document).ready(function() {
  var existCondition = setInterval(function() {
  if (jQuery('[id^=gform_next_button_]').length) {
      clearInterval(existCondition);
      findActiveNextButton();
  }
  }, 100); // check every 100ms
});

// On page load, find out active next button, make note of it, and hide it
function findActiveNextButton() {
  let next_buttons = jQuery('[id^=gform_next_button_]');
  let active_next_button = null;
  next_buttons.each(function(index) {
    if(jQuery(this).is(":visible")) {
      active_next_button = jQuery(this);
    }
  });
  if(!active_next_button) {
    return;
  }
  window.activeNextButtonId = active_next_button.attr('id');

  // Don't hide next button as this would currently apply to all pages - 
  // Hide with a specific ID rule in Code Chest or stylesheet instead
  //active_next_button.css('opacity', 0);
}

// Listen for org selection (existing or just created) to then proceed to the next GF page, of a multi-step          
jQuery(window).on('orgss-selection-made', (event) => {
  let nextButton = jQuery('#' + window.activeNextButtonId);
  nextButton.trigger('click');
});
