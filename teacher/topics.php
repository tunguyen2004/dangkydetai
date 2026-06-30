<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

$user = current_user();

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $owned = db()->prepare('SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?');
            $owned->execute([$classId, (int) $user['id']]);
            if ((int) $owned->fetchColumn() === 0) {
                throw new RuntimeException('Bạn không phụ trách lớp này.');
            }

            $stmt = db()->prepare(
                'INSERT INTO topics (class_id, teacher_id, code, title, description, technology, max_groups, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $classId,
                (int) $user['id'],
                trim((string) $_POST['code']),
                trim((string) $_POST['title']),
                trim((string) $_POST['description']),
                trim((string) ($_POST['technology'] ?? '')),
                max(1, (int) $_POST['max_groups']),
                (string) $_POST['status'],
            ]);
            log_activity('create_topic', 'Thêm đề tài ' . (string) $_POST['code']);
            flash('success', 'Đã thêm đề tài.');
        }

        if ($action === 'toggle_status') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $stmt = db()->prepare("UPDATE topics SET status = IF(status = 'open', 'closed', 'open') WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$topicId, (int) $user['id']]);
            flash('success', 'Đã cập nhật trạng thái đề tài.');
        }

        if ($action === 'delete') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $used = db()->prepare('SELECT COUNT(*) FROM topic_registrations WHERE topic_id = ?');
            $used->execute([$topicId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa đề tài đã có nhóm đăng ký. Hãy đóng đề tài nếu không muốn nhận thêm.');
            }
            db()->prepare('DELETE FROM topics WHERE id = ? AND teacher_id = ?')->execute([$topicId, (int) $user['id']]);
            flash('success', 'Đã xóa đề tài.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('teacher/topics.php');
}

$classes = db()->prepare('SELECT id, name FROM classes WHERE teacher_id = ? ORDER BY name');
$classes->execute([(int) $user['id']]);
$classes = $classes->fetchAll();

$stmt = db()->prepare(
    "SELECT t.*, c.name AS class_name,
            (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_id = t.id AND r.status = 'approved') AS approved_count,
            (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_id = t.id AND r.status = 'pending') AS pending_count
     FROM topics t
     JOIN classes c ON c.id = t.class_id
     WHERE t.teacher_id = ?
     ORDER BY t.created_at DESC"
);
$stmt->execute([(int) $user['id']]);
$topics = $stmt->fetchAll();

$page_title = 'Quản lý đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Quản lý đề tài</h1>
        <p>Giảng viên tạo danh sách đề tài và giới hạn số nhóm được chọn.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Thêm đề tài</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Lớp</label>
                <select class="form-select" name="class_id" required>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= e((string) $class['id']) ?>"><?= e($class['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mã đề tài</label>
                <input class="form-control" name="code" placeholder="DT05" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Tên đề tài</label>
                <input class="form-control" name="title" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Số nhóm tối đa</label>
                <input class="form-control" name="max_groups" type="number" min="1" value="1">
            </div>
            <div class="col-md-4">
                <label class="form-label">Công nghệ</label>
                <input class="form-control" name="technology" placeholder="PHP, MySQL">
            </div>
            <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select class="form-select" name="status">
                    <option value="open">Đang mở</option>
                    <option value="closed">Đã đóng</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" name="description" rows="2" required></textarea>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Thêm đề tài</button>
            </div>
        </form>
    </div>
</section>

<section class="grid-3">
    <?php foreach ($topics as $topic): ?>
        <article class="card-panel">
            <div class="panel-body">
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <span class="badge-soft-info"><?= e($topic['code']) ?></span>
                    <span class="<?= e(badge_class($topic['status'])) ?>"><?= e(status_label($topic['status'])) ?></span>
                </div>
                <h2 class="h5 fw-bold"><?= e($topic['title']) ?></h2>
                <p class="text-muted"><?= e($topic['description']) ?></p>
                <p class="mb-2"><strong>Lớp:</strong> <?= e($topic['class_name']) ?></p>
                <p class="mb-2"><strong>Công nghệ:</strong> <?= e($topic['technology'] ?: '-') ?></p>
                <p class="mb-3"><strong>Sức chứa:</strong> <?= e((string) $topic['approved_count']) ?>/<?= e((string) $topic['max_groups']) ?> nhóm đã duyệt, <?= e((string) $topic['pending_count']) ?> chờ duyệt</p>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="topic_id" value="<?= e((string) $topic['id']) ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><?= $topic['status'] === 'open' ? 'Đóng' : 'Mở' ?></button>
                    </form>
                    <form method="post" onsubmit="return confirm('Xóa đề tài này?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="topic_id" value="<?= e((string) $topic['id']) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$topics): ?>
        <div class="card-panel"><div class="empty-state">Chưa có đề tài nào.</div></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
