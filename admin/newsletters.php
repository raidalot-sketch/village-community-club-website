<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$pageTitle = 'Newsletters';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('SELECT pdf_path, cover_image FROM newsletters WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            delete_upload($row['pdf_path']);
            delete_upload($row['cover_image']);
            db()->prepare('DELETE FROM newsletters WHERE id = :id')->execute(['id' => $id]);
            audit_log('newsletter.delete', "Deleted newsletter #$id");
            flash_success('Newsletter deleted.');
        }
    } elseif ($action === 'toggle_publish' && $id) {
        $current = db()->prepare('SELECT is_published FROM newsletters WHERE id = :id');
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if ($row) {
            db()->prepare('UPDATE newsletters SET is_published = :p WHERE id = :id')->execute(['p' => $row['is_published'] ? 0 : 1, 'id' => $id]);
            flash_success('Newsletter updated.');
        }
    }
    redirect('/admin/newsletters.php');
}

$newsletters = db()->query('SELECT * FROM newsletters ORDER BY issue_year DESC, issue_month DESC, id DESC')->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">All Newsletters</h2>
        <a class="btn btn--primary" href="<?= site_url('/admin/newsletters-edit.php') ?>">+ Add Newsletter</a>
    </div>

    <?php if (empty($newsletters)): ?>
        <p class="empty-state">No newsletters yet.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th></th><th>Title</th><th>Issue</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($newsletters as $n): ?>
                <tr>
                    <td><img class="thumb-preview" src="<?= e($n['cover_image'] ? upload_url($n['cover_image']) : asset_url('images/placeholders/newsletter-cover-placeholder.svg')) ?>" alt=""></td>
                    <td><?= e($n['title']) ?></td>
                    <td><?= e($n['issue_number']) ?></td>
                    <td><?= e($n['issue_year']) ?></td>
                    <td><span class="status-pill status-pill--<?= $n['is_published'] ? 'published' : 'draft' ?>"><?= $n['is_published'] ? 'Published' : 'Unpublished' ?></span></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= upload_url($n['pdf_path']) ?>" target="_blank" rel="noopener">View PDF</a>
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/newsletters-edit.php?id=' . $n['id']) ?>">Edit</a>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <input type="hidden" name="action" value="toggle_publish">
                            <button type="submit" class="btn btn--small btn--secondary"><?= $n['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                        </form>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Delete '<?= e($n['title']) ?>'? This will remove the PDF file permanently.">Delete</button>
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
