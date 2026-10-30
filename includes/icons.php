<?php
declare(strict_types=1);

function icon(string $name, int $size = 24): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'cart' => '<circle cx="8" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/><path d="M3 4h2.2l1.8 9.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.5L21 8H7"/>',
        'inbox' => '<path d="M12 3v10"/><path d="M8 9l4 4 4-4"/><path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/><path d="M4 14h4l1.5 2h5L16 14h4"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M16 14h3"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.2"/><path d="M3.5 19c.7-2.8 2.8-4.2 5.5-4.2s4.8 1.4 5.5 4.2"/><path d="M16 14.8c1.5.3 2.7 1.2 3.4 3.2"/>',
        'percent' => '<circle cx="7.5" cy="7.5" r="2.2"/><circle cx="16.5" cy="16.5" r="2.2"/><path d="M18 6L6 18"/>',
        'chart' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.8 5.8l1.6 1.6M16.6 16.6l1.6 1.6M18.2 5.8l-1.6 1.6M7.4 16.6l-1.6 1.6"/>',
        'logout' => '<path d="M10 7V5H5v14h5v-2"/><path d="M10 12h9"/><path d="M16 8l4 4-4 4"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="M7 7l1 13h8l1-13"/><path d="M10 11v5M14 11v5"/>',
        'edit' => '<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"/><path d="M13.5 6.5l3 3"/>',
        'check' => '<path d="M5 12.5l4.2 4.2L19 7"/>',
        'alert' => '<path d="M12 4l9 16H3L12 4z"/><path d="M12 10v4"/><path d="M12 16.6h.01"/>',
        'print' => '<path d="M7 9V4h10v5"/><path d="M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="13" width="10" height="7" rx="1"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="8" cy="8" r="1.2"/>',
        'barcode' => '<path d="M5 6v12M8 6v12M11 6v12M14 6v12M17 7v10M20 6v12"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/>',
        'cash' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.4"/><path d="M6 9.5h.01M18 14.5h.01"/>',
        'trend' => '<path d="M4 16l5-5 3.2 3.2L20 7"/><path d="M14 7h6v6"/>',
        'user' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.1-3 3.3-4.5 7-4.5s5.9 1.5 7 4.5"/>',
        'store' => '<path d="M4 10l1.2-5h13.6L20 10"/><path d="M4 10h16v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9z"/><path d="M9 20v-5h6v5"/>',
        'receipt' => '<path d="M7 3h10v18l-2.2-1.5L12.5 21 10 19.4 7.8 21 6 19.5V3z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'minus' => '<path d="M5 12h14"/>',
    ];
    $inner = $paths[$name] ?? $paths['box'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
