<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('perro_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");

const PERRO_ROOT = __DIR__ . '/..';
const PERRO_STORAGE = PERRO_ROOT . '/storage';
const PERRO_DATA = PERRO_STORAGE . '/data';
const PERRO_UPLOADS = PERRO_STORAGE . '/uploads';

foreach ([PERRO_STORAGE, PERRO_DATA, PERRO_UPLOADS] as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

foreach (['dogs', 'submissions', 'reports', 'moderation', 'settings'] as $dataset) {
    $file = PERRO_DATA . '/' . $dataset . '.json';
    if (!is_file($file)) {
        @file_put_contents($file, "[]\n", LOCK_EX);
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text(string $key, int $max = 5000): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function now_iso(): string
{
    return date(DATE_ATOM);
}

function request_path(): string
{
    if (isset($_GET['path'])) {
        return trim((string) $_GET['path'], '/');
    }
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    return trim(rawurldecode($path), '/');
}

function app_url(string $path = ''): string
{
    global $config;
    return rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
}

function route_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . route_url($path), true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $provided = (string) ($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        render_error_page('La sesión venció', 'Volvé a cargar la página e intentá nuevamente.');
        exit;
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function is_admin(): bool
{
    return ($_SESSION['perro_admin'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', 'Ingresá para continuar.');
        redirect('admin');
    }
}

function verify_admin_login(string $username, string $password): bool
{
    global $config;
    $lastAttempt = (int) ($_SESSION['login_last_attempt'] ?? 0);
    if ($lastAttempt > time() - 2) {
        return false;
    }
    $_SESSION['login_last_attempt'] = time();
    $userOk = hash_equals((string) $config['admin_username'], $username);
    $passwordOk = verify_admin_password($password);
    return $userOk && $passwordOk;
}

function verify_admin_password(string $password): bool
{
    global $config;
    if (function_exists('find_record')) {
        $settings = find_record('settings', 'admin');
        if (!empty($settings['password_hash'])) {
            return password_verify($password, (string) $settings['password_hash']);
        }
    }
    return hash_equals((string) $config['admin_password_sha256'], hash('sha256', $password));
}

function slugify(string $value): string
{
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower($converted !== false ? $converted : $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: 'perro';
    return trim($value, '-') ?: 'perro';
}

function random_id(string $prefix = ''): string
{
    return $prefix . date('ymd') . '-' . bin2hex(random_bytes(5));
}

function valid_whatsapp(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?: '';
    if (str_starts_with($digits, '0')) {
        $digits = '595' . ltrim($digits, '0');
    }
    if (!str_starts_with($digits, '595') || strlen($digits) < 12 || strlen($digits) > 13) {
        return '';
    }
    return $digits;
}

function method_is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}
