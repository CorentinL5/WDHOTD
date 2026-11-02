<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/web.php';
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
