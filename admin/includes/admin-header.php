<?php
/**
 * Shared admin shell header. Included by every admin page after
 * require_login() (or require_administrator()) has already run.
 * Expects $pageTitle to be set by the including page.
 */
$pageTitle = $pageTitle ?? 'Admin';
$admin = current_admin();
$currentFile = basename($_SERVER['SCRIPT_NAME']);

function admin_nav_active(string ...$files): string
{
    global $currentFile;
    return in_array($currentFile, $files, true) ? ' is-active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> — Admin — <?= e(get_setting('site_name', SITE_NAME)) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('images/placeholders/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset_url('vendor/quill/quill.snow.css') ?>">
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main-content">Skip to main content</a>
<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar__brand"><a href="<?= site_url('/admin/index.php') ?>"><?= e(get_setting('site_name', SITE_NAME)) ?></a></div>
        <ul class="admin-nav">
            <li><a class="<?= admin_nav_active('index.php') ?>" href="<?= site_url('/admin/index.php') ?>">Dashboard</a></li>

            <li class="admin-nav__group-title">Content</li>
            <li><a class="<?= admin_nav_active('news.php', 'news-edit.php') ?>" href="<?= site_url('/admin/news.php') ?>">News</a></li>
            <li><a class="<?= admin_nav_active('events.php', 'events-edit.php') ?>" href="<?= site_url('/admin/events.php') ?>">Events</a></li>
            <li><a class="<?= admin_nav_active('newsletters.php', 'newsletters-edit.php') ?>" href="<?= site_url('/admin/newsletters.php') ?>">Newsletters</a></li>
            <li><a class="<?= admin_nav_active('gallery-albums.php', 'gallery-album-edit.php') ?>" href="<?= site_url('/admin/gallery-albums.php') ?>">Gallery</a></li>
            <li><a class="<?= admin_nav_active('committee.php', 'committee-edit.php') ?>" href="<?= site_url('/admin/committee.php') ?>">Committee</a></li>

            <li class="admin-nav__group-title">Website</li>
            <li><a class="<?= admin_nav_active('pages.php', 'pages-edit.php') ?>" href="<?= site_url('/admin/pages.php') ?>">Page Content</a></li>
            <li><a class="<?= admin_nav_active('settings.php') ?>" href="<?= site_url('/admin/settings.php') ?>">Settings</a></li>
            <li><a class="<?= admin_nav_active('enquiries.php') ?>" href="<?= site_url('/admin/enquiries.php') ?>">Enquiries</a></li>

            <?php if (is_administrator()): ?>
            <li class="admin-nav__group-title">Administration</li>
            <li><a class="<?= admin_nav_active('users.php', 'users-edit.php') ?>" href="<?= site_url('/admin/users.php') ?>">Users</a></li>
            <li><a class="<?= admin_nav_active('audit-log.php') ?>" href="<?= site_url('/admin/audit-log.php') ?>">Audit Log</a></li>
            <?php endif; ?>

            <li class="admin-nav__group-title">Account</li>
            <li><a class="<?= admin_nav_active('profile.php') ?>" href="<?= site_url('/admin/profile.php') ?>">My Profile</a></li>
            <li><a href="<?= site_url('/index.php') ?>" target="_blank" rel="noopener">View Website &#8599;</a></li>
            <li><a href="<?= site_url('/admin/logout.php') ?>">Log Out</a></li>
        </ul>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="btn btn--secondary admin-topbar__toggle" id="sidebarToggle" aria-label="Toggle menu">&#9776;</button>
            <h1 style="margin:0;font-size:1.3rem;"><?= e($pageTitle) ?></h1>
            <div class="admin-user-menu">
                <span><?= e($admin['full_name'] ?? '') ?></span>
                <span class="admin-role-badge"><?= e(ucfirst($admin['role'] ?? '')) ?></span>
            </div>
        </header>
        <main class="admin-content" id="admin-main-content">
            <?php flash_render(); ?>
