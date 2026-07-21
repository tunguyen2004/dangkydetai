<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');

$user = current_user();

function student_context(string $context, int $studentId): ?array
{
    [$classId, $periodId] = array_pad(explode('|', $context), 2, 0);
    $stmt = db()->prepare(
        'SELECT c.*, p.id AS period_id, p.name AS period_name, p.group_start, p.group_end, p.status AS period_status,
                co.code AS course_code, co.name AS course_name
         FROM classes c
         JOIN courses co ON co.id = c.course_id
         JOIN class_students cs ON cs.class_id = c.id
         JOIN registration_period_classes rpc ON rpc.class_id = c.id
         JOIN registration_periods p ON p.id = rpc.registration_period_id
         WHERE c.id = ? AND p.id = ? AND cs.student_id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $classId, (int) $periodId, $studentId]);
    $contextRow = $stmt->fetch();

    return $contextRow ?: null;
}

function student_owned_group(int $groupId, int $studentId): ?array
{
    $stmt = db()->prepare(
        'SELECT g.*, c.min_members, c.max_members, c.allow_self_group, p.group_start, p.group_end, p.status AS period_status
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         JOIN classes c ON c.id = g.class_id
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
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_group') {
            $context = student_context((string) ($_POST['context'] ?? ''), (int) $user['id']);
            if (!$context) {
                throw new RuntimeException('Bạn không thuộc lớp hoặc đợt đăng ký này.');
            }
            if ((int) $context['allow_self_group'] !== 1) {
                throw new RuntimeException('Lớp này chưa cho phép sinh viên tự tạo nhóm.');
            }
            if ($context['period_status'] !== 'open' || !time_between($context['group_start'], $context['group_end'])) {
                throw new RuntimeException('Hiện không nằm trong thời gian tạo nhóm.');
            }
            if (student_group_for_context((int) $user['id'], (int) $context['id'], (int) $context['period_id'])) {
                throw new RuntimeException('Bạn đã thuộc một nhóm trong lớp và đợt này.');
            }

            $count = db()->prepare('SELECT COUNT(*) FROM student_groups WHERE class_id = ? AND registration_period_id = ?');
            $count->execute([(int) $context['id'], (int) $context['period_id']]);
            if ((int) $count->fetchColumn() >= (int) $context['max_groups']) {
                throw new RuntimeException('Lớp đã đạt số nhóm tối đa trong đợt này.');
            }

            db()->beginTransaction();
            $code = random_join_code();
            db()->prepare('INSERT INTO student_groups (class_id, registration_period_id, name, join_code, created_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $context['id'], (int) $context['period_id'], trim((string) $_POST['name']), $code, (int) $user['id']]);
            $groupId = (int) db()->lastInsertId();
            db()->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')")
                ->execute([$groupId, (int) $user['id']]);
            db()->commit();

            log_activity('create_group', 'Tạo nhóm ' . (string) $_POST['name']);
            flash('success', 'Đã tạo nhóm. Mã tham gia: ' . $code);
        }

        if ($action === 'invite') {
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $group = student_owned_group($groupId, (int) $user['id']);
            if (!$group || !is_group_leader($groupId, (int) $user['id'])) {
                throw new RuntimeException('Chỉ trưởng nhóm mới được mời thành viên.');
            }
            if ($group['status'] === 'locked' || group_active_registration($groupId)) {
                throw new RuntimeException('Nhóm đã đăng ký hoặc đã khóa nên không thể thêm thành viên.');
            }
            if ($group['period_status'] !== 'open' || !time_between($group['group_start'], $group['group_end'])) {
                throw new RuntimeException('Hiện không nằm trong thời gian tạo nhóm.');
            }
            if (group_member_count($groupId) >= (int) $group['max_members']) {
                throw new RuntimeException('Nhóm đã đủ số lượng thành viên tối đa.');
            }

            $keyword = trim((string) ($_POST['keyword'] ?? ''));
            $stmt = db()->prepare(
                "SELECT u.*
                 FROM users u
                 JOIN class_students cs ON cs.student_id = u.id
                 WHERE cs.class_id = ? AND u.role = 'student' AND u.is_locked = 0
                   AND (u.email = ? OR u.user_code = ?)
                 LIMIT 1"
            );
            $stmt->execute([(int) $group['class_id'], $keyword, $keyword]);
            $student = $stmt->fetch();

            if (!$student) {
                throw new RuntimeException('Không tìm thấy sinh viên trong cùng lớp.');
            }
            if ((int) $student['id'] === (int) $user['id']) {
                throw new RuntimeException('Bạn đã là trưởng nhóm.');
            }
            if (student_group_for_context((int) $student['id'], (int) $group['class_id'], (int) $group['registration_period_id'])) {
                throw new RuntimeException('Sinh viên này đã thuộc nhóm khác trong lớp và đợt này.');
            }

            $pending = db()->prepare("SELECT COUNT(*) FROM group_invitations WHERE group_id = ? AND invited_user_id = ? AND status = 'pending'");
            $pending->execute([$groupId, (int) $student['id']]);
            if ((int) $pending->fetchColumn() > 0) {
                throw new RuntimeException('Sinh viên này đã có lời mời đang chờ.');
            }

            db()->prepare('INSERT INTO group_invitations (group_id, invited_user_id, invited_by) VALUES (?, ?, ?)')
                ->execute([$groupId, (int) $student['id'], (int) $user['id']]);
            log_activity('invite_member', 'Mời ' . $student['email'] . ' vào nhóm');
            flash('success', 'Đã gửi lời mời vào nhóm.');
        }

        if ($action === 'respond_invite') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $response = (string) ($_POST['response'] ?? 'rejected');
            if (!in_array($response, ['accepted', 'rejected'], true)) {
                throw new RuntimeException('Phản hồi không hợp lệ.');
            }

            $stmt = db()->prepare(
                "SELECT i.*, g.class_id, g.registration_period_id, c.max_members,
                        p.group_start, p.group_end, p.status AS period_status
                 FROM group_invitations i
                 JOIN student_groups g ON g.id = i.group_id
                 JOIN classes c ON c.id = g.class_id
                 JOIN registration_periods p ON p.id = g.registration_period_id
                 WHERE i.id = ? AND i.invited_user_id = ? AND i.status = 'pending'
                 LIMIT 1"
            );
            $stmt->execute([$inviteId, (int) $user['id']]);
            $invite = $stmt->fetch();
            if (!$invite) {
                throw new RuntimeException('Lời mời không còn hợp lệ.');
            }

            if ($response === 'accepted') {
                if ($invite['period_status'] !== 'open' || !time_between($invite['group_start'], $invite['group_end'])) {
                    db()->prepare("UPDATE group_invitations SET status = 'expired', responded_at = NOW() WHERE id = ?")->execute([$inviteId]);
                    throw new RuntimeException('Lời mời đã hết hiệu lực vì ngoài thời gian tạo nhóm.');
                }
                if (student_group_for_context((int) $user['id'], (int) $invite['class_id'], (int) $invite['registration_period_id'])) {
                    throw new RuntimeException('Bạn đã thuộc một nhóm khác trong lớp và đợt này.');
                }
                if (group_member_count((int) $invite['group_id']) >= (int) $invite['max_members']) {
                    db()->prepare("UPDATE group_invitations SET status = 'expired', responded_at = NOW() WHERE id = ?")->execute([$inviteId]);
                    throw new RuntimeException('Nhóm đã đủ thành viên.');
                }
                db()->beginTransaction();
                db()->prepare("UPDATE group_invitations SET status = 'accepted', responded_at = NOW() WHERE id = ?")->execute([$inviteId]);
                db()->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')")
                    ->execute([(int) $invite['group_id'], (int) $user['id']]);
                db()->commit();
                flash('success', 'Bạn đã tham gia nhóm.');
            } else {
                db()->prepare("UPDATE group_invitations SET status = 'rejected', responded_at = NOW() WHERE id = ?")->execute([$inviteId]);
                flash('success', 'Bạn đã từ chối lời mời.');
            }
        }

        if ($action === 'leave_group') {
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $group = student_owned_group($groupId, (int) $user['id']);
            if (!$group) {
                throw new RuntimeException('Bạn chưa thuộc nhóm này.');
            }
            if (group_active_registration($groupId)) {
                throw new RuntimeException('Nhóm đã có đăng ký đề tài còn hiệu lực nên không thể rời/giải tán.');
            }

            $isLeader = is_group_leader($groupId, (int) $user['id']);
            if ($isLeader && group_member_count($groupId) > 1) {
                throw new RuntimeException('Trưởng nhóm cần chuyển quyền hoặc mời thành viên rời trước khi giải tán.');
            }

            if ($isLeader) {
                db()->prepare('DELETE FROM student_groups WHERE id = ?')->execute([$groupId]);
                flash('success', 'Đã giải tán nhóm.');
            } else {
                db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$groupId, (int) $user['id']]);
                flash('success', 'Bạn đã rời nhóm.');
            }
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $exception->getMessage());
    }

    redirect('student/group.php');
}

