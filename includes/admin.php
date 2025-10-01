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

/**
 * Sanitize plugin options before saving to database.
 *
 * @param array $input The input array of options to be sanitized.
 * @return array Sanitized options array.
 */
function ucs_sanitize_options($input) {
    $sanitized = array();
    
    // Sanitize compare page ID
    if (isset($input['compare_page_id'])) {
        $sanitized['compare_page_id'] = absint($input['compare_page_id']);
    }
    
    // Sanitize boolean values
    $boolean_fields = array();
    foreach ($boolean_fields as $field) {
        if (isset($input[$field])) {
            $sanitized[$field] = (bool) $input[$field];
        }
    }
    
    // Car Details meta box visibility (checkbox not present means false)
    $sanitized['enable_car_details'] = isset($input['enable_car_details']) && (string)$input['enable_car_details'] === '1';
    
    // Sanitize column management settings
    if (isset($input['enabled_columns']) && is_array($input['enabled_columns'])) {
        $sanitized['enabled_columns'] = array();
        $valid_columns = array('title', 'price', 'mileage', 'engine', 'transmission', 'categories', 'date', 'rating', 'comments', 'actions');
        foreach ($valid_columns as $column) {
            $sanitized['enabled_columns'][$column] = isset($input['enabled_columns'][$column]) && $input['enabled_columns'][$column] == '1';
        }
    }
    
    if (isset($input['enabled_grid_fields']) && is_array($input['enabled_grid_fields'])) {
        $sanitized['enabled_grid_fields'] = array();
        $valid_grid_fields = array('year', 'make', 'model', 'trim', 'price', 'mileage', 'engine', 'transmission', 'rating', 'comments');
        foreach ($valid_grid_fields as $field) {
            $sanitized['enabled_grid_fields'][$field] = isset($input['enabled_grid_fields'][$field]) && $input['enabled_grid_fields'][$field] == '1';
        }
    }
    
    // Sanitize text fields
    $text_fields = array();
    foreach ($text_fields as $field) {
        if (isset($input[$field])) {
            $sanitized[$field] = sanitize_text_field($input[$field]);
        }
    }
    
    return $sanitized;
}

function ucs_settings_init() {
    register_setting(
        'used-cars-search', 
        'ucs_options',
        array(
            'sanitize_callback' => 'ucs_sanitize_options',
            'default' => array()
        )
    );

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

    // Enable/Disable Car Details Meta Box
    add_settings_field(
        'ucs_enable_car_details',
        'Car Details Meta Box',
        'ucs_enable_car_details_callback',
        'used-cars-search',
        'ucs_settings_section'
    );

    // Column Management Settings
    add_settings_field(
        'ucs_column_management',
        'Column Management',
        'ucs_column_management_callback',
        'used-cars-search',
        'ucs_settings_section'
    );
}

/**
 * Renders the settings section description.
 *
 * @since 1.0.0
 */
function ucs_settings_section_callback() {
    // translators: Description for the plugin settings section
    echo '<p>' . esc_html__('Configure the settings for the Used Cars Search plugin.', 'used-cars-search') . '</p>';
}

/**
 * Renders the compare page dropdown in the settings.
 *
 * @since 1.0.0
 */
function ucs_compare_page_id_callback() {
    $options = get_option('ucs_options');
    $selected_page_id = isset($options['compare_page_id']) ? $options['compare_page_id'] : '';

    $pages = get_pages();

    if ($pages) {
        echo '<select name="ucs_options[compare_page_id]">';
        echo '<option value="">' . esc_html__('— Select a Page —', 'used-cars-search') . '</option>';
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr( $page->ID ) . '" ' . selected( (int) $selected_page_id, (int) $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
        }
        echo '</select>';
    }
}

/**
 * Renders the enable/disable checkbox for the Car Details meta box.
 *
 * @since 1.6.12
 */
