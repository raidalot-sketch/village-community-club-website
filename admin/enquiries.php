<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle = 'Contact Enquiries';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status' && $id) {
        $current = db()->prepare('SELECT status FROM contact_enquiries WHERE id = :id');
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'new' ? 'resolved' : 'new';
            db()->prepare('UPDATE contact_enquiries SET status = :s WHERE id = :id')->execute(['s' => $newStatus, 'id' => $id]);
            flash_success('Enquiry marked as ' . $newStatus . '.');
        }
    } elseif ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM contact_enquiries WHERE id = :id')->execute(['id' => $id]);
        audit_log('enquiry.delete', "Deleted enquiry #$id");
        flash_success('Enquiry deleted.');
    }
    redirect('/admin/enquiries.php');
}

$filter = $_GET['status'] ?? 'all';
$sql = 'SELECT * FROM contact_enquiries';
if ($filter === 'new' || $filter === 'resolved') {
    $sql .= ' WHERE status = :status';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
if ($filter === 'new' || $filter === 'resolved') {
    $stmt->execute(['status' => $filter]);
} else {
    $stmt->execute();
}
$enquiries = $stmt->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">Contact Enquiries</h2>
        <div class="quick-actions" style="margin:0;">
            <a class="btn btn--small <?= $filter === 'all' ? 'btn--primary' : 'btn--secondary' ?>" href="<?= site_url('/admin/enquiries.php') ?>">All</a>
            <a class="btn btn--small <?= $filter === 'new' ? 'btn--primary' : 'btn--secondary' ?>" href="<?= site_url('/admin/enquiries.php?status=new') ?>">New</a>
            <a class="btn btn--small <?= $filter === 'resolved' ? 'btn--primary' : 'btn--secondary' ?>" href="<?= site_url('/admin/enquiries.php?status=resolved') ?>">Resolved</a>
        </div>
    </div>

    <?php if (empty($enquiries)): ?>
        <p class="empty-state">No enquiries to show.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Received</th><th>Name</th><th>Contact</th><th>Subject / Message</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($enquiries as $enq): ?>
                <tr>
                    <td><?= e(format_date($enq['created_at'], 'j M Y, g:ia')) ?></td>
                    <td><?= e($enq['name']) ?></td>
                    <td><a href="mailto:<?= e($enq['email']) ?>"><?= e($enq['email']) ?></a><?= $enq['phone'] ? '<br>' . e($enq['phone']) : '' ?></td>
                    <td><strong><?= e($enq['subject'] ?: '(No subject)') ?></strong><br><?= e(truncate_text($enq['message'], 140)) ?></td>
                    <td><span class="status-pill status-pill--<?= e($enq['status']) ?>"><?= e(ucfirst($enq['status'])) ?></span></td>
                    <td class="admin-table__actions">
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $enq['id'] ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn--small btn--secondary"><?= $enq['status'] === 'new' ? 'Mark Resolved' : 'Reopen' ?></button>
                        </form>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $enq['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Permanently delete this enquiry?">Delete</button>
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
