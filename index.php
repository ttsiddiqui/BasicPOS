<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(home_for(current_user()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (attempt_login(post_str('username'), (string) ($_POST['password'] ?? ''))) {
        $user = current_user();
        redirect($user ? home_for($user) : 'index.php');
    }
    redirect('index.php');
}

html_head('Log in');
?>
<div class="auth-shell">
  <section class="auth-hero">
    <div class="brand"><span class="brand-mark">BP</span><span><strong>BasicPOS</strong><small>by WebC</small></span></div>
    <h1>Sell with a clear screen.</h1>
    <p>Products, stock, expenses, salary, and a simple profit report. The cashier sees the sale screen only.</p>
    <ul class="hero-points">
      <li><?= icon('cart', 22) ?> Big buttons for fast checkout</li>
      <li><?= icon('box', 22) ?> Products, categories, and brands</li>
      <li><?= icon('chart', 22) ?> Daily, monthly, and profit reports</li>
    </ul>
  </section>
  <section class="auth-panel">
    <form class="auth-card card" method="post">
      <p class="kicker">BasicPOS by WebC</p>
      <h1>Log in</h1>
      <p class="hint"><?= e(setting('business_name', 'Your shop')) ?></p>
      <?php render_flash(); ?>
      <?= csrf_field() ?>
      <div class="stack">
        <label class="field">Username<input name="username" autocomplete="username" required autofocus></label>
        <label class="field">Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="btn btn-primary btn-lg" type="submit"><?= icon('user', 22) ?> Log in</button>
      </div>
    </form>
  </section>
</div>
<?php html_foot(); ?>
