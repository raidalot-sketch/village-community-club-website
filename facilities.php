<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('facilities');

seo_set([
    'title' => $page['meta_title'] ?? 'Facilities & Activities',
    'description' => $page['meta_description'] ?? 'Facilities and activities at ' . get_setting('site_name', SITE_NAME),
]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'Facilities & Activities') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (is_placeholder($page['content'] ?? '')): ?>
            <span class="placeholder-flag">Placeholder text — edit from Admin &gt; Pages &gt; Facilities</span>
        <?php endif; ?>
        <div class="rich-content" style="max-width:820px;">
            <?= $page['content'] ?? '<p>Content coming soon.</p>' ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
