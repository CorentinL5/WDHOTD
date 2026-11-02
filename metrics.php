<?php
require_once __DIR__ . '/public_data.php';
require_once __DIR__ . '/metrics_service.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {header('Allow: POST');http_response_code(405);exit;}
if (($_SERVER['HTTP_SEC_GPC'] ?? '') === '1' || ($_SERVER['HTTP_DNT'] ?? '') === '1') {http_response_code(204);exit;}
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (strcasecmp($_SERVER['HTTP_ORIGIN'] ?? '', $origin) !== 0 || !preg_match('~^application/json(?:;|$)~i', $_SERVER['CONTENT_TYPE'] ?? '') || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024) {http_response_code(400);exit;}
try {
    $content = file_get_contents('php://input', false, null, 0, 1025);
    if ($content === false || strlen($content) > 1024) throw new InvalidArgumentException();
    $body = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
    $events = $body['events'] ?? null;
    if (!is_array($events) || !array_is_list($events) || array_filter($events, static fn($event) => !is_string($event))) throw new InvalidArgumentException();
    $directory = metricsDirectory();
    if ($directory !== null) metricsRecord($events, $directory);
    http_response_code(204);
} catch (JsonException | InvalidArgumentException $error) {http_response_code(400);}
catch (Throwable $error) {http_response_code(204); /* Best-effort counters must never interrupt reading. */}
