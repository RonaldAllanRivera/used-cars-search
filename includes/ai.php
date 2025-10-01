<?php
if (!defined('ABSPATH')) exit;

// Core AI utilities for Used Cars Search
// This file contains the low-level client for OpenAI and shared helpers.

if (!function_exists('ucs_ai_get_options')) {
    function ucs_ai_get_options() {
        $defaults = array(
            'enabled'     => 0,
            'queue_paused'=> 0,
            'api_key'     => '',
            'model'       => apply_filters('ucs_ai_model', 'gpt-4o-mini'),
            'temperature' => 0.3,
            'max_tokens'  => 1200,
        );
        $opts = get_option('ucs_ai_options');
        if (!is_array($opts)) $opts = array();
        $opts = wp_parse_args($opts, $defaults);
        // Never expose the key via filters inadvertently
        $opts['api_key'] = is_string($opts['api_key']) ? trim($opts['api_key']) : '';
        return $opts;
    }
}

if (!function_exists('ucs_ai_available_models')) {
    function ucs_ai_available_models() {
        $models = array(
            'gpt-4o-mini'   => 'gpt-4o-mini (fast, cost-effective)',
            'gpt-4o'        => 'gpt-4o',
            'gpt-4.1-mini'  => 'gpt-4.1-mini',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo (legacy)'
        );
        return apply_filters('ucs_ai_models', $models);
    }
}

if (!function_exists('ucs_ai_sanitize_text')) {
    function ucs_ai_sanitize_text($val) {
        return is_string($val) ? sanitize_text_field($val) : '';
    }
}

// Simple keyword extractor from plain text for SEO keywords fallback
if (!function_exists('ucs_ai_extract_keywords_from_text')) {
    /**
     * Extract up to $limit simple keywords from given text.
     * - Lowercases
     * - Strips punctuation
     * - Removes short words and common stopwords
     * - Returns comma-separated keywords
     */
    function ucs_ai_extract_keywords_from_text($text, $limit = 6) {
        $text = wp_strip_all_tags((string) $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = array('the','and','for','with','this','that','from','your','you','are','our','has','have','into','about','over','why','who','is','it','of','to','a','an','in','on','at','by','or','as','be','we','they','their','its','out','new');
        $stop_map = array_fill_keys($stop, true);
        $freq = array();
        foreach ($words as $w) {
            if (strlen($w) < 3) continue;
            if (isset($stop_map[$w])) continue;
            $freq[$w] = isset($freq[$w]) ? $freq[$w] + 1 : 1;
        }
        if (empty($freq)) return '';
        arsort($freq);
        $keywords = array_slice(array_keys($freq), 0, max(1, intval($limit)));
        return implode(', ', $keywords);
    }
}

if (!function_exists('ucs_ai_http_headers')) {
    function ucs_ai_http_headers($api_key) {
        return array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
    }
}

// Helper: compute future datetimes consistently (local and GMT) for scheduling
if (!function_exists('ucs_ai_future_datetime')) {
    /**
     * Returns an array with 'ts_gmt', 'date_gmt', and 'date_local' for now + $hours hours.
     * Uses GMT as the source of truth and converts to site local time.
     */
    function ucs_ai_future_datetime($hours = 24) {
        $hours = intval($hours);
        if ($hours < 1) { $hours = 24; }
        $ts_gmt = time() + ($hours * HOUR_IN_SECONDS);
        $date_gmt = gmdate('Y-m-d H:i:s', $ts_gmt);
        $date_local = get_date_from_gmt($date_gmt, 'Y-m-d H:i:s');
        return array(
            'ts_gmt'    => $ts_gmt,
            'date_gmt'  => $date_gmt,
            'date_local'=> $date_local,
        );
    }
}

if (!function_exists('ucs_ai_chat_completion')) {
    /**
     * Calls OpenAI Chat Completions API with given messages and args
     * Returns WP_Error on failure or decoded array on success.
     */
    function ucs_ai_chat_completion($messages, $args = array()) {
        $opts = ucs_ai_get_options();
        $api_key = isset($args['api_key']) && $args['api_key'] ? $args['api_key'] : $opts['api_key'];
        if (!$api_key) {
            // translators: Error message when the OpenAI API key is missing
            return new WP_Error('ucs_ai_no_key', __('OpenAI API key is not configured.', 'used-cars-search'));
        }
        $model = isset($args['model']) && $args['model'] ? $args['model'] : $opts['model'];
        $temperature = isset($args['temperature']) ? floatval($args['temperature']) : floatval($opts['temperature']);
        $max_tokens = isset($args['max_tokens']) ? intval($args['max_tokens']) : intval($opts['max_tokens']);

        $body = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
        );

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => ucs_ai_http_headers($api_key),
            'body'    => wp_json_encode($body),
        ));
        if (is_wp_error($resp)) return $resp;
        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) {
            /* translators: %d = HTTP status code returned by the OpenAI API */
            // translators: %d is the HTTP status code from the OpenAI API
            return new WP_Error('ucs_ai_http_error', sprintf(__('OpenAI HTTP %d', 'used-cars-search'), $code), array('body' => $raw));
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // translators: Error message when the response from OpenAI is not valid JSON
            return new WP_Error('ucs_ai_bad_json', __('Invalid JSON from OpenAI.', 'used-cars-search'), array('body' => $raw));
        }
        return $data;
    }
}

