<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM pages WHERE id = :id');
$stmt->execute(['id' => $id]);
$page = $stmt->fetch();

if (!$page) {
    flash_error('Page not found.');
    redirect('/admin/pages.php');
}

$pageTitle = 'Edit: ' . $page['title'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = sanitize_text($_POST['title'] ?? '', 190);
    $content = sanitize_html($_POST['content'] ?? '');
    $metaTitle = sanitize_text($_POST['meta_title'] ?? '', 190);
    $metaDescription = sanitize_text($_POST['meta_description'] ?? '', 300);

    if ($title === '') {
        $errors[] = 'Please enter a page title.';
    }

    if (empty($errors)) {
        db()->prepare('UPDATE pages SET title=:title, content=:content, meta_title=:meta_title, meta_description=:meta_description,
            updated_by=:updated_by, updated_at=NOW() WHERE id=:id')->execute([
            'title' => $title, 'content' => $content, 'meta_title' => $metaTitle, 'meta_description' => $metaDescription,
            'updated_by' => $_SESSION['admin_id'], 'id' => $id,
        ]);
        audit_log('page.update', "Updated page content: {$page['page_key']}");
        flash_success('Page content updated.');
        redirect('/admin/pages.php');
    }
    $page['title'] = $title;
    $page['content'] = $content;
    $page['meta_title'] = $metaTitle;
    $page['meta_description'] = $metaDescription;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/pages-edit.php?id=' . $id) ?>">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-field">
        <label for="title">Page title</label>
        <input type="text" id="title" name="title" value="<?= e($page['title']) ?>" required>
    </div>

    <div class="form-field">
        <label for="content">Page content</label>
        <div class="editor-wrap" data-editor-for="content"></div>
        <textarea id="content" name="content"><?= $page['content'] ?></textarea>
    </div>

    <fieldset>
        <legend>SEO (optional)</legend>
        <div class="form-field">
            <label for="meta_title">Meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($page['meta_title']) ?>">
        </div>
        <div class="form-field">
            <label for="meta_description">Meta description</label>
            <textarea id="meta_description" name="meta_description" rows="2"><?= e($page['meta_description']) ?></textarea>
        </div>
    </fieldset>

    <div>
        <button type="submit" class="btn btn--primary">Save Page</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/pages.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
