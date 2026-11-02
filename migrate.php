<?php
/** Run with `php migrate.php`; safe to repeat on an existing database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('SKIP_LOG', true);
define('JSON_RESPONSE', true);
require_once __DIR__ . '/history.php';
try {
    require __DIR__ . '/config.php';
    $columns = $db->query('SHOW COLUMNS FROM OTDItem')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('event_text', $columns, true)) $db->exec('ALTER TABLE OTDItem ADD COLUMN event_text TEXT NULL');
    if (!in_array('featured', $columns, true)) $db->exec('ALTER TABLE OTDItem ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0');
    $db->exec("CREATE TABLE IF NOT EXISTS OTDImport (
        month TINYINT UNSIGNED NOT NULL, day TINYINT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending', attempted_at DATETIME NULL,
        succeeded_at DATETIME NULL, item_count INT UNSIGNED NOT NULL DEFAULT 0,
        error_message TEXT NULL, PRIMARY KEY (month, day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS AppSetting (
        setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO AppSetting (setting_key, setting_value) VALUES ('support_url', ''), ('source_url', '')");
    $db->exec("CREATE TABLE IF NOT EXISTS Advertisement (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) NOT NULL UNIQUE, kind VARCHAR(16) NOT NULL DEFAULT 'sponsor',
        title VARCHAR(160) NOT NULL, body VARCHAR(255) NOT NULL,
        destination TEXT NOT NULL, link_label VARCHAR(80) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 0, priority INT NOT NULL DEFAULT 0,
        starts_at DATETIME NULL, ends_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("UPDATE Advertisement SET active = 0 WHERE slug = 'premium-launch'");
    $rows = $db->query('SELECT id, raw_json FROM OTDItem WHERE event_text IS NULL');
    $update = $db->prepare('UPDATE OTDItem SET event_text = ?, featured = ? WHERE id = ?');
    $count = 0;
    foreach ($rows as $row) {
        $raw = json_decode($row['raw_json'] ?? '', true) ?: [];
        if (!empty($raw['text'])) { $update->execute([historyText($raw['text']), (int)($raw['_featured'] ?? false), $row['id']]); $count++; }
    }
    echo "Migration terminée. $count récits récupérés depuis les données existantes.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration impossible : ' . $error->getMessage() . "\n");
    exit(1);
}
