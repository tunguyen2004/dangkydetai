<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['teacher', 'student']);

$user = current_user();
$role = (string) ($user['role'] ?? '');

if ($role === 'teacher') {
    function teacher_topic_assignments_list_path(array $overrides = []): string
    {
        $allowed = ['q', 'class_id', 'period_id', 'status', 'per_page', 'page', 'create_topic', 'assign_topic'];
        $query = [];
    
        foreach ($allowed as $key) {
            $value = array_key_exists($key, $overrides) ? $overrides[$key] : ($_GET[$key] ?? null);
            if ($value !== null && $value !== '') {
                $query[$key] = (string) $value;
            }
        }
    
        return 'user/topics.php' . ($query ? '?' . http_build_query($query) : '');
    }
    
    if (is_post()) {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
    
        try {
            if ($action === 'create_topic') {
                $stmt = db()->prepare(
                    'INSERT INTO topics (teacher_id, code, title, description, min_members, max_members)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    (int) $user['id'],
                    trim((string) $_POST['code']),
                    trim((string) $_POST['title']),
                    trim((string) $_POST['description']),
                    max(1, (int) $_POST['min_members']),
                    max(1, (int) $_POST['max_members']),
                ]);
                log_activity('create_topic', 'Tạo đề tài gốc ' . (string) $_POST['code']);
                flash('success', 'Đã tạo đề tài gốc.');
            }
    
            if ($action === 'assign_topic') {
                $topicId = (int) ($_POST['topic_id'] ?? 0);
                $classId = (int) ($_POST['class_id'] ?? 0);
                $periodId = (int) ($_POST['registration_period_id'] ?? 0);
    
                $ownedTopic = db()->prepare('SELECT COUNT(*) FROM topics WHERE id = ? AND teacher_id = ?');
                $ownedTopic->execute([$topicId, (int) $user['id']]);
                if ((int) $ownedTopic->fetchColumn() === 0) {
                    throw new RuntimeException('Bạn không sở hữu đề tài này.');
                }
    
                $ownedClass = db()->prepare('SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?');
                $ownedClass->execute([$classId, (int) $user['id']]);
                if ((int) $ownedClass->fetchColumn() === 0) {
                    throw new RuntimeException('Bạn không phụ trách lớp này.');
                }
    
                $assignedPeriod = db()->prepare('SELECT COUNT(*) FROM registration_period_classes WHERE class_id = ? AND registration_period_id = ?');
                $assignedPeriod->execute([$classId, $periodId]);
                if ((int) $assignedPeriod->fetchColumn() === 0) {
                    throw new RuntimeException('Đợt đăng ký chưa được gán cho lớp này.');
                }
    
                db()->prepare(
                    'INSERT INTO topic_classes (topic_id, class_id, registration_period_id, max_groups, status)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $topicId,
                    $classId,
                    $periodId,
                    max(1, (int) $_POST['max_groups']),
                    (string) $_POST['status'],
                ]);
                log_activity('assign_topic_class', 'Gán đề tài #' . $topicId . ' cho lớp #' . $classId);
                flash('success', 'Đã mở đề tài cho lớp và đợt đăng ký.');
            }
    
            if ($action === 'toggle_assignment') {
                $topicClassId = (int) ($_POST['topic_class_id'] ?? 0);
                $stmt = db()->prepare(
                    "UPDATE topic_classes tc
                     JOIN classes c ON c.id = tc.class_id
                     SET tc.status = IF(tc.status = 'open', 'closed', 'open')
                     WHERE tc.id = ? AND c.teacher_id = ?"
                );
                $stmt->execute([$topicClassId, (int) $user['id']]);
                flash('success', 'Đã cập nhật trạng thái mở đề tài.');
            }
    
            if ($action === 'delete_assignment') {
                $topicClassId = (int) ($_POST['topic_class_id'] ?? 0);
                $used = db()->prepare('SELECT COUNT(*) FROM topic_registrations WHERE topic_class_id = ?');
                $used->execute([$topicClassId]);
                if ((int) $used->fetchColumn() > 0) {
                    throw new RuntimeException('Không thể xóa đề tài đã có nhóm đăng ký. Hãy đóng đề tài nếu không muốn nhận thêm.');
                }
                db()->prepare(
                    'DELETE tc FROM topic_classes tc
                     JOIN classes c ON c.id = tc.class_id
                     WHERE tc.id = ? AND c.teacher_id = ?'
                )->execute([$topicClassId, (int) $user['id']]);
                flash('success', 'Đã xóa bản ghi mở đề tài cho lớp.');
            }
    
            if ($action === 'delete_topic') {
                $topicId = (int) ($_POST['topic_id'] ?? 0);
                $used = db()->prepare('SELECT COUNT(*) FROM topic_classes WHERE topic_id = ?');
                $used->execute([$topicId]);
                if ((int) $used->fetchColumn() > 0) {
                    throw new RuntimeException('Đề tài đã được gán cho lớp, không nên xóa để tránh mất lịch sử.');
                }
                db()->prepare('DELETE FROM topics WHERE id = ? AND teacher_id = ?')->execute([$topicId, (int) $user['id']]);
                flash('success', 'Đã xóa đề tài gốc.');
            }
        } catch (Throwable $exception) {
            flash('danger', $exception->getMessage());
        }
    
        redirect(teacher_topic_assignments_list_path(['create_topic' => null, 'assign_topic' => null]));
    }
    
    $topicsStmt = db()->prepare('SELECT * FROM topics WHERE teacher_id = ? ORDER BY created_at DESC, code');
    $topicsStmt->execute([(int) $user['id']]);
    $topics = $topicsStmt->fetchAll();
    
    $contextsStmt = db()->prepare(
        "SELECT c.id AS class_id, c.name AS class_name, co.code AS course_code,
                p.id AS period_id, p.name AS period_name, p.status AS period_status
         FROM classes c
         JOIN courses co ON co.id = c.course_id
         JOIN registration_period_classes rpc ON rpc.class_id = c.id
         JOIN registration_periods p ON p.id = rpc.registration_period_id
         WHERE c.teacher_id = ?
         ORDER BY p.created_at DESC, c.name"
    );
    $contextsStmt->execute([(int) $user['id']]);
    $contexts = $contextsStmt->fetchAll();
    
    $teacherClassOptions = [];
    $teacherPeriodOptions = [];
    foreach ($contexts as $context) {
        $teacherClassOptions[(int) $context['class_id']] = [
            'id' => (int) $context['class_id'],
            'name' => $context['class_name'],
            'course_code' => $context['course_code'],
        ];
        $teacherPeriodOptions[(int) $context['period_id']] = [
            'id' => (int) $context['period_id'],
            'name' => $context['period_name'],
        ];
    }
    
    $assignmentSearch = trim((string) ($_GET['q'] ?? ''));
    $selectedClassId = max(0, (int) ($_GET['class_id'] ?? 0));
    $selectedPeriodId = max(0, (int) ($_GET['period_id'] ?? 0));
    $selectedAssignmentStatus = (string) ($_GET['status'] ?? '');
    $assignmentStatuses = ['open', 'closed', 'full'];
    if (!in_array($selectedAssignmentStatus, $assignmentStatuses, true)) {
        $selectedAssignmentStatus = '';
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
    $assignmentFilterConditions = [];
    $assignmentParams = [(int) $user['id']];
    
    if ($assignmentSearch !== '') {
        $keyword = '%' . $assignmentSearch . '%';
        $assignmentFilterConditions[] = '(t.code LIKE ? OR t.title LIKE ?)';
        array_push($assignmentParams, $keyword, $keyword);
    }
    if ($selectedClassId > 0) {
        $assignmentFilterConditions[] = 'tc.class_id = ?';
        $assignmentParams[] = $selectedClassId;
    }
    if ($selectedPeriodId > 0) {
        $assignmentFilterConditions[] = 'tc.registration_period_id = ?';
        $assignmentParams[] = $selectedPeriodId;
    }
    if ($selectedAssignmentStatus === 'full') {
        $assignmentFilterConditions[] = "(
            SELECT COUNT(*) FROM topic_registrations r_filter
            WHERE r_filter.topic_class_id = tc.id AND r_filter.status IN ('pending', 'approved')
        ) >= tc.max_groups";
    } elseif ($selectedAssignmentStatus !== '') {
        $assignmentFilterConditions[] = 'tc.status = ?';
        $assignmentParams[] = $selectedAssignmentStatus;
    }
    
    $assignmentFilterSql = $assignmentFilterConditions ? ' AND ' . implode(' AND ', $assignmentFilterConditions) : '';
    $assignmentCountStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM topic_classes tc
         JOIN topics t ON t.id = tc.topic_id
         WHERE t.teacher_id = ?' . $assignmentFilterSql
    );
    $assignmentCountStmt->execute($assignmentParams);
    $totalAssignments = (int) $assignmentCountStmt->fetchColumn();
    $totalAssignmentPages = max(1, (int) ceil($totalAssignments / $perPage));
    $page = min($page, $totalAssignmentPages);
    $offset = ($page - 1) * $perPage;
    $visibleStart = $totalAssignments === 0 ? 0 : $offset + 1;
    $visibleEnd = min($offset + $perPage, $totalAssignments);
    
    $assignmentsStmt = db()->prepare(
        "SELECT tc.*, t.code, t.title, t.description, t.min_members, t.max_members,
                c.name AS class_name, co.code AS course_code, p.name AS period_name,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status IN ('pending', 'approved')) AS active_count,
                (SELECT COUNT(*) FROM topic_registrations r WHERE r.topic_class_id = tc.id AND r.status = 'approved') AS approved_count
         FROM topic_classes tc
         JOIN topics t ON t.id = tc.topic_id
         JOIN classes c ON c.id = tc.class_id
         JOIN courses co ON co.id = c.course_id
         JOIN registration_periods p ON p.id = tc.registration_period_id
         WHERE t.teacher_id = ?" . $assignmentFilterSql . "
         ORDER BY tc.assigned_at DESC, t.code
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $assignmentsStmt->execute($assignmentParams);
    $assignments = $assignmentsStmt->fetchAll();
    
    $page_title = 'Quản lý đề tài';
    $openTopicCreate = (int) ($_GET['create_topic'] ?? 0) === 1;
    $openTopicAssignment = (int) ($_GET['assign_topic'] ?? 0) === 1;
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <section class="section-heading">
        <div>
            <h1>Quản lý đề tài</h1>
            <p>Tạo đề tài gốc, sau đó mở đề tài cho từng lớp và từng đợt đăng ký.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="<?= e(url(teacher_topic_assignments_list_path(['create_topic' => 1]))) ?>">Tạo đề tài</a>
            <a class="btn btn-primary" href="<?= e(url(teacher_topic_assignments_list_path(['assign_topic' => 1]))) ?>">Mở cho lớp</a>
        </div>
    </section>
    
    <dialog class="app-modal teacher-topic-editor-modal" data-editor-modal <?= $openTopicCreate ? 'data-open-on-load="1"' : '' ?>>
    <section class="teacher-topic-editor-modal-panel">
        <div class="panel-body">
            <div class="app-modal-header">
                <h2 class="h4 fw-bold mb-0">Tạo đề tài gốc</h2>
                <a class="app-modal-close" href="<?= e(url(teacher_topic_assignments_list_path(['create_topic' => null]))) ?>" aria-label="Đóng">&times;</a>
            </div>
            <form class="row g-3" method="post" data-async-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_topic">
                <div class="col-md-3">
                    <label class="form-label">Mã đề tài</label>
                    <input class="form-control" name="code" placeholder="DT05" required>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Tên đề tài</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tối thiểu</label>
                    <input class="form-control" name="min_members" type="number" min="1" value="2">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tối đa</label>
                    <input class="form-control" name="max_members" type="number" min="1" value="4">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="2" required></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary w-100" type="submit">Tạo đề tài</button>
                </div>
            </form>
        </div>
    </section>
    </dialog>
    
    <dialog class="app-modal teacher-topic-assignment-modal" data-editor-modal <?= $openTopicAssignment ? 'data-open-on-load="1"' : '' ?>>
    <section class="teacher-topic-editor-modal-panel">
        <div class="panel-body">
            <div class="app-modal-header">
                <h2 class="h4 fw-bold mb-0">Mở đề tài cho lớp</h2>
                <a class="app-modal-close" href="<?= e(url(teacher_topic_assignments_list_path(['assign_topic' => null]))) ?>" aria-label="Đóng">&times;</a>
            </div>
            <form class="row g-3" method="post" data-async-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_topic">
                <div class="col-md-6">
                    <label class="form-label">Đề tài gốc</label>
                    <select class="form-select" name="topic_id" required>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= e((string) $topic['id']) ?>"><?= e($topic['code'] . ' - ' . $topic['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lớp và đợt đăng ký</label>
                    <select class="form-select" name="context" required onchange="const [c,p]=this.value.split('|'); this.form.class_id.value=c; this.form.registration_period_id.value=p;">
                        <?php foreach ($contexts as $context): ?>
                            <option value="<?= e($context['class_id'] . '|' . $context['period_id']) ?>">
                                <?= e($context['course_code'] . ' - ' . $context['class_name'] . ' · ' . $context['period_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="class_id" value="<?= e((string) ($contexts[0]['class_id'] ?? '')) ?>">
                    <input type="hidden" name="registration_period_id" value="<?= e((string) ($contexts[0]['period_id'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Số nhóm tối đa</label>
                    <input class="form-control" name="max_groups" type="number" min="1" value="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="open">Đang mở</option>
                        <option value="closed">Đã đóng</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit" <?= (!$topics || !$contexts) ? 'disabled' : '' ?>>Mở đề tài</button>
                </div>
            </form>
            <?php if (!$contexts): ?>
                <p class="text-muted mt-3 mb-0">Chưa có lớp nào được gán đợt đăng ký cho giảng viên này.</p>
            <?php endif; ?>
        </div>
    </section>
    </dialog>
    
    <section class="card-panel teacher-topics-filter-card">
        <div class="panel-body">
            <form class="teacher-topics-filter-form" method="get" data-auto-filter-form>
                <div>
                    <label class="form-label" for="teacher-topic-search">Tìm đề tài</label>
                    <input class="form-control" id="teacher-topic-search" name="q" value="<?= e($assignmentSearch) ?>" placeholder="Mã hoặc tên đề tài">
                </div>
                <div>
                    <label class="form-label" for="teacher-topic-class">Lớp học phần</label>
                    <select class="form-select" id="teacher-topic-class" name="class_id">
                        <option value="">Tất cả lớp</option>
                        <?php foreach ($teacherClassOptions as $classOption): ?>
                            <option value="<?= e((string) $classOption['id']) ?>" <?= $selectedClassId === $classOption['id'] ? 'selected' : '' ?>>
                                <?= e($classOption['course_code'] . ' - ' . $classOption['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="teacher-topic-period">Đợt đăng ký</label>
                    <select class="form-select" id="teacher-topic-period" name="period_id">
                        <option value="">Tất cả đợt</option>
                        <?php foreach ($teacherPeriodOptions as $periodOption): ?>
                            <option value="<?= e((string) $periodOption['id']) ?>" <?= $selectedPeriodId === $periodOption['id'] ? 'selected' : '' ?>><?= e($periodOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="teacher-topic-status">Trạng thái</label>
                    <select class="form-select" id="teacher-topic-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="open" <?= $selectedAssignmentStatus === 'open' ? 'selected' : '' ?>>Đang mở</option>
                        <option value="closed" <?= $selectedAssignmentStatus === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                        <option value="full" <?= $selectedAssignmentStatus === 'full' ? 'selected' : '' ?>>Đã đủ nhóm</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="teacher-topic-per-page">Hiển thị</label>
                    <select class="form-select" id="teacher-topic-per-page" name="per_page">
                        <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 / trang</option>
                        <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 / trang</option>
                    </select>
                </div>
            </form>
        </div>
    </section>
    
    <section class="card-panel mb-4">
        <div class="panel-body table-responsive">
            <div class="teacher-topics-toolbar">
                <h2 class="h4 fw-bold mb-0">Đề tài đã mở cho lớp</h2>
                <span><?= e((string) $visibleStart) ?>-<?= e((string) $visibleEnd) ?> / <?= e((string) $totalAssignments) ?> đề tài</span>
            </div>
            <table class="table-clean role-mobile-table teacher-assignment-mobile-table">
                <thead>
                    <tr>
                        <th>Đề tài</th>
                        <th>Lớp / đợt</th>
                        <th>Yêu cầu</th>
                        <th>Sức chứa</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php $isFull = (int) $assignment['active_count'] >= (int) $assignment['max_groups']; ?>
                        <tr>
                            <td>
                                <span class="badge-soft-info"><?= e($assignment['code']) ?></span>
                                <strong><?= e($assignment['title']) ?></strong><br>
                                <span class="text-muted"><?= e($assignment['description']) ?></span>
                            </td>
                            <td><?= e($assignment['course_code'] . ' - ' . $assignment['class_name']) ?><br><span class="text-muted"><?= e($assignment['period_name']) ?></span></td>
                            <td><?= e((string) $assignment['min_members']) ?> - <?= e((string) $assignment['max_members']) ?> thành viên</td>
                            <td>
                                <span class="<?= e($assignment['status'] === 'open' && !$isFull ? 'badge-soft-success' : 'badge-soft-secondary') ?>">
                                    <?= $isFull ? 'Đã đủ nhóm' : e(status_label($assignment['status'])) ?>
                                </span><br>
                                <span class="text-muted"><?= e((string) $assignment['active_count']) ?>/<?= e((string) $assignment['max_groups']) ?> nhóm giữ chỗ, <?= e((string) $assignment['approved_count']) ?> đã duyệt</span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_assignment">
                                        <input type="hidden" name="topic_class_id" value="<?= e((string) $assignment['id']) ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><?= $assignment['status'] === 'open' ? 'Đóng' : 'Mở' ?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Xóa bản ghi mở đề tài này?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_assignment">
                                        <input type="hidden" name="topic_class_id" value="<?= e((string) $assignment['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assignments): ?>
                        <tr><td colspan="5" class="empty-state">Chưa mở đề tài nào cho lớp.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($totalAssignmentPages > 1): ?>
                <nav class="teacher-topics-pagination" aria-label="Phân trang danh sách đề tài">
                    <span>Trang <?= e((string) $page) ?> / <?= e((string) $totalAssignmentPages) ?></span>
                    <div class="teacher-topics-pagination-links">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url(teacher_topic_assignments_list_path(['page' => $page - 1]))) ?>">Trước</a>
                        <?php endif; ?>
                        <?php for ($pageNumber = 1; $pageNumber <= $totalAssignmentPages; $pageNumber++): ?>
                            <a class="btn btn-sm <?= $pageNumber === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(url(teacher_topic_assignments_list_path(['page' => $pageNumber]))) ?>">
                                <?= e((string) $pageNumber) ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalAssignmentPages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url(teacher_topic_assignments_list_path(['page' => $page + 1]))) ?>">Sau</a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="card-panel">
        <div class="panel-body table-responsive">
            <h2 class="h4 fw-bold mb-3">Kho đề tài gốc</h2>
            <table class="table-clean role-mobile-table teacher-topic-bank-mobile-table">
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Đề tài</th>
                        <th>Yêu cầu thành viên</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topics as $topic): ?>
                        <tr>
                            <td><span class="badge-soft-info"><?= e($topic['code']) ?></span></td>
                            <td><strong><?= e($topic['title']) ?></strong><br><span class="text-muted"><?= e($topic['description']) ?></span></td>
                            <td><?= e((string) $topic['min_members']) ?> - <?= e((string) $topic['max_members']) ?> thành viên</td>
                            <td>
                                <form method="post" onsubmit="return confirm('Xóa đề tài gốc này?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_topic">
                                    <input type="hidden" name="topic_id" value="<?= e((string) $topic['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$topics): ?>
                        <tr><td colspan="4" class="empty-state">Chưa có đề tài gốc nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($role === 'student') {
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
                redirect('user/topics.php');
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
    
        redirect('user/topics.php');
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
        <a class="btn btn-outline-primary" href="<?= e(url('user/group.php')) ?>">Nhóm của bạn</a>
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
    <?php require_once __DIR__ . '/../includes/footer.php';
    exit;
}
