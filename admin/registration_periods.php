<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function input_datetime(string $value): string
{
    return date('Y-m-d\TH:i', strtotime($value));
}

function registration_period_payload(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $groupStart = str_replace('T', ' ', (string) ($_POST['group_start'] ?? '')) . ':00';
    $groupEnd = str_replace('T', ' ', (string) ($_POST['group_end'] ?? '')) . ':00';
    $registerStart = str_replace('T', ' ', (string) ($_POST['register_start'] ?? '')) . ':00';
    $registerEnd = str_replace('T', ' ', (string) ($_POST['register_end'] ?? '')) . ':00';
    $status = (string) ($_POST['status'] ?? 'draft');

    if ($name === '' || strtotime($groupStart) === false || strtotime($groupEnd) === false
        || strtotime($registerStart) === false || strtotime($registerEnd) === false) {
        throw new RuntimeException('Vui lòng nhập đầy đủ thông tin và thời gian của đợt đăng ký.');
    }
    if (strtotime($groupStart) > strtotime($groupEnd) || strtotime($registerStart) > strtotime($registerEnd)) {
        throw new RuntimeException('Thời gian kết thúc phải sau thời gian bắt đầu.');
    }
    if (!in_array($status, ['draft', 'open', 'closed'], true)) {
        throw new RuntimeException('Trạng thái không hợp lệ.');
    }

    return [$name, $groupStart, $groupEnd, $registerStart, $registerEnd, $status];
}

