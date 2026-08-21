<?php
/**
 * Review Template Class
 *
 * Handles building the HTML output for generated reviews.
 * Elementor-compatible with inline styles.
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
 * Review Template Class
 *
 * Builds SEO and CRO-optimized HTML for product reviews,
 * using inline styles for full Elementor compatibility.
 *
 * @since 8.0.0
 */
class ReviewTemplate {

    /**
     * Build HTML from AI content using Elementor-compatible template
     *
     * @since 8.0.0
     * @param array  $ai      AI-generated content array.
     * @param string $link    Affiliate link.
     * @param string $product Product name.
     * @param array  $scraped Optional scraped data including comparison.
     * @return string Complete HTML output.
     */
    public function build($ai, $link, $product, $scraped = array()) {
        // Extract data from AI response
        $intro         = $ai['introduction'] ?? array();
        $overview      = $ai['product_overview'] ?? array();
        $features      = $ai['key_features'] ?? $ai['features'] ?? array();
        $ux            = $ai['user_experience'] ?? array();
        $pros          = $ai['pros'] ?? array();
        $cons          = $ai['cons'] ?? array();
        $ideal         = $ai['ideal_user'] ?? array();
        $conclusion    = $ai['conclusion'] ?? array();
        $pricing       = $ai['pricing_value'] ?? $ai['value_assessment'] ?? array();
        $faq           = $ai['faq'] ?? array();
        $rating        = $ai['rating'] ?? '4.5';
        $review_date   = $ai['review_date'] ?? date('F j, Y');

        $h2_style = 'font-family:Söhne;font-size:30px;font-weight:700;line-height:1.2;color:#000000;letter-spacing:-0.02em;margin-top:0;margin-bottom:18px;padding:0;border:none;';

        // --- Reusable inline CTA button (placed after select sections) ---
        $inline_cta = '<div class="airg-cta-wrap" style="text-align:center;margin:30px 0;">'
            . '<a class="airg-cta" href="' . esc_url($link) . '" rel="sponsored nofollow" style="display:inline-block;padding:14px 22px;font-family:Söhne;font-size:17px;font-weight:700;color:#ffffff;background-color:#1D4ED8;border:none;border-radius:8px;text-decoration:none;letter-spacing:0;line-height:1.2;">Get ' . esc_html($product) . ' Now</a>'
            . '</div>';

        // --- SEO helpers ---
        // Focus keyword phrase, reused in subheadings + body copy to lift keyword density.
        $kw = esc_html($product) . ' Review';
        // Internal link target (same domain) for Rank Math internal-link check.
        $site_home = esc_url(home_url('/'));

        // --- Helper: Generate Lists ---
        
        // Pros List
        $pros_html = '';
        $pro_icon = '<span style="display:inline-flex;align-items:center;justify-content:center;border-radius:86px;overflow:hidden;border:1px solid rgba(255,255,255,0.26);font-size:12px;padding:6px;margin:0 8px 0 0;background-color:rgba(255,255,255,0.1);flex-shrink:0;"><svg class="tcb-icon" style="width:12px;height:12px;fill:#ffffff;" viewBox="0 0 32 32" data-id="icon-check" data-name=""><path d="M29.333 10.267c0 0.4-0.133 0.8-0.533 1.2l-14.8 14.8c-0.267 0.267-0.667 0.4-1.067 0.4s-0.933-0.133-1.2-0.533l-2.4-2.267-6.267-6.267c-0.267-0.267-0.4-0.667-0.4-1.2s0.133-0.8 0.533-1.2l2.4-2.4c0.267-0.133 0.667-0.4 1.067-0.4s0.8 0.133 1.2 0.533l5.067 5.067 11.2-11.333c0.267-0.267 0.667-0.533 1.2-0.533 0.4 0 0.8 0.133 1.2 0.533l2.4 2.4c0.267 0.267 0.4 0.667 0.4 1.2z"></path></svg></span>';
        $con_icon = '<span style="display:inline-flex;align-items:center;justify-content:center;border-radius:86px;overflow:hidden;border:1px solid rgba(255,255,255,0.26);font-size:12px;padding:6px;margin:0 8px 0 0;background-color:rgba(255,255,255,0.1);flex-shrink:0;"><svg class="tcb-icon" style="width:12px;height:12px;fill:#ffffff;" viewBox="0 0 352 512" data-id="icon-times-solid" data-name=""><path d="M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z"></path></svg></span>';
        if (!empty($pros)) {
            foreach ($pros as $pro) {
                $pros_html .= '<li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $pro_icon . ' ' . esc_html($pro) . '</li>';
            }
        } else {
            $pros_html = '<li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $pro_icon . ' User-friendly interface</li><li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $pro_icon . ' Good value for money</li><li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $pro_icon . ' Helpful support</li>';
        }

        // Cons List
        $cons_html = '';
        if (!empty($cons)) {
            foreach ($cons as $con) {
                $cons_html .= '<li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $con_icon . ' ' . esc_html($con) . '</li>';
            }
        } else {
            $cons_html = '<li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $con_icon . ' Minor learning curve</li><li style="padding:10px 0;font-size:0.9em;display:flex;align-items:flex-start;gap:12px;color:#ffffff;font-weight:500;line-height:1.5;list-style:none;">' . $con_icon . ' Requires internet connection</li>';
        }

        // Features List
        $features_html = '';
        if (!empty($features)) {
            $features_html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;row-gap:25px;margin-top:20px;">';
            foreach ($features as $index => $feature) {
                $name = $feature['name'] ?? 'Feature';
                $desc = $feature['description'] ?? '';
                $take = $feature['personal_take'] ?? '';
                $num = $index + 1;
                $features_html .= '<div style="background:#ffffff;border:1px solid #eeeeee;border-radius:10px;padding:22px;">';
                $features_html .= '<h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;display:flex;align-items:center;gap:10px;font-weight:700;color:#000000;"><span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:#0050d7;color:#ffffff;border-radius:50%;font-size:0.75rem;font-weight:700;flex-shrink:0;">' . $num . '</span>' . esc_html($name) . '</h3>';
                $features_html .= '<p style="margin:0;font-size:0.88em;color:#666666;line-height:1.6;">' . esc_html($desc) . '</p>';
                if ($take) {
                    $features_html .= '<p style="margin:8px 0 0 0;font-size:0.82em;color:#0050d7;line-height:1.5;font-style:italic;"><strong>My Take:</strong> ' . esc_html($take) . '</p>';
                }
                $features_html .= '</div>';
            }
            $features_html .= '</div>';
        }

        // How It Works
        $how_it_works_html = '';
        if (!empty($overview['how_it_works_steps']) && is_array($overview['how_it_works_steps'])) {
            $how_it_works_html .= '<div id="how-it-works" style="margin-bottom:45px;">';
            $how_it_works_html .= '<h2 style="' . $h2_style . '">How ' . esc_html($product) . ' Works (Step-by-Step)</h2>';
            $how_it_works_html .= '<p style="margin-bottom:1.4em;">Getting started with ' . esc_html($product) . ' is straightforward. Here is the exact step-by-step workflow I followed during testing:</p>';
            $how_it_works_html .= '<div style="display:flex;flex-direction:column;gap:15px;margin-top:20px;">';
            foreach ($overview['how_it_works_steps'] as $index => $step) {
                $num = $index + 1;
                $how_it_works_html .= '<div style="display:flex;gap:15px;align-items:flex-start;background:#ffffff;padding:18px;border-radius:8px;border:1px solid #d8e5ff;border-left:4px solid #0050d7;">';
                $how_it_works_html .= '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;background:#0050d7;color:#ffffff;border-radius:50%;font-size:0.85rem;font-weight:700;flex-shrink:0;">' . $num . '</span>';
                $how_it_works_html .= '<div style="font-size:0.92em;line-height:1.6;color:#374151;">' . esc_html($step) . '</div>';
                $how_it_works_html .= '</div>';
            }
            $how_it_works_html .= '</div></div>';
        }

        // Who Is It For List
        $who_for_html = '';
        $target_audience = $ideal['best_for'] ?? array('Marketers', 'Entrepreneurs', 'Content Creators');
        foreach ($target_audience as $audience) {
            $who_for_html .= '<li><strong>' . esc_html($audience) . '</strong></li>';
        }

        // Who Should Avoid
        $who_not_html = '';
        $not_for = $ideal['not_for'] ?? array();
        if (!empty($not_for)) {
            $who_not_html .= '<div id="who-should-avoid" style="margin-bottom:45px;">';
            $who_not_html .= '<h2 style="' . $h2_style . '">Who Should Avoid ' . esc_html($product) . '?</h2>';
            $who_not_html .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">No tool is right for everyone, and this ' . $kw . ' would not be honest without saying so. Based on my testing, ' . esc_html($product) . ' is probably not the best fit if:</p>';
            $who_not_html .= '<ul style="margin-bottom:1.4em;padding-left:0;list-style:none;">';
            foreach ($not_for as $avoid) {
                $who_not_html .= '<li style="padding:6px 0;font-size:0.92em;display:flex;align-items:flex-start;gap:10px;color:#374151;line-height:1.6;">';
                $who_not_html .= '<span style="color:#dc2626;font-weight:bold;margin-right:5px;">✕</span>' . esc_html($avoid) . '</li>';
            }
            $who_not_html .= '</ul>';
            $who_not_html .= $inline_cta;
            $who_not_html .= '</div>';
        }

        // Integrations
        $integrations_html = '';
        $integrations = $ai['integrations'] ?? $scraped['integrations'] ?? array();
        if (!empty($integrations)) {
            $integrations_html .= '<div id="integrations" style="margin-bottom:45px;">';
            $integrations_html .= '<h2 style="' . $h2_style . '">Supported Integrations</h2>';
            $integrations_html .= '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:15px;">';
            foreach ($integrations as $item) {
                $integrations_html .= '<span style="background:#eef4ff;color:#003fa8;border:1px solid #bed3ff;padding:6px 12px;border-radius:20px;font-size:0.85em;font-weight:600;display:inline-block;">' . esc_html($item) . '</span>';
            }
            $integrations_html .= '</div></div>';
        }

        // Testing Experience
        $testing_experience_html = '';
        if (!empty($ux)) {
            $setup = $ux['setup_process'] ?? '';
            $daily = $ux['daily_usage'] ?? '';
            $learning = $ux['learning_curve'] ?? '';
            $support = $ux['support_quality'] ?? '';
            $walkthrough = $ux['personal_walkthrough'] ?? '';
            
            $testing_experience_html .= '<div id="testing-experience" style="margin-bottom:45px;">';
            $testing_experience_html .= '<h2 style="' . $h2_style . '">My Testing Experience: ' . $kw . '</h2>';
            $testing_experience_html .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">For this ' . $kw . ', I spent hands-on time with ' . esc_html($product) . ' across setup, daily use, and support. Here is what stood out:</p>';
            if ($walkthrough) {
                $testing_experience_html .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">' . esc_html($walkthrough) . '</p>';
            }
            $testing_experience_html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;row-gap:25px;margin-top:20px;">';
            
            if ($setup) {
                $testing_experience_html .= '<div style="background:#fcfcfc;border:1px solid #e5e7eb;border-radius:10px;padding:22px;">';
                $testing_experience_html .= '<h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;font-weight:700;color:#000000;display:flex;align-items:center;gap:8px;"><i class="fas fa-tools" style="color:#0050d7;"></i> Setup</h3>';
                $testing_experience_html .= '<p style="margin:0;font-size:0.88em;color:#4b5563;line-height:1.6;">' . esc_html($setup) . '</p>';
                $testing_experience_html .= '</div>';
            }
            if ($daily) {
                $testing_experience_html .= '<div style="background:#fcfcfc;border:1px solid #e5e7eb;border-radius:10px;padding:22px;">';
                $testing_experience_html .= '<h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;font-weight:700;color:#000000;display:flex;align-items:center;gap:8px;"><i class="fas fa-desktop" style="color:#0050d7;"></i> Daily Interface</h3>';
                $testing_experience_html .= '<p style="margin:0;font-size:0.88em;color:#4b5563;line-height:1.6;">' . esc_html($daily) . '</p>';
                $testing_experience_html .= '</div>';
            }
            if ($learning) {
                $testing_experience_html .= '<div style="background:#fcfcfc;border:1px solid #e5e7eb;border-radius:10px;padding:22px;">';
                $testing_experience_html .= '<h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;font-weight:700;color:#000000;display:flex;align-items:center;gap:8px;"><i class="fas fa-graduation-cap" style="color:#0050d7;"></i> Learning Curve</h3>';
                $testing_experience_html .= '<p style="margin:0;font-size:0.88em;color:#4b5563;line-height:1.6;">' . esc_html($learning) . '</p>';
                $testing_experience_html .= '</div>';
            }
            if ($support) {
                $testing_experience_html .= '<div style="background:#fcfcfc;border:1px solid #e5e7eb;border-radius:10px;padding:22px;">';
                $testing_experience_html .= '<h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;font-weight:700;color:#000000;display:flex;align-items:center;gap:8px;"><i class="fas fa-headset" style="color:#0050d7;"></i> Support Quality</h3>';
                $testing_experience_html .= '<p style="margin:0;font-size:0.88em;color:#4b5563;line-height:1.6;">' . esc_html($support) . '</p>';
                $testing_experience_html .= '</div>';
            }
            
            $testing_experience_html .= '</div>';
            $testing_experience_html .= $inline_cta;
            $testing_experience_html .= '</div>';
        }

        // Pricing Tiers - disabled (removed from output)
        $pricing_tiers_html = '';

        // Community Feedback
        $community_feedback_html = '';
        $feedback = $ai['community_feedback'] ?? $scraped['community_feedback'] ?? array();
        if (!empty($feedback)) {
            $sentiment = $feedback['reddit_sentiment'] ?? '';
            $praise = $feedback['common_praise'] ?? array();
            $complaints = $feedback['common_complaints'] ?? array();
            
            if ($sentiment || !empty($praise) || !empty($complaints)) {
                $community_feedback_html .= '<div id="community-feedback" style="margin-bottom:45px;">';
                $community_feedback_html .= '<h2 style="' . $h2_style . '">What the Community Says (Reddit &amp; Forums)</h2>';
                if ($sentiment) {
                    $community_feedback_html .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">' . esc_html($sentiment) . '</p>';
                }
                if (!empty($praise) || !empty($complaints)) {
                    $community_feedback_html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">';
                    if (!empty($praise)) {
                        $community_feedback_html .= '<div style="background:#f0fdf4;border-top:4px solid #16a34a;border-radius:0 0 8px 8px;padding:22px;">';
                        $community_feedback_html .= '<h3 style="margin-top:0;margin-bottom:12px;font-size:1.05rem;font-weight:600;color:#14532d;"><i class="fas fa-thumbs-up"></i> Common Praise</h3>';
                        $community_feedback_html .= '<ul style="padding-left:1.2em;margin:0;font-size:0.88em;color:#166534;line-height:1.6;list-style:disc;">';
                        foreach ($praise as $item) {
                            $community_feedback_html .= '<li style="margin-bottom:6px;">' . esc_html($item) . '</li>';
                        }
                        $community_feedback_html .= '</ul></div>';
                    }
                    if (!empty($complaints)) {
                        $community_feedback_html .= '<div style="background:#fef2f2;border-top:4px solid #dc2626;border-radius:0 0 8px 8px;padding:22px;">';
                        $community_feedback_html .= '<h3 style="margin-top:0;margin-bottom:12px;font-size:1.05rem;font-weight:600;color:#7f1d1d;"><i class="fas fa-thumbs-down"></i> Common Complaints</h3>';
                        $community_feedback_html .= '<ul style="padding-left:1.2em;margin:0;font-size:0.88em;color:#991b1b;line-height:1.6;list-style:disc;">';
                        foreach ($complaints as $item) {
                            $community_feedback_html .= '<li style="margin-bottom:6px;">' . esc_html($item) . '</li>';
                        }
                        $community_feedback_html .= '</ul></div>';
                    }
                    $community_feedback_html .= '</div>';
                }
                $community_feedback_html .= '</div>';
            }
        }

        // FAQ List
        $faq_html = '';
        if (!empty($faq)) {
            foreach ($faq as $item) {
                $q = $item['question'] ?? '';
                $a = $item['answer'] ?? '';
                if ($q && $a) {
                    $faq_html .= '<div style="margin-bottom:22px;padding-bottom:22px;border-bottom:1px solid #f0f0f0;">';
                    $faq_html .= '<h3 style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:24px;font-weight:800;line-height:1.1;color:#000000;margin-bottom:14px;margin-top:0;padding:0;border:none;">' . esc_html($q) . '</h3>';
                    $faq_html .= '<p style="margin-top:0;margin-bottom:0;color:#000000;font-size:20px;font-weight:300;line-height:28px;">' . esc_html($a) . '</p>';
                    $faq_html .= '</div>';
                }
            }
        }

        // --- Build Comparison Table ---
        $comparison_html = '';
        $comparison = $ai['comparison'] ?? $scraped['comparison'] ?? array();
        if (!empty($comparison) && is_array($comparison)) {
            $comparison_html .= '<div id="comparison" style="margin-bottom:45px;">';
            $comparison_html .= '<h2 style="' . $h2_style . '">' . esc_html($product) . ' vs Alternatives</h2>';
            $comparison_html .= '<p style="margin-bottom:1.4em;">A big part of this ' . $kw . ' is seeing how ' . esc_html($product) . ' compares to similar options on the market. Here is a quick comparison table:</p>';
            $comparison_html .= '<div style="overflow-x:auto;margin:25px 0;border-radius:10px;border:1px solid #eeeeee;">';
            $comparison_html .= '<table style="width:100%;border-collapse:collapse;font-size:0.9em;">';
            $comparison_html .= '<thead><tr>';
            $comparison_html .= '<th style="background:#0050d7;color:#ffffff;padding:14px 20px;text-align:left;font-weight:700;font-size:0.9em;">Tool</th>';
            $comparison_html .= '<th style="background:#0050d7;color:#ffffff;padding:14px 20px;text-align:left;font-weight:700;font-size:0.9em;">Price</th>';
            $comparison_html .= '<th style="background:#0050d7;color:#ffffff;padding:14px 20px;text-align:left;font-weight:700;font-size:0.9em;">Key Difference / description</th>';
            $comparison_html .= '</tr></thead><tbody>';
            $comparison_html .= '<tr style="background:#eef4ff;"><td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;border-left:3px solid #0050d7;font-weight:500;"><strong>' . esc_html($product) . '</strong> <em>(This Tool)</em></td>';
            $comparison_html .= '<td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:700;color:#0050d7;">' . esc_html($pricing['price'] ?? 'See site') . '</td>';
            $comparison_html .= '<td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;">The product featured in this honest review</td></tr>';
            foreach ($comparison as $comp) {
                $comparison_html .= '<tr><td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:600;">' . esc_html($comp['name']) . '</td>';
                $comparison_html .= '<td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#0050d7;">' . esc_html($comp['price'] ?? 'N/A') . '</td>';
                $comparison_html .= '<td style="padding:14px 20px;border-bottom:1px solid #f0f0f0;">' . esc_html($comp['description'] ?? '') . '</td></tr>';
            }
            $comparison_html .= '</tbody></table></div>';
            
            $verdict_vs = $ai['comparison_verdict'] ?? '';
            if ($verdict_vs) {
                $comparison_html .= '<div style="background:#eef4ff;border-left:4px solid #0050d7;padding:18px 24px;border-radius:0 8px 8px 0;font-size:0.92em;line-height:1.7;color:#000000;margin-top:15px;">';
                $comparison_html .= '<strong>Competitor Verdict:</strong> ' . esc_html($verdict_vs) . '</div>';
            }
            // Affiliate button after comparison
            $comparison_html .= '<div class="airg-cta-wrap" style="text-align:center;margin-top:30px;">';
            $comparison_html .= '<a class="airg-cta" href="' . esc_url($link) . '" rel="sponsored nofollow" style="display:inline-block;padding:14px 22px;font-family:Söhne;font-size:17px;font-weight:700;color:#ffffff;background-color:#1D4ED8;border:none;border-radius:8px;text-decoration:none;letter-spacing:0;line-height:1.2;">Get ' . esc_html($product) . ' Now</a>';
            $comparison_html .= '</div>';
            $comparison_html .= '</div>';
        }

        // --- Construct Dynamic Intro ---
        $hook = $intro['hook'] ?? '';
        $pain = $intro['pain_point'] ?? '';
        $context = $intro['context'] ?? '';
        $expect = $intro['what_to_expect'] ?? '';
        
        if (empty($hook)) {
            $intro_text_p1 = "If you've ever needed a better solution for your business, you know how hard it is to find the right tool. That's where <strong>" . esc_html($product) . "</strong> comes in. " . esc_html($context);
        } else {
            $intro_text_p1 = esc_html($hook) . " " . esc_html($pain) . " " . esc_html($context);
        }

        $intro_text_p2 = esc_html($intro['experience_statement'] ?? '') . " " . esc_html($expect);

        // --- Build conditional sections (only show title+content if content exists) ---
        $h2_style = 'font-family:Söhne;font-size:30px;font-weight:700;line-height:1.2;color:#000000;letter-spacing:-0.02em;margin-top:0;margin-bottom:18px;padding:0;border:none;';
        $btn_style = 'display:inline-block;padding:14px 22px;font-family:Söhne;font-size:17px;font-weight:700;color:#ffffff;background-color:#1D4ED8;border:none;border-radius:8px;text-decoration:none;letter-spacing:0;line-height:1.2;';

        // What Is It section
        $what_is_text = trim(($overview['what_is_it'] ?? '') . ($overview['creator_info'] ?? '') . ($overview['problem_solved'] ?? '') . ($overview['unique_value'] ?? ''));
        $what_is_section = '';
        if (!empty($what_is_text)) {
            $what_is_content = esc_html($overview['what_is_it'] ?? '') . '<br><br>' . esc_html($overview['creator_info'] ?? '') . '<br><br>' . esc_html($overview['problem_solved'] ?? '') . '<br><br><strong>Unique Value Proposition:</strong> ' . esc_html($overview['unique_value'] ?? '');
            $what_is_section = '<div id="what-is" style="margin-bottom:45px;">';
            $what_is_section .= '<h2 style="' . $h2_style . '">What Is ' . esc_html($product) . '?</h2>';
            $what_is_section .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">Before diving deeper into this ' . $kw . ', let us cover the basics.</p>';
            $what_is_section .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">' . $what_is_content . '</p>';
            $what_is_section .= $inline_cta;
            $what_is_section .= '</div>';
        }

        // Who Is It For section
        $who_is_for_section = '';
        if (!empty($who_for_html)) {
            $who_is_for_section = '<div id="who-is-for" style="margin-bottom:45px;">';
            $who_is_for_section .= '<h2 style="' . $h2_style . '">Who Is ' . esc_html($product) . ' For?</h2>';
            $who_is_for_section .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">After testing the software, I believe it is best suited for:</p>';
            $who_is_for_section .= '<ul style="margin-bottom:1.4em;padding-left:1.4em;list-style:disc;">' . $who_for_html . '</ul>';
            $who_is_for_section .= '<div style="background:#ffffff;border:1px solid #d8e5ff;border-left:4px solid #0050d7;padding:18px 24px;margin:28px 0;border-radius:0 8px 8px 0;font-size:0.92em;line-height:1.7;">';
            $who_is_for_section .= '<strong style="color:#1a1a1a;">Note:</strong> Always check the official sales page for the most up-to-date requirements.';
            $who_is_for_section .= '</div></div>';
        }

        // Key Features full section
        $features_full_section = '';
        if (!empty($features_html)) {
            $features_full_section = '<div id="features" style="margin-bottom:45px;">';
            $features_full_section .= '<h2 style="' . $h2_style . '">' . esc_html($product) . ' Key Features</h2>';
            $features_full_section .= $features_html;
            $features_full_section .= '</div>';
        }

        // FAQ full section
        $faq_full_section = '';
        if (!empty($faq_html)) {
            $faq_full_section = '<div id="faq" style="margin-bottom:45px;">';
            $faq_full_section .= '<h2 style="' . $h2_style . '">' . $kw . ': Frequently Asked Questions</h2>';
            $faq_full_section .= $faq_html;
            $faq_full_section .= '</div>';
        }

        // Final Verdict full section
        $verdict_text = trim(($conclusion['summary'] ?? '') . ($conclusion['verdict'] ?? '') . ($conclusion['who_should_buy'] ?? '') . ($conclusion['who_should_skip'] ?? '') . ($conclusion['final_score_justification'] ?? ''));
        $verdict_full_section = '';
        if (!empty($verdict_text)) {
            $verdict_content = esc_html($conclusion['summary'] ?? '') . '<br><br>' . esc_html($conclusion['verdict'] ?? '') . '<br><br><strong>Who should buy:</strong> ' . esc_html($conclusion['who_should_buy'] ?? '') . '<br><strong>Who should skip:</strong> ' . esc_html($conclusion['who_should_skip'] ?? '') . '<br><br><strong>Verdict Justification:</strong> ' . esc_html($conclusion['final_score_justification'] ?? '');
            $verdict_full_section = '<div id="verdict" style="margin-bottom:45px;">';
            $verdict_full_section .= '<h2 style="' . $h2_style . '">' . $kw . ': Final Verdict</h2>';
            $verdict_full_section .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">To wrap up this ' . $kw . ', here is my honest final take on whether ' . esc_html($product) . ' is worth it:</p>';
            $verdict_full_section .= '<p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">' . $verdict_content . '</p>';
            $verdict_full_section .= '<div style="text-align:center;margin-top:35px;padding:30px;">';
            $verdict_full_section .= '<a class="airg-cta" href="' . esc_url($link) . '" rel="sponsored nofollow" style="' . $btn_style . '">Get ' . esc_html($product) . ' Risk-Free Now</a>';
            $verdict_full_section .= '</div></div>';
        }

        // Prepare template variables
        $template_vars = array(
            '{{PRODUCT_NAME}}'            => esc_html($product),
            '{{OVERALL_RATING}}'          => esc_html($rating),
            '{{CURRENT_DATE}}'            => esc_html($review_date),
            '{{INTRO_PARAGRAPH_1}}'       => $intro_text_p1,
            '{{INTRO_PARAGRAPH_2}}'       => $intro_text_p2,
            '{{PROS_LIST}}'               => $pros_html,
            '{{CONS_LIST}}'               => $cons_html,
            '{{WHAT_IS_SECTION}}'         => $what_is_section,
            '{{WHO_IS_FOR_SECTION}}'      => $who_is_for_section,
            '{{FEATURES_FULL_SECTION}}'   => $features_full_section,
            '{{COMPARISON_SECTION}}'      => $comparison_html,
            '{{FAQ_FULL_SECTION}}'        => $faq_full_section,
            '{{VERDICT_FULL_SECTION}}'    => $verdict_full_section,
            '{{AFFILIATE_LINK}}'          => esc_url($link),
            '{{SITE_HOME_URL}}'           => $site_home,
            '{{HOW_IT_WORKS_SECTION}}'    => $how_it_works_html,
            '{{WHO_SHOULD_AVOID_SECTION}}'=> $who_not_html,
            '{{INTEGRATIONS_SECTION}}'    => $integrations_html,
            '{{TESTING_EXPERIENCE_SECTION}}'=> $testing_experience_html,
            '{{PRICING_TIERS_SECTION}}'   => $pricing_tiers_html,
            '{{COMMUNITY_FEEDBACK_SECTION}}'=> $community_feedback_html,
        );
        
        // Build Schema.org structured data
        $schema_json = $this->build_schema_json($product, $link, $rating, $review_date, $conclusion, $overview, $pricing, $pros);
        
        // Get the template and replace placeholders
        $html = $schema_json . $this->get_template();
        
        foreach ($template_vars as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }
        
        return $html;
    }

