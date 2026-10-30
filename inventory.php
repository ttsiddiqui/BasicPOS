<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        if ($action === 'receive') {
            $ids = $_POST['product_id'] ?? [];
            $qtys = $_POST['qty'] ?? [];
            $costs = $_POST['cost'] ?? [];
            $lines = [];
            if (is_array($ids)) {
                foreach ($ids as $i => $id) {
                    $qtyRaw = str_replace([',', ' '], '', (string) (is_array($qtys) ? ($qtys[$i] ?? '0') : '0'));
                    $costRaw = str_replace([',', ' '], '', (string) (is_array($costs) ? ($costs[$i] ?? '0') : '0'));
                    $lines[] = [
                        'product_id' => (int) $id,
                        'qty' => is_numeric($qtyRaw) ? (float) $qtyRaw : 0,
                        'cost' => is_numeric($costRaw) ? (float) $costRaw : 0,
                    ];
                }
            }
            create_purchase($lines, post_str('purchased_on'), post_str('note'), isset($_POST['update_cost']), (int) current_user()['id']);
            flash('success', 'Stock received.');
            redirect('inventory.php');
        } elseif ($action === 'undo') {
            undo_purchase(post_int('id'));
            flash('success', 'That stock receipt was removed and the quantities were taken back.');
            redirect('inventory.php');
        } else {
            throw new RuntimeException('That action is not available.');
        }
    } catch (RuntimeException $e) {
        if ($action === 'receive') {
            $error = $e->getMessage();
        } else {
            flash('error', $e->getMessage());
            redirect('inventory.php');
        }
    }
}

$products = db()->query('SELECT id, name, product_code, barcode, cost_price, stock_qty, active FROM bp_products ORDER BY name')->fetchAll();
$history = db()->query('SELECT p.*, u.full_name FROM bp_purchases p JOIN bp_users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 30')->fetchAll();
$itemMap = [];
if ($history) {
    $ids = array_map(static fn (array $row): int => (int) $row['id'], $history);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare('SELECT * FROM bp_purchase_items WHERE purchase_id IN (' . $in . ') ORDER BY id');
    $st->execute($ids);
    foreach ($st->fetchAll() as $item) {
        $itemMap[(int) $item['purchase_id']][] = $item['product_name'] . ' × ' . qty_fmt($item['qty']);
    }
}

$line = static function (array $products): void {
    echo '<div class="stock-line">';
    echo '<select name="product_id[]"><option value="">Choose a product</option>';
    foreach ($products as $product) {
        $label = $product['name'];
        if ($product['product_code']) {
            $label .= ' · ' . $product['product_code'];
        }
        if (!(int) $product['active']) {
            $label .= ' (hidden)';
        }
        echo '<option value="' . (int) $product['id'] . '" data-cost="' . e(money_str($product['cost_price'])) . '">' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<input name="qty[]" type="number" min="0.001" step="0.001" value="1" aria-label="Quantity" required>';
    echo '<input class="cost" name="cost[]" type="number" min="0" step="0.01" value="0" aria-label="Cost" required>';
    echo '<button class="btn btn-ghost btn-sm remove-line" type="button">Remove</button>';
    echo '</div>';
};

layout_start('Stock in', 'inventory');
?>
<div class="page-head">
  <div>
    <h1>Stock in</h1>
    <p>Receive products into the shop. This increases stock and counts as a purchase in the profit report.</p>
  </div>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form class="card" method="post" id="stockForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="receive">
  <div class="form-grid">
    <label class="field">Date<input type="date" name="purchased_on" required value="<?= e(post_str('purchased_on') !== '' ? post_str('purchased_on') : date('Y-m-d')) ?>"></label>
    <label class="field">Note<input name="note" maxlength="255" placeholder="Supplier or bill number" value="<?= e($error ? post_str('note') : '') ?>"></label>
  </div>
  <p class="hint">Quantity and cost for each product. Cost is per unit.</p>
  <div id="stockLines">
    <?php $line($products); ?>
  </div>
  <template id="stockLineTpl"><?php $line($products); ?></template>
  <button class="btn btn-ghost" id="addStockLine" type="button"><?= icon('plus', 20) ?> Add another product</button>
  <label class="check"><input type="checkbox" name="update_cost" value="1" checked> Update each product's cost price to this cost</label>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= icon('inbox', 20) ?> Save stock in</button>
  </div>
</form>

<section class="card">
  <h2>Recent receipts</h2>
  <?php if (!$history): ?>
    <?= empty_state('inbox', 'No stock receipts yet', 'When you receive products, they will be listed here.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data stack">
        <thead><tr><th>Receipt</th><th>Date</th><th>Products</th><th class="num">Cost</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <tr>
              <td data-label="Receipt"><strong><?= e((string) $row['reference_no']) ?></strong><br><small class="muted"><?= e((string) $row['full_name']) ?></small></td>
              <td data-label="Date"><?= e(day_fmt((string) $row['purchased_on'])) ?></td>
              <td data-label="Products"><?= e(implode(', ', $itemMap[(int) $row['id']] ?? [])) ?><?= $row['note'] ? '<br><small class="muted">' . e((string) $row['note']) . '</small>' : '' ?></td>
              <td class="num" data-label="Cost"><?= e(money($row['total_cost'])) ?></td>
              <td class="actions" data-label="">
                <form method="post" data-confirm="Remove this receipt and take the quantities out of stock? Product cost prices stay as they are now.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="undo">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> Undo</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php layout_end(); ?>
