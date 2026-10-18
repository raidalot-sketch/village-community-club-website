<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => site_url('/index.php'), 'priority' => '1.0'],
    ['loc' => site_url('/about.php'), 'priority' => '0.8'],
    ['loc' => site_url('/news.php'), 'priority' => '0.8'],
    ['loc' => site_url('/events.php'), 'priority' => '0.8'],
    ['loc' => site_url('/newsletters.php'), 'priority' => '0.6'],
    ['loc' => site_url('/gallery.php'), 'priority' => '0.6'],
    ['loc' => site_url('/membership.php'), 'priority' => '0.8'],
    ['loc' => site_url('/facilities.php'), 'priority' => '0.6'],
    ['loc' => site_url('/hall-hire.php'), 'priority' => '0.8'],
    ['loc' => site_url('/committee.php'), 'priority' => '0.5'],
    ['loc' => site_url('/contact.php'), 'priority' => '0.7'],
    ['loc' => site_url('/privacy-policy.php'), 'priority' => '0.2'],
    ['loc' => site_url('/cookie-policy.php'), 'priority' => '0.2'],
    ['loc' => site_url('/accessibility.php'), 'priority' => '0.2'],
];

$news = db()->query("SELECT slug, updated_at FROM news_articles WHERE status='published' AND deleted_at IS NULL")->fetchAll();
foreach ($news as $n) {
    $urls[] = ['loc' => site_url('/news-detail.php?slug=' . urlencode($n['slug'])), 'lastmod' => $n['updated_at'], 'priority' => '0.6'];
}

$events = db()->query("SELECT slug, updated_at FROM events WHERE status='published' AND deleted_at IS NULL")->fetchAll();
foreach ($events as $ev) {
    $urls[] = ['loc' => site_url('/event-detail.php?slug=' . urlencode($ev['slug'])), 'lastmod' => $ev['updated_at'], 'priority' => '0.6'];
}

$albums = db()->query('SELECT slug, updated_at FROM gallery_albums')->fetchAll();
foreach ($albums as $a) {
    $urls[] = ['loc' => site_url('/gallery-album.php?slug=' . urlencode($a['slug'])), 'lastmod' => $a['updated_at'], 'priority' => '0.4'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($url['lastmod'])) {
        echo '    <lastmod>' . date('Y-m-d', strtotime($url['lastmod'])) . "</lastmod>\n";
    }
    echo '    <priority>' . $url['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
