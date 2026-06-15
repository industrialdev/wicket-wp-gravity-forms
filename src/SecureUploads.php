<?php

declare(strict_types=1);

namespace WicketGF;

/**
 * Secure file uploads for Gravity Forms.
 *
 * Adds a per-field "Store uploads securely" option to file upload fields. When
 * enabled, files submitted through that field are moved out of the public
 * uploads directory into a storage location above the web root, and the entry
 * value is rewritten to a capability-gated download endpoint. The public file
 * is moved before notifications fire, so notification emails contain the gated
 * link rather than a public URL.
 *
 * Storage location resolution order:
 *   1. WICKET_GF_SECURE_UPLOADS_DIR constant, if defined.
 *   2. Default: <above-docroot>/gf-uploads-secure (derived from WP_CONTENT_DIR).
 *   3. The `wicket_gf_secure_uploads_base_dir` filter (applied last).
 *
 * The download capability defaults to `gravityforms_view_entries` and can be
 * changed with the `wicket_gf_secure_upload_capability` filter.
 */
class SecureUploads
{
    /** Query var / admin-post action used for the gated download endpoint. */
    public const DOWNLOAD_ACTION = 'wicket_gf_secure_file';

    /**
     * Register hooks. Called once from the plugin bootstrap.
     */
    public static function init(): void
    {
        // Field editor: add the setting and load/persist its value.
        add_action('gform_field_standard_settings', [self::class, 'render_field_setting'], 200, 2);
        add_action('gform_editor_js', [self::class, 'editor_js']);
        add_filter('gform_tooltips', [self::class, 'register_tooltips']);

        // Move files + rewrite entry value before notifications are sent.
        // Registered as a filter: the returned $entry is what GF uses for the
        // notifications dispatched later in the same request.
        add_filter('gform_entry_post_save', [self::class, 'secure_entry_files'], 10, 2);

        // Gated download endpoint. Both hooks route to the same handler, which
        // enforces login + capability; the nopriv registration ensures
        // logged-out requests get a clean 403 rather than admin-post.php's
        // empty fall-through response.
        add_action('admin_post_' . self::DOWNLOAD_ACTION, [self::class, 'handle_download']);
        add_action('admin_post_nopriv_' . self::DOWNLOAD_ACTION, [self::class, 'handle_download']);

        // Render secured fields as the filename linking to the gated endpoint
        // (entry detail screen and {all_fields} notification merge tag).
        add_filter('gform_entry_field_value', [self::class, 'filter_entry_field_value'], 10, 4);
    }

    /**
     * Absolute path to the secure storage base directory (no trailing slash).
     */
    public static function base_dir(): string
    {
        if (defined('WICKET_GF_SECURE_UPLOADS_DIR') && WICKET_GF_SECURE_UPLOADS_DIR) {
            $dir = (string) WICKET_GF_SECURE_UPLOADS_DIR;
        } else {
            // WP_CONTENT_DIR is <docroot>/app (Bedrock) or <docroot>/wp-content.
            // One level up is the docroot; two levels up is above the docroot.
            $dir = dirname(WP_CONTENT_DIR, 2) . '/gf-uploads-secure';
        }

        $dir = (string) apply_filters('wicket_gf_secure_uploads_base_dir', $dir);

        return untrailingslashit($dir);
    }

    /**
     * Whether a field is a file upload configured for secure storage.
     */
    protected static function is_secure_field(\GF_Field $field): bool
    {
        return $field->get_input_type() === 'fileupload' && !empty($field->wicketGfSecureUpload);
    }

    /**
     * Entry meta key under which the secure relative paths for a field are
     * stored (JSON array, one entry per uploaded file).
     */
    protected static function meta_key(int $field_id): string
    {
        return '_wicket_gf_secure_' . $field_id;
    }

    // ---------------------------------------------------------------------
    // Field editor UI
    // ---------------------------------------------------------------------

