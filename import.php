<?php
/**
 * Script d'import des données depuis l'API Wikipedia On This Day
 * 
 * Paramètres GET:
 *   - month: mois (1-12)
 *   - day: jour (1-31)
 * 
 * Ce script vérifie si l'import a déjà été fait aujourd'hui pour éviter les doublons
 */

// Éviter la double inclusion de config.php
if (!isset($db)) {
    require __DIR__ . '/config.php';
}

// Mode verbose pour les logs
$verbose = isset($_GET['verbose']) && $_GET['verbose'] === '1';

function logMessage($message, $verbose = false) {
    if ($verbose) {
        echo date('[Y-m-d H:i:s] ') . $message . "\n";
    }
    error_log($message);
}

/**
 * Nettoie un titre en enlevant les balises HTML
 */
function cleanTitleForImport($title) {
    if (empty($title)) return null;
    // Enlever toutes les balises HTML
    $cleaned = strip_tags($title);
    // Nettoyer les espaces multiples
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    return trim($cleaned) ?: null;
}

// 1) Validation des paramètres de date
if (!isset($_GET['month']) || !isset($_GET['day'])) {
    logMessage("Import failed: month and day parameters required");
    http_response_code(400);
    die("Error: month and day parameters are required.");
}

$month = (int)$_GET['month'];
$day = (int)$_GET['day'];

// Validation de la date
if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
    logMessage("Import failed: Invalid date range - month: $month, day: $day");
    http_response_code(400);
    die("Error: Invalid date range.");
}

// Vérification avec checkdate (utilise l'année actuelle comme référence)
$currentYear = (int)date('Y');
if (!checkdate($month, $day, $currentYear)) {
    logMessage("Import failed: Invalid date - month: $month, day: $day");
    http_response_code(400);
    die("Error: Invalid date.");
}

$monthStr = str_pad((string)$month, 2, "0", STR_PAD_LEFT);
$dayStr = str_pad((string)$day, 2, "0", STR_PAD_LEFT);

// 2) Vérifier si l'import a déjà été fait aujourd'hui (sauf si force=1)
$force = isset($_GET['force']) && $_GET['force'] === '1';
$today = date("Y-m-d");

if (!$force) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM OTDItem 
            WHERE month = ? AND day = ? 
              AND DATE(updated_at) = ?
        ");
        $stmt->execute([$month, $day, $today]);
        $exists_today = $stmt->fetchColumn();

        if ($exists_today > 0) {
            logMessage("Import skipped: Already imported today for $monthStr/$dayStr");
            if ($verbose) {
                die("Already imported today. Use ?force=1 to force re-import.\n");
            }
            exit;
        }
    } catch (PDOException $e) {
        logMessage("Database error while checking existing import: " . $e->getMessage());
        if (!$verbose) {
            http_response_code(500);
            die("Database error.");
        }
        throw $e;
    }
} else {
    logMessage("Force import enabled for $monthStr/$dayStr");
}

// 3) Récupération des données depuis l'API Wikipedia
$url = "https://en.wikipedia.org/api/rest_v1/feed/onthisday/all/$monthStr/$dayStr";
logMessage("Fetching data from: $url");

$ch = curl_init($url);
if ($ch === false) {
    logMessage("Import failed: Could not initialize cURL");
    http_response_code(500);
    die("Error: Could not initialize cURL.");
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => defined('WIKIPEDIA_USER_AGENT') ? WIKIPEDIA_USER_AGENT : 'wdhotd-importer/1.0',
    CURLOPT_TIMEOUT => defined('WIKIPEDIA_API_TIMEOUT') ? WIKIPEDIA_API_TIMEOUT : 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$json = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($json === false || !empty($curlError)) {
    logMessage("Import failed: cURL error - $curlError");
    http_response_code(502);
    die("Error: Failed to fetch data from Wikipedia API. " . ($verbose ? $curlError : ""));
}

if ($httpCode !== 200) {
    logMessage("Import failed: HTTP $httpCode from Wikipedia API");
    http_response_code(502);
    die("Error: Wikipedia API returned HTTP $httpCode");
}

$data = json_decode($json, true);
if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    logMessage("Import failed: Invalid JSON response from API");
    http_response_code(502);
    die("Error: Invalid JSON response from Wikipedia API.");
}

