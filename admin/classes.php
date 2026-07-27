<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

const CLASS_MAX_MEMBERS = 20;
const CLASS_MAX_GROUPS = 100;

final class ClassFormValidationException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message, public readonly string $value = '')
    {
        parent::__construct($message);
    }
}

function class_number_input(string $field, string $label, int $maximum): int
{
    $raw = trim((string) ($_POST[$field] ?? ''));
    if ($raw === '' || !ctype_digit($raw)) {
        throw new ClassFormValidationException($field, $label . ' phải là số nguyên dương.', $raw);
    }

    $normalized = ltrim($raw, '0');
    $normalized = $normalized === '' ? '0' : $normalized;
    $maximumString = (string) $maximum;
    if (strlen($normalized) > strlen($maximumString)
        || (strlen($normalized) === strlen($maximumString) && strcmp($normalized, $maximumString) > 0)) {
        throw new ClassFormValidationException($field, $label . ' chỉ được từ 1 đến ' . $maximum . '.', $raw);
    }

    $value = (int) $normalized;
    if ($value < 1) {
        throw new ClassFormValidationException($field, $label . ' phải lớn hơn hoặc bằng 1.', $raw);
    }

    return $value;
}

function class_form_values_from_request(): array
{
    return [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'course_id' => (string) ($_POST['course_id'] ?? ''),
        'teacher_id' => (string) ($_POST['teacher_id'] ?? ''),
        'min_members' => (string) ($_POST['min_members'] ?? ''),
        'max_members' => (string) ($_POST['max_members'] ?? ''),
        'max_groups' => (string) ($_POST['max_groups'] ?? ''),
        'allow_self_group' => isset($_POST['allow_self_group']) ? '1' : '0',
    ];
}

function class_editor_redirect_path(string $action, array $overrides = []): string
{
    if ($action === 'update' && (int) ($_POST['class_id'] ?? 0) > 0) {
        return class_list_path(array_merge(['edit' => (int) $_POST['class_id']], $overrides));
    }

    return class_list_path(array_merge(['create' => 1], $overrides));
}

function class_payload(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $minMembers = class_number_input('min_members', 'Số thành viên tối thiểu', CLASS_MAX_MEMBERS);
    $maxMembers = class_number_input('max_members', 'Số thành viên tối đa', CLASS_MAX_MEMBERS);
    $maxGroups = class_number_input('max_groups', 'Số nhóm tối đa', CLASS_MAX_GROUPS);

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

function assert_student_can_leave_class(int $classId, int $studentId): void
{
    $membershipStmt = db()->prepare('SELECT COUNT(*) FROM class_students WHERE class_id = ? AND student_id = ?');
    $membershipStmt->execute([$classId, $studentId]);
    if ((int) $membershipStmt->fetchColumn() === 0) {
        throw new RuntimeException('Sinh viên không còn thuộc lớp học phần này.');
    }

    $groupStmt = db()->prepare(
        "SELECT g.name,
                EXISTS(
                    SELECT 1 FROM topic_registrations tr
                    WHERE tr.group_id = g.id AND tr.status IN ('pending', 'approved')
                ) AS has_active_registration
         FROM student_groups g
         WHERE g.class_id = ?
           AND (
               g.created_by = ?
               OR EXISTS (
                   SELECT 1 FROM group_members gm
                   WHERE gm.group_id = g.id AND gm.user_id = ?
               )
           )
         LIMIT 1"
    );
    $groupStmt->execute([$classId, $studentId, $studentId]);
    $group = $groupStmt->fetch();

    if ($group) {
        $reason = (int) $group['has_active_registration'] === 1
            ? ' và nhóm đã có đăng ký đề tài còn hiệu lực'
            : '';
        throw new RuntimeException('Không thể thay đổi lớp vì sinh viên đang thuộc nhóm "' . $group['name'] . '"' . $reason . '.');
    }
}

function cancel_pending_class_invitations(int $classId, int $studentId): int
{
    $stmt = db()->prepare(
        "UPDATE group_invitations gi
         JOIN student_groups g ON g.id = gi.group_id
         SET gi.status = 'cancelled', gi.responded_at = NOW()
         WHERE gi.invited_user_id = ? AND gi.status = 'pending' AND g.class_id = ?"
    );
    $stmt->execute([$studentId, $classId]);

    return $stmt->rowCount();
}

function class_list_path(array $overrides = []): string
{
    $periodStatus = (string) ($_GET['period_status'] ?? '');
    if (!in_array($periodStatus, ['draft', 'open', 'closed', 'unassigned'], true)) {
        $periodStatus = '';
    }

    $perPage = (int) ($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 20, 50], true)) {
        $perPage = 10;
    }

    $query = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'period_id' => max(0, (int) ($_GET['period_id'] ?? 0)),
        'period_status' => $periodStatus,
        'per_page' => $perPage,
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    foreach ($query as $key => $value) {
        if ($value === null || $value === '' || $value === 0
            || ($key === 'page' && (int) $value <= 1)
            || ($key === 'per_page' && (int) $value === 10)) {
            unset($query[$key]);
        }
    }

    return 'admin/classes.php' . ($query ? '?' . http_build_query($query) : '');
}

