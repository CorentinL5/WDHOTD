<?php
/** Check local release inputs without connecting to MySQL or an external host. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/public_data.php';
require_once __DIR__ . '/release_checks.php';
$settings = publicData('settings.json') ?? [];
$report = releaseChecks(publicSiteURL($settings['site_url'] ?? null), publicData('calendar.json')['dates'] ?? [], 'publicData', new DateTimeImmutable('today', new DateTimeZone('Europe/Brussels')));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($report['ready'] ? 0 : 1);
