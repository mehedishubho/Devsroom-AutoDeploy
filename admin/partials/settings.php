<?php

/**
 * Settings page template.
 *
 * @package Devsoom_AutoDeploy
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap devsoom-autodeploy">
    <h1><?php esc_html_e('Devsoom AutoDeploy Settings', 'devsoom-autodeploy'); ?></h1>

    <?php
    // Display messages.
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['token_added'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token added successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['token_deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token deleted successfully.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['oauth_success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('OAuth connection successful.', 'devsoom-autodeploy') . '</p></div>';
    }
    if (isset($_GET['error'])) {
        $error_messages = array(
            'oauth_failed' => __('OAuth connection failed.', 'devsoom-autodeploy'),
            'oauth_state_invalid' => __('OAuth state verification failed.', 'devsoom-autodeploy'),
            'oauth_exchange_failed' => __('Failed to exchange OAuth code for token.', 'devsoom-autodeploy'),
            'missing_token' => __('Please provide a token.', 'devsoom-autodeploy'),
            'invalid_token' => __('Invalid token.', 'devsoom-autodeploy'),
            'invalid_id' => __('Invalid token ID.', 'devsoom-autodeploy'),
        );
        $error_message = $error_messages[$_GET['error']] ?? $_GET['error'];
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
    }
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'devsoom-autodeploy'); ?></a>
        <a href="#authentication" class="nav-tab"><?php esc_html_e('Authentication', 'devsoom-autodeploy'); ?></a>
    </h2>

    <!-- General Settings Tab -->
    <div id="general" class="tab-content active">
        <form method="post" action="">
            <?php wp_nonce_field('devsoom_autodeploy_settings', 'devsoom_autodeploy_nonce'); ?>

            <h3><?php esc_html_e('Deployment Settings', 'devsoom-autodeploy'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="polling_interval"><?php esc_html_e('Polling Interval', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <select name="polling_interval" id="polling_interval">
                            <option value="hourly" <?php selected($settings['polling_interval'], 'hourly'); ?>><?php esc_html_e('Hourly', 'devsoom-autodeploy'); ?></option>
                            <option value="twicedaily" <?php selected($settings['polling_interval'], 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'devsoom-autodeploy'); ?></option>
                            <option value="daily" <?php selected($settings['polling_interval'], 'daily'); ?>><?php esc_html_e('Daily', 'devsoom-autodeploy'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('How often to check for updates via polling.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Backup Settings', 'devsoom-autodeploy'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="backup_retention_days"><?php esc_html_e('Backup Retention', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="backup_retention_days" id="backup_retention_days" value="<?php echo esc_attr($settings['backup_retention_days']); ?>" min="1" max="365">
                        <?php esc_html_e('days', 'devsoom-autodeploy'); ?>
                        <p class="description">
                            <?php esc_html_e('How long to keep backups before automatic cleanup.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_backup_size_mb"><?php esc_html_e('Maximum Backup Size', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_backup_size_mb" id="max_backup_size_mb" value="<?php echo esc_attr($settings['max_backup_size_mb']); ?>" min="1" max="1000">
                        <?php esc_html_e('MB', 'devsoom-autodeploy'); ?>
                        <p class="description">
                            <?php esc_html_e('Maximum size of individual backups.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Notification Settings', 'devsoom-autodeploy'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Notifications', 'devsoom-autodeploy'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_notifications" value="1" <?php checked($settings['enable_notifications'], 1); ?>>
                            <?php esc_html_e('Send email notifications on deployment events', 'devsoom-autodeploy'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_email"><?php esc_html_e('Notification Email', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr($settings['notification_email']); ?>">
                        <p class="description">
                            <?php esc_html_e('Email address to send notifications to. Leave blank to use admin email.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Security Settings', 'devsoom-autodeploy'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="scan_level_default"><?php esc_html_e('Default Scan Level', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <select name="scan_level_default" id="scan_level_default">
                            <option value="none" <?php selected($settings['scan_level_default'], 'none'); ?>><?php esc_html_e('None', 'devsoom-autodeploy'); ?></option>
                            <option value="basic" <?php selected($settings['scan_level_default'], 'basic'); ?>><?php esc_html_e('Basic', 'devsoom-autodeploy'); ?></option>
                            <option value="advanced" <?php selected($settings['scan_level_default'], 'advanced'); ?>><?php esc_html_e('Advanced', 'devsoom-autodeploy'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Default security scan level for new repositories.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="devsoom_autodeploy_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'devsoom-autodeploy'); ?>">
            </p>
        </form>
    </div>

    <!-- Authentication Tab -->
    <div id="authentication" class="tab-content">
        <h3><?php esc_html_e('GitHub OAuth Settings', 'devsoom-autodeploy'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('devsoom_autodeploy_settings', 'devsoom_autodeploy_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="github_client_id"><?php esc_html_e('GitHub Client ID', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="github_client_id" id="github_client_id" class="regular-text" value="<?php echo esc_attr($settings['github_client_id']); ?>">
                        <p class="description">
                            <?php esc_html_e('GitHub OAuth App Client ID.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github_client_secret"><?php esc_html_e('GitHub Client Secret', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="github_client_secret" id="github_client_secret" class="regular-text" value="<?php echo esc_attr($settings['github_client_secret']); ?>">
                        <p class="description">
                            <?php esc_html_e('GitHub OAuth App Client Secret.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="devsoom_autodeploy_save_settings" class="button button-primary" value="<?php esc_attr_e('Save OAuth Settings', 'devsoom-autodeploy'); ?>">
            </p>
        </form>

        <?php if (! empty($settings['github_client_id']) && ! empty($settings['github_client_secret'])) : ?>
            <p>
                <a href="<?php echo esc_url($this->get_oauth_url()); ?>" class="button button-secondary">
                    <?php esc_html_e('Connect with GitHub OAuth', 'devsoom-autodeploy'); ?>
                </a>
            </p>
        <?php endif; ?>

        <h3><?php esc_html_e('Personal Access Tokens', 'devsoom-autodeploy'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('devsoom_autodeploy_settings', 'devsoom_autodeploy_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pat_token"><?php esc_html_e('Add New Token', 'devsoom-autodeploy'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="pat_token" id="pat_token" class="regular-text" placeholder="<?php esc_attr_e('ghp_xxxxxxxxxxxxxxxxxxxx', 'devsoom-autodeploy'); ?>">
                        <input type="text" name="pat_token_name" id="pat_token_name" class="regular-text" placeholder="<?php esc_attr_e('Token name (optional)', 'devsoom-autodeploy'); ?>">
                        <p class="description">
                            <?php esc_html_e('Create a Personal Access Token in GitHub with repo scope.', 'devsoom-autodeploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="devsoom_autodeploy_add_pat" class="button button-secondary" value="<?php esc_attr_e('Add Token', 'devsoom-autodeploy'); ?>">
            </p>
        </form>

        <h3><?php esc_html_e('Your Tokens', 'devsoom-autodeploy'); ?></h3>

        <?php if (empty($tokens)) : ?>
            <p><?php esc_html_e('No tokens added yet.', 'devsoom-autodeploy'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'devsoom-autodeploy'); ?></th>
                        <th><?php esc_html_e('Type', 'devsoom-autodeploy'); ?></th>
                        <th><?php esc_html_e('Created', 'devsoom-autodeploy'); ?></th>
                        <th><?php esc_html_e('Actions', 'devsoom-autodeploy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $token) : ?>
                        <tr>
                            <td><?php echo esc_html($token['token_name']); ?></td>
                            <td><?php echo esc_html(ucfirst($token['auth_method'])); ?></td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $token['created_at'])); ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this token?', 'devsoom-autodeploy'); ?>');">
                                    <?php wp_nonce_field('devsoom_autodeploy_settings', 'devsoom_autodeploy_nonce'); ?>
                                    <input type="hidden" name="token_id" value="<?php echo esc_attr($token['id']); ?>">
                                    <button type="submit" name="devsoom_autodeploy_delete_token" class="button button-small">
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
</div>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation.
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        });
    });
</script>