$groups = student_groups((int) $user['id']);

$contextsStmt = db()->prepare(
    "SELECT c.id AS class_id, c.name AS class_name, co.code AS course_code,
            p.id AS period_id, p.name AS period_name
     FROM class_students cs
     JOIN classes c ON c.id = cs.class_id
     JOIN courses co ON co.id = c.course_id
     JOIN registration_period_classes rpc ON rpc.class_id = c.id
     JOIN registration_periods p ON p.id = rpc.registration_period_id
     WHERE cs.student_id = ?
       AND NOT EXISTS (
            SELECT 1
            FROM group_members gm
            JOIN student_groups g ON g.id = gm.group_id
            WHERE gm.user_id = ? AND g.class_id = c.id AND g.registration_period_id = p.id
       )
     ORDER BY p.created_at DESC, c.name"
);
$contextsStmt->execute([(int) $user['id'], (int) $user['id']]);
$availableContexts = $contextsStmt->fetchAll();

$membersByGroup = [];
if ($groups) {
    $ids = array_map(static fn(array $group): int => (int) $group['id'], $groups);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT gm.group_id, u.name, u.email, u.user_code, gm.role
         FROM group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id IN ($placeholders)
         ORDER BY gm.role DESC, u.name"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $member) {
        $membersByGroup[(int) $member['group_id']][] = $member;
    }
}

