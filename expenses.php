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
            save_expense([
                'title' => post_str('title'),
                'amount' => post_float('amount'),
                'date' => post_str('expense_date'),
                'note' => post_str('note'),
            ], $id > 0 ? $id : null, (int) current_user()['id']);
            flash('success', $id > 0 ? 'Expense updated.' : 'Expense recorded.');
            redirect('expenses.php');
        } elseif ($action === 'delete') {
            delete_expense(post_int('id'));
            flash('success', 'Expense removed.');
            redirect('expenses.php');
        } else {
            throw new RuntimeException('That action is not available.');
        }
    } catch (RuntimeException $e) {
        if ($action === 'save') {
            $error = $e->getMessage();
        } else {
            flash('error', $e->getMessage());
            redirect('expenses.php');
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if (($_POST['action'] ?? '') === 'save' && $error) {
    $editId = post_int('id');
}
$editing = null;
if ($editId > 0) {
    $st = db()->prepare('SELECT * FROM bp_expenses WHERE id = ?');
    $st->execute([$editId]);
    $editing = $st->fetch() ?: null;
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
$rows = db()->query('SELECT * FROM bp_expenses ORDER BY expense_date DESC, id DESC LIMIT 100')->fetchAll();

layout_start('Expenses', 'expenses');
?>
<div class="page-head">
  <div>
    <h1>Expenses</h1>
    <p>Record money the shop spends, such as rent, bills, or supplies.</p>
  </div>
</div>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form class="card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
  <h2><?= $editing ? 'Edit expense' : 'New expense' ?></h2>
  <div class="form-grid">
    <label class="field span-2">Title<input name="title" required maxlength="160" value="<?= e($value('title')) ?>" placeholder="Rent, electricity, delivery"></label>
    <label class="field">Amount<input name="amount" type="number" min="0.01" step="0.01" required value="<?= e($value('amount')) ?>"></label>
    <label class="field">Date<input type="date" name="expense_date" required value="<?= e($value('expense_date', date('Y-m-d'))) ?>"></label>
    <label class="field span-2">Note<input name="note" maxlength="255" value="<?= e($value('note')) ?>"></label>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save expense</button>
    <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('expenses.php')) ?>">Cancel</a><?php endif; ?>
  </div>
</form>
<section class="card">
  <h2>Recent expenses</h2>
  <?php if (!$rows): ?>
    <?= empty_state('wallet', 'No expenses yet', 'A rent payment or a bill will show in the monthly profit report.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data stack">
        <thead><tr><th>Title</th><th>Date</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td data-label="Title"><strong><?= e((string) $row['title']) ?></strong><?= $row['note'] ? '<br><small class="muted">' . e((string) $row['note']) . '</small>' : '' ?></td>
              <td data-label="Date"><?= e(day_fmt((string) $row['expense_date'])) ?></td>
              <td class="num" data-label="Amount"><?= e(money($row['amount'])) ?></td>
              <td class="actions" data-label="">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('expenses.php?edit=' . (int) $row['id'])) ?>"><?= icon('edit', 18) ?> Edit</a>
                <form method="post" data-confirm="Remove this expense?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
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
</section>
<?php layout_end(); ?>
