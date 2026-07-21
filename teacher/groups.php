<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

$user = current_user();

function teacher_context(string $context, int $teacherId): ?array
{
    [$classId, $periodId] = array_pad(explode('|', $context), 2, 0);
    $stmt = db()->prepare(
        'SELECT c.*, p.id AS period_id, p.name AS period_name, p.group_start, p.group_end, p.status AS period_status
         FROM classes c
         JOIN registration_period_classes rpc ON rpc.class_id = c.id
         JOIN registration_periods p ON p.id = rpc.registration_period_id
         WHERE c.id = ? AND p.id = ? AND c.teacher_id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $classId, (int) $periodId, $teacherId]);
    $contextRow = $stmt->fetch();

    return $contextRow ?: null;
}

function teacher_owned_group(int $groupId, int $teacherId): ?array
{
    $stmt = db()->prepare(
        'SELECT g.*, c.teacher_id, c.max_members, p.group_start, p.group_end, p.status AS period_status
         FROM student_groups g
         JOIN classes c ON c.id = g.class_id
         JOIN registration_periods p ON p.id = g.registration_period_id
         WHERE g.id = ? AND c.teacher_id = ?
         LIMIT 1'
    );
    $stmt->execute([$groupId, $teacherId]);
    $group = $stmt->fetch();

    return $group ?: null;
}

function student_in_class(int $studentId, int $classId): ?array
{
    $stmt = db()->prepare(
        "SELECT u.*
         FROM users u
         JOIN class_students cs ON cs.student_id = u.id
         WHERE u.id = ? AND cs.class_id = ? AND u.role = 'student' AND u.is_locked = 0
         LIMIT 1"
    );
    $stmt->execute([$studentId, $classId]);
    $student = $stmt->fetch();

    return $student ?: null;
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_group') {
            $context = teacher_context((string) ($_POST['context'] ?? ''), (int) $user['id']);
            $leaderId = (int) ($_POST['leader_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));

            if (!$context) {
                throw new RuntimeException('Lớp hoặc đợt đăng ký không hợp lệ.');
            }
            if ($name === '') {
                throw new RuntimeException('Vui lòng nhập tên nhóm.');
            }
            if ($context['period_status'] !== 'open' || !time_between($context['group_start'], $context['group_end'])) {
                throw new RuntimeException('Hiện không nằm trong thời gian tạo nhóm của đợt đăng ký.');
            }

            $count = db()->prepare('SELECT COUNT(*) FROM student_groups WHERE class_id = ? AND registration_period_id = ?');
            $count->execute([(int) $context['id'], (int) $context['period_id']]);
            if ((int) $count->fetchColumn() >= (int) $context['max_groups']) {
                throw new RuntimeException('Lớp đã đạt số nhóm tối đa trong đợt này.');
            }

            $leader = student_in_class($leaderId, (int) $context['id']);
            if (!$leader) {
                throw new RuntimeException('Trưởng nhóm phải là sinh viên thuộc lớp này.');
            }
            if (student_group_for_context($leaderId, (int) $context['id'], (int) $context['period_id'])) {
                throw new RuntimeException('Sinh viên được chọn đã thuộc nhóm khác trong lớp và đợt này.');
            }

            db()->beginTransaction();
            $code = random_join_code();
            db()->prepare('INSERT INTO student_groups (class_id, registration_period_id, name, join_code, created_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $context['id'], (int) $context['period_id'], $name, $code, (int) $user['id']]);
            $groupId = (int) db()->lastInsertId();
            db()->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')")
                ->execute([$groupId, $leaderId]);
            db()->commit();

            log_activity('teacher_create_group', 'Giảng viên tạo nhóm ' . $name);
            flash('success', 'Đã tạo nhóm và gán trưởng nhóm. Mã tham gia: ' . $code);
        }

        if ($action === 'add_member') {
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $group = teacher_owned_group($groupId, (int) $user['id']);

            if (!$group) {
                throw new RuntimeException('Bạn chỉ được sửa nhóm trong lớp mình phụ trách.');
            }
            if ($group['period_status'] !== 'open' || !time_between($group['group_start'], $group['group_end'])) {
                throw new RuntimeException('Hiện không nằm trong thời gian tạo nhóm của đợt đăng ký.');
            }
            if ($group['status'] === 'locked') {
                throw new RuntimeException('Nhóm đã khóa nên không thể thêm thành viên.');
            }

            $registration = group_active_registration($groupId);
            if ($registration) {
                throw new RuntimeException('Nhóm đã có đăng ký đề tài còn hiệu lực nên không thể thêm thành viên.');
            }
            if (group_member_count($groupId) >= (int) $group['max_members']) {
                throw new RuntimeException('Nhóm đã đủ số lượng thành viên tối đa.');
            }

            $student = student_in_class($studentId, (int) $group['class_id']);
            if (!$student) {
                throw new RuntimeException('Sinh viên phải thuộc cùng lớp với nhóm.');
            }
            if (student_group_for_context($studentId, (int) $group['class_id'], (int) $group['registration_period_id'])) {
                throw new RuntimeException('Sinh viên này đã thuộc nhóm khác trong lớp và đợt này.');
            }

            db()->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')")
                ->execute([$groupId, $studentId]);

            log_activity('teacher_add_group_member', 'Thêm ' . $student['email'] . ' vào nhóm ' . $group['name']);
            flash('success', 'Đã thêm sinh viên vào nhóm.');
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $exception->getMessage());
    }

    redirect('teacher/groups.php');
}

$contextsStmt = db()->prepare(
    'SELECT c.id AS class_id, c.name AS class_name, co.code AS course_code,
            p.id AS period_id, p.name AS period_name, p.status AS period_status
     FROM classes c
     JOIN courses co ON co.id = c.course_id
     JOIN registration_period_classes rpc ON rpc.class_id = c.id
     JOIN registration_periods p ON p.id = rpc.registration_period_id
     WHERE c.teacher_id = ?
     ORDER BY p.created_at DESC, c.name'
);
$contextsStmt->execute([(int) $user['id']]);
$teacherContexts = $contextsStmt->fetchAll();

$studentsStmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.user_code, c.id AS class_id, c.name AS class_name
     FROM users u
     JOIN class_students cs ON cs.student_id = u.id
     JOIN classes c ON c.id = cs.class_id
     WHERE c.teacher_id = ? AND u.role = 'student' AND u.is_locked = 0
     ORDER BY c.name, u.name"
);
$studentsStmt->execute([(int) $user['id']]);
$students = $studentsStmt->fetchAll();
$studentsByClass = [];
foreach ($students as $student) {
    $studentsByClass[(int) $student['class_id']][] = $student;
}

