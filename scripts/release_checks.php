<?php
/** Read-only release checks shared by the CLI report and production packager. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function releaseChecks(?string $siteURL, array $calendar, callable $readArchive, DateTimeImmutable $today): array {
    $errors = [];
    $warnings = [];
    if (!$siteURL) $errors[] = 'Configure a real public HTTPS site URL before publishing.';
    $available = [];
    foreach ($calendar as $date) {
        $month = filter_var($date['month'] ?? null, FILTER_VALIDATE_INT);
        $day = filter_var($date['day'] ?? null, FILTER_VALIDATE_INT);
        if (!$month || !$day || !checkdate($month, $day, 2000)) {
            $errors[] = 'The published calendar contains an invalid date.';
            continue;
        }
        $name = sprintf('%02d-%02d.json', $month, $day);
        $archive = $readArchive($name);
        if (!$archive || empty($archive['data']) || !is_array($archive['data']) || !isset($archive['stats'], $archive['yearRange']) || (int)($archive['date']['month'] ?? 0) !== $month || (int)($archive['date']['day'] ?? 0) !== $day) {
            $errors[] = 'Missing, empty or inconsistent archive: ' . $name;
            continue;
        }
        $available[sprintf('%02d-%02d', $month, $day)] = true;
    }
    if (!isset($available[$today->format('m-d')])) $errors[] = 'Today has no published stories. Run the private sync/export job.';
    for ($offset = 1; $offset < 7; $offset++) {
        $next = $today->modify('+' . $offset . ' days');
        // Archives are anniversaries and can cross the end of a calendar year.
        $key = $next->format('m-d');
        if (!isset($available[$key])) $warnings[] = 'No archive for ' . $next->format('j F') . '. Refresh before that date.';
    }
    return ['ready' => !$errors, 'published_dates' => count($available), 'errors' => array_values(array_unique($errors)), 'warnings' => $warnings];
}
