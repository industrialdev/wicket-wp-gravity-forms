<?php

declare(strict_types=1);

namespace WicketGF;

use GFAPI;
use GFCache;
use GFCommon;
use GFExport;
use GFFormsModel;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opt-in "update existing form" mode for the Gravity Forms import screen.
 *
 * Injects a checkbox and a target-form dropdown into the core Import Forms
 * page (admin.php?page=gf_export&subview=import_form). The checkbox is off by
 * default; when off, the core import flow runs untouched. When on, the
 * uploaded single-form JSON replaces the selected form's meta (fields,
 * notifications, confirmations, settings) instead of creating a new form.
 * Entries and add-on feeds survive because the form id never changes.
 */
class ImportUpdate
{
    /**
     * Mirror of GFExport::$min_import_version, which is private in core.
     */
    private const MIN_IMPORT_VERSION = '1.3.12.3';

    /**
     * Result codes carried on the redirect URL and rendered as admin notices.
     */
    private const RESULT_UPDATED = 'updated';
    private const RESULT_NO_FILE = 'nofile';
    private const RESULT_MULTI_FILE = 'multifile';
    private const RESULT_TARGET = 'target';
    private const RESULT_INVALID = 'invalid';
    private const RESULT_VERSION = 'version';
    private const RESULT_NOT_SINGLE = 'notsingle';
    private const RESULT_FAILED = 'failed';

