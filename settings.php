<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$admin = require_admin();
$timezones = timezone_choices();
$currencies = currency_choices();
$currentTz = setting('timezone', 'Asia/Karachi');
if ($currentTz !== '' && !isset($timezones[$currentTz])) {
    $timezones[$currentTz] = $currentTz;
}
$currentCurrency = setting('currency_symbol', 'Rs');
if ($currentCurrency !== '' && !isset($currencies[$currentCurrency])) {
    $currencies[$currentCurrency] = $currentCurrency;
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
                throw new RuntimeException(t('Current admin password is wrong.'));
            }
            $target = $action === 'admin' ? $adminRow : $cashier;
            if (!$target) {
                throw new RuntimeException(t('That user was not found.'));
            }
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if ($newPassword !== '' || $confirm !== '') {
                if ($newPassword !== $confirm) {
                    throw new RuntimeException(t('The new passwords do not match.'));
                }
            }
            update_user_login((int) $target['id'], post_str('username'), post_str('full_name'), $newPassword !== '' ? $newPassword : null);
            if ($action === 'admin' && $newPassword !== '') {
                unset($_SESSION['weak_password']);
            }
            flash('success', $action === 'admin' ? 'Admin login updated.' : 'Cashier login updated.');
            redirect('settings.php');
        } else {
            throw new RuntimeException(t('That action is not available.'));
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

layout_start('Settings', 'settings');
?>
<div class="page-head">
  <div>
    <h1><?= tx('Settings') ?></h1>
    <p><?= tx('Shop details for receipts, and the two logins. There is one admin and one cashier.') ?></p>
  </div>
</div>
<?php if ($error): ?><div class="flash flash-error"><?= e(t($error)) ?></div><?php endif; ?>

<form class="card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="shop">
  <h2><?= tx('Shop') ?></h2>
  <div class="form-grid">
    <label class="field"><?= tx('Shop name') ?><input name="business_name" required maxlength="120" value="<?= e(setting('business_name')) ?>"></label>
    <label class="field"><?= tx('Currency') ?>
      <select name="currency_symbol">
        <?php foreach ($currencies as $symbol => $label): ?>
          <option value="<?= e($symbol) ?>"<?= $currentCurrency === $symbol ? ' selected' : '' ?>><?= e(t($label)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><?= tx('Phone') ?><input name="phone" maxlength="40" value="<?= e(setting('phone')) ?>"></label>
    <label class="field"><?= tx('Timezone') ?>
      <select name="timezone">
        <?php foreach ($timezones as $zone => $label): ?>
          <option value="<?= e($zone) ?>"<?= $currentTz === $zone ? ' selected' : '' ?>><?= e(t($label)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field span-2"><?= tx('Address') ?><input name="address" maxlength="255" value="<?= e(setting('address')) ?>"></label>
    <label class="field span-2"><?= tx('Receipt note') ?><input name="receipt_footer" maxlength="180" value="<?= e(setting('receipt_footer')) ?>"></label>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> <?= tx('Save shop') ?></button></div>
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
    <h2><?= tx($heading) ?></h2>
    <p class="hint"><?= $key === 'cashier' ? tx('This person can only open the sale screen.') : tx('This person can open every page.') ?></p>
    <div class="form-grid">
      <label class="field"><?= tx('Username') ?><input name="username" required maxlength="50" value="<?= e((string) $person['username']) ?>"></label>
      <label class="field"><?= tx('Name') ?><input name="full_name" required maxlength="100" value="<?= e((string) $person['full_name']) ?>"></label>
      <label class="field"><?= tx('New password') ?><input name="new_password" type="password" autocomplete="new-password" placeholder="<?= tx('Leave empty to keep') ?>"></label>
      <label class="field"><?= tx('Confirm new password') ?><input name="confirm_password" type="password" autocomplete="new-password"></label>
      <label class="field span-2"><?= tx('Current admin password') ?><input name="current_password" type="password" autocomplete="current-password" required></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check', 20) ?> <?= $key === 'cashier' ? tx('Save cashier') : tx('Save admin') ?></button></div>
  </form>
<?php endforeach; ?>
<?php layout_end(); ?>
