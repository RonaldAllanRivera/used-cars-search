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
    if (!empty($opts['queue_paused'])) return; // Paused

    // If a stop was requested while idle, honor it and clear.
    if (get_transient('ucs_ai_queue_stop')) {
        delete_transient('ucs_ai_queue_stop');
        return;
    }

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

        // Soft stop requested: finish current item and exit
        if (get_transient('ucs_ai_queue_stop')) {
            delete_transient('ucs_ai_queue_stop');
            break;
        }
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

    // AJAX: queue status (lock + counts + paused)
    add_action('wp_ajax_ucs_ai_queue_status', function(){
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        check_ajax_referer('ucs_ai_admin', 'nonce');
        global $wpdb; $table = ucs_ai_queue_table();
        $queued = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status=%s", 'queued')));
        $processing = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status=%s", 'processing')));
        $errors = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status=%s", 'error')));
        $canceled = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status=%s", 'canceled')));
        $lock = (bool) get_transient('ucs_ai_worker_lock');
        $stopping = (bool) get_transient('ucs_ai_queue_stop');
        $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : array();
        $paused = !empty($opts['queue_paused']);
        wp_send_json_success(array(
            'lock' => $lock,
            'queued' => $queued,
            'processing' => $processing,
            'errors' => $errors,
            'canceled' => $canceled,
            'paused' => $paused,
            'stopping' => $stopping,
        ));
    });

    // AJAX: toggle pause/resume
    add_action('wp_ajax_ucs_ai_queue_toggle_pause', function(){
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        check_ajax_referer('ucs_ai_admin', 'nonce');
        $opts = get_option('ucs_ai_options');
        if (!is_array($opts)) $opts = array();
        $paused = !empty($opts['queue_paused']);
        $opts['queue_paused'] = $paused ? 0 : 1;
        update_option('ucs_ai_options', $opts);
        wp_send_json_success(array('paused' => (bool)$opts['queue_paused']));
    });

    // AJAX: request soft stop (after current item)
    add_action('wp_ajax_ucs_ai_queue_stop', function(){
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        check_ajax_referer('ucs_ai_admin', 'nonce');
        set_transient('ucs_ai_queue_stop', 1, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success(array('stopping' => true));
    });

    // AJAX: cancel all queued items
    add_action('wp_ajax_ucs_ai_queue_cancel_all', function(){
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        check_ajax_referer('ucs_ai_admin', 'nonce');
        global $wpdb; $table = ucs_ai_queue_table();
        $updated = $wpdb->query("UPDATE $table SET status='canceled', updated_at=NOW() WHERE status='queued'");
        wp_send_json_success(array('canceled' => intval($updated)));
    });

    // Floating admin indicator (polls every 20s)
    add_action('admin_footer', function(){
        // Allow disabling via filter
        if (false === apply_filters('ucs_ai_queue_indicator_enabled', true)) return;
        $nonce = wp_create_nonce('ucs_ai_admin');
        ?>
        <style>
            #ucs-ai-queue-indicator { position: fixed; left: 50%; transform: translateX(-50%); top: 48px; z-index: 99999; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 8px 10px; display: none; align-items: center; gap: 8px; max-width: calc(100% - 32px); }
            #ucs-ai-queue-indicator .ucs-dot { width: 10px; height: 10px; border-radius: 50%; background: #8c8f94; display: inline-block; }
            #ucs-ai-queue-indicator.ucs-running .ucs-dot { background: #2271b1; animation: ucs-pulse 1s infinite alternate; }
            #ucs-ai-queue-indicator .ucs-text { font-size: 12px; color: #1d2327; }
            @keyframes ucs-rotate { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
            @keyframes ucs-pulse { from { opacity: .5; } to { opacity: 1; } }
        </style>
        <div id="ucs-ai-queue-indicator" aria-live="polite" aria-atomic="true">
            <span class="ucs-dot" aria-hidden="true"></span>
            <span class="ucs-text"></span>
            <span class="ucs-actions" style="display:flex; gap:6px; margin-left:8px;">
                <button type="button" class="button button-small ucs-btn-pause">Pause</button>
                <button type="button" class="button button-small ucs-btn-stop">Stop</button>
                <button type="button" class="button button-small ucs-btn-cancel">Cancel queued</button>
            </span>
        </div>
        <script>
        (function(){
            const el = document.getElementById('ucs-ai-queue-indicator');
            if (!el || typeof ajaxurl === 'undefined') return;
            const text = el.querySelector('.ucs-text');
            const dot = el.querySelector('.ucs-dot');
            const btnPause = el.querySelector('.ucs-btn-pause');
            const btnStop = el.querySelector('.ucs-btn-stop');
            const btnCancel = el.querySelector('.ucs-btn-cancel');
            let timer;
            function position(){
                const bar = document.getElementById('wpadminbar');
                let top = (bar ? bar.offsetHeight : 32);
                // If Screen Options/Help panel is open, push indicator below it
                const meta = document.getElementById('screen-meta');
                if (meta) {
                    const style = window.getComputedStyle(meta);
                    if (style && style.display !== 'none' && meta.offsetHeight) {
                        top += meta.offsetHeight;
                    }
                }
                el.style.top = (top + 8) + 'px';
            }
            function render(state){
                const { lock, queued, processing, errors, canceled, paused, stopping } = state || {};
                // update buttons
                if (paused) {
                    btnPause.textContent = 'Resume';
                } else {
                    btnPause.textContent = 'Pause';
                }
                btnCancel.disabled = !(queued > 0);
                // show status
                if (paused) {
                    el.classList.remove('ucs-running');
                    text.textContent = `AI Queue: Paused ( ${queued||0} queued${processing?`, ${processing} processing`:''}${errors?`, ${errors} error(s)`:''}${canceled?`, ${canceled} canceled`:''} )`;
                    el.style.display = (queued||processing||errors||canceled) ? 'flex' : 'flex';
                } else if (lock) {
                    el.classList.add('ucs-running');
                    const stopNote = stopping ? ' — Stopping…' : '';
                    text.textContent = `AI Queue: Processing… (in progress${processing?`, ${processing} processing`:''}${queued?`, ${queued} queued`:''}${errors?`, ${errors} error(s)`:''}${canceled?`, ${canceled} canceled`:''})${stopNote}`;
                    el.style.display = 'flex';
                } else if ((queued||0) > 0 || (processing||0) > 0) {
                    el.classList.remove('ucs-running');
                    text.textContent = `AI Queue: Waiting ( ${queued||0} queued${processing?`, ${processing} processing`:''}${errors?`, ${errors} error(s)`:''}${canceled?`, ${canceled} canceled`:''} )`;
                    el.style.display = 'flex';
                } else {
                    el.style.display = 'none';
                }
            }
            function ajax(action){
                const form = new FormData();
                form.append('action', action);
                form.append('nonce','<?php echo esc_js($nonce); ?>');
                return fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: form })
                    .then(r => r.json());
            }
            function poll(){
                const form = new FormData();
                form.append('action','ucs_ai_queue_status');
                form.append('nonce','<?php echo esc_js($nonce); ?>');
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: form })
                    .then(r => r.json()).then(d => {
                        if (d && d.success) render(d.data); else render(null);
                    })
                    .catch(() => render(null));
            }
            position();
            poll();
            timer = setInterval(poll, 20000);
            window.addEventListener('resize', position);
            window.addEventListener('beforeunload', function(){ if (timer) clearInterval(timer); });

            // Recalculate when Screen Options / Help are toggled
            const toggleSelectors = '#show-settings-link, #contextual-help-link';
            document.querySelectorAll(toggleSelectors).forEach(function(btn){
                btn.addEventListener('click', function(){
                    // run after WP toggles the panel
                    setTimeout(position, 50);
                    setTimeout(position, 300);
                });
            });
            // Observe #screen-meta attribute changes
            const metaEl = document.getElementById('screen-meta');
            if (metaEl && 'MutationObserver' in window) {
                const mo = new MutationObserver(function(){ position(); });
                mo.observe(metaEl, { attributes: true, attributeFilter: ['style','class'] });
            }

            // Wire up actions
            if (btnPause) btnPause.addEventListener('click', function(){
                btnPause.disabled = true; ajax('ucs_ai_queue_toggle_pause').then(() => { btnPause.disabled = false; poll(); }).catch(() => { btnPause.disabled = false; });
            });
            if (btnStop) btnStop.addEventListener('click', function(){
                btnStop.disabled = true; ajax('ucs_ai_queue_stop').then(() => { btnStop.disabled = false; poll(); }).catch(() => { btnStop.disabled = false; });
            });
            if (btnCancel) btnCancel.addEventListener('click', function(){
                if (!confirm('Cancel all queued items?')) return;
                btnCancel.disabled = true; ajax('ucs_ai_queue_cancel_all').then(() => { btnCancel.disabled = false; poll(); }).catch(() => { btnCancel.disabled = false; });
            });
        })();
        </script>
        <?php
    });
}
