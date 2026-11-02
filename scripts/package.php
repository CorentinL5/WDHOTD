<?php
/** Build a new allowlisted web root; never package the checkout wholesale. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/public_data.php';
$project = dirname(__DIR__);
$options = getopt('', ['production']);
$settings = publicData('settings.json') ?? [];
$configuredSiteURL = publicSiteURL($settings['site_url'] ?? null);
$siteURL = isset($options['production']) ? $configuredSiteURL : null;
$calendar = publicData('calendar.json')['dates'] ?? [];
if (!$calendar) throw new RuntimeException('No published archive. Run the sync/export job first.');
if (isset($options['production']) && !$siteURL) throw new RuntimeException('Configure the real public HTTPS site URL before building a production release.');
$id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
$release = $project . '/dist/release-' . $id;
$public = $release . '/public';
if (!mkdir($public, 0755, true)) throw new RuntimeException('Cannot create a new release directory.');
$manifest = [];
function publishFile(string $relative, string $content): void {
    global $public, $manifest;
    if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) throw new RuntimeException('Invalid release path.');
    $destination = $public . '/' . $relative;
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true)) throw new RuntimeException('Cannot create a release folder.');
    if (file_put_contents($destination, $content) === false) throw new RuntimeException('Cannot write release file.');
    $manifest[$relative] = hash('sha256', $content);
}
foreach (['index.php', 'feed.php', 'stats.php', 'calendar.php', 'sponsor.php', 'public_data.php', 'web.php', 'history.php', 'images.php', 'LICENSE', 'NOTICE'] as $file) publishFile($file, (string)file_get_contents($project . '/' . $file));
$assets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/assets', FilesystemIterator::SKIP_DOTS));
foreach ($assets as $file) {
    if ($file->isLink()) throw new RuntimeException('Symlinks are not allowed in release assets.');
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['css', 'js', 'svg', 'woff', 'woff2', 'ttf'], true)) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($project) + 1));
    publishFile($relative, (string)file_get_contents($file->getPathname()));
}
$publishedDates = [];
$publicFields = array_flip(['id', 'type', 'year', 'selected_title', 'event_text', 'extract_short', 'thumbnail', 'wikipedia_url', 'featured', 'image']);
$imageFields = array_flip(['src', 'small', 'width', 'height', 'ratio', 'kind', 'title', 'page_url']);
foreach ($calendar as $date) {
    $month = (int)$date['month']; $day = (int)$date['day'];
    if (!checkdate($month, $day, 2000)) throw new RuntimeException('Invalid published calendar date.');
    $name = sprintf('%02d-%02d.json', $month, $day);
    $archive = publicData($name);
    if (!$archive || !isset($archive['data'], $archive['stats'], $archive['yearRange'])) throw new RuntimeException('Incomplete published archive: ' . $name);
    $items = [];
    foreach ($archive['data'] as $item) {
        $item = array_intersect_key($item, $publicFields);
        if (!empty($item['image'])) $item['image'] = array_intersect_key($item['image'], $imageFields);
        $items[] = $item;
    }
    publishFile('data-cache/' . $name, json_encode(['date' => ['month' => $month, 'day' => $day], 'data' => $items, 'stats' => $archive['stats'], 'yearRange' => $archive['yearRange']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $publishedDates[] = ['month' => $month, 'day' => $day];
}
publishFile('data-cache/calendar.json', json_encode(['dates' => $publishedDates], JSON_THROW_ON_ERROR));
$ads = array_map(static fn($ad) => array_intersect_key($ad, array_flip(['kind', 'title', 'body', 'destination', 'link_label', 'starts_at', 'ends_at'])), array_slice($settings['ads'] ?? [], 0, 20));
publishFile('data-cache/settings.json', json_encode(['site_url' => $siteURL ?? '', 'support_url' => publicSupportURL($settings['support_url'] ?? null) ?? '', 'source_url' => publicSupportURL($settings['source_url'] ?? null) ?? '', 'ads' => $ads], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$apache = (string)file_get_contents($project . '/.htaccess');
if (isset($options['production'])) {
    $redirect = "    RewriteCond %{HTTPS} !=on\n    RewriteRule ^(.*)$ " . $siteURL . "/$1 [R=308,L]\n";
    $apache = str_replace('    RewriteEngine On', "    RewriteEngine On\n" . $redirect, $apache);
}
publishFile('.htaccess', $apache);
publishFile('robots.txt', $siteURL ? "User-agent: *\nAllow: /\nDisallow: /*view=saved\nSitemap: $siteURL/sitemap.xml\n" : "User-agent: *\nDisallow: /\n");
$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
if ($siteURL) {
    $urls = [$siteURL . '/'];
    foreach ($publishedDates as $date) $urls[] = $siteURL . '/?month=' . $date['month'] . '&day=' . $date['day'];
    foreach ($urls as $url) $sitemap .= '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
}
publishFile('sitemap.xml', $sitemap . '</urlset>');
ksort($manifest);
file_put_contents($release . '/manifest.json', json_encode(['release' => $id, 'production' => isset($options['production']), 'site_url' => $siteURL, 'dates' => count($publishedDates), 'files' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$zipPath = null;
if (class_exists('ZipArchive')) {
    $zipPath = $release . '/wdhotd-public.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create release ZIP.');
    foreach (array_keys($manifest) as $file) $zip->addFile($public . '/' . $file, $file);
    $zip->close();
}
echo json_encode(['public_directory' => $public, 'manifest' => $release . '/manifest.json', 'zip' => $zipPath, 'production' => isset($options['production']), 'dates' => count($publishedDates)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
