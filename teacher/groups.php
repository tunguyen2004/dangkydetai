<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

$user = current_user();
$stmt = db()->prepare(
    "SELECT g.*, c.name AS class_name, c.min_members, c.max_members,
            (
                SELECT GROUP_CONCAT(CONCAT(u.student_code, ' - ', u.name, IF(gm.role = 'leader', ' (Trưởng nhóm)', '')) ORDER BY gm.role DESC, u.name SEPARATOR '\n')
                FROM group_members gm
                JOIN users u ON u.id = gm.user_id
                WHERE gm.group_id = g.id
            ) AS members,
            r.status AS registration_status, t.title AS topic_title
     FROM student_groups g
     JOIN classes c ON c.id = g.class_id
     LEFT JOIN topic_registrations r ON r.id = (
        SELECT r2.id FROM topic_registrations r2 WHERE r2.group_id = g.id ORDER BY r2.id DESC LIMIT 1
     )
     LEFT JOIN topics t ON t.id = r.topic_id
     WHERE c.teacher_id = ?
     ORDER BY g.created_at DESC"
);
$stmt->execute([(int) $user['id']]);
$groups = $stmt->fetchAll();

$page_title = 'Danh sách nhóm';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-heading">
    <div>
        <h1>Danh sách nhóm</h1>
        <p>Theo dõi thành viên, số lượng và đề tài của từng nhóm.</p>
    </div>
</section>

<section class="card-panel">
    <div class="panel-body table-responsive">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Nhóm</th>
                    <th>Lớp</th>
                    <th>Thành viên</th>
                    <th>Đề tài</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <strong><?= e($group['name']) ?></strong><br>
                            <span class="text-muted">Mã tham gia: <?= e($group['join_code']) ?></span>
                        </td>
                        <td><?= e($group['class_name']) ?></td>
                        <td>
                            <div style="white-space: pre-line"><?= e($group['members'] ?: 'Chưa có thành viên') ?></div>
                            <small class="text-muted"><?= e((string) group_member_count((int) $group['id'])) ?>/<?= e((string) $group['max_members']) ?> thành viên</small>
                        </td>
                        <td><?= e($group['topic_title'] ?: 'Chưa đăng ký') ?></td>
                        <td>
                            <?php if ($group['registration_status']): ?>
                                <span class="<?= e(badge_class($group['registration_status'])) ?>"><?= e(status_label($group['registration_status'])) ?></span>
                            <?php else: ?>
                                <span class="badge-soft-secondary">Đang lập nhóm</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$groups): ?>
                    <tr><td colspan="5" class="empty-state">Chưa có nhóm nào trong lớp bạn phụ trách.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

