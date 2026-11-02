<?php
require_once __DIR__ . '/history.php';
require_once __DIR__ . '/public_data.php';
try {
    [$month, $day] = historyDate($_GET);
    $cache = publicData(sprintf('%02d-%02d.json', $month, $day));
    publicJson(['success' => true, 'stats' => $cache['stats'] ?? ['events' => 0, 'births' => 0, 'deaths' => 0, 'holidays' => 0, 'total' => 0], 'yearRange' => $cache['yearRange'] ?? ['min' => null, 'max' => null], 'date' => ['month' => $month, 'day' => $day]]);
} catch (InvalidArgumentException $error) { http_response_code(400); publicJson(['success' => false, 'error' => $error->getMessage()], 0); }
