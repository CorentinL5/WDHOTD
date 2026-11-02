<?php
/** Bounded aggregate counters, separate from MySQL and saved collections. */
const METRIC_EVENTS = ['pageview', 'open-story', 'save-story', 'view-timeline', 'surprise', 'choose-date', 'support', 'source'];
function metricsDirectory(): ?string {
    $configured = getenv('WDHOTD_METRICS_DIR');
    if ($configured !== false && $configured !== '') {
        $parent = realpath(dirname($configured));
        $root = realpath(__DIR__);
        if (!$parent || !$root) return null;
        $target = $parent . DIRECTORY_SEPARATOR . basename($configured);
        // A production counter store must survive releases and stay outside the web root.
        if (publicSiteURL(publicData('settings.json')['site_url'] ?? null) && (strtolower($parent) === strtolower($root) || str_starts_with(strtolower($parent), strtolower($root) . DIRECTORY_SEPARATOR))) return null;
        return $target;
    }
    return publicSiteURL(publicData('settings.json')['site_url'] ?? null) ? null : __DIR__ . '/.metrics';
}
function metricsRecord(array $events, string $directory): bool {
    if (!$events || count($events) > count(METRIC_EVENTS) || array_diff($events, METRIC_EVENTS)) throw new InvalidArgumentException('Unknown counter event.');
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    $file = fopen($directory . '/counters.json', 'c+b');
    if (!$file) return false;
    try {
        // Under contention, discard a best-effort count rather than delay reading.
        if (!flock($file, LOCK_EX | LOCK_NB)) return false;
        if (fstat($file)['size'] > 65536) return false;
        $raw = stream_get_contents($file);
        $days = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($days)) return false;
        $today = gmdate('Y-m-d');
        $oldest = gmdate('Y-m-d', time() - 89 * 86400);
        foreach ($days as $date => $counts) if ($date < $oldest || $date > $today) unset($days[$date]);
        foreach (array_unique($events) as $event) $days[$today][$event] = min(1000000000, (int)($days[$today][$event] ?? 0) + 1);
        $json = json_encode($days, JSON_THROW_ON_ERROR);
        if (strlen($json) > 65536) return false;
        rewind($file);
        if (fwrite($file, $json) !== strlen($json)) return false;
        ftruncate($file, strlen($json));
        fflush($file);
        return true;
    } finally {flock($file, LOCK_UN);fclose($file);}
}
function metricsSnapshot(?string $directory): array {
    $result = ['enabled' => $directory !== null, 'period_days' => 90, 'totals' => array_fill_keys(METRIC_EVENTS, 0), 'days' => []];
    if ($directory === null || !is_file($directory . '/counters.json')) return $result;
    $file = fopen($directory . '/counters.json', 'rb');
    if (!$file) {$result['enabled'] = false;return $result;}
    try {
        if (!flock($file, LOCK_SH | LOCK_NB) || fstat($file)['size'] > 65536) {$result['enabled'] = false;return $result;}
        $days = json_decode(stream_get_contents($file), true);
        if (!is_array($days)) {$result['enabled'] = false;return $result;}
        $oldest = gmdate('Y-m-d', time() - 89 * 86400);
        ksort($days);
        foreach ($days as $date => $counts) {
            if ($date < $oldest || $date > gmdate('Y-m-d') || !is_array($counts)) continue;
            $public = [];
            foreach (METRIC_EVENTS as $event) {$public[$event] = max(0, min(1000000000, (int)($counts[$event] ?? 0)));$result['totals'][$event] += $public[$event];}
            $result['days'][] = ['date' => $date, 'counts' => $public];
        }
        return $result;
    } finally {flock($file, LOCK_UN);fclose($file);}
}
