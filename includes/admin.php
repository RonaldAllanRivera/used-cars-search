<?php
error_log('Loaded: admin.php');
if (!defined('ABSPATH')) exit;

// 1. ADMIN SIDEBAR PAGE (already working)
add_action('admin_menu', function() {
    add_menu_page(
        'AI Software Search Admin',
        'AI Software Search',
        'manage_options',
        'pais_admin',
        'pais_admin_page',
        'dashicons-search',
        25
    );
    add_submenu_page(
        'pais_admin',
        'Ratings Management',
        'Ratings Management',
        'manage_options',
        'pais_admin_ratings',
        'pais_admin_ratings_page'
    );
});

add_action('admin_init', 'pais_settings_init');

function pais_settings_init() {
    register_setting('popular-ai-software-search', 'pais_options');

    add_settings_section(
        'pais_settings_section',
        'Plugin Settings',
        'pais_settings_section_callback',
        'popular-ai-software-search'
    );

    add_settings_field(
        'pais_compare_page_id',
        'Compare Page',
        'pais_compare_page_id_callback',
        'popular-ai-software-search',
        'pais_settings_section'
    );
}

function pais_settings_section_callback() {
    echo '<p>Configure the settings for the Popular AI Software Search plugin.</p>';
}

function pais_compare_page_id_callback() {
    $options = get_option('pais_options');
    $selected_page_id = isset($options['compare_page_id']) ? $options['compare_page_id'] : '';

    $pages = get_pages();

    if ($pages) {
        echo "<select name='pais_options[compare_page_id]'>";
        echo "<option value=''>— Select a Page —</option>";
        foreach ($pages as $page) {
            $selected = selected($selected_page_id, $page->ID, false);
            echo "<option value='" . esc_attr($page->ID) . "' $selected>" . esc_html($page->post_title) . "</option>";
        }
        echo "</select>";
        echo "<p class='description'>Select the page where you have placed the <code>[pais_compare_page]</code> shortcode.</p>";
    } else {
        echo "<p>No pages found. Please create a page for the compare feature first.</p>";
    }
}

function pais_admin_page() {
    global $wpdb;

    // --- PROCESS ACTIONS ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
        if (isset($_POST['pais_reset_ratings'])) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}pais_ratings");
            echo '<div class="notice notice-success"><p>All star ratings have been reset.</p></div>';
        }
        if (isset($_POST['pais_delete_comments'])) {
            $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post'");
            if ($post_ids) {
                $ids = implode(',', array_map('intval', $post_ids));
                $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ($ids)");
                $wpdb->query("UPDATE {$wpdb->posts} SET comment_count = 0 WHERE ID IN ($ids)");
                echo '<div class="notice notice-success"><p>All comments on plugin posts have been deleted.</p></div>';
            }
        }
    }

    // --- ADMIN PAGE CONTENT ---
    echo '<div class="wrap"><h1>AI Software Search Admin Tools</h1>';
    // Show dashboard widget stats here too
    pais_render_dashboard_widget();

    echo '<hr style="margin:2em 0 1.2em 0;">';

    // Settings Form
    echo '<form action="options.php" method="post">';
    settings_fields('popular-ai-software-search');
    do_settings_sections('popular-ai-software-search');
    submit_button('Save Settings');
    echo '</form>';

    echo '<hr style="margin:2em 0 1.2em 0;">';
    echo '<h2 style="color:#a00;margin-bottom:0.5em;">Danger Zone</h2>';
    echo '<form method="post" style="margin-bottom:2em;">';
    echo '<button type="submit" name="pais_reset_ratings" class="button button-danger" style="margin-right:18px"
            onclick="return confirm(\'Are you sure? This will DELETE ALL star ratings!\')">Reset All Ratings</button>';
    echo '<button type="submit" name="pais_delete_comments" class="button button-danger"
            onclick="return confirm(\'Are you sure? This will DELETE ALL comments on plugin posts!\')">Delete All Comments</button>';
    echo '</form>';
    echo '</div>';
}


