<?php

/**
 * Single deployment view template.
 *
 * @package Devsroom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsroom-autodeploy">
    <h1><?php esc_html_e('Deployment Details', 'devsroom-autodeploy'); ?></h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments')); ?>" class="button">
            <?php esc_html_e('← Back to Deployments', 'devsroom-autodeploy'); ?>
        </a>
    </p>

    <h2><?php esc_html_e('Deployment Information', 'devsroom-autodeploy'); ?></h2>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></th>
            <td><strong><?php echo esc_html($deployment['plugin_slug']); ?></strong></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></th>
            <td>
                <a href="<?php echo esc_url('https://github.com/' . $deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>" target="_blank">
                    <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['branch']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Hash', 'devsroom-autodeploy'); ?></th>
            <td><code><?php echo esc_html($deployment['commit_hash']); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Message', 'devsroom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['commit_message']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Author', 'devsroom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['commit_author']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Trigger Type', 'devsroom-autodeploy'); ?></th>
            <td>
                <?php
                $trigger_labels = array(
                    'webhook' => __('Webhook', 'devsroom-autodeploy'),
                    'polling' => __('Polling', 'devsroom-autodeploy'),
                    'manual'  => __('Manual', 'devsroom-autodeploy'),
                );
                echo esc_html($trigger_labels[$deployment['trigger_type']] ?? $deployment['trigger_type']);
                ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
            <td>
                <?php
                $status_classes = array(
                    'success' => 'success',
                    'failed' => 'error',
                    'pending' => 'warning',
                    'scanning' => 'info',
                    'backing_up' => 'info',
                );
                $status_class = $status_classes[$deployment['status']] ?? 'info';
                ?>
                <span class="status-badge status-<?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html(ucfirst($deployment['status'])); ?>
                </span>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Duration', 'devsroom-autodeploy'); ?></th>
            <td>
                <?php
                if ($deployment['duration']) {
                    echo esc_html($deployment['duration']) . ' ' . esc_html__('seconds', 'devsroom-autodeploy');
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Started At', 'devsroom-autodeploy'); ?></th>
            <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['started_at'])); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Completed At', 'devsroom-autodeploy'); ?></th>
            <td>
                <?php
                if ($deployment['completed_at']) {
                    echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['completed_at']));
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
        <?php if (! empty($deployment['backup_path'])) : ?>
            <tr>
                <th><?php esc_html_e('Backup Path', 'devsroom-autodeploy'); ?></th>
                <td><code><?php echo esc_html($deployment['backup_path']); ?></code></td>
            </tr>
        <?php endif; ?>
        <?php if (! empty($deployment['error_message'])) : ?>
            <tr>
                <th><?php esc_html_e('Error Message', 'devsroom-autodeploy'); ?></th>
                <td class="error-message"><?php echo esc_html($deployment['error_message']); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <h2><?php esc_html_e('Deployment Logs', 'devsroom-autodeploy'); ?></h2>

    <?php if (empty($logs)) : ?>
        <p><?php esc_html_e('No logs available for this deployment.', 'devsroom-autodeploy'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Time', 'devsroom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Level', 'devsroom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Message', 'devsroom-autodeploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date(get_option('time_format'), $log['created_at'])); ?></td>
                        <td>
                            <span class="log-level log-<?php echo esc_attr($log['level']); ?>">
                                <?php echo esc_html(strtoupper($log['level'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>