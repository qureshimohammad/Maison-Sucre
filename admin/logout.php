<?php
// Admin logout (POST + CSRF only). Only touches the admin session, never the customer one.
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    a_redirect('index.php');
}
a_csrf_verify();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// fresh session just to carry the goodbye message
session_start();
session_regenerate_id(true);
a_flash('info', 'You have been logged out.');
a_redirect('login.php');
