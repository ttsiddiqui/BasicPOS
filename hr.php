<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$tab = (string) ($_GET['tab'] ?? 'employees');
if (!in_array($tab, ['employees', 'advances', 'payroll'], true)) {
    $tab = 'employees';
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        if ($action === 'save_employee') {
            $id = post_int('id');
            save_employee([
                'name' => post_str('name'),
                'phone' => post_str('phone'),
                'position' => post_str('position'),
                'monthly_salary' => post_float('monthly_salary'),
                'joined_on' => post_str('joined_on'),
                'active' => isset($_POST['active']) ? 1 : 0,
            ], $id > 0 ? $id : null);
            flash('success', $id > 0 ? 'Employee updated.' : 'Employee added.');
            redirect('hr.php?tab=employees');
        } elseif ($action === 'toggle_employee') {
            set_employee_active(post_int('id'), post_str('active') === '1');
            flash('success', 'Employee updated.');
            redirect('hr.php?tab=employees');
        } elseif ($action === 'save_advance') {
            save_advance([
                'employee_id' => post_int('employee_id'),
                'kind' => post_str('kind'),
                'amount' => post_float('amount'),
                'date' => post_str('given_on'),
                'note' => post_str('note'),
            ], (int) current_user()['id']);
            flash('success', 'Advance recorded.');
            redirect('hr.php?tab=advances');
        } elseif ($action === 'delete_advance') {
            delete_advance(post_int('id'));
            flash('success', 'Advance removed.');
            redirect('hr.php?tab=advances');
        } elseif ($action === 'issue_payroll') {
            issue_payroll([
                'employee_id' => post_int('employee_id'),
                'month' => post_int('month'),
                'year' => post_int('year'),
                'paid_on' => post_str('paid_on'),
                'base_salary' => post_float('base_salary'),
                'advance_deducted' => post_float('advance_deducted'),
                'note' => post_str('note'),
            ], (int) current_user()['id']);
            flash('success', 'Payroll issued.');
            redirect('hr.php?tab=payroll');
        } elseif ($action === 'undo_payroll') {
            undo_payroll(post_int('id'));
            flash('success', 'Payroll undone. Deducted advances were put back.');
            redirect('hr.php?tab=payroll');
        } else {
            throw new RuntimeException(t('That action is not available.'));
        }
    } catch (RuntimeException $e) {
        $keep = ['save_employee' => 'employees', 'save_advance' => 'advances', 'issue_payroll' => 'payroll'];
        if (isset($keep[$action])) {
            $error = $e->getMessage();
            $tab = $keep[$action];
        } else {
            flash('error', $e->getMessage());
            redirect('hr.php?tab=' . $tab);
        }
    }
}

$employees = db()->query('SELECT e.id, e.name, e.phone, e.position, e.monthly_salary, e.active, e.joined_on, COALESCE((SELECT SUM(remaining) FROM bp_advances a WHERE a.employee_id = e.id), 0) AS balance, (SELECT CONCAT(period_year, \'-\', LPAD(period_month, 2, \'0\')) FROM bp_payrolls p WHERE p.employee_id = e.id ORDER BY period_year DESC, period_month DESC LIMIT 1) AS last_period FROM bp_employees e ORDER BY e.active DESC, e.name')->fetchAll();
$activeEmployees = array_values(array_filter($employees, static fn (array $row): bool => (int) $row['active'] === 1));
$advances = db()->query('SELECT a.*, e.name FROM bp_advances a JOIN bp_employees e ON e.id = a.employee_id ORDER BY a.given_on DESC, a.id DESC LIMIT 50')->fetchAll();
$payrolls = db()->query('SELECT p.*, e.name FROM bp_payrolls p JOIN bp_employees e ON e.id = p.employee_id ORDER BY p.paid_on DESC, p.id DESC LIMIT 40')->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
if (($_POST['action'] ?? '') === 'save_employee' && $error) {
    $editId = post_int('id');
}
$editing = null;
foreach ($employees as $employee) {
    if ((int) $employee['id'] === $editId) {
        $editing = $employee;
    }
}
$empValue = static function (string $key, string $fallback = '') use ($editing): string {
    if (($_POST['action'] ?? '') === 'save_employee' && array_key_exists($key, $_POST)) {
        return trim((string) $_POST[$key]);
    }
    if ($editing && isset($editing[$key]) && $editing[$key] !== null) {
        return (string) $editing[$key];
    }
    return $fallback;
};
$selectedEmployee = (int) ($_GET['employee'] ?? 0);
if (($_POST['action'] ?? '') === 'issue_payroll') {
    $selectedEmployee = post_int('employee_id');
}
$selectedRow = null;
foreach ($activeEmployees as $employee) {
    if ((int) $employee['id'] === $selectedEmployee) {
        $selectedRow = $employee;
    }
}
if (!$selectedRow && $activeEmployees) {
    $selectedRow = $activeEmployees[0];
    $selectedEmployee = (int) $selectedRow['id'];
}
$months = month_names();
$payrollMonth = (($_POST['action'] ?? '') === 'issue_payroll') ? post_int('month') : (int) date('n');
$payrollYear = (($_POST['action'] ?? '') === 'issue_payroll') ? post_int('year') : (int) date('Y');
if ($payrollMonth < 1 || $payrollMonth > 12) {
    $payrollMonth = (int) date('n');
}

