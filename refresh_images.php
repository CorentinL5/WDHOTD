<?php
/** Rebuild saved image choices from existing source data. No reimport is needed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require_once __DIR__ . '/history.php';
require __DIR__ . '/config.php';
$rows = $db->query('SELECT id, pageid, raw_json FROM OTDItem');
$update = $db->prepare('UPDATE OTDItem SET thumbnail = ?, raw_json = ? WHERE id = ?');
$count = 0;
$db->beginTransaction();
try {
    foreach ($rows as $row) {
        $entry = json_decode($row['raw_json'] ?? '', true);
        if (!is_array($entry)) continue;
        $entry['_image'] = historyImage($entry, $row['pageid'] === null ? null : (int)$row['pageid']);
        $update->execute([$entry['_image']['src'] ?? null, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $row['id']]);
        $count++;
    }
    $db->commit();
    echo "Updated image metadata for $count stories.\n";
} catch (Throwable $error) { $db->rollBack(); throw $error; }
