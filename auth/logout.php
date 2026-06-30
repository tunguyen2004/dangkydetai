<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (($_GET['timeout'] ?? '') !== '1') {
    verify_csrf();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();

session_name('K73_DKDT_SESSION');
session_start();
flash(($_GET['timeout'] ?? '') === '1' ? 'warning' : 'success', ($_GET['timeout'] ?? '') === '1' ? 'Bạn đã tự động đăng xuất sau 20 phút không hoạt động.' : 'Bạn đã đăng xuất khỏi hệ thống.');
redirect('auth/login.php');
