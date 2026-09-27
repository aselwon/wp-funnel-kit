export function apiUrl(root: string, path: string): string {
    const [route, query = ''] = path.split('?');
    const url = new URL(root);
    if (url.searchParams.has('rest_route')) {
        url.searchParams.set('rest_route', url.searchParams.get('rest_route') + route);
    } else {
        url.pathname += route;
    }
    new URLSearchParams(query).forEach((value, key) => url.searchParams.set(key, value));
    return url.toString();
}
