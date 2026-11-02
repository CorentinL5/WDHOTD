<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/web.php';
require_once dirname(__DIR__) . '/scripts/release_checks.php';
$releaseDate = new DateTimeImmutable('2026-12-31', new DateTimeZone('Europe/Brussels'));
$releaseCalendar = [['month' => 12, 'day' => 31], ['month' => 1, 'day' => 1]];
$releaseArchives = [
    '12-31.json' => ['date' => $releaseCalendar[0], 'data' => [['id' => 1]], 'stats' => [], 'yearRange' => []],
    '01-01.json' => ['date' => $releaseCalendar[1], 'data' => [['id' => 2]], 'stats' => [], 'yearRange' => []],
];
$releaseReader = static fn($name) => $releaseArchives[$name] ?? null;
$ready = releaseChecks('https://history.org', $releaseCalendar, $releaseReader, $releaseDate);
if (!$ready['ready'] || count($ready['warnings']) !== 5) throw new RuntimeException('Valid anniversary archives must work across New Year.');
if (releaseChecks(null, $releaseCalendar, $releaseReader, $releaseDate)['ready']) throw new RuntimeException('A public release must have a configured domain.');
if (releaseChecks('https://history.org', [$releaseCalendar[1]], $releaseReader, $releaseDate)['ready']) throw new RuntimeException('A public release must have stories for today.');
if (releaseChecks('https://history.org', $releaseCalendar, static fn($name) => $releaseArchives['01-01.json'], $releaseDate)['ready']) throw new RuntimeException('Mismatched archive dates must block publication.');
echo "PASS: release readiness blocks missing domains, missing daily content and mismatched archives.\n";
foreach (['https://localhost', 'https://wdhotd.test', 'https://127.0.0.1', 'https://192.168.1.1', 'http://history.org', 'https://user:pass@history.org', 'https://history.org/?bad=1'] as $url) {
    if (publicSiteURL($url) !== null) throw new RuntimeException('A nonpublic or invalid canonical URL was accepted.');
}
if (publicSiteURL('https://history.org/app/') !== 'https://history.org/app') throw new RuntimeException('A public canonical URL was rejected.');
$pipes = [];
$process = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/package.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) throw new RuntimeException('Package failed: ' . $errors);
$result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
$root = $result['public_directory'];
$manifest = json_decode(file_get_contents($result['manifest']), true, 512, JSON_THROW_ON_ERROR);
foreach (['.env', '.git', 'config.php', 'import.php', 'sync.php', 'configure.php', 'db_wdhotd.sql', 'scripts', 'tests', 'tmp', '.github'] as $private) {
    if (file_exists($root . '/' . $private)) throw new RuntimeException('A private file entered the public release: ' . $private);
}
foreach ($manifest['files'] as $file => $hash) {
    if (!hash_equals($hash, hash_file('sha256', $root . '/' . $file))) throw new RuntimeException('Release integrity check failed.');
}
if (!str_contains(file_get_contents($root . '/robots.txt'), 'Disallow: /')) throw new RuntimeException('Preview release must not be indexed.');
if (!file_exists($root . '/assets/js/collection.js') || !file_exists($root . '/web.php')) throw new RuntimeException('A required public asset is missing.');
echo 'PASS: canonical URL validation, private-file exclusion, release hashes and preview indexing policy.' . "\n";
echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