function class_list_url(array $overrides = []): string
{
    return url(class_list_path($overrides));
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $redirectPath = class_list_path();

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

        if ($action === 'remove_student') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $studentId = (int) ($_POST['student_id'] ?? 0);
            if ($classId <= 0 || $studentId <= 0) {
                throw new RuntimeException('Thông tin lớp hoặc sinh viên không hợp lệ.');
            }

            db()->beginTransaction();
            try {
                assert_student_can_leave_class($classId, $studentId);
                $cancelledInvitations = cancel_pending_class_invitations($classId, $studentId);
                db()->prepare('DELETE FROM class_students WHERE class_id = ? AND student_id = ?')->execute([$classId, $studentId]);
                db()->commit();
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $exception;
            }

            log_activity('remove_class_student', 'Bỏ sinh viên #' . $studentId . ' khỏi lớp #' . $classId
                . ($cancelledInvitations > 0 ? '; đã hủy ' . $cancelledInvitations . ' lời mời nhóm chờ phản hồi' : ''));
            flash('success', 'Đã bỏ sinh viên khỏi lớp học phần.');
        }

        if ($action === 'transfer_student') {
            $sourceClassId = (int) ($_POST['class_id'] ?? 0);
            $targetClassId = (int) ($_POST['target_class_id'] ?? 0);
            $studentId = (int) ($_POST['student_id'] ?? 0);
            if ($sourceClassId <= 0 || $targetClassId <= 0 || $studentId <= 0 || $sourceClassId === $targetClassId) {
                throw new RuntimeException('Thông tin chuyển lớp không hợp lệ.');
            }

            $classStmt = db()->prepare('SELECT id, course_id FROM classes WHERE id IN (?, ?)');
            $classStmt->execute([$sourceClassId, $targetClassId]);
            $classRows = $classStmt->fetchAll();
            if (count($classRows) !== 2 || (int) $classRows[0]['course_id'] !== (int) $classRows[1]['course_id']) {
                throw new RuntimeException('Chỉ có thể chuyển sinh viên giữa các lớp cùng học phần.');
            }

            db()->beginTransaction();
            try {
                assert_student_can_leave_class($sourceClassId, $studentId);
                $targetMembership = db()->prepare('SELECT COUNT(*) FROM class_students WHERE class_id = ? AND student_id = ?');
                $targetMembership->execute([$targetClassId, $studentId]);
                if ((int) $targetMembership->fetchColumn() > 0) {
                    throw new RuntimeException('Sinh viên đã thuộc lớp nhận, không thể chuyển trùng.');
                }

                $cancelledInvitations = cancel_pending_class_invitations($sourceClassId, $studentId);
                db()->prepare('INSERT INTO class_students (class_id, student_id) VALUES (?, ?)')->execute([$targetClassId, $studentId]);
                db()->prepare('DELETE FROM class_students WHERE class_id = ? AND student_id = ?')->execute([$sourceClassId, $studentId]);
                db()->commit();
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $exception;
            }

            log_activity('transfer_class_student', 'Chuyển sinh viên #' . $studentId . ' từ lớp #' . $sourceClassId . ' sang lớp #' . $targetClassId
                . ($cancelledInvitations > 0 ? '; đã hủy ' . $cancelledInvitations . ' lời mời nhóm chờ phản hồi' : ''));
            flash('success', 'Đã chuyển sinh viên sang lớp học phần mới.');
        }

        if ($action === 'assign_period') {
            db()->prepare('INSERT IGNORE INTO registration_period_classes (registration_period_id, class_id) VALUES (?, ?)')
                ->execute([(int) $_POST['registration_period_id'], (int) $_POST['class_id']]);
            log_activity('assign_period_class', 'Gán đợt #' . (string) $_POST['registration_period_id'] . ' cho lớp #' . (string) $_POST['class_id']);
            flash('success', 'Đã gán đợt đăng ký cho lớp.');
        }
    } catch (ClassFormValidationException $exception) {
        $_SESSION['class_form_errors'] = [$exception->field => $exception->getMessage()];
        $_SESSION['class_form_values'] = class_form_values_from_request();
        $redirectPath = class_editor_redirect_path($action, [
            'validation_field' => $exception->field,
            'validation_value' => $exception->value,
        ]);
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
        if (in_array($action, ['create', 'update'], true)) {
            $_SESSION['class_form_values'] = class_form_values_from_request();
            $redirectPath = class_editor_redirect_path($action);
        }
    }

    redirect($redirectPath);
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
$classOptions = db()->query(
    'SELECT c.id, c.name, co.code AS course_code
     FROM classes c
     JOIN courses co ON co.id = c.course_id
     ORDER BY co.code, c.name'
)->fetchAll();

