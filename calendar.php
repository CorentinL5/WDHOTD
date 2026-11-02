<?php
require_once __DIR__ . '/public_data.php';
publicJson(publicData('calendar.json') ?? ['dates' => []]);
