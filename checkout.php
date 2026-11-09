<?php
// PAGE 4 - Cart & Checkout (login required)
require_once __DIR__ . '/includes/functions.php';
require_login('checkout.php');

$user = current_user($pdo);
if (!$user) {
    redirect('auth.php?next=checkout.php');
}

/**
 * Apply quantity changes and cake messages from the cart form.
 * Quantities are capped at the stock we actually have. Returns a list of notices.
 */
function apply_cart_form(PDO $pdo): array
{
    $notices = [];
    $cart    = cart_get();
    if (!$cart) {
        return $notices;
    }

    $new = [];
    foreach (cart_details($pdo)['items'] as $item) {
        $pid = (int)$item['id'];

        $qty = filter_var($_POST['qty'][$pid] ?? $item['qty'], FILTER_VALIDATE_INT);
        if ($qty === false) {
            $qty = $item['qty'];
        }
        if ($qty <= 0) {
            continue;                                   // quantity 0 = remove
        }

        $max = min((int)$item['stock'], MAX_QTY);
        if ($qty > $max) {
            $qty = $max;
            $notices[] = 'Only ' . $max . ' x ' . $item['name'] . ' available, so we changed the quantity.';
        }
        if ($qty < 1) {
            continue;                                   // sold out meanwhile
        }

        $msg = '';
        if ($item['category'] === 'Cakes' && isset($_POST['msg'][$pid]) && is_string($_POST['msg'][$pid])) {
            $msg = mb_substr(trim($_POST['msg'][$pid]), 0, 60);
        }
        $new[$pid] = ['qty' => $qty, 'msg' => $msg];
    }
    $_SESSION['cart'] = $new;
    return $notices;
}

$errors = [];
$form   = [
    'address' => (string)($user['address'] ?? ''),
    'phone'   => (string)$user['phone'],
    'payment' => 'COD',
];

/* ---------------- POST handlers ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Remove one item
    if (isset($_POST['remove'])) {
        $rid = filter_var($_POST['remove'], FILTER_VALIDATE_INT);
        if ($rid) {
            unset($_SESSION['cart'][$rid]);
            flash('info', 'Item removed from your cart.');
        }
        redirect('checkout.php');
    }

    $action = post_str('action');

    if ($action === 'update_cart' || $action === 'place_order') {
        foreach (apply_cart_form($pdo) as $notice) {
            flash('warning', $notice);
        }
    }

    if ($action === 'update_cart') {
        flash('success', 'Cart updated.');
        redirect('checkout.php');
    }

    if ($action === 'place_order') {
        $form['address'] = post_str('address');
        $form['phone']   = preg_replace('/\D/', '', post_str('phone'));
        $form['payment'] = post_str('payment');

        $cart = cart_get();
        ksort($cart);                                   // lock rows in a fixed order to avoid deadlocks

        if (!$cart) {
            $errors[] = 'Your cart is empty.';
        }
        if (mb_strlen($form['address']) < 10 || mb_strlen($form['address']) > 500) {
            $errors[] = 'Please enter a full delivery address (10 to 500 characters).';
        }
        if (!preg_match('/^[6-9]\d{9}$/', $form['phone'])) {
            $errors[] = 'Please enter a valid 10-digit mobile number.';
        }
        if (!array_key_exists($form['payment'], PAYMENT_METHODS)) {
            $errors[] = 'Please choose a payment method.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                // Re-read price and stock from the database, locking the rows until we finish
                $lock  = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
                $lines = [];
                $sum   = 0.0;
                foreach ($cart as $pid => $line) {
                    $lock->execute([$pid]);
                    $p = $lock->fetch();
                    if (!$p) {
                        throw new RuntimeException('An item in your cart is no longer available.');
                    }
                    if ((int)$p['stock'] < (int)$line['qty']) {
                        throw new RuntimeException($p['name'] . ' only has ' . (int)$p['stock'] . ' left. Please update your cart.');
                    }
                    $sum    += round($p['price'] * $line['qty'], 2);
                    $lines[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'price' => $p['price'],
                                'qty' => (int)$line['qty'], 'msg' => ($line['msg'] ?? '') !== '' ? $line['msg'] : null];
                }
                $t = calc_totals($sum);

                $pdo->prepare(
                    'INSERT INTO orders (user_id, subtotal, gst, total, payment_method, delivery_address, phone)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$user['id'], $t['subtotal'], $t['gst'], $t['total'], $form['payment'], $form['address'], $form['phone']]);
                $orderId = (int)$pdo->lastInsertId();

                $addItem = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, cake_message)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $cutStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                foreach ($lines as $l) {
                    $addItem->execute([$orderId, $l['id'], $l['name'], $l['qty'], $l['price'], $l['msg']]);
                    $cutStock->execute([$l['qty'], $l['id']]);
                }

                // Remember the address for next time
                $pdo->prepare('UPDATE users SET address = ?, phone = ? WHERE id = ?')
                    ->execute([$form['address'], $form['phone'], $user['id']]);

                $pdo->commit();

                unset($_SESSION['cart']);
                flash('success', 'Order placed! Here is your bill.');
                redirect('bill.php?id=' . $orderId);

            } catch (RuntimeException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errors[] = $ex->getMessage();
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errors[] = 'Something went wrong while saving your order. Nothing was charged. Please try again.';
            }
        }
    }
}

/* ---------------- Page ---------------- */
$cart = cart_details($pdo);

