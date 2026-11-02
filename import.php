<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require_once __DIR__ . '/history.php';
try {
    require __DIR__ . '/config.php';
    $options = getopt('', ['month:', 'day:', 'force']);
    [$month, $day] = historyDate($options);
    require_once __DIR__ . '/import_service.php';
    require_once __DIR__ . '/export_service.php';
    $result = importHistory($db, $month, $day, isset($options['force']));
    $result['published_dates'] = exportHistory($db);
    echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
