<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/images.php';
function expectImage(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$page = ['pageid' => 7, 'title' => 'Ada_Lovelace', 'thumbnail' => ['source' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/ab/Portrait.jpg/330px-Portrait.jpg?source=api', 'width' => 330, 'height' => 440], 'originalimage' => ['source' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Portrait.jpg', 'width' => 1200, 'height' => 1600]];
$entry = ['text' => 'Ada Lovelace publishes her notes.', 'pages' => [$page]];
$image = historyImage($entry, 7);
expectImage(str_contains($image['src'], '/960px-Portrait.jpg?source=api'), 'Large images use a 960-pixel derivative and keep source parameters');
expectImage($image['kind'] === 'portrait' && $image['width'] === 960 && $image['height'] === 1280, 'Portrait proportions and available dimensions are preserved');
$small = $page;
$small['originalimage']['width'] = 260;
$small['originalimage']['height'] = 350;
$result = historyImage(['text' => $entry['text'], 'pages' => [$small]], 7);
expectImage($result['width'] === 260 && $result['src'] === $small['originalimage']['source'], 'Small originals are never upscaled');
$tiny = $page;
$tiny['originalimage']['width'] = 120;
expectImage(historyImage(['text' => $entry['text'], 'pages' => [$tiny]], 7) === null, 'Tiny originals are rejected');
$logo = $page;
$logo['thumbnail']['source'] = str_replace('Portrait.jpg', 'Club_logo.svg.png', $logo['thumbnail']['source']);
expectImage(historyImage(['text' => $entry['text'], 'pages' => [$logo]], 7) === null, 'Generic logos do not become editorial photos');
$irrelevant = $page;
$irrelevant['title'] = 'Capital_punishment';
expectImage(historyImage(['text' => 'Capital punishment: an execution in 1984.', 'pages' => [$irrelevant]], 7) === null, 'Broad unrelated archive imagery is excluded');
$panorama = $page;
$panorama['originalimage']['width'] = 3000;
$panorama['originalimage']['height'] = 1000;
expectImage(historyImage(['text' => $entry['text'], 'pages' => [$panorama]], 7)['kind'] === 'panorama', 'Panoramas are identified for framing');
expectImage(historyImage(['text' => 'An event', 'pages' => []]) === null, 'Missing imagery has a clean fallback');
