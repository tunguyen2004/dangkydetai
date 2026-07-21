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
        if (!in_array($status, ['approved', 'rejected', 'revoked'], true)) {
            throw new RuntimeException('Trạng thái duyệt không hợp lệ.');
        }

        $stmt = db()->prepare(
            "SELECT r.*, g.id AS group_id, g.class_id AS group_class_id, g.registration_period_id AS group_period_id,
                    tc.id AS topic_class_id, tc.class_id, tc.registration_period_id, tc.max_groups,
                    t.min_members, t.max_members, t.code AS topic_code, c.teacher_id
             FROM topic_registrations r
             JOIN student_groups g ON g.id = r.group_id
             JOIN topic_classes tc ON tc.id = r.topic_class_id
             JOIN topics t ON t.id = tc.topic_id
             JOIN classes c ON c.id = tc.class_id
             WHERE r.id = ?
             LIMIT 1"
        );
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch();

        if (!$registration || (int) $registration['teacher_id'] !== (int) $user['id']) {
            throw new RuntimeException('Bạn không có quyền duyệt đăng ký này.');
        }

        if ((int) $registration['group_class_id'] !== (int) $registration['class_id']
            || (int) $registration['group_period_id'] !== (int) $registration['registration_period_id']) {
            throw new RuntimeException('Đăng ký bị lệch lớp hoặc đợt đăng ký.');
        }

        if ($status === 'approved') {
            if (!in_array((string) $registration['status'], ['pending', 'approved'], true)) {
                throw new RuntimeException('Chỉ duyệt được đăng ký đang chờ hoặc đang được duyệt.');
            }

            $memberCount = group_member_count((int) $registration['group_id']);
            if ($memberCount < (int) $registration['min_members'] || $memberCount > (int) $registration['max_members']) {
                throw new RuntimeException('Số thành viên nhóm không phù hợp với yêu cầu đề tài.');
            }

            $active = db()->prepare(
                "SELECT COUNT(*)
                 FROM topic_registrations
                 WHERE group_id = ? AND status IN ('pending', 'approved') AND id <> ?"
            );
            $active->execute([(int) $registration['group_id'], $registrationId]);
            if ((int) $active->fetchColumn() > 0) {
                throw new RuntimeException('Nhóm này đang có đăng ký khác còn hiệu lực.');
            }

            $approved = db()->prepare(
                "SELECT COUNT(*)
                 FROM topic_registrations
                 WHERE topic_class_id = ? AND status = 'approved' AND id <> ?"
            );
            $approved->execute([(int) $registration['topic_class_id'], $registrationId]);
            if ((int) $approved->fetchColumn() >= (int) $registration['max_groups']) {
                throw new RuntimeException('Đề tài này đã đủ số nhóm được duyệt.');
            }
        }

        if ($status === 'rejected' && $registration['status'] !== 'pending') {
            throw new RuntimeException('Chỉ từ chối đăng ký đang chờ duyệt.');
        }

        if ($status === 'revoked' && $registration['status'] !== 'approved') {
            throw new RuntimeException('Chỉ hủy duyệt đăng ký đã được duyệt.');
        }

        db()->beginTransaction();
        db()->prepare(
            "UPDATE topic_registrations
             SET status = ?, teacher_feedback = ?, reviewed_by = ?, reviewed_at = NOW(),
                 active_group_id = IF(? IN ('pending', 'approved'), group_id, NULL)
             WHERE id = ?"
        )->execute([$status, $feedback, (int) $user['id'], $status, $registrationId]);

        $groupStatus = match ($status) {
            'approved' => 'locked',
            'rejected', 'revoked' => 'forming',
            default => 'registered',
        };
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
$allowedFilters = ['pending', 'approved', 'rejected', 'revoked', 'cancelled', 'all'];
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
    "SELECT r.*, g.name AS group_name, c.name AS class_name, co.code AS course_code,
            p.name AS period_name, t.title AS topic_title, t.code AS topic_code,
            GROUP_CONCAT(CONCAT(u.user_code, ' - ', u.name, IF(gm.role = 'leader', ' (Trưởng nhóm)', '')) ORDER BY gm.role DESC, u.name SEPARATOR '\n') AS members
     FROM topic_registrations r
     JOIN student_groups g ON g.id = r.group_id
     JOIN topic_classes tc ON tc.id = r.topic_class_id
     JOIN topics t ON t.id = tc.topic_id
     JOIN classes c ON c.id = tc.class_id
     JOIN courses co ON co.id = c.course_id
     JOIN registration_periods p ON p.id = tc.registration_period_id
     LEFT JOIN group_members gm ON gm.group_id = g.id
     LEFT JOIN users u ON u.id = gm.user_id
     $where
     GROUP BY r.id
     ORDER BY FIELD(r.status, 'pending', 'approved', 'revoked', 'rejected', 'cancelled'), r.created_at DESC"
);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

$page_title = 'Duyệt đăng ký đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Duyệt đăng ký đề tài</h1>
        <p>Giảng viên chấp nhận, từ chối hoặc hủy duyệt đăng ký của nhóm thuộc lớp mình phụ trách.</p>
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
                <div class="registration-review">
                    <div>
                        <span class="<?= e(badge_class($registration['status'])) ?>"><?= e(status_label($registration['status'])) ?></span>
                        <h2 class="h4 fw-bold mt-3"><?= e($registration['group_name']) ?> · <?= e($registration['topic_code']) ?></h2>
                        <p class="mb-1"><strong>Đề tài:</strong> <?= e($registration['topic_title']) ?></p>
                        <p class="mb-1"><strong>Lớp:</strong> <?= e($registration['course_code'] . ' - ' . $registration['class_name']) ?></p>
                        <p class="mb-1"><strong>Đợt:</strong> <?= e($registration['period_name']) ?></p>
                        <p class="mb-1"><strong>Ngày gửi:</strong> <?= e(format_datetime($registration['created_at'])) ?></p>
                        <p class="mb-1"><strong>Thành viên:</strong></p>
                        <div class="text-muted" style="white-space: pre-line"><?= e($registration['members']) ?></div>
                        <?php if ($registration['teacher_feedback']): ?>
                            <p class="mt-3 mb-0 text-muted"><strong>Phản hồi:</strong> <?= e($registration['teacher_feedback']) ?></p>
                        <?php endif; ?>
                    </div>
                    <form class="review-form" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="registration_id" value="<?= e((string) $registration['id']) ?>">
                        <label class="form-label">Phản hồi giảng viên</label>
                        <textarea class="form-control mb-2" name="teacher_feedback" rows="3" placeholder="Lý do từ chối hoặc hủy duyệt"><?= e($registration['teacher_feedback']) ?></textarea>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($registration['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-success" name="status" value="approved" type="submit">Duyệt</button>
                                <button class="btn btn-sm btn-outline-danger" name="status" value="rejected" type="submit">Từ chối</button>
                            <?php elseif ($registration['status'] === 'approved'): ?>
                                <button class="btn btn-sm btn-outline-danger" name="status" value="revoked" type="submit">Hủy duyệt</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Đã xử lý</button>
                            <?php endif; ?>
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
