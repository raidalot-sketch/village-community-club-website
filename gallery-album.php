<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM gallery_albums WHERE slug = :slug');
$stmt->execute(['slug' => $slug]);
$album = $stmt->fetch();

if (!$album) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="section container text-center"><h1>Album Not Found</h1><p><a class="btn btn--primary" href="' . site_url('/gallery.php') . '">Back to Gallery</a></p></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$itemsStmt = db()->prepare('SELECT * FROM media_items WHERE album_id = :id ORDER BY sort_order ASC, id ASC');
$itemsStmt->execute(['id' => $album['id']]);
$items = $itemsStmt->fetchAll();

seo_set(['title' => $album['title'], 'description' => $album['description'] ?: ('Photos from ' . $album['title'])]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p><a href="<?= site_url('/gallery.php') ?>">&laquo; Back to Gallery</a></p>
        <h1><?= e($album['title']) ?></h1>
        <?php if ($album['description']): ?><p><?= e($album['description']) ?></p><?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($items)): ?>
            <p class="empty-state">No photos or videos have been added to this album yet.</p>
        <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($items as $item):
                if ($item['type'] === 'image'): ?>
                <a class="gallery-grid__item" href="<?= upload_url($item['file_path']) ?>"
                   data-full="<?= e(upload_url($item['file_path'])) ?>"
                   data-alt="<?= e($item['alt_text']) ?>"
                   data-caption="<?= e($item['caption']) ?>">
                    <img src="<?= e($item['thumbnail_path'] ? upload_url($item['thumbnail_path']) : upload_url($item['file_path'])) ?>" alt="<?= e($item['alt_text'] ?: $item['title']) ?>" loading="lazy">
                </a>
                <?php else: ?>
                <div class="gallery-grid__item" data-embed-src="<?= e($item['video_embed_url']) ?>" data-embed-title="<?= e($item['title'] ?: 'Club video') ?>">
                    <div class="video-consent" style="background:var(--color-primary-dark);">
                        <p><?= e($item['title'] ?: 'Video') ?></p>
                        <button type="button" class="btn btn--light btn--small video-consent-btn">Load Video</button>
                    </div>
                </div>
                <?php endif;
            endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox for full-size images (keyboard accessible, see assets/js/main.js) -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <figure class="lightbox__figure">
        <img class="lightbox__image" src="" alt="">
        <figcaption class="lightbox__caption"></figcaption>
    </figure>
    <button type="button" class="lightbox__close" aria-label="Close photo viewer">&times;</button>
    <button type="button" class="lightbox__prev" aria-label="Previous photo">&larr;</button>
    <button type="button" class="lightbox__next" aria-label="Next photo">&rarr;</button>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
