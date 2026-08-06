<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_administrator();

$pageTitle = 'Admin Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id === (int) $_SESSION['admin_id'] && in_array($action, ['disable', 'delete'], true)) {
        flash_error('You cannot disable or delete your own account while logged in.');
        redirect('/admin/users.php');
    }

    if ($action === 'disable' && $id) {
        db()->prepare('UPDATE admin_users SET is_active = 0 WHERE id = :id')->execute(['id' => $id]);
        audit_log('user.disable', "Disabled admin user #$id");
        flash_success('User disabled.');
    } elseif ($action === 'enable' && $id) {
        db()->prepare('UPDATE admin_users SET is_active = 1 WHERE id = :id')->execute(['id' => $id]);
        audit_log('user.enable', "Enabled admin user #$id");
        flash_success('User enabled.');
    } elseif ($action === 'delete' && $id) {
        $target = db()->prepare('SELECT role FROM admin_users WHERE id = :id');
        $target->execute(['id' => $id]);
        $row = $target->fetch();
        if ($row && $row['role'] === 'administrator') {
            $adminCount = (int) db()->query("SELECT COUNT(*) c FROM admin_users WHERE role='administrator' AND is_active=1")->fetch()['c'];
            if ($adminCount <= 1) {
                flash_error('You cannot delete the last remaining administrator account.');
                redirect('/admin/users.php');
            }
        }
        db()->prepare('DELETE FROM admin_users WHERE id = :id')->execute(['id' => $id]);
        audit_log('user.delete', "Deleted admin user #$id");
        flash_success('User deleted.');
    }
    redirect('/admin/users.php');
}

$users = db()->query('SELECT * FROM admin_users ORDER BY full_name ASC')->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">Admin Users</h2>
        <a class="btn btn--primary" href="<?= site_url('/admin/users-edit.php') ?>">+ Add User</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['full_name']) ?><?= (int) $u['id'] === (int) $_SESSION['admin_id'] ? ' <span class="tag">You</span>' : '' ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><span class="status-pill status-pill--<?= $u['is_active'] ? 'published' : 'draft' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
                    <td><?= $u['last_login_at'] ? e(format_date($u['last_login_at'], 'j M Y, g:ia')) : 'Never' ?></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/users-edit.php?id=' . $u['id']) ?>">Edit</a>
                        <?php if ((int) $u['id'] !== (int) $_SESSION['admin_id']): ?>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="action" value="<?= $u['is_active'] ? 'disable' : 'enable' ?>">
                            <button type="submit" class="btn btn--small btn--secondary"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Permanently delete the account for '<?= e($u['full_name']) ?>'?">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
