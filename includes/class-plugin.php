<?php
namespace FunnelKit;
defined('ABSPATH') || exit;

final class Plugin {
    public function boot(): void {
        add_action('rest_api_init', [$this, 'routes']);
        add_shortcode('funnelkit', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', static function () {
            $post = get_post();
            if ($post && has_shortcode($post->post_content, 'funnelkit')) {
                wp_enqueue_style('funnelkit-public', plugins_url('assets/public.css', FUNNELKIT_FILE), [], '1.0.0');
            }
        });
        add_action('admin_menu', static function () { add_options_page('FunnelKit Lite', 'FunnelKit', 'manage_options', 'funnelkit', static function () { echo '<div id="funnelkit-admin"></div>'; }); });
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        foreach (['admin_post_', 'admin_post_nopriv_'] as $prefix) {
            add_action($prefix . 'funnelkit_lead', [$this, 'capture']);
            add_action($prefix . 'funnelkit_checkout', [$this, 'checkout']);
        }
    }
    public function admin_assets(string $hook): void {
        if ($hook !== 'settings_page_funnelkit') { return; }
        $asset = require dirname(FUNNELKIT_FILE) . '/build/index.asset.php';
        wp_enqueue_script('funnelkit-admin', plugins_url('build/index.js', FUNNELKIT_FILE), $asset['dependencies'], $asset['version'], true);
        wp_enqueue_style('funnelkit-admin', plugins_url('build/index.css', FUNNELKIT_FILE), [], $asset['version']);
        wp_add_inline_script('funnelkit-admin', 'window.funnelkit=' . wp_json_encode(['root' => rest_url('funnelkit/v1/'), 'nonce' => wp_create_nonce('wp_rest')]) . ';', 'before');
    }
    public function routes(): void {
        $auth = static function () { return current_user_can('manage_options'); };
        register_rest_route('funnelkit/v1', '/funnels', [
            ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => static function () { return Store::all(); }],
            ['methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ($r) { return $this->save($r); }],
        ]);
        register_rest_route('funnelkit/v1', '/funnels/(?P<id>\d+)', [
            ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => static function ($r) { return Store::get((int) $r['id']) ?? new \WP_Error('not_found', 'Funnel not found.', ['status' => 404]); }],
            ['methods' => 'PUT', 'permission_callback' => $auth, 'callback' => function ($r) { return $this->save($r); }],
            ['methods' => 'DELETE', 'permission_callback' => $auth, 'callback' => static function ($r) { Store::delete((int) $r['id']); return ['deleted' => true]; }],
        ]);
        register_rest_route('funnelkit/v1', '/leads', ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => static function ($r) { return Store::leads(max(1, min(100000, (int) $r->get_param('page')))); }]);
        register_rest_route('funnelkit/v1', '/metrics', ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => [Store::class, 'metrics']]);
        register_rest_route('funnelkit/v1', '/webhook', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'webhook']]);
    }
    private function save(\WP_REST_Request $r) {
        $id = (int) $r->get_param('id');
        if ($id && !Store::get($id)) { return new \WP_Error('not_found', 'Funnel not found.', ['status' => 404]); }
        $data = $r->get_json_params();
        if (!is_array($data)) { return new \WP_Error('invalid', 'JSON object required.', ['status' => 400]); }
        foreach (['title', 'copy_a', 'copy_b', 'cta', 'price_id', 'thank_you'] as $field) {
            if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > 10000)) { return new \WP_Error('invalid', 'Invalid text field.', ['status' => 400]); }
        }
        if (isset($data['ab_enabled']) && !is_bool($data['ab_enabled'])) { return new \WP_Error('invalid', 'A/B must be boolean.', ['status' => 400]); }
        $data = array_merge($id ? Store::get($id) : Store::defaults(), $data);
        if (!trim($data['title']) || !trim($data['cta'])) { return new \WP_Error('invalid', 'Title and CTA are required.', ['status' => 400]); }
        if ($data['thank_you'] && (!in_array(wp_parse_url($data['thank_you'], PHP_URL_SCHEME), ['http', 'https'], true) || wp_parse_url($data['thank_you'], PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST) || wp_parse_url($data['thank_you'], PHP_URL_USER))) { return new \WP_Error('invalid', 'Use a valid thank-you URL on this site.', ['status' => 400]); }
        return Store::save($data, $id);
    }
    public function shortcode($attributes): string {
        $attrs = shortcode_atts(['id' => 1], $attributes);
        $f = Store::get((int) $attrs['id']);
        if (!$f) { return ''; }
        $v = $f['ab_enabled'] && wp_rand(0, 1) ? 'B' : 'A';
        wp_enqueue_style('funnelkit-public', plugins_url('assets/public.css', FUNNELKIT_FILE), [], '1.0.0');
        ob_start(); ?>
        <section class="funnelkit"><p class="funnelkit-label">FunnelKit Lite · Demo</p><h2><?php echo esc_html($f['title']); ?></h2>
        <p><?php echo nl2br(esc_html($f[$v === 'A' ? 'copy_a' : 'copy_b'])); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="funnelkit_lead"><input type="hidden" name="funnel_id" value="<?php echo (int) $f['id']; ?>"><input type="hidden" name="variant" value="<?php echo esc_attr($v); ?>">
            <?php wp_nonce_field('funnelkit_lead_' . $f['id']); ?>
            <label>Your name <input name="lead_name" maxlength="120" autocomplete="name" required></label>
            <label>Email address <input type="email" name="email" maxlength="254" autocomplete="email" required></label>
            <label><input type="checkbox" name="consent" value="1" required> I agree to store my details for this demo.</label>
            <button type="submit"><?php echo esc_html($f['cta']); ?></button><small>Mock checkout. No card or real payment required.</small>
        </form></section>
        <?php return ob_get_clean();
    }
    private function post(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : ''; }
    public function capture(): void {
        $id = absint($this->post('funnel_id')); $f = Store::get($id);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$f || !wp_verify_nonce($this->post('_wpnonce'), 'funnelkit_lead_' . $id)) { wp_die('Invalid or expired form. Reload the funnel page.', '', ['response' => 403]); }
        $email = sanitize_email($this->post('email')); $name = sanitize_text_field($this->post('lead_name'));
        if (!is_email($email) || strlen($email) > 254 || !$name || strlen($name) > 120 || $this->post('consent') !== '1') { wp_die('A valid name, email and consent are required.', '', ['response' => 400]); }
        $token = bin2hex(random_bytes(32));
        $variant = $f['ab_enabled'] && $this->post('variant') === 'B' ? 'B' : 'A';
        if (!Store::lead($id, $variant, $email, $name, $token)) { wp_die('Unable to save lead. Please retry.', '', ['response' => 500]); }
        wp_safe_redirect(add_query_arg(['action' => 'funnelkit_checkout', 'token' => $token], admin_url('admin-post.php')), 303); exit;
    }
    public function checkout(): void {
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex');
        $token = isset($_GET['token']) && is_string($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $lead = preg_match('/^[a-f0-9]{64}$/', $token) ? Store::by_token($token) : null;
        if (!$lead) { wp_die('Checkout not found.', '', ['response' => 404]); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($this->post('_wpnonce'), 'funnelkit_pay_' . $lead['id'])) { wp_die('Expired checkout. Reload and retry.', '', ['response' => 403]); }
            if (!Store::paid((int) $lead['id'])) { wp_die('Payment could not be recorded.', '', ['response' => 500]); }
            $f = Store::get((int) $lead['funnel_id']);
            $url = $f['thank_you'] ?? '';
            wp_safe_redirect($url ?: add_query_arg(['action' => 'funnelkit_checkout', 'token' => $token], admin_url('admin-post.php')), 303); exit;
        }
        $paid = $lead['status'] === 'paid';
        $html = '<h1>' . ($paid ? 'Thank you!' : 'Mock checkout') . '</h1><p>' . ($paid ? 'Your demo payment is recorded.' : 'This is a simulation. No money will be charged.') . '</p>';
        if (!$paid) { $html .= '<form method="post">' . wp_nonce_field('funnelkit_pay_' . $lead['id'], '_wpnonce', false, false) . '<button type="submit">Simulate successful payment</button></form>'; }
        $html .= '<p><a href="' . esc_url(home_url('/')) . '">Return to site</a></p>';
        wp_die($html, 'FunnelKit Lite', ['response' => 200]);
    }
    public function webhook(\WP_REST_Request $r) {
        $secret = defined('FUNNELKIT_WEBHOOK_SECRET') ? FUNNELKIT_WEBHOOK_SECRET : '';
        $timestamp = (string) $r->get_header('x_funnelkit_timestamp');
        $signature = (string) $r->get_header('x_funnelkit_signature');
        if (!$secret || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || !hash_equals(hash_hmac('sha256', $timestamp . '.' . $r->get_body(), $secret), $signature)) { return new \WP_Error('signature', 'Invalid signature.', ['status' => 401]); }
        $data = $r->get_json_params();
        if (!is_array($data) || ($data['type'] ?? '') !== 'mock.checkout.completed' || !is_string($data['token'] ?? null)) { return new \WP_Error('event', 'Invalid event.', ['status' => 400]); }
        $lead = Store::by_token($data['token']);
        if (!$lead) { return new \WP_Error('lead', 'Lead not found.', ['status' => 404]); }
        if (!Store::paid((int) $lead['id'])) { return new \WP_Error('storage', 'Storage error.', ['status' => 500]); }
        return ['received' => true];
    }
}
