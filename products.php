<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$tab = (string) ($_GET['tab'] ?? 'products');
if (!in_array($tab, ['products', 'categories', 'brands', 'units'], true)) {
    $tab = 'products';
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        if ($action === 'save_product') {
            $id = post_int('id');
            save_product([
                'name' => post_str('name'),
                'barcode' => post_str('barcode'),
                'product_code' => post_str('product_code'),
                'unit_id' => post_int('unit_id'),
                'category_id' => post_int('category_id'),
                'brand_id' => post_int('brand_id'),
                'cost_price' => post_float('cost_price'),
                'sale_price' => post_float('sale_price'),
                'reorder_level' => post_float('reorder_level'),
                'opening_stock' => post_float('opening_stock'),
                'active' => isset($_POST['active']) ? 1 : 0,
            ], $id > 0 ? $id : null);
            flash('success', $id > 0 ? 'Product updated.' : 'Product added.');
            redirect('products.php');
        } elseif ($action === 'delete_product') {
            $result = delete_product(post_int('id'));
            flash('success', $result === 'deleted' ? 'Product removed.' : 'Product hidden because it has stock or sales history.');
            redirect('products.php');
        } elseif ($action === 'save_category') {
            $id = post_int('id');
            save_named('bp_categories', post_str('name'), $id > 0 ? $id : null);
            flash('success', 'Category saved.');
            redirect('products.php?tab=categories');
        } elseif ($action === 'delete_category') {
            delete_named('bp_categories', post_int('id'));
            flash('success', 'Category removed.');
            redirect('products.php?tab=categories');
        } elseif ($action === 'save_brand') {
            $id = post_int('id');
            save_named('bp_brands', post_str('name'), $id > 0 ? $id : null);
            flash('success', 'Brand saved.');
            redirect('products.php?tab=brands');
        } elseif ($action === 'delete_brand') {
            delete_named('bp_brands', post_int('id'));
            flash('success', 'Brand removed.');
            redirect('products.php?tab=brands');
        } elseif ($action === 'save_unit') {
            $id = post_int('id');
            save_named('bp_units', post_str('name'), $id > 0 ? $id : null);
            flash('success', 'Unit saved.');
            redirect('products.php?tab=units');
        } elseif ($action === 'delete_unit') {
            delete_named('bp_units', post_int('id'));
            flash('success', 'Unit removed.');
            redirect('products.php?tab=units');
        } else {
            throw new RuntimeException('That action is not available.');
        }
    } catch (RuntimeException $e) {
        if (in_array($action, ['save_product', 'save_category', 'save_brand', 'save_unit'], true)) {
            $error = $e->getMessage();
        } else {
            flash('error', $e->getMessage());
            redirect('products.php?tab=' . $tab);
        }
    }
}

$categories = db()->query('SELECT * FROM bp_categories ORDER BY name')->fetchAll();
$brands = db()->query('SELECT * FROM bp_brands ORDER BY name')->fetchAll();
$units = db()->query('SELECT * FROM bp_units ORDER BY name')->fetchAll();

$status = (string) ($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'hidden', 'all'], true)) {
    $status = 'active';
}
$lowOnly = ($_GET['stock'] ?? '') === 'low';
$q = trim((string) ($_GET['q'] ?? ''));
$productSql = 'SELECT p.*, c.name AS category_name, b.name AS brand_name, u.name AS unit_name FROM bp_products p LEFT JOIN bp_categories c ON c.id = p.category_id LEFT JOIN bp_brands b ON b.id = p.brand_id LEFT JOIN bp_units u ON u.id = p.unit_id WHERE 1 = 1';
$productParams = [];
if ($status === 'active') {
    $productSql .= ' AND p.active = 1';
} elseif ($status === 'hidden') {
    $productSql .= ' AND p.active = 0';
}
if ($lowOnly) {
    $productSql .= ' AND p.stock_qty <= p.reorder_level';
}
if ($q !== '') {
    $productSql .= ' AND (p.name LIKE ? OR p.barcode LIKE ? OR p.product_code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($productParams, $like, $like, $like);
}
$productSql .= ' ORDER BY p.name LIMIT 301';
$pst = db()->prepare($productSql);
$pst->execute($productParams);
$products = $pst->fetchAll();
$truncated = count($products) > 300;
if ($truncated) {
    $products = array_slice($products, 0, 300);
}

