<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle = 'Events';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id) {
        db()->prepare('UPDATE events SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        audit_log('event.delete', "Deleted event #$id");
        flash_success('Event deleted.');
    } elseif ($action === 'toggle_publish' && $id) {
        $current = db()->prepare('SELECT status FROM events WHERE id = :id');
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'published' ? 'draft' : 'published';
            db()->prepare('UPDATE events SET status = :s WHERE id = :id')->execute(['s' => $newStatus, 'id' => $id]);
            audit_log('event.status', "Event #$id set to $newStatus");
            flash_success('Event ' . ($newStatus === 'published' ? 'published.' : 'moved to draft.'));
        }
    }
    redirect('/admin/events.php');
}

$total = (int) db()->query('SELECT COUNT(*) c FROM events WHERE deleted_at IS NULL')->fetch()['c'];
$p = paginate($total, 15);
$stmt = db()->prepare('SELECT * FROM events WHERE deleted_at IS NULL ORDER BY event_date DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue('limit', $p['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $p['offset'], PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">All Events</h2>
        <a class="btn btn--primary" href="<?= site_url('/admin/events-edit.php') ?>">+ Add Event</a>
    </div>

    <?php if (empty($events)): ?>
        <p class="empty-state">No events yet.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th></th><th>Title</th><th>Date</th><th>Status</th><th>Price</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev):
                $isPast = strtotime($ev['event_date']) < strtotime('today'); ?>
                <tr>
                    <td><img class="thumb-preview" src="<?= e($ev['featured_image'] ? upload_url($ev['featured_image']) : asset_url('images/placeholders/event-placeholder.svg')) ?>" alt=""></td>
                    <td><?= e($ev['title']) ?><?= $ev['is_featured'] ? ' <span class="tag">Featured</span>' : '' ?><?= $isPast ? ' <span class="tag">Past</span>' : '' ?></td>
                    <td><?= e(format_date($ev['event_date'])) ?></td>
                    <td><span class="status-pill status-pill--<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span></td>
                    <td><?= $ev['is_free'] ? 'Free' : '£' . number_format((float) $ev['ticket_price'], 2) ?></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/events-edit.php?id=' . $ev['id']) ?>">Edit</a>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                            <input type="hidden" name="action" value="toggle_publish">
                            <button type="submit" class="btn btn--small btn--secondary"><?= $ev['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
                        </form>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Delete '<?= e($ev['title']) ?>'? This cannot be undone from the website.">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($p['page'], $p['totalPages']) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
