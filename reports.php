<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$type = (string) ($_GET['type'] ?? 'daily');
if (!in_array($type, ['daily', 'range', 'monthly', 'pnl'], true)) {
    $type = 'daily';
}
$today = date('Y-m-d');
$date = valid_date((string) ($_GET['date'] ?? '')) ?? $today;
$from = valid_date((string) ($_GET['from'] ?? '')) ?? date('Y-m-01');
$to = valid_date((string) ($_GET['to'] ?? '')) ?? $today;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
$rangeError = $from > $to ? 'The start date is after the end date.' : null;
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));

layout_start('Reports', 'reports');
?>
<div class="page-head">
  <div>
    <h1>Reports</h1>
    <p>Daily sales, a custom date range, a month of sales, and a simple monthly profit.</p>
  </div>
  <button class="btn btn-ghost no-print" type="button" onclick="window.print()"><?= icon('print', 20) ?> Print</button>
</div>
<nav class="report-picks no-print">
  <a class="pick<?= $type === 'daily' ? ' active' : '' ?>" href="<?= e(url('reports.php?type=daily')) ?>"><span class="ico-wrap"><?= icon('calendar', 26) ?></span><span>Daily sale</span></a>
  <a class="pick<?= $type === 'range' ? ' active' : '' ?>" href="<?= e(url('reports.php?type=range')) ?>"><span class="ico-wrap"><?= icon('chart', 26) ?></span><span>Custom dates</span></a>
  <a class="pick<?= $type === 'monthly' ? ' active' : '' ?>" href="<?= e(url('reports.php?type=monthly')) ?>"><span class="ico-wrap"><?= icon('receipt', 26) ?></span><span>Monthly sale</span></a>
  <a class="pick<?= $type === 'pnl' ? ' active' : '' ?>" href="<?= e(url('reports.php?type=pnl')) ?>"><span class="ico-wrap"><?= icon('trend', 26) ?></span><span>Profit / loss</span></a>
</nav>

