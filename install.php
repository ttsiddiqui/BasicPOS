<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/sample.php';

function schema_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }
    return $out;
}

function split_db_host(string $host): array
{
    $host = trim($host);
    if ($host === '') {
        $host = 'localhost';
    }
    $port = '3306';
    if (preg_match('/^(.+):(\d+)$/', $host, $m)) {
        $host = $m[1];
        $port = $m[2];
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $host)) {
        throw new RuntimeException('Database host looks invalid. Use localhost on most cPanel accounts.');
    }
    if ((int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('Database port is not valid.');
    }
    return [$host, $port];
}

$timezones = timezone_choices();
$currencies = currency_choices();
$installed = is_file(lock_path()) && is_file(config_path());
$error = null;
$done = null;

if ($installed && ($_GET['done'] ?? '') === '1' && !empty($_SESSION['install_done']) && is_array($_SESSION['install_done'])) {
    $done = $_SESSION['install_done'];
}

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('The PHP pdo_mysql extension is missing. In cPanel, open Select PHP Version and enable pdo_mysql.');
        }
        verify_csrf();
        [$host, $port] = split_db_host(post_str('db_host'));
        $dbName = post_str('db_name');
        $dbUser = post_str('db_user');
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $dbName) || !preg_match('/^[A-Za-z0-9_.-]+$/', $dbUser)) {
            throw new RuntimeException('Database name and user can only use letters, numbers, dots, dashes, and underscores.');
        }
        if (str_contains($dbPass, "\0")) {
            throw new RuntimeException('Database password is not valid.');
        }
        $business = post_str('business_name');
        $currency = post_str('currency_symbol');
        $tz = post_str('timezone');
        $adminUser = post_str('admin_username');
        $adminName = post_str('admin_name');
        $adminPass = (string) ($_POST['admin_password'] ?? '');
        $adminPass2 = (string) ($_POST['admin_password2'] ?? '');
        $cashierUser = post_str('cashier_username');
        $cashierName = post_str('cashier_name');
        $cashierPass = (string) ($_POST['cashier_password'] ?? '');
        $cashierPass2 = (string) ($_POST['cashier_password2'] ?? '');
        if ($business === '' || bp_strlen($business) > 120) {
            throw new RuntimeException('Enter the shop name.');
        }
        if (!isset($currencies[$currency])) {
            throw new RuntimeException('Choose a currency.');
        }
        if (!isset($timezones[$tz]) || !in_array($tz, timezone_identifiers_list(), true)) {
            throw new RuntimeException('Choose a timezone.');
        }
        foreach ([['Admin username', $adminUser], ['Cashier username', $cashierUser]] as $pair) {
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $pair[1])) {
                throw new RuntimeException($pair[0] . ' must be 3 to 50 letters, numbers, dots, or dashes.');
            }
        }
        if (strcasecmp($adminUser, $cashierUser) === 0) {
            throw new RuntimeException('Admin and cashier need different usernames.');
        }
        if ($adminName === '' || $cashierName === '') {
            throw new RuntimeException('Enter a name for the admin and the cashier.');
        }
        if ($adminPass !== $adminPass2 || $cashierPass !== $cashierPass2) {
            throw new RuntimeException('The passwords do not match. Type each one again.');
        }
        if (strlen($adminPass) < 6 || strlen($cashierPass) < 6) {
            throw new RuntimeException('Use at least 6 characters for each password.');
        }
        date_default_timezone_set($tz);
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $detail = $e->getMessage();
            if (str_contains($detail, '2002') || str_contains($detail, 'No such file or directory')) {
                throw new RuntimeException('Could not open the database host. On cPanel, host is usually localhost. If that fails, try 127.0.0.1.');
            }
            throw new RuntimeException('Could not sign in to the database. Check the database name, user, and password, and make sure the user has all privileges.');
        }
        $schema = file_get_contents(app_root() . '/sql/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('The database schema file is missing.');
        }
        foreach (schema_statements($schema) as $statement) {
            $pdo->exec($statement);
        }
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM bp_users')->fetchColumn();
        if ($userCount === 0) {
            $pdo->beginTransaction();
            try {
                $hasSettings = (int) $pdo->query('SELECT COUNT(*) FROM bp_settings')->fetchColumn() > 0;
                if (!$hasSettings) {
                    $st = $pdo->prepare('INSERT INTO bp_settings (id, business_name, currency_symbol, phone, address, receipt_footer, timezone) VALUES (1, ?, ?, \'\', \'\', ?, ?)');
                    $st->execute([$business, $currency, 'Thank you for your purchase', $tz]);
                }
                $now = date('Y-m-d H:i:s');
                $userSt = $pdo->prepare('INSERT INTO bp_users (username, password_hash, full_name, role, created_at) VALUES (?,?,?,?,?)');
                $userSt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), $adminName, 'admin', $now]);
                $userSt->execute([$cashierUser, password_hash($cashierPass, PASSWORD_DEFAULT), $cashierName, 'cashier', $now]);
                seed_units($pdo);
                if (!empty($_POST['sample'])) {
                    seed_sample($pdo);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        $php = "<?php\nreturn " . var_export([
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
        ], true) . ";\n";
        if (file_put_contents(config_path(), $php) === false) {
            throw new RuntimeException('BasicPOS could not write config.php. Make this folder writable, then open install.php again.');
        }
        if (file_put_contents(lock_path(), "BasicPOS by WebC\n" . date('c') . "\n") === false) {
            throw new RuntimeException('BasicPOS could not write install.lock. Make this folder writable, then open install.php again.');
        }
        $_SESSION['install_done'] = [
            'business' => $business,
            'admin' => $adminUser,
            'cashier' => $cashierUser,
            'existing' => $userCount > 0,
        ];
        redirect('install.php?done=1');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$values = [
    'db_host' => post_str('db_host') !== '' ? post_str('db_host') : 'localhost',
    'db_name' => post_str('db_name'),
    'db_user' => post_str('db_user'),
    'business_name' => post_str('business_name') !== '' ? post_str('business_name') : 'My Shop',
    'currency_symbol' => isset($currencies[post_str('currency_symbol')]) ? post_str('currency_symbol') : 'Rs',
    'timezone' => isset($timezones[post_str('timezone')]) ? post_str('timezone') : 'Asia/Karachi',
    'admin_username' => post_str('admin_username') !== '' ? post_str('admin_username') : 'admin',
    'admin_name' => post_str('admin_name') !== '' ? post_str('admin_name') : 'Admin',
    'cashier_username' => post_str('cashier_username') !== '' ? post_str('cashier_username') : 'cashier',
    'cashier_name' => post_str('cashier_name') !== '' ? post_str('cashier_name') : 'Cashier',
];
$sampleOn = $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['sample']);

