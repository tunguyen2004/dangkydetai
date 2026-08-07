<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['teacher', 'student']);

$user = current_user();
$role = (string) ($user['role'] ?? '');

if ($role === 'teacher') {
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
    
    return 'user/group.php' . ($query ? '?' . http_build_query($query) : '');
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
    <?php require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($role === 'student') {
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
    
            if ($action === 'transfer_leadership') {
                $groupId = (int) ($_POST['group_id'] ?? 0);
                $newLeaderId = (int) ($_POST['new_leader_id'] ?? 0);
                $group = student_owned_group($groupId, (int) $user['id']);
    
                if (!$group || !is_group_leader($groupId, (int) $user['id'])) {
                    throw new RuntimeException('Chỉ trưởng nhóm hiện tại mới được chuyển quyền trưởng nhóm.');
                }
                if ($newLeaderId <= 0 || $newLeaderId === (int) $user['id']) {
                    throw new RuntimeException('Vui lòng chọn một thành viên khác để nhận quyền trưởng nhóm.');
                }
                if ($group['status'] === 'locked' || group_active_registration($groupId)) {
                    throw new RuntimeException('Nhóm đã đăng ký hoặc đã khóa nên không thể chuyển quyền trưởng nhóm.');
                }
    
                db()->beginTransaction();
                // Lock all members so the handover always leaves exactly one leader.
                $membersStmt = db()->prepare('SELECT user_id, role FROM group_members WHERE group_id = ? FOR UPDATE');
                $membersStmt->execute([$groupId]);
                $groupMembers = $membersStmt->fetchAll();
    
                $currentLeaderFound = false;
                $newLeaderIsMember = false;
                $leaderCount = 0;
                foreach ($groupMembers as $member) {
                    if ($member['role'] === 'leader') {
                        $leaderCount++;
                    }
                    if ((int) $member['user_id'] === (int) $user['id'] && $member['role'] === 'leader') {
                        $currentLeaderFound = true;
                    }
                    if ((int) $member['user_id'] === $newLeaderId && $member['role'] === 'member') {
                        $newLeaderIsMember = true;
                    }
                }
                if ($leaderCount !== 1) {
                    throw new RuntimeException('Dữ liệu nhóm cần có đúng một trưởng nhóm trước khi chuyển quyền.');
                }
                if (!$currentLeaderFound || !$newLeaderIsMember) {
                    throw new RuntimeException('Người được chọn phải là thành viên hiện tại của nhóm.');
                }
    
                db()->prepare(
                    "UPDATE group_members
                     SET role = CASE
                        WHEN user_id = ? THEN 'leader'
                        WHEN user_id = ? THEN 'member'
                        ELSE role
                     END
                     WHERE group_id = ? AND user_id IN (?, ?)"
                )->execute([$newLeaderId, (int) $user['id'], $groupId, $newLeaderId, (int) $user['id']]);
                db()->commit();
    
                log_activity('transfer_group_leadership', 'Chuyển quyền trưởng nhóm #' . $groupId . ' cho sinh viên #' . $newLeaderId);
                flash('success', 'Đã chuyển quyền trưởng nhóm thành công.');
            }
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', $exception->getMessage());
        }
    
        redirect('user/group.php');
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
            "SELECT gm.group_id, gm.user_id, u.name, u.email, u.user_code, gm.role
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
        <a class="btn btn-primary" href="<?= e(url('user/topics.php')) ?>">Đăng ký đề tài</a>
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
            $hasActiveRegistration = $registration && in_array((string) $registration['status'], ['pending', 'approved'], true);
            $canTransferLeadership = $isLeader && !$hasActiveRegistration && $group['status'] !== 'locked' && count($members) > 1;
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
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?= e($member['user_code']) ?></td>
                                        <td><?= e($member['name']) ?></td>
                                        <td><?= e($member['email']) ?></td>
                                        <td><?= $member['role'] === 'leader' ? 'Trưởng nhóm' : 'Thành viên' ?></td>
                                        <td>
                                            <?php if ($canTransferLeadership && $member['role'] === 'member'): ?>
                                                <form method="post" data-async-form onsubmit="return confirm('Chuyển quyền trưởng nhóm cho thành viên này?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="transfer_leadership">
                                                    <input type="hidden" name="group_id" value="<?= e((string) $group['id']) ?>">
                                                    <input type="hidden" name="new_leader_id" value="<?= e((string) $member['user_id']) ?>">
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Chuyển quyền</button>
                                                </form>
                                            <?php elseif ($member['role'] === 'leader'): ?>
                                                <span class="text-muted">Trưởng nhóm</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
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
    <?php require_once __DIR__ . '/../includes/footer.php';
    exit;
}
