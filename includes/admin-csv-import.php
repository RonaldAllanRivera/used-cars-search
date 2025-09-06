<?php
if (!defined('ABSPATH')) exit;

// CSV Import Admin Page
add_action('admin_menu', function(){
    add_submenu_page(
        'ucs_admin',
        __('CSV Import', 'used-cars-search'),
        __('CSV Import', 'used-cars-search'),
        'manage_options',
        'ucs_csv_import',
        'ucs_render_csv_import_page'
    );
});

function ucs_render_csv_import_page() {
    if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions', 'used-cars-search')); }

    $results = null; $errors = [];
    if (!empty($_POST['ucs_csv_import_nonce']) && wp_verify_nonce($_POST['ucs_csv_import_nonce'], 'ucs_csv_import')) {
        $use_sample = !empty($_POST['ucs_use_sample']);
        $file_path = '';
        if ($use_sample) {
            $sample = plugin_dir_path(__DIR__ . '/..') . 'cars.csv';
            // plugin_dir_path expects a file; resolve from this file
            $sample = dirname(__DIR__) . '/cars.csv';
            if (file_exists($sample)) { $file_path = $sample; } else { $errors[] = __('Sample cars.csv not found in plugin folder.', 'used-cars-search'); }
        } else {
            // Handle file upload
            if (!empty($_FILES['ucs_csv_file']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $overrides = [ 'test_form' => false, 'mimes' => ['csv' => 'text/csv','txt' => 'text/plain'] ];
                $uploaded = wp_handle_upload($_FILES['ucs_csv_file'], $overrides);
                if (isset($uploaded['error'])) { $errors[] = $uploaded['error']; }
                else { $file_path = $uploaded['file']; }
            } else {
                $errors[] = __('Please upload a CSV file or choose the sample file.', 'used-cars-search');
            }
        }
        if ($file_path && empty($errors)) {
            $results = ucs_process_cars_csv($file_path);
            if (!$results['ok']) { $errors[] = $results['message']; }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('CSV Import', 'used-cars-search') . '</h1>';
    echo '<p>' . esc_html__('Upload a CSV of cars to create draft posts. Title is Year Make Model. Car details are saved to custom fields. The Make is assigned as the post category (created if missing).', 'used-cars-search') . '</p>';

    if ($errors) {
        echo '<div class="notice notice-error"><p>' . implode('<br>', array_map('esc_html', $errors)) . '</p></div>';
    }
    if ($results && $results['ok']) {
        /* translators: 1: total rows processed, 2: posts created, 3: posts updated, 4: rows skipped */
        echo '<div class="notice notice-success"><p>' . sprintf(
            esc_html__('Imported %d rows. Created %d posts, updated %d posts. Skipped %d rows.','used-cars-search'),
            intval($results['total']), intval($results['created']), intval($results['updated']), intval($results['skipped'])
        ) . '</p></div>';
        if (!empty($results['rows'])) {
            echo '<h2>' . esc_html__('Summary', 'used-cars-search') . '</h2>';
            echo '<table class="widefat fixed striped" style="max-width:1000px;">';
            echo '<thead><tr><th>#</th><th>' . esc_html__('Title','used-cars-search') . '</th><th>' . esc_html__('Category','used-cars-search') . '</th><th>' . esc_html__('Post ID','used-cars-search') . '</th><th>' . esc_html__('Action','used-cars-search') . '</th></tr></thead><tbody>';
            foreach ($results['rows'] as $i => $row) {
                echo '<tr>';
                echo '<td>' . ($i+1) . '</td>';
                echo '<td>' . esc_html($row['title']) . '</td>';
                echo '<td>' . esc_html($row['make']) . '</td>';
                echo '<td>' . ($row['post_id'] ? intval($row['post_id']) : '—') . '</td>';
                echo '<td>' . esc_html($row['action']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    echo '<form method="post" enctype="multipart/form-data" style="margin-top:1em;max-width:680px;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
    wp_nonce_field('ucs_csv_import', 'ucs_csv_import_nonce');
    echo '<table class="form-table">';
    echo '<tr><th><label for="ucs_csv_file">' . esc_html__('CSV File', 'used-cars-search') . '</label></th><td><input type="file" id="ucs_csv_file" name="ucs_csv_file" accept=".csv,text/csv" /></td></tr>';
    echo '<tr><th></th><td><label><input type="checkbox" name="ucs_use_sample" value="1" /> ' . esc_html__('Use cars.csv from this plugin folder', 'used-cars-search') . '</label></td></tr>';
    echo '<tr><th><label for="ucs_import_status">' . esc_html__('Post Status', 'used-cars-search') . '</label></th><td><select id="ucs_import_status" name="ucs_import_status"><option value="draft">' . esc_html__('Draft','used-cars-search') . '</option><option value="publish">' . esc_html__('Publish','used-cars-search') . '</option></select></td></tr>';
    echo '</table>';
    submit_button(__('Import CSV', 'used-cars-search'));
    echo '</form>';
    echo '</div>';
}

function ucs_process_cars_csv($file_path) {
    $status = isset($_POST['ucs_import_status']) && in_array($_POST['ucs_import_status'], ['draft','publish'], true)
        ? $_POST['ucs_import_status']
        : 'draft';

    $required = ['Year','Make','Model'];
    $optional = ['Trim','Mileage','Engine','Transmission','Price'];

    $map = [];
    $total = $created = $updated = $skipped = 0;
    $rows_out = [];

    if (!file_exists($file_path)) {
        return ['ok' => false, 'message' => __('CSV file not found.', 'used-cars-search')];
    }

    $fh = fopen($file_path, 'r');
    if (!$fh) {
        return ['ok' => false, 'message' => __('Unable to open CSV file.', 'used-cars-search')];
    }

    // Handle potential BOM
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return ['ok'=>false, 'message' => __('Empty CSV.', 'used-cars-search')]; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $headers = str_getcsv($first);

    // Build header map (case-insensitive)
    $lower = array_map('strtolower', $headers);
    foreach ($required as $h) {
        $i = array_search(strtolower($h), $lower, true);
        if ($i === false) {
            fclose($fh);
            /* translators: %s = missing CSV column header label */
            return ['ok'=>false, 'message' => sprintf(__('Missing required column: %s', 'used-cars-search'), $h)];
        }
        $map[$h] = $i;
    }
    foreach ($optional as $h) {
        $i = array_search(strtolower($h), $lower, true);
        if ($i !== false) { $map[$h] = $i; }
    }

    // Iterate rows
    $rowIndex = 1; // header consumed
    while (($cols = fgetcsv($fh)) !== false) {
        $rowIndex++;
        if (count($cols) === 1 && trim((string)$cols[0]) === '') { continue; }
        $total++;

        $year = sanitize_text_field($cols[$map['Year']] ?? '');
        $make = sanitize_text_field($cols[$map['Make']] ?? '');
        $model = sanitize_text_field($cols[$map['Model']] ?? '');
        $trim  = isset($map['Trim']) ? sanitize_text_field($cols[$map['Trim']]) : '';
        $mileage = isset($map['Mileage']) ? preg_replace('/[^0-9]/', '', (string)$cols[$map['Mileage']]) : '';
        $engine = isset($map['Engine']) ? sanitize_text_field($cols[$map['Engine']]) : '';
        $trans = isset($map['Transmission']) ? sanitize_text_field($cols[$map['Transmission']]) : '';
        $price = isset($map['Price']) ? preg_replace('/[^0-9.]/', '', (string)$cols[$map['Price']]) : '';

        if ($year === '' || $make === '' || $model === '') { $skipped++; continue; }

        $title = trim($year . ' ' . $make . ' ' . $model);

        // Ensure category exists (Make as category)
        $term = term_exists($make, 'category');
        if (!$term) {
            $term = wp_insert_term($make, 'category');
        }
        if (is_wp_error($term)) {
            $term_id = 0;
        } elseif (is_array($term) && isset($term['term_id'])) {
            $term_id = intval($term['term_id']);
        } elseif (is_numeric($term)) {
            $term_id = intval($term);
        } else {
            $term_id = 0;
        }

        // Check if a post with the same title exists; if yes, update meta only (avoid deprecated get_page_by_title)
        global $wpdb;
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'post' AND post_status <> 'trash' LIMIT 1",
            $title
        ));
        $post_id = 0; $action = 'created';
        if ($existing_id) {
            $post_id = intval($existing_id);
            // Optionally update category and meta
            if ($term_id) { wp_set_post_categories($post_id, [$term_id], true); }
            $action = 'updated';
            $updated++;
        } else {
            $post_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => '',
                'post_status' => $status,
                'post_type' => 'post',
                'post_category' => $term_id ? [$term_id] : [],
            ]);
            if (is_wp_error($post_id) || !$post_id) { $skipped++; continue; }
            $created++;
        }

        // Update meta
        if ($post_id) {
            update_post_meta($post_id, 'ucs_year', intval($year));
            update_post_meta($post_id, 'ucs_make', $make);
            update_post_meta($post_id, 'ucs_model', $model);
            if ($trim !== '') update_post_meta($post_id, 'ucs_trim', $trim); else delete_post_meta($post_id, 'ucs_trim');
            if ($mileage !== '') update_post_meta($post_id, 'ucs_mileage', intval($mileage));
            if ($engine !== '') update_post_meta($post_id, 'ucs_engine', $engine); else delete_post_meta($post_id, 'ucs_engine');
            if ($trans !== '') update_post_meta($post_id, 'ucs_transmission', $trans); else delete_post_meta($post_id, 'ucs_transmission');
            if ($price !== '') update_post_meta($post_id, 'ucs_price', floatval($price));
        }

        $rows_out[] = [
            'title' => $title,
            'make' => $make,
            'post_id' => $post_id,
            'action' => $action
        ];
    }
    fclose($fh);

    return [
        'ok' => true,
        'total' => $total,
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'rows' => $rows_out,
    ];
}
