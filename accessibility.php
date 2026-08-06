<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('accessibility');

seo_set(['title' => $page['title'] ?? 'Accessibility Statement', 'description' => $page['meta_description'] ?? 'Accessibility Statement for ' . get_setting('site_name', SITE_NAME)]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'Accessibility Statement') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="rich-content" style="max-width:820px;">
            <?= $page['content'] ?? '<p>Content coming soon.</p>' ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
