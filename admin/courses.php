<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $code = trim((string) ($_POST['code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập mã học phần và tên học phần.');
            }

            db()->prepare('INSERT INTO courses (code, name, description) VALUES (?, ?, ?)')
                ->execute([$code, $name, $description ?: null]);
            log_activity('create_course', 'Tạo học phần ' . $code);
            flash('success', 'Đã tạo học phần.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/courses.php');
}

$courses = db()->query(
    "SELECT co.*,
            COUNT(c.id) AS class_count
     FROM courses co
     LEFT JOIN classes c ON c.course_id = co.id
     GROUP BY co.id
     ORDER BY co.created_at DESC, co.name"
)->fetchAll();

$page_title = 'Học phần';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Học phần</h1>
        <p>Học phần là dữ liệu nền, lớp học phần sẽ trỏ đến học phần này.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Tạo học phần</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Mã học phần</label>
                <input class="form-control" name="code" placeholder="WEB301" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tên học phần</label>
                <input class="form-control" name="name" placeholder="Công nghệ Web" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mô tả</label>
                <input class="form-control" name="description" placeholder="Mô tả ngắn">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Tạo học phần</button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean admin-mobile-table courses-mobile-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Học phần</th>
                    <th>Mô tả</th>
                    <th>Số lớp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><span class="badge-soft-info"><?= e($course['code']) ?></span></td>
                        <td><strong><?= e($course['name']) ?></strong></td>
                        <td><?= e($course['description'] ?: '-') ?></td>
                        <td><?= e((string) $course['class_count']) ?> lớp</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$courses): ?>
                    <tr><td colspan="4" class="empty-state">Chưa có học phần nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