// Core: Apply AI-generated changes to a post (shared by AJAX and Cron)
if (!function_exists('ucs_ai_apply_changes_core')) {
    function ucs_ai_apply_changes_core($post_id, $payload, $fields) {
        $post_id = intval($post_id);
        if (!$post_id || get_post_status($post_id) === false) {
            return new WP_Error('ucs_ai_no_post', __('Invalid post.', 'used-cars-search'));
        }

        $fields = is_array($fields) ? $fields : array('title','content','seo_title','seo_description','seo_keywords');
        $updated = array();
        $post_update = array('ID' => $post_id);
        $current_status = get_post_status($post_id);

        if (in_array('title', $fields, true) && !empty($payload['title'])) {
            $post_update['post_title'] = sanitize_text_field($payload['title']);
            $updated[] = 'title';
        }
        if (in_array('content', $fields, true) && !empty($payload['content'])) {
            $post_update['post_content'] = wp_kses_post($payload['content']);
            $updated[] = 'content';
        }
        if (count($post_update) > 1) {
            // If we're updating a draft/pending/auto-draft with AI content, schedule it 24h from now
            if (in_array($current_status, array('draft','pending','auto-draft'), true)) {
                $future = ucs_ai_future_datetime(24);
                if (!empty($future)) {
                    $post_update['post_status']    = 'future';
                    $post_update['post_date']      = $future['date_local'];
                    $post_update['post_date_gmt']  = $future['date_gmt'];
                    // Mark that this post was scheduled by AI so filters can enforce it
                    update_post_meta($post_id, '_ucs_ai_scheduled_gmt', $future['date_gmt']);
                }
            }
            wp_update_post($post_update);
        }

        // Prepare fallbacks for SEO fields if missing
        $given_title   = isset($payload['title']) ? sanitize_text_field($payload['title']) : '';
        $given_content = isset($payload['content']) ? wp_kses_post($payload['content']) : '';
        $current_title = get_the_title($post_id);
        $current_content = get_post_field('post_content', $post_id);

        $fields_lc = array_map('strtolower', (array)$fields);
        $sel_title_or_content = in_array('title', $fields_lc, true) || in_array('content', $fields_lc, true);

        if (in_array('seo_title', $fields_lc, true) || $sel_title_or_content) {
            $seo_title_in = isset($payload['seo_title']) ? trim($payload['seo_title']) : '';
            $seo_title = sanitize_text_field($seo_title_in);
            if ($seo_title === '') {
                $base = $given_title !== '' ? $given_title : $current_title;
                if (function_exists('mb_substr')) { $seo_title = mb_substr($base, 0, 60); } else { $seo_title = substr($base, 0, 60); }
            }
            if ($seo_title !== '') {
                update_post_meta($post_id, '_ucs_seo_title', $seo_title);
                // Popular SEO plugins
                update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
                update_post_meta($post_id, 'rank_math_title', $seo_title);
                $updated[] = 'seo_title';
            }
        }
        if (in_array('seo_description', $fields_lc, true) || $sel_title_or_content) {
            $seo_desc_in = isset($payload['seo_description']) ? trim($payload['seo_description']) : '';
            $seo_desc = sanitize_textarea_field($seo_desc_in);
            if ($seo_desc === '') {
                $text = wp_strip_all_tags($given_content !== '' ? $given_content : $current_content);
                $text = trim(preg_replace('/\s+/', ' ', $text));
                if (function_exists('mb_substr')) { $seo_desc = mb_substr($text, 0, 160); } else { $seo_desc = substr($text, 0, 160); }
            }
            if ($seo_desc !== '') {
                update_post_meta($post_id, '_ucs_seo_description', $seo_desc);
                // Popular SEO plugins
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_desc);
                update_post_meta($post_id, 'rank_math_description', $seo_desc);
                $updated[] = 'seo_description';
            }
        }
        if (in_array('seo_keywords', $fields_lc, true) || $sel_title_or_content) {
            $seo_keywords_in = isset($payload['seo_keywords']) ? trim($payload['seo_keywords']) : '';
            $seo_keywords_raw = sanitize_text_field($seo_keywords_in);
            if ($seo_keywords_raw === '') {
                // Respect plugin setting: if car details meta box is disabled, derive from title/content
                $opts_general = get_option('ucs_options');
                $car_details_enabled = !isset($opts_general['enable_car_details']) || (bool)$opts_general['enable_car_details'];
                if ($car_details_enabled) {
                    // Build from Year/Make/Model/Trim meta
                    $year = get_post_meta($post_id, 'ucs_year', true);
                    $make = get_post_meta($post_id, 'ucs_make', true);
                    $model = get_post_meta($post_id, 'ucs_model', true);
                    $trimv = get_post_meta($post_id, 'ucs_trim', true);
                    $kw = array();
                    if ($year && $make && $model) { $kw[] = trim($year.' '.$make.' '.$model.($trimv?(' '.$trimv):'')); }
                    if ($make && $model) { $kw[] = $make.' '.$model; }
                    if ($make) { $kw[] = 'used '.$make; }
                    if ($model) { $kw[] = 'used '.$model; }
                    if ($make && $model) { $kw[] = $make.' '.$model.' for sale'; }
                    $kw = array_values(array_unique(array_filter($kw)));
                    $seo_keywords_raw = implode(', ', array_slice($kw, 0, 6));
                } else {
                    $base_text = ($given_title !== '' ? $given_title : $current_title) . ' ' . ($given_content !== '' ? wp_strip_all_tags($given_content) : wp_strip_all_tags($current_content));
                    $seo_keywords_raw = ucs_ai_extract_keywords_from_text($base_text, 6);
                }
            }
            if ($seo_keywords_raw !== '') {
                update_post_meta($post_id, '_ucs_seo_keywords', $seo_keywords_raw);
                // Use first keyword as focus keyphrase for plugins
                $first_kw = trim(explode(',', $seo_keywords_raw)[0]);
                if ($first_kw !== '') {
                    update_post_meta($post_id, '_yoast_wpseo_focuskw', $first_kw);
                    update_post_meta($post_id, 'rank_math_focus_keyword', $first_kw);
                }
                $updated[] = 'seo_keywords';
            }
        }

        return array('updated' => $updated);
    }
}