$invitesStmt = db()->prepare(
    "SELECT i.*, g.name AS group_name, c.name AS class_name, co.code AS course_code, u.name AS invited_by_name
     FROM group_invitations i
     JOIN student_groups g ON g.id = i.group_id
     JOIN classes c ON c.id = g.class_id
     JOIN courses co ON co.id = c.course_id
     JOIN users u ON u.id = i.invited_by
     WHERE i.invited_user_id = ? AND i.status = 'pending'
     ORDER BY i.created_at DESC"
);
$invitesStmt->execute([(int) $user['id']]);
$invites = $invitesStmt->fetchAll();

$page_title = 'Nhóm của bạn';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Nhóm của bạn</h1>
        <p>Tạo nhóm, mời thành viên và theo dõi trạng thái đăng ký đề tài theo từng lớp.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('student/topics.php')) ?>">Đăng ký đề tài</a>
</section>

<?php if ($invites): ?>
    <section class="card-panel mb-4">
        <div class="panel-body">
            <h2 class="h4 fw-bold mb-3">Lời mời vào nhóm</h2>
            <div class="d-grid gap-2">
                <?php foreach ($invites as $invite): ?>
                    <div class="d-flex justify-content-between gap-3 flex-wrap align-items-center p-3 border rounded-2">
                        <span>
                            Nhóm <strong><?= e($invite['group_name']) ?></strong>
                            của lớp <?= e($invite['course_code'] . ' - ' . $invite['class_name']) ?> mời bạn tham gia.
                        </span>
                        <form class="d-flex gap-2" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond_invite">
                            <input type="hidden" name="invite_id" value="<?= e((string) $invite['id']) ?>">
                            <button class="btn btn-sm btn-success" name="response" value="accepted" type="submit">Chấp
                                nhận</button>
                            <button class="btn btn-sm btn-outline-danger" name="response" value="rejected" type="submit">Từ
                                chối</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($availableContexts): ?>
    <section class="card-panel mb-4">
        <div class="panel-body">
            <h2 class="h4 fw-bold mb-3">Tạo nhóm mới</h2>
            <form class="row g-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_group">
                <div class="col-md-5">
                    <label class="form-label">Tên nhóm</label>
                    <input class="form-control" name="name" placeholder="Ví dụ: Nhóm Web 01" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Lớp và đợt đăng ký</label>
                    <select class="form-select" name="context" required>
                        <?php foreach ($availableContexts as $context): ?>
                            <option value="<?= e($context['class_id'] . '|' . $context['period_id']) ?>">
                                <?= e($context['course_code'] . ' - ' . $context['class_name'] . ' · ' . $context['period_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Tạo nhóm</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="d-grid gap-4">
    <?php foreach ($groups as $group): ?>
        <?php
        $members = $membersByGroup[(int) $group['id']] ?? [];
        $registration = group_registration((int) $group['id']);
        $isLeader = is_group_leader((int) $group['id'], (int) $user['id']);
        ?>
        <article class="card-panel">
            <div class="panel-body">
                <div class="section-heading mb-3">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><?= e($group['name']) ?></h2>
                        <p class="mb-0 text-muted">
                            <?= e($group['course_code'] . ' - ' . $group['class_name']) ?> · <?= e($group['period_name']) ?>
                            · Mã tham gia: <?= e($group['join_code']) ?>
                        </p>
                    </div>
                    <span class="<?= e(badge_class($registration['status'] ?? $group['status'])) ?>">
                        <?= e($registration ? status_label($registration['status']) : status_label($group['status'])) ?>
                    </span>
                </div>

                <section class="grid-3 mb-4">
                    <article class="stat-card">
                        <span>Số lượng</span>
                        <strong><?= e((string) count($members)) ?>/<?= e((string) $group['max_members']) ?></strong>
                        <p class="text-muted mb-0">Tối thiểu cần <?= e((string) $group['min_members']) ?> thành viên.</p>
                    </article>
                    <article class="stat-card">
                        <span>Đề tài</span>
                        <strong><?= e($registration['topic_code'] ?? 'Chưa có') ?></strong>
                        <p class="text-muted mb-0"><?= e($registration['topic_title'] ?? 'Nhóm chưa đăng ký đề tài.') ?></p>
                    </article>
                    <article class="stat-card">
                        <span>Vai trò của em</span>
                        <strong><?= $isLeader ? 'Trưởng nhóm' : 'Thành viên' ?></strong>
                        <p class="text-muted mb-0">
                            <?= $isLeader ? 'Có quyền mời thành viên và đăng ký đề tài.' : 'Theo dõi trạng thái nhóm.' ?>
                        </p>
                    </article>
                </section>

                <div class="table-responsive mb-4">
                    <table class="table-clean role-mobile-table student-members-mobile-table">
                        <thead>
                            <tr>
                                <th>Mã SV</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?= e($member['user_code']) ?></td>
                                    <td><?= e($member['name']) ?></td>
                                    <td><?= e($member['email']) ?></td>
                                    <td><?= $member['role'] === 'leader' ? 'Trưởng nhóm' : 'Thành viên' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($registration && $registration['teacher_feedback']): ?>
                    <p class="text-muted"><strong>Phản hồi giảng viên:</strong> <?= e($registration['teacher_feedback']) ?></p>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($isLeader): ?>
                        <form class="d-flex gap-2 flex-wrap" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="invite">
                            <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                            <input class="form-control form-control-sm" name="keyword" placeholder="Email hoặc mã sinh viên"
                                required style="width: 260px">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Gửi lời mời</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn rời/giải tán nhóm?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="leave_group">
                        <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                        <button class="btn btn-sm btn-outline-danger"
                            type="submit"><?= $isLeader ? 'Giải tán nhóm' : 'Rời nhóm' ?></button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$groups): ?>
        <div class="card-panel">
            <div class="empty-state">Bạn chưa thuộc nhóm nào. Hãy tạo nhóm hoặc chấp nhận lời mời.</div>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>