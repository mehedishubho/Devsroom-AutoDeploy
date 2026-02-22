<?php

/**
 * Plugin deactivation handler.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy;

/**
 * Class Deactivator
 *
 * Handles plugin deactivation tasks.
 *
 * @since 1.0.0
 */
class Deactivator
{

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Clear scheduled events.
        wp_clear_scheduled_hook('devsoom_autodeploy_polling_event');
        wp_clear_scheduled_hook('devsoom_autodeploy_cleanup_event');

        // Flush rewrite rules.
        flush_rewrite_rules();
    }
}
