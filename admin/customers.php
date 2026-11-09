<?php
// Admin: read-only customer list
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

$q    = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$page = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

$where = ''; $params = [];
if ($q !== '') {
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $where  = 'WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?';
    $params = [$like, $like, $like];
}
$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$cnt->execute($params);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / A_PER_PAGE));
$page   = min($page, $pages);
$limit  = A_PER_PAGE;
$offset = ($page - 1) * A_PER_PAGE;

$st = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
            (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'Cancelled') AS spent
       FROM users u $where ORDER BY u.id DESC LIMIT $limit OFFSET $offset"
);
$st->execute($params);
$users = $st->fetchAll();

a_header('Customers', 'customers', $admin);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Customers <small class="text-muted fs-6">(<?= $total ?>)</small></h1>
    <form class="d-flex gap-2" method="get" action="customers.php">
        <input class="form-control form-control-sm" style="width:230px" type="search" name="q" value="<?= a_e($q) ?>" placeholder="Name, email or phone">
        <button class="btn btn-sm btn-rose" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="customers.php">Clear</a><?php endif; ?>
    </form>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th class="text-center">Orders</th><th class="text-end">Spent</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-semibold"><?= a_e($u['name']) ?></td>
                    <td><?= a_e($u['email']) ?></td>
                    <td><?= a_e($u['phone']) ?></td>
                    <td><?= a_e(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td class="text-center"><?= (int)$u['orders_count'] ?></td>
                    <td class="text-end"><?= a_money($u['spent']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="6" class="text-center text-muted py-5">No customers found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php a_pager($page, $pages, ['q' => $q]); ?>
<?php a_footer(); ?>
