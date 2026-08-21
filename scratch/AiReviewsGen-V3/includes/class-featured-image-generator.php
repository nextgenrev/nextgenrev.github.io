<?php
/**
 * Featured Image Generator Class
 *
 * Generates featured images by compositing uploaded tool logos
 * onto the template image (inside the speech bubble graphic).
 *
 * @package AIReviewGeneratorPro
 * @since   8.1.0
 */

namespace AIReviewGenerator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Image Generator Class
 *
 * Uses GD library to overlay the uploaded tool logo onto the
 * template image's empty speech bubble area.
 *
 * @since 8.1.0
 */
class FeaturedImageGenerator {

    /**
     * Option name for plugin settings
     *
     * @var string
     */
    private $option_name = 'ai_gen_options';

    /**
     * Shared logo area in the 2752x1536 templates: the white rectangle on
     * image1.png and the black rectangle on image2.png. The inner content box
     * spans (352,235) to (1703,528) on both images.
     */
    private $bubble_x = 119;
    private $bubble_y = 167;
    private $bubble_width = 1818;
    private $bubble_height = 429;
    private $padding_top = 68;
    private $padding_right = 234;
    private $padding_bottom = 68;
    private $padding_left = 233;

    /**
     * Constructor - register hooks
     *
     * @since 8.1.0
     */
    public function __construct() {
        // Admin hooks
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));

        // AJAX hooks
        add_action('wp_ajax_airg_generate_featured_image', array($this, 'ajax_generate_featured_image'));
        add_action('wp_ajax_airg_upload_logo', array($this, 'ajax_upload_logo'));

        // Enqueue scripts for meta box
        add_action('admin_enqueue_scripts', array($this, 'enqueue_meta_box_scripts'));
    }

    /**
     * Enqueue scripts for the meta box on post edit screens
     *
     * @since 8.1.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_meta_box_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'airg-featured-image',
            AIRG_PLUGIN_URL . 'assets/js/featured-image.js',
            array('jquery'),
            (string) filemtime(AIRG_PLUGIN_DIR . 'assets/js/featured-image.js'),
            true
        );

        wp_localize_script('airg-featured-image', 'airg_featured_image', array(
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('airg_featured_image_nonce'),
            'upload_nonce' => wp_create_nonce('airg_upload_logo_nonce'),
        ));
    }

    /**
     * Add meta box to post edit screen
     *
     * @since 8.1.0
     * @return void
     */
    public function add_meta_box() {
        add_meta_box(
            'airg_featured_image_meta_box',
            __('AI Review - Featured Image Generator', 'ai-review-generator-pro'),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box content
     *
     * @since 8.1.0
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function render_meta_box($post) {
        wp_nonce_field('airg_featured_image_meta', 'airg_featured_image_nonce_field');

        $logo_url = get_post_meta($post->ID, '_review_tool_logo', true);
        $generated_url = get_post_meta($post->ID, '_review_generated_featured_image', true);
        ?>
        <div class="airg-meta-box-content" style="padding: 8px 0;">
            <p style="margin: 0 0 10px; font-weight: 600;">
                <label for="airg-tool-logo"><?php esc_html_e('Tool Logo', 'ai-review-generator-pro'); ?></label>
            </p>
            <div id="airg-logo-preview" style="margin-bottom: 10px;">
                <?php if (!empty($logo_url)): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; padding: 4px;">
                <?php endif; ?>
            </div>
            <input type="hidden" id="airg-tool-logo" name="airg_tool_logo" value="<?php echo esc_attr($logo_url); ?>">
            <input type="file" id="airg-tool-logo-file" accept="image/*" style="display: none;">
            <button type="button" class="button" id="airg-upload-logo-btn" style="width: 100%; margin-bottom: 8px;">
                <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 4px;"></span>
                <span class="airg-upload-logo-label"><?php echo empty($logo_url) ? esc_html__('Upload Logo', 'ai-review-generator-pro') : esc_html__('Change Logo', 'ai-review-generator-pro'); ?></span>
            </button>
            <p id="airg-logo-upload-status" class="description" style="display: none; margin: 0 0 8px;"></p>
            <?php if (!empty($logo_url)): ?>
                <button type="button" class="button" id="airg-remove-logo-btn" style="width: 100%; margin-bottom: 12px; color: #a00;">
                    <span class="dashicons dashicons-no" style="vertical-align: middle; margin-right: 4px;"></span>
                    <?php esc_html_e('Remove Logo', 'ai-review-generator-pro'); ?>
                </button>
            <?php endif; ?>

            <hr style="margin: 12px 0;">

            <p style="margin: 0 0 6px; font-weight: 600;">
                <?php esc_html_e('Logo Color', 'ai-review-generator-pro'); ?>
            </p>
            <p style="margin: 0 0 12px;">
                <label style="margin-right: 14px;">
                    <input type="radio" name="airg_logo_color" value="black" checked>
                    <?php esc_html_e('Black', 'ai-review-generator-pro'); ?>
                </label>
                <label>
                    <input type="radio" name="airg_logo_color" value="white">
                    <?php esc_html_e('White', 'ai-review-generator-pro'); ?>
                </label>
            </p>

            <button type="button" class="button button-primary" id="airg-generate-featured-image-btn" style="width: 100%;" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
                <?php esc_html_e('Generate Featured Image', 'ai-review-generator-pro'); ?>
            </button>
            <p class="description" style="margin-top: 6px; font-size: 11px;">
                <?php esc_html_e('Places the uploaded logo inside the template bubble graphic and sets it as the featured image.', 'ai-review-generator-pro'); ?>
            </p>

            <div id="airg-image-generation-status" style="margin-top: 10px; display: none;">
                <span class="spinner" style="visibility: visible; float: none; margin: 0 8px 0 0;"></span>
                <span><?php esc_html_e('Generating image...', 'ai-review-generator-pro'); ?></span>
            </div>

            <?php if (!empty($generated_url)): ?>
            <div style="margin-top: 10px; padding: 8px; background: #f0f6fc; border-radius: 4px; font-size: 11px;">
                <strong><?php esc_html_e('Last generated:', 'ai-review-generator-pro'); ?></strong><br>
                <a href="<?php echo esc_url($generated_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View image', 'ai-review-generator-pro'); ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save meta box data when post is saved
     *
     * @since 8.1.0
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_meta_box_data($post_id) {
        // Verify nonce
        if (!isset($_POST['airg_featured_image_nonce_field']) ||
            !wp_verify_nonce($_POST['airg_featured_image_nonce_field'], 'airg_featured_image_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save logo URL
        if (isset($_POST['airg_tool_logo'])) {
            $logo_url = esc_url_raw($_POST['airg_tool_logo']);
            update_post_meta($post_id, '_review_tool_logo', $logo_url);
        }
    }

    /**
     * Upload a logo from the native file picker to the WordPress Media Library.
     *
     * @since 8.1.0
     * @return void
     */
    public function ajax_upload_logo() {
        if (!check_ajax_referer('airg_upload_logo_nonce', 'nonce', false)) {
            wp_send_json_error(__('The upload session expired. Reload the page and try again.', 'ai-review-generator-pro'), 403);
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to upload files.', 'ai-review-generator-pro'), 403);
        }

        if (empty($_FILES['logo']) || !is_array($_FILES['logo'])) {
            wp_send_json_error(__('No logo file was received.', 'ai-review-generator-pro'), 400);
        }

        $file = $_FILES['logo'];
        if (!empty($file['error'])) {
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE   => __('The logo exceeds the server upload limit.', 'ai-review-generator-pro'),
                UPLOAD_ERR_FORM_SIZE  => __('The logo exceeds the allowed upload size.', 'ai-review-generator-pro'),
                UPLOAD_ERR_PARTIAL    => __('The logo was only partially uploaded. Please try again.', 'ai-review-generator-pro'),
                UPLOAD_ERR_NO_FILE    => __('No logo file was selected.', 'ai-review-generator-pro'),
                UPLOAD_ERR_NO_TMP_DIR => __('The server is missing its temporary upload folder.', 'ai-review-generator-pro'),
                UPLOAD_ERR_CANT_WRITE => __('The server could not write the uploaded logo.', 'ai-review-generator-pro'),
                UPLOAD_ERR_EXTENSION  => __('A server extension stopped the logo upload.', 'ai-review-generator-pro'),
            );
            $message = isset($upload_errors[$file['error']])
                ? $upload_errors[$file['error']]
                : __('The logo upload could not be completed.', 'ai-review-generator-pro');
            wp_send_json_error($message, 400);
        }

        $file_size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($file_size <= 0) {
            wp_send_json_error(__('The selected logo is empty.', 'ai-review-generator-pro'), 400);
        }

        $max_upload_size = wp_max_upload_size();
        if ($max_upload_size > 0 && $file_size > $max_upload_size) {
            wp_send_json_error(
                sprintf(
                    /* translators: %s: maximum upload size. */
                    __('The logo is too large. Maximum upload size: %s.', 'ai-review-generator-pro'),
                    size_format($max_upload_size)
                ),
                413
            );
        }

        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $filename);
        if (empty($filetype['type']) || strpos($filetype['type'], 'image/') !== 0) {
            wp_send_json_error(__('Please select a supported image file.', 'ai-review-generator-pro'), 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('logo', 0, array(), array('test_form' => false));
        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message(), 400);
        }

        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            wp_send_json_error(__('The logo was uploaded, but its URL could not be resolved.', 'ai-review-generator-pro'), 500);
        }

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'url'           => esc_url_raw($image_url),
        ));
    }

    /**
     * Generate featured image for a post
     *
     * Composites the uploaded tool logo onto the template image's
     * speech bubble area and sets it as the post's featured image.
     *
     * @since 8.1.0
     * @param int    $post_id      Post ID.
     * @param string $product_name Product/tool name.
     * @param string $logo_url     Optional logo URL.
     * @param bool   $force        Force generation even if auto-generate is off.
     * @param string $logo_color   Logo color: 'black' uses image1.png, 'white' uses image2.png.
     * @return int|null Attachment ID or null on failure.
     */
    public function generate($post_id, $product_name, $logo_url = '', $force = false, $logo_color = 'black') {
        $options = get_option($this->option_name, array());

        // Check if auto-generate is disabled (skip if forced or manually triggered)
        $auto_generate = $options['auto_generate_image'] ?? 'yes';
        if ($auto_generate !== 'yes' && !$force) {
            error_log('AI Review Generator - Featured image: Auto-generate is disabled');
            return null;
        }

        // Get logo URL from post meta if not provided
        if (empty($logo_url)) {
            $logo_url = get_post_meta($post_id, '_review_tool_logo', true);
        }

        if (empty($logo_url)) {
            error_log('AI Review Generator - Featured image: No tool logo uploaded');
            return null;
        }

        // Generate the composite image
        $image_data = $this->composite_logo_on_template($logo_url, $logo_color);

        if (empty($image_data)) {
            error_log('AI Review Generator - Featured image: Failed to composite image');
            return null;
        }

        // Upload to WordPress Media Library
        $attachment_id = $this->upload_to_media_library($image_data, $product_name, $post_id);

        if (!$attachment_id) {
            return null;
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Store the generated image URL in post meta
        $image_url = wp_get_attachment_url($attachment_id);
        if ($image_url) {
            update_post_meta($post_id, '_review_generated_featured_image', $image_url);
        }

        return $attachment_id;
    }

    /**
     * Composite the tool logo onto the template image's speech bubble
     *
     * Loads the template image from the plugin's assets folder,
     * downloads the logo, and places it centered inside the bubble area.
     *
     * @since 8.1.0
     * @param string $logo_url   URL of the uploaded tool logo.
     * @param string $logo_color Logo color: 'black' uses image1.png, 'white' uses image2.png.
     * @return string|null PNG image data or null on failure.
     */
    private function composite_logo_on_template($logo_url, $logo_color = 'black') {
        if (!function_exists('imagecreatetruecolor')) {
            error_log('AI Review Generator - GD library not available');
            return null;
        }

        // Load template image from plugin assets.
        // White logos sit on image2.png; black logos sit on image1.png.
        $template_file = ($logo_color === 'white') ? 'assets/image2.png' : 'assets/image1.png';
        $template_path = AIRG_PLUGIN_DIR . $template_file;
        if (!file_exists($template_path)) {
            error_log('AI Review Generator - Template image not found: ' . $template_path);
            return null;
        }

        $template = @imagecreatefrompng($template_path);
        if (!$template) {
            // Try loading as generic image
            $template_data = file_get_contents($template_path);
            $template = @imagecreatefromstring($template_data);
        }

        if (!$template) {
            error_log('AI Review Generator - Could not load template image');
            return null;
        }

        // Download the logo image
        $logo_data = $this->download_image($logo_url);
        if (empty($logo_data)) {
            imagedestroy($template);
            error_log('AI Review Generator - Could not download logo image');
            return null;
        }

        $logo = @imagecreatefromstring($logo_data);
        if (!$logo) {
            imagedestroy($template);
            error_log('AI Review Generator - Could not parse logo image data');
            return null;
        }

        // Enable alpha blending on template
        imagealphablending($template, true);
        imagesavealpha($template, true);

        // Get logo dimensions
        $logo_w = imagesx($logo);
        $logo_h = imagesy($logo);

        // Calculate the exact content box inside the black rounded rectangle.
        $content_x = $this->bubble_x + $this->padding_left;
        $content_y = $this->bubble_y + $this->padding_top;
        $max_w = $this->bubble_width - $this->padding_left - $this->padding_right;
        $max_h = $this->bubble_height - $this->padding_top - $this->padding_bottom;

        // Scale logo to fit within the bubble while maintaining aspect ratio
        $scale = min($max_w / $logo_w, $max_h / $logo_h);
        $new_w = (int)($logo_w * $scale);
        $new_h = (int)($logo_h * $scale);

        // Center differently shaped logos within the exact 1351x293 content box.
        $dest_x = $content_x + (int)(($max_w - $new_w) / 2);
        $dest_y = $content_y + (int)(($max_h - $new_h) / 2);

        // Handle logo transparency
        imagealphablending($logo, true);
        imagesavealpha($logo, true);

        // Create a resampled version of the logo with high quality
        $resized_logo = imagecreatetruecolor($new_w, $new_h);
        imagealphablending($resized_logo, false);
        imagesavealpha($resized_logo, true);
        $transparent = imagecolorallocatealpha($resized_logo, 0, 0, 0, 127);
        imagefill($resized_logo, 0, 0, $transparent);
        imagealphablending($resized_logo, true);

        imagecopyresampled(
            $resized_logo,
            $logo,
            0, 0,
            0, 0,
            $new_w, $new_h,
            $logo_w, $logo_h
        );

        // Composite the clean logo directly onto the template. No shadow,
        // outline, or additional effect is applied.
        imagealphablending($template, true);
        imagecopy(
            $template,
            $resized_logo,
            $dest_x, $dest_y,
            0, 0,
            $new_w, $new_h
        );

        imagedestroy($resized_logo);

        // Output as PNG
        ob_start();
        imagepng($template, null, 7);
        $result = ob_get_clean();

        imagedestroy($template);
        imagedestroy($logo);

        return $result ?: null;
    }

    /**
     * Download an image from URL
     *
     * @since 8.1.0
     * @param string $url Image URL.
     * @return string|null Binary image data or null.
     */
    private function download_image($url) {
        if (empty($url)) {
            return null;
        }

        // If it's a local WordPress URL, try to get it from the filesystem
        $upload_dir = wp_upload_dir();
        if (strpos($url, $upload_dir['baseurl']) === 0) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            if (file_exists($file_path)) {
                return file_get_contents($file_path);
            }
        }

        // Download from remote URL
        $response = wp_remote_get($url, array(
            'timeout'   => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return (strlen($body) > 100) ? $body : null;
    }

    /**
     * Upload image data to WordPress Media Library
     *
     * @since 8.1.0
     * @param string $image_data Binary image data.
     * @param string $product    Product name for filename/SEO.
     * @param int    $post_id    Post ID to attach image to.
     * @return int|null Attachment ID or null on failure.
     */
    private function upload_to_media_library($image_data, $product, $post_id) {
        if (empty($image_data)) {
            return null;
        }

        $filename = sanitize_file_name($product . '-review-' . time() . '.png');
        $upload = wp_upload_bits($filename, null, $image_data);

        if ($upload['error']) {
            error_log('AI Review Generator - Featured image upload error: ' . $upload['error']);
            return null;
        }

        // SEO-optimized metadata
        $focus_keyword = $product . ' Review';
        $alt_text      = $focus_keyword . ' - Featured Image ' . date('Y');
        $caption       = $focus_keyword . ' featured image';
        $description   = 'Featured image for ' . $focus_keyword . '.';

        // Create attachment
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => 'image/png',
            'post_title'     => $focus_keyword . ' Featured Image ' . date('Y'),
            'post_content'   => $description,
            'post_excerpt'   => $caption,
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ), $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('AI Review Generator - Featured image attachment creation failed');
            return null;
        }

        // Set ALT text for SEO
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

        return $attachment_id;
    }

    /**
     * AJAX handler: Generate featured image manually
     *
     * @since 8.1.0
     * @return void
     */
    public function ajax_generate_featured_image() {
        check_ajax_referer('airg_featured_image_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'ai-review-generator-pro'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'ai-review-generator-pro'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found', 'ai-review-generator-pro'));
        }

        $product_name = $post->post_title;
        if (empty($product_name)) {
            wp_send_json_error(__('Post title is empty', 'ai-review-generator-pro'));
        }

        // Clean the product name (remove "Review 2026" etc from title)
        $product_name = preg_replace('/\s*[-–—]\s*.*$/', '', $product_name);
        $product_name = preg_replace('/\s+Review\s*\d*.*$/i', '', $product_name);
        $product_name = trim($product_name);

        $logo_url = get_post_meta($post_id, '_review_tool_logo', true);

        if (empty($logo_url)) {
            wp_send_json_error(__('Please upload a tool logo first before generating the featured image.', 'ai-review-generator-pro'));
        }

        // Logo color determines which template background is used.
        $logo_color = sanitize_text_field($_POST['logo_color'] ?? 'black');
        if ($logo_color !== 'white') {
            $logo_color = 'black';
        }

        $attachment_id = $this->generate($post_id, $product_name, $logo_url, true, $logo_color);

        if ($attachment_id) {
            $image_url = wp_get_attachment_url($attachment_id);
            wp_send_json_success(array(
                'message'       => __('Featured image generated successfully!', 'ai-review-generator-pro'),
                'attachment_id' => $attachment_id,
                'image_url'     => $image_url,
            ));
        } else {
            wp_send_json_error(__('Failed to generate featured image. Check error logs for details.', 'ai-review-generator-pro'));
        }
    }
}
