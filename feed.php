<?php
/**
 * API Feed - Retourne les événements "On This Day" au format JSON
 * Paramètres GET:
 *   - type: all|events|births|deaths|holidays (default: all)
 *   - offset: nombre d'éléments à ignorer (default: 0)
 *   - limit: nombre d'éléments à retourner (default: 20, max: 100)
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

try {
    // Validation et sanitization des paramètres
    $allowedTypes = ['all', 'events', 'births', 'deaths', 'holidays'];
    $type = $_GET['type'] ?? 'all';
    if (!in_array($type, $allowedTypes)) {
        $type = 'all';
    }

    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20))); // Limite entre 1 et 100

    // Date - peut être personnalisée via GET, sinon date du jour
    if (isset($_GET['month']) && isset($_GET['day'])) {
        $month = max(1, min(12, (int)$_GET['month']));
        $day = max(1, min(31, (int)$_GET['day']));
        // Validation de la date
        if (!checkdate($month, $day, date('Y'))) {
            // Si date invalide, utiliser la date du jour
            $month = (int)date('n');
            $day = (int)date('j');
        }
    } else {
        // Date du jour par défaut
        $month = (int)date('n');
        $day = (int)date('j');
    }

    // Construction dynamique du WHERE avec validation
    $where = "WHERE month = :month AND day = :day";
    $params = [
        ':month' => $month,
        ':day' => $day
    ];

    if ($type !== 'all') {
        $where .= " AND type = :type";
        $params[':type'] = $type;
    }

    // Requête préparée avec protection SQL injection
    $sql = $db->prepare("
        SELECT 
            id, 
            type, 
            year, 
            selected_title, 
            extract_html, 
            extract_short, 
            thumbnail, 
            wikipedia_url
        FROM OTDItem
        $where
        ORDER BY year DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");

    // Bind des paramètres
    foreach ($params as $key => $value) {
        $sql->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $sql->bindValue(':limit', $limit, PDO::PARAM_INT);
    $sql->bindValue(':offset', $offset, PDO::PARAM_INT);

    $sql->execute();
    $results = $sql->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Nettoie un titre en enlevant les balises HTML
     */
    function cleanTitle($title) {
        if (empty($title)) return 'Untitled';
        // Enlever toutes les balises HTML
        $cleaned = strip_tags($title);
        // Nettoyer les espaces multiples
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return trim($cleaned) ?: 'Untitled';
    }

    // Nettoyage et formatage des données pour le front-end
    foreach ($results as &$item) {
        // S'assurer que les valeurs null sont gérées
        $item['year'] = $item['year'] ?? null;
        $item['selected_title'] = cleanTitle($item['selected_title'] ?? 'Untitled');
        $item['extract_html'] = $item['extract_html'] ?? null;
        $item['extract_short'] = $item['extract_short'] ?? null;
        $item['thumbnail'] = $item['thumbnail'] ?? null;
        $item['wikipedia_url'] = $item['wikipedia_url'] ?? null;
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
        'meta' => [
            'total' => count($results),
            'offset' => $offset,
            'limit' => $limit,
            'type' => $type,
            'date' => [
                'month' => $month,
                'day' => $day
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Database error occurred'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Feed API error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'An error occurred'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Feed API error: " . $e->getMessage());
}
