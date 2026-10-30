<?php
declare(strict_types=1);

function seed_units(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM bp_units')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $st = $pdo->prepare('INSERT INTO bp_units (name) VALUES (?)');
    foreach (['Piece', 'Kg', 'Gram', 'Liter', 'Box', 'Pack', 'Dozen'] as $name) {
        $st->execute([$name]);
    }
}

function seed_sample(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM bp_products')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $catSt = $pdo->prepare('INSERT INTO bp_categories (name, created_at) VALUES (?, ?)');
    foreach (['Grocery', 'Beverages', 'Household'] as $name) {
        $catSt->execute([$name, $now]);
    }
    $brandSt = $pdo->prepare('INSERT INTO bp_brands (name, created_at) VALUES (?, ?)');
    foreach (['Local', 'National'] as $name) {
        $brandSt->execute([$name, $now]);
    }
    $ids = static function (PDO $pdo, string $table): array {
        $out = [];
        foreach ($pdo->query('SELECT id, name FROM ' . $table) as $row) {
            $out[$row['name']] = (int) $row['id'];
        }
        return $out;
    };
    $cats = $ids($pdo, 'bp_categories');
    $brands = $ids($pdo, 'bp_brands');
    $units = $ids($pdo, 'bp_units');
    $st = $pdo->prepare('INSERT INTO bp_products (name, barcode, product_code, unit_id, category_id, brand_id, cost_price, sale_price, stock_qty, reorder_level, active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)');
    $rows = [
        ['Basmati Rice 5kg', '1000001', 'RICE5', 'Pack', 'Grocery', 'Local', 900, 1100, 20, 5],
        ['Cooking Oil 1L', '1000002', 'OIL1', 'Liter', 'Grocery', 'National', 450, 520, 30, 5],
        ['Milk Pack 1L', '1000003', 'MILK1', 'Liter', 'Beverages', 'National', 180, 220, 40, 8],
        ['Sugar 1kg', '1000004', 'SUGAR1', 'Kg', 'Grocery', 'Local', 150, 180, 50, 10],
        ['Tea 900g', '1000005', 'TEA1', 'Pack', 'Grocery', 'National', 800, 980, 15, 4],
        ['Mineral Water 1.5L', '1000006', 'WATER1', 'Piece', 'Beverages', 'National', 40, 60, 80, 12],
        ['Soap Bar', '1000007', 'SOAP1', 'Piece', 'Household', 'Local', 50, 80, 60, 10],
        ['Dishwash Liquid', '1000008', 'DISH1', 'Piece', 'Household', 'Local', 120, 180, 3, 5],
    ];
    foreach ($rows as $row) {
        $st->execute([
            $row[0], $row[1], $row[2],
            $units[$row[3]] ?? null,
            $cats[$row[4]] ?? null,
            $brands[$row[5]] ?? null,
            number_format((float) $row[6], 2, '.', ''),
            number_format((float) $row[7], 2, '.', ''),
            number_format((float) $row[8], 3, '.', ''),
            number_format((float) $row[9], 3, '.', ''),
            $now,
        ]);
    }
}
