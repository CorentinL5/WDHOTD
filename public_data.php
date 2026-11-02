<?php
require_once __DIR__ . '/web.php';
webHeaders();
/** Public requests only read generated files. Database access belongs to CLI jobs. */
function publicData(string $name): ?array {
    if (!preg_match('/^(?:\d{2}-\d{2}|calendar|settings|archive-stats)\.json$/D', $name)) return null;
    $path = __DIR__ . '/data-cache/' . $name;
    if (!is_file($path) || filesize($path) > 8 * 1024 * 1024) return null;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function publicCacheTag(string $name, string $variant): ?string {
    if (!preg_match('/^(?:\d{2}-\d{2}|calendar|settings)\.json$/D', $name)) return null;
    $path = __DIR__ . '/data-cache/' . $name;
    if (!is_file($path)) return null;
    // Hash bytes rather than mtime/size: same-second exports can have equal lengths.
    // A 304 still avoids decoding, filtering and re-encoding the archive.
    $hash = hash_file('sha256', $path);
    return $hash === false ? null : 'W/"' . hash('sha256', $name . '|' . $variant . '|' . $hash) . '"';
}
function publicNotModified(?string $etag, int $ttl = 300): bool {
    if ($etag === null) return false;
    header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=60');
    header('ETag: ' . $etag);
    $tags = array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    foreach ($tags as $tag) if ($tag === '*' || preg_replace('/^W\//', '', $tag) === preg_replace('/^W\//', '', $etag)) { http_response_code(304); return true; }
    return false;
}
function publicJson(array $data, int $ttl = 300, ?string $etag = null): void {
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    // Weak validators describe the same JSON across identity/gzip encodings.
    $etag = $etag ?? 'W/"' . hash('sha256', $body) . '"';
    header('Content-Type: application/json; charset=utf-8');
    if (publicNotModified($etag, $ttl)) return;
    echo $body;
}

function publicSupportURL(?string $url): ?string {
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') return null;
    if (parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)) return null;
    return $url;
}
