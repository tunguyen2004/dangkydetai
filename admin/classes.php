<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO classes (course_id, teacher_id, name, min_members, max_members, max_groups, allow_self_group)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $_POST['course_id'],
                (int) $_POST['teacher_id'],
                trim((string) $_POST['name']),
                max(1, (int) $_POST['min_members']),
                max(1, (int) $_POST['max_members']),
                max(1, (int) $_POST['max_groups']),
                isset($_POST['allow_self_group']) ? 1 : 0,
            ]);
            log_activity('create_class', 'Tạo lớp ' . (string) $_POST['name']);
            flash('success', 'Đã tạo lớp học phần.');
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

$page_title = 'Lớp học phần';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Lớp học phần</h1>
        <p>Admin tạo lớp, phân công giảng viên, gán sinh viên và gán đợt đăng ký.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Tạo lớp</h2>
        <form class="row g-3" method="post" data-async-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Tên lớp</label>
                <input class="form-control" name="name" placeholder="Công nghệ Web - K73.CNTT01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Học phần</label>
                <select class="form-select" name="course_id" data-custom-select required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e((string) $course['id']) ?>"><?= e($course['code'] . ' - ' . $course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Giảng viên</label>
                <select class="form-select" name="teacher_id" data-custom-select required>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= e((string) $teacher['id']) ?>"><?= e($teacher['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối thiểu</label>
                <input class="form-control" name="min_members" type="number" min="1" value="2">
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối đa</label>
                <input class="form-control" name="max_members" type="number" min="1" value="4">
            </div>
            <div class="col-md-1">
                <label class="form-label">Nhóm</label>
                <input class="form-control" name="max_groups" type="number" min="1" value="12">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="form-check fw-bold">
                    <input class="form-check-input" name="allow_self_group" type="checkbox" checked>
                    Cho phép sinh viên tự tạo nhóm
                </label>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" <?= (!$courses || !$teachers) ? 'disabled' : '' ?>>Tạo lớp</button>
            </div>
        </form>
        <?php if (!$courses): ?>
            <p class="text-muted mt-3 mb-0">Cần tạo học phần trước khi tạo lớp.</p>
        <?php endif; ?>
    </div>
</section>

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
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$classes): ?>
                        <tr><td colspan="5" class="empty-state">Chưa có lớp học phần nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
