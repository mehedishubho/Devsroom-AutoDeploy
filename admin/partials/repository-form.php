<?php

/**
 * Repository form template.
 *
 * @package Devsoom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsoom-autodeploy">
    <h1><?php esc_html_e('Repositories', 'devsoom-autodeploy'); ?></h1>

    <?php
    // Display messages.
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository saved successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository deleted successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['deployed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deployment completed successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['error'])) {
        $error_messages = array(
            'missing_fields' => __('Please fill in all required fields.', 'devsoom-autodeploy'),
            'invalid_token' => __('Invalid authentication token.', 'devsoom-autodeploy'),
            'invalid_repo' => __('Repository not found or access denied.', 'devsoom-autodeploy'),
            'webhook_failed' => __('Failed to create webhook on GitHub.', 'devsoom-autodeploy'),
            'invalid_id' => __('Invalid repository ID.', 'devsoom-autodeploy'),
            'not_found' => __('Repository not found.', 'devsoom-autodeploy'),
        );
        $error_message = $error_messages[$_GET['error']] ?? $_GET['error'];
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
    }
    ?>

    <h2><?php esc_html_e('Add New Repository', 'devsoom-autodeploy'); ?></h2>

    <form method="post" action="">
        <?php wp_nonce_field('devsoom_autodeploy_save_repository', 'devsoom_autodeploy_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="plugin_slug"><?php esc_html_e('Plugin Slug', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <input type="text" name="plugin_slug" id="plugin_slug" class="regular-text" required>
                    <p class="description">
                        <?php esc_html_e('The WordPress plugin slug (e.g., my-plugin).', 'devsoom-autodeploy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="repo_owner"><?php esc_html_e('Repository Owner', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <input type="text" name="repo_owner" id="repo_owner" class="regular-text" required>
                    <p class="description">
                        <?php esc_html_e('The GitHub username or organization name.', 'devsoom-autodeploy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="repo_name"><?php esc_html_e('Repository Name', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <input type="text" name="repo_name" id="repo_name" class="regular-text" required>
                    <p class="description">
                        <?php esc_html_e('The GitHub repository name.', 'devsoom-autodeploy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="branch"><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <input type="text" name="branch" id="branch" class="regular-text" value="main" required>
                    <p class="description">
                        <?php esc_html_e('The branch to track (default: main).', 'devsoom-autodeploy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="auth_token_id"><?php esc_html_e('Authentication Token', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <select name="auth_token_id" id="auth_token_id" required>
                        <option value=""><?php esc_html_e('Select a token', 'devsoom-autodeploy'); ?></option>
                        <?php foreach ($tokens as $token) : ?>
                            <option value="<?php echo esc_attr($token['id']); ?>">
                                <?php echo esc_html($token['token_name']); ?>
                                (<?php echo esc_html($token['auth_method']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select a GitHub authentication token.', 'devsoom-autodeploy'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=devsoom-autodeploy-settings')); ?>">
                            <?php esc_html_e('Add a new token', 'devsoom-autodeploy'); ?>
                        </a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Auto Deploy', 'devsoom-autodeploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_deploy" value="1" checked>
                        <?php esc_html_e('Enable automatic deployment via webhook and polling', 'devsoom-autodeploy'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Backup', 'devsoom-autodeploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_backup" value="1" checked>
                        <?php esc_html_e('Create backup before deployment', 'devsoom-autodeploy'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="scan_level"><?php esc_html_e('Security Scan Level', 'devsoom-autodeploy'); ?></label>
                </th>
                <td>
                    <select name="scan_level" id="scan_level">
                        <option value="none"><?php esc_html_e('None', 'devsoom-autodeploy'); ?></option>
                        <option value="basic" selected><?php esc_html_e('Basic', 'devsoom-autodeploy'); ?></option>
                        <option value="advanced"><?php esc_html_e('Advanced', 'devsoom-autodeploy'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Basic: Check for common PHP injection patterns. Advanced: Includes malware signatures and obfuscated code detection.', 'devsoom-autodeploy'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="devsoom_autodeploy_save_repository" class="button button-primary" value="<?php esc_attr_e('Add Repository', 'devsoom-autodeploy'); ?>">
        </p>
    </form>

    <h2><?php esc_html_e('Connected Repositories', 'devsoom-autodeploy'); ?></h2>

    <?php if (empty($repositories)) : ?>
        <p><?php esc_html_e('No repositories connected yet.', 'devsoom-autodeploy'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Repository', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Branch', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Auto Deploy', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Last Deployed', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Update Available', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'devsoom-autodeploy'); ?></th>
                    <th><?php esc_html_e('Actions', 'devsoom-autodeploy'); ?></th>
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
                        <td><?php echo $repo['auto_deploy'] ? esc_html__('Yes', 'devsoom-autodeploy') : esc_html__('No', 'devsoom-autodeploy'); ?></td>
                        <td>
                            <?php
                            if ($repo['last_deployed_at']) {
                                echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $repo['last_deployed_at']));
                            } else {
                                esc_html_e('Never', 'devsoom-autodeploy');
                            }
                            ?>
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
                            <span class="status-badge status-<?php echo esc_attr($repo['status']); ?>">
                                <?php echo esc_html(ucfirst($repo['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field('devsoom_autodeploy_save_repository', 'devsoom_autodeploy_nonce'); ?>
                                <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                <button type="submit" name="devsoom_autodeploy_deploy_now" class="button button-small <?php echo $repo['has_update'] ? 'button-primary' : ''; ?>">
                                    <?php echo $repo['has_update'] ? esc_html__('Pull Update', 'devsoom-autodeploy') : esc_html__('Deploy Now', 'devsoom-autodeploy'); ?>
                                </button>
                            </form>
                            <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this repository?', 'devsoom-autodeploy'); ?>');">
                                <?php wp_nonce_field('devsoom_autodeploy_save_repository', 'devsoom_autodeploy_nonce'); ?>
                                <input type="hidden" name="repository_id" value="<?php echo esc_attr($repo['id']); ?>">
                                <button type="submit" name="devsoom_autodeploy_delete_repository" class="button button-small">
                                    <?php esc_html_e('Delete', 'devsoom-autodeploy'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>