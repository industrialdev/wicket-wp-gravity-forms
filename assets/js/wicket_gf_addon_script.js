jQuery(document).ready(function ($) {
    // Handle re-sync button click
    $("#wicket-gf-addon-resync-fields-button").click(function (e) {
        // TODO: Show a spinning loading icon inside the button so the user knows this is loading. Also prevent a second click

        // Call backend function to resync Wicket Member fields to the db-stored JSON array, then render a checkmark
        var formdata = new FormData();
        formdata.append("name", "test");
        formdata.append("apiKey", "test");

        var requestOptions = {
            method: "POST",
            body: formdata,
            redirect: "follow",
        };

        fetch("/wp-json/wicket-gf/v1/resync-member-fields", requestOptions)
            .then((response) => response.text())
            .then((result) => {
                let queryString = window.location.search;
                let urlParams = new URLSearchParams(queryString);
                if (
                    urlParams.get("norefresh") == "" ||
                    !urlParams.get("norefresh")
                ) {
                    location.reload(); // Reload the page so the fields will load afresh from the new DB entry
                }
            })
            .catch((error) => console.log("error", error));
    });
});
