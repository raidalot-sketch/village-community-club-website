<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle = 'My Profile';
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirm'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    } elseif (mb_strlen($newPassword) < 10) {
        $errors[] = 'Your new password must be at least 10 characters long.';
    } elseif ($newPassword !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }

    if (empty($errors)) {
        db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
            ->execute(['h' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $_SESSION['admin_id']]);
        audit_log('user.password_change', 'Changed own password');
        $success = 'Your password has been updated.';
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel" style="max-width:520px;">
    <h2 style="margin-top:0;">Account Details</h2>
    <p><strong>Name:</strong> <?= e($admin['full_name']) ?><br>
       <strong>Username:</strong> <?= e($admin['username']) ?><br>
       <strong>Email:</strong> <?= e($admin['email']) ?><br>
       <strong>Role:</strong> <?= e(ucfirst($admin['role'])) ?></p>

    <h2>Change Password</h2>
    <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= site_url('/admin/profile.php') ?>">
        <?php csrf_field(); ?>
        <div class="form-field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
        <div class="form-field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" minlength="10" required autocomplete="new-password"></div>
        <div class="form-field"><label for="new_password_confirm">Confirm new password</label><input type="password" id="new_password_confirm" name="new_password_confirm" minlength="10" required autocomplete="new-password"></div>
        <div><button type="submit" class="btn btn--primary">Update Password</button></div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
