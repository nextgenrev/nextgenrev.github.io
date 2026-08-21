<?php
/**
 * Web Scraper Class
 *
 * Handles scraping product information from affiliate URLs.
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
 * Web Scraper Class
 *
 * @since 8.0.0
 */
class WebScraper {

    /**
     * Plugin options for API access
     *
     * @var array
     */
    private $options;

    /**
     * Constructor
     *
     * @since 8.0.0
     * @param array $options Plugin options for API access.
     */
    public function __construct($options = array()) {
        $this->options = $options;
    }

    /**
     * Scrape content from a URL
     *
     * @since 8.0.0
     * @param string $url              The URL to scrape.
     * @param bool   $include_research Whether to run the slower AI research pass.
     * @return array Scraped data including title, description, content, features, pricing, offer, comparison.
     */
    public function scrape($url, $include_research = true) {
        $result = array(
            'title'              => '',
            'description'        => '',
            'content'            => '',
            'features'           => array(),
            'pricing'            => '',
            'offer'              => '',
            'comparison'         => array(),
            'pricing_tiers'      => array(),
            'integrations'       => array(),
            'community_feedback' => array(),
        );

        $response = wp_remote_get($url, array(
            'timeout'    => 30,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        ));

        if (is_wp_error($response)) {
            return $result;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return $result;
        }

        // Parse HTML
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        $xpath = new \DOMXPath($doc);
        libxml_clear_errors();

        // Extract title
        $result['title'] = $this->extract_title($xpath);

        // Extract description
        $result['description'] = $this->extract_description($xpath);

        // Extract content
        $result['content'] = $this->extract_content($xpath);

        // Extract features
        $result['features'] = $this->extract_features($xpath);

        // Extract pricing from HTML first
        $result['pricing'] = $this->extract_pricing($html);

        // Get clean product name for web searches
        $product_name = '';
        if (!empty($result['title'])) {
            $product_name = $this->extract_product_name($result['title']);
        }

        // Keep the research pass optional so the browser can run it in a
        // separate request. This avoids combining two remote AI calls behind
        // a host or proxy's per-request timeout.
        if ($include_research) {
            $result = $this->enrich_with_research($result, $product_name);
        }

        return $result;
    }

    /**
     * Add pricing, offer, comparison, integration, and community research.
     *
     * This is public so the admin generation flow can run research as its own
     * short AJAX stage after the product page has been scraped.
     *
     * @since 8.0.1
     * @param array  $result       Basic scraped product data.
     * @param string $product_name Optional product name fallback.
     * @return array Enriched scraped data.
     */
    public function enrich_with_research($result, $product_name = '') {
        if (!is_array($result)) {
            $result = array();
        }

        if (empty($product_name) && !empty($result['title'])) {
            $product_name = $this->extract_product_name($result['title']);
        }

        if (empty($product_name)) {
            return $result;
        }

        // Use one batched grounded AI call instead of six sequential calls.
        $existing_price = $result['pricing'] ?? '';
        $web_data = $this->batch_web_research($product_name, $existing_price);

        if (!empty($web_data['pricing']) && empty($result['pricing'])) {
            $result['pricing'] = $web_data['pricing'];
            error_log('AI Review Generator - Price found via batched web search: ' . $web_data['pricing']);
        }
        if (!empty($web_data['offer'])) {
            $result['offer'] = $web_data['offer'];
        }
        if (!empty($web_data['comparison'])) {
            $result['comparison'] = $web_data['comparison'];
        }
        if (!empty($web_data['pricing_tiers'])) {
            $result['pricing_tiers'] = $web_data['pricing_tiers'];
        }
        if (!empty($web_data['integrations'])) {
            $result['integrations'] = $web_data['integrations'];
        }
        if (!empty($web_data['community_feedback'])) {
            $result['community_feedback'] = $web_data['community_feedback'];
        }

        return $result;
    }

