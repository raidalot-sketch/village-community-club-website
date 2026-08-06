<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('cookies');

seo_set(['title' => $page['title'] ?? 'Cookie Policy', 'description' => $page['meta_description'] ?? 'Cookie Policy for ' . get_setting('site_name', SITE_NAME)]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'Cookie Policy') ?></h1>
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
