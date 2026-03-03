<?php

/**
 * Repository form template.
 *
 * @package Devsroom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsroom-autodeploy">
    <h1 class="devsroom-page-head"><?php esc_html_e('Repositories', 'devsroom-autodeploy'); ?></h1>

    <?php
    // Display messages.
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository saved successfully.', 'devsroom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository deleted successfully.', 'devsroom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['deployed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deployment completed successfully.', 'devsroom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['deployed_activated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deployment and activation completed successfully.', 'devsroom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['error'])) {
        $error_messages = array(
            'missing_fields' => __('Please fill in all required fields.', 'devsroom-autodeploy'),
            'invalid_token' => __('Invalid authentication token.', 'devsroom-autodeploy'),
            'invalid_repo' => __('Repository not found or access denied.', 'devsroom-autodeploy'),
            'webhook_failed' => __('Failed to create webhook on GitHub.', 'devsroom-autodeploy'),
            'invalid_id' => __('Invalid repository ID.', 'devsroom-autodeploy'),
            'not_found' => __('Repository not found.', 'devsroom-autodeploy'),
            'db_error' => __('Database error while saving repository. Please try again.', 'devsroom-autodeploy'),
            'invalid_plugin_slug' => __('Invalid plugin slug: Cannot use "devsroom-autodeploy" as target plugin. Please specify a different plugin folder.', 'devsroom-autodeploy'),
            'invalid_slug_format' => __('Invalid plugin slug format. Use only lowercase letters, numbers, and hyphens (e.g., my-plugin).', 'devsroom-autodeploy'),
            'activation_failed' => __('Deployment succeeded, but plugin activation failed.', 'devsroom-autodeploy'),
        );
        $error_key = sanitize_key(wp_unslash($_GET['error']));
        $error_message = $error_messages[$error_key] ?? sanitize_text_field(wp_unslash($_GET['error']));

        if ('activation_failed' === $error_key && isset($_GET['activation_message'])) {
            $activation_message = sanitize_text_field(wp_unslash($_GET['activation_message']));
            if ('' !== $activation_message) {
                $error_message .= ' ' . sprintf(
                    /* translators: %s Activation failure details. */
                    __('Details: %s', 'devsroom-autodeploy'),
                    $activation_message
                );
            }
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
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
                            placeholder="e.g., my-custom-plugin">
                        <p class="description">
                            <?php esc_html_e('The WordPress plugin folder where repository files will be deployed.', 'devsroom-autodeploy'); ?><br>
                            <?php esc_html_e('This should match the target plugin folder name in your wp-content/plugins directory.', 'devsroom-autodeploy'); ?><br>
                            <?php esc_html_e('Example: If deploying to wp-content/plugins/my-plugin, enter "my-plugin".', 'devsroom-autodeploy'); ?><br>
                            <strong class="error-message">
                                <?php esc_html_e('Warning: Do NOT use "devsroom-autodeploy" as this will break the deployment system.', 'devsroom-autodeploy'); ?>
                            </strong>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="repo_owner"><?php esc_html_e('Repository Owner', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="repo_owner" id="repo_owner" class="regular-text" required>
                        <p class="description">
                            <?php esc_html_e('The GitHub username or organization name.', 'devsroom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="repo_name"><?php esc_html_e('Repository Name', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="repo_name" id="repo_name" class="regular-text" required>
                        <p class="description">
                            <?php esc_html_e('The GitHub repository name.', 'devsroom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="branch"><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="branch" id="branch" class="regular-text" value="main" required>
                        <p class="description">
                            <?php esc_html_e('The branch to track (default: main).', 'devsroom-autodeploy'); ?>
                        </p>
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
                                    <?php echo esc_html($token['token_name']); ?>
                                    (<?php echo esc_html($token['auth_method']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select a GitHub authentication token.', 'devsroom-autodeploy'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-settings')); ?>">
                                <?php esc_html_e('Add a new token', 'devsroom-autodeploy'); ?>
                            </a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Auto Deploy', 'devsroom-autodeploy'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_deploy" value="1" checked>
                            <?php esc_html_e('Enable automatic deployment via webhook and polling', 'devsroom-autodeploy'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Backup', 'devsroom-autodeploy'); ?></th>
                    <td>
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
                            <?php esc_html_e('Basic: Check for common PHP injection patterns. Advanced: Includes malware signatures and obfuscated code detection.', 'devsroom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="devsroom_autodeploy_save_repository" class="button button-primary" value="<?php esc_attr_e('Add Repository', 'devsroom-autodeploy'); ?>">
            </p>
        </form>
    </div>

    <div class="devsroom-section devsroom-panel">
        <h2><?php esc_html_e('Connected Repositories', 'devsroom-autodeploy'); ?></h2>

        <?php if (empty($repositories)) : ?>
            <p><?php esc_html_e('No repositories connected yet.', 'devsroom-autodeploy'); ?></p>
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
                            <th><?php esc_html_e('Update Available', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></th>
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
                                <td><?php echo $repo['auto_deploy'] ? esc_html__('Yes', 'devsroom-autodeploy') : esc_html__('No', 'devsroom-autodeploy'); ?></td>
                                <td>
                                    <?php
                                    if ($repo['last_deployed_at']) {
                                        echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $repo['last_deployed_at']));
                                    } else {
                                        esc_html_e('Never', 'devsroom-autodeploy');
                                    }
                                    ?>
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
                                    <span class="status-badge status-<?php echo esc_attr($repo['status']); ?>">
                                        <?php echo esc_html(ucfirst($repo['status'])); ?>
                                    </span>
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
                                            <button type="submit" name="devsroom_autodeploy_delete_repository" class="button button-small">
                                                <?php esc_html_e('Delete', 'devsroom-autodeploy'); ?>
                                            </button>
                                        </form>
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
