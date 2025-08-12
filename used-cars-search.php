<?php
// used-cars-search.php
/*
Plugin Name: Used Cars Search
Description: AJAX-powered, vanilla JS WordPress plugin for advanced used car search with autosuggest, grid/list view, and Elementor support.
Version: 0.2.0
Author: Ronald Allan Rivera
*/


if ( ! defined('ABSPATH') ) exit; // Prevent direct access

if (is_admin()) {
    require_once __DIR__ . '/includes/admin.php';
    // AI modules: admin-only to avoid touching frontend
    require_once __DIR__ . '/includes/ai.php';
    require_once __DIR__ . '/includes/admin-ai.php';
    require_once __DIR__ . '/includes/admin-ai-assist.php';
}

// Load REST endpoints
add_action('plugins_loaded', function() {
    require_once __DIR__ . '/includes/rest-endpoints.php';
    require_once __DIR__ . '/includes/compare-page-template.php';
    // SEO module: adds SEO meta box and head tags safely
    require_once __DIR__ . '/includes/seo.php';
    // Meta registration: expose car details + SEO fields to REST API
    require_once __DIR__ . '/includes/meta.php';
});

// 1. Enqueue Vanilla JS and CSS only where shortcode/widget is present

add_action('wp_enqueue_scripts', function() {
    $post = get_post();
    // Load scripts on single posts (for star ratings) or on pages with the shortcodes.
    if (!is_singular('post') && (!$post || (strpos($post->post_content, '[used_cars_search]') === false && strpos($post->post_content, '[ucs_compare_page]') === false))) {
        return;
    }
    wp_enqueue_script(
        'ucs-main-js',
        plugins_url('assets/js/main.js', __FILE__),
        array(),
        '1.0',
        true
    );
    $options = get_option('ucs_options');
    $compare_page_id = !empty($options['compare_page_id']) ? $options['compare_page_id'] : 0;
    $compare_page_url = $compare_page_id ? get_permalink($compare_page_id) : home_url('/');

    wp_localize_script('ucs-main-js', 'ucs_vars', array(
        'rest_url' => trailingslashit(home_url()) . 'wp-json/',
        'compare_page_url' => $compare_page_url,
    ));
    wp_add_inline_script('ucs-main-js', 'window.ucs_vars = window.ucs_vars || {};
');

    wp_enqueue_script(
        'ucs-compare-js',
        plugins_url('assets/js/compare.js', __FILE__),
        array('wp-hooks'), // Add dependency
        '0.1',
        true
    );


    // Set as module type for ES6 imports
    add_filter('script_loader_tag', function($tag, $handle) {
        if (in_array($handle, ['ucs-main-js', 'ucs-compare-js'])) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);

    wp_enqueue_style(
            'ucs-style',
            plugins_url('assets/css/styles.css', __FILE__),
            [],
            '0.1'
        );

    wp_enqueue_style(
            'ucs-compare-style',
            plugins_url('assets/css/compare.css', __FILE__),
            ['ucs-style'],
            '0.1'
        );

    // Enqueue styles for compare page specifically
    if (is_singular() && has_shortcode(get_post(get_the_ID())->post_content, 'ucs_compare_page')) {
        wp_enqueue_style('ucs-style');
        wp_enqueue_style('ucs-compare-style');
        wp_enqueue_style('dashicons');
    }
});

add_action('admin_enqueue_scripts', function($hook) {
    // Only load on your plugin’s admin pages, or everywhere if you want
    // To target your custom admin pages, check $hook value, e.g. 'toplevel_page_ucs_admin'
    wp_enqueue_style(
        'ucs-admin-style',
        plugins_url('assets/css/styles.css', __FILE__),
        [],
        '1.0'
    );
});

// 2. Register Shortcode
function ucs_render_search_shortcode($atts) {
    ob_start(); ?>
    <div id="ucs-search-root">
        <div id="ucs-search-form">
            <input type="text" id="ucs-keyword" placeholder="Type keyword..." autocomplete="off">
            <select id="ucs-category">
                <option value="">All Categories</option>
                <!-- More categories will be loaded here -->
            </select>
            <button id="ucs-search-btn" type="button">Search</button>
        </div>
        <div id="ucs-autosuggest" style="position:relative;"></div>
        <div id="ucs-results"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('used_cars_search', 'ucs_render_search_shortcode');
add_shortcode('ucs_compare_page', 'render_compare_page');

register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'ucs_ratings';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        rating TINYINT NOT NULL,
        ip VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// [ucs_star_rating]
add_shortcode('ucs_star_rating', function() {
    if (!is_singular('post')) return '';
    global $post;
    ob_start();
    ?>
        <div class="ucs-rating-widget">
        <div class="ucs-rating-header">How useful was this post?</div>
        <div id="ucs-star-rating" data-post="<?php echo esc_attr($post->ID); ?>"></div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var el = document.getElementById('ucs-star-rating');
        if (!el) return;
        var postId = el.dataset.post;
        let rated = false, userRating = 0;
        var rest_url = (window.ucs_vars && window.ucs_vars.rest_url) ? window.ucs_vars.rest_url : '/wp-json/';
        fetch(rest_url + 'usedcars/v1/rating?post_id=' + postId)
            .then(res => res.json())
            .then(data => renderStars(data.avg, data.count));
        function renderStars(avg, count) {
            let rounded = Math.round(avg || 0);
            el.innerHTML = `<div class="ucs-star-row">
                ${[1,2,3,4,5].map(n =>
                    `<span class="ucs-star${n<=rounded?' selected':''}" data-value="${n}" title="${n} star${n>1?'s':''}">&#9733;</span>`
                ).join('')}
            </div>
            <div class="ucs-rating-summary">${avg ? (avg + ' / 5') : 'No rating yet'} (${count} vote${count==1?'':'s'})</div>
            <div id="ucs-rating-msg" style="margin-top:3px;color:#008800;font-size:0.99em;"></div>`;
            if (!rated) {
                var stars = el.querySelectorAll('.ucs-star');
                stars.forEach(star => {
                    // Highlight stars on hover
                    star.addEventListener('mouseenter', function() {
                        let val = parseInt(this.dataset.value, 10);
                        stars.forEach((s, idx) => {
                            s.classList.toggle('highlighted', idx < val);
                        });
                    });
                    // Remove highlight on mouseout
                    star.addEventListener('mouseleave', function() {
                        stars.forEach(s => s.classList.remove('highlighted'));
                    });
                    // Click to rate
                    star.onclick = function() {
                        let value = this.dataset.value;
                        userRating = value;
                        fetch(rest_url + 'usedcars/v1/rate', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({post_id: postId, rating: value})
                        }).then(r=>r.json()).then(resp => {
                            document.getElementById('ucs-rating-msg').innerText =
                                resp.success ? "Thanks for rating!" : (resp.message || "Error.");
                            rated = true;
                            // re-render to lock in selection
                            renderStars(value, count + 1);
                        });
                    };
                });
                // Remove highlight from all on leaving the star row area
                el.querySelector('.ucs-star-row').addEventListener('mouseleave', function() {
                    stars.forEach(s => s.classList.remove('highlighted'));
                });
            }
        }
    });
    </script>
    <?php
    return ob_get_clean();
});



add_filter('the_content', function($content) {
    if (is_singular('post')) {
        $star_widget = do_shortcode('[ucs_star_rating]');
        // Add the widget BEFORE or AFTER content (choose one)
        // return $star_widget . $content; // Widget before content
        return $content . $star_widget;    // Widget after content (recommended)
    }
    return $content;
});
