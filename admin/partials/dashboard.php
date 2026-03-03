<?php

/**
 * Dashboard page template.
 *
 * @package Devsroom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsroom-autodeploy">
    <h1 class="devsroom-page-head"><?php esc_html_e('Devsroom AutoDeploy Dashboard', 'devsroom-autodeploy'); ?></h1>

    <?php if (isset($_GET['deployed'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Deployment completed successfully!', 'devsroom-autodeploy'); ?></p>
        </div>
    <?php endif; ?>

    <div class="devsroom-dashboard-content">
        <div class="devsroom-section">
            <div class="devsroom-autodeploy-stats-grid">
                <div class="devsroom-autodeploy-stat-card">
                    <h3><?php esc_html_e('Total Repositories', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['total_repositories']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <h3><?php esc_html_e('Active Repositories', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['active_repositories']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <h3><?php esc_html_e('Total Deployments', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['total_deployments']); ?></div>
                </div>

                <div class="devsroom-autodeploy-stat-card">
                    <h3><?php esc_html_e('Success Rate', 'devsroom-autodeploy'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</div>
                </div>
            </div>
        </div>

        <?php if (!$hide_recent_deployments) : ?>
            <div class="devsroom-section">
                <div class="notice notice-info devsroom-recent-deployments-notice is-dismissible">
                    <h2><?php esc_html_e('Recent Deployments', 'devsroom-autodeploy'); ?></h2>

                    <?php if (empty($recent_deployments)) : ?>
                        <p><?php esc_html_e('No deployments yet. Connect a repository to get started.', 'devsroom-autodeploy'); ?></p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-primary">
                                <?php esc_html_e('Add Repository', 'devsroom-autodeploy'); ?>
                            </a>
                        </p>
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
                        </div>

                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments')); ?>" class="button">
                                <?php esc_html_e('View All Deployments', 'devsroom-autodeploy'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="devsroom-section">
            <h2><?php esc_html_e('Repositories', 'devsroom-autodeploy'); ?></h2>

            <?php if (empty($repositories)) : ?>
                <div class="devsroom-panel">
                    <p><?php esc_html_e('No repositories connected yet.', 'devsroom-autodeploy'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-repositories')); ?>" class="button button-primary">
                            <?php esc_html_e('Add Repository', 'devsroom-autodeploy'); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <?php if ($updates_count > 0) : ?>
                    <div class="notice notice-info is-dismissible">
                        <p>
                            <?php
                            printf(
                                esc_html__('There are %d repositories with updates available.', 'devsroom-autodeploy'),
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
                                <th><?php esc_html_e('Update Available', 'devsroom-autodeploy'); ?></th>
                                <th><?php esc_html_e('Actions', 'devsroom-autodeploy'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repositories as $repo) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($repo['plugin_slug']); ?></strong></td>
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
                                                <?php esc_html_e('Yes', 'devsroom-autodeploy'); ?>
                                            </span>
                                            <?php if ($repo['latest_commit_message']) : ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo esc_html(substr($repo['latest_commit_message'], 0, 50)); ?>...
                                                </small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="no-update-badge">
                                                <?php esc_html_e('No', 'devsroom-autodeploy'); ?>
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
