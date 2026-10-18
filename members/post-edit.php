<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
$user=club_require_login();$id=isset($_GET['id'])?(int)$_GET['id']:null;$row=$id?club_community_record('post',$id):null;
if(($id&&(!$row||!club_content_editable($row)))||(!$id&&!club_can('content.submit')&&!club_can('content.publish'))){http_response_code(403);exit('You cannot edit this post.');}
$values=$row??['title'=>'','content'=>''];$error='';$pendingRevisionId=null;
if($id&&$row['status']==='published'){$s=db()->prepare("SELECT id,payload FROM club_revisions WHERE owner_type='post' AND owner_id=? AND status='submitted' ORDER BY id DESC LIMIT 1");$s->execute([$id]);if($pending=$s->fetch()){$pendingRevisionId=(int)$pending['id'];$values=array_merge($values,json_decode($pending['payload'],true));}}
$values['content']=html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$values['content'])),ENT_QUOTES,'UTF-8');
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();$values=array_merge($values,$_POST);
    try{$saved=club_content_save('post',$id,$_POST,($_POST['action']??'')==='submit',$_FILES['attachments']??[]);
        flash_success($saved['status']==='submitted'?'Your post is awaiting committee approval.':'Your draft has been saved.');redirect('/members/posts.php');
    }catch(Throwable $e){$error=$e->getMessage();}
}
club_member_layout_start($id?'Edit post':'Write a post');
?>
<p>Tell the club what’s happening. Your post will be reviewed before publication.</p><?php flash_render();if($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if($row&&$row['status']==='published'): ?><div class="platform-panel"><p>The approved post stays online. Your changes create a revision for committee approval.</p></div><?php endif ?>
<form method="post" enctype="multipart/form-data" class="platform-panel"><?php csrf_field(); ?><div class="form-field"><label for="title">Post title</label><input id="title" name="title" required maxlength="190" value="<?= e($values['title']) ?>"></div><div class="form-field"><label for="content">Your news</label><textarea id="content" name="content" rows="12" required maxlength="50000"><?= e($values['content']) ?></textarea><small>Plain text is welcome. Photographs and documents can be attached below.</small></div><div class="form-field"><label for="attachments">Photographs or documents</label><input id="attachments" type="file" name="attachments[]" multiple data-club-upload accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.docx,.xlsx"><small>Up to three attachments. Phone photos up to 30 MB are reduced automatically; only the prepared image is retained. PDFs, Word and Excel files are supported.</small></div><div class="hero__actions"><?php if(!$row||$row['status']!=='published'): ?><button class="btn btn--secondary" name="action" value="draft">Save draft</button><?php endif ?><button class="btn btn--primary" name="action" value="submit">Submit for approval</button><a href="<?= e(site_url('/members/posts.php')) ?>">Cancel</a></div></form>
<?php if($id): ?><h2>Saved attachments</h2><?php club_content_attachments('post',$id); ?><?php endif ?>
<?php if($pendingRevisionId): ?><h2>Proposed attachments awaiting approval</h2><?php club_content_attachments('revision',$pendingRevisionId); ?><?php endif ?>
<?php club_member_layout_end(); ?>