// Enforce: If a post carries our AI schedule meta and its scheduled time is in the future, force status=future
if (!function_exists('ucs_ai_enforce_future_status')) {
    add_filter('wp_insert_post_data', function($data, $postarr){
        // Only act on posts
        if (empty($postarr['ID'])) return $data;
        $post_id = intval($postarr['ID']);
        if ($post_id <= 0) return $data;

        // Check our scheduling meta
        $scheduled_gmt = get_post_meta($post_id, '_ucs_ai_scheduled_gmt', true);
        if (!$scheduled_gmt) return $data;

        // If scheduled time is still in the future, keep as future regardless of external overrides
        $now_gmt = gmdate('Y-m-d H:i:s');
        if ($scheduled_gmt > $now_gmt) {
            $data['post_status'] = 'future';
            // Ensure dates align with the scheduled time
            $data['post_date_gmt'] = $scheduled_gmt;
            $data['post_date'] = get_date_from_gmt($scheduled_gmt, 'Y-m-d H:i:s');
        } else {
            // Time passed; clean up the meta so normal publish can proceed
            delete_post_meta($post_id, '_ucs_ai_scheduled_gmt');
        }
        return $data;
    }, 10, 2);
}

// Core: Generate AI suggestions for a post id (shared by Cron)
if (!function_exists('ucs_ai_generate_for_post_core')) {
    function ucs_ai_generate_for_post_core($post_id, $fields = array('title','content','seo_title','seo_description','seo_keywords')) {
        $post = get_post($post_id);
        if (!$post) {
            // translators: Error message when a post cannot be found
            return new WP_Error('ucs_ai_no_post', __('Post not found.', 'used-cars-search'));
        }

        $fields = is_array($fields) ? $fields : array('title','content','seo_title','seo_description','seo_keywords');

        // Check plugin setting for car details usage
        $opts_general = get_option('ucs_options');
        $car_details_enabled = !isset($opts_general['enable_car_details']) || (bool)$opts_general['enable_car_details'];
        // Allow developers to override per environment/post
        $car_details_enabled = apply_filters('ucs_ai_car_details_enabled', $car_details_enabled, $post_id);

        $year = $car_details_enabled ? get_post_meta($post_id, 'ucs_year', true) : '';
        $make = $car_details_enabled ? get_post_meta($post_id, 'ucs_make', true) : '';
        $model = $car_details_enabled ? get_post_meta($post_id, 'ucs_model', true) : '';
        $trim = $car_details_enabled ? get_post_meta($post_id, 'ucs_trim', true) : '';
        $price = $car_details_enabled ? get_post_meta($post_id, 'ucs_price', true) : '';
        $mileage = $car_details_enabled ? get_post_meta($post_id, 'ucs_mileage', true) : '';
        $engine = $car_details_enabled ? get_post_meta($post_id, 'ucs_engine', true) : '';
        $trans = $car_details_enabled ? get_post_meta($post_id, 'ucs_transmission', true) : '';

        $details = array(
            'year' => $year, 'make' => $make, 'model' => $model, 'trim' => $trim,
            'price' => $price, 'mileage' => $mileage, 'engine' => $engine, 'transmission' => $trans,
        );

        $requested = implode(',', $fields);
        $opts_prompt = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : array();
        $sub_enabled = !empty($opts_prompt['subheadline_enabled']);
        $homepage_url = !empty($opts_prompt['homepage_url']) ? $opts_prompt['homepage_url'] : 'https://everythingusedcars.com/';

        if ($car_details_enabled) {
            $system_message = $sub_enabled
                ? 'You are a senior conversion copywriter and expert automotive copywriter. Output STRICT JSON only with keys ["title","content","seo_title","seo_description","seo_keywords"]. Do not include code fences, backticks, or any commentary outside JSON. Style: natural, persuasive, accurate, and fact-consistent with provided details. Constraints: Title 60-70 chars; SEO title 55-60 chars; SEO description 150-160 chars; Content 700-1200 words. Content must use HTML ONLY (no Markdown). Required content structure inside the "content" HTML: (1) First line: an H2 sub-headline linked to the homepage, formatted as <h2><a href="' . $homepage_url . '">{SUBHEADLINE}</a></h2>, where {SUBHEADLINE} is a dynamic, conversion-focused phrase derived from the generated "title" and the vehicle\'s primary value props. Requirements for {SUBHEADLINE}: 6–12 words; readable Title Case; no emojis; avoid boilerplate; do not duplicate the title verbatim; optionally include one benefit keyword (e.g., price, low mileage, fuel efficiency, reliability, warranty, financing). (2) Immediately after, include one introductory paragraph that frames the vehicle value (you may bold the vehicle name using <strong>Year Make Model Trim</strong>). (3) Use <h2> sections with these exact headings: "Vehicle Overview", "Why It Stands Out", "Who It Is For", "Performance & Efficiency", "Ownership & Reliability". (4) Add an <h3>"Frequently Asked Questions"</h3> section that contains a <ul> with 4–5 <li> items; each item starts with <strong>Question</strong> followed by the concise answer text in the same <li>. (5) Add an <h3>"Summary"</h3> section with a concise closing paragraph. (6) End the content with 6–12 lines of hashtags, each on its own line, starting with # (e.g., #UsedCars). Do NOT include any labels like "Keywords:", "Hashtags:", or counts like "TitleChars:" or "ContentWords:". If a field is unknown, omit it gracefully; never fabricate unavailable specs. Return only valid JSON.'
                : 'You are a senior conversion copywriter and expert automotive copywriter. Output STRICT JSON only with keys ["title","content","seo_title","seo_description","seo_keywords"]. Do not include code fences, backticks, or any commentary outside JSON. Style: natural, persuasive, accurate, and fact-consistent with provided details. Constraints: Title 60-70 chars; SEO title 55-60 chars; SEO description 150-160 chars; Content 700-1200 words. Content must use HTML ONLY (no Markdown). Required content structure inside the "content" HTML: (1) Start with one introductory paragraph that frames the vehicle value (you may bold the vehicle name using <strong>Year Make Model Trim</strong>). (2) Use <h2> sections with these exact headings: "Vehicle Overview", "Why It Stands Out", "Who It Is For", "Performance & Efficiency", "Ownership & Reliability". (3) Add an <h3>"Frequently Asked Questions"</h3> section that contains a <ul> with 4–5 <li> items; each item starts with <strong>Question</strong> followed by the concise answer text in the same <li>. (4) Add an <h3>"Summary"</h3> section with a concise closing paragraph. (5) End the content with 6–12 lines of hashtags, each on its own line, starting with # (e.g., #UsedCars). Do NOT include any labels like "Keywords:", "Hashtags:", or counts like "TitleChars:" or "ContentWords:". If a field is unknown, omit it gracefully; never fabricate unavailable specs. Return only valid JSON.';
        } else {
            $system_message = $sub_enabled
                ? 'You are a senior conversion copywriter. Output STRICT JSON only with keys ["title","content","seo_title","seo_description","seo_keywords"]. Do not include code fences, backticks, or any commentary outside JSON. Style: natural, persuasive, accurate. Constraints: Title 60-70 chars; SEO title 55-60 chars; SEO description 150-160 chars; Content 700-1200 words. Content must use HTML ONLY (no Markdown). Required structure inside the "content" HTML: (1) First line: an H2 sub-headline linked to the homepage, formatted as <h2><a href="' . $homepage_url . '">{SUBHEADLINE}</a></h2>. (2) Immediately after, include one introductory paragraph based ONLY on the current post title and content excerpts; do not invent vehicle specifications. (3) Use <h2> sections with these exact headings: "Vehicle Overview", "Why It Stands Out", "Who It Is For", "Performance & Efficiency", "Ownership & Reliability". (4) Add an <h3>"Frequently Asked Questions"</h3> section that contains a <ul> with 4–5 <li> items; each item starts with <strong>Question</strong> followed by the concise answer text. (5) Add an <h3>"Summary"</h3> section with a concise closing paragraph. (6) End with 6–12 lines of hashtags, each on its own line, starting with #. Never fabricate unavailable specs; infer themes from title/content only. Return only valid JSON.'
                : 'You are a senior conversion copywriter. Output STRICT JSON only with keys ["title","content","seo_title","seo_description","seo_keywords"]. Do not include code fences, backticks, or any commentary outside JSON. Style: natural, persuasive, accurate. Constraints: Title 60-70 chars; SEO title 55-60 chars; SEO description 150-160 chars; Content 700-1200 words. Content must use HTML ONLY (no Markdown). Required structure inside the "content" HTML: (1) Start with one introductory paragraph based ONLY on the current post title and content excerpts; do not invent vehicle specifications. (2) Use <h2> sections with these exact headings: "Vehicle Overview", "Why It Stands Out", "Who It Is For", "Performance & Efficiency", "Ownership & Reliability". (3) Add an <h3>"Frequently Asked Questions"</h3> section that contains a <ul> with 4–5 <li> items; each item starts with <strong>Question</strong> followed by the concise answer text. (4) Add an <h3>"Summary"</h3> section with a concise closing paragraph. (5) End with 6–12 lines of hashtags, each on its own line, starting with #. Never fabricate unavailable specs; infer themes from title/content only. Return only valid JSON.';
        }

        $guidelines = array(
            'content_use_html_only_no_markdown',
            'faq_list_items_4_to_5_inline_q_and_answer',
            'hashtags_6_to_12_lines_each_prefixed_with_hash',
            $car_details_enabled ? 'keywords_include_year_make_model_trim_engine_transmission_price_mileage_if_available' : 'keywords_infer_from_post_title_and_content',
            'avoid_fabricating_unavailable_specs'
        );
        if ($sub_enabled) {
            $guidelines[] = 'first_line_is_h2_anchor_dynamic_conversion_subheadline_link_to_homepage';
            $guidelines[] = 'subheadline_6_to_12_words_title_case_no_emojis_no_boilerplate';
            $guidelines[] = 'intro_paragraph_follows_h2_anchor';
        } else {
            $guidelines[] = 'no_subheadline_start_with_intro_paragraph';
        }

        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => wp_json_encode(array(
                'task' => 'generate_used_car_post',
                'requested_fields' => $fields,
                'details' => $details,
                'format' => array('title','content','seo_title','seo_description','seo_keywords'),
                'guidelines' => $guidelines,
                'length_targets' => array(
                    'title_chars_min' => 60,
                    'title_chars_max' => 70,
                    'seo_title_chars_min' => 55,
                    'seo_title_chars_max' => 60,
                    'seo_description_chars_min' => 150,
                    'seo_description_chars_max' => 160,
                    'content_words_min' => 700,
                    'content_words_max' => 1200
                )
            )))
        );

        $resp = ucs_ai_chat_completion($messages, array());
        if (is_wp_error($resp)) return $resp;

        $content = '';
        if (!empty($resp['choices'][0]['message']['content'])) {
            $content = $resp['choices'][0]['message']['content'];
        }
        $json = json_decode($content, true);
        if (!is_array($json)) {
            // Try to extract JSON between braces
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $json = json_decode($m[0], true);
            }
        }
        if (!is_array($json)) return new WP_Error('ucs_ai_bad_json', __('Model did not return JSON.', 'used-cars-search'));

        // Normalize keys
        $out = array(
            'title' => isset($json['title']) ? $json['title'] : '',
            'content' => isset($json['content']) ? $json['content'] : '',
            'seo_title' => isset($json['seo_title']) ? $json['seo_title'] : '',
            'seo_description' => isset($json['seo_description']) ? $json['seo_description'] : '',
            'seo_keywords' => isset($json['seo_keywords']) ? $json['seo_keywords'] : '',
        );

        // Fallbacks to guarantee SEO fields are present
        // 1) SEO Title
        if (in_array('seo_title', $fields, true) && empty($out['seo_title'])) {
            $base_title = $out['title'] ? $out['title'] : get_the_title($post_id);
            if (function_exists('mb_substr')) {
                $out['seo_title'] = mb_substr($base_title, 0, 60);
            } else {
                $out['seo_title'] = substr($base_title, 0, 60);
            }
        }
        // 2) SEO Description from content text
        if (in_array('seo_description', $fields, true) && empty($out['seo_description'])) {
            $text = wp_strip_all_tags($out['content']);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if (function_exists('mb_substr')) {
                $out['seo_description'] = mb_substr($text, 0, 160);
            } else {
                $out['seo_description'] = substr($text, 0, 160);
            }
        }
        // 3) SEO Keywords from meta (Year/Make/Model/Trim)
        if (in_array('seo_keywords', $fields, true) && empty($out['seo_keywords'])) {
            if ($car_details_enabled) {
                $kw = array();
                if ($year && $make && $model) { $kw[] = trim($year.' '.$make.' '.$model.($trim?(' '.$trim):'')); }
                if ($make && $model) { $kw[] = $make.' '.$model; }
                if ($make) { $kw[] = 'used '.$make; }
                if ($model) { $kw[] = 'used '.$model; }
                if ($make && $model) { $kw[] = $make.' '.$model.' for sale'; }
                $kw = array_values(array_unique(array_filter($kw)));
                $out['seo_keywords'] = implode(', ', array_slice($kw, 0, 6));
            } else {
                $base_text = get_the_title($post_id) . ' ' . wp_strip_all_tags($out['content']);
                $out['seo_keywords'] = ucs_ai_extract_keywords_from_text($base_text, 6);
            }
        }

        return $out;
    }
}

