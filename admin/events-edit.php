<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$event = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute(['id' => $id]);
    $event = $stmt->fetch();
    if (!$event) {
        flash_error('Event not found.');
        redirect('/admin/events.php');
    }
}

$pageTitle = $event ? 'Edit Event' : 'Add Event';
$errors = [];

$values = $event ?: [
    'title' => '', 'description' => '', 'featured_image' => '', 'event_date' => date('Y-m-d'),
    'start_time' => '', 'end_time' => '', 'venue' => '', 'address' => '', 'booking_info' => '',
    'booking_link' => '', 'is_free' => 1, 'ticket_price' => '', 'is_featured' => 0, 'status' => 'draft',
    'recurrence' => 'none', 'recurrence_end_date' => '', 'meta_title' => '', 'meta_description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['title'] = sanitize_text($_POST['title'] ?? '', 190);
    $values['description'] = sanitize_html($_POST['description'] ?? '');
    $values['event_date'] = $_POST['event_date'] ?? '';
    $values['start_time'] = $_POST['start_time'] ?: null;
    $values['end_time'] = $_POST['end_time'] ?: null;
    $values['venue'] = sanitize_text($_POST['venue'] ?? '', 190);
    $values['address'] = sanitize_text($_POST['address'] ?? '', 255);
    $values['booking_info'] = sanitize_text($_POST['booking_info'] ?? '', 400);
    $values['booking_link'] = filter_var(trim($_POST['booking_link'] ?? ''), FILTER_VALIDATE_URL) ?: '';
    $values['is_free'] = !empty($_POST['is_free']) ? 1 : 0;
    $values['ticket_price'] = $values['is_free'] ? null : (float) ($_POST['ticket_price'] ?? 0);
    $values['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $values['status'] = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $values['recurrence'] = in_array($_POST['recurrence'] ?? 'none', ['none', 'weekly', 'monthly'], true) ? $_POST['recurrence'] : 'none';
    $values['recurrence_end_date'] = $_POST['recurrence_end_date'] ?: null;
    $values['meta_title'] = sanitize_text($_POST['meta_title'] ?? '', 190);
    $values['meta_description'] = sanitize_text($_POST['meta_description'] ?? '', 300);

    if ($values['title'] === '') {
        $errors[] = 'Please enter a title.';
    }
    if (empty($values['event_date']) || !strtotime($values['event_date'])) {
        $errors[] = 'Please enter a valid event date.';
    }
    if (!$values['is_free'] && $values['ticket_price'] <= 0) {
        $errors[] = 'Please enter a ticket price, or mark the event as free.';
    }

    $imagePath = $values['featured_image'] ?? '';
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = handle_upload('featured_image', 'events', 'image');
        if ($upload['ok']) {
            $imagePath = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        $baseSlug = slugify($values['title']);
        $slug = unique_slug('events', $baseSlug, $id ?: null);

        $fields = [
            'title' => $values['title'], 'slug' => $slug, 'description' => $values['description'],
            'image' => $imagePath, 'event_date' => $values['event_date'], 'start_time' => $values['start_time'],
            'end_time' => $values['end_time'], 'venue' => $values['venue'], 'address' => $values['address'],
            'booking_info' => $values['booking_info'], 'booking_link' => $values['booking_link'],
            'is_free' => $values['is_free'], 'ticket_price' => $values['ticket_price'], 'featured' => $values['is_featured'],
            'status' => $values['status'], 'recurrence' => $values['recurrence'], 'recurrence_end' => $values['recurrence_end_date'],
            'meta_title' => $values['meta_title'], 'meta_description' => $values['meta_description'],
        ];

        if ($id) {
            $fields['id'] = $id;
            db()->prepare('UPDATE events SET title=:title, slug=:slug, description=:description, featured_image=:image,
                event_date=:event_date, start_time=:start_time, end_time=:end_time, venue=:venue, address=:address,
                booking_info=:booking_info, booking_link=:booking_link, is_free=:is_free, ticket_price=:ticket_price,
                is_featured=:featured, status=:status, recurrence=:recurrence, recurrence_end_date=:recurrence_end,
                meta_title=:meta_title, meta_description=:meta_description WHERE id=:id')->execute($fields);
            audit_log('event.update', "Updated event #$id: {$values['title']}");
            flash_success('Event updated.');
        } else {
            $fields['created_by'] = $_SESSION['admin_id'];
            db()->prepare('INSERT INTO events (title, slug, description, featured_image, event_date, start_time, end_time,
                venue, address, booking_info, booking_link, is_free, ticket_price, is_featured, status, recurrence,
                recurrence_end_date, meta_title, meta_description, created_by, created_at)
                VALUES (:title, :slug, :description, :image, :event_date, :start_time, :end_time, :venue, :address,
                :booking_info, :booking_link, :is_free, :ticket_price, :featured, :status, :recurrence, :recurrence_end,
                :meta_title, :meta_description, :created_by, NOW())')->execute($fields);
            $id = (int) db()->lastInsertId();
            audit_log('event.create', "Created event #$id: {$values['title']}");
            flash_success('Event created.');
        }
        redirect('/admin/events.php');
    }
    $values['featured_image'] = $imagePath;
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/events-edit.php' . ($id ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-field">
        <label for="title">Event title</label>
        <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
    </div>

    <div class="form-field">
        <label for="description">Description</label>
        <div class="editor-wrap" data-editor-for="description"></div>
        <textarea id="description" name="description"><?= $values['description'] ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="event_date">Date</label>
            <input type="date" id="event_date" name="event_date" value="<?= e($values['event_date']) ?>" required>
        </div>
        <div class="form-field">
            <label for="recurrence">Repeats</label>
            <select id="recurrence" name="recurrence">
                <option value="none" <?= $values['recurrence'] === 'none' ? 'selected' : '' ?>>Does not repeat</option>
                <option value="weekly" <?= $values['recurrence'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $values['recurrence'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="start_time">Start time</label>
            <input type="time" id="start_time" name="start_time" value="<?= e((string) $values['start_time']) ?>">
        </div>
        <div class="form-field">
            <label for="end_time">Finish time</label>
            <input type="time" id="end_time" name="end_time" value="<?= e((string) $values['end_time']) ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label for="venue">Venue</label>
            <input type="text" id="venue" name="venue" value="<?= e($values['venue']) ?>" placeholder="e.g. The Village Hall">
        </div>
        <div class="form-field">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?= e($values['address']) ?>">
        </div>
    </div>

    <div class="form-field">
        <label for="featured_image">Event image / poster</label>
        <?php if (!empty($values['featured_image'])): ?>
            <div class="current-image"><img class="thumb-preview" id="imagePreview" src="<?= upload_url($values['featured_image']) ?>" alt=""><span>Current image — choose a new file to replace it</span></div>
        <?php else: ?>
            <img class="thumb-preview" id="imagePreview" src="" alt="" hidden>
        <?php endif; ?>
        <input type="file" id="featured_image" name="featured_image" accept="image/jpeg,image/png,image/webp" data-preview-target="#imagePreview">
    </div>

    <fieldset>
        <legend>Tickets & Booking</legend>
        <div class="form-checkbox">
            <input type="checkbox" id="is_free" name="is_free" value="1" <?= !empty($values['is_free']) ? 'checked' : '' ?>>
            <label for="is_free">This event is free to attend</label>
        </div>
        <div class="form-field">
            <label for="ticket_price">Ticket price (£, if not free)</label>
            <input type="number" id="ticket_price" name="ticket_price" min="0" step="0.01" value="<?= e((string) $values['ticket_price']) ?>">
        </div>
        <div class="form-field">
            <label for="booking_info">Booking information</label>
            <input type="text" id="booking_info" name="booking_info" value="<?= e($values['booking_info']) ?>" placeholder="e.g. Tickets available on the door">
        </div>
        <div class="form-field">
            <label for="booking_link">External booking link</label>
            <input type="url" id="booking_link" name="booking_link" value="<?= e($values['booking_link']) ?>" placeholder="https://">
        </div>
    </fieldset>

    <div class="form-row">
        <div class="form-field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $values['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $values['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="form-field">
            <label for="is_featured">Featured event</label>
            <div class="form-checkbox">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($values['is_featured']) ? 'checked' : '' ?>>
                <label for="is_featured">Show as featured on the homepage and events listing</label>
            </div>
        </div>
    </div>

    <fieldset>
        <legend>SEO (optional)</legend>
        <div class="form-field">
            <label for="meta_title">Meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($values['meta_title']) ?>">
        </div>
        <div class="form-field">
            <label for="meta_description">Meta description</label>
            <textarea id="meta_description" name="meta_description" rows="2"><?= e($values['meta_description']) ?></textarea>
        </div>
    </fieldset>

    <div>
        <button type="submit" class="btn btn--primary">Save Event</button>
        <a class="btn btn--secondary" href="<?= site_url('/admin/events.php') ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
