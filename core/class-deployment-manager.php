<?php

/**
 * Deployment Manager class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Core;

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
     * @param string $trigger_type Trigger type (webhook, polling, manual).
     * @param int    $user_id      User ID triggering the deployment.
     * @param bool   $force        Whether to force deployment when commit is unchanged.
     * @return array Deployment result.
     */
    public function deploy(int $repository_id, string $trigger_type = 'manual', int $user_id = 0, bool $force = false): array
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
        if (! $force && $commit_hash === $repository['last_commit_hash']) {
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

        // Acquire deployment lock to prevent concurrent deploys to the same plugin.
        $lock_result = $this->acquire_lock($repository_id, $deployment_id);

        if (! $lock_result || ! $lock_result['success']) {
            $error_message = $lock_result['message'] ?? 'Failed to acquire deployment lock';
            $this->logger->error($deployment_id, 'Deployment rejected: could not acquire lock', array(
                'repository_id' => $repository_id,
                'reason'        => $error_message,
            ));
            $this->update_deployment_status($deployment_id, 'failed', $error_message);

            return array(
                'success'       => false,
                'message'       => $error_message,
                'deployment_id' => $deployment_id,
            );
        }

        // Get plugin path.
        $plugin_path = WP_PLUGIN_DIR . '/' . $repository['plugin_slug'];

        // Safety check: Prevent overwriting the AutoDeploy plugin itself.
        $autodeploy_plugin_path = WP_PLUGIN_DIR . '/' . DEVSROOM_AUTODEPLOY_PLUGIN_SLUG;
        $plugin_path_real = realpath($plugin_path);
        $autodeploy_path_real = realpath($autodeploy_plugin_path);

        if ($plugin_path_real === $autodeploy_path_real) {
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
        // Use WP_CONTENT_DIR/upgrade/ so temp dir is on the same filesystem as
        // WP_PLUGIN_DIR — this guarantees rename() is atomic on POSIX (Pitfall 1).
        $temp_dir = WP_CONTENT_DIR . '/upgrade/devsroom-autodeploy-' . $repository_id . '-' . time();
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
            $this->release_lock($repository_id);

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
            $this->release_lock($repository_id);

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
            $this->release_lock($repository_id);

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

        // Deploy files using atomic swap.
        // Instead of deleting the live plugin then copying (dangerous window where a
        // failure leaves the site broken), we move the old dir aside and rename the
        // new dir into place. The old dir is preserved for rollback until verification passes.
        $this->logger->info($deployment_id, 'Deploying files via atomic swap...');

        $old_path      = $plugin_path . '.old';
        $plugin_exists = is_dir($plugin_path);

        // Step 1: Move current plugin aside (if exists).
        if ($plugin_exists) {
            if (! @rename($plugin_path, $old_path)) {
                // Windows fallback: copy then delete.
                if (! $this->copy_directory($plugin_path, $old_path)) {
                    $this->cleanup_temp_dir($temp_dir);
                    $this->release_lock($repository_id);
                    return array(
                        'success'       => false,
                        'message'       => 'Could not move current plugin aside for atomic swap.',
                        'deployment_id' => $deployment_id,
                    );
                }
                $this->cleanup_dir($plugin_path);
            }
        }

        // Step 2: Move new plugin into place.
        if (! @rename($extracted_dir, $plugin_path)) {
            // Windows fallback: copy then delete.
            if (! $this->copy_directory($extracted_dir, $plugin_path)) {
                // Restore old plugin.
                if ($plugin_exists) {
                    if (! @rename($old_path, $plugin_path)) {
                        $this->copy_directory($old_path, $plugin_path);
                        $this->cleanup_dir($old_path);
                    }
                }
                $this->cleanup_temp_dir($temp_dir);
                $this->release_lock($repository_id);
                return array(
                    'success'       => false,
                    'message'       => 'Could not deploy new plugin files.',
                    'deployment_id' => $deployment_id,
                );
            }
            $this->cleanup_dir($extracted_dir);
        }

        // Step 3: Clean up temp directory.
        $this->cleanup_temp_dir($temp_dir);

        // Post-deploy verification.
        $this->logger->info($deployment_id, 'Running post-deploy verification');
        $verification = $this->verify_deployment($plugin_path, $repository['plugin_slug']);
        $this->logger->info($deployment_id, 'Verification result', $verification);

        if (! $verification['success']) {
            // Verification failed — rollback to previous version.
            $this->logger->warning($deployment_id, 'Verification failed, rolling back', $verification);

            $failed_path = $plugin_path . '.failed';
            // Move broken deployment aside.
            @rename($plugin_path, $failed_path);

            // Restore previous version.
            if (is_dir($old_path)) {
                if (! @rename($old_path, $plugin_path)) {
                    // Windows fallback.
                    $this->copy_directory($old_path, $plugin_path);
                    $this->cleanup_dir($old_path);
                }
            }

            // Clean up failed deployment.
            if (is_dir($failed_path)) {
                $this->cleanup_dir($failed_path);
            }

            $this->update_deployment_status($deployment_id, 'failed', 'Deployment verification failed: ' . $verification['message']);
            $this->release_lock($repository_id);

            return array(
                'success'       => false,
                'message'       => 'Deployment verification failed: ' . $verification['message'],
                'deployment_id' => $deployment_id,
            );
        }

        // Verification passed — clean up .old directory.
        if (is_dir($old_path)) {
            $this->cleanup_dir($old_path);
        }

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

        // Release deployment lock.
        $this->release_lock($repository_id);

        return array(
            'success'       => true,
            'message'       => 'Deployment completed successfully.',
            'deployment_id' => $deployment_id,
            'commit_hash'   => $commit_hash,
            'duration'      => $duration,
        );
    }

    /**
     * Acquire deployment lock for a repository.
     *
     * Uses an atomic UPDATE with WHERE locked_at IS NULL to prevent race conditions.
     * Stale locks older than 10 minutes are automatically cleared.
     *
     * @param int $repository_id Repository ID.
     * @param int $deployment_id Deployment ID that will hold the lock.
     * @return array|false Lock result array or false on failure.
     */
    public function acquire_lock(int $repository_id, int $deployment_id): array|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        // Attempt atomic lock acquisition.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET locked_at = NOW(), locked_by = %d WHERE id = %d AND locked_at IS NULL",
                $deployment_id,
                $repository_id
            )
        );

        if (1 === $updated) {
            $this->logger->info($deployment_id, 'Deployment lock acquired', array(
                'repository_id' => $repository_id,
            ));

            return array(
                'success' => true,
                'message' => 'Lock acquired',
            );
        }

        // Lock exists — check if it's stale (older than 10 minutes).
        $stale_lock = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT locked_at, locked_by FROM {$table_name} WHERE id = %d AND locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
                $repository_id
            ),
            ARRAY_A
        );

        if ($stale_lock) {
            // Force-acquire stale lock.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_name} SET locked_at = NOW(), locked_by = %d WHERE id = %d",
                    $deployment_id,
                    $repository_id
                )
            );

            $this->logger->warning($deployment_id, 'Stale deployment lock cleared', array(
                'repository_id'    => $repository_id,
                'previous_lock_at' => $stale_lock['locked_at'],
                'previous_lock_by' => $stale_lock['locked_by'],
            ));

            return array(
                'success'           => true,
                'message'           => 'Lock acquired (stale lock cleared)',
                'stale_lock_cleared' => true,
            );
        }

        // Lock exists and is NOT stale — reject.
        $this->logger->warning($deployment_id, 'Deployment rejected: lock already held', array(
            'repository_id' => $repository_id,
        ));

        return array(
            'success' => false,
            'message' => 'Deployment already in progress for this plugin',
        );
    }

    /**
     * Release deployment lock for a repository.
     *
     * @param int $repository_id Repository ID.
     * @return void
     */
    public function release_lock(int $repository_id): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        $wpdb->update(
            $table_name,
            array(
                'locked_at' => null,
                'locked_by' => null,
            ),
            array('id' => $repository_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Check if a repository is currently locked.
     *
     * @param int $repository_id Repository ID.
     * @return array|false Lock info array or false if not locked.
     */
    public function is_locked(int $repository_id): array|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        $lock = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT locked_at, locked_by FROM {$table_name} WHERE id = %d AND locked_at IS NOT NULL",
                $repository_id
            ),
            ARRAY_A
        );

        return $lock ?: false;
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

        $table_name = $wpdb->prefix . 'devsroom_repositories';

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

        $table_name = $wpdb->prefix . 'devsroom_deployments';

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

        $table_name = $wpdb->prefix . 'devsroom_deployments';

        $data = array(
            'status' => $status,
        );
        $format = array('%s');

        if ('success' === $status || 'failed' === $status) {
            $data['completed_at'] = current_time('mysql');
            $format[] = '%s';
        }

        if ($error_message) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }

        if (null !== $duration) {
            $data['duration'] = $duration;
            $format[] = '%d';
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $deployment_id),
            $format,
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

        $table_name = $wpdb->prefix . 'devsroom_deployments';

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

        $table_name = $wpdb->prefix . 'devsroom_repositories';

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

        $table_name = $wpdb->prefix . 'devsroom_deployments';

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
     * Copy directory contents recursively.
     *
     * Used as a Windows fallback when rename() fails, and for moving plugin
     * directories between locations. Returns false if any file copy fails.
     *
     * @param string $source Source directory.
     * @param string $dest   Destination directory.
     * @return bool True on success, false if any file copy fails.
     */
    private function copy_directory(string $source, string $dest): bool
    {
        if (! is_dir($source)) {
            return false;
        }

        wp_mkdir_p($dest);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                wp_mkdir_p($target);
            } else {
                if (! copy($item->getPathname(), $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Verify a deployed plugin is loadable.
     *
     * Runs a series of checks to confirm the plugin directory is valid after an
     * atomic swap: file existence, PHP syntax, plugin header, readability, and
     * OPcache invalidation. Returns detailed results for logging and rollback decisions.
     *
     * @param string $plugin_path Path to the plugin directory.
     * @param string $plugin_slug Plugin slug (used to find the main file).
     * @return array Verification result with keys: success (bool), message (string), checks (array).
     */
    private function verify_deployment(string $plugin_path, string $plugin_slug): array
    {
        $checks = array();

        // Check 1: Main plugin file exists.
        $main_file = $plugin_path . '/' . $plugin_slug . '.php';
        if (! file_exists($main_file)) {
            $checks['file_exists'] = false;
            return array(
                'success' => false,
                'message' => 'Main plugin file not found',
                'checks'  => $checks,
            );
        }
        $checks['file_exists'] = true;

        // Check 2: PHP syntax check via token_get_all().
        try {
            $code = file_get_contents($main_file);
            if (false === $code) {
                $checks['syntax'] = false;
                return array(
                    'success' => false,
                    'message' => 'Could not read main plugin file',
                    'checks'  => $checks,
                );
            }
            token_get_all($code, TOKEN_PARSE);
            $checks['syntax'] = true;
        } catch (\Throwable $e) {
            $checks['syntax'] = false;
            return array(
                'success' => false,
                'message' => 'PHP syntax error: ' . $e->getMessage(),
                'checks'  => $checks,
            );
        }

        // Check 3: Plugin header exists.
        $header_content = file_get_contents($main_file, false, null, 0, 8192);
        if (! preg_match('/^[\s\/*#@]*Plugin Name:/mi', $header_content)) {
            $checks['header'] = false;
            return array(
                'success' => false,
                'message' => 'Plugin header not found in main file',
                'checks'  => $checks,
            );
        }
        $checks['header'] = true;

        // Check 4: Critical files readable.
        $critical_paths = array($main_file, $plugin_path . '/core');
        foreach ($critical_paths as $path) {
            if (file_exists($path) && ! is_readable($path)) {
                $checks['readable'] = false;
                return array(
                    'success' => false,
                    'message' => 'Critical path not readable: ' . basename($path),
                    'checks'  => $checks,
                );
            }
        }
        $checks['readable'] = true;

        // Check 5: Clear OPcache.
        if (function_exists('wp_opcache_invalidate')) {
            wp_opcache_invalidate($main_file, true);
        }

        return array(
            'success' => true,
            'message' => 'Verification passed',
            'checks'  => $checks,
        );
    }

    /**
     * Remove a directory and all its contents.
     *
     * Used for cleaning up .old and .failed directories after atomic swap.
     * Logs failures but does not abort — best-effort cleanup.
     *
     * @param string $path Directory path to remove.
     * @return void
     */
    private function cleanup_dir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                if (! @rmdir($file->getPathname())) {
                    error_log('Devsoom AutoDeploy: Failed to remove directory: ' . $file->getPathname());
                }
            } else {
                if (! @unlink($file->getPathname())) {
                    error_log('Devsoom AutoDeploy: Failed to remove file: ' . $file->getPathname());
                }
            }
        }

        @rmdir($path);
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