if (!function_exists('ucs_ai_ajax_test_connection')) {
    function ucs_ai_ajax_test_connection() {
        if (!current_user_can('manage_options')) {
            // translators: Error message when user doesn't have permission
            wp_send_json_error(array('message' => __('Unauthorized', 'used-cars-search')), 403);
        }
        check_ajax_referer('ucs_ai_nonce', 'nonce');

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        if (!$api_key) {
            $opts = ucs_ai_get_options();
            $api_key = $opts['api_key'];
        }
        if (!$api_key) {
            // translators: Error message when the API key is not provided
            wp_send_json_error(array('message' => __('API key is empty.', 'used-cars-search')));
        }

        $messages = array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => 'Reply with: OK')
        );
        $result = ucs_ai_chat_completion($messages, array('api_key' => $api_key, 'max_tokens' => 5));
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'data'    => $result->get_error_data(),
            ));
        }
        $content = '';
        if (!empty($result['choices'][0]['message']['content'])) {
            $content = trim($result['choices'][0]['message']['content']);
        }
        $ok = strtoupper($content) === 'OK';
        wp_send_json_success(array(
            'ok' => $ok,
            'raw' => $content,
            'model' => isset($result['model']) ? $result['model'] : '',
            'usage' => isset($result['usage']) ? $result['usage'] : new stdClass(),
        ));
    }
    add_action('wp_ajax_ucs_ai_test_connection', 'ucs_ai_ajax_test_connection');
}
