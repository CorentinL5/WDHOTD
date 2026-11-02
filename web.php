<?php
/** Shared public-response headers. This file never loads private configuration. */
function webHeaders(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' https://upload.wikimedia.org https://thumb.wikimedia.org; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000');
}

function publicSiteURL(?string $url): ?string {
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) return null;
    $host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '[]'));
    if (!str_contains($host, '.') || preg_match('/\.(test|local|localhost|invalid|example)$/', $host) || (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) return null;
    return rtrim($url, '/');
}

function assetURL(string $path): string {
    return $path . '?v=' . (string)(filemtime(__DIR__ . '/' . $path) ?: '1');
}
