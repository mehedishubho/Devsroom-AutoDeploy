<?php

/**
 * Settings class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Admin;

use Devsroom_AutoDeploy\Core\Auth_Manager;
use Devsroom_AutoDeploy\Core\Polling_Scheduler;

/**
 * Class Settings
 *
 * Displays the settings page.
 *
 * @since 1.0.0
 */
class Settings
{

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render(): void
    {
        // Handle OAuth callback.
        if (isset($_GET['oauth_callback'])) {
            $this->handle_oauth_callback();
        }

        // Handle form submissions.
        $this->handle_form_submissions();

        // Get settings.
        $settings = $this->get_settings();

        // Get auth tokens.
        $auth_manager = Auth_Manager::get_instance();
        $tokens = $auth_manager->get_user_tokens(get_current_user_id());

        include DEVSROOM_AUTODEPLOY_PATH . 'admin/partials/settings.php';
    }

    /**
     * Handle OAuth callback.
     *
     * @return void
     */
    private function handle_oauth_callback(): void
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if (empty($code) || empty($state)) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=oauth_failed'));
            exit;
        }

        $auth_manager = Auth_Manager::get_instance();

        // Verify state.
        if (! $auth_manager->verify_oauth_state(get_current_user_id(), $state)) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=oauth_state_invalid'));
            exit;
        }

        // Exchange code for token.
        $token_data = $auth_manager->exchange_code_for_token($code, get_current_user_id());

        if (! $token_data) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=oauth_exchange_failed'));
            exit;
        }

        // Store token.
        $auth_manager->store_oauth_token(
            get_current_user_id(),
            $token_data['access_token'],
            $token_data['refresh_token'] ?? '',
            $token_data['expires_in'] ?? 0,
            $token_data['scope'] ?? ''
        );

        wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&oauth_success=true'));
        exit;
    }

    /**
     * Handle form submissions.
     *
     * @return void
     */
    private function handle_form_submissions(): void
    {
        // Check nonce.
        if (! isset($_POST['devsroom_autodeploy_nonce']) || ! wp_verify_nonce($_POST['devsroom_autodeploy_nonce'], 'devsroom_autodeploy_settings')) {
            return;
        }

        // Check permissions.
        if (! current_user_can('manage_options')) {
            return;
        }

        // Handle save settings.
        if (isset($_POST['devsroom_autodeploy_save_settings'])) {
            $this->save_settings();
        }

        // Handle add PAT token.
        if (isset($_POST['devsroom_autodeploy_add_pat'])) {
            $this->add_pat_token();
        }

        // Handle delete token.
        if (isset($_POST['devsroom_autodeploy_delete_token'])) {
            $this->delete_token();
        }
    }

    /**
     * Save settings.
     *
     * @return void
     */
    private function save_settings(): void
    {
        // GitHub OAuth settings.
        $client_id     = sanitize_text_field($_POST['github_client_id'] ?? '');
        $client_secret = sanitize_text_field($_POST['github_client_secret'] ?? '');

        update_option('devsroom_autodeploy_github_client_id', $client_id);
        update_option('devsroom_autodeploy_github_client_secret', $client_secret);

        // Deployment settings.
        $polling_interval = sanitize_text_field($_POST['polling_interval'] ?? 'hourly');
        update_option('devsroom_autodeploy_polling_interval', $polling_interval);
        $scheduler = Polling_Scheduler::get_instance();
        $scheduler->clear_schedule();
        $scheduler->schedule();

        // Backup settings.
        $backup_retention_days = (int) ($_POST['backup_retention_days'] ?? 30);
        $max_backup_size_mb = (int) ($_POST['max_backup_size_mb'] ?? 100);

        update_option('devsroom_autodeploy_backup_retention_days', $backup_retention_days);
        update_option('devsroom_autodeploy_max_backup_size_mb', $max_backup_size_mb);

        // Notification settings.
        $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
        $notification_email = sanitize_email($_POST['notification_email'] ?? '');

        update_option('devsroom_autodeploy_enable_notifications', $enable_notifications);
        update_option('devsroom_autodeploy_notification_email', $notification_email);

        // Security settings.
        $scan_level_default = sanitize_text_field($_POST['scan_level_default'] ?? 'basic');
        update_option('devsroom_autodeploy_scan_level_default', $scan_level_default);

        wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&saved=true'));
        exit;
    }

    /**
     * Add PAT token.
     *
     * @return void
     */
    private function add_pat_token(): void
    {
        $token     = sanitize_text_field($_POST['pat_token'] ?? '');
        $token_name = sanitize_text_field($_POST['pat_token_name'] ?? '');

        if (empty($token)) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=missing_token'));
            exit;
        }

        $auth_manager = Auth_Manager::get_instance();

        // Validate token.
        $validation_result = $auth_manager->validate_token($token);

        if (! $validation_result) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=invalid_token'));
            exit;
        }

        // Store token.
        $auth_manager->store_pat_token(get_current_user_id(), $token, $token_name);

        wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&token_added=true'));
        exit;
    }

    /**
     * Delete token.
     *
     * @return void
     */
    private function delete_token(): void
    {
        $token_id = (int) ($_POST['token_id'] ?? 0);

        if ($token_id <= 0) {
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&error=invalid_id'));
            exit;
        }

        $auth_manager = Auth_Manager::get_instance();
        $auth_manager->delete_token($token_id);

        wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-settings&token_deleted=true'));
        exit;
    }

    /**
     * Get settings.
     *
     * @return array Settings data.
     */
    private function get_settings(): array
    {
        return array(
            'github_client_id'        => get_option('devsroom_autodeploy_github_client_id', ''),
            'github_client_secret'     => get_option('devsroom_autodeploy_github_client_secret', ''),
            'polling_interval'        => get_option('devsroom_autodeploy_polling_interval', 'hourly'),
            'backup_retention_days'   => get_option('devsroom_autodeploy_backup_retention_days', 30),
            'max_backup_size_mb'     => get_option('devsroom_autodeploy_max_backup_size_mb', 100),
            'enable_notifications'    => get_option('devsroom_autodeploy_enable_notifications', true),
            'notification_email'      => get_option('devsroom_autodeploy_notification_email', ''),
            'scan_level_default'     => get_option('devsroom_autodeploy_scan_level_default', 'basic'),
        );
    }

    /**
     * Get GitHub OAuth URL for current user.
     *
     * @return string
     */
    public function get_oauth_url(): string
    {
        $auth_manager = Auth_Manager::get_instance();
        return $auth_manager->get_oauth_authorization_url(get_current_user_id());
    }
}
