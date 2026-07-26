<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function activity_action_label(string $action): string
{
    return [
        'login' => 'Đăng nhập',
        'change_password' => 'Đổi mật khẩu',
        'create_user' => 'Tạo tài khoản',
        'toggle_user_lock' => 'Khóa / mở tài khoản',
        'reset_password' => 'Reset mật khẩu',
        'create_course' => 'Tạo học phần',
        'create_class' => 'Tạo lớp học phần',
        'update_class' => 'Cập nhật lớp học phần',
        'delete_class' => 'Xóa lớp học phần',
        'add_class_students' => 'Gán sinh viên vào lớp',
        'create_registration_period' => 'Tạo đợt đăng ký',
        'update_registration_period' => 'Cập nhật đợt đăng ký',
        'set_period_status' => 'Đổi trạng thái đợt',
        'extend_registration_period' => 'Cập nhật thời gian đợt',
        'assign_period_class' => 'Gán lớp vào đợt',
        'auto_close_registration_period' => 'Tự động đóng đợt',
        'create_topic' => 'Tạo đề tài',
        'assign_topic_class' => 'Mở đề tài cho lớp',
        'create_group' => 'Tạo nhóm',
        'teacher_create_group' => 'Giảng viên tạo nhóm',
        'invite_member' => 'Mời thành viên',
        'teacher_add_group_member' => 'Giảng viên thêm thành viên',
        'register_topic' => 'Đăng ký đề tài',
        'cancel_registration' => 'Hủy đăng ký đề tài',
        'review_registration' => 'Duyệt đăng ký đề tài',
    ][$action] ?? ucwords(str_replace('_', ' ', $action));
}

function activity_date_is_valid(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$search = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

if ($dateFrom !== '' && !activity_date_is_valid($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !activity_date_is_valid($dateTo)) {
    $dateTo = '';
}

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(l.action LIKE ? OR l.detail LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.user_code LIKE ?)';
    $keyword = '%' . $search . '%';
    array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword);
}
if ($action !== '') {
    $conditions[] = 'l.action = ?';
    $params[] = $action;
}
if ($dateFrom !== '') {
    $conditions[] = 'l.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $conditions[] = 'l.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $dateTo;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$baseQuery = ' FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id';

$countStmt = db()->prepare('SELECT COUNT(*)' . $baseQuery . $where);
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$logsStmt = db()->prepare(
    'SELECT l.*, u.name AS user_name, u.email AS user_email, u.user_code, u.role AS user_role'
    . $baseQuery . $where . " ORDER BY l.created_at DESC, l.id DESC LIMIT {$perPage} OFFSET {$offset}"
);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$todayLogs = (int) db()->query('SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$systemLogs = (int) db()->query('SELECT COUNT(*) FROM activity_logs WHERE user_id IS NULL')->fetchColumn();

$queryUrl = static function (int $targetPage) use ($search, $action, $dateFrom, $dateTo): string {
    $query = array_filter([
        'q' => $search,
        'action' => $action,
        'from' => $dateFrom,
        'to' => $dateTo,
        'page' => $targetPage > 1 ? $targetPage : null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');

    $path = url('admin/activity_logs.php');
    return $query ? $path . '?' . http_build_query($query) : $path;
};

$page_title = 'Nhật ký hoạt động';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Nhật ký hoạt động</h1>
        <p>Tra cứu các thao tác quan trọng để kiểm tra người thực hiện và thời điểm phát sinh.</p>
    </div>
</section>

<section class="activity-summary">
    <article class="activity-summary-card">
        <span>Kết quả hiển thị</span>
        <strong><?= e((string) $totalLogs) ?></strong>
    </article>
    <article class="activity-summary-card">
        <span>Hoạt động hôm nay</span>
        <strong><?= e((string) $todayLogs) ?></strong>
    </article>
    <article class="activity-summary-card">
        <span>Tác vụ tự động</span>
        <strong><?= e((string) $systemLogs) ?></strong>
    </article>
</section>

<section class="card-panel activity-filter-card">
    <div class="panel-body">
        <form class="activity-filter-form" method="get">
            <div>
                <label class="form-label" for="activity-search">Tìm kiếm</label>
                <input class="form-control" id="activity-search" name="q" value="<?= e($search) ?>"
                    placeholder="Người dùng, email, nội dung...">
            </div>
            <div>
                <label class="form-label" for="activity-action">Thao tác</label>
                <select class="form-select" id="activity-action" name="action">
                    <option value="">Tất cả thao tác</option>
                    <?php foreach ($actions as $actionOption): ?>
                        <option value="<?= e((string) $actionOption) ?>" <?= $action === $actionOption ? 'selected' : '' ?>>
                            <?= e(activity_action_label((string) $actionOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="activity-from">Từ ngày</label>
                <input class="form-control" id="activity-from" name="from" type="date" value="<?= e($dateFrom) ?>">
            </div>
            <div>
                <label class="form-label" for="activity-to">Đến ngày</label>
                <input class="form-control" id="activity-to" name="to" type="date" value="<?= e($dateTo) ?>">
            </div>
            <div class="activity-filter-actions">
                <button class="btn btn-primary" type="submit">Lọc</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/activity_logs.php')) ?>">Xóa lọc</a>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean admin-mobile-table activity-log-mobile-table">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Người thực hiện</th>
                    <th>Thao tác</th>
                    <th>Nội dung</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $isSystem = $log['user_id'] === null;
                    $actorName = $isSystem ? 'Hệ thống' : (string) ($log['user_name'] ?: 'Tài khoản đã xóa');
                    $actorMeta = $isSystem
                        ? 'Tác vụ tự động'
                        : trim((string) (($log['user_code'] ?: '') . ($log['user_email'] ? ' · ' . $log['user_email'] : '')));
                    $initial = $isSystem ? 'HT' : mb_strtoupper(mb_substr($actorName, 0, 1));
                    ?>
                    <tr>
                        <td class="activity-time">
                            <strong><?= e(date('d/m/Y', strtotime((string) $log['created_at']))) ?></strong>
                            <small><?= e(date('H:i:s', strtotime((string) $log['created_at']))) ?></small>
                        </td>
                        <td>
                            <div class="activity-actor">
                                <span class="activity-avatar <?= $isSystem ? 'is-system' : '' ?>"><?= e($initial) ?></span>
                                <span>
                                    <strong><?= e($actorName) ?></strong>
                                    <small><?= e($actorMeta ?: role_label((string) ($log['user_role'] ?? ''))) ?></small>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="activity-action">
                                <span class="badge-soft-info"><?= e(activity_action_label((string) $log['action'])) ?></span>
                                <code><?= e((string) $log['action']) ?></code>
                            </div>
                        </td>
                        <td class="activity-detail"><?= e((string) $log['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="4" class="empty-state">Chưa tìm thấy nhật ký phù hợp.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="activity-pagination" aria-label="Phân trang nhật ký">
                <span>Trang <?= e((string) $page) ?> / <?= e((string) $totalPages) ?></span>
                <div class="activity-pagination-links">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline-secondary" href="<?= e($queryUrl($page - 1)) ?>">Trang trước</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-outline-secondary" href="<?= e($queryUrl($page + 1)) ?>">Trang sau</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
