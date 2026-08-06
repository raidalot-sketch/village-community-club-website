<?php
/**
 * Per-page SEO helpers: title, meta description, canonical URL, Open Graph
 * tags, and JSON-LD structured data. Call seo_set() near the top of a
 * public page, then seo_render_head() inside includes/header.php.
 */

$GLOBALS['__seo'] = [
    'title' => get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : 'The Village Community Club'),
    'description' => 'The official website of ' . (defined('SITE_NAME') ? SITE_NAME : 'The Village Community Club') . ', serving ' . (defined('SITE_VILLAGE') ? SITE_VILLAGE : 'Little Meadow Village') . '.',
    'image' => '',
    'canonical' => '',
    'schema' => [],
];

function seo_set(array $opts): void
{
    $GLOBALS['__seo'] = array_merge($GLOBALS['__seo'], array_filter($opts, fn($v) => $v !== null));
}

function seo_add_schema(array $schema): void
{
    $GLOBALS['__seo']['schema'][] = $schema;
}

function seo_render_head(): void
{
    $seo = $GLOBALS['__seo'];
    $fullTitle = $seo['title'] . ' | ' . get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : 'The Village Community Club');
    $canonical = $seo['canonical'] ?: site_url($_SERVER['REQUEST_URI'] ?? '');
    $description = $seo['description'];

    echo "<title>" . e($fullTitle) . "</title>\n";
    echo '<meta name="description" content="' . e($description) . '">' . "\n";
    echo '<link rel="canonical" href="' . e($canonical) . '">' . "\n";

    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . e(get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : '')) . '">' . "\n";
    echo '<meta property="og:title" content="' . e($fullTitle) . '">' . "\n";
    echo '<meta property="og:description" content="' . e($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . e($canonical) . '">' . "\n";
    if ($seo['image']) {
        echo '<meta property="og:image" content="' . e($seo['image']) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

    if (!empty($seo['schema'])) {
        foreach ($seo['schema'] as $schema) {
            echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }
}

/** Organization/LocalBusiness schema for the club, used site-wide. */
function schema_organization(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : ''),
        'description' => get_setting('about_summary', ''),
        'url' => site_url('/'),
        'telephone' => get_setting('contact_phone', ''),
        'email' => get_setting('contact_email', ''),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => get_setting('address_line1', ''),
            'addressLocality' => get_setting('address_town', defined('SITE_VILLAGE') ? SITE_VILLAGE : ''),
            'postalCode' => get_setting('address_postcode', ''),
            'addressCountry' => get_setting('address_country', 'GB'),
        ],
    ];
}

function schema_article(array $article): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article['title'],
        'description' => truncate_text($article['summary'] ?? '', 200),
        'image' => $article['featured_image'] ? upload_url($article['featured_image']) : '',
        'datePublished' => $article['published_at'],
        'dateModified' => $article['updated_at'] ?? $article['published_at'],
        'author' => ['@type' => 'Person', 'name' => $article['author'] ?: 'Committee'],
    ];
}

function schema_event(array $event): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event['title'],
        'startDate' => $event['event_date'] . ($event['start_time'] ? 'T' . $event['start_time'] : ''),
        'endDate' => $event['event_date'] . ($event['end_time'] ? 'T' . $event['end_time'] : ''),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [
            '@type' => 'Place',
            'name' => $event['venue'],
            'address' => $event['address'],
        ],
        'image' => $event['featured_image'] ? upload_url($event['featured_image']) : '',
        'description' => truncate_text($event['description'] ?? '', 200),
        'offers' => $event['is_free']
            ? ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP']
            : ['@type' => 'Offer', 'price' => $event['ticket_price'] ?: '0', 'priceCurrency' => 'GBP'],
    ];
}
