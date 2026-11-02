<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/history.php';
require_once dirname(__DIR__) . '/public_data.php';
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
if (!preg_match('~^https?://(127\.0\.0\.1|localhost|wdhotd\.test)(:\d+)?$~', $base)) throw new RuntimeException('Use a local development URL.');
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $label);
    $checks++;
    echo "PASS: $label\n";
}
function request(string $path, array $headers = [], ?array $payload = null): array {
    global $base;
    $responseHeaders = [];
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$responseHeaders) {
        if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $responseHeaders[strtolower(trim($key))] = trim($value); }
        return strlen($line);
    }]);
    if ($payload !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload)]);
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) throw new RuntimeException($error);
    return [$code, $body, json_decode($body, true), $responseHeaders];
}
check(historyDate(['month' => 2, 'day' => 29]) === [2, 29], 'Leap-day anniversaries are supported');
check(publicData('../../config.php') === null, 'Cache reads are limited to published filenames');
[$code, $html, , $headers] = request('/');
check($code === 200 && str_contains($html, 'lang="en"'), 'English home page responds');
check(($headers['x-content-type-options'] ?? '') === 'nosniff' && ($headers['x-frame-options'] ?? '') === 'DENY', 'Public pages return browser-protection headers');
check(str_contains($headers['content-security-policy'] ?? '', "script-src 'self'") && str_contains($headers['content-security-policy'] ?? '', "frame-ancestors 'none'"), 'Content policy restricts scripts and embedding');
check(str_contains($html, 'id="collectionTools"') && str_contains($html, 'id="importSaved"') && str_contains($html, 'id="exportSaved"'), 'Saved stories have explicit backup and recovery controls');
check(!str_contains($html, 'launchForm') && !str_contains($html, 'Premium') && !isset($headers['set-cookie']), 'Reading needs no account, launch form or session cookie');
check(str_contains($html, 'Contribution-only') && !str_contains($html, 'MIT licensed'), 'The site declares contribution-only source visibility');
$settings = publicData('settings.json') ?? [];
if (publicSupportURL($settings['support_url'] ?? null)) check(str_contains($html, htmlspecialchars($settings['support_url'], ENT_QUOTES, 'UTF-8')), 'The configured support link is rendered');
[$code, , $calendar] = request('/calendar.php');
check($code === 200 && !empty($calendar['dates']), 'The surprise calendar contains published dates');
[$code, , $first, $headers] = request('/feed.php?month=11&day=2&type=all&limit=7');
check($code === 200 && $first['success'] && count($first['data']) === 7 && $first['meta']['has_more'], 'Cached feed pagination works');
check(!empty($first['data'][0]['event_text']), 'The historical event is separate from article context');
[$code, $body] = request('/feed.php?month=11&day=2&type=all&limit=7', ['If-None-Match: ' . $headers['etag']]);
check($code === 304 && $body === '', 'Unchanged cached responses return HTTP 304');
[$code] = request('/feed.php?month=11&day=2&type=all&limit=7', ['If-None-Match: W/' . preg_replace('/^W\//', '', $headers['etag'])]);
check($code === 304, 'Weak validators reuse the same cached representation');
[$code] = request('/feed.php?month=11&day=2&type=events&limit=7', ['If-None-Match: ' . $headers['etag']]);
check($code === 200, 'Cache validators are isolated by category');
[$code, , $second] = request('/feed.php?month=11&day=2&type=all&limit=7&offset=7');
check($code === 200 && !array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')), 'Consecutive feed pages do not overlap');
[$code, , $limited] = request('/feed.php?month=11&day=2&limit=99999');
check($code === 200 && count($limited['data']) <= 100, 'The API caps response size');
[$code, , $invalid] = request('/feed.php?month=2&day=30');
check($code === 400 && !$invalid['success'], 'Invalid dates return HTTP 400');
[$code, , $stats] = request('/stats.php?month=11&day=2');
check($code === 200 && $stats['stats']['total'] === $first['meta']['total'], 'Statistics use the same published archive');
[$code, , $siteStats] = request('/stats.php?scope=site');
check($code === 200 && $siteStats['archive']['stats']['total'] > 0 && !isset($siteStats['audience']['ip']), 'Public project statistics expose aggregates only');
[$code] = request('/metrics.php', ['Origin: ' . $base, 'Content-Type: application/json'], ['events' => ['pageview', 'open-story']]);
check($code === 204, 'Small same-origin counter batches are accepted');
[$code, , $afterCount] = request('/stats.php?scope=site');
check($afterCount['audience']['totals']['pageview'] === $siteStats['audience']['totals']['pageview'] + 1, 'Accepted batches update the isolated aggregate store');
[$code] = request('/metrics.php', ['Origin: ' . $base, 'Content-Type: application/json', 'Sec-GPC: 1'], ['events' => ['pageview']]);
[$code, , $afterGPC] = request('/stats.php?scope=site');
check($afterGPC['audience']['totals']['pageview'] === $afterCount['audience']['totals']['pageview'], 'GPC requests do not increment counters');
[$code] = request('/metrics.php', ['Origin: ' . $base, 'Content-Type: application/json'], ['events' => ['private-custom-field']]);
check($code === 400, 'Only fixed aggregate events can be counted');
[$code] = request('/metrics.php', ['Origin: https://example.invalid', 'Content-Type: application/json'], ['events' => ['pageview']]);
check($code === 400, 'Cross-origin browser counter submissions are rejected');
[$code] = request('/metrics.php');
check($code === 405, 'Counters do not accept GET writes');
[$code, , $sponsor] = request('/sponsor.php');
check($code === 200 && ($sponsor['ad'] === null || $sponsor['ad']['destination'] !== '#membership'), 'The old Premium promotion is gone');
foreach (['import.php?force=1', 'sync.php', 'export.php', 'configure.php', 'config.php', 'migrate.php', 'refresh_images.php', 'newsletter.php'] as $path) {
    [$code] = request('/' . $path);
    check(in_array($code, [403, 404], true), 'No public write endpoint: ' . $path);
}
echo "$checks checks passed. No signup, import or payment was performed.\n";
