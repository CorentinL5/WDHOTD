<?php
/**
 * API Stats - Retourne les statistiques du jour au format JSON
 * Paramètres GET:
 *   - month: mois (1-12) - optionnel, défaut: jour actuel
 *   - day: jour (1-31) - optionnel, défaut: jour actuel
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

try {
    // Date - peut être personnalisée via GET, sinon date du jour
    if (isset($_GET['month']) && isset($_GET['day'])) {
        $month = max(1, min(12, (int)$_GET['month']));
        $day = max(1, min(31, (int)$_GET['day']));
        // Validation de la date
        if (!checkdate($month, $day, date('Y'))) {
            $month = (int)date('n');
            $day = (int)date('j');
        }
    } else {
        $month = (int)date('n');
        $day = (int)date('j');
    }

    // Récupérer les statistiques par type
    $stats = [];
    $categories = ['events', 'births', 'deaths', 'holidays'];
    
    foreach ($categories as $type) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM OTDItem
            WHERE month = ? AND day = ? AND type = ?
        ");
        $stmt->execute([$month, $day, $type]);
        $stats[$type] = (int)$stmt->fetchColumn();
    }
    
    // Total
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM OTDItem
        WHERE month = ? AND day = ?
    ");
    $stmt->execute([$month, $day]);
    $stats['total'] = (int)$stmt->fetchColumn();
    
    // Année la plus ancienne et la plus récente
    $stmt = $db->prepare("
        SELECT MIN(year) as min_year, MAX(year) as max_year
        FROM OTDItem
        WHERE month = ? AND day = ? AND year IS NOT NULL
    ");
    $stmt->execute([$month, $day]);
    $yearRange = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'yearRange' => [
            'min' => $yearRange['min_year'] ? (int)$yearRange['min_year'] : null,
            'max' => $yearRange['max_year'] ? (int)$yearRange['max_year'] : null
        ],
        'date' => [
            'month' => $month,
            'day' => $day,
            'formatted' => date('d/m/Y', mktime(0, 0, 0, $month, $day, date('Y')))
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Database error occurred'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Stats API error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'An error occurred'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Stats API error: " . $e->getMessage());
}