function period_can_open(int $periodId): bool
{
    $assigned = db()->prepare('SELECT COUNT(*) FROM registration_period_classes WHERE registration_period_id = ?');
    $assigned->execute([$periodId]);

    return (int) $assigned->fetchColumn() > 0;
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            [$name, $groupStart, $groupEnd, $registerStart, $registerEnd, $status] = registration_period_payload();
            if ($status === 'open') {
                throw new RuntimeException('Hãy tạo đợt ở trạng thái Nháp, gán ít nhất một lớp rồi mới mở đợt.');
            }
            $stmt = db()->prepare(
                'INSERT INTO registration_periods (name, group_start, group_end, register_start, register_end, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $groupStart, $groupEnd, $registerStart, $registerEnd, $status]);
            log_activity('create_registration_period', 'Tạo đợt đăng ký ' . $name);
            flash('success', 'Đã tạo đợt đăng ký.');
        }

        if ($action === 'update_period') {
            $periodId = (int) ($_POST['registration_period_id'] ?? 0);
            if ($periodId <= 0) {
                throw new RuntimeException('Đợt đăng ký không hợp lệ.');
            }
            [$name, $groupStart, $groupEnd, $registerStart, $registerEnd, $status] = registration_period_payload();
            if ($status === 'open' && !period_can_open($periodId)) {
                throw new RuntimeException('Cần gán đợt đăng ký cho ít nhất một lớp trước khi mở.');
            }

            $stmt = db()->prepare(
                'UPDATE registration_periods
                 SET name = ?, group_start = ?, group_end = ?, register_start = ?, register_end = ?, status = ?
                 WHERE id = ?'
            );
            $stmt->execute([$name, $groupStart, $groupEnd, $registerStart, $registerEnd, $status, $periodId]);
            if ($stmt->rowCount() === 0) {
                $exists = db()->prepare('SELECT COUNT(*) FROM registration_periods WHERE id = ?');
                $exists->execute([$periodId]);
                if ((int) $exists->fetchColumn() === 0) {
                    throw new RuntimeException('Không tìm thấy đợt đăng ký cần sửa.');
                }
            }
            log_activity('update_registration_period', 'Cập nhật đợt đăng ký #' . $periodId . ' - ' . $name);
            flash('success', 'Đã cập nhật đợt đăng ký.');
        }

        if ($action === 'set_status') {
            $periodId = (int) ($_POST['registration_period_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'draft');
            if (!in_array($status, ['draft', 'open', 'closed'], true)) {
                throw new RuntimeException('Trạng thái không hợp lệ.');
            }
            if ($status === 'open') {
                if (!period_can_open($periodId)) {
                    throw new RuntimeException('Cần gán đợt đăng ký cho ít nhất một lớp trước khi mở.');
                }
            }
            db()->prepare('UPDATE registration_periods SET status = ? WHERE id = ?')->execute([$status, $periodId]);
            log_activity('set_period_status', 'Cập nhật trạng thái đợt #' . $periodId);
            flash('success', 'Đã cập nhật trạng thái đợt đăng ký.');
        }

        if ($action === 'assign_class') {
            db()->prepare('INSERT IGNORE INTO registration_period_classes (registration_period_id, class_id) VALUES (?, ?)')
                ->execute([(int) $_POST['registration_period_id'], (int) $_POST['class_id']]);
            log_activity('assign_period_class', 'Gán lớp #' . (string) $_POST['class_id'] . ' vào đợt #' . (string) $_POST['registration_period_id']);
            flash('success', 'Đã gán lớp vào đợt đăng ký.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/registration_periods.php');
}

$registrationPeriods = db()->query(
    "SELECT p.*,
            GROUP_CONCAT(CONCAT(c.name, ' - ', co.code) ORDER BY c.name SEPARATOR '\n') AS class_names,
            COUNT(rpc.class_id) AS class_count
     FROM registration_periods p
     LEFT JOIN registration_period_classes rpc ON rpc.registration_period_id = p.id
     LEFT JOIN classes c ON c.id = rpc.class_id
     LEFT JOIN courses co ON co.id = c.course_id
     GROUP BY p.id
     ORDER BY p.id DESC"
)->fetchAll();
$classes = db()->query(
    "SELECT c.id, c.name, co.code AS course_code
     FROM classes c
     JOIN courses co ON co.id = c.course_id
     ORDER BY c.name"
)->fetchAll();

$editPeriod = null;
$editPeriodId = (int) ($_GET['edit'] ?? 0);
if ($editPeriodId > 0) {
    $stmt = db()->prepare('SELECT * FROM registration_periods WHERE id = ? LIMIT 1');
    $stmt->execute([$editPeriodId]);
    $editPeriod = $stmt->fetch() ?: null;
}
$openPeriodEditor = $editPeriod !== null || isset($_GET['create']);

$page_title = 'Đợt đăng ký';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Đợt đăng ký</h1>
        <p>Thiết lập thời gian tạo nhóm, đăng ký đề tài và gán đợt cho lớp.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/registration_periods.php?create=1')) ?>">Tạo đợt</a>
</section>

<dialog class="app-modal period-editor-modal" data-editor-modal <?= $openPeriodEditor ? 'data-open-on-load="1"' : '' ?>>
    <section class="period-editor-modal-panel rp-create-card">
        <div class="panel-body">
            <div class="app-modal-header">
                <h2 class="h4 fw-bold mb-0"><?= $editPeriod ? 'Sửa đợt đăng ký' : 'Tạo đợt đăng ký' ?></h2>
                <a class="app-modal-close" href="<?= e(url('admin/registration_periods.php')) ?>" aria-label="Đóng">&times;</a>
            </div>
        <div class="rp-form-header">
            <div class="rp-form-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <h2 class="rp-form-title"><?= $editPeriod ? 'Cập nhật thông tin đợt' : 'Thiết lập đợt mới' ?></h2>
                <p class="rp-form-subtitle">Điền thông tin đợt đăng ký và thời gian áp dụng</p>
            </div>
        </div>
        <form method="post" data-async-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editPeriod ? 'update_period' : 'create' ?>">
            <?php if ($editPeriod): ?>
                <input type="hidden" name="registration_period_id" value="<?= e((string) $editPeriod['id']) ?>">
            <?php endif; ?>

            <!-- Row 1: Tên đợt + Trạng thái -->
            <div class="rp-field-row">
                <div class="rp-field rp-field-grow">
                    <label class="rp-label">Tên đợt</label>
                    <div class="rp-input-wrap">
                        <span class="rp-input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </span>
                        <input class="rp-input" name="name" value="<?= e($editPeriod['name'] ?? 'Đợt đăng ký đề tài bài tập lớn') ?>" required placeholder="Nhập tên đợt đăng ký">
                    </div>
                </div>
                <div class="rp-field" style="min-width:180px">
                    <label class="rp-label">Trạng thái</label>
                    <div class="rp-select-wrap">
                        <select class="rp-select" name="status">
                            <?php if (!$editPeriod): ?>
                                <option value="draft" selected>📝 Nháp</option>
                            <?php else: ?>
                                <option value="draft" <?= $editPeriod['status'] === 'draft' ? 'selected' : '' ?>>📝 Nháp</option>
                                <option value="open" <?= $editPeriod['status'] === 'open' ? 'selected' : '' ?>>🟢 Đang mở</option>
                                <option value="closed" <?= $editPeriod['status'] === 'closed' ? 'selected' : '' ?>>🔴 Đã đóng</option>
                            <?php endif; ?>
                        </select>
                        <span class="rp-select-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Row 2: Thời gian tạo nhóm -->
            <div class="rp-date-group">
                <div class="rp-date-group-label">
                    <span class="rp-date-dot" style="background:#8b5cf6"></span>
                    Thời gian tạo nhóm
                </div>
                <div class="rp-date-pair">
                    <div class="rp-field">
                        <label class="rp-label-sm">Bắt đầu</label>
                        <div class="rp-datetime-wrap">
                            <span class="rp-datetime-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <input class="rp-datetime" name="group_start" type="datetime-local" value="<?= e($editPeriod ? input_datetime($editPeriod['group_start']) : '') ?>" required>
                        </div>
                    </div>
                    <div class="rp-date-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="rp-field">
                        <label class="rp-label-sm">Kết thúc</label>
                        <div class="rp-datetime-wrap">
                            <span class="rp-datetime-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <input class="rp-datetime" name="group_end" type="datetime-local" value="<?= e($editPeriod ? input_datetime($editPeriod['group_end']) : '') ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Thời gian đăng ký -->
            <div class="rp-date-group">
                <div class="rp-date-group-label">
                    <span class="rp-date-dot" style="background:#3b82f6"></span>
                    Thời gian đăng ký đề tài
                </div>
                <div class="rp-date-pair">
                    <div class="rp-field">
                        <label class="rp-label-sm">Bắt đầu</label>
                        <div class="rp-datetime-wrap">
                            <span class="rp-datetime-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <input class="rp-datetime" name="register_start" type="datetime-local" value="<?= e($editPeriod ? input_datetime($editPeriod['register_start']) : '') ?>" required>
                        </div>
                    </div>
                    <div class="rp-date-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="rp-field">
                        <label class="rp-label-sm">Kết thúc</label>
                        <div class="rp-datetime-wrap">
                            <span class="rp-datetime-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <input class="rp-datetime" name="register_end" type="datetime-local" value="<?= e($editPeriod ? input_datetime($editPeriod['register_end']) : '') ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rp-form-actions">
                <button class="rp-btn-submit" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= $editPeriod ? 'Lưu thay đổi' : 'Tạo đợt đăng ký' ?>
                </button>
            </div>
        </form>
        </div>
    </section>
</dialog>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean rp-periods-table">
            <thead>
                <tr>
                    <th>Tên đợt</th>
                    <th>Thời gian</th>
                    <th>Lớp áp dụng</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrationPeriods as $period): ?>
                    <tr>
                        <td data-label="T&#234;n &#273;&#7907;t">
                            <strong><?= e($period['name']) ?></strong><br>
                            <span class="<?= e(badge_class($period['status'])) ?>"><?= e(status_label($period['status'])) ?></span>
                        </td>
                        <td data-label="Th&#7901;i gian">
                            <div class="rp-time-info">
                                <div class="rp-time-row">
                                    <span class="rp-time-dot" style="background:#8b5cf6"></span>
                                    <div>
                                        <span class="rp-time-label">Tạo nhóm</span>
                                        <span class="rp-time-value"><?= e(format_datetime($period['group_start'])) ?> → <?= e(format_datetime($period['group_end'])) ?></span>
                                    </div>
                                </div>
                                <div class="rp-time-row">
                                    <span class="rp-time-dot" style="background:#3b82f6"></span>
                                    <div>
                                        <span class="rp-time-label">Đăng ký</span>
                                        <span class="rp-time-value"><?= e(format_datetime($period['register_start'])) ?> → <?= e(format_datetime($period['register_end'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="L&#7899;p &#225;p d&#7909;ng">
                            <div style="white-space: pre-line"><?= e($period['class_names'] ?: 'Chưa gán lớp') ?></div>
                            <small class="text-muted"><?= e((string) $period['class_count']) ?> lớp</small>
                        </td>
                        <td data-label="Thao t&#225;c">
                            <div class="rp-actions-stack">
                                <!-- Trạng thái -->
                                <form class="rp-action-row" method="post" data-async-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="registration_period_id" value="<?= e((string) $period['id']) ?>">
                                    <div class="rp-select-wrap rp-select-sm">
                                        <select class="rp-select" name="status">
                                            <option value="draft" <?= $period['status'] === 'draft' ? 'selected' : '' ?>>📝 Nháp</option>
                                            <option value="open" <?= $period['status'] === 'open' ? 'selected' : '' ?>>🟢 Mở</option>
                                            <option value="closed" <?= $period['status'] === 'closed' ? 'selected' : '' ?>>🔴 Đóng</option>
                                        </select>
                                        <span class="rp-select-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                                    </div>
                                    <button class="rp-btn-action rp-btn-save" type="submit">Lưu</button>
                                </form>

                                <!-- Gán lớp -->
                                <form class="rp-action-row" method="post" data-async-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="assign_class">
                                    <input type="hidden" name="registration_period_id" value="<?= e((string) $period['id']) ?>">
                                    <div class="rp-select-wrap rp-select-sm">
                                        <select class="rp-select" name="class_id" required>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?= e((string) $class['id']) ?>"><?= e($class['course_code'] . ' - ' . $class['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="rp-select-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                                    </div>
                                    <button class="rp-btn-action rp-btn-assign" type="submit" <?= !$classes ? 'disabled' : '' ?>>Gán lớp</button>
                                </form>

                                <!-- Chỉnh sửa thông tin và thời gian trong popup -->
                                <a class="rp-btn-action rp-btn-update" href="<?= e(url('admin/registration_periods.php?edit=' . $period['id'])) ?>">Sửa đợt</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$registrationPeriods): ?>
                    <tr><td colspan="4" class="empty-state">Chưa có đợt đăng ký nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
