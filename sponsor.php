<?php
header('Content-Type: application/json; charset=utf-8');
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require_once __DIR__ . '/public_data.php';
try {
    $ad = null;
    foreach ((publicData('settings.json')['ads'] ?? []) as $candidate) {
        if ((!$candidate['starts_at'] || (int)$candidate['starts_at'] <= time()) && (!$candidate['ends_at'] || (int)$candidate['ends_at'] > time())) { $ad = $candidate; break; }
    }
    publicJson(['ad' => $ad]);
} catch (Throwable $error) {
    error_log('WDHOTD sponsor: ' . $error->getMessage());
    echo json_encode(['ad' => null]);
}
