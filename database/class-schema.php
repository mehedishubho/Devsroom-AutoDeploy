<?php

/**
 * Database schema handler.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Database;

/**
 * Class Schema
 *
 * Handles database table creation and management.
 *
 * @since 1.0.0
 */
class Schema
{

    /**
     * Database charset.
     *
     * @var string
     */
    private string $charset;

    /**
     * Database collate.
     *
     * @var string
     */
    private string $collate;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->charset  = $wpdb->get_charset_collate();
        $this->collate = $wpdb->collate;
    }

    /**
     * Create all database tables.
     *
     * @return void
     */
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $schema = new self();

        // Create repositories table.
        $schema->create_repositories_table();

        // Create auth tokens table.
        $schema->create_auth_tokens_table();

        // Create deployments table.
        $schema->create_deployments_table();

        // Create logs table.
        $schema->create_logs_table();

        // Create backups table.
        $schema->create_backups_table();

        // Set database version.
        update_option('devsroom_autodeploy_db_version', DEVSROOM_AUTODEPLOY_VERSION);
    }

    /**
     * Create repositories table.
     *
     * @return void
     */
    private function create_repositories_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			plugin_slug varchar(255) NOT NULL,
			repo_owner varchar(255) NOT NULL,
			repo_name varchar(255) NOT NULL,
			branch varchar(100) DEFAULT 'main',
			auth_method enum('pat', 'oauth') DEFAULT 'pat',
			auth_token_id bigint(20) unsigned DEFAULT NULL,
			auto_deploy tinyint(1) DEFAULT 1,
			webhook_secret varchar(100) DEFAULT NULL,
			enable_backup tinyint(1) DEFAULT 1,
			scan_level enum('none', 'basic', 'advanced') DEFAULT 'basic',
			last_commit_hash varchar(100) DEFAULT NULL,
			last_deployed_at datetime DEFAULT NULL,
			status enum('active', 'paused', 'error') DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY plugin_slug (plugin_slug),
			KEY auth_token_id (auth_token_id),
			KEY status (status),
			KEY last_deployed_at (last_deployed_at)
		) $this->charset;";

        dbDelta($sql);
    }

    /**
     * Create auth tokens table.
     *
     * @return void
     */
    private function create_auth_tokens_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_auth_tokens';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			auth_method enum('pat', 'oauth') NOT NULL,
			token text NOT NULL,
			token_name varchar(255) DEFAULT NULL,
			refresh_token text DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			scope text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY auth_method (auth_method),
			KEY is_active (is_active)
		) $this->charset;";

        dbDelta($sql);
    }

    /**
     * Create deployments table.
     *
     * @return void
     */
    private function create_deployments_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_deployments';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			repository_id bigint(20) unsigned NOT NULL,
			commit_hash varchar(100) NOT NULL,
			commit_message text DEFAULT NULL,
			commit_author varchar(255) DEFAULT NULL,
			trigger_type enum('webhook', 'polling', 'manual') NOT NULL,
			status enum('pending', 'success', 'failed', 'scanning', 'backing_up') DEFAULT 'pending',
			backup_path varchar(500) DEFAULT NULL,
			scan_result text DEFAULT NULL,
			error_message text DEFAULT NULL,
			started_at datetime DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			duration int(11) DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY repository_id (repository_id),
			KEY status (status),
			KEY trigger_type (trigger_type),
			KEY created_at (created_at)
		) $this->charset;";

        dbDelta($sql);
    }

    /**
     * Create logs table.
     *
     * @return void
     */
    private function create_logs_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_logs';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			deployment_id bigint(20) unsigned DEFAULT NULL,
			level enum('info', 'warning', 'error', 'debug') DEFAULT 'info',
			message text NOT NULL,
			context text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY deployment_id (deployment_id),
			KEY level (level),
			KEY created_at (created_at)
		) $this->charset;";

        dbDelta($sql);
    }

    /**
     * Create backups table.
     *
     * @return void
     */
    private function create_backups_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_backups';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			repository_id bigint(20) unsigned NOT NULL,
			deployment_id bigint(20) unsigned DEFAULT NULL,
			backup_path varchar(500) NOT NULL,
			file_size bigint(20) DEFAULT NULL,
			commit_hash varchar(100) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY repository_id (repository_id),
			KEY deployment_id (deployment_id),
		 KEY expires_at (expires_at)
		) $this->charset;";

        dbDelta($sql);
    }

    /**
     * Drop all plugin tables.
     *
     * @return void
     */
    public static function drop_tables(): void
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'devsroom_repositories',
            $wpdb->prefix . 'devsroom_auth_tokens',
            $wpdb->prefix . 'devsroom_deployments',
            $wpdb->prefix . 'devsroom_logs',
            $wpdb->prefix . 'devsroom_backups',
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('devsroom_autodeploy_db_version');
    }
}