$editingProduct = null;
$productId = (int) ($_GET['id'] ?? 0);
if (($_POST['action'] ?? '') === 'save_product' && $error) {
    $productId = post_int('id');
}
if ($productId > 0) {
    $st = db()->prepare('SELECT * FROM bp_products WHERE id = ?');
    $st->execute([$productId]);
    $editingProduct = $st->fetch() ?: null;
}
$showProductForm = isset($_GET['new']) || $editingProduct || (($_POST['action'] ?? '') === 'save_product' && $error);
$field = static function (string $key, $fallback = '') use ($editingProduct): string {
    if (($_POST['action'] ?? '') === 'save_product' && array_key_exists($key, $_POST)) {
        return trim((string) $_POST[$key]);
    }
    if ($editingProduct && isset($editingProduct[$key]) && $editingProduct[$key] !== null) {
        return (string) $editingProduct[$key];
    }
    return (string) $fallback;
};

$namedEdit = (int) ($_GET['edit'] ?? 0);
if (in_array($_POST['action'] ?? '', ['save_category', 'save_brand', 'save_unit'], true) && $error) {
    $namedEdit = post_int('id');
}

layout_start('Products', 'products');
?>
<div class="page-head">
  <div>
    <h1>Products</h1>
    <p>Name, barcode, code, unit, category, brand, cost, and sale price. Categories and brands are yours to add.</p>
  </div>
  <?php if ($tab === 'products' && !$showProductForm): ?>
    <a class="btn btn-primary" href="<?= e(url('products.php?new=1')) ?>"><?= icon('plus', 22) ?> Add product</a>
  <?php endif; ?>
</div>

<nav class="tabs">
  <a class="<?= $tab === 'products' ? 'active' : '' ?>" href="<?= e(url('products.php')) ?>"><?= icon('box', 18) ?> Products</a>
  <a class="<?= $tab === 'categories' ? 'active' : '' ?>" href="<?= e(url('products.php?tab=categories')) ?>"><?= icon('tag', 18) ?> Categories</a>
  <a class="<?= $tab === 'brands' ? 'active' : '' ?>" href="<?= e(url('products.php?tab=brands')) ?>"><?= icon('store', 18) ?> Brands</a>
  <a class="<?= $tab === 'units' ? 'active' : '' ?>" href="<?= e(url('products.php?tab=units')) ?>"><?= icon('grid', 18) ?> Units</a>
</nav>

