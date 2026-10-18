<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = get_page('membership');

seo_set([
    'title' => $page['meta_title'] ?? 'Membership',
    'description' => $page['meta_description'] ?? 'Join ' . get_setting('site_name', SITE_NAME),
]);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($page['title'] ?? 'Membership') ?></h1>
        <p>Find out about becoming a member of your village club.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (is_placeholder($page['content'] ?? '')): ?>
            <span class="placeholder-flag">Placeholder text — edit from Admin &gt; Pages &gt; Membership</span>
        <?php endif; ?>
        <div class="rich-content" style="max-width:820px;">
            <?= $page['content'] ?? '<p>Content coming soon.</p>' ?>
        </div>
        <p class="mt-lg">
            <a class="btn btn--primary" href="<?= site_url('/contact.php?subject=Membership') ?>">Ask About Joining</a>
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
