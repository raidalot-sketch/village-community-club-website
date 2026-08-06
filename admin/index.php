<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle = 'Dashboard';

$counts = [
    'news' => (int) db()->query("SELECT COUNT(*) c FROM news_articles WHERE deleted_at IS NULL")->fetch()['c'],
    'events' => (int) db()->query("SELECT COUNT(*) c FROM events WHERE deleted_at IS NULL")->fetch()['c'],
    'newsletters' => (int) db()->query('SELECT COUNT(*) c FROM newsletters')->fetch()['c'],
    'media' => (int) db()->query('SELECT COUNT(*) c FROM media_items')->fetch()['c'],
];

$recentNews = db()->query("SELECT title, status, updated_at FROM news_articles WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5")->fetchAll();
$recentEvents = db()->query("SELECT title, status, updated_at FROM events WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5")->fetchAll();
$upcomingEvents = db()->query("SELECT title, event_date, slug FROM events WHERE status='published' AND deleted_at IS NULL AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();
$newEnquiries = (int) db()->query("SELECT COUNT(*) c FROM contact_enquiries WHERE status='new'")->fetch()['c'];

require_once __DIR__ . '/includes/admin-header.php';
?>

<p>Welcome back, <strong><?= e($admin['full_name']) ?></strong>. Here's what's happening on the website.</p>

<div class="quick-actions">
    <a class="btn btn--primary" href="<?= site_url('/admin/news-edit.php') ?>">+ Add News Article</a>
    <a class="btn btn--primary" href="<?= site_url('/admin/events-edit.php') ?>">+ Add Event</a>
    <a class="btn btn--primary" href="<?= site_url('/admin/newsletters-edit.php') ?>">+ Add Newsletter</a>
    <a class="btn btn--primary" href="<?= site_url('/admin/gallery-album-edit.php') ?>">+ Add Gallery Album</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-card__value"><?= $counts['news'] ?></div><div class="stat-card__label">News Articles</div></div>
    <div class="stat-card"><div class="stat-card__value"><?= $counts['events'] ?></div><div class="stat-card__label">Events</div></div>
    <div class="stat-card"><div class="stat-card__value"><?= $counts['newsletters'] ?></div><div class="stat-card__label">Newsletters</div></div>
    <div class="stat-card"><div class="stat-card__value"><?= $counts['media'] ?></div><div class="stat-card__label">Gallery Items</div></div>
</div>

<?php if ($newEnquiries > 0): ?>
<div class="alert alert--success" style="background:var(--color-primary-light);">
    You have <strong><?= $newEnquiries ?></strong> new contact enquir<?= $newEnquiries === 1 ? 'y' : 'ies' ?> awaiting a reply.
    <a href="<?= site_url('/admin/enquiries.php') ?>">View enquiries &raquo;</a>
</div>
<?php endif; ?>

<div class="form-row">
    <div class="admin-panel">
        <div class="admin-panel__header"><h2 style="margin:0;">Upcoming Events</h2></div>
        <?php if (empty($upcomingEvents)): ?>
            <p>No upcoming events scheduled.</p>
        <?php else: ?>
            <ul class="stack">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <li><?= e(format_date($ev['event_date'])) ?> — <?= e($ev['title']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <div class="admin-panel__header"><h2 style="margin:0;">Recently Updated</h2></div>
        <ul class="stack">
            <?php foreach ($recentNews as $n): ?>
                <li>News: <?= e($n['title']) ?> <span class="status-pill status-pill--<?= e($n['status']) ?>"><?= e($n['status']) ?></span></li>
            <?php endforeach; ?>
            <?php foreach ($recentEvents as $ev): ?>
                <li>Event: <?= e($ev['title']) ?> <span class="status-pill status-pill--<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span></li>
            <?php endforeach; ?>
            <?php if (empty($recentNews) && empty($recentEvents)): ?>
                <li>No content yet — use the quick-add buttons above to get started.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
