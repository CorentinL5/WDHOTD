<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Private maintenance configuration. Public routes never include this file.
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=(.*)$/', $line, $match)) continue;
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
        if (!array_key_exists($match[1], $_ENV) && getenv($match[1]) === false) $_ENV[$match[1]] = $value;
    }
}
function appEnvironment(string $key, $default = null) {
    if (array_key_exists($key, $_ENV)) return $_ENV[$key];
    $value = getenv($key);
    return $value === false ? $default : $value;
}
define('APP_ENV', appEnvironment('APP_ENV', 'development'));
if (!in_array(APP_ENV, ['development', 'testing', 'production'], true)) throw new RuntimeException('APP_ENV must be development, testing or production.');
define('APP_DEBUG', APP_ENV !== 'production' && filter_var(appEnvironment('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN));
date_default_timezone_set(appEnvironment('APP_TIMEZONE', 'Europe/Brussels'));
$suffix = APP_ENV === 'production' ? '_PROD' : '_LOCAL';
$host = appEnvironment('DB_HOST', appEnvironment('DB_HOST' . $suffix, APP_ENV === 'production' ? null : 'localhost'));
$user = appEnvironment('DB_USER', appEnvironment('DB_USER' . $suffix, APP_ENV === 'production' ? null : 'root'));
$pass = appEnvironment('DB_PASS', appEnvironment('DB_PASS' . $suffix, APP_ENV === 'production' ? null : ''));
$dbname = appEnvironment('DB_NAME', appEnvironment('DB_NAME' . $suffix, APP_ENV === 'production' ? null : 'wdhotd'));
$port = (int)appEnvironment('DB_PORT', 3306);
if (!$host || !$user || !$dbname || $pass === null || str_contains((string)$host, 'sqlXXX') || str_contains((string)$user, 'epiz_12345678')) throw new RuntimeException('Configure the database credentials for the selected environment before running maintenance.');
if ($port < 1 || $port > 65535) throw new RuntimeException('Invalid DB_PORT.');
define('WIKIPEDIA_API_TIMEOUT', max(1, min(60, (int)appEnvironment('WIKIPEDIA_API_TIMEOUT', 20))));
define('WIKIPEDIA_USER_AGENT', appEnvironment('WIKIPEDIA_USER_AGENT', 'wdhotd-importer/1.0'));
try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (PDOException $error) {
    throw new RuntimeException(APP_DEBUG ? $error->getMessage() : 'Database connection failed. Check the private maintenance configuration.', 0, $error);
}
