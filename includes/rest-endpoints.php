<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {

    // --- Search Endpoint ---
    register_rest_route('popularai/v1', '/search', [
        'methods' => 'GET',
        'callback' => 'pais_rest_search',
        'permission_callback' => '__return_true',
        'args' => [
            'keyword' => ['required' => false],
            'category' => ['required' => false],
            'orderby' => ['required' => false, 'default' => 'date'],
            'order'   => ['required' => false, 'default' => 'desc'],
            'page'    => ['required' => false, 'default' => 1],
            'per_page'=> ['required' => false, 'default' => 12],
        ],
    ]);

    // --- Autosuggest Endpoint ---
    register_rest_route('popularai/v1', '/autosuggest', [
        'methods' => 'GET',
        'callback' => 'pais_rest_autosuggest',
        'permission_callback' => '__return_true',
        'args' => [
            'q' => ['required' => false],
        ],
    ]);
});

// --- Search Callback ---
function pais_rest_search($request) {
        // 1. Setup WP_Query arguments for efficient, paginated search
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => intval($request['per_page']),
        'paged'          => intval($request['page']),
        'orderby'        => sanitize_text_field($request['orderby']),
        'order'          => sanitize_text_field($request['order']),
        'post_status'    => 'publish',
        'pais_safe_search' => true, // Custom flag to activate our new safe performance filter
    ];

    // 2. Add category and keyword filters if they exist
    if (!empty($request['category'])) {
        $args['category_name'] = sanitize_text_field($request['category']);
    }
    if (!empty($request['keyword'])) {
        $args['s'] = sanitize_text_field($request['keyword']);
    }

    // 3. Handle special 'comments' orderby case
    if ($request['orderby'] === 'comments') {
        $args['orderby'] = 'comment_count';
    }

    // 4. Execute the optimized query
    $query = new WP_Query($args);

    // 5. Format the results
    $posts = array_map(function($post) {
        $rating = pais_get_rating_for_post($post->ID);
        return [
            'ID'        => $post->ID,
            'title'     => get_the_title($post),
            'excerpt'   => get_the_excerpt($post),
            'permalink' => get_permalink($post),
            'category'  => get_the_category_list(', ', '', $post->ID),
            'date'      => get_the_date('', $post->ID),
            'comments'  => get_comments_number($post->ID),
            'rating'    => $rating['avg'],
            'votes'     => $rating['count'],
        ];
    }, $query->posts);

    // 6. Return the response with pagination data from the query itself
    return [
        'total'         => intval($query->found_posts),
        'posts'         => $posts,
        'max_num_pages' => intval($query->max_num_pages),
        'current_page'  => intval($request['page']),
    ];
}



// --- Autosuggest Callback ---
function pais_rest_autosuggest($request) {
    $q = strtolower(sanitize_text_field($request['q']));
    $stopwords = pais_get_stopwords();
    global $wpdb;
    $sql = $wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' LIMIT 3000");
    $results = $wpdb->get_results($sql);
    $keywords = [];
    foreach ($results as $row) {
        $words = preg_split('/\W+/', strtolower($row->post_title));
        foreach ($words as $word) {
            if (strlen($word) < 3) continue;
            if (in_array($word, $stopwords)) continue;
            if ($q && strpos($word, $q) !== 0) continue;
            $keywords[$word] = true;
        }
    }
    $keywords = array_keys($keywords);
    sort($keywords);
    return array_slice($keywords, 0, 10);
}

// --- Helper: Get stopwords as array ---
function pais_get_stopwords() {
    $raw = get_option('pais_stopwords', 'the,an,and,or,but,if,then,so,for,of,on,at,by,with,a');
    $stopwords = array_map('trim', explode(',', strtolower($raw)));
    return array_filter($stopwords);
}


add_action('rest_api_init', function() {
    register_rest_route('popularai/v1', '/rate', array(
        'methods' => 'POST',
        'callback' => function($request) {
            global $wpdb;
            $post_id = intval($request->get_param('post_id'));
            $rating = intval($request->get_param('rating'));
            $ip = $_SERVER['REMOTE_ADDR'];

            if ($rating < 1 || $rating > 5 || !$post_id) {
                return new WP_Error('invalid', 'Invalid rating or post.', ['status' => 400]);
            }

            $table = $wpdb->prefix . 'pais_ratings';
            // Optionally: prevent duplicate voting by IP for this post
            $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND ip=%s", $post_id, $ip));
            if ($existing) {
                return new WP_Error('duplicate', 'Already voted from this IP.', ['status' => 403]);
            }

            $wpdb->insert($table, [
                'post_id' => $post_id,
                'rating'  => $rating,
                'ip'      => $ip
            ]);
            return ['success' => true];
        },
        'permission_callback' => '__return_true'
    ));
});


function pais_get_rating_for_post($post_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'pais_ratings';
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count FROM $table WHERE post_id = %d",
        $post_id
    ), ARRAY_A);

    // If there are no ratings, avg_rating will be NULL. Default to 0.
    $avg_rating = $result && $result['avg_rating'] !== null ? (float)$result['avg_rating'] : 0;
    $rating_count = $result ? (int)$result['rating_count'] : 0;

    return [
        'avg'   => round($avg_rating, 1),
        'count' => $rating_count,
    ];
}

// Get categories with post count
function pais_get_categories_with_count() {
    $categories = get_categories([
        'hide_empty' => true, // Only show categories with posts
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    
    $result = [];
    foreach ($categories as $category) {
        $result[] = [
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'count' => $category->count,
        ];
    }
    
    return $result;
}

// Add categories with post count endpoint
add_action('rest_api_init', function() {
    register_rest_route('popularai/v1', '/categories', [
        'methods' => 'GET',
        'callback' => 'pais_get_categories_with_count',
        'permission_callback' => '__return_true',
    ]);
});

add_action('rest_api_init', function() {
    register_rest_route('popularai/v1', '/rating', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $post_id = intval($request->get_param('post_id'));
            return pais_get_rating_for_post($post_id);
        },
        'permission_callback' => '__return_true'
    ));
});

/**
 * Modifies the search query to enforce a safer, LIKE-based whole-word matching.
 * This is much more performant than filtering in PHP and safer than REGEXP.
 *
 * @param string   $search   The existing search SQL from WordPress.
 * @param WP_Query $wp_query The current query object.
 * @return string The modified search SQL.
 */
function pais_safe_whole_word_filter($search, $wp_query) {
    // Only apply this filter if our custom query var is set
    if (!empty($wp_query->get('pais_safe_search')) && !empty($wp_query->get('s'))) {
        global $wpdb;
        $term = $wp_query->get('s');

        // We need to build a custom WHERE clause that checks for the term as a whole word.
        $like_clauses = [
            $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", '% ' . $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", '% ' . $wpdb->esc_like($term)),
            $wpdb->prepare("{$wpdb->posts}.post_title = %s", $term),
            $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", '% ' . $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", '% ' . $wpdb->esc_like($term)),
            $wpdb->prepare("{$wpdb->posts}.post_excerpt = %s", $term),
            $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", '% ' . $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", $wpdb->esc_like($term) . ' %'),
            $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", '% ' . $wpdb->esc_like($term)),
            $wpdb->prepare("{$wpdb->posts}.post_content = %s", $term),
        ];
        
        // WordPress's default search is inside the $search parameter. We replace it.
        $search = " AND (" . implode(' OR ', $like_clauses) . ")";
    }
    return $search;
}
add_filter('posts_search', 'pais_safe_whole_word_filter', 10, 2);


