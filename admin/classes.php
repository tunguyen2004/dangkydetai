<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function class_payload(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $minMembers = max(1, (int) ($_POST['min_members'] ?? 0));
    $maxMembers = max(1, (int) ($_POST['max_members'] ?? 0));
    $maxGroups = max(1, (int) ($_POST['max_groups'] ?? 0));

    if ($name === '' || $courseId <= 0 || $teacherId <= 0) {
        throw new RuntimeException('Vui lòng nhập đủ tên lớp, học phần và giảng viên phụ trách.');
    }
    if ($minMembers > $maxMembers) {
        throw new RuntimeException('Số thành viên tối thiểu không được lớn hơn số thành viên tối đa.');
    }

    $courseStmt = db()->prepare('SELECT COUNT(*) FROM courses WHERE id = ?');
    $courseStmt->execute([$courseId]);
    if ((int) $courseStmt->fetchColumn() === 0) {
        throw new RuntimeException('Học phần không hợp lệ.');
    }

    $teacherStmt = db()->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'teacher' AND is_locked = 0");
    $teacherStmt->execute([$teacherId]);
    if ((int) $teacherStmt->fetchColumn() === 0) {
        throw new RuntimeException('Giảng viên phụ trách không hợp lệ hoặc đã bị khóa.');
    }

    return [$courseId, $teacherId, $name, $minMembers, $maxMembers, $maxGroups, isset($_POST['allow_self_group']) ? 1 : 0];
}

function class_has_runtime_data(int $classId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM student_groups WHERE class_id = ?');
    $stmt->execute([$classId]);

    return (int) $stmt->fetchColumn() > 0;
}

