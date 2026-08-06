<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    audit_log('logout', 'User logged out');
}
auth_logout();
redirect('/admin/login.php');
