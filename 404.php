<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
seo_set(['title' => 'Page Not Found', 'description' => 'The page you were looking for could not be found.']);
require_once __DIR__ . '/includes/header.php';
?>
<section class="section container text-center">
    <h1>Page Not Found</h1>
    <p>Sorry, we could not find the page you were looking for. It may have been moved or no longer exists.</p>
    <p><a class="btn btn--primary" href="<?= site_url('/index.php') ?>">Back to Home</a></p>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
