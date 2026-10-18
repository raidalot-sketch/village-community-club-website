<?php
// Club platform modules share the existing bootstrap, escaping, CSRF and PDO helpers.
date_default_timezone_set('Europe/London');
foreach (['member-auth','club-membership','club-uploads','community','polls','club-integrations'] as $module) {
    $path = __DIR__ . '/' . $module . '.php';
    if (is_file($path)) require_once $path;
}