    /**
     * Perform all web research in a single grounded AI call
     *
     * Combines price lookup, offers, comparisons, pricing tiers, integrations,
     * and community feedback into one prompt to avoid sequential API call timeouts.
     *
     * @since 8.0.0
     * @param string $product_name    The product name.
     * @param string $existing_price  Price already found via HTML scraping (if any).
     * @return array Parsed web research data.
     */
    private function batch_web_research($product_name, $existing_price = '') {
        $price_instruction = empty($existing_price) 
            ? 'Search for the EXACT current price. Check MunchEye.com, JVZoo, WarriorPlus, or the official sales page. Return the lowest/entry-level ONE-TIME price from 2024-2026. Format: "$XX.00"'
            : 'Price already found: ' . $existing_price . '. Set pricing to empty string.';

        $prompt = 'You are a product research assistant. Search the web and gather ALL of the following data about "' . $product_name . '" in a SINGLE response.

CRITICAL ACCURACY RULES:
- ONLY include information you can VERIFY from actual web search results.
- If you cannot find verified information for a section, return empty values.
- Do NOT guess, assume, or fabricate ANY data. Real accuracy > completeness.
- Prefer official sources (product website, official pricing page, verified review sites).
- Include ONLY tools/products that actually exist with their REAL current prices.

RESEARCH TASKS:

1. PRICING: ' . $price_instruction . '

2. SPECIAL OFFERS: Search for current launch bonuses, discount codes, bundle deals, OTOs, or limited-time offers. Provide a brief 1-2 sentence summary. Only include offers you actually find on the web.

3. COMPETITORS: Find 3 real competitor/alternative tools with their actual names, current verified prices, and 1-sentence descriptions. ONLY include tools that genuinely exist and compete in the same space. Do NOT fabricate tool names or prices.

4. PRICING TIERS: Find all available pricing plans (Starter, Pro, Enterprise, etc.) with exact prices and key features for each. Only include tiers actually listed on the product website or verified sources.

5. INTEGRATIONS: Find what third-party apps/platforms this tool actually integrates with. Only list integrations that are documented/verified. List up to 8.

6. COMMUNITY FEEDBACK: Search Reddit, G2, Product Hunt, and forums. Summarize overall sentiment (50-70 words), list common praises and complaints. Only include opinions you actually find. If no community discussion exists, return empty values.

RESPOND WITH ONLY THIS JSON (no other text):
{
  "pricing": "$XX.00 or empty string",
  "offer": "Special offer summary or empty string",
  "comparison": [
    {"name": "Tool Name", "price": "$XX/month", "description": "Brief description"}
  ],
  "pricing_tiers": [
    {"name": "Plan Name", "price": "$XX", "features": ["Feature A", "Feature B"]}
  ],
  "integrations": ["App1", "App2", "App3"],
  "community_feedback": {
    "reddit_sentiment": "Summary paragraph...",
    "common_praise": ["Point 1", "Point 2", "Point 3"],
    "common_complaints": ["Point 1", "Point 2"]
  }
}

Use empty strings, empty arrays, or empty objects for any data you CANNOT VERIFY from web search results. Return ONLY valid JSON.';

        $text = $this->call_grounded_ai($prompt, 2500);
        error_log('AI Review Generator - Batched web research response length: ' . strlen($text));

        $result = array(
            'pricing'            => '',
            'offer'              => '',
            'comparison'         => array(),
            'pricing_tiers'      => array(),
            'integrations'       => array(),
            'community_feedback' => array(),
        );

        if (empty($text)) {
            return $result;
        }

        // Extract JSON from response
        $text = preg_replace('/```(?:json)?\s*/i', '', $text);
        $text = trim($text);

        $first_brace = strpos($text, '{');
        $last_brace = strrpos($text, '}');

        if ($first_brace === false || $last_brace === false) {
            error_log('AI Review Generator - Batched web research: No JSON found');
            return $result;
        }

        $json_string = substr($text, $first_brace, $last_brace - $first_brace + 1);
        $data = json_decode($json_string, true);

        if (!is_array($data)) {
            error_log('AI Review Generator - Batched web research: JSON decode failed: ' . json_last_error_msg());
            return $result;
        }

        // Extract and validate pricing
        if (!empty($data['pricing']) && preg_match('/\$\s?(\d+(?:\.\d{1,2})?)/', $data['pricing'], $m)) {
            $price_value = floatval($m[1]);
            if ($price_value >= 7 && $price_value <= 497) {
                $result['pricing'] = '$' . number_format($price_value, 2);
            }
        }

        // Extract offer
        if (!empty($data['offer']) && $data['offer'] !== 'NOT_FOUND') {
            $result['offer'] = sanitize_text_field($data['offer']);
        }

        // Extract comparison
        if (!empty($data['comparison']) && is_array($data['comparison'])) {
            $valid = array();
            foreach ($data['comparison'] as $comp) {
                if (isset($comp['name']) && isset($comp['price'])) {
                    $valid[] = array(
                        'name'        => sanitize_text_field($comp['name']),
                        'price'       => sanitize_text_field($comp['price']),
                        'description' => sanitize_text_field($comp['description'] ?? '')
                    );
                }
            }
            $result['comparison'] = array_slice($valid, 0, 4);
        }

        // Extract pricing tiers
        if (!empty($data['pricing_tiers']) && is_array($data['pricing_tiers'])) {
            $result['pricing_tiers'] = $data['pricing_tiers'];
        }

        // Extract integrations
        if (!empty($data['integrations']) && is_array($data['integrations'])) {
            $result['integrations'] = array_slice($data['integrations'], 0, 8);
        }

        // Extract community feedback
        if (!empty($data['community_feedback']) && is_array($data['community_feedback'])) {
            $result['community_feedback'] = $data['community_feedback'];
        }

        return $result;
    }