    /**
     * Per-user transient that carries the result across the redirect. The
     * redirect URL keeps the same code as a query arg, but only for
     * observability; the notice itself is consumed once from the transient so
     * a refresh or bookmark of the redirect URL never replays the message.
     */
    private const RESULT_TRANSIENT = 'wicket_gf_import_result_';

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'intercept_update_import']);
        add_action('admin_init', [self::class, 'queue_result_notice']);
        add_action('admin_footer', [self::class, 'render_import_ui']);
    }

    /**
     * Whether the current request is the core Import Forms page.
     */
    public static function is_import_page(): bool
    {
        return is_admin()
            && rgget('page') === 'gf_export'
            && rgget('subview') === 'import_form';
    }

    /**
     * POST interceptor. Runs before the core page callback, so an opt-in
     * update import here prevents core from also creating duplicate forms.
     *
     * When our checkbox is absent (default), this returns immediately and the
     * core import behaves exactly as upstream.
     */
    public static function intercept_update_import(): void
    {
        if (!self::is_import_page() || rgpost('import_forms') === false) {
            return;
        }

        if (rgpost('wicket_gf_update_existing') !== '1') {
            return;
        }

        check_admin_referer('gf_import_forms', 'gf_import_forms_nonce');

        if (!class_exists('GFCommon') || !GFCommon::current_user_can_any('gravityforms_edit_forms')) {
            wp_die(esc_html__('You do not have permission to access this page', 'wicket-gf'));
        }

        $files = $_FILES['gf_import_file'] ?? null;
        $names = is_array($files['name'] ?? null) ? $files['name'] : [];
        $tmp_names = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
        $errors = is_array($files['error'] ?? null) ? $files['error'] : [];

        if (empty($tmp_names[0])) {
            self::redirect_with_result(self::RESULT_NO_FILE);
        }

        if (count($tmp_names) > 1) {
            self::redirect_with_result(self::RESULT_MULTI_FILE);
        }

        if (($errors[0] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_names[0])) {
            self::redirect_with_result(self::RESULT_NO_FILE);
        }

        $target_id = absint(rgpost('wicket_gf_update_target'));
        $target_form = $target_id > 0 ? GFAPI::get_form($target_id) : null;

        // Mirror the dropdown, which only offers active, non-trashed forms.
        if (!is_array($target_form) || !empty($target_form['is_trash']) || !rgar($target_form, 'is_active')) {
            self::redirect_with_result(self::RESULT_TARGET);
        }

        $result = self::update_from_file($tmp_names[0], $target_id);
        if (is_wp_error($result)) {
            GFCommon::log_debug(__METHOD__ . '(): Update import failed => ' . print_r($result->get_error_message(), true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            self::redirect_with_result($result->get_error_code());
        }

        self::redirect_with_result(self::RESULT_UPDATED, $target_id);
    }

    /**
     * Replace one existing form with the form contained in an export file.
     *
     * @param string $filepath  Path to the uploaded JSON file.
     * @param int    $target_id Existing form id to overwrite.
     * @return true|WP_Error
     */
    public static function update_from_file(string $filepath, int $target_id)
    {
        $contents = file_get_contents($filepath);
        if ($contents === false) {
            return new WP_Error(self::RESULT_NO_FILE, 'The import file could not be read.');
        }

        $form = self::parse_update_payload($contents);
        if (is_wp_error($form)) {
            return $form;
        }

        $result = GFAPI::update_form($form, $target_id);
        if (is_wp_error($result)) {
            return new WP_Error(self::RESULT_FAILED, 'The form could not be updated.', $result->get_error_message());
        }

        // Parity with the core import: let listeners react to the replaced form.
        $updated = GFAPI::get_form($target_id);
        do_action('gform_forms_post_import', [$updated]);

        return true;
    }

    /**
     * Validate and clean one single-form export payload, mirroring the
     * validation chain inside GFExport::import_json().
     *
     * @param string $forms_json Raw JSON string.
     * @return array|WP_Error The cleaned single form object, or a WP_Error
     *                        whose code is one of this class' result codes.
     */
    public static function parse_update_payload(string $forms_json)
    {
        // GFExport is lazy-loaded by core; boot it exactly like core does.
        if (!class_exists('GFExport')) {
            require_once GFCommon::get_base_path() . '/export.php';
        }

        $clean = GFExport::sanitize_forms_json($forms_json);
        $forms = json_decode($clean, true);

        if (!is_array($forms) || empty($forms)) {
            return new WP_Error(self::RESULT_INVALID, 'The file is not a valid Gravity Forms export.');
        }

        $version = rgar($forms, 'version');
        if (!$version || version_compare((string) $version, self::MIN_IMPORT_VERSION, '<')) {
            return new WP_Error(self::RESULT_VERSION, 'The export file is not compatible with the current Gravity Forms version.');
        }

        // Parity with core import_json: reset the legacy-markup cache flag.
        if (class_exists('GFCache')) {
            GFCache::delete('legacy_is_in_use');
        }

        unset($forms['version']);

        if (count($forms) !== 1) {
            return new WP_Error(self::RESULT_NOT_SINGLE, 'Update mode supports export files containing a single form only.');
        }

        $form = reset($forms);
        $form['markupVersion'] = 2;
        $form = GFFormsModel::convert_field_objects($form);

        return GFFormsModel::sanitize_settings($form);
    }

    /**
     * Inject the checkbox and target-form dropdown into the core import form
     * via a template plus a few lines of vanilla JS. Nothing renders when the
     * core markup cannot be found, so a future GF layout change degrades to
     * stock behavior instead of breaking the page.
     */
    public static function render_import_ui(): void
    {
        if (!self::is_import_page() || !class_exists('GFAPI') || !GFCommon::current_user_can_any('gravityforms_edit_forms')) {
            return;
        }

        $options = '';
        foreach (GFAPI::get_forms() as $form) {
            $options .= sprintf(
                '<option value="%1$d">%2$s (#%1$d)</option>',
                absint($form['id']),
                esc_html(rgar($form, 'title'))
            );
        }
        ?>
        <script type="text/template" id="tmpl-wicket-gf-import-update">
            <div>
                <label for="wicket_gf_update_existing" class="gform-settings-column--left">
                    <?php esc_html_e('Update existing form', 'wicket-gf'); ?>
                </label>
                <div class="gform-settings-column--right">
                    <input type="checkbox" name="wicket_gf_update_existing" id="wicket_gf_update_existing" value="1" />
                    <label for="wicket_gf_update_existing">
                        <?php esc_html_e('Replace the selected form with the file contents instead of creating a new form. Entries and feeds are kept.', 'wicket-gf'); ?>
                    </label>
                </div>
            </div>
            <div id="wicket-gf-update-target-row" style="display: none;">
                <label for="wicket_gf_update_target" class="gform-settings-column--left">
                    <?php esc_html_e('Target form', 'wicket-gf'); ?>
                </label>
                <div class="gform-settings-column--right">
                    <select name="wicket_gf_update_target" id="wicket_gf_update_target" disabled>
                        <option value=""><?php esc_html_e('Select the form to overwrite', 'wicket-gf'); ?></option>
                        <?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built escaped above.?>
                    </select>
                </div>
            </div>
        </script>
        <script>
            (function () {
                var submit = document.querySelector('form input[name="import_forms"]');
                var tmpl = document.getElementById('tmpl-wicket-gf-import-update');
                if (!submit || !tmpl) {
                    return;
                }
                var form = submit.closest('form');
                var rows = document.createElement('div');
                rows.innerHTML = tmpl.innerHTML;
                var checkbox = rows.querySelector('#wicket_gf_update_existing');
                var targetRow = rows.querySelector('#wicket-gf-update-target-row');
                form.insertBefore(rows, submit);
                checkbox.addEventListener('change', function () {
                    targetRow.style.display = checkbox.checked ? '' : 'none';
                    rows.querySelector('#wicket_gf_update_target').disabled = !checkbox.checked;
                });
                form.addEventListener('submit', function (e) {
                    if (!checkbox.checked || window.confirm(<?php echo wp_json_encode(__('Update mode replaces the selected form: its fields, notifications, confirmations, and settings are overwritten with the file contents. Entries are kept, but this screen cannot undo the change. Continue?', 'wicket-gf')); ?>)) {
                        return;
                    }
                    e.preventDefault();
                });
            })();
        </script>
        <?php
    }

    /**
     * Queue the stored redirect result through the Gravity Forms message
     * system so it renders exactly like core import results. GF's
     * find_admin_notices() strips standard WP admin_notices output that lacks
     * its gf-notice markup, so a custom admin_notices renderer does not
     * survive on GF pages. The transient is consumed on first read, so a
     * refresh or bookmark of the redirect URL never replays the message.
     */
    public static function queue_result_notice(): void
    {
        if (!self::is_import_page() || !class_exists('GFCommon')) {
            return;
        }

        $key = self::RESULT_TRANSIENT . get_current_user_id();
        $stored = get_transient($key);
        if (!is_array($stored) || !isset($stored[0], $stored[1])) {
            return;
        }

        delete_transient($key);

        $result = (string) $stored[0];
        $target_id = absint($stored[1]);

        if ($result === self::RESULT_UPDATED && $target_id > 0) {
            $edit_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=gf_edit_forms&id=' . $target_id)),
                esc_html__('Edit form.', 'wicket-gf')
            );
            GFCommon::add_message(
                sprintf(esc_html__('Form #%d updated from the import file.', 'wicket-gf'), $target_id) . ' ' . $edit_link
            );

            return;
        }

        $messages = [
            self::RESULT_NO_FILE => __('No import file was uploaded. Select a Gravity Forms export JSON and try again.', 'wicket-gf'),
            self::RESULT_MULTI_FILE => __('Update mode supports one file at a time. Upload a single form export to update an existing form.', 'wicket-gf'),
            self::RESULT_TARGET => __('The selected target form does not exist. Pick a form from the dropdown.', 'wicket-gf'),
            self::RESULT_INVALID => __('The file could not be imported. Make sure it is a Gravity Forms export JSON.', 'wicket-gf'),
            self::RESULT_VERSION => __('The export file is not compatible with the current Gravity Forms version.', 'wicket-gf'),
            self::RESULT_NOT_SINGLE => __('Update mode supports export files containing a single form only.', 'wicket-gf'),
            self::RESULT_FAILED => __('The form could not be updated. Check the Gravity Forms logging for details.', 'wicket-gf'),
        ];

        if (isset($messages[$result])) {
            GFCommon::add_error_message($messages[$result]);
        }
    }

    /**
     * Store the result once for the acting user and redirect back to the
     * import page. The query args stay on the URL for observability only.
     */
    private static function redirect_with_result(string $result, int $target_id = 0): void
    {
        set_transient(self::RESULT_TRANSIENT . get_current_user_id(), [$result, $target_id], MINUTE_IN_SECONDS * 2);
        $url = add_query_arg(
            ['wicket_gf_import' => $result, 'wicket_gf_target' => $target_id],
            admin_url('admin.php?page=gf_export&subview=import_form')
        );

        wp_safe_redirect($url);
        exit;
    }
}
