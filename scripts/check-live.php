<?php
/** Small, read-only post-deployment check. Never disables TLS verification. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/web.php';
$site = publicSiteURL($argv[1] ?? null);
if (!$site) { fwrite(STDERR, "Pass the real public HTTPS site URL.\n"); exit(1); }
$failures = [];
function probe(string $path): array {
    global $site;
    $headers = []; $body = '';
    $curl = curl_init($site . $path);
    curl_setopt_array($curl, [CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'WDHOTD-deployment-check/1.0', CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$headers) {
        if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
        return strlen($line);
    }, CURLOPT_WRITEFUNCTION => static function ($curl, $chunk) use (&$body) { if (strlen($body) + strlen($chunk) > 1024 * 1024) return 0; $body .= $chunk; return strlen($chunk); }]);
    $ok = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_error($curl); curl_close($curl);
    if ($ok === false) throw new RuntimeException('Request failed without bypassing TLS verification: ' . $error);
    return [$code, $headers, $body];
}
function expectLive(bool $ok, string $label): void { global $failures; echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n"; if (!$ok) $failures[] = $label; }
try {
    [$code, $headers, $html] = probe('/');
    expectLive($code === 200, 'HTTPS homepage responds with a valid certificate');
    expectLive(($headers['x-content-type-options'] ?? '') === 'nosniff', 'MIME sniffing protection');
    expectLive(($headers['x-frame-options'] ?? '') === 'DENY', 'Page embedding is blocked');
    expectLive(str_contains($headers['content-security-policy'] ?? '', "script-src 'self'") && str_contains($headers['content-security-policy'] ?? '', "frame-ancestors 'none'"), 'Content security policy');
    expectLive(isset($headers['strict-transport-security']), 'HSTS on HTTPS');
    expectLive(!str_contains($html, 'content="noindex') && str_contains($html, 'rel="canonical"'), 'Production canonical URL and indexing policy');
    foreach (['/.env', '/.git/config', '/config.php', '/sync.php', '/tests/', '/data-cache/'] as $path) {
        [$code] = probe($path); expectLive(in_array($code, [403, 404], true), 'Private path blocked: ' . $path);
    }
    [$code, , $robots] = probe('/robots.txt'); expectLive($code === 200 && str_contains($robots, 'Sitemap: ' . $site . '/sitemap.xml'), 'Public robots.txt points to the sitemap');
    [$code, , $xml] = probe('/sitemap.xml'); expectLive($code === 200 && str_contains($xml, '<urlset'), 'Published sitemap');
} catch (Throwable $error) { $failures[] = $error->getMessage(); fwrite(STDERR, $error->getMessage() . "\n"); }
echo $failures ? "Deployment still needs attention.\n" : "Read-only deployment checks passed. This is not a comprehensive security audit.\n";
exit($failures ? 1 : 0);
