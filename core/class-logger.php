<?php

/**
 * Logger class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Core;

/**
 * Class Logger
 *
 * Handles logging of deployment activities.
 *
 * @since 1.0.0
 */
class Logger
{

    /**
     * Singleton instance.
     *
     * @var Logger|null
     */
    private static ?Logger $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Logger
     */
    public static function get_instance(): Logger
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log an info message.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $message      Log message.
     * @param array  $context      Additional context.
     * @return int|false Log ID or false on failure.
     */
    public function info(int $deployment_id, string $message, array $context = array()): int|false
    {
        return $this->log($deployment_id, 'info', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $message      Log message.
     * @param array  $context      Additional context.
     * @return int|false Log ID or false on failure.
     */
    public function warning(int $deployment_id, string $message, array $context = array()): int|false
    {
        return $this->log($deployment_id, 'warning', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $message      Log message.
     * @param array  $context      Additional context.
     * @return int|false Log ID or false on failure.
     */
    public function error(int $deployment_id, string $message, array $context = array()): int|false
    {
        return $this->log($deployment_id, 'error', $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $message      Log message.
     * @param array  $context      Additional context.
     * @return int|false Log ID or false on failure.
     */
    public function debug(int $deployment_id, string $message, array $context = array()): int|false
    {
        return $this->log($deployment_id, 'debug', $message, $context);
    }

    /**
     * Log a message.
     *
     * @param int    $deployment_id Deployment ID.
     * @param string $level        Log level.
     * @param string $message      Log message.
     * @param array  $context      Additional context.
     * @return int|false Log ID or false on failure.
     */
    private function log(int $deployment_id, string $level, string $message, array $context = array()): int|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        $context_json = ! empty($context) ? wp_json_encode($context) : null;

        $result = $wpdb->insert(
            $table_name,
            array(
                'deployment_id' => $deployment_id,
                'level'        => $level,
                'message'      => $message,
                'context'      => $context_json,
            ),
            array('%d', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get logs for a deployment.
     *
     * @param int $deployment_id Deployment ID.
     * @param int $limit         Number of logs to retrieve.
     * @return array Array of logs.
     */
    public function get_deployment_logs(int $deployment_id, int $limit = 100): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE deployment_id = %d ORDER BY created_at ASC LIMIT %d",
                $deployment_id,
                $limit
            ),
            ARRAY_A
        );

        // Parse context JSON.
        foreach ($logs as &$log) {
            if ($log['context']) {
                $log['context'] = json_decode($log['context'], true);
            }
        }

        return $logs ?: array();
    }

    /**
     * Get logs by level.
     *
     * @param string $level Log level.
     * @param int    $limit Number of logs to retrieve.
     * @return array Array of logs.
     */
    public function get_logs_by_level(string $level, int $limit = 100): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE level = %s ORDER BY created_at DESC LIMIT %d",
                $level,
                $limit
            ),
            ARRAY_A
        );

        return $logs ?: array();
    }

    /**
     * Get recent logs.
     *
     * @param int $limit Number of logs to retrieve.
     * @return array Array of logs.
     */
    public function get_recent_logs(int $limit = 50): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $logs ?: array();
    }

    /**
     * Delete logs for a deployment.
     *
     * @param int $deployment_id Deployment ID.
     * @return int Number of rows deleted.
     */
    public function delete_deployment_logs(int $deployment_id): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        return (int) $wpdb->delete(
            $table_name,
            array('deployment_id' => $deployment_id),
            array('%d')
        );
    }

    /**
     * Clean up old logs.
     *
     * @param int $days Number of days to keep logs.
     * @return int Number of rows deleted.
     */
    public function cleanup_old_logs(int $days = 30): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $cutoff_date
            )
        );
    }
}