    /**
     * Extract clean product name from page title
     *
     * @since 8.0.0
     * @param string $title The page title.
     * @return string Clean product name.
     */
    private function extract_product_name($title) {
        // Remove common suffixes and separators
        $title = preg_replace('/\s*[-–—|]\s*.*/i', '', $title);
        $title = preg_replace('/\s*(Review|Official|Site|Website|Buy|Get|Order).*$/i', '', $title);
        $title = trim($title);
        return $title;
    }

    /**
     * Search for product price online using Gemini with grounding
     *
     * Uses Google Search grounding to find verified pricing from official sources.
     *
     * @since 8.0.0
     * @param string $product_name The product name to search for.
     * @return string The price found, or empty string.
     */
    private function search_price_online($product_name) {
        if (empty($product_name)) {
            return '';
        }

        $search_prompt = 'You are a price research assistant. Search the web and find the EXACT current price for "' . $product_name . '".

SEARCH PRIORITY (check these sources in order):
1. MunchEye.com - Check for product launch details and front-end price
2. JVZoo or WarriorPlus product pages
3. The official product sales page
4. Recent reviews or launch announcements

IMPORTANT RULES:
- Find the ACTUAL price displayed on the sales page or launch platform
- If there are multiple pricing tiers (Starter, Pro, Enterprise), return the LOWEST/entry-level price
- Only return ONE-TIME payment prices, NOT monthly subscriptions
- The price must be from 2024, 2025, or 2026 - ignore old outdated prices
- If you find prices like "$37 Starter" and "$47 Pro", return $37

RESPONSE FORMAT:
Reply with ONLY the price in this exact format: $XX or $XX.XX
Examples: $37 or $27 or $17.00
If you cannot find a verified price, reply exactly: NOT_FOUND

What is the verified price for "' . $product_name . '"?';

        $text = $this->call_grounded_ai($search_prompt);
        error_log('AI Review Generator - Price search response: ' . $text);
        
        // Check if price was not found
        if (stripos($text, 'NOT_FOUND') !== false || stripos($text, 'not found') !== false) {
            return '';
        }
        
        // Extract price from response - look for dollar amounts
        if (preg_match('/\$\s?(\d+(?:\.\d{1,2})?)/', $text, $match)) {
            $price_value = floatval($match[1]);
            
            // Validate the price is in a reasonable range for digital products
            if ($price_value >= 7 && $price_value <= 497) {
                // Format price consistently
                if (floor($price_value) == $price_value) {
                    return '$' . intval($price_value) . '.00';
                } else {
                    return '$' . number_format($price_value, 2);
                }
            }
        }

        return '';
    }

