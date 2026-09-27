import { apiUrl } from './api-url';
test('plain WordPress permalinks preserve the REST route when paginating', () => {
    const url = new URL(apiUrl('http://localhost:8080/index.php?rest_route=/funnelkit/v1/', 'leads?page=2'));
    expect(url.searchParams.get('rest_route')).toBe('/funnelkit/v1/leads');
    expect(url.searchParams.get('page')).toBe('2');
});
test('pretty WordPress permalinks support pagination', () => {
    expect(apiUrl('https://example.test/wp-json/funnelkit/v1/', 'leads?page=2')).toBe('https://example.test/wp-json/funnelkit/v1/leads?page=2');
});
