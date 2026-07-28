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

function is_local_environment(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = explode(':', $host)[0];

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function app_absolute_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host;
}

function password_reset_url(string $token): string
{
    return app_absolute_url() . url('auth/reset_password.php?token=' . rawurlencode($token));
}

function smtp_is_configured(): bool
{
    foreach (['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL'] as $constant) {
        if (!defined($constant) || trim((string) constant($constant)) === '') {
            return false;
        }
    }

    return defined('SMTP_PORT') && (int) SMTP_PORT > 0;
}

function send_password_reset_email(string $email, string $name, string $resetUrl): bool
{
    $autoload = ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!smtp_is_configured() || !is_file($autoload)) {
        return false;
    }

    require_once $autoload;

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $mailer->Port = (int) SMTP_PORT;
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USERNAME;
        $mailer->Password = SMTP_PASSWORD;
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = 15;
        $mailer->SMTPDebug = 3;
        $mailer->Debugoutput = function ($str, $level) {
            error_log("SMTP DEBUG [$level]: $str");
        };
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mailer->SMTPSecure = strtolower((string) (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls')) === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->setFrom(SMTP_FROM_EMAIL, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME);
        $mailer->addAddress($email, $name);
        $mailer->isHTML(false);
        $mailer->Subject = 'Đặt lại mật khẩu - ' . APP_NAME;
        $mailer->Body = "Chào " . $name . ",\n\n"
            . "Bạn đã yêu cầu đặt lại mật khẩu. Mở liên kết sau để đặt mật khẩu mới:\n"
            . $resetUrl . "\n\n"
            . "Liên kết có hiệu lực trong 15 phút và chỉ dùng được một lần. Nếu bạn không yêu cầu, vui lòng bỏ qua email này.";
        $mailer->send();

        return true;
    } catch (Throwable $exception) {
        error_log('Password reset email failed: ' . $exception->getMessage());
        return false;
    }
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
        'cancelled' => 'Đã hủy',
        'revoked' => 'Hủy duyệt',
        'open' => 'Đang mở',
        'closed' => 'Đã đóng',
        'draft' => 'Nháp',
        'forming' => 'Đang lập nhóm',
        'registered' => 'Đã đăng ký',
        'locked' => 'Đã khóa',
        'accepted' => 'Đã chấp nhận',
        'expired' => 'Hết hiệu lực',
    ][$status] ?? $status;
}

function badge_class(string $status): string
{
    return [
        'pending' => 'badge-soft-warning',
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'cancelled' => 'badge-soft-secondary',
        'revoked' => 'badge-soft-danger',
        'open' => 'badge-soft-success',
        'closed' => 'badge-soft-secondary',
        'draft' => 'badge-soft-secondary',
        'forming' => 'badge-soft-info',
        'registered' => 'badge-soft-warning',
        'locked' => 'badge-soft-success',
        'accepted' => 'badge-soft-success',
        'expired' => 'badge-soft-secondary',
    ][$status] ?? 'badge-soft-secondary';
}

function active_registration_period(): ?array
{
    $stmt = db()->query("SELECT * FROM registration_periods WHERE status = 'open' ORDER BY id DESC LIMIT 1");
    $period = $stmt->fetch();

    return $period ?: null;
}

function close_expired_registration_periods(): int
{
    $expired = db()->query(
        "SELECT id, name
         FROM registration_periods
         WHERE status = 'open'
           AND GREATEST(group_end, register_end) < NOW()"
    )->fetchAll();

    if (!$expired) {
        return 0;
    }

    $update = db()->prepare("UPDATE registration_periods SET status = 'closed' WHERE id = ? AND status = 'open'");
    $log = db()->prepare(
        "INSERT INTO activity_logs (user_id, action, detail, created_at)
         VALUES (NULL, 'auto_close_registration_period', ?, NOW())"
    );
    $closedCount = 0;

    foreach ($expired as $period) {
        $update->execute([(int) $period['id']]);

        if ($update->rowCount() === 1) {
            $log->execute(['Tự động đóng đợt đăng ký #' . $period['id'] . ' - ' . $period['name'] . ' vì đã hết thời gian.']);
            $closedCount++;
        }
    }

    return $closedCount;
}