    /**
     * Search for special offers and bonuses online using grounded AI
     *
     * @since 8.0.0
     * @param string $product_name The product name to search for.
     * @return string Special offer details found, or empty string.
     */
    private function search_offer_online($product_name) {
        if (empty($product_name)) {
            return '';
        }

        $search_prompt = 'Search the web for current special offers, bonuses, or discounts for "' . $product_name . '".

LOOK FOR:
1. Launch bonuses or early bird bonuses
2. Special discount codes or coupons
3. Bundle deals or OTO (one-time offer) details
4. Limited time offers
5. Free bonuses included with purchase

RESPONSE FORMAT:
Provide a brief 1-2 sentence summary of the CURRENT special offer.
Example: "Launch special: Get 50% off plus 5 exclusive bonuses worth $997"
Example: "Early bird discount available until January 10th"

If no special offers found, reply exactly: NOT_FOUND';

        $text = $this->call_grounded_ai($search_prompt);
        
        if (stripos($text, 'NOT_FOUND') !== false || empty($text)) {
            return '';
        }

        return $text;
    }

    /**
     * Search for competitor comparison data using grounded AI
     *
     * @since 8.0.0
     * @param string $product_name The product name to search for.
     * @return array Array of competitor tools with names and prices.
     */
    private function search_comparison_online($product_name) {
        if (empty($product_name)) {
            return array();
        }

        $search_prompt = 'Search the web and find 3-4 competitor or alternative tools to "' . $product_name . '".

For each competitor, find:
1. The tool name
2. The current price (one-time or monthly)
3. A brief description (1 sentence)

RESPONSE FORMAT (use this exact JSON format):
[
  {"name": "Competitor 1 Name", "price": "$XX/month or $XX one-time", "description": "Brief description"},
  {"name": "Competitor 2 Name", "price": "$XX/month or $XX one-time", "description": "Brief description"},
  {"name": "Competitor 3 Name", "price": "$XX/month or $XX one-time", "description": "Brief description"}
]

Only include REAL competitors with REAL prices. Do not make up products.
If no competitors found, reply exactly: []';

        $text = $this->call_grounded_ai($search_prompt);
        error_log('AI Review Generator - Comparison search response: ' . $text);

        // Try to extract JSON array from response
        if (preg_match('/\[.*\]/s', $text, $match)) {
            $json_str = $match[0];
            $competitors = json_decode($json_str, true);
            
            if (is_array($competitors) && !empty($competitors)) {
                // Validate and clean the data
                $valid_competitors = array();
                foreach ($competitors as $comp) {
                    if (isset($comp['name']) && isset($comp['price'])) {
                        $valid_competitors[] = array(
                            'name'        => sanitize_text_field($comp['name']),
                            'price'       => sanitize_text_field($comp['price']),
                            'description' => sanitize_text_field($comp['description'] ?? '')
                        );
                    }
                }
                return array_slice($valid_competitors, 0, 4); // Max 4 competitors
            }
        }

        return array();
    }

    /**
     * Search for pricing tiers online using grounded AI
     *
     * @since 8.0.0
     * @param string $product_name The product name to search for.
     * @return array Array of pricing tiers.
     */
    private function search_pricing_tiers($product_name) {
        if (empty($product_name)) {
            return array();
        }

        $search_prompt = 'Search the web for the current pricing plans or pricing tiers of the product "' . $product_name . '".
Include all available plans (such as Starter, Pro, Enterprise, Unlimited, Front-End, etc.) and their prices (one-time or monthly/yearly).

RESPONSE FORMAT (use this exact JSON format):
[
  {"name": "Tier Name 1", "price": "$XX/month or $XX one-time", "features": ["Feature A", "Feature B"]},
  {"name": "Tier Name 2", "price": "$XX/month or $XX one-time", "features": ["Feature A", "Feature B", "Feature C"]}
]

Only return the JSON array of tiers. Do not write any other explanation or text.
If no multiple tiers are found, reply exactly: []';

        $text = $this->call_grounded_ai($search_prompt);
        error_log('AI Review Generator - Pricing tiers search response: ' . $text);

        if (preg_match('/\[.*\]/s', $text, $match)) {
            $tiers = json_decode($match[0], true);
            if (is_array($tiers)) {
                return $tiers;
            }
        }
        return array();
    }

