<?php
/**
 * GitHub Pusher Class
 *
 * Handles pushing generated posts to GitHub Pages as Jekyll-compatible
 * Markdown files with full SEO front matter.
 *
 * @package AIReviewGeneratorPro
 * @since   8.1.0
 */

namespace AIReviewGenerator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GitHubPusher {
    private $token;
    private $repo;
    private $author;

    public function __construct($options) {
        $this->token  = $options['github_token'] ?? '';
        $this->repo   = $options['github_repo'] ?? '';
        $this->author = $options['github_author'] ?? 'paul';
    }

    /**
     * Push a WordPress post to GitHub Pages _posts folder.
     *
     * @param int    $post_id      The WordPress post ID.
     * @param string $product_name The product name for the commit message.
     * @return true|\WP_Error
     */
    public function push($post_id, $product_name = '') {
        if (empty($this->token) || empty($this->repo)) {
            return new \WP_Error('github_missing_settings', __('GitHub settings are incomplete. Please add your Token and Repository in the plugin settings.', 'ai-review-generator-pro'));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', __('Post not found.', 'ai-review-generator-pro'));
        }

        // --- Gather post data ---
        $title_raw = $post->post_title;
        $title_escaped = str_replace('"', '\\"', $title_raw);

        $slug = $post->post_name;
        if (empty($slug)) {
            $slug = sanitize_title($title_raw);
        }

        $date_prefix = wp_date('Y-m-d', strtotime($post->post_date));
        $file_name = "{$date_prefix}-{$slug}.md";

        if (empty($product_name)) {
            $product_name = $title_raw;
        }

        // --- Featured Image ---
        $thumbnail_url = '';
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
        }

        // --- Extract SEO meta description ---
        $meta_description = $this->extract_meta_description($post);

        // --- Categories ---
        $categories = wp_get_post_categories($post_id, array('fields' => 'names'));
        if (empty($categories)) {
            $categories = array('Software');
        }
        $categories_yaml = '[' . implode(', ', $categories) . ']';

        // --- Tags ---
        $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
        $tags_yaml = '';
        if (!empty($tags)) {
            $tags_yaml = '[' . implode(', ', $tags) . ']';
        }

        // --- Content: replace custom fonts with Inter ---
        $content = $post->post_content;
        $content = str_replace('Söhne', 'Inter', $content);

        // --- Build front matter ---
        $front_matter  = "---\n";
        $front_matter .= "layout: post\n";
        $front_matter .= "title: \"{$title_escaped}\"\n";
        $front_matter .= "author: {$this->author}\n";
        $front_matter .= "categories: {$categories_yaml}\n";
        if (!empty($tags_yaml)) {
            $front_matter .= "tags: {$tags_yaml}\n";
        }
        if ($thumbnail_url) {
            $front_matter .= "image: {$thumbnail_url}\n";
        }
        if (!empty($meta_description)) {
            $desc_escaped = str_replace('"', '\\"', $meta_description);
            $front_matter .= "description: \"{$desc_escaped}\"\n";
        }
        $front_matter .= "---\n\n";

        $markdown = $front_matter . $content;

        // --- GitHub API: check if file already exists (for updates) ---
        $api_url = "https://api.github.com/repos/{$this->repo}/contents/_posts/{$file_name}";

        $existing_sha = $this->get_existing_file_sha($api_url);

        $body = array(
            'message' => "Add review: {$product_name}",
            'content' => base64_encode($markdown),
        );
        if ($existing_sha) {
            $body['message'] = "Update review: {$product_name}";
            $body['sha'] = $existing_sha;
        }

        $args = array(
            'method'  => 'PUT',
            'headers' => array(
                'Authorization' => 'token ' . $this->token,
                'Accept'        => 'application/vnd.github.v3+json',
                'User-Agent'    => 'AI-Review-Generator',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 45,
        );

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 201 && $status_code !== 200) {
            $resp_body = wp_remote_retrieve_body($response);
            $err_data = json_decode($resp_body, true);
            $err_msg = $err_data['message'] ?? 'Unknown error';
            return new \WP_Error('github_api_error', "GitHub API Error ({$status_code}): {$err_msg}");
        }

        // Save the SHA so future updates can reference it
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($resp_body['content']['sha'])) {
            update_post_meta($post_id, '_airg_github_sha', $resp_body['content']['sha']);
        }
        update_post_meta($post_id, '_airg_github_pushed', current_time('mysql'));

        return true;
    }

    /**
     * Check if the file already exists on GitHub and return its SHA.
     *
     * @param string $api_url The GitHub API file URL.
     * @return string|false The file SHA, or false if not found.
     */
    private function get_existing_file_sha($api_url) {
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'token ' . $this->token,
                'Accept'        => 'application/vnd.github.v3+json',
                'User-Agent'    => 'AI-Review-Generator',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['sha'] ?? false;
    }

    /**
     * Extract a meta description from the post content.
     *
     * Tries Rank Math, Yoast, then falls back to post excerpt or auto-generated.
     *
     * @param \WP_Post $post The post object.
     * @return string
     */
    private function extract_meta_description($post) {
        // Try Rank Math
        $desc = get_post_meta($post->ID, 'rank_math_description', true);
        if (!empty($desc)) {
            return wp_strip_all_tags($desc);
        }

        // Try Yoast
        $desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (!empty($desc)) {
            return wp_strip_all_tags($desc);
        }

        // Use excerpt
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // Auto-generate from content
        $plain = wp_strip_all_tags($post->post_content);
        $plain = preg_replace('/\s+/', ' ', $plain);
        if (strlen($plain) > 160) {
            $plain = substr($plain, 0, 157) . '...';
        }
        return $plain;
    }
}
