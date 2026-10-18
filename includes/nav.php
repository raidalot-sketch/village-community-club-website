<nav class="primary-nav" id="primaryNav" aria-label="Primary">
    <ul class="primary-nav__list">
        <?php $links = [
            ['index.php', 'Home', ['index.php', '']],
            ['events.php', "What's on", ['events.php', 'event-detail.php']],
            ['hall-hire.php', 'Hall hire', ['hall-hire.php']],
            ['membership.php', 'Membership', ['membership.php']],
            ['about.php', 'Our club', ['about.php', 'facilities.php', 'committee.php']],
            ['contact.php', 'Contact', ['contact.php']],
        ]; foreach ($links as [$path, $label, $active]): ?>
        <li><a href="<?= e(site_url('/' . $path)) ?>" class="primary-nav__link<?= is_active_page(...$active) ?>"<?= in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $active, true) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
