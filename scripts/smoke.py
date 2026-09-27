"""HTTP smoke against the disposable Compose demo. Creates one demo lead."""
import hashlib
import hmac
import http.cookiejar
import json
import os
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = os.environ.get('WP_URL', 'http://localhost:8080').rstrip('/')
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, data=None, headers=None, method=None):
    url = path if path.startswith('http') else BASE + path
    req = urllib.request.Request(url, data=data, headers=headers or {}, method=method)
    try:
        with client.open(req, timeout=30) as response:
            return response.status, response.read().decode(), response.url
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.url

class Inputs(HTMLParser):
    def __init__(self):
        super().__init__()
        self.values = {}
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'input' and values.get('name'):
            self.values[values['name']] = values.get('value', '')

def inputs(html):
    parser = Inputs()
    parser.feed(html)
    return parser.values

def form(path, data):
    return request(path, urllib.parse.urlencode(data).encode(), {'Content-Type': 'application/x-www-form-urlencoded'})

status, _, _ = request('/?rest_route=/funnelkit/v1/leads')
assert status == 401, ('Anonymous lead access', status)
request('/wp-login.php')
status, _, _ = form('/wp-login.php', {'log': os.environ.get('WP_USER', 'demo'), 'pwd': os.environ.get('WP_PASSWORD', 'local-demo-change-me'), 'wp-submit': 'Log In', 'testcookie': '1'})
assert status == 200
status, html, _ = request('/wp-admin/options-general.php?page=funnelkit')
assert status == 200 and 'funnelkit-admin' in html
config = json.loads(re.search(r'window\.funnelkit=(\{.*?\});', html).group(1))
assert 'build/index.js' in html
_, dashboard, _ = request('/wp-admin/index.php')
assert 'funnelkit-lite/build/index.js' not in dashboard
headers = {'X-WP-Nonce': config['nonce'], 'Content-Type': 'application/json'}

def api(route, data=None, method='GET'):
    status, body, _ = request('/?rest_route=' + route, json.dumps(data).encode() if data is not None else None, headers, method)
    return status, json.loads(body)

status, funnel = api('/funnelkit/v1/funnels', {'title': 'HTTP smoke funnel', 'copy_a': 'Smoke A', 'copy_b': 'Smoke B', 'cta': 'Try demo', 'ab_enabled': True}, 'POST')
assert status == 200
fid = funnel['id']
page_id = None
try:
    status, page = api('/wp/v2/pages', {'title': 'HTTP smoke funnel', 'status': 'publish', 'content': '[funnelkit id="%d"]' % fid}, 'POST')
    assert status == 201, page
    page_id = page['id']
    # Public flow must also work for a visitor without an admin session.
    jar.clear()
    status, html, _ = request(page['link'])
    assert status == 200 and 'Smoke ' in html
    fields = inputs(html)
    fields.update({'lead_name': 'Smoke Test', 'email': 'smoke@example.test', 'consent': '1'})
    status, checkout, checkout_url = form('/wp-admin/admin-post.php', fields)
    assert status == 200 and 'Simulate successful payment' in checkout, status
    token = urllib.parse.parse_qs(urllib.parse.urlparse(checkout_url).query)['token'][0]
    body = json.dumps({'type': 'mock.checkout.completed', 'token': token}).encode()
    timestamp = str(int(time.time()))
    signature = hmac.new(os.environ.get('FUNNELKIT_WEBHOOK_SECRET', 'local-demo-webhook-secret-change-me').encode(), timestamp.encode() + b'.' + body, hashlib.sha256).hexdigest()
    status, _, _ = request('/?rest_route=/funnelkit/v1/webhook', body, {'Content-Type': 'application/json'})
    assert status == 401
    # Test checkout POST first, then duplicate signed webhook delivery.
    status, confirmation, _ = form(checkout_url, inputs(checkout))
    assert status == 200 and 'Your demo payment is recorded' in confirmation
    for _ in range(2):
        status, _, _ = request('/?rest_route=/funnelkit/v1/webhook', body, {'Content-Type': 'application/json', 'X-FunnelKit-Timestamp': timestamp, 'X-FunnelKit-Signature': signature})
        assert status == 200
finally:
    request('/wp-login.php')
    form('/wp-login.php', {'log': os.environ.get('WP_USER', 'demo'), 'pwd': os.environ.get('WP_PASSWORD', 'local-demo-change-me'), 'wp-submit': 'Log In', 'testcookie': '1'})
    _, html, _ = request('/wp-admin/options-general.php?page=funnelkit')
    headers['X-WP-Nonce'] = json.loads(re.search(r'window\.funnelkit=(\{.*?\});', html).group(1))['nonce']
    if page_id:
        api('/wp/v2/pages/' + str(page_id) + '&force=true', method='DELETE')
    api('/funnelkit/v1/funnels/' + str(fid), method='DELETE')
print('PASS: activation/admin assets, REST permissions/CRUD, anonymous capture, mock checkout, signed webhook and duplicate delivery. One paid smoke lead retained.')