function class_group_stats(int $classId): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS group_count,
                MIN(member_count) AS min_member_count,
                MAX(member_count) AS max_member_count
         FROM (
             SELECT g.id, COUNT(gm.user_id) AS member_count
             FROM student_groups g
             LEFT JOIN group_members gm ON gm.group_id = g.id
             WHERE g.class_id = ?
             GROUP BY g.id
         ) AS group_stats'
    );
    $stmt->execute([$classId]);

    return $stmt->fetch() ?: ['group_count' => 0, 'min_member_count' => null, 'max_member_count' => null];
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            [$courseId, $teacherId, $name, $minMembers, $maxMembers, $maxGroups, $allowSelfGroup] = class_payload();
            $stmt = db()->prepare(
                'INSERT INTO classes (course_id, teacher_id, name, min_members, max_members, max_groups, allow_self_group)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$courseId, $teacherId, $name, $minMembers, $maxMembers, $maxGroups, $allowSelfGroup]);
            log_activity('create_class', 'Tạo lớp ' . $name);
            flash('success', 'Đã tạo lớp học phần.');
        }

        if ($action === 'update') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            if ($classId <= 0) {
                throw new RuntimeException('Lớp học phần không hợp lệ.');
            }

            $existingStmt = db()->prepare('SELECT * FROM classes WHERE id = ? LIMIT 1');
            $existingStmt->execute([$classId]);
            $existingClass = $existingStmt->fetch();
            if (!$existingClass) {
                throw new RuntimeException('Không tìm thấy lớp học phần.');
            }

            [$courseId, $teacherId, $name, $minMembers, $maxMembers, $maxGroups, $allowSelfGroup] = class_payload();
            $groupStats = class_group_stats($classId);
            $groupCount = (int) $groupStats['group_count'];

            if ($groupCount > 0 && $courseId !== (int) $existingClass['course_id']) {
                throw new RuntimeException('Không thể đổi học phần gốc khi lớp đã phát sinh nhóm.');
            }
            if ($groupCount > $maxGroups) {
                throw new RuntimeException('Số nhóm tối đa mới không được nhỏ hơn số nhóm đã tồn tại.');
            }
            if ($groupCount > 0
                && ($minMembers > (int) $groupStats['min_member_count']
                    || $maxMembers < (int) $groupStats['max_member_count'])) {
                throw new RuntimeException('Quy định số thành viên mới sẽ làm một hoặc nhiều nhóm hiện có không còn hợp lệ.');
            }

            db()->prepare(
                'UPDATE classes
                 SET course_id = ?, teacher_id = ?, name = ?, min_members = ?, max_members = ?, max_groups = ?, allow_self_group = ?
                 WHERE id = ?'
            )->execute([$courseId, $teacherId, $name, $minMembers, $maxMembers, $maxGroups, $allowSelfGroup, $classId]);
            log_activity('update_class', 'Cập nhật lớp #' . $classId . ' - ' . $name);
            flash('success', 'Đã cập nhật lớp học phần.');
        }

        if ($action === 'delete') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            if ($classId <= 0) {
                throw new RuntimeException('Lớp học phần không hợp lệ.');
            }
            if (class_has_runtime_data($classId)) {
                throw new RuntimeException('Lớp đã phát sinh nhóm hoặc đăng ký đề tài nên không thể xóa để bảo toàn lịch sử.');
            }

            $nameStmt = db()->prepare('SELECT name FROM classes WHERE id = ?');
            $nameStmt->execute([$classId]);
            $className = $nameStmt->fetchColumn();
            if ($className === false) {
                throw new RuntimeException('Không tìm thấy lớp học phần.');
            }

            db()->prepare('DELETE FROM classes WHERE id = ?')->execute([$classId]);
            log_activity('delete_class', 'Xóa lớp #' . $classId . ' - ' . $className);
            flash('success', 'Đã xóa lớp học phần chưa phát sinh dữ liệu.');
        }

        if ($action === 'add_students') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $studentIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($_POST['student_ids'] ?? [])),
                static fn (int $studentId): bool => $studentId > 0
            )));

            $classStmt = db()->prepare('SELECT COUNT(*) FROM classes WHERE id = ?');
            $classStmt->execute([$classId]);
            if ((int) $classStmt->fetchColumn() === 0) {
                throw new RuntimeException('Lớp học phần không hợp lệ.');
            }
            if (!$studentIds) {
                throw new RuntimeException('Vui lòng chọn ít nhất một sinh viên.');
            }

            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = db()->prepare(
                "INSERT IGNORE INTO class_students (class_id, student_id)
                 SELECT ?, id
                 FROM users
                 WHERE role = 'student' AND is_locked = 0 AND id IN ($placeholders)"
            );
            $stmt->execute(array_merge([$classId], $studentIds));
            $insertedCount = $stmt->rowCount();

            if ($insertedCount > 0) {
                log_activity('add_class_students', 'Gán ' . $insertedCount . ' sinh viên vào lớp #' . $classId);
                flash('success', 'Đã gán ' . $insertedCount . ' sinh viên vào lớp.');
            } else {
                flash('warning', 'Các sinh viên đã chọn đều đang thuộc lớp này.');
            }
        }

        if ($action === 'assign_period') {
            db()->prepare('INSERT IGNORE INTO registration_period_classes (registration_period_id, class_id) VALUES (?, ?)')
                ->execute([(int) $_POST['registration_period_id'], (int) $_POST['class_id']]);
            log_activity('assign_period_class', 'Gán đợt #' . (string) $_POST['registration_period_id'] . ' cho lớp #' . (string) $_POST['class_id']);
            flash('success', 'Đã gán đợt đăng ký cho lớp.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/classes.php');
}

$courses = db()->query('SELECT id, code, name FROM courses ORDER BY name')->fetchAll();
$teachers = db()->query("SELECT id, name FROM users WHERE role = 'teacher' AND is_locked = 0 ORDER BY name")->fetchAll();
$students = db()->query(
    "SELECT u.id, u.name, u.email, u.user_code,
            GROUP_CONCAT(cs.class_id ORDER BY cs.class_id SEPARATOR ',') AS class_ids
     FROM users u
     LEFT JOIN class_students cs ON cs.student_id = u.id
     WHERE u.role = 'student' AND u.is_locked = 0
     GROUP BY u.id
     ORDER BY u.name"
)->fetchAll();
$registrationPeriods = db()->query('SELECT id, name, status FROM registration_periods ORDER BY id DESC')->fetchAll();
$classes = db()->query(
    "SELECT c.*, co.code AS course_code, co.name AS course_name, u.name AS teacher_name,
            COUNT(DISTINCT cs.student_id) AS student_count,
            COUNT(DISTINCT g.id) AS group_count,
            GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', p.status, ')') ORDER BY p.created_at DESC SEPARATOR '\n') AS period_names
     FROM classes c
     JOIN courses co ON co.id = c.course_id
     JOIN users u ON u.id = c.teacher_id
     LEFT JOIN class_students cs ON cs.class_id = c.id
     LEFT JOIN student_groups g ON g.class_id = c.id
     LEFT JOIN registration_period_classes rpc ON rpc.class_id = c.id
     LEFT JOIN registration_periods p ON p.id = rpc.registration_period_id
     GROUP BY c.id
     ORDER BY c.id DESC"
)->fetchAll();

