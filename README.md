# Wicket WP Gravity Forms

## Features
* Adds Wicket data type to Populate Anything, allowing people and organization data to be queried live.
* Adds "Wicket Settings" under the Gravity Forms menu in WordPress admin.
  * From there, you can enter a slug for each desired Gravity Form, and a corresponding form ID.
* Provides `wicket_gf_get_form_id_by_slug($slug)` function so you can lookup a form ID using your defined slug (e.g. "my-form").
* Provides `[wicket_gravityform]` shortcode that lets you pass in a `slug` paramter rather than an `id` parameter.
  * Accepts all other parameters that the standard `[gravityform]` shortcode accepts. 
  * This lets you set the shortcode on your page or coded template once, and then update that form ID later if you need to import a new one or temporarily switch to an in-development form.