logMessage("Successfully fetched data from Wikipedia API");

// 4) Traitement de chaque catégorie
$categories = ['events', 'births', 'deaths', 'holidays'];
$total_inserted = 0;
$total_updated = 0;
$total_skipped = 0;
$errors = [];

foreach ($categories as $type) {
    if (!isset($data[$type]) || !is_array($data[$type])) {
        logMessage("No data found for category: $type", $verbose);
        continue;
    }

    logMessage("Processing category: $type (" . count($data[$type]) . " items)", $verbose);

    foreach ($data[$type] as $index => $entry) {
        try {
            if (!isset($entry['pages'][0]) || !is_array($entry['pages'][0])) {
                $total_skipped++;
                continue;
            }

            $page = $entry['pages'][0];

            // Extraction sécurisée des données
            $year = isset($entry['year']) ? (int)$entry['year'] : null;
            $pageid = isset($page['pageid']) ? (int)$page['pageid'] : null;

            if ($pageid === null) {
                $total_skipped++;
                continue;
            }

            $canonical = isset($page['title']) ? cleanTitleForImport($page['title']) : null;
            $normalized = isset($page['normalizedtitle']) ? cleanTitleForImport($page['normalizedtitle']) : $canonical;
            $selected = isset($page['displaytitle']) ? cleanTitleForImport($page['displaytitle']) : $canonical;

            $extract_short = isset($page['extract']) ? trim($page['extract']) : null;
            $extract_html = isset($page['extract_html']) ? trim($page['extract_html']) : null;

            $thumbnail = isset($page['thumbnail']['source']) ? trim($page['thumbnail']['source']) : null;
            $url_page = isset($page['content_urls']['desktop']['page']) ? trim($page['content_urls']['desktop']['page']) : null;

            // Validation des valeurs requises
            if (empty($canonical) && empty($selected)) {
                $total_skipped++;
                continue;
            }

            // 5) Vérifier si l'entrée existe déjà
            $stmt = $db->prepare("
                SELECT id, extract_short 
                FROM OTDItem
                WHERE type = ? AND year = ? AND month = ? AND day = ? AND pageid = ?
                LIMIT 1
            ");
            $stmt->execute([$type, $year, $month, $day, $pageid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                // Nouvelle entrée → INSERT
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
                    json_encode($entry, JSON_UNESCAPED_UNICODE)
                ]);

                $total_inserted++;
            } else {
                // Entrée existante → UPDATE si le contenu a changé
                if ($row['extract_short'] !== $extract_short) {
                    $stmt = $db->prepare("
                        UPDATE OTDItem
                        SET canonical_title = ?, normalized_title = ?, selected_title = ?,
                            extract_short = ?, extract_html = ?, thumbnail = ?,
                            wikipedia_url = ?, raw_json = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $canonical, $normalized, $selected,
                        $extract_short, $extract_html,
                        $thumbnail, $url_page,
                        json_encode($entry, JSON_UNESCAPED_UNICODE),
                        $row['id']
                    ]);

                    $total_updated++;
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error processing $type entry #$index: " . $e->getMessage();
            logMessage($errorMsg);
            $errors[] = $errorMsg;
            $total_skipped++;
        } catch (Exception $e) {
            $errorMsg = "Error processing $type entry #$index: " . $e->getMessage();
            logMessage($errorMsg);
            $errors[] = $errorMsg;
            $total_skipped++;
        }
    }
}

// Résumé final
$summary = "Import completed: $total_inserted inserted, $total_updated updated, $total_skipped skipped";
if (!empty($errors)) {
    $summary .= ", " . count($errors) . " errors";
}
logMessage($summary, $verbose);

if ($verbose) {
    echo "\n=== IMPORT SUMMARY ===\n";
    echo "Date: $monthStr/$dayStr\n";
    echo "Inserted: $total_inserted\n";
    echo "Updated: $total_updated\n";
    echo "Skipped: $total_skipped\n";
    if (!empty($errors)) {
        echo "Errors: " . count($errors) . "\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "=====================\n";
}
