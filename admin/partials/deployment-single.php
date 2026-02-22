<?php

/**
 * Single deployment view template.
 *
 * @package Devsoom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsoom-autodeploy">
    <h1><?php esc_html_e('Deployment Details', 'devsoom-autodeploy'); ?></h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-deployments')); ?>" class="button">
            <?php esc_html_e('← Back to Deployments', 'devsoom-autodeploy'); ?>
        </a>
    </p>

    <h2><?php esc_html_e('Deployment Information', 'devsoom-autodeploy'); ?></h2>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e('Plugin', 'devsoom-autodeploy'); ?></th>
            <td><strong><?php echo esc_html($deployment['plugin_slug']); ?></strong></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Repository', 'devsoom-autodeploy'); ?></th>
            <td>
                <a href="<?php echo esc_url('https://github.com/' . $deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>" target="_blank">
                    <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['branch']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Hash', 'devsoom-autodeploy'); ?></th>
            <td><code><?php echo esc_html($deployment['commit_hash']); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Message', 'devsoom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['commit_message']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Commit Author', 'devsoom-autodeploy'); ?></th>
            <td><?php echo esc_html($deployment['commit_author']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Trigger Type', 'devsoom-autodeploy'); ?></th>
            <td>
                <?php
                $trigger_labels = array(
                    'webhook' => __('Webhook', 'devsoom-autodeploy'),
                    'polling' => __('Polling', 'devsoom-autodeploy'),
                    'manual'  => __('Manual', 'devsoom-autodeploy'),
                );
                echo esc_html($trigger_labels[$deployment['trigger_type']] ?? $deployment['trigger_type']);
                ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Status', 'devsoom-autodeploy'); ?></th>
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
            <th><?php esc_html_e('Duration', 'devsoom-autodeploy'); ?></th>
            <td>
                <?php
                if ($deployment['duration']) {
                    echo esc_html($deployment['duration']) . ' ' . esc_html__('seconds', 'devsoom-autodeploy');
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Started At', 'devsoom-autodeploy'); ?></th>
            <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['started_at'])); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Completed At', 'devsoom-autodeploy'); ?></th>
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
                <th><?php esc_html_e('Backup Path', 'devsoom-autodeploy'); ?></th>
                <td><code><?php echo esc_html($deployment['backup_path']); ?></code></td>
            </tr>
        <?php endif; ?>
        <?php if (! empty($deployment['error_message'])) : ?>
            <tr>
                <th><?php esc_html_e('Error Message', 'devsoom-autodeploy'); ?></th>
                <td class="error-message"><?php echo esc_html($deployment['error_message']); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <h2><?php esc_html_e('Deployment Logs', 'devsoom-autodeploy'); ?></h2>

    <?php if (empty($logs)) : ?>
        <p><?php esc_html_e('No logs available for this deployment.', 'devsoom-autodeploy'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Time', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Level', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Message', 'devsoom-autodeploy'); ?></th>
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