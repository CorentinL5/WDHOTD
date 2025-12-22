<?php
$host = "sqlXXX.epizy.com";   // fourni dans ton panel InfinityFree
$user = "epiz_12345678";
$pass = "TON_MDP";
$dbname = "epiz_12345678_otd";

// if host (wdhotd.test) => localhost DB or is 192.168.x.x
if ( $_SERVER["HTTP_HOST"] == "localhost" or $_SERVER["HTTP_HOST"] == "wdhotd.test" or str_contains($_SERVER["HTTP_HOST"], "192.168") ) {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "wdhotd";
}

global $db;
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
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

function logVisit(PDO $db, string $page, ?string $referer): void {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    list($os, $browser, $device) = detectClient($ua);

    $stmt = $db->prepare("
        INSERT INTO WDLogs (page, referer, user_agent, os, browser, device)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $page,
        $referer,
        $ua,
        $os,
        $browser,
        $device
    ]);
}

logVisit(
    $db,
    $_SERVER['PHP_SELF'] ?? 'unknown',
    $_SERVER['HTTP_REFERER'] ?? null
);

require_once __DIR__ . "/auto_import.php";
