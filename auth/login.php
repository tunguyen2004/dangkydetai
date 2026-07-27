<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (logged_in()) {
    redirect('dashboard.php');
}

if (is_post()) {
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        flash('danger', 'Email hoặc mật khẩu không đúng.');
    } elseif ((int) $user['is_locked'] === 1) {
        flash('danger', 'Tài khoản đang bị khóa. Vui lòng liên hệ quản trị viên.');
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        log_activity('login', 'Đăng nhập hệ thống');
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            redirect('auth/change_password.php');
        }
        redirect(post_login_redirect_path($user));
    }
}

$page_title = 'Đăng nhập';
$hide_site_chrome = true;
$main_class = 'auth-shell';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-intro">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset_url('assets/images/logo.svg')) ?>" alt="" aria-hidden="true">
        <span>Đăng ký đề tài</span>
    </a>
    <div>
        <p class="text-uppercase fw-bold text-warning mb-3">Project registration system</p>
        <h1>Không gian quản lý nhóm và đề tài học phần.</h1>
        <p class="lead">Một luồng rõ ràng: tạo nhóm, đăng ký đề tài, giảng viên duyệt và theo dõi trạng thái.</p>
    </div>
    <div class="auth-feature-list">
        <span>Quản lý nhóm</span>
        <span>Duyệt đề tài</span>
        <span>Kiểm soát ràng buộc</span>
    </div>
</section>
<section class="auth-form-area">
    <form class="auth-card" method="post">
        <?= csrf_field() ?>
        <p class="text-uppercase fw-bold text-warning mb-2">Đăng nhập hệ thống</p>
        <h2 class="fw-bold mb-3">Chào mừng trở lại</h2>
        <p class="text-muted">Dùng tài khoản admin, giảng viên hoặc sinh viên để vào đúng chức năng.</p>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($_POST['email'] ?? 'admin@k73.test') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-control" id="password" name="password" type="password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Đăng nhập</button>
        <p class="auth-secondary-link"><a href="<?= e(url('auth/forgot_password.php')) ?>">Quên mật khẩu?</a></p>
        <p class="text-muted mt-4 mb-0">
            Mẫu: admin@k73.test/admin123, giangvien@k73.test/gv123456, sv01@k73.test/sv123456.
        </p>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
