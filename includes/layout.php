<?php
declare(strict_types=1);

function html_head(string $title, string $bodyClass = ''): void
{
    $currency = 'Rs';
    if (app_installed() && !is_install_request()) {
        $currency = currency_symbol();
    }
    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#102033">';
    echo '<title>' . e($title) . ' · BasicPOS</title>';
    echo '<link rel="icon" href="' . e(asset('assets/favicon.svg')) . '" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="' . e(asset('assets/css/app.css')) . '">';
    echo '</head>';
    echo '<body class="' . e($bodyClass) . '" data-base="' . e(web_base()) . '" data-currency="' . e($currency) . '"' . (($bodyClass !== '' && str_contains($bodyClass, 'print-now')) ? ' data-print="1"' : '') . '>';
}

function html_foot(bool $withScript = true): void
{
    if ($withScript) {
        echo '<script src="' . e(asset('assets/js/app.js')) . '"></script>';
    }
    echo '</body></html>';
}

function render_flash(): void
{
    $flash = take_flash();
    if (!$flash) {
        return;
    }
    $type = ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
    echo '<div class="flash flash-' . $type . '" role="status">' . e((string) ($flash['message'] ?? '')) . '</div>';
}

function weak_password_note(): void
{
    if (empty($_SESSION['weak_password'])) {
        return;
    }
    $user = current_user();
    if (!$user) {
        return;
    }
    if ($user['role'] === 'admin') {
        $text = 'You are still using a starting password. Change it in Settings.';
    } else {
        $text = 'This cashier login is still using a starting password. Ask the admin to change it.';
    }
    echo '<div class="flash flash-error no-print">' . e($text) . '</div>';
}

function nav_items(): array
{
    return [
        ['dashboard.php', 'dashboard', 'Dashboard', 'grid'],
        ['products.php', 'products', 'Products', 'box'],
        ['pos.php', 'pos', 'Sale', 'cart'],
        ['inventory.php', 'inventory', 'Stock in', 'inbox'],
        ['expenses.php', 'expenses', 'Expenses', 'wallet'],
        ['hr.php', 'hr', 'HR', 'users'],
        ['discounts.php', 'discounts', 'Discounts', 'percent'],
        ['reports.php', 'reports', 'Reports', 'chart'],
    ];
}

function layout_start(string $title, string $active = ''): void
{
    $user = current_user();
    $role = (string) ($user['role'] ?? '');
    $pos = $active === 'pos';
    $class = 'role-' . $role . ($pos ? ' layout-pos' : '');
    html_head($title, $class);
    echo '<a class="skip" href="#content">Skip to content</a>';
    if ($role === 'cashier') {
        echo '<header class="cashier-bar">';
        echo '<a class="brand brand-compact" href="' . e(url('pos.php')) . '"><span class="brand-mark">BP</span><span><strong>BasicPOS</strong><small>by WebC</small></span></a>';
        echo '<strong class="cashier-title">Sale</strong>';
        echo '<div class="who">';
        echo '<span class="avatar">' . e(bp_upper(bp_substr((string) $user['full_name'], 0, 1))) . '</span>';
        echo '<span class="who-name"><strong>' . e((string) $user['full_name']) . '</strong><small>Cashier</small></span>';
        echo '</div>';
        echo '<form method="post" action="' . e(url('logout.php')) . '">';
        echo csrf_field();
        echo '<button class="btn btn-ghost btn-sm" type="submit">' . icon('logout', 20) . ' Log out</button>';
        echo '</form>';
        echo '</header>';
    echo '<div class="content' . ($pos ? ' content-pos' : '') . '" id="content">';
    if (!$pos) {
        weak_password_note();
        render_flash();
    }
    return;
}

    echo '<div class="shell">';
    echo '<aside class="sidebar" id="sidebar">';
    echo '<a class="brand" href="' . e(url('dashboard.php')) . '"><span class="brand-mark">BP</span><span><strong>BasicPOS</strong><small>by WebC</small></span></a>';
    echo '<nav>';
    foreach (nav_items() as $item) {
        $isActive = $item[1] === $active;
        echo '<a class="nav-link' . ($isActive ? ' active' : '') . '" href="' . e(url($item[0])) . '"' . ($isActive ? ' aria-current="page"' : '') . '>';
        echo icon($item[3], 26);
        echo '<span class="label">' . e($item[2]) . '</span>';
        echo '</a>';
    }
    echo '</nav>';
    echo '<div class="sidebar-foot">';
    $settingsActive = $active === 'settings';
    echo '<a class="nav-link' . ($settingsActive ? ' active' : '') . '" href="' . e(url('settings.php')) . '">' . icon('gear', 26) . '<span class="label">Settings</span></a>';
    echo '<form method="post" action="' . e(url('logout.php')) . '">';
    echo csrf_field();
    echo '<button class="nav-link" type="submit">' . icon('logout', 26) . '<span class="label">Log out</span></button>';
    echo '</form>';
    echo '</div>';
    echo '</aside>';
    echo '<div class="scrim" id="scrim"></div>';
    echo '<div class="main">';
    echo '<header class="topbar">';
    echo '<button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Open menu">' . icon('menu', 26) . '</button>';
    echo '<div class="topbar-title"><strong>' . e(setting('business_name', 'BasicPOS')) . '</strong><small>' . e($title) . '</small></div>';
    echo '<div class="who">';
    echo '<span class="avatar">' . e(bp_upper(bp_substr((string) ($user['full_name'] ?? 'A'), 0, 1))) . '</span>';
    echo '<span class="who-name"><strong>' . e((string) ($user['full_name'] ?? '')) . '</strong><small>Admin</small></span>';
    echo '</div>';
    echo '</header>';
    echo '<div class="content' . ($pos ? ' content-pos' : '') . '" id="content">';
    if (!$pos) {
        weak_password_note();
        render_flash();
    }
}

function layout_end(): void
{
    echo '</div>';
    if ((current_user()['role'] ?? '') !== 'cashier') {
        echo '</div></div>';
    }
    html_foot(true);
}

function empty_state(string $iconName, string $title, string $text): string
{
    return '<div class="empty">' . icon($iconName, 42) . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p></div>';
}
