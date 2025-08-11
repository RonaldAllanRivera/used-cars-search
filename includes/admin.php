<?php

if (!defined('ABSPATH')) exit; // Prevent direct access

if (is_admin()) {

// 1. ADMIN SIDEBAR PAGE (rebranded)
add_action('admin_menu', function() {
    add_menu_page(
        'Used Cars Search Admin',
        'Used Cars Search',
        'manage_options',
        'ucs_admin',
        'ucs_admin_page',
        'dashicons-car',
        25
    );
    add_submenu_page(
        'ucs_admin',
        'Ratings Management',
        'Ratings Management',
        'manage_options',
        'ucs_admin_ratings',
        'ucs_admin_ratings_page'
    );
});

add_action('admin_init', 'ucs_settings_init');

function ucs_settings_init() {
    register_setting('used-cars-search', 'ucs_options');

    add_settings_section(
        'ucs_settings_section',
        'Plugin Settings',
        'ucs_settings_section_callback',
        'used-cars-search'
    );

    add_settings_field(
        'ucs_compare_page_id',
        'Compare Page',
        'ucs_compare_page_id_callback',
        'used-cars-search',
        'ucs_settings_section'
    );
}

function ucs_settings_section_callback() {
    echo '<p>Configure the settings for the Used Cars Search plugin.</p>';
}

function ucs_compare_page_id_callback() {
    $options = get_option('ucs_options');
    $selected_page_id = isset($options['compare_page_id']) ? $options['compare_page_id'] : '';

    $pages = get_pages();

    if ($pages) {
        echo "<select name='ucs_options[compare_page_id]'>";
        echo "<option value=''>— Select a Page —</option>";
        foreach ($pages as $page) {
            $selected = $selected_page_id == $page->ID ? 'selected' : '';
            echo "<option value='{$page->ID}' $selected>" . esc_html($page->post_title) . "</option>";
        }
        echo "</select>";
    }
}

function ucs_admin_page() {
    $options = get_option('ucs_options');
    echo '<div class="wrap"><h1>Used Cars Search</h1>';
    ucs_render_dashboard_widget();
    echo "<p>How to display the search UI:<br><code>[used_cars_search]</code> in any page or post, or add it to a template with <code>echo do_shortcode('[used_cars_search]');</code></p>";
    echo '<p>How to display the compare page:<br><code>[ucs_compare_page]</code> in a new page (e.g., a page with the slug "/compare").</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('used-cars-search');
    do_settings_sections('used-cars-search');
    submit_button('Save Settings');
    echo '</form>';

    // REST API documentation section
    $posts_endpoint = esc_url( rest_url('wp/v2/posts') );
    $read_example   = esc_url( rest_url('wp/v2/posts/123?_fields=id,title.rendered,meta') );

    $create_payload = [
        'title' => '2018 Honda Civic LX',
        'status' => 'publish',
        'content' => 'Description or details here.',
        'meta' => [
            'ucs_year' => 2018,
            'ucs_make' => 'Honda',
            'ucs_model' => 'Civic',
            'ucs_trim' => 'LX',
            'ucs_price' => 12995.0,
            'ucs_mileage' => 58432,
            'ucs_engine' => '2.0L I4',
            'ucs_transmission' => 'Automatic',
            '_ucs_seo_title' => '2018 Honda Civic LX for sale | DealerName',
            '_ucs_seo_description' => 'Clean title Civic LX, low miles, great condition.',
            '_ucs_seo_keywords' => 'Honda Civic 2018',
            '_ucs_seo_noindex' => false,
        ],
    ];
    $update_payload = [
        'meta' => [
            'ucs_price' => 12750.00,
            'ucs_mileage' => 58010,
            '_ucs_seo_noindex' => true,
        ],
    ];
    $create_json = esc_html( wp_json_encode($create_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) );
    $update_json = esc_html( wp_json_encode($update_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) );

    echo '<hr style="margin:2em 0 1em">';
    echo '<h2>REST API: Car Details & SEO</h2>';
    echo '<p>This plugin registers all car details and SEO fields as post meta with REST support. You can create or update them via the core Posts endpoint.</p>';
    echo '<ul style="line-height:1.6em;">';
    echo '<li><strong>Create</strong>: <code>POST ' . $posts_endpoint . '</code></li>';
    echo '<li><strong>Update</strong>: <code>POST ' . $posts_endpoint . '/{id}</code></li>';
    echo '<li><strong>Read</strong>: <code>GET ' . $read_example . '</code></li>';
    echo '</ul>';

    echo '<p><strong>Authentication</strong>: Use <em>Application Passwords</em> (Users → Profile). In Postman/Make.com use Basic Auth with your WordPress username and the generated password.</p>';

    echo '<p><strong>Supported meta keys</strong>:</p>';
    echo '<ul style="columns:2;-webkit-columns:2;-moz-columns:2;line-height:1.6em;">';
    echo '<li><code>ucs_year</code> (integer)</li>';
    echo '<li><code>ucs_make</code> (string)</li>';
    echo '<li><code>ucs_model</code> (string)</li>';
    echo '<li><code>ucs_trim</code> (string)</li>';
    echo '<li><code>ucs_price</code> (number)</li>';
    echo '<li><code>ucs_mileage</code> (integer)</li>';
    echo '<li><code>ucs_engine</code> (string)</li>';
    echo '<li><code>ucs_transmission</code> (string)</li>';
    echo '<li><code>_ucs_seo_title</code> (string)</li>';
    echo '<li><code>_ucs_seo_description</code> (string)</li>';
    echo '<li><code>_ucs_seo_keywords</code> (string)</li>';
    echo '<li><code>_ucs_seo_noindex</code> (boolean)</li>';
    echo '</ul>';

    echo '<p><strong>Postman quick steps</strong>:</p>';
    echo '<ol style="line-height:1.6em;">';
    echo '<li>Create an Application Password (Users → Your Profile).</li>';
    echo '<li>Set Authorization: <em>Basic Auth</em> (Username = your WP user, Password = Application Password).</li>';
    echo '<li>Set Header: <code>Content-Type: application/json</code>.</li>';
    echo '<li>POST to <code>' . $posts_endpoint . '</code> with the Create JSON below.</li>';
    echo '<li>Then POST to <code>' . $posts_endpoint . '/{id}</code> with the Update JSON to modify fields.</li>';
    echo '<li>Verify with GET: <code>' . $posts_endpoint . '/{id}?_fields=id,title.rendered,meta</code>.</li>';
    echo '</ol>';

    echo '<p><strong>Create JSON</strong>:</p>';
    echo '<pre style="white-space:pre-wrap;max-width:900px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;"><code>' . $create_json . '</code></pre>';

    echo '<p><strong>Update JSON</strong>:</p>';
    echo '<pre style="white-space:pre-wrap;max-width:900px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;"><code>' . $update_json . '</code></pre>';

    echo '<p><strong>Make.com</strong>: Use HTTP → Make a request, Method: POST, URL: <code>' . $posts_endpoint . '</code> (or <code>' . $posts_endpoint . '/{id}</code>), Auth: Basic, Headers: <code>Content-Type: application/json</code>. Map your variables into the <code>meta</code> object as shown above.</p>';

    echo '<div style="margin-top:2em;padding:1em;border:1px solid #c00;background:#fee;max-width:500px;">';
    echo '<b>Danger Zone</b><br>';
    echo '<a href="#" class="button">Reset All Ratings</a> <a href="#" class="button">Delete All Comments</a>';
    echo '</div>';
    echo '</div>';
}

function ucs_render_dashboard_widget() {
    global $wpdb;
    $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
    $ratings_row = $wpdb->get_row("SELECT AVG(rating) as avg_rating, COUNT(*) as num_ratings FROM {$wpdb->prefix}ucs_ratings");
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
}


function ucs_admin_ratings_page() {
    ?>
    <div class="wrap">
        <h1>Ratings Management</h1>
        <div id="ucs-admin-table"></div>
        <script>
        let ucsCurrentPage = 1, ucsSearch = '', ucsLoading = false;
        let ucsCurrentSort = 'date', ucsCurrentOrder = 'desc';

        function ucsSort(column) {
            if (ucsCurrentSort === column) {
                ucsCurrentOrder = ucsCurrentOrder === 'asc' ? 'desc' : 'asc';
            } else {
                ucsCurrentSort = column;
                ucsCurrentOrder = 'desc';
            }
            ucsLoadRatings(1, ucsSearch, ucsCurrentSort, ucsCurrentOrder);
        }

        function ucsLoadRatings(page = 1, search = '', sort = ucsCurrentSort, order = ucsCurrentOrder) {
            ucsLoading = true;
            document.getElementById('ucs-admin-table').innerHTML = 'Loading...';
            fetch(ajaxurl + '?action=ucs_ratings_list&page=' + page +
                '&search=' + encodeURIComponent(search) +
                '&sort=' + encodeURIComponent(sort) +
                '&order=' + encodeURIComponent(order)
            )
            .then(r => r.json())
            .then(data => {
                ucsLoading = false;
                document.getElementById('ucs-admin-table').innerHTML = `
                    <form onsubmit="event.preventDefault();ucsSearch=this.search.value;ucsLoadRatings(1,ucsSearch);">
                        <input type="text" name="search" value="${search.replace(/"/g, '&quot;')}" placeholder="Search title..." />
                        <button type="submit">Search</button>
                    </form>
                    <table class="widefat fixed" style="margin-top:1em;">
                        <thead>
                        <tr>
                            <th class="ucs-sort-th" onclick="ucsSort('ID')">ID</th>
                            <th class="ucs-sort-th" onclick="ucsSort('title')">Title</th>
                            <th class="ucs-sort-th" onclick="ucsSort('date')">Date</th>
                            <th class="ucs-sort-th" onclick="ucsSort('rating')">Avg Rating</th>
                            <th class="ucs-sort-th" onclick="ucsSort('votes')">Votes</th>
                            <th class="ucs-sort-th" onclick="ucsSort('comments')">Comments</th>
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
                        <button ${data.page==1?'disabled':''} onclick="ucsLoadRatings(${data.page-1},'${search.replace(/'/g,"\\'")}')">Prev</button>
                        <button ${data.page==data.max_page?'disabled':''} onclick="ucsLoadRatings(${data.page+1},'${search.replace(/'/g,"\\'")}')">Next</button>
                    </div>
                `;
            });
        }
        document.addEventListener('DOMContentLoaded',function(){ucsLoadRatings();});
        </script>
    </div>
    <?php
}

add_action('wp_ajax_ucs_ratings_list', function() {
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
            "SELECT AVG(rating) as avg_rating, COUNT(*) as num_votes FROM {$wpdb->prefix}ucs_ratings WHERE post_id=%d", $p->ID
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




    // Register Meta Box
    function ucs_add_car_details_meta_box() {
        add_meta_box(
            'ucs_car_details_meta_box',
            __('Car Details', 'used-cars-search'),
            'ucs_render_car_details_meta_box',
            'post', // Target 'post' post type
            'normal',
            'high'
        );
    }
    add_action('add_meta_boxes', 'ucs_add_car_details_meta_box');

    // Render Meta Box Content
    function ucs_render_car_details_meta_box($post) {
        // Security nonce
        wp_nonce_field('ucs_save_meta_box_data', 'ucs_meta_box_nonce');

        // Field data
        $fields = ['year', 'make', 'model', 'trim', 'price', 'mileage', 'engine', 'transmission'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = get_post_meta($post->ID, 'ucs_' . $field, true);
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="ucs_year"><?php _e('Year', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_year" name="ucs_year">
                        <option value="">Select Year</option>
                        <?php for ($y = 2025; $y >= 1980; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php selected($values['year'], $y); ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ucs_make"><?php _e('Make', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_make" name="ucs_make">
                        <option value="">Select Make</option>
                        <?php
                        $makes = [
                            'Acura','Alfa Romeo','Audi','BMW','Buick','Cadillac','Chevrolet','Chrysler','Dodge','Fiat','Ford','Genesis','GMC','Honda','Hyundai','Infiniti','Jaguar','Jeep','Kia','Land Rover','Lexus','Lincoln','Maserati','Mazda','Mercedes-Benz','MINI','Mitsubishi','Nissan','Porsche','RAM','Subaru','Tesla','Toyota','Volkswagen','Volvo'
                        ];
                        foreach ($makes as $make): ?>
                            <option value="<?php echo $make; ?>" <?php selected($values['make'], $make); ?>><?php echo $make; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ucs_model"><?php _e('Model', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_model" name="ucs_model" value="<?php echo esc_attr($values['model']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_trim"><?php _e('Trim', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_trim" name="ucs_trim" value="<?php echo esc_attr($values['trim']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_price"><?php _e('Price', 'used-cars-search'); ?></label></th>
                <td><input type="number" id="ucs_price" name="ucs_price" value="<?php echo esc_attr($values['price']); ?>" class="regular-text" step="0.01"/></td>
            </tr>
            <tr>
                <th><label for="ucs_mileage"><?php _e('Mileage', 'used-cars-search'); ?></label></th>
                <td><input type="number" id="ucs_mileage" name="ucs_mileage" value="<?php echo esc_attr($values['mileage']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_engine"><?php _e('Engine', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_engine" name="ucs_engine" value="<?php echo esc_attr($values['engine']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_transmission"><?php _e('Transmission', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_transmission" name="ucs_transmission">
                        <option value="">Select Transmission</option>
                        <?php
                        $transmissions = [
                            'Automatic','Manual','CVT','Dual-Clutch','Tiptronic','Semi-Automatic','Automated Manual'
                        ];
                        foreach ($transmissions as $trans): ?>
                            <option value="<?php echo $trans; ?>" <?php selected($values['transmission'], $trans); ?>><?php echo $trans; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // Save Meta Box Data
    function ucs_save_meta_box_data($post_id) {
        // Verify nonce
        if (!isset($_POST['ucs_meta_box_nonce']) || !wp_verify_nonce($_POST['ucs_meta_box_nonce'], 'ucs_save_meta_box_data')) {
            return;
        }
        // Prevent autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = ['year', 'make', 'model', 'trim', 'price', 'mileage', 'engine', 'transmission'];

        foreach ($fields as $field) {
            if (isset($_POST['ucs_' . $field])) {
                update_post_meta($post_id, 'ucs_' . $field, sanitize_text_field($_POST['ucs_' . $field]));
            } else {
                delete_post_meta($post_id, 'ucs_' . $field);
            }
        }
    }
    add_action('save_post', 'ucs_save_meta_box_data');

} // end is_admin() check
