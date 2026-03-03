<?php

/**
 * Dashboard class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Admin;

/**
 * Class Dashboard
 *
 * Displays the dashboard page.
 *
 * @since 1.0.0
 */
class Dashboard
{

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        // Register AJAX handler for dismissing recent deployments.
        add_action('wp_ajax_devsroom_autodeploy_dismiss_recent_deployments', array($this, 'ajax_dismiss_recent_deployments'));
    }

    /**
     * AJAX handler for dismissing recent deployments notice.
     *
     * @return void
     */
    public function ajax_dismiss_recent_deployments(): void
    {
        check_ajax_referer('devsroom_autodeploy_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'devsroom-autodeploy')));
        }

        update_user_meta(get_current_user_id(), 'devsroom_hide_recent_deployments', true);

        wp_send_json_success();
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render(): void
    {
        // Handle dismissible notice.
        if (isset($_GET['devsroom_dismiss_recent_deployments'])) {
            if (! current_user_can('manage_options')) {
                wp_die(__('Permission denied', 'devsroom-autodeploy'));
            }
            update_user_meta(get_current_user_id(), 'devsroom_hide_recent_deployments', true);
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy'));
            exit;
        }

        // Handle restore recent deployments.
        if (isset($_GET['devsroom_restore_recent_deployments'])) {
            if (! current_user_can('manage_options')) {
                wp_die(__('Permission denied', 'devsroom-autodeploy'));
            }
            delete_user_meta(get_current_user_id(), 'devsroom_hide_recent_deployments');
            wp_redirect(admin_url('admin.php?page=devsroom-autodeploy'));
            exit;
        }

        // Check if recent deployments should be hidden.
        $hide_recent_deployments = get_user_meta(get_current_user_id(), 'devsroom_hide_recent_deployments', true);

        // Get statistics.
        $stats = $this->get_statistics();

        // Get recent deployments.
        $recent_deployments = $this->get_recent_deployments(5);

        // Get repositories with update status.
        $repository_manager = new Repository_Manager();
        $repositories = $repository_manager->get_repositories_with_update_status();

        // Get updates count.
        $updates_count = $repository_manager->get_updates_count();

        include DEVSROOM_AUTODEPLOY_PATH . 'admin/partials/dashboard.php';
    }

    /**
     * Get dashboard statistics.
     *
     * @return array Statistics data.
     */
    private function get_statistics(): array
    {
        global $wpdb;

        $repositories_table = $wpdb->prefix . 'devsroom_repositories';
        $deployments_table = $wpdb->prefix . 'devsroom_deployments';

        // Total repositories.
        $total_repositories = (int) $wpdb->get_var("SELECT COUNT(*) FROM $repositories_table");

        // Active repositories.
        $active_repositories = (int) $wpdb->get_var("SELECT COUNT(*) FROM $repositories_table WHERE status = 'active'");

        // Total deployments.
        $total_deployments = (int) $wpdb->get_var("SELECT COUNT(*) FROM $deployments_table");

        // Successful deployments.
        $successful_deployments = (int) $wpdb->get_var("SELECT COUNT(*) FROM $deployments_table WHERE status = 'success'");

        // Failed deployments.
        $failed_deployments = (int) $wpdb->get_var("SELECT COUNT(*) FROM $deployments_table WHERE status = 'failed'");

        // Success rate.
        $success_rate = $total_deployments > 0
            ? round(($successful_deployments / $total_deployments) * 100, 2)
            : 0;

        return array(
            'total_repositories'     => $total_repositories,
            'active_repositories'    => $active_repositories,
            'total_deployments'      => $total_deployments,
            'successful_deployments' => $successful_deployments,
            'failed_deployments'     => $failed_deployments,
            'success_rate'          => $success_rate,
        );
    }

    /**
     * Get recent deployments.
     *
     * @param int $limit Number of deployments to retrieve.
     * @return array Array of deployments.
     */
    private function get_recent_deployments(int $limit = 10): array
    {
        global $wpdb;

        $deployments_table = $wpdb->prefix . 'devsroom_deployments';
        $repositories_table = $wpdb->prefix . 'devsroom_repositories';

        $deployments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.*, r.plugin_slug, r.repo_owner, r.repo_name, r.branch
				FROM $deployments_table d
				INNER JOIN $repositories_table r ON d.repository_id = r.id
				ORDER BY d.created_at DESC
				LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $deployments ?: array();
    }
}
