<?php
/**
 * Image Handler Class
 *
 * Handles featured image generation with product name overlay.
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
 * Image Handler Class
 *
 * Downloads template image and adds product name overlay using GD library.
 *
 * @since 8.0.0
 */
class ImageHandler {

    /**
     * Template image URL
     *
     * @var string
     */
    private $template_url = 'https://nextgreviews.com/wp-content/uploads/featuredimage/image.png';

    /**
     * Generate featured image with product name overlay
     *
     * @since 8.0.0
     * @param string $product The product name.
     * @return int|null Attachment ID or null on failure.
     */
    public function generate($product) {
        $image_data = null;
        
        // Try to download template image
        $response = wp_remote_get($this->template_url, array(
            'timeout'   => 30,
            'sslverify' => false,
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $image_data = wp_remote_retrieve_body($response);
            if (!empty($image_data) && strlen($image_data) > 1000) {
                // Add product name overlay
                if (function_exists('imagecreatefromstring')) {
                    $image_data = $this->add_overlay($image_data, $product);
                }
            } else {
                $image_data = null;
            }
        }
        
        // Fallback: Create a simple generated image if template fails
        if (empty($image_data)) {
            error_log('AI Review Generator - Template image failed, using fallback');
            $image_data = $this->create_fallback_image($product);
        }
        
        if (empty($image_data)) {
            error_log('AI Review Generator - Could not generate any image');
            return null;
        }

        // Upload image
        $filename = sanitize_file_name($product . '-' . time() . '.png');
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            error_log('AI Review Generator - Upload error: ' . $upload['error']);
            return null;
        }

        // SEO-optimized metadata
        $focus_keyword = $product . ' Review';
        $alt_text      = $focus_keyword . ' - Featured Image ' . date('Y');
        $caption       = $focus_keyword . ' featured image showing product overview';
        $description   = 'Featured image for ' . $focus_keyword . '. Complete breakdown of ' . $product . ' pricing, features, pros and cons.';

        // Create attachment
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => 'image/png',
            'post_title'     => $focus_keyword . ' Featured Image ' . date('Y'),
            'post_content'   => $description,
            'post_excerpt'   => $caption,
            'post_status'    => 'inherit',
        ), $upload['file']);

        if (is_wp_error($attachment_id)) {
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
     * Create fallback image when template fails
     *
     * @since 8.0.0
     * @param string $product Product name.
     * @return string|null Image data or null on failure.
     */
    private function create_fallback_image($product) {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        // Create 1200x630 image (optimal for social sharing)
        $width  = 1200;
        $height = 630;
        $image  = imagecreatetruecolor($width, $height);
        
        if (!$image) {
            return null;
        }

        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Create gradient background (dark blue to purple)
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int)(30 + (80 - 30) * $ratio);
            $g = (int)(40 + (60 - 40) * $ratio);
            $b = (int)(100 + (140 - 100) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }

        // Add product name
        $white = imagecolorallocate($image, 255, 255, 255);
        $font_path = $this->get_font();
        
        if ($font_path && file_exists($font_path) && function_exists('imagettftext')) {
            // Use TTF font
            $font_size = 60;
            $bbox = @imagettfbbox($font_size, 0, $font_path, $product);
            if ($bbox) {
                $text_width = abs($bbox[4] - $bbox[0]);
                $text_x = ($width - $text_width) / 2;
                $text_y = ($height / 2) + ($font_size / 2);
                imagettftext($image, $font_size, 0, (int)$text_x, (int)$text_y, $white, $font_path, $product);
                
                // Add "REVIEW" below
                $review_size = 30;
                $review_text = 'REVIEW ' . date('Y');
                $bbox2 = @imagettfbbox($review_size, 0, $font_path, $review_text);
                if ($bbox2) {
                    $review_width = abs($bbox2[4] - $bbox2[0]);
                    $review_x = ($width - $review_width) / 2;
                    $review_y = $text_y + 60;
                    imagettftext($image, $review_size, 0, (int)$review_x, (int)$review_y, $white, $font_path, $review_text);
                }
            }
        } else {
            // Fallback to built-in font
            $font = 5;
            $text_width = imagefontwidth($font) * strlen($product);
            $text_x = ($width - $text_width) / 2;
            $text_y = ($height / 2) - (imagefontheight($font) / 2);
            imagestring($image, $font, (int)$text_x, (int)$text_y, $product, $white);
            
            // Add "REVIEW" below
            $review_text = 'REVIEW ' . date('Y');
            $review_width = imagefontwidth($font) * strlen($review_text);
            $review_x = ($width - $review_width) / 2;
            imagestring($image, $font, (int)$review_x, (int)$text_y + 30, $review_text, $white);
        }

        // Output PNG
        ob_start();
        imagepng($image, null, 9);
        $result = ob_get_clean();

        imagedestroy($image);

        return $result ?: null;
    }

    /**
     * Add product name overlay to image
     *
     * @since 8.0.0
     * @param string $image_data Binary image data.
     * @param string $product    Product name.
     * @return string Modified image data.
     */
    private function add_overlay($image_data, $product) {
        $image = @imagecreatefromstring($image_data);
        if (!$image) {
            return $image_data;
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        // Dark gray color for text (#333333)
        $text_color = imagecolorallocate($image, 51, 51, 51);

        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Try TTF font
        $font_path = $this->get_font();
        $use_ttf   = $font_path && file_exists($font_path) && function_exists('imagettftext');

        if ($use_ttf) {
            $this->add_ttf_text($image, $product, $font_path, $text_color, $width, $height);
        } else {
            $this->add_gd_text($image, $product, $text_color, $width, $height);
        }

        // Output PNG
        ob_start();
        imagepng($image, null, 9);
        $result = ob_get_clean();

        imagedestroy($image);

        return $result ?: $image_data;
    }

    /**
     * Add text using TTF font with specific layout rules
     *
     * @since 8.0.0
     * @param resource $image     GD image resource.
     * @param string   $text      Text to add.
     * @param string   $font_path Path to TTF font.
     * @param int      $color     Color identifier.
     * @param int      $width     Image width.
     * @param int      $height    Image height.
     */
    private function add_ttf_text($image, $text, $font_path, $color, $width, $height) {
        // Box dimensions
        $box_x = 2970;
        $box_y = 467;
        $box_width = 567;
        $box_height = 148;
        $padding = 10;

        // Available space for text
        $max_text_width = $box_width - ($padding * 2);
        $max_text_height = $box_height - ($padding * 2);

        // Count words
        $words = preg_split('/\s+/', trim($text));
        $word_count = count($words);

        // Determine layout: single or double line
        $use_two_lines = ($word_count >= 3);

        // Find optimal font size
        $font_size = 340;
        $min_font_size = 230;

        $text_lines = array();
        $line_height = 0;
        $total_height = 0;

        while ($font_size >= $min_font_size) {
            $text_lines = $this->split_text_for_box($text, $font_size, $font_path, $max_text_width, $use_two_lines);

            if (empty($text_lines)) {
                $font_size -= 3;
                continue;
            }

            // Calculate total height
            $line_height = 0;
            foreach ($text_lines as $line) {
                $bbox = @imagettfbbox($font_size, 0, $font_path, $line);
                if ($bbox === false) {
                    continue 2;
                }
                $line_height = max($line_height, abs($bbox[5] - $bbox[1]));
            }
            $total_height = $line_height * count($text_lines);

            if ($total_height <= $max_text_height) {
                break;
            }

            $font_size -= 3;
        }

        if (empty($text_lines)) {
            return;
        }

        // Calculate horizontal right-align
        $widest_line = 0;
        foreach ($text_lines as $line) {
            $bbox = @imagettfbbox($font_size, 0, $font_path, $line);
            if ($bbox) {
                $line_width = abs($bbox[4] - $bbox[0]);
                $widest_line = max($widest_line, $line_width);
            }
        }

        $text_start_x = $box_x + $box_width - $padding - $widest_line;

        // Calculate vertical center
        $total_text_height = $line_height * count($text_lines);
        $text_start_y = $box_y + $padding + (($max_text_height - $total_text_height) / 2) + $line_height;

        // Draw each line
        foreach ($text_lines as $index => $line) {
            // Right-align each line
            $bbox = @imagettfbbox($font_size, 0, $font_path, $line);
            $line_width = $bbox ? abs($bbox[4] - $bbox[0]) : 0;
            $line_x = $box_x + $box_width - $padding - $line_width;
            $line_y = $text_start_y + ($index * $line_height);

            $this->draw_white_text($image, $font_size, 0, (int)$line_x, (int)$line_y, $font_path, $line);
        }
    }

    /**
     * Split text into lines based on word count and box width
     *
     * @since 8.0.0
     * @param string $text         Text to split.
     * @param int    $font_size   Font size.
     * @param string $font_path   Path to TTF font.
     * @param int    $max_width   Maximum width for text.
     * @param bool   $use_two_lines Whether to use two lines.
     * @return array Array of text lines.
     */
    private function split_text_for_box($text, $font_size, $font_path, $max_width, $use_two_lines) {
        $words = preg_split('/\s+/', trim($text));
        $word_count = count($words);

        if (!$use_two_lines || $word_count < 3) {
            // Single line for 1-2 words
            return array($text);
        }

        // Split into two balanced lines
        $half = (int) ceil($word_count / 2);
        $line1 = implode(' ', array_slice($words, 0, $half));
        $line2 = implode(' ', array_slice($words, $half));

        // Check if both lines fit within max_width
        $bbox1 = @imagettfbbox($font_size, 0, $font_path, $line1);
        $bbox2 = @imagettfbbox($font_size, 0, $font_path, $line2);

        if ($bbox1 && $bbox2) {
            $width1 = abs($bbox1[4] - $bbox1[0]);
            $width2 = abs($bbox2[4] - $bbox2[0]);

            if ($width1 <= $max_width && $width2 <= $max_width) {
                return array($line1, $line2);
            }
        }

        // Try alternative split points for better balance
        for ($i = 1; $i < $word_count; $i++) {
            $line1 = implode(' ', array_slice($words, 0, $i));
            $line2 = implode(' ', array_slice($words, $i));

            $bbox1 = @imagettfbbox($font_size, 0, $font_path, $line1);
            $bbox2 = @imagettfbbox($font_size, 0, $font_path, $line2);

            if ($bbox1 && $bbox2) {
                $width1 = abs($bbox1[4] - $bbox1[0]);
                $width2 = abs($bbox2[4] - $bbox2[0]);

                if ($width1 <= $max_width && $width2 <= $max_width) {
                    return array($line1, $line2);
                }
            }
        }

        // Fallback to single line if two-line split fails
        return array($text);
    }

    /**
     * Draw white text with green stroke
     *
     * @since 8.0.0
     * @param resource $image     Target image.
     * @param float    $size      Font size.
     * @param float    $angle     Text angle.
     * @param int      $x         X position.
     * @param int      $y         Y position.
     * @param string   $font      Font path.
     * @param string   $text      Text to draw.
     */
    private function draw_white_text($image, $size, $angle, $x, $y, $font, $text) {
        // Color: #ffcc00
        $fill_color = imagecolorallocate($image, 252, 221, 6);

        // Stroke: #000 30px
        $stroke_color = imagecolorallocate($image, 0, 0, 0);
        $stroke_width = 15;

        // Move text 20px to the right
        $x_offset = 20;

        // Draw stroke
        for ($i = 1; $i <= $stroke_width; $i++) {
            imagettftext($image, $size, $angle, $x + $x_offset + $i, $y, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset - $i, $y, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset, $y + $i, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset, $y - $i, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset + $i, $y + $i, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset - $i, $y - $i, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset + $i, $y - $i, $stroke_color, $font, $text);
            imagettftext($image, $size, $angle, $x + $x_offset - $i, $y + $i, $stroke_color, $font, $text);
        }

        // Draw fill
        imagettftext($image, $size, $angle, $x + $x_offset, $y, $fill_color, $font, $text);
    }

    /**
     * Add text using built-in GD font (fallback)
     *
     * @since 8.0.0
     * @param resource $image  GD image resource.
     * @param string   $text   Text to add.
     * @param int      $color  Color identifier.
     * @param int      $width  Image width.
     * @param int      $height Image height.
     */
    private function add_gd_text($image, $text, $color, $width, $height) {
        $font        = 5;
        $char_height = imagefontheight($font);
        $bar_height  = (int) ($height * 0.11);
        $bar_center  = (int) ($bar_height / 2);

        $text_x = (int) ($width * 0.55);
        $text_y = $bar_center - (int) ($char_height / 2);

        imagestring($image, $font, $text_x, $text_y, $text, $color);
    }

    /**
     * Get font path
     *
     * @since 8.0.0
     * @return string|null Font path or null if not found.
     */
    private function get_font() {
        $font_path = AIRG_PLUGIN_DIR . 'assets/fonts/masterkomika-dr03k.otf';

        return file_exists($font_path) ? $font_path : null;
    }
}