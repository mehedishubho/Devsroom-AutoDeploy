<?php

/**
 * Polling Scheduler class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Core;

/**
 * Class Polling_Scheduler
 *
 * Handles scheduled polling for repository updates.
 *
 * @since 1.0.0
 */
class Polling_Scheduler
{

    /**
     * Singleton instance.
     *
     * @var Polling_Scheduler|null
     */
    private static ?Polling_Scheduler $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Polling_Scheduler
     */
    public static function get_instance(): Polling_Scheduler
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Register polling event.
        add_action('devsroom_autodeploy_polling_event', array($this, 'poll_repositories'));

        // Register cleanup event.
        add_action('devsroom_autodeploy_cleanup_event', array($this, 'cleanup'));
    }

    /**
     * Schedule polling events.
     *
     * @return void
     */
    public function schedule(): void
    {
        if (! wp_next_scheduled('devsroom_autodeploy_polling_event')) {
            $interval = get_option('devsroom_autodeploy_polling_interval', 'hourly');
            wp_schedule_event(time(), $interval, 'devsroom_autodeploy_polling_event');
        }

        if (! wp_next_scheduled('devsroom_autodeploy_cleanup_event')) {
            wp_schedule_event(time(), 'daily', 'devsroom_autodeploy_cleanup_event');
        }
    }

    /**
     * Clear scheduled events.
     *
     * @return void
     */
    public function clear_schedule(): void
    {
        wp_clear_scheduled_hook('devsroom_autodeploy_polling_event');
        wp_clear_scheduled_hook('devsroom_autodeploy_cleanup_event');
    }

    /**
     * Poll repositories for updates.
     *
     * @return void
     */
    public function poll_repositories(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        // Get all active repositories with auto-deploy enabled.
        $repositories = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' AND auto_deploy = 1",
            ARRAY_A
        );

        if (empty($repositories)) {
            return;
        }

        $auth_manager = Auth_Manager::get_instance();
        $deployment_manager = Deployment_Manager::get_instance();

        foreach ($repositories as $repository) {
            // Get auth token.
            $token_data = $auth_manager->get_token($repository['auth_token_id']);

            if (! $token_data) {
                continue;
            }

            // Initialize GitHub API.
            $github_api = new GitHub_API($token_data['token']);

            // Get latest commit.
            $commit = $github_api->get_latest_commit(
                $repository['repo_owner'],
                $repository['repo_name'],
                $repository['branch']
            );

            if (! $commit) {
                continue;
            }

            $commit_hash = $commit['sha'];

            // Check if new commit.
            if ($commit_hash !== $repository['last_commit_hash']) {
                // Trigger deployment.
                $deployment_manager->deploy(
                    $repository['id'],
                    'polling',
                    0
                );
            }
        }
    }

    /**
     * Cleanup old data.
     *
     * @return void
     */
    public function cleanup(): void
    {
        $logger = Logger::get_instance();
        $backup_manager = Backup_Manager::get_instance();

        // Cleanup old logs.
        $logger->cleanup_old_logs(30);

        // Cleanup expired backups.
        $backup_manager->cleanup_expired_backups();
    }
}
