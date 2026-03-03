<?php

/**
 * Admin class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Admin;

use Devsroom_AutoDeploy\Admin\Dashboard;
use Devsroom_AutoDeploy\Admin\Repository_Manager;
use Devsroom_AutoDeploy\Admin\Deployment_View;
use Devsroom_AutoDeploy\Admin\Settings;

/**
 * Class Admin
 *
 * Handles admin menu and pages.
 *
 * @since 1.0.0
 */
class Admin
{

    /**
     * Plugin name.
     *
     * @var string
     */
    private string $plugin_name;

    /**
     * Plugin version.
     *
     * @var string
     */
    private string $version;

    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin name.
     * @param string $version     Plugin version.
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Add plugin menu.
     *
     * @return void
     */
    public function add_plugin_menu(): void
    {
        // Get updates count.
        $repository_manager = new Repository_Manager();
        $updates_count = $repository_manager->get_updates_count();
        $menu_title = __('Repositories', 'devsroom-autodeploy');
        if ($updates_count > 0) {
            $menu_title .= ' <span class="update-plugins count-' . esc_attr($updates_count) . '"><span class="update-count">' . number_format_i18n($updates_count) . '</span></span>';
        }

        // Main menu.
        add_menu_page(
            'devsroom-autodeploy',
            __('Devsroom AutoDeploy', 'devsroom-autodeploy'),
            __('AutoDeploy', 'devsroom-autodeploy'),
            'manage_options',
            'devsroom-autodeploy',
            array($this, 'display_dashboard'),
            'dashicons-update-alt',
            30
        );

        // Dashboard submenu.
        add_submenu_page(
            'devsroom-autodeploy',
            __('Dashboard', 'devsroom-autodeploy'),
            __('Dashboard', 'devsroom-autodeploy'),
            'manage_options',
            'devsroom-autodeploy',
            array($this, 'display_dashboard')
        );

        // Repositories submenu.
        add_submenu_page(
            'devsroom-autodeploy',
            __('Repositories', 'devsroom-autodeploy'),
            $menu_title,
            'manage_options',
            'devsroom-autodeploy-repositories',
            array($this, 'display_repositories')
        );

        // Deployments submenu.
        add_submenu_page(
            'devsroom-autodeploy',
            __('Deployments', 'devsroom-autodeploy'),
            __('Deployments', 'devsroom-autodeploy'),
            'manage_options',
            'devsroom-autodeploy-deployments',
            array($this, 'display_deployments')
        );

        // Settings submenu.
        add_submenu_page(
            'devsroom-autodeploy',
            __('Settings', 'devsroom-autodeploy'),
            __('Settings', 'devsroom-autodeploy'),
            'manage_options',
            'devsroom-autodeploy-settings',
            array($this, 'display_settings')
        );
    }

    /**
     * Display dashboard page.
     *
     * @return void
     */
    public function display_dashboard(): void
    {
        static $dashboard = null;

        if ($dashboard === null) {
            $dashboard = new Dashboard();
        }

        $dashboard->render();
    }

    /**
     * Display repositories page.
     *
     * @return void
     */
    public function display_repositories(): void
    {
        $repository_manager = new Repository_Manager();
        $repository_manager->render();
    }

    /**
     * Display deployments page.
     *
     * @return void
     */
    public function display_deployments(): void
    {
        $deployment_view = new Deployment_View();
        $deployment_view->render();
    }

    /**
     * Display settings page.
     *
     * @return void
     */
    public function display_settings(): void
    {
        $settings = new Settings();
        $settings->render();
    }

    /**
     * Enqueue admin styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_styles(string $hook): void
    {
        // Only load on our plugin pages.
        if (strpos($hook, 'devsroom-autodeploy') === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            DEVSROOM_AUTODEPLOY_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts(string $hook): void
    {
        // Only load on our plugin pages.
        if (strpos($hook, 'devsroom-autodeploy') === false) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            DEVSROOM_AUTODEPLOY_URL . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script.
        wp_localize_script(
            $this->plugin_name,
            'devsroom_autodeploy',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('devsroom_autodeploy_nonce'),
                'strings'  => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'devsroom-autodeploy'),
                    'confirm_deploy' => __('Are you sure you want to deploy this plugin?', 'devsroom-autodeploy'),
                    'deploying'     => __('Deploying...', 'devsroom-autodeploy'),
                    'success'       => __('Success!', 'devsroom-autodeploy'),
                    'error'         => __('Error!', 'devsroom-autodeploy'),
                ),
            )
        );
    }
}
