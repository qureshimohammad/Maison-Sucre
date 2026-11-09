<?php
// Admin: one order in full + status updates
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    a_redirect('orders.php');
}

/* ---- Update status (POST) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    a_csrf_verify();
    $new = a_str('status');

    try {
        if (!in_array($new, A_STATUSES, true)) {
            throw new RuntimeException('Please choose a valid status.');
        }
        $pdo->beginTransaction();

        // Lock the order row so two admins cannot change it at the same moment (and stock is restored only once)
        $cur = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $cur->execute([$orderId]);
        $current = $cur->fetchColumn();
        if ($current === false) {
            throw new RuntimeException('That order does not exist.');
        }
        if (!in_array($new, A_TRANSITIONS[$current] ?? [], true)) {
            throw new RuntimeException('An order that is ' . $current . ' cannot be changed to ' . $new . '.');
        }

        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $orderId]);

        if ($new === 'Cancelled') {                       // put the cakes back on the shelf
            $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
            $items->execute([$orderId]);
            $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($items as $it) {
                $restock->execute([(int)$it['quantity'], (int)$it['product_id']]);
            }
        }
        $pdo->commit();
        a_flash('success', 'Order marked as ' . $new . ($new === 'Cancelled' ? ' and stock was restored.' : '.'));
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        a_flash('danger', $ex->getMessage());
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        a_flash('danger', 'Could not update the order. Please try again.');
    }
    a_redirect('order.php?id=' . $orderId);
}

/* ---- Load ---- */
$st = $pdo->prepare(
    'SELECT o.*, u.name AS customer_name, u.email AS customer_email
       FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?'
);
$st->execute([$orderId]);
$order = $st->fetch();
if (!$order) {
    a_flash('danger', 'That order was not found.');
    a_redirect('orders.php');
}
$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$orderId]);
$items = $st->fetchAll();

$cgst = round($order['gst'] / 2, 2);
$sgst = round($order['gst'] - $cgst, 2);
$next = A_TRANSITIONS[$order['status']] ?? [];

a_header('Order ' . a_order_no((int)$order['id']), 'orders', $admin);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
    <a href="orders.php">&larr; All orders</a>
    <button type="button" class="btn btn-sm btn-outline-rose" onclick="window.print()">🖨️ Print</button>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h1 class="h4 mb-1">Order <?= a_e(a_order_no((int)$order['id'])) ?></h1>
                        <div class="text-muted small">Placed <?= a_e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></div>
                    </div>
                    <div><span class="badge fs-6 <?= a_status_class($order['status']) ?>"><?= a_e($order['status']) ?></span></div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>#</th><th>Item</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $i => $it): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= a_e($it['product_name']) ?>
                                    <?php if ($it['cake_message']): ?>
                                        <div class="small"><span class="badge text-bg-light border">Cake message</span> “<?= a_e($it['cake_message']) ?>”</div>
                                    <?php endif; ?></td>
                                <td class="text-end"><?= a_money($it['unit_price']) ?></td>
                                <td class="text-center"><?= (int)$it['quantity'] ?></td>
                                <td class="text-end"><?= a_money($it['unit_price'] * $it['quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row justify-content-end"><div class="col-md-6">
                    <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= a_money($order['subtotal']) ?></span></div>
                    <div class="d-flex justify-content-between small"><span>CGST (2.5%)</span><span><?= a_money($cgst) ?></span></div>
                    <div class="d-flex justify-content-between small"><span>SGST (2.5%)</span><span><?= a_money($sgst) ?></span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span><?= a_money($order['total']) ?></span></div>
                </div></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3 no-print">
            <div class="card-body">
                <h2 class="h6 mb-3">Update status</h2>
                <?php if ($next): ?>
                    <form method="post" action="order.php?id=<?= (int)$order['id'] ?>"
                          data-confirm="Change this order's status? Cancelling puts the items back in stock and cannot be undone.">
                        <?= a_csrf_field() ?>
                        <select name="status" class="form-select mb-2" required>
                            <?php foreach ($next as $s): ?><option value="<?= a_e($s) ?>"><?= a_e($s) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-rose w-100" type="submit">Save status</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted small mb-0">This order is <?= a_e(strtolower($order['status'])) ?>, so its status is locked.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-2">Customer</h2>
                <div class="fw-semibold"><?= a_e($order['customer_name']) ?></div>
                <div class="small"><?= a_e($order['customer_email']) ?></div>
                <div class="small"><?= a_e($order['phone']) ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-2">Deliver to</h2>
                <div class="small"><?= nl2br(a_e($order['delivery_address'])) ?></div>
                <div class="small mt-2"><span class="text-muted">Payment:</span> <?= a_e(A_PAYMENTS[$order['payment_method']] ?? $order['payment_method']) ?></div>
            </div>
        </div>
    </div>
</div>
<?php a_footer(); ?>
