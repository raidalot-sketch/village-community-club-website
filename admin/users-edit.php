<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_administrator();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$user = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if (!$user) {
        flash_error('User not found.');
        redirect('/admin/users.php');
    }
}

$pageTitle = $user ? 'Edit User' : 'Add User';
$errors = [];
$values = $user ?: ['username' => '', 'email' => '', 'full_name' => '', 'role' => 'editor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['username'] = trim($_POST['username'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $values['full_name'] = sanitize_text($_POST['full_name'] ?? '', 120);
    $values['role'] = ($_POST['role'] ?? 'editor') === 'administrator' ? 'administrator' : 'editor';
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_.\-]{3,60}$/', $values['username'])) {
        $errors[] = 'Username must be 3–60 characters: letters, numbers, dots, dashes and underscores only.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($values['full_name'] === '') {
        $errors[] = 'Please enter a full name.';
    }
    if (!$id && mb_strlen($password) < 10) {
        $errors[] = 'Please set a password of at least 10 characters for this new user.';
    }
    if ($password !== '' && mb_strlen($password) < 10) {
        $errors[] = 'The new password must be at least 10 characters long.';
    }
    if ($id === (int) $_SESSION['admin_id'] && $values['role'] !== 'administrator') {
        $errors[] = 'You cannot remove your own administrator role.';
    }

    if (empty($errors)) {
        try {
            if ($id) {
                if ($password !== '') {
                    db()->prepare('UPDATE admin_users SET username=:u, email=:e, full_name=:f, role=:r, password_hash=:p WHERE id=:id')
                        ->execute(['u' => $values['username'], 'e' => $values['email'], 'f' => $values['full_name'], 'r' => $values['role'], 'p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
                } else {
                    db()->prepare('UPDATE admin_users SET username=:u, email=:e, full_name=:f, role=:r WHERE id=:id')
                        ->execute(['u' => $values['username'], 'e' => $values['email'], 'f' => $values['full_name'], 'r' => $values['role'], 'id' => $id]);
                }
                audit_log('user.update', "Updated admin user #$id");
                flash_success('User updated.');
            } else {
                db()->prepare('INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active, created_at)
                    VALUES (:u, :e, :p, :f, :r, 1, NOW())')
                    ->execute(['u' => $values['username'], 'e' => $values['email'], 'p' => password_hash($password, PASSWORD_DEFAULT), 'f' => $values['full_name'], 'r' => $values['role']]);
                $newId = (int) db()->lastInsertId();
                audit_log('user.create', "Created admin user #$newId: {$values['username']}");
                flash_success('User created.');
            }
            redirect('/admin/users.php');
        } catch (PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'That username or email is already in use.' : 'Could not save the user.';
        }
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/users-edit.php' . ($id ? '?id=' . $id : '')) ?>">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-field"><label for="full_name">Full name</label><input type="text" id="full_name" name="full_name" value="<?= e($values['full_name']) ?>" required></div>
    <div class="form-row">
        <div class="form-field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= e($values['username']) ?>" required></div>
        <div class="form-field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required></div>
    </div>
    <div class="form-field">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="editor" <?= $values['role'] === 'editor' ? 'selected' : '' ?>>Editor — can manage content only</option>
            <option value="administrator" <?= $values['role'] === 'administrator' ? 'selected' : '' ?>>Administrator — full access</option>
        </select>
    </div>
    <div class="form-field">
        <label for="password"><?= $id ? 'New password' : 'Password' ?></label>
        <input type="password" id="password" name="password" minlength="10" <?= $id ? '' : 'required' ?>>
        <small class="hint"><?= $id ? 'Leave blank to keep the current password.' : 'At least 10 characters.' ?></small>
    </div>

    <div>
        <button type="submit" class="btn btn--primary">Save User</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/users.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
