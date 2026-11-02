<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/history.php';

function writePublicData(string $name, array $data): void {
    $directory = __DIR__ . '/data-cache';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Cannot create the public cache.');
    $temporary = tempnam($directory, '.export-');
    try {
        if (file_put_contents($temporary, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $directory . '/' . $name)) throw new RuntimeException('Cannot publish ' . $name);
    } finally { if (is_file($temporary)) unlink($temporary); }
}

function exportHistory(PDO $db): int {
    $dates = $db->query('SELECT DISTINCT month, day FROM OTDItem ORDER BY month, day')->fetchAll();
    $query = $db->prepare('SELECT * FROM OTDItem WHERE month = ? AND day = ? ORDER BY year DESC, id DESC');
    $calendar = [];
    $totals = ['events' => 0, 'births' => 0, 'deaths' => 0, 'holidays' => 0, 'total' => 0];
    $earliest = null; $latest = null;
    foreach ($dates as $date) {
        $query->execute([$date['month'], $date['day']]);
        $items = [];
        $stats = ['events' => 0, 'births' => 0, 'deaths' => 0, 'holidays' => 0, 'total' => 0];
        $min = null; $max = null;
        foreach ($query as $row) {
            $item = historyItem($row);
            $items[] = $item;
            $stats[$item['type']]++;
            $stats['total']++;
            if ($item['year'] !== null) { $min = $min === null ? $item['year'] : min($min, $item['year']); $max = $max === null ? $item['year'] : max($max, $item['year']); }
        }
        $day = ['month' => (int)$date['month'], 'day' => (int)$date['day']];
        writePublicData(sprintf('%02d-%02d.json', $day['month'], $day['day']), ['date' => $day, 'data' => $items, 'stats' => $stats, 'yearRange' => ['min' => $min, 'max' => $max]]);
        $calendar[] = $day;
        foreach ($stats as $type => $count) $totals[$type] += $count;
        if ($min !== null) $earliest = $earliest === null ? $min : min($earliest, $min);
        if ($max !== null) $latest = $latest === null ? $max : max($latest, $max);
    }
    writePublicData('calendar.json', ['dates' => $calendar]);
    writePublicData('archive-stats.json', ['dates' => count($calendar), 'stats' => $totals, 'yearRange' => ['min' => $earliest, 'max' => $latest], 'updated_at' => gmdate('c')]);
    $settings = $db->query('SELECT setting_key, setting_value FROM AppSetting')->fetchAll(PDO::FETCH_KEY_PAIR);
    // Export an explicit allowlist, never database credentials or private settings.
    $ads = $db->query("SELECT kind, title, body, destination, link_label, UNIX_TIMESTAMP(starts_at) AS starts_at, UNIX_TIMESTAMP(ends_at) AS ends_at FROM Advertisement WHERE active = 1 AND slug <> 'premium-launch' ORDER BY priority DESC, id DESC LIMIT 20")->fetchAll();
    writePublicData('settings.json', ['support_url' => $settings['support_url'] ?? '', 'source_url' => $settings['source_url'] ?? '', 'site_url' => $settings['site_url'] ?? '', 'ads' => $ads]);
    return count($calendar);
}