// 2. DASHBOARD SUMMARY WIDGET
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'pais_dashboard_widget',
        'AI Software Search — Overview',
        'pais_render_dashboard_widget'
    );
});

function pais_render_dashboard_widget() {
    global $wpdb;
    $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
    $ratings_row = $wpdb->get_row("SELECT AVG(rating) as avg_rating, COUNT(*) as num_ratings FROM {$wpdb->prefix}pais_ratings");
    $avg_rating = $ratings_row && $ratings_row->num_ratings > 0 ? round($ratings_row->avg_rating, 2) : 0;
    $num_ratings = $ratings_row ? intval($ratings_row->num_ratings) : 0;
    $comment_count = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->comments} 
        WHERE comment_post_ID IN (
            SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post'
        ) AND comment_approved = '1'
    ");

    echo '<ul style="line-height:1.6em;font-size:1.11em;margin:0;padding:0 0 0 1.4em;">';
    echo '<li><strong>Published Posts:</strong> ' . number_format($post_count) . '</li>';
    echo '<li><strong>Total Star Ratings:</strong> ' . number_format($num_ratings) . '</li>';
    echo '<li><strong>Average Star Rating:</strong> ' . ($num_ratings > 0 ? '<span style="color:#ffc107;font-weight:bold">' . $avg_rating . ' / 5</span>' : '—') . '</li>';
    echo '<li><strong>Approved Comments:</strong> ' . number_format($comment_count) . '</li>';
    echo '</ul>';
    echo '<hr style="margin:1.2em 0 0.7em 0;">';
    echo '<div style="font-size:1em"><strong>How to display the search UI:</strong><br>
        Use the shortcode <code>[popular_ai_software_search]</code> in any page or post, or add it to a template with <code>echo do_shortcode(\'[popular_ai_software_search]\');</code>
        </div>';
    echo '<div style="font-size:1em; margin-top: 1em;"><strong>How to display the compare page:</strong><br>
        Use the shortcode <code>[pais_compare_page]</code> in a new page (e.g., a page with the slug "/compare/").
        </div>';
}


