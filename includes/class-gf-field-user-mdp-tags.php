<?php
/**
 * The custom Gravity Forms field for hidden user tags.
 */
if (!class_exists('GF_Field')) {
    die();
}

/**
 * Class GFWicketFieldUserMdpTags.
 *
 * A custom Gravity Forms field that automatically retrieves and stores
 * the current user's tags from Wicket as a comma-separated list.
 */
class GFWicketFieldUserMdpTags extends GF_Field
{
    /**
     * Field type identifier.
     */
    public $type = 'wicket_user_mdp_tags';

    /**
     * Constructor.
     */
    public function __construct($data = [])
    {
        parent::__construct($data);

        // Set default label for admin
        if (empty($this->label)) {
            /*
            If strings are made translatable at the constructor, WP will throw an error:
            Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the wicket-gf domain was triggered too early. This is usually an indicator for some code in the plugin or theme running too early. Translations should be loaded at the init action or later.
            */
            $this->label = 'Wicket User Tags (Hidden)'; // Default non-translated label
        }
    }

    /**
     * Return the field title for the form editor.
     */
    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket User Tags', 'wicket-gf');
    }

    /**
     * Define field button properties for the form editor.
     */
    public function get_form_editor_button()
    {
        return [
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    /**
     * Define the fields settings for the form editor.
     */
    public function get_form_editor_field_settings()
    {
        return [
            'label_setting',
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
        ];
    }

    /**
     * Returns the field's form editor description.
     */
    public function get_form_editor_field_description()
    {
        return esc_attr__('Automatically retrieves the current user\'s tags from Wicket and stores them as a comma-separated list.', 'wicket-gf');
    }

    /**
     * Returns the field input markup for the form editor.
     */
    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<div style="color: #666; font-size: 12px; margin-top: 5px;">Hidden field - will contain user\'s MDP tags on form load</div>';
        }

        // Get current person's tags if we're on the frontend
        if (!is_admin()) {
            $value = $this->get_user_tags();
        }

        // Build the input
        $id = (int) $this->id;
        $field_id = $form['id'] . '_' . $id;

        // Construct the HTML - always hidden on frontend
        $html = sprintf(
            "<style>.gform_wrapper.gravity-theme label[for='input_%s'].gfield_label { display: none; }</style><div style='display:none;' class='ginput_container ginput_container_hidden'><input name='input_%d' id='input_%s' type='hidden' value='%s' class='gform_hidden'/></div>",
            $field_id,
            $id,
            $field_id,
            esc_attr($value)
        );

        return $html;
    }

    /**
     * Get the current user's tags from Wicket.
     */
    private function get_user_tags()
    {
        if (function_exists('wicket_current_person')) {
            $person = wicket_current_person();

            if ($person && $person->getAttribute('tags') !== null && is_array($person->getAttribute('tags'))) {
                $tags_list = implode(',', $person->getAttribute('tags'));

                return $tags_list;
            }
        }

        return '';
    }
}

// Register the field with Gravity Forms
GF_Fields::register(new GFWicketFieldUserMdpTags());
