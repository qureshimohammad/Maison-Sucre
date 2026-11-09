<?php
// PAGE 1 - Home
$pageTitle = 'Home';
$active    = 'home';
require __DIR__ . '/includes/header.php';

$featured = $pdo->query('SELECT * FROM products WHERE is_featured = 1 AND stock > 0 ORDER BY id LIMIT 4')->fetchAll();
?>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <span class="hero-tag">Baked fresh every morning</span>
                <h1 class="hero-title">Little moments,<br>baked with <em>butter</em>.</h1>
                <p class="lead text-muted">From birthday cakes to a warm croissant on a Monday, order from <?= e(SHOP_NAME) ?> and get it delivered to your door.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="menu.php" class="btn btn-rose btn-lg">Order now</a>
                    <?php if (!is_logged_in()): ?>
                        <a href="auth.php" class="btn btn-outline-rose btn-lg">Create account</a>
                    <?php else: ?>
                        <a href="bill.php" class="btn btn-outline-rose btn-lg">My orders</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center">
    <img src="assets/images/hero.jpg" alt="Freshly baked pastries and cakes"
         style="display:block; width:100%; max-width:440px; aspect-ratio:1/1; object-fit:cover; margin:0 auto; border-radius:50% 50% 46% 54% / 55% 45% 55% 45%; box-shadow:0 14px 34px rgba(74,44,34,.18);">
</div>
            </div>
        </div>
    </div>
</section>

<!-- Featured -->
<section class="container section">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
        <div>
            <h2 class="section-title mb-1">Customer favourites</h2>
            <p class="text-muted mb-0">The things people keep coming back for.</p>
        </div>
        <a href="menu.php" class="link-rose">See the full menu &rarr;</a>
    </div>
    <div class="row g-4">
        <?php foreach ($featured as $p): ?>
            <div class="col-sm-6 col-lg-3">
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
                        <h5 class="card-title"><?= e($p['name']) ?></h5>
                        <p class="small text-muted flex-grow-1"><?= e($p['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="price"><?= money($p['price']) ?></span>
                            <a href="menu.php?cat=<?= urlencode($p['category']) ?>#p<?= (int)$p['id'] ?>" class="btn btn-sm btn-rose">Order</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Why us -->
<section class="why-band">
    <div class="container section">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="why-icon">🌾</div>
                <h5>Fresh, every single day</h5>
                <p class="text-muted small mb-0">Everything is baked in small batches each morning. Nothing sits around overnight.</p>
            </div>
            <div class="col-md-4">
                <div class="why-icon">✍️</div>
                <h5>Personal cakes</h5>
                <p class="text-muted small mb-0">Add a message to any cake while you order, and we will pipe it on by hand.</p>
            </div>
            <div class="col-md-4">
                <div class="why-icon">🛵</div>
                <h5>Delivered with care</h5>
                <p class="text-muted small mb-0">Pay on delivery or online. Every order comes with a proper bill you can print.</p>
            </div>
        </div>
    </div>
</section>

<!-- About -->
<section class="container section">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <h2 class="section-title">Our story</h2>
            <p class="text-muted">
                <?= e(SHOP_NAME) ?> started in a tiny home kitchen with one oven and a lot of butter.
                Today we are still a small team who believe a good pastry needs patience,
                real ingredients and a little love. Come hungry, leave happy.
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
