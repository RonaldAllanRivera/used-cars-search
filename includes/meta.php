<?php
if (!defined('ABSPATH')) exit;

// Sanitizers must accept 4 params: ($value, $meta_key, $object_type, $object_subtype)
function ucs_sanitize_integer($value, $meta_key = '', $object_type = '', $object_subtype = '') {
    if (is_array($value) || is_object($value)) return 0;
    return intval($value);
}
function ucs_sanitize_number($value, $meta_key = '', $object_type = '', $object_subtype = '') {
    if (is_array($value) || is_object($value)) return 0.0;
    return floatval($value);
}
function ucs_sanitize_text($value, $meta_key = '', $object_type = '', $object_subtype = '') {
    if (is_array($value) || is_object($value)) return '';
    return sanitize_text_field($value);
}
function ucs_sanitize_textarea($value, $meta_key = '', $object_type = '', $object_subtype = '') {
    if (is_array($value) || is_object($value)) return '';
    return sanitize_textarea_field($value);
}
function ucs_sanitize_boolean($value, $meta_key = '', $object_type = '', $object_subtype = '') {
    // Normalize truthy/falsey values consistently for REST
    return (bool) rest_sanitize_boolean($value);
}

// Authorization callback: allow meta updates when the user can edit the object
function ucs_meta_auth_callback($allowed, $meta_key, $object_id, $user_id, $cap, $caps) {
    // Default deny if we cannot determine object
    if (!$object_id) return false;
    // Posts: require capability to edit the specific post
    if (get_post_type($object_id)) {
        return user_can($user_id, 'edit_post', $object_id);
    }
    // Otherwise fall back to WordPress' computed allowance
    return (bool) $allowed;
}

/**
 * Register car details and SEO meta for REST API usage.
 * Exposes fields under the WP core /wp/v2/posts endpoint via the "meta" object.
 */
add_action('init', function() {
    $car_post_types = apply_filters('ucs_car_post_types', ['post']);
    $seo_post_types = function_exists('ucs_seo_get_post_types') ? ucs_seo_get_post_types() : ['post'];

    // Car details meta keys
    $car_meta = [
        'ucs_year'          => ['type' => 'integer', 'sanitize' => 'ucs_sanitize_integer'],
        'ucs_make'          => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        'ucs_model'         => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        'ucs_trim'          => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        'ucs_price'         => ['type' => 'number',  'sanitize' => 'ucs_sanitize_number'],
        'ucs_mileage'       => ['type' => 'integer', 'sanitize' => 'ucs_sanitize_integer'],
        'ucs_engine'        => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        'ucs_transmission'  => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
    ];

    foreach ($car_post_types as $pt) {
        foreach ($car_meta as $key => $args) {
            register_post_meta($pt, $key, [
                'type'              => $args['type'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $args['sanitize'],
                'auth_callback'     => 'ucs_meta_auth_callback',
            ]);
        }
    }

    // SEO meta keys (protected keys with underscore are fine with show_in_rest=true)
    $seo_meta = [
        '_ucs_seo_title'       => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        '_ucs_seo_description' => ['type' => 'string',  'sanitize' => 'ucs_sanitize_textarea'],
        '_ucs_seo_keywords'    => ['type' => 'string',  'sanitize' => 'ucs_sanitize_text'],
        '_ucs_seo_noindex'     => ['type' => 'boolean', 'sanitize' => 'ucs_sanitize_boolean'],
    ];

    foreach ($seo_post_types as $pt) {
        foreach ($seo_meta as $key => $args) {
            register_post_meta($pt, $key, [
                'type'              => $args['type'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $args['sanitize'],
                'auth_callback'     => 'ucs_meta_auth_callback',
            ]);
        }
    }
});
