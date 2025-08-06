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
    echo '<div class="wrap"><h1>Ratings Management</h1>';
    echo '<p>Here you can manage ratings for the Used Cars Search plugin.</p>';
    // Add your ratings management UI here
    echo '</div>';
}




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