function pais_admin_ratings_page() {
    ?>
    <div class="wrap">
        <h1>Ratings Management</h1>
        <div id="pais-admin-table"></div>
        <script>
        // Simple client-side UI for table, search, pagination
        let paisCurrentPage = 1, paisSearch = '', paisLoading = false;
        let paisCurrentSort = 'date', paisCurrentOrder = 'desc';

        function paisSort(column) {
            if (paisCurrentSort === column) {
                paisCurrentOrder = paisCurrentOrder === 'asc' ? 'desc' : 'asc';
            } else {
                paisCurrentSort = column;
                paisCurrentOrder = 'desc';
            }
            paisLoadRatings(1, paisSearch, paisCurrentSort, paisCurrentOrder);
        }


        function paisLoadRatings(page = 1, search = '', sort = paisCurrentSort, order = paisCurrentOrder) {
            paisLoading = true;
            document.getElementById('pais-admin-table').innerHTML = 'Loading...';
            fetch(ajaxurl + '?action=pais_ratings_list&page=' + page +
                '&search=' + encodeURIComponent(search) +
                '&sort=' + encodeURIComponent(sort) +
                '&order=' + encodeURIComponent(order)
            )
            .then(r => r.json())
            .then(data => {
                paisLoading = false;
                    document.getElementById('pais-admin-table').innerHTML = `
                        <form onsubmit="event.preventDefault();paisSearch=this.search.value;paisLoadRatings(1,paisSearch);">
                            <input type="text" name="search" value="${search.replace(/"/g, '&quot;')}" placeholder="Search title..." />
                            <button type="submit">Search</button>
                        </form>
                        <table class="widefat fixed" style="margin-top:1em;">
                            <thead>
                            <tr>
                                <th class="pais-sort-th" onclick="paisSort('ID')">ID</th>
                                <th class="pais-sort-th" onclick="paisSort('title')">Title</th>
                                <th class="pais-sort-th" onclick="paisSort('date')">Date</th>
                                <th class="pais-sort-th" onclick="paisSort('rating')">Avg Rating</th>
                                <th class="pais-sort-th" onclick="paisSort('votes')">Votes</th>
                                <th class="pais-sort-th" onclick="paisSort('comments')">Comments</th>
                            </tr>
                            </thead>

                            <tbody>
                                ${data.posts.map(post => `
                                    <tr>
                                        <td>${post.ID}</td>
                                        <td><a href="${post.permalink}" target="_blank">${post.title}</a></td>
                                        <td>${post.date}</td>
                                        <td style="color:#ffc107;font-weight:bold;">${post.rating ? post.rating + ' / 5' : '—'}</td>
                                        <td>${post.votes}</td>
                                        <td>${post.comments}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        <div style="margin-top:1em;">
                            Page ${data.page} of ${data.max_page}
                            <button ${data.page==1?'disabled':''} onclick="paisLoadRatings(${data.page-1},'${search.replace(/'/g,"\\'")}')">Prev</button>
                            <button ${data.page==data.max_page?'disabled':''} onclick="paisLoadRatings(${data.page+1},'${search.replace(/'/g,"\\'")}')">Next</button>
                        </div>
                    `;
                });
        }
        document.addEventListener('DOMContentLoaded',function(){paisLoadRatings();});
        </script>
    </div>
    <?php
}



add_action('wp_ajax_pais_ratings_list', function() {
    global $wpdb;

    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page-1)*$per_page;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Get sort and order from request
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

    // Allowable SQL columns
    $sortable = [
        'ID' => 'ID',
        'title' => 'post_title',
        'date' => 'post_date'
    ];
    $sort_sql = $sortable[$sort] ?? 'post_date';

    // For rating, votes, comments -- sort in PHP after fetching
    $is_php_sort = in_array($sort, ['rating','votes','comments']);

    // Query post IDs with search
    $post_where = "WHERE post_type = 'post' AND post_status = 'publish'";
    if ($search) {
        $post_where .= $wpdb->prepare(" AND post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} $post_where");

    // Use SQL ORDER BY for DB columns; otherwise default by date
    $order_by = $is_php_sort ? 'post_date' : $sort_sql;

    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title, post_date FROM {$wpdb->posts} $post_where ORDER BY $order_by $order LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $result = [];
    foreach ($posts as $p) {
        // Ratings
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as num_votes FROM {$wpdb->prefix}pais_ratings WHERE post_id=%d", $p->ID
        ));
        $avg_rating = $row && $row->num_votes > 0 ? round($row->avg_rating,2) : '';
        $num_votes = $row ? intval($row->num_votes) : 0;

        // Comments
        $comments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1'", $p->ID
        ));

        $result[] = [
            'ID' => $p->ID,
            'title' => esc_html($p->post_title),
            'permalink' => get_permalink($p->ID),
            'date' => date('Y-m-d', strtotime($p->post_date)),
            'rating' => $avg_rating === '' ? 0 : $avg_rating,
            'votes' => $num_votes,
            'comments' => $comments
        ];
    }

    // For non-SQL columns, sort in PHP
    if ($is_php_sort && count($result) > 1) {
        usort($result, function($a, $b) use ($sort, $order) {
            $valA = $a[$sort]; $valB = $b[$sort];
            if ($valA == $valB) return 0;
            if ($order === 'ASC') return ($valA < $valB) ? -1 : 1;
            return ($valA > $valB) ? -1 : 1;
        });
    }

    wp_send_json([
        'posts' => $result,
        'page' => $page,
        'max_page' => max(1, ceil($total/$per_page)),
        'total' => $total
    ]);
});
