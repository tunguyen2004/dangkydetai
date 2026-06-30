<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');

$user = current_user();

function random_join_code(): string
{
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_group') {
            if (student_group((int) $user['id'])) {
                throw new RuntimeException('Bạn đã thuộc một nhóm, không thể tạo nhóm mới.');
            }

            $classId = (int) ($_POST['class_id'] ?? 0);
            $stmt = db()->prepare(
                "SELECT c.*, s.group_start, s.group_end, s.status AS semester_status
                 FROM classes c
                 JOIN semesters s ON s.id = c.semester_id
                 JOIN class_students cs ON cs.class_id = c.id
                 WHERE c.id = ? AND cs.student_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$classId, (int) $user['id']]);
            $class = $stmt->fetch();

            if (!$class) {
                throw new RuntimeException('Bạn không thuộc lớp học phần này.');
            }
            if ((int) $class['allow_self_group'] !== 1) {
                throw new RuntimeException('Lớp này chưa cho phép sinh viên tự tạo nhóm.');
            }
            if ($class['semester_status'] !== 'open' || !today_between($class['group_start'], $class['group_end'])) {
                throw new RuntimeException('Hiện không nằm trong thời gian tạo nhóm.');
            }

            $count = db()->prepare('SELECT COUNT(*) FROM student_groups WHERE class_id = ?');
            $count->execute([$classId]);
            if ((int) $count->fetchColumn() >= (int) $class['max_groups']) {
                throw new RuntimeException('Lớp đã đạt số nhóm tối đa.');
            }

            db()->beginTransaction();
            $code = random_join_code();
            db()->prepare('INSERT INTO student_groups (class_id, name, join_code, created_by) VALUES (?, ?, ?, ?)')
                ->execute([$classId, trim((string) $_POST['name']), $code, (int) $user['id']]);
            $groupId = (int) db()->lastInsertId();
            db()->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')")
                ->execute([$groupId, (int) $user['id']]);
            db()->commit();

            log_activity('create_group', 'Tạo nhóm ' . (string) $_POST['name']);
            flash('success', 'Đã tạo nhóm. Mã tham gia: ' . $code);
        }

        if ($action === 'invite') {
            $group = student_group((int) $user['id']);
            if (!$group || !is_group_leader((int) $group['id'], (int) $user['id'])) {
                throw new RuntimeException('Chỉ trưởng nhóm mới được mời thành viên.');
            }
            if (in_array($group['status'], ['approved', 'locked'], true)) {
                throw new RuntimeException('Nhóm đã được duyệt đề tài nên không thể thêm thành viên.');
            }
            if (group_member_count((int) $group['id']) >= (int) $group['max_members']) {
                throw new RuntimeException('Nhóm đã đủ số lượng thành viên tối đa.');
            }

            $keyword = trim((string) ($_POST['keyword'] ?? ''));
            $stmt = db()->prepare("SELECT u.* FROM users u JOIN class_students cs ON cs.student_id = u.id WHERE cs.class_id = ? AND u.role = 'student' AND (u.email = ? OR u.student_code = ?) LIMIT 1");
            $stmt->execute([(int) $group['class_id'], $keyword, $keyword]);
            $student = $stmt->fetch();

            if (!$student) {
                throw new RuntimeException('Không tìm thấy sinh viên trong cùng lớp.');
            }
            if ((int) $student['id'] === (int) $user['id']) {
                throw new RuntimeException('Bạn đã là trưởng nhóm.');
            }
            if (student_group((int) $student['id'])) {
                throw new RuntimeException('Sinh viên này đã thuộc nhóm khác.');
            }

            db()->prepare('INSERT INTO group_invitations (group_id, invited_user_id, invited_by) VALUES (?, ?, ?)')
                ->execute([(int) $group['id'], (int) $student['id'], (int) $user['id']]);
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
                "SELECT i.*, g.class_id, c.max_members
                 FROM group_invitations i
                 JOIN student_groups g ON g.id = i.group_id
                 JOIN classes c ON c.id = g.class_id
                 WHERE i.id = ? AND i.invited_user_id = ? AND i.status = 'pending'
                 LIMIT 1"
            );
            $stmt->execute([$inviteId, (int) $user['id']]);
            $invite = $stmt->fetch();
            if (!$invite) {
                throw new RuntimeException('Lời mời không còn hợp lệ.');
            }

            if ($response === 'accepted') {
                if (student_group((int) $user['id'])) {
                    throw new RuntimeException('Bạn đã thuộc một nhóm khác.');
                }
                if (group_member_count((int) $invite['group_id']) >= (int) $invite['max_members']) {
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
            $group = student_group((int) $user['id']);
            if (!$group) {
                throw new RuntimeException('Bạn chưa thuộc nhóm nào.');
            }
            if (group_registration((int) $group['id']) && in_array((string) group_registration((int) $group['id'])['status'], ['pending', 'approved'], true)) {
                throw new RuntimeException('Nhóm đã đăng ký đề tài nên không thể rời/giải tán.');
            }

            $isLeader = is_group_leader((int) $group['id'], (int) $user['id']);
            if ($isLeader && group_member_count((int) $group['id']) > 1) {
                throw new RuntimeException('Trưởng nhóm cần chuyển quyền hoặc mời thành viên rời trước khi giải tán.');
            }

            if ($isLeader) {
                db()->prepare('DELETE FROM student_groups WHERE id = ?')->execute([(int) $group['id']]);
                flash('success', 'Đã giải tán nhóm.');
            } else {
                db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([(int) $group['id'], (int) $user['id']]);
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

$group = student_group((int) $user['id']);
$classesStmt = db()->prepare(
    "SELECT c.*, s.name AS semester_name
     FROM classes c
     JOIN semesters s ON s.id = c.semester_id
     JOIN class_students cs ON cs.class_id = c.id
     WHERE cs.student_id = ?
     ORDER BY c.name"
);
$classesStmt->execute([(int) $user['id']]);
$studentClasses = $classesStmt->fetchAll();

$members = [];
$registration = null;
if ($group) {
    $stmt = db()->prepare(
        "SELECT u.name, u.email, u.student_code, gm.role
         FROM group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?
         ORDER BY gm.role DESC, u.name"
    );
    $stmt->execute([(int) $group['id']]);
    $members = $stmt->fetchAll();
    $registration = group_registration((int) $group['id']);
}

$invitesStmt = db()->prepare(
    "SELECT i.*, g.name AS group_name, u.name AS invited_by_name
     FROM group_invitations i
     JOIN student_groups g ON g.id = i.group_id
     JOIN users u ON u.id = i.invited_by
     WHERE i.invited_user_id = ? AND i.status = 'pending'
     ORDER BY i.created_at DESC"
);
$invitesStmt->execute([(int) $user['id']]);
$invites = $invitesStmt->fetchAll();

$page_title = 'Nhóm của em';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Nhóm của em</h1>
        <p>Tạo nhóm, mời thành viên và theo dõi trạng thái đăng ký đề tài.</p>
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
                        <span>Nhóm <strong><?= e($invite['group_name']) ?></strong> mời bạn tham gia.</span>
                        <form class="d-flex gap-2" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond_invite">
                            <input type="hidden" name="invite_id" value="<?= e((string) $invite['id']) ?>">
                            <button class="btn btn-sm btn-success" name="response" value="accepted" type="submit">Chấp nhận</button>
                            <button class="btn btn-sm btn-outline-danger" name="response" value="rejected" type="submit">Từ chối</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!$group): ?>
    <section class="card-panel">
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
                    <label class="form-label">Lớp học phần</label>
                    <select class="form-select" name="class_id" required>
                        <?php foreach ($studentClasses as $class): ?>
                            <option value="<?= e((string) $class['id']) ?>"><?= e($class['name'] . ' · ' . $class['semester_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Tạo nhóm</button>
                </div>
            </form>
        </div>
    </section>
<?php else: ?>
    <section class="grid-3 mb-4">
        <article class="stat-card">
            <span>Tên nhóm</span>
            <strong><?= e($group['name']) ?></strong>
            <p class="text-muted mb-0">Mã tham gia: <?= e($group['join_code']) ?></p>
        </article>
        <article class="stat-card">
            <span>Số lượng</span>
            <strong><?= e((string) count($members)) ?>/<?= e((string) $group['max_members']) ?></strong>
            <p class="text-muted mb-0">Tối thiểu cần <?= e((string) $group['min_members']) ?> thành viên.</p>
        </article>
        <article class="stat-card">
            <span>Đăng ký</span>
            <strong><?= e($registration ? status_label($registration['status']) : 'Chưa có') ?></strong>
            <p class="text-muted mb-0"><?= e($registration['topic_title'] ?? 'Nhóm chưa đăng ký đề tài.') ?></p>
        </article>
    </section>

    <section class="card-panel mb-4">
        <div class="panel-body">
            <h2 class="h4 fw-bold mb-3">Thành viên</h2>
            <div class="table-responsive">
                <table class="table-clean">
                    <thead><tr><th>Mã SV</th><th>Họ tên</th><th>Email</th><th>Vai trò</th></tr></thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= e($member['student_code']) ?></td>
                                <td><?= e($member['name']) ?></td>
                                <td><?= e($member['email']) ?></td>
                                <td><?= $member['role'] === 'leader' ? 'Trưởng nhóm' : 'Thành viên' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php if (is_group_leader((int) $group['id'], (int) $user['id'])): ?>
        <section class="card-panel mb-4">
            <div class="panel-body">
                <h2 class="h4 fw-bold mb-3">Mời thành viên</h2>
                <form class="row g-3" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="invite">
                    <div class="col-md-8">
                        <label class="form-label">Email hoặc mã sinh viên</label>
                        <input class="form-control" name="keyword" placeholder="sv06@k73.test hoặc SV006" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">Gửi lời mời</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn rời/giải tán nhóm?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="leave_group">
        <button class="btn btn-outline-danger" type="submit"><?= is_group_leader((int) $group['id'], (int) $user['id']) ? 'Giải tán nhóm' : 'Rời nhóm' ?></button>
    </form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
