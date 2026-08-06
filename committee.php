<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('committee_intro');
$members = db()->query('SELECT * FROM committee_members WHERE is_published = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

seo_set([
    'title' => $page['meta_title'] ?? 'Committee & Club Officials',
    'description' => $page['meta_description'] ?? 'Meet the committee of ' . get_setting('site_name', SITE_NAME),
]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'Committee & Club Officials') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (is_placeholder($page['content'] ?? '')): ?>
            <span class="placeholder-flag">Placeholder text — edit from Admin &gt; Pages &gt; Committee</span>
        <?php endif; ?>
        <div class="rich-content" style="max-width:820px;margin-bottom:var(--space-lg);">
            <?= $page['content'] ?? '' ?>
        </div>

        <?php if (empty($members)): ?>
            <p class="empty-state">Committee members will appear here once added from the admin area.</p>
        <?php else: ?>
        <div class="committee-grid">
            <?php foreach ($members as $member): ?>
            <div class="committee-card">
                <img src="<?= e($member['photo'] ? upload_url($member['photo']) : asset_url('images/placeholders/person-placeholder.svg')) ?>" alt="<?= e($member['name']) ?>" loading="lazy">
                <h3><?= e($member['name']) ?></h3>
                <p class="committee-card__role"><?= e($member['role_title']) ?></p>
                <?php if ($member['bio']): ?><p><?= e($member['bio']) ?></p><?php endif; ?>
                <?php if ($member['email']): ?><p><a href="mailto:<?= e($member['email']) ?>"><?= e($member['email']) ?></a></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
