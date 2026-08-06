<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('about');

seo_set([
    'title' => $page['meta_title'] ?? 'About the Club',
    'description' => $page['meta_description'] ?? 'Learn about ' . get_setting('site_name', SITE_NAME),
]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'About the Club') ?></h1>
        <p><?= e(get_setting('site_tagline')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (is_placeholder($page['content'] ?? '')): ?>
            <span class="placeholder-flag">Placeholder text — edit from Admin &gt; Pages &gt; About</span>
        <?php endif; ?>
        <div class="rich-content" style="max-width:820px;">
            <?= $page['content'] ?? '<p>Content coming soon.</p>' ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
