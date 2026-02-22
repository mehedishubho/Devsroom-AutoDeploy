<?php

/**
 * Uninstall plugin.
 *
 * @package Devsoom_AutoDeploy
 */

// If uninstall is not called from WordPress, exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin files.
require_once plugin_dir_path(__FILE__) . 'devsoom-autodeploy.php';

// Delete all plugin options.
$options = array(
    'devsoom_autodeploy_activated_at',
    'devsoom_autodeploy_polling_interval',
    'devsoom_autodeploy_backup_retention_days',
    'devsoom_autodeploy_enable_notifications',
    'devsoom_autodeploy_notification_email',
    'devsoom_autodeploy_max_backup_size_mb',
    'devsoom_autodeploy_scan_level_default',
    'devsoom_autodeploy_db_version',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete all user meta.
delete_metadata('user', 0, 'devsoom_autodeploy_oauth_state', '', true);
delete_metadata('user', 0, 'devsoom_autodeploy_oauth_verifier', '', true);

// Drop all database tables.
use Devsoom_AutoDeploy\Database\Schema;

Schema::drop_tables();

// Delete backup directory.
$backup_dir = WP_CONTENT_DIR . '/devsoom-autodeploy-backups';
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
