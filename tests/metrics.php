<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/metrics_service.php';
$root = dirname(__DIR__) . '/tmp';
if (!is_dir($root)) mkdir($root, 0700, true);
$directory = $root . '/counter-test-' . bin2hex(random_bytes(6));
try {
    if (!metricsRecord(['pageview', 'open-story', 'open-story'], $directory)) throw new RuntimeException('Cannot write fixture counters.');
    $snapshot = metricsSnapshot($directory);
    if ($snapshot['totals']['pageview'] !== 1 || $snapshot['totals']['open-story'] !== 1) throw new RuntimeException('A batch must deduplicate events.');
    $raw = file_get_contents($directory . '/counters.json');
    if (count(json_decode($raw, true)) !== 1 || str_contains($raw, 'ip') || str_contains($raw, 'user')) throw new RuntimeException('Only daily aggregate fixtures should be retained.');
    try {metricsRecord(['arbitrary-user-controlled-name'], $directory);throw new RuntimeException('Unknown event was accepted.');} catch (InvalidArgumentException $expected) {}
    $days = json_decode($raw, true);$days['2000-01-01'] = ['pageview' => 500];
    file_put_contents($directory . '/counters.json', json_encode($days));
    metricsRecord(['pageview'], $directory);
    $snapshot = metricsSnapshot($directory);
    if ($snapshot['totals']['pageview'] !== 2 || count($snapshot['days']) !== 1) throw new RuntimeException('Expired aggregates must be discarded.');
    if (metricsSnapshot(null)['enabled']) throw new RuntimeException('Unavailable storage must not report enabled counters.');
    echo "PASS: aggregate counters deduplicate batches, reject arbitrary events and retain at most 90 days.\n";
} finally {
    // This path is a new fixture directory under the repository's ignored tmp/ root.
    if (realpath(dirname($directory)) !== realpath($root)) throw new RuntimeException('Unexpected fixture cleanup path.');
    if (is_file($directory . '/counters.json')) unlink($directory . '/counters.json');
    if (is_dir($directory)) rmdir($directory);
}
