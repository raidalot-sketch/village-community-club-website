<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM news_articles WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL");
$stmt->execute(['slug' => $slug]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="section container text-center"><h1>Article Not Found</h1><p>Sorry, we could not find that news article. It may have been removed.</p><p><a class="btn btn--primary" href="' . site_url('/news.php') . '">Back to News</a></p></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

seo_set([
    'title' => $article['meta_title'] ?: $article['title'],
    'description' => $article['meta_description'] ?: truncate_text($article['summary'] ?: $article['content'], 160),
    'image' => $article['featured_image'] ? upload_url($article['featured_image']) : '',
]);
seo_add_schema(schema_article($article));

$related = db()->prepare("SELECT title, slug, featured_image, published_at FROM news_articles
    WHERE status='published' AND deleted_at IS NULL AND id != :id ORDER BY published_at DESC LIMIT 3");
$related->execute(['id' => $article['id']]);
$relatedArticles = $related->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<article class="section">
    <div class="container" style="max-width:820px;">
        <p><a href="<?= site_url('/news.php') ?>">&laquo; Back to News</a></p>
        <?php if ($article['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
        <h1><?= e($article['title']) ?></h1>
        <p style="color:var(--color-text-muted);font-weight:600;">
            <?= e(format_date($article['published_at'])) ?><?= $article['author'] ? ' &middot; By ' . e($article['author']) : '' ?>
        </p>
        <img src="<?= e($article['featured_image'] ? upload_url($article['featured_image']) : asset_url('images/placeholders/news-placeholder.svg')) ?>" alt="" style="border-radius:var(--radius-lg);margin-bottom:var(--space-md);" loading="lazy">
        <div class="rich-content"><?= $article['content'] ?></div>
    </div>
</article>

<?php if (!empty($relatedArticles)): ?>
<section class="section section--alt">
    <div class="container">
        <h2>More News</h2>
        <div class="card-grid">
            <?php foreach ($relatedArticles as $rel): ?>
            <a class="card" href="<?= site_url('/news-detail.php?slug=' . urlencode($rel['slug'])) ?>">
                <img class="card__image" src="<?= e($rel['featured_image'] ? upload_url($rel['featured_image']) : asset_url('images/placeholders/news-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="card__body">
                    <div class="card__meta"><?= e(format_date($rel['published_at'])) ?></div>
                    <h3 class="card__title"><?= e($rel['title']) ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
