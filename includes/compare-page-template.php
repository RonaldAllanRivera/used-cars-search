<?php
// includes/compare-page-template.php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'rest-endpoints.php';

function render_compare_page() {
    ob_start();

    $compare_ids_str = isset($_GET['compare_ids']) ? sanitize_text_field($_GET['compare_ids']) : '';
    $compare_ids = array_filter(array_map('intval', explode(',', $compare_ids_str)));

    if (empty($compare_ids)) {
        echo '<div class="pais-compare-page"><p>No items selected for comparison. Please go back and select up to 4 items.</p></div>';
        return ob_get_clean();
    }

    $args = [
        'post_type' => 'post',
        'post__in' => $compare_ids,
        'posts_per_page' => count($compare_ids),
        'orderby' => 'post__in',
    ];

    $query = new WP_Query($args);
    $posts_data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $rating = pais_get_rating_for_post($post_id);
            $posts_data[$post_id] = [
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_content(), 40, '...'),
                'category' => get_the_category_list(', ', '', $post_id),
                'date' => get_the_date(),
                'comments' => get_comments_number(),
                'rating' => $rating['avg'],
                'votes' => $rating['count'],
            ];
        }
        wp_reset_postdata();
    }

    ?>
    <div class="pais-compare-page">
        <h1>Compare AI Software</h1>
        <?php if (!empty($posts_data)): ?>
            <div class="pais-results-grid pais-compare-grid">
                <?php foreach ($posts_data as $post): ?>
                    <div class="pais-result-item">
                        <h3><a href="<?php echo esc_url($post['permalink']); ?>" target="_blank"><?php echo esc_html($post['title']); ?></a></h3>
                        <p class="pais-excerpt"><?php echo esc_html($post['excerpt']); ?></p>
                        <div class="pais-result-meta">
                             <div class="pais-category"><?php echo $post['category']; ?></div>
                             <div class="pais-rating">
                                <?php if ($post['votes'] > 0): ?>
                                    <span class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="dashicons dashicons-star-<?php echo ($i <= round($post['rating'])) ? 'filled' : 'empty'; ?>"></span>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="pais-votes">(<?php echo esc_html($post['votes']); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="pais-comments"><span class="dashicons dashicons-admin-comments"></span> <?php echo esc_html($post['comments']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Could not retrieve the selected items. Please try again.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
