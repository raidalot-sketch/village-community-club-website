<?php
require_once __DIR__ . '/includes/bootstrap.php';
club_require_login();
header('X-Robots-Tag: noindex, nofollow');
$error = '';
try {
    $poll = club_poll_find((int)($_GET['id'] ?? 0));
    if (!club_poll_can_view($poll)) throw new DomainException('This poll is unavailable.');
} catch (DomainException $e) { http_response_code(404); $poll = null; $error = 'This poll is unavailable.'; }
if ($poll && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try { club_poll_vote((int)$poll['id'], (int)($_POST['choice_id'] ?? 0)); flash_success('Your vote has been saved. You can change it until the poll closes.'); redirect('/poll.php?id='.$poll['id']); }
    catch (DomainException $e) { $error = $e->getMessage(); }
}
seo_set(['title'=>$poll ? $poll['title'] : 'Poll unavailable']);
club_member_layout_start($poll ? $poll['title'] : 'Poll unavailable');
?>
<a href="<?= e(site_url('/polls.php')) ?>">← Members’ polls</a>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($poll): $state = club_poll_state($poll); $vote = club_poll_my_vote((int)$poll['id']); ?>
<article class="platform-panel"><span class="eyebrow">Members only · <?= e(ucfirst($state)) ?></span><p><?= nl2br(e($poll['description'])) ?></p>
<p>Opens <?= e(format_date($poll['opens_at'],'j F Y, g:ia')) ?><br>Closes <?= e(format_date($poll['closes_at'],'j F Y, g:ia')) ?> · UK time</p>
<?php if ($state === 'open' && club_can('polls.vote') && club_is_active_member()): ?>
<form method="post"><?php csrf_field(); ?><fieldset><legend>Choose one answer</legend>
<?php foreach (club_poll_choices((int)$poll['id']) as $choice): ?><div class="form-field"><label><input required type="radio" name="choice_id" value="<?= (int)$choice['id'] ?>" <?= $vote === (int)$choice['id'] ? 'checked' : '' ?>> <?= e($choice['label']) ?></label></div><?php endforeach; ?>
</fieldset><p><?= $vote ? 'Your vote is saved. You can change your answer before closing.' : 'One vote per current, approved member. You can change your answer before closing.' ?></p><button class="btn btn--primary"><?= $vote ? 'Update my vote' : 'Save my vote' ?></button></form>
<?php elseif ($state === 'closed'): $results = club_poll_results((int)$poll['id']); ?>
<h2>The results</h2><p><?= (int)$results['total'] ?> <?= $results['total'] === 1 ? 'response' : 'responses' ?></p>
<?php if (!$results['total']): ?><p>No votes were received.</p><?php endif; ?>
<?php foreach ($results['choices'] as $choice): ?><div class="platform-panel"><strong><?= e($choice['label']) ?></strong><p><?= $choice['votes'] ?> votes · <?= $choice['percentage'] ?>%</p><progress max="100" value="<?= $choice['percentage'] ?>" aria-label="<?= e($choice['label']) ?>"></progress></div><?php endforeach; ?>
<?php else: ?><p><?= $state === 'cancelled' ? 'This poll has been cancelled.' : 'Voting is not available at the moment.' ?></p><?php endif; ?>
<?php if ($state !== 'closed'): ?><p>Results will be available after voting closes. Participation totals and individual choices are not displayed while voting is open.</p><?php endif; ?>
<?php if ($poll['reviewer_note'] && ((int)$poll['author_user_id'] === (int)club_current_user()['id'] || club_can('content.publish'))): ?><p>Committee note: <?= e($poll['reviewer_note']) ?></p><?php endif; ?>
<?php if ((int)$poll['author_user_id'] === (int)club_current_user()['id'] && in_array($poll['status'],['draft','rejected'],true)): ?><a class="btn btn--secondary" href="<?= e(site_url('/members/poll-edit.php?id='.$poll['id'])) ?>">Edit my poll</a><?php endif; ?>
<p>These are informal club opinion polls. Individual votes are stored privately to enforce one vote per member; committee screens show only results after closing.</p></article>
<?php endif; ?>
<?php club_member_layout_end(); ?>
