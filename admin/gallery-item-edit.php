<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$itemId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT m.*, a.title AS album_title FROM media_items m JOIN gallery_albums a ON a.id = m.album_id WHERE m.id = :id');
$stmt->execute(['id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    flash_error('Item not found.');
    redirect('/admin/gallery-albums.php');
}

$pageTitle = 'Edit Photo/Video Details';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = sanitize_text($_POST['title'] ?? '', 190);
    $caption = sanitize_text($_POST['caption'] ?? '', 400);
    $altText = sanitize_text($_POST['alt_text'] ?? '', 255);

    if ($item['type'] === 'image' && $altText === '') {
        $errors[] = 'Please add alternative text describing this photo, for visitors using screen readers.';
    }

    if (empty($errors)) {
        db()->prepare('UPDATE media_items SET title = :title, caption = :caption, alt_text = :alt WHERE id = :id')
            ->execute(['title' => $title, 'caption' => $caption, 'alt' => $altText, 'id' => $itemId]);
        flash_success('Details updated.');
        redirect('/admin/gallery-album-edit.php?id=' . $item['album_id']);
    }
    $item['title'] = $title;
    $item['caption'] = $caption;
    $item['alt_text'] = $altText;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="admin-panel" style="max-width:640px;">
    <?php if ($item['type'] === 'image'): ?>
        <img class="thumb-preview" style="width:140px;height:140px;" src="<?= e($item['thumbnail_path'] ? upload_url($item['thumbnail_path']) : upload_url($item['file_path'])) ?>" alt="">
    <?php endif; ?>
    <form class="admin-form" method="post" action="<?= site_url('/admin/gallery-item-edit.php?id=' . $itemId) ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= $itemId ?>">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($item['title']) ?>">
        </div>
        <div class="form-field">
            <label for="caption">Caption</label>
            <textarea id="caption" name="caption" rows="2"><?= e($item['caption']) ?></textarea>
        </div>
        <?php if ($item['type'] === 'image'): ?>
        <div class="form-field">
            <label for="alt_text">Alternative text (for accessibility)</label>
            <input type="text" id="alt_text" name="alt_text" value="<?= e($item['alt_text']) ?>" required>
            <small class="hint">Describe what's in the photo, e.g. "Members enjoying the summer fete on the village green."</small>
        </div>
        <?php endif; ?>
        <div>
            <button type="submit" class="btn btn--primary">Save</button>
            <a class="btn btn--secondary" href="<?= site_url('/admin/gallery-album-edit.php?id=' . $item['album_id']) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
