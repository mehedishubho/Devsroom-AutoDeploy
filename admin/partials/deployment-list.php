<?php

/**
 * Deployment list template.
 *
 * @package Devsroom_AutoDeploy
 */

if (! defined('ABSPATH')) {
    exit;
}

$statuses = array(
    ''              => __('All', 'devsroom-autodeploy'),
    'success'       => __('Success', 'devsroom-autodeploy'),
    'failed'        => __('Failed', 'devsroom-autodeploy'),
    'pending'       => __('Pending', 'devsroom-autodeploy'),
    'backing_up'    => __('Backing Up', 'devsroom-autodeploy'),
    'scanning'      => __('Scanning', 'devsroom-autodeploy'),
    'deploying'     => __('Deploying', 'devsroom-autodeploy'),
    'verifying'     => __('Verifying', 'devsroom-autodeploy'),
    'rolling_back'  => __('Rolling Back', 'devsroom-autodeploy'),
);

$status_map = array(
    'success'      => 'success',
    'failed'       => 'error',
    'pending'      => 'warning',
    'scanning'     => 'info',
    'backing_up'   => 'info',
    'locking'      => 'locking',
    'comparing'    => 'comparing',
    'downloading'  => 'downloading',
    'extracting'   => 'extracting',
    'deploying'    => 'deploying',
    'verifying'    => 'verifying',
    'rolling_back' => 'rolling_back',
    'cancelled'    => 'cancelled',
);

$trigger_labels = array(
    'webhook' => __('Webhook', 'devsroom-autodeploy'),
    'polling' => __('Polling', 'devsroom-autodeploy'),
    'manual'  => __('Manual', 'devsroom-autodeploy'),
);
?>

<div class="wrap devsroom-autodeploy">
    <h1 class="devsroom-page-head">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e('Deployments', 'devsroom-autodeploy'); ?>
    </h1>

    <div class="devsroom-section devsroom-panel">
        <div class="devsroom-toolbar">
            <ul class="subsubsub">
                <?php foreach ($statuses as $key => $label) : ?>
                    <li>
                        <?php if ($status === $key) : ?>
                            <strong><?php echo esc_html($label); ?></strong>
                        <?php else : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&status=' . $key)); ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($key !== array_key_last($statuses)) : ?>
                            |
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (empty($deployments)) : ?>
            <div class="ds-empty-state">
                <span class="dashicons dashicons-cloud"></span>
                <h3><?php esc_html_e('No deployments found', 'devsroom-autodeploy'); ?></h3>
                <p><?php esc_html_e('Deployments will appear here when you deploy plugins from connected repositories.', 'devsroom-autodeploy'); ?></p>
            </div>
        <?php else : ?>
            <div class="devsroom-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Commit', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Trigger', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Duration', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Date', 'devsroom-autodeploy'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deployments as $deployment) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&deployment_id=' . $deployment['id'])); ?>" class="font-semibold">
                                        <?php echo esc_html($deployment['plugin_slug']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url('https://github.com/' . $deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>" target="_blank">
                                        <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="ds-branch-tag">
                                        <span class="dashicons dashicons-admin-branch"></span>
                                        <?php echo esc_html($deployment['branch']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo esc_html(substr($deployment['commit_hash'], 0, 7)); ?></code>
                                </td>
                                <td>
                                    <?php
                                    $trigger_icon = array(
                                        'webhook' => 'admin-post',
                                        'polling' => 'update',
                                        'manual'  => 'edit',
                                    );
                                    $icon = $trigger_icon[$deployment['trigger_type']] ?? 'admin-generic';
                                    ?>
                                    <span class="text-muted">
                                        <span class="dashicons dashicons-<?php echo esc_attr($icon); ?> ds-icon-sm ds-trigger-icon"></span>
                                        <?php echo esc_html($trigger_labels[$deployment['trigger_type']] ?? $deployment['trigger_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($status_map[$deployment['status']] ?? 'info'); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $deployment['status']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($deployment['duration']) : ?>
                                        <span class="text-muted"><?php echo esc_html($deployment['duration']); ?>s</span>
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['created_at'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $total_pages = ceil($total / $per_page);
            if ($total_pages > 1) :
            ?>
                <div class="ds-pagination">
                    <?php if ($paged > 1) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&paged=' . ($paged - 1) . ($status ? '&status=' . $status : ''))); ?>">
                            &laquo;
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $paged - 2);
                    $end = min($total_pages, $paged + 2);

                    if ($start > 1) :
                    ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&paged=1' . ($status ? '&status=' . $status : ''))); ?>">1</a>
                        <?php if ($start > 2) : ?>
                            <span class="ds-pagination-dots">&hellip;</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++) : ?>
                        <?php if ($i === $paged) : ?>
                            <span class="ds-pagination-current"><?php echo esc_html($i); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&paged=' . $i . ($status ? '&status=' . $status : ''))); ?>">
                                <?php echo esc_html($i); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages) : ?>
                        <?php if ($end < $total_pages - 1) : ?>
                            <span class="ds-pagination-dots">&hellip;</span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&paged=' . $total_pages . ($status ? '&status=' . $status : ''))); ?>">
                            <?php echo esc_html($total_pages); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($paged < $total_pages) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&paged=' . ($paged + 1) . ($status ? '&status=' . $status : ''))); ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
