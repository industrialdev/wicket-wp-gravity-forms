// ORGSS auto-advance on selection
if( WicketGfPluginData.shouldAutoAdvance ) {
  // Wait for the next buttons to be present on screen
  jQuery(document).ready(function() {
    let tries = 0;
    var existCondition = setInterval(function() {
      if (jQuery('[id^=gform_next_button_]').length) {
        clearInterval(existCondition);
        findActiveNextButton();
      } else if(tries > 30) {
        clearInterval(existCondition);
      }
      tries++;
    }, 100); // check every 100ms
  });

  // Listen for org selection (existing or just created) to then proceed to the next GF page, of a multi-step          
  jQuery(window).on('orgss-selection-made', (event) => {
    setTimeout(function() {
      let nextButton = jQuery('#' + window.activeNextButtonId);
      nextButton.trigger('click');
    }, 2000); // Give GF a couple seconds to register any programatic form changes before we advance too fast
  });

}

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