function ucs_enable_car_details_callback() {
    $options = get_option('ucs_options');
    $enabled = isset($options['enable_car_details']) ? (bool)$options['enable_car_details'] : true; // default: enabled
    echo '<label>';
    echo '<input type="checkbox" name="ucs_options[enable_car_details]" value="1" ' . checked($enabled, true, false) . ' /> ';
    echo esc_html__('Show Car Details meta box on Add/Edit Post screens', 'used-cars-search');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Uncheck to hide the Car Details meta box on the post editor.', 'used-cars-search') . '</p>';
}

/**
 * Renders the column management settings.
 *
 * @since 1.6.11
 */
function ucs_column_management_callback() {
    $options = get_option('ucs_options');
    $default_columns = array(
        'title' => true,
        'price' => true,
        'mileage' => true,
        'engine' => true,
        'transmission' => true,
        'categories' => true,
        'date' => true,
        'rating' => true,
        'comments' => true,
        'actions' => true
    );
    $enabled_columns = isset($options['enabled_columns']) ? $options['enabled_columns'] : $default_columns;
    
    echo '<div class="ucs-column-management">';
    echo '<h4>' . esc_html__('List View Columns', 'used-cars-search') . '</h4>';
    echo '<p class="description">' . esc_html__('Select which columns to display in the list view table.', 'used-cars-search') . '</p>';
    echo '<div class="ucs-columns-grid">';
    
    $list_columns = array(
        'title' => __('Title', 'used-cars-search'),
        'price' => __('Price', 'used-cars-search'),
        'mileage' => __('Mileage', 'used-cars-search'),
        'engine' => __('Engine', 'used-cars-search'),
        'transmission' => __('Transmission', 'used-cars-search'),
        'categories' => __('Categories', 'used-cars-search'),
        'date' => __('Date', 'used-cars-search'),
        'rating' => __('Rating', 'used-cars-search'),
        'comments' => __('Comments', 'used-cars-search'),
        'actions' => __('Actions', 'used-cars-search')
    );
    
    foreach ($list_columns as $key => $label) {
        $checked = isset($enabled_columns[$key]) && $enabled_columns[$key] ? 'checked="checked"' : '';
        echo '<div class="ucs-column-item">';
        echo '<label>';
        echo '<input type="checkbox" name="ucs_options[enabled_columns][' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ';
        echo esc_html($label);
        echo '</label>';
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<h4 style="margin-top: 20px;">' . esc_html__('Grid View Fields', 'used-cars-search') . '</h4>';
    echo '<p class="description">' . esc_html__('Select which fields to display in the grid view cards.', 'used-cars-search') . '</p>';
    echo '<div class="ucs-columns-grid">';
    
    $grid_fields = array(
        'year' => __('Year', 'used-cars-search'),
        'make' => __('Make', 'used-cars-search'),
        'model' => __('Model', 'used-cars-search'),
        'trim' => __('Trim', 'used-cars-search'),
        'price' => __('Price', 'used-cars-search'),
        'mileage' => __('Mileage', 'used-cars-search'),
        'engine' => __('Engine', 'used-cars-search'),
        'transmission' => __('Transmission', 'used-cars-search'),
        'rating' => __('Rating', 'used-cars-search'),
        'comments' => __('Comments', 'used-cars-search')
    );
    
    $enabled_grid_fields = isset($options['enabled_grid_fields']) ? $options['enabled_grid_fields'] : array(
        'year' => true,
        'make' => true,
        'model' => true,
        'trim' => true,
        'price' => true,
        'mileage' => true,
        'engine' => true,
        'transmission' => true,
        'rating' => true,
        'comments' => true
    );
    
    foreach ($grid_fields as $key => $label) {
        $checked = isset($enabled_grid_fields[$key]) && $enabled_grid_fields[$key] ? 'checked="checked"' : '';
        echo '<div class="ucs-column-item">';
        echo '<label>';
        echo '<input type="checkbox" name="ucs_options[enabled_grid_fields][' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ';
        echo esc_html($label);
        echo '</label>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // Add some CSS for better styling
    ?>
    <style>
    .ucs-column-management {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 10px;
    }
    .ucs-columns-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    .ucs-column-item {
        padding: 5px;
        background: white;
        border: 1px solid #e5e5e5;
        border-radius: 3px;
    }
    .ucs-column-item label {
        display: block;
        cursor: pointer;
        font-weight: normal;
    }
    .ucs-column-item input[type="checkbox"] {
        margin-right: 8px;
    }
    </style>
    <?php
}

/**
 * Renders the main admin page.
 *
 * @since 1.0.0
 */
function ucs_admin_page() {
    $options = get_option('ucs_options');
    // translators: Main admin page title
    echo '<div class="wrap"><h1>' . esc_html__('Used Cars Search', 'used-cars-search') . '</h1>';
    ucs_render_dashboard_widget();
    // translators: Instructions for displaying the search UI
    echo '<p>' . esc_html__('How to display the search UI:', 'used-cars-search') . '<br><code>[used_cars_search]</code> ' . esc_html__('in any page or post, or add it to a template with', 'used-cars-search') . ' <code>' . esc_html('echo do_shortcode(\'[used_cars_search]\');') . '</code></p>';
    // translators: Instructions for displaying the compare page
    echo '<p>' . esc_html__('How to display the compare page:', 'used-cars-search') . '<br><code>[ucs_compare_page]</code> ' . esc_html__('in a new page (e.g., a page with the slug "/compare").', 'used-cars-search') . '</p>';
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
    echo '<h2>' . esc_html__('REST API: Car Details & SEO', 'used-cars-search') . '</h2>';
    echo '<p>' . esc_html__('This plugin registers all car details and SEO fields as post meta with REST support. You can create or update them via the core Posts endpoint.', 'used-cars-search') . '</p>';
    echo '<ul style="line-height:1.6em;">';
    // translators: %s is the REST API endpoint URL for creating posts
    echo '<li><strong>' . esc_html__('Create', 'used-cars-search') . ':</strong> <code>POST ' . esc_html( $posts_endpoint ) . '</code></li>';
    // translators: %s is the REST API endpoint URL for updating posts
    echo '<li><strong>' . esc_html__('Update', 'used-cars-search') . ':</strong> <code>POST ' . esc_html( $posts_endpoint . '/{id}' ) . '</code></li>';
    // translators: %s is the REST API endpoint URL for reading posts
    echo '<li><strong>' . esc_html__('Read', 'used-cars-search') . ':</strong> <code>GET ' . esc_html( $read_example ) . '</code></li>';
    echo '</ul>';

    // translators: Authentication instructions for the REST API
    echo '<p><strong>' . esc_html__('Authentication', 'used-cars-search') . '</strong>: ' . 
         esc_html__('Use', 'used-cars-search') . ' <em>' . 
         esc_html__('Application Passwords', 'used-cars-search') . '</em> (' . 
         esc_html__('Users → Profile', 'used-cars-search') . '). ' .
         esc_html__('In Postman/Make.com use Basic Auth with your WordPress username and the generated password.', 'used-cars-search') . 
         '</p>';

    // translators: Section header for supported meta keys
    echo '<p><strong>' . esc_html__('Supported meta keys', 'used-cars-search') . ':</strong></p>';
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

    echo '<p><strong>' . esc_html__('Postman quick steps', 'used-cars-search') . ':</strong></p>';
    echo '<ol style="line-height:1.6em;">';
    echo '<li>' . esc_html__('Create an Application Password (Users → Your Profile).', 'used-cars-search') . '</li>';
    echo '<li>' . sprintf(
        /* translators: %1$s: The authentication method (Basic Auth) */
        esc_html__('Set Authorization: %1$s (Username = your WP user, Password = Application Password).', 'used-cars-search'),
        '<em>' . esc_html__('Basic Auth', 'used-cars-search') . '</em>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %s: The HTTP header to be set (e.g., Content-Type: application/json) */
        esc_html__('Set Header: %s', 'used-cars-search'),
        '<code>Content-Type: application/json</code>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %1$s: The REST API endpoint URL for creating posts */
        esc_html__('POST to %1$s with the Create JSON below.', 'used-cars-search'),
        '<code>' . esc_html($posts_endpoint) . '</code>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %1$s: The REST API endpoint URL for updating a post (includes {id} placeholder) */
        esc_html__('Then POST to %1$s with the Update JSON to modify fields.', 'used-cars-search'),
        '<code>' . esc_html($posts_endpoint . '/{id}') . '</code>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %s: The REST API endpoint URL for getting post details (includes {id} placeholder and _fields parameter) */
        esc_html__('Verify with GET: %s', 'used-cars-search'),
        '<code>' . esc_html($posts_endpoint . '/{id}?_fields=id,title.rendered,meta') . '</code>'
    ) . '.</li>';
    echo '</ol>';

    echo '<p><strong>' . esc_html__('Create JSON', 'used-cars-search') . ':</strong></p>';
    echo '<pre style="white-space:pre-wrap;max-width:900px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;"><code>' . esc_html( $create_json ) . '</code></pre>';

    echo '<p><strong>' . esc_html__('Update JSON', 'used-cars-search') . ':</strong></p>';
    echo '<pre style="white-space:pre-wrap;max-width:900px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;"><code>' . esc_html( $update_json ) . '</code></pre>';

    echo '<p><strong>Make.com</strong>: ' . sprintf(
        /* translators: %1$s: The REST API endpoint for creating posts, %2$s: The endpoint for updating posts, %3$s: The required Content-Type header, %4$s: The meta object name */
        esc_html__('Use HTTP → Make a request, Method: POST, URL: %1$s (or %2$s), Auth: Basic, Headers: %3$s. Map your variables into the %4$s object as shown above.', 'used-cars-search'),
        '<code>' . esc_html($posts_endpoint) . '</code>',
        '<code>' . esc_html($posts_endpoint . '/{id}') . '</code>',
        '<code>Content-Type: application/json</code>',
        '<code>meta</code>'
    ) . '</p>';

    // translators: Warning section for dangerous operations
    echo '<div style="margin-top:2em;padding:1em;border:1px solid #c00;background:#fee;max-width:500px;">';
    echo '<b>' . esc_html__('Danger Zone', 'used-cars-search') . '</b><br>';
    echo '<a href="#" class="button">' . esc_html__('Reset All Ratings', 'used-cars-search') . '</a> ';
    echo '<a href="#" class="button">' . esc_html__('Delete All Comments', 'used-cars-search') . '</a>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renders the dashboard widget with plugin statistics.
 *
 * @since 1.0.0
 */
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
    echo '<li><strong>' . esc_html__('Published Posts:', 'used-cars-search') . '</strong> ' . esc_html(number_format($post_count)) . '</li>';
    // translators: %s is the number of star ratings
    echo '<li><strong>' . esc_html__('Total Star Ratings:', 'used-cars-search') . '</strong> ' . esc_html( number_format( $num_ratings ) ) . '</li>';
    // translators: %s is the average star rating out of 5
    echo '<li><strong>' . esc_html__('Average Star Rating:', 'used-cars-search') . '</strong> ' . ( $num_ratings > 0 ? '<span style="color:#ffc107;font-weight:bold">' . esc_html( $avg_rating ) . ' / 5</span>' : '—' ) . '</li>';
    echo '<li><strong>' . esc_html__('Approved Comments:', 'used-cars-search') . '</strong> ' . esc_html(number_format($comment_count)) . '</li>';
    echo '</ul>';
    echo '<hr style="margin:1.2em 0 0.7em 0;">';
}


/**
 * Renders the ratings management page.
 *
 * @since 1.0.0
 */
function ucs_admin_ratings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Ratings Management', 'used-cars-search'); ?></h1>
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
            
            // Create form data to send as POST
            const formData = new FormData();
            formData.append('action', 'ucs_ratings_list');
            formData.append('nonce', ucsVars.nonce);
            formData.append('page', page);
            formData.append('search', search);
            formData.append('sort', sort);
            formData.append('order', order);
            
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
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
        // Localize script with nonce
        const ucsVars = {
            'nonce': '<?php echo esc_js(wp_create_nonce("ucs_ratings_nonce")); ?>'
        };
        
        document.addEventListener('DOMContentLoaded',function(){
            ucsLoadRatings();
        });
        </script>
    </div>
    <?php
}

add_action('wp_ajax_ucs_ratings_list', function() {
    // Verify nonce for AJAX request
    check_ajax_referer('ucs_ratings_nonce', 'nonce');
    
    global $wpdb;

    // Sanitize and validate input from POST data
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 20;
    $offset = ($page-1)*$per_page;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    // Normalize sort key to lowercase and whitelist allowed values
    $sort_raw = isset($_POST['sort']) ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'date';
    $sort = strtolower($sort_raw);
    $allowed_sorts = ['id','title','date','rating','votes','comments'];
    if (!in_array($sort, $allowed_sorts, true)) {
        $sort = 'date';
    }
    $order = (isset($_POST['order']) && in_array(strtoupper($_POST['order']), ['ASC', 'DESC'], true)) 
        ? strtoupper($_POST['order']) 
        : 'DESC';
        
    // Initialize response array
    $response = [
        'posts' => [],
        'page' => $page,
        'max_page' => 1,
        'total' => 0
    ];

    // For rating, votes, comments -- sort in PHP after fetching
    $is_php_sort = in_array($sort, ['rating','votes','comments'], true);
    
    // Allowable SQL columns for direct DB sorting (keys normalized to lowercase)
    $sortable = [
        'id' => 'ID',
        'title' => 'title',
        'date' => 'date'
    ];
    $sort_sql = isset($sortable[$sort]) ? $sortable[$sort] : 'date';

    // Build base query
    $query_args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'no_found_rows' => false
    ];
    
    // Add search if provided
    if ($search) {
        $query_args['s'] = $search;
    }
    
    // Handle sorting
    if ($sort === 'id') {
        $query_args['orderby'] = 'ID';
        $query_args['order'] = $order;
    } else if ($sort === 'date') {
        $query_args['orderby'] = 'date';
        $query_args['order'] = $order;
    } else if ($sort === 'title') {
        $query_args['orderby'] = 'title';
        $query_args['order'] = $order;
    }
    
    // Get posts with WP_Query for better security and compatibility
    $posts_query = new WP_Query($query_args);
    $total = $posts_query->found_posts;
    $response['max_page'] = max(1, ceil($total / $per_page));
    $response['total'] = $total;

    // Process the posts
    $result = [];
    
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();
            global $post;
            
            // Get ratings
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as num_votes 
                FROM {$wpdb->prefix}ucs_ratings 
                WHERE post_id = %d", 
                $post->ID
            ));
            
            $avg_rating = $row && $row->num_votes > 0 ? round($row->avg_rating, 2) : 0;
            $num_votes = $row ? intval($row->num_votes) : 0;
            
            // Get comment count
            $comments = get_comments([
                'post_id' => $post->ID,
                'count' => true,
                'status' => 'approve'
            ]);
            
            $result[] = [
                'ID' => $post->ID,
                'title' => get_the_title($post->ID),
                'permalink' => get_permalink($post->ID),
                'date' => gmdate('Y-m-d', strtotime($post->post_date . ' UTC')),
                'rating' => (float)$avg_rating,
                'votes' => $num_votes,
                'comments' => (int)$comments
            ];
        }
        wp_reset_postdata();
    }
    
    // If we're not already sorting in the query, sort in PHP
    if ($is_php_sort && count($result) > 1) {
        usort($result, function($a, $b) use ($sort, $order) {
            $valA = $a[$sort];
            $valB = $b[$sort];
            if ($valA == $valB) return 0;
            
            // Handle different data types properly
            if (is_numeric($valA) && is_numeric($valB)) {
                return ($order === 'ASC') ? ($valA - $valB) : ($valB - $valA);
            }
            
            // String comparison
            $cmp = strcmp((string)$valA, (string)$valB);
            return ($order === 'ASC') ? $cmp : -$cmp;
        });
    }

    // Fallback: if sorting by ID, enforce numeric sort in PHP to ensure correct order regardless of DB ordering
    if ($sort === 'id' && count($result) > 1) {
        usort($result, function($a, $b) use ($order) {
            if ($a['ID'] == $b['ID']) return 0;
            return ($order === 'ASC') ? ($a['ID'] <=> $b['ID']) : ($b['ID'] <=> $a['ID']);
        });
    }
    
    $response['posts'] = $result;
    wp_send_json($response);
});



    // Register Meta Box
    /**
 * Adds the car details meta box to the post editor.
 *
 * @since 1.0.0
 */