$editClass = null;
$editClassHasRuntimeData = false;
$editClassId = (int) ($_GET['edit'] ?? 0);
if ($editClassId > 0) {
    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ? LIMIT 1');
    $stmt->execute([$editClassId]);
    $editClass = $stmt->fetch() ?: null;

    $editClassHasRuntimeData = $editClass ? class_has_runtime_data($editClassId) : false;
}
$openClassEditor = $editClass !== null || isset($_GET['create']);

$page_title = 'Lớp học phần';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Lớp học phần</h1>
        <p>Admin tạo lớp, phân công giảng viên, gán sinh viên và gán đợt đăng ký.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/classes.php?create=1')) ?>">Tạo lớp</a>
</section>

<dialog class="app-modal class-editor-modal" data-editor-modal <?= $openClassEditor ? 'data-open-on-load="1"' : '' ?>>
    <section class="class-editor-modal-panel">
        <div class="panel-body">
            <div class="app-modal-header">
                <h2 class="h4 fw-bold mb-0"><?= $editClass ? 'Sửa lớp học phần' : 'Tạo lớp học phần' ?></h2>
                <a class="app-modal-close" href="<?= e(url('admin/classes.php')) ?>" aria-label="Đóng">&times;</a>
            </div>
        <?php if ($editClassHasRuntimeData): ?>
            <p class="text-muted mb-3">Lớp đã có nhóm: không thể đổi học phần gốc; các quy định mới phải vẫn phù hợp với toàn bộ nhóm hiện có.</p>
        <?php endif; ?>
        <form class="row g-3" method="post" data-async-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editClass ? 'update' : 'create' ?>">
            <?php if ($editClass): ?>
                <input type="hidden" name="class_id" value="<?= e((string) $editClass['id']) ?>">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Tên lớp</label>
                <input class="form-control" name="name" value="<?= e($editClass['name'] ?? '') ?>" placeholder="Công nghệ Web - K73.CNTT01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Học phần</label>
                <select class="form-select" name="course_id" data-custom-select required <?= $editClassHasRuntimeData ? 'disabled' : '' ?>>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e((string) $course['id']) ?>" <?= $editClass && (int) $editClass['course_id'] === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' - ' . $course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editClassHasRuntimeData): ?>
                    <input type="hidden" name="course_id" value="<?= e((string) $editClass['course_id']) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Giảng viên</label>
                <select class="form-select" name="teacher_id" data-custom-select required>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= e((string) $teacher['id']) ?>" <?= $editClass && (int) $editClass['teacher_id'] === (int) $teacher['id'] ? 'selected' : '' ?>><?= e($teacher['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối thiểu</label>
                <input class="form-control" name="min_members" type="number" min="1" value="<?= e((string) ($editClass['min_members'] ?? 2)) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối đa</label>
                <input class="form-control" name="max_members" type="number" min="1" value="<?= e((string) ($editClass['max_members'] ?? 4)) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Nhóm</label>
                <input class="form-control" name="max_groups" type="number" min="1" value="<?= e((string) ($editClass['max_groups'] ?? 12)) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="form-check fw-bold">
                    <input class="form-check-input" name="allow_self_group" type="checkbox" <?= !$editClass || (int) $editClass['allow_self_group'] === 1 ? 'checked' : '' ?>>
                    Cho phép sinh viên tự tạo nhóm
                </label>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2 class-editor-actions">
                <button class="btn btn-primary <?= $editClass ? 'flex-grow-1' : 'w-100' ?>" type="submit" <?= (!$courses || !$teachers) ? 'disabled' : '' ?>><?= $editClass ? 'Lưu thay đổi' : 'Tạo lớp' ?></button>
                <?php if ($editClass): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/classes.php')) ?>">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!$courses): ?>
            <p class="text-muted mt-3 mb-0">Cần tạo học phần trước khi tạo lớp.</p>
        <?php endif; ?>
        </div>
    </section>
</dialog>

