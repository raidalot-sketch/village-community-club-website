<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$pageTitle = 'Committee Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('SELECT photo FROM committee_members WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            delete_upload($row['photo']);
            db()->prepare('DELETE FROM committee_members WHERE id = :id')->execute(['id' => $id]);
            audit_log('committee.delete', "Deleted committee member #$id");
            flash_success('Committee member removed.');
        }
    } elseif ($action === 'toggle_publish' && $id) {
        $current = db()->prepare('SELECT is_published FROM committee_members WHERE id = :id');
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if ($row) {
            db()->prepare('UPDATE committee_members SET is_published = :p WHERE id = :id')->execute(['p' => $row['is_published'] ? 0 : 1, 'id' => $id]);
            flash_success('Profile updated.');
        }
    }
    redirect('/admin/committee.php');
}

$members = db()->query('SELECT * FROM committee_members ORDER BY sort_order ASC, id ASC')->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">Committee Members</h2>
        <a class="btn btn--primary" href="<?= site_url('/admin/committee-edit.php') ?>">+ Add Committee Member</a>
    </div>

    <?php if (empty($members)): ?>
        <p class="empty-state">No committee members added yet.</p>
    <?php else: ?>
    <p style="color:var(--color-text-muted);">Drag to reorder how members appear on the public Committee page.</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th></th><th></th><th>Name</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody data-reorder-url="<?= site_url('/admin/committee-reorder.php') ?>">
            <?php foreach ($members as $m): ?>
                <tr data-reorder-id="<?= $m['id'] ?>">
                    <td class="drag-handle" title="Drag to reorder">&#9776;</td>
                    <td><img class="thumb-preview" style="border-radius:50%;" src="<?= e($m['photo'] ? upload_url($m['photo']) : asset_url('images/placeholders/person-placeholder.svg')) ?>" alt=""></td>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e($m['role_title']) ?></td>
                    <td><span class="status-pill status-pill--<?= $m['is_published'] ? 'published' : 'draft' ?>"><?= $m['is_published'] ? 'Visible' : 'Hidden' ?></span></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/committee-edit.php?id=' . $m['id']) ?>">Edit</a>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="action" value="toggle_publish">
                            <button type="submit" class="btn btn--small btn--secondary"><?= $m['is_published'] ? 'Hide' : 'Show' ?></button>
                        </form>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Remove '<?= e($m['name']) ?>' from the committee list?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
