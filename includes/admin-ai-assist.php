<?php
if (!defined('ABSPATH')) exit;

// Admin AI Assist: single-post meta box and bulk action

// Add AI Assist meta box on post edit screen
add_action('add_meta_boxes', function() {
    add_meta_box(
        'ucs_ai_assist_box',
        __('AI Assist (Used Cars)', 'used-cars-search'),
        'ucs_ai_render_assist_box',
        'post',
        'side',
        'high'
    );
});

function ucs_ai_render_assist_box($post) {
    if (!current_user_can('edit_post', $post->ID)) return;
    $nonce = wp_create_nonce('ucs_ai_mb_nonce');
    $fields = ['year','make','model','trim','price','mileage','engine','transmission'];
    $meta = [];
    foreach ($fields as $f) { $meta[$f] = get_post_meta($post->ID, 'ucs_'.$f, true); }
    $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : [];
    $enabled = !empty($opts['enabled']);
    echo '<div style="font-size:12px;line-height:1.45">';
    if (!$enabled) {
        echo '<p>'.esc_html__('AI features are currently disabled. Enable them in Used Cars Search → AI Settings.', 'used-cars-search').'</p>';
    }
    echo '<p>'.esc_html__('Select fields to generate and click Generate. Review the suggestions and click Apply.', 'used-cars-search').'</p>';
    echo '<label><input type="checkbox" class="ucs-ai-field" value="title" checked> '.esc_html__('Post Title', 'used-cars-search').'</label><br>';
    echo '<label><input type="checkbox" class="ucs-ai-field" value="content" checked> '.esc_html__('Post Content', 'used-cars-search').'</label><br>';
    echo '<label><input type="checkbox" class="ucs-ai-field" value="seo_title" checked> '.esc_html__('SEO Title', 'used-cars-search').'</label><br>';
    echo '<label><input type="checkbox" class="ucs-ai-field" value="seo_description" checked> '.esc_html__('SEO Description', 'used-cars-search').'</label><br>';
    echo '<label><input type="checkbox" class="ucs-ai-field" value="seo_keywords" checked> '.esc_html__('SEO Keywords', 'used-cars-search').'</label><br>';
    echo '<p><button type="button" class="button" id="ucs-ai-generate" '.($enabled?'':'disabled').'>'.esc_html__('Generate', 'used-cars-search').'</button> ';
    echo '<span id="ucs-ai-status" style="margin-left:6px;"></span></p>';

    echo '<div id="ucs-ai-results" style="display:none">';
    echo '<p><strong>'.esc_html__('Suggestions', 'used-cars-search').'</strong></p>';
    echo '<label>'.esc_html__('Title', 'used-cars-search').'<br><input type="text" id="ucs-ai-title" class="widefat"></label><br>';
    echo '<label>'.esc_html__('Content', 'used-cars-search').'<br><textarea id="ucs-ai-content" rows="6" class="widefat"></textarea></label><br>';
    echo '<label>'.esc_html__('SEO Title', 'used-cars-search').'<br><input type="text" id="ucs-ai-seo-title" class="widefat"></label><br>';
    echo '<label>'.esc_html__('SEO Description', 'used-cars-search').'<br><textarea id="ucs-ai-seo-description" rows="3" class="widefat"></textarea></label><br>';
    echo '<label>'.esc_html__('SEO Keywords (comma-separated)', 'used-cars-search').'<br><input type="text" id="ucs-ai-seo-keywords" class="widefat"></label><br>';
    echo '<p><button type="button" class="button button-primary" id="ucs-ai-apply" '.($enabled?'':'disabled').'>'.esc_html__('Apply to Post', 'used-cars-search').'</button></p>';
    echo '</div>';

    // Context data for JS
    $ctx = array(
        'nonce' => $nonce,
        'post_id' => $post->ID,
        'meta' => $meta,
    );
?>
<script>
(function(){
    const ctx = <?php echo wp_json_encode($ctx); ?>;
    const $ = (s)=>document.querySelector(s);
    const status = $('#ucs-ai-status');
    const resBox = $('#ucs-ai-results');
    const genBtn = $('#ucs-ai-generate');
    const applyBtn = $('#ucs-ai-apply');
    function getSelectedFields(){
        return Array.from(document.querySelectorAll('.ucs-ai-field:checked')).map(x=>x.value);
    }
    if (genBtn) genBtn.addEventListener('click', function(){
        status.textContent = '<?php echo esc_js(__('Generating...', 'used-cars-search')); ?>';
        const form = new FormData();
        form.append('action','ucs_ai_generate');
        form.append('nonce', ctx.nonce);
        form.append('post_id', ctx.post_id);
        form.append('fields', JSON.stringify(getSelectedFields()));
        fetch(ajaxurl, { method:'POST', body: form })
          .then(r=>r.json()).then(d=>{
            if (!d || !d.success) {
                status.innerHTML = '<span style="color:#a00;">'+(d && d.data && d.data.message ? d.data.message : 'Error')+'</span>';
                return;
            }
            const v = d.data;
            if (v.title) $('#ucs-ai-title').value = v.title;
            if (v.content) $('#ucs-ai-content').value = v.content;
            if (v.seo_title) $('#ucs-ai-seo-title').value = v.seo_title;
            if (v.seo_description) $('#ucs-ai-seo-description').value = v.seo_description;
            if (v.seo_keywords) $('#ucs-ai-seo-keywords').value = v.seo_keywords;
            resBox.style.display = 'block';
            status.innerHTML = '<span style="color:green;">OK</span>';
          }).catch(e=>{
            status.innerHTML = '<span style="color:#a00;">'+(e && e.message ? e.message : 'Error')+'</span>';
          });
    });
    if (applyBtn) applyBtn.addEventListener('click', function(){
        status.textContent = '<?php echo esc_js(__('Applying...', 'used-cars-search')); ?>';
        const form = new FormData();
        form.append('action','ucs_ai_apply');
        form.append('nonce', ctx.nonce);
        form.append('post_id', ctx.post_id);
        form.append('payload', JSON.stringify({
            title: $('#ucs-ai-title').value,
            content: $('#ucs-ai-content').value,
            seo_title: $('#ucs-ai-seo-title').value,
            seo_description: $('#ucs-ai-seo-description').value,
            seo_keywords: $('#ucs-ai-seo-keywords').value,
            fields: getSelectedFields()
        }));
        fetch(ajaxurl, { method:'POST', body: form })
          .then(r=>r.json()).then(d=>{
            if (!d || !d.success) {
                status.innerHTML = '<span style="color:#a00;">'+(d && d.data && d.data.message ? d.data.message : 'Error')+'</span>';
                return;
            }
            
            // Show success message briefly before reload
            status.innerHTML = '<span style="color:green;">'+(d.data && d.data.message ? d.data.message : 'Done')+' - Refreshing page...</span>';
            
            // Reload the page after a short delay to show the success message
            setTimeout(function() {
                window.location.reload();
            }, 800);
          }).catch(e=>{
            status.innerHTML = '<span style="color:#a00;">'+(e && e.message ? e.message : 'Error')+'</span>';
          });
    });
})();
</script>
<?php
    echo '</div>';
}

