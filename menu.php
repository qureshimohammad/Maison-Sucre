<?php
// PAGE 2 - Menu (products from MySQL, category filter, add to cart)
require_once __DIR__ . '/includes/functions.php';

// Which category tab is selected?
$cat = $_GET['cat'] ?? ($_POST['category'] ?? 'All');
if (!is_string($cat) || !in_array($cat, CATEGORIES, true)) {
    $cat = 'All';
}
$menuUrl = 'menu.php' . ($cat !== 'All' ? '?cat=' . urlencode($cat) : '');

/* ---- Add to cart (POST) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pid = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $qty = filter_var($_POST['qty'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_QTY]]);

    if (!$pid || !$qty) {
        flash('danger', 'Please choose a valid quantity (1 to ' . MAX_QTY . ').');
        redirect($menuUrl);
    }

    $stmt = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ?');
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if (!$product) {
        flash('danger', 'That item does not exist.');
        redirect($menuUrl);
    }

    $already  = (int)($_SESSION['cart'][$pid]['qty'] ?? 0);
    $newQty   = $already + $qty;
    $maxAllow = min((int)$product['stock'], MAX_QTY);

    if ($newQty > $maxAllow) {
        flash('warning', 'Sorry, we can only offer ' . $maxAllow . ' x ' . $product['name'] . ' per order right now.');
    } else {
        $_SESSION['cart'][$pid] = ['qty' => $newQty, 'msg' => $_SESSION['cart'][$pid]['msg'] ?? ''];
        flash('success', $qty . ' x ' . $product['name'] . ' added to your cart.');
    }
    redirect($menuUrl . '#p' . $pid);
}

/* ---- Load products ---- */
if ($cat === 'All') {
    $products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category = ? ORDER BY id');
    $stmt->execute([$cat]);
    $products = $stmt->fetchAll();
}

$pageTitle = 'Menu';
$active    = 'menu';
require __DIR__ . '/includes/header.php';
?>

<section class="container section">
    <div class="text-center mb-4">
        <h1 class="section-title">Our menu</h1>
        <p class="text-muted">Everything is baked today. Pick your favourites and add them to the cart.</p>
    </div>

    <ul class="nav nav-pills justify-content-center gap-2 mb-5 cat-pills">
        <li class="nav-item"><a class="nav-link <?= $cat === 'All' ? 'active' : '' ?>" href="menu.php">All</a></li>
        <?php foreach (CATEGORIES as $c): ?>
            <li class="nav-item">
                <a class="nav-link <?= $cat === $c ? 'active' : '' ?>" href="menu.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$products): ?>
        <p class="text-center text-muted">Nothing in this category yet. Check back soon!</p>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($products as $p):
            $stock  = (int)$p['stock'];
            $maxQty = min($stock, MAX_QTY);
        ?>
            <div class="col-sm-6 col-lg-4 col-xl-3" id="p<?= (int)$p['id'] ?>">
                <div class="card product-card h-100">
                    <div class="product-thumb cat-<?= e(strtolower($p['category'])) ?>" style="position:relative; height:190px; overflow:hidden;">
                        <?php if (!empty($p['image'])): ?>
                            <img src="assets/images/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"
                            style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span class="product-emoji"><?= e($p['emoji']) ?></span>
                        <?php endif; ?>
                        <?php if ($p['badge']): ?><span class="product-badge" style="z-index:2;"><?= e($p['badge']) ?></span><?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <small class="text-uppercase text-muted cat-label"><?= e($p['category']) ?></small>
                        <h5 class="card-title"><?= e($p['name']) ?></h5>
                        <p class="small text-muted flex-grow-1"><?= e($p['description']) ?></p>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="price"><?= money($p['price']) ?></span>
                            <?php if ($stock === 0): ?>
                                <span class="badge text-bg-secondary">Sold out</span>
                            <?php elseif ($stock <= 5): ?>
                                <span class="badge text-bg-warning">Only <?= $stock ?> left</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($stock > 0): ?>
                            <form method="post" action="menu.php" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="category" value="<?= e($cat) ?>">
                                <input type="number" name="qty" value="1" min="1" max="<?= $maxQty ?>"
                                       class="form-control qty-input" aria-label="Quantity">
                                <button type="submit" class="btn btn-rose flex-grow-1">Add to cart</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>Unavailable</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
