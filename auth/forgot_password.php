<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (logged_in()) {
    redirect('dashboard.php');
}

$previewLink = (string) ($_SESSION['password_reset_preview_link'] ?? '');
unset($_SESSION['password_reset_preview_link']);

if (is_post()) {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $previewLink = '';

    try {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $userStmt = db()->prepare('SELECT id, name, email, is_locked FROM users WHERE email = ? LIMIT 1');
            $userStmt->execute([$email]);
            $user = $userStmt->fetch();

            if ($user && (int) $user['is_locked'] === 0) {
                $recentStmt = db()->prepare(
                    'SELECT COUNT(*) FROM password_reset_tokens
                     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
                );
                $recentStmt->execute([(int) $user['id']]);

                if ((int) $recentStmt->fetchColumn() === 0) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    db()->beginTransaction();
                    try {
                        db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL')
                            ->execute([(int) $user['id']]);
                        db()->prepare(
                            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip)
                             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?)'
                        )->execute([(int) $user['id'], $tokenHash, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
                        db()->commit();
                    } catch (Throwable $exception) {
                        if (db()->inTransaction()) {
                            db()->rollBack();
                        }
                        throw $exception;
                    }

                    $resetUrl = password_reset_url($token);
                    $mailSent = send_password_reset_email((string) $user['email'], (string) $user['name'], $resetUrl);

                    if (!$mailSent && is_local_environment()) {
                        $previewLink = $resetUrl;
                        $_SESSION['password_reset_preview_link'] = $previewLink;
                    } elseif (!$mailSent) {
                        db()->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')->execute([$tokenHash]);
                    }

                    db()->prepare('INSERT INTO activity_logs (user_id, action, detail, created_at) VALUES (?, ?, ?, NOW())')
                        ->execute([(int) $user['id'], 'request_password_reset', 'Yêu cầu đặt lại mật khẩu']);
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Password reset request failed: ' . $exception->getMessage());
        $previewLink = '';
    }

    flash('success', 'Nếu email tồn tại và tài khoản đang hoạt động, hệ thống đã gửi hướng dẫn đặt lại mật khẩu.');
    $_SESSION['password_reset_preview_link'] = $previewLink;
    redirect('auth/forgot_password.php');
}

$page_title = 'Quên mật khẩu';
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
        <p class="text-uppercase fw-bold text-warning mb-3">Khôi phục tài khoản</p>
        <h1>Đặt lại mật khẩu một cách an toàn.</h1>
        <p class="lead">
            Hệ thống sẽ gửi liên kết đặt lại mật khẩu có hiệu lực trong 15 phút.
            Liên kết chỉ được sử dụng một lần.
        </p>
    </div>
</section>

<section class="auth-form-area">
    <form class="auth-card" method="post">
        <?= csrf_field() ?>

        <p class="text-uppercase fw-bold text-warning mb-2">Quên mật khẩu</p>
        <h2 class="fw-bold mb-3">Nhập email tài khoản</h2>
        <p class="text-muted">
            Nếu email hợp lệ, hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.
            Để bảo mật, chúng tôi không xác nhận email có tồn tại hay không.
        </p>

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" autocomplete="email" required autofocus>
        </div>

        <button class="btn btn-primary w-100" type="submit">Gửi liên kết đặt lại</button>

        <p class="auth-secondary-link">
            <a href="<?= e(url('auth/login.php')) ?>">Quay lại đăng nhập</a>
        </p>

        <?php if ($previewLink !== ''): ?>
            <div class="reset-preview" role="status">
                <strong>Chế độ Local:</strong> Hệ thống email chưa được cấu hình, hãy sử dụng liên kết bên dưới để kiểm
                thử.<br>
                <a href="<?= e($previewLink) ?>">Đặt lại mật khẩu</a>
            </div>
        <?php endif; ?>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>