    /**
     * Search for tool integrations online using grounded AI
     *
     * @since 8.0.0
     * @param string $product_name The product name.
     * @return array Array of integration names.
     */
    private function search_integrations_online($product_name) {
        if (empty($product_name)) {
            return array();
        }

        $search_prompt = 'Search the web to find what third-party apps, software, or platforms "' . $product_name . '" integrates with (e.g. Zapier, WordPress, Mailchimp, Stripe, Shopify, ActiveCampaign, etc.).

RESPONSE FORMAT (use this exact JSON format):
["App 1", "App 2", "App 3", "App 4", "App 5"]

Only return the JSON array of app names (max 8 apps). Do not write any other text.
If no integrations are found, reply exactly: []';

        $text = $this->call_grounded_ai($search_prompt);
        error_log('AI Review Generator - Integrations search response: ' . $text);

        if (preg_match('/\[.*\]/s', $text, $match)) {
            $integrations = json_decode($match[0], true);
            if (is_array($integrations)) {
                return $integrations;
            }
        }
        return array();
    }

    /**
     * Search for community feedback online using grounded AI
     *
     * @since 8.0.0
     * @param string $product_name The product name.
     * @return array Array of community opinions.
     */
    private function search_community_feedback($product_name) {
        if (empty($product_name)) {
            return array();
        }

        $search_prompt = 'Search Reddit, G2, Product Hunt, and online forums to find what real users say about "' . $product_name . '".
Identify the overall sentiment, common praises, and common complaints/frustrations.

RESPONSE FORMAT (use this exact JSON format):
{
  "reddit_sentiment": "A summary paragraph (50-70 words) of user opinions and discussions on Reddit and other forums.",
  "common_praise": ["Praise point 1", "Praise point 2", "Praise point 3"],
  "common_complaints": ["Complaint/limitation point 1", "Complaint/limitation point 2"]
}

Only return the JSON object. Do not write any other explanation or text.
If no feedback is found, reply exactly: {}';

        $text = $this->call_grounded_ai($search_prompt);
        error_log('AI Review Generator - Community feedback search response: ' . $text);

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $feedback = json_decode($match[0], true);
            if (is_array($feedback)) {
                return $feedback;
            }
        }
        return array();
    }

    /**
     * Call the appropriate AI model with grounding capabilities
     *
     * @since 8.0.0
     * @param string $prompt     The prompt to run.
     * @param int    $max_tokens Maximum tokens for response (default 1000).
     * @return string The AI response text, or empty string.
     */
    private function call_grounded_ai($prompt, $max_tokens = 1000) {
        $provider = $this->options['ai_provider'] ?? 'gemini';
        
        if ($provider === 'openrouter' || (!empty($this->options['openrouter_key']) && empty($this->options['gemini_key']))) {
            $key = $this->options['openrouter_key'] ?? '';
            if (empty($key)) {
                $plugin_options = get_option('ai_gen_options', array());
                $key = $plugin_options['openrouter_key'] ?? '';
            }
            
            if (empty($key)) {
                error_log('AI Review Generator - No OpenRouter API key for grounding search');
                return '';
            }
            
            $model = $this->options['openrouter_model'] ?? 'google/gemini-2.0-flash:grounded';
            if (strpos($model, ':grounded') !== false) {
                $model = str_replace(':grounded', '', $model);
            }
            
            $body = array(
                'model'    => $model,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'tools'    => array(
                    array('type' => 'web_search')
                ),
                'temperature' => 0.0,
                'max_tokens' => $max_tokens,
            );
            
            $response = wp_remote_post(
                'https://openrouter.ai/api/v1/chat/completions',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $key,
                        'Content-Type'  => 'application/json',
                        'HTTP-Referer'  => get_site_url(),
                        'X-Title'       => get_bloginfo('name'),
                    ),
                    'body'    => json_encode($body),
                    'timeout' => 45,
                )
            );
            
            if (is_wp_error($response)) {
                error_log('AI Review Generator - OpenRouter grounding search connection error: ' . $response->get_error_message());
                return '';
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                error_log('AI Review Generator - OpenRouter grounding search response code: ' . $code);
                return '';
            }
            
            $body_data = json_decode(wp_remote_retrieve_body($response), true);
            return trim($body_data['choices'][0]['message']['content'] ?? '');
        } else {
            // Direct Gemini call
            $gemini_key = $this->options['gemini_key'] ?? '';
            if (empty($gemini_key)) {
                $plugin_options = get_option('ai_gen_options', array());
                $gemini_key = $plugin_options['gemini_key'] ?? '';
            }
            
            if (empty($gemini_key)) {
                error_log('AI Review Generator - No Gemini API key for grounding search');
                return '';
            }
            
            $response = wp_remote_post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $gemini_key,
                array(
                    'headers' => array('Content-Type' => 'application/json'),
                    'body'    => json_encode(array(
                        'contents' => array(
                            array(
                                'parts' => array(
                                    array('text' => $prompt)
                                )
                            )
                        ),
                        'tools' => array(
                            array(
                                'google_search' => new \stdClass()
                            )
                        ),
                        'generationConfig' => array(
                            'temperature' => 0.0,
                            'maxOutputTokens' => $max_tokens
                        )
                    )),
                    'timeout' => 45,
                )
            );
            
            if (is_wp_error($response)) {
                error_log('AI Review Generator - Gemini grounding search connection error: ' . $response->get_error_message());
                return '';
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                error_log('AI Review Generator - Gemini grounding search response code: ' . $code);
                return '';
            }
            
            $body_data = json_decode(wp_remote_retrieve_body($response), true);
            
            $text = '';
            if (isset($body_data['candidates'][0]['content']['parts'])) {
                foreach ($body_data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
            }
            return trim($text);
        }
    }

    /**
     * Extract page title
     *
     * @since 8.0.0
     * @param \DOMXPath $xpath The XPath object.
     * @return string The page title.
     */
    private function extract_title($xpath) {
        // Try Open Graph title first
        $og_title = $xpath->query('//meta[@property="og:title"]/@content');
        if ($og_title->length > 0) {
            return $og_title->item(0)->nodeValue;
        }

        // Fallback to title tag
        $title_tag = $xpath->query('//title');
        if ($title_tag->length > 0) {
            return $title_tag->item(0)->nodeValue;
        }

        return '';
    }

    /**
     * Extract page description
     *
     * @since 8.0.0
     * @param \DOMXPath $xpath The XPath object.
     * @return string The page description.
     */
    private function extract_description($xpath) {
        // Try Open Graph description
        $og_desc = $xpath->query('//meta[@property="og:description"]/@content');
        if ($og_desc->length > 0) {
            return $og_desc->item(0)->nodeValue;
        }

        // Fallback to meta description
        $meta_desc = $xpath->query('//meta[@name="description"]/@content');
        if ($meta_desc->length > 0) {
            return $meta_desc->item(0)->nodeValue;
        }

        return '';
    }

    /**
     * Extract page content from headings and paragraphs
     *
     * @since 8.0.0
     * @param \DOMXPath $xpath The XPath object.
     * @return string The extracted content.
     */
    private function extract_content($xpath) {
        $content_parts = array();

        // Extract from headings
        $headings = $xpath->query('//h1|//h2|//h3|//h4');
        foreach ($headings as $h) {
            $text = trim($h->textContent);
            if (strlen($text) > 5 && strlen($text) < 200) {
                $content_parts[] = '[HEADING] ' . $text;
            }
        }

        // Extract from paragraphs
        $paragraphs = $xpath->query('//p');
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);
            if (strlen($text) > 20 && strlen($text) < 1000) {
                $content_parts[] = $text;
            }
        }

        // Extract from divs with meaningful text (sales pages often use divs instead of p)
        $divs = $xpath->query('//div[not(descendant::div) and not(descendant::p)]');
        foreach ($divs as $div) {
            $text = trim($div->textContent);
            if (strlen($text) > 40 && strlen($text) < 500) {
                $content_parts[] = $text;
            }
        }

        // Extract from spans with substantial text (common in landing pages)
        $spans = $xpath->query('//span[string-length(normalize-space(.)) > 50]');
        foreach ($spans as $span) {
            $text = trim($span->textContent);
            if (strlen($text) > 50 && strlen($text) < 500 && !in_array($text, $content_parts)) {
                $content_parts[] = $text;
            }
        }

        return implode("\n", array_slice($content_parts, 0, 30));
    }

    /**
     * Extract features from list items and feature-like sections
     *
     * @since 8.0.0
     * @param \DOMXPath $xpath The XPath object.
     * @return array Array of feature strings.
     */
    private function extract_features($xpath) {
        $features = array();

        // Extract from list items
        $list_items = $xpath->query('//ul/li|//ol/li');
        foreach ($list_items as $li) {
            $text = trim($li->textContent);
            if (strlen($text) > 10 && strlen($text) < 300) {
                $features[] = $text;
            }
        }

        // Extract from elements with feature-related classes/IDs
        $feature_nodes = $xpath->query('//*[contains(@class, "feature") or contains(@class, "benefit") or contains(@class, "highlight") or contains(@id, "feature")]');
        foreach ($feature_nodes as $node) {
            $text = trim($node->textContent);
            if (strlen($text) > 15 && strlen($text) < 300 && !in_array($text, $features)) {
                $features[] = $text;
            }
        }

        // Extract from checkmark/tick items (common on sales pages)
        $check_items = $xpath->query('//*[contains(@class, "check") or contains(@class, "tick") or contains(@class, "included")]');
        foreach ($check_items as $item) {
            $text = trim($item->textContent);
            if (strlen($text) > 10 && strlen($text) < 200 && !in_array($text, $features)) {
                $features[] = $text;
            }
        }

        return array_slice($features, 0, 20);
    }

    /**
     * Extract pricing information using contextual regex patterns
     *
     * @since 8.0.0
     * @param string $html The raw HTML content.
     * @return string The exact price found, or empty string.
     */
    private function extract_pricing($html) {
        // Priority 1: Look for prices near specific keywords (Front End, Buy Now, Price, etc.)
        $context_patterns = array(
            '/front[\s\-]*end[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/buy[\s\-]*now[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/get[\s\-]*access[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/price[:\s]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/only[\s]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/just[\s]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/regular[\s\-]*price[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/one[\s\-]*time[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
            '/today[^$]*\$[\s]*([\d,]+(?:\.\d{2})?)/i',
        );
        
        foreach ($context_patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $value = floatval(str_replace(',', '', $match[1]));
                if ($value > 5 && $value < 500) {
                    return '$' . number_format($value, 2);
                }
            }
        }
        
        // Priority 2: Look for prices inside button-like elements
        if (preg_match('/<(?:button|a)[^>]*class[^>]*(?:btn|button|buy|cta)[^>]*>[^<]*\$[\s]*([\d,]+(?:\.\d{2})?)/i', $html, $match)) {
            $value = floatval(str_replace(',', '', $match[1]));
            if ($value > 5 && $value < 500) {
                return '$' . number_format($value, 2);
            }
        }
        
        // Priority 3: Look for prices in common price containers
        if (preg_match('/<[^>]*class[^>]*(?:price|cost|amount)[^>]*>[^<]*\$[\s]*([\d,]+(?:\.\d{2})?)/i', $html, $match)) {
            $value = floatval(str_replace(',', '', $match[1]));
            if ($value > 5 && $value < 500) {
                return '$' . number_format($value, 2);
            }
        }
        
        // Priority 4: Fallback - find most common reasonable price
        if (preg_match_all('/\$[\s]*([\d,]+(?:\.\d{2})?)/', $html, $matches)) {
            $prices = array();
            foreach ($matches[1] as $price_str) {
                $value = floatval(str_replace(',', '', $price_str));
                // Focus on typical digital product price range
                if ($value >= 17 && $value <= 97) {
                    $prices[] = $value;
                }
            }
            
            if (!empty($prices)) {
                // Get most frequent price in the typical range
                $price_counts = array_count_values(array_map('strval', $prices));
                arsort($price_counts);
                $best_price = floatval(key($price_counts));
                return '$' . number_format($best_price, 2);
            }
            
            // Wider fallback
            $prices = array();
            foreach ($matches[1] as $price_str) {
                $value = floatval(str_replace(',', '', $price_str));
                if ($value > 10 && $value < 500) {
                    $prices[] = $value;
                }
            }
            
            if (!empty($prices)) {
                $price_counts = array_count_values(array_map('strval', $prices));
                arsort($price_counts);
                $best_price = floatval(key($price_counts));
                return '$' . number_format($best_price, 2);
            }
        }
        
        return '';
    }
}