$pageTitle = 'Cart & Checkout';
$active    = 'cart';
require __DIR__ . '/includes/header.php';
?>

<section class="container section">
    <h1 class="section-title mb-4">Your cart</h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$cart['items']): ?>
        <div class="empty-cart text-center">
            <div class="empty-emoji">🧺</div>
            <h4>Your cart is empty</h4>
            <p class="text-muted">Add a few treats from the menu and they will show up here.</p>
            <a href="menu.php" class="btn btn-rose">Browse the menu</a>
        </div>
    <?php else: ?>

    <form method="post" action="checkout.php" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="row g-4">

            <!-- Cart table -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 cart-table">
                            <thead>
                                <tr><th>Item</th><th style="width:110px">Qty</th><th class="text-end">Total</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cart['items'] as $it): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"> 
                                            <?php if (!empty($it['image'])): ?>
                                                <img src="assets/images/<?= e($it['image']) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                                            <?php else: ?><?= e($it['emoji']) ?>
                                            <?php endif; ?> <?= e($it['name']) ?></div>
                                        <div class="small text-muted"><?= money($it['price']) ?> each</div>
                                        <?php if ($it['category'] === 'Cakes'): ?>
                                            <input type="text" class="form-control form-control-sm mt-2"
                                                   name="msg[<?= (int)$it['id'] ?>]" maxlength="60"
                                                   placeholder="Message on cake (optional)"
                                                   value="<?= e($it['msg']) ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm"
                                               name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>"
                                               min="0" max="<?= min((int)$it['stock'], MAX_QTY) ?>" aria-label="Quantity">
                                    </td>
                                    <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
                                    <td class="text-end">
                                        <!-- Belongs to the separate #removeForm below so Enter never triggers it -->
                                        <button type="submit" form="removeForm" name="remove" value="<?= (int)$it['id'] ?>"
                                                class="btn btn-sm btn-link text-danger" title="Remove" formnovalidate>✕</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <a href="menu.php" class="link-rose small">&larr; Keep shopping</a>
                        <button type="submit" name="action" value="update_cart" class="btn btn-outline-rose btn-sm" formnovalidate>
                            Update cart
                        </button>
                    </div>
                </div>
                <p class="small text-muted mt-2">Set a quantity to 0 and press "Update cart" to remove an item.</p>
            </div>

            <!-- Delivery + summary -->
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Delivery details</h5>
                        <div class="mb-3">
                            <label class="form-label" for="address">Delivery address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required
                                      minlength="10" maxlength="500"><?= e($form['address']) ?></textarea>
                            <div class="invalid-feedback">Please enter your full address.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Mobile number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required
                                   pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric" value="<?= e($form['phone']) ?>">
                            <div class="invalid-feedback">Enter a 10-digit mobile number.</div>
                        </div>

                        <div class="form-label mb-2">Payment method</div>
                        <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="payment" id="pay-<?= e($key) ?>"
                                       value="<?= e($key) ?>" <?= $form['payment'] === $key ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="pay-<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-text">UPI and Card are demo options. No real payment is taken.</div>
                    </div>
                </div>

                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="mb-3">Order summary</h5>
                        <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
                        <div class="d-flex justify-content-between mb-1"><span>GST (<?= (int)(GST_RATE * 100) ?>%)</span><span><?= money($cart['gst']) ?></span></div>
                        <div class="d-flex justify-content-between mb-1 text-muted small"><span>Delivery</span><span>Free</span></div>
                        <hr>
                        <div class="d-flex justify-content-between fs-5 fw-semibold mb-3"><span>Total</span><span><?= money($cart['total']) ?></span></div>
                        <button type="submit" name="action" value="place_order" class="btn btn-rose btn-lg w-100">Place order</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Separate tiny form used only by the ✕ remove buttons -->
    <form id="removeForm" method="post" action="checkout.php"><?= csrf_field() ?></form>

    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