// AJAX: Generate suggestions for a post
add_action('wp_ajax_ucs_ai_generate', 'ucs_ai_ajax_generate');
function ucs_ai_ajax_generate() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => __('Unauthorized', 'used-cars-search')], 403);
    }
    check_ajax_referer('ucs_ai_mb_nonce', 'nonce');

    $fields = isset($_POST['fields']) ? json_decode(stripslashes((string)$_POST['fields']), true) : ['title','content','seo_title','seo_description','seo_keywords'];
    if (!is_array($fields) || empty($fields)) {
        $fields = ['title','content','seo_title','seo_description','seo_keywords'];
    }

    $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : [];
    if (empty($opts['enabled'])) {
        wp_send_json_error(['message' => __('AI is disabled in settings.', 'used-cars-search')]);
    }

    $data = ucs_ai_generate_for_post($post_id, $fields);
    if (is_wp_error($data)) {
        wp_send_json_error(['message' => $data->get_error_message()]);
    }
    wp_send_json_success($data);
}

// AJAX: Apply selected suggestions to post
add_action('wp_ajax_ucs_ai_apply', 'ucs_ai_ajax_apply');
function ucs_ai_ajax_apply() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => __('Unauthorized', 'used-cars-search')], 403);
    }
    check_ajax_referer('ucs_ai_mb_nonce', 'nonce');

    $payload = isset($_POST['payload']) ? json_decode(stripslashes((string)$_POST['payload']), true) : [];
    if (!is_array($payload)) $payload = [];
    $fields = isset($payload['fields']) && is_array($payload['fields']) ? $payload['fields'] : ['title','content','seo_title','seo_description','seo_keywords'];
    if (function_exists('ucs_ai_apply_changes_core')) {
        $res = ucs_ai_apply_changes_core($post_id, $payload, $fields);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }
        $updated = isset($res['updated']) ? $res['updated'] : array();
        /* translators: %s = comma-separated list of updated fields (e.g., title, content, seo_title) */
        wp_send_json_success(['message' => sprintf(__('Updated: %s', 'used-cars-search'), implode(', ', $updated))]);
    } else {
        wp_send_json_error(['message' => __('AI core not available.', 'used-cars-search')]);
    }
}

