<?php
require_once __DIR__ . '/includes/bootstrap.php';

seo_set(['title' => 'Latest News', 'description' => 'News and updates from ' . get_setting('site_name', SITE_NAME)]);

$total = (int) db()->query("SELECT COUNT(*) c FROM news_articles WHERE status='published' AND deleted_at IS NULL")->fetch()['c'];
$p = paginate($total, 9);

$stmt = db()->prepare("SELECT * FROM news_articles WHERE status='published' AND deleted_at IS NULL
    ORDER BY published_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue('limit', $p['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $p['offset'], PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Latest News</h1>
        <p>Updates, stories and announcements from around the Club.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($articles)): ?>
            <p class="empty-state">No news articles have been published yet. Check back soon.</p>
        <?php else: ?>
        <div class="card-grid">
            <?php foreach ($articles as $article): ?>
            <a class="card" href="<?= site_url('/news-detail.php?slug=' . urlencode($article['slug'])) ?>">
                <img class="card__image" src="<?= e($article['featured_image'] ? upload_url($article['featured_image']) : asset_url('images/placeholders/news-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="card__body">
                    <?php if ($article['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
                    <div class="card__meta"><?= e(format_date($article['published_at'])) ?> &middot; <?= e($article['author']) ?></div>
                    <h3 class="card__title"><?= e($article['title']) ?></h3>
                    <p class="card__excerpt"><?= e(truncate_text($article['summary'] ?: $article['content'], 120)) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?= pagination_links($p['page'], $p['totalPages']) ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
