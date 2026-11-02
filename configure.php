<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require_once __DIR__ . '/public_data.php';
$arguments = getopt('', ['support-url:', 'source-url:', 'site-url:']);
foreach ($arguments as $key => $value) {
    if ($value !== '' && !publicSupportURL($value)) { fwrite(STDERR, "Use a valid HTTPS URL.\n"); exit(1); }
    if ($key === 'site-url' && $value !== '' && !publicSiteURL($value)) { fwrite(STDERR, "Use the real public HTTPS site URL, without a query or fragment.\n"); exit(1); }
}
require __DIR__ . '/config.php';
require_once __DIR__ . '/export_service.php';
$query = $db->prepare('INSERT INTO AppSetting (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach (['support-url' => 'support_url', 'source-url' => 'source_url', 'site-url' => 'site_url'] as $option => $key) if (isset($arguments[$option])) $query->execute([$key, $arguments[$option]]);
echo 'Published ' . exportHistory($db) . " dates and project settings.\n";
