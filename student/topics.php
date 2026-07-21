<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');

$user = current_user();

function student_group_by_id(int $groupId, int $studentId): ?array
{
    $stmt = db()->prepare(
        'SELECT g.*, c.min_members AS class_min_members, c.max_members AS class_max_members,
                p.name AS period_name, p.register_start, p.register_end, p.status AS period_status,
                co.code AS course_code, c.name AS class_name
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         JOIN classes c ON c.id = g.class_id
         JOIN courses co ON co.id = c.course_id
         JOIN registration_periods p ON p.id = g.registration_period_id
         WHERE g.id = ? AND gm.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$groupId, $studentId]);
    $group = $stmt->fetch();

    return $group ?: null;
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'register_topic');

    try {
        if ($action === 'cancel_registration') {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $stmt = db()->prepare(
                "SELECT r.*, g.id AS group_id
                 FROM topic_registrations r
                 JOIN student_groups g ON g.id = r.group_id
                 JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ? AND gm.role = 'leader'
                 WHERE r.id = ? AND r.status = 'pending'
                 LIMIT 1"
            );
            $stmt->execute([(int) $user['id'], $registrationId]);
            $registration = $stmt->fetch();
            if (!$registration) {
                throw new RuntimeException('Không tìm thấy đăng ký chờ duyệt của nhóm bạn.');
            }

            db()->beginTransaction();
            db()->prepare("UPDATE topic_registrations SET status = 'cancelled', active_group_id = NULL WHERE id = ?")->execute([$registrationId]);
            db()->prepare("UPDATE student_groups SET status = 'forming' WHERE id = ?")->execute([(int) $registration['group_id']]);
            db()->commit();

            log_activity('cancel_registration', 'Hủy đăng ký #' . $registrationId);
            flash('success', 'Đã hủy đăng ký chờ duyệt.');
            redirect('student/topics.php');
        }

        $groupId = (int) ($_POST['group_id'] ?? 0);
        $topicClassId = (int) ($_POST['topic_class_id'] ?? 0);
        $group = student_group_by_id($groupId, (int) $user['id']);

        if (!$group) {
            throw new RuntimeException('Bạn chưa thuộc nhóm này.');
        }
        if (!is_group_leader($groupId, (int) $user['id'])) {
            throw new RuntimeException('Chỉ trưởng nhóm được đăng ký đề tài.');
        }
        if ($group['status'] === 'locked') {
            throw new RuntimeException('Nhóm đã khóa nên không thể đăng ký đề tài khác.');
        }
        if ($group['period_status'] !== 'open' || !time_between($group['register_start'], $group['register_end'])) {
            throw new RuntimeException('Hiện không nằm trong thời gian đăng ký đề tài.');
        }

        $current = group_active_registration($groupId);
        if ($current) {
            throw new RuntimeException('Nhóm đã có đăng ký đang chờ duyệt hoặc đã được duyệt.');
        }

        $stmt = db()->prepare(
            "SELECT tc.*, t.code, t.title, t.min_members, t.max_members
             FROM topic_classes tc
             JOIN topics t ON t.id = tc.topic_id
             WHERE tc.id = ? AND tc.class_id = ? AND tc.registration_period_id = ? AND tc.status = 'open'
             LIMIT 1"
        );
        $stmt->execute([$topicClassId, (int) $group['class_id'], (int) $group['registration_period_id']]);
        $topic = $stmt->fetch();
        if (!$topic) {
            throw new RuntimeException('Đề tài không hợp lệ, không thuộc lớp/đợt của nhóm hoặc đã đóng.');
        }

        $memberCount = group_member_count($groupId);
        if ($memberCount < (int) $topic['min_members']) {
            throw new RuntimeException('Nhóm chưa đủ số lượng thành viên tối thiểu của đề tài.');
        }
        if ($memberCount > (int) $topic['max_members']) {
            throw new RuntimeException('Nhóm vượt số lượng thành viên tối đa của đề tài. Hãy chọn đề tài khác hoặc điều chỉnh thành viên.');
        }

        $activeCount = topic_class_active_count($topicClassId);
        if ($activeCount >= (int) $topic['max_groups']) {
            throw new RuntimeException('Đề tài đã đủ số nhóm đăng ký/chờ duyệt.');
        }

        db()->beginTransaction();
        db()->prepare(
            "INSERT INTO topic_registrations (group_id, topic_class_id, requested_by, status, active_group_id)
             VALUES (?, ?, ?, 'pending', ?)"
        )->execute([$groupId, $topicClassId, (int) $user['id'], $groupId]);
        db()->prepare("UPDATE student_groups SET status = 'registered' WHERE id = ?")->execute([$groupId]);
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

$groups = student_groups((int) $user['id']);
$topicsByGroup = [];
foreach ($groups as $group) {
    $stmt = db()->prepare(
        "SELECT tc.*, t.code, t.title, t.description, t.min_members, t.max_members,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status IN ('pending', 'approved')) AS active_count,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status = 'approved') AS approved_count
         FROM topic_classes tc
         JOIN topics t ON t.id = tc.topic_id
         WHERE tc.class_id = ? AND tc.registration_period_id = ?
         ORDER BY tc.status DESC, t.code"
    );
    $stmt->execute([(int) $group['class_id'], (int) $group['registration_period_id']]);
    $topicsByGroup[(int) $group['id']] = $stmt->fetchAll();
}

$page_title = 'Đăng ký đề tài';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Đăng ký đề tài</h1>
        <p>Trưởng nhóm chọn đề tài đã được mở cho đúng lớp và đợt đăng ký của nhóm.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(url('student/group.php')) ?>">Nhóm của bạn</a>
</section>

<?php if (!$groups): ?>
    <section class="card-panel">
        <div class="empty-state">Bạn cần tạo hoặc tham gia nhóm trước khi đăng ký đề tài.</div>
    </section>
<?php else: ?>
    <section class="d-grid gap-4">
        <?php foreach ($groups as $group): ?>
            <?php
            $registration = group_registration((int) $group['id']);
            $activeRegistration = group_active_registration((int) $group['id']);
            $isLeader = is_group_leader((int) $group['id'], (int) $user['id']);
            $memberCount = group_member_count((int) $group['id']);
            $periodOpen = $group['period_status'] === 'open' && time_between($group['register_start'], $group['register_end']);
            ?>
            <article class="card-panel">
                <div class="panel-body">
                    <div class="section-heading mb-3">
                        <div>
                            <h2 class="h4 fw-bold mb-1"><?= e($group['name']) ?></h2>
                            <p class="text-muted mb-0"><?= e($group['course_code'] . ' - ' . $group['class_name']) ?> ·
                                <?= e($group['period_name']) ?> · <?= e((string) $memberCount) ?> thành viên</p>
                        </div>
                        <span
                            class="<?= e(badge_class($periodOpen ? 'open' : 'closed')) ?>"><?= $periodOpen ? 'Đang trong hạn đăng ký' : 'Ngoài hạn đăng ký' ?></span>
                    </div>

                    <?php if ($registration): ?>
                        <section class="notice-panel mb-4">
                            <span
                                class="<?= e(badge_class($registration['status'])) ?>"><?= e(status_label($registration['status'])) ?></span>
                            <h3 class="h5 fw-bold mt-3"><?= e($registration['topic_code'] . ' - ' . $registration['topic_title']) ?>
                            </h3>
                            <p class="mb-0"><strong>Phản hồi giảng viên:</strong>
                                <?= e($registration['teacher_feedback'] ?: 'Chưa có phản hồi.') ?></p>
                            <?php if ($registration['status'] === 'pending' && $isLeader): ?>
                                <form class="mt-3" method="post" onsubmit="return confirm('Hủy đăng ký đang chờ duyệt?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel_registration">
                                    <input type="hidden" name="registration_id" value="<?= e((string) $registration['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hủy đăng ký</button>
                                </form>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <section class="grid-3">
                        <?php foreach ($topicsByGroup[(int) $group['id']] ?? [] as $topic): ?>
                            <?php
                            $isFull = (int) $topic['active_count'] >= (int) $topic['max_groups'];
                            $memberFits = $memberCount >= (int) $topic['min_members'] && $memberCount <= (int) $topic['max_members'];
                            $canSubmit = $isLeader
                                && $periodOpen
                                && $group['status'] !== 'locked'
                                && !$activeRegistration
                                && !$isFull
                                && $memberFits
                                && $topic['status'] === 'open';
                            ?>
                            <article class="card-panel">
                                <div class="panel-body">
                                    <div class="d-flex justify-content-between gap-3 mb-2">
                                        <span class="badge-soft-info"><?= e($topic['code']) ?></span>
                                        <span
                                            class="<?= e($topic['status'] === 'open' && !$isFull ? 'badge-soft-success' : 'badge-soft-secondary') ?>">
                                            <?= $isFull ? 'Đã đủ nhóm' : e(status_label($topic['status'])) ?>
                                        </span>
                                    </div>
                                    <h3 class="h5 fw-bold"><?= e($topic['title']) ?></h3>
                                    <p class="text-muted"><?= e($topic['description']) ?></p>
                                    <p><strong>Yêu cầu:</strong> <?= e((string) $topic['min_members']) ?> -
                                        <?= e((string) $topic['max_members']) ?> thành viên</p>
                                    <p><strong>Sức chứa:</strong>
                                        <?= e((string) $topic['active_count']) ?>/<?= e((string) $topic['max_groups']) ?> nhóm</p>
                                    <?php if ($canSubmit): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="register_topic">
                                            <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                                            <input type="hidden" name="topic_class_id" value="<?= e((string) $topic['id']) ?>">
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
                        <?php if (empty($topicsByGroup[(int) $group['id']])): ?>
                            <div class="card-panel">
                                <div class="empty-state">Lớp này chưa có đề tài được mở trong đợt hiện tại.</div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>