<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (logged_in()) {
    redirect('dashboard.php');
}

$page_title = 'Trang chủ';
require_once __DIR__ . '/includes/header.php';
?>
<section class="section-heading">
    <div>
        <p class="text-uppercase fw-bold text-primary mb-2">Công nghệ web · Nhóm 10</p>
        <h1>Quản lý nhóm và đăng ký đề tài học phần.</h1>
        <p>Sinh viên lập nhóm, trưởng nhóm đăng ký đề tài, giảng viên duyệt và hệ thống kiểm soát trùng nhóm, trùng đề tài, sai thời hạn.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('auth/login.php')) ?>">Đăng nhập hệ thống</a>
</section>

<section class="grid-3">
    <article class="stat-card">
        <span>Sinh viên</span>
        <strong>Tạo nhóm</strong>
        <p class="text-muted mb-0">Mời thành viên, nhận lời mời và theo dõi trạng thái đăng ký đề tài.</p>
    </article>
    <article class="stat-card">
        <span>Giảng viên</span>
        <strong>Duyệt đề tài</strong>
        <p class="text-muted mb-0">Quản lý danh mục đề tài, xem nhóm và phản hồi đăng ký.</p>
    </article>
    <article class="stat-card">
        <span>Admin</span>
        <strong>Quản trị</strong>
        <p class="text-muted mb-0">Quản lý người dùng, lớp học phần và thời gian đăng ký.</p>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
