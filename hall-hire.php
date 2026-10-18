<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = get_page('hall_hire');
seo_set(['title' => 'Hall hire', 'description' => 'Ask about hiring a hall at Nettlebed Village Club for a celebration, meeting or regular group.']);
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><h1>Room for your next occasion.</h1><p>Hall hire at Nettlebed Village Club, on the High Street.</p></div></section>
<section class="section"><div class="container hire-layout">
    <div class="rich-content"><?= $page['content'] ?? '<p>Contact the club to discuss your plans and available dates.</p>' ?>
        <a class="btn btn--primary" href="<?= e(site_url('/contact.php?subject=Hall%20hire')) ?>">Enquire about hall hire</a>
    </div>
    <aside class="hire-aside"><h2>Let’s plan it together.</h2><p>Tell us your preferred date, the occasion, expected guest numbers and any equipment you need.</p><p>We’ll help you check which space suits your plans, availability and the current hire charges.</p><a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a><p class="hire-note">An enquiry does not reserve a date. The club will confirm arrangements with you.</p></aside>
</div></section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
