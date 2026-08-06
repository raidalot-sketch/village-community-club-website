<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_administrator();

$pageTitle = 'Website Settings';
$errors = [];

$fields = [
    'site_name', 'site_village', 'site_tagline',
    'home_hero_heading', 'home_hero_subheading', 'home_welcome_message', 'membership_cta_text', 'footer_summary',
    'opening_hours', 'contact_phone', 'contact_email',
    'address_line1', 'address_line2', 'address_town', 'address_postcode', 'address_country', 'map_embed_src',
    'social_facebook', 'social_instagram', 'social_x', 'social_youtube',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($fields as $key) {
        $value = trim($_POST[$key] ?? '');
        if (str_starts_with($key, 'social_') || $key === 'map_embed_src') {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = 'Please enter a valid web address for ' . str_replace('_', ' ', $key) . ', or leave it blank.';
                continue;
            }
        }
        if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid contact email address.';
            continue;
        }
        set_setting($key, sanitize_text($value, 1000));
    }

    if (!empty($_FILES['logo']['name'])) {
        $upload = handle_upload('logo', 'pages', 'image');
        if ($upload['ok']) {
            set_setting('logo_path', $upload['path']);
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        audit_log('settings.update', 'Updated website settings');
        flash_success('Settings saved.');
        redirect('/admin/settings.php');
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= site_url('/admin/settings.php') ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?>

    <fieldset>
        <legend>Club Identity</legend>
        <div class="form-row">
            <div class="form-field"><label for="site_name">Club name</label><input type="text" id="site_name" name="site_name" value="<?= e(get_setting('site_name')) ?>"></div>
            <div class="form-field"><label for="site_village">Village name</label><input type="text" id="site_village" name="site_village" value="<?= e(get_setting('site_village')) ?>"></div>
        </div>
        <div class="form-field"><label for="site_tagline">Tagline</label><input type="text" id="site_tagline" name="site_tagline" value="<?= e(get_setting('site_tagline')) ?>"></div>
        <div class="form-field">
            <label for="logo">Club logo</label>
            <div class="current-image"><img class="thumb-preview" id="logoPreview" src="<?= e(get_setting('logo_path') ? upload_url(get_setting('logo_path')) : asset_url('images/placeholders/logo-placeholder.svg')) ?>" alt=""><span>Current logo</span></div>
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" data-preview-target="#logoPreview">
        </div>
    </fieldset>

    <fieldset>
        <legend>Home Page</legend>
        <div class="form-field"><label for="home_hero_heading">Hero heading</label><input type="text" id="home_hero_heading" name="home_hero_heading" value="<?= e(get_setting('home_hero_heading')) ?>"></div>
        <div class="form-field"><label for="home_hero_subheading">Hero subheading</label><input type="text" id="home_hero_subheading" name="home_hero_subheading" value="<?= e(get_setting('home_hero_subheading')) ?>"></div>
        <div class="form-field"><label for="home_welcome_message">Welcome message</label><textarea id="home_welcome_message" name="home_welcome_message" rows="3"><?= e(get_setting('home_welcome_message')) ?></textarea></div>
        <div class="form-field"><label for="membership_cta_text">Membership call-to-action text</label><textarea id="membership_cta_text" name="membership_cta_text" rows="2"><?= e(get_setting('membership_cta_text')) ?></textarea></div>
        <div class="form-field"><label for="footer_summary">Footer summary</label><textarea id="footer_summary" name="footer_summary" rows="2"><?= e(get_setting('footer_summary')) ?></textarea></div>
    </fieldset>

    <fieldset>
        <legend>Opening Hours / Meeting Times</legend>
        <div class="form-field"><label for="opening_hours">Opening hours</label><textarea id="opening_hours" name="opening_hours" rows="3"><?= e(get_setting('opening_hours')) ?></textarea><small class="hint">One entry per line.</small></div>
    </fieldset>

    <fieldset>
        <legend>Contact Details</legend>
        <div class="form-row">
            <div class="form-field"><label for="contact_phone">Phone number</label><input type="text" id="contact_phone" name="contact_phone" value="<?= e(get_setting('contact_phone')) ?>"></div>
            <div class="form-field"><label for="contact_email">Email address</label><input type="email" id="contact_email" name="contact_email" value="<?= e(get_setting('contact_email')) ?>"></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Address & Map</legend>
        <div class="form-row">
            <div class="form-field"><label for="address_line1">Address line 1</label><input type="text" id="address_line1" name="address_line1" value="<?= e(get_setting('address_line1')) ?>"></div>
            <div class="form-field"><label for="address_line2">Address line 2</label><input type="text" id="address_line2" name="address_line2" value="<?= e(get_setting('address_line2')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label for="address_town">Town / Village</label><input type="text" id="address_town" name="address_town" value="<?= e(get_setting('address_town')) ?>"></div>
            <div class="form-field"><label for="address_postcode">Postcode</label><input type="text" id="address_postcode" name="address_postcode" value="<?= e(get_setting('address_postcode')) ?>"></div>
        </div>
        <div class="form-field">
            <label for="map_embed_src">Google Maps embed URL</label>
            <input type="url" id="map_embed_src" name="map_embed_src" value="<?= e(get_setting('map_embed_src')) ?>" placeholder="https://www.google.com/maps/embed?...">
            <small class="hint">From Google Maps: Share &gt; Embed a map &gt; copy the "src" value from the code. Leave blank to hide the map.</small>
        </div>
    </fieldset>

    <fieldset>
        <legend>Social Media Links</legend>
        <div class="form-row">
            <div class="form-field"><label for="social_facebook">Facebook URL</label><input type="url" id="social_facebook" name="social_facebook" value="<?= e(get_setting('social_facebook')) ?>"></div>
            <div class="form-field"><label for="social_instagram">Instagram URL</label><input type="url" id="social_instagram" name="social_instagram" value="<?= e(get_setting('social_instagram')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label for="social_x">X / Twitter URL</label><input type="url" id="social_x" name="social_x" value="<?= e(get_setting('social_x')) ?>"></div>
            <div class="form-field"><label for="social_youtube">YouTube URL</label><input type="url" id="social_youtube" name="social_youtube" value="<?= e(get_setting('social_youtube')) ?>"></div>
        </div>
    </fieldset>

    <div><button type="submit" class="btn btn--primary">Save Settings</button></div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
