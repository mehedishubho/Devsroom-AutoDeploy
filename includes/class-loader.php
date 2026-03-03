<?php

/**
 * Autoloader for Devsroom AutoDeploy plugin.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy;

/**
 * Class Loader
 *
 * Handles autoloading of plugin classes and registering hooks.
 *
 * @since 1.0.0
 */
class Loader
{

    /**
     * The array of actions registered with WordPress.
     *
     * @var array
     */
    protected array $actions = array();

    /**
     * The array of filters registered with WordPress.
     *
     * @var array
     */
    protected array $filters = array();

    /**
     * Singleton instance.
     *
     * @var Loader|null
     */
    private static ?Loader $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Loader
     */
    public static function get_instance(): Loader
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
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * Autoload classes.
     *
     * @param string $class Class name.
     * @return void
     */
    public function autoload(string $class): void
    {
        // Check if the class is in our namespace.
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
            return;
        }

        // Remove namespace prefix.
        $class = str_replace(__NAMESPACE__ . '\\', '', $class);

        // Convert namespace separators to directory separators.
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

        // Convert to lowercase for file names.
        $class = strtolower($class);

        // Replace underscores with hyphens for file names.
        $class = str_replace('_', '-', $class);

        // Build file path.
        $file = DEVSROOM_AUTODEPLOY_PATH . $class . '.php';

        // Include file if it exists.
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress action.
     * @param object $component     A reference to the instance of the object on which the action is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default 1.
     * @return void
     */
    public function add_action(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress filter.
     * @param object $component     A reference to the instance of the object on which the filter is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default 1.
     * @return void
     */
    public function add_filter(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single collection.
     *
     * @param array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
     * @param string $hook          The name of the WordPress filter that is being registered.
     * @param object $component     A reference to the instance of the object on which the filter is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      The priority at which the function should be fired.
     * @param int    $accepted_args The number of arguments that should be passed to the $callback.
     * @return array The collection of actions and filters registered with WordPress.
     */
    private function add(array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args): array
    {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}

// Initialize autoloader.
Loader::get_instance();