function time_between(?string $start, ?string $end): bool
{
    if (!$start || !$end) {
        return false;
    }

    $now = time();
    return $now >= strtotime($start) && $now <= strtotime($end);
}

function today_between(?string $start, ?string $end): bool
{
    return time_between($start, $end);
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function log_activity(string $action, string $detail): void
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, detail, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$user['id'] ?? null, $action, $detail]);
}

function random_join_code(): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = db()->prepare('SELECT COUNT(*) FROM student_groups WHERE join_code = ?');
        $stmt->execute([$code]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $code;
}

function student_group(int $studentId): ?array
{
    $stmt = db()->prepare(
        "SELECT g.*, c.name AS class_name, c.min_members, c.max_members, c.teacher_id,
                p.name AS period_name, p.group_start, p.group_end, p.register_start, p.register_end, p.status AS period_status,
                co.code AS course_code, co.name AS course_name
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         JOIN classes c ON c.id = g.class_id
         JOIN courses co ON co.id = c.course_id
         JOIN registration_periods p ON p.id = g.registration_period_id
         WHERE gm.user_id = ?
         ORDER BY g.created_at DESC, g.id DESC
         LIMIT 1"
    );
    $stmt->execute([$studentId]);
    $group = $stmt->fetch();

    return $group ?: null;
}

function student_groups(int $studentId): array
{
    $stmt = db()->prepare(
        "SELECT g.*, c.name AS class_name, c.min_members, c.max_members, c.teacher_id,
                p.name AS period_name, p.group_start, p.group_end, p.register_start, p.register_end, p.status AS period_status,
                co.code AS course_code, co.name AS course_name
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         JOIN classes c ON c.id = g.class_id
         JOIN courses co ON co.id = c.course_id
         JOIN registration_periods p ON p.id = g.registration_period_id
         WHERE gm.user_id = ?
         ORDER BY p.created_at DESC, c.name, g.name"
    );
    $stmt->execute([$studentId]);

    return $stmt->fetchAll();
}

function student_group_for_context(int $studentId, int $classId, int $periodId): ?array
{
    $stmt = db()->prepare(
        "SELECT g.*
         FROM group_members gm
         JOIN student_groups g ON g.id = gm.group_id
         WHERE gm.user_id = ? AND g.class_id = ? AND g.registration_period_id = ?
         LIMIT 1"
    );
    $stmt->execute([$studentId, $classId, $periodId]);
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
        "SELECT r.*, t.title AS topic_title, t.code AS topic_code, t.description AS topic_description,
                t.min_members AS topic_min_members, t.max_members AS topic_max_members,
                tc.max_groups AS topic_max_groups, tc.status AS topic_class_status
         FROM topic_registrations r
         JOIN topic_classes tc ON tc.id = r.topic_class_id
         JOIN topics t ON t.id = tc.topic_id
         WHERE r.group_id = ?
         ORDER BY r.id DESC
         LIMIT 1"
    );
    $stmt->execute([$groupId]);
    $registration = $stmt->fetch();

    return $registration ?: null;
}

function group_active_registration(int $groupId): ?array
{
    $stmt = db()->prepare(
        "SELECT r.*, t.title AS topic_title, t.code AS topic_code
         FROM topic_registrations r
         JOIN topic_classes tc ON tc.id = r.topic_class_id
         JOIN topics t ON t.id = tc.topic_id
         WHERE r.group_id = ? AND r.status IN ('pending', 'approved')
         ORDER BY r.id DESC
         LIMIT 1"
    );
    $stmt->execute([$groupId]);
    $registration = $stmt->fetch();

    return $registration ?: null;
}

function topic_class_active_count(int $topicClassId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM topic_registrations WHERE topic_class_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$topicClassId]);

    return (int) $stmt->fetchColumn();
}

function notification_summary(array $user): array
{
    $summary = ['total' => 0, 'pending_registrations' => 0, 'pending_invites' => 0];

    if ($user['role'] === 'teacher') {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
             FROM topic_registrations r
             JOIN topic_classes tc ON tc.id = r.topic_class_id
             JOIN classes c ON c.id = tc.class_id
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
