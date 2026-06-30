<?php
$user = current_user();
$title = $page_title ?? APP_NAME;
$bodyClass = $body_class ?? '';
$mainClass = $main_class ?? 'page-shell';
$hideChrome = !empty($hide_site_chrome);
$notifications = $user ? notification_summary($user) : ['total' => 0];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body
    class="<?= e($bodyClass) ?>"
    <?php if ($user): ?>
        data-session-timeout="<?= e((string) SESSION_TIMEOUT_SECONDS) ?>"
        data-session-timeout-url="<?= e(url('auth/logout.php?timeout=1')) ?>"
    <?php endif; ?>
>
<?php if (!$hideChrome): ?>
<header class="site-header">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt="" aria-hidden="true">
        <span>Đăng ký đề tài</span>
    </a>
    <button class="nav-toggle" type="button" data-nav-toggle aria-label="Mở menu">
        <span></span><span></span><span></span>
    </button>
    <nav class="site-nav" data-nav>
        <?php if ($user): ?>
            <a href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= e(url('admin/users.php')) ?>">Tài khoản</a>
                <a href="<?= e(url('admin/classes.php')) ?>">Lớp học phần</a>
                <a href="<?= e(url('admin/semesters.php')) ?>">Đợt đăng ký</a>
            <?php elseif ($user['role'] === 'teacher'): ?>
                <a href="<?= e(url('teacher/topics.php')) ?>">Đề tài</a>
                <a href="<?= e(url('teacher/groups.php')) ?>">Nhóm</a>
                <a href="<?= e(url('teacher/registrations.php')) ?>">Duyệt đăng ký</a>
            <?php else: ?>
                <a href="<?= e(url('student/group.php')) ?>">Nhóm của em</a>
                <a href="<?= e(url('student/topics.php')) ?>">Đăng ký đề tài</a>
            <?php endif; ?>
            <div class="nav-pill" title="Thông báo">
                Thông báo
                <?php if ((int) $notifications['total'] > 0): ?>
                    <strong><?= e((string) $notifications['total']) ?></strong>
                <?php endif; ?>
            </div>
            <div class="account-box">
                <span><?= e($user['name']) ?></span>
                <small><?= e(role_label($user['role'])) ?></small>
            </div>
            <form action="<?= e(url('auth/logout.php')) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-light" type="submit">Đăng xuất</button>
            </form>
        <?php else: ?>
            <a href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a>
        <?php endif; ?>
    </nav>
</header>
<?php endif; ?>

<main class="<?= e($mainClass) ?>">
    <?php $messages = flash_messages(); ?>
    <?php if ($messages): ?>
        <div class="flash-stack" aria-live="polite">
            <?php foreach ($messages as $message): ?>
                <div class="flash-message flash-<?= e($message['type']) ?>" data-auto-dismiss="20000">
                    <span><?= e($message['message']) ?></span>
                    <button type="button" aria-label="Đóng thông báo">×</button>
                    <i></i>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
