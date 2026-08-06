<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$member = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM committee_members WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $member = $stmt->fetch();
    if (!$member) {
        flash_error('Committee member not found.');
        redirect('/admin/committee.php');
    }
}

$pageTitle = $member ? 'Edit Committee Member' : 'Add Committee Member';
$errors = [];
$values = $member ?: ['name' => '', 'role_title' => '', 'bio' => '', 'email' => '', 'phone' => '', 'photo' => '', 'is_published' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['name'] = sanitize_text($_POST['name'] ?? '', 150);
    $values['role_title'] = sanitize_text($_POST['role_title'] ?? '', 150);
    $values['bio'] = sanitize_text($_POST['bio'] ?? '', 1000);
    $values['email'] = sanitize_text($_POST['email'] ?? '', 190);
    $values['phone'] = sanitize_text($_POST['phone'] ?? '', 40);
    $values['is_published'] = !empty($_POST['is_published']) ? 1 : 0;

    if ($values['name'] === '') {
        $errors[] = 'Please enter a name.';
    }
    if ($values['role_title'] === '') {
        $errors[] = 'Please enter a committee role.';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address, or leave it blank.';
    }

    $photoPath = $values['photo'] ?? '';
    if (!empty($_FILES['photo']['name'])) {
        $upload = handle_upload('photo', 'committee', 'image');
        if ($upload['ok']) {
            $photoPath = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        $fields = [
            'name' => $values['name'], 'role' => $values['role_title'], 'bio' => $values['bio'],
            'email' => $values['email'], 'phone' => $values['phone'], 'photo' => $photoPath, 'published' => $values['is_published'],
        ];
        if ($id) {
            $fields['id'] = $id;
            db()->prepare('UPDATE committee_members SET name=:name, role_title=:role, bio=:bio, email=:email, phone=:phone,
                photo=:photo, is_published=:published WHERE id=:id')->execute($fields);
            flash_success('Committee member updated.');
        } else {
            $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) m FROM committee_members')->fetch()['m'];
            $fields['sort_order'] = $maxOrder + 1;
            db()->prepare('INSERT INTO committee_members (name, role_title, bio, email, phone, photo, is_published, sort_order, created_at)
                VALUES (:name, :role, :bio, :email, :phone, :photo, :published, :sort_order, NOW())')->execute($fields);
            flash_success('Committee member added.');
        }
        redirect('/admin/committee.php');
    }
    $values['photo'] = $photoPath;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/committee-edit.php' . ($id ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-row">
        <div class="form-field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required>
        </div>
        <div class="form-field">
            <label for="role_title">Committee role</label>
            <input type="text" id="role_title" name="role_title" value="<?= e($values['role_title']) ?>" placeholder="e.g. Chairperson" required>
        </div>
    </div>

    <div class="form-field">
        <label for="bio">Short biography</label>
        <textarea id="bio" name="bio" rows="3"><?= e($values['bio']) ?></textarea>
    </div>

    <div class="form-field">
        <label for="photo">Photograph</label>
        <?php if (!empty($values['photo'])): ?>
            <div class="current-image"><img class="thumb-preview" id="photoPreview" style="border-radius:50%;" src="<?= upload_url($values['photo']) ?>" alt=""><span>Current photo — choose a new file to replace it</span></div>
        <?php else: ?>
            <img class="thumb-preview" id="photoPreview" src="" alt="" hidden>
        <?php endif; ?>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview-target="#photoPreview">
    </div>

    <fieldset>
        <legend>Public contact details (optional)</legend>
        <div class="form-row">
            <div class="form-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($values['email']) ?>">
            </div>
            <div class="form-field">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= e($values['phone']) ?>">
            </div>
        </div>
    </fieldset>

    <div class="form-checkbox">
        <input type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($values['is_published']) ? 'checked' : '' ?>>
        <label for="is_published">Visible on the public Committee page</label>
    </div>

    <div>
        <button type="submit" class="btn btn--primary">Save</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/committee.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
