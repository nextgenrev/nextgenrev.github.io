<?php
/**
 * Autoloader for the AI Review Generator Pro plugin
 *
 * @package AIReviewGeneratorPro
 * @since   8.0.0
 */

namespace AIReviewGenerator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Loader Class
 *
 * Handles autoloading of classes and initializes all plugin components.
 *
 * @since 8.0.0
 */
class Loader {

    /**
     * Array of actions to register
     *
     * @var array
     */
    protected $actions = array();

    /**
     * Array of filters to register
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Constructor
     *
     * Sets up autoloading and loads dependencies.
     *
     * @since 8.0.0
     */
    public function __construct() {
        $this->setup_autoloader();
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Set up class autoloader
     *
     * @since 8.0.0
     * @return void
     */
    private function setup_autoloader() {
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * Autoload classes
     *
     * @since 8.0.0
     * @param string $class_name The class name to load.
     * @return void
     */
    public function autoload($class_name) {
        // Only autoload our namespace
        if (strpos($class_name, 'AIReviewGenerator\\') !== 0) {
            return;
        }

        // Remove namespace prefix
        $relative_class = str_replace('AIReviewGenerator\\', '', $class_name);
        
        // Helper to convert CamelCase to kebab-case
        $to_kebab_case = function($string) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $string));
        };
        
        // Check if it's an Admin class (e.g., Admin\Admin)
        if (strpos($relative_class, 'Admin\\') === 0) {
            $class_only = str_replace('Admin\\', '', $relative_class);
            $file = AIRG_PLUGIN_DIR . 'admin/class-' . $to_kebab_case($class_only) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // Check if it's an AI provider class
        if (strpos($relative_class, 'AI\\') === 0) {
            $class_only = str_replace('AI\\', '', $relative_class);
            $file = AIRG_PLUGIN_DIR . 'includes/ai/class-' . $to_kebab_case($class_only) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // Default: includes directory
        $file = AIRG_PLUGIN_DIR . 'includes/class-' . $to_kebab_case($relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    /**
     * Load required dependencies
     *
     * @since 8.0.0
     * @return void
     */
    private function load_dependencies() {
        // Core classes are autoloaded, no manual includes needed
    }

    /**
     * Define admin hooks
     *
     * @since 8.0.0
     * @return void
     */
    private function define_admin_hooks() {
        $admin = new Admin\Admin();
        
        $this->add_action('admin_menu', $admin, 'add_menu_pages');
        $this->add_action('admin_init', $admin, 'init_settings');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
        $this->add_action('add_meta_boxes', $admin, 'add_github_metabox');
    }

    /**
     * Add an action hook
     *
     * @since 8.0.0
     * @param string $hook          The hook name.
     * @param object $component     The component object.
     * @param string $callback      The callback method.
     * @param int    $priority      Hook priority.
     * @param int    $accepted_args Number of accepted arguments.
     * @return void
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
    }

    /**
     * Add a filter hook
     *
     * @since 8.0.0
     * @param string $hook          The hook name.
     * @param object $component     The component object.
     * @param string $callback      The callback method.
     * @param int    $priority      Hook priority.
     * @param int    $accepted_args Number of accepted arguments.
     * @return void
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
    }

    /**
     * Register all hooks with WordPress
     *
     * @since 8.0.0
     * @return void
     */
    public function run() {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
