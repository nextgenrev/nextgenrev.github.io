<?php
/**
 * Content Generator Class
 *
 * Handles AI content generation using multiple providers.
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
 * Content Generator Class
 *
 * Orchestrates AI content generation with provider selection and prompt building.
 *
 * @since 8.0.0
 */
class ContentGenerator {

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Constructor
     *
     * @since 8.0.0
     * @param array $options Plugin options including API credentials.
     */
    public function __construct($options) {
        $this->options = $options;
    }

    /**
     * Generate review content using AI
     *
     * @since 8.0.0
     * @param string $product        The product name.
     * @param string $affiliate_link The affiliate link.
     * @param array  $scraped        Scraped data from the product page.
     * @return array|\WP_Error Generated content array or WP_Error on failure.
     */
    public function generate($product, $affiliate_link, $scraped = array()) {
        $provider = $this->options['ai_provider'] ?? 'gemini';
        
        // Build scraped context
        $scraped_context = $this->build_scraped_context($scraped);
        
        // Build SEO-optimized prompt
        $prompt = $this->build_prompt($product, $scraped_context);
        
        // Call appropriate provider
        switch ($provider) {
            case 'groq':
                return $this->call_groq($prompt);
            case 'cloudflare':
                return $this->call_cloudflare($prompt);
            case 'gemini':
                return $this->call_gemini($prompt);
            case 'openrouter':
                return $this->call_openrouter($prompt);
            case 'opencodezen':
                return $this->call_opencodezen($prompt);
            default:
                return new \WP_Error('invalid_provider', __('Invalid AI provider', 'ai-review-generator-pro'));
        }
    }

