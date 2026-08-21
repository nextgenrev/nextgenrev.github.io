<?php
/**
 * AI Review Generator Pro
 *
 * @package           AIReviewGeneratorPro
 * @author            Updulla
 * @copyright         2026 Updulla
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI Review Generator Pro
 * Plugin URI:        https://updulla.me/ai-review-generator-pro
 * Description:       Generate SEO-optimized affiliate product reviews with multiple AI providers and web scraping.
 * Version:           8.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Updulla
 * Author URI:        https://updulla.me
 * Text Domain:       ai-review-generator-pro
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version
 */
define('AIRG_VERSION', '8.0.0');

/**
 * Plugin directory path
 */
define('AIRG_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL
 */
define('AIRG_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename
 */
define('AIRG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Keep the theme's post-content wrapper white on published posts.
 *
 * @since 8.0.0
 * @return void
 */
function airg_enqueue_frontend_styles() {
    if (!is_singular('post')) {
        return;
    }

    $stylesheet_path = AIRG_PLUGIN_DIR . 'assets/css/frontend.css';
    $stylesheet_version = file_exists($stylesheet_path) ? (string) filemtime($stylesheet_path) : AIRG_VERSION;

    wp_enqueue_style(
        'airg-frontend',
        AIRG_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        $stylesheet_version
    );

    // CTA styles are emitted inline so host/CDN cache rules cannot leave
    // published reviews using an outdated affiliate-button design.
    $cta_stylesheet_path = AIRG_PLUGIN_DIR . 'assets/css/cta.css';
    if (is_readable($cta_stylesheet_path)) {
        $cta_styles = file_get_contents($cta_stylesheet_path);
        if ($cta_styles !== false && $cta_styles !== '') {
            wp_add_inline_style('airg-frontend', $cta_styles);
        }
    }
}
add_action('wp_enqueue_scripts', 'airg_enqueue_frontend_styles');

/**
 * Detect review content created by this plugin.
 *
 * New reviews include the airg-review-post wrapper. The additional markers
 * keep previously generated reviews compatible with the improved layout.
 *
 * @since 8.0.0
 * @param string $content Post content.
 * @return bool
 */
function airg_is_generated_review_content($content) {
    if (strpos($content, 'airg-review-post') !== false) {
        return true;
    }

    return strpos($content, 'Quick Verdict') !== false
        && strpos($content, 'Table of Contents') !== false
        && strpos($content, 'application/ld+json') !== false;
}

/**
 * Give legacy generated reviews the same stable styling hook as new reviews.
 *
 * @since 8.0.0
 * @param string $content Filtered post content.
 * @return string
 */
function airg_wrap_legacy_review_content($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    if (!airg_is_generated_review_content($content)
        || strpos($content, 'airg-review-post') !== false) {
        return $content;
    }

    return '<div class="airg-review-post airg-review-post--legacy">' . $content . '</div>';
}
add_filter('the_content', 'airg_wrap_legacy_review_content', 20);

/**
 * Add a page-level class for theme-compatible generated review adjustments.
 *
 * @since 8.0.0
 * @param array $classes Body classes.
 * @return array
 */
function airg_review_body_class($classes) {
    if (!is_singular('post')) {
        return $classes;
    }

    $content = get_post_field('post_content', get_queried_object_id());
    if (is_string($content) && airg_is_generated_review_content($content)) {
        $classes[] = 'airg-generated-review';
    }

    return $classes;
}
add_filter('body_class', 'airg_review_body_class');

// Load the autoloader
require_once AIRG_PLUGIN_DIR . 'includes/class-loader.php';

/**
 * Initialize the plugin
 *
 * @since 8.0.0
 * @return void
 */
function airg_init() {
    $loader = new \AIReviewGenerator\Loader();
    $loader->run();

    // Initialize Featured Image Generator
    new \AIReviewGenerator\FeaturedImageGenerator();
}
add_action('plugins_loaded', 'airg_init');

// Register AJAX handler directly (must be before 'init' hook)
add_action('wp_ajax_airg_generate', 'airg_ajax_handler');
add_action('wp_ajax_airg_start_generation', 'airg_ajax_start_generation_handler');
add_action('wp_ajax_airg_generation_step', 'airg_ajax_generation_step_handler');
add_action('wp_ajax_airg_push_github', 'airg_ajax_push_github_handler');

/**
 * AJAX handler wrapper
 *
 * @since 8.0.0
 * @return void
 */
function airg_ajax_handler() {
    airg_run_admin_ajax('ajax_generate');
}

/**
 * Start a staged review-generation job.
 *
 * @since 8.0.1
 * @return void
 */
function airg_ajax_start_generation_handler() {
    airg_run_admin_ajax('ajax_start_generation');
}

/**
 * Run one stage of a review-generation job.
 *
 * @since 8.0.1
 * @return void
 */
function airg_ajax_generation_step_handler() {
    airg_run_admin_ajax('ajax_generation_step');
}

/**
 * Push a post to GitHub Pages.
 *
 * @since 8.1.0
 * @return void
 */
function airg_ajax_push_github_handler() {
    airg_run_admin_ajax('ajax_push_github');
}

/**
 * Safely dispatch an AJAX method on the admin controller.
 *
 * @since 8.0.1
 * @param string $method Public admin method to call.
 * @return void
 */
function airg_run_admin_ajax($method) {
    // Enable error logging
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', true);
    }
    if (!defined('WP_DEBUG_DISPLAY')) {
        define('WP_DEBUG_DISPLAY', false);
    }
    @ini_set('display_errors', 0);

    try {
        // Load admin class and call handler
        if (!class_exists('\AIReviewGenerator\Admin\Admin')) {
            require_once AIRG_PLUGIN_DIR . 'admin/class-admin.php';
        }
        
        $admin = new \AIReviewGenerator\Admin\Admin();
        if (!is_callable(array($admin, $method))) {
            throw new \RuntimeException('Invalid AJAX handler.');
        }
        $admin->{$method}();
        
    } catch (\Throwable $e) {
        error_log('AIRG Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        wp_send_json_error('Server Error: ' . $e->getMessage());
    }
}

/**
 * Plugin activation hook
 *
 * @since 8.0.0
 * @return void
 */
function airg_activate() {
    require_once AIRG_PLUGIN_DIR . 'includes/class-activator.php';
    \AIReviewGenerator\Activator::activate();
}
register_activation_hook(__FILE__, 'airg_activate');

/**
 * Plugin deactivation hook
 *
 * @since 8.0.0
 * @return void
 */
function airg_deactivate() {
    require_once AIRG_PLUGIN_DIR . 'includes/class-deactivator.php';
    \AIReviewGenerator\Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'airg_deactivate');
