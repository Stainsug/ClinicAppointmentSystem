<?php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (empty($_SESSION['is_admin_logged_in']) || empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (!empty($_SESSION['admin_must_change_password']) && $currentPage !== 'admin_change_password.php') {
    header('Location: admin_change_password.php');
    exit;
}

$loggedAdminId = (int)$_SESSION['admin_id'];
$loggedAdminUsername = (string)($_SESSION['admin_username'] ?? 'Admin');
