<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_render_head(); ?>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('images/placeholders/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">
    <div class="site-header__bar container">
        <a class="site-header__brand" href="<?= site_url('/index.php') ?>">
            <img class="site-header__logo" src="<?= e(get_setting('logo_path') ? upload_url(get_setting('logo_path')) : asset_url('images/placeholders/logo-placeholder.svg')) ?>" alt="<?= e(get_setting('site_name', SITE_NAME)) ?> logo" width="56" height="56">
            <span class="site-header__titles">
                <span class="site-header__name"><?= e(get_setting('site_name', SITE_NAME)) ?></span>
                <span class="site-header__village"><?= e(get_setting('site_village', SITE_VILLAGE)) ?></span>
            </span>
        </a>

        <button type="button" class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="primaryNav">
            <span class="nav-toggle__icon" aria-hidden="true"></span>
            <span class="nav-toggle__label">Menu</span>
        </button>

        <?php include APP_ROOT . '/includes/nav.php'; ?>
    </div>
</header>

<?php if (empty($_COOKIE['vcc_cookie_consent'])): ?>
<div class="cookie-banner" id="cookieBanner" role="region" aria-label="Cookie notice">
    <div class="container cookie-banner__inner">
        <p>We use a small number of essential cookies to run this site, and optional cookies for embedded maps and videos. See our <a href="<?= site_url('/cookie-policy.php') ?>">Cookie Policy</a> for details.</p>
        <div class="cookie-banner__actions">
            <button type="button" class="btn btn--secondary" id="cookieDecline">Essential only</button>
            <button type="button" class="btn btn--primary" id="cookieAccept">Accept all</button>
        </div>
    </div>
</div>
<?php endif; ?>

<main id="main-content">
<?php flash_render(); ?>
