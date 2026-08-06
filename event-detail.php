<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM events WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL");
$stmt->execute(['slug' => $slug]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="section container text-center"><h1>Event Not Found</h1><p>Sorry, we could not find that event. It may have already taken place or been removed.</p><p><a class="btn btn--primary" href="' . site_url('/events.php') . '">Back to Events</a></p></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$isPast = strtotime($event['event_date']) < strtotime('today');

seo_set([
    'title' => $event['meta_title'] ?: $event['title'],
    'description' => $event['meta_description'] ?: truncate_text($event['description'] ?? '', 160),
    'image' => $event['featured_image'] ? upload_url($event['featured_image']) : '',
]);
seo_add_schema(schema_event($event));

require_once __DIR__ . '/includes/header.php';
?>

<article class="section">
    <div class="container" style="max-width:820px;">
        <p><a href="<?= site_url('/events.php') ?>">&laquo; Back to Events</a></p>
        <?php if ($isPast): ?><span class="tag">Past event</span><?php elseif ($event['is_featured']): ?><span class="card__badge">Featured</span><?php endif; ?>
        <h1><?= e($event['title']) ?></h1>

        <img src="<?= e($event['featured_image'] ? upload_url($event['featured_image']) : asset_url('images/placeholders/event-placeholder.svg')) ?>" alt="" style="border-radius:var(--radius-lg);margin-bottom:var(--space-md);" loading="lazy">

        <div class="info-grid" style="margin-bottom:var(--space-md);">
            <div class="info-block">
                <h3>When</h3>
                <p><?= e(format_date($event['event_date'], 'l j F Y')) ?><br>
                <?php if ($event['start_time']): ?><?= e(format_time($event['start_time'])) ?><?= $event['end_time'] ? ' – ' . e(format_time($event['end_time'])) : '' ?><?php endif; ?></p>
            </div>
            <div class="info-block">
                <h3>Where</h3>
                <p><?= e($event['venue']) ?><br><?= e($event['address']) ?></p>
            </div>
            <div class="info-block">
                <h3>Cost</h3>
                <p><?= $event['is_free'] ? 'Free to attend' : '£' . number_format((float) $event['ticket_price'], 2) ?></p>
                <?php if ($event['booking_info']): ?><p><?= e($event['booking_info']) ?></p><?php endif; ?>
                <?php if ($event['booking_link'] && !$isPast): ?>
                    <a class="btn btn--primary" href="<?= e($event['booking_link']) ?>" target="_blank" rel="noopener noreferrer">Book / More Info</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="rich-content"><?= $event['description'] ?></div>
    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