$classSearch = trim((string) ($_GET['q'] ?? ''));
$selectedPeriodId = max(0, (int) ($_GET['period_id'] ?? 0));
$selectedPeriodStatus = (string) ($_GET['period_status'] ?? '');
if (!in_array($selectedPeriodStatus, ['draft', 'open', 'closed', 'unassigned'], true)) {
    $selectedPeriodStatus = '';
}
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50], true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$conditions = [];
$params = [];
if ($classSearch !== '') {
    $conditions[] = '(c.name LIKE ? OR co.code LIKE ? OR co.name LIKE ? OR u.name LIKE ? OR u.user_code LIKE ?)';
    $keyword = '%' . $classSearch . '%';
    array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword);
}
if ($selectedPeriodId > 0) {
    $conditions[] = 'EXISTS (
        SELECT 1 FROM registration_period_classes rpc_filter
        WHERE rpc_filter.class_id = c.id AND rpc_filter.registration_period_id = ?
    )';
    $params[] = $selectedPeriodId;
}
if ($selectedPeriodStatus === 'unassigned') {
    $conditions[] = 'NOT EXISTS (
        SELECT 1 FROM registration_period_classes rpc_filter
        WHERE rpc_filter.class_id = c.id
    )';
} elseif ($selectedPeriodStatus !== '') {
    $conditions[] = 'EXISTS (
        SELECT 1
        FROM registration_period_classes rpc_filter
        JOIN registration_periods p_filter ON p_filter.id = rpc_filter.registration_period_id
        WHERE rpc_filter.class_id = c.id AND p_filter.status = ?
    )';
    $params[] = $selectedPeriodStatus;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$classBaseQuery = ' FROM classes c
    JOIN courses co ON co.id = c.course_id
    JOIN users u ON u.id = c.teacher_id';

