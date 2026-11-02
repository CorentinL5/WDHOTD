<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require __DIR__ . '/config.php';
require_once __DIR__ . '/export_service.php';
echo 'Published ' . exportHistory($db) . " dates from MySQL.\n";