<?php if ($type === 'daily' || $type === 'range'): ?>
  <?php
    $start = $type === 'daily' ? $date : $from;
    $end = $type === 'daily' ? $date : $to;
    $summary = $rangeError ? ['invoices' => 0, 'subtotal' => 0, 'discount_total' => 0, 'total' => 0] : sales_summary($start, $end);
    $sales = $rangeError ? [] : sales_between($start, $end);
    $days = ($type === 'range' && !$rangeError) ? sales_by_day($start, $end) : [];
  ?>
  <form class="card no-print" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div class="form-grid">
      <?php if ($type === 'daily'): ?>
        <label class="field">Date<input type="date" name="date" value="<?= e($date) ?>"></label>
      <?php else: ?>
        <label class="field">From<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label class="field">To<input type="date" name="to" value="<?= e($to) ?>"></label>
      <?php endif; ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Show</button></div>
  </form>
  <?php if ($rangeError): ?><div class="flash flash-error"><?= e($rangeError) ?></div><?php endif; ?>
  <section class="stats">
    <article class="stat"><span class="ico-wrap"><?= icon('cash', 28) ?></span><div><span>Sales</span><b><?= e(money($summary['total'])) ?></b></div></article>
    <article class="stat"><span class="ico-wrap ico-ink"><?= icon('receipt', 28) ?></span><div><span>Invoices</span><b><?= (int) $summary['invoices'] ?></b></div></article>
    <article class="stat"><span class="ico-wrap ico-amber"><?= icon('percent', 28) ?></span><div><span>Discounts</span><b><?= e(money($summary['discount_total'])) ?></b></div></article>
    <article class="stat"><span class="ico-wrap"><?= icon('chart', 28) ?></span><div><span>Before discount</span><b><?= e(money($summary['subtotal'])) ?></b></div></article>
  </section>
  <?php if ($days): ?>
    <section class="card">
      <h2>By day</h2>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Date</th><th class="num">Invoices</th><th class="num">Discount</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($days as $day): ?>
              <tr>
                <td data-label="Date"><a href="<?= e(url('reports.php?type=daily&date=' . $day['day'])) ?>"><?= e(day_fmt((string) $day['day'])) ?></a></td>
                <td class="num" data-label="Invoices"><?= (int) $day['invoices'] ?></td>
                <td class="num" data-label="Discount"><?= e(money($day['discount_total'])) ?></td>
                <td class="num" data-label="Total"><?= e(money($day['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
  <section class="card">
    <h2>Invoices</h2>
    <?php if (!$sales): ?>
      <?= empty_state('receipt', 'No sales in this period', 'Completed sales that are not void show here.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Invoice</th><th>When</th><th>Cashier</th><th class="num">Items</th><th class="num">Discount</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($sales as $sale): ?>
              <tr>
                <td data-label="Invoice"><a href="<?= e(url('receipt.php?id=' . (int) $sale['id'])) ?>"><strong><?= e((string) $sale['invoice_no']) ?></strong></a></td>
                <td data-label="When"><?= e(when_fmt((string) $sale['created_at'])) ?></td>
                <td data-label="Cashier"><?= e((string) $sale['cashier']) ?></td>
                <td class="num" data-label="Items"><?= e(qty_fmt($sale['item_qty'])) ?></td>
                <td class="num" data-label="Discount"><?= e(money($sale['discount_total'])) ?></td>
                <td class="num" data-label="Total"><?= e(money($sale['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php elseif ($type === 'monthly'): ?>
  <?php
    $summary = sales_summary($monthStart, $monthEnd);
    $days = sales_by_day($monthStart, $monthEnd);
  ?>
  <form class="card no-print" method="get">
    <input type="hidden" name="type" value="monthly">
    <div class="form-grid">
      <label class="field">Month
        <select name="month"><?php foreach ($months as $num => $name): ?><option value="<?= $num ?>"<?= $num === $month ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
      </label>
      <label class="field">Year
        <select name="year"><?php for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
      </label>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Show <?= e(month_label($year, $month)) ?></button></div>
  </form>
  <section class="stats">
    <article class="stat"><span class="ico-wrap"><?= icon('cash', 28) ?></span><div><span><?= e(month_label($year, $month)) ?></span><b><?= e(money($summary['total'])) ?></b></div></article>
    <article class="stat"><span class="ico-wrap ico-ink"><?= icon('receipt', 28) ?></span><div><span>Invoices</span><b><?= (int) $summary['invoices'] ?></b></div></article>
    <article class="stat"><span class="ico-wrap ico-amber"><?= icon('percent', 28) ?></span><div><span>Discounts</span><b><?= e(money($summary['discount_total'])) ?></b></div></article>
    <article class="stat"><span class="ico-wrap"><?= icon('chart', 28) ?></span><div><span>Average sale</span><b><?= e(money($summary['invoices'] ? $summary['total'] / $summary['invoices'] : 0)) ?></b></div></article>
  </section>
  <section class="card">
    <h2>Each day</h2>
    <?php if (!$days): ?>
      <?= empty_state('calendar', 'No sales this month', 'Days with sales will be listed here.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Date</th><th class="num">Invoices</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($days as $day): ?>
              <tr>
                <td data-label="Date"><a href="<?= e(url('reports.php?type=daily&date=' . $day['day'])) ?>"><?= e(day_fmt((string) $day['day'])) ?></a></td>
                <td class="num" data-label="Invoices"><?= (int) $day['invoices'] ?></td>
                <td class="num" data-label="Total"><?= e(money($day['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <?php
    $pnl = pnl_for_range($monthStart, $monthEnd);
    $buySt = db()->prepare('SELECT reference_no, purchased_on, total_cost, note FROM bp_purchases WHERE purchased_on >= ? AND purchased_on <= ? ORDER BY purchased_on, id');
    $buySt->execute([$monthStart, $monthEnd]);
    $purchases = $buySt->fetchAll();
    $paySt = db()->prepare('SELECT p.net_pay, p.paid_on, p.period_year, p.period_month, e.name FROM bp_payrolls p JOIN bp_employees e ON e.id = p.employee_id WHERE p.paid_on >= ? AND p.paid_on <= ? ORDER BY p.paid_on, p.id');
    $paySt->execute([$monthStart, $monthEnd]);
    $payrollRows = $paySt->fetchAll();
    $advSt = db()->prepare('SELECT a.amount, a.given_on, a.kind, e.name FROM bp_advances a JOIN bp_employees e ON e.id = a.employee_id WHERE a.given_on >= ? AND a.given_on <= ? ORDER BY a.given_on, a.id');
    $advSt->execute([$monthStart, $monthEnd]);
    $advanceRows = $advSt->fetchAll();
    $expSt = db()->prepare('SELECT title, amount, expense_date FROM bp_expenses WHERE expense_date >= ? AND expense_date <= ? ORDER BY expense_date, id');
    $expSt->execute([$monthStart, $monthEnd]);
    $expenseRows = $expSt->fetchAll();
    $profit = (float) $pnl['profit'];
  ?>
  <form class="card no-print" method="get">
    <input type="hidden" name="type" value="pnl">
    <div class="form-grid">
      <label class="field">Month
        <select name="month"><?php foreach ($months as $num => $name): ?><option value="<?= $num ?>"<?= $num === $month ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
      </label>
      <label class="field">Year
        <select name="year"><?php for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
      </label>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Show <?= e(month_label($year, $month)) ?></button></div>
  </form>
  <h2><?= e(month_label($year, $month)) ?></h2>
  <p class="hint">Profit = sales − purchases − payroll paid − advances given − expenses. An advance deducted from salary is not counted twice.</p>
  <div class="pnl">
    <div><span>Sales</span><b><?= e(money($pnl['sales'])) ?></b></div>
    <div><span>Purchases</span><b><?= e(money($pnl['purchases'])) ?></b></div>
    <div><span>Payroll paid</span><b><?= e(money($pnl['payroll'])) ?></b></div>
    <div><span>Advances and loans given</span><b><?= e(money($pnl['advances'])) ?></b></div>
    <div><span>Expenses</span><b><?= e(money($pnl['expenses'])) ?></b></div>
    <div class="pnl-total"><span><?= $profit < 0 ? 'Loss' : 'Profit' ?></span><b><?= e(money($profit)) ?></b></div>
  </div>
  <div class="two-col">
    <section class="card">
      <h2>Purchases</h2>
      <?php if (!$purchases): ?><p class="hint">None this month.</p><?php else: ?>
        <div class="list-compact">
          <?php foreach ($purchases as $row): ?>
            <div class="list-row"><span><?= e((string) $row['reference_no']) ?><br><small class="muted"><?= e(day_fmt((string) $row['purchased_on'])) ?></small></span><strong><?= e(money($row['total_cost'])) ?></strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2>Expenses</h2>
      <?php if (!$expenseRows): ?><p class="hint">None this month.</p><?php else: ?>
        <div class="list-compact">
          <?php foreach ($expenseRows as $row): ?>
            <div class="list-row"><span><?= e((string) $row['title']) ?><br><small class="muted"><?= e(day_fmt((string) $row['expense_date'])) ?></small></span><strong><?= e(money($row['amount'])) ?></strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2>Payroll</h2>
      <?php if (!$payrollRows): ?><p class="hint">None paid this month.</p><?php else: ?>
        <div class="list-compact">
          <?php foreach ($payrollRows as $row): ?>
            <div class="list-row"><span><?= e((string) $row['name']) ?><br><small class="muted"><?= e(day_fmt((string) $row['paid_on'])) ?></small></span><strong><?= e(money($row['net_pay'])) ?></strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2>Advances and loans</h2>
      <?php if (!$advanceRows): ?><p class="hint">None given this month.</p><?php else: ?>
        <div class="list-compact">
          <?php foreach ($advanceRows as $row): ?>
            <div class="list-row"><span><?= e((string) $row['name']) ?> · <?= e((string) $row['kind']) ?><br><small class="muted"><?= e(day_fmt((string) $row['given_on'])) ?></small></span><strong><?= e(money($row['amount'])) ?></strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
