<?php

/**
 * Deployment list template.
 *
 * @package Devsoom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsoom-autodeploy">
    <h1><?php esc_html_e('Deployments', 'devsoom-autodeploy'); ?></h1>

    <?php
    // Status filter.
    $statuses = array(
        ''       => __('All', 'devsoom-autodeploy'),
        'success' => __('Success', 'devsoom-autodeploy'),
        'failed'  => __('Failed', 'devsoom-autodeploy'),
        'pending' => __('Pending', 'devsoom-autodeploy'),
    );
    ?>

    <ul class="subsubsub">
        <?php foreach ($statuses as $key => $label) : ?>
            <li>
                <?php if ($status === $key) : ?>
                    <strong><?php echo esc_html($label); ?></strong>
                <?php else : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-deployments&status=' . $key)); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endif; ?>
                <?php if ($key !== array_key_last($statuses)) : ?>
                    |
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($deployments)) : ?>
        <p><?php esc_html_e('No deployments found.', 'devsoom-autodeploy'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Repository', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Commit', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Trigger', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Duration', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Date', 'devsoom-autodeploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deployments as $deployment) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-deployments&deployment_id=' . $deployment['id'])); ?>">
                                    <?php echo esc_html($deployment['plugin_slug']); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                        </td>
                        <td><?php echo esc_html($deployment['branch']); ?></td>
                        <td>
                            <code><?php echo esc_html(substr($deployment['commit_hash'], 0, 7)); ?></code>
                        </td>
                        <td>
                            <?php
                            $trigger_labels = array(
                                'webhook'  => __('Webhook', 'devsoom-autodeploy'),
                                'polling'  => __('Polling', 'devsoom-autodeploy'),
                                'manual'   => __('Manual', 'devsoom-autodeploy'),
                            );
                            echo esc_html($trigger_labels[$deployment['trigger_type']] ?? $deployment['trigger_type']);
                            ?>
                        </td>
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
                        <td>
                            <?php
                            if ($deployment['duration']) {
                                echo esc_html($deployment['duration']) . 's';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['created_at'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Pagination.
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) :
        ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    for ($i = 1; $i <= $total_pages; $i++) :
                        $url = admin_url('admin.php?page=devsoom-autodeploy-deployments&paged=' . $i);
                        if (! empty($status)) {
                            $url .= '&status=' . $status;
                        }
                    ?>
                        <?php if ($i === $paged) : ?>
                            <span class="paging-input"><?php echo esc_html($i); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($i); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>