<?php

/**
 * Plugin Name: Devsoom AutoDeploy
 * Plugin URI: https://devsoom.com/
 * Description: Automate WordPress plugin deployments from GitHub repositories. Connect your plugins to GitHub repos and deploy updates automatically via webhooks or scheduled checks.
 * Version: 1.0.0
 * Author: Devsoom
 * Author URI: https://devsoom.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: devsoom-autodeploy
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package Devsoom_AutoDeploy
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('DEVSOMM_AUTODEPLOY_VERSION', '1.0.0');

/**
 * Plugin directory path.
 */
define('DEVSOMM_AUTODEPLOY_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('DEVSOMM_AUTODEPLOY_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename.
 */
define('DEVSOMM_AUTODEPLOY_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin file.
 */
define('DEVSOMM_AUTODEPLOY_FILE', __FILE__);

/**
 * Autoload classes.
 */
require_once DEVSOMM_AUTODEPLOY_PATH . 'includes/class-loader.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once DEVSOMM_AUTODEPLOY_PATH . 'includes/class-main.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function devsoom_autodeploy_run()
{
    $plugin = new Devsoom_AutoDeploy\Main();
    $plugin->run();
}
devsoom_autodeploy_run();
