<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$newsletter = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM newsletters WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $newsletter = $stmt->fetch();
    if (!$newsletter) {
        flash_error('Newsletter not found.');
        redirect('/admin/newsletters.php');
    }
}

$pageTitle = $newsletter ? 'Edit Newsletter' : 'Add Newsletter';
$errors = [];

$values = $newsletter ?: [
    'title' => '', 'issue_number' => '', 'issue_month' => date('n'), 'issue_year' => date('Y'),
    'description' => '', 'pdf_path' => '', 'cover_image' => '', 'is_published' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['title'] = sanitize_text($_POST['title'] ?? '', 190);
    $values['issue_number'] = sanitize_text($_POST['issue_number'] ?? '', 30);
    $values['issue_month'] = (int) ($_POST['issue_month'] ?? 0) ?: null;
    $values['issue_year'] = (int) ($_POST['issue_year'] ?? date('Y'));
    $values['description'] = sanitize_text($_POST['description'] ?? '', 400);
    $values['is_published'] = !empty($_POST['is_published']) ? 1 : 0;

    if ($values['title'] === '') {
        $errors[] = 'Please enter a title.';
    }

    $pdfPath = $values['pdf_path'] ?? '';
    if (!empty($_FILES['pdf_file']['name'])) {
        $upload = handle_upload('pdf_file', 'newsletters', 'pdf');
        if ($upload['ok']) {
            $pdfPath = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    } elseif (!$id) {
        $errors[] = 'Please upload a PDF file for this newsletter.';
    }

    $coverPath = $values['cover_image'] ?? '';
    if (!empty($_FILES['cover_image']['name'])) {
        $upload = handle_upload('cover_image', 'newsletters', 'image');
        if ($upload['ok']) {
            $coverPath = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        $fields = [
            'title' => $values['title'], 'issue_number' => $values['issue_number'], 'issue_month' => $values['issue_month'],
            'issue_year' => $values['issue_year'], 'description' => $values['description'], 'pdf' => $pdfPath,
            'cover' => $coverPath, 'published' => $values['is_published'],
        ];
        if ($id) {
            $fields['id'] = $id;
            db()->prepare('UPDATE newsletters SET title=:title, issue_number=:issue_number, issue_month=:issue_month,
                issue_year=:issue_year, description=:description, pdf_path=:pdf, cover_image=:cover, is_published=:published
                WHERE id=:id')->execute($fields);
            audit_log('newsletter.update', "Updated newsletter #$id");
            flash_success('Newsletter updated.');
        } else {
            $fields['created_by'] = $_SESSION['admin_id'];
            db()->prepare('INSERT INTO newsletters (title, issue_number, issue_month, issue_year, description, pdf_path,
                cover_image, is_published, created_by, created_at)
                VALUES (:title, :issue_number, :issue_month, :issue_year, :description, :pdf, :cover, :published, :created_by, NOW())')
                ->execute($fields);
            $id = (int) db()->lastInsertId();
            audit_log('newsletter.create', "Created newsletter #$id");
            flash_success('Newsletter added.');
        }
        redirect('/admin/newsletters.php');
    }
    $values['pdf_path'] = $pdfPath;
    $values['cover_image'] = $coverPath;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/newsletters-edit.php' . ($id ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="issue_number">Issue number</label>
            <input type="text" id="issue_number" name="issue_number" value="<?= e($values['issue_number']) ?>" placeholder="e.g. Issue 12">
        </div>
        <div class="form-field">
            <label for="issue_year">Year</label>
            <input type="number" id="issue_year" name="issue_year" value="<?= e((string) $values['issue_year']) ?>" min="2000" max="2100" required>
        </div>
    </div>

    <div class="form-field">
        <label for="issue_month">Month</label>
        <select id="issue_month" name="issue_month">
            <option value="">Not specified</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= (int) $values['issue_month'] === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="form-field">
        <label for="description">Short description</label>
        <textarea id="description" name="description" rows="2"><?= e($values['description']) ?></textarea>
    </div>

    <div class="form-field">
        <label for="pdf_file">Newsletter PDF</label>
        <?php if (!empty($values['pdf_path'])): ?>
            <p><a href="<?= upload_url($values['pdf_path']) ?>" target="_blank" rel="noopener">Current file: view PDF</a> — choose a new file to replace it.</p>
        <?php endif; ?>
        <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf">
    </div>

    <div class="form-field">
        <label for="cover_image">Cover image (optional)</label>
        <?php if (!empty($values['cover_image'])): ?>
            <div class="current-image"><img class="thumb-preview" id="coverPreview" src="<?= upload_url($values['cover_image']) ?>" alt=""><span>Current cover — choose a new file to replace it</span></div>
        <?php else: ?>
            <img class="thumb-preview" id="coverPreview" src="" alt="" hidden>
        <?php endif; ?>
        <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp" data-preview-target="#coverPreview">
    </div>

    <div class="form-checkbox">
        <input type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($values['is_published']) ? 'checked' : '' ?>>
        <label for="is_published">Published (visible to website visitors)</label>
    </div>

    <div>
        <button type="submit" class="btn btn--primary">Save Newsletter</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/newsletters.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
