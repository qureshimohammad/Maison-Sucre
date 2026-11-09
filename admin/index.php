<?php
// Admin dashboard
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

$today = $pdo->query(
    "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS revenue
       FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'Cancelled'"
)->fetch();

$all = $pdo->query(
    "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total END), 0) AS revenue FROM orders"
)->fetch();

$customers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

$byStatus = array_fill_keys(A_STATUSES, 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
    $byStatus[$r['status']] = (int)$r['n'];
}

$lowStock = $pdo->query('SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock, name LIMIT 8')->fetchAll();

$recent = $pdo->query(
    'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer
       FROM orders o JOIN users u ON u.id = o.user_id
      ORDER BY o.id DESC LIMIT 8'
)->fetchAll();

a_header('Dashboard', 'dashboard', $admin);
?>
<h1 class="h3 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="text-muted small">Orders today</div><div class="stat-value"><?= (int)$today['n'] ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="text-muted small">Revenue today</div><div class="stat-value"><?= a_money($today['revenue']) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="text-muted small">All-time orders</div><div class="stat-value"><?= (int)$all['n'] ?></div>
        <div class="small text-muted"><?= a_money($all['revenue']) ?> (excl. cancelled)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="text-muted small">Customers</div><div class="stat-value"><?= $customers ?></div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($byStatus as $status => $n): ?>
        <div class="col-6 col-md-3">
            <a class="text-decoration-none" href="orders.php?status=<?= urlencode($status) ?>">
                <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                    <span class="badge <?= a_status_class($status) ?>"><?= a_e($status) ?></span>
                    <span class="fs-4 fw-semibold text-body"><?= $n ?></span>
                </div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong>Recent orders</strong><a href="orders.php" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Bill</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><a href="order.php?id=<?= (int)$o['id'] ?>"><?= a_e(a_order_no((int)$o['id'])) ?></a>
                                <div class="small text-muted"><?= a_e(date('d M, h:i A', strtotime($o['created_at']))) ?></div></td>
                            <td><?= a_e($o['customer']) ?></td>
                            <td><span class="badge <?= a_status_class($o['status']) ?>"><?= a_e($o['status']) ?></span></td>
                            <td class="text-end"><?= a_money($o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-4">No orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong>Low stock (5 or fewer)</strong><a href="products.php" class="small">Manage</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($lowStock as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="product_edit.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none"><?= a_e($p['name']) ?></a>
                        <span class="badge <?= (int)$p['stock'] === 0 ? 'text-bg-danger' : 'text-bg-warning' ?>">
                            <?= (int)$p['stock'] === 0 ? 'Sold out' : (int)$p['stock'] . ' left' ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$lowStock): ?><li class="list-group-item text-muted">Everything is well stocked.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php a_footer(); ?>
