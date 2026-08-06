<?php
require_once __DIR__ . '/includes/bootstrap.php';

seo_set(['title' => 'Contact Us', 'description' => 'Get in touch with ' . get_setting('site_name', SITE_NAME)]);

$errors = [];
$values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => $_GET['subject'] ?? '',
    'message' => '',
    'consent' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Honeypot: a real visitor will never fill in this hidden field.
    $honeypot = trim($_POST['website'] ?? '');

    // Simple time-trap: reject submissions completed implausibly fast (bots).
    $formLoadedAt = (int) ($_POST['form_loaded_at'] ?? 0);
    $tooFast = $formLoadedAt > 0 && (time() - $formLoadedAt) < 3;

    $values['name'] = sanitize_text($_POST['name'] ?? '', 150);
    $values['email'] = sanitize_text($_POST['email'] ?? '', 190);
    $values['phone'] = sanitize_text($_POST['phone'] ?? '', 40);
    $values['subject'] = sanitize_text($_POST['subject'] ?? '', 190);
    $values['message'] = trim(strip_tags($_POST['message'] ?? ''));
    $values['consent'] = !empty($_POST['consent']);

    if ($values['name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($values['message'] === '' || mb_strlen($values['message']) < 10) {
        $errors[] = 'Please enter a message of at least 10 characters.';
    }
    if (!$values['consent']) {
        $errors[] = 'Please confirm you are happy for us to contact you about your enquiry.';
    }

    if ($honeypot !== '' || $tooFast) {
        // Silently pretend success to avoid tipping off spam bots.
        flash_success('Thank you — your message has been sent. We will get back to you soon.');
        redirect('/contact.php');
    }

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO contact_enquiries (name, email, phone, subject, message, consent, status, ip_address, created_at)
            VALUES (:name, :email, :phone, :subject, :message, 1, "new", :ip, NOW())');
        $stmt->execute([
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $values['phone'],
            'subject' => $values['subject'],
            'message' => $values['message'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $emailBody = "New contact form enquiry from " . get_setting('site_name', SITE_NAME) . " website:\n\n"
            . "Name: {$values['name']}\n"
            . "Email: {$values['email']}\n"
            . "Phone: {$values['phone']}\n"
            . "Subject: {$values['subject']}\n\n"
            . "Message:\n{$values['message']}\n";
        send_email(defined('MAIL_TO_ADDRESS') ? MAIL_TO_ADDRESS : get_setting('contact_email'), 'New enquiry: ' . ($values['subject'] ?: 'Website contact form'), $emailBody, $values['email']);

        flash_success('Thank you — your message has been sent. We will get back to you soon.');
        redirect('/contact.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We would love to hear from you.</p>
    </div>
</section>

<section class="section">
    <div class="container two-col" style="align-items:start;">
        <div>
            <?php if (!empty($errors)): ?>
                <div class="form-errors">
                    <p><strong>Please fix the following:</strong></p>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form class="form-grid" method="post" action="<?= site_url('/contact.php') ?>" id="contactForm" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="form_loaded_at" value="<?= time() ?>">
                <div class="form-honeypot" aria-hidden="true">
                    <label for="website">Leave this field blank</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-field">
                    <label for="name">Full name <span aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required autocomplete="name">
                </div>
                <div class="form-field">
                    <label for="email">Email address <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required autocomplete="email">
                </div>
                <div class="form-field">
                    <label for="phone">Telephone number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" autocomplete="tel">
                    <small class="hint">Optional — only if you'd like us to call you back.</small>
                </div>
                <div class="form-field">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?= e($values['subject']) ?>">
                </div>
                <div class="form-field">
                    <label for="message">Message <span aria-hidden="true">*</span></label>
                    <textarea id="message" name="message" rows="6" required><?= e($values['message']) ?></textarea>
                </div>
                <div class="form-checkbox">
                    <input type="checkbox" id="consent" name="consent" value="1" <?= $values['consent'] ? 'checked' : '' ?> required>
                    <label for="consent">I am happy for the Club to contact me about my enquiry, in line with the <a href="<?= site_url('/privacy-policy.php') ?>">Privacy Policy</a>. <span aria-hidden="true">*</span></label>
                </div>
                <button type="submit" class="btn btn--primary btn--block">Send Message</button>
            </form>
        </div>

        <div class="info-block">
            <h3>Visit or Write to Us</h3>
            <address>
                <?= nl2br(e(get_setting('address_line1') . "\n" . get_setting('address_line2') . "\n" . get_setting('address_town') . ' ' . get_setting('address_postcode'))) ?>
            </address>
            <?php if (get_setting('contact_phone')): ?><p><a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a></p><?php endif; ?>
            <?php if (get_setting('contact_email')): ?><p><a href="mailto:<?= e(get_setting('contact_email')) ?>"><?= e(get_setting('contact_email')) ?></a></p><?php endif; ?>
            <h3>Opening Times</h3>
            <p><?= nl2br(e(get_setting('opening_hours'))) ?></p>
        </div>
    </div>
</section>

<?php if (get_setting('map_embed_src')): ?>
<section class="section section--alt">
    <div class="container">
        <h2>Find Us</h2>
        <div class="map-embed" data-embed-src="<?= e(get_setting('map_embed_src')) ?>" data-embed-title="Map showing club location">
            <div class="video-consent" style="background:var(--color-primary-light);color:var(--color-text);">
                <p>The map is provided by Google Maps, which sets its own cookies once loaded.</p>
                <button type="button" class="btn btn--secondary map-consent-btn">Load Map</button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
