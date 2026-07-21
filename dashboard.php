<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$page_title = 'Dashboard';

$stats = [
    'students' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'groups' => (int) db()->query('SELECT COUNT(*) FROM student_groups')->fetchColumn(),
    'topics' => (int) db()->query('SELECT COUNT(*) FROM topic_classes')->fetchColumn(),
    'pending' => (int) db()->query("SELECT COUNT(*) FROM topic_registrations WHERE status = 'pending'")->fetchColumn(),
];

$teacherFilter = '';
$params = [];
if ($user['role'] === 'teacher') {
    $teacherFilter = ' WHERE c.teacher_id = ?';
    $params[] = (int) $user['id'];
}

$stmt = db()->prepare(
    "SELECT g.name AS group_name, c.name AS class_name, t.title AS topic_title, r.status, r.created_at
     FROM topic_registrations r
     JOIN student_groups g ON g.id = r.group_id
     JOIN topic_classes tc ON tc.id = r.topic_class_id
     JOIN classes c ON c.id = tc.class_id
     JOIN topics t ON t.id = tc.topic_id
     $teacherFilter
     ORDER BY r.created_at DESC
     LIMIT 8"
);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Dashboard</h1>
        <p>Theo dõi nhanh tình trạng nhóm, đề tài và đăng ký.</p>
    </div>
    <?php if ($user['role'] === 'student'): ?>
        <a class="btn btn-primary" href="<?= e(url('student/group.php')) ?>">Vào nhóm của bạn</a>
    <?php elseif ($user['role'] === 'teacher'): ?>
        <a class="btn btn-primary" href="<?= e(url('teacher/registrations.php')) ?>">Duyệt đăng ký</a>
    <?php endif; ?>
</section>

<section class="grid-4 mb-4">
    <article class="stat-card stat-blue"><span>Sinh viên</span><strong><?= e((string) $stats['students']) ?></strong><span class="stat-icon">🎓</span></article>
    <article class="stat-card stat-purple"><span>Nhóm</span><strong><?= e((string) $stats['groups']) ?></strong><span class="stat-icon">👥</span></article>
    <article class="stat-card stat-emerald"><span>Đề tài đã mở</span><strong><?= e((string) $stats['topics']) ?></strong><span class="stat-icon">📋</span></article>
    <article class="stat-card stat-amber"><span>Chờ duyệt</span><strong><?= e((string) $stats['pending']) ?></strong><span class="stat-icon">⏳</span></article>
</section>

<section class="card-panel">
    <div class="panel-body">
        <h2 class="h4 fw-bold mb-3">Đăng ký gần đây</h2>
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Nhóm</th>
                        <th>Lớp</th>
                        <th>Đề tài</th>
                        <th>Trạng thái</th>
                        <th>Ngày gửi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td><?= e($registration['group_name']) ?></td>
                            <td><?= e($registration['class_name']) ?></td>
                            <td><?= e($registration['topic_title']) ?></td>
                            <td><span class="<?= e(badge_class($registration['status'])) ?>"><?= e(status_label($registration['status'])) ?></span></td>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $registration['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$registrations): ?>
                        <tr><td colspan="5" class="empty-state">Chưa có đăng ký nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
