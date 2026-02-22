<?php

/**
 * Repository Manager class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy\Admin;

use Devsoom_AutoDeploy\Core\Auth_Manager;
use Devsoom_AutoDeploy\Core\Deployment_Manager;
use Devsoom_AutoDeploy\Core\GitHub_API;

/**
 * Class Repository_Manager
 *
 * Manages repository connections.
 *
 * @since 1.0.0
 */
class Repository_Manager
{

    /**
     * Render repositories page.
     *
     * @return void
     */
    public function render(): void
    {
        // Handle form submissions.
        $this->handle_form_submissions();

        // Get repositories.
        $repositories = $this->get_repositories();

        // Get auth tokens.
        $auth_manager = Auth_Manager::get_instance();
        $tokens = $auth_manager->get_user_tokens(get_current_user_id());

        include DEVSOMM_AUTODEPLOY_PATH . 'admin/partials/repository-form.php';
    }

    /**
     * Handle form submissions.
     *
     * @return void
     */
    private function handle_form_submissions(): void
    {
        // Check nonce.
        if (! isset($_POST['devsoom_autodeploy_nonce']) || ! wp_verify_nonce($_POST['devsoom_autodeploy_nonce'], 'devsoom_autodeploy_save_repository')) {
            return;
        }

        // Check permissions.
        if (! current_user_can('manage_options')) {
            return;
        }

        // Handle add/edit repository.
        if (isset($_POST['devsoom_autodeploy_save_repository'])) {
            $this->save_repository();
        }

        // Handle delete repository.
        if (isset($_POST['devsoom_autodeploy_delete_repository'])) {
            $this->delete_repository();
        }

        // Handle manual deployment.
        if (isset($_POST['devsoom_autodeploy_deploy_now'])) {
            $this->trigger_deployment();
        }
    }

    /**
     * Save repository.
     *
     * @return void
     */
    private function save_repository(): void
    {
        $plugin_slug    = sanitize_text_field($_POST['plugin_slug'] ?? '');
        $repo_owner     = sanitize_text_field($_POST['repo_owner'] ?? '');
        $repo_name      = sanitize_text_field($_POST['repo_name'] ?? '');
        $branch         = sanitize_text_field($_POST['branch'] ?? 'main');
        $auth_token_id  = (int) ($_POST['auth_token_id'] ?? 0);
        $auto_deploy     = isset($_POST['auto_deploy']) ? 1 : 0;
        $enable_backup  = isset($_POST['enable_backup']) ? 1 : 0;
        $scan_level     = sanitize_text_field($_POST['scan_level'] ?? 'basic');

        // Validate required fields.
        if (empty($plugin_slug) || empty($repo_owner) || empty($repo_name)) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=missing_fields'));
            exit;
        }

        // Validate auth token.
        $auth_manager = Auth_Manager::get_instance();
        $token_data   = $auth_manager->get_token($auth_token_id);

        if (! $token_data) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=invalid_token'));
            exit;
        }

        // Validate repository access.
        $github_api = new GitHub_API($token_data['token']);
        $repository = $github_api->get_repository($repo_owner, $repo_name);

        if (! $repository) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=invalid_repo'));
            exit;
        }

        // Generate webhook secret.
        $webhook_secret = wp_generate_password(32, false, false);

        // Create webhook.
        $webhook_url = rest_url('devsoom-autodeploy/v1/webhook/' . $webhook_secret);
        $webhook = $github_api->create_webhook($repo_owner, $repo_name, $webhook_url, $webhook_secret);

        if (! $webhook) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=webhook_failed'));
            exit;
        }

        // Save to database.
        global $wpdb;
        $table_name = $wpdb->prefix . 'devsoom_repositories';

        $repository_id = (int) ($_POST['repository_id'] ?? 0);

        if ($repository_id > 0) {
            // Update existing repository.
            $wpdb->update(
                $table_name,
                array(
                    'plugin_slug'   => $plugin_slug,
                    'repo_owner'    => $repo_owner,
                    'repo_name'     => $repo_name,
                    'branch'        => $branch,
                    'auth_token_id' => $auth_token_id,
                    'auto_deploy'   => $auto_deploy,
                    'enable_backup' => $enable_backup,
                    'scan_level'    => $scan_level,
                ),
                array('id' => $repository_id),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'),
                array('%d')
            );
        } else {
            // Insert new repository.
            $wpdb->insert(
                $table_name,
                array(
                    'plugin_slug'   => $plugin_slug,
                    'repo_owner'    => $repo_owner,
                    'repo_name'     => $repo_name,
                    'branch'        => $branch,
                    'auth_method'   => 'pat',
                    'auth_token_id' => $auth_token_id,
                    'auto_deploy'   => $auto_deploy,
                    'webhook_secret' => $webhook_secret,
                    'enable_backup' => $enable_backup,
                    'scan_level'    => $scan_level,
                    'status'        => 'active',
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s')
            );
        }

        wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&saved=true'));
        exit;
    }

    /**
     * Delete repository.
     *
     * @return void
     */
    private function delete_repository(): void
    {
        $repository_id = (int) ($_POST['repository_id'] ?? 0);

        if ($repository_id <= 0) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=invalid_id'));
            exit;
        }

        // Get repository.
        $repository = $this->get_repository($repository_id);

        if (! $repository) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=not_found'));
            exit;
        }

        // Delete webhook from GitHub.
        $auth_manager = Auth_Manager::get_instance();
        $token_data   = $auth_manager->get_token($repository['auth_token_id']);

        if ($token_data) {
            $github_api = new GitHub_API($token_data['token']);
            $webhooks = $github_api->get_webhooks($repository['repo_owner'], $repository['repo_name']);

            if ($webhooks) {
                foreach ($webhooks as $webhook) {
                    if (isset($webhook['id'])) {
                        $github_api->delete_webhook(
                            $repository['repo_owner'],
                            $repository['repo_name'],
                            $webhook['id']
                        );
                    }
                }
            }
        }

        // Delete from database.
        global $wpdb;
        $table_name = $wpdb->prefix . 'devsoom_repositories';

        $wpdb->delete(
            $table_name,
            array('id' => $repository_id),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&deleted=true'));
        exit;
    }

    /**
     * Trigger manual deployment.
     *
     * @return void
     */
    private function trigger_deployment(): void
    {
        $repository_id = (int) ($_POST['repository_id'] ?? 0);

        if ($repository_id <= 0) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=invalid_id'));
            exit;
        }

        $deployment_manager = Deployment_Manager::get_instance();
        $result = $deployment_manager->deploy($repository_id, 'manual', get_current_user_id());

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&deployed=true'));
        } else {
            wp_redirect(admin_url('admin.php?page=devsoom-autodeploy-repositories&error=' . urlencode($result['message'])));
        }
        exit;
    }

    /**
     * Get all repositories.
     *
     * @return array Array of repositories.
     */
    private function get_repositories(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_repositories';

        $repositories = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC",
            ARRAY_A
        );

        return $repositories ?: array();
    }

    /**
     * Get repository by ID.
     *
     * @param int $repository_id Repository ID.
     * @return array|false Repository data or false on failure.
     */
    private function get_repository(int $repository_id): array|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_repositories';

        $repository = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $repository_id
            ),
            ARRAY_A
        );

        return $repository ?: false;
    }
}
