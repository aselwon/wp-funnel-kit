<?php
namespace FunnelKit;
defined('ABSPATH') || exit;

final class Store {
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'funnelkit_leads'; }
    public static function activate(bool $network_wide = false): void {
        if ($network_wide) { wp_die('Activate FunnelKit Lite separately on each site. Network activation is not supported.'); }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            funnel_id bigint(20) unsigned NOT NULL,
            variant varchar(1) NOT NULL,
            email varchar(254) NOT NULL,
            name varchar(120) NOT NULL,
            status varchar(12) NOT NULL DEFAULT 'pending',
            token_hash varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            paid_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY funnel_id (funnel_id),
            UNIQUE KEY token_hash (token_hash)
        ) {$wpdb->get_charset_collate()};");
        if (false === get_option('funnelkit_funnels', false)) {
            add_option('funnelkit_funnels', [1 => self::defaults()], '', false);
        }
    }
    public static function defaults(): array {
        return ['id' => 1, 'title' => 'Launch your next big idea', 'copy_a' => 'Get our practical launch playbook and turn your idea into action.', 'copy_b' => 'Ready to launch? Your next chapter starts with one small step.', 'cta' => 'Get the playbook', 'ab_enabled' => true, 'price_id' => '', 'thank_you' => ''];
    }
    public static function all(): array { return array_values(get_option('funnelkit_funnels', [])); }
    public static function get(int $id): ?array {
        $items = get_option('funnelkit_funnels', []);
        return $items[$id] ?? null;
    }
    public static function save(array $data, int $id = 0): array {
        $items = get_option('funnelkit_funnels', []);
        if (!$id) { $id = max((int) get_option('funnelkit_last_id', 1), ...array_merge([0], array_keys($items))) + 1; update_option('funnelkit_last_id', $id, false); }
        $item = ['id' => $id, 'title' => sanitize_text_field($data['title'] ?? ''), 'copy_a' => sanitize_textarea_field($data['copy_a'] ?? ''), 'copy_b' => sanitize_textarea_field($data['copy_b'] ?? ''), 'cta' => sanitize_text_field($data['cta'] ?? 'Continue'), 'ab_enabled' => !empty($data['ab_enabled']), 'price_id' => sanitize_text_field($data['price_id'] ?? ''), 'thank_you' => esc_url_raw($data['thank_you'] ?? '')];
        $items[$id] = $item;
        update_option('funnelkit_funnels', $items, false);
        return $item;
    }
    public static function delete(int $id): void {
        $items = get_option('funnelkit_funnels', []); unset($items[$id]); update_option('funnelkit_funnels', $items, false);
    }
    public static function lead(int $funnel, string $variant, string $email, string $name, string $token): int {
        global $wpdb;
        $ok = $wpdb->insert(self::table(), ['funnel_id' => $funnel, 'variant' => $variant, 'email' => $email, 'name' => $name, 'status' => 'pending', 'token_hash' => hash('sha256', $token), 'created_at' => current_time('mysql', true)]);
        return $ok ? (int) $wpdb->insert_id : 0;
    }
    public static function by_token(string $token): ?array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE token_hash = %s', hash('sha256', $token)), ARRAY_A);
    }
    public static function paid(int $id): bool {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . " SET status = 'paid', paid_at = %s WHERE id = %d AND status = 'pending'", current_time('mysql', true), $id));
        return $result !== false;
    }
    public static function leads(int $page): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT id, funnel_id, variant, email, name, status, created_at, paid_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT 50 OFFSET %d', ($page - 1) * 50), ARRAY_A);
    }
    public static function metrics(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT funnel_id, variant, COUNT(*) AS leads, SUM(status = \'paid\') AS paid FROM ' . self::table() . ' GROUP BY funnel_id, variant', ARRAY_A);
    }
}
