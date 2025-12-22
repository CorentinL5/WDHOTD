<?php
// Récupérer dernière date d’import
$last_date = $db->query("
    SELECT DATE(MAX(updated_at)) 
    FROM OTDItem
")->fetchColumn();

$today = date("Y-m-d");

// Déjà importé aujourd’hui → stop
if ($last_date === $today) return;

// Déclenche l’import en arrière-plan
$_GET['month'] = $month = date("m");
$_GET['day']   = $day   = date("d");

require __DIR__ . "/import.php";
