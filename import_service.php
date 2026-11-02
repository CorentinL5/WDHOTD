<?php
require_once __DIR__ . '/history.php';

/** Import state, original source data and editorial fields all live in MySQL. */
function importHistory(PDO $db, int $month, int $day, bool $force = false): array {
    [$month, $day] = historyDate(['month' => $month, 'day' => $day]);
    $lockName = sprintf('wdhotd:%02d:%02d', $month, $day);
    $lock = $db->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if (!(int)$lock->fetchColumn()) return ['status' => 'busy'];

    try {
        $query = $db->prepare('SELECT succeeded_at, attempted_at FROM OTDImport WHERE month = ? AND day = ?');
        $query->execute([$month, $day]);
        $state = $query->fetch();
        if (!$force && $state) {
            if ($state['succeeded_at'] && substr($state['succeeded_at'], 0, 10) === date('Y-m-d')) return ['status' => 'fresh'];
            if ($state['attempted_at'] && strtotime($state['attempted_at']) > time() - 300) return ['status' => 'waiting'];
        }
        $attempt = $db->prepare("INSERT INTO OTDImport (month, day, status, attempted_at) VALUES (?, ?, 'running', NOW()) ON DUPLICATE KEY UPDATE status = 'running', attempted_at = NOW(), error_message = NULL");
        $attempt->execute([$month, $day]);

        $url = sprintf('https://en.wikipedia.org/api/rest_v1/feed/onthisday/all/%02d/%02d', $month, $day);
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => WIKIPEDIA_USER_AGENT, CURLOPT_TIMEOUT => WIKIPEDIA_API_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $json = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($json === false || $code !== 200) throw new RuntimeException('Wikipédia est indisponible : HTTP ' . $code . ' ' . $error);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['events']) || !is_array($data['events']) || !$data['events']) throw new RuntimeException('Réponse Wikipédia incomplète.');

        $featured = [];
        foreach ($data['selected'] ?? [] as $entry) {
            $pageid = $entry['pages'][0]['pageid'] ?? null;
            if ($pageid !== null) $featured[($entry['year'] ?? '') . '|' . $pageid] = true;
        }
        $find = $db->prepare('SELECT id FROM OTDItem WHERE type = ? AND year <=> ? AND month = ? AND day = ? AND pageid <=> ? AND (event_text = ? OR event_text IS NULL) LIMIT 1');
        $insert = $db->prepare('INSERT INTO OTDItem (type, year, month, day, pageid, canonical_title, normalized_title, selected_title, event_text, extract_short, extract_html, thumbnail, wikipedia_url, raw_json, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $db->prepare('UPDATE OTDItem SET canonical_title = ?, normalized_title = ?, selected_title = ?, event_text = ?, extract_short = ?, extract_html = ?, thumbnail = ?, wikipedia_url = ?, raw_json = ?, featured = ?, updated_at = NOW() WHERE id = ?');
        $db->beginTransaction();
        $count = 0;
        foreach (['events', 'births', 'deaths', 'holidays'] as $type) {
            foreach ($data[$type] ?? [] as $entry) {
                $pages = $entry['pages'] ?? [];
                if (!$pages || !historyText($entry['text'] ?? '')) continue;
                // Prefer an article with context and an illustration to an empty first page.
                usort($pages, static function ($a, $b) {
                    $score = static fn($page) => (empty($page['extract']) ? 0 : 2) + (empty($page['thumbnail']['source']) ? 0 : 1);
                    return $score($b) <=> $score($a);
                });
                $page = $pages[0];
                $year = isset($entry['year']) ? (int)$entry['year'] : null;
                $pageid = $page['pageid'] ?? null;
                $event = historyText($entry['text']);
                $isFeatured = false;
                if ($type === 'events') {
                    foreach ($entry['pages'] as $related) {
                        if (isset($featured[($entry['year'] ?? '') . '|' . ($related['pageid'] ?? '')])) $isFeatured = true;
                    }
                }
                $entry['_featured'] = $isFeatured;
                $entry['_image'] = historyImage($entry, $pageid === null ? null : (int)$pageid);
                $canonical = historyText($page['title'] ?? '');
                $values = [$canonical, historyText($page['normalizedtitle'] ?? $canonical), historyText($page['displaytitle'] ?? $canonical), $event, historyText($page['extract'] ?? ''), $page['extract_html'] ?? null, $entry['_image']['src'] ?? null, $page['content_urls']['desktop']['page'] ?? null, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (int)$isFeatured];
                $find->execute([$type, $year, $month, $day, $pageid, $event]);
                $id = $find->fetchColumn();
                if ($id) $update->execute([...$values, $id]);
                else $insert->execute([$type, $year, $month, $day, $pageid, ...$values]);
                $count++;
            }
        }
        if (!$count) throw new RuntimeException('Aucune histoire exploitable dans la réponse Wikipédia.');
        $success = $db->prepare("UPDATE OTDImport SET status = 'success', succeeded_at = NOW(), item_count = ?, error_message = NULL WHERE month = ? AND day = ?");
        $success->execute([$count, $month, $day]);
        $db->commit();
        return ['status' => 'success', 'count' => $count];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        try {
            $failure = $db->prepare("UPDATE OTDImport SET status = 'error', error_message = ? WHERE month = ? AND day = ?");
            $failure->execute([substr($error->getMessage(), 0, 1000), $month, $day]);
        } catch (Throwable $ignored) { /* The migration may not have been applied yet. */ }
        throw $error;
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
