<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('BasicPOS needs PHP 8.0 or newer. In cPanel, open Select PHP Version and choose 8.0 or newer.');
}

function app_root(): string
{
    return dirname(__DIR__);
}

function config_path(): string
{
    return app_root() . '/config.php';
}

function lock_path(): string
{
    return app_root() . '/install.lock';
}

function is_install_request(): bool
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php';
}

function app_installed(): bool
{
    return is_file(config_path()) && is_file(lock_path());
}

function web_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $app = realpath(app_root());
    if ($doc && $app && str_starts_with($app, $doc)) {
        $rel = substr($app, strlen($doc));
        $base = rtrim(str_replace('\\', '/', $rel), '/');
        return $base;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $base = '';
    } else {
        $base = $dir;
    }
    return $base;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = web_base();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset(string $path): string
{
    $full = app_root() . '/' . ltrim($path, '/');
    $version = is_file($full) ? (string) filemtime($full) : '1';
    return url($path) . '?v=' . $version;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bp_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function bp_substr(string $value, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
}

function bp_upper(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value) : strtoupper($value);
}

function bp_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BASICPOSSESS');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $cookiePath = web_base();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath === '' ? '/' : $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

require_once __DIR__ . '/i18n.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    $ok = is_string($sent) && is_string($known) && $known !== '' && hash_equals($known, $sent);
    if (!$ok) {
        http_response_code(419);
        exit(t('Your session expired. Go back, refresh the page, and try again.'));
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function post_str(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function post_float(string $key): float
{
    $raw = str_replace([',', ' '], '', (string) ($_POST[$key] ?? '0'));
    return is_numeric($raw) ? (float) $raw : 0.0;
}

function valid_date(string $value): ?string
{
    $dt = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    if (!is_file(config_path())) {
        $cfg = [];
        return $cfg;
    }
    $loaded = require config_path();
    $cfg = is_array($loaded) ? $loaded : [];
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config();
    $host = (string) ($c['db_host'] ?? 'localhost');
    $port = (string) ($c['db_port'] ?? '3306');
    $name = (string) ($c['db_name'] ?? '');
    $user = (string) ($c['db_user'] ?? '');
    $pass = (string) ($c['db_pass'] ?? '');
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_FOUND_ROWS => true,
    ]);
    return $pdo;
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Type: text/html; charset=UTF-8');
}

if (getenv('BP_DEBUG') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $e): void {
    error_log('BasicPOS: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (getenv('BP_DEBUG') === '1' || is_install_request()) {
        echo '<p style="font-family:sans-serif;padding:24px">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>BasicPOS</title><p style="font-family:sans-serif;padding:24px">' . e(t('Something went wrong. Please go back and try again.')) . '</p>';
});

if (!app_installed() && !is_install_request()) {
    redirect('install.php');
}

if (!is_install_request() && app_installed()) {
    try {
        db();
    } catch (Throwable $e) {
        error_log('BasicPOS database connection failed: ' . $e->getMessage());
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>BasicPOS</title><p style="font-family:sans-serif;padding:24px">' . e(t('BasicPOS cannot reach the database. Check the database name, user, and password in config.php, then reload this page.')) . '</p>';
        exit;
    }
    $tz = 'Asia/Karachi';
    try {
        $row = db()->query('SELECT timezone FROM bp_settings WHERE id = 1')->fetch();
        if ($row && is_string($row['timezone']) && in_array($row['timezone'], timezone_identifiers_list(), true)) {
            $tz = $row['timezone'];
        }
    } catch (Throwable $e) {
        error_log('BasicPOS settings read failed: ' . $e->getMessage());
    }
    date_default_timezone_set($tz);
    try {
        $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P');
        db()->exec('SET time_zone = ' . db()->quote($offset));
    } catch (Throwable $e) {
        error_log('BasicPOS timezone set failed: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/logic.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