function ucs_add_car_details_meta_box() {
        // Check plugin setting to determine whether to show the meta box
        $options = get_option('ucs_options');
        $enabled = isset($options['enable_car_details']) ? (bool)$options['enable_car_details'] : true; // default enabled
        if (!$enabled) {
            return; // Do not register the meta box when disabled
        }

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
    /**
 * Renders the car details meta box content.
 *
 * @since 1.0.0
 * @param WP_Post $post The post object.
 */
function ucs_render_car_details_meta_box($post) {
        // Security nonce
        wp_nonce_field('ucs_save_meta_box_data', 'ucs_meta_box_nonce');

        // AI Assist behavior note
        $settings_url = esc_url( admin_url('admin.php?page=ucs_admin') );
        echo '<div class="notice notice-info" style="margin:8px 0 12px;padding:8px 12px;">';
        echo '<p style="margin:0;"><strong>' . esc_html__('AI Assist', 'used-cars-search') . ':</strong> ' . esc_html__('When Car Details are enabled, AI may use Year, Make, Model, Trim, Price, Mileage, Engine, and Transmission to improve generated content and SEO keywords. Leaving fields empty will simply omit them.', 'used-cars-search') . '</p>';
        echo '<p style="margin:6px 0 0;">' . sprintf( esc_html__('To disable Car Details for all posts, go to %s. When disabled, AI will rely only on the Post Title and Content and will not infer or fabricate vehicle specifications.', 'used-cars-search'), '<a href="' . $settings_url . '">' . esc_html__('Used Cars Search → Settings', 'used-cars-search') . '</a>' ) . '</p>';
        echo '</div>';

        // Field data
        $fields = ['year', 'make', 'model', 'trim', 'price', 'mileage', 'engine', 'transmission'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = get_post_meta($post->ID, 'ucs_' . $field, true);
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="ucs_year"><?php esc_html_e('Year', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_year" name="ucs_year">
                        <option value=""><?php esc_html_e('Select Year', 'used-cars-search'); ?></option>
                        <?php for ($y = 2025; $y >= 1980; $y--): ?>
                            <option value="<?php echo esc_attr( $y ); ?>" <?php selected( (string)$values['year'], (string)$y ); ?>><?php echo esc_html( $y ); ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ucs_make"><?php esc_html_e('Make', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_make" name="ucs_make">
                        <option value=""><?php esc_html_e('Select Make', 'used-cars-search'); ?></option>
                        <?php
                        // Standard list
                        $standard_makes = [
                            'Acura','Alfa Romeo','Audi','BMW','Buick','Cadillac','Chevrolet','Chrysler','Dodge','Fiat','Ford','Genesis','GMC','Honda','Hyundai','Infiniti','Jaguar','Jeep','Kia','Land Rover','Lexus','Lincoln','Maserati','Mazda','Mercedes-Benz','MINI','Mitsubishi','Nissan','Porsche','RAM','Subaru','Tesla','Toyota','Volkswagen','Volvo'
                        ];
                        // Discover distinct makes from existing posts
                        global $wpdb;
                        $db_makes = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='ucs_make' AND meta_value<>''");
                        $all_makes = array_merge($standard_makes, is_array($db_makes) ? $db_makes : []);
                        // Ensure current value is included
                        if (!empty($values['make'])) { $all_makes[] = (string)$values['make']; }
                        // Unique by lowercase, then restore original cases by picking first occurrence
                        $seen = [];
                        $unique_makes = [];
                        foreach ($all_makes as $m) {
                            $key = strtolower(trim((string)$m));
                            if ($key === '') continue;
                            if (!isset($seen[$key])) { $seen[$key] = true; $unique_makes[] = trim((string)$m); }
                        }
                        natcasesort($unique_makes);
                        foreach ($unique_makes as $make): ?>
                            <option value="<?php echo esc_attr($make); ?>" <?php selected( (string)$values['make'], (string)$make ); ?>><?php echo esc_html($make); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ucs_model"><?php esc_html_e('Model', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_model" name="ucs_model" value="<?php echo esc_attr($values['model']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_trim"><?php esc_html_e('Trim', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_trim" name="ucs_trim" value="<?php echo esc_attr($values['trim']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_price"><?php esc_html_e('Price', 'used-cars-search'); ?></label></th>
                <td><input type="number" id="ucs_price" name="ucs_price" value="<?php echo esc_attr($values['price']); ?>" class="regular-text" step="0.01"/></td>
            </tr>
            <tr>
                <th><label for="ucs_mileage"><?php esc_html_e('Mileage', 'used-cars-search'); ?></label></th>
                <td><input type="number" id="ucs_mileage" name="ucs_mileage" value="<?php echo esc_attr($values['mileage']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_engine"><?php esc_html_e('Engine', 'used-cars-search'); ?></label></th>
                <td><input type="text" id="ucs_engine" name="ucs_engine" value="<?php echo esc_attr($values['engine']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ucs_transmission"><?php esc_html_e('Transmission', 'used-cars-search'); ?></label></th>
                <td>
                    <select id="ucs_transmission" name="ucs_transmission">
                        <option value=""><?php esc_html_e('Select Transmission', 'used-cars-search'); ?></option>
                        <?php
                        $transmissions = [
                            'Automatic','Manual','CVT','Dual-Clutch','Tiptronic','Semi-Automatic','Automated Manual'
                        ];
                        foreach ($transmissions as $trans): ?>
                            <option value="<?php echo esc_attr( $trans ); ?>" <?php selected( (string)$values['transmission'], (string)$trans ); ?>><?php echo esc_html( $trans ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // Save Meta Box Data
    /**
 * Saves the car details meta box data.
 *
 * @since 1.0.0
 * @param int $post_id The post ID.
 */
function ucs_save_meta_box_data($post_id) {
        // Verify nonce
        $nonce = isset($_POST['ucs_meta_box_nonce']) ? sanitize_text_field(wp_unslash($_POST['ucs_meta_box_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'ucs_save_meta_box_data')) {
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
            $meta_key = 'ucs_' . $field;
            if (isset($_POST[$meta_key])) {
                $value = sanitize_text_field(wp_unslash($_POST[$meta_key]));
                update_post_meta($post_id, $meta_key, $value);
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }
    }
    add_action('save_post', 'ucs_save_meta_box_data');

} // end is_admin() check
