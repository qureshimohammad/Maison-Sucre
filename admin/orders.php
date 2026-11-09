<?php
// Admin: all orders, with search, status filter and pagination
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

$q      = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], A_STATUSES, true) ? $_GET['status'] : '';
$page   = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

$where  = [];
$params = [];
if ($status !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $cond = '(u.name LIKE ? OR u.email LIKE ? OR o.phone LIKE ?';
    array_push($params, $like, $like, $like);
    if (preg_match('/^(?:MS)?0*(\d+)$/i', $q, $m)) {      // "MS00012" or "12" finds that bill number
        $cond    .= ' OR o.id = ?';
        $params[] = (int)$m[1];
    }
    $where[] = $cond . ')';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / A_PER_PAGE));
$page  = min($page, $pages);
$offset = ($page - 1) * A_PER_PAGE;
$limit  = A_PER_PAGE;

$st = $pdo->prepare(
    "SELECT o.id, o.total, o.status, o.payment_method, o.created_at, u.name AS customer,
            (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS item_count
       FROM orders o JOIN users u ON u.id = o.user_id
       $whereSql ORDER BY o.id DESC LIMIT $limit OFFSET $offset"
);
$st->execute($params);
$orders = $st->fetchAll();

a_header('Orders', 'orders', $admin);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Orders <small class="text-muted fs-6">(<?= $total ?>)</small></h1>
    <form class="d-flex gap-2 flex-wrap" method="get" action="orders.php">
        <input class="form-control form-control-sm" style="width:200px" type="search" name="q" value="<?= a_e($q) ?>"
               placeholder="Bill no, name, email, phone">
        <select class="form-select form-select-sm" style="width:140px" name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (A_STATUSES as $s): ?>
                <option value="<?= a_e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= a_e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-rose" type="submit">Search</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="orders.php">Clear</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Bill no</th><th>Date</th><th>Customer</th><th class="text-center">Items</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= a_e(a_order_no((int)$o['id'])) ?></td>
                    <td><?= a_e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>
                    <td><?= a_e($o['customer']) ?></td>
                    <td class="text-center"><?= (int)$o['item_count'] ?></td>
                    <td><?= a_e($o['payment_method']) ?></td>
                    <td><span class="badge <?= a_status_class($o['status']) ?>"><?= a_e($o['status']) ?></span></td>
                    <td class="text-end"><?= a_money($o['total']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-rose" href="order.php?id=<?= (int)$o['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="8" class="text-center text-muted py-5">No orders found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php a_pager($page, $pages, ['q' => $q, 'status' => $status]); ?>
<?php a_footer(); ?>