<?php if ($error && in_array($_POST['action'] ?? '', ['save_product', 'save_category', 'save_brand', 'save_unit'], true)): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($tab === 'products'): ?>
  <?php if ($showProductForm): ?>
    <?php
      $activeChecked = (($_POST['action'] ?? '') === 'save_product') ? isset($_POST['active']) : ($editingProduct ? (int) $editingProduct['active'] === 1 : true);
    ?>
    <form class="card" method="post" action="<?= e(url('products.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_product">
      <input type="hidden" name="id" value="<?= $editingProduct ? (int) $editingProduct['id'] : 0 ?>">
      <h2><?= $editingProduct ? 'Edit product' : 'New product' ?></h2>
      <div class="form-grid">
        <label class="field span-2">Name<input name="name" required maxlength="160" value="<?= e($field('name')) ?>"></label>
        <label class="field">Barcode<input name="barcode" maxlength="64" value="<?= e($field('barcode')) ?>" inputmode="numeric"></label>
        <label class="field">Product code<input name="product_code" maxlength="64" value="<?= e($field('product_code')) ?>"></label>
        <label class="field">Category
          <select name="category_id">
            <option value="0">None</option>
            <?php foreach ($categories as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (string) $row['id'] === $field('category_id') ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Brand
          <select name="brand_id">
            <option value="0">None</option>
            <?php foreach ($brands as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (string) $row['id'] === $field('brand_id') ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Unit
          <select name="unit_id">
            <option value="0">None</option>
            <?php foreach ($units as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (string) $row['id'] === $field('unit_id') ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Reorder level<input name="reorder_level" type="number" min="0" step="0.001" value="<?= e($field('reorder_level', $editingProduct ? '' : '5')) ?>"></label>
        <label class="field">Cost price<input name="cost_price" type="number" min="0" step="0.01" required value="<?= e($field('cost_price', '0')) ?>"></label>
        <label class="field">Sale price<input name="sale_price" type="number" min="0" step="0.01" required value="<?= e($field('sale_price', '0')) ?>"></label>
        <?php if (!$editingProduct): ?>
          <label class="field span-2">Opening stock<input name="opening_stock" type="number" min="0" step="0.001" value="<?= e($field('opening_stock', '0')) ?>"><span class="hint">This adds quantity only. It is not counted as a purchase. Use Stock in for deliveries.</span></label>
        <?php else: ?>
          <p class="span-2 hint">Current stock is <?= e(qty_fmt($editingProduct['stock_qty'])) ?>. Change it from Stock in.</p>
        <?php endif; ?>
        <label class="check span-2"><input type="checkbox" name="active" value="1"<?= $activeChecked ? ' checked' : '' ?>> Show this product on the sale screen</label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save product</button>
        <a class="btn btn-ghost" href="<?= e(url('products.php')) ?>">Close</a>
      </div>
    </form>
  <?php endif; ?>

  <form class="card" method="get" action="<?= e(url('products.php')) ?>">
    <div class="search-form">
      <input name="q" value="<?= e($q) ?>" placeholder="Search name, barcode, or code">
      <?php if ($lowOnly): ?><input type="hidden" name="stock" value="low"><?php endif; ?>
      <?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <button class="btn btn-dark" type="submit"><?= icon('search', 20) ?> Search</button>
    </div>
  </form>
  <nav class="chips">
    <a class="chip<?= $status === 'active' && !$lowOnly ? ' active' : '' ?>" href="<?= e(url('products.php')) ?>">Active</a>
    <a class="chip<?= $lowOnly ? ' active' : '' ?>" href="<?= e(url('products.php?stock=low')) ?>">Low stock</a>
    <a class="chip<?= $status === 'hidden' ? ' active' : '' ?>" href="<?= e(url('products.php?status=hidden')) ?>">Hidden</a>
    <a class="chip<?= $status === 'all' && !$lowOnly ? ' active' : '' ?>" href="<?= e(url('products.php?status=all')) ?>">All</a>
  </nav>
  <div class="card">
    <?php if (!$products): ?>
      <?= empty_state('box', 'No products yet', 'Add a product, or add a category and brand first.') ?>
    <?php else: ?>
      <?php if ($truncated): ?><p class="hint">Showing the first 300 matches. Search to narrow the list.</p><?php endif; ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead>
            <tr><th>Product</th><th>Code</th><th>Category</th><th class="num">Cost</th><th class="num">Sale</th><th class="num">Stock</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <?php $low = (float) $product['stock_qty'] <= (float) $product['reorder_level']; ?>
              <tr>
                <td data-label="Product"><strong><?= e((string) $product['name']) ?></strong><?php if (!(int) $product['active']): ?> <span class="badge badge-off">Hidden</span><?php endif; ?><?php if ($low): ?> <span class="badge badge-warn">Low</span><?php endif; ?></td>
                <td data-label="Code"><?= e((string) ($product['product_code'] ?: $product['barcode'] ?: '—')) ?></td>
                <td data-label="Category"><?= e((string) ($product['category_name'] ?: '—')) ?><?= $product['brand_name'] ? ' · ' . e((string) $product['brand_name']) : '' ?></td>
                <td class="num" data-label="Cost"><?= e(money($product['cost_price'])) ?></td>
                <td class="num" data-label="Sale"><?= e(money($product['sale_price'])) ?></td>
                <td class="num" data-label="Stock"><?= e(qty_fmt($product['stock_qty'])) ?> <?= e((string) ($product['unit_name'] ?? '')) ?></td>
                <td class="actions" data-label="">
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('products.php?id=' . (int) $product['id'])) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <form method="post" data-confirm="Remove this product?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <?php
    $map = [
        'categories' => ['bp_categories', 'Category', 'save_category', 'delete_category', $categories],
        'brands' => ['bp_brands', 'Brand', 'save_brand', 'delete_brand', $brands],
        'units' => ['bp_units', 'Unit', 'save_unit', 'delete_unit', $units],
    ];
    [$table, $label, $saveAction, $deleteAction, $rows] = $map[$tab];
    $current = null;
    foreach ($rows as $row) {
        if ((int) $row['id'] === $namedEdit) {
            $current = $row;
        }
    }
    $namedValue = $current['name'] ?? '';
    if (($_POST['action'] ?? '') === $saveAction && $error) {
        $namedValue = post_str('name');
    }
  ?>
  <form class="card" method="post" action="<?= e(url('products.php?tab=' . $tab)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= e($saveAction) ?>">
    <input type="hidden" name="id" value="<?= $current ? (int) $current['id'] : 0 ?>">
    <h2><?= $current ? 'Edit ' . e(strtolower($label)) : 'Add ' . e(strtolower($label)) ?></h2>
    <div class="form-grid">
      <label class="field">Name<input name="name" required maxlength="<?= $tab === 'units' ? '40' : '80' ?>" value="<?= e((string) $namedValue) ?>"></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save</button>
      <?php if ($current): ?><a class="btn btn-ghost" href="<?= e(url('products.php?tab=' . $tab)) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
  <div class="card">
    <?php if (!$rows): ?>
      <?= empty_state('tag', 'Nothing here yet', 'Add one with the form above.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th><?= e($label) ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td data-label="<?= e($label) ?>"><?= e((string) $row['name']) ?></td>
                <td class="actions" data-label="">
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('products.php?tab=' . $tab . '&edit=' . (int) $row['id'])) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <form method="post" action="<?= e(url('products.php?tab=' . $tab)) ?>" data-confirm="Remove this <?= e(strtolower($label)) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= e($deleteAction) ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
