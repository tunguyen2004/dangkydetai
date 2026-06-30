<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

$user = current_user();

if (is_post()) {
    verify_csrf();

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $feedback = trim((string) ($_POST['teacher_feedback'] ?? ''));

    try {
        if (!in_array($status, ['approved', 'rejected', 'revision'], true)) {
            throw new RuntimeException('Trạng thái duyệt không hợp lệ.');
        }

        $stmt = db()->prepare(
            "SELECT r.*, g.id AS group_id, t.id AS topic_id, t.max_groups, c.teacher_id
             FROM topic_registrations r
             JOIN student_groups g ON g.id = r.group_id
             JOIN topics t ON t.id = r.topic_id
             JOIN classes c ON c.id = g.class_id
             WHERE r.id = ?
             LIMIT 1"
        );
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch();

        if (!$registration || (int) $registration['teacher_id'] !== (int) $user['id']) {
            throw new RuntimeException('Bạn không có quyền duyệt đăng ký này.');
        }

        if ($status === 'approved' && topic_approved_count((int) $registration['topic_id']) >= (int) $registration['max_groups']) {
            throw new RuntimeException('Đề tài này đã đủ số nhóm được duyệt.');
        }

        db()->beginTransaction();
        db()->prepare(
            'UPDATE topic_registrations
             SET status = ?, teacher_feedback = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        )->execute([$status, $feedback, (int) $user['id'], $registrationId]);

        $groupStatus = $status === 'approved' ? 'approved' : 'registered';
        db()->prepare('UPDATE student_groups SET status = ? WHERE id = ?')->execute([$groupStatus, (int) $registration['group_id']]);
        db()->commit();

        log_activity('review_registration', status_label($status) . ' đăng ký #' . $registrationId);
        flash('success', 'Đã cập nhật kết quả duyệt.');
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $exception->getMessage());
    }

    redirect('teacher/registrations.php');
}

$statusFilter = (string) ($_GET['status'] ?? 'pending');
$allowedFilters = ['pending', 'approved', 'rejected', 'revision', 'all'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'pending';
}

$where = 'WHERE c.teacher_id = ?';
$params = [(int) $user['id']];
if ($statusFilter !== 'all') {
    $where .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

$stmt = db()->prepare(
    "SELECT r.*, g.name AS group_name, c.name AS class_name, t.title AS topic_title, t.code AS topic_code,
            GROUP_CONCAT(CONCAT(u.student_code, ' - ', u.name, IF(gm.role = 'leader', ' (Trưởng nhóm)', '')) ORDER BY gm.role DESC, u.name SEPARATOR '\n') AS members
     FROM topic_registrations r
     JOIN student_groups g ON g.id = r.group_id
     JOIN classes c ON c.id = g.class_id
     JOIN topics t ON t.id = r.topic_id
     LEFT JOIN group_members gm ON gm.group_id = g.id
     LEFT JOIN users u ON u.id = gm.user_id
     $where
     GROUP BY r.id
     ORDER BY FIELD(r.status, 'pending', 'revision', 'approved', 'rejected'), r.created_at DESC"
);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

$page_title = 'Duyệt đăng ký đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Duyệt đăng ký đề tài</h1>
        <p>Giảng viên chấp nhận, từ chối hoặc yêu cầu nhóm chỉnh sửa đăng ký.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach ($allowedFilters as $filter): ?>
            <a class="btn btn-sm <?= $statusFilter === $filter ? 'btn-primary' : 'btn-outline-primary' ?>" href="?status=<?= e($filter) ?>">
                <?= e($filter === 'all' ? 'Tất cả' : status_label($filter)) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="d-grid gap-3">
    <?php foreach ($registrations as $registration): ?>
        <article class="card-panel">
            <div class="panel-body">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div>
                        <span class="<?= e(badge_class($registration['status'])) ?>"><?= e(status_label($registration['status'])) ?></span>
                        <h2 class="h4 fw-bold mt-3"><?= e($registration['group_name']) ?> · <?= e($registration['topic_code']) ?></h2>
                        <p class="mb-1"><strong>Đề tài:</strong> <?= e($registration['topic_title']) ?></p>
                        <p class="mb-1"><strong>Lớp:</strong> <?= e($registration['class_name']) ?></p>
                        <p class="mb-1"><strong>Thành viên:</strong></p>
                        <div class="text-muted" style="white-space: pre-line"><?= e($registration['members']) ?></div>
                        <p class="mt-3 mb-0"><strong>Ghi chú nhóm:</strong> <?= e($registration['note'] ?: '-') ?></p>
                        <?php if ($registration['teacher_feedback']): ?>
                            <p class="mt-2 mb-0 text-muted"><strong>Phản hồi:</strong> <?= e($registration['teacher_feedback']) ?></p>
                        <?php endif; ?>
                    </div>
                    <form class="review-form" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="registration_id" value="<?= e((string) $registration['id']) ?>">
                        <label class="form-label">Phản hồi giảng viên</label>
                        <textarea class="form-control mb-2" name="teacher_feedback" rows="3" placeholder="Lý do từ chối hoặc yêu cầu chỉnh sửa"><?= e($registration['teacher_feedback']) ?></textarea>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-success" name="status" value="approved" type="submit">Duyệt</button>
                            <button class="btn btn-sm btn-warning" name="status" value="revision" type="submit">Yêu cầu sửa</button>
                            <button class="btn btn-sm btn-outline-danger" name="status" value="rejected" type="submit">Từ chối</button>
                        </div>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$registrations): ?>
        <div class="card-panel"><div class="empty-state">Không có đăng ký nào ở trạng thái này.</div></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
