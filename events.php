<?php
require_once __DIR__ . '/includes/bootstrap.php';

$showPast = isset($_GET['show']) && $_GET['show'] === 'past';

seo_set(['title' => $showPast ? 'Past Events' : 'Upcoming Events', 'description' => 'Events and activities at ' . get_setting('site_name', SITE_NAME)]);

if ($showPast) {
    $total = (int) db()->query("SELECT COUNT(*) c FROM events WHERE status='published' AND deleted_at IS NULL AND event_date < CURDATE()")->fetch()['c'];
    $p = paginate($total, 9);
    $stmt = db()->prepare("SELECT * FROM events WHERE status='published' AND deleted_at IS NULL AND event_date < CURDATE()
        ORDER BY event_date DESC LIMIT :limit OFFSET :offset");
} else {
    $total = (int) db()->query("SELECT COUNT(*) c FROM events WHERE status='published' AND deleted_at IS NULL AND event_date >= CURDATE()")->fetch()['c'];
    $p = paginate($total, 9);
    $stmt = db()->prepare("SELECT * FROM events WHERE status='published' AND deleted_at IS NULL AND event_date >= CURDATE()
        ORDER BY event_date ASC LIMIT :limit OFFSET :offset");
}
$stmt->bindValue('limit', $p['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $p['offset'], PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Club Events</h1>
        <p>Everything that's on at the Club — old and new friends alike are always welcome.</p>
        <div class="hero__actions" style="margin-top:var(--space-sm);">
            <a class="btn <?= !$showPast ? 'btn--primary' : 'btn--secondary' ?>" href="<?= site_url('/events.php') ?>">Upcoming</a>
            <a class="btn <?= $showPast ? 'btn--primary' : 'btn--secondary' ?>" href="<?= site_url('/events.php?show=past') ?>">Past Events</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($events)): ?>
            <p class="empty-state"><?= $showPast ? 'No past events to show yet.' : 'No upcoming events are scheduled yet. Check back soon.' ?></p>
        <?php else: ?>
        <div class="card-grid">
            <?php foreach ($events as $event): ?>
            <a class="card" href="<?= site_url('/event-detail.php?slug=' . urlencode($event['slug'])) ?>">
                <img class="card__image" src="<?= e($event['featured_image'] ? upload_url($event['featured_image']) : asset_url('images/placeholders/event-placeholder.svg')) ?>" alt="" loading="lazy">
                <div class="card__body">
                    <?php if ($event['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
                    <div class="card__meta"><?= e(format_date($event['event_date'])) ?><?= $event['start_time'] ? ' &middot; ' . e(format_time($event['start_time'])) : '' ?></div>
                    <h3 class="card__title"><?= e($event['title']) ?></h3>
                    <p class="card__excerpt"><?= e($event['venue']) ?></p>
                    <p class="tag"><?= $event['is_free'] ? 'Free' : 'From £' . number_format((float) $event['ticket_price'], 2) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?= pagination_links($p['page'], $p['totalPages'], 'show=' . ($showPast ? 'past' : 'upcoming')) ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