html_head('Install', 'page-install');
?>
<div class="auth-shell">
  <section class="auth-hero">
    <div class="brand"><span class="brand-mark">BP</span><span><strong>BasicPOS</strong><small>by WebC</small></span></div>
    <h1>Set up your shop.</h1>
    <p>Create the database tables, the admin login, and the cashier login. The cashier can only make sales.</p>
    <ul class="hero-points">
      <li><?= icon('cart', 22) ?> Sale screen with big product buttons</li>
      <li><?= icon('inbox', 22) ?> Stock, expenses, salary, and discounts</li>
      <li><?= icon('chart', 22) ?> Daily sales and monthly profit</li>
    </ul>
  </section>
  <section class="auth-panel">
    <div class="auth-card card">
      <?php if ($installed && !$done): ?>
        <p class="kicker">BasicPOS by WebC</p>
        <h1>Already installed</h1>
        <p class="hint">This shop is ready. Log in with the admin or cashier username you created.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('index.php')) ?>">Go to login</a>
      <?php elseif ($done): ?>
        <p class="kicker">BasicPOS by WebC</p>
        <h1>You're ready</h1>
        <?php if (!empty($done['existing'])): ?>
          <p>Connected to the existing BasicPOS database. Your earlier usernames and passwords are unchanged.</p>
        <?php else: ?>
          <p><strong><?= e((string) $done['business']) ?></strong> is set up. There are two logins:</p>
          <p>Admin: <strong><?= e((string) $done['admin']) ?></strong><br>Cashier: <strong><?= e((string) $done['cashier']) ?></strong></p>
          <p class="hint">Use the passwords you just typed. The cashier only sees the sale screen.</p>
        <?php endif; ?>
        <a class="btn btn-primary btn-lg" href="<?= e(url('index.php')) ?>">Log in</a>
      <?php else: ?>
        <p class="kicker">BasicPOS by WebC</p>
        <h1>Install</h1>
        <p class="hint">In cPanel, create a MySQL database and user, then add the user to that database with all privileges.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <?php if (!extension_loaded('pdo_mysql')): ?><div class="flash flash-error">Enable pdo_mysql in cPanel Select PHP Version before installing.</div><?php endif; ?>
        <form method="post" class="stack">
          <?= csrf_field() ?>
          <h2>Database</h2>
          <div class="form-grid">
            <label class="field">Host<input name="db_host" value="<?= e($values['db_host']) ?>" required autocomplete="off"></label>
            <label class="field">Database name<input name="db_name" value="<?= e($values['db_name']) ?>" required autocomplete="off"></label>
            <label class="field">Database user<input name="db_user" value="<?= e($values['db_user']) ?>" required autocomplete="off"></label>
            <label class="field">Database password<input name="db_pass" type="password" value="" autocomplete="new-password"></label>
          </div>
          <h2>Shop</h2>
          <div class="form-grid">
            <label class="field">Shop name<input name="business_name" value="<?= e($values['business_name']) ?>" required></label>
            <label class="field">Currency
              <select name="currency_symbol">
                <?php foreach ($currencies as $symbol => $label): ?>
                  <option value="<?= e($symbol) ?>"<?= $values['currency_symbol'] === $symbol ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field span-2">Timezone
              <select name="timezone">
                <?php foreach ($timezones as $zone => $label): ?>
                  <option value="<?= e($zone) ?>"<?= $values['timezone'] === $zone ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <h2>Admin</h2>
          <p class="hint">The admin can manage products, stock, expenses, HR, discounts, and reports.</p>
          <div class="form-grid">
            <label class="field">Username<input name="admin_username" value="<?= e($values['admin_username']) ?>" required></label>
            <label class="field">Name<input name="admin_name" value="<?= e($values['admin_name']) ?>" required></label>
            <label class="field">Password<input name="admin_password" type="password" required autocomplete="new-password"></label>
            <label class="field">Confirm password<input name="admin_password2" type="password" required autocomplete="new-password"></label>
          </div>
          <h2>Cashier</h2>
          <p class="hint">The cashier can only sell products.</p>
          <div class="form-grid">
            <label class="field">Username<input name="cashier_username" value="<?= e($values['cashier_username']) ?>" required></label>
            <label class="field">Name<input name="cashier_name" value="<?= e($values['cashier_name']) ?>" required></label>
            <label class="field">Password<input name="cashier_password" type="password" required autocomplete="new-password"></label>
            <label class="field">Confirm password<input name="cashier_password2" type="password" required autocomplete="new-password"></label>
          </div>
          <label class="check"><input type="checkbox" name="sample" value="1"<?= $sampleOn ? ' checked' : '' ?>> Add sample products so you can try a sale</label>
          <button class="btn btn-primary btn-lg" type="submit">Install BasicPOS</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php html_foot(); ?>
