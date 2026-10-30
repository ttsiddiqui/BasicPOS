<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$admin = require_admin();
$timezones = ['Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kolkata', 'Asia/Dhaka', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Europe/London', 'UTC'];
$currentTz = setting('timezone', 'Asia/Karachi');
if (!in_array($currentTz, $timezones, true)) {
    $timezones[] = $currentTz;
}
$error = null;
$cashier = user_by_role('cashier');
$adminRow = user_by_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        if ($action === 'shop') {
            save_settings([
                'business_name' => post_str('business_name'),
                'currency_symbol' => post_str('currency_symbol'),
                'phone' => post_str('phone'),
                'address' => post_str('address'),
                'receipt_footer' => post_str('receipt_footer'),
                'timezone' => post_str('timezone'),
            ]);
            flash('success', 'Shop details saved.');
            redirect('settings.php');
        } elseif ($action === 'admin' || $action === 'cashier') {
            if (!verify_user_password((int) $admin['id'], (string) ($_POST['current_password'] ?? ''))) {
                throw new RuntimeException('Current admin password is wrong.');
            }
            $target = $action === 'admin' ? $adminRow : $cashier;
            if (!$target) {
                throw new RuntimeException('That user was not found.');
            }
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if ($newPassword !== '' || $confirm !== '') {
                if ($newPassword !== $confirm) {
                    throw new RuntimeException('The new passwords do not match.');
                }
            }
            update_user_login((int) $target['id'], post_str('username'), post_str('full_name'), $newPassword !== '' ? $newPassword : null);
            if ($action === 'admin' && $newPassword !== '') {
                unset($_SESSION['weak_password']);
            }
            flash('success', $action === 'admin' ? 'Admin login updated.' : 'Cashier login updated.');
            redirect('settings.php');
        } else {
            throw new RuntimeException('That action is not available.');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

layout_start('Settings', 'settings');
?>
<div class="page-head">
  <div>
    <h1>Settings</h1>
    <p>Shop details for receipts, and the two logins. There is one admin and one cashier.</p>
  </div>
</div>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form class="card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="shop">
  <h2>Shop</h2>
  <div class="form-grid">
    <label class="field">Shop name<input name="business_name" required maxlength="120" value="<?= e(setting('business_name')) ?>"></label>
    <label class="field">Currency symbol<input name="currency_symbol" required maxlength="10" value="<?= e(setting('currency_symbol', 'Rs')) ?>"></label>
    <label class="field">Phone<input name="phone" maxlength="40" value="<?= e(setting('phone')) ?>"></label>
    <label class="field">Timezone
      <select name="timezone">
        <?php foreach ($timezones as $zone): ?>
          <option value="<?= e($zone) ?>"<?= setting('timezone') === $zone ? ' selected' : '' ?>><?= e($zone) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field span-2">Address<input name="address" maxlength="255" value="<?= e(setting('address')) ?>"></label>
    <label class="field span-2">Receipt note<input name="receipt_footer" maxlength="180" value="<?= e(setting('receipt_footer')) ?>"></label>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save shop</button></div>
</form>

<?php
$people = [
    'admin' => ['Admin login', $adminRow],
    'cashier' => ['Cashier login', $cashier],
];
foreach ($people as $key => [$heading, $person]):
    if (!$person) {
        continue;
    }
?>
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= e($key) ?>">
    <h2><?= e($heading) ?></h2>
    <p class="hint"><?= $key === 'cashier' ? 'This person can only open the sale screen.' : 'This person can open every page.' ?></p>
    <div class="form-grid">
      <label class="field">Username<input name="username" required maxlength="50" value="<?= e((string) $person['username']) ?>"></label>
      <label class="field">Name<input name="full_name" required maxlength="100" value="<?= e((string) $person['full_name']) ?>"></label>
      <label class="field">New password<input name="new_password" type="password" autocomplete="new-password" placeholder="Leave empty to keep"></label>
      <label class="field">Confirm new password<input name="confirm_password" type="password" autocomplete="new-password"></label>
      <label class="field span-2">Current admin password<input name="current_password" type="password" autocomplete="current-password" required></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> Save <?= e($key) ?></button></div>
  </form>
<?php endforeach; ?>
<?php layout_end(); ?>
