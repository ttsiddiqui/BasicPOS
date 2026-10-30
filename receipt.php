<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($user['role'] ?? '') !== 'admin' || post_str('action') !== 'void') {
        flash('error', 'Only the admin can void a sale.');
        redirect('pos.php');
    }
    try {
        void_sale($id, (int) $user['id']);
        flash('success', 'Sale voided and stock returned.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('receipt.php?id=' . $id);
}

$sale = $id > 0 ? fetch_sale($id) : null;
$print = (($_GET['print'] ?? '') === '1');
html_head($sale ? (string) $sale['invoice_no'] : 'Receipt', 'page-receipt' . ($print ? ' print-now' : ''));
?>
<div class="receipt-page">
  <div class="receipt-actions no-print">
    <?php if ($sale): ?>
      <button class="btn btn-primary" type="button" onclick="window.print()"><?= icon('print', 20) ?> <?= tx('Print') ?></button>
    <?php endif; ?>
    <a class="btn btn-dark" href="<?= e(url('pos.php')) ?>"><?= icon('cart', 20) ?> <?= tx('New sale') ?></a>
    <?php if ($user['role'] === 'admin'): ?>
      <a class="btn btn-ghost" href="<?= e(url('reports.php?type=daily')) ?>"><?= tx('Reports') ?></a>
    <?php endif; ?>
  </div>
  <?php render_flash(); ?>
  <?php if (!$sale): ?>
    <div class="card receipt"><h1><?= tx('Receipt not found') ?></h1><p class="center"><?= tx('That sale is not in the system.') ?></p></div>
  <?php else: ?>
    <article class="receipt">
      <?php if ((int) $sale['voided'] === 1): ?><div class="void-banner"><?= tx('VOID') ?></div><?php endif; ?>
      <h1><?= e(setting('business_name')) ?></h1>
      <p class="center muted">BasicPOS by WebC</p>
      <?php if (setting('address') !== ''): ?><p class="center"><?= e(setting('address')) ?></p><?php endif; ?>
      <?php if (setting('phone') !== ''): ?><p class="center"><?= e(setting('phone')) ?></p><?php endif; ?>
      <hr>
      <table>
        <tr><td><?= tx('Invoice') ?></td><td style="text-align:right"><?= e((string) $sale['invoice_no']) ?></td></tr>
        <tr><td><?= tx('Date') ?></td><td style="text-align:right"><?= e(when_fmt((string) $sale['created_at'])) ?></td></tr>
        <tr><td><?= tx('Cashier') ?></td><td style="text-align:right"><?= e((string) $sale['cashier']) ?></td></tr>
      </table>
      <hr>
      <table>
        <?php foreach ($sale['items'] as $item): ?>
          <tr>
            <td colspan="2"><strong><?= e((string) $item['product_name']) ?></strong></td>
          </tr>
          <tr>
            <td><?= e(qty_fmt($item['qty'])) ?> <?= e((string) $item['unit_name']) ?> × <?= e(money($item['unit_price'])) ?></td>
            <td style="text-align:right"><?= e(money($item['line_total'])) ?></td>
          </tr>
          <?php if ((float) $item['discount_amount'] > 0): ?>
            <tr><td class="muted"><?= tx('Discount') ?></td><td style="text-align:right">-<?= e(money($item['discount_amount'])) ?></td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </table>
      <hr>
      <table>
        <tr><td><?= tx('Subtotal') ?></td><td style="text-align:right"><?= e(money($sale['subtotal'])) ?></td></tr>
        <tr><td><?= tx('Discount') ?></td><td style="text-align:right">-<?= e(money($sale['discount_total'])) ?></td></tr>
        <tr><td><strong><?= tx('Total') ?></strong></td><td style="text-align:right"><strong><?= e(money($sale['total'])) ?></strong></td></tr>
        <tr><td><?= tx('Cash') ?></td><td style="text-align:right"><?= e(money($sale['amount_received'])) ?></td></tr>
        <tr><td><?= tx('Change') ?></td><td style="text-align:right"><?= e(money($sale['change_amount'])) ?></td></tr>
      </table>
      <p class="big-change"><?= tx('Change') ?> <?= e(money($sale['change_amount'])) ?></p>
      <p class="center"><?= e(setting('receipt_footer')) ?></p>
    </article>
    <?php if ($user['role'] === 'admin' && !(int) $sale['voided']): ?>
      <form class="receipt-actions no-print" method="post" data-confirm="<?= tx('Void this sale and return the items to stock?') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="void">
        <input type="hidden" name="id" value="<?= (int) $sale['id'] ?>">
        <button class="btn btn-danger" type="submit"><?= icon('trash', 18) ?> <?= tx('Void sale') ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php html_foot(); ?>
