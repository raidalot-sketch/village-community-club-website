<?php
require_once __DIR__ . '/../includes/bootstrap.php';
club_require_login(); club_require_capability('content.publish');
header('X-Robots-Tag: noindex, nofollow');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try { club_poll_moderate((int)($_POST['id'] ?? 0),(string)($_POST['action'] ?? ''),(string)($_POST['reason'] ?? '')); flash_success('Poll updated.'); redirect('/admin/club-polls.php'); }
    catch (DomainException $e) { $error = $e->getMessage(); }
}
$polls = club_poll_list();
seo_set(['title'=>'Review club polls']); require APP_ROOT . '/includes/header.php';
?>
<section class="section"><div class="container member-layout"><span class="eyebrow">Committee workspace</span><h1>Review club polls</h1><p>Publish members’ questions, or return them with feedback. Results stay hidden until voting closes, including here.</p><a href="<?= e(site_url('/polls.php')) ?>">All member polls</a>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php foreach ($polls as $poll): $state = club_poll_state($poll); ?><article class="platform-panel"><span class="status-label"><?= e(ucfirst($state)) ?></span><h2><a href="<?= e(site_url('/poll.php?id='.$poll['id'])) ?>"><?= e($poll['title']) ?></a></h2><p><?= nl2br(e($poll['description'])) ?></p><ul><?php foreach (club_poll_choices((int)$poll['id']) as $choice): ?><li><?= e($choice['label']) ?></li><?php endforeach; ?></ul><p><?= e(format_date($poll['opens_at'],'j M Y, g:ia')) ?> – <?= e(format_date($poll['closes_at'],'j M Y, g:ia')) ?> UK time</p>
<?php if ($poll['reviewer_note']): ?><p>Committee note: <?= e($poll['reviewer_note']) ?></p><?php endif; ?>
<?php if (!in_array($state,['closed','cancelled'],true)): ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$poll['id'] ?>"><div class="form-field"><label for="reason-<?= (int)$poll['id'] ?>">Review note / reason (required when returning, cancelling or closing)</label><textarea id="reason-<?= (int)$poll['id'] ?>" name="reason" maxlength="2000" rows="2"></textarea></div><div class="actions">
<?php if ($state === 'submitted'): ?><button class="btn btn--primary" name="action" value="publish">Approve and publish</button><button class="btn btn--secondary" name="action" value="reject">Return for changes</button><?php endif; ?>
<?php if ($state === 'open'): ?><button class="btn btn--secondary" name="action" value="close">Close voting early</button><?php endif; ?>
<button class="btn btn--secondary" name="action" value="cancel">Cancel poll</button></div></form><?php endif; ?></article><?php endforeach; ?>
<?php if (!$polls): ?><div class="platform-panel">No polls awaiting review.</div><?php endif; ?></div></section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
