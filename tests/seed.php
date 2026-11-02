<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require dirname(__DIR__) . '/config.php';
if ($dbname !== 'wdhotd_test') throw new RuntimeException('Fixture seeding is restricted to wdhotd_test.');
$schema = file_get_contents(dirname(__DIR__) . '/db_wdhotd.sql');
$schema = preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;/s', '', $schema);
$schema = preg_replace('/USE `wdhotd`\s*;/', '', $schema);
$db->exec($schema);
$insert = $db->prepare('INSERT INTO OTDItem (type, year, month, day, pageid, selected_title, event_text, extract_short, raw_json) VALUES (?, ?, 11, 2, ?, ?, ?, ?, ?)');
for ($i = 0; $i < 15; $i++) {
    $type = $i < 12 ? 'events' : ['births', 'deaths', 'holidays'][$i - 12];
    $year = $type === 'holidays' ? null : 1800 + $i;
    $text = 'Synthetic integration-test story ' . $i . '. This is not a historical claim.';
    $insert->execute([$type, $year, 900000 + $i, 'Test archive ' . $i, $text, 'Synthetic article context.', json_encode(['text' => $text, 'pages' => []])]);
}
echo "Created synthetic test stories without contacting Wikipedia.\n";
