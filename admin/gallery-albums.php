<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$pageTitle = 'Gallery Albums';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id) {
        $items = db()->prepare('SELECT file_path, thumbnail_path FROM media_items WHERE album_id = :id');
        $items->execute(['id' => $id]);
        foreach ($items->fetchAll() as $item) {
            delete_upload($item['file_path']);
        }
        db()->prepare('DELETE FROM gallery_albums WHERE id = :id')->execute(['id' => $id]);
        audit_log('gallery.album_delete', "Deleted gallery album #$id");
        flash_success('Album and its photos/videos deleted.');
    }
    redirect('/admin/gallery-albums.php');
}

$albums = db()->query("SELECT a.*, (SELECT COUNT(*) FROM media_items m WHERE m.album_id = a.id) AS item_count,
    cm.thumbnail_path AS cover_thumb, cm.file_path AS cover_file
    FROM gallery_albums a LEFT JOIN media_items cm ON cm.id = a.cover_media_id
    ORDER BY a.sort_order ASC, a.created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header">
        <h2 style="margin:0;">Gallery Albums</h2>
        <a class="btn btn--primary" href="<?= site_url('/admin/gallery-album-edit.php') ?>">+ Add Album</a>
    </div>

    <?php if (empty($albums)): ?>
        <p class="empty-state">No albums yet.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th></th><th>Album</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($albums as $a):
                $cover = $a['cover_thumb'] ?: $a['cover_file']; ?>
                <tr>
                    <td><img class="thumb-preview" src="<?= e($cover ? upload_url($cover) : asset_url('images/placeholders/album-placeholder.svg')) ?>" alt=""></td>
                    <td><?= e($a['title']) ?></td>
                    <td><?= (int) $a['item_count'] ?></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/gallery-album.php?slug=' . urlencode($a['slug'])) ?>" target="_blank" rel="noopener">View</a>
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/gallery-album-edit.php?id=' . $a['id']) ?>">Manage</a>
                        <form method="post" class="confirm-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--small btn--danger" data-confirm="Delete the album '<?= e($a['title']) ?>' and all its photos/videos? This cannot be undone.">Delete</button>
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
