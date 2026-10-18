<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/admin/index.php');
}

$error = null;
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $result = attempt_login($identifier, $password);
        if ($result['success']) {
            audit_log('login', 'Successful login');
            redirect('/admin/index.php');
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login — <?= e(get_setting('site_name', SITE_NAME)) ?></title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/nettlebed.css') ?>">
</head>
<body class="admin-body">
<div class="login-wrap">
    <div class="login-card">
        <img class="login-card__logo" src="<?= e(get_setting('logo_path') ? upload_url(get_setting('logo_path')) : asset_url('images/club-logo.jpg')) ?>" alt="">
        <h1>Admin Login</h1>
        <p style="text-align:center;color:var(--color-text-muted);"><?= e(get_setting('site_name', SITE_NAME)) ?></p>

        <?php if ($timeout): ?>
            <div class="alert alert--error">You were logged out after a period of inactivity. Please log in again.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="form-grid" method="post" action="<?= site_url('/admin/login.php') ?>">
            <?php csrf_field(); ?>
            <div class="form-field">
                <label for="identifier">Username or email</label>
                <input type="text" id="identifier" name="identifier" required autofocus autocomplete="username">
            </div>
            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn--primary btn--block">Log In</button>
        </form>
        <p class="text-center mt-lg"><a href="<?= site_url('/admin/forgot-password.php') ?>">Forgotten your password?</a></p>
        <p class="text-center"><a href="<?= site_url('/index.php') ?>">&laquo; Back to website</a></p>
    </div>
</div>
</body>
</html>
