<nav class="primary-nav" id="primaryNav" aria-label="Primary">
    <ul class="primary-nav__list">
        <li><a href="<?= site_url('/index.php') ?>" class="primary-nav__link<?= is_active_page('index.php', '') ?>">Home</a></li>
        <li><a href="<?= site_url('/about.php') ?>" class="primary-nav__link<?= is_active_page('about.php') ?>">About</a></li>
        <li><a href="<?= site_url('/news.php') ?>" class="primary-nav__link<?= is_active_page('news.php', 'news-detail.php') ?>">News</a></li>
        <li><a href="<?= site_url('/events.php') ?>" class="primary-nav__link<?= is_active_page('events.php', 'event-detail.php') ?>">Events</a></li>
        <li><a href="<?= site_url('/newsletters.php') ?>" class="primary-nav__link<?= is_active_page('newsletters.php') ?>">Newsletters</a></li>
        <li><a href="<?= site_url('/gallery.php') ?>" class="primary-nav__link<?= is_active_page('gallery.php') ?>">Gallery</a></li>
        <li><a href="<?= site_url('/membership.php') ?>" class="primary-nav__link<?= is_active_page('membership.php') ?>">Membership</a></li>
        <li><a href="<?= site_url('/facilities.php') ?>" class="primary-nav__link<?= is_active_page('facilities.php') ?>">Activities</a></li>
        <li><a href="<?= site_url('/committee.php') ?>" class="primary-nav__link<?= is_active_page('committee.php') ?>">Committee</a></li>
        <li><a href="<?= site_url('/contact.php') ?>" class="primary-nav__link<?= is_active_page('contact.php') ?>">Contact</a></li>
    </ul>
</nav>