layout_start('HR', 'hr');
?>
<div class="page-head">
  <div>
    <h1><?= tx('HR') ?></h1>
    <p><?= tx('Staff, monthly salary, advances, and loans. Payroll can deduct an outstanding advance.') ?></p>
  </div>
</div>
<nav class="tabs">
  <a class="<?= $tab === 'employees' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=employees')) ?>"><?= icon('user', 18) ?> <?= tx('Employees') ?></a>
  <a class="<?= $tab === 'advances' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=advances')) ?>"><?= icon('wallet', 18) ?> <?= tx('Advances') ?></a>
  <a class="<?= $tab === 'payroll' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=payroll')) ?>"><?= icon('cash', 18) ?> <?= tx('Payroll') ?></a>
</nav>
<?php if ($error): ?><div class="flash flash-error"><?= e(t($error)) ?></div><?php endif; ?>

<?php if ($tab === 'employees'): ?>
  <?php $activeChecked = (($_POST['action'] ?? '') === 'save_employee') ? isset($_POST['active']) : ($editing ? (int) $editing['active'] === 1 : true); ?>
  <form class="card" method="post" action="<?= e(url('hr.php?tab=employees')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_employee">
    <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
    <h2><?= $editing ? tx('Edit employee') : tx('New employee') ?></h2>
    <div class="form-grid">
      <label class="field"><?= tx('Name') ?><input name="name" required maxlength="120" value="<?= e($empValue('name')) ?>"></label>
      <label class="field"><?= tx('Phone') ?><input name="phone" maxlength="40" value="<?= e($empValue('phone')) ?>"></label>
      <label class="field"><?= tx('Position') ?><input name="position" maxlength="80" value="<?= e($empValue('position')) ?>" placeholder="<?= tx('Helper, cashier') ?>"></label>
      <label class="field"><?= tx('Monthly salary') ?><input name="monthly_salary" type="number" min="0" step="0.01" required value="<?= e($empValue('monthly_salary', '0')) ?>"></label>
      <label class="field"><?= tx('Joined') ?><input type="date" name="joined_on" value="<?= e($empValue('joined_on')) ?>"></label>
      <label class="check"> <input type="checkbox" name="active" value="1"<?= $activeChecked ? ' checked' : '' ?>> <?= tx('Active') ?></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> <?= tx('Save employee') ?></button>
      <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('hr.php?tab=employees')) ?>"><?= tx('Cancel') ?></a><?php endif; ?>
    </div>
  </form>
  <section class="card">
    <h2><?= tx('Team') ?></h2>
    <?php if (!$employees): ?>
      <?= empty_state('users', t('No employees yet'), t('Add someone before issuing payroll.')) ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th><?= tx('Name') ?></th><th><?= tx('Position') ?></th><th class="num"><?= tx('Salary') ?></th><th class="num"><?= tx('Advance left') ?></th><th><?= tx('Last payroll') ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($employees as $employee): ?>
              <tr>
                <td data-label="<?= tx('Name') ?>"><strong><?= e((string) $employee['name']) ?></strong><?php if (!(int) $employee['active']): ?> <span class="badge badge-off"><?= tx('Hidden') ?></span><?php endif; ?><br><small class="muted"><?= e((string) $employee['phone']) ?></small></td>
                <td data-label="<?= tx('Position') ?>"><?= e((string) ($employee['position'] ?: '—')) ?></td>
                <td class="num" data-label="<?= tx('Salary') ?>"><?= e(money($employee['monthly_salary'])) ?></td>
                <td class="num" data-label="<?= tx('Advance left') ?>"><?= e(money($employee['balance'])) ?></td>
                <td data-label="<?= tx('Last payroll') ?>"><?= e((string) ($employee['last_period'] ?: '—')) ?></td>
                <td class="actions" data-label="">
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('hr.php?tab=employees&edit=' . (int) $employee['id'])) ?>"><?= icon('edit', 18) ?> <?= tx('Edit') ?></a>
                  <?php if ((int) $employee['active']): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('hr.php?tab=payroll&employee=' . (int) $employee['id'])) ?>"><?= icon('cash', 18) ?> <?= tx('Pay') ?></a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_employee">
                      <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                      <input type="hidden" name="active" value="0">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= tx('Hide') ?></button>
                    </form>
                  <?php else: ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_employee">
                      <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                      <input type="hidden" name="active" value="1">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= tx('Activate') ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php elseif ($tab === 'advances'): ?>
  <form class="card" method="post" action="<?= e(url('hr.php?tab=advances')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_advance">
    <h2><?= tx('Give advance or loan') ?></h2>
    <div class="form-grid">
      <label class="field"><?= tx('Employee') ?>
        <select name="employee_id" required>
          <option value=""><?= tx('Choose') ?></option>
          <?php foreach ($employees as $employee): ?>
            <option value="<?= (int) $employee['id'] ?>"><?= e((string) $employee['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><?= tx('Type') ?>
        <select name="kind">
          <option value="advance"><?= tx('Advance') ?></option>
          <option value="loan"><?= tx('Loan') ?></option>
        </select>
      </label>
      <label class="field"><?= tx('Amount') ?><input name="amount" type="number" min="0.01" step="0.01" required></label>
      <label class="field"><?= tx('Date') ?><input type="date" name="given_on" required value="<?= e(date('Y-m-d')) ?>"></label>
      <label class="field span-2"><?= tx('Note') ?><input name="note" maxlength="255"></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> <?= tx('Save') ?></button>
    </div>
  </form>
  <section class="card">
    <h2><?= tx('Advances and loans') ?></h2>
    <?php if (!$advances): ?>
      <?= empty_state('wallet', t('None recorded'), t('Money given before payday shows here and can be deducted from salary.')) ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th><?= tx('Employee') ?></th><th><?= tx('Type') ?></th><th><?= tx('Date') ?></th><th class="num"><?= tx('Amount') ?></th><th class="num"><?= tx('Left') ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($advances as $row): ?>
              <tr>
                <td data-label="<?= tx('Employee') ?>"><?= e((string) $row['name']) ?></td>
                <td data-label="<?= tx('Type') ?>"><?= tx(ucfirst((string) $row['kind'])) ?></td>
                <td data-label="<?= tx('Date') ?>"><?= e(day_fmt((string) $row['given_on'])) ?><?= $row['note'] ? '<br><small class="muted">' . e((string) $row['note']) . '</small>' : '' ?></td>
                <td class="num" data-label="<?= tx('Amount') ?>"><?= e(money($row['amount'])) ?></td>
                <td class="num" data-label="<?= tx('Left') ?>"><?= e(money($row['remaining'])) ?></td>
                <td class="actions" data-label="">
                  <?php if (money_str($row['amount']) === money_str($row['remaining'])): ?>
                    <form method="post" data-confirm="<?= tx('Remove this advance?') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_advance">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> <?= tx('Remove') ?></button>
                    </form>
                  <?php else: ?>
                    <span class="badge badge-off"><?= tx('On payroll') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <form class="card" method="post" action="<?= e(url('hr.php?tab=payroll')) ?>" id="payrollForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="issue_payroll">
    <h2><?= tx('Issue payroll') ?></h2>
    <p class="hint"><?= tx('One salary per employee each month. Deducted advances are not paid out again.') ?></p>
    <div class="form-grid">
      <label class="field"><?= tx('Employee') ?>
        <select name="employee_id" id="employee" required>
          <?php foreach ($activeEmployees as $employee): ?>
            <option value="<?= (int) $employee['id'] ?>" data-salary="<?= e(money_str($employee['monthly_salary'])) ?>" data-balance="<?= e(money_str($employee['balance'])) ?>"<?= (int) $employee['id'] === $selectedEmployee ? ' selected' : '' ?>><?= e((string) $employee['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><?= tx('Month') ?>
        <select name="month">
          <?php foreach ($months as $num => $name): ?>
            <option value="<?= $num ?>"<?= $num === $payrollMonth ? ' selected' : '' ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><?= tx('Year') ?>
        <select name="year">
          <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>"<?= $y === $payrollYear ? ' selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="field"><?= tx('Paid on') ?><input type="date" name="paid_on" required value="<?= e((($_POST['action'] ?? '') === 'issue_payroll') ? post_str('paid_on') : date('Y-m-d')) ?>"></label>
      <label class="field"><?= tx('Salary') ?><input id="baseSalary" name="base_salary" type="number" min="0.01" step="0.01" required value="<?= e($selectedRow ? money_str($selectedRow['monthly_salary']) : '') ?>"></label>
      <label class="field"><?= tx('Deduct advance') ?><input id="deduct" name="advance_deducted" type="number" min="0" step="0.01" value="<?= e($selectedRow ? money_str(min((float) $selectedRow['monthly_salary'], (float) $selectedRow['balance'])) : '0') ?>"></label>
      <p class="span-2 hint"><?= tx('Outstanding advance: ') ?><strong id="balanceOut"><?= e($selectedRow ? money($selectedRow['balance']) : money(0)) ?></strong><br><?= tx('Take-home pay: ') ?><strong id="netOut"><?= e($selectedRow ? money(max(0, (float) $selectedRow['monthly_salary'] - min((float) $selectedRow['monthly_salary'], (float) $selectedRow['balance']))) : money(0)) ?></strong></p>
      <label class="field span-2"><?= tx('Note') ?><input name="note" maxlength="255" value="<?= e((($_POST['action'] ?? '') === 'issue_payroll') ? post_str('note') : '') ?>"></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"<?= $activeEmployees ? '' : ' disabled' ?>><?= icon('check', 20) ?> <?= tx('Issue payroll') ?></button>
    </div>
  </form>
  <section class="card">
    <h2><?= tx('Payroll history') ?></h2>
    <?php if (!$payrolls): ?>
      <?= empty_state('cash', t('No payroll yet'), t('Issued salaries show here and in the monthly profit report.')) ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th><?= tx('Employee') ?></th><th><?= tx('Month') ?></th><th><?= tx('Paid on') ?></th><th class="num"><?= tx('Salary') ?></th><th class="num"><?= tx('Deducted') ?></th><th class="num"><?= tx('Paid out') ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($payrolls as $row): ?>
              <tr>
                <td data-label="<?= tx('Employee') ?>"><?= e((string) $row['name']) ?></td>
                <td data-label="<?= tx('Month') ?>"><?= e(month_label((int) $row['period_year'], (int) $row['period_month'])) ?></td>
                <td data-label="<?= tx('Paid on') ?>"><?= e(day_fmt((string) $row['paid_on'])) ?></td>
                <td class="num" data-label="<?= tx('Salary') ?>"><?= e(money($row['base_salary'])) ?></td>
                <td class="num" data-label="<?= tx('Deducted') ?>"><?= e(money($row['advance_deducted'])) ?></td>
                <td class="num" data-label="<?= tx('Paid out') ?>"><?= e(money($row['net_pay'])) ?></td>
                <td class="actions" data-label="">
                  <form method="post" data-confirm="<?= tx('Undo this payroll and put the deducted advance back?') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo_payroll">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= tx('Undo') ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php layout_end(); ?>
