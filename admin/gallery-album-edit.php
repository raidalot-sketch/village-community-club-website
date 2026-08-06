<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$album = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM gallery_albums WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $album = $stmt->fetch();
    if (!$album) {
        flash_error('Album not found.');
        redirect('/admin/gallery-albums.php');
    }
}

$pageTitle = $album ? 'Manage Album: ' . $album['title'] : 'Add Album';
$errors = [];
$values = $album ?: ['title' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? 'save_album';
    csrf_verify();

    if ($formAction === 'save_album') {
        $values['title'] = sanitize_text($_POST['title'] ?? '', 190);
        $values['description'] = sanitize_text($_POST['description'] ?? '', 400);

        if ($values['title'] === '') {
            $errors[] = 'Please enter an album title.';
        }

        if (empty($errors)) {
            $slug = unique_slug('gallery_albums', slugify($values['title']), $id ?: null);
            if ($id) {
                db()->prepare('UPDATE gallery_albums SET title=:title, slug=:slug, description=:description WHERE id=:id')
                    ->execute(['title' => $values['title'], 'slug' => $slug, 'description' => $values['description'], 'id' => $id]);
                flash_success('Album details updated.');
            } else {
                db()->prepare('INSERT INTO gallery_albums (title, slug, description, created_by, created_at) VALUES (:title, :slug, :description, :cb, NOW())')
                    ->execute(['title' => $values['title'], 'slug' => $slug, 'description' => $values['description'], 'cb' => $_SESSION['admin_id']]);
                $id = (int) db()->lastInsertId();
                audit_log('gallery.album_create', "Created album #$id: {$values['title']}");
                flash_success('Album created. You can now add photos and videos below.');
            }
            redirect('/admin/gallery-album-edit.php?id=' . $id);
        }
    } elseif ($formAction === 'upload_images' && $id) {
        $count = count($_FILES['images']['name'] ?? []);
        $uploaded = 0;
        for ($i = 0; $i < $count; $i++) {
            if (empty($_FILES['images']['name'][$i])) {
                continue;
            }
            $singleFile = [
                'name' => $_FILES['images']['name'][$i], 'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i],
            ];
            $_FILES['__single_image'] = $singleFile;
            $upload = handle_upload('__single_image', 'gallery', 'image');
            if ($upload['ok']) {
                $stmt = db()->prepare('INSERT INTO media_items (album_id, type, file_path, thumbnail_path, title, alt_text, sort_order, created_at)
                    VALUES (:album_id, "image", :file, :thumb, :title, :alt, (SELECT n FROM (SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM media_items WHERE album_id = :album_id2) t), NOW())');
                $stmt->execute([
                    'album_id' => $id, 'album_id2' => $id, 'file' => $upload['path'], 'thumb' => $upload['thumbnail'] ?? null,
                    'title' => pathinfo($singleFile['name'], PATHINFO_FILENAME), 'alt' => '',
                ]);
                $uploaded++;
            }
        }
        // Set a cover automatically if the album doesn't have one yet.
        db()->prepare('UPDATE gallery_albums SET cover_media_id = (SELECT id FROM media_items WHERE album_id = :id ORDER BY sort_order ASC LIMIT 1)
            WHERE id = :id AND cover_media_id IS NULL')->execute(['id' => $id]);
        audit_log('gallery.upload', "Uploaded $uploaded image(s) to album #$id");
        flash_success($uploaded . ' photo(s) uploaded.');
        redirect('/admin/gallery-album-edit.php?id=' . $id);
    } elseif ($formAction === 'add_video' && $id) {
        $videoUrl = trim($_POST['video_embed_url'] ?? '');
        $videoTitle = sanitize_text($_POST['video_title'] ?? '', 190);
        if (!preg_match('#^https://(www\.)?(youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)#', $videoUrl)) {
            flash_error('Please use a YouTube or Vimeo embed URL, e.g. https://www.youtube-nocookie.com/embed/VIDEO_ID');
        } else {
            db()->prepare('INSERT INTO media_items (album_id, type, video_embed_url, title, sort_order, created_at)
                VALUES (:album_id, "video", :url, :title, (SELECT n FROM (SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM media_items WHERE album_id = :album_id2) t), NOW())')
                ->execute(['album_id' => $id, 'album_id2' => $id, 'url' => $videoUrl, 'title' => $videoTitle]);
            flash_success('Video added.');
        }
        redirect('/admin/gallery-album-edit.php?id=' . $id);
    } elseif ($formAction === 'delete_item' && $id) {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $item = db()->prepare('SELECT file_path FROM media_items WHERE id = :iid AND album_id = :id');
        $item->execute(['iid' => $itemId, 'id' => $id]);
        $row = $item->fetch();
        if ($row) {
            delete_upload($row['file_path']);
            db()->prepare('DELETE FROM media_items WHERE id = :iid')->execute(['iid' => $itemId]);
            db()->prepare('UPDATE gallery_albums SET cover_media_id = NULL WHERE id = :id AND cover_media_id = :iid')->execute(['id' => $id, 'iid' => $itemId]);
            flash_success('Item deleted.');
        }
        redirect('/admin/gallery-album-edit.php?id=' . $id);
    } elseif ($formAction === 'set_cover' && $id) {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        db()->prepare('UPDATE gallery_albums SET cover_media_id = :iid WHERE id = :id')->execute(['iid' => $itemId, 'id' => $id]);
        flash_success('Album cover updated.');
        redirect('/admin/gallery-album-edit.php?id=' . $id);
    }
}

$items = [];
if ($id) {
    $itemsStmt = db()->prepare('SELECT * FROM media_items WHERE album_id = :id ORDER BY sort_order ASC, id ASC');
    $itemsStmt->execute(['id' => $id]);
    $items = $itemsStmt->fetchAll();
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="admin-panel">
    <h2 style="margin-top:0;">Album Details</h2>
    <form class="admin-form" method="post" action="<?= site_url('/admin/gallery-album-edit.php' . ($id ? '?id=' . $id : '')) ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="form_action" value="save_album">
        <div class="form-field">
            <label for="title">Album title</label>
            <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="2"><?= e($values['description']) ?></textarea>
        </div>
        <div><button type="submit" class="btn btn--primary"><?= $id ? 'Save Album Details' : 'Create Album & Continue' ?></button></div>
    </form>
</div>

<?php if ($id): ?>

<div class="admin-panel">
    <h2 style="margin-top:0;">Upload Photos</h2>
    <form class="admin-form" method="post" action="<?= site_url('/admin/gallery-album-edit.php?id=' . $id) ?>" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="form_action" value="upload_images">
        <div class="form-field">
            <label for="images">Choose one or more images (JPG, PNG, WebP)</label>
            <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </div>
        <div><button type="submit" class="btn btn--primary">Upload Photos</button></div>
    </form>
</div>

<div class="admin-panel">
    <h2 style="margin-top:0;">Add a Video</h2>
    <p style="color:var(--color-text-muted);">Paste a YouTube or Vimeo <strong>embed</strong> URL (privacy-friendly form preferred), e.g. <code>https://www.youtube-nocookie.com/embed/VIDEO_ID</code> or <code>https://player.vimeo.com/video/VIDEO_ID</code>.</p>
    <form class="admin-form" method="post" action="<?= site_url('/admin/gallery-album-edit.php?id=' . $id) ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="form_action" value="add_video">
        <div class="form-row">
            <div class="form-field">
                <label for="video_title">Video title</label>
                <input type="text" id="video_title" name="video_title">
            </div>
            <div class="form-field">
                <label for="video_embed_url">Embed URL</label>
                <input type="url" id="video_embed_url" name="video_embed_url" required>
            </div>
        </div>
        <div><button type="submit" class="btn btn--primary">Add Video</button></div>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel__header"><h2 style="margin:0;">Photos & Videos in this Album</h2></div>
    <?php if (empty($items)): ?>
        <p class="empty-state">No photos or videos added yet.</p>
    <?php else: ?>
    <p style="color:var(--color-text-muted);">Drag items to reorder them. The order here is the order shown on the website.</p>
    <div class="card-grid" data-reorder-url="<?= site_url('/admin/gallery-reorder.php?album_id=' . $id) ?>">
        <?php foreach ($items as $item): ?>
        <div class="card" data-reorder-id="<?= $item['id'] ?>" style="cursor:grab;">
            <?php if ($item['type'] === 'image'): ?>
                <img class="card__image" src="<?= e($item['thumbnail_path'] ? upload_url($item['thumbnail_path']) : upload_url($item['file_path'])) ?>" alt="">
            <?php else: ?>
                <div class="card__image" style="display:flex;align-items:center;justify-content:center;background:var(--color-primary-dark);color:#fff;">Video</div>
            <?php endif; ?>
            <div class="card__body">
                <p class="card__title" style="font-size:1rem;"><?= e($item['title'] ?: 'Untitled') ?></p>
                <?php if ($item['type'] === 'image' && empty($item['alt_text'])): ?>
                    <p class="placeholder-flag">Missing alt text</p>
                <?php endif; ?>
                <div class="admin-table__actions">
                    <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/gallery-item-edit.php?id=' . $item['id']) ?>">Edit Details</a>
                    <?php if ($item['type'] === 'image'): ?>
                    <form method="post" class="confirm-inline">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="form_action" value="set_cover">
                        <button type="submit" class="btn btn--small btn--secondary">Set as Cover</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" class="confirm-inline">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="form_action" value="delete_item">
                        <button type="submit" class="btn btn--small btn--danger" data-confirm="Delete this item?">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<p><a class="btn btn--secondary" href="<?= site_url('/admin/gallery-albums.php') ?>">&laquo; Back to Albums</a></p>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
