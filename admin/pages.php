<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle = 'Page Content';
$pages = db()->query('SELECT * FROM pages ORDER BY title ASC')->fetchAll();

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-panel">
    <div class="admin-panel__header"><h2 style="margin:0;">Editable Page Content</h2></div>
    <p style="color:var(--color-text-muted);">These sections appear on the public website. Home page hero text, opening hours, address and social links are edited from <a href="<?= site_url('/admin/settings.php') ?>">Settings</a> instead.</p>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Page</th><th>Last updated</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pages as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e(format_date($p['updated_at'], 'j M Y, g:ia')) ?></td>
                    <td class="admin-table__actions">
                        <a class="btn btn--small btn--secondary" href="<?= site_url('/admin/pages-edit.php?id=' . $p['id']) ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
