<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (logged_in()) {
    redirect('dashboard.php');
}

function active_password_reset_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT prt.id, prt.user_id, prt.expires_at, u.name, u.email, u.is_locked
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $record = $stmt->fetch();

    return $record && (int) $record['is_locked'] === 0 ? $record : null;
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$resetRecord = active_password_reset_token($token);

if (!$resetRecord) {
    flash('danger', 'Liên kết đặt lại mật khẩu không hợp lệ, đã được sử dụng hoặc đã hết hạn.');
    redirect('auth/forgot_password.php');
}

if (is_post()) {
    verify_csrf();

    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    try {
        if (strlen($newPassword) < 6) {
            throw new RuntimeException('Mật khẩu mới cần có ít nhất 6 ký tự.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Xác nhận mật khẩu không khớp.');
        }

        db()->beginTransaction();
        try {
            $lockedStmt = db()->prepare(
                'SELECT id, user_id FROM password_reset_tokens
                 WHERE id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()
                 FOR UPDATE'
            );
            $lockedStmt->execute([(int) $resetRecord['id'], hash('sha256', $token)]);
            $lockedRecord = $lockedStmt->fetch();

            if (!$lockedRecord) {
                throw new RuntimeException('Liên kết đặt lại mật khẩu đã được sử dụng hoặc đã hết hạn.');
            }

            db()->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $lockedRecord['user_id']]);

            db()->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
                ->execute([(int) $lockedRecord['id']]);

            db()->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                ->execute([(int) $lockedRecord['user_id']]);

            db()->prepare('INSERT INTO activity_logs (user_id, action, detail, created_at) VALUES (?, ?, ?, NOW())')
                ->execute([
                    (int) $lockedRecord['user_id'],
                    'reset_password_self_service',
                    'Đặt lại mật khẩu bằng liên kết một lần'
                ]);

            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        session_regenerate_id(true);

        flash('success', 'Đã đặt lại mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.');
        redirect('auth/login.php');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}

$page_title = 'Đặt lại mật khẩu';
$hide_site_chrome = true;
$main_class = 'auth-shell';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-intro auth-intro-compact">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt="" aria-hidden="true">
        <span>Đăng ký đề tài</span>
    </a>

    <div>
        <p class="text-uppercase fw-bold text-warning mb-3">Xác minh thành công</p>
        <h1>Chọn mật khẩu mới cho tài khoản.</h1>
        <p class="lead">
            Liên kết này chỉ được sử dụng một lần. Sau khi lưu mật khẩu mới,
            hãy đăng nhập lại bằng mật khẩu vừa tạo.
        </p>
    </div>
</section>

<section class="auth-form-area">
    <form class="auth-card" method="post">
        <?= csrf_field() ?>

        <input type="hidden" name="token" value="<?= e($token) ?>">

        <p class="text-uppercase fw-bold text-warning mb-2">Đặt lại mật khẩu</p>
        <h2 class="fw-bold mb-3">Tạo mật khẩu mới</h2>

        <p class="text-muted">
            Tài khoản: <?= e((string) $resetRecord['email']) ?>
        </p>

        <div class="mb-3">
            <label class="form-label" for="new-password">Mật khẩu mới</label>
            <input class="form-control" id="new-password" name="new_password" type="password" minlength="6"
                autocomplete="new-password" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label" for="confirm-password">Nhập lại mật khẩu mới</label>
            <input class="form-control" id="confirm-password" name="confirm_password" type="password" minlength="6"
                autocomplete="new-password" required>
        </div>

        <button class="btn btn-primary w-100" type="submit">
            Lưu mật khẩu mới
        </button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>