$stmt = db()->prepare(
    "SELECT g.*, c.name AS class_name, c.min_members, c.max_members, co.code AS course_code,
            p.name AS period_name,
            creator.name AS creator_name, creator.role AS creator_role,
            (
                SELECT GROUP_CONCAT(CONCAT(u.user_code, ' - ', u.name, IF(gm.role = 'leader', ' (Trưởng nhóm)', '')) ORDER BY gm.role DESC, u.name SEPARATOR '\n')
                FROM group_members gm
                JOIN users u ON u.id = gm.user_id
                WHERE gm.group_id = g.id
            ) AS members,
            r.status AS registration_status, t.title AS topic_title
     FROM student_groups g
     JOIN classes c ON c.id = g.class_id
     JOIN courses co ON co.id = c.course_id
     JOIN registration_periods p ON p.id = g.registration_period_id
     JOIN users creator ON creator.id = g.created_by
     LEFT JOIN topic_registrations r ON r.id = (
        SELECT r2.id FROM topic_registrations r2 WHERE r2.group_id = g.id ORDER BY r2.id DESC LIMIT 1
     )
     LEFT JOIN topic_classes tc ON tc.id = r.topic_class_id
     LEFT JOIN topics t ON t.id = tc.topic_id
     WHERE c.teacher_id = ?
     ORDER BY g.created_at DESC"
);
$stmt->execute([(int) $user['id']]);
$groups = $stmt->fetchAll();