// Helper: Build messages and call OpenAI for a given post
function ucs_ai_generate_for_post($post_id, $fields) {
    if (!function_exists('ucs_ai_generate_for_post_core')) {
        return new WP_Error('ucs_ai_missing', __('AI core missing', 'used-cars-search'));
    }
    // Normalize requested fields
    $requested = array_values(array_intersect((array)$fields, ['title','content','seo_title','seo_description','seo_keywords']));
    if (empty($requested)) $requested = ['title','content','seo_title','seo_description','seo_keywords'];
    // Delegate to the shared core to ensure consistent long-form prompt, structure, and parsing
    return ucs_ai_generate_for_post_core($post_id, $requested);
}

// Bulk action: AI Assist Generate & Apply
add_filter('bulk_actions-edit-post', function($actions){
    $actions['ucs_ai_bulk_generate_apply'] = __('AI Assist: Generate & Apply', 'used-cars-search');
    return $actions;
});

add_filter('handle_bulk_actions-edit-post', function($redirect_to, $action, $post_ids){
    if ($action !== 'ucs_ai_bulk_generate_apply') return $redirect_to;
    $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : [];
    if (empty($opts['enabled'])) {
        return add_query_arg(['ucs_ai_bulk_error' => 1], $redirect_to);
    }
    $ok = 0; $fail = 0;
    foreach ($post_ids as $pid) {
        if (!current_user_can('edit_post', $pid)) { $fail++; continue; }
        $data = ucs_ai_generate_for_post($pid, ['title','content','seo_title','seo_description','seo_keywords']);
        if (is_wp_error($data)) { $fail++; continue; }
        // apply via core (schedules post 24h ahead for draft/pending)
        if (function_exists('ucs_ai_apply_changes_core')) {
            $applied = ucs_ai_apply_changes_core($pid, $data, ['title','content','seo_title','seo_description','seo_keywords']);
            if (is_wp_error($applied)) { $fail++; continue; }
        } else {
            $fail++; continue;
        }
        $ok++;
    }
    return add_query_arg(['ucs_ai_bulk_done' => $ok, 'ucs_ai_bulk_fail' => $fail], $redirect_to);
}, 10, 3);

add_action('admin_notices', function(){
    if (!empty($_GET['ucs_ai_bulk_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>'.esc_html__('AI is disabled in settings. Enable it to use bulk action.', 'used-cars-search').'</p></div>';
    }
    if (isset($_GET['ucs_ai_bulk_done'])) {
        $ok = intval($_GET['ucs_ai_bulk_done']);
        $fail = intval($_GET['ucs_ai_bulk_fail'] ?? 0);
        /* translators: 1: number of posts successfully processed, 2: number of posts that failed */
        echo '<div class="notice notice-success is-dismissible"><p>'.sprintf(esc_html__('AI Assist: processed %d posts, %d failed.', 'used-cars-search'), $ok, $fail).'</p></div>';
    }
});
