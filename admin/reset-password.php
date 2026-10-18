<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/admin/index.php');
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$adminId = $token ? verify_password_reset_token($token) : null;
$error = null;
$done = false;

if (!$token || !$adminId) {
    $error = 'This password reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (mb_strlen($password) < 10) {
        $error = 'Your new password must be at least 10 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        consume_password_reset_token($token, $password);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Reset Password — Admin</title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/nettlebed.css') ?>">
</head>
<body class="admin-body">
<div class="login-wrap">
    <div class="login-card">
        <h1>Reset Password</h1>
        <?php if ($done): ?>
            <div class="alert alert--success">Your password has been updated. You can now log in.</div>
            <p class="text-center"><a class="btn btn--primary btn--block" href="<?= site_url('/admin/login.php') ?>">Go to Login</a></p>
        <?php elseif ($error && !$adminId): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
            <p class="text-center"><a href="<?= site_url('/admin/forgot-password.php') ?>">Request a new reset link</a></p>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
            <form class="form-grid" method="post" action="<?= site_url('/admin/reset-password.php') ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-field">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required minlength="10" autofocus>
                    <small class="hint">At least 10 characters.</small>
                </div>
                <div class="form-field">
                    <label for="password_confirm">Confirm new password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="10">
                </div>
                <button type="submit" class="btn btn--primary btn--block">Set New Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
