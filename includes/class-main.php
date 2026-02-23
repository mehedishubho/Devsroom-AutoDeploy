<?php

/**
 * Main plugin class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy;

use Devsoom_AutoDeploy\Admin\Admin;
use Devsoom_AutoDeploy\Core\Deployment_Manager;
use Devsoom_AutoDeploy\Core\Polling_Scheduler;
use Devsoom_AutoDeploy\Public\Webhook_Handler;

/**
 * Class Main
 *
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @since 1.0.0
 */
class Main
{

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @var Loader
     */
    protected Loader $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var string
     */
    protected string $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected string $version;

    /**
     * Initialize the plugin.
     */
    public function __construct()
    {
        $this->version     = DEVSOMM_AUTODEPLOY_VERSION;
        $this->plugin_name = 'devsoom-autodeploy';
        $this->loader      = Loader::get_instance();

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_core_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @return void
     */
    private function load_dependencies(): void
    {
        // Core classes.
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-auth-manager.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-github-api.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-deployment-manager.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-backup-manager.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-security-scanner.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-logger.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-notification.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'core/class-polling-scheduler.php';

        // Admin classes.
        require_once DEVSOMM_AUTODEPLOY_PATH . 'admin/class-admin.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'admin/class-dashboard.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'admin/class-repository-manager.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'admin/class-deployment-view.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'admin/class-settings.php';

        // Public classes.
        require_once DEVSOMM_AUTODEPLOY_PATH . 'public/class-webhook-handler.php';

        // Database classes.
        require_once DEVSOMM_AUTODEPLOY_PATH . 'database/class-schema.php';

        // Activation/Deactivation.
        require_once DEVSOMM_AUTODEPLOY_PATH . 'includes/class-activator.php';
        require_once DEVSOMM_AUTODEPLOY_PATH . 'includes/class-deactivator.php';

        // Register activation and deactivation hooks.
        register_activation_hook(DEVSOMM_AUTODEPLOY_FILE, array('Devsoom_AutoDeploy\Activator', 'activate'));
        register_deactivation_hook(DEVSOMM_AUTODEPLOY_FILE, array('Devsoom_AutoDeploy\Deactivator', 'deactivate'));
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @return void
     */
    private function set_locale(): void
    {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @return void
     */
    public function load_plugin_textdomain(): void
    {
        load_plugin_textdomain(
            'devsoom-autodeploy',
            false,
            dirname(DEVSOMM_AUTODEPLOY_BASENAME) . '/languages/'
        );
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @return void
     */
    private function define_admin_hooks(): void
    {
        $plugin_admin = new Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @return void
     */
    private function define_public_hooks(): void
    {
        $webhook_handler = new Webhook_Handler();

        // Register webhook endpoint.
        add_action('rest_api_init', array($webhook_handler, 'register_routes'));
    }

    /**
     * Register all of the hooks related to core functionality.
     *
     * @return void
     */
    private function define_core_hooks(): void
    {
        // Core hooks are registered in load_dependencies via activation/deactivation hooks.
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @return void
     */
    public function run(): void
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return string The plugin name.
     */
    public function get_plugin_name(): string
    {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version(): string
    {
        return $this->version;
    }
}
