<?php
/**
 * Admin bootstrap. Every admin page starts with:  require_once __DIR__ . '/includes/bootstrap.php';
 *
 * Deliberately independent from the public site:
 *   - does NOT load includes/functions.php, so no function/constant names can collide
 *   - uses its own session (cookie name MS_ADMIN, limited to the /admin path)
 *   - every helper is prefixed a_ / A_
 * It only reuses ../db.php for the $pdo connection.
 */

require_once dirname(__DIR__, 2) . '/db.php';        // gives us $pdo

/* ---------- constants ---------- */
const A_IDLE_TIMEOUT = 1800;                         // auto logout after 30 min of no activity
const A_PER_PAGE     = 20;
const A_IMG_MAX_BYTES = 2097152;                     // 2 MB product photo limit
define('A_IMG_DIR', dirname(__DIR__, 2) . '/assets/images');
// Keep in sync with CATEGORIES in includes/functions.php (the public menu only shows those tabs)
define('A_CATEGORIES', ['Cakes', 'Pastries', 'Macarons', 'Cookies', 'Specials']);
define('A_STATUSES',   ['Placed', 'Preparing', 'Delivered', 'Cancelled']);
define('A_PAYMENTS',   ['COD' => 'Cash on delivery', 'UPI' => 'UPI', 'Card' => 'Card']);
// Which status can an order move to from its current one
define('A_TRANSITIONS', [
    'Placed'    => ['Preparing', 'Delivered', 'Cancelled'],
    'Preparing' => ['Delivered', 'Cancelled'],
    'Delivered' => [],
    'Cancelled' => [],
]);

/* ---------- headers ---------- */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');                   // back button after logout shows nothing sensitive

/* ---------- session (separate from the shop's customer session) ---------- */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('MS_ADMIN');
    $cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php')), '/');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath !== '' ? $cookiePath : '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- helpers ---------- */
function a_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function a_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function a_money($n): string
{
    return '₹' . number_format((float)$n, 2);
}

function a_order_no(int $id): string
{
    return 'MS' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);   // same format as the customer bill
}

function a_str(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

function a_flash(string $type, string $msg): void
{
    $_SESSION['a_flash'][] = ['type' => $type, 'msg' => $msg];
}

function a_csrf_token(): string
{
    if (empty($_SESSION['a_csrf'])) {
        $_SESSION['a_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['a_csrf'];
}

function a_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . a_e(a_csrf_token()) . '">';
}

function a_csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(a_csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired or the form was invalid. Go back, refresh the page and try again.');
    }
}

function a_status_class(string $status): string
{
    switch ($status) {
        case 'Placed':    return 'text-bg-primary';
        case 'Preparing': return 'text-bg-warning';
        case 'Delivered': return 'text-bg-success';
        case 'Cancelled': return 'text-bg-secondary';
        default:          return 'text-bg-light';
    }
}

/** Forget the admin login but keep a usable session (so we can still show a flash message). */
function a_end_login(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Gate for every protected admin page. Returns the logged-in admin row. */
function a_require_admin(PDO $pdo): array
{
    if (empty($_SESSION['admin_id'])) {
        a_redirect('login.php');
    }
    if (time() - (int)($_SESSION['admin_last'] ?? 0) > A_IDLE_TIMEOUT) {
        a_end_login();
        a_flash('info', 'You were logged out after 30 minutes of inactivity.');
        a_redirect('login.php');
    }
    $_SESSION['admin_last'] = time();

    // Re-check against the database each request, so deleting an admin kicks them out immediately
    $st = $pdo->prepare('SELECT id, name, email FROM admins WHERE id = ?');
    $st->execute([(int)$_SESSION['admin_id']]);
    $admin = $st->fetch();
    if (!$admin) {
        a_end_login();
        a_redirect('login.php');
    }
    return $admin;
}

/** Pagination links. $params = current filters to keep in the URL. */
function a_pager(int $page, int $pages, array $params = []): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $q = http_build_query(array_filter(array_merge($params, ['page' => $i]), fn($v) => $v !== '' && $v !== null));
        echo '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="?' . a_e($q) . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}

/* ---------- layout ---------- */
function a_header(string $title, string $active = '', ?array $admin = null): void
{
    $nav = [
        'dashboard' => ['index.php',     'Dashboard'],
        'orders'    => ['orders.php',    'Orders'],
        'products'  => ['products.php',  'Products'],
        'customers' => ['customers.php', 'Customers'],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= a_e($title) ?> · Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ms-brown:#4a2c22; --ms-rose:#c2566b; --ms-cream:#faf5f0; }
        body { background:var(--ms-cream); color:#3a2a25; }
        .admin-nav { background:var(--ms-brown); }
        .admin-nav .navbar-brand, .admin-nav .nav-link { color:#f6e9e2; }
        .admin-nav .nav-link.active, .admin-nav .nav-link:hover { color:#fff; border-bottom:2px solid var(--ms-rose); }
        .btn-rose { background:var(--ms-rose); border-color:var(--ms-rose); color:#fff; }
        .btn-rose:hover { background:#a8445a; border-color:#a8445a; color:#fff; }
        .btn-outline-rose { color:var(--ms-rose); border-color:var(--ms-rose); }
        .btn-outline-rose:hover { background:var(--ms-rose); color:#fff; }
        .stat-card .stat-value { font-size:1.75rem; font-weight:700; color:var(--ms-brown); }
        .thumb { width:44px; height:44px; object-fit:cover; border-radius:8px; }
        .thumb-emoji { width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; background:#f1e4dc; border-radius:8px; font-size:1.4rem; }
        .card { border:0; box-shadow:0 2px 10px rgba(74,44,34,.07); }
        @media print { .no-print { display:none !important; } body { background:#fff; } }
    </style>
</head>
<body>
<?php if ($admin): ?>
<nav class="navbar navbar-expand-md admin-nav no-print">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="index.php">🥐 <?= a_e('Maison Sucre') ?> <small class="opacity-75">Admin</small></a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav"
                aria-label="Toggle navigation"><span class="navbar-toggler-icon" style="filter:invert(1)"></span></button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav as $key => [$href, $label]): ?>
                    <li class="nav-item"><a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= a_e($href) ?>"><?= a_e($label) ?></a></li>
                <?php endforeach; ?>
                <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank" rel="noopener">View shop ↗</a></li>
            </ul>
            <form method="post" action="logout.php" class="d-flex align-items-center gap-2 mb-0">
                <?= a_csrf_field() ?>
                <span class="small text-white-50"><?= a_e($admin['name']) ?></span>
                <button class="btn btn-sm btn-outline-light" type="submit">Log out</button>
            </form>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-4">
<?php
    foreach ($_SESSION['a_flash'] ?? [] as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show no-print" role="alert">' . a_e($f['msg'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['a_flash']);
}

function a_footer(): void
{
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Any form with data-confirm asks first (used for delete / cancel)
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
});
</script>
</body>
</html>
<?php
}
