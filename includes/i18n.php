<?php
declare(strict_types=1);

function language_choices(): array
{
    return [
        'en' => 'English',
        'az' => 'Azərbaycan',
    ];
}

function currency_choices(): array
{
    return [
        '₼' => '₼ — Azerbaijani manat (AZN)',
        'Rs' => 'Rs — Pakistani rupee',
        '$' => '$ — US dollar',
        '€' => '€ — Euro',
        '£' => '£ — British pound',
        '₺' => '₺ — Turkish lira',
        '₽' => '₽ — Russian ruble',
        'د.إ' => 'د.إ — UAE dirham',
        '﷼' => '﷼ — Saudi riyal',
        '₹' => '₹ — Indian rupee',
        '৳' => '৳ — Bangladeshi taka',
    ];
}

function timezone_choices(): array
{
    return [
        'Asia/Baku' => 'Asia/Baku — Azerbaijan',
        'Asia/Karachi' => 'Asia/Karachi — Pakistan',
        'Asia/Dubai' => 'Asia/Dubai — United Arab Emirates',
        'Asia/Riyadh' => 'Asia/Riyadh — Saudi Arabia',
        'Asia/Kolkata' => 'Asia/Kolkata — India',
        'Asia/Dhaka' => 'Asia/Dhaka — Bangladesh',
        'Europe/Istanbul' => 'Europe/Istanbul — Türkiye',
        'Asia/Singapore' => 'Asia/Singapore',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur — Malaysia',
        'Europe/London' => 'Europe/London — United Kingdom',
        'UTC' => 'UTC',
    ];
}

function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? $_COOKIE['bp_lang'] ?? 'en';
    if (!is_string($lang) || !isset(language_choices()[$lang])) {
        return 'en';
    }
    return $lang;
}

function set_lang(string $lang): void
{
    if (!isset(language_choices()[$lang])) {
        $lang = 'en';
    }
    $_SESSION['lang'] = $lang;
    unset($GLOBALS['bp_lang_code'], $GLOBALS['bp_lang_map']);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $path = web_base();
    setcookie('bp_lang', $lang, [
        'expires' => time() + 60 * 60 * 24 * 400,
        'path' => $path === '' ? '/' : $path,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    $_COOKIE['bp_lang'] = $lang;
}

function translations(): array
{
    $lang = current_lang();
    if (($GLOBALS['bp_lang_code'] ?? '') === $lang && isset($GLOBALS['bp_lang_map']) && is_array($GLOBALS['bp_lang_map'])) {
        return $GLOBALS['bp_lang_map'];
    }
    $map = [];
    if ($lang !== 'en') {
        $file = __DIR__ . '/lang/' . $lang . '.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $map = $loaded;
            }
        }
    }
    $GLOBALS['bp_lang_code'] = $lang;
    $GLOBALS['bp_lang_map'] = $map;
    return $map;
}

function t(string $text, array $vars = []): string
{
    $map = translations();
    $out = array_key_exists($text, $map) ? (string) $map[$text] : $text;
    foreach ($vars as $key => $value) {
        $out = str_replace('{' . $key . '}', (string) $value, $out);
    }
    return $out;
}

function tx(string $text, array $vars = []): string
{
    return e(t($text, $vars));
}

function month_names(): array
{
    $names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $out = [];
    foreach ($names as $num => $name) {
        $out[$num] = t($name);
    }
    return $out;
}

function long_date(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    return t(date('l', $timestamp)) . ', ' . date('d', $timestamp) . ' ' . t(date('M', $timestamp)) . ' ' . date('Y', $timestamp);
}
