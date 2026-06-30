<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');

$user = current_user();
$group = student_group((int) $user['id']);

if (is_post()) {
    verify_csrf();

    try {
        if (!$group) {
            throw new RuntimeException('Bạn cần có nhóm trước khi đăng ký đề tài.');
        }
        if (!is_group_leader((int) $group['id'], (int) $user['id'])) {
            throw new RuntimeException('Chỉ trưởng nhóm được đăng ký đề tài.');
        }
        if (group_member_count((int) $group['id']) < (int) $group['min_members']) {
            throw new RuntimeException('Nhóm chưa đủ số lượng thành viên tối thiểu.');
        }

        $stmt = db()->prepare(
            "SELECT s.topic_start, s.topic_end, s.status AS semester_status
             FROM classes c
             JOIN semesters s ON s.id = c.semester_id
             WHERE c.id = ?"
        );
        $stmt->execute([(int) $group['class_id']]);
        $semester = $stmt->fetch();
        if (!$semester || $semester['semester_status'] !== 'open' || !today_between($semester['topic_start'], $semester['topic_end'])) {
            throw new RuntimeException('Hiện không nằm trong thời gian đăng ký đề tài.');
        }

        $current = group_registration((int) $group['id']);
        if ($current && in_array((string) $current['status'], ['pending', 'approved'], true)) {
            throw new RuntimeException('Nhóm đã có đăng ký đang chờ duyệt hoặc đã được duyệt.');
        }

        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM topics WHERE id = ? AND class_id = ? AND status = 'open' LIMIT 1");
        $stmt->execute([$topicId, (int) $group['class_id']]);
        $topic = $stmt->fetch();
        if (!$topic) {
            throw new RuntimeException('Đề tài không hợp lệ hoặc đã đóng.');
        }

        $activeCount = db()->prepare("SELECT COUNT(*) FROM topic_registrations WHERE topic_id = ? AND status IN ('pending', 'approved', 'revision')");
        $activeCount->execute([$topicId]);
        if ((int) $activeCount->fetchColumn() >= (int) $topic['max_groups']) {
            throw new RuntimeException('Đề tài đã đủ số nhóm đăng ký/chờ duyệt.');
        }

        db()->beginTransaction();
        db()->prepare(
            "INSERT INTO topic_registrations (group_id, topic_id, requested_by, status, note)
             VALUES (?, ?, ?, 'pending', ?)"
        )->execute([(int) $group['id'], $topicId, (int) $user['id'], trim((string) ($_POST['note'] ?? ''))]);
        db()->prepare("UPDATE student_groups SET status = 'registered' WHERE id = ?")->execute([(int) $group['id']]);
        db()->commit();

        log_activity('register_topic', 'Nhóm ' . $group['name'] . ' đăng ký đề tài ' . $topic['code']);
        flash('success', 'Đã gửi đăng ký đề tài. Vui lòng chờ giảng viên duyệt.');
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $exception->getMessage());
    }

    redirect('student/topics.php');
}

$topics = [];
$registration = null;
if ($group) {
    $registration = group_registration((int) $group['id']);
    $stmt = db()->prepare(
        "SELECT t.*,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_id = t.id AND r.status IN ('pending', 'approved', 'revision')) AS active_count,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_id = t.id AND r.status = 'approved') AS approved_count
         FROM topics t
         WHERE t.class_id = ?
         ORDER BY t.status DESC, t.code"
    );
    $stmt->execute([(int) $group['class_id']]);
    $topics = $stmt->fetchAll();
}

$page_title = 'Đăng ký đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Đăng ký đề tài</h1>
        <p>Trưởng nhóm chọn đề tài còn chỗ và gửi đăng ký cho giảng viên duyệt.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(url('student/group.php')) ?>">Nhóm của em</a>
</section>

<?php if (!$group): ?>
    <section class="card-panel"><div class="empty-state">Bạn cần tạo hoặc tham gia nhóm trước khi đăng ký đề tài.</div></section>
<?php else: ?>
    <?php if ($registration): ?>
        <section class="card-panel mb-4">
            <div class="panel-body">
                <span class="<?= e(badge_class($registration['status'])) ?>"><?= e(status_label($registration['status'])) ?></span>
                <h2 class="h4 fw-bold mt-3"><?= e($registration['topic_code'] . ' - ' . $registration['topic_title']) ?></h2>
                <p class="mb-1"><strong>Ghi chú nhóm:</strong> <?= e($registration['note'] ?: '-') ?></p>
                <p class="mb-0"><strong>Phản hồi giảng viên:</strong> <?= e($registration['teacher_feedback'] ?: 'Chưa có phản hồi.') ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="grid-3">
        <?php foreach ($topics as $topic): ?>
            <?php
            $isFull = (int) $topic['active_count'] >= (int) $topic['max_groups'];
            $canSubmit = is_group_leader((int) $group['id'], (int) $user['id'])
                && group_member_count((int) $group['id']) >= (int) $group['min_members']
                && !$isFull
                && $topic['status'] === 'open'
                && (!$registration || in_array((string) $registration['status'], ['rejected', 'revision'], true));
            ?>
            <article class="card-panel">
                <div class="panel-body">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span class="badge-soft-info"><?= e($topic['code']) ?></span>
                        <span class="<?= e($topic['status'] === 'open' && !$isFull ? 'badge-soft-success' : 'badge-soft-secondary') ?>">
                            <?= $isFull ? 'Đã đủ nhóm' : e(status_label($topic['status'])) ?>
                        </span>
                    </div>
                    <h2 class="h5 fw-bold"><?= e($topic['title']) ?></h2>
                    <p class="text-muted"><?= e($topic['description']) ?></p>
                    <p><strong>Công nghệ:</strong> <?= e($topic['technology'] ?: '-') ?></p>
                    <p><strong>Sức chứa:</strong> <?= e((string) $topic['active_count']) ?>/<?= e((string) $topic['max_groups']) ?> nhóm</p>
                    <?php if ($canSubmit): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="topic_id" value="<?= e((string) $topic['id']) ?>">
                            <label class="form-label">Ghi chú đăng ký</label>
                            <textarea class="form-control mb-2" name="note" rows="2" placeholder="Nhóm đã có định hướng triển khai..."></textarea>
                            <button class="btn btn-primary w-100" type="submit">Đăng ký đề tài</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary w-100" type="button" disabled>
                            Không thể đăng ký
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$topics): ?>
            <div class="card-panel"><div class="empty-state">Lớp của bạn chưa có đề tài.</div></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