$countStmt = db()->prepare('SELECT COUNT(*)' . $classBaseQuery . $where);
$countStmt->execute($params);
$totalClasses = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalClasses / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$classesStmt = db()->prepare(
    "SELECT c.*, co.code AS course_code, co.name AS course_name, u.name AS teacher_name,
            COUNT(DISTINCT cs.student_id) AS student_count,
            COUNT(DISTINCT g.id) AS group_count,
            GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', p.status, ')') ORDER BY p.created_at DESC SEPARATOR '\n') AS period_names
     " . $classBaseQuery . "
     LEFT JOIN class_students cs ON cs.class_id = c.id
     LEFT JOIN student_groups g ON g.class_id = c.id
     LEFT JOIN registration_period_classes rpc ON rpc.class_id = c.id
     LEFT JOIN registration_periods p ON p.id = rpc.registration_period_id
     " . $where . "
     GROUP BY c.id
     ORDER BY c.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$classesStmt->execute($params);
$classes = $classesStmt->fetchAll();
$visibleStart = $totalClasses === 0 ? 0 : $offset + 1;
$visibleEnd = min($offset + $perPage, $totalClasses);

$membershipClass = null;
$membershipStudents = [];
$transferTargets = [];
$membershipClassId = (int) ($_GET['members'] ?? 0);
if ($membershipClassId > 0) {
    $membershipClassStmt = db()->prepare(
        'SELECT c.id, c.course_id, c.name, co.code AS course_code, co.name AS course_name
         FROM classes c
         JOIN courses co ON co.id = c.course_id
         WHERE c.id = ? LIMIT 1'
    );
    $membershipClassStmt->execute([$membershipClassId]);
    $membershipClass = $membershipClassStmt->fetch() ?: null;

    if ($membershipClass) {
        $membershipStudentsStmt = db()->prepare(
            'SELECT u.id, u.name, u.email, u.user_code, u.is_locked, cs.joined_at
             FROM class_students cs
             JOIN users u ON u.id = cs.student_id
             WHERE cs.class_id = ?
             ORDER BY u.name, u.id'
        );
        $membershipStudentsStmt->execute([$membershipClassId]);
        $membershipStudents = $membershipStudentsStmt->fetchAll();

        $transferTargetsStmt = db()->prepare(
            'SELECT c.id, c.name, co.code AS course_code
             FROM classes c
             JOIN courses co ON co.id = c.course_id
             WHERE c.course_id = ? AND c.id <> ?
             ORDER BY c.name'
        );
        $transferTargetsStmt->execute([(int) $membershipClass['course_id'], $membershipClassId]);
        $transferTargets = $transferTargetsStmt->fetchAll();
    }
}

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
$classFormErrors = (array) ($_SESSION['class_form_errors'] ?? []);
$classFormValues = (array) ($_SESSION['class_form_values'] ?? []);
unset($_SESSION['class_form_errors'], $_SESSION['class_form_values']);
$validationField = (string) ($_GET['validation_field'] ?? '');
$validationValue = (string) ($_GET['validation_value'] ?? '');
if (!$classFormErrors && in_array($validationField, ['min_members', 'max_members', 'max_groups'], true)) {
    $validationLabels = [
        'min_members' => 'Số thành viên tối thiểu',
        'max_members' => 'Số thành viên tối đa',
        'max_groups' => 'Số nhóm tối đa',
    ];
    $validationMaximums = [
        'min_members' => CLASS_MAX_MEMBERS,
        'max_members' => CLASS_MAX_MEMBERS,
        'max_groups' => CLASS_MAX_GROUPS,
    ];
    $label = $validationLabels[$validationField];
    $maximum = $validationMaximums[$validationField];
    $classFormErrors[$validationField] = ctype_digit($validationValue)
        ? $label . ' chỉ được từ 1 đến ' . $maximum . '.'
        : $label . ' phải là số nguyên dương.';
    $classFormValues[$validationField] = $validationValue;
}
$classFormValue = static function (string $field, string $fallback = '') use ($classFormValues): string {
    return array_key_exists($field, $classFormValues) ? (string) $classFormValues[$field] : $fallback;
};
$classFormAllowsSelfGroup = array_key_exists('allow_self_group', $classFormValues)
    ? $classFormValues['allow_self_group'] === '1'
    : (!$editClass || (int) $editClass['allow_self_group'] === 1);

