<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();

if (is_post()) {
    verify_csrf();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    try {
        if ((int) ($user['must_change_password'] ?? 0) !== 1 && !password_verify($currentPassword, (string) $user['password'])) {
            throw new RuntimeException('Mật khẩu hiện tại không đúng.');
        }
        if (strlen($newPassword) < 6) {
            throw new RuntimeException('Mật khẩu mới cần có ít nhất 6 ký tự.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Xác nhận mật khẩu không khớp.');
        }

        db()->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);
        log_activity('change_password', 'Đổi mật khẩu');
        flash('success', 'Đã đổi mật khẩu. Bạn có thể tiếp tục sử dụng hệ thống.');
        redirect(post_login_redirect_path($user));
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}

$page_title = 'Đổi mật khẩu';
$hide_site_chrome = (int) ($user['must_change_password'] ?? 0) === 1;
$main_class = $hide_site_chrome ? 'auth-shell' : 'page-shell';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($hide_site_chrome): ?>
    <section class="auth-intro">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt="" aria-hidden="true">
            <span>Đăng ký đề tài</span>
        </a>
        <div>
            <p class="text-uppercase fw-bold text-warning mb-3">Bảo mật tài khoản</p>
            <h1>Đổi mật khẩu trước khi tiếp tục.</h1>
            <p class="lead">Mật khẩu tạm chỉ dùng để đăng nhập lần đầu sau khi được cấp hoặc reset.</p>
        </div>
    </section>
    <section class="auth-form-area">
<?php else: ?>
    <section class="section-heading">
        <div>
            <h1>Đổi mật khẩu</h1>
            <p>Cập nhật mật khẩu đăng nhập của tài khoản.</p>
        </div>
    </section>
    <section>
<?php endif; ?>
    <form class="auth-card" method="post">
        <?= csrf_field() ?>
        <p class="text-uppercase fw-bold text-warning mb-2">Tài khoản</p>
        <h2 class="fw-bold mb-3">Đổi mật khẩu</h2>
        <?php if ((int) ($user['must_change_password'] ?? 0) !== 1): ?>
            <div class="mb-3">
                <label class="form-label">Mật khẩu hiện tại</label>
                <input class="form-control" name="current_password" type="password" required>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Mật khẩu mới</label>
            <input class="form-control" name="new_password" type="password" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nhập lại mật khẩu mới</label>
            <input class="form-control" name="confirm_password" type="password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Lưu mật khẩu mới</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
