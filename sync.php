<?php
/** Bounded scheduled job: refresh upcoming dates and slowly warm missing archives. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require __DIR__ . '/config.php';
require_once __DIR__ . '/import_service.php';
require_once __DIR__ . '/export_service.php';
$options = getopt('', ['days:', 'warm:']);
$days = max(1, min(14, (int)($options['days'] ?? 7)));
$warm = max(0, min(7, (int)($options['warm'] ?? 3)));
$locked = (int)$db->query("SELECT GET_LOCK('wdhotd:scheduled-sync', 0)")->fetchColumn();
if (!$locked) { echo "Another sync is already running.\n"; exit; }
$failures = 0;
try {
    $dates = [];
    for ($i = 0; $i < $days; $i++) {
        $date = new DateTimeImmutable('today +' . $i . ' days');
        $dates[$date->format('m-d')] = [(int)$date->format('n'), (int)$date->format('j')];
    }
    $existing = $db->query('SELECT DISTINCT month, day FROM OTDItem')->fetchAll();
    $known = [];
    foreach ($existing as $date) $known[sprintf('%02d-%02d', $date['month'], $date['day'])] = true;
    $warmed = 0;
    for ($date = new DateTimeImmutable('2000-01-01'); $date->format('Y') === '2000' && $warmed < $warm; $date = $date->modify('+1 day')) {
        $key = $date->format('m-d');
        if (!isset($known[$key]) && !isset($dates[$key])) { $dates[$key] = [(int)$date->format('n'), (int)$date->format('j')]; $warmed++; }
    }
    foreach ($dates as $key => [$month, $day]) {
        try { $result = importHistory($db, $month, $day); echo $key . ': ' . $result['status'] . "\n"; }
        catch (Throwable $error) { $failures++; fwrite(STDERR, $key . ': ' . $error->getMessage() . "\n"); }
    }
    echo 'Published ' . exportHistory($db) . " dates.\n";
} finally { $db->query("SELECT RELEASE_LOCK('wdhotd:scheduled-sync')"); }
exit($failures ? 1 : 0);
