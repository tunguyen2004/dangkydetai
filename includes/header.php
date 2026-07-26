<?php
$user = current_user();
$title = $page_title ?? APP_NAME;
$bodyClass = $body_class ?? '';
$mainClass = $main_class ?? '';
$hideChrome = !empty($hide_site_chrome);
$hasSidebar = $user && !$hideChrome;
$notifications = $user ? notification_summary($user) : ['total' => 0];
$scriptPath = trim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
$basePath = trim(app_base_url(), '/');
$currentPath = $basePath !== '' && str_starts_with($scriptPath, $basePath . '/')
    ? substr($scriptPath, strlen($basePath) + 1)
    : $scriptPath;
$isActive = static fn(string $path): bool => trim($path, '/') === $currentPath;

$manageLinks = [];
if ($user) {
    if ($user['role'] === 'admin') {
        $manageLinks = [
            ['path' => 'admin/users.php', 'label' => 'Tài khoản', 'icon' => 'users'],
            ['path' => 'admin/courses.php', 'label' => 'Học phần', 'icon' => 'book'],
            ['path' => 'admin/classes.php', 'label' => 'Lớp học phần', 'icon' => 'building'],
            ['path' => 'admin/registration_periods.php', 'label' => 'Đợt đăng ký', 'icon' => 'calendar'],
            ['path' => 'admin/activity_logs.php', 'label' => 'Nhật ký hoạt động', 'icon' => 'history'],
        ];
    } elseif ($user['role'] === 'teacher') {
        $manageLinks = [
            ['path' => 'teacher/topics.php', 'label' => 'Đề tài', 'icon' => 'file-text'],
            ['path' => 'teacher/groups.php', 'label' => 'Nhóm', 'icon' => 'users'],
            ['path' => 'teacher/registrations.php', 'label' => 'Duyệt đăng ký', 'icon' => 'clipboard'],
        ];
    } else {
        $manageLinks = [
            ['path' => 'student/group.php', 'label' => 'Nhóm của bạn', 'icon' => 'users'],
            ['path' => 'student/topics.php', 'label' => 'Đăng ký đề tài', 'icon' => 'file-text'],
        ];
    }
}

$sidebarIcons = [
    'dashboard' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    'building' => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 10 9 10"/><polyline points="12 7 12 12 16 14"/>',
    'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14l2 2 4-4"/>',
    'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
];
$icon = static fn(string $name): string => '<svg viewBox="0 0 24 24">' . ($sidebarIcons[$name] ?? '') . '</svg>';

$userInitials = mb_strtoupper(mb_substr($user['name'] ?? '', 0, 1));
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap&subset=vietnamese"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>

<body class="<?= $hasSidebar ? 'has-sidebar' : '' ?> <?= e($bodyClass) ?>" <?php if ($user): ?>
        data-session-timeout="<?= e((string) SESSION_TIMEOUT_SECONDS) ?>"
        data-session-timeout-url="<?= e(url('auth/logout.php?timeout=1')) ?>" <?php endif; ?>>

    <?php if ($hasSidebar): ?>
        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar" data-sidebar>
            <div class="sidebar-header">
                <a class="sidebar-brand" href="<?= e(url('dashboard.php')) ?>">
                    <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt="">
                    <span>Đăng ký đề tài</span>
                </a>
                <button class="sidebar-close" type="button" data-sidebar-close aria-label="Đóng menu">&times;</button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-group">
                    <span class="nav-group-title">Tổng quan</span>
                    <a class="sidebar-link <?= $isActive('dashboard.php') ? 'is-active' : '' ?>"
                        href="<?= e(url('dashboard.php')) ?>">
                        <?= $icon('dashboard') ?>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-group">
                    <span class="nav-group-title">Quản lý</span>
                    <?php foreach ($manageLinks as $link): ?>
                        <a class="sidebar-link <?= $isActive($link['path']) ? 'is-active' : '' ?>"
                            href="<?= e(url($link['path'])) ?>">
                            <?= $icon($link['icon']) ?>
                            <span><?= e($link['label']) ?></span>
                            <?php if ($link['icon'] === 'clipboard' && (int) $notifications['pending_registrations'] > 0): ?>
                                <span class="link-badge"><?= e((string) $notifications['pending_registrations']) ?></span>
                            <?php elseif ($link['icon'] === 'users' && $user['role'] === 'student' && (int) $notifications['pending_invites'] > 0): ?>
                                <span class="link-badge"><?= e((string) $notifications['pending_invites']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-footer-row">
                    <div class="sidebar-user">
                        <div class="user-avatar"><?= e($userInitials) ?></div>
                        <div class="user-info">
                            <strong><?= e($user['name']) ?></strong>
                            <small><?= e(role_label($user['role'])) ?></small>
                        </div>
                    </div>
                    <div class="sidebar-footer-actions">
                        <a class="sidebar-footer-action <?= $isActive('auth/change_password.php') ? 'is-active' : '' ?>"
                            href="<?= e(url('auth/change_password.php')) ?>" title="Đổi mật khẩu" aria-label="Đổi mật khẩu">
                            <?= $icon('lock') ?>
                        </a>
                        <form action="<?= e(url('auth/logout.php')) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="sidebar-footer-action is-danger" type="submit" title="Đăng xuất"
                                aria-label="Đăng xuất">
                                <?= $icon('logout') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ===== MAIN WRAPPER ===== -->
        <div class="main-wrapper">
            <header class="top-bar">
                <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Mở menu">☰</button>
                <span class="top-bar-title"><?= e($title) ?></span>
            </header>

            <main class="main-content">
            <?php elseif (!$hideChrome): ?>
                <!-- ===== LANDING HEADER ===== -->
                <header class="landing-header">
                    <a class="brand" href="<?= e(url('index.php')) ?>">
                        <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt=""
                            style="width:32px;height:32px;padding:4px;border:1px solid rgba(245,158,11,.7);border-radius:8px;background:rgba(255,255,255,.05)">
                        <span>Đăng ký đề tài</span>
                    </a>
                    <a class="btn btn-primary" href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a>
                </header>
                <main class="page-shell">
                <?php else: ?>
                    <!-- ===== AUTH / CHROMELESS ===== -->
                    <main class="<?= e($mainClass ?: 'auth-shell') ?>">
                    <?php endif; ?>

                    <?php $messages = flash_messages(); ?>
                    <?php if ($messages): ?>
                        <div class="flash-stack" aria-live="polite">
                            <?php foreach ($messages as $message): ?>
                                <div class="flash-message flash-<?= e($message['type']) ?>" data-auto-dismiss="20000">
                                    <span><?= e($message['message']) ?></span>
                                    <button type="button" aria-label="Đóng thông báo">&times;</button>
                                    <i></i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
