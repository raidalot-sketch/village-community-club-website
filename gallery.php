<?php
require_once __DIR__ . '/includes/bootstrap.php';

seo_set(['title' => 'Photo & Video Gallery', 'description' => 'Browse photos and videos from ' . get_setting('site_name', SITE_NAME)]);

$albums = db()->query("SELECT a.*, cm.thumbnail_path AS cover_thumb, cm.file_path AS cover_file
    FROM gallery_albums a LEFT JOIN media_items cm ON cm.id = a.cover_media_id
    ORDER BY a.sort_order ASC, a.created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Photo & Video Gallery</h1>
        <p>A look back at Club events, activities and village life.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($albums)): ?>
            <p class="empty-state">Photo albums will appear here once they have been added from the admin area.</p>
        <?php else: ?>
        <div class="card-grid">
            <?php foreach ($albums as $album):
                $cover = $album['cover_thumb'] ?: $album['cover_file'];
            ?>
            <a class="album-card" href="<?= site_url('/gallery-album.php?slug=' . urlencode($album['slug'])) ?>">
                <img class="album-card__cover" src="<?= e($cover ? upload_url($cover) : asset_url('images/placeholders/album-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="album-card__title"><?= e($album['title']) ?></div>
                <?php if ($album['description']): ?><p style="color:var(--color-text-muted);"><?= e($album['description']) ?></p><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
