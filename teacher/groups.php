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

function teacher_groups_list_path(array $overrides = []): string
{
    $allowed = ['q', 'class_id', 'period_id', 'registration_status', 'per_page', 'page', 'create'];
    $query = [];

    foreach ($allowed as $key) {
        $value = array_key_exists($key, $overrides) ? $overrides[$key] : ($_GET[$key] ?? null);
        if ($value !== null && $value !== '') {
            $query[$key] = (string) $value;
        }
    }

    return 'teacher/groups.php' . ($query ? '?' . http_build_query($query) : '');
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

    redirect(teacher_groups_list_path(['create' => null]));
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

$teacherClassOptions = [];
$teacherPeriodOptions = [];
foreach ($teacherContexts as $context) {
    $teacherClassOptions[(int) $context['class_id']] = [
        'id' => (int) $context['class_id'],
        'name' => $context['class_name'],
        'course_code' => $context['course_code'],
    ];
    $teacherPeriodOptions[(int) $context['period_id']] = [
        'id' => (int) $context['period_id'],
        'name' => $context['period_name'],
        'status' => $context['period_status'],
    ];
}

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

$memberContextStmt = db()->prepare(
    'SELECT gm.user_id, g.class_id, g.registration_period_id
     FROM group_members gm
     JOIN student_groups g ON g.id = gm.group_id
     JOIN classes c ON c.id = g.class_id
     WHERE c.teacher_id = ?'
);
$memberContextStmt->execute([(int) $user['id']]);
$studentsInGroupContext = [];
foreach ($memberContextStmt->fetchAll() as $membership) {
    $key = (int) $membership['user_id'] . '|' . (int) $membership['class_id'] . '|' . (int) $membership['registration_period_id'];
    $studentsInGroupContext[$key] = true;
}

$groupSearch = trim((string) ($_GET['q'] ?? ''));
$selectedClassId = max(0, (int) ($_GET['class_id'] ?? 0));
$selectedPeriodId = max(0, (int) ($_GET['period_id'] ?? 0));
$selectedRegistrationStatus = (string) ($_GET['registration_status'] ?? '');
$registrationStatuses = ['unregistered', 'pending', 'approved', 'rejected', 'cancelled', 'revoked'];
if (!in_array($selectedRegistrationStatus, $registrationStatuses, true)) {
    $selectedRegistrationStatus = '';
}
if ($selectedClassId > 0 && !isset($teacherClassOptions[$selectedClassId])) {
    $selectedClassId = 0;
}
if ($selectedPeriodId > 0 && !isset($teacherPeriodOptions[$selectedPeriodId])) {
    $selectedPeriodId = 0;
}

$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20], true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$groupFilterConditions = [];
$groupParams = [(int) $user['id']];

if ($groupSearch !== '') {
    $keyword = '%' . $groupSearch . '%';
    $groupFilterConditions[] = "(g.name LIKE ? OR g.join_code LIKE ? OR EXISTS (
        SELECT 1
        FROM group_members gm_filter
        JOIN users u_filter ON u_filter.id = gm_filter.user_id
        WHERE gm_filter.group_id = g.id
          AND (u_filter.name LIKE ? OR u_filter.email LIKE ? OR u_filter.user_code LIKE ?)
    ))";
    array_push($groupParams, $keyword, $keyword, $keyword, $keyword, $keyword);
}
if ($selectedClassId > 0) {
    $groupFilterConditions[] = 'g.class_id = ?';
    $groupParams[] = $selectedClassId;
}
if ($selectedPeriodId > 0) {
    $groupFilterConditions[] = 'g.registration_period_id = ?';
    $groupParams[] = $selectedPeriodId;
}
if ($selectedRegistrationStatus === 'unregistered') {
    $groupFilterConditions[] = 'NOT EXISTS (SELECT 1 FROM topic_registrations r_filter WHERE r_filter.group_id = g.id)';
} elseif ($selectedRegistrationStatus !== '') {
    $groupFilterConditions[] = "COALESCE((
        SELECT r_filter.status FROM topic_registrations r_filter
        WHERE r_filter.group_id = g.id ORDER BY r_filter.id DESC LIMIT 1
    ), '') = ?";
    $groupParams[] = $selectedRegistrationStatus;
}

$groupFilterSql = $groupFilterConditions ? ' AND ' . implode(' AND ', $groupFilterConditions) : '';
$groupCountStmt = db()->prepare(
    'SELECT COUNT(*)
     FROM student_groups g
     JOIN classes c ON c.id = g.class_id
     WHERE c.teacher_id = ?' . $groupFilterSql
);
$groupCountStmt->execute($groupParams);
$totalGroups = (int) $groupCountStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalGroups / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$visibleStart = $totalGroups === 0 ? 0 : $offset + 1;
$visibleEnd = min($offset + $perPage, $totalGroups);

$stmt = db()->prepare(
    "SELECT g.*, c.name AS class_name, c.min_members, c.max_members, co.code AS course_code,
            p.name AS period_name,
            creator.name AS creator_name, creator.role AS creator_role,
            (SELECT COUNT(*) FROM group_members gm_count WHERE gm_count.group_id = g.id) AS member_count,
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
     WHERE c.teacher_id = ?" . $groupFilterSql . "
     ORDER BY g.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($groupParams);
$groups = $stmt->fetchAll();

$page_title = 'Danh sách nhóm';
$openTeacherGroupEditor = (int) ($_GET['create'] ?? 0) === 1;
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Danh sách nhóm</h1>
        <p>Theo dõi thành viên, số lượng và đề tài của từng nhóm trong lớp phụ trách.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url(teacher_groups_list_path(['create' => 1]))) ?>">Tạo nhóm</a>