    /**
     * Build context string from scraped data
     *
     * @since 8.0.0
     * @param array $scraped Scraped data array.
     * @return string Formatted context string.
     */
    private function build_scraped_context($scraped) {
        $context = '';
        
        if (!empty($scraped['title'])) {
            $context .= "Page Title: " . $scraped['title'] . "\n";
        }
        if (!empty($scraped['description'])) {
            $context .= "Description: " . $scraped['description'] . "\n";
        }
        if (!empty($scraped['pricing'])) {
            $context .= "Pricing found: " . $scraped['pricing'] . "\n";
        }
        if (!empty($scraped['content'])) {
            $context .= "Page Content:\n" . $scraped['content'] . "\n";
        }
        if (!empty($scraped['features'])) {
            $context .= "Features found:\n- " . implode("\n- ", $scraped['features']) . "\n";
        }
        if (!empty($scraped['pricing_tiers'])) {
            $context .= "Pricing Plans/Tiers found:\n" . json_encode($scraped['pricing_tiers'], JSON_PRETTY_PRINT) . "\n";
        }
        if (!empty($scraped['integrations'])) {
            $context .= "Supported Integrations found:\n- " . implode("\n- ", $scraped['integrations']) . "\n";
        }
        if (!empty($scraped['community_feedback'])) {
            $context .= "Reddit/Forum Community Opinions:\n" . json_encode($scraped['community_feedback'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $context;
    }

    /**
     * Build SEO-optimized prompt for AI
     *
     * @since 8.0.0
     * @param string $product         The product name.
     * @param string $scraped_context Formatted scraped content.
     * @return string The complete prompt.
     */
    private function build_prompt($product, $scraped_context) {
        $focus_keyword1 = $product . ' Review';
        $focus_keyword2 = $product . ' Pricing';
        $year = date('Y');
        $date = date('F j, Y');
        
        $example_structure = array(
            'title' => $focus_keyword1 . ' ' . $year . ' – Honest Verdict & Breakdown',
            'meta_description' => $focus_keyword1 . ' - is it worth your money? Complete breakdown of features, ' . strtolower($focus_keyword2) . ', pros and cons. Read my honest analysis.',
            'rating' => '4.6',
            'review_date' => $date,
            'introduction' => array(
                'hook' => 'An engaging hook starting with ' . $focus_keyword1 . '. First-person narrative.',
                'pain_point' => 'Describe user frustrations/pain points.',
                'context' => 'General industry context / why this product exists.',
                'experience_statement' => 'Statement confirming you have tested ' . $product . ' for 30+ hours.',
                'what_to_expect' => 'Brief overview of what the reader will learn in this review.'
            ),
            'product_overview' => array(
                'what_is_it' => 'Detailed explanation of what ' . $product . ' is.',
                'creator_info' => 'Information about the vendor or creator.',
                'problem_solved' => 'How this tool solves the target problem.',
                'how_it_works_steps' => array(
                    'Step 1 description (highly detailed, first-person)',
                    'Step 2 description (highly detailed, first-person)',
                    'Step 3 description (highly detailed, first-person)'
                ),
                'unique_value' => 'What makes this product stand out.'
            ),
            'key_features' => array(
                array('name' => 'Feature 1 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 2 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 3 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 4 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 5 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 6 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 7 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it'),
                array('name' => 'Feature 8 Name', 'description' => 'Detailed explanation of how it works', 'personal_take' => 'My personal evaluation after testing it')
            ),
            'integrations' => array('App 1', 'App 2', 'App 3', 'App 4'),
            'user_experience' => array(
                'setup_process' => 'My setup experience, ease of registration, and starting out.',
                'daily_usage' => 'Interface walkthrough and daily usability observations.',
                'learning_curve' => 'Learning curve, onboarding, documentation quality.',
                'support_quality' => 'My support tests or review of support availability.',
                'personal_walkthrough' => 'First-person testing anecdotes (e.g. "When I first loaded up the dashboard, I noticed...")'
            ),
            'pricing_value' => array(
                'price' => 'Frontend price (e.g. $37 or $37/month)',
                'guarantee' => 'Refund policy or risk-free guarantee information.',
                'special_offer' => 'Special launch bonuses or discounts.'
            ),
            'pros' => array('Specific Pro 1', 'Specific Pro 2', 'Specific Pro 3', 'Specific Pro 4', 'Specific Pro 5', 'Specific Pro 6', 'Specific Pro 7'),
            'cons' => array('Honest Con 1', 'Honest Con 2', 'Honest Con 3', 'Honest Con 4'),
            'ideal_user' => array(
                'best_for' => array('Target Audience 1', 'Target Audience 2', 'Target Audience 3'),
                'use_cases' => array('Specific Use Case 1', 'Specific Use Case 2', 'Specific Use Case 3'),
                'not_for' => array('Avoid Category 1', 'Avoid Category 2')
            ),
            'community_feedback' => array(
                'reddit_sentiment' => 'Summary of what Reddit/Product Hunt community thinks about it.',
                'common_praise' => array('Praise point 1', 'Praise point 2', 'Praise point 3'),
                'common_complaints' => array('Complaint point 1', 'Complaint point 2')
            ),
            'faq' => array(
                array('question' => 'How long to see results with ' . $product . '?', 'answer' => 'Detailed response'),
                array('question' => 'Does ' . $product . ' have customer support?', 'answer' => 'Detailed response'),
                array('question' => 'Is ' . $product . ' good for beginners?', 'answer' => 'Detailed response'),
                array('question' => 'What are the best ' . $product . ' alternatives?', 'answer' => 'Detailed response'),
                array('question' => 'Does ' . $product . ' offer a free trial?', 'answer' => 'Detailed response'),
                array('question' => 'What makes ' . $product . ' different from competitors?', 'answer' => 'Detailed response'),
                array('question' => 'Is there any monthly fee for ' . $product . '?', 'answer' => 'Detailed response'),
                array('question' => 'Can I use ' . $product . ' on mobile devices?', 'answer' => 'Detailed response')
            ),
            'comparison' => array(
                array('name' => 'Alternative 1 Name', 'price' => '$XX/month', 'description' => 'Key difference/description'),
                array('name' => 'Alternative 2 Name', 'price' => '$XX/month', 'description' => 'Key difference/description'),
                array('name' => 'Alternative 3 Name', 'price' => '$XX/month', 'description' => 'Key difference/description')
            ),
            'comparison_verdict' => 'Comparison summary comparing ' . $product . ' directly against competitors.',
            'conclusion' => array(
                'summary' => 'Summary of findings.',
                'verdict' => 'Final decision recommendation.',
                'who_should_buy' => 'Ideal profiles who should purchase.',
                'who_should_skip' => 'Profiles who should skip this tool.',
                'final_score_justification' => 'Justification of the score out of 5.'
            )
        );

        return 'You are an industry expert and product reviewer who has spent hundreds of hours testing digital tools. You are writing a highly detailed review for "' . $product . '" following Google\'s E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and Helpful Content guidelines. Write in a first-person, conversational, and authoritative voice ("I", "my", "in my testing"). Focus on Rank Math 100/100 score and maximum reader value.

STRICT GROUNDING & SOURCE-ONLY RULE (CRITICAL - HIGHEST PRIORITY):
- Your review MUST be based ONLY and EXCLUSIVELY on the provided SCRAPED CONTEXT & BACKGROUND RESEARCH below.
- Do NOT invent, assume, or hallucinate ANY facts, specifications, product features, pricing, plans, integrations, alternatives, creator details, dates, statistics, or user counts that are not explicitly mentioned in the provided context.
- ZERO TOLERANCE for fabrication: If a fact is not in the source context, DO NOT include it. This includes feature names, pricing numbers, company names, integration lists, step-by-step workflows, and competitor details.
- If the provided context does not contain enough information to cover all 8 features, all 8 FAQs, or other placeholders, do NOT make them up. Instead, focus in-depth on the features/facts that *are* present. Return only the valid features, integrations, and FAQs that are documented in the source context (e.g. if only 3 features are known, return a "key_features" array containing only those 3 features; do not hallucinate fictional ones to reach 8).
- For any field or array element where no factual source information is available, leave it empty/blank, or omit it, rather than inventing false details.
- NEVER invent creator/founder names, company founding years, user statistics, or revenue numbers unless they appear word-for-word in the source context.
- NEVER fabricate integration names, third-party tools, or competitor products unless explicitly listed in the source context.
- If the source context is sparse, write a SHORTER but 100% accurate review. Accuracy is MORE important than length.

FOCUS KEYWORDS (must appear in title, meta, content, headings):
- Primary: "' . $focus_keyword1 . '"
- Secondary: "' . $focus_keyword2 . '"

SCRAPED CONTEXT & BACKGROUND RESEARCH:
' . $scraped_context . '

CONTENT LENGTH & DETAIL REQUIREMENTS:
- **Aim for 1500-2500 words total** — but NEVER sacrifice accuracy for length. If the source context only supports 1200 words of factual content, write 1200 words. Do NOT pad with invented details.
- **Introduction**: Write 150-200 words introducing the tool, sharing pain points, and setting context. Only mention facts from the source.
- **Product Overview**: Write 150-200 words explaining the tool, creator background (ONLY if mentioned in source), and problems solved.
- **How it Works Steps**: Provide up to 3 detailed steps (at least 50-60 words per step) walking through usage based strictly on provided source facts. If steps are not documented in the source, OMIT this section or keep it very brief.
- **Key Features**: Write detailed features (ONLY those found in the source text — could be 2, 5, or 8). For each feature, write 40-60 words description plus 30-40 words "personal_take" evaluation. Do NOT invent features.
- **User Experience**: Write 60-80 words for each of setup_process, daily_usage, learning_curve, support_quality, and personal_walkthrough. Base ONLY on facts from the source text. If no UX info exists in source, write general observations based on the product type but clearly frame them as expectations, not tested facts.
- **Pricing**: Mention only the main frontend price and any special offer/guarantee if found in the source. Do NOT include pricing tiers or plan breakdowns. If no pricing info in source, set to empty.
- **Ideal User & Alternatives**: Write detailed comparisons. Use ONLY actual alternatives and data mentioned in the source context.
- **Community Feedback**: Write 80-100 words total compiling Reddit and forum reviews from the background research. If none found, leave empty.
- **FAQ**: Answer FAQ questions (ONLY those answerable from source facts) strictly using information from the source text. Do NOT invent answers.
- **Conclusion**: Write 150-200 words summarizing findings, final score, and specific profiles.

WRITING STYLE, E-E-A-T & HELPFUL CONTENT (CRITICAL):
- **E-E-A-T Focus**: Write strictly as a domain expert who has personally used the product. Demonstrate "Experience" by describing specific, relatable workflows and realistic outcomes. Demonstrate "Expertise" by offering deep analysis rather than superficial summaries.
- **Helpful Content**: Your primary goal is to help the reader make an informed decision, not to sell the product. Be brutally honest about flaws (Cons) and highly specific about who should NOT buy it.
- **Ban AI Clichés**: NEVER use words like "revolutionary", "game-changer", "delve", "unlock", "seamless", "power of", "in conclusion", "furthermore", "ultimately", or "testament to".
- **Formatting for Scannability**: Use bold text naturally to highlight key concepts within sentences. Use bullet points whenever listing items.
- **Narrative Style**: Use an authoritative but conversational first-person voice ("I", "my team", "in my testing").
- **Keyword Naturalness**: Avoid exact-match keyword stuffing. Use semantic variations and natural language around the focus keywords.
- **PARAGRAPH LENGTH RULE (CRITICAL)**: Keep every paragraph SHORT — maximum 2-3 sentences per paragraph. Break longer thoughts into multiple short paragraphs. Readers scan content online, so use frequent paragraph breaks. No single paragraph should exceed 50 words. This applies to ALL text fields: introduction, product overview, user experience, conclusion, descriptions, and personal takes.

CRITICAL JSON RULES:
- Return ONLY the JSON object, nothing else.
- No markdown wrappers (like ```json), no text before or after.
- Use straight double quotes only.
- Escape any quotes inside strings with backslash.
- No trailing commas.
- No line breaks inside string values.

JSON structure:

' . json_encode($example_structure, JSON_PRETTY_PRINT) . '

Output ONLY the JSON object above filled in with your verbose content.';
    }

    /**
     * Call Groq API
     *
     * @since 8.0.0
     * @param string $prompt The prompt to send.
     * @return array|\WP_Error Parsed response or error.
     */
    private function call_groq($prompt) {
        $key = $this->options['groq_key'] ?? '';
        if (empty($key)) {
            return new \WP_Error('missing_key', __('Groq API key is missing', 'ai-review-generator-pro'));
        }

        $model = $this->options['groq_model'] ?? 'openai/gpt-oss-120b';
        $deprecated_models = array(
            'llama-3.3-70b-versatile' => 'openai/gpt-oss-120b',
            'llama-3.1-8b-instant'    => 'openai/gpt-oss-20b',
        );
        $model = $deprecated_models[$model] ?? $model;

        // Free-tier GPT-OSS requests have an 8,000 TPM limit. Groq reserves the
        // requested completion tokens up front, so 8,192 output tokens alone can
        // make an otherwise valid prompt exceed that limit.
        $max_completion_tokens = 3200;
        $request_body = array(
            'model'                 => $model,
            'messages'              => array(
                array('role' => 'system', 'content' => 'You are a JSON generator. You ONLY output valid JSON objects. Never include explanations or markdown. You MUST only include facts explicitly provided in the user prompt. Do NOT invent or hallucinate any information.'),
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_completion_tokens' => $max_completion_tokens,
            'temperature'           => 0.4,
            'reasoning_effort'      => 'low',
            'reasoning_format'      => 'hidden',
            'response_format'       => array('type' => 'json_object'),
        );
        $request_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($request_body),
            'timeout' => 50,
        );

        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', $request_args);

        // If a larger scraped prompt still exceeds TPM, use Groq's exact token
        // counts to reduce the completion budget and retry once with headroom.
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 413) {
            $error_body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = is_array($error_body) && isset($error_body['error']['message'])
                ? $error_body['error']['message']
                : '';

            if (is_string($error_message)
                && preg_match('/Limit\s+(\d+),\s+Requested\s+(\d+)/i', $error_message, $matches)) {
                $limit = (int) $matches[1];
                $requested = (int) $matches[2];
                $adjusted_max = $max_completion_tokens - max(0, $requested - $limit) - 256;

                if ($adjusted_max >= 1200 && $adjusted_max < $max_completion_tokens) {
                    $request_body['max_completion_tokens'] = $adjusted_max;
                    $request_args['body'] = wp_json_encode($request_body);
                    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', $request_args);
                }
            }
        }

        return $this->parse_openai_response($response);
    }

    /**
     * Call Cloudflare Workers AI
     *
     * @since 8.0.0
     * @param string $prompt The prompt to send.
     * @return array|\WP_Error Parsed response or error.
     */
    private function call_cloudflare($prompt) {
        $account_id = $this->options['cf_account_id'] ?? '';
        $token      = $this->options['cf_api_token'] ?? '';
        
        if (empty($account_id) || empty($token)) {
            return new \WP_Error('missing_credentials', __('Cloudflare credentials missing', 'ai-review-generator-pro'));
        }

        $response = wp_remote_post(
            'https://api.cloudflare.com/client/v4/accounts/' . $account_id . '/ai/run/@cf/meta/llama-3.1-8b-instruct',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => json_encode(array(
                    'messages' => array(array('role' => 'user', 'content' => $prompt)),
                )),
                'timeout' => 50,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $this->parse_json($body['result']['response'] ?? '');
    }

    /**
     * Call Google Gemini API
     *
     * @since 8.0.0
     * @param string $prompt The prompt to send.
     * @return array|\WP_Error Parsed response or error.
     */
    private function call_gemini($prompt) {
        $key = $this->options['gemini_key'] ?? '';
        if (empty($key)) {
            return new \WP_Error('missing_key', __('Gemini API key is missing', 'ai-review-generator-pro'));
        }

        $allowed_models = array(
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.1-pro-preview',
            'gemini-2.5-flash',
        );
        $model = $this->options['gemini_model'] ?? 'gemini-3.7-flash';
        if (!in_array($model, $allowed_models, true)) {
            $model = 'gemini-3.7-flash';
        }

        $request_args = array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode(array(
                'contents'         => array(array('parts' => array(array('text' => $prompt)))),
                'generationConfig' => array(
                    'responseMimeType' => 'application/json',
                    'maxOutputTokens'  => 8192,
                ),
            )),
            'timeout' => 50,
        );

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key),
            $request_args
        );

        // Retry with the previous stable Flash model if the new default is not
        // available yet for the API project's region or billing tier.
        if (!is_wp_error($response)
            && $model === 'gemini-3.7-flash'
            && wp_remote_retrieve_response_code($response) === 404) {
            $response = wp_remote_post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . rawurlencode($key),
                $request_args
            );
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $api_message = '';
            if (is_array($body) && isset($body['error']['message']) && is_string($body['error']['message'])) {
                $api_message = sanitize_text_field($body['error']['message']);
            }

            if ($api_message === '') {
                $api_message = __('Unknown Gemini API error.', 'ai-review-generator-pro');
            }

            return new \WP_Error('gemini_api_error', sprintf(
                __('Gemini API error %1$d: %2$s', 'ai-review-generator-pro'),
                $status,
                $api_message
            ));
        }

        if (!is_array($body)) {
            return new \WP_Error(
                'gemini_invalid_response',
                __('Gemini returned an invalid response. Please try again.', 'ai-review-generator-pro')
            );
        }

        $candidate = $body['candidates'][0] ?? array();
        $parts = $candidate['content']['parts'] ?? array();
        $text_parts = array();

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part)
                    && empty($part['thought'])
                    && isset($part['text'])
                    && is_string($part['text'])
                    && trim($part['text']) !== '') {
                    $text_parts[] = $part['text'];
                }
            }
        }

        if (empty($text_parts)) {
            $finish_reason = isset($candidate['finishReason']) && is_string($candidate['finishReason'])
                ? $candidate['finishReason']
                : 'UNKNOWN';

            return new \WP_Error('gemini_empty_response', sprintf(
                __('Gemini returned no text. Finish reason: %s.', 'ai-review-generator-pro'),
                sanitize_text_field($finish_reason)
            ));
        }

        return $this->parse_json(implode("\n", $text_parts));
    }

    /**
     * Call OpenRouter API
     *
     * @since 8.0.0
     * @param string $prompt The prompt to send.
     * @return array|\WP_Error Parsed response or error.
     */
    private function call_openrouter($prompt) {
        $key = $this->options['openrouter_key'] ?? '';
        if (empty($key)) {
            return new \WP_Error('missing_key', __('OpenRouter API key is missing', 'ai-review-generator-pro'));
        }

        $model = $this->options['openrouter_model'] ?? 'google/gemini-2.0-flash:grounded';
        
        $body = array(
            'model'       => $model,
            'messages'    => array(
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens'  => 8192,
            'temperature' => 0.6,
        );

        // Always enable web search for content generation to ensure factual accuracy
        if (strpos($model, 'grounded') !== false) {
            $body['model'] = 'google/gemini-2.0-flash';
        }
        $body['tools'] = array(
            array('type' => 'web_search')
        );

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => get_bloginfo('name'),
            ),
            'body'    => json_encode($body),
            'timeout' => 50,
        ));

        return $this->parse_openai_response($response);
    }

    /**
     * Call OpenCode Zen API
     *
     * OpenCode Zen provides free AI models via an OpenAI-compatible endpoint.
     *
     * @since 8.0.0
     * @param string $prompt The prompt to send.
     * @return array|\WP_Error Parsed response or error.
     */
    private function call_opencodezen($prompt) {
        $key = $this->options['opencodezen_key'] ?? '';
        if (empty($key)) {
            return new \WP_Error('missing_key', __('OpenCode Zen API key is missing', 'ai-review-generator-pro'));
        }

        $model = $this->options['opencodezen_model'] ?? 'deepseek-v4-flash-free';

        $response = wp_remote_post('https://opencode.ai/zen/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array(
                'model'       => $model,
                'messages'    => array(
                    array('role' => 'system', 'content' => 'You are a JSON generator. You ONLY output valid JSON objects. Never include explanations or markdown. You MUST only include facts explicitly provided in the user prompt. Do NOT invent or hallucinate any information.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens'  => 16384,
                'temperature' => 0.4,
            )),
            'timeout' => 50,
        ));

        return $this->parse_openai_response($response);
    }

    /**
     * Parse OpenAI-style API response
     *
     * @since 8.0.0
     * @param array|\WP_Error $response The API response.
     * @return array|\WP_Error Parsed content or error.
     */
    private function parse_openai_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $api_message = '';
            if (is_array($body)) {
                if (isset($body['error']['message']) && is_string($body['error']['message'])) {
                    $api_message = $body['error']['message'];
                } elseif (isset($body['message']) && is_string($body['message'])) {
                    $api_message = $body['message'];
                }
            }

            if ($api_message !== '') {
                return new \WP_Error('api_error', sprintf(
                    __('API error %1$d: %2$s', 'ai-review-generator-pro'),
                    $code,
                    sanitize_text_field($api_message)
                ));
            }

            return new \WP_Error('api_error', sprintf(__('API error: %d', 'ai-review-generator-pro'), $code));
        }

        return $this->parse_json($body['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Parse JSON from AI response text
     *
     * Implements a multi-pass strategy to handle common AI response issues:
     * 1. Try raw decode first (fastest path)
     * 2. Strip markdown wrappers and extract JSON block
     * 3. Fix smart quotes, control chars, and trailing commas
     * 4. Attempt to repair truncated JSON by closing open braces/brackets
     * 5. Regex fallback to extract key fields from broken JSON
     *
     * @since 8.0.0
     * @param string $text The text containing JSON.
     * @return array|\WP_Error Parsed array or error.
     */
    private function parse_json($text) {
        if (empty($text)) {
            error_log('AI Review Generator - Empty response received from AI provider');
            return new \WP_Error('empty_response', __('AI returned an empty response. The model may have hit its output limit. Try a different provider or model.', 'ai-review-generator-pro'));
        }

        // Pass 1: Try raw decode (handles well-behaved providers like Gemini with responseMimeType)
        $data = json_decode(trim($text), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['title'])) {
            return $data;
        }

        // Pass 2: Strip markdown wrappers and extract JSON block
        $cleaned = preg_replace('/```(?:json)?\s*/i', '', $text);
        $cleaned = trim($cleaned);

        $first_brace = strpos($cleaned, '{');
        $last_brace = strrpos($cleaned, '}');

        if ($first_brace === false) {
            error_log('AI Review Generator - No opening brace found. Response starts with: ' . substr($text, 0, 200));
            return new \WP_Error('parse_error', __('Could not find JSON in AI response. The AI may have returned plain text instead of JSON.', 'ai-review-generator-pro'));
        }

        // If no closing brace, the response was likely truncated (max_tokens hit)
        $is_truncated = ($last_brace === false || $last_brace < $first_brace);
        if ($is_truncated) {
            $json_string = substr($cleaned, $first_brace);
            error_log('AI Review Generator - Response appears truncated (no closing brace). Length: ' . strlen($text));
        } else {
            $json_string = substr($cleaned, $first_brace, $last_brace - $first_brace + 1);
        }

        // Pass 3: Fix common AI response issues (preserve non-ASCII content in strings)
        // Replace control characters (except within already-escaped sequences)
        $json_string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $json_string);
        $json_string = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $json_string);

        // Fix smart quotes (these break JSON parsing)
        $json_string = str_replace(
            ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99"],
            ['\"', '\"', "'", "'"],
            $json_string
        );
        // Fix dashes and special chars
        $json_string = str_replace(
            ["\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6", "\xc2\xa0"],
            ['-', '-', '...', ' '],
            $json_string
        );

        // Remove trailing commas before } or ]
        $json_string = preg_replace('/,\s*([\}\]])/', '$1', $json_string);

        // Normalize whitespace
        $json_string = preg_replace('/\s+/', ' ', $json_string);

        // Try decode
        $data = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['title'])) {
            return $data;
        }

        // Pass 4: Attempt to repair truncated JSON by closing open structures
        if ($is_truncated || json_last_error() !== JSON_ERROR_NONE) {
            $repaired = $this->repair_truncated_json($json_string);
            $data = json_decode($repaired, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['title'])) {
                error_log('AI Review Generator - Recovered truncated JSON via brace repair');
                return $data;
            }
        }

