<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

function pos_query_from_request(): string
{
    $q = trim((string) ($_POST['q'] ?? $_GET['q'] ?? ''));
    $cat = (int) ($_POST['cat'] ?? $_GET['cat'] ?? 0);
    $params = [];
    if ($cat > 0) {
        $params['cat'] = $cat;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    return $params ? ('?' . http_build_query($params)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action');
    try {
        $next = pos_query_from_request();
        if ($action === 'find') {
            $q = post_str('q');
            if ($q !== '') {
                $found = find_product_id_by_code($q);
                if ($found) {
                    cart_add($found, 1);
                    $next = '';
                }
            }
        } elseif ($action === 'add') {
            cart_add(post_int('product_id'), 1);
        } elseif ($action === 'set' || $action === 'inc' || $action === 'dec') {
            $qty = post_float('qty');
            if ($action === 'inc') {
                $qty += 1;
            }
            if ($action === 'dec') {
                $qty -= 1;
            }
            cart_set(post_int('product_id'), $qty);
        } elseif ($action === 'remove') {
            cart_set(post_int('product_id'), 0);
        } elseif ($action === 'clear') {
            $_SESSION['cart'] = [];
            $next = '';
        } elseif ($action === 'checkout') {
            $token = post_str('checkout_token');
            $known = (string) ($_SESSION['checkout_token'] ?? '');
            if ($token === '' || $known === '' || !hash_equals($known, $token)) {
                if (!empty($_SESSION['last_sale_id'])) {
                    redirect('receipt.php?id=' . (int) $_SESSION['last_sale_id'] . '&print=1');
                }
                throw new RuntimeException('The cart changed. Please check it and charge again.');
            }
            $raw = trim((string) ($_POST['amount_received'] ?? ''));
            $received = null;
            if ($raw !== '') {
                $normalized = str_replace([',', ' '], '', $raw);
                if (!is_numeric($normalized)) {
                    throw new RuntimeException('Enter the cash received as a number, or leave it empty for the exact amount.');
                }
                $received = (float) $normalized;
            }
            $result = complete_sale(cart_quantities(), $received, (int) current_user()['id'], $token);
            $_SESSION['cart'] = [];
            $_SESSION['last_sale_id'] = (int) $result['id'];
            $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
            redirect('receipt.php?id=' . (int) $result['id'] . '&print=1');
        } else {
            throw new RuntimeException('That action is not available.');
        }
        redirect('pos.php' . $next);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('pos.php' . pos_query_from_request());
    }
}

if (empty($_SESSION['checkout_token']) || !is_string($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0);
$cart = cart_lines();
$inCart = cart_quantities();
$categories = db()->query('SELECT c.id, c.name FROM bp_categories c WHERE EXISTS (SELECT 1 FROM bp_products p WHERE p.category_id = c.id AND p.active = 1) ORDER BY c.name')->fetchAll();

$sql = 'SELECT p.*, c.name AS category_name, b.name AS brand_name, u.name AS unit_name FROM bp_products p LEFT JOIN bp_categories c ON c.id = p.category_id LEFT JOIN bp_brands b ON b.id = p.brand_id LEFT JOIN bp_units u ON u.id = p.unit_id WHERE p.active = 1';
$params = [];
if ($cat > 0) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $cat;
}
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.barcode LIKE ? OR p.product_code LIKE ? OR c.name LIKE ? OR b.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$sql .= ' ORDER BY p.name';
$st = db()->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$keep = static function () use ($q, $cat): string {
    $html = '';
    if ($cat > 0) {
        $html .= '<input type="hidden" name="cat" value="' . $cat . '">';
    }
    if ($q !== '') {
        $html .= '<input type="hidden" name="q" value="' . e($q) . '">';
    }
    return $html;
};

layout_start('Sale', 'pos');
?>
<div class="pos-layout">
  <section>
    <div class="pos-alerts">
      <?php weak_password_note(); render_flash(); ?>
    </div>
    <div class="pos-tools">
      <h1>Sale</h1>
      <form method="post" class="search-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="find">
        <?= $keep() ?>
        <input id="posSearch" name="q" value="<?= e($q) ?>" placeholder="Scan barcode or search name / code" autocomplete="off" enterkeyhint="search">
        <button class="btn btn-dark" type="submit"><?= icon('search', 22) ?> Find</button>
      </form>
      <nav class="chips">
        <?php
          $base = [];
          if ($q !== '') {
              $base['q'] = $q;
          }
        ?>
        <a class="chip<?= $cat === 0 ? ' active' : '' ?>" href="<?= e(url('pos.php' . ($base ? '?' . http_build_query($base) : ''))) ?>">All</a>
        <?php foreach ($categories as $category): ?>
          <?php $params = $base; $params['cat'] = (int) $category['id']; ?>
          <a class="chip<?= $cat === (int) $category['id'] ? ' active' : '' ?>" href="<?= e(url('pos.php?' . http_build_query($params))) ?>"><?= e((string) $category['name']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="catalog">
      <?php if (!$products): ?>
        <?= empty_state('box', 'No matching products', 'Try another word, or ask the admin to add the product.') ?>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $product): ?>
            <?php
              $id = (int) $product['id'];
              $stock = round((float) $product['stock_qty'], 3);
              $held = round((float) ($inCart[$id] ?? 0), 3);
              $available = round($stock - $held, 3);
              $offer = discount_for($id, (float) $product['sale_price']);
              $searchBlob = bp_lower(implode(' ', array_filter([
                  (string) $product['name'],
                  (string) $product['barcode'],
                  (string) $product['product_code'],
                  (string) ($product['category_name'] ?? ''),
                  (string) ($product['brand_name'] ?? ''),
              ])));
            ?>
            <form method="post" data-search="<?= e($searchBlob) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= $id ?>">
              <?= $keep() ?>
              <button class="product-card" type="submit"<?= $available <= 0 ? ' disabled' : '' ?>>
                <span class="meta"><?= e((string) ($product['category_name'] ?: 'Product')) ?></span>
                <span class="name"><?= e((string) $product['name']) ?></span>
                <?php if ($offer['unit_discount'] > 0): ?>
                  <span class="badge badge-hot">Save <?= e(money($offer['unit_discount'])) ?></span>
                <?php endif; ?>
                <?php if ($offer['unit_discount'] > 0): ?>
                  <span class="was"><?= e(money($product['sale_price'])) ?></span>
                <?php endif; ?>
                <span class="price"><?= e(money($offer['net'])) ?></span>
                <span class="meta">
                  <?php if ($available <= 0): ?>
                    Out of stock
                  <?php else: ?>
                    <?= e(qty_fmt($available)) ?> <?= e((string) ($product['unit_name'] ?? '')) ?> left
                    <?php if ($held > 0): ?> · <?= e(qty_fmt($held)) ?> in cart<?php endif; ?>
                  <?php endif; ?>
                </span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <aside class="cart-panel" id="cartPanel">
    <div class="cart-head">
      <h2>Current sale</h2>
      <button class="icon-btn cart-close" id="cartClose" type="button" aria-label="Close cart"><?= icon('close', 22) ?></button>
      <?php if ($cart['count'] > 0): ?>
        <form method="post" data-confirm="Clear this sale?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <button class="btn btn-ghost btn-sm" type="submit">Clear</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="cart-lines">
      <?php if (!$cart['lines']): ?>
        <?= empty_state('cart', 'Cart is empty', 'Tap a product, or scan its barcode and press enter.') ?>
      <?php else: ?>
        <?php foreach ($cart['lines'] as $line): ?>
          <div class="cart-row">
            <div class="cart-top">
              <h3><?= e((string) $line['name']) ?></h3>
              <span class="line-price"><?= e(money($line['line_total'])) ?></span>
            </div>
            <?php if ($line['discount'] > 0): ?>
              <div class="hint"><?= e((string) $line['label']) ?> · save <?= e(money($line['discount'])) ?></div>
            <?php endif; ?>
            <form method="post" class="stepper">
              <?= csrf_field() ?>
              <?= $keep() ?>
              <input type="hidden" name="product_id" value="<?= (int) $line['id'] ?>">
              <button class="sr-submit" type="submit" name="action" value="set">Update quantity</button>
              <button class="icon-btn" type="submit" name="action" value="dec" aria-label="Decrease"><?= icon('minus', 20) ?></button>
              <input name="qty" inputmode="decimal" value="<?= e(qty_fmt($line['qty'])) ?>" aria-label="Quantity">
              <button class="icon-btn" type="submit" name="action" value="inc" aria-label="Increase"><?= icon('plus', 20) ?></button>
              <button class="icon-btn" type="submit" name="action" value="remove" aria-label="Remove"><?= icon('trash', 18) ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <form method="post" class="cart-foot">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="checkout">
      <input type="hidden" name="checkout_token" value="<?= e((string) $_SESSION['checkout_token']) ?>">
      <div class="total-line"><span>Subtotal</span><strong><?= e(money($cart['subtotal'])) ?></strong></div>
      <div class="total-line"><span>Discount</span><strong><?= e(money($cart['discount'])) ?></strong></div>
      <div class="total-line" id="payTotal" data-total="<?= e(money_str($cart['total'])) ?>"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
      <label class="field">Cash received
        <input id="tender" name="amount_received" inputmode="decimal" autocomplete="off" placeholder="Leave empty for exact">
      </label>
      <div id="changeOut" class="big-change">Exact amount</div>
      <button class="btn btn-primary btn-lg" type="submit"<?= $cart['count'] === 0 ? ' disabled' : '' ?>><?= icon('check', 22) ?> Charge <?= e(money($cart['total'])) ?></button>
    </form>
  </aside>
</div>
<button class="cart-fab" id="cartFab" type="button">
  <span><?= icon('cart', 22) ?> <?= (int) $cart['count'] ?> in cart</span>
  <strong><?= e(money($cart['total'])) ?></strong>
</button>
<?php layout_end(); ?>
