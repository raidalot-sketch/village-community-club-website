
</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div class="site-footer__col">
            <h2 class="site-footer__heading"><?= e(get_setting('site_name', SITE_NAME)) ?></h2>
            <p><?= e(get_setting('footer_summary', 'The heart of ' . get_setting('site_village', SITE_VILLAGE) . ' — everyone is welcome.')) ?></p>
            <ul class="social-links" aria-label="Social media">
                <?php $socials = [
                    'facebook' => get_setting('social_facebook', ''),
                    'instagram' => get_setting('social_instagram', ''),
                    'x' => get_setting('social_x', ''),
                    'youtube' => get_setting('social_youtube', ''),
                ]; ?>
                <?php foreach ($socials as $network => $url): if (!$url) continue; ?>
                    <li><a href="<?= e($url) ?>" rel="noopener noreferrer" target="_blank"><?= e(ucfirst($network)) ?><span class="visually-hidden"> (opens in a new tab)</span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="site-footer__col">
            <h2 class="site-footer__heading">Useful links</h2>
            <ul class="footer-links">
                <li><a href="<?= site_url('/hall-hire.php') ?>">Hall hire</a></li>
                <li><a href="<?= site_url('/about.php') ?>">About the Club</a></li>
                <li><a href="<?= site_url('/facilities.php') ?>">Facilities &amp; activities</a></li>
                <li><a href="<?= site_url('/news.php') ?>">Club news</a></li>
                <li><a href="<?= site_url('/gallery.php') ?>">Photographs</a></li>
                <li><a href="<?= site_url('/membership.php') ?>">Membership</a></li>
                <li><a href="<?= site_url('/newsletters.php') ?>">Newsletters</a></li>
                <li><a href="<?= site_url('/committee.php') ?>">Committee</a></li>
                <li><a href="<?= site_url('/contact.php') ?>">Contact Us</a></li>
            </ul>
        </div>

        <div class="site-footer__col">
            <h2 class="site-footer__heading">Opening Times</h2>
            <p><?= nl2br(e(get_setting('opening_hours', "Clubhouse: Tue & Thu, 7pm–10pm\nSaturday mornings: 10am–12pm"))) ?></p>
        </div>

        <div class="site-footer__col">
            <h2 class="site-footer__heading">Get in Touch</h2>
            <address>
                <?= nl2br(e(club_address())) ?><br>
                <?php if (get_setting('contact_phone')): ?><a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a><br><?php endif; ?>
                <?php if (get_setting('contact_email')): ?><a href="mailto:<?= e(get_setting('contact_email')) ?>"><?= e(get_setting('contact_email')) ?></a><?php endif; ?>
            </address>
        </div>
    </div>

    <div class="site-footer__bottom container">
        <p>&copy; <?= date('Y') ?> <?= e(get_setting('site_name', SITE_NAME)) ?>. All rights reserved.</p>
        <ul class="footer-legal">
            <li><a href="<?= site_url('/privacy-policy.php') ?>">Privacy Policy</a></li>
            <li><a href="<?= site_url('/cookie-policy.php') ?>">Cookie Policy</a></li>
            <li><a href="<?= site_url('/accessibility.php') ?>">Accessibility Statement</a></li>
        </ul>
    </div>
</footer>

<script src="<?= asset_url('js/main.js') ?>" defer></script>
</body>
</html>
