<?php
/**
 * Auto-import des données du jour
 * Vérifie si l'import du jour a déjà été fait et le déclenche si nécessaire
 * 
 * Ce script est appelé automatiquement à chaque requête via config.php
 * Il évite les imports multiples en vérifiant la date du dernier import
 */

// Éviter les imports multiples simultanés avec un verrou simple
$lockFile = __DIR__ . '/.import_lock';
$maxLockAge = 300; // 5 minutes maximum pour un import

// Vérifier si un import est déjà en cours
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < $maxLockAge) {
        // Import en cours, on skip
        return;
    } else {
        // Lock trop vieux, on le supprime (import probablement bloqué)
        @unlink($lockFile);
    }
}

try {
    // Vérifier que $db est disponible
    if (!isset($db)) {
        return;
    }
    
    // Créer le verrou
    @file_put_contents($lockFile, time());

    // Récupérer la dernière date d'import
    $stmt = $db->prepare("
        SELECT DATE(MAX(updated_at)) 
        FROM OTDItem
        WHERE month = ? AND day = ?
    ");
    $currentMonth = (int)date('n');
    $currentDay = (int)date('j');
    $stmt->execute([$currentMonth, $currentDay]);
    $last_date = $stmt->fetchColumn();

    $today = date("Y-m-d");

    // Si déjà importé aujourd'hui → on skip
    if ($last_date === $today) {
        @unlink($lockFile);
        return;
    }

    // Déclencher l'import avec les paramètres du jour actuel
    // On utilise un tableau de paramètres plutôt que de modifier $_GET globalement
    $_GET['month'] = $currentMonth;
    $_GET['day'] = $currentDay;

    // Appeler import.php en mode silencieux
    ob_start();
    include __DIR__ . "/import.php";
    ob_end_clean();

} catch (Exception $e) {
    error_log("Auto-import error: " . $e->getMessage());
} finally {
    // Toujours supprimer le verrou
    @unlink($lockFile);
}
