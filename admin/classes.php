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
                'INSERT INTO classes (semester_id, teacher_id, name, course_code, min_members, max_members, max_groups, allow_self_group)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $_POST['semester_id'],
                (int) $_POST['teacher_id'],
                trim((string) $_POST['name']),
                trim((string) $_POST['course_code']),
                (int) $_POST['min_members'],
                (int) $_POST['max_members'],
                (int) $_POST['max_groups'],
                isset($_POST['allow_self_group']) ? 1 : 0,
            ]);
            log_activity('create_class', 'Tạo lớp ' . (string) $_POST['name']);
            flash('success', 'Đã tạo lớp học phần.');
        }

        if ($action === 'add_student') {
            db()->prepare('INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)')
                ->execute([(int) $_POST['class_id'], (int) $_POST['student_id']]);
            log_activity('add_class_student', 'Gán sinh viên vào lớp #' . (string) $_POST['class_id']);
            flash('success', 'Đã gán sinh viên vào lớp.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/classes.php');
}

$teachers = db()->query("SELECT id, name FROM users WHERE role = 'teacher' AND is_locked = 0 ORDER BY name")->fetchAll();
$students = db()->query("SELECT id, name, student_code FROM users WHERE role = 'student' AND is_locked = 0 ORDER BY name")->fetchAll();
$semesters = db()->query('SELECT id, name FROM semesters ORDER BY id DESC')->fetchAll();
$classes = db()->query(
    "SELECT c.*, s.name AS semester_name, u.name AS teacher_name,
            COUNT(cs.student_id) AS student_count,
            (SELECT COUNT(*) FROM student_groups g WHERE g.class_id = c.id) AS group_count
     FROM classes c
     JOIN semesters s ON s.id = c.semester_id
     JOIN users u ON u.id = c.teacher_id
     LEFT JOIN class_students cs ON cs.class_id = c.id
     GROUP BY c.id
     ORDER BY c.id DESC"
)->fetchAll();

$page_title = 'Lớp học phần';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Lớp học phần</h1>
        <p>Admin tạo lớp, phân công giảng viên và gán sinh viên.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Tạo lớp</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Tên lớp</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mã học phần</label>
                <input class="form-control" name="course_code" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Giảng viên</label>
                <select class="form-select" name="teacher_id" required>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= e((string) $teacher['id']) ?>"><?= e($teacher['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Đợt đăng ký</label>
                <select class="form-select" name="semester_id" required>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= e((string) $semester['id']) ?>"><?= e($semester['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tối thiểu</label>
                <input class="form-control" name="min_members" type="number" min="1" value="2">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tối đa</label>
                <input class="form-control" name="max_members" type="number" min="1" value="4">
            </div>
            <div class="col-md-2">
                <label class="form-label">Số nhóm tối đa</label>
                <input class="form-control" name="max_groups" type="number" min="1" value="12">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="form-check fw-bold">
                    <input class="form-check-input" name="allow_self_group" type="checkbox" checked>
                    Cho phép tự tạo nhóm
                </label>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Tạo lớp</button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Lớp</th>
                        <th>Giảng viên</th>
                        <th>Quy định nhóm</th>
                        <th>Số liệu</th>
                        <th>Gán sinh viên</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td>
                                <strong><?= e($class['name']) ?></strong><br>
                                <span class="text-muted"><?= e($class['course_code']) ?> · <?= e($class['semester_name']) ?></span>
                            </td>
                            <td><?= e($class['teacher_name']) ?></td>
                            <td><?= e((string) $class['min_members']) ?> - <?= e((string) $class['max_members']) ?> thành viên, tối đa <?= e((string) $class['max_groups']) ?> nhóm</td>
                            <td><?= e((string) $class['student_count']) ?> sinh viên · <?= e((string) $class['group_count']) ?> nhóm</td>
                            <td>
                                <form class="d-flex gap-2" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_student">
                                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                                    <select class="form-select form-select-sm" name="student_id">
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?= e((string) $student['id']) ?>"><?= e($student['student_code'] . ' - ' . $student['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Gán</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
