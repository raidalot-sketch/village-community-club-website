<?php
require_once __DIR__ . '/includes/bootstrap.php';
club_require_login();
$polls = club_poll_list();
seo_set(['title'=>'Members’ polls', 'description'=>'Private opinion polls for Nettlebed Village Club members.']);
header('X-Robots-Tag: noindex, nofollow');
require APP_ROOT . '/includes/header.php';
?>
<section class="section"><div class="container member-layout">
<div class="section-heading"><span class="eyebrow">Have your say</span><h1>Members’ polls</h1><p>A little input can make a big difference to club life.</p></div>
<div class="actions"><a class="btn btn--secondary" href="<?= e(site_url('/account.php')) ?>">My account</a>
<?php if (club_can('content.submit')): ?><a class="btn btn--primary" href="<?= e(site_url('/members/poll-edit.php')) ?>">Suggest a poll</a><?php endif; ?>
<?php if (club_can('content.publish')): ?><a class="btn btn--secondary" href="<?= e(site_url('/admin/club-polls.php')) ?>">Review polls</a><?php endif; ?></div>
<?php if (!club_is_active_member()): ?><p class="platform-panel">Voting and published polls are available to current, approved members.</p><?php endif; ?>
<div class="platform-grid">
<?php foreach ($polls as $poll): ?><article class="platform-panel"><span class="status-label"><?= e(ucfirst(club_poll_state($poll))) ?></span><h2><a href="<?= e(site_url('/poll.php?id='.$poll['id'])) ?>"><?= e($poll['title']) ?></a></h2><p><?= e(truncate_text($poll['description'] ?? '',180)) ?></p><p>Closes <?= e(format_date($poll['closes_at'],'j M Y, g:ia')) ?> UK time</p></article><?php endforeach; ?>
<?php if (!$polls): ?><div class="platform-panel"><h2>No polls just yet</h2><p>Come back soon, or suggest a question for the club.</p></div><?php endif; ?>
</div><p>Private club opinion polls. Votes can be changed until closing. Results appear after closing; individual choices are kept private within the website.</p>
</div></section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
