<?php
// Logout (POST only, with CSRF token, so another site cannot log people out)
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

// Wipe the session data and the session cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Start a clean session just to show the goodbye message
session_start();
session_regenerate_id(true);
flash('info', 'You have been logged out. See you soon!');
redirect('index.php');