    /**
     * Render the "Store uploads securely" setting on file upload fields.
     *
     * @param int $position Settings position slot.
     * @param int $form_id  Current form ID (unused).
     */
    public static function render_field_setting($position, $form_id): void
    {
        if ($position !== 200) {
            return;
        }
        ?>
        <li class="wicket_gf_secure_upload_setting field_setting">
            <input type="checkbox" id="wicket_gf_secure_upload" onclick="SetFieldProperty('wicketGfSecureUpload', this.checked);" />
            <label for="wicket_gf_secure_upload" class="inline">
                <?php esc_html_e('Store uploads securely (outside the web root)', 'wicket-gf'); ?>
                <?php gform_tooltip('wicket_gf_secure_upload'); ?>
            </label>
            <div class="wicket_gf_secure_upload_subfolder_wrapper" style="margin-top:8px;">
                <label for="wicket_gf_secure_upload_subfolder" class="inline">
                    <?php esc_html_e('Secure subfolder (optional)', 'wicket-gf'); ?>
                </label>
                <input type="text" id="wicket_gf_secure_upload_subfolder" size="35"
                    onkeyup="SetFieldProperty('wicketGfSecureUploadSubfolder', this.value);"
                    onchange="SetFieldProperty('wicketGfSecureUploadSubfolder', this.value);" />
                <p class="description">
                    <?php esc_html_e('Groups this field\'s files under a named folder inside the secure store, so different forms or document types stay separate. Leave blank to store them directly under the form.', 'wicket-gf'); ?>
                    <br />
                    <?php printf(
                        /* translators: %s: example resulting storage path */
                        esc_html__('Example: entering %1$s stores files at %2$s', 'wicket-gf'),
                        '<code>first-aid-certificates</code>',
                        '<code>gf-uploads-secure/first-aid-certificates/form-{id}/entry-{id}/yourfile.pdf</code>'
                    ); ?>
                </p>
            </div>
        </li>
        <?php
    }

    /**
     * Editor JS: expose the setting on file upload fields and bind its values.
     */
    public static function editor_js(): void
    {
        ?>
        <script type="text/javascript">
            fieldSettings.fileupload += ', .wicket_gf_secure_upload_setting';

            jQuery(document).on('gform_load_field_settings', function (event, field) {
                jQuery('#wicket_gf_secure_upload').prop('checked', field.wicketGfSecureUpload == true);
                jQuery('#wicket_gf_secure_upload_subfolder').val(field.wicketGfSecureUploadSubfolder || '');
            });
        </script>
        <?php
    }

    /**
     * Register the tooltip text for the setting.
     *
     * @param array $tooltips
     * @return array
     */
    public static function register_tooltips($tooltips)
    {
        $tooltips['wicket_gf_secure_upload'] = '<h6>' . esc_html__('Secure Upload', 'wicket-gf') . '</h6>'
            . esc_html__('Files uploaded to this field are stored above the web root and can only be downloaded by authorised, logged-in users. The public URL is removed.', 'wicket-gf');

        return $tooltips;
    }

    // ---------------------------------------------------------------------
    // Move files on submission
    // ---------------------------------------------------------------------

