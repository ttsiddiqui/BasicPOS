<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_admin();
$stats = dashboard_stats();
$hour = (int) date('G');
$hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$maxBar = 1.0;
foreach ($stats['series'] as $point) {
    if ($point['total'] > $maxBar) {
        $maxBar = $point['total'];
    }
}
$profit = (float) $stats['pnl']['profit'];

layout_start('Dashboard', 'dashboard');
?>
<div class="page-head">
  <div>
    <h1><?= e($hello) ?>, <?= e((string) $user['full_name']) ?></h1>
    <p><?= e(date('l, d M Y')) ?> · <?= e(setting('business_name')) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('pos.php')) ?>"><?= icon('cart', 22) ?> New sale</a>
  </div>
</div>

<section class="stats">
  <article class="stat">
    <span class="ico-wrap"><?= icon('cash', 28) ?></span>
    <div><span>Today's sales</span><b><?= e(money($stats['today']['total'])) ?></b></div>
  </article>
  <article class="stat">
    <span class="ico-wrap ico-ink"><?= icon('chart', 28) ?></span>
    <div><span>This month's sales</span><b><?= e(money($stats['month']['total'])) ?></b></div>
  </article>
  <article class="stat">
    <span class="ico-wrap <?= $profit < 0 ? 'ico-hot' : '' ?>"><?= icon('trend', 28) ?></span>
    <div><span>This month's profit</span><b class="<?= $profit < 0 ? 'money-bad' : 'money-good' ?>"><?= e(money($profit)) ?></b></div>
  </article>
  <article class="stat">
    <span class="ico-wrap ico-amber"><?= icon('alert', 28) ?></span>
    <div><span>Low stock</span><b><?= (int) $stats['low_count'] ?></b></div>
  </article>
</section>

<div class="two-col">
  <section class="card">
    <h2>Last 7 days</h2>
    <div class="week" aria-hidden="true">
      <?php foreach ($stats['series'] as $point): ?>
        <?php $height = max(4, (int) round(($point['total'] / $maxBar) * 100)); ?>
        <div class="col" title="<?= e(money($point['total'])) ?>">
          <i style="height: <?= $height ?>%"></i>
          <small><?= e($point['label']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="hint"><?= (int) $stats['product_count'] ?> products in the catalog.</p>
  </section>
  <section class="card">
    <h2>Jump in</h2>
    <div class="tiles">
      <a class="tile" href="<?= e(url('products.php?new=1')) ?>"><span class="ico-wrap"><?= icon('plus', 26) ?></span><span>Add product</span></a>
      <a class="tile" href="<?= e(url('inventory.php')) ?>"><span class="ico-wrap"><?= icon('inbox', 26) ?></span><span>Stock in</span></a>
      <a class="tile" href="<?= e(url('expenses.php')) ?>"><span class="ico-wrap"><?= icon('wallet', 26) ?></span><span>Expense</span></a>
      <a class="tile" href="<?= e(url('hr.php?tab=payroll')) ?>"><span class="ico-wrap"><?= icon('users', 26) ?></span><span>Payroll</span></a>
      <a class="tile" href="<?= e(url('discounts.php')) ?>"><span class="ico-wrap"><?= icon('percent', 26) ?></span><span>Discount</span></a>
      <a class="tile" href="<?= e(url('reports.php?type=pnl')) ?>"><span class="ico-wrap"><?= icon('chart', 26) ?></span><span>Profit report</span></a>
    </div>
  </section>
</div>

<div class="two-col">
  <section class="card">
    <h2>Recent sales</h2>
    <?php if (!$stats['recent']): ?>
      <?= empty_state('receipt', 'No sales yet', 'A completed sale will show up here.') ?>
    <?php else: ?>
      <div class="list-compact">
        <?php foreach ($stats['recent'] as $sale): ?>
          <a href="<?= e(url('receipt.php?id=' . (int) $sale['id'])) ?>">
            <span><strong><?= e((string) $sale['invoice_no']) ?></strong><br><small class="muted"><?= e(when_fmt((string) $sale['created_at'])) ?> · <?= e((string) $sale['cashier']) ?></small></span>
            <strong><?= e(money($sale['total'])) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Low stock</h2>
    <?php if (!$stats['low_stock']): ?>
      <?= empty_state('check', 'Stock looks fine', 'Products at or below their reorder level show here.') ?>
    <?php else: ?>
      <div class="list-compact">
        <?php foreach ($stats['low_stock'] as $product): ?>
          <a href="<?= e(url('products.php?id=' . (int) $product['id'])) ?>">
            <span><?= e((string) $product['name']) ?></span>
            <strong><?= e(qty_fmt($product['stock_qty'])) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
      <p><a href="<?= e(url('products.php?stock=low')) ?>">View all low stock</a></p>
    <?php endif; ?>
  </section>
</div>
<?php
layout_end();
