<?php

declare(strict_types=1);

namespace WicketGF;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin Logs List View for MDP Sync Logs.
 *
 * Registers a submenu page under Gravity Forms for browsing MDP sync history.
 */
class MdpSyncLogsPage
{
    private const PAGE_SLUG = 'wicket_gf_mdp_sync_logs';
    private const PER_PAGE = 20;

    private MdpSyncLogger $logger;

    public function __construct(MdpSyncLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Register hooks.
     */
    public function register(): void
    {
        \add_action('admin_menu', [$this, 'add_menu_page']);
    }

    /**
     * Add the submenu page under Gravity Forms.
     */
    public function add_menu_page(): void
    {
        \add_submenu_page(
            'gf_edit_forms',
            \__('MDP Sync Logs', 'wicket-gf'),
            \__('MDP Sync Logs', 'wicket-gf'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Render the logs list page.
     */
    public function render_page(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Unauthorized', 'wicket-gf'));
        }

        $filter_status = \sanitize_text_field((string) ($_GET['filter_status'] ?? ''));
        $filter_form   = \absint($_GET['filter_form'] ?? 0);
        $paged         = \max(1, \absint($_GET['paged'] ?? 1));
        $offset        = ($paged - 1) * self::PER_PAGE;

        $args = [
            'limit'  => self::PER_PAGE,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order'  => 'DESC',
        ];

        if ($filter_status !== '') {
            $args['status'] = $filter_status;
        }
        if ($filter_form > 0) {
            $args['form_id'] = $filter_form;
        }

        $logs  = $this->logger->get_logs($args);
        $total = $this->logger->count_logs(\array_intersect_key($args, \array_flip(['status', 'form_id'])));
        $pages = (int) \ceil($total / self::PER_PAGE);

        $status_colors = [
            'success' => '#2ea043',
            'failed'  => '#d63638',
            'pending' => '#dba617',
            'skipped' => '#6c757d',
        ];

        ?>
<div class="wrap">
    <h1><?php \esc_html_e('MDP Sync Logs', 'wicket-gf'); ?></h1>

    <!-- Filters -->
    <form method="get" style="margin-bottom:16px;">
        <input type="hidden" name="page" value="<?php echo \esc_attr(self::PAGE_SLUG); ?>" />

        <select name="filter_status" style="vertical-align:middle;">
            <option value=""><?php \esc_html_e('All Statuses', 'wicket-gf'); ?></option>
            <?php foreach (['success', 'failed', 'pending', 'skipped'] as $s): ?>
                <option value="<?php echo \esc_attr($s); ?>" <?php \selected($filter_status, $s); ?>>
                    <?php echo \esc_html(\ucfirst($s)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="filter_form" value="<?php echo \esc_attr($filter_form > 0 ? (string)$filter_form : ''); ?>"
            placeholder="<?php \esc_attr_e('Form ID', 'wicket-gf'); ?>" min="0" class="small-text"
            style="vertical-align:middle;" />

        <?php \submit_button(\__('Filter', 'wicket-gf'), 'secondary', '', false, ['style' => 'vertical-align:middle;']); ?>
    </form>

    <!-- Summary -->
    <p class="description">
        <?php
        \printf(
            \esc_html__('Showing %1$d of %2$d log entries (Page %3$d of %4$d)', 'wicket-gf'),
            \count($logs),
            $total,
            $paged,
            \max($pages, 1)
        );
        ?>
    </p>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th style="width:70px;">Form</th>
                <th style="width:70px;">Entry</th>
                <th style="width:90px;">Entity</th>
                <th style="width:80px;">Status</th>
                <th>Message</th>
                <th style="width:150px;">Timestamp</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="7"><?php \esc_html_e('No sync logs found.', 'wicket-gf'); ?></td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo \esc_html($log->id); ?></td>
                <td><?php echo \esc_html($log->form_id); ?></td>
                <td>
                    <?php if ($log->entry_id > 0): ?>
                        <a href="<?php echo \esc_url(\admin_url('admin.php?page=gf_entries&view=entry&id=' . $log->form_id . '&lid=' . $log->entry_id)); ?>">
                            <?php echo \esc_html($log->entry_id); ?>
                        </a>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
                <td><?php echo \esc_html($log->entity_type); ?></td>
                <td>
                    <?php
                    $color = $status_colors[$log->status] ?? '#6c757d';
                    \printf(
                        '<span style="display:inline-block;padding:1px 6px;border-radius:3px;color:#fff;background:%s;font-size:11px;font-weight:600;">%s</span>',
                        \esc_attr($color),
                        \esc_html(\strtoupper($log->status))
                    );
                    ?>
                </td>
                <td><?php echo \esc_html($log->message); ?></td>
                <td style="font-size:12px;color:#666;"><?php echo \esc_html($log->created_at); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="tablenav bottom" style="margin-top:8px;">
        <div class="tablenav-pages">
            <?php
            $base_url = \admin_url('admin.php?page=' . self::PAGE_SLUG);
            if ($filter_status !== '') {
                $base_url .= '&filter_status=' . \urlencode($filter_status);
            }
            if ($filter_form > 0) {
                $base_url .= '&filter_form=' . $filter_form;
            }

            if ($paged > 1) {
                \printf(
                    '<a class="button" href="%s&paged=%d">%s</a> ',
                    \esc_url($base_url),
                    $paged - 1,
                    \esc_html__('&larr; Prev', 'wicket-gf')
                );
            }
            if ($paged < $pages) {
                \printf(
                    '<a class="button" href="%s&paged=%d">%s</a>',
                    \esc_url($base_url),
                    $paged + 1,
                    \esc_html__('Next &rarr;', 'wicket-gf')
                );
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
    }

    /**
     * Get the page slug. Exposed for tests.
     */
    public static function get_page_slug(): string
    {
        return self::PAGE_SLUG;
    }
}
