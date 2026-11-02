<?php
require_once __DIR__ . '/web.php';
webHeaders();
/** Public requests only read generated files. Database access belongs to CLI jobs. */
function publicData(string $name): ?array {
    if (!preg_match('/^(?:\d{2}-\d{2}|calendar|settings)\.json$/D', $name)) return null;
    $path = __DIR__ . '/data-cache/' . $name;
    if (!is_file($path) || filesize($path) > 8 * 1024 * 1024) return null;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function publicJson(array $data, int $ttl = 300): void {
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $etag = '"' . hash('sha256', $body) . '"';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=60');
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
    echo $body;
}

function publicSupportURL(?string $url): ?string {
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') return null;
    if (parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)) return null;
    return $url;
}