</section>

<dialog class="app-modal teacher-group-editor-modal" data-editor-modal <?= $openTeacherGroupEditor ? 'data-open-on-load="1"' : '' ?>>
<section class="teacher-group-editor-modal-panel">
    <div class="panel-body">
        <div class="app-modal-header">
            <h2 class="h4 fw-bold mb-0">Giảng viên tạo nhóm</h2>
            <a class="app-modal-close" href="<?= e(url(teacher_groups_list_path(['create' => null]))) ?>" aria-label="Đóng">&times;</a>
        </div>
        <form class="row g-3" method="post" data-async-form data-async-id="teacher-group-create" data-teacher-group-create>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_group">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Tên nhóm</label>
                <input class="form-control" name="name" placeholder="Ví dụ: Nhóm 01" required>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Lớp và đợt đăng ký</label>
                <select class="form-select" id="teacher-group-context" name="context" data-teacher-group-context required>
                    <?php foreach ($teacherContexts as $context): ?>
                        <option value="<?= e($context['class_id'] . '|' . $context['period_id']) ?>" data-class-id="<?= e((string) $context['class_id']) ?>">
                            <?= e($context['course_code'] . ' - ' . $context['class_name'] . ' · ' . $context['period_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-8">
                <label class="form-label">Trưởng nhóm</label>
                <select class="form-select" id="teacher-group-leader" name="leader_id" data-teacher-group-leader required>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= e((string) $student['id']) ?>" data-class-id="<?= e((string) $student['class_id']) ?>">
                            <?= e($student['class_name'] . ' · ' . $student['user_code'] . ' - ' . $student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" data-teacher-group-submit <?= (!$teacherContexts || !$students) ? 'disabled' : '' ?>>Tạo nhóm</button>
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
</dialog>

<section class="card-panel teacher-groups-filter-card">
    <div class="panel-body">
        <form class="teacher-groups-filter-form" method="get" data-auto-filter-form>
            <div>
                <label class="form-label" for="teacher-group-search">Tìm nhóm</label>
                <input class="form-control" id="teacher-group-search" name="q" value="<?= e($groupSearch) ?>" placeholder="Tên nhóm, mã tham gia, sinh viên">
            </div>
            <div>
                <label class="form-label" for="teacher-group-class">Lớp học phần</label>
                <select class="form-select" id="teacher-group-class" name="class_id">
                    <option value="">Tất cả lớp</option>
                    <?php foreach ($teacherClassOptions as $classOption): ?>
                        <option value="<?= e((string) $classOption['id']) ?>" <?= $selectedClassId === $classOption['id'] ? 'selected' : '' ?>>
                            <?= e($classOption['course_code'] . ' - ' . $classOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="teacher-group-period">Đợt đăng ký</label>
                <select class="form-select" id="teacher-group-period" name="period_id">
                    <option value="">Tất cả đợt</option>
                    <?php foreach ($teacherPeriodOptions as $periodOption): ?>
                        <option value="<?= e((string) $periodOption['id']) ?>" <?= $selectedPeriodId === $periodOption['id'] ? 'selected' : '' ?>>
                            <?= e($periodOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="teacher-group-status">Trạng thái đăng ký</label>
                <select class="form-select" id="teacher-group-status" name="registration_status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="unregistered" <?= $selectedRegistrationStatus === 'unregistered' ? 'selected' : '' ?>>Chưa đăng ký</option>
                    <?php foreach (array_slice($registrationStatuses, 1) as $status): ?>
                        <option value="<?= e($status) ?>" <?= $selectedRegistrationStatus === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="teacher-group-per-page">Hiển thị</label>
                <select class="form-select" id="teacher-group-per-page" name="per_page">
                    <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 / trang</option>
                    <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 / trang</option>
                </select>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <div class="teacher-groups-toolbar">
            <strong>Danh sách nhóm</strong>
            <span><?= e((string) $visibleStart) ?>-<?= e((string) $visibleEnd) ?> / <?= e((string) $totalGroups) ?> nhóm</span>
        </div>
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
                    $availableStudents = array_values(array_filter(
                        $studentsByClass[(int) $group['class_id']] ?? [],
                        static function (array $student) use ($group, $studentsInGroupContext): bool {
                            $key = (int) $student['id'] . '|' . (int) $group['class_id'] . '|' . (int) $group['registration_period_id'];

                            return !isset($studentsInGroupContext[$key]);
                        }
                    ));
                    $canAddMember = $group['status'] !== 'locked'
                        && !in_array((string) ($group['registration_status'] ?? ''), ['pending', 'approved'], true)
                        && (int) $group['member_count'] < (int) $group['max_members']
                        && !empty($availableStudents);
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
                            <small class="text-muted"><?= e((string) $group['member_count']) ?>/<?= e((string) $group['max_members']) ?> thành viên</small>
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
                                <form class="d-grid gap-2" method="post" data-async-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                                    <select class="form-select form-select-sm" name="student_id" required>
                                        <?php foreach ($availableStudents as $student): ?>
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
        <?php if ($totalPages > 1): ?>
            <nav class="teacher-groups-pagination" aria-label="Phân trang danh sách nhóm">
                <span>Trang <?= e((string) $page) ?> / <?= e((string) $totalPages) ?></span>
                <div class="teacher-groups-pagination-links">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url(teacher_groups_list_path(['page' => $page - 1]))) ?>">Trước</a>
                    <?php endif; ?>
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="btn btn-sm <?= $pageNumber === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(url(teacher_groups_list_path(['page' => $pageNumber]))) ?>">
                            <?= e((string) $pageNumber) ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url(teacher_groups_list_path(['page' => $page + 1]))) ?>">Sau</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
