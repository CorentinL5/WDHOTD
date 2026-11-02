<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Configuration principale de WDHOTD
 * Charge les variables d'environnement depuis .env si disponible
 */

// Charger les variables d'environnement depuis .env si le fichier existe
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Ignorer les commentaires
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!empty($key) && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

// Détection de l'environnement (local vs production)
$host = strtolower(explode(':', $_SERVER["HTTP_HOST"] ?? 'localhost')[0]);
$isLocal = (
    $host === "localhost" ||
    $host === "wdhotd.test" ||
    strpos($host, "192.168.") === 0 ||
    $host === "127.0.0.1"
);

// Configuration de la base de données
if ($isLocal) {
    $host = $_ENV['DB_HOST_LOCAL'] ?? 'localhost';
    $user = $_ENV['DB_USER_LOCAL'] ?? 'root';
    $pass = $_ENV['DB_PASS_LOCAL'] ?? '';
    $dbname = $_ENV['DB_NAME_LOCAL'] ?? 'wdhotd';
} else {
    $host = $_ENV['DB_HOST_PROD'] ?? 'sqlXXX.epizy.com';
    $user = $_ENV['DB_USER_PROD'] ?? 'epiz_12345678';
    $pass = $_ENV['DB_PASS_PROD'] ?? 'TON_MDP';
    $dbname = $_ENV['DB_NAME_PROD'] ?? 'epiz_12345678_otd';
}

// Configuration de l'application
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Brussels');
define('APP_ENV', $_ENV['APP_ENV'] ?? ($isLocal ? 'development' : 'production'));
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? $isLocal, FILTER_VALIDATE_BOOLEAN));

// Configuration de l'API Wikipedia
define('WIKIPEDIA_API_TIMEOUT', (int)($_ENV['WIKIPEDIA_API_TIMEOUT'] ?? 20));
define('WIKIPEDIA_USER_AGENT', $_ENV['WIKIPEDIA_USER_AGENT'] ?? 'wdhotd-importer/1.0');

// Connexion à la base de données avec gestion d'erreurs améliorée
global $db;
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ];
    
    $db = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    if (defined('JSON_RESPONSE')) {
        throw new RuntimeException('Les archives sont momentanément indisponibles. Réessayez dans un instant.', 0, $e);
    }
    if (APP_DEBUG) {
        die("DB Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please contact the administrator.");
    }
}

function detectClient($ua): array
{
    // Detect OS
    $os = "Unknown";
    if (preg_match('/Windows/i', $ua)) $os = "Windows";
    elseif (preg_match('/Mac OS/i', $ua)) $os = "MacOS";
    elseif (preg_match('/Android/i', $ua)) $os = "Android";
    elseif (preg_match('/iPhone|iPad/i', $ua)) $os = "iOS";
    elseif (preg_match('/Linux/i', $ua)) $os = "Linux";

    // Detect Browser
    $browser = "Unknown";
    if (preg_match('/Chrome/i', $ua)) $browser = "Chrome";
    elseif (preg_match('/Firefox/i', $ua)) $browser = "Firefox";
    elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = "Safari";
    elseif (preg_match('/Edge/i', $ua)) $browser = "Edge";
    elseif (preg_match('/Opera|OPR/i', $ua)) $browser = "Opera";

    // Detect device
    $device = "Desktop";
    if (preg_match('/Mobile|Android|iPhone/i', $ua)) $device = "Mobile";

    return [$os, $browser, $device];
}

/**
 * Enregistre une visite dans les logs
 * @param PDO $db Instance de la base de données
 * @param string $page Nom de la page visitée
 * @param string|null $referer Referer HTTP (peut être null)
 * @return void
 */
function logVisit(PDO $db, string $page, ?string $referer): void {
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        list($os, $browser, $device) = detectClient($ua);

        $stmt = $db->prepare("
            INSERT INTO WDLogs (page, referer, user_agent, os, browser, device)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            substr($page, 0, 255), // Limiter la longueur pour la sécurité
            $referer ? substr($referer, 0, 1000) : null,
            substr($ua, 0, 500),
            substr($os, 0, 64),
            substr($browser, 0, 64),
            substr($device, 0, 32)
        ]);
    } catch (PDOException $e) {
        // Logger l'erreur sans interrompre l'exécution
        error_log("Failed to log visit: " . $e->getMessage());
    }
}

// Logging des visites (en mode silencieux pour ne pas bloquer l'application)
if (!defined('SKIP_LOG')) {
    logVisit(
        $db,
        $_SERVER['PHP_SELF'] ?? 'unknown',
        $_SERVER['HTTP_REFERER'] ?? null
    );
}

// Imports are triggered by data endpoints, never by loading configuration.
