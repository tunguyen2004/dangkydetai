<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function input_datetime(string $value): string
{
    return date('Y-m-d\TH:i', strtotime($value));
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO registration_periods (name, group_start, group_end, register_start, register_end, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) $_POST['name']),
                str_replace('T', ' ', (string) $_POST['group_start']) . ':00',
                str_replace('T', ' ', (string) $_POST['group_end']) . ':00',
                str_replace('T', ' ', (string) $_POST['register_start']) . ':00',
                str_replace('T', ' ', (string) $_POST['register_end']) . ':00',
                (string) $_POST['status'],
            ]);
            log_activity('create_registration_period', 'Tạo đợt đăng ký ' . (string) $_POST['name']);
            flash('success', 'Đã tạo đợt đăng ký.');
        }

        if ($action === 'set_status') {
            $periodId = (int) ($_POST['registration_period_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'draft');
            if (!in_array($status, ['draft', 'open', 'closed'], true)) {
                throw new RuntimeException('Trạng thái không hợp lệ.');
            }
            if ($status === 'open') {
                $assigned = db()->prepare('SELECT COUNT(*) FROM registration_period_classes WHERE registration_period_id = ?');
                $assigned->execute([$periodId]);
                if ((int) $assigned->fetchColumn() === 0) {
                    throw new RuntimeException('Cần gán đợt đăng ký cho ít nhất một lớp trước khi mở.');
                }
            }
            db()->prepare('UPDATE registration_periods SET status = ? WHERE id = ?')->execute([$status, $periodId]);
            log_activity('set_period_status', 'Cập nhật trạng thái đợt #' . $periodId);
            flash('success', 'Đã cập nhật trạng thái đợt đăng ký.');
        }

        if ($action === 'update_dates') {
            $periodId = (int) ($_POST['registration_period_id'] ?? 0);
            db()->prepare(
                'UPDATE registration_periods
                 SET group_start = ?, group_end = ?, register_start = ?, register_end = ?
                 WHERE id = ?'
            )->execute([
                str_replace('T', ' ', (string) $_POST['group_start']) . ':00',
                str_replace('T', ' ', (string) $_POST['group_end']) . ':00',
                str_replace('T', ' ', (string) $_POST['register_start']) . ':00',
                str_replace('T', ' ', (string) $_POST['register_end']) . ':00',
                $periodId,
            ]);
            log_activity('extend_registration_period', 'Cập nhật thời gian đợt #' . $periodId);
            flash('success', 'Đã cập nhật thời gian đợt đăng ký.');
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

$page_title = 'Đợt đăng ký';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Đợt đăng ký</h1>
        <p>Thiết lập thời gian tạo nhóm, đăng ký đề tài và gán đợt cho lớp.</p>
    </div>
</section>

<section class="card-panel rp-create-card mb-4">
    <div class="panel-body">
        <div class="rp-form-header">
            <div class="rp-form-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <h2 class="rp-form-title">Tạo đợt mới</h2>
                <p class="rp-form-subtitle">Điền thông tin đợt đăng ký và thời gian áp dụng</p>
            </div>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <!-- Row 1: Tên đợt + Trạng thái -->
            <div class="rp-field-row">
                <div class="rp-field rp-field-grow">
                    <label class="rp-label">Tên đợt</label>
                    <div class="rp-input-wrap">
                        <span class="rp-input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </span>
                        <input class="rp-input" name="name" value="Đợt đăng ký đề tài bài tập lớn" required placeholder="Nhập tên đợt đăng ký">
                    </div>
                </div>
                <div class="rp-field" style="min-width:180px">
                    <label class="rp-label">Trạng thái</label>
                    <div class="rp-select-wrap">
                        <select class="rp-select" name="status">
                            <option value="draft">📝 Nháp</option>
                            <option value="open">🟢 Đang mở</option>
                            <option value="closed">🔴 Đã đóng</option>
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
                            <input class="rp-datetime" name="group_start" type="datetime-local" required>
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
                            <input class="rp-datetime" name="group_end" type="datetime-local" required>
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
                            <input class="rp-datetime" name="register_start" type="datetime-local" required>
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
                            <input class="rp-datetime" name="register_end" type="datetime-local" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rp-form-actions">
                <button class="rp-btn-submit" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tạo đợt đăng ký
                </button>
            </div>
        </form>
    </div>
</section>

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
                                <form class="rp-action-row" method="post">
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
                                <form class="rp-action-row" method="post">
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

                                <!-- Cập nhật giờ -->
                                <form class="rp-update-dates" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_dates">
                                    <input type="hidden" name="registration_period_id" value="<?= e((string) $period['id']) ?>">
                                    <div class="rp-mini-dates">
                                        <div class="rp-mini-date-group">
                                            <span class="rp-mini-label"><span class="rp-time-dot" style="background:#8b5cf6"></span> Nhóm</span>
                                            <div class="rp-mini-date-pair">
                                                <div class="rp-datetime-wrap rp-datetime-sm">
                                                    <input class="rp-datetime" name="group_start" type="datetime-local" value="<?= e(input_datetime($period['group_start'])) ?>">
                                                </div>
                                                <span class="rp-mini-sep">→</span>
                                                <div class="rp-datetime-wrap rp-datetime-sm">
                                                    <input class="rp-datetime" name="group_end" type="datetime-local" value="<?= e(input_datetime($period['group_end'])) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="rp-mini-date-group">
                                            <span class="rp-mini-label"><span class="rp-time-dot" style="background:#3b82f6"></span> Đăng ký</span>
                                            <div class="rp-mini-date-pair">
                                                <div class="rp-datetime-wrap rp-datetime-sm">
                                                    <input class="rp-datetime" name="register_start" type="datetime-local" value="<?= e(input_datetime($period['register_start'])) ?>">
                                                </div>
                                                <span class="rp-mini-sep">→</span>
                                                <div class="rp-datetime-wrap rp-datetime-sm">
                                                    <input class="rp-datetime" name="register_end" type="datetime-local" value="<?= e(input_datetime($period['register_end'])) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="rp-btn-action rp-btn-update" type="submit">Cập nhật giờ</button>
                                </form>
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
