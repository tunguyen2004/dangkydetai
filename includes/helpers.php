<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_base_url(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $project = basename(ROOT_PATH);
    $needle = '/' . $project;
    $position = strpos($script, $needle);

    return $position === false ? '' : substr($script, 0, $position + strlen($needle));
}

function url(string $path = ''): string
{
    $base = rtrim(app_base_url(), '/');
    $path = ltrim($path, '/');

    return $path === '' ? ($base === '' ? '/' : $base . '/') : $base . '/' . $path;
}

function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url($path) . '?v=' . rawurlencode($version);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';

    if ($token === '' || $known === '' || !hash_equals($known, $token)) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && (int) $cachedUser['id'] === (int) $_SESSION['user_id']) {
        return $cachedUser;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_locked'] === 1) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

function logged_in(): bool
{
    return current_user() !== null;
}

function role_label(string $role): string
{
    return [
        'admin' => 'Quản trị viên',
        'teacher' => 'Giảng viên',
        'student' => 'Sinh viên',
    ][$role] ?? 'Khách';
}

function has_role(array|string $roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], (array) $roles, true);
}

function require_login(): void
{
    if (!logged_in()) {
        flash('warning', 'Bạn cần đăng nhập để truy cập chức năng này.');
        redirect('auth/login.php');
    }
}

function require_role(array|string $roles): void
{
    require_login();

    if (!has_role($roles)) {
        flash('danger', 'Tài khoản của bạn không có quyền truy cập chức năng này.');
        redirect('dashboard.php');
    }
}

function post_login_redirect_path(array $user): string
{
    return match ($user['role']) {
        'admin' => 'admin/users.php',
        'teacher' => 'teacher/registrations.php',
        default => 'student/group.php',
    };
}

function status_label(string $status): string
{
    return [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'revision' => 'Cần chỉnh sửa',
        'open' => 'Đang mở',
        'closed' => 'Đã đóng',
        'draft' => 'Nháp',
    ][$status] ?? $status;
}

function badge_class(string $status): string
{
    return [
        'pending' => 'badge-soft-warning',
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'revision' => 'badge-soft-info',
        'open' => 'badge-soft-success',
        'closed' => 'badge-soft-secondary',
        'draft' => 'badge-soft-secondary',
    ][$status] ?? 'badge-soft-secondary';
}

function active_semester(): ?array
{
    $stmt = db()->query("SELECT * FROM semesters WHERE status = 'open' ORDER BY id DESC LIMIT 1");
    $semester = $stmt->fetch();

    return $semester ?: null;
}

function today_between(?string $start, ?string $end): bool
{
    if (!$start || !$end) {
        return false;
    }

    $today = date('Y-m-d');
    return $today >= $start && $today <= $end;
}

function log_activity(string $action, string $detail): void
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, detail, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$user['id'] ?? null, $action, $detail]);
}

function student_group(int $studentId): ?array
{
    $stmt = db()->prepare(
        "SELECT g.*, c.name AS class_name, c.min_members, c.max_members, c.teacher_id
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         JOIN classes c ON c.id = g.class_id
         WHERE gm.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$studentId]);
    $group = $stmt->fetch();

    return $group ?: null;
}

function group_member_count(int $groupId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
    $stmt->execute([$groupId]);

    return (int) $stmt->fetchColumn();
}

function is_group_leader(int $groupId, int $userId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ? AND role = 'leader'");
    $stmt->execute([$groupId, $userId]);

    return (int) $stmt->fetchColumn() > 0;
}

function group_registration(int $groupId): ?array
{
    $stmt = db()->prepare(
        "SELECT r.*, t.title AS topic_title, t.code AS topic_code
         FROM topic_registrations r
         JOIN topics t ON t.id = r.topic_id
         WHERE r.group_id = ?
         ORDER BY r.id DESC
         LIMIT 1"
    );
    $stmt->execute([$groupId]);
    $registration = $stmt->fetch();

    return $registration ?: null;
}

function topic_approved_count(int $topicId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM topic_registrations WHERE topic_id = ? AND status = 'approved'");
    $stmt->execute([$topicId]);

    return (int) $stmt->fetchColumn();
}

function notification_summary(array $user): array
{
    $summary = ['total' => 0, 'pending_registrations' => 0, 'pending_invites' => 0];

    if ($user['role'] === 'teacher') {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
             FROM topic_registrations r
             JOIN student_groups g ON g.id = r.group_id
             JOIN classes c ON c.id = g.class_id
             WHERE c.teacher_id = ? AND r.status = 'pending'"
        );
        $stmt->execute([(int) $user['id']]);
        $summary['pending_registrations'] = (int) $stmt->fetchColumn();
    }

    if ($user['role'] === 'student') {
        $stmt = db()->prepare("SELECT COUNT(*) FROM group_invitations WHERE invited_user_id = ? AND status = 'pending'");
        $stmt->execute([(int) $user['id']]);
        $summary['pending_invites'] = (int) $stmt->fetchColumn();
    }

    $summary['total'] = $summary['pending_registrations'] + $summary['pending_invites'];

    return $summary;
}