<section class="card-panel mb-4" data-bulk-student-assignment>
    <div class="panel-body">
        <div class="section-heading mb-3">
            <div>
                <h2 class="h4 fw-bold">Gán sinh viên vào lớp</h2>
                <p>Chọn lớp, tìm sinh viên rồi tích nhiều người để gán trong một lần.</p>
            </div>
            <span class="badge-soft-info" data-selected-count>Đã chọn 0 sinh viên</span>
        </div>

        <form method="post" data-async-form data-async-id="bulk-students">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_students">
            <div class="bulk-student-toolbar">
                <div>
                    <label class="form-label" for="bulk-class-id">Lớp học phần</label>
                    <select class="form-select" id="bulk-class-id" name="class_id" data-bulk-class data-custom-select data-preserve-value required>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= e((string) $class['id']) ?>">
                                <?= e($class['course_code'] . ' - ' . $class['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="student-search">Tìm sinh viên</label>
                    <input class="form-control" id="student-search" type="search" placeholder="Nhập mã, họ tên hoặc email" data-student-search>
                </div>
                <div class="bulk-student-actions">
                    <button class="btn btn-outline-primary" type="button" data-select-visible>Chọn tất cả</button>
                    <button class="btn btn-outline-secondary" type="button" data-clear-selection>Bỏ chọn</button>
                </div>
            </div>

            <div class="bulk-student-list" data-student-list>
                <?php foreach ($students as $student): ?>
                    <label
                        class="bulk-student-option"
                        data-student-option
                        data-search-text="<?= e(mb_strtolower(($student['user_code'] ?: '') . ' ' . $student['name'] . ' ' . $student['email'])) ?>"
                    >
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="student_ids[]"
                            value="<?= e((string) $student['id']) ?>"
                            data-class-ids="<?= e((string) ($student['class_ids'] ?? '')) ?>"
                            data-student-check
                        >
                        <span class="bulk-student-info">
                            <strong><?= e(($student['user_code'] ?: 'Chưa có mã') . ' - ' . $student['name']) ?></strong>
                            <small><?= e($student['email']) ?></small>
                        </span>
                        <span class="bulk-student-status" data-student-status></span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <div class="empty-state">Chưa có tài khoản sinh viên hoạt động.</div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary" type="submit" data-assign-students <?= (!$classes || !$students) ? 'disabled' : '' ?>>
                    Gán sinh viên đã chọn
                </button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean admin-mobile-table classes-mobile-table">
                <thead>
                    <tr>
                        <th>Lớp</th>
                        <th>Giảng viên</th>
                        <th>Quy định nhóm</th>
                        <th>Số liệu</th>
                        <th>Đợt đăng ký</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td>
                                <strong><?= e($class['name']) ?></strong><br>
                                <span class="text-muted"><?= e($class['course_code'] . ' - ' . $class['course_name']) ?></span><br>
                                <small class="text-muted" style="white-space: pre-line"><?= e($class['period_names'] ?: 'Chưa gán đợt đăng ký') ?></small>
                            </td>
                            <td><?= e($class['teacher_name']) ?></td>
                            <td>
                                <?= e((string) $class['min_members']) ?> - <?= e((string) $class['max_members']) ?> thành viên<br>
                                <span class="text-muted">Tối đa <?= e((string) $class['max_groups']) ?> nhóm · <?= (int) $class['allow_self_group'] === 1 ? 'SV tự tạo nhóm' : 'GV tạo nhóm' ?></span>
                            </td>
                            <td><?= e((string) $class['student_count']) ?> sinh viên · <?= e((string) $class['group_count']) ?> nhóm</td>
                            <td>
                                <form class="d-flex gap-2" method="post" data-async-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="assign_period">
                                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                                    <select class="form-select form-select-sm" name="registration_period_id" required>
                                        <?php foreach ($registrationPeriods as $period): ?>
                                            <option value="<?= e((string) $period['id']) ?>"><?= e($period['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit" <?= !$registrationPeriods ? 'disabled' : '' ?>>Gán đợt</button>
                                </form>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/classes.php?edit=' . $class['id'])) ?>">Sửa</a>
                                    <?php if ((int) $class['group_count'] === 0): ?>
                                        <form method="post" data-async-form onsubmit="return confirm('Xóa lớp này? Sinh viên và các gán đợt chưa phát sinh sẽ bị gỡ khỏi lớp.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge-soft-secondary">Không xóa dữ liệu đã phát sinh</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$classes): ?>
                        <tr><td colspan="6" class="empty-state">Chưa có lớp học phần nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
