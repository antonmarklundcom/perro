<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
$blocked = str_starts_with($path, '/storage/')
    || str_starts_with($path, '/includes/')
    || str_starts_with($path, '/tools/')
    || preg_match('/\.md$/i', $path) === 1
    || in_array($path, ['/config.php', '/.htaccess', '/README.md', '/CONFIGURACION.md'], true);
if ($blocked) {
    http_response_code(404);
    exit;
}
if ($path !== '/' && is_file($file)) {
    return false;
}
$_GET['path'] = trim($path, '/');
require __DIR__ . '/index.php';
