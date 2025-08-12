<?php
if (!defined('ABSPATH')) exit;

// Admin UI for OpenAI settings and test connection

add_action('admin_menu', function() {
    add_submenu_page(
        'ucs_admin',
        __('AI Settings', 'used-cars-search'),
        __('AI Settings', 'used-cars-search'),
        'manage_options',
        'ucs_ai_settings',
        'ucs_ai_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('ucs_ai', 'ucs_ai_options', 'ucs_ai_sanitize_options');
});

function ucs_ai_sanitize_options($opts) {
    $clean = array();
    $clean['enabled']     = isset($opts['enabled']) ? 1 : 0;
    $clean['api_key']     = isset($opts['api_key']) ? trim(sanitize_text_field($opts['api_key'])) : '';
    $requested_model = isset($opts['model']) ? sanitize_text_field($opts['model']) : 'gpt-4o-mini';
    $models = function_exists('ucs_ai_available_models') ? array_keys(ucs_ai_available_models()) : array('gpt-4o-mini');
    $clean['model'] = in_array($requested_model, $models, true) ? $requested_model : 'gpt-4o-mini';
    $temp = isset($opts['temperature']) ? floatval($opts['temperature']) : 0.3;
    if ($temp < 0) $temp = 0; if ($temp > 1) $temp = 1; $clean['temperature'] = $temp;
    $max  = isset($opts['max_tokens']) ? intval($opts['max_tokens']) : 800;
    if ($max < 64) $max = 64; if ($max > 4000) $max = 4000; $clean['max_tokens'] = $max;
    return $clean;
}

function ucs_ai_settings_page() {
    if (!current_user_can('manage_options')) return;
    $opts = function_exists('ucs_ai_get_options') ? ucs_ai_get_options() : get_option('ucs_ai_options');
    if (!is_array($opts)) $opts = array();
    $nonce = wp_create_nonce('ucs_ai_nonce');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('AI Settings', 'used-cars-search'); ?></h1>
        <p><?php esc_html_e('Configure OpenAI integration. This feature is admin-only and does not affect frontend functionality.', 'used-cars-search'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ucs_ai'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="ucs_ai_enabled"><?php esc_html_e('Enable AI Features', 'used-cars-search'); ?></label></th>
                        <td>
                            <label><input type="checkbox" id="ucs_ai_enabled" name="ucs_ai_options[enabled]" value="1" <?php checked(!empty($opts['enabled'])); ?>> <?php esc_html_e('Enabled (admin tools only)', 'used-cars-search'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ucs_ai_api_key"><?php esc_html_e('OpenAI API Key', 'used-cars-search'); ?></label></th>
                        <td>
                            <input type="password" id="ucs_ai_api_key" name="ucs_ai_options[api_key]" value="<?php echo esc_attr($opts['api_key'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Stored securely in WordPress options. Used only on the server for API calls.', 'used-cars-search'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ucs_ai_model"><?php esc_html_e('Model', 'used-cars-search'); ?></label></th>
                        <td>
                            <?php $models = function_exists('ucs_ai_available_models') ? ucs_ai_available_models() : array('gpt-4o-mini' => 'gpt-4o-mini'); ?>
                            <select id="ucs_ai_model" name="ucs_ai_options[model]">
                                <?php foreach ($models as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected(($opts['model'] ?? 'gpt-4o-mini'), $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Default: gpt-4o-mini. You can filter the available list via ucs_ai_models.', 'used-cars-search'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ucs_ai_temperature"><?php esc_html_e('Temperature', 'used-cars-search'); ?></label></th>
                        <td>
                            <input type="number" step="0.1" min="0" max="1" id="ucs_ai_temperature" name="ucs_ai_options[temperature]" value="<?php echo esc_attr($opts['temperature'] ?? 0.3); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ucs_ai_max_tokens"><?php esc_html_e('Max Tokens', 'used-cars-search'); ?></label></th>
                        <td>
                            <input type="number" min="64" max="4000" id="ucs_ai_max_tokens" name="ucs_ai_options[max_tokens]" value="<?php echo esc_attr($opts['max_tokens'] ?? 800); ?>" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <h2><?php esc_html_e('How to Use', 'used-cars-search'); ?></h2>
        <div class="ucs-howto-box" style="background: #f8f9fa; padding: 15px 20px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
            <h3><?php esc_html_e('Configure:', 'used-cars-search'); ?></h3>
            <ol>
                <li><?php esc_html_e('WP Admin → Used Cars Search → AI Settings', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('Enable AI, enter API key, select model from dropdown.', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('Use "Test Connection" to verify; "OK" indicates success.', 'used-cars-search'); ?></li>
            </ol>
            
            <h3><?php esc_html_e('Single post:', 'used-cars-search'); ?></h3>
            <ol>
                <li><?php esc_html_e('Edit any post → "AI Assist (Used Cars)" meta box.', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('Select fields → Generate → review → Apply.', 'used-cars-search'); ?></li>
            </ol>
            
            <h3><?php esc_html_e('Bulk:', 'used-cars-search'); ?></h3>
            <ol>
                <li><?php esc_html_e('Posts → check posts → Bulk actions → "AI Assist: Generate & Apply" → Apply.', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('Notice shows processed/failed counts.', 'used-cars-search'); ?></li>
            </ol>

            <h3><?php esc_html_e('Background queue (WP‑Cron):', 'used-cars-search'); ?></h3>
            <ol>
                <li><?php esc_html_e('Enable AI above and save settings.', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('Enqueue posts: Posts → select items → Bulk actions → "AI Assist: Queue for Background" → Apply.', 'used-cars-search'); ?></li>
                <li><?php esc_html_e('WP‑Cron runs every minute and processes queued items in small batches automatically.', 'used-cars-search'); ?></li>
                <li><?php echo wp_kses_post(__('To run unattended while logged out or overnight on Laragon, schedule a task to request <code>http://localhost/popular-ai-software-search/wp-cron.php</code> every minute (Windows Task Scheduler).', 'used-cars-search')); ?></li>
                <li><?php echo wp_kses_post(__('Alternative: add <code>define(\'ALTERNATE_WP_CRON\', true);</code> in wp-config.php and ensure periodic traffic to trigger WP‑Cron.', 'used-cars-search')); ?></li>
                <li><?php esc_html_e('You will see admin notices for enqueue results; items are applied in the background—no browser tab required.', 'used-cars-search'); ?></li>
            </ol>

            <h4 style="margin-top:10px;"><?php esc_html_e('SiteGround and shared hosting setup:', 'used-cars-search'); ?></h4>
            <p><?php esc_html_e('On shared hosts, it is best to disable the built‑in WP‑Cron trigger and use a real server cron calling wp-cron.php at a fixed interval.', 'used-cars-search'); ?></p>
            <ol>
                <li><?php echo wp_kses_post(__('In <code>wp-config.php</code> add: <code>define(\'DISABLE_WP_CRON\', true);</code>', 'used-cars-search')); ?></li>
                <li><?php echo wp_kses_post(__('SiteGround: Site Tools → Devs → Cron Jobs → Create Cron Job', 'used-cars-search')); ?>
                    <ul style="list-style: disc; margin-left:20px;">
                        <li><?php echo wp_kses_post(__('Schedule: Every 1 minute (or every 5 minutes if 1 minute is not allowed).', 'used-cars-search')); ?></li>
                        <li><?php echo wp_kses_post(__('Command (choose one):', 'used-cars-search')); ?>
                            <div style="margin:6px 0 10px 0;">
                                <code>wget -q -O - https://your-domain.com/wp-cron.php?doing_wp_cron &gt; /dev/null 2&gt;&1</code><br>
                                <code>curl -s https://your-domain.com/wp-cron.php?doing_wp_cron &gt; /dev/null 2&gt;&1</code>
                            </div>
                        </li>
                    </ul>
                </li>
                <li><?php echo wp_kses_post(__('cPanel (generic shared hosting): Cron Jobs → Add New Cron Job → use the same <code>wget</code> or <code>curl</code> command above.', 'used-cars-search')); ?></li>
                <li><?php echo wp_kses_post(__('Replace <code>your-domain.com</code> with your real domain. Keep <code>?doing_wp_cron</code> to avoid caching layers from skipping the run.', 'used-cars-search')); ?></li>
                <li><?php echo wp_kses_post(__('If your host restricts every‑minute intervals, pick the smallest allowed (e.g., 5 minutes). The queue will continue on the next tick.', 'used-cars-search')); ?></li>
            </ol>
        </div>
        
        <hr>
        <h2><?php esc_html_e('Test Connection', 'used-cars-search'); ?></h2>
        <p><?php esc_html_e('Click to verify your API key and model. No content is generated; the test requests a simple "OK" reply.', 'used-cars-search'); ?></p>
        <p>
            <button class="button button-secondary" id="ucs-ai-test-btn"><?php esc_html_e('Test Connection', 'used-cars-search'); ?></button>
            <span id="ucs-ai-test-status" style="margin-left:8px;"></span>
        </p>
        <script>
        (function(){
            const btn = document.getElementById('ucs-ai-test-btn');
            const status = document.getElementById('ucs-ai-test-status');
            const keyInput = document.getElementById('ucs_ai_api_key');
            if (!btn) return;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                status.textContent = 'Testing...';
                const form = new FormData();
                form.append('action','ucs_ai_test_connection');
                form.append('nonce','<?php echo esc_js($nonce); ?>');
                // send transient key value if provided but not yet saved
                if (keyInput && keyInput.value) form.append('api_key', keyInput.value);
                fetch(ajaxurl, { method:'POST', body: form })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.data && d.data.ok) {
                            status.innerHTML = '<span style="color:green;">OK</span>';
                        } else {
                            const msg = d && d.data && d.data.message ? d.data.message : 'Failed';
                            status.innerHTML = '<span style="color:#a00;">' + String(msg) + '</span>';
                        }
                    })
                    .catch(err => {
                        status.innerHTML = '<span style="color:#a00;">' + (err && err.message ? err.message : 'Error') + '</span>';
                    });
            });
        })();
        </script>
    </div>
    <?php
}
