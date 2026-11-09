<?php
// Admin: product list, quick stock edit, featured toggle, delete
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

/** Delete an uploaded photo, but only files created by this admin panel (prefix p_) — never hero.jpg etc. */
function a_delete_own_image(?string $file): void
{
    if ($file && preg_match('/^p_[a-f0-9]{16}\.(jpg|png|webp)$/', $file)) {
        @unlink(A_IMG_DIR . '/' . $file);
    }
}

/* ---- POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    a_csrf_verify();
    $action = a_str('action');
    $pid    = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $back   = 'products.php' . (isset($_POST['back']) && is_string($_POST['back']) && preg_match('/^[\w=&%+.-]*$/', $_POST['back']) && $_POST['back'] !== '' ? '?' . $_POST['back'] : '');

    if (!$pid) {
        a_flash('danger', 'Invalid product.');
        a_redirect($back);
    }

    if ($action === 'set_stock') {
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        if ($stock === false || $stock === null) {
            a_flash('danger', 'Stock must be a whole number from 0 to 9999.');
        } else {
            $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$stock, $pid]);
            a_flash('success', 'Stock updated.');
        }
    } elseif ($action === 'toggle_featured') {
        $pdo->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?')->execute([$pid]);
        a_flash('success', 'Featured flag changed.');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT name, image FROM products WHERE id = ?');
        $st->execute([$pid]);
        $p = $st->fetch();
        if ($p) {
            try {
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$pid]);
                a_delete_own_image($p['image'] ?? null);
                a_flash('success', '"' . $p['name'] . '" was deleted.');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {         // foreign key: used in past orders, so it must stay for the bills
                    $pdo->prepare('UPDATE products SET stock = 0 WHERE id = ?')->execute([$pid]);
                    a_flash('warning', '"' . $p['name'] . '" appears in past orders, so it cannot be deleted. It was set to sold out (stock 0) instead.');
                } else {
                    a_flash('danger', 'Could not delete that product.');
                }
            }
        }
    }
    a_redirect($back);
}

/* ---- Load ---- */
$q   = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$cat = isset($_GET['cat']) && is_string($_GET['cat']) && in_array($_GET['cat'], A_CATEGORIES, true) ? $_GET['cat'] : '';

$where = []; $params = [];
if ($cat !== '') { $where[] = 'category = ?'; $params[] = $cat; }
if ($q !== '')   { $where[] = 'name LIKE ?';  $params[] = '%' . addcslashes($q, '%_\\') . '%'; }
$st = $pdo->prepare('SELECT * FROM products ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY category, id');
$st->execute($params);
$products = $st->fetchAll();
$back = http_build_query(array_filter(['q' => $q, 'cat' => $cat]));

a_header('Products', 'products', $admin);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Products <small class="text-muted fs-6">(<?= count($products) ?>)</small></h1>
    <div class="d-flex gap-2 flex-wrap">
        <form class="d-flex gap-2" method="get" action="products.php">
            <input class="form-control form-control-sm" style="width:170px" type="search" name="q" value="<?= a_e($q) ?>" placeholder="Search name">
            <select class="form-select form-select-sm" style="width:130px" name="cat" onchange="this.form.submit()">
                <option value="">All categories</option>
                <?php foreach (A_CATEGORIES as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= a_e($c) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        </form>
        <a class="btn btn-sm btn-rose" href="product_edit.php">+ Add product</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Product</th><th>Category</th><th class="text-end">Price</th><th style="width:170px">Stock</th><th class="text-center">Featured</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($p['image'])): ?>
                                <img class="thumb" src="../assets/images/<?= a_e($p['image']) ?>" alt="">
                            <?php else: ?><span class="thumb-emoji"><?= a_e($p['emoji']) ?></span><?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= a_e($p['name']) ?></div>
                                <?php if ($p['badge']): ?><span class="badge text-bg-light border"><?= a_e($p['badge']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= a_e($p['category']) ?></td>
                    <td class="text-end"><?= a_money($p['price']) ?></td>
                    <td>
                        <form method="post" action="products.php" class="d-flex gap-1">
                            <?= a_csrf_field() ?>
                            <input type="hidden" name="action" value="set_stock">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="back" value="<?= a_e($back) ?>">
                            <input class="form-control form-control-sm <?= (int)$p['stock'] <= 5 ? 'border-warning' : '' ?>" type="number"
                                   name="stock" min="0" max="9999" value="<?= (int)$p['stock'] ?>" aria-label="Stock">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
                        </form>
                    </td>
                    <td class="text-center">
                        <form method="post" action="products.php">
                            <?= a_csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_featured">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="back" value="<?= a_e($back) ?>">
                            <button class="btn btn-sm <?= $p['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit"
                                    title="Show on home page"><?= $p['is_featured'] ? '★' : '☆' ?></button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-rose" href="product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
                        <form method="post" action="products.php" class="d-inline"
                              data-confirm="Delete “<?= a_e($p['name']) ?>”? This cannot be undone.">
                            <?= a_csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="back" value="<?= a_e($back) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="6" class="text-center text-muted py-5">No products found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">★ Featured products appear in “Customer favourites” on the home page (only while in stock, max 4).</p>
<?php a_footer(); ?>
