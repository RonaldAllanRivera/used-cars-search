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
            'max_tokens'  => 800,
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

if (!function_exists('ucs_ai_http_headers')) {
    function ucs_ai_http_headers($api_key) {
        return array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
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
            return new WP_Error('ucs_ai_http_error', sprintf(__('OpenAI HTTP %d', 'used-cars-search'), $code), array('body' => $raw));
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
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

        if (in_array('title', $fields, true) && !empty($payload['title'])) {
            $post_update['post_title'] = sanitize_text_field($payload['title']);
            $updated[] = 'title';
        }
        if (in_array('content', $fields, true) && !empty($payload['content'])) {
            $post_update['post_content'] = wp_kses_post($payload['content']);
            $updated[] = 'content';
        }
        if (count($post_update) > 1) {
            wp_update_post($post_update);
        }

        if (in_array('seo_title', $fields, true) && !empty($payload['seo_title'])) {
            update_post_meta($post_id, '_ucs_seo_title', sanitize_text_field($payload['seo_title']));
            $updated[] = 'seo_title';
        }
        if (in_array('seo_description', $fields, true) && !empty($payload['seo_description'])) {
            update_post_meta($post_id, '_ucs_seo_description', sanitize_textarea_field($payload['seo_description']));
            $updated[] = 'seo_description';
        }
        if (in_array('seo_keywords', $fields, true) && !empty($payload['seo_keywords'])) {
            update_post_meta($post_id, '_ucs_seo_keywords', sanitize_text_field($payload['seo_keywords']));
            $updated[] = 'seo_keywords';
        }

        return array('updated' => $updated);
    }
}

// Core: Generate AI suggestions for a post id (shared by Cron)
if (!function_exists('ucs_ai_generate_for_post_core')) {
    function ucs_ai_generate_for_post_core($post_id, $fields = array('title','content','seo_title','seo_description','seo_keywords')) {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('ucs_ai_no_post', __('Post not found.', 'used-cars-search'));

        $fields = is_array($fields) ? $fields : array('title','content','seo_title','seo_description','seo_keywords');

        $year = get_post_meta($post_id, 'ucs_year', true);
        $make = get_post_meta($post_id, 'ucs_make', true);
        $model = get_post_meta($post_id, 'ucs_model', true);
        $trim = get_post_meta($post_id, 'ucs_trim', true);
        $price = get_post_meta($post_id, 'ucs_price', true);
        $mileage = get_post_meta($post_id, 'ucs_mileage', true);
        $engine = get_post_meta($post_id, 'ucs_engine', true);
        $trans = get_post_meta($post_id, 'ucs_transmission', true);

        $details = array(
            'year' => $year, 'make' => $make, 'model' => $model, 'trim' => $trim,
            'price' => $price, 'mileage' => $mileage, 'engine' => $engine, 'transmission' => $trans,
        );

        $requested = implode(',', $fields);
        $messages = array(
            array('role' => 'system', 'content' => 'You are an assistant that writes concise, market-ready automotive listings. Always return STRICT JSON only.'),
            array('role' => 'user', 'content' => wp_json_encode(array(
                'task' => 'generate_used_car_post',
                'requested_fields' => $fields,
                'details' => $details,
                'format' => array('title','content','seo_title','seo_description','seo_keywords')
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
        return $out;
    }
}

if (!function_exists('ucs_ai_ajax_test_connection')) {
    function ucs_ai_ajax_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'used-cars-search')), 403);
        }
        check_ajax_referer('ucs_ai_nonce', 'nonce');

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        if (!$api_key) {
            $opts = ucs_ai_get_options();
            $api_key = $opts['api_key'];
        }
        if (!$api_key) {
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
