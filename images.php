<?php
/** Image decisions use source metadata already saved in MySQL, without HTTP calls. */
function historyImage(array $entry, ?int $pageid = null): ?array {
    $candidates = [];
    $text = strtolower(str_replace(['_', '–', '—'], ' ', $entry['text'] ?? ''));
    foreach ($entry['pages'] ?? [] as $index => $page) {
        $thumb = $page['thumbnail'] ?? null;
        $original = $page['originalimage'] ?? $thumb;
        if (empty($thumb['source']) || empty($original['width']) || empty($original['height'])) continue;
        $source = $thumb['source'];
        $host = parse_url($source, PHP_URL_HOST);
        if (parse_url($source, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['upload.wikimedia.org', 'thumb.wikimedia.org'], true)) continue;
        $filename = urldecode(basename(parse_url($source, PHP_URL_PATH)));
        // Avoid stretching insignia, flags and generic logos into editorial photography.
        if (preg_match('/(?:flag[_ -]|logo|coat[_ -]of[_ -]arms|emblem|seal[_ -]of|blank[_ -])/i', $filename)) continue;
        $width = (int)$original['width'];
        $height = (int)$original['height'];
        $ratio = $width / max(1, $height);
        if ($width < 240 || $height < 160 || $ratio < 0.35 || $ratio > 3.5) continue;
        $title = str_replace('_', ' ', $page['normalizedtitle'] ?? $page['title'] ?? '');
        $title = preg_replace('/\s*\([^)]*\)/', '', $title);
        $exact = $title !== '' && strpos($text, strtolower($title)) !== false;
        $score = ($exact ? 12 : 0) + (($page['pageid'] ?? null) === $pageid ? 15 : 0) - $index * 0.15;
        // A broad article can illustrate the wrong event (e.g. a historical execution).
        if (preg_match('/^(capital punishment|world war i{0,2}|coup d.état|time zone|computer worm)$/i', $title)) continue;
        if ($score < 0) continue;
        if (!$exact && ($page['pageid'] ?? null) !== $pageid) continue;
        $large = $source;
        if ($width <= 960 && !empty($original['source'])) $large = $original['source'];
        elseif (preg_match('~/\d+px-[^/]+(?:\?.*)?$~', $source)) $large = preg_replace('~/\d+px-([^/]+)(\?.*)?$~U', '/960px-$1$2', $source);
        // Only advertise the actual available dimensions; never invent an upscale.
        $availableWidth = $large === $source ? min($width, (int)($thumb['width'] ?? $width)) : min($width, 960);
        $candidates[] = ['score' => $score, 'src' => $large, 'small' => $source, 'width' => $availableWidth, 'height' => (int)round($availableWidth / $ratio), 'ratio' => round($ratio, 4), 'kind' => $ratio < 0.9 ? 'portrait' : ($ratio > 2 ? 'panorama' : 'landscape'), 'title' => $title, 'page_url' => $page['content_urls']['desktop']['page'] ?? null];
    }
    if (!$candidates) return null;
    usort($candidates, static fn($a, $b) => $b['score'] <=> $a['score']);
    $image = $candidates[0];
    unset($image['score']);
    return $image;
}
