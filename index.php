<?php
require_once __DIR__ . '/includes/bootstrap.php';
seo_set(['title' => 'Welcome', 'description' => get_setting('site_tagline'), 'image' => site_image_url('hero_image_path', 'images/club-building.jpg')]);
seo_add_schema(schema_organization());
$events = db()->query("SELECT * FROM events WHERE status='published' AND deleted_at IS NULL AND event_date >= CURDATE() ORDER BY event_date, start_time LIMIT 3")->fetchAll();
$news = db()->query("SELECT * FROM news_articles WHERE status='published' AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 3")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<section class="club-hero">
    <div class="container club-hero__layout">
        <div class="club-hero__copy">
            <h1><?= e(get_setting('home_hero_heading')) ?></h1>
            <p><?= e(get_setting('home_hero_subheading')) ?></p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="<?= e(site_url('/events.php')) ?>">See what’s on</a>
                <a class="btn btn--secondary" href="<?= e(site_url('/hall-hire.php')) ?>">Hire a hall</a>
            </div>
            <p class="club-hero__location">32 High Street, Nettlebed · Oxfordshire</p>
        </div>
        <figure class="club-hero__photo">
            <img src="<?= e(site_image_url('hero_image_path', 'images/club-building.jpg')) ?>" alt="<?= e(get_setting('hero_image_alt', 'The courtyard and club building on Nettlebed High Street')) ?>" width="960" height="720" fetchpriority="high">
            <figcaption>A familiar place. A friendly face.</figcaption>
        </figure>
    </div>
</section>
<section class="section club-priorities"><div class="container club-priorities__layout">
    <div class="club-events">
        <div class="section__header"><h2>Come for the company.</h2><a href="<?= e(site_url('/events.php')) ?>">What’s on</a></div>
        <p>Find your next evening out, catch up with friends and see what’s happening at the club.</p>
        <?php if ($events): ?>
        <div class="event-list"><?php foreach ($events as $event): ?>
            <a class="event-row" href="<?= e(site_url('/event-detail.php?slug=' . urlencode($event['slug']))) ?>">
                <time datetime="<?= e($event['event_date']) ?>"><strong><?= e(format_date($event['event_date'], 'j')) ?></strong><span><?= e(format_date($event['event_date'], 'M')) ?></span></time>
                <div><h3><?= e($event['title']) ?></h3><p><?= e(format_time($event['start_time'])) ?><?= $event['venue'] ? ' · ' . e($event['venue']) : '' ?></p></div>
                <span aria-hidden="true">&rarr;</span>
            </a>
        <?php endforeach; ?></div>
        <?php else: ?>
        <div class="club-empty"><h3>Find the latest on Facebook.</h3><p>While we prepare the website calendar, the club’s Facebook page has the latest event announcements.</p><a href="<?= e(get_setting('social_facebook')) ?>" target="_blank" rel="noopener noreferrer">Visit the club on Facebook<span class="visually-hidden"> (opens in a new tab)</span> &rarr;</a></div>
        <?php endif; ?>
    </div>
    <div class="club-hire"><h2>Make it your occasion.</h2><p>Two halls on Nettlebed High Street, with space for celebrations, meetings and regular activities.</p><p>From a first idea to finding the right room, talk to the club about your plans.</p><a class="btn btn--secondary" href="<?= e(site_url('/hall-hire.php')) ?>">Explore hall hire</a><p class="club-hire__contact">Or call <a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a></p></div>
</div></section>
<section class="club-welcome section"><div class="container club-welcome__layout">
    <div><h2>A little more local.</h2><p><?= nl2br(e(get_setting('home_welcome_message'))) ?></p><a href="<?= e(site_url('/about.php')) ?>">Get to know the club &rarr;</a></div>
    <div class="club-membership"><h3>Make yourself part of it.</h3><p><?= e(get_setting('membership_cta_text')) ?></p><a class="btn btn--primary" href="<?= e(site_url('/membership.php')) ?>">Ask about membership</a></div>
</div></section>
<?php if ($news): ?>
<section class="section"><div class="container"><div class="section__header"><h2>From the club.</h2><a href="<?= e(site_url('/news.php')) ?>">All news</a></div><div class="card-grid">
<?php foreach ($news as $article): ?><a class="card" href="<?= e(site_url('/news-detail.php?slug=' . urlencode($article['slug']))) ?>"><div class="card__body"><p class="card__meta"><?= e(format_date($article['published_at'])) ?></p><h3><?= e($article['title']) ?></h3><p><?= e(truncate_text($article['summary'], 130)) ?></p></div></a><?php endforeach; ?>
</div></div></section>
<?php endif; ?>
<section class="section club-visit"><div class="container"><h2>Find us on the High Street.</h2><div class="club-visit__layout">
    <div><h3>Visit the club</h3><address><?= nl2br(e(club_address())) ?></address><a href="<?= e(site_url('/contact.php')) ?>">Get in touch &rarr;</a></div>
    <div><h3>Opening hours</h3><p><?= nl2br(e(get_setting('opening_hours'))) ?></p></div>
    <div><h3>Have a question?</h3><p><a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a><br><a href="mailto:<?= e(get_setting('contact_email')) ?>"><?= e(get_setting('contact_email')) ?></a></p></div>
</div></div></section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
