<?php

/**
 * Repository form template.
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
        <span class="dashicons dashicons-admin-links"></span>
        <?php esc_html_e('Repositories', 'devsroom-autodeploy'); ?>
    </h1>

    <?php
    $notices = array(
        'saved'             => array('success', __('Repository saved successfully.', 'devsroom-autodeploy')),
        'deleted'           => array('success', __('Repository deleted successfully.', 'devsroom-autodeploy')),
        'deployed'          => array('success', __('Deployment completed successfully.', 'devsroom-autodeploy')),
        'deployed_activated' => array('success', __('Deployment and activation completed successfully.', 'devsroom-autodeploy')),
        'force_unlocked'    => array('success', __('Deployment lock cleared.', 'devsroom-autodeploy')),
    );

    foreach ($notices as $key => $notice) {
        if (isset($_GET[$key])) {
            echo '<div class="notice notice-' . esc_attr($notice[0]) . ' is-dismissible"><p>' . esc_html($notice[1]) . '</p></div>';
        }
    }

    if (isset($_GET['error'])) {
        $error_messages = array(
            'missing_fields'      => __('Please fill in all required fields.', 'devsroom-autodeploy'),
            'invalid_token'       => __('Invalid authentication token.', 'devsroom-autodeploy'),
            'invalid_repo'        => __('Repository not found or access denied.', 'devsroom-autodeploy'),
            'webhook_failed'      => __('Failed to create webhook on GitHub.', 'devsroom-autodeploy'),
            'invalid_id'          => __('Invalid repository ID.', 'devsroom-autodeploy'),
            'not_found'           => __('Repository not found.', 'devsroom-autodeploy'),
            'db_error'            => __('Database error while saving repository. Please try again.', 'devsroom-autodeploy'),
            'invalid_plugin_slug' => __('Invalid plugin slug: Cannot use "devsroom-autodeploy" as target plugin.', 'devsroom-autodeploy'),
            'invalid_slug_format' => __('Invalid plugin slug format. Use only lowercase letters, numbers, and hyphens.', 'devsroom-autodeploy'),
            'activation_failed'   => __('Deployment succeeded, but plugin activation failed.', 'devsroom-autodeploy'),
        );
        $error_key = sanitize_key(wp_unslash($_GET['error']));
        $error_message = $error_messages[$error_key] ?? sanitize_text_field(wp_unslash($_GET['error']));

        if ('activation_failed' === $error_key && isset($_GET['activation_message'])) {
            $activation_message = sanitize_text_field(wp_unslash($_GET['activation_message']));
            if ('' !== $activation_message) {
                $error_message .= ' ' . sprintf(__('Details: %s', 'devsroom-autodeploy'), $activation_message);
            }
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
    }

    if (isset($_GET['deploy_queued'])) {
        if ('activating' === sanitize_key(wp_unslash($_GET['deploy_queued']))) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Deployment queued. The plugin will be deployed and activated shortly.', 'devsroom-autodeploy') . '</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Deployment queued. It will start shortly.', 'devsroom-autodeploy') . '</p></div>';
        }
    }
    ?>

    <div class="devsroom-section devsroom-panel">
        <h2><?php esc_html_e('Add New Repository', 'devsroom-autodeploy'); ?></h2>

        <form class="devsroom-form" method="post" action="">
            <?php wp_nonce_field('devsroom_autodeploy_save_repository', 'devsroom_autodeploy_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="plugin_slug"><?php esc_html_e('Target Plugin Slug', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="plugin_slug" id="plugin_slug" class="regular-text" required
                            placeholder="<?php esc_attr_e('e.g., my-custom-plugin', 'devsroom-autodeploy'); ?>">
                        <p class="description">
                            <?php esc_html_e('The WordPress plugin folder where repository files will be deployed.', 'devsroom-autodeploy'); ?>
                            <strong class="error-message">
                                <?php esc_html_e('Do NOT use "devsroom-autodeploy".', 'devsroom-autodeploy'); ?>
                            </strong>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="repo_owner"><?php esc_html_e('Repository Owner', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="repo_owner" id="repo_owner" class="regular-text" required
                            placeholder="<?php esc_attr_e('GitHub username or organization', 'devsroom-autodeploy'); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="repo_name"><?php esc_html_e('Repository Name', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="repo_name" id="repo_name" class="regular-text" required
                            placeholder="<?php esc_attr_e('GitHub repository name', 'devsroom-autodeploy'); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="branch"><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="branch" id="branch" class="regular-text" value="main" required>
                        <p class="description"><?php esc_html_e('The branch to track (default: main).', 'devsroom-autodeploy'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="auth_token_id"><?php esc_html_e('Authentication Token', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <select name="auth_token_id" id="auth_token_id" required>
                            <option value=""><?php esc_html_e('Select a token', 'devsroom-autodeploy'); ?></option>
                            <?php foreach ($tokens as $token) : ?>
                                <option value="<?php echo esc_attr($token['id']); ?>">
                                    <?php echo esc_html($token['token_name']); ?> (<?php echo esc_html($token['auth_method']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-settings')); ?>">
                                <span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                                <?php esc_html_e('Manage tokens in Settings', 'devsroom-autodeploy'); ?>
                            </a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Options', 'devsroom-autodeploy'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_deploy" value="1" checked>
                            <?php esc_html_e('Enable automatic deployment via webhook and polling', 'devsroom-autodeploy'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="enable_backup" value="1" checked>
                            <?php esc_html_e('Create backup before deployment', 'devsroom-autodeploy'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="scan_level"><?php esc_html_e('Security Scan Level', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <select name="scan_level" id="scan_level">
                            <option value="none"><?php esc_html_e('None', 'devsroom-autodeploy'); ?></option>
                            <option value="basic" selected><?php esc_html_e('Basic', 'devsroom-autodeploy'); ?></option>
                            <option value="advanced"><?php esc_html_e('Advanced', 'devsroom-autodeploy'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Basic: Common PHP injection patterns. Advanced: Malware signatures + obfuscation.', 'devsroom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="devsroom-form-actions">
                <input type="submit" name="devsroom_autodeploy_save_repository" class="button button-primary" value="<?php esc_attr_e('Add Repository', 'devsroom-autodeploy'); ?>">
            </div>
        </form>
    </div>

    <div class="devsroom-section devsroom-panel">
        <div class="devsroom-panel-header">
            <h2><?php esc_html_e('Connected Repositories', 'devsroom-autodeploy'); ?></h2>
            <?php if (!empty($repositories)) : ?>
                <input type="text" id="repository-search" placeholder="<?php esc_attr_e('Search repositories...', 'devsroom-autodeploy'); ?>" class="regular-text" style="max-width: 200px;">
            <?php endif; ?>
        </div>

        <?php if (empty($repositories)) : ?>
            <div class="ds-empty-state">
                <span class="dashicons dashicons-admin-links"></span>
                <h3><?php esc_html_e('No repositories connected', 'devsroom-autodeploy'); ?></h3>
                <p><?php esc_html_e('Add your first repository above to start deploying.', 'devsroom-autodeploy'); ?></p>
            </div>
        <?php else : ?>
            <div class="devsroom-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Auto Deploy', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Last Deployed', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Update', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
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
                                    <?php if ($repo['auto_deploy']) : ?>
                                        <span class="status-badge status-success"><?php esc_html_e('On', 'devsroom-autodeploy'); ?></span>
                                    <?php else : ?>
                                        <span class="no-update-badge"><?php esc_html_e('Off', 'devsroom-autodeploy'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($repo['last_deployed_at']) : ?>
                                        <span class="text-muted">
                                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $repo['last_deployed_at'])); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-muted"><?php esc_html_e('Never', 'devsroom-autodeploy'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($repo['has_update']) : ?>
                                        <span class="update-available-badge">
                                            <span class="dashicons dashicons-update" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                            <?php esc_html_e('Available', 'devsroom-autodeploy'); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="no-update-badge"><?php esc_html_e('Up to date', 'devsroom-autodeploy'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($status_map[$repo['status']] ?? 'info'); ?>">
                                        <?php echo esc_html(ucfirst($repo['status'])); ?>
                                    </span>
                                    <?php if (! empty($repo['locked_at'])) : ?>
                                        <br>
                                        <span class="devsroom-lock-indicator" title="<?php echo esc_attr(sprintf(__('Locked since %s', 'devsroom-autodeploy'), $repo['locked_at'])); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                            <?php esc_html_e('Locked', 'devsroom-autodeploy'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="devsroom-inline-forms">
                                        <form class="devsroom-inline-form" method="post" action="">
                                            <?php wp_nonce_field('devsroom_autodeploy_save_repository', 'devsroom_autodeploy_nonce'); ?>
                                            <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                            <button type="submit" name="devsroom_autodeploy_deploy_now" class="button button-small <?php echo $repo['has_update'] ? 'button-primary' : ''; ?>">
                                                <?php echo $repo['has_update'] ? esc_html__('Pull Update', 'devsroom-autodeploy') : esc_html__('Deploy Now', 'devsroom-autodeploy'); ?>
                                            </button>
                                        </form>
                                        <form class="devsroom-inline-form" method="post" action="">
                                            <?php wp_nonce_field('devsroom_autodeploy_save_repository', 'devsroom_autodeploy_nonce'); ?>
                                            <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                            <button type="submit" name="devsroom_autodeploy_deploy_activate" class="button button-small">
                                                <?php esc_html_e('Deploy + Activate', 'devsroom-autodeploy'); ?>
                                            </button>
                                        </form>
                                        <form class="devsroom-inline-form" method="post" action="" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this repository?', 'devsroom-autodeploy'); ?>');">
                                            <?php wp_nonce_field('devsroom_autodeploy_save_repository', 'devsroom_autodeploy_nonce'); ?>
                                            <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                            <button type="submit" name="devsroom_autodeploy_delete_repository" class="button button-small button-danger">
                                                <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-top: -1px;"></span>
                                            </button>
                                        </form>
                                        <?php if (! empty($repo['locked_at'])) : ?>
                                            <a href="<?php echo esc_url(wp_nonce_url(
                                                admin_url('admin.php?page=devsroom-autodeploy-repositories&action=force_unlock&repository_id=' . $repo['id']),
                                                'devsroom_autodeploy_force_unlock_' . $repo['id']
                                            )); ?>"
                                               class="button button-small"
                                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to force-unlock this repository?', 'devsroom-autodeploy'); ?>');">
                                                <span class="dashicons dashicons-unlock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-top: -1px;"></span>
                                                <?php esc_html_e('Unlock', 'devsroom-autodeploy'); ?>
                                            </a>
                                        <?php endif; ?>
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
