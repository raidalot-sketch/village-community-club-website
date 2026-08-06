<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_administrator();

$pageTitle = 'Audit Log';

$total = (int) db()->query('SELECT COUNT(*) c FROM audit_log')->fetch()['c'];
$p = paginate($total, 40);
$stmt = db()->prepare('SELECT al.*, u.full_name, u.username FROM audit_log al
    LEFT JOIN admin_users u ON u.id = al.admin_id
    ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue('limit', $p['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $p['offset'], PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header"><h2 style="margin:0;">Audit Log</h2></div>
    <p style="color:var(--color-text-muted);">A record of significant actions taken in the admin area, for accountability and troubleshooting.</p>

    <?php if (empty($logs)): ?>
        <p class="empty-state">No activity recorded yet.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(format_date($log['created_at'], 'j M Y, g:ia')) ?></td>
                    <td><?= e($log['full_name'] ?: 'Deleted user') ?></td>
                    <td><?= e($log['action']) ?></td>
                    <td><?= e($log['details']) ?></td>
                    <td><?= e($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($p['page'], $p['totalPages']) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
