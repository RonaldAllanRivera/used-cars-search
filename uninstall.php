<?php
/**
 * Uninstall cleanup for Used Cars Search
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
$option_keys = array(
    'ucs_options',      // general plugin settings (compare page, etc.)
    'ucs_ai_options',   // AI settings and flags (enabled, paused, etc.)
    'ucs_stopwords',    // autosuggest/keyword stopwords list
);
foreach ($option_keys as $key) {
    delete_option($key);
    // Multisite safety
    if (is_multisite()) {
        delete_site_option($key);
    }
}

// Clear transients/locks used by the AI queue
$transient_keys = array(
    'ucs_ai_worker_lock',
    'ucs_ai_queue_stop',
);
foreach ($transient_keys as $t) {
    delete_transient($t);
}

// Unschedule the AI queue worker cron event
if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('ucs_ai_queue_worker');
}

// Drop custom database tables (if they exist)
// - AI queue: {prefix}ucs_ai_queue
// - Ratings:  {prefix}ucs_ratings (used for star ratings)
global $wpdb;
$tables = array(
    isset($wpdb) ? $wpdb->prefix . 'ucs_ai_queue' : null,
    isset($wpdb) ? $wpdb->prefix . 'ucs_ratings'  : null,
);
foreach ($tables as $table) {
    if ($table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
