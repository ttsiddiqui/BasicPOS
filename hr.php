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
            throw new RuntimeException('That action is not available.');
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
$months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
$payrollMonth = (($_POST['action'] ?? '') === 'issue_payroll') ? post_int('month') : (int) date('n');
$payrollYear = (($_POST['action'] ?? '') === 'issue_payroll') ? post_int('year') : (int) date('Y');
if ($payrollMonth < 1 || $payrollMonth > 12) {
    $payrollMonth = (int) date('n');
}

layout_start('HR', 'hr');
?>
<div class="page-head">
  <div>
    <h1>HR</h1>
    <p>Staff, monthly salary, advances, and loans. Payroll can deduct an outstanding advance.</p>
  </div>
</div>
<nav class="tabs">
  <a class="<?= $tab === 'employees' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=employees')) ?>"><?= icon('user', 18) ?> Employees</a>
  <a class="<?= $tab === 'advances' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=advances')) ?>"><?= icon('wallet', 18) ?> Advances</a>
  <a class="<?= $tab === 'payroll' ? 'active' : '' ?>" href="<?= e(url('hr.php?tab=payroll')) ?>"><?= icon('cash', 18) ?> Payroll</a>
</nav>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($tab === 'employees'): ?>
  <?php $activeChecked = (($_POST['action'] ?? '') === 'save_employee') ? isset($_POST['active']) : ($editing ? (int) $editing['active'] === 1 : true); ?>
  <form class="card" method="post" action="<?= e(url('hr.php?tab=employees')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_employee">
    <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
    <h2><?= $editing ? 'Edit employee' : 'New employee' ?></h2>
    <div class="form-grid">
      <label class="field">Name<input name="name" required maxlength="120" value="<?= e($empValue('name')) ?>"></label>
      <label class="field">Phone<input name="phone" maxlength="40" value="<?= e($empValue('phone')) ?>"></label>
      <label class="field">Position<input name="position" maxlength="80" value="<?= e($empValue('position')) ?>" placeholder="Helper, cashier"></label>
      <label class="field">Monthly salary<input name="monthly_salary" type="number" min="0" step="0.01" required value="<?= e($empValue('monthly_salary', '0')) ?>"></label>
      <label class="field">Joined<input type="date" name="joined_on" value="<?= e($empValue('joined_on')) ?>"></label>
      <label class="check"> <input type="checkbox" name="active" value="1"<?= $activeChecked ? ' checked' : '' ?>> Active</label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save employee</button>
      <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('hr.php?tab=employees')) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
  <section class="card">
    <h2>Team</h2>
    <?php if (!$employees): ?>
      <?= empty_state('users', 'No employees yet', 'Add someone before issuing payroll.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Name</th><th>Position</th><th class="num">Salary</th><th class="num">Advance left</th><th>Last payroll</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($employees as $employee): ?>
              <tr>
                <td data-label="Name"><strong><?= e((string) $employee['name']) ?></strong><?php if (!(int) $employee['active']): ?> <span class="badge badge-off">Hidden</span><?php endif; ?><br><small class="muted"><?= e((string) $employee['phone']) ?></small></td>
                <td data-label="Position"><?= e((string) ($employee['position'] ?: '—')) ?></td>
                <td class="num" data-label="Salary"><?= e(money($employee['monthly_salary'])) ?></td>
                <td class="num" data-label="Advance left"><?= e(money($employee['balance'])) ?></td>
                <td data-label="Last payroll"><?= e((string) ($employee['last_period'] ?: '—')) ?></td>
                <td class="actions" data-label="">
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('hr.php?tab=employees&edit=' . (int) $employee['id'])) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <?php if ((int) $employee['active']): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('hr.php?tab=payroll&employee=' . (int) $employee['id'])) ?>"><?= icon('cash', 18) ?> Pay</a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_employee">
                      <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                      <input type="hidden" name="active" value="0">
                      <button class="btn btn-ghost btn-sm" type="submit">Hide</button>
                    </form>
                  <?php else: ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_employee">
                      <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                      <input type="hidden" name="active" value="1">
                      <button class="btn btn-ghost btn-sm" type="submit">Activate</button>
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
    <h2>Give advance or loan</h2>
    <div class="form-grid">
      <label class="field">Employee
        <select name="employee_id" required>
          <option value="">Choose</option>
          <?php foreach ($employees as $employee): ?>
            <option value="<?= (int) $employee['id'] ?>"><?= e((string) $employee['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Type
        <select name="kind">
          <option value="advance">Advance</option>
          <option value="loan">Loan</option>
        </select>
      </label>
      <label class="field">Amount<input name="amount" type="number" min="0.01" step="0.01" required></label>
      <label class="field">Date<input type="date" name="given_on" required value="<?= e(date('Y-m-d')) ?>"></label>
      <label class="field span-2">Note<input name="note" maxlength="255"></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save</button>
    </div>
  </form>
  <section class="card">
    <h2>Advances and loans</h2>
    <?php if (!$advances): ?>
      <?= empty_state('wallet', 'None recorded', 'Money given before payday shows here and can be deducted from salary.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Employee</th><th>Type</th><th>Date</th><th class="num">Amount</th><th class="num">Left</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($advances as $row): ?>
              <tr>
                <td data-label="Employee"><?= e((string) $row['name']) ?></td>
                <td data-label="Type"><?= e(ucfirst((string) $row['kind'])) ?></td>
                <td data-label="Date"><?= e(day_fmt((string) $row['given_on'])) ?><?= $row['note'] ? '<br><small class="muted">' . e((string) $row['note']) . '</small>' : '' ?></td>
                <td class="num" data-label="Amount"><?= e(money($row['amount'])) ?></td>
                <td class="num" data-label="Left"><?= e(money($row['remaining'])) ?></td>
                <td class="actions" data-label="">
                  <?php if (money_str($row['amount']) === money_str($row['remaining'])): ?>
                    <form method="post" data-confirm="Remove this advance?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_advance">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 18) ?> Remove</button>
                    </form>
                  <?php else: ?>
                    <span class="badge badge-off">On payroll</span>
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
    <h2>Issue payroll</h2>
    <p class="hint">One salary per employee each month. Deducted advances are not paid out again.</p>
    <div class="form-grid">
      <label class="field">Employee
        <select name="employee_id" id="employee" required>
          <?php foreach ($activeEmployees as $employee): ?>
            <option value="<?= (int) $employee['id'] ?>" data-salary="<?= e(money_str($employee['monthly_salary'])) ?>" data-balance="<?= e(money_str($employee['balance'])) ?>"<?= (int) $employee['id'] === $selectedEmployee ? ' selected' : '' ?>><?= e((string) $employee['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Month
        <select name="month">
          <?php foreach ($months as $num => $name): ?>
            <option value="<?= $num ?>"<?= $num === $payrollMonth ? ' selected' : '' ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Year
        <select name="year">
          <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>"<?= $y === $payrollYear ? ' selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="field">Paid on<input type="date" name="paid_on" required value="<?= e((($_POST['action'] ?? '') === 'issue_payroll') ? post_str('paid_on') : date('Y-m-d')) ?>"></label>
      <label class="field">Salary<input id="baseSalary" name="base_salary" type="number" min="0.01" step="0.01" required value="<?= e($selectedRow ? money_str($selectedRow['monthly_salary']) : '') ?>"></label>
      <label class="field">Deduct advance<input id="deduct" name="advance_deducted" type="number" min="0" step="0.01" value="<?= e($selectedRow ? money_str(min((float) $selectedRow['monthly_salary'], (float) $selectedRow['balance'])) : '0') ?>"></label>
      <p class="span-2 hint">Outstanding advance: <strong id="balanceOut"><?= e($selectedRow ? money($selectedRow['balance']) : money(0)) ?></strong><br>Take-home pay: <strong id="netOut"><?= e($selectedRow ? money(max(0, (float) $selectedRow['monthly_salary'] - min((float) $selectedRow['monthly_salary'], (float) $selectedRow['balance']))) : money(0)) ?></strong></p>
      <label class="field span-2">Note<input name="note" maxlength="255" value="<?= e((($_POST['action'] ?? '') === 'issue_payroll') ? post_str('note') : '') ?>"></label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"<?= $activeEmployees ? '' : ' disabled' ?>><?= icon('check', 20) ?> Issue payroll</button>
    </div>
  </form>
  <section class="card">
    <h2>Payroll history</h2>
    <?php if (!$payrolls): ?>
      <?= empty_state('cash', 'No payroll yet', 'Issued salaries show here and in the monthly profit report.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data stack">
          <thead><tr><th>Employee</th><th>Month</th><th>Paid on</th><th class="num">Salary</th><th class="num">Deducted</th><th class="num">Paid out</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($payrolls as $row): ?>
              <tr>
                <td data-label="Employee"><?= e((string) $row['name']) ?></td>
                <td data-label="Month"><?= e(month_label((int) $row['period_year'], (int) $row['period_month'])) ?></td>
                <td data-label="Paid on"><?= e(day_fmt((string) $row['paid_on'])) ?></td>
                <td class="num" data-label="Salary"><?= e(money($row['base_salary'])) ?></td>
                <td class="num" data-label="Deducted"><?= e(money($row['advance_deducted'])) ?></td>
                <td class="num" data-label="Paid out"><?= e(money($row['net_pay'])) ?></td>
                <td class="actions" data-label="">
                  <form method="post" data-confirm="Undo this payroll and put the deducted advance back?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo_payroll">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Undo</button>
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
