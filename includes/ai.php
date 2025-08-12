<?php
if (!defined('ABSPATH')) exit;

// Core AI utilities for Used Cars Search
// This file contains the low-level client for OpenAI and shared helpers.

if (!function_exists('ucs_ai_get_options')) {
    function ucs_ai_get_options() {
        $defaults = array(
            'enabled'     => 0,
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
