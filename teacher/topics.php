<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

$user = current_user();

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_topic') {
            $stmt = db()->prepare(
                'INSERT INTO topics (teacher_id, code, title, description, min_members, max_members)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $user['id'],
                trim((string) $_POST['code']),
                trim((string) $_POST['title']),
                trim((string) $_POST['description']),
                max(1, (int) $_POST['min_members']),
                max(1, (int) $_POST['max_members']),
            ]);
            log_activity('create_topic', 'Tạo đề tài gốc ' . (string) $_POST['code']);
            flash('success', 'Đã tạo đề tài gốc.');
        }

        if ($action === 'assign_topic') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $classId = (int) ($_POST['class_id'] ?? 0);
            $periodId = (int) ($_POST['registration_period_id'] ?? 0);

            $ownedTopic = db()->prepare('SELECT COUNT(*) FROM topics WHERE id = ? AND teacher_id = ?');
            $ownedTopic->execute([$topicId, (int) $user['id']]);
            if ((int) $ownedTopic->fetchColumn() === 0) {
                throw new RuntimeException('Bạn không sở hữu đề tài này.');
            }

            $ownedClass = db()->prepare('SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?');
            $ownedClass->execute([$classId, (int) $user['id']]);
            if ((int) $ownedClass->fetchColumn() === 0) {
                throw new RuntimeException('Bạn không phụ trách lớp này.');
            }

            $assignedPeriod = db()->prepare('SELECT COUNT(*) FROM registration_period_classes WHERE class_id = ? AND registration_period_id = ?');
            $assignedPeriod->execute([$classId, $periodId]);
            if ((int) $assignedPeriod->fetchColumn() === 0) {
                throw new RuntimeException('Đợt đăng ký chưa được gán cho lớp này.');
            }

            db()->prepare(
                'INSERT INTO topic_classes (topic_id, class_id, registration_period_id, max_groups, status)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $topicId,
                $classId,
                $periodId,
                max(1, (int) $_POST['max_groups']),
                (string) $_POST['status'],
            ]);
            log_activity('assign_topic_class', 'Gán đề tài #' . $topicId . ' cho lớp #' . $classId);
            flash('success', 'Đã mở đề tài cho lớp và đợt đăng ký.');
        }

        if ($action === 'toggle_assignment') {
            $topicClassId = (int) ($_POST['topic_class_id'] ?? 0);
            $stmt = db()->prepare(
                "UPDATE topic_classes tc
                 JOIN classes c ON c.id = tc.class_id
                 SET tc.status = IF(tc.status = 'open', 'closed', 'open')
                 WHERE tc.id = ? AND c.teacher_id = ?"
            );
            $stmt->execute([$topicClassId, (int) $user['id']]);
            flash('success', 'Đã cập nhật trạng thái mở đề tài.');
        }

        if ($action === 'delete_assignment') {
            $topicClassId = (int) ($_POST['topic_class_id'] ?? 0);
            $used = db()->prepare('SELECT COUNT(*) FROM topic_registrations WHERE topic_class_id = ?');
            $used->execute([$topicClassId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa đề tài đã có nhóm đăng ký. Hãy đóng đề tài nếu không muốn nhận thêm.');
            }
            db()->prepare(
                'DELETE tc FROM topic_classes tc
                 JOIN classes c ON c.id = tc.class_id
                 WHERE tc.id = ? AND c.teacher_id = ?'
            )->execute([$topicClassId, (int) $user['id']]);
            flash('success', 'Đã xóa bản ghi mở đề tài cho lớp.');
        }

        if ($action === 'delete_topic') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $used = db()->prepare('SELECT COUNT(*) FROM topic_classes WHERE topic_id = ?');
            $used->execute([$topicId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new RuntimeException('Đề tài đã được gán cho lớp, không nên xóa để tránh mất lịch sử.');
            }
            db()->prepare('DELETE FROM topics WHERE id = ? AND teacher_id = ?')->execute([$topicId, (int) $user['id']]);
            flash('success', 'Đã xóa đề tài gốc.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('teacher/topics.php');
}

$topicsStmt = db()->prepare('SELECT * FROM topics WHERE teacher_id = ? ORDER BY created_at DESC, code');
$topicsStmt->execute([(int) $user['id']]);
$topics = $topicsStmt->fetchAll();

$contextsStmt = db()->prepare(
    "SELECT c.id AS class_id, c.name AS class_name, co.code AS course_code,
            p.id AS period_id, p.name AS period_name, p.status AS period_status
     FROM classes c
     JOIN courses co ON co.id = c.course_id
     JOIN registration_period_classes rpc ON rpc.class_id = c.id
     JOIN registration_periods p ON p.id = rpc.registration_period_id
     WHERE c.teacher_id = ?
     ORDER BY p.created_at DESC, c.name"
);
$contextsStmt->execute([(int) $user['id']]);
$contexts = $contextsStmt->fetchAll();

$assignmentsStmt = db()->prepare(
    "SELECT tc.*, t.code, t.title, t.description, t.min_members, t.max_members,
            c.name AS class_name, co.code AS course_code, p.name AS period_name,
            (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status IN ('pending', 'approved')) AS active_count,
            (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status = 'approved') AS approved_count
     FROM topic_classes tc
     JOIN topics t ON t.id = tc.topic_id
     JOIN classes c ON c.id = tc.class_id
     JOIN courses co ON co.id = c.course_id
     JOIN registration_periods p ON p.id = tc.registration_period_id
     WHERE t.teacher_id = ?
     ORDER BY tc.assigned_at DESC, t.code"
);
$assignmentsStmt->execute([(int) $user['id']]);
$assignments = $assignmentsStmt->fetchAll();

$page_title = 'Quản lý đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Quản lý đề tài</h1>
        <p>Tạo đề tài gốc, sau đó mở đề tài cho từng lớp và từng đợt đăng ký.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Tạo đề tài gốc</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_topic">
            <div class="col-md-2">
                <label class="form-label">Mã đề tài</label>
                <input class="form-control" name="code" placeholder="DT05" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tên đề tài</label>
                <input class="form-control" name="title" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối thiểu</label>
                <input class="form-control" name="min_members" type="number" min="1" value="2">
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối đa</label>
                <input class="form-control" name="max_members" type="number" min="1" value="4">
            </div>
            <div class="col-md-4">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" name="description" rows="2" required></textarea>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Tạo đề tài</button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Mở đề tài cho lớp</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_topic">
            <div class="col-md-4">
                <label class="form-label">Đề tài gốc</label>
                <select class="form-select" name="topic_id" required>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= e((string) $topic['id']) ?>"><?= e($topic['code'] . ' - ' . $topic['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lớp và đợt đăng ký</label>
                <select class="form-select" name="context" required onchange="const [c,p]=this.value.split('|'); this.form.class_id.value=c; this.form.registration_period_id.value=p;">
                    <?php foreach ($contexts as $context): ?>
                        <option value="<?= e($context['class_id'] . '|' . $context['period_id']) ?>">
                            <?= e($context['course_code'] . ' - ' . $context['class_name'] . ' · ' . $context['period_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="class_id" value="<?= e((string) ($contexts[0]['class_id'] ?? '')) ?>">
                <input type="hidden" name="registration_period_id" value="<?= e((string) ($contexts[0]['period_id'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Số nhóm tối đa</label>
                <input class="form-control" name="max_groups" type="number" min="1" value="1">
            </div>
            <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select class="form-select" name="status">
                    <option value="open">Đang mở</option>
                    <option value="closed">Đã đóng</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" <?= (!$topics || !$contexts) ? 'disabled' : '' ?>>Mở đề tài</button>
            </div>
        </form>
        <?php if (!$contexts): ?>
            <p class="text-muted mt-3 mb-0">Chưa có lớp nào được gán đợt đăng ký cho giảng viên này.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body table-responsive">
        <h2 class="h4 fw-bold mb-3">Đề tài đã mở cho lớp</h2>
        <table class="table-clean role-mobile-table teacher-assignment-mobile-table">
            <thead>
                <tr>
                    <th>Đề tài</th>
                    <th>Lớp / đợt</th>
                    <th>Yêu cầu</th>
                    <th>Sức chứa</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <?php $isFull = (int) $assignment['active_count'] >= (int) $assignment['max_groups']; ?>
                    <tr>
                        <td>
                            <span class="badge-soft-info"><?= e($assignment['code']) ?></span>
                            <strong><?= e($assignment['title']) ?></strong><br>
                            <span class="text-muted"><?= e($assignment['description']) ?></span>
                        </td>
                        <td><?= e($assignment['course_code'] . ' - ' . $assignment['class_name']) ?><br><span class="text-muted"><?= e($assignment['period_name']) ?></span></td>
                        <td><?= e((string) $assignment['min_members']) ?> - <?= e((string) $assignment['max_members']) ?> thành viên</td>
                        <td>
                            <span class="<?= e($assignment['status'] === 'open' && !$isFull ? 'badge-soft-success' : 'badge-soft-secondary') ?>">
                                <?= $isFull ? 'Đã đủ nhóm' : e(status_label($assignment['status'])) ?>
                            </span><br>
                            <span class="text-muted"><?= e((string) $assignment['active_count']) ?>/<?= e((string) $assignment['max_groups']) ?> nhóm giữ chỗ, <?= e((string) $assignment['approved_count']) ?> đã duyệt</span>
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_assignment">
                                    <input type="hidden" name="topic_class_id" value="<?= e((string) $assignment['id']) ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= $assignment['status'] === 'open' ? 'Đóng' : 'Mở' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Xóa bản ghi mở đề tài này?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_assignment">
                                    <input type="hidden" name="topic_class_id" value="<?= e((string) $assignment['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assignments): ?>
                    <tr><td colspan="5" class="empty-state">Chưa mở đề tài nào cho lớp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <h2 class="h4 fw-bold mb-3">Kho đề tài gốc</h2>
        <table class="table-clean role-mobile-table teacher-topic-bank-mobile-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Đề tài</th>
                    <th>Yêu cầu thành viên</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $topic): ?>
                    <tr>
                        <td><span class="badge-soft-info"><?= e($topic['code']) ?></span></td>
                        <td><strong><?= e($topic['title']) ?></strong><br><span class="text-muted"><?= e($topic['description']) ?></span></td>
                        <td><?= e((string) $topic['min_members']) ?> - <?= e((string) $topic['max_members']) ?> thành viên</td>
                        <td>
                            <form method="post" onsubmit="return confirm('Xóa đề tài gốc này?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_topic">
                                <input type="hidden" name="topic_id" value="<?= e((string) $topic['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topics): ?>
                    <tr><td colspan="4" class="empty-state">Chưa có đề tài gốc nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
