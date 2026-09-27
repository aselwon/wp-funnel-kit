<?php
// Invoked by WP-CLI against only the disposable ZIP verification prefix.
global $wpdb;
if ($wpdb->prefix !== 'fkziptest_') { throw new RuntimeException('Refusing to use a non-test prefix.'); }
$table = $wpdb->prefix . 'funnelkit_leads';
$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
$phase = $args[0] ?? '';
if ($phase === 'active') {
    if (!$exists || get_option('funnelkit_funnels', null) === null || !str_contains(do_shortcode('[funnelkit id="1"]'), 'funnelkit_lead')) {
        throw new RuntimeException('ZIP activation, storage or shortcode assertion failed.');
    }
    echo "ZIP_ACTIVE_OK\n";
} elseif ($phase === 'removed') {
    if ($exists || get_option('funnelkit_funnels', null) !== null || get_option('funnelkit_last_id', null) !== null) {
        throw new RuntimeException('Uninstall did not remove its storage.');
    }
    echo "ZIP_UNINSTALL_OK\n";
} elseif ($phase === 'cleanup') {
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like('fkziptest_') . '%'));
    foreach ($tables as $name) {
        if (!preg_match('/^fkziptest_[a-zA-Z0-9_]+$/', $name)) { throw new RuntimeException('Unexpected table name.'); }
        if ($wpdb->query('DROP TABLE `' . $name . '`') === false) { throw new RuntimeException('Test cleanup failed.'); }
    }
    echo "ZIP_TEST_TABLES_REMOVED\n";
} else { throw new RuntimeException('Unknown lifecycle phase.'); }