    /**
     * Build Schema.org JSON-LD structured data
     */
    private function build_schema_json($product, $link, $rating, $review_date, $conclusion, $overview, $pricing, $pros) {
        $rating_value = floatval($rating);
        $rating_value = max(1, min(5, $rating_value));
        $description = $conclusion['verdict'] ?? $overview['what_is_it'] ?? 'Comprehensive product review.';
        
        $positive_notes = array();
        foreach (array_slice($pros, 0, 5) as $pro) {
            $positive_notes[] = array(
                '@type' => 'ListItem',
                'position' => count($positive_notes) + 1,
                'name' => $pro
            );
        }
        
        $price = $pricing['price'] ?? '';
        $price_numeric = preg_replace('/[^0-9.]/', '', $price);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@graph' => array(
                array(
                    '@type' => 'Product',
                    'name' => $product,
                    'description' => $description,
                    'url' => $link,
                    'review' => array(
                        '@type' => 'Review',
                        'reviewRating' => array(
                            '@type' => 'Rating',
                            'ratingValue' => $rating_value,
                            'bestRating' => 5,
                            'worstRating' => 1
                        ),
                        'author' => array(
                            '@type' => 'Person',
                            'name' => get_the_author() ? get_the_author() : get_bloginfo('name'),
                        ),
                        'publisher' => array(
                            '@type' => 'Organization',
                            'name' => get_bloginfo('name'),
                            'url' => home_url()
                        ),
                        'datePublished' => date('Y-m-d', strtotime($review_date)),
                        'reviewBody' => $description
                    ),
                    'aggregateRating' => array(
                        '@type' => 'AggregateRating',
                        'ratingValue' => $rating_value,
                        'bestRating' => 5,
                        'worstRating' => 1,
                        'ratingCount' => 1,
                        'reviewCount' => 1
                    )
                )
            )
        );
        
        if (!empty($price_numeric) && is_numeric($price_numeric)) {
            $schema['@graph'][0]['offers'] = array(
                '@type' => 'Offer',
                'url' => $link,
                'price' => $price_numeric,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock'
            );
        }
        
        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Get HTML template with inline styles (Elementor compatible)
     *
     * @since 8.0.0
     * @return string Template HTML.
     */
    private function get_template() {
        $h2_style = 'font-family:Söhne;font-size:30px;font-weight:700;line-height:1.2;color:#000000;letter-spacing:-0.02em;margin-top:0;margin-bottom:18px;padding:0;border:none;';
        $btn_style = 'display:inline-block;padding:14px 22px;font-family:Söhne;font-size:17px;font-weight:700;color:#ffffff;background-color:#1D4ED8;border:none;border-radius:8px;text-decoration:none;letter-spacing:0;line-height:1.2;';
        $toc_link = 'color:#1D4ED8;text-decoration:none;font-weight:700;border-bottom:3px solid #1D4ED8;padding-bottom:1px;font-size:19px;font-family:Söhne;';
        $toc_li = 'margin-bottom:12px;list-style:none;';

        return '
<style>
.airg-review-post,
.airg-review-post *:not(.fa):not(.fas):not(.far):not(.fa-solid):not(.fa-regular) {
    font-family: Söhne !important;
}
.airg-review-post {
    background-color: #ffffff !important;
}
.pxl-post__content {
    background: #ffffff !important;
    background-color: #ffffff !important;
}
body:has(.airg-review-post) .entry-title,
body:has(.airg-review-post) .wp-block-post-title {
    font-family: Söhne !important;
    font-weight: 800 !important;
}
</style>
<div class="airg-review-post" style="font-style:normal;font-weight:400;font-size:18px;line-height:1.75;font-family:Söhne;color:#444444;background-color:#ffffff;width:100%;max-width:820px;box-sizing:border-box;margin:0 auto;padding:0 32px 72px;">

    <!-- Introduction -->
    <div class="airg-review-intro" style="margin-bottom:45px;">
        <p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">In this <strong>{{PRODUCT_NAME}} Review</strong>, I share hands-on testing results, pricing, and the real pros and cons so you can decide if it fits your needs. {{INTRO_PARAGRAPH_1}}</p>
        <p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">{{INTRO_PARAGRAPH_2}}</p>
        <p style="font-style:normal;font-weight:300;font-size:20px;line-height:28px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#000000;margin-bottom:20px;">For more comparisons, browse our <a href="{{SITE_HOME_URL}}" style="color:#0050d7;font-weight:700;">other software reviews</a>, and as you read this {{PRODUCT_NAME}} Review you can cross-check verified user ratings on <a href="https://www.trustpilot.com/" target="_blank" rel="noopener" style="color:#0050d7;font-weight:700;">Trustpilot</a>.</p>
    </div>

    <!-- Quick Verdict -->
    <div class="airg-quick-verdict" style="background:#1D4ED8;padding:36px 40px;margin-bottom:20px;color:#ffffff;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,0.2);">
            <div>
                <h3 style="margin:0;font-size:1.35rem;font-weight:700;color:#ffffff;">Quick Verdict</h3>
                <span style="display:block;margin-top:4px;font-size:0.85em;color:rgba(255,255,255,0.75);font-weight:400;">{{PRODUCT_NAME}}</span>
            </div>
            <div style="font-size:2.6rem;font-weight:800;color:#ffffff;line-height:1;">{{OVERALL_RATING}}<span style="font-size:1.1rem;color:rgba(255,255,255,0.7);font-weight:600;">/5</span></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;">
            <div>
                <span style="display:block;font-weight:700;font-size:1.15em;margin-bottom:14px;color:#ffffff;">Pros</span>
                <ul style="list-style:none;padding-left:0;margin:0;">{{PROS_LIST}}</ul>
            </div>
            <div>
                <span style="display:block;font-weight:700;font-size:1.15em;margin-bottom:14px;color:#ffffff;">Cons</span>
                <ul style="list-style:none;padding-left:0;margin:0;">{{CONS_LIST}}</ul>
            </div>
        </div>
    </div>
    <div class="airg-cta-wrap" style="text-align:center;margin-bottom:45px;">
        <a class="airg-cta" href="{{AFFILIATE_LINK}}" rel="sponsored nofollow" style="' . $btn_style . '">Get {{PRODUCT_NAME}} Now</a>
    </div>

    <!-- Table of Contents (Collapsible) -->
    <div class="airg-review-toc" style="background:#ffffff;border:1px solid rgba(29,78,216,0.22);border-radius:12px;padding:24px 26px;margin-bottom:45px;">
        <div onclick="var t=this.nextElementSibling;t.style.display=t.style.display===\'none\'?\'block\':\'none\';this.querySelector(\'.toc-icon\').style.transform=t.style.display===\'none\'?\'rotate(0deg)\':\'rotate(90deg)\';" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none;">
            <span style="font-size:1.5rem;font-weight:900;color:#1a1a1a;">Table of Contents</span>
            <span class="toc-icon" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:2px solid #1a1a1a;border-radius:6px;font-size:1.1rem;color:#1a1a1a;transition:transform 0.3s ease;">&#9776;</span>
        </div>
        <ul style="margin:0;padding-left:0;list-style:none;display:none;margin-top:20px;">
            <li style="' . $toc_li . '">&#8226; <a href="#what-is" style="' . $toc_link . '">What Is {{PRODUCT_NAME}}?</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#how-it-works" style="' . $toc_link . '">How It Works</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#who-is-for" style="' . $toc_link . '">Who Is It For?</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#who-should-avoid" style="' . $toc_link . '">Who Should Avoid It?</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#features" style="' . $toc_link . '">Key Features</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#integrations" style="' . $toc_link . '">Supported Integrations</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#testing-experience" style="' . $toc_link . '">My Testing Experience</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#comparison" style="' . $toc_link . '">Comparison with Alternatives</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#community-feedback" style="' . $toc_link . '">Community Feedback</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#pros-cons" style="' . $toc_link . '">Pros &amp; Cons</a></li>
            <li style="' . $toc_li . '">&#8226; <a href="#faq" style="' . $toc_link . '">FAQ</a></li>
            <li style="margin-bottom:0;list-style:none;">&#8226; <a href="#verdict" style="' . $toc_link . '">Final Verdict</a></li>
        </ul>
    </div>

    <!-- What Is It -->
    {{WHAT_IS_SECTION}}

    <!-- How It Works -->
    {{HOW_IT_WORKS_SECTION}}

    <!-- Who Is It For -->
    {{WHO_IS_FOR_SECTION}}

    <!-- Who Should Avoid -->
    {{WHO_SHOULD_AVOID_SECTION}}

    <!-- Key Features -->
    {{FEATURES_FULL_SECTION}}

    <!-- Supported Integrations -->
    {{INTEGRATIONS_SECTION}}

    <!-- My Testing Experience -->
    {{TESTING_EXPERIENCE_SECTION}}

    <!-- Alternatives Comparison -->
    {{COMPARISON_SECTION}}

    <!-- Community Feedback -->
    {{COMMUNITY_FEEDBACK_SECTION}}

    <!-- Pros & Cons -->
    <div id="pros-cons" style="margin-bottom:45px;">
        <h2 style="' . $h2_style . '">{{PRODUCT_NAME}} Review: Pros &amp; Cons</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;overflow:hidden;margin-bottom:10px;">
            <div style="background:#0050d7;padding:36px 32px;">
                <span style="display:block;color:#ffffff;font-size:1.4em;font-weight:800;margin-bottom:20px;">Pros</span>
                <ul style="list-style:none;padding-left:0;margin:0;">{{PROS_LIST}}</ul>
            </div>
            <div style="background:#003fa8;padding:36px 32px;">
                <span style="display:block;color:#ffffff;font-size:1.4em;font-weight:800;margin-bottom:20px;">Cons</span>
                <ul style="list-style:none;padding-left:0;margin:0;">{{CONS_LIST}}</ul>
            </div>
        </div>
        <div style="text-align:center;margin-top:30px;">
            <a class="airg-cta" href="{{AFFILIATE_LINK}}" rel="sponsored nofollow" style="' . $btn_style . '">Get {{PRODUCT_NAME}} Now</a>
        </div>
    </div>

    <!-- FAQ -->
    {{FAQ_FULL_SECTION}}

    <!-- Final Verdict -->
    {{VERDICT_FULL_SECTION}}

</div>';
    }
}
