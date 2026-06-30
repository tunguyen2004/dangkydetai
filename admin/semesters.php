<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO semesters (name, group_start, group_end, topic_start, topic_end, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) $_POST['name']),
                $_POST['group_start'],
                $_POST['group_end'],
                $_POST['topic_start'],
                $_POST['topic_end'],
                $_POST['status'],
            ]);
            log_activity('create_semester', 'Tạo đợt đăng ký ' . (string) $_POST['name']);
            flash('success', 'Đã tạo đợt đăng ký.');
        }

        if ($action === 'set_status') {
            $semesterId = (int) ($_POST['semester_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'draft');
            if (!in_array($status, ['draft', 'open', 'closed'], true)) {
                throw new RuntimeException('Trạng thái không hợp lệ.');
            }
            if ($status === 'open') {
                db()->exec("UPDATE semesters SET status = 'closed' WHERE status = 'open'");
            }
            db()->prepare('UPDATE semesters SET status = ? WHERE id = ?')->execute([$status, $semesterId]);
            log_activity('set_semester_status', 'Cập nhật trạng thái đợt #' . $semesterId);
            flash('success', 'Đã cập nhật trạng thái đợt đăng ký.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/semesters.php');
}

$semesters = db()->query('SELECT * FROM semesters ORDER BY id DESC')->fetchAll();
$page_title = 'Đợt đăng ký';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Đợt đăng ký</h1>
        <p>Thiết lập thời gian tạo nhóm và đăng ký đề tài.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Tạo đợt mới</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <label class="form-label">Tên đợt</label>
                <input class="form-control" name="name" value="Học kỳ 1 năm học 2026-2027" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Bắt đầu tạo nhóm</label>
                <input class="form-control" name="group_start" type="date" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kết thúc tạo nhóm</label>
                <input class="form-control" name="group_end" type="date" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Bắt đầu đăng ký</label>
                <input class="form-control" name="topic_start" type="date" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kết thúc đăng ký</label>
                <input class="form-control" name="topic_end" type="date" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select class="form-select" name="status">
                    <option value="draft">Nháp</option>
                    <option value="open">Đang mở</option>
                    <option value="closed">Đã đóng</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Tạo đợt</button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Tên đợt</th>
                    <th>Tạo nhóm</th>
                    <th>Đăng ký đề tài</th>
                    <th>Trạng thái</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($semesters as $semester): ?>
                    <tr>
                        <td><strong><?= e($semester['name']) ?></strong></td>
                        <td><?= e(date('d/m/Y', strtotime($semester['group_start']))) ?> - <?= e(date('d/m/Y', strtotime($semester['group_end']))) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($semester['topic_start']))) ?> - <?= e(date('d/m/Y', strtotime($semester['topic_end']))) ?></td>
                        <td><span class="<?= e(badge_class($semester['status'])) ?>"><?= e(status_label($semester['status'])) ?></span></td>
                        <td>
                            <form class="d-flex gap-2" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="semester_id" value="<?= e((string) $semester['id']) ?>">
                                <select class="form-select form-select-sm" name="status">
                                    <option value="draft">Nháp</option>
                                    <option value="open">Mở</option>
                                    <option value="closed">Đóng</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
