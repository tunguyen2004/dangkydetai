<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('K73_DKDT_SESSION');
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!empty($_SESSION['user_id'])) {
    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

    if ($now - $lastActivity >= SESSION_TIMEOUT_SECONDS) {
        unset($_SESSION['user_id'], $_SESSION['last_activity']);
        session_regenerate_id(true);
        flash('warning', 'Phiên làm việc đã hết hạn sau 20 phút không hoạt động. Vui lòng đăng nhập lại.');
        redirect('auth/login.php');
    }

    $_SESSION['last_activity'] = $now;
}
