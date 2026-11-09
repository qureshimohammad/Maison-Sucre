<?php
// PAGE 5 - Bill (invoice) and order history (login required)
require_once __DIR__ . '/includes/functions.php';
require_login('bill.php');

$uid     = (int)$_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order   = null;
$items   = [];

if ($orderId) {
    // "AND o.user_id = ?" means nobody can open another customer's bill by changing the number in the URL
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS customer_name, u.email AS customer_email
           FROM orders o JOIN users u ON u.id = o.user_id
          WHERE o.id = ? AND o.user_id = ?'
    );
    $stmt->execute([$orderId, $uid]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('danger', 'That bill was not found.');
        redirect('bill.php');
    }

    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS item_count
           FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC'
    );
    $stmt->execute([$uid]);
    $orders = $stmt->fetchAll();
}

$pageTitle = $order ? 'Bill ' . order_no((int)$order['id']) : 'My Orders';
$active    = 'bill';
require __DIR__ . '/includes/header.php';
?>

<section class="container section">

<?php if ($order):
    $cgst = round($order['gst'] / 2, 2);
    $sgst = round($order['gst'] - $cgst, 2);
?>
    <div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
        <a href="bill.php" class="link-rose">&larr; All orders</a>
        <button type="button" class="btn btn-rose" onclick="window.print()">🖨️ Print bill</button>
    </div>

    <div class="invoice card mx-auto">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="mb-1">🥐 <?= e(SHOP_NAME) ?></h2>
                    <div class="small text-muted">
                        12 Baker Street, Navrangpura, Ahmedabad 380009<br>
                        +91 98765 43210<br>
                        GSTIN: 24ABCDE1234F1Z5 (sample)
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="invoice-title">TAX INVOICE</div>
                    <div>Bill no: <strong><?= e(order_no((int)$order['id'])) ?></strong></div>
                    <div>Date: <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></div>
                    <div>Status: <span class="badge text-bg-success"><?= e($order['status']) ?></span></div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="small text-uppercase text-muted">Billed to</div>
                    <div class="fw-semibold"><?= e($order['customer_name']) ?></div>
                    <div class="small"><?= e($order['customer_email']) ?></div>
                    <div class="small"><?= e($order['phone']) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="small text-uppercase text-muted">Deliver to</div>
                    <div class="small"><?= nl2br(e($order['delivery_address'])) ?></div>
                    <div class="small mt-2"><span class="text-muted">Payment:</span> <?= e(PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method']) ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table invoice-table align-middle">
                    <thead>
                        <tr><th>#</th><th>Item</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <?= e($it['product_name']) ?>
                                <?php if ($it['cake_message']): ?>
                                    <div class="small text-muted">Cake message: "<?= e($it['cake_message']) ?>"</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= money($it['unit_price']) ?></td>
                            <td class="text-center"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end"><?= money($it['unit_price'] * $it['quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-6 col-lg-5">
                    <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                    <div class="d-flex justify-content-between mb-1 small"><span>CGST (2.5%)</span><span><?= money($cgst) ?></span></div>
                    <div class="d-flex justify-content-between mb-1 small"><span>SGST (2.5%)</span><span><?= money($sgst) ?></span></div>
                    <div class="d-flex justify-content-between mb-1 small text-muted"><span>Delivery</span><span>Free</span></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold"><span>Grand total</span><span><?= money($order['total']) ?></span></div>
                </div>
            </div>

            <hr class="my-4">
            <p class="text-center mb-1">Thank you for ordering from <?= e(SHOP_NAME) ?>! 🧁</p>
            <p class="text-center small text-muted mb-0">This is a computer-generated invoice for a college project demo.</p>
        </div>
    </div>

<?php else: ?>

    <h1 class="section-title mb-4">My orders</h1>

    <?php if (!$orders): ?>
        <div class="empty-cart text-center">
            <div class="empty-emoji">🧾</div>
            <h4>No orders yet</h4>
            <p class="text-muted">When you place an order, its bill will appear here.</p>
            <a href="menu.php" class="btn btn-rose">Start ordering</a>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Bill no</th><th>Date</th><th class="text-center">Items</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(order_no((int)$o['id'])) ?></td>
                            <td><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>
                            <td class="text-center"><?= (int)$o['item_count'] ?></td>
                            <td><?= e($o['payment_method']) ?></td>
                            <td><span class="badge text-bg-success"><?= e($o['status']) ?></span></td>
                            <td class="text-end"><?= money($o['total']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-rose" href="bill.php?id=<?= (int)$o['id'] ?>">View bill</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
