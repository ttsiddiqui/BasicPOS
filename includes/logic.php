<?php
declare(strict_types=1);

function settings(): array
{
    if (!isset($GLOBALS['bp_settings']) || !is_array($GLOBALS['bp_settings'])) {
        $row = db()->query('SELECT * FROM bp_settings WHERE id = 1')->fetch();
        $GLOBALS['bp_settings'] = $row ?: [];
    }
    return $GLOBALS['bp_settings'];
}

function settings_reload(): void
{
    unset($GLOBALS['bp_settings']);
}

function setting(string $key, string $default = ''): string
{
    $value = settings()[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function currency_symbol(): string
{
    if (!app_installed()) {
        return 'Rs';
    }
    return setting('currency_symbol', 'Rs');
}

function money(float|int|string|null $amount): string
{
    $n = round((float) $amount, 2);
    $sign = $n < 0 ? '-' : '';
    return $sign . currency_symbol() . ' ' . number_format(abs($n), 2);
}

function money_str(float|int|string|null $amount): string
{
    return number_format(round((float) $amount, 2), 2, '.', '');
}

function qty_fmt(float|int|string|null $qty): string
{
    $n = round((float) $qty, 3);
    if (abs($n - round($n)) < 0.0005) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
}

function qty_str(float|int|string|null $qty): string
{
    return number_format(round((float) $qty, 3), 3, '.', '');
}

function when_fmt(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('d', $t) . ' ' . t(date('M', $t)) . ' ' . date('Y', $t) . ', ' . date('g:i A', $t) : $dt;
}

function day_fmt(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('d', $t) . ' ' . t(date('M', $t)) . ' ' . date('Y', $t) : $d;
}

function month_label(int $year, int $month): string
{
    $name = date('F', mktime(0, 0, 0, $month, 1, $year));
    return t($name) . ' ' . $year;
}

function is_duplicate_key(Throwable $e): bool
{
    return $e instanceof PDOException && (string) $e->getCode() === '23000';
}

function existing_id(string $table, int $id): ?int
{
    $allowed = [
        'bp_units' => true,
        'bp_categories' => true,
        'bp_brands' => true,
        'bp_products' => true,
        'bp_employees' => true,
    ];
    if ($id <= 0) {
        return null;
    }
    if (!isset($allowed[$table])) {
        throw new RuntimeException(t('That choice is not valid.'));
    }
    $st = db()->prepare('SELECT id FROM ' . $table . ' WHERE id = ?');
    $st->execute([$id]);
    if (!$st->fetch()) {
        throw new RuntimeException(t('Please choose a valid option from the list.'));
    }
    return $id;
}

function save_named(string $table, string $name, ?int $id = null): void
{
    $allowed = [
        'bp_categories' => ['Category', 80],
        'bp_brands' => ['Brand', 80],
        'bp_units' => ['Unit', 40],
    ];
    if (!isset($allowed[$table])) {
        throw new RuntimeException(t('That list is not valid.'));
    }
    [$label, $max] = $allowed[$table];
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException(t('{label} name is required.', ['label' => t($label)]));
    }
    if (bp_strlen($name) > $max) {
        throw new RuntimeException(t('That name is too long.'));
    }
    try {
        if ($id) {
            existing_id($table, $id);
            if ($table === 'bp_units') {
                $st = db()->prepare('UPDATE bp_units SET name = ? WHERE id = ?');
            } elseif ($table === 'bp_brands') {
                $st = db()->prepare('UPDATE bp_brands SET name = ? WHERE id = ?');
            } else {
                $st = db()->prepare('UPDATE bp_categories SET name = ? WHERE id = ?');
            }
            $st->execute([$name, $id]);
        } elseif ($table === 'bp_units') {
            $st = db()->prepare('INSERT INTO bp_units (name) VALUES (?)');
            $st->execute([$name]);
        } elseif ($table === 'bp_brands') {
            $st = db()->prepare('INSERT INTO bp_brands (name, created_at) VALUES (?, ?)');
            $st->execute([$name, date('Y-m-d H:i:s')]);
        } else {
            $st = db()->prepare('INSERT INTO bp_categories (name, created_at) VALUES (?, ?)');
            $st->execute([$name, date('Y-m-d H:i:s')]);
        }
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new RuntimeException(t('That name already exists.'));
        }
        throw $e;
    }
}

function delete_named(string $table, int $id): void
{
    $allowed = ['bp_categories' => true, 'bp_brands' => true, 'bp_units' => true];
    if (!isset($allowed[$table]) || $id <= 0) {
        throw new RuntimeException(t('That item was not found.'));
    }
    try {
        $st = db()->prepare('DELETE FROM ' . $table . ' WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() < 1) {
            throw new RuntimeException(t('That item was not found.'));
        }
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new RuntimeException(t('This is used by a product, so it cannot be removed.'));
        }
        throw $e;
    }
}