    /**
     * Move secured-field files out of the public uploads dir and rewrite the
     * entry value to the gated download endpoint.
     *
     * @param array $entry
     * @param array $form
     * @return array
     */
    public static function secure_entry_files($entry, $form)
    {
        if (!class_exists('GFAPI') || empty($form['fields'])) {
            return $entry;
        }

        $entry_id = (int) rgar($entry, 'id');

        foreach ($form['fields'] as $field) {
            if (!$field instanceof \GF_Field || !self::is_secure_field($field)) {
                continue;
            }

            $field_id = (int) $field->id;
            $raw_value = rgar($entry, (string) $field_id);
            if (empty($raw_value)) {
                continue;
            }

            // Already secured (e.g. re-save of an existing entry): skip.
            if (is_string($raw_value) && strpos($raw_value, 'action=' . self::DOWNLOAD_ACTION) !== false) {
                continue;
            }

            // GF may store the value as a JSON array even for single-file
            // fields, so decode by shape rather than trusting multipleFiles.
            $decoded = json_decode((string) $raw_value, true);
            $stored_as_array = json_last_error() === JSON_ERROR_NONE && is_array($decoded);
            $urls = $stored_as_array ? $decoded : [$raw_value];
            $urls = array_values(array_filter(array_map('strval', $urls)));
            if (!$urls) {
                continue;
            }
            // Preserve the original storage shape when writing the new value.
            $store_as_array = $stored_as_array || !empty($field->multipleFiles);

            $subfolder = self::sanitize_subfolder((string) ($field->wicketGfSecureUploadSubfolder ?? ''));
            $download_urls = [];
            $relative_paths = [];

            foreach ($urls as $index => $url) {
                $relative = self::move_to_secure_storage((string) $url, $subfolder, (int) $form['id'], $entry_id);
                if ($relative === null) {
                    // Move failed: keep the original value for this file so the
                    // submission is not silently lost, and log it.
                    self::log('Failed to secure file for entry ' . $entry_id . ' field ' . $field_id . ': ' . $url);
                    $download_urls[] = $url;
                    $relative_paths[] = null;
                    continue;
                }
                $relative_paths[] = $relative;
                $download_urls[] = self::download_url($entry_id, $field_id, (int) $index);
            }

            // Persist the server-side path map (tamper-proof source of truth).
            gform_update_meta($entry_id, self::meta_key($field_id), wp_json_encode($relative_paths), (int) $form['id']);

            $new_value = $store_as_array ? wp_json_encode($download_urls) : (string) $download_urls[0];
            $result = \GFAPI::update_entry_field($entry_id, (string) $field_id, $new_value);
            if (is_wp_error($result)) {
                self::log('Failed to update entry field ' . $field_id . ' for entry ' . $entry_id . ': ' . $result->get_error_message());
            } else {
                // Keep the in-memory entry consistent for later hooks/notifications.
                $entry[(string) $field_id] = $new_value;
            }
        }

        return $entry;
    }

    /**
     * Move a single uploaded file from its public location into secure storage.
     *
     * @return string|null Relative path under the secure base dir, or null on failure.
     */
    protected static function move_to_secure_storage(string $url, string $subfolder, int $form_id, int $entry_id): ?string
    {
        $upload = wp_upload_dir();
        $baseurl = trailingslashit($upload['baseurl']);
        $basedir = trailingslashit($upload['basedir']);

        // Resolve the source file from its public URL.
        $relative_to_uploads = str_replace($baseurl, '', $url);
        if ($relative_to_uploads === $url) {
            // URL is not under the uploads dir; nothing we can safely move.
            return null;
        }
        $source = $basedir . ltrim($relative_to_uploads, '/');
        if (!is_file($source)) {
            return null;
        }

        $filename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        if ($filename === '') {
            return null;
        }

        // Layout: [subfolder/]form-<id>/entry-<id>/<filename>. The per-entry
        // directory keeps filenames unique without collision counters.
        $relative_dir = ($subfolder !== '' ? $subfolder . '/' : '') . 'form-' . $form_id . '/entry-' . $entry_id;
        $dest_dir = self::base_dir() . '/' . $relative_dir;

        if (!self::ensure_dir($dest_dir)) {
            return null;
        }

        $dest = $dest_dir . '/' . $filename;
        if (!@rename($source, $dest)) {
            return null;
        }

        return $relative_dir . '/' . $filename;
    }

    /**
     * Create a directory (recursively) and drop guard files so a misconfigured
     * server can never serve a directory listing.
     */
    protected static function ensure_dir(string $dir): bool
    {
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        $base = self::base_dir();
        $index = $base . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
        $htaccess = $base . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }

