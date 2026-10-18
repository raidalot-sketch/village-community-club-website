<?php
/**
 * Application bootstrap.
 *
 * Every public page and every admin page includes this file first (via
 * require_once). It loads configuration, opens the database connection,
 * starts a securely-configured session, sets up error handling, and pulls
 * in the rest of the shared helper libraries.
 */

// Show nothing to the browser on fatal errors in production; log instead.
define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';

if (!file_exists($configFile)) {
    // No configuration yet — send everyone to the installer.
    $installer = '/install.php';
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: ' . $installer);
        exit;
    }
} else {
    require_once $configFile;
}

// ---------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/php-error.log');
if (!is_dir(APP_ROOT . '/logs')) {
    @mkdir(APP_ROOT . '/logs', 0750, true);
}

set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (defined('APP_ENV') && APP_ENV === 'production') {
        http_response_code(500);
        echo 'Sorry, something went wrong. Please try again shortly.';
    } else {
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    }
    exit;
});

// ---------------------------------------------------------------------
// Secure session configuration (must happen before session_start)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = defined('FORCE_HTTPS') && FORCE_HTTPS;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('vcc_session');
    session_start();
}

if (defined('FORCE_HTTPS') && FORCE_HTTPS && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

// ---------------------------------------------------------------------
// Security headers (sent on every request)
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' https:; style-src 'self' 'unsafe-inline'; script-src 'self' https://www.youtube.com https://player.vimeo.com; frame-src https://www.facebook.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com; worker-src 'self' blob:; font-src 'self'");

// ---------------------------------------------------------------------
// Shared helper libraries
// ---------------------------------------------------------------------
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/sanitize.php';
require_once APP_ROOT . '/includes/flash.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/seo.php';
require_once APP_ROOT . '/includes/mailer.php';

// ---------------------------------------------------------------------
// Idle session timeout (applies to logged-in admin users only)
// ---------------------------------------------------------------------
if (!empty($_SESSION['admin_id'])) {
    $timeoutSeconds = (defined('SESSION_IDLE_TIMEOUT_MINUTES') ? SESSION_IDLE_TIMEOUT_MINUTES : 20) * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
        require_once APP_ROOT . '/includes/auth.php';
        auth_logout();
        header('Location: ' . site_url('/admin/login.php?timeout=1'));
        exit;
    }
    $_SESSION['last_activity'] = time();
}

require_once APP_ROOT . '/includes/club.php';
