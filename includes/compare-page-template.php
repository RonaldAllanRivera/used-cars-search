<?php
// includes/compare-page-template.php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'rest-endpoints.php';

function render_compare_page() {
    ob_start();

    $compare_ids_str = isset($_GET['compare_ids']) ? sanitize_text_field($_GET['compare_ids']) : '';
    $compare_ids = array_filter(array_map('intval', explode(',', $compare_ids_str)));

    if (empty($compare_ids)) {
        echo '<div class="ucs-compare-page"><p>No items selected for comparison. Please go back and select up to 4 items.</p></div>';
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
            $rating = ucs_get_rating_for_post($post_id);
            // Fetch car details meta
            $year = get_post_meta($post_id, 'ucs_year', true);
            $make = get_post_meta($post_id, 'ucs_make', true);
            $model = get_post_meta($post_id, 'ucs_model', true);
            $trim = get_post_meta($post_id, 'ucs_trim', true);
            $price = get_post_meta($post_id, 'ucs_price', true);
            $mileage = get_post_meta($post_id, 'ucs_mileage', true);
            $engine = get_post_meta($post_id, 'ucs_engine', true);
            $transmission = get_post_meta($post_id, 'ucs_transmission', true);

            $posts_data[$post_id] = [
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_content(), 40, '...'),
                'category' => get_the_category_list(', ', '', $post_id),
                'date' => get_the_date(),
                'comments' => get_comments_number(),
                'rating' => $rating['avg'],
                'votes' => $rating['count'],
                'details' => [
                    'year' => $year,
                    'make' => $make,
                    'model' => $model,
                    'trim' => $trim,
                    'price' => $price,
                    'mileage' => $mileage,
                    'engine' => $engine,
                    'transmission' => $transmission,
                ],
            ];
        }
        wp_reset_postdata();
    }

    ?>
    <div class="ucs-compare-page">
        <?php if (!empty($posts_data)): ?>
            <div class="ucs-results-grid ucs-compare-grid">
                <?php foreach ($posts_data as $post): ?>
                    <div class="ucs-result-item">
                        <h3><a href="<?php echo esc_url($post['permalink']); ?>" target="_blank"><?php echo esc_html($post['title']); ?></a></h3>

                        <?php
                        // Build a compact top line: Year Make Model Trim
                        $top_line_parts = [];
                        if (!empty($post['details']['year'])) $top_line_parts[] = esc_html($post['details']['year']);
                        if (!empty($post['details']['make'])) $top_line_parts[] = esc_html($post['details']['make']);
                        if (!empty($post['details']['model'])) $top_line_parts[] = esc_html($post['details']['model']);
                        if (!empty($post['details']['trim'])) $top_line_parts[] = esc_html($post['details']['trim']);
                        $top_line = trim(implode(' ', $top_line_parts));
                        ?>

                        <?php if ($top_line): ?>
                            <div class="ucs-car-info" style="margin-bottom:0.5rem; font-weight:600; color:#111827;">
                                <?php echo $top_line; ?>
                            </div>
                        <?php endif; ?>

                        <div class="ucs-car-tags" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.6rem;">
                            <?php if (!empty($post['details']['price']) && is_numeric($post['details']['price'])): ?>
                                <span class="ucs-car-info">$<?php echo esc_html( number_format_i18n( floatval($post['details']['price']) ) ); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($post['details']['mileage']) && is_numeric($post['details']['mileage'])): ?>
                                <span class="ucs-mileage-tag"><?php echo esc_html( number_format_i18n( intval($post['details']['mileage']) ) ); ?> <?php echo esc_html__( 'miles', 'used-cars-search' ); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($post['details']['engine'])): ?>
                                <span class="ucs-engine-tag"><?php echo esc_html($post['details']['engine']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($post['details']['transmission'])): ?>
                                <span class="ucs-transmission-tag"><?php echo esc_html($post['details']['transmission']); ?></span>
                            <?php endif; ?>
                        </div>

                        <p class="ucs-excerpt"><?php echo esc_html($post['excerpt']); ?></p>

                        <div class="ucs-result-meta">
                            <div class="ucs-category"><?php echo $post['category']; ?></div>
                            <div class="ucs-rating">
                                <?php if ($post['votes'] > 0): ?>
                                    <span class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="dashicons dashicons-star-<?php echo ($i <= round($post['rating'])) ? 'filled' : 'empty'; ?>"></span>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="ucs-votes">(<?php echo esc_html($post['votes']); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="ucs-comments"><span class="dashicons dashicons-admin-comments"></span> <?php echo esc_html($post['comments']); ?></div>
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