function product_code_taken(string $column, string $value, ?int $ignoreId): bool
{
    if ($value === '' || !in_array($column, ['barcode', 'product_code'], true)) {
        return false;
    }
    $sql = 'SELECT id FROM bp_products WHERE ' . $column . ' = ?';
    $params = [$value];
    if ($ignoreId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (bool) $st->fetch();
}

function save_product(array $in, ?int $id = null): int
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException(t('Product name is required.'));
    }
    if (bp_strlen($name) > 160) {
        throw new RuntimeException(t('Product name is too long.'));
    }
    $barcode = trim((string) ($in['barcode'] ?? ''));
    $code = trim((string) ($in['product_code'] ?? ''));
    if (bp_strlen($barcode) > 64 || bp_strlen($code) > 64) {
        throw new RuntimeException(t('Barcode or product code is too long.'));
    }
    if (product_code_taken('barcode', $barcode, $id)) {
        throw new RuntimeException(t('That barcode is already used.'));
    }
    if (product_code_taken('product_code', $code, $id)) {
        throw new RuntimeException(t('That product code is already used.'));
    }
    $cost = round((float) ($in['cost_price'] ?? 0), 2);
    $sale = round((float) ($in['sale_price'] ?? 0), 2);
    $reorder = round((float) ($in['reorder_level'] ?? 0), 3);
    $opening = round((float) ($in['opening_stock'] ?? 0), 3);
    if ($cost < 0 || $sale < 0 || $reorder < 0 || $opening < 0) {
        throw new RuntimeException(t('Prices and quantities cannot be negative.'));
    }
    $unitId = existing_id('bp_units', (int) ($in['unit_id'] ?? 0));
    $categoryId = existing_id('bp_categories', (int) ($in['category_id'] ?? 0));
    $brandId = existing_id('bp_brands', (int) ($in['brand_id'] ?? 0));
    $active = !empty($in['active']) ? 1 : 0;
    $barcodeDb = $barcode === '' ? null : $barcode;
    $codeDb = $code === '' ? null : $code;
    if ($id) {
        existing_id('bp_products', $id);
        $st = db()->prepare('UPDATE bp_products SET name = ?, barcode = ?, product_code = ?, unit_id = ?, category_id = ?, brand_id = ?, cost_price = ?, sale_price = ?, reorder_level = ?, active = ? WHERE id = ?');
        $st->execute([$name, $barcodeDb, $codeDb, $unitId, $categoryId, $brandId, money_str($cost), money_str($sale), qty_str($reorder), $active, $id]);
        return $id;
    }
    $st = db()->prepare('INSERT INTO bp_products (name, barcode, product_code, unit_id, category_id, brand_id, cost_price, sale_price, stock_qty, reorder_level, active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$name, $barcodeDb, $codeDb, $unitId, $categoryId, $brandId, money_str($cost), money_str($sale), qty_str($opening), qty_str($reorder), $active, date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

function delete_product(int $id): string
{
    existing_id('bp_products', $id);
    try {
        $st = db()->prepare('DELETE FROM bp_products WHERE id = ? AND stock_qty = 0');
        $st->execute([$id]);
        if ($st->rowCount() === 1) {
            return 'deleted';
        }
    } catch (PDOException $e) {
        if (!is_duplicate_key($e)) {
            throw $e;
        }
    }
    $st = db()->prepare('UPDATE bp_products SET active = 0 WHERE id = ?');
    $st->execute([$id]);
    return 'hidden';
}

function discount_context(): array
{
    static $ctx = null;
    if ($ctx !== null) {
        return $ctx;
    }
    $discounts = db()->query('SELECT * FROM bp_discounts WHERE active = 1')->fetchAll();
    $map = [];
    $links = db()->query('SELECT discount_id, product_id FROM bp_discount_products')->fetchAll();
    foreach ($links as $link) {
        $map[(int) $link['discount_id']][(int) $link['product_id']] = true;
    }
    $ctx = ['discounts' => $discounts, 'map' => $map];
    return $ctx;
}

function discount_for(int $productId, float $salePrice, ?array $ctx = null): array
{
    $ctx = $ctx ?? discount_context();
    $today = date('Y-m-d');
    $best = 0.0;
    $label = '';
    foreach ($ctx['discounts'] as $d) {
        if (!(int) $d['active']) {
            continue;
        }
        if (!empty($d['starts_on']) && $d['starts_on'] > $today) {
            continue;
        }
        if (!empty($d['ends_on']) && $d['ends_on'] < $today) {
            continue;
        }
        $applies = $d['apply_to'] === 'all' || isset($ctx['map'][(int) $d['id']][$productId]);
        if (!$applies) {
            continue;
        }
        if ($d['discount_type'] === 'percent') {
            $amt = $salePrice * ((float) $d['discount_value'] / 100);
        } else {
            $amt = (float) $d['discount_value'];
        }
        $amt = round($amt, 2);
        if ($amt > $best) {
            $best = $amt;
            $label = (string) $d['name'];
        }
    }
    if ($best > $salePrice) {
        $best = round($salePrice, 2);
    }
    if ($best < 0) {
        $best = 0.0;
    }
    return [
        'unit_discount' => $best,
        'net' => round($salePrice - $best, 2),
        'label' => $label,
    ];
}

function discount_product_ids(int $id): array
{
    $st = db()->prepare('SELECT product_id FROM bp_discount_products WHERE discount_id = ?');
    $st->execute([$id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function save_discount(array $in, ?int $id = null): void
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' || bp_strlen($name) > 120) {
        throw new RuntimeException(t('Enter a discount name (up to 120 characters).'));
    }
    $type = (string) ($in['type'] ?? '');
    if (!in_array($type, ['fixed', 'percent'], true)) {
        throw new RuntimeException(t('Choose a fixed amount or a percentage.'));
    }
    $value = round((float) ($in['value'] ?? 0), 2);
    if ($type === 'percent' && ($value <= 0 || $value > 100)) {
        throw new RuntimeException(t('Percentage must be more than 0 and at most 100.'));
    }
    if ($type === 'fixed' && $value <= 0) {
        throw new RuntimeException(t('Fixed discount must be more than 0.'));
    }
    $apply = (string) ($in['apply_to'] ?? '');
    if (!in_array($apply, ['all', 'selected'], true)) {
        throw new RuntimeException(t('Choose all products or selected products.'));
    }
    $startRaw = trim((string) ($in['starts_on'] ?? ''));
    $endRaw = trim((string) ($in['ends_on'] ?? ''));
    $start = $startRaw === '' ? null : valid_date($startRaw);
    $end = $endRaw === '' ? null : valid_date($endRaw);
    if ($startRaw !== '' && !$start) {
        throw new RuntimeException(t('Start date is not valid.'));
    }
    if ($endRaw !== '' && !$end) {
        throw new RuntimeException(t('End date is not valid.'));
    }
    if ($start && $end && $start > $end) {
        throw new RuntimeException(t('The start date must be on or before the end date.'));
    }
    $ids = [];
    foreach ((array) ($in['product_ids'] ?? []) as $pid) {
        $pid = (int) $pid;
        if ($pid > 0) {
            $ids[$pid] = $pid;
        }
    }
    if ($apply === 'all') {
        $ids = [];
    }
    $active = !empty($in['active']) ? 1 : 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $st = $pdo->prepare('UPDATE bp_discounts SET name = ?, discount_type = ?, discount_value = ?, apply_to = ?, active = ?, starts_on = ?, ends_on = ? WHERE id = ?');
            $st->execute([$name, $type, money_str($value), $apply, $active, $start, $end, $id]);
            if ($st->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM bp_discounts WHERE id = ?');
                $check->execute([$id]);
                if (!$check->fetch()) {
                    throw new RuntimeException(t('That discount was not found.'));
                }
            }
            $pdo->prepare('DELETE FROM bp_discount_products WHERE discount_id = ?')->execute([$id]);
            $discountId = $id;
        } else {
            $st = $pdo->prepare('INSERT INTO bp_discounts (name, discount_type, discount_value, apply_to, active, starts_on, ends_on, created_at) VALUES (?,?,?,?,?,?,?,?)');
            $st->execute([$name, $type, money_str($value), $apply, $active, $start, $end, date('Y-m-d H:i:s')]);
            $discountId = (int) $pdo->lastInsertId();
        }
        if ($ids) {
            $check = $pdo->prepare('SELECT id FROM bp_products WHERE id = ?');
            $link = $pdo->prepare('INSERT INTO bp_discount_products (discount_id, product_id) VALUES (?, ?)');
            $linked = 0;
            foreach ($ids as $pid) {
                $check->execute([$pid]);
                if ($check->fetch()) {
                    $link->execute([$discountId, $pid]);
                    $linked++;
                }
            }
            if ($linked === 0) {
                throw new RuntimeException(t('Pick at least one product.'));
            }
        } elseif ($apply === 'selected') {
            throw new RuntimeException(t('Pick at least one product.'));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function delete_discount(int $id): void
{
    $st = db()->prepare('DELETE FROM bp_discounts WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() < 1) {
        throw new RuntimeException(t('That discount was not found.'));
    }
}

function next_doc_no(PDO $pdo, string $table, string $column, string $prefix): string
{
    $allowed = [
        'bp_sales' => 'invoice_no',
        'bp_purchases' => 'reference_no',
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new RuntimeException(t('Could not make a reference number.'));
    }
    $like = $prefix . '%';
    $st = $pdo->prepare('SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' LIKE ? ORDER BY ' . $column . ' DESC LIMIT 1 FOR UPDATE');
    $st->execute([$like]);
    $last = $st->fetchColumn();
    $n = 1;
    if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
        $n = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function find_product_id_by_code(string $code): ?int
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    $st = db()->prepare('SELECT id FROM bp_products WHERE active = 1 AND barcode = ? LIMIT 1');
    $st->execute([$code]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $st = db()->prepare('SELECT id FROM bp_products WHERE active = 1 AND product_code = ? LIMIT 1');
    $st->execute([$code]);
    $id = $st->fetchColumn();
    return $id ? (int) $id : null;
}

function cart_quantities(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return [];
    }
    $clean = [];
    foreach ($cart as $id => $qty) {
        $id = (int) $id;
        $qty = round((float) $qty, 3);
        if ($id > 0 && $qty > 0) {
            $clean[$id] = $qty;
        }
    }
    return $clean;
}

function cart_set(int $productId, float $qty): void
{
    $qty = round($qty, 3);
    $cart = cart_quantities();
    if ($qty <= 0) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        return;
    }
    $st = db()->prepare('SELECT name, stock_qty, active FROM bp_products WHERE id = ?');
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p || !(int) $p['active']) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        throw new RuntimeException(t('That product is not available.'));
    }
    $stock = round((float) $p['stock_qty'], 3);
    if ($qty - $stock > 0.0005) {
        if ($stock <= 0) {
            unset($cart[$productId]);
            $_SESSION['cart'] = $cart;
            throw new RuntimeException(t('{name} is out of stock.', ['name' => (string) $p['name']]));
        }
        $cart[$productId] = $stock;
        $_SESSION['cart'] = $cart;
        throw new RuntimeException(t('Only {qty} of {name} is in stock.', ['qty' => qty_fmt($stock), 'name' => (string) $p['name']]));
    }
    $cart[$productId] = $qty;
    $_SESSION['cart'] = $cart;
}

function cart_add(int $productId, float $qty = 1): void
{
    $cart = cart_quantities();
    $current = $cart[$productId] ?? 0;
    cart_set($productId, $current + $qty);
}

function cart_lines(): array
{
    $cart = cart_quantities();
    $empty = ['lines' => [], 'subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'count' => 0];
    if (!$cart) {
        $_SESSION['cart'] = [];
        return $empty;
    }
    $ids = array_keys($cart);
    sort($ids);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare('SELECT p.*, COALESCE(u.name, \'\') AS unit_name FROM bp_products p LEFT JOIN bp_units u ON u.id = p.unit_id WHERE p.id IN (' . $in . ')');
    $st->execute($ids);
    $products = [];
    foreach ($st->fetchAll() as $p) {
        $products[(int) $p['id']] = $p;
    }
    $lines = [];
    $subtotal = 0.0;
    $discount = 0.0;
    $newCart = [];
    foreach ($cart as $id => $qty) {
        if (!isset($products[$id]) || !(int) $products[$id]['active']) {
            continue;
        }
        $stock = round((float) $products[$id]['stock_qty'], 3);
        if ($qty - $stock > 0.0005) {
            $qty = $stock;
        }
        if ($qty <= 0) {
            continue;
        }
        $newCart[$id] = $qty;
        $price = round((float) $products[$id]['sale_price'], 2);
        $offer = discount_for($id, $price);
        $gross = round($price * $qty, 2);
        $net = round($offer['net'] * $qty, 2);
        $lineDiscount = round($gross - $net, 2);
        $subtotal += $gross;
        $discount += $lineDiscount;
        $lines[] = [
            'id' => $id,
            'name' => $products[$id]['name'],
            'unit_name' => $products[$id]['unit_name'],
            'qty' => $qty,
            'stock' => $stock,
            'unit_price' => $price,
            'net_unit' => $offer['net'],
            'discount' => $lineDiscount,
            'label' => $offer['label'],
            'line_total' => $net,
        ];
    }
    usort($lines, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));
    $_SESSION['cart'] = $newCart;
    $subtotal = round($subtotal, 2);
    $discount = round($discount, 2);
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => round($subtotal - $discount, 2),
        'count' => count($lines),
    ];
}

