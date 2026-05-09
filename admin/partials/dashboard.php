<?php

/**
 * Dashboard page template.
 *
 * @package Devsroom_AutoDeploy
 */

if (! defined('ABSPATH')) {
    exit;
}

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
?>

<div class="wrap devsroom-autodeploy">
    <h1 class="devsroom-page-head">
        <span class="dashicons dashicons-dashboard"></span>
        <?php esc_html_e('Dashboard', 'devsroom-autodeploy'); ?>
    </h1>

    <?php if (isset($_GET['deployed'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Deployment completed successfully!', 'devsroom-autodeploy'); ?></p>
        </div>
    <?php endif; ?>

    <div class="devsroom-dashboard-content">
        <div class="devsroom-section">
            <div class="devsroom-autodeploy-stats-grid">
                <div class="devsroom-autodeploy-stat-card">
                    <div class="stat-icon dashicons dashicons-admin-plugins"></div>
                    <h3><?php esc_html_e('Repositories', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['total_repositories']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <div class="stat-icon dashicons dashicons-yes-alt"></div>
                    <h3><?php esc_html_e('Active', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['active_repositories']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <div class="stat-icon dashicons dashicons-update"></div>
                    <h3><?php esc_html_e('Deployments', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['total_deployments']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <div class="stat-icon dashicons dashicons-chart-bar"></div>
                    <h3><?php esc_html_e('Success Rate', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</div>
                </div>
            </div>
        </div>

        <?php if (!$hide_recent_deployments) : ?>
            <div class="devsroom-section">
                <div class="devsroom-panel">
                    <div class="devsroom-panel-header">
                        <h2><?php esc_html_e('Recent Deployments', 'devsroom-autodeploy'); ?></h2>
                        <?php if (!empty($recent_deployments)) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments')); ?>" class="button button-small">
                                <?php esc_html_e('View All', 'devsroom-autodeploy'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($recent_deployments)) : ?>
                        <div class="ds-empty-state">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <h3><?php esc_html_e('No deployments yet', 'devsroom-autodeploy'); ?></h3>
                            <p><?php esc_html_e('Connect a GitHub repository to start deploying plugins automatically.', 'devsroom-autodeploy'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                                <?php esc_html_e('Add Repository', 'devsroom-autodeploy'); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="devsroom-table-wrap">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></th>
                                        <th><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></th>
                                        <th><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></th>
                                        <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
                                        <th><?php esc_html_e('Date', 'devsroom-autodeploy'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_deployments as $deployment) : ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments&deployment_id=' . $deployment['id'])); ?>" style="font-weight: 600;">
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
                                                <span class="status-badge status-<?php echo esc_attr($status_map[$deployment['status']] ?? 'info'); ?>">
                                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $deployment['status']))); ?>
                                                </span>
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
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="devsroom-section">
            <div class="devsroom-panel">
                <div class="devsroom-panel-header">
                    <h2><?php esc_html_e('Repositories', 'devsroom-autodeploy'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-small button-primary">
                        <span class="dashicons dashicons-plus" style="margin-top: 4px; font-size: 14px;"></span>
                        <?php esc_html_e('Add New', 'devsroom-autodeploy'); ?>
                    </a>
                </div>

                <?php if (empty($repositories)) : ?>
                    <div class="ds-empty-state">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h3><?php esc_html_e('No repositories connected', 'devsroom-autodeploy'); ?></h3>
                        <p><?php esc_html_e('Connect your first GitHub repository to start automating plugin deployments.', 'devsroom-autodeploy'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Add Repository', 'devsroom-autodeploy'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <?php if ($updates_count > 0) : ?>
                        <div class="notice notice-info is-dismissible">
                            <p>
                                <span class="dashicons dashicons-update-alt" style="margin-top: 3px;"></span>
                                <?php
                                printf(
                                    esc_html__('%d repositories have updates available.', 'devsroom-autodeploy'),
                                    esc_html($updates_count)
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="devsroom-table-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></th>
                                    <th><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></th>
                                    <th><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></th>
                                    <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
                                    <th><?php esc_html_e('Update', 'devsroom-autodeploy'); ?></th>
                                    <th><?php esc_html_e('Actions', 'devsroom-autodeploy'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repositories as $repo) : ?>
                                    <tr class="repository-row">
                                        <td><strong><?php echo esc_html($repo['plugin_slug']); ?></strong></td>
                                        <td>
                                            <a href="<?php echo esc_url('https://github.com/' . $repo['repo_owner'] . '/' . $repo['repo_name']); ?>" target="_blank">
                                                <?php echo esc_html($repo['repo_owner'] . '/' . $repo['repo_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="ds-branch-tag">
                                                <span class="dashicons dashicons-admin-branch"></span>
                                                <?php echo esc_html($repo['branch']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo esc_attr($status_map[$repo['status']] ?? 'info'); ?>">
                                                <?php echo esc_html(ucfirst($repo['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($repo['has_update']) : ?>
                                                <span class="update-available-badge">
                                                    <?php esc_html_e('Available', 'devsroom-autodeploy'); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="no-update-badge">
                                                    <?php esc_html_e('Up to date', 'devsroom-autodeploy'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="devsroom-inline-forms">
                                                <?php if ($repo['has_update']) : ?>
                                                    <form class="devsroom-inline-form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>">
                                                        <?php wp_nonce_field('devsroom_autodeploy_save_repository', 'devsroom_autodeploy_nonce'); ?>
                                                        <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                                        <button type="submit" name="devsroom_autodeploy_deploy_now" class="button button-small button-primary">
                                                            <?php esc_html_e('Pull Update', 'devsroom-autodeploy'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-small">
                                                    <?php esc_html_e('Manage', 'devsroom-autodeploy'); ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
