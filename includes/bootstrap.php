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
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");
header('Cache-Control: no-store');

const PERRO_ROOT = __DIR__ . '/..';
const PERRO_STORAGE = PERRO_ROOT . '/storage';
const PERRO_DATA = PERRO_STORAGE . '/data';
const PERRO_UPLOADS = PERRO_STORAGE . '/uploads';

// Fingerprint application files only; never include credentials or production data.
function perro_release_id(): string
{
    static $id;
    if ($id === null) {
        $hashes = [];
        foreach (['index.php', 'router.php', 'includes/bootstrap.php', 'includes/data.php', 'includes/legal.php', 'includes/moderation.php', 'includes/accounts.php', 'includes/experience.php', 'includes/render.php', 'assets/css/site.css', 'assets/js/site.js'] as $file) {
            $hashes[] = hash('sha256', str_replace("\r\n", "\n", file_get_contents(PERRO_ROOT . '/' . $file)));
        }
        $id = 'perro-' . substr(hash('sha256', implode('', $hashes)), 0, 12);
    }
    return $id;
}
header('X-Perro-Release: ' . perro_release_id());

foreach ([PERRO_STORAGE, PERRO_DATA, PERRO_UPLOADS] as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

foreach (['dogs', 'submissions', 'reports', 'moderation', 'settings', 'security'] as $dataset) {
    $file = PERRO_DATA . '/' . $dataset . '.json';
    if (!is_file($file)) {
        $newFile = @fopen($file, 'x');
        if ($newFile) {
            fwrite($newFile, "[]\n");
            fclose($newFile);
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text(string $key, int $max = 5000): string
{
    $input = $_POST[$key] ?? '';
    $value = is_string($input) ? trim($input) : '';
    if (function_exists('mb_substr')) return mb_substr($value, 0, $max);
    return preg_match('/^.{0,' . max(0, $max) . '}/us', $value, $match) === 1 ? $match[0] : '';
}

function checked(string $key): bool
{
    return ($_POST[$key] ?? null) === '1';
}

function now_iso(): string
{
    return date(DATE_ATOM);
}

function request_path(): string
{
    if (isset($_GET['path'])) {
        return is_string($_GET['path']) ? trim($_GET['path'], '/') : '';
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
    $provided = text('csrf_token', 100);
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        render_error_page('La sesión venció', 'Volvé a cargar la página e intentá nuevamente.', 419);
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
    if (($_SESSION['perro_admin'] ?? false) !== true) return false;
    $accountId = current_admin_account_id();
    if ($accountId !== 'admin') {
        $account = find_record('settings', $accountId);
        if (!$account || empty($account['active']) || !empty($account['disabled'])) { unset($_SESSION['perro_admin']); return false; }
    }
    if (($_SESSION['admin_last_seen'] ?? 0) < time() - 1800
        || ($_SESSION['admin_started'] ?? 0) < time() - 28800
        || !hash_equals(admin_credential_version(), (string) ($_SESSION['admin_version'] ?? ''))) {
        unset($_SESSION['perro_admin']);
        return false;
    }
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function current_admin_account_id(): string
{
    return is_string($_SESSION['admin_account_id'] ?? null) ? $_SESSION['admin_account_id'] : 'admin';
}

function password_input(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && strlen($value) <= 300 ? $value : '';
}

function admin_credential_version(?string $accountId = null): string
{
    global $config;
    $accountId ??= current_admin_account_id();
    $settings = find_record('settings', $accountId);
    if ($accountId !== 'admin') return hash('sha256', $accountId . '|' . ($settings['password_hash'] ?? '') . '|' . (int) ($settings['active'] ?? false) . '|' . (int) ($settings['disabled'] ?? false));
    return hash('sha256', (string) ($settings['password_hash'] ?? $config['admin_password_sha256']));
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
    return with_data_lock(static function () use ($config, $username, $password): bool {
        $file = PERRO_DATA . '/login-attempts.json';
        $buckets = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
        if (!is_array($buckets)) throw new RuntimeException('No se puede leer el control de acceso.');
        $buckets = array_filter($buckets, static fn(array $b): bool => ($b['started'] ?? 0) > time() - 900);
        // Use the server-observed address, never an untrusted forwarded header.
        $key = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), admin_credential_version('admin'));
        $bucket = $buckets[$key] ?? ['started' => time(), 'count' => 0];
        if ($bucket['count'] >= 10) return false;
        $bucket['count']++;
        $accountId = hash_equals((string) $config['admin_username'], $username) ? 'admin' : 'admin-user-' . hash('sha256', strtolower(trim($username)));
        $ok = verify_admin_password($password, $accountId);
        if ($ok) unset($buckets[$key]); else $buckets[$key] = $bucket;
        if (count($buckets) > 2000) $buckets = array_slice($buckets, -2000, null, true);
        if (@file_put_contents($file, json_encode($buckets), LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el control de acceso.');
        }
        if ($ok) $_SESSION['admin_account_id'] = $accountId;
        return $ok;
    });
}

function verify_admin_password(string $password, ?string $accountId = null): bool
{
    global $config;
    $accountId ??= current_admin_account_id();
    if (function_exists('find_record')) {
        $settings = find_record('settings', $accountId);
        if ($accountId !== 'admin' && (!$settings || empty($settings['active']) || !empty($settings['disabled']))) return false;
        if (!empty($settings['password_hash'])) {
            return password_verify($password, (string) $settings['password_hash']);
        }
    }
    if ($accountId !== 'admin') return false;
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

function ini_bytes(string $value): int
{
    $number = (int) $value;
    return match (strtolower(substr(trim($value), -1))) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

set_exception_handler(static function (Throwable $error): void {
    $GLOBALS['perro_storage_error'] = true;
    error_log('Perro storage/request failure: ' . get_class($error));
    http_response_code(503);
    if (function_exists('render_error_page')) {
        render_error_page('No pudimos completar la operación', 'No podemos confirmar el guardado. Guardá tu referencia y avisale al equipo. Intentá nuevamente cuando se revise el almacenamiento.', 503);
    } else {
        echo 'Servicio temporalmente no disponible.';
    }
});
