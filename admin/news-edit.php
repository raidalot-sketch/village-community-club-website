<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$article = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM news_articles WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute(['id' => $id]);
    $article = $stmt->fetch();
    if (!$article) {
        flash_error('Article not found.');
        redirect('/admin/news.php');
    }
}

$pageTitle = $article ? 'Edit News Article' : 'Add News Article';
$errors = [];

$values = $article ?: [
    'title' => '', 'summary' => '', 'content' => '', 'author' => '', 'status' => 'draft',
    'is_featured' => 0, 'featured_image' => '', 'meta_title' => '', 'meta_description' => '',
    'published_at' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['title'] = sanitize_text($_POST['title'] ?? '', 190);
    $values['summary'] = sanitize_text($_POST['summary'] ?? '', 400);
    $values['content'] = sanitize_html($_POST['content'] ?? '');
    $values['author'] = sanitize_text($_POST['author'] ?? '', 120);
    $values['status'] = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $values['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $values['meta_title'] = sanitize_text($_POST['meta_title'] ?? '', 190);
    $values['meta_description'] = sanitize_text($_POST['meta_description'] ?? '', 300);
    $values['published_at'] = $_POST['published_at'] ?: date('Y-m-d');

    if ($values['title'] === '') {
        $errors[] = 'Please enter a title.';
    }
    if ($values['content'] === '') {
        $errors[] = 'Please enter the article content.';
    }

    $imagePath = $values['featured_image'] ?? '';
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = handle_upload('featured_image', 'news', 'image');
        if ($upload['ok']) {
            $imagePath = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        $baseSlug = slugify($values['title']);
        $slug = unique_slug('news_articles', $baseSlug, $id ?: null);
        $publishedAt = $values['status'] === 'published' ? $values['published_at'] . ' ' . date('H:i:s') : ($article['published_at'] ?? null);

        if ($id) {
            $stmt = db()->prepare('UPDATE news_articles SET title=:title, slug=:slug, summary=:summary, content=:content,
                featured_image=:image, author=:author, status=:status, is_featured=:featured,
                published_at=:published_at, meta_title=:meta_title, meta_description=:meta_description
                WHERE id=:id');
            $stmt->execute([
                'title' => $values['title'], 'slug' => $slug, 'summary' => $values['summary'], 'content' => $values['content'],
                'image' => $imagePath, 'author' => $values['author'], 'status' => $values['status'], 'featured' => $values['is_featured'],
                'published_at' => $publishedAt, 'meta_title' => $values['meta_title'], 'meta_description' => $values['meta_description'],
                'id' => $id,
            ]);
            audit_log('news.update', "Updated news article #$id: {$values['title']}");
            flash_success('Article updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO news_articles (title, slug, summary, content, featured_image, author, status,
                is_featured, published_at, meta_title, meta_description, created_by, created_at)
                VALUES (:title, :slug, :summary, :content, :image, :author, :status, :featured, :published_at, :meta_title, :meta_description, :created_by, NOW())');
            $stmt->execute([
                'title' => $values['title'], 'slug' => $slug, 'summary' => $values['summary'], 'content' => $values['content'],
                'image' => $imagePath, 'author' => $values['author'], 'status' => $values['status'], 'featured' => $values['is_featured'],
                'published_at' => $publishedAt, 'meta_title' => $values['meta_title'], 'meta_description' => $values['meta_description'],
                'created_by' => $_SESSION['admin_id'],
            ]);
            $id = (int) db()->lastInsertId();
            audit_log('news.create', "Created news article #$id: {$values['title']}");
            flash_success('Article created.');
        }
        redirect('/admin/news.php');
    }
    $values['featured_image'] = $imagePath;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/news-edit.php' . ($id ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
    </div>

    <div class="form-field">
        <label for="summary">Summary</label>
        <textarea id="summary" name="summary" rows="2"><?= e($values['summary']) ?></textarea>
        <small class="hint">Shown on news listing cards. Keep it to one or two sentences.</small>
    </div>

    <div class="form-field">
        <label for="content">Article Content</label>
        <div class="editor-wrap" data-editor-for="content"></div>
        <textarea id="content" name="content"><?= $values['content'] ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="author">Author</label>
            <input type="text" id="author" name="author" value="<?= e($values['author']) ?>" placeholder="e.g. The Committee">
        </div>
        <div class="form-field">
            <label for="published_at">Publication date</label>
            <input type="date" id="published_at" name="published_at" value="<?= e(is_array($values['published_at']) ? '' : substr((string) $values['published_at'], 0, 10)) ?>">
        </div>
    </div>

    <div class="form-field">
        <label for="featured_image">Featured image</label>
        <?php if (!empty($values['featured_image'])): ?>
            <div class="current-image"><img class="thumb-preview" id="imagePreview" src="<?= upload_url($values['featured_image']) ?>" alt=""><span>Current image — choose a new file to replace it</span></div>
        <?php else: ?>
            <img class="thumb-preview" id="imagePreview" src="" alt="" hidden>
        <?php endif; ?>
        <input type="file" id="featured_image" name="featured_image" accept="image/jpeg,image/png,image/webp" data-preview-target="#imagePreview">
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $values['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $values['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="form-field">
            <label for="is_featured">Featured article</label>
            <div class="form-checkbox">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($values['is_featured']) ? 'checked' : '' ?>>
                <label for="is_featured">Show as featured on the homepage and news listing</label>
            </div>
        </div>
    </div>

    <fieldset>
        <legend>SEO (optional)</legend>
        <div class="form-field">
            <label for="meta_title">Meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($values['meta_title']) ?>">
            <small class="hint">Leave blank to use the article title.</small>
        </div>
        <div class="form-field">
            <label for="meta_description">Meta description</label>
            <textarea id="meta_description" name="meta_description" rows="2"><?= e($values['meta_description']) ?></textarea>
        </div>
    </fieldset>

    <div>
        <button type="submit" class="btn btn--primary">Save Article</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/news.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
