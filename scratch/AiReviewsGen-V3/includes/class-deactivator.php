<?php
/**
 * Plugin Deactivator
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
 * Deactivator Class
 *
 * Handles plugin deactivation tasks.
 *
 * @since 8.0.0
 */
class Deactivator {

    /**
     * Run deactivation tasks
     *
     * @since 8.0.0
     * @return void
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
