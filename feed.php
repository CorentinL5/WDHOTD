<?php
require __DIR__ . '/config.php';

$type = $_GET['type'] ?? 'all';

$offset = (int)($_GET['offset'] ?? 0);
$limit  = (int)($_GET['limit'] ?? 20);

// Date du jour
$month = (int)date('n');
$day   = (int)date('j');

// Construction dynamique du WHERE
$where = "WHERE month = :month AND day = :day";
$params = [
    ':month' => $month,
    ':day'   => $day
];

if ($type !== 'all') {
    $where .= " AND type = :type";
    $params[':type'] = $type;
}

$sql = $db->prepare("
    SELECT id, type, year, selected_title, extract_html, extract_short, thumbnail, wikipedia_url
    FROM OTDItem
    $where
    ORDER BY year DESC, id DESC
    LIMIT :limit OFFSET :offset
");

// Bind des paramètres du WHERE
foreach ($params as $k => $v) {
    $sql->bindValue($k, $v);
}

// Bind offset/limit
$sql->bindValue(':limit', $limit, PDO::PARAM_INT);
$sql->bindValue(':offset', $offset, PDO::PARAM_INT);

$sql->execute();

echo json_encode($sql->fetchAll());
