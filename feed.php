<?php
header('Content-Type: application/json; charset=utf-8');
define('JSON_RESPONSE', true);
define('SKIP_LOG', true);
require_once __DIR__ . '/history.php';
require_once __DIR__ . '/public_data.php';

try {
    [$month, $day] = historyDate($_GET);
    $type = $_GET['type'] ?? 'all';
    if (!in_array($type, ['all', 'events', 'births', 'deaths', 'holidays'], true)) throw new InvalidArgumentException('Unknown story category.');
    $offset = max(0, min(10000, (int)($_GET['offset'] ?? 0)));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $cache = publicData(sprintf('%02d-%02d.json', $month, $day));
    $items = $cache['data'] ?? [];
    if ($type !== 'all') $items = array_values(array_filter($items, static fn($item) => $item['type'] === $type));
    $rows = array_slice($items, $offset, $limit);
    publicJson(['success' => true, 'data' => $rows, 'meta' => ['offset' => $offset, 'limit' => $limit, 'total' => count($items), 'has_more' => $offset + count($rows) < count($items), 'type' => $type, 'date' => ['month' => $month, 'day' => $day], 'available' => $cache !== null, 'import_status' => 'cached']]);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('WDHOTD feed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'The archives are temporarily unavailable. Please try again in a moment.'], JSON_UNESCAPED_UNICODE);
}
