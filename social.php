<?php
require_once __DIR__.'/includes/bootstrap.php';$error='';$notice='';$showFacebook=false;
$publisher=club_can('content.publish');if($publisher)header('Cache-Control: private, no-store');
try{$facebook=club_social_url('facebook',club_setting('facebook_url'));}catch(InvalidArgumentException $e){$facebook='';}
try{$instagram=club_social_url('instagram',club_setting('instagram_url'));}catch(InvalidArgumentException $e){$instagram='';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();
    if(($_POST['action']??'')==='show_facebook')$showFacebook=$facebook!=='';
    elseif(($_POST['action']??'')==='share'){
        try{club_social_queue((string)($_POST['kind']??''),(int)($_POST['id']??0),is_array($_POST['channels']??null)?$_POST['channels']:[]);$notice='Your sharing request is saved. Account connection is required; nothing has been posted to Facebook or Instagram.';}catch(DomainException|InvalidArgumentException $e){$error=$e->getMessage();}
    }
}
seo_set(['title'=>'Follow the club','description'=>'Keep in touch with Nettlebed Village Club on social media.']);require APP_ROOT.'/includes/header.php';
?>
<section class="section"><div class="container"><span class="eyebrow">Keep in touch</span><h1>Club life, wherever you are</h1><p>Follow the club for announcements, photographs and the latest news.</p>
<?php if($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif ?><?php if($notice): ?><p class="alert alert--success" role="status"><?= e($notice) ?></p><?php endif ?>
<div class="platform-grid"><article class="platform-panel"><h2>Facebook</h2><?php if($facebook): ?><p><a class="btn btn--primary" href="<?= e($facebook) ?>" rel="noopener noreferrer" target="_blank">Visit our Facebook page</a></p>
<?php if($showFacebook): ?><iframe title="Nettlebed Village Club Facebook posts" src="https://www.facebook.com/plugins/page.php?href=<?= rawurlencode($facebook) ?>&amp;tabs=timeline&amp;width=500&amp;height=650&amp;small_header=false&amp;adapt_container_width=true&amp;hide_cover=false&amp;show_facepile=false" width="500" height="650" loading="lazy" style="width:100%;border:0" allow="encrypted-media; picture-in-picture"></iframe><p>If Facebook cannot display its feed, use the page link above.</p>
<?php else: ?><p>Facebook content is optional. Loading it contacts Meta and may allow Meta to use cookies and process your device information.</p><form method="post"><?php csrf_field(); ?><button class="btn btn--secondary" name="action" value="show_facebook">Allow and show Facebook posts</button></form><?php endif ?>
<?php else: ?><p>The club’s Facebook page address is being confirmed.</p><?php endif ?></article>
<article class="platform-panel"><h2>Instagram</h2><?php if($instagram): ?><p><a class="btn btn--primary" href="<?= e($instagram) ?>" target="_blank" rel="noopener noreferrer">Visit our Instagram profile</a></p><p>An authorised Instagram feed has not been connected yet.</p><?php else: ?><p>The club’s Instagram profile is being confirmed. We’ll add it here when it is ready.</p><?php endif ?><p>Website posts and events remain available even when a social feed cannot load.</p><a href="<?= e(site_url('/calendar.php')) ?>">See the club calendar</a></article></div>
<?php if($publisher): $items=[];foreach(['post'=>'club_posts','event'=>'events'] as $kind=>$table){$q=db()->query("SELECT id,title,slug FROM $table WHERE status='published'".($kind==='event'?" AND visibility='public' AND deleted_at IS NULL":'').' ORDER BY id DESC LIMIT 30');foreach($q->fetchAll() as $row)$items[]=array_merge($row,['kind'=>$kind]);} ?>
<section class="platform-panel"><span class="eyebrow">Committee workspace</span><h2>Prepare a social announcement</h2><p>Approved posts are automatically queued for Facebook and Instagram. Their delivery status appears in My posts and Club settings. The form below only prepares a separate request for older website content; it does not send it.</p>
<?php foreach($items as $item): ?><form method="post" class="platform-panel"><?php csrf_field(); ?><input type="hidden" name="kind" value="<?= e($item['kind']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><h3><?= e($item['title']) ?></h3><p><a href="<?= e(site_url(($item['kind']==='post'?'/post.php?slug=':'/event-detail.php?slug=').rawurlencode($item['slug']))) ?>">Preview approved website content</a></p><fieldset><legend>Choose channels</legend><label><input type="checkbox" name="channels[]" value="facebook"> Facebook</label> <label><input type="checkbox" name="channels[]" value="instagram"> Instagram (approved image required)</label></fieldset><button class="btn btn--secondary" name="action" value="share">Save sharing request</button></form><?php endforeach ?>
<?php if(!$items): ?><p>Published public posts and events will appear here.</p><?php endif ?>
<h3>Recent requests</h3><?php $q=db()->query("SELECT channel,status,created_at FROM club_outbox WHERE channel IN('facebook','instagram') ORDER BY id DESC LIMIT 20");foreach($q->fetchAll() as $row): ?><p><?= e(ucfirst($row['channel'])) ?> · <?= e(str_replace('_',' ',$row['status'])) ?> · <?= e(format_date($row['created_at'],'j M Y, g:ia')) ?></p><?php endforeach ?></section>
<?php endif ?></div></section>
<?php require APP_ROOT.'/includes/footer.php'; ?>
