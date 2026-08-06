<?php
require_once __DIR__ . '/includes/bootstrap.php';

seo_set([
    'title' => 'Home',
    'description' => get_setting('site_tagline', 'A warm welcome for everyone in ' . get_setting('site_village', SITE_VILLAGE)),
]);
seo_add_schema(schema_organization());

$latestNews = db()->query("SELECT * FROM news_articles WHERE status = 'published' AND deleted_at IS NULL
    ORDER BY published_at DESC LIMIT 3")->fetchAll();

$upcomingEvents = db()->query("SELECT * FROM events WHERE status = 'published' AND deleted_at IS NULL
    AND event_date >= CURDATE() ORDER BY event_date ASC, start_time ASC LIMIT 3")->fetchAll();

$latestNewsletter = db()->query("SELECT * FROM newsletters WHERE is_published = 1
    ORDER BY issue_year DESC, issue_month DESC, id DESC LIMIT 1")->fetch();

$galleryPreview = db()->query("SELECT m.* FROM media_items m
    JOIN gallery_albums a ON a.id = m.album_id
    ORDER BY m.created_at DESC LIMIT 6")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero" style="--hero-image: url('<?= e(asset_url('images/placeholders/hero-placeholder.svg')) ?>')">
    <div class="container">
        <span class="placeholder-flag" style="background:#fff;color:#234f39;">PLACEHOLDER HERO IMAGE — replace from Admin &gt; Settings</span>
        <h1><?= e(get_setting('home_hero_heading', 'Welcome to ' . get_setting('site_name', SITE_NAME))) ?></h1>
        <p class="hero__subtitle"><?= e(get_setting('home_hero_subheading', 'The friendly heart of ' . get_setting('site_village', SITE_VILLAGE))) ?></p>
        <div class="hero__actions">
            <a class="btn btn--primary" href="<?= site_url('/membership.php') ?>">Become a Member</a>
            <a class="btn btn--light" href="<?= site_url('/events.php') ?>">See What's On</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container two-col">
        <div>
            <h2>Welcome</h2>
            <?php if (is_placeholder(get_setting('home_welcome_message'))): ?>
                <span class="placeholder-flag">Placeholder text</span>
            <?php endif; ?>
            <p><?= nl2br(e(get_setting('home_welcome_message'))) ?></p>
            <p><a class="btn btn--secondary" href="<?= site_url('/about.php') ?>">More About Us</a></p>
        </div>
        <img src="<?= e(asset_url('images/placeholders/news-placeholder.svg')) ?>" alt="[PLACEHOLDER] Photo of club members at an event — replace from Admin" loading="lazy" width="700" height="440">
    </div>
</section>

<section class="section section--alt">
    <div class="container">
        <div class="section__header">
            <h2>Latest News</h2>
            <a href="<?= site_url('/news.php') ?>">All news &raquo;</a>
        </div>
        <?php if (empty($latestNews)): ?>
            <p class="empty-state">No news articles have been published yet. Check back soon.</p>
        <?php else: ?>
        <div class="card-grid">
            <?php foreach ($latestNews as $article): ?>
            <a class="card" href="<?= site_url('/news-detail.php?slug=' . urlencode($article['slug'])) ?>">
                <img class="card__image" src="<?= e($article['featured_image'] ? upload_url($article['featured_image']) : asset_url('images/placeholders/news-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="card__body">
                    <?php if ($article['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
                    <div class="card__meta"><?= e(format_date($article['published_at'])) ?></div>
                    <h3 class="card__title"><?= e($article['title']) ?></h3>
                    <p class="card__excerpt"><?= e(truncate_text($article['summary'] ?: $article['content'], 110)) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section__header">
            <h2>Upcoming Events</h2>
            <a href="<?= site_url('/events.php') ?>">All events &raquo;</a>
        </div>
        <?php if (empty($upcomingEvents)): ?>
            <p class="empty-state">No upcoming events are scheduled yet. Check back soon.</p>
        <?php else: ?>
        <div class="card-grid">
            <?php foreach ($upcomingEvents as $event): ?>
            <a class="card" href="<?= site_url('/event-detail.php?slug=' . urlencode($event['slug'])) ?>">
                <img class="card__image" src="<?= e($event['featured_image'] ? upload_url($event['featured_image']) : asset_url('images/placeholders/event-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="card__body">
                    <?php if ($event['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
                    <div class="card__meta"><?= e(format_date($event['event_date'])) ?><?= $event['start_time'] ? ' &middot; ' . e(format_time($event['start_time'])) : '' ?></div>
                    <h3 class="card__title"><?= e($event['title']) ?></h3>
                    <p class="card__excerpt"><?= e($event['venue']) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($latestNewsletter): ?>
<section class="section section--alt">
    <div class="container">
        <h2>Latest Newsletter</h2>
        <div class="newsletter-item">
            <img class="newsletter-item__cover" src="<?= e($latestNewsletter['cover_image'] ? upload_url($latestNewsletter['cover_image']) : asset_url('images/placeholders/newsletter-cover-placeholder.svg')) ?>" alt="" loading="lazy">
            <div class="newsletter-item__meta">
                <h3 style="margin-bottom:0.2rem;"><?= e($latestNewsletter['title']) ?></h3>
                <p style="margin:0;color:var(--color-text-muted);"><?= e($latestNewsletter['issue_number']) ?> &middot; <?= e(format_date($latestNewsletter['issue_year'] . '-' . $latestNewsletter['issue_month'] . '-01', 'F Y')) ?></p>
            </div>
            <a class="btn btn--secondary" href="<?= upload_url($latestNewsletter['pdf_path']) ?>" target="_blank" rel="noopener">View / Download PDF</a>
        </div>
        <p class="mt-lg"><a href="<?= site_url('/newsletters.php') ?>">Browse all newsletters &raquo;</a></p>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section__header">
            <h2>Gallery</h2>
            <a href="<?= site_url('/gallery.php') ?>">View full gallery &raquo;</a>
        </div>
        <?php if (empty($galleryPreview)): ?>
            <p class="empty-state">Photos will appear here once they have been added from the admin area.</p>
        <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($galleryPreview as $item): ?>
                <a class="gallery-grid__item" href="<?= site_url('/gallery.php') ?>">
                    <img src="<?= e($item['thumbnail_path'] ? upload_url($item['thumbnail_path']) : ($item['file_path'] ? upload_url($item['file_path']) : asset_url('images/placeholders/gallery-placeholder-1.svg'))) ?>" alt="<?= e($item['alt_text'] ?: 'Club photo') ?>" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--alt text-center">
    <div class="container">
        <h2>Join The Club</h2>
        <p style="max-width:42rem;margin:0 auto var(--space-md);"><?= e(get_setting('membership_cta_text')) ?></p>
        <a class="btn btn--primary" href="<?= site_url('/membership.php') ?>">Find Out About Membership</a>
    </div>
</section>

<section class="section">
    <div class="container info-grid">
        <div class="info-block">
            <h3>Opening Times</h3>
            <p><?= nl2br(e(get_setting('opening_hours'))) ?></p>
        </div>
        <div class="info-block">
            <h3>Address</h3>
            <address>
                <?= nl2br(e(get_setting('address_line1') . "\n" . get_setting('address_line2') . "\n" . get_setting('address_town') . ' ' . get_setting('address_postcode'))) ?>
            </address>
        </div>
        <div class="info-block">
            <h3>Get In Touch</h3>
            <p>
                <?php if (get_setting('contact_phone')): ?><a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a><br><?php endif; ?>
                <?php if (get_setting('contact_email')): ?><a href="mailto:<?= e(get_setting('contact_email')) ?>"><?= e(get_setting('contact_email')) ?></a><br><?php endif; ?>
                <a href="<?= site_url('/contact.php') ?>">Contact form &raquo;</a>
            </p>
        </div>
    </div>
</section>

<?php if (get_setting('map_embed_src')): ?>
<section class="section section--alt">
    <div class="container">
        <h2>Find Us</h2>
        <div class="map-embed" data-embed-src="<?= e(get_setting('map_embed_src')) ?>" data-embed-title="Map showing club location">
            <div class="video-consent" style="background:var(--color-primary-light);color:var(--color-text);">
                <p>The map is provided by Google Maps, which sets its own cookies once loaded.</p>
                <button type="button" class="btn btn--secondary map-consent-btn">Load Map</button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
