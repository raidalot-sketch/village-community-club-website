<?php
require_once __DIR__ . '/includes/bootstrap.php';

seo_set(['title' => 'Newsletters', 'description' => 'Read and download newsletters from ' . get_setting('site_name', SITE_NAME)]);

$newsletters = db()->query("SELECT * FROM newsletters WHERE is_published = 1
    ORDER BY issue_year DESC, issue_month DESC, id DESC")->fetchAll();

$byYear = [];
foreach ($newsletters as $n) {
    $byYear[$n['issue_year']][] = $n;
}
krsort($byYear);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Newsletters</h1>
        <p>Catch up on Club news, delivered a few times a year.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($byYear)): ?>
            <p class="empty-state">No newsletters have been published yet. Check back soon.</p>
        <?php else: ?>
            <?php foreach ($byYear as $year => $items): ?>
                <h2 class="newsletter-year-heading"><?= e($year) ?></h2>
                <ul class="newsletter-list">
                    <?php foreach ($items as $n): ?>
                    <li class="newsletter-item">
                        <img class="newsletter-item__cover" src="<?= e($n['cover_image'] ? upload_url($n['cover_image']) : asset_url('images/placeholders/newsletter-cover-placeholder.svg')) ?>" alt="" loading="lazy">
                        <div class="newsletter-item__meta">
                            <h3 style="margin-bottom:0.2rem;"><?= e($n['title']) ?></h3>
                            <p style="margin:0;color:var(--color-text-muted);">
                                <?= e($n['issue_number']) ?><?= $n['issue_month'] ? ' &middot; ' . e(date('F', mktime(0, 0, 0, (int) $n['issue_month'], 1))) : '' ?>
                            </p>
                            <?php if ($n['description']): ?><p style="margin-top:0.4rem;"><?= e($n['description']) ?></p><?php endif; ?>
                        </div>
                        <a class="btn btn--secondary" href="<?= upload_url($n['pdf_path']) ?>" target="_blank" rel="noopener">View / Download PDF</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
