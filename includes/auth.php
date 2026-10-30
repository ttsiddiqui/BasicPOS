<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['uid']) || !app_installed()) {
        return null;
    }
    $st = db()->prepare('SELECT id, username, full_name, role FROM bp_users WHERE id = ?');
    $st->execute([(int) $_SESSION['uid']]);
    $row = $st->fetch();
    if (!$row) {
        unset($_SESSION['uid']);
        return null;
    }
    $user = $row;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('index.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('error', 'Only the admin can open that page.');
        redirect('pos.php');
    }
    return $user;
}

function home_for(array $user): string
{
    return ($user['role'] ?? '') === 'cashier' ? 'pos.php' : 'dashboard.php';
}

function attempt_login(string $username, string $password): bool
{
    $username = trim($username);
    $fails = (int) ($_SESSION['login_fails'] ?? 0);
    $last = (int) ($_SESSION['login_fail_at'] ?? 0);
    if ($fails >= 8 && (time() - $last) < 60) {
        flash('error', 'Too many tries. Wait a minute, then try again.');
        return false;
    }
    if ($username === '' || $password === '' || strlen($password) > 200) {
        $_SESSION['login_fails'] = $fails + 1;
        $_SESSION['login_fail_at'] = time();
        flash('error', 'Wrong username or password.');
        return false;
    }
    $st = db()->prepare('SELECT * FROM bp_users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    $hash = is_array($user) ? (string) $user['password_hash'] : '';
    if (!$user || !password_verify($password, $hash)) {
        $_SESSION['login_fails'] = $fails + 1;
        $_SESSION['login_fail_at'] = time();
        flash('error', 'Wrong username or password.');
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['login_fails'], $_SESSION['login_fail_at']);
    if ($password === 'admin123' || $password === 'cashier123') {
        $_SESSION['weak_password'] = 1;
    } else {
        unset($_SESSION['weak_password']);
    }
    return true;
}
