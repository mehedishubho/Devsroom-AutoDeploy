<?php

/**
 * Plugin activation handler.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy;

use Devsoom_AutoDeploy\Database\Schema;

/**
 * Class Activator
 *
 * Handles plugin activation tasks.
 *
 * @since 1.0.0
 */
class Activator
{

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Create database tables.
        Schema::create_tables();

        // Set default options.
        self::set_default_options();

        // Set activation timestamp.
        update_option('devsoom_autodeploy_activated_at', current_time('mysql'));

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function set_default_options(): void
    {
        $defaults = array(
            'polling_interval'     => 'hourly',
            'backup_retention_days' => 30,
            'enable_notifications' => true,
            'notification_email'    => get_option('admin_email'),
            'max_backup_size_mb'   => 100,
            'scan_level_default'   => 'basic',
        );

        foreach ($defaults as $key => $value) {
            if (false === get_option('devsoom_autodeploy_' . $key)) {
                add_option('devsoom_autodeploy_' . $key, $value);
            }
        }
    }
}