        return true;
    }

    /**
     * Sanitize the per-field subfolder into a safe relative path segment.
     */
    protected static function sanitize_subfolder(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $parts = [];
        foreach (explode('/', $value) as $segment) {
            $segment = sanitize_file_name($segment);
            if ($segment !== '' && $segment !== '.' && $segment !== '..') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Display secured file fields as the original filename linking to the
     * gated download endpoint, rather than the bare admin-post.php URL.
     *
     * @param string    $value Pre-rendered field value.
     * @param \GF_Field $field
     * @param array     $entry
     * @param array     $form
     * @return string
     */
    public static function filter_entry_field_value($value, $field, $entry, $form)
    {
        if (!$field instanceof \GF_Field || $field->get_input_type() !== 'fileupload' || empty($field->wicketGfSecureUpload)) {
            return $value;
        }

        $entry_id = (int) rgar($entry, 'id');
        $field_id = (int) $field->id;
        $paths = json_decode((string) gform_get_meta($entry_id, self::meta_key($field_id)), true);
        if (!is_array($paths) || !$paths) {
            return $value;
        }

        $links = [];
        foreach ($paths as $index => $relative) {
            if (!is_string($relative) || $relative === '') {
                continue;
            }
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(self::download_url($entry_id, $field_id, (int) $index)),
                esc_html(basename($relative))
            );
        }

        return $links ? implode('<br />', $links) : $value;
    }

    // ---------------------------------------------------------------------
    // Gated download endpoint
    // ---------------------------------------------------------------------

    /**
     * Build the capability-gated download URL stored as the entry value.
     */
    protected static function download_url(int $entry_id, int $field_id, int $index): string
    {
        return add_query_arg(
            [
                'action' => self::DOWNLOAD_ACTION,
                'entry'  => $entry_id,
                'field'  => $field_id,
                'idx'    => $index,
            ],
            admin_url('admin-post.php')
        );
    }

    /**
     * Stream a secured file to an authorised user. Reached by both the
     * logged-in and logged-out admin-post hooks; the login + capability check
     * below is the gate, so logged-out requests get an explicit 403.
     */
    public static function handle_download(): void
    {
        $capability = (string) apply_filters('wicket_gf_secure_upload_capability', 'gravityforms_view_entries');
        if (!is_user_logged_in() || !current_user_can($capability)) {
            wp_die(esc_html__('You are not allowed to access this file.', 'wicket-gf'), '', ['response' => 403]);
        }

        $entry_id = isset($_GET['entry']) ? absint($_GET['entry']) : 0;
        $field_id = isset($_GET['field']) ? absint($_GET['field']) : 0;
        $index = isset($_GET['idx']) ? absint($_GET['idx']) : 0;
        if (!$entry_id || !$field_id) {
            wp_die(esc_html__('Invalid request.', 'wicket-gf'), '', ['response' => 400]);
        }

        $entry = \GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            wp_die(esc_html__('File not found.', 'wicket-gf'), '', ['response' => 404]);
        }

        $paths = json_decode((string) gform_get_meta($entry_id, self::meta_key($field_id)), true);
        $relative = is_array($paths) && isset($paths[$index]) ? $paths[$index] : null;
        if (!is_string($relative) || $relative === '') {
            wp_die(esc_html__('File not found.', 'wicket-gf'), '', ['response' => 404]);
        }

        // Resolve and confine to the secure base directory (defence against
        // any traversal that slipped past sanitisation).
        $base = realpath(self::base_dir());
        $full = realpath(self::base_dir() . '/' . $relative);
        if ($base === false || $full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
            wp_die(esc_html__('File not found.', 'wicket-gf'), '', ['response' => 404]);
        }

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full) . '"');
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');

        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($full);
        exit;
    }

    /**
     * Write to the Wicket log if available, otherwise PHP error_log.
     */
    protected static function log(string $message): void
    {
        if (function_exists('wicket_gf_write_log')) {
            wicket_gf_write_log($message, 'error');

            return;
        }
        error_log('[Wicket GF Secure Uploads] ' . $message);
    }
}
