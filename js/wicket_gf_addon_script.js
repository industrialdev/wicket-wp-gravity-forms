jQuery( document ).ready( function($) {

  $('#wicket-gf-addon-resync-fields-button').click(function(e) {
    // Call backend function to resync Wicket Member fields to the db-stored JSON array, then render a checkmark
    var formdata = new FormData();
    formdata.append("name", 'test');
    formdata.append("apiKey", 'test');

    var requestOptions = {
      method: 'POST',
      body: formdata,
      redirect: 'follow'
    };

    fetch("/wp-json/wicket-gf/v1/resync-member-fields", requestOptions)
      .then(response => response.text())
      .then(result => {
        console.log(result);
      })
      .catch(error => console.log('error', error));
  })

});