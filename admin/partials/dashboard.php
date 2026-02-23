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

    <h2><?php esc_html_e('Repositories', 'devsoom-autodeploy'); ?></h2>

    <?php if (empty($repositories)) : ?>
        <p><?php esc_html_e('No repositories connected yet.', 'devsoom-autodeploy'); ?></p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-repositories')); ?>" class="button button-primary">
                <?php esc_html_e('Add Repository', 'devsoom-autodeploy'); ?>
            </a>
        </p>
    <?php else : ?>
        <?php if ($updates_count > 0) : ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('There are %d repositories with updates available.', 'devsoom-autodeploy'),
                        esc_html($updates_count)
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Repository', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Update Available', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Actions', 'devsoom-autodeploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($repositories as $repo) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($repo['plugin_slug']); ?></strong>
                        </td>
                        <td>
                            <a href="<?php echo esc_url('https://github.com/' . $repo['repo_owner'] . '/' . $repo['repo_name']); ?>" target="_blank">
                                <?php echo esc_html($repo['repo_owner'] . '/' . $repo['repo_name']); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($repo['branch']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($repo['status']); ?>">
                                <?php echo esc_html(ucfirst($repo['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($repo['has_update']) : ?>
                                <span class="update-available-badge">
                                    <?php esc_html_e('Yes', 'devsoom-autodeploy'); ?>
                                </span>
                                <?php if ($repo['latest_commit_message']) : ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo esc_html(substr($repo['latest_commit_message'], 0, 50)); ?>...
                                    </small>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="no-update-badge">
                                    <?php esc_html_e('No', 'devsoom-autodeploy'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($repo['has_update']) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-repositories')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('devsoom_autodeploy_save_repository', 'devsoom_autodeploy_nonce'); ?>
                                    <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                    <button type="submit" name="devsoom_autodeploy_deploy_now" class="button button-small button-primary">
                                        <?php esc_html_e('Pull Update', 'devsoom-autodeploy'); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-repositories')); ?>" class="button button-small">
                                <?php esc_html_e('Manage', 'devsoom-autodeploy'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>