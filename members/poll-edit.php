<?php
require_once __DIR__ . '/../includes/bootstrap.php';
club_require_login();
club_require_capability('content.submit');
header('X-Robots-Tag: noindex, nofollow');
$id = (int)($_GET['id'] ?? 0); $error = '';
$values = ['title'=>'','description'=>'','opens_at'=>club_poll_now()->modify('+1 hour')->format('Y-m-d\TH:i'),'closes_at'=>club_poll_now()->modify('+7 days')->format('Y-m-d\TH:i'),'choices'=>['','']];
if ($id) {
    try {
        $existing = club_poll_find($id);
        if ((int)$existing['author_user_id'] !== (int)club_current_user()['id'] || !in_array($existing['status'],['draft','rejected'],true)) throw new DomainException('This poll cannot be edited.');
        $values = $existing; $values['choices'] = array_column(club_poll_choices($id),'label');
    } catch (DomainException $e) { http_response_code(403); $error = $e->getMessage(); $locked = true; }
}
if (empty($locked) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $values = ['title'=>$_POST['title'] ?? '', 'description'=>$_POST['description'] ?? '', 'opens_at'=>$_POST['opens_at'] ?? '', 'closes_at'=>$_POST['closes_at'] ?? '', 'choices'=>preg_split('/\R/',(string)($_POST['choices_text'] ?? '')), 'action'=>$_POST['action'] ?? 'draft'];
    try { $saved = club_poll_save($id ?: null,$values); flash_success($values['action'] === 'submit' ? 'Poll submitted for committee approval.' : 'Poll draft saved.'); redirect('/poll.php?id='.$saved); }
    catch (DomainException $e) { $error = $e->getMessage(); }
}
seo_set(['title'=>$id ? 'Edit my poll' : 'Suggest a poll']);
require APP_ROOT . '/includes/header.php';
?>
<section class="section"><div class="container member-layout"><a href="<?= e(site_url('/polls.php')) ?>">← Members’ polls</a><div class="platform-panel"><span class="eyebrow">Your club, your ideas</span><h1><?= $id ? 'Edit my poll' : 'Suggest a poll' ?></h1><p>Your poll will be reviewed by the committee before members can vote.</p>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (empty($locked)): ?><form method="post"><?php csrf_field(); ?>
<div class="form-field"><label for="title">Your question</label><input id="title" name="title" maxlength="255" required value="<?= e($values['title']) ?>"></div>
<div class="form-field"><label for="description">A little context (optional)</label><textarea id="description" name="description" maxlength="10000" rows="4"><?= e($values['description']) ?></textarea></div>
<div class="form-field"><label for="choices">Answer choices — one per line</label><textarea id="choices" name="choices_text" required rows="5" aria-describedby="choices-help"><?= e(implode("\n",$values['choices'])) ?></textarea><p id="choices-help">Between two and ten different choices. Members choose one answer.</p></div>
<div class="platform-grid"><div class="form-field"><label for="opens">Voting opens (UK time)</label><input id="opens" type="datetime-local" name="opens_at" required value="<?= e(str_replace(' ','T',substr($values['opens_at'],0,16))) ?>"></div><div class="form-field"><label for="closes">Voting closes (UK time)</label><input id="closes" type="datetime-local" name="closes_at" required value="<?= e(str_replace(' ','T',substr($values['closes_at'],0,16))) ?>"></div></div>
<p>Members may change their vote until closing. Results appear afterwards. Questions and answers are locked after submission; ask the committee to return a poll for changes before publication.</p><div class="actions"><button class="btn btn--secondary" name="action" value="draft">Save draft</button><button class="btn btn--primary" name="action" value="submit">Submit for approval</button></div>
</form><?php endif; ?></div></div></section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
