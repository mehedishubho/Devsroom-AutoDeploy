<?php

/**
 * Uninstall plugin.
 *
 * @package Devsroom_AutoDeploy
 */

// If uninstall is not called from WordPress, exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin files.
require_once plugin_dir_path(__FILE__) . 'devsroom-autodeploy.php';

// Delete all plugin options.
$options = array(
    'devsroom_autodeploy_activated_at',
    'devsroom_autodeploy_polling_interval',
    'devsroom_autodeploy_backup_retention_days',
    'devsroom_autodeploy_enable_notifications',
    'devsroom_autodeploy_notification_email',
    'devsroom_autodeploy_max_backup_size_mb',
    'devsroom_autodeploy_scan_level_default',
    'devsroom_autodeploy_db_version',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete all user meta.
delete_metadata('user', 0, 'devsroom_autodeploy_oauth_state', '', true);
delete_metadata('user', 0, 'devsroom_autodeploy_oauth_verifier', '', true);

// Drop all database tables.
use Devsroom_AutoDeploy\Database\Schema;

Schema::drop_tables();

// Delete backup directory.
$backup_dir = WP_CONTENT_DIR . '/devsroom-autodeploy-backups';
if (is_dir($backup_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($backup_dir);
}
