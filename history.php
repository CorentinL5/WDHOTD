<?php
require_once __DIR__ . '/images.php';
/** Shared date/content rules. No connection or HTTP side effects. */
function historyDate(array $params): array {
    $month = filter_var($params['month'] ?? date('n'), FILTER_VALIDATE_INT);
    $day = filter_var($params['day'] ?? date('j'), FILTER_VALIDATE_INT);
    // A historical anniversary can be February 29 even during a non-leap year.
    if (!$month || !$day || !checkdate($month, $day, 2000)) {
        throw new InvalidArgumentException('This date does not exist.');
    }
    return [$month, $day];
}

function historyText(?string $text): string {
    return trim(html_entity_decode(strip_tags($text ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function historyItem(array $row): array {
    $raw = json_decode($row['raw_json'] ?? '', true) ?: [];
    $image = $raw['_image'] ?? historyImage($raw, isset($row['pageid']) ? (int)$row['pageid'] : null);
    return [
        'id' => (int)$row['id'], 'type' => $row['type'],
        'year' => $row['year'] === null ? null : (int)$row['year'],
        'selected_title' => historyText($row['selected_title'] ?? $row['canonical_title'] ?? 'A story worth discovering'),
        'event_text' => historyText($row['event_text'] ?? $raw['text'] ?? ''),
        'extract_short' => historyText($row['extract_short'] ?? ''),
        'thumbnail' => $image['src'] ?? null,
        'image' => $image,
        'wikipedia_url' => $row['wikipedia_url'] ?? null,
        'featured' => (bool)($row['featured'] ?? $raw['_featured'] ?? false),
    ];
}
