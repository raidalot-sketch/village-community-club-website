<?php
/**
 * Sample configuration file.
 *
 * Copy this file to "config.php" in the same folder and fill in your own
 * values. "config.php" is listed in .gitignore and must NEVER be committed
 * to version control or left readable by the public.
 *
 * The installer (install.php) will create config.php for you automatically
 * — you only need to edit this by hand if you are configuring the site
 * without running the installer.
 */

// ---------------------------------------------------------------------
// Database connection
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'village_club');
define('DB_USER', 'db_username');
define('DB_PASS', 'db_password');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Site identity (also editable later from Admin > Settings)
// ---------------------------------------------------------------------
define('SITE_NAME', 'The Village Community Club');
define('SITE_VILLAGE', 'Little Meadow Village');
define('SITE_URL', 'http://localhost/village-community-club'); // no trailing slash

// ---------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------
// Random 32+ character string, unique to this installation.
// Used to key CSRF tokens and the remember-me cookie signature.
// Generate one with: bin2hex(random_bytes(32))
define('APP_SECRET', 'CHANGE-THIS-TO-A-RANDOM-64-CHARACTER-STRING-BEFORE-LAUNCH');

// Force all cookies/session to HTTPS only. Set true once the live site
// has a valid SSL certificate (recommended for all production hosting).
define('FORCE_HTTPS', false);

// Minutes of inactivity before an admin is automatically logged out.
define('SESSION_IDLE_TIMEOUT_MINUTES', 20);

// ---------------------------------------------------------------------
// Outgoing email (contact form + password resets)
// ---------------------------------------------------------------------
// 'mail'  = use PHP's built-in mail() function (works on most cPanel hosts)
// 'smtp'  = use the SMTP settings below (recommended if mail() is unreliable)
define('MAIL_METHOD', 'mail');

define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', SITE_NAME);
define('MAIL_TO_ADDRESS', 'enquiries@example.com'); // where contact form messages are sent

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// ---------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------
define('MAX_UPLOAD_IMAGE_BYTES', 5 * 1024 * 1024);   // 5 MB per image
define('MAX_UPLOAD_PDF_BYTES', 15 * 1024 * 1024);    // 15 MB per PDF
define('MAX_UPLOAD_VIDEO_BYTES', 30 * 1024 * 1024);  // 30 MB per video (if enabled)
define('ALLOW_VIDEO_UPLOADS', false); // most shared hosts prefer YouTube/Vimeo embeds instead

// ---------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------
// 'production' hides technical error details from visitors and logs them
// instead. Always use 'production' on the live website.
define('APP_ENV', 'production');

// This is set to true automatically by install.php and should be false
// (or the file removed) on every live/public installation.
define('INSTALLER_UNLOCKED', false);
