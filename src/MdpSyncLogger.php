<?php

declare(strict_types=1);

namespace WicketGF;

// No direct access
defined('ABSPATH') || exit;

/**
 * MDP Sync Logger.
 *
 * Persists sync events to a custom table for browsable history.
 * Supports configurable retention via admin setting.
 *
 * Table: {prefix}wicket_gf_mdp_sync_log
 */
class MdpSyncLogger
{
    /**
     * Option key for retention days.
     */
    private const OPTION_RETENTION_DAYS = 'wicket_gf_mdp_log_retention_days';

    /**
     * Default retention period in days. 0 = keep forever.
     */
    private const DEFAULT_RETENTION_DAYS = 30;

    /**
     * Cron hook for log cleanup.
     */
    private const CLEANUP_HOOK = 'wicket_gf_mdp_sync_log_cleanup';

    /**
     * Log levels.
     */
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_FAILED  = 'failed';
    public const LEVEL_SKIPPED = 'skipped';
    public const LEVEL_PENDING = 'pending';

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action(self::CLEANUP_HOOK, [$this, 'cleanup_old_logs']);
    }

    /**
     * Ensure the log table exists. Safe to call multiple times.
     *
     * @return bool True if table exists or was created.
     */
    public function ensure_table(): bool
    {
        global $wpdb;

        $table = $this->table_name();
        $charset = $wpdb->get_charset_collate();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table DDL, not queryable via WP API
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        if ($row) {
            return true;
        }

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id int(11) unsigned NOT NULL DEFAULT 0,
            entry_id int(11) unsigned NOT NULL DEFAULT 0,
            entity_type varchar(32) NOT NULL DEFAULT '',
            uuid varchar(64) NOT NULL DEFAULT '',
            status varchar(16) NOT NULL DEFAULT '',
            message text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_form_entry (form_id, entry_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Verify creation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verification after DDL
        $check = $wpdb->get_row($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return !empty($check);
    }

    /**
     * Log a sync event.
     *
     * @param array $data {
     *   @type int    $form_id     GF form ID.
     *   @type int    $entry_id    GF entry ID.
     *   @type string $entity_type 'person' or 'organization'.
     *   @type string $uuid        Entity UUID (sanitized/truncated for storage).
     *   @type string $status      One of the LEVEL_* constants.
     *   @type string $message     Human-readable message.
     * }
     * @return int|false Inserted row ID, or false on failure.
     */
    public function log(array $data): int|false
    {
        global $wpdb;

        $this->ensure_table();

        $insert = [
            'form_id'     => absint($data['form_id'] ?? 0),
            'entry_id'    => absint($data['entry_id'] ?? 0),
            'entity_type' => sanitize_text_field((string) ($data['entity_type'] ?? '')),
            'uuid'        => sanitize_text_field(substr((string) ($data['uuid'] ?? ''), 0, 64)),
            'status'      => sanitize_text_field((string) ($data['status'] ?? '')),
            'message'     => sanitize_text_field((string) ($data['message'] ?? '')),
            'created_at'  => current_time('mysql'),
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
        $result = $wpdb->insert($this->table_name(), $insert);

        if ($result === false) {
            return false;
        }

        // Schedule cleanup if retention is configured
        $this->maybe_schedule_cleanup();

        return (int) $wpdb->insert_id;
    }

    /**
     * Get logs with optional filtering and pagination.
     *
     * @param array $args {
     *   @type int    $form_id    Filter by form ID.
     *   @type int    $entry_id   Filter by entry ID.
     *   @type string $status     Filter by status.
     *   @type int    $limit      Max rows (default 50).
     *   @type int    $offset     Offset for pagination.
     *   @type string $orderby    Column to order by (default 'created_at').
     *   @type string $order      'ASC' or 'DESC' (default 'DESC').
     * }
     * @return array Array of log row objects.
     */
    public function get_logs(array $args = []): array
    {
        global $wpdb;

        $this->ensure_table();

        $table = $this->table_name();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['form_id'])) {
            $where[] = 'form_id = %d';
            $params[] = absint($args['form_id']);
        }

        if (!empty($args['entry_id'])) {
            $where[] = 'entry_id = %d';
            $params[] = absint($args['entry_id']);
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        $orderby = in_array($args['orderby'] ?? '', ['id', 'form_id', 'entry_id', 'status', 'created_at'], true)
            ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
        $limit = absint($args['limit'] ?? 50);
        $offset = absint($args['offset'] ?? 0);

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
             . " ORDER BY {$orderby} {$order}"
             . " LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read
        return $wpdb->get_results(
            $wpdb->prepare($sql, ...$params)
        );
    }

    /**
     * Count logs matching filters.
     *
     * @param array $args Same filters as get_logs (minus limit/offset/order).
     * @return int
     */
    public function count_logs(array $args = []): int
    {
        global $wpdb;

        $this->ensure_table();

        $table = $this->table_name();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['form_id'])) {
            $where[] = 'form_id = %d';
            $params[] = absint($args['form_id']);
        }

        if (!empty($args['entry_id'])) {
            $where[] = 'entry_id = %d';
            $params[] = absint($args['entry_id']);
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table count
        return (int) $wpdb->get_var(
            empty($params) ? $sql : $wpdb->prepare($sql, ...$params)
        );
    }

    /**
     * Delete logs older than the configured retention period.
     *
     * @return int Number of rows deleted.
     */
    public function cleanup_old_logs(): int
    {
        global $wpdb;

        $days = $this->get_retention_days();

        if ($days <= 0) {
            return 0; // Retention disabled
        }

        $table = $this->table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table cleanup
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return (int) $result;
    }

    /**
     * Get the retention period in days.
     *
     * @return int 0 = keep forever.
     */
    public function get_retention_days(): int
    {
        return (int) get_option(self::OPTION_RETENTION_DAYS, self::DEFAULT_RETENTION_DAYS);
    }

    /**
     * Set the retention period in days.
     *
     * @param int $days 0 = keep forever.
     */
    public function set_retention_days(int $days): void
    {
        update_option(self::OPTION_RETENTION_DAYS, max(0, $days));
    }

    /**
     * Get the retention option key. Exposed for admin settings registration.
     */
    public static function get_retention_option_key(): string
    {
        return self::OPTION_RETENTION_DAYS;
    }

    /**
     * Get the cleanup cron hook. Exposed for tests.
     */
    public static function get_cleanup_hook(): string
    {
        return self::CLEANUP_HOOK;
    }

    /**
     * Get the full table name including WP prefix.
     */
    public function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wicket_gf_mdp_sync_log';
    }

    /**
     * Schedule cleanup cron if retention is configured and not already scheduled.
     */
    private function maybe_schedule_cleanup(): void
    {
        if ($this->get_retention_days() <= 0) {
            return;
        }

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }
}
