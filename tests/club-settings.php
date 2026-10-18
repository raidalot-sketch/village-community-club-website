<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
if (DB_NAME !== 'nettlebed_preview') { throw new RuntimeException('Preview database required.'); }
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/mailer.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
db()->beginTransaction();
try {
    set_setting('test_uploaded_photo', 'pages/club-test.jpg');
    set_setting('address_line1', '32 High Street');
    set_setting('address_line2', '');
    set_setting('address_town', 'Nettlebed');
    set_setting('address_postcode', 'RG9 5DD');
    check(str_contains(site_image_url('test_missing_image', 'images/club-building.jpg'), '/assets/images/club-building.jpg'), 'Missing image uses bundled club photograph');
    check(site_image_url('test_uploaded_photo', 'images/club-building.jpg') === SITE_URL.'/uploads/pages/club-test.jpg', 'Saved photo setting overrides the bundled photograph');
    check(club_address() === "32 High Street\nNettlebed\nRG9 5DD", 'Shared address uses editable fields and omits empty lines');
    check(send_email('preview@example.invalid', 'Disabled mail test', 'Local test') === false, 'Preview mail is disabled without sending');
} finally { db()->rollBack(); }
