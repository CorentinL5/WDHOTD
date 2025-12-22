<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) Date must be provided
if (!isset($_GET['month']) || !isset($_GET['day'])) exit;

$month = str_pad((string)$_GET['month'], 2, "0", STR_PAD_LEFT);
$day   = str_pad((string)$_GET['day'], 2, "0", STR_PAD_LEFT);


if (!checkdate($month, $day, date("Y"))) die("Invalid date.");

// 2) Avoid re-import same day
$today = date("Y-m-d");

$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM OTDItem 
    WHERE month = ? AND day = ? 
      AND DATE(updated_at) = ?
");
$stmt->execute([$month, $day, $today]);
$exists_today = $stmt->fetchColumn();

if ($exists_today > 0) exit;

// 3) Fetch JSON
$url = "https://en.wikipedia.org/api/rest_v1/feed/onthisday/all/$month/$day";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => "wdhotd-importer/1.0",
    CURLOPT_TIMEOUT        => 20
]);
$json = curl_exec($ch);
curl_close($ch);

$data = $json ? json_decode($json, true) : null;
if (!$data) die("Failed to fetch JSON.\n$url");

$categories = ['events', 'births', 'deaths', 'holidays'];

$total_inserted = 0;
$total_updated  = 0;

// 4) Process each category
foreach ($categories as $type) {

    if (!isset($data[$type])) continue;

    foreach ($data[$type] as $entry) {

        if (!isset($entry['pages'][0])) continue;

        $page = $entry['pages'][0];

        $year = $entry['year'] ?? null;
        $pageid = $page['pageid'] ?? null;

        $canonical = $page['title'] ?? null;
        $normalized = $page['normalizedtitle'] ?? $canonical;
        $selected = $page['displaytitle'] ?? $canonical;

        $extract_short = $page['extract'] ?? null;
        $extract_html = $page['extract_html'] ?? null;

        $thumbnail = $page['thumbnail']['source'] ?? null;
        $url_page = $page['content_urls']['desktop']['page'] ?? null;

        //
        // 5) CHECK IF ENTRY ALREADY EXISTS
        //
        $stmt = $db->prepare("
            SELECT id, extract_short 
            FROM OTDItem
            WHERE type=? AND year=? AND month=? AND day=? AND pageid=?
            LIMIT 1
        ");
        $stmt->execute([$type, $year, $month, $day, $pageid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {

            //
            // NEW ENTRY → INSERT
            //
            $stmt = $db->prepare("
                INSERT INTO OTDItem
                (type, year, month, day, pageid,
                 canonical_title, normalized_title, selected_title,
                 extract_short, extract_html, thumbnail, wikipedia_url, raw_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $type, $year, $month, $day, $pageid,
                $canonical, $normalized, $selected,
                $extract_short, $extract_html,
                $thumbnail, $url_page,
                json_encode($entry)
            ]);

            $total_inserted++;

        } else {

            //
            // EXISTING ENTRY → UPDATE IF CONTENT CHANGED
            //
            if ($row['extract_short'] !== $extract_short) {
                $stmt = $db->prepare("
                    UPDATE OTDItem
                    SET canonical_title=?, normalized_title=?, selected_title=?,
                        extract_short=?, extract_html=?, thumbnail=?,
                        wikipedia_url=?, raw_json=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([
                    $canonical, $normalized, $selected,
                    $extract_short, $extract_html,
                    $thumbnail, $url_page,
                    json_encode($entry),
                    $row['id']
                ]);

                $total_updated++;
            }
        }
    }
}
