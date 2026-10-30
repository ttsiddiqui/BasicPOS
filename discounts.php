<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        if ($action === 'save') {
            $id = post_int('id');
            $ids = $_POST['product_ids'] ?? [];
            save_discount([
                'name' => post_str('name'),
                'type' => post_str('discount_type'),
                'value' => post_float('discount_value'),
                'apply_to' => post_str('apply_to'),
                'starts_on' => post_str('starts_on'),
                'ends_on' => post_str('ends_on'),
                'active' => isset($_POST['active']) ? 1 : 0,
                'product_ids' => is_array($ids) ? $ids : [],
            ], $id > 0 ? $id : null);
            flash('success', $id > 0 ? 'Discount updated.' : 'Discount added.');
            redirect('discounts.php');
        } elseif ($action === 'delete') {
            delete_discount(post_int('id'));
            flash('success', 'Discount removed.');
            redirect('discounts.php');
        } else {
            throw new RuntimeException(t('That action is not available.'));
        }
    } catch (RuntimeException $e) {
        if ($action === 'save') {
            $error = $e->getMessage();
        } else {
            flash('error', $e->getMessage());
            redirect('discounts.php');
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if (($_POST['action'] ?? '') === 'save' && $error) {
    $editId = post_int('id');
}
$editing = null;
$picked = [];
if ($editId > 0) {
    $st = db()->prepare('SELECT * FROM bp_discounts WHERE id = ?');
    $st->execute([$editId]);
    $editing = $st->fetch() ?: null;
    if ($editing) {
        $picked = discount_product_ids((int) $editing['id']);
    }
}
if (($_POST['action'] ?? '') === 'save' && $error) {
    $picked = array_map('intval', is_array($_POST['product_ids'] ?? null) ? $_POST['product_ids'] : []);
}
$value = static function (string $key, string $fallback = '') use ($editing): string {
    if (($_POST['action'] ?? '') === 'save' && array_key_exists($key, $_POST)) {
        return trim((string) $_POST[$key]);
    }
    if ($editing && isset($editing[$key]) && $editing[$key] !== null) {
        return (string) $editing[$key];
    }
    return $fallback;
};
$type = $value('discount_type', 'percent');
$apply = $value('apply_to', 'all');
$activeChecked = (($_POST['action'] ?? '') === 'save') ? isset($_POST['active']) : ($editing ? (int) $editing['active'] === 1 : true);
$products = db()->query('SELECT id, name, product_code FROM bp_products ORDER BY name')->fetchAll();
$discounts = db()->query('SELECT * FROM bp_discounts ORDER BY id DESC')->fetchAll();
$today = date('Y-m-d');

layout_start('Discounts', 'discounts');
?>
<div class="page-head">
  <div>
    <h1><?= tx('Discounts') ?></h1>
    <p><?= tx('Take a fixed amount or a percentage off every product, or only the ones you pick. If two discounts match, the customer gets the bigger saving.') ?></p>
  </div>
</div>
<?php if ($error): ?><div class="flash flash-error"><?= e(t($error)) ?></div><?php endif; ?>
<form class="card" method="post" id="discountForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
  <h2><?= $editing ? tx('Edit discount') : tx('New discount') ?></h2>
  <div class="form-grid">
    <label class="field span-2"><?= tx('Name') ?><input name="name" required maxlength="120" value="<?= e($value('name')) ?>" placeholder="<?= tx('Weekend offer') ?>"></label>
    <label class="field"><?= tx('Type') ?>
      <select name="discount_type">
        <option value="percent"<?= $type === 'percent' ? ' selected' : '' ?>><?= tx('Percentage') ?></option>
        <option value="fixed"<?= $type === 'fixed' ? ' selected' : '' ?>><?= tx('Fixed amount') ?></option>
      </select>
    </label>
    <label class="field"><?= tx('Value') ?><input name="discount_value" type="number" min="0.01" step="0.01" required value="<?= e($value('discount_value')) ?>"></label>
    <label class="field"><?= tx('Applies to') ?>
      <select name="apply_to">
        <option value="all"<?= $apply === 'all' ? ' selected' : '' ?>><?= tx('All products') ?></option>
        <option value="selected"<?= $apply === 'selected' ? ' selected' : '' ?>><?= tx('Selected products') ?></option>
      </select>
    </label>
    <label class="check"><input type="checkbox" name="active" value="1"<?= $activeChecked ? ' checked' : '' ?>> <?= tx('Active') ?></label>
    <label class="field"><?= tx('Starts') ?><input type="date" name="starts_on" value="<?= e($value('starts_on')) ?>"></label>
    <label class="field"><?= tx('Ends') ?><input type="date" name="ends_on" value="<?= e($value('ends_on')) ?>"></label>
  </div>
  <div id="productPicks" class="stack">
    <p class="hint"><?= tx('Tick products only when this discount is for selected products.') ?></p>
    <label class="field"><?= tx('Find a product') ?><input id="filterProducts" type="search" placeholder="<?= tx('Type a name or code') ?>"></label>
    <div class="check-list">
      <?php if (!$products): ?>
        <p class="hint"><?= tx('Add a product first.') ?></p>
      <?php endif; ?>
      <?php foreach ($products as $product): ?>
        <label class="check-row" data-name="<?= e(bp_lower((string) $product['name'] . ' ' . (string) $product['product_code'])) ?>">
          <input type="checkbox" name="product_ids[]" value="<?= (int) $product['id'] ?>"<?= in_array((int) $product['id'], $picked, true) ? ' checked' : '' ?>>
          <span><?= e((string) $product['name']) ?><?= $product['product_code'] ? ' · ' . e((string) $product['product_code']) : '' ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> <?= tx('Save discount') ?></button>
    <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('discounts.php')) ?>"><?= tx('Cancel') ?></a><?php endif; ?>
  </div>
</form>

<section class="card">
  <h2><?= tx('Current discounts') ?></h2>
  <?php if (!$discounts): ?>
    <?= empty_state('percent', t('No discounts yet'), t('A discount applies on the sale screen while it is active.')) ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data stack">
        <thead><tr><th><?= tx('Name') ?></th><th><?= tx('Offer') ?></th><th><?= tx('Products') ?></th><th><?= tx('Dates') ?></th><th><?= tx('Status') ?></th><th></th></tr></thead>
        <tbody>
          <?php foreach ($discounts as $discount): ?>
            <?php
              $status = t('Live');
              $badge = 'badge';
              if (!(int) $discount['active']) {
                  $status = t('Off');
                  $badge = 'badge badge-off';
              } elseif (!empty($discount['starts_on']) && $discount['starts_on'] > $today) {
                  $status = t('Scheduled');
                  $badge = 'badge badge-warn';
              } elseif (!empty($discount['ends_on']) && $discount['ends_on'] < $today) {
                  $status = t('Ended');
                  $badge = 'badge badge-off';
              }
              $offer = $discount['discount_type'] === 'percent'
                  ? rtrim(rtrim(number_format((float) $discount['discount_value'], 2, '.', ''), '0'), '.') . '%'
                  : money($discount['discount_value']);
            ?>
            <tr>
              <td data-label="<?= tx('Name') ?>"><strong><?= e((string) $discount['name']) ?></strong></td>
              <td data-label="<?= tx('Offer') ?>"><?= tx('{offer} off', ['offer' => $offer]) ?></td>
              <td data-label="<?= tx('Products') ?>"><?= $discount['apply_to'] === 'all' ? tx('All products') : tx('Selected') ?></td>
              <td data-label="<?= tx('Dates') ?>"><?= e(trim(day_fmt($discount['starts_on'] ?: null) . ' – ' . day_fmt($discount['ends_on'] ?: null), ' –')) ?: tx('Any day') ?></td>
              <td data-label="<?= tx('Status') ?>"><span class="<?= e($badge) ?>"><?= e($status) ?></span></td>
              <td class="actions" data-label="">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('discounts.php?edit=' . (int) $discount['id'])) ?>"><?= icon('edit', 18) ?> <?= tx('Edit') ?></a>
                <form method="post" data-confirm="<?= tx('Remove this discount?') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $discount['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> <?= tx('Remove') ?></button>
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
