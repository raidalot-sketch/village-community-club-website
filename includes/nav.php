<nav class="primary-nav" id="primaryNav" aria-label="Primary">
    <ul class="primary-nav__list">
        <?php $links = [
            ['index.php', 'Home', ['index.php', '']],
            ['calendar.php', "What's on", ['calendar.php', 'events.php', 'event-detail.php']],
            ['hall-hire.php', 'Hall hire', ['hall-hire.php']],
            ['membership.php', 'Membership', ['membership.php']],
            ['about.php', 'Our club', ['about.php', 'facilities.php', 'committee.php']],
            ['contact.php', 'Contact', ['contact.php']],
        ]; foreach ($links as [$path, $label, $active]): ?>
        <li><a href="<?= e(site_url('/' . $path)) ?>" class="primary-nav__link<?= is_active_page(...$active) ?>"<?= in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $active, true) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
        <li><a class="primary-nav__link member-nav-link" href="<?= e(site_url(club_current_user()?'/account.php':'/login.php')) ?>"><?= club_current_user()?'My club':'Member log in' ?></a></li>
    </ul>
</nav>
