<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

function next_user_code(string $role): ?string
{
    $prefix = [
        'admin' => 'AD',
        'teacher' => 'GV',
        'student' => 'SV',
    ][$role] ?? null;

    if ($prefix === null) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT user_code
         FROM users
         WHERE user_code LIKE ?
         ORDER BY CAST(SUBSTRING(user_code, 3) AS UNSIGNED) DESC
         LIMIT 1'
    );
    $stmt->execute([$prefix . '%']);
    $lastCode = (string) ($stmt->fetchColumn() ?: '');
    $lastNumber = preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $matches)
        ? (int) $matches[1]
        : 0;

    return $prefix . str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $role = (string) ($_POST['role'] ?? 'student');
            $password = (string) ($_POST['password'] ?? '');
            $phone = trim((string) ($_POST['phone'] ?? ''));

            if ($name === '' || $email === '' || $password === '' || !in_array($role, ['admin', 'teacher', 'student'], true)) {
                throw new RuntimeException('Vui lòng nhập đủ thông tin tài khoản.');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Email không đúng định dạng.');
            }
            if (preg_match('/^\d{10}$/', $phone) !== 1) {
                throw new RuntimeException('Số điện thoại phải gồm đúng 10 chữ số.');
            }

            $emailExists = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $emailExists->execute([$email]);
            if ((int) $emailExists->fetchColumn() > 0) {
                throw new RuntimeException('Email này đã được sử dụng. Vui lòng nhập email khác.');
            }

            $userCode = next_user_code($role);

            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password, role, user_code, phone)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $userCode,
                $phone,
            ]);
            log_activity('create_user', 'Tạo tài khoản ' . $email . ' với mã ' . $userCode);
            flash('success', 'Đã tạo tài khoản mới. Mã người dùng: ' . $userCode);
        }

        if ($action === 'toggle_lock') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) current_user()['id']) {
                throw new RuntimeException('Không thể tự khóa tài khoản đang dùng.');
            }
            db()->prepare('UPDATE users SET is_locked = 1 - is_locked WHERE id = ?')->execute([$userId]);
            log_activity('toggle_user_lock', 'Khóa/mở tài khoản #' . $userId);
            flash('success', 'Đã cập nhật trạng thái tài khoản.');
        }

        if ($action === 'reset_password') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            db()->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?')->execute([password_hash('123456', PASSWORD_DEFAULT), $userId]);
            log_activity('reset_password', 'Reset mật khẩu tài khoản #' . $userId);
            flash('success', 'Đã đặt lại mật khẩu thành 123456. Người dùng sẽ phải đổi mật khẩu sau khi đăng nhập.');
        }
    } catch (PDOException $exception) {
        flash('danger', 'Không thể lưu dữ liệu. Email hoặc mã người dùng có thể đã tồn tại.');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('admin/users.php');
}

$users = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
$page_title = 'Quản lý tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Quản lý tài khoản</h1>
        <p>Admin tạo tài khoản, khóa/mở và reset mật khẩu cho người dùng.</p>
    </div>
</section>

<section class="card-panel mb-4">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Thêm tài khoản</h2>
        <form class="row g-3" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Họ tên</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input class="form-control" name="email" type="email" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vai trò</label>
                <select class="form-select" name="role">
                    <option value="student">Sinh viên</option>
                    <option value="teacher">Giảng viên</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mật khẩu</label>
                <input class="form-control" name="password" value="123456" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">SĐT</label>
                <input class="form-control" name="phone" type="tel" inputmode="numeric" autocomplete="tel"
                    pattern="[0-9]{10}" minlength="10" maxlength="10" data-digits-only required
                    placeholder="Ví dụ: 0912345678" title="Số điện thoại gồm đúng 10 chữ số">
            </div>
            <div class="col-md-3">
                <label class="form-label">Mã người dùng</label>
                <input class="form-control" value="Tự sinh theo vai trò" disabled>
                <div class="form-text">Sinh viên: SV001, Giảng viên: GV001, Admin: AD001.</div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Tạo tài khoản</button>
            </div>
        </form>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean admin-mobile-table users-mobile-table">
                <thead>
                    <tr>
                        <th>Người dùng</th>
                        <th>Vai trò</th>
                        <th>Mã</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['name']) ?></strong><br>
                                <span class="text-muted"><?= e($row['email']) ?></span>
                            </td>
                            <td><?= e(role_label($row['role'])) ?></td>
                            <td><?= e($row['user_code'] ?: '-') ?></td>
                            <td>
                                <span class="<?= (int) $row['is_locked'] === 1 ? 'badge-soft-danger' : 'badge-soft-success' ?>">
                                    <?= (int) $row['is_locked'] === 1 ? 'Đang khóa' : 'Hoạt động' ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_lock">
                                        <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $row['is_locked'] === 1 ? 'Mở khóa' : 'Khóa' ?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Reset mật khẩu tài khoản này thành 123456?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
                                        <button class="btn btn-sm btn-outline-warning" type="submit">Reset mật khẩu</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
