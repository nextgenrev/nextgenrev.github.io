<?php
/**
 * Admin Class
 *
 * Handles all admin-related functionality including menu pages, settings, and AJAX.
 *
 * @package AIReviewGeneratorPro
 * @since   8.0.0
 */

namespace AIReviewGenerator\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 *
 * Main admin controller handling menus, settings pages, and AJAX requests.
 *
 * @since 8.0.0
 */
class Admin {

    /**
     * Option name for plugin settings
     *
     * @var string
     */
    private $option_name = 'ai_gen_options';

    /**
     * Constructor
     *
     * @since 8.0.0
     */
    public function __construct() {
        // Constructor left empty - hooks are registered via Loader
    }

    /**
     * Register admin menu pages
     *
     * @since 8.0.0
     * @return void
     */
    public function add_menu_pages() {
        add_menu_page(
            __('AI Review Generator', 'ai-review-generator-pro'),
            __('AI Review Generator', 'ai-review-generator-pro'),
            'manage_options',
            'ai-gen',
            array($this, 'render_generator_page'),
            'dashicons-welcome-write-blog',
            30
        );

        add_options_page(
            __('AI Review Settings', 'ai-review-generator-pro'),
            __('AI Review Gen', 'ai-review-generator-pro'),
            'manage_options',
            'ai-gen-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Initialize settings
     *
     * @since 8.0.0
     * @return void
     */
    public function init_settings() {
        register_setting('ai_gen_group', $this->option_name);
    }

    /**
     * Enqueue admin assets
     *
     * @since 8.0.0
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('toplevel_page_ai-gen', 'settings_page_ai-gen-settings'))) {
            return;
        }

        // Enqueue WordPress Media Library on generator page for logo upload
        if ($hook === 'toplevel_page_ai-gen') {
            wp_enqueue_media();
        }

        // Enqueue Font Awesome
        wp_enqueue_style(
            'airg-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            array(),
            '6.5.1'
        );

        // Enqueue admin CSS
        wp_enqueue_style(
            'airg-admin',
            AIRG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIRG_VERSION
        );

        // Load the dashboard script inline so aggressive host/CDN caching cannot
        // leave the admin screen running an older uploader implementation.
        $admin_script_path = AIRG_PLUGIN_DIR . 'assets/js/admin.js';
        wp_register_script('airg-admin', false, array('jquery'), AIRG_VERSION, true);
        wp_enqueue_script('airg-admin');

        // Localize script
        wp_localize_script('airg-admin', 'airg_ajax', array(
            'ajax_url'              => admin_url('admin-ajax.php'),
            'nonce'                 => wp_create_nonce('airg_generate_nonce'),
            'upload_nonce'          => wp_create_nonce('airg_upload_logo_nonce'),
            'max_upload_size'       => wp_max_upload_size(),
            'max_upload_size_label' => size_format(wp_max_upload_size()),
        ));

        if (is_readable($admin_script_path)) {
            $admin_script = file_get_contents($admin_script_path);
            if ($admin_script !== false && $admin_script !== '') {
                wp_add_inline_script('airg-admin', $admin_script, 'after');
            }
        }

        // Enqueue featured image JS on settings page for Test API button and avatar upload
        if ($hook === 'settings_page_ai-gen-settings') {
            wp_enqueue_media();
            wp_enqueue_script(
                'airg-featured-image',
                AIRG_PLUGIN_URL . 'assets/js/featured-image.js',
                array('jquery'),
                (string) filemtime(AIRG_PLUGIN_DIR . 'assets/js/featured-image.js'),
                true
            );
            wp_localize_script('airg-featured-image', 'airg_featured_image', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('airg_featured_image_nonce'),
            ));
        }
    }

    /**
     * Render the settings page
     *
     * @since 8.0.0
     * @return void
     */
    public function render_settings_page() {
        $options = get_option($this->option_name, array());
        $current_provider = $options['ai_provider'] ?? 'gemini';
        ?>
        <div class="wrap">
            <div class="airg-settings-wrap">
                <div class="airg-settings-header">
                    <h1><i class="fas fa-cog"></i> AI Review Generator Settings</h1>
                    <p>Configure your AI provider</p>
                </div>
                <div class="airg-settings-card">
                    <form method="post" action="options.php">
                        <?php settings_fields('ai_gen_group'); ?>
                        <div class="airg-section">
                            <h2 class="airg-section-title"><i class="fas fa-robot"></i> Content AI Provider</h2>
                            <select name="<?php echo esc_attr($this->option_name); ?>[ai_provider]" id="ai-provider-select" class="airg-select">
                                <option value="groq" <?php selected($current_provider, 'groq'); ?>>Groq (Free & Fast)</option>
                                <option value="cloudflare" <?php selected($current_provider, 'cloudflare'); ?>>Cloudflare Workers AI</option>
                                <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="openrouter" <?php selected($current_provider, 'openrouter'); ?>>OpenRouter</option>
                                <option value="opencodezen" <?php selected($current_provider, 'opencodezen'); ?>>OpenCode Zen (Free Models)</option>
                            </select>
                            
                            <div id="groq-settings" class="provider-section <?php echo ($current_provider === 'groq') ? 'active' : ''; ?>">
                                <h3><i class="fas fa-bolt"></i> Groq Settings</h3>
                                <div class="airg-input-group">
                                    <label>API Key</label>
                                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[groq_key]" value="<?php echo esc_attr($options['groq_key'] ?? ''); ?>" class="airg-input" placeholder="gsk_...">
                                </div>
                                <div class="airg-input-group">
                                    <label>Model</label>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[groq_model]" class="airg-select">
                                        <?php
                                        $gm = $options['groq_model'] ?? 'openai/gpt-oss-120b';
                                        $deprecated_groq_models = array(
                                            'llama-3.3-70b-versatile' => 'openai/gpt-oss-120b',
                                            'llama-3.1-8b-instant'    => 'openai/gpt-oss-20b',
                                        );
                                        $gm = $deprecated_groq_models[$gm] ?? $gm;
                                        ?>
                                        <option value="openai/gpt-oss-120b" <?php selected($gm, 'openai/gpt-oss-120b'); ?>>GPT-OSS 120B</option>
                                        <option value="openai/gpt-oss-20b" <?php selected($gm, 'openai/gpt-oss-20b'); ?>>GPT-OSS 20B (Fast)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="cloudflare-settings" class="provider-section <?php echo ($current_provider === 'cloudflare') ? 'active' : ''; ?>">
                                <h3><i class="fas fa-cloud"></i> Cloudflare Settings</h3>
                                <div class="airg-input-group">
                                    <label>Account ID</label>
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[cf_account_id]" value="<?php echo esc_attr($options['cf_account_id'] ?? ''); ?>" class="airg-input">
                                </div>
                                <div class="airg-input-group">
                                    <label>API Token</label>
                                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[cf_api_token]" value="<?php echo esc_attr($options['cf_api_token'] ?? ''); ?>" class="airg-input">
                                </div>
                            </div>
                            
                            <div id="gemini-settings" class="provider-section <?php echo ($current_provider === 'gemini') ? 'active' : ''; ?>">
                                <h3><i class="fas fa-star"></i> Gemini Settings</h3>
                                <div class="airg-input-group">
                                    <label>API Key</label>
                                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[gemini_key]" value="<?php echo esc_attr($options['gemini_key'] ?? ''); ?>" class="airg-input" placeholder="AQ... or AIza...">
                                </div>
                                <div class="airg-input-group">
                                    <label>Model</label>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[gemini_model]" class="airg-select">
                                        <?php $gemini_model = $options['gemini_model'] ?? 'gemini-3.7-flash'; ?>
                                        <option value="gemini-3.7-flash" <?php selected($gemini_model, 'gemini-3.7-flash'); ?>>Gemini 3.7 Flash (Recommended)</option>
                                        <option value="gemini-3.6-flash" <?php selected($gemini_model, 'gemini-3.6-flash'); ?>>Gemini 3.6 Flash (Fallback)</option>
                                        <option value="gemini-3.1-pro-preview" <?php selected($gemini_model, 'gemini-3.1-pro-preview'); ?>>Gemini 3.1 Pro Preview (Maximum Quality)</option>
                                        <option value="gemini-2.5-flash" <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Legacy)</option>
                                    </select>
                                </div>
                                <p class="airg-hint" style="margin-top:8px;color:#666;font-size:12px;">
                                    <i class="fas fa-info-circle"></i>
                                    Google AI Pro and Gemini API quota are separate. Check API quota and billing in Google AI Studio.
                                </p>
                            </div>
                            
                            <div id="openrouter-settings" class="provider-section <?php echo ($current_provider === 'openrouter') ? 'active' : ''; ?>">
                                <h3><i class="fas fa-network-wired"></i> OpenRouter Settings</h3>
                                <div class="airg-input-group">
                                    <label>API Key</label>
                                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[openrouter_key]" value="<?php echo esc_attr($options['openrouter_key'] ?? ''); ?>" class="airg-input" placeholder="sk-or-v1-...">
                                </div>
                                <div class="airg-input-group">
                                    <label>Model</label>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[openrouter_model]" class="airg-select">
                                        <?php $om = $options['openrouter_model'] ?? 'google/gemini-2.0-flash:grounded'; ?>
                                        <option value="google/gemini-2.0-flash:grounded" <?php selected($om, 'google/gemini-2.0-flash:grounded'); ?>>Gemini 2.0 Flash (grounded)</option>
                                        <option value="google/gemini-2.5-flash" <?php selected($om, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="opencodezen-settings" class="provider-section <?php echo ($current_provider === 'opencodezen') ? 'active' : ''; ?>">
                                <h3><i class="fas fa-code"></i> OpenCode Zen Settings</h3>
                                <div class="airg-input-group">
                                    <label>API Key</label>
                                    <input type="password" name="<?php echo esc_attr($this->option_name); ?>[opencodezen_key]" value="<?php echo esc_attr($options['opencodezen_key'] ?? ''); ?>" class="airg-input" placeholder="Your OpenCode Zen API key">
                                </div>
                                <div class="airg-input-group">
                                    <label>Model</label>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[opencodezen_model]" class="airg-select">
                                        <?php $ozm = $options['opencodezen_model'] ?? 'deepseek-v4-flash-free'; ?>
                                        <option value="deepseek-v4-flash-free" <?php selected($ozm, 'deepseek-v4-flash-free'); ?>>DeepSeek V4 Flash Free (Recommended)</option>
                                        <option value="big-pickle" <?php selected($ozm, 'big-pickle'); ?>>Big Pickle</option>
                                        <option value="mimo-v2.5-free" <?php selected($ozm, 'mimo-v2.5-free'); ?>>MiMo V2.5 Free</option>
                                        <option value="minimax-m3-free" <?php selected($ozm, 'minimax-m3-free'); ?>>MiniMax M3 Free</option>
                                        <option value="nemotron-3-ultra-free" <?php selected($ozm, 'nemotron-3-ultra-free'); ?>>Nemotron 3 Ultra Free</option>
                                    </select>
                                </div>
                                <p class="airg-hint" style="margin-top: 8px; color: #666; font-size: 12px;"><i class="fas fa-info-circle"></i> All models are free. DeepSeek V4 Flash is best for long, structured reviews. Big Pickle excels at complex reasoning tasks.</p>
                            </div>
                        </div>
                        
                        <div class="airg-section" style="margin-top: 30px;">
                            <h2 class="airg-section-title"><i class="fab fa-github"></i> GitHub Pages Settings</h2>
                            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Configure your GitHub settings to allow pushing generated posts directly to your Jekyll _posts folder.</p>
                            
                            <div class="airg-input-group">
                                <label>GitHub Token</label>
                                <input type="password" name="<?php echo esc_attr($this->option_name); ?>[github_token]" value="<?php echo esc_attr($options['github_token'] ?? ''); ?>" class="airg-input" placeholder="ghp_...">
                            </div>
                            <div class="airg-input-group">
                                <label>Repository Name</label>
                                <input type="text" name="<?php echo esc_attr($this->option_name); ?>[github_repo]" value="<?php echo esc_attr($options['github_repo'] ?? ''); ?>" class="airg-input" placeholder="username/repo">
                            </div>
                            <div class="airg-input-group">
                                <label>Author Name</label>
                                <input type="text" name="<?php echo esc_attr($this->option_name); ?>[github_author]" value="<?php echo esc_attr($options['github_author'] ?? 'paul'); ?>" class="airg-input" placeholder="paul">
                            </div>
                        </div>

                        <div class="airg-section" style="margin-top: 30px;">
                            <h2 class="airg-section-title"><i class="fas fa-image"></i> Featured Image Settings</h2>
                            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Generate featured images by placing the tool logo onto the template graphic. No API key required.</p>
                            
                            <div class="airg-input-group">
                                <label>Auto-generate Featured Image</label>
                                <select name="<?php echo esc_attr($this->option_name); ?>[auto_generate_image]" class="airg-select">
                                    <?php $auto_img = $options['auto_generate_image'] ?? 'yes'; ?>
                                    <option value="yes" <?php selected($auto_img, 'yes'); ?>>Enabled (generate for every new review)</option>
                                    <option value="no" <?php selected($auto_img, 'no'); ?>>Disabled (manual only via post editor)</option>
                                </select>
                                <p class="airg-hint" style="margin-top: 4px; color: #888; font-size: 11px;"><i class="fas fa-info-circle"></i> Upload a tool logo in the post editor, then click "Generate Featured Image" to place it inside the template bubble graphic.</p>
                            </div>
                        </div>
                        
                        <button type="submit" class="airg-submit-btn"><i class="fas fa-save"></i> Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the generator page
     *
     * @since 8.0.0
     * @return void
     */
    public function render_generator_page() {
        $options = get_option($this->option_name, array());
        $provider = $options['ai_provider'] ?? 'gemini';
        $has_keys = ($provider === 'groq' && !empty($options['groq_key'])) 
                 || ($provider === 'cloudflare' && !empty($options['cf_account_id'])) 
                 || ($provider === 'gemini' && !empty($options['gemini_key']))
                 || ($provider === 'openrouter' && !empty($options['openrouter_key']))
                 || ($provider === 'opencodezen' && !empty($options['opencodezen_key']));
        ?>
        <div id="airg-loader">
            <div class="loader-box">
                <div class="loader-spinner"></div>
                <h2 class="loader-title">Creating Review</h2>
                <p class="loader-sub">Preparing generation...</p>
                <div class="loader-steps">
                    <div class="l-step"><i class="fas fa-search"></i><span class="l-step-title">Scrape</span></div>
                    <div class="l-step"><i class="fas fa-globe"></i><span class="l-step-title">Research</span></div>
                    <div class="l-step"><i class="fas fa-magic"></i><span class="l-step-title">Write</span></div>
                    <div class="l-step"><i class="fas fa-rocket"></i><span class="l-step-title">Publish</span></div>
                    <div class="l-step"><i class="fas fa-image"></i><span class="l-step-title">Image</span></div>
                    <div class="l-step" id="l-step-github" style="display:none;"><i class="fab fa-github"></i><span class="l-step-title">GitHub</span></div>
                </div>
                <div class="loader-tip"><i class="fas fa-clock"></i> Progress is saved between steps</div>
            </div>
        </div>
        
        <div class="wrap">
            <div class="airg-dashboard">
                <div class="airg-header">
                    <h1><i class="fas fa-rocket"></i> AI Review Generator</h1>
                    <p>Create product reviews in seconds</p>
                </div>
                
                <div class="airg-main-card">
                    <?php if (!$has_keys): ?>
                    <div class="airg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="airg-warning-text"><a href="<?php echo admin_url('options-general.php?page=ai-gen-settings'); ?>">Configure API</a> before generating.</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="airg-form-section">
                        <h2><i class="fas fa-edit"></i> Review Details</h2>
                        <div class="airg-field">
                            <label>Product Name</label>
                            <input type="text" id="airg-product-name" placeholder="e.g., ProductXYZ Pro">
                        </div>
                        <div class="airg-field">
                            <label>Affiliate Link</label>
                            <input type="url" id="airg-affiliate-link" placeholder="https://...">
                        </div>
                        <div class="airg-field">
                            <label>Category</label>
                            <select id="airg-category">
                                <option value="0">— Select —</option>
                                <?php 
                                $categories = get_categories(array('hide_empty' => false));
                                foreach ($categories as $category) {
                                    echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="airg-field">
                            <label>Tool Logo <span style="font-weight: normal; color: #888; font-size: 12px;">(optional - for AI featured image)</span></label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="text" id="airg-logo-url" placeholder="Upload or paste logo URL" style="flex: 1;" readonly>
                                <input type="file" id="airg-dashboard-logo-file" accept="image/*" style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;">
                                <label for="airg-dashboard-logo-file" id="airg-dashboard-upload-logo" class="button" role="button" tabindex="0" style="white-space: nowrap;"><i class="fas fa-upload"></i> <span class="airg-upload-logo-label">Upload</span></label>
                            </div>
                            <div id="airg-dashboard-logo-status" style="display: none; margin-top: 8px; color: #646970; font-size: 12px;"></div>
                            <div id="airg-dashboard-logo-preview" style="margin-top: 8px;"></div>
                        </div>
                        <div class="airg-field" style="display: flex; align-items: center; gap: 8px; margin-top: 15px; margin-bottom: 20px;">
                            <input type="checkbox" id="airg-push-github" value="1" checked>
                            <label for="airg-push-github" style="margin: 0; font-weight: 500;">Push to GitHub Pages</label>
                        </div>
                        <button id="airg-generate-btn" class="airg-btn-generate" <?php echo !$has_keys ? 'disabled' : ''; ?>><i class="fas fa-magic"></i> Generate & Publish</button>
                    </div>
                    
                    <div class="airg-features">
                        <div class="airg-feature"><i class="fas fa-bolt"></i><div class="airg-feature-text">Fast</div></div>
                        <div class="airg-feature"><i class="fas fa-image"></i><div class="airg-feature-text">Auto Image</div></div>
                        <div class="airg-feature"><i class="fas fa-search"></i><div class="airg-feature-text">SEO Ready</div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Start a staged generation job.
     *
     * Each expensive network operation runs in a separate AJAX request. This
     * keeps individual requests below common shared-hosting gateway timeouts.
     *
     * @since 8.0.1
     * @return void
     */
    public function ajax_start_generation() {
        $this->authorize_generation_request();

        $product_name   = sanitize_text_field($_POST['product_name'] ?? '');
        $affiliate_link = esc_url_raw($_POST['affiliate_link'] ?? '');
        $category       = intval($_POST['category'] ?? 0);
        $logo_url       = esc_url_raw($_POST['logo_url'] ?? '');
        $push_github    = !empty($_POST['push_github']) ? true : false;

        if (empty($product_name) || empty($affiliate_link)) {
            wp_send_json_error(__('Product name and affiliate link are required', 'ai-review-generator-pro'), 400);
        }

        $job_id = sanitize_key(wp_generate_password(24, false, false));
        $job = array(
            'user_id'        => get_current_user_id(),
            'product_name'   => $product_name,
            'affiliate_link' => $affiliate_link,
            'category'       => $category,
            'logo_url'       => $logo_url,
            'push_github'    => $push_github,
            'stage'          => 'scrape',
            'created_at'     => time(),
        );

        if (!$this->save_generation_job($job_id, $job)) {
            wp_send_json_error(__('Could not start the generation job. Please try again.', 'ai-review-generator-pro'), 500);
        }

        wp_send_json_success(array('job_id' => $job_id));
    }

    /**
     * Run one short generation stage and persist its result for the next one.
     *
     * @since 8.0.1
     * @return void
     */
    public function ajax_generation_step() {
        $this->authorize_generation_request();

        $job_id = sanitize_key($_POST['job_id'] ?? '');
        $step   = sanitize_key($_POST['step'] ?? '');
        $job    = $this->get_generation_job($job_id);

        if (empty($job)) {
            wp_send_json_error(__('This generation job expired. Please start again.', 'ai-review-generator-pro'), 410);
        }

        $options = get_option($this->option_name, array());

        switch ($step) {
            case 'scrape':
                $scraper = new \AIReviewGenerator\WebScraper($options);
                $job['scraped'] = $scraper->scrape($job['affiliate_link'], false);
                $job['stage'] = 'research';
                break;

            case 'research':
                $scraper = new \AIReviewGenerator\WebScraper($options);
                $job['scraped'] = $scraper->enrich_with_research(
                    $job['scraped'] ?? array(),
                    $job['product_name']
                );
                $job['stage'] = 'content';
                break;

            case 'content':
                $content_generator = new \AIReviewGenerator\ContentGenerator($options);
                $ai_content = $content_generator->generate(
                    $job['product_name'],
                    $job['affiliate_link'],
                    $job['scraped'] ?? array()
                );

                if (is_wp_error($ai_content)) {
                    wp_send_json_error($ai_content->get_error_message(), 422);
                }

                $job['ai_content'] = $ai_content;
                $job['stage'] = 'post';
                break;

            case 'post':
                if (empty($job['post_id']) || !get_post($job['post_id'])) {
                    $post_id = $this->create_review_post($job);
                    if (is_wp_error($post_id)) {
                        wp_send_json_error($post_id->get_error_message(), 500);
                    }

                    // Persist the post ID immediately so a safe retry cannot
                    // publish a duplicate post if the connection drops later.
                    $job['post_id'] = (int) $post_id;
                }
                $job['stage'] = 'image';
                break;

            case 'image':
                $post_id = intval($job['post_id'] ?? 0);
                if ($post_id <= 0 || !get_post($post_id)) {
                    wp_send_json_error(__('The generated post could not be found.', 'ai-review-generator-pro'), 500);
                }

                if (!get_post_thumbnail_id($post_id)) {
                    $featured_image_gen = new \AIReviewGenerator\FeaturedImageGenerator();
                    $featured_image_gen->generate(
                        $post_id,
                        $job['product_name'],
                        $job['logo_url'] ?? ''
                    );
                }

                if (!empty($job['push_github'])) {
                    $job['stage'] = 'github';
                    break;
                }

                delete_transient($this->get_generation_job_key($job_id));
                wp_send_json_success(array(
                    'done'     => true,
                    'edit_url' => get_edit_post_link($post_id, 'raw'),
                ));

            case 'github':
                $post_id = intval($job['post_id'] ?? 0);
                $pusher = new \AIReviewGenerator\GitHubPusher($options);
                $result = $pusher->push($post_id, $job['product_name']);

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message(), 500);
                }

                delete_transient($this->get_generation_job_key($job_id));
                wp_send_json_success(array(
                    'done'     => true,
                    'edit_url' => get_edit_post_link($post_id, 'raw'),
                ));

            default:
                wp_send_json_error(__('Invalid generation step.', 'ai-review-generator-pro'), 400);
        }

        if (!$this->save_generation_job($job_id, $job)) {
            wp_send_json_error(__('Could not save generation progress. Please try again.', 'ai-review-generator-pro'), 500);
        }

        wp_send_json_success(array(
            'done'       => false,
            'next_stage' => $job['stage'],
        ));
    }

    /**
     * Verify the shared generation nonce and capability.
     *
     * @since 8.0.1
     * @return void
     */
    private function authorize_generation_request() {
        check_ajax_referer('airg_generate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'ai-review-generator-pro'), 403);
        }
    }

    /**
     * Build the transient key for a user-owned generation job.
     *
     * @since 8.0.1
     * @param string $job_id Job identifier.
     * @return string
     */
    private function get_generation_job_key($job_id) {
        return 'airg_job_' . get_current_user_id() . '_' . sanitize_key($job_id);
    }

    /**
     * Retrieve a generation job owned by the current user.
     *
     * @since 8.0.1
     * @param string $job_id Job identifier.
     * @return array
     */
    private function get_generation_job($job_id) {
        if (empty($job_id)) {
            return array();
        }

        $job = get_transient($this->get_generation_job_key($job_id));
        if (!is_array($job) || intval($job['user_id'] ?? 0) !== get_current_user_id()) {
            return array();
        }

        return $job;
    }

    /**
     * Store generation progress for thirty minutes.
     *
     * @since 8.0.1
     * @param string $job_id Job identifier.
     * @param array  $job    Job state.
     * @return bool
     */
    private function save_generation_job($job_id, $job) {
        $key = $this->get_generation_job_key($job_id);
        $saved = set_transient(
            $key,
            $job,
            30 * MINUTE_IN_SECONDS
        );

        // WordPress may return false when the stored value is unchanged. That
        // is still a successful idempotent retry if the job is present.
        return $saved || get_transient($key) === $job;
    }

    /**
     * Build and publish a review post from completed generation data.
     *
     * Featured-image work intentionally remains a separate stage because GD
     * image processing can be expensive on shared hosting.
     *
     * @since 8.0.1
     * @param array $job Completed generation job.
     * @return int|\WP_Error Post ID on success.
     */
    private function create_review_post($job) {
        $ai_content    = $job['ai_content'] ?? array();
        $scraped       = $job['scraped'] ?? array();
        $product_name  = $job['product_name'] ?? '';
        $affiliate_link = $job['affiliate_link'] ?? '';
        $category      = intval($job['category'] ?? 0);

        if (empty($ai_content) || empty($product_name) || empty($affiliate_link)) {
            return new \WP_Error(
                'incomplete_generation_job',
                __('The generated article data is incomplete. Please start again.', 'ai-review-generator-pro')
            );
        }

        if (!empty($scraped['pricing'])) {
            if (!isset($ai_content['pricing_value']) || !is_array($ai_content['pricing_value'])) {
                $ai_content['pricing_value'] = array();
            }
            $ai_content['pricing_value']['price'] = $scraped['pricing'];
        }

        if (!empty($scraped['offer'])) {
            if (!isset($ai_content['pricing_value']) || !is_array($ai_content['pricing_value'])) {
                $ai_content['pricing_value'] = array();
            }
            $ai_content['pricing_value']['special_offer'] = $scraped['offer'];
        }

        if (!empty($scraped['comparison'])) {
            $ai_content['comparison'] = $scraped['comparison'];
        }

        $review_template = new \AIReviewGenerator\ReviewTemplate();
        $html = $review_template->build($ai_content, $affiliate_link, $product_name, $scraped);
        $post_title = !empty($ai_content['title'])
            ? sanitize_text_field($ai_content['title'])
            : sprintf(__('%s Review', 'ai-review-generator-pro'), $product_name);

        $post_id = wp_insert_post(array(
            'post_title'    => $post_title,
            'post_content'  => $html,
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_category' => $category > 0 ? array($category) : array(),
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $focus_keyword = $product_name . ' Review';
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        if (!empty($ai_content['meta_description'])) {
            update_post_meta($post_id, 'rank_math_description', $ai_content['meta_description']);
        }
        update_post_meta($post_id, 'rank_math_title', $post_title);

        if (!empty($job['logo_url'])) {
            update_post_meta($post_id, '_review_tool_logo', esc_url_raw($job['logo_url']));
        }

        return (int) $post_id;
    }

    /**
     * Handle AJAX generation request
     *
     * @since 8.0.0
     * @return void
     */
    public function ajax_generate() {
        check_ajax_referer('airg_generate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'ai-review-generator-pro'));
        }

        // Extend PHP execution time for long AI operations
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $product_name    = sanitize_text_field($_POST['product_name'] ?? '');
        $affiliate_link  = esc_url_raw($_POST['affiliate_link'] ?? '');
        $category        = intval($_POST['category'] ?? 0);

        if (empty($product_name) || empty($affiliate_link)) {
            wp_send_json_error(__('Product name and affiliate link are required', 'ai-review-generator-pro'));
        }

        $options = get_option($this->option_name, array());

        // Initialize services
        $content_generator = new \AIReviewGenerator\ContentGenerator($options);
        $featured_image_gen = new \AIReviewGenerator\FeaturedImageGenerator();
        $review_template   = new \AIReviewGenerator\ReviewTemplate();

        // Scrape website (pass options for web search fallback)
        $scraper = new \AIReviewGenerator\WebScraper($options);
        $scraped = $scraper->scrape($affiliate_link);

        // Generate AI content
        $ai_content = $content_generator->generate($product_name, $affiliate_link, $scraped);
        if (is_wp_error($ai_content)) {
            wp_send_json_error($ai_content->get_error_message());
        }

        // Override price with scraped minimum price if available
        if (!empty($scraped['pricing'])) {
            if (!isset($ai_content['pricing_value'])) {
                $ai_content['pricing_value'] = array();
            }
            $ai_content['pricing_value']['price'] = $scraped['pricing'];
        }

        // Add offer from web search to AI content
        if (!empty($scraped['offer'])) {
            if (!isset($ai_content['pricing_value'])) {
                $ai_content['pricing_value'] = array();
            }
            $ai_content['pricing_value']['special_offer'] = $scraped['offer'];
        }

        // Add comparison data from web search
        if (!empty($scraped['comparison'])) {
            $ai_content['comparison'] = $scraped['comparison'];
        }

        // Build HTML (pass scraped data for additional info)
        $html = $review_template->build($ai_content, $affiliate_link, $product_name, $scraped);

        // Create post first (needed for featured image attachment)
        $post_id = wp_insert_post(array(
            'post_title'    => $ai_content['title'],
            'post_content'  => $html,
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_category' => $category > 0 ? array($category) : array(),
        ), true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(__('Post creation failed', 'ai-review-generator-pro'));
        }

        // Set Rank Math SEO meta so the analysis matches the on-page keyword.
        $focus_keyword = $product_name . ' Review';
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        if (!empty($ai_content['meta_description'])) {
            update_post_meta($post_id, 'rank_math_description', $ai_content['meta_description']);
        }
        if (!empty($ai_content['title'])) {
            update_post_meta($post_id, 'rank_math_title', $ai_content['title']);
        }

        // Generate featured image (logo overlay on template)
        $image_id = null;
        $logo_url = '';

        // Check if a logo URL was passed from form field
        if (!empty($_POST['logo_url'])) {
            $logo_url = esc_url_raw($_POST['logo_url']);
            update_post_meta($post_id, '_review_tool_logo', $logo_url);
        }

        // Generate featured image with logo overlay on template
        $image_id = $featured_image_gen->generate($post_id, $product_name, $logo_url);

        wp_send_json_success(array(
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ));
    }
}
