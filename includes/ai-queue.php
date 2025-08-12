<?php
if (!defined('ABSPATH')) exit;

// AI Queue: background processing via WP-Cron
// - Queue table creation helper
// - Enqueue API
// - Cron schedule and worker
// - Minimal admin bulk action to enqueue selected posts

function ucs_ai_queue_table() {
    global $wpdb; return $wpdb->prefix . 'ucs_ai_queue';
}

function ucs_ai_queue_install() {
    global $wpdb; $table = ucs_ai_queue_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        fields_json LONGTEXT NULL,
        model_map_json LONGTEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        tokens_in INT NULL,
        tokens_out INT NULL,
        cost DECIMAL(10,4) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_status (status),
        KEY idx_post (post_id)
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Auto-install table if missing (for existing activations)
add_action('plugins_loaded', function(){
    global $wpdb; $table = ucs_ai_queue_table();
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
        ucs_ai_queue_install();
    }
});

// Add every-minute schedule
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['ucs_every_minute'])) {
        $schedules['ucs_every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute (UCS)', 'used-cars-search')
        );
    }
    return $schedules;
});

// Ensure event is scheduled
add_action('init', function(){
    if (!wp_next_scheduled('ucs_ai_queue_worker')) {
        wp_schedule_event(time() + 60, 'ucs_every_minute', 'ucs_ai_queue_worker');
    }
});

// Simple lock to avoid overlap
function ucs_ai_queue_lock_acquire() {
    if (get_transient('ucs_ai_worker_lock')) return false;
    set_transient('ucs_ai_worker_lock', 1, 5 * MINUTE_IN_SECONDS);
    return true;
}
function ucs_ai_queue_lock_release() { delete_transient('ucs_ai_worker_lock'); }

// Enqueue a post for AI generation
function ucs_ai_queue_add($post_id, $fields = null, $model_map = null) {
    global $wpdb; $table = ucs_ai_queue_table();
    $post_id = intval($post_id); if (!$post_id) return false;
    $data = array(
        'post_id' => $post_id,
        'fields_json' => $fields ? wp_json_encode(array_values($fields)) : null,
        'model_map_json' => $model_map ? wp_json_encode($model_map) : null,
        'status' => 'queued',
        'attempts' => 0,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    );
    $ok = $wpdb->insert($table, $data);
    return (bool)$ok;
}

// Worker: process a batch each run
add_action('ucs_ai_queue_worker', function(){
    // Optional toggle via options later
    if (!function_exists('ucs_ai_get_options')) return;
    $opts = ucs_ai_get_options();
    if (empty($opts['enabled'])) return; // Respect master toggle

    if (!ucs_ai_queue_lock_acquire()) return;
    try {
        ucs_ai_process_queue_batch();
    } finally {
        ucs_ai_queue_lock_release();
    }
});

function ucs_ai_process_queue_batch($limit = 10) {
    global $wpdb; $table = ucs_ai_queue_table();
    $limit = intval(apply_filters('ucs_ai_queue_batch_size', $limit));
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status=%s ORDER BY id ASC LIMIT %d", 'queued', $limit));
    if (!$items) return 0;

    $processed = 0;
    foreach ($items as $row) {
        $id = intval($row->id); $post_id = intval($row->post_id);
        $fields = $row->fields_json ? json_decode($row->fields_json, true) : array('title','content','seo_title','seo_description','seo_keywords');
        if (!is_array($fields) || empty($fields)) $fields = array('title','content','seo_title','seo_description','seo_keywords');

        // Mark processing
        $wpdb->update($table, array('status' => 'processing', 'updated_at' => current_time('mysql')), array('id' => $id));

        // Generate
        $gen = function_exists('ucs_ai_generate_for_post_core') ? ucs_ai_generate_for_post_core($post_id, $fields) : new WP_Error('ucs_ai_missing', 'AI core not loaded');
        if (is_wp_error($gen)) {
            $wpdb->update($table, array(
                'status' => 'error',
                'attempts' => intval($row->attempts) + 1,
                'last_error' => $gen->get_error_message(),
                'updated_at' => current_time('mysql'),
            ), array('id' => $id));
            continue;
        }
        // Apply
        $applied = function_exists('ucs_ai_apply_changes_core') ? ucs_ai_apply_changes_core($post_id, $gen, $fields) : new WP_Error('ucs_ai_missing', 'AI core not loaded');
        if (is_wp_error($applied)) {
            $wpdb->update($table, array(
                'status' => 'error',
                'attempts' => intval($row->attempts) + 1,
                'last_error' => $applied->get_error_message(),
                'updated_at' => current_time('mysql'),
            ), array('id' => $id));
            continue;
        }

        $wpdb->update($table, array(
            'status' => 'done',
            'updated_at' => current_time('mysql'),
        ), array('id' => $id));
        $processed++;
    }
    return $processed;
}

// Admin: add bulk action to enqueue
if (is_admin()) {
    add_filter('bulk_actions-edit-post', function($actions){
        $actions['ucs_ai_bulk_queue'] = __('AI Assist: Queue for Background', 'used-cars-search');
        return $actions;
    });

    add_filter('handle_bulk_actions-edit-post', function($redirect_to, $doaction, $post_ids){
        if ($doaction !== 'ucs_ai_bulk_queue') return $redirect_to;
        $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : array();
        if (empty($opts['enabled'])) {
            return add_query_arg('ucs_ai_queue_error', 1, $redirect_to);
        }
        $ok = 0; $fail = 0;
        foreach ($post_ids as $pid) {
            $r = ucs_ai_queue_add($pid, array('title','content','seo_title','seo_description','seo_keywords'));
            if ($r) $ok++; else $fail++;
        }
        $redirect_to = add_query_arg('ucs_ai_queue_done', $ok, $redirect_to);
        if ($fail) $redirect_to = add_query_arg('ucs_ai_queue_fail', $fail, $redirect_to);
        return $redirect_to;
    }, 10, 3);

    add_action('admin_notices', function(){
        if (!empty($_GET['ucs_ai_queue_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>'.esc_html__('AI is disabled in settings. Enable it to enqueue.', 'used-cars-search').'</p></div>';
        }
        if (isset($_GET['ucs_ai_queue_done'])) {
            $ok = intval($_GET['ucs_ai_queue_done']);
            $fail = intval($_GET['ucs_ai_queue_fail'] ?? 0);
            echo '<div class="notice notice-success is-dismissible"><p>'.sprintf(esc_html__('AI Queue: enqueued %d posts, %d failed.', 'used-cars-search'), $ok, $fail).'</p></div>';
        }
    });
}
