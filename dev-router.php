<?php
// Local dev router for `php -S` (which ignores .htaccess).
// Run from repo root: php -S 127.0.0.1:8000 -t public_html dev-router.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public_html' . $path;
if ($path !== '/' && is_file($file)) {
    return false; // serve static file directly
}
require __DIR__ . '/public_html/index.php';