function sale_from_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'invoice_no' => (string) $row['invoice_no'],
        'total' => (float) $row['total'],
        'change' => (float) $row['change_amount'],
    ];
}

function complete_sale(array $cart, ?float $received, int $userId, string $token): array
{
    if ($token === '') {
        throw new RuntimeException(t('The cart changed. Please check it and charge again.'));
    }
    $clean = [];
    foreach ($cart as $id => $qty) {
        $id = (int) $id;
        $qty = round((float) $qty, 3);
        if ($id > 0 && $qty > 0) {
            $clean[$id] = $qty;
        }
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id, invoice_no, total, change_amount FROM bp_sales WHERE client_token = ? FOR UPDATE');
        $st->execute([$token]);
        $existing = $st->fetch();
        if ($existing) {
            $pdo->commit();
            return sale_from_row($existing);
        }
        if (!$clean) {
            throw new RuntimeException(t('Add a product before charging.'));
        }
        $ids = array_keys($clean);
        sort($ids);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pst = $pdo->prepare('SELECT * FROM bp_products WHERE id IN (' . $in . ') FOR UPDATE');
        $pst->execute($ids);
        $products = [];
        foreach ($pst->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }
        $unitNames = [];
        foreach ($pdo->query('SELECT id, name FROM bp_units') as $unit) {
            $unitNames[(int) $unit['id']] = (string) $unit['name'];
        }
        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        foreach ($clean as $id => $qty) {
            if (!isset($products[$id]) || !(int) $products[$id]['active']) {
                throw new RuntimeException(t('A product in the cart is no longer available.'));
            }
            $p = $products[$id];
            $stock = round((float) $p['stock_qty'], 3);
            if ($qty - $stock > 0.0005) {
                throw new RuntimeException(t('{name} only has {qty} in stock.', ['name' => (string) $p['name'], 'qty' => qty_fmt($stock)]));
            }
            $salePrice = round((float) $p['sale_price'], 2);
            $offer = discount_for($id, $salePrice);
            $gross = round($salePrice * $qty, 2);
            $net = round($offer['net'] * $qty, 2);
            $lineDiscount = round($gross - $net, 2);
            $subtotal += $gross;
            $discountTotal += $lineDiscount;
            $lines[] = [
                'product_id' => $id,
                'product_name' => $p['name'],
                'unit_name' => $unitNames[(int) ($p['unit_id'] ?? 0)] ?? '',
                'qty' => $qty,
                'unit_price' => $salePrice,
                'discount_amount' => $lineDiscount,
                'line_total' => $net,
                'cost_price' => round((float) $p['cost_price'], 2),
            ];
        }
        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);
        $total = round($subtotal - $discountTotal, 2);
        if ($received === null) {
            $received = $total;
        }
        $received = round($received, 2);
        if ($received + 0.001 < $total) {
            throw new RuntimeException(t('Cash received is less than the total.'));
        }
        $change = round($received - $total, 2);
        $prefix = 'BP' . date('ymd') . '-';
        $saleId = 0;
        $invoice = '';
        $inserted = false;
        for ($try = 0; $try < 5; $try++) {
            $invoice = next_doc_no($pdo, 'bp_sales', 'invoice_no', $prefix);
            try {
                $ins = $pdo->prepare('INSERT INTO bp_sales (invoice_no, client_token, user_id, subtotal, discount_total, total, amount_received, change_amount, created_at) VALUES (?,?,?,?,?,?,?,?,?)');
                $ins->execute([$invoice, $token, $userId, money_str($subtotal), money_str($discountTotal), money_str($total), money_str($received), money_str($change), date('Y-m-d H:i:s')]);
                $saleId = (int) $pdo->lastInsertId();
                $inserted = true;
                break;
            } catch (PDOException $e) {
                if (!is_duplicate_key($e)) {
                    throw $e;
                }
                $again = $pdo->prepare('SELECT id, invoice_no, total, change_amount FROM bp_sales WHERE client_token = ?');
                $again->execute([$token]);
                $row = $again->fetch();
                if ($row) {
                    $pdo->commit();
                    return sale_from_row($row);
                }
                if ($try === 4) {
                    throw $e;
                }
            }
        }
        if (!$inserted) {
            throw new RuntimeException(t('The sale could not be saved. Please try again.'));
        }
        $itemSt = $pdo->prepare('INSERT INTO bp_sale_items (sale_id, product_id, product_name, unit_name, qty, unit_price, discount_amount, line_total, cost_price) VALUES (?,?,?,?,?,?,?,?,?)');
        $stockSt = $pdo->prepare('UPDATE bp_products SET stock_qty = ROUND(stock_qty - ?, 3) WHERE id = ? AND stock_qty + 0.0001 >= ?');
        foreach ($lines as $line) {
            $itemSt->execute([
                $saleId,
                $line['product_id'],
                $line['product_name'],
                $line['unit_name'],
                qty_str($line['qty']),
                money_str($line['unit_price']),
                money_str($line['discount_amount']),
                money_str($line['line_total']),
                money_str($line['cost_price']),
            ]);
            $stockSt->execute([qty_str($line['qty']), $line['product_id'], qty_str($line['qty'])]);
            if ($stockSt->rowCount() !== 1) {
                throw new RuntimeException(t('Stock changed for {name}. Check the cart and try again.', ['name' => (string) $line['product_name']]));
            }
        }
        $pdo->commit();
        return [
            'id' => $saleId,
            'invoice_no' => $invoice,
            'total' => $total,
            'change' => $change,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function fetch_sale(int $id): ?array
{
    $st = db()->prepare('SELECT s.*, u.full_name AS cashier FROM bp_sales s JOIN bp_users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([$id]);
    $sale = $st->fetch();
    if (!$sale) {
        return null;
    }
    $items = db()->prepare('SELECT * FROM bp_sale_items WHERE sale_id = ? ORDER BY id');
    $items->execute([$id]);
    $sale['items'] = $items->fetchAll();
    return $sale;
}

function void_sale(int $id, int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM bp_sales WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $sale = $st->fetch();
        if (!$sale) {
            throw new RuntimeException(t('Sale not found.'));
        }
        if ((int) $sale['voided'] === 1) {
            throw new RuntimeException(t('This sale is already void.'));
        }
        $items = $pdo->prepare('SELECT * FROM bp_sale_items WHERE sale_id = ?');
        $items->execute([$id]);
        $upd = $pdo->prepare('UPDATE bp_products SET stock_qty = ROUND(stock_qty + ?, 3) WHERE id = ?');
        foreach ($items->fetchAll() as $item) {
            $upd->execute([qty_str($item['qty']), (int) $item['product_id']]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException(t('Stock could not be returned for {name}.', ['name' => (string) $item['product_name']]));
            }
        }
        $mark = $pdo->prepare('UPDATE bp_sales SET voided = 1, voided_at = ?, voided_by = ? WHERE id = ?');
        $mark->execute([date('Y-m-d H:i:s'), $userId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function create_purchase(array $rawLines, string $date, string $note, bool $updateCost, int $userId): int
{
    $date = valid_date($date) ?? '';
    if ($date === '') {
        throw new RuntimeException(t('Choose the date of this stock in.'));
    }
    $note = trim($note);
    if (bp_strlen($note) > 255) {
        throw new RuntimeException(t('The note is too long.'));
    }
    $merged = [];
    foreach ($rawLines as $line) {
        $id = (int) ($line['product_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $qty = round((float) ($line['qty'] ?? 0), 3);
        $cost = round((float) ($line['cost'] ?? 0), 2);
        if ($qty <= 0) {
            throw new RuntimeException(t('Each product needs a quantity greater than zero.'));
        }
        if ($cost < 0) {
            throw new RuntimeException(t('Cost cannot be negative.'));
        }
        if (!isset($merged[$id])) {
            $merged[$id] = ['qty' => 0.0, 'cost' => $cost];
        }
        $merged[$id]['qty'] = round($merged[$id]['qty'] + $qty, 3);
        $merged[$id]['cost'] = $cost;
    }
    if (!$merged) {
        throw new RuntimeException(t('Add at least one product.'));
    }
    ksort($merged);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ids = array_keys($merged);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pst = $pdo->prepare('SELECT id, name, active FROM bp_products WHERE id IN (' . $in . ') FOR UPDATE');
        $pst->execute($ids);
        $products = [];
        foreach ($pst->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }
        $total = 0.0;
        $ready = [];
        foreach ($merged as $id => $line) {
            if (!isset($products[$id])) {
                throw new RuntimeException(t('One of the products was not found.'));
            }
            $lineTotal = round($line['qty'] * $line['cost'], 2);
            $total += $lineTotal;
            $ready[] = [
                'id' => $id,
                'name' => $products[$id]['name'],
                'qty' => $line['qty'],
                'cost' => $line['cost'],
                'line_total' => $lineTotal,
            ];
        }
        $total = round($total, 2);
        $prefix = 'STK' . date('ymd') . '-';
        $purchaseId = 0;
        for ($try = 0; $try < 5; $try++) {
            $ref = next_doc_no($pdo, 'bp_purchases', 'reference_no', $prefix);
            try {
                $ins = $pdo->prepare('INSERT INTO bp_purchases (reference_no, user_id, total_cost, note, purchased_on, created_at) VALUES (?,?,?,?,?,?)');
                $ins->execute([$ref, $userId, money_str($total), $note, $date, date('Y-m-d H:i:s')]);
                $purchaseId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                if (!is_duplicate_key($e) || $try === 4) {
                    throw $e;
                }
            }
        }
        $itemSt = $pdo->prepare('INSERT INTO bp_purchase_items (purchase_id, product_id, product_name, qty, cost_price, line_total) VALUES (?,?,?,?,?,?)');
        $stockSql = $updateCost
            ? 'UPDATE bp_products SET stock_qty = ROUND(stock_qty + ?, 3), cost_price = ? WHERE id = ?'
            : 'UPDATE bp_products SET stock_qty = ROUND(stock_qty + ?, 3) WHERE id = ?';
        $stockSt = $pdo->prepare($stockSql);
        foreach ($ready as $line) {
            $itemSt->execute([$purchaseId, $line['id'], $line['name'], qty_str($line['qty']), money_str($line['cost']), money_str($line['line_total'])]);
            if ($updateCost) {
                $stockSt->execute([qty_str($line['qty']), money_str($line['cost']), $line['id']]);
            } else {
                $stockSt->execute([qty_str($line['qty']), $line['id']]);
            }
        }
        $pdo->commit();
        return $purchaseId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function undo_purchase(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM bp_purchases WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        if (!$st->fetch()) {
            throw new RuntimeException(t('That stock receipt was not found.'));
        }
        $items = $pdo->prepare('SELECT * FROM bp_purchase_items WHERE purchase_id = ?');
        $items->execute([$id]);
        $rows = $items->fetchAll();
        $upd = $pdo->prepare('UPDATE bp_products SET stock_qty = ROUND(stock_qty - ?, 3) WHERE id = ? AND stock_qty + 0.0001 >= ?');
        foreach ($rows as $item) {
            $upd->execute([qty_str($item['qty']), (int) $item['product_id'], qty_str($item['qty'])]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException(t('Some of this stock has already been sold, so this receipt cannot be removed.'));
            }
        }
        $pdo->prepare('DELETE FROM bp_purchase_items WHERE purchase_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM bp_purchases WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function save_expense(array $in, ?int $id, int $userId): void
{
    $title = trim((string) ($in['title'] ?? ''));
    $note = trim((string) ($in['note'] ?? ''));
    $amount = round((float) ($in['amount'] ?? 0), 2);
    $date = valid_date(trim((string) ($in['date'] ?? ''))) ?? '';
    if ($title === '' || bp_strlen($title) > 160) {
        throw new RuntimeException(t('Enter an expense title.'));
    }
    if (bp_strlen($note) > 255) {
        throw new RuntimeException(t('The note is too long.'));
    }
    if ($amount <= 0) {
        throw new RuntimeException(t('Amount must be more than zero.'));
    }
    if ($date === '') {
        throw new RuntimeException(t('Choose the expense date.'));
    }
    if ($id) {
        $st = db()->prepare('UPDATE bp_expenses SET title = ?, amount = ?, expense_date = ?, note = ? WHERE id = ?');
        $st->execute([$title, money_str($amount), $date, $note, $id]);
        if ($st->rowCount() === 0) {
            $check = db()->prepare('SELECT id FROM bp_expenses WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                throw new RuntimeException(t('That expense was not found.'));
            }
        }
        return;
    }
    $st = db()->prepare('INSERT INTO bp_expenses (title, amount, expense_date, note, user_id, created_at) VALUES (?,?,?,?,?,?)');
    $st->execute([$title, money_str($amount), $date, $note, $userId, date('Y-m-d H:i:s')]);
}

function delete_expense(int $id): void
{
    $st = db()->prepare('DELETE FROM bp_expenses WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() < 1) {
        throw new RuntimeException(t('That expense was not found.'));
    }
}

function save_employee(array $in, ?int $id = null): int
{
    $name = trim((string) ($in['name'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));
    $position = trim((string) ($in['position'] ?? ''));
    $salary = round((float) ($in['monthly_salary'] ?? 0), 2);
    $joinedRaw = trim((string) ($in['joined_on'] ?? ''));
    $joined = $joinedRaw === '' ? null : valid_date($joinedRaw);
    if ($name === '' || bp_strlen($name) > 120) {
        throw new RuntimeException(t('Employee name is required.'));
    }
    if (bp_strlen($phone) > 40 || bp_strlen($position) > 80) {
        throw new RuntimeException(t('Phone or position is too long.'));
    }
    if ($salary < 0) {
        throw new RuntimeException(t('Salary cannot be negative.'));
    }
    if ($joinedRaw !== '' && !$joined) {
        throw new RuntimeException(t('Joined date is not valid.'));
    }
    $active = !empty($in['active']) ? 1 : 0;
    if ($id) {
        existing_id('bp_employees', $id);
        $st = db()->prepare('UPDATE bp_employees SET name = ?, phone = ?, position = ?, monthly_salary = ?, active = ?, joined_on = ? WHERE id = ?');
        $st->execute([$name, $phone, $position, money_str($salary), $active, $joined, $id]);
        return $id;
    }
    $st = db()->prepare('INSERT INTO bp_employees (name, phone, position, monthly_salary, active, joined_on, created_at) VALUES (?,?,?,?,?,?,?)');
    $st->execute([$name, $phone, $position, money_str($salary), $active, $joined, date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

function set_employee_active(int $id, bool $active): void
{
    existing_id('bp_employees', $id);
    $st = db()->prepare('UPDATE bp_employees SET active = ? WHERE id = ?');
    $st->execute([$active ? 1 : 0, $id]);
}

function save_advance(array $in, int $userId): void
{
    $employeeId = existing_id('bp_employees', (int) ($in['employee_id'] ?? 0));
    if (!$employeeId) {
        throw new RuntimeException(t('Choose an employee.'));
    }
    $kind = (string) ($in['kind'] ?? 'advance');
    if (!in_array($kind, ['advance', 'loan'], true)) {
        throw new RuntimeException(t('Choose advance or loan.'));
    }
    $amount = round((float) ($in['amount'] ?? 0), 2);
    $date = valid_date(trim((string) ($in['date'] ?? ''))) ?? '';
    $note = trim((string) ($in['note'] ?? ''));
    if ($amount <= 0) {
        throw new RuntimeException(t('Amount must be more than zero.'));
    }
    if ($date === '') {
        throw new RuntimeException(t('Choose the date.'));
    }
    if (bp_strlen($note) > 255) {
        throw new RuntimeException(t('The note is too long.'));
    }
    $st = db()->prepare('INSERT INTO bp_advances (employee_id, kind, amount, remaining, given_on, note, user_id, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$employeeId, $kind, money_str($amount), money_str($amount), $date, $note, $userId, date('Y-m-d H:i:s')]);
}

function delete_advance(int $id): void
{
    $st = db()->prepare('SELECT amount, remaining FROM bp_advances WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException(t('That record was not found.'));
    }
    if (money_str($row['amount']) !== money_str($row['remaining'])) {
        throw new RuntimeException(t('This has already been deducted in payroll, so it cannot be removed.'));
    }
    try {
        $del = db()->prepare('DELETE FROM bp_advances WHERE id = ? AND remaining = amount');
        $del->execute([$id]);
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new RuntimeException(t('This has already been deducted in payroll, so it cannot be removed.'));
        }
        throw $e;
    }
}

function issue_payroll(array $in, int $userId): void
{
    $employeeId = existing_id('bp_employees', (int) ($in['employee_id'] ?? 0));
    if (!$employeeId) {
        throw new RuntimeException(t('Choose an employee.'));
    }
    $month = (int) ($in['month'] ?? 0);
    $year = (int) ($in['year'] ?? 0);
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        throw new RuntimeException(t('Choose a valid month.'));
    }
    $paidOn = valid_date(trim((string) ($in['paid_on'] ?? ''))) ?? '';
    if ($paidOn === '') {
        throw new RuntimeException(t('Choose the date this salary was paid.'));
    }
    $base = round((float) ($in['base_salary'] ?? 0), 2);
    $deduct = round((float) ($in['advance_deducted'] ?? 0), 2);
    $note = trim((string) ($in['note'] ?? ''));
    if ($base <= 0) {
        throw new RuntimeException(t('Enter the salary for this month.'));
    }
    if ($deduct < 0) {
        throw new RuntimeException(t('Advance deduction cannot be negative.'));
    }
    if ($deduct - $base > 0.001) {
        throw new RuntimeException(t('Advance deduction cannot be more than the salary.'));
    }
    if (bp_strlen($note) > 255) {
        throw new RuntimeException(t('The note is too long.'));
    }
    $net = round($base - $deduct, 2);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $dup = $pdo->prepare('SELECT id FROM bp_payrolls WHERE employee_id = ? AND period_year = ? AND period_month = ? FOR UPDATE');
        $dup->execute([$employeeId, $year, $month]);
        if ($dup->fetch()) {
            throw new RuntimeException(t('Payroll for this employee and month is already issued.'));
        }
        $adv = $pdo->prepare('SELECT * FROM bp_advances WHERE employee_id = ? AND remaining > 0 ORDER BY given_on, id FOR UPDATE');
        $adv->execute([$employeeId]);
        $rows = $adv->fetchAll();
        $balance = 0.0;
        foreach ($rows as $row) {
            $balance += (float) $row['remaining'];
        }
        $balance = round($balance, 2);
        if ($deduct - $balance > 0.001) {
            throw new RuntimeException(t('Advance deduction is more than the outstanding balance of {amount}.', ['amount' => money($balance)]));
        }
        $ins = $pdo->prepare('INSERT INTO bp_payrolls (employee_id, period_year, period_month, base_salary, advance_deducted, net_pay, paid_on, note, user_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$employeeId, $year, $month, money_str($base), money_str($deduct), money_str($net), $paidOn, $note, $userId, date('Y-m-d H:i:s')]);
        $payrollId = (int) $pdo->lastInsertId();
        $left = $deduct;
        $upd = $pdo->prepare('UPDATE bp_advances SET remaining = ? WHERE id = ?');
        $link = $pdo->prepare('INSERT INTO bp_payroll_deductions (payroll_id, advance_id, amount) VALUES (?,?,?)');
        foreach ($rows as $row) {
            if ($left <= 0) {
                break;
            }
            $remaining = round((float) $row['remaining'], 2);
            $take = round(min($left, $remaining), 2);
            if ($take <= 0) {
                continue;
            }
            $upd->execute([money_str($remaining - $take), (int) $row['id']]);
            $link->execute([$payrollId, (int) $row['id'], money_str($take)]);
            $left = round($left - $take, 2);
        }
        if ($left > 0.009) {
            throw new RuntimeException(t('The advance balance changed. Please try again.'));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_duplicate_key($e)) {
            throw new RuntimeException(t('Payroll for this employee and month is already issued.'));
        }
        throw $e;
    }
}

function undo_payroll(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM bp_payrolls WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        if (!$st->fetch()) {
            throw new RuntimeException(t('That payroll was not found.'));
        }
        $deds = $pdo->prepare('SELECT * FROM bp_payroll_deductions WHERE payroll_id = ?');
        $deds->execute([$id]);
        $upd = $pdo->prepare('UPDATE bp_advances SET remaining = LEAST(amount, ROUND(remaining + ?, 2)) WHERE id = ?');
        foreach ($deds->fetchAll() as $deduction) {
            $upd->execute([money_str($deduction['amount']), (int) $deduction['advance_id']]);
        }
        $pdo->prepare('DELETE FROM bp_payrolls WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function save_settings(array $in): void
{
    $name = trim((string) ($in['business_name'] ?? ''));
    $currency = trim((string) ($in['currency_symbol'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));
    $footer = trim((string) ($in['receipt_footer'] ?? ''));
    $tz = trim((string) ($in['timezone'] ?? ''));
    if ($name === '' || bp_strlen($name) > 120) {
        throw new RuntimeException(t('Shop name is required.'));
    }
    $allowedCurrency = array_keys(currency_choices());
    if (!in_array($currency, $allowedCurrency, true) && $currency !== setting('currency_symbol', '')) {
        throw new RuntimeException(t('Choose a currency.'));
    }
    if (bp_strlen($phone) > 40 || bp_strlen($address) > 255 || bp_strlen($footer) > 180) {
        throw new RuntimeException(t('Phone, address, or receipt note is too long.'));
    }
    $allowedTz = array_keys(timezone_choices());
    if ((!in_array($tz, $allowedTz, true) && $tz !== setting('timezone', '')) || !in_array($tz, timezone_identifiers_list(), true)) {
        throw new RuntimeException(t('Choose a timezone.'));
    }
    $st = db()->prepare('UPDATE bp_settings SET business_name = ?, currency_symbol = ?, phone = ?, address = ?, receipt_footer = ?, timezone = ? WHERE id = 1');
    $st->execute([$name, $currency, $phone, $address, $footer, $tz]);
    settings_reload();
    date_default_timezone_set($tz);
}

function user_by_role(string $role): ?array
{
    $st = db()->prepare('SELECT id, username, full_name, role FROM bp_users WHERE role = ? ORDER BY id LIMIT 1');
    $st->execute([$role]);
    $row = $st->fetch();
    return $row ?: null;
}

function verify_user_password(int $userId, string $password): bool
{
    $st = db()->prepare('SELECT password_hash FROM bp_users WHERE id = ?');
    $st->execute([$userId]);
    $hash = $st->fetchColumn();
    return is_string($hash) && $hash !== '' && password_verify($password, $hash);
}

function update_user_login(int $userId, string $username, string $fullName, ?string $newPassword): void
{
    $username = trim($username);
    $fullName = trim($fullName);
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        throw new RuntimeException(t('Username must be 3 to 50 letters, numbers, dots, or dashes.'));
    }
    if ($fullName === '' || bp_strlen($fullName) > 100) {
        throw new RuntimeException(t('Enter the person\'s name.'));
    }
    if ($newPassword !== null && $newPassword !== '' && strlen($newPassword) < 6) {
        throw new RuntimeException(t('Use at least 6 characters for the password.'));
    }
    if ($newPassword !== null && strlen($newPassword) > 200) {
        throw new RuntimeException(t('That password is too long.'));
    }
    try {
        if ($newPassword !== null && $newPassword !== '') {
            $st = db()->prepare('UPDATE bp_users SET username = ?, full_name = ?, password_hash = ? WHERE id = ?');
            $st->execute([$username, $fullName, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        } else {
            $st = db()->prepare('UPDATE bp_users SET username = ?, full_name = ? WHERE id = ?');
            $st->execute([$username, $fullName, $userId]);
        }
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new RuntimeException(t('That username is already used.'));
        }
        throw $e;
    }
}

function sales_summary(string $start, string $end): array
{
    $st = db()->prepare('SELECT COUNT(*) AS invoices, COALESCE(SUM(subtotal), 0) AS subtotal, COALESCE(SUM(discount_total), 0) AS discount_total, COALESCE(SUM(total), 0) AS total FROM bp_sales WHERE voided = 0 AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)');
    $st->execute([$start, $end]);
    $row = $st->fetch() ?: [];
    return [
        'invoices' => (int) ($row['invoices'] ?? 0),
        'subtotal' => round((float) ($row['subtotal'] ?? 0), 2),
        'discount_total' => round((float) ($row['discount_total'] ?? 0), 2),
        'total' => round((float) ($row['total'] ?? 0), 2),
    ];
}

function sales_between(string $start, string $end, int $limit = 500): array
{
    $st = db()->prepare('SELECT s.*, u.full_name AS cashier, (SELECT COALESCE(SUM(qty), 0) FROM bp_sale_items i WHERE i.sale_id = s.id) AS item_qty FROM bp_sales s JOIN bp_users u ON u.id = s.user_id WHERE s.voided = 0 AND s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY s.created_at DESC, s.id DESC LIMIT ' . (int) $limit);
    $st->execute([$start, $end]);
    return $st->fetchAll();
}

function sales_by_day(string $start, string $end): array
{
    $st = db()->prepare('SELECT DATE(created_at) AS day, COUNT(*) AS invoices, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(discount_total), 0) AS discount_total FROM bp_sales WHERE voided = 0 AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY DATE(created_at) ORDER BY day');
    $st->execute([$start, $end]);
    return $st->fetchAll();
}

function sales_series(int $days = 7): array
{
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $map = [];
    foreach (sales_by_day($start, $end) as $row) {
        $map[$row['day']] = $row;
    }
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $day = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
        $out[] = [
            'day' => $day,
            'label' => t(date('D', strtotime($day))),
            'total' => round((float) ($map[$day]['total'] ?? 0), 2),
        ];
    }
    return $out;
}

function pnl_for_range(string $start, string $end): array
{
    $salesSt = db()->prepare('SELECT COALESCE(SUM(total), 0) FROM bp_sales WHERE voided = 0 AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)');
    $salesSt->execute([$start, $end]);
    $sales = round((float) $salesSt->fetchColumn(), 2);

    $buy = db()->prepare('SELECT COALESCE(SUM(total_cost), 0) FROM bp_purchases WHERE purchased_on >= ? AND purchased_on <= ?');
    $buy->execute([$start, $end]);
    $purchases = round((float) $buy->fetchColumn(), 2);

    $pay = db()->prepare('SELECT COALESCE(SUM(net_pay), 0) FROM bp_payrolls WHERE paid_on >= ? AND paid_on <= ?');
    $pay->execute([$start, $end]);
    $payroll = round((float) $pay->fetchColumn(), 2);

    $adv = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM bp_advances WHERE given_on >= ? AND given_on <= ?');
    $adv->execute([$start, $end]);
    $advances = round((float) $adv->fetchColumn(), 2);

    $exp = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM bp_expenses WHERE expense_date >= ? AND expense_date <= ?');
    $exp->execute([$start, $end]);
    $expenses = round((float) $exp->fetchColumn(), 2);

    return [
        'sales' => $sales,
        'purchases' => $purchases,
        'payroll' => $payroll,
        'advances' => $advances,
        'expenses' => $expenses,
        'profit' => round($sales - $purchases - $payroll - $advances - $expenses, 2),
    ];
}

function dashboard_stats(): array
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $low = db()->query('SELECT id, name, stock_qty, reorder_level FROM bp_products WHERE active = 1 AND stock_qty <= reorder_level ORDER BY stock_qty ASC, name ASC LIMIT 6')->fetchAll();
    $lowCount = (int) db()->query('SELECT COUNT(*) FROM bp_products WHERE active = 1 AND stock_qty <= reorder_level')->fetchColumn();
    $productCount = (int) db()->query('SELECT COUNT(*) FROM bp_products')->fetchColumn();
    $recent = db()->query('SELECT s.id, s.invoice_no, s.total, s.created_at, u.full_name AS cashier FROM bp_sales s JOIN bp_users u ON u.id = s.user_id WHERE s.voided = 0 ORDER BY s.id DESC LIMIT 8')->fetchAll();
    return [
        'today' => sales_summary($today, $today),
        'month' => sales_summary($monthStart, $monthEnd),
        'pnl' => pnl_for_range($monthStart, $monthEnd),
        'low_stock' => $low,
        'low_count' => $lowCount,
        'product_count' => $productCount,
        'recent' => $recent,
        'series' => sales_series(7),
    ];
}
