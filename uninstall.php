<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
$cleanup = static function (): void {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'funnelkit_leads');
    delete_option('funnelkit_funnels');
    delete_option('funnelkit_last_id');
};
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
        switch_to_blog($site_id); $cleanup(); restore_current_blog();
    }
} else { $cleanup(); }
