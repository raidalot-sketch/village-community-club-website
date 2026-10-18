<?php
require_once __DIR__.'/includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Use the log out button in your account.');}
csrf_verify();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);session_regenerate_id(true);flash_success('You have logged out.');redirect('/login.php');