$page_title = 'Danh sách nhóm';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Danh sách nhóm</h1>
        <p>Theo dõi thành viên, số lượng và đề tài của từng nhóm trong lớp phụ trách.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Giảng viên tạo nhóm</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_group">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Tên nhóm</label>
                <input class="form-control" name="name" placeholder="Ví dụ: Nhóm 01" required>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Lớp và đợt đăng ký</label>
                <select class="form-select" name="context" required>
                    <?php foreach ($teacherContexts as $context): ?>
                        <option value="<?= e($context['class_id'] . '|' . $context['period_id']) ?>">
                            <?= e($context['course_code'] . ' - ' . $context['class_name'] . ' · ' . $context['period_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-8">
                <label class="form-label">Trưởng nhóm</label>
                <select class="form-select" name="leader_id" required>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= e((string) $student['id']) ?>">
                            <?= e($student['class_name'] . ' · ' . $student['user_code'] . ' - ' . $student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" <?= (!$teacherContexts || !$students) ? 'disabled' : '' ?>>Tạo nhóm</button>
            </div>
        </form>
        <?php if (!$teacherContexts): ?>
            <p class="text-muted mt-3 mb-0">Bạn chưa có lớp nào được gán đợt đăng ký.</p>
        <?php elseif (!$students): ?>
            <p class="text-muted mt-3 mb-0">Lớp của bạn chưa có sinh viên.</p>
        <?php else: ?>
            <p class="text-muted mt-3 mb-0">Hệ thống sẽ kiểm tra trưởng nhóm có thuộc đúng lớp và chưa có nhóm trong đợt này.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean role-mobile-table teacher-groups-mobile-table">
            <thead>
                <tr>
                    <th>Nhóm</th>
                    <th>Lớp / đợt</th>
                    <th>Thành viên</th>
                    <th>Đề tài</th>
                    <th>Trạng thái</th>
                    <th>Thêm thành viên</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <?php
                    $canAddMember = $group['status'] !== 'locked'
                        && !in_array((string) ($group['registration_status'] ?? ''), ['pending', 'approved'], true)
                        && group_member_count((int) $group['id']) < (int) $group['max_members']
                        && !empty($studentsByClass[(int) $group['class_id']]);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($group['name']) ?></strong><br>
                            <span class="text-muted">Mã tham gia: <?= e($group['join_code']) ?></span><br>
                            <small class="text-muted">Tạo bởi: <?= e($group['creator_name']) ?> (<?= e(role_label($group['creator_role'])) ?>)</small>
                        </td>
                        <td><?= e($group['course_code'] . ' - ' . $group['class_name']) ?><br><span class="text-muted"><?= e($group['period_name']) ?></span></td>
                        <td>
                            <div style="white-space: pre-line"><?= e($group['members'] ?: 'Chưa có thành viên') ?></div>
                            <small class="text-muted"><?= e((string) group_member_count((int) $group['id'])) ?>/<?= e((string) $group['max_members']) ?> thành viên</small>
                        </td>
                        <td><?= e($group['topic_title'] ?: 'Chưa đăng ký') ?></td>
                        <td>
                            <?php if ($group['registration_status']): ?>
                                <span class="<?= e(badge_class($group['registration_status'])) ?>"><?= e(status_label($group['registration_status'])) ?></span>
                            <?php else: ?>
                                <span class="<?= e(badge_class($group['status'])) ?>"><?= e(status_label($group['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canAddMember): ?>
                                <form class="d-grid gap-2" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                                    <select class="form-select form-select-sm" name="student_id" required>
                                        <?php foreach ($studentsByClass[(int) $group['class_id']] ?? [] as $student): ?>
                                            <option value="<?= e((string) $student['id']) ?>">
                                                <?= e($student['user_code'] . ' - ' . $student['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Thêm</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Không thể thêm</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$groups): ?>
                    <tr><td colspan="6" class="empty-state">Chưa có nhóm nào trong lớp bạn phụ trách.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
