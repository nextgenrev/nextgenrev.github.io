<?php
/**
 * Plugin Activator
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
 * Activator Class
 *
 * Handles plugin activation tasks.
 *
 * @since 8.0.0
 */
class Activator {

    /**
     * Run activation tasks
     *
     * @since 8.0.0
     * @return void
     */
    public static function activate() {
        // Set default options if not exists
        if (!get_option('ai_gen_options')) {
            update_option('ai_gen_options', array(
                'ai_provider'  => 'gemini',
                'gemini_key'   => '',
                'gemini_model' => 'gemini-3.7-flash',
            ));
        }

        // Create font directory if needed
        $font_dir = wp_upload_dir()['basedir'] . '/ai-review-fonts';
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
