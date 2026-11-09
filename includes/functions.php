<?php
/**
 * Shared helpers used by every page:
 * session start, CSRF protection, flash messages, login guard, cart maths.
 */
require_once __DIR__ . '/../db.php';

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

const SHOP_NAME       = 'Maison Sucre';
const GST_RATE        = 0.05;   // 5% GST on bakery items (split 2.5% CGST + 2.5% SGST on the bill)
const MAX_QTY         = 20;     // max units of one product per order
const CATEGORIES      = ['Cakes', 'Pastries', 'Macarons', 'Cookies', 'Specials'];
const PAYMENT_METHODS = ['COD' => 'Cash on Delivery', 'UPI' => 'UPI (demo)', 'Card' => 'Card (demo)'];

/* ---------- output / input helpers ---------- */

/** Escape text before printing it into HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return '₹' . number_format((float)$amount, 2);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Read a POST field as a trimmed string ('' if missing or not a string). */
function post_str(string $key): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : '';
}

function order_no(int $id): string
{
    return 'MS' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

/* ---------- flash messages (shown once after a redirect) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('Security check failed. Please go back, refresh the page and try again.');
    }
}

/* ---------- authentication ---------- */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Redirect to the login page if nobody is logged in. */
function require_login(string $next = 'index.php'): void
{
    if (!is_logged_in()) {
        flash('info', 'Please log in to continue.');
        redirect('auth.php?next=' . urlencode($next));
    }
}

/** Only allow redirects to our own pages (prevents open-redirect tricks). */
function safe_next($next): string
{
    $allowed = ['index.php', 'menu.php', 'checkout.php', 'bill.php'];
    return (is_string($next) && in_array($next, $allowed, true)) ? $next : 'index.php';
}

/** Fresh copy of the logged-in user's row, or null (and logs out) if the account vanished. */
function current_user(PDO $pdo): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, phone, address FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['user_name']);
        return null;
    }
    return $user;
}

/* ---------- cart (kept in the session: product_id => ['qty' => n, 'msg' => text]) ---------- */

function cart_get(): array
{
    return (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart_get() as $line) {
        $n += (int)$line['qty'];
    }
    return $n;
}

/** Subtotal -> [subtotal, gst, total], all rounded to 2 decimals. */
function calc_totals(float $subtotal): array
{
    $subtotal = round($subtotal, 2);
    $gst      = round($subtotal * GST_RATE, 2);
    return ['subtotal' => $subtotal, 'gst' => $gst, 'total' => round($subtotal + $gst, 2)];
}

/**
 * Join the session cart with live product data from MySQL.
 * Prices always come from the database, never from the browser.
 */
function cart_details(PDO $pdo): array
{
    $cart = cart_get();
    $empty = ['items' => [], 'subtotal' => 0.0, 'gst' => 0.0, 'total' => 0.0];
    if (!$cart) {
        return $empty;
    }

    $ids  = array_map('intval', array_keys($cart));
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) ORDER BY id");
    $stmt->execute($ids);

    $items    = [];
    $subtotal = 0.0;
    $valid    = [];
    foreach ($stmt as $p) {
        $line = $cart[$p['id']];
        $p['qty']        = (int)$line['qty'];
        $p['msg']        = (string)($line['msg'] ?? '');
        $p['line_total'] = round($p['price'] * $p['qty'], 2);
        $subtotal       += $p['line_total'];
        $items[]         = $p;
        $valid[$p['id']] = $line;
    }
    $_SESSION['cart'] = $valid;   // drop products that no longer exist

    return ['items' => $items] + calc_totals($subtotal);
}
