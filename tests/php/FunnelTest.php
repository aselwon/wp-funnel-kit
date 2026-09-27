<?php
use FunnelKit\Store;
use PHPUnit\Framework\TestCase;

final class FunnelTest extends TestCase {
    private int $funnel;
    private array $leadIds = [];
    protected function setUp(): void {
        $this->funnel = Store::save(array_merge(Store::defaults(), ['title' => 'Integration test']))['id'];
    }
    protected function tearDown(): void {
        global $wpdb;
        foreach ($this->leadIds as $id) { $wpdb->delete(Store::table(), ['id' => $id]); }
        Store::delete($this->funnel);
        wp_set_current_user(0);
    }
    private function lead(): string {
        $token = bin2hex(random_bytes(32));
        $this->leadIds[] = Store::lead($this->funnel, 'B', 'test@example.com', 'Test', $token);
        return $token;
    }
    public function testPaidIsIdempotentAndMetricsAreScoped(): void {
        $token = $this->lead(); $lead = Store::by_token($token);
        self::assertSame('pending', $lead['status']);
        self::assertTrue(Store::paid((int) $lead['id']));
        self::assertTrue(Store::paid((int) $lead['id']));
        self::assertSame('paid', Store::by_token($token)['status']);
        $rows = array_values(array_filter(Store::metrics(), fn($r) => (int) $r['funnel_id'] === $this->funnel));
        self::assertSame(1, (int) $rows[0]['paid']);
        self::assertSame(1, (int) $rows[0]['leads']);
    }
    public function testPrivateRoutesDenyAnonymousUsers(): void {
        wp_set_current_user(0);
        foreach (['funnels', 'leads', 'metrics'] as $path) {
            $response = rest_do_request(new WP_REST_Request('GET', '/funnelkit/v1/' . $path));
            self::assertSame(401, $response->get_status());
        }
    }
    public function testAdminCrudAndValidation(): void {
        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        self::assertNotEmpty($admins); wp_set_current_user($admins[0]->ID);
        $r = new WP_REST_Request('PUT', '/funnelkit/v1/funnels/' . $this->funnel);
        $r->set_header('content-type', 'application/json'); $r->set_body('{"title":"Updated","ab_enabled":false}');
        self::assertSame(200, rest_do_request($r)->get_status());
        self::assertSame('Updated', Store::get($this->funnel)['title']);
        $r->set_body('{"title":[]}');
        self::assertSame(400, rest_do_request($r)->get_status());
    }
    public function testWebhookRequiresSignatureAndIsReplaySafe(): void {
        $token = $this->lead();
        $r = new WP_REST_Request('POST', '/funnelkit/v1/webhook');
        $r->set_header('content-type', 'application/json');
        $body = wp_json_encode(['type' => 'mock.checkout.completed', 'token' => $token]); $r->set_body($body);
        self::assertSame(401, rest_do_request($r)->get_status());
        self::assertTrue(defined('FUNNELKIT_WEBHOOK_SECRET'));
        $time = (string) time();
        $r->set_header('x-funnelkit-timestamp', $time);
        $r->set_header('x-funnelkit-signature', hash_hmac('sha256', $time . '.' . $body, FUNNELKIT_WEBHOOK_SECRET));
        self::assertSame(200, rest_do_request($r)->get_status());
        self::assertSame(200, rest_do_request($r)->get_status());
        self::assertSame('paid', Store::by_token($token)['status']);
        $r->set_header('x-funnelkit-timestamp', (string) (time() - 1000));
        self::assertSame(401, rest_do_request($r)->get_status());
    }
    public function testShortcodeEscapesContentAndHasCaptureAction(): void {
        Store::save(array_merge(Store::get($this->funnel), ['title' => '<script>alert(1)</script>']), $this->funnel);
        $html = do_shortcode('[funnelkit id="' . $this->funnel . '"]');
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('funnelkit_lead', $html);
        self::assertStringContainsString('name="_wpnonce"', $html);
    }
}
