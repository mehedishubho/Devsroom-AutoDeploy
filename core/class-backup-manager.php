<?php

/**
 * Backup Manager class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Core;

/**
 * Class Backup_Manager
 *
 * Handles plugin backups before deployment.
 *
 * @since 1.0.0
 */
class Backup_Manager
{

    /**
     * Singleton instance.
     *
     * @var Backup_Manager|null
     */
    private static ?Backup_Manager $instance = null;

    /**
     * Backup directory.
     *
     * @var string
     */
    private string $backup_dir;

    /**
     * Maximum backup size in bytes.
     *
     * @var int
     */
    private int $max_backup_size;

    /**
     * Get singleton instance.
     *
     * @return Backup_Manager
     */
    public static function get_instance(): Backup_Manager
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
        $this->backup_dir      = WP_CONTENT_DIR . '/devsroom-autodeploy-backups';
        $this->max_backup_size = (int) get_option('devsroom_autodeploy_max_backup_size_mb', 100) * 1024 * 1024;

        // Create backup directory if it doesn't exist.
        $this->ensure_backup_directory();
    }

    /**
     * Ensure backup directory exists.
     *
     * @return bool True on success, false on failure.
     */
    private function ensure_backup_directory(): bool
    {
        if (! is_dir($this->backup_dir)) {
            return wp_mkdir_p($this->backup_dir);
        }

        // Create .htaccess to protect backups.
        $htaccess = $this->backup_dir . '/.htaccess';
        if (! file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        // Create index.php to prevent directory listing.
        $index = $this->backup_dir . '/index.php';
        if (! file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return true;
    }

    /**
     * Create a backup of a plugin.
     *
     * @param string $plugin_path    Plugin directory path.
     * @param string $commit_hash    Commit hash being backed up.
     * @param int    $repository_id  Repository ID.
     * @return array|false Backup info or false on failure.
     */
    public function create_backup(string $plugin_path, string $commit_hash, int $repository_id): array|false
    {
        // Validate plugin path.
        if (! is_dir($plugin_path)) {
            return false;
        }

        // Check plugin size.
        $plugin_size = $this->get_directory_size($plugin_path);
        if ($plugin_size > $this->max_backup_size) {
            return false;
        }

        // Generate backup filename.
        $plugin_slug = basename($plugin_path);
        $timestamp    = current_time('Y-m-d_H-i-s');
        $backup_name  = "{$plugin_slug}_{$timestamp}_{$commit_hash}.zip";
        $backup_path  = $this->backup_dir . '/' . $backup_name;

        // Initialize WordPress filesystem.
        WP_Filesystem();

        global $wp_filesystem;

        // Create ZIP archive.
        $zip = new \ZipArchive();

        if ($zip->open($backup_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // Add files to archive.
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen($plugin_path) + 1);

            if ($file->isFile()) {
                $zip->addFile($file_path, $relative_path);
            } elseif ($file->isDir()) {
                $zip->addEmptyDir($relative_path);
            }
        }

        $zip->close();

        // Verify backup was created.
        if (! file_exists($backup_path)) {
            return false;
        }

        // Save backup record to database.
        global $wpdb;
        $table_name = $wpdb->prefix . 'devsroom_backups';

        $wpdb->insert(
            $table_name,
            array(
                'repository_id' => $repository_id,
                'backup_path'  => $backup_path,
                'file_size'    => filesize($backup_path),
                'commit_hash'  => $commit_hash,
                'expires_at'   => date('Y-m-d H:i:s', strtotime('+' . get_option('devsroom_autodeploy_backup_retention_days', 30) . ' days')),
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );

        return array(
            'backup_path' => $backup_path,
            'file_size'   => filesize($backup_path),
            'commit_hash' => $commit_hash,
            'backup_id'   => $wpdb->insert_id,
        );
    }

    /**
     * Restore a backup.
     *
     * @param string $backup_path  Backup file path.
     * @param string $plugin_path  Plugin directory path.
     * @return bool True on success, false on failure.
     */
    public function restore_backup(string $backup_path, string $plugin_path): bool
    {
        if (! file_exists($backup_path)) {
            return false;
        }

        // Initialize WordPress filesystem.
        WP_Filesystem();

        global $wp_filesystem;

        // Remove existing plugin directory.
        if (is_dir($plugin_path)) {
            $wp_filesystem->delete($plugin_path, true);
        }

        // Create plugin directory.
        wp_mkdir_p($plugin_path);

        // Extract backup.
        $zip = new \ZipArchive();

        if ($zip->open($backup_path) !== true) {
            return false;
        }

        $zip->extractTo($plugin_path);
        $zip->close();

        return true;
    }

    /**
     * Delete a backup.
     *
     * @param int $backup_id Backup ID.
     * @return bool True on success, false on failure.
     */
    public function delete_backup(int $backup_id): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_backups';

        $backup = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $backup_id
            ),
            ARRAY_A
        );

        if (! $backup) {
            return false;
        }

        // Delete backup file.
        if (file_exists($backup['backup_path'])) {
            unlink($backup['backup_path']);
        }

        // Delete database record.
        $wpdb->delete(
            $table_name,
            array('id' => $backup_id),
            array('%d')
        );

        return true;
    }

    /**
     * Get backups for a repository.
     *
     * @param int $repository_id Repository ID.
     * @param int $limit         Number of backups to retrieve.
     * @return array Array of backups.
     */
    public function get_backups(int $repository_id, int $limit = 10): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_backups';

        $backups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE repository_id = %d ORDER BY created_at DESC LIMIT %d",
                $repository_id,
                $limit
            ),
            ARRAY_A
        );

        return $backups ?: array();
    }

    /**
     * Clean up expired backups.
     *
     * @return int Number of backups deleted.
     */
    public function cleanup_expired_backups(): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_backups';
        $now        = current_time('mysql');

        // Get expired backups.
        $expired = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, backup_path FROM $table_name WHERE expires_at < %s",
                $now
            ),
            ARRAY_A
        );

        $deleted = 0;

        foreach ($expired as $backup) {
            // Delete backup file.
            if (file_exists($backup['backup_path'])) {
                unlink($backup['backup_path']);
            }

            // Delete database record.
            $wpdb->delete(
                $table_name,
                array('id' => $backup['id']),
                array('%d')
            );

            $deleted++;
        }

        return $deleted;
    }

    /**
     * Get directory size.
     *
     * @param string $directory Directory path.
     * @return int Size in bytes.
     */
    private function get_directory_size(string $directory): int
    {
        $size = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Get total backup size.
     *
     * @return int Total size in bytes.
     */
    public function get_total_backup_size(): int
    {
        $size = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->backup_dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Get backup count.
     *
     * @return int Number of backups.
     */
    public function get_backup_count(): int
    {
        $count = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->backup_dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && 'zip' === strtolower($file->getExtension())) {
                $count++;
            }
        }

        return $count;
    }
}
