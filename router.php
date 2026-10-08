<?php

declare(strict_types=1);

$path = rawurldecode(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$file = __DIR__ . $path;
$blocked = preg_match('#(?:^|/)[.]#', $path) === 1 || str_contains($path, '\\')
    || str_starts_with($path, '/storage/')
    || str_starts_with($path, '/includes/')
    || str_starts_with($path, '/tools/')
    || preg_match('/\.md$/i', $path) === 1
    || in_array($path, ['/config.php', '/.htaccess', '/README.md', '/CONFIGURACION.md'], true);
if ($blocked) {
    http_response_code(404);
    exit;
}
if (($path === '/favicon.svg' || str_starts_with($path, '/assets/')) && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
