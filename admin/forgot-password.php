<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/admin/index.php');
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $token = create_password_reset_token($email);
        if ($token) {
            $resetLink = site_url('/admin/reset-password.php?token=' . $token);
            $body = "A password reset was requested for your " . get_setting('site_name', SITE_NAME) . " admin account.\n\n"
                . "Click the link below to choose a new password. This link expires in 1 hour.\n\n"
                . $resetLink . "\n\n"
                . "If you did not request this, you can safely ignore this email.";
            send_email($email, 'Password Reset Request', $body);
        }
    }

    // Always show the same message, whether or not the email was found,
    // so this form cannot be used to discover which emails have accounts.
    $message = 'If an account exists with that email address, a password reset link has been sent.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Forgotten Password — Admin</title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset_url('css/nettlebed.css') ?>">
</head>
<body class="admin-body">
<div class="login-wrap">
    <div class="login-card">
        <h1>Forgotten Password</h1>
        <?php if ($message): ?>
            <div class="alert alert--success"><?= e($message) ?></div>
            <p class="text-center"><a href="<?= site_url('/admin/login.php') ?>">Back to login</a></p>
        <?php else: ?>
            <p>Enter your admin email address and we'll send you a link to reset your password.</p>
            <form class="form-grid" method="post" action="<?= site_url('/admin/forgot-password.php') ?>">
                <?php csrf_field(); ?>
                <div class="form-field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <button type="submit" class="btn btn--primary btn--block">Send Reset Link</button>
            </form>
            <p class="text-center mt-lg"><a href="<?= site_url('/admin/login.php') ?>">&laquo; Back to login</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
