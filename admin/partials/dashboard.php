<?php

/**
 * Dashboard page template.
 *
 * @package Devsoom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsoom-autodeploy">
    <h1><?php esc_html_e('Devsoom AutoDeploy Dashboard', 'devsoom-autodeploy'); ?></h1>

    <?php if (isset($_GET['deployed'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Deployment completed successfully!', 'devsoom-autodeploy'); ?></p>
        </div>
    <?php endif; ?>

    <div class="devsoom-autodeploy-stats-grid">
        <div class="devsoom-autodeploy-stat-card">
            <h3><?php esc_html_e('Total Repositories', 'devsoom-autodeploy'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['total_repositories']); ?></div>
        </div>

        <div class="devsoom-autodeploy-stat-card">
            <h3><?php esc_html_e('Active Repositories', 'devsoom-autodeploy'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['active_repositories']); ?></div>
        </div>

        <div class="devsoom-autodeploy-stat-card">
            <h3><?php esc_html_e('Total Deployments', 'devsoom-autodeploy'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['total_deployments']); ?></div>
        </div>

        <div class="devsoom-autodeploy-stat-card">
            <h3><?php esc_html_e('Success Rate', 'devsoom-autodeploy'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</div>
        </div>
    </div>

    <h2><?php esc_html_e('Recent Deployments', 'devsoom-autodeploy'); ?></h2>

    <?php if (empty($recent_deployments)) : ?>
        <p><?php esc_html_e('No deployments yet. Connect a repository to get started.', 'devsoom-autodeploy'); ?></p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-repositories')); ?>" class="button button-primary">
                <?php esc_html_e('Add Repository', 'devsoom-autodeploy'); ?>
            </a>
        </p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Repository', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Date', 'devsoom-autodeploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_deployments as $deployment) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($deployment['plugin_slug']); ?></strong>
                        </td>
                        <td>
                            <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                        </td>
                        <td><?php echo esc_html($deployment['branch']); ?></td>
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
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['created_at'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-deployments')); ?>" class="button">
                <?php esc_html_e('View All Deployments', 'devsoom-autodeploy'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>