$page_title = 'Lớp học phần';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Lớp học phần</h1>
        <p>Admin tạo lớp, phân công giảng viên, gán sinh viên và gán đợt đăng ký.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(class_list_url(['create' => 1])) ?>">Tạo lớp</a>
</section>

<dialog class="app-modal class-editor-modal" data-editor-modal <?= $openClassEditor ? 'data-open-on-load="1"' : '' ?>>
    <section class="class-editor-modal-panel">
        <div class="panel-body">
            <div class="app-modal-header">
                <h2 class="h4 fw-bold mb-0"><?= $editClass ? 'Sửa lớp học phần' : 'Tạo lớp học phần' ?></h2>
                <a class="app-modal-close" href="<?= e(class_list_url()) ?>" aria-label="Đóng">&times;</a>
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
                <input class="form-control" name="name" value="<?= e($classFormValue('name', $editClass['name'] ?? '')) ?>" placeholder="Công nghệ Web - K73.CNTT01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Học phần</label>
                <select class="form-select" name="course_id" data-custom-select required <?= $editClassHasRuntimeData ? 'disabled' : '' ?>>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e((string) $course['id']) ?>" <?= $classFormValue('course_id', (string) ($editClass['course_id'] ?? '')) === (string) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' - ' . $course['name']) ?></option>
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
                        <option value="<?= e((string) $teacher['id']) ?>" <?= $classFormValue('teacher_id', (string) ($editClass['teacher_id'] ?? '')) === (string) $teacher['id'] ? 'selected' : '' ?>><?= e($teacher['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối thiểu</label>
                <input class="form-control <?= isset($classFormErrors['min_members']) ? 'is-invalid' : '' ?>" name="min_members" type="number" min="1" max="<?= CLASS_MAX_MEMBERS ?>" step="1" required value="<?= e($classFormValue('min_members', (string) ($editClass['min_members'] ?? 2))) ?>">
                <?php if (isset($classFormErrors['min_members'])): ?><div class="form-field-error"><?= e((string) $classFormErrors['min_members']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-1">
                <label class="form-label">Tối đa</label>
                <input class="form-control <?= isset($classFormErrors['max_members']) ? 'is-invalid' : '' ?>" name="max_members" type="number" min="1" max="<?= CLASS_MAX_MEMBERS ?>" step="1" required value="<?= e($classFormValue('max_members', (string) ($editClass['max_members'] ?? 4))) ?>">
                <?php if (isset($classFormErrors['max_members'])): ?><div class="form-field-error"><?= e((string) $classFormErrors['max_members']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-1">
                <label class="form-label">Nhóm</label>
                <input class="form-control <?= isset($classFormErrors['max_groups']) ? 'is-invalid' : '' ?>" name="max_groups" type="number" min="1" max="<?= CLASS_MAX_GROUPS ?>" step="1" required value="<?= e($classFormValue('max_groups', (string) ($editClass['max_groups'] ?? 12))) ?>" aria-describedby="max-groups-help">
                <div class="form-text" id="max-groups-help">Từ 1 đến <?= CLASS_MAX_GROUPS ?> nhóm.</div>
                <?php if (isset($classFormErrors['max_groups'])): ?><div class="form-field-error"><?= e((string) $classFormErrors['max_groups']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="form-check fw-bold">
                    <input class="form-check-input" name="allow_self_group" type="checkbox" <?= $classFormAllowsSelfGroup ? 'checked' : '' ?>>
                    Cho phép sinh viên tự tạo nhóm
                </label>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2 class-editor-actions">
                <button class="btn btn-primary <?= $editClass ? 'flex-grow-1' : 'w-100' ?>" type="submit" <?= (!$courses || !$teachers) ? 'disabled' : '' ?>><?= $editClass ? 'Lưu thay đổi' : 'Tạo lớp' ?></button>
                <?php if ($editClass): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(class_list_url()) ?>">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!$courses): ?>
            <p class="text-muted mt-3 mb-0">Cần tạo học phần trước khi tạo lớp.</p>
        <?php endif; ?>
        </div>
    </section>
</dialog>

<dialog class="app-modal class-members-modal" data-editor-modal <?= $membershipClass ? 'data-open-on-load="1"' : '' ?>>
    <section class="class-members-modal-panel">
        <div class="panel-body">
            <div class="app-modal-header">
                <div>
                    <h2 class="h4 fw-bold mb-1">Quản lý sinh viên trong lớp</h2>
                    <?php if ($membershipClass): ?>
                        <p class="text-muted mb-0"><?= e($membershipClass['course_code'] . ' - ' . $membershipClass['name']) ?></p>
                    <?php endif; ?>
                </div>
                <a class="app-modal-close" href="<?= e(class_list_url()) ?>" aria-label="Đóng">&times;</a>
            </div>

            <p class="membership-helper">Chỉ bỏ hoặc chuyển sinh viên khi chưa thuộc nhóm của lớp này. Nếu còn lời mời nhóm chờ phản hồi, hệ thống sẽ tự hủy lời mời đó.</p>

            <div class="table-responsive membership-table-wrap">
                <table class="table-clean admin-mobile-table class-members-mobile-table">
                    <thead>
                        <tr>
                            <th>Sinh viên</th>
                            <th>Tham gia lớp</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membershipStudents as $member): ?>
                            <tr>
                                <td>
                                    <strong><?= e(($member['user_code'] ?: 'Chưa có mã') . ' - ' . $member['name']) ?></strong><br>
                                    <span class="text-muted"><?= e($member['email']) ?></span>
                                    <?php if ((int) $member['is_locked'] === 1): ?>
                                        <span class="badge-soft-danger">Tài khoản đang khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(format_datetime((string) $member['joined_at'])) ?></td>
                                <td>
                                    <div class="membership-actions">
                                        <form method="post" data-async-form onsubmit="return confirm('Bỏ <?= e(addslashes($member['name'])) ?> khỏi lớp này? Hệ thống sẽ kiểm tra nhóm và đăng ký đề tài trước khi thực hiện.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_student">
                                            <input type="hidden" name="class_id" value="<?= e((string) $membershipClass['id']) ?>">
                                            <input type="hidden" name="student_id" value="<?= e((string) $member['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Bỏ khỏi lớp</button>
                                        </form>

                                        <?php if ($transferTargets): ?>
                                            <form class="membership-transfer-form" method="post" data-async-form onsubmit="return confirm('Chuyển <?= e(addslashes($member['name'])) ?> sang lớp đã chọn? Hệ thống sẽ kiểm tra nhóm và đăng ký đề tài trước khi thực hiện.')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="transfer_student">
                                                <input type="hidden" name="class_id" value="<?= e((string) $membershipClass['id']) ?>">
                                                <input type="hidden" name="student_id" value="<?= e((string) $member['id']) ?>">
                                                <select class="form-select form-select-sm" name="target_class_id" data-custom-select required>
                                                    <option value="">Chọn lớp nhận</option>
                                                    <?php foreach ($transferTargets as $targetClass): ?>
                                                        <option value="<?= e((string) $targetClass['id']) ?>">
                                                            <?= e($targetClass['course_code'] . ' - ' . $targetClass['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Chuyển lớp</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge-soft-secondary">Chưa có lớp cùng học phần để chuyển</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$membershipStudents): ?>
                            <tr><td colspan="3" class="empty-state">Lớp này chưa có sinh viên.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                        <?php foreach ($classOptions as $class): ?>
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
                <button class="btn btn-primary" type="submit" data-assign-students <?= (!$classOptions || !$students) ? 'disabled' : '' ?>>
                    Gán sinh viên đã chọn
                </button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel class-list-filter-card">
    <div class="panel-body">
        <form class="class-list-filter-form" method="get">
            <div class="class-list-search-field">
                <label class="form-label" for="class-search">Tìm kiếm lớp</label>
                <input class="form-control" id="class-search" name="q" value="<?= e($classSearch) ?>"
                    placeholder="Tên/mã lớp, học phần, giảng viên">
            </div>
            <div>
                <label class="form-label" for="class-period-filter">Đợt đăng ký</label>
                <select class="form-select" id="class-period-filter" name="period_id" data-custom-select>
                    <option value="">Tất cả đợt</option>
                    <?php foreach ($registrationPeriods as $period): ?>
                        <option value="<?= e((string) $period['id']) ?>" <?= $selectedPeriodId === (int) $period['id'] ? 'selected' : '' ?>>
                            <?= e($period['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="class-period-status-filter">Trạng thái đợt</label>
                <select class="form-select" id="class-period-status-filter" name="period_status" data-custom-select>
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft" <?= $selectedPeriodStatus === 'draft' ? 'selected' : '' ?>>Nháp</option>
                    <option value="open" <?= $selectedPeriodStatus === 'open' ? 'selected' : '' ?>>Đang mở</option>
                    <option value="closed" <?= $selectedPeriodStatus === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                    <option value="unassigned" <?= $selectedPeriodStatus === 'unassigned' ? 'selected' : '' ?>>Chưa gán đợt</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="class-per-page">Hiển thị</label>
                <select class="form-select" id="class-per-page" name="per_page" data-custom-select>
                    <?php foreach ([10, 20, 50] as $perPageOption): ?>
                        <option value="<?= e((string) $perPageOption) ?>" <?= $perPage === $perPageOption ? 'selected' : '' ?>>
                            <?= e((string) $perPageOption) ?> dòng
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="class-list-filter-actions">
                <button class="btn btn-primary" type="submit">Lọc</button>
                <a class="btn btn-outline-secondary" href="<?= e(class_list_url(['q' => null, 'period_id' => null, 'period_status' => null, 'page' => null])) ?>">Xóa lọc</a>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body">
        <div class="class-list-toolbar">
            <div>
                <h2 class="h4 fw-bold mb-1">Danh sách lớp học phần</h2>
                <p class="text-muted mb-0">Hiển thị <?= e((string) $visibleStart) ?>-<?= e((string) $visibleEnd) ?> trong <?= e((string) $totalClasses) ?> lớp phù hợp.</p>
            </div>
        </div>
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
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(class_list_url(['members' => (int) $class['id']])) ?>">Thành viên</a>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(class_list_url(['edit' => (int) $class['id']])) ?>">Sửa</a>
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

        <?php if ($totalPages > 1): ?>
            <nav class="class-pagination" aria-label="Phân trang lớp học phần">
                <span>Trang <?= e((string) $page) ?> / <?= e((string) $totalPages) ?></span>
                <div class="class-pagination-links">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(class_list_url(['page' => $page - 1])) ?>">Trang trước</a>
                    <?php endif; ?>
                    <?php for ($pageNumber = max(1, $page - 2); $pageNumber <= min($totalPages, $page + 2); $pageNumber++): ?>
                        <a class="btn <?= $pageNumber === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(class_list_url(['page' => $pageNumber])) ?>">
                            <?= e((string) $pageNumber) ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(class_list_url(['page' => $page + 1])) ?>">Trang sau</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
