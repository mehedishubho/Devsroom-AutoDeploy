<?php

/**
 * Deployment Manager class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy\Core;

/**
 * Class Deployment_Manager
 *
 * Orchestrates the deployment process.
 *
 * @since 1.0.0
 */
class Deployment_Manager
{

    /**
     * Singleton instance.
     *
     * @var Deployment_Manager|null
     */
    private static ?Deployment_Manager $instance = null;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Backup Manager instance.
     *
     * @var Backup_Manager
     */
    private Backup_Manager $backup_manager;

    /**
     * Security Scanner instance.
     *
     * @var Security_Scanner
     */
    private Security_Scanner $scanner;

    /**
     * Notification instance.
     *
     * @var Notification
     */
    private Notification $notification;

    /**
     * Get singleton instance.
     *
     * @return Deployment_Manager
     */
    public static function get_instance(): Deployment_Manager
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
        $this->logger         = Logger::get_instance();
        $this->backup_manager = Backup_Manager::get_instance();
        $this->scanner        = new Security_Scanner();
        $this->notification   = Notification::get_instance();
    }

    /**
     * Deploy a plugin from GitHub.
     *
     * @param int    $repository_id Repository ID.
     * @param string $trigger_type  Trigger type (webhook, polling, manual).
     * @param int    $user_id      User ID triggering the deployment.
     * @return array Deployment result.
     */
    public function deploy(int $repository_id, string $trigger_type = 'manual', int $user_id = 0): array
    {
        // Get repository configuration.
        $repository = $this->get_repository($repository_id);

        if (! $repository) {
            return array(
                'success' => false,
                'message' => 'Repository not found.',
            );
        }

        // Check if auto-deploy is enabled.
        if ('manual' !== $trigger_type && empty($repository['auto_deploy'])) {
            return array(
                'success' => false,
                'message' => 'Auto-deploy is disabled for this repository.',
            );
        }

        // Get auth token.
        $auth_manager = Auth_Manager::get_instance();
        $token_data   = $auth_manager->get_token($repository['auth_token_id']);

        if (! $token_data) {
            return array(
                'success' => false,
                'message' => 'Authentication token not found or invalid.',
            );
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
            return array(
                'success' => false,
                'message' => 'Failed to fetch latest commit from GitHub.',
            );
        }

        $commit_hash = $commit['sha'];

        // Check if already deployed.
        if ($commit_hash === $repository['last_commit_hash']) {
            return array(
                'success' => true,
                'message' => 'Already up to date.',
                'skipped' => true,
            );
        }

        // Create deployment record.
        $deployment_id = $this->create_deployment_record(
            $repository_id,
            $commit_hash,
            $commit['commit']['message'] ?? '',
            $commit['commit']['author']['name'] ?? '',
            $trigger_type,
            $user_id
        );

        if (! $deployment_id) {
            return array(
                'success' => false,
                'message' => 'Failed to create deployment record.',
            );
        }

        $this->logger->info($deployment_id, 'Deployment started', array(
            'trigger_type' => $trigger_type,
            'commit_hash'  => $commit_hash,
        ));

        // Get plugin path.
        $plugin_path = WP_PLUGIN_DIR . '/' . $repository['plugin_slug'];

        // Safety check: Prevent overwriting the AutoDeploy plugin itself.
        $autodeploy_plugin_path = WP_PLUGIN_DIR . '/' . DEVSOMM_AUTODEPLOY_PLUGIN_SLUG;
        if ($plugin_path === $autodeploy_plugin_path) {
            $this->logger->error($deployment_id ?? 0, 'Security violation: Attempt to overwrite AutoDeploy plugin', array(
                'plugin_slug' => $repository['plugin_slug'],
                'plugin_path' => $plugin_path,
            ));

            return array(
                'success' => false,
                'message' => 'Cannot deploy to the AutoDeploy plugin directory. Please specify a different plugin slug.',
            );
        }

        // Create backup if enabled.
        $backup_path = null;
        if ($repository['enable_backup'] && is_dir($plugin_path)) {
            $this->update_deployment_status($deployment_id, 'backing_up');
            $this->logger->info($deployment_id, 'Creating backup...');

            $backup = $this->backup_manager->create_backup(
                $plugin_path,
                $commit_hash,
                $repository_id
            );

            if ($backup) {
                $backup_path = $backup['backup_path'];
                $this->logger->info($deployment_id, 'Backup created', array(
                    'backup_path' => $backup_path,
                    'file_size'   => $backup['file_size'],
                ));
            } else {
                $this->logger->warning($deployment_id, 'Failed to create backup');
            }
        }

        // Download repository archive.
        $temp_dir = get_temp_dir() . 'devsoom-autodeploy-' . $repository_id . '-' . time();
        wp_mkdir_p($temp_dir);

        $archive_path = $temp_dir . '/archive.zip';

        $this->logger->info($deployment_id, 'Downloading repository archive...');

        if (! $github_api->download_archive(
            $repository['repo_owner'],
            $repository['repo_name'],
            $repository['branch'],
            $archive_path
        )) {
            $this->logger->error($deployment_id, 'Failed to download repository archive');
            $this->update_deployment_status($deployment_id, 'failed', 'Failed to download repository archive');
            $this->notification->send_deployment_failure(array(
                'plugin_name'  => $repository['plugin_slug'],
                'repo_owner'   => $repository['repo_owner'],
                'repo_name'    => $repository['repo_name'],
                'branch'       => $repository['branch'],
                'error_message' => 'Failed to download repository archive',
            ));

            // Cleanup.
            $this->cleanup_temp_dir($temp_dir);

            return array(
                'success' => false,
                'message' => 'Failed to download repository archive.',
                'deployment_id' => $deployment_id,
            );
        }

        // Extract archive.
        $this->logger->info($deployment_id, 'Extracting archive...');

        $zip = new \ZipArchive();
        if ($zip->open($archive_path) !== true) {
            $this->logger->error($deployment_id, 'Failed to open archive');
            $this->update_deployment_status($deployment_id, 'failed', 'Failed to open archive');
            $this->cleanup_temp_dir($temp_dir);

            return array(
                'success' => false,
                'message' => 'Failed to open archive.',
                'deployment_id' => $deployment_id,
            );
        }

        $zip->extractTo($temp_dir);
        $zip->close();

        // Find extracted directory.
        $extracted_dir = $this->find_extracted_directory($temp_dir);

        if (! $extracted_dir) {
            $this->logger->error($deployment_id, 'Failed to find extracted directory');
            $this->update_deployment_status($deployment_id, 'failed', 'Failed to find extracted directory');
            $this->cleanup_temp_dir($temp_dir);

            return array(
                'success' => false,
                'message' => 'Failed to find extracted directory.',
                'deployment_id' => $deployment_id,
            );
        }

        // Run security scan if enabled.
        $scan_result = null;
        if ('none' !== $repository['scan_level']) {
            $this->update_deployment_status($deployment_id, 'scanning');
            $this->logger->info($deployment_id, 'Running security scan...', array(
                'scan_level' => $repository['scan_level'],
            ));

            $scan_result = $this->scanner->scan_directory($extracted_dir, $repository['scan_level']);

            $this->logger->info($deployment_id, 'Security scan completed', array(
                'status' => $scan_result['status'],
                'issues' => $scan_result['errors'],
            ));

            // Store scan result.
            $this->update_deployment_scan_result($deployment_id, $scan_result);

            // Send security alert if issues found.
            if ('failed' === $scan_result['status']) {
                $this->notification->send_security_alert(array(
                    'plugin_name' => $repository['plugin_slug'],
                    'scan_result' => $scan_result,
                ));
            }
        }

        // Deploy files.
        $this->logger->info($deployment_id, 'Deploying files...');

        WP_Filesystem();
        global $wp_filesystem;

        // Remove existing plugin directory.
        if (is_dir($plugin_path)) {
            $wp_filesystem->delete($plugin_path, true);
        }

        // Create plugin directory.
        wp_mkdir_p($plugin_path);

        // Copy files.
        $this->copy_directory($extracted_dir, $plugin_path);

        // Update repository last commit hash.
        $this->update_repository_commit_hash($repository_id, $commit_hash);

        // Update deployment status.
        $duration = time() - strtotime($this->get_deployment_start_time($deployment_id));
        $this->update_deployment_status($deployment_id, 'success', null, $duration);

        $this->logger->info($deployment_id, 'Deployment completed successfully', array(
            'duration' => $duration,
            'backup_path' => $backup_path,
        ));

        // Send success notification.
        $this->notification->send_deployment_success(array(
            'plugin_name'    => $repository['plugin_slug'],
            'repo_owner'     => $repository['repo_owner'],
            'repo_name'      => $repository['repo_name'],
            'branch'         => $repository['branch'],
            'commit_hash'    => $commit_hash,
            'commit_message' => $commit['commit']['message'] ?? '',
            'commit_author'  => $commit['commit']['author']['name'] ?? '',
            'duration'       => $duration,
        ));

        // Cleanup.
        $this->cleanup_temp_dir($temp_dir);

        return array(
            'success'       => true,
            'message'       => 'Deployment completed successfully.',
            'deployment_id' => $deployment_id,
            'commit_hash'   => $commit_hash,
            'duration'      => $duration,
        );
    }

    /**
     * Get repository configuration.
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

    /**
     * Create a deployment record.
     *
     * @param int    $repository_id Repository ID.
     * @param string $commit_hash   Commit hash.
     * @param string $commit_message Commit message.
     * @param string $commit_author Commit author.
     * @param string $trigger_type  Trigger type.
     * @param int    $user_id      User ID.
     * @return int|false Deployment ID or false on failure.
     */
    private function create_deployment_record(int $repository_id, string $commit_hash, string $commit_message, string $commit_author, string $trigger_type, int $user_id): int|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_deployments';

        $result = $wpdb->insert(
            $table_name,
            array(
                'repository_id'   => $repository_id,
                'commit_hash'     => $commit_hash,
                'commit_message'  => $commit_message,
                'commit_author'   => $commit_author,
                'trigger_type'    => $trigger_type,
                'status'          => 'pending',
                'created_by'      => $user_id,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update deployment status.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $status       New status.
     * @param string $error_message Error message.
     * @param int    $duration     Duration in seconds.
     * @return bool True on success, false on failure.
     */
    private function update_deployment_status(int $deployment_id, string $status, string $error_message = null, int $duration = null): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_deployments';

        $data = array(
            'status' => $status,
        );

        if ('success' === $status || 'failed' === $status) {
            $data['completed_at'] = current_time('mysql');
        }

        if ($error_message) {
            $data['error_message'] = $error_message;
        }

        if ($duration) {
            $data['duration'] = $duration;
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $deployment_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * Update deployment scan result.
     *
     * @param int   $deployment_id Deployment ID.
     * @param array $scan_result   Scan result.
     * @return bool True on success, false on failure.
     */
    private function update_deployment_scan_result(int $deployment_id, array $scan_result): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_deployments';

        $result = $wpdb->update(
            $table_name,
            array(
                'scan_result' => wp_json_encode($scan_result),
            ),
            array('id' => $deployment_id),
            array('%s'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * Update repository commit hash.
     *
     * @param int    $repository_id Repository ID.
     * @param string $commit_hash   Commit hash.
     * @return bool True on success, false on failure.
     */
    private function update_repository_commit_hash(int $repository_id, string $commit_hash): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_repositories';

        $result = $wpdb->update(
            $table_name,
            array(
                'last_commit_hash' => $commit_hash,
                'last_deployed_at' => current_time('mysql'),
            ),
            array('id' => $repository_id),
            array('%s', '%s'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * Get deployment start time.
     *
     * @param int $deployment_id Deployment ID.
     * @return string Start time.
     */
    private function get_deployment_start_time(int $deployment_id): string
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_deployments';

        $deployment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT started_at FROM $table_name WHERE id = %d",
                $deployment_id
            )
        );

        return $deployment ?: current_time('mysql');
    }

    /**
     * Find extracted directory.
     *
     * @param string $temp_dir Temporary directory.
     * @return string|false Extracted directory path or false on failure.
     */
    private function find_extracted_directory(string $temp_dir): string|false
    {
        $files = scandir($temp_dir);

        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            $path = $temp_dir . '/' . $file;

            if (is_dir($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Copy directory contents.
     *
     * @param string $source Source directory.
     * @param string $dest   Destination directory.
     * @return void
     */
    private function copy_directory(string $source, string $dest): void
    {
        WP_Filesystem();
        global $wp_filesystem;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                wp_mkdir_p($target);
            } else {
                $wp_filesystem->copy($item->getPathname(), $target, true);
            }
        }
    }

    /**
     * Cleanup temporary directory.
     *
     * @param string $temp_dir Temporary directory.
     * @return void
     */
    private function cleanup_temp_dir(string $temp_dir): void
    {
        if (is_dir($temp_dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($temp_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            rmdir($temp_dir);
        }
    }
}