        // Pass 5: Regex fallback - extract key fields from broken JSON
        $data = $this->regex_extract_fields($json_string);
        if (!empty($data['title'])) {
            error_log('AI Review Generator - Recovered partial data via regex fallback. Fields: ' . implode(', ', array_keys($data)));
            return $data;
        }

        // All passes failed
        $error_msg = json_last_error_msg();
        error_log('AI Review Generator - JSON Parse FAILED after all passes. Error: ' . $error_msg);
        error_log('AI Review Generator - Response length: ' . strlen($text) . ' | First 500 chars: ' . substr($json_string, 0, 500));
        return new \WP_Error('json_error', sprintf(
            __('JSON decode error: %s. Response length was %d chars — it may have been truncated. Try using Gemini or increasing max tokens.', 'ai-review-generator-pro'),
            $error_msg,
            strlen($text)
        ));
    }

    /**
     * Attempt to repair truncated JSON by closing unclosed structures
     *
     * @since 8.0.0
     * @param string $json_string Potentially truncated JSON.
     * @return string Repaired JSON string.
     */
    private function repair_truncated_json($json_string) {
        // Remove any trailing partial string value (cut mid-sentence)
        // Look for the last complete key-value pair
        $json_string = preg_replace('/,\s*"[^"]*"\s*:\s*"[^"]*$/', '', $json_string);
        $json_string = preg_replace('/,\s*"[^"]*"\s*:\s*$/', '', $json_string);
        $json_string = preg_replace('/,\s*"[^"]*$/', '', $json_string);
        // Remove trailing comma
        $json_string = preg_replace('/,\s*$/', '', $json_string);

        // Count unclosed braces and brackets
        $open_braces = 0;
        $open_brackets = 0;
        $in_string = false;
        $escape = false;
        $len = strlen($json_string);

        for ($i = 0; $i < $len; $i++) {
            $char = $json_string[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $in_string = !$in_string;
                continue;
            }
            if (!$in_string) {
                if ($char === '{') $open_braces++;
                elseif ($char === '}') $open_braces--;
                elseif ($char === '[') $open_brackets++;
                elseif ($char === ']') $open_brackets--;
            }
        }

        // If we're inside a string, close it
        if ($in_string) {
            $json_string .= '"';
        }

        // Close any open brackets then braces
        while ($open_brackets > 0) {
            $json_string .= ']';
            $open_brackets--;
        }
        while ($open_braces > 0) {
            $json_string .= '}';
            $open_braces--;
        }

        return $json_string;
    }

    /**
     * Extract key fields from broken JSON using regex
     *
     * @since 8.0.0
     * @param string $json_string The broken JSON string.
     * @return array Extracted fields (may be partial).
     */
    private function regex_extract_fields($json_string) {
        $data = array();

        // Extract simple string fields
        $simple_fields = array('title', 'rating', 'meta_description', 'review_date', 'comparison_verdict');
        foreach ($simple_fields as $field) {
            if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $json_string, $m)) {
                $data[$field] = stripslashes($m[1]);
            }
        }

        // Extract nested introduction fields
        $intro_fields = array('hook', 'pain_point', 'context', 'experience_statement', 'what_to_expect');
        foreach ($intro_fields as $field) {
            if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $json_string, $m)) {
                if (!isset($data['introduction'])) $data['introduction'] = array();
                $data['introduction'][$field] = stripslashes($m[1]);
            }
        }

        // Extract product overview fields
        $overview_fields = array('what_is_it', 'creator_info', 'problem_solved', 'unique_value');
        foreach ($overview_fields as $field) {
            if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $json_string, $m)) {
                if (!isset($data['product_overview'])) $data['product_overview'] = array();
                $data['product_overview'][$field] = stripslashes($m[1]);
            }
        }

        // Extract pros array
        if (preg_match('/"pros"\s*:\s*\[((?:[^\[\]]|\[(?:[^\[\]])*\])*)\]/', $json_string, $m)) {
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $m[1], $items);
            if (!empty($items[1])) {
                $data['pros'] = array_map('stripslashes', $items[1]);
            }
        }

        // Extract cons array
        if (preg_match('/"cons"\s*:\s*\[((?:[^\[\]]|\[(?:[^\[\]])*\])*)\]/', $json_string, $m)) {
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $m[1], $items);
            if (!empty($items[1])) {
                $data['cons'] = array_map('stripslashes', $items[1]);
            }
        }

        return $data;
    }
}
