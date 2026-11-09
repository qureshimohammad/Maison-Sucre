<?php
/**
 * Shared page top: <head>, navbar and flash messages.
 * A page sets $pageTitle and $active before including this file.
 */
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Welcome';
$active    = $active ?? '';
$cartCount = cart_count();
$flashes   = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(SHOP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/images/logo.png" alt="<?= e(SHOP_NAME) ?>" height="46"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link <?= $active === 'menu' ? 'active' : '' ?>" href="menu.php">Menu</a></li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item"><a class="nav-link <?= $active === 'bill' ? 'active' : '' ?>" href="bill.php">My Orders</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'cart' ? 'active' : '' ?>" href="checkout.php">
                        🛒 Cart<?php if ($cartCount > 0): ?> <span class="badge cart-badge"><?= $cartCount ?></span><?php endif; ?>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item ms-lg-2">
                        <span class="nav-user">Hi, <?= e(explode(' ', $_SESSION['user_name'] ?? 'friend')[0]) ?></span>
                    </li>
                    <li class="nav-item ms-lg-1">
                        <form method="post" action="logout.php" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-rose">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-sm btn-rose" href="auth.php">Login / Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($flashes): ?>
    <div class="container mt-3 no-print">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> flash" role="alert"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main>
