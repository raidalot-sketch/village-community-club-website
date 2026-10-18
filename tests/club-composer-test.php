<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/bootstrap.php';
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
function composer_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function composer_denied(callable $call):void {try{$call();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid composer content accepted.');}
$quick=['post_kind'=>'post','content_format'=>'html','content'=>'<script>Hidden script</script><p>Hello&nbsp; <strong>club</strong>!</p><p>Our next gathering</p>'];
$payload=club_content_payload('post',$quick);
composer_check($payload['title']==='Hello club! Our next gathering','Quick title must use clean decoded text and paragraph spacing.');
composer_check($payload['post_kind']==='post','Quick kind missing.');
$long=club_content_payload('post',array_merge($quick,['content'=>'<p>'.str_repeat('é',110).'</p>']));composer_check(mb_strlen($long['title'])===90,'Quick title must stop at 90 Unicode characters.');
$photo=club_content_payload('post',array_merge($quick,['content'=>'<figure><img src="/media.php?id=7" alt="Summer party"></figure>']),[7]);composer_check($photo['title']==='Photo update','Photo-only update needs a title.');
composer_denied(fn()=>club_content_payload('post',array_merge($quick,['post_kind'=>'unknown'])));
$article=club_content_payload('post',['title'=>'Original article heading','content'=>'<h2>Article body</h2>','content_format'=>'html']);composer_check($article['title']==='Original article heading'&&$article['post_kind']==='article','Default article behaviour changed.');
composer_denied(fn()=>club_content_payload('post',['content'=>'<p>Article without title</p>','content_format'=>'html']));
$legacy=club_content_payload('post',['title'=>'Legacy title','content'=>'<b>Plain text</b>']);composer_check($legacy['title']==='Legacy title'&&str_contains($legacy['content'],'&lt;b&gt;')&&!isset($legacy['post_kind']),'Legacy plain post changed.');
echo "Composer payload tests passed.\n";
$pdo=db();$pdo->beginTransaction();
try {
 $tag='composer-'.bin2hex(random_bytes(5));$users=[];
 foreach(['author','publisher'] as $name){
  $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,email_verified_at,status) VALUES(?,?,?,NOW(),'active')")->execute([$name,$tag.$name.'@example.invalid','unused']);$users[$name]=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO club_user_roles(user_id,role) VALUES(?,?)')->execute([$users[$name],$name==='publisher'?'publisher':'member']);
  if($name==='author')$pdo->prepare("INSERT INTO club_memberships(user_id,status,starts_on,expires_on,external_ref) VALUES(?,'active','2020-01-01','2099-01-01',?)")->execute([$users[$name],$tag]);
 }
 $_SESSION['club_user_id']=$users['author'];unset($_SESSION['club_auth_sig'],$_SESSION['admin_id']);
 $session=club_editor_start();$original=['title'=>$tag.' article','content'=>'<p>Original article text</p>','content_format'=>'html','editor_session_id'=>$session['id'],'editor_session_token'=>$session['token'],'editor_version'=>club_editor_version(null),'editor_media_present'=>1,'keep_attachment_ids'=>[]];
 $saved=club_content_save('post',null,$original,true);$postId=$saved['id'];
 composer_check(club_community_record('post',$postId)['post_kind']==='article','New classic article kind missing.');
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$postId,'approve');$_SESSION['club_user_id']=$users['author'];
 $edit=club_editor_start($postId);$changed=['post_kind'=>'post','content'=>'<p>Community gathering this weekend</p>','content_format'=>'html','editor_session_id'=>$edit['id'],'editor_session_token'=>$edit['token'],'editor_version'=>club_editor_version($postId),'editor_media_present'=>1,'keep_attachment_ids'=>[]];
 $revision=club_content_save('post',$postId,$changed,true);
 $public=club_community_record('post',$postId);composer_check($public['post_kind']==='article'&&$public['title']===$tag.' article','Unapproved quick post replaced live article metadata.');
 $proposed=json_decode(club_editor_revision($postId)['payload'],true);composer_check($proposed['post_kind']==='post'&&$proposed['title']==='Community gathering this weekend','Revision lost generated title or kind.');
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$postId,'approve','',$revision['owner_id']);
 $public=club_community_record('post',$postId);composer_check($public['post_kind']==='post'&&$public['title']==='Community gathering this weekend','Approved quick kind or title not applied.');
 $_SESSION['club_user_id']=$users['author'];$photos=club_editor_start();
 $pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'party.webp','image/webp',100)")->execute([$photos['id'],$users['author'],$tag.'.webp']);$photoId=(int)$pdo->lastInsertId();
 $photoInput=['post_kind'=>'post','content'=>'<figure><img src="/media.php?id='.$photoId.'" alt="Party photograph"></figure>','content_format'=>'html','editor_session_id'=>$photos['id'],'editor_session_token'=>$photos['token'],'editor_version'=>club_editor_version(null),'editor_media_present'=>1,'keep_attachment_ids'=>[$photoId]];
 $photoPost=club_content_save('post',null,$photoInput,false);composer_check(club_community_record('post',$photoPost['id'])['title']==='Photo update','Saved photo-only update title missing.');
 $photoInput['editor_version']=club_editor_version($photoPost['id']);$photoInput['post_kind']='invalid';composer_denied(fn()=>club_content_save('post',$photoPost['id'],$photoInput,false));
 echo "Composer integration tests passed: quick and photo-only saves, article defaults, kind validation and private revision approval.\n";
} finally {if($pdo->inTransaction())$pdo->rollBack();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);}
