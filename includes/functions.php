<?php
/**
 * General-purpose helper functions shared by public and admin pages.
 */

/** Escape a string for safe HTML output. Use this around every value printed into a template. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Build an absolute site URL from a root-relative path, e.g. site_url('/news.php'). */
function site_url(string $path = ''): string
{
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

/** Build a URL to a static asset in /assets, with cache-busting based on file mtime. */
function asset_url(string $path): string
{
    $full = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = file_exists($full) ? filemtime($full) : time();
    return site_url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Build a URL to a stored upload. $relativePath is what is stored in the database. */
function upload_url(?string $relativePath): string
{
    if (empty($relativePath)) {
        return '';
    }
    return site_url('/uploads/' . ltrim($relativePath, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : site_url($path)));
    exit;
}

/** Turn a title into a clean, unique-ready URL slug. */
function slugify(string $text): string
{
    $text = trim($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    return $text === '' ? 'item-' . substr(bin2hex(random_bytes(3)), 0, 6) : $text;
}

/** Ensure a slug is unique within a table, appending -2, -3, etc. as needed. */
function unique_slug(string $table, string $slug, ?int $excludeId = null): string
{
    $pdo = db();
    $base = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function format_date(?string $date, string $format = 'j F Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function format_time(?string $time, string $format = 'g:ia'): string
{
    if (empty($time)) {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date($format, $ts) : '';
}

function truncate_text(string $text, int $length = 160): string
{
    $text = trim(strip_tags($text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . '…';
}

/** Get a value from the settings table, with a fallback default. Cached per-request. */
function get_setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            // settings table may not exist yet (e.g. during install) — fall through to defaults
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = :v2');
    $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
}

/** Simple pagination helper. Returns ['page','perPage','offset']. */
function paginate(int $totalItems, int $perPage = 9): array
{
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return compact('page', 'perPage', 'offset', 'totalPages');
}

function pagination_links(int $page, int $totalPages, string $baseQuery = ''): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $sep = $baseQuery ? '&' : '';
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        $html .= '<a class="pagination__link" href="?' . $baseQuery . $sep . 'page=' . ($page - 1) . '" rel="prev">&laquo; Previous</a>';
    }
    $html .= '<span class="pagination__status">Page ' . $page . ' of ' . $totalPages . '</span>';
    if ($page < $totalPages) {
        $html .= '<a class="pagination__link" href="?' . $baseQuery . $sep . 'page=' . ($page + 1) . '" rel="next">Next &raquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}

/** Record an entry in the audit log. Call after any significant admin action. */
function audit_log(string $action, string $details = ''): void
{
    if (empty($_SESSION['admin_id'])) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO audit_log (admin_id, action, details, ip_address, created_at)
        VALUES (:admin_id, :action, :details, :ip, NOW())');
    $stmt->execute([
        'admin_id' => $_SESSION['admin_id'],
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

/** Current full request path, used to highlight active nav items. */
function current_page(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}

function is_active_page(string ...$files): string
{
    return in_array(current_page(), $files, true) ? ' nav__link--active' : '';
}

/** Fetch an editable content block from the `pages` table by its key. */
function get_page(string $pageKey): ?array
{
    static $cache = [];
    if (isset($cache[$pageKey])) {
        return $cache[$pageKey];
    }
    $stmt = db()->prepare('SELECT * FROM pages WHERE page_key = :k');
    $stmt->execute(['k' => $pageKey]);
    $page = $stmt->fetch() ?: null;
    $cache[$pageKey] = $page;
    return $page;
}

/** True if $value is empty or begins with the visible "[PLACEHOLDER" marker used throughout demo content. */
function is_placeholder(?string $value): bool
{
    return empty($value) || str_starts_with(trim($value), '[PLACEHOLDER') || str_starts_with(trim($value), '[SAMPLE');
}
