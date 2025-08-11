<?php
// includes/seo.php
if (!defined('ABSPATH')) exit;

// Meta keys
const UCS_SEO_TITLE_KEY = '_ucs_seo_title';
const UCS_SEO_DESC_KEY = '_ucs_seo_description';
const UCS_SEO_KEYS_KEY = '_ucs_seo_keywords';
const UCS_SEO_NOINDEX_KEY = '_ucs_seo_noindex';

/**
 * Determine if a conflicting SEO plugin is active.
 * Basic detection for Yoast SEO and Rank Math to avoid duplicate tags.
 */
function ucs_seo_conflicting_plugin_active() {
    return (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION'));
}

/**
 * Should this plugin output SEO tags/overrides?
 * Defaults to false when a known SEO plugin is active.
 * Can be overridden via the 'ucs_seo_enable_output' filter.
 */
function ucs_seo_should_output() {
    $default = ! ucs_seo_conflicting_plugin_active();
    /**
     * Filter: ucs_seo_enable_output
     * Return true to allow this plugin to output SEO tags, false to disable.
     */
    return (bool) apply_filters('ucs_seo_enable_output', $default);
}

// Add meta box
function ucs_seo_get_post_types() {
    $screens = apply_filters('ucs_seo_post_types', ['post']);
    return is_array($screens) ? $screens : ['post'];
}

add_action('add_meta_boxes', function() {
    add_meta_box(
        'ucs_seo_meta',
        __('Used Cars SEO', 'used-cars-search'),
        'ucs_seo_render_meta_box',
        ucs_seo_get_post_types(),
        'normal',
        'default'
    );
});

function ucs_seo_render_meta_box($post) {
    wp_nonce_field('ucs_seo_meta_nonce', 'ucs_seo_meta_nonce');
    $seo_title = get_post_meta($post->ID, UCS_SEO_TITLE_KEY, true);
    $seo_desc  = get_post_meta($post->ID, UCS_SEO_DESC_KEY, true);
    $seo_keys  = get_post_meta($post->ID, UCS_SEO_KEYS_KEY, true);
    $noindex   = (bool) get_post_meta($post->ID, UCS_SEO_NOINDEX_KEY, true);
    ?>
    <p>
        <label for="ucs_seo_title"><strong><?php esc_html_e('SEO Title', 'used-cars-search'); ?></strong></label><br />
        <input type="text" id="ucs_seo_title" name="ucs_seo_title" value="<?php echo esc_attr($seo_title); ?>" class="widefat" maxlength="120" placeholder="Custom title for SERPs (recommended up to ~60 chars)" />
    </p>
    <p>
        <label for="ucs_seo_description"><strong><?php esc_html_e('SEO Description', 'used-cars-search'); ?></strong></label><br />
        <textarea id="ucs_seo_description" name="ucs_seo_description" class="widefat" rows="3" maxlength="320" placeholder="Custom meta description (recommended up to ~160 chars)"><?php echo esc_textarea($seo_desc); ?></textarea>
    </p>
    <p>
        <label for="ucs_seo_keywords"><strong><?php esc_html_e('SEO Keywords', 'used-cars-search'); ?></strong></label><br />
        <input type="text" id="ucs_seo_keywords" name="ucs_seo_keywords" value="<?php echo esc_attr($seo_keys); ?>" class="widefat" placeholder="Comma-separated keywords (legacy)" />
    </p>
    <p>
        <label for="ucs_seo_noindex">
            <input type="checkbox" id="ucs_seo_noindex" name="ucs_seo_noindex" value="1" <?php checked($noindex); ?> />
            <?php esc_html_e('No-index this post', 'used-cars-search'); ?>
        </label>
    </p>
    <?php
}

// Save meta
add_action('save_post', function($post_id) {
    if (!isset($_POST['ucs_seo_meta_nonce']) || !wp_verify_nonce($_POST['ucs_seo_meta_nonce'], 'ucs_seo_meta_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Sanitize
    $title = isset($_POST['ucs_seo_title']) ? sanitize_text_field(wp_unslash($_POST['ucs_seo_title'])) : '';
    $desc  = isset($_POST['ucs_seo_description']) ? sanitize_textarea_field(wp_unslash($_POST['ucs_seo_description'])) : '';
    $keys  = isset($_POST['ucs_seo_keywords']) ? sanitize_text_field(wp_unslash($_POST['ucs_seo_keywords'])) : '';
    $noidx = !empty($_POST['ucs_seo_noindex']) ? '1' : '';

    // Soft length caps
    if (function_exists('mb_substr')) {
        $title = mb_substr($title, 0, 120);
        $desc  = mb_substr($desc, 0, 320);
        $keys  = mb_substr($keys, 0, 255);
    } else {
        $title = substr($title, 0, 120);
        $desc  = substr($desc, 0, 320);
        $keys  = substr($keys, 0, 255);
    }

    update_post_meta($post_id, UCS_SEO_TITLE_KEY, $title);
    update_post_meta($post_id, UCS_SEO_DESC_KEY, $desc);
    update_post_meta($post_id, UCS_SEO_KEYS_KEY, $keys);
    if ($noidx) {
        update_post_meta($post_id, UCS_SEO_NOINDEX_KEY, '1');
    } else {
        delete_post_meta($post_id, UCS_SEO_NOINDEX_KEY);
    }
});

// Override document <title> when SEO title is present
add_filter('pre_get_document_title', function($title) {
    if (!is_singular('post')) return $title;
    if (!ucs_seo_should_output()) return $title;
    $post_id = get_queried_object_id();
    if (!$post_id) return $title;
    $seo_title = get_post_meta($post_id, UCS_SEO_TITLE_KEY, true);
    return $seo_title ? $seo_title : $title;
}, 20);

// Output description/keywords/robots in <head>
add_action('wp_head', function() {
    if (!is_singular('post')) return;
    if (!ucs_seo_should_output()) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;
    $desc = trim(get_post_meta($post_id, UCS_SEO_DESC_KEY, true));
    $keys = trim(get_post_meta($post_id, UCS_SEO_KEYS_KEY, true));
    $noidx = (bool) get_post_meta($post_id, UCS_SEO_NOINDEX_KEY, true);

    if ($desc) {
        echo "\n<meta name=\"description\" content=\"" . esc_attr($desc) . "\" />\n";
    }
    if ($keys) {
        echo "<meta name=\"keywords\" content=\"" . esc_attr($keys) . "\" />\n";
    }
    if ($noidx) {
        echo "<meta name=\"robots\" content=\"noindex,follow\" />\n";
    }
}, 1);

// Use wp_robots API (WP 5.7+) to set noindex
add_filter('wp_robots', function($robots) {
    if (!is_singular('post')) return $robots;
    if (!ucs_seo_should_output()) return $robots;
    $post_id = get_queried_object_id();
    if (!$post_id) return $robots;
    if (get_post_meta($post_id, UCS_SEO_NOINDEX_KEY, true)) {
        $robots['noindex'] = true;
    }
    return $robots;
});
