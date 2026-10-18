<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/bootstrap.php';
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage().'\n'.$e->getTraceAsString());exit(1);});
function editor_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function editor_save(string $kind,?int $id,array $input,bool $submit,array $files=[]):array {if(($input['content_format']??'')==='html'&&!isset($input['editor_version']))$input['editor_version']=club_editor_version($id);return club_content_save($kind,$id,$input,$submit,$files);}
function editor_denied(callable $fn):void {try{$fn();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new RuntimeException('Unsafe operation accepted');}
editor_check(function_exists('club_editor_sanitize'),'Rich editor sanitizer must exist');
$html='<h2>Heading</h2><p style="text-align:center;color:red" onclick="bad()"><strong>Bold</strong> <em>italic</em></p><figure><img src="/media.php?id=7" alt="Club"><figcaption>Caption</figcaption></figure>';
$clean=club_editor_sanitize($html,[7]);
editor_check(str_contains($clean,'<h2>Heading</h2>')&&str_contains($clean,'<strong>Bold</strong>')&&str_contains($clean,'<figcaption>Caption</figcaption>'),'Formatting round trip failed');
editor_check(!str_contains($clean,'onclick')&&!str_contains($clean,'color:')&&str_contains($clean,'text-align:center'),'Unsafe attributes survived');
editor_check(club_editor_sanitize($clean,[7])===$clean,'Sanitization is not stable');
editor_denied(fn()=>club_editor_sanitize('<img src="/media.php?id=8">',[7]));
editor_denied(fn()=>club_editor_sanitize('<img src="https://evil.invalid/media.php?id=7">',[7]));
editor_denied(fn()=>club_editor_sanitize('<img src="data:image/png;base64,AAAA">',[7]));
editor_check(!str_contains(club_editor_sanitize('<script>alert(1)</script><a href="javascript:alert(1)">safe</a><svg onload="bad()"/>'),'javascript:'),'Dangerous URL survived');
echo "Editor sanitizer tests passed.\n";

$pdo=db();$pdo->beginTransaction();
try {
 $tag='editor-'.bin2hex(random_bytes(5));$users=[];
 foreach(['author','other','publisher','expired'] as $name){
  $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,email_verified_at,status) VALUES(?,?,?,NOW(),'active')")->execute([$name,$tag.$name.'@example.invalid','unused']);$users[$name]=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO club_user_roles(user_id,role) VALUES(?,?)')->execute([$users[$name],$name==='publisher'?'publisher':'member']);
  if(in_array($name,['author','other'],true))$pdo->prepare("INSERT INTO club_memberships(user_id,status,starts_on,expires_on,external_ref) VALUES(?,'active','2020-01-01','2099-01-01',?)")->execute([$users[$name],$tag.$name]);
 }
 unset($_SESSION['club_user_id'],$_SESSION['admin_id'],$_SESSION['club_auth_sig']);editor_denied(fn()=>club_editor_start());
 $_SESSION['club_user_id']=$users['expired'];editor_denied(fn()=>club_editor_start());
 $_SESSION['club_user_id']=$users['author'];$count=(int)$pdo->query('SELECT COUNT(*) FROM club_posts')->fetchColumn();$start=club_editor_start();$session=club_editor_validate($start['id'],$start['token'],null);
 editor_check((int)$pdo->query('SELECT COUNT(*) FROM club_posts')->fetchColumn()===$count,'Starting uploads creates a post');
 editor_denied(fn()=>club_editor_validate($start['id'],'wrong',null));
 $pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'photo.webp','image/webp',100)")->execute([$start['id'],$users['author'],$tag.'.webp']);$photo=(int)$pdo->lastInsertId();
 $stage=$pdo->query('SELECT * FROM club_attachments WHERE id='.$photo)->fetch();editor_check(club_attachment_allowed($stage),'Owner cannot view staged image');
 $_SESSION['club_user_id']=$users['other'];editor_denied(fn()=>club_editor_validate($start['id'],$start['token'],null));editor_check(!club_attachment_allowed($stage),'Other member can view staged image');
 $_SESSION['club_user_id']=$users['author'];$other=club_editor_start();$pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'foreign.webp','image/webp',100)")->execute([$other['id'],$users['author'],$tag.'other.webp']);$foreign=(int)$pdo->lastInsertId();
 $input=['title'=>$tag,'content_format'=>'html','content'=>'<h2>Hello</h2><figure><img src="/media.php?id='.$photo.'" alt="Team"><figcaption>A team photo</figcaption></figure>','summary'=>'Latest club news','cover_attachment_id'=>$photo,'editor_session_id'=>$start['id'],'editor_session_token'=>$start['token']];
 editor_denied(fn()=>editor_save('post',null,array_merge($input,['content'=>'<img src="/media.php?id='.$foreign.'">']),false));
 editor_check(count(club_editor_media($start))===1,'Start result media contract failed');
 $foreignPost=editor_save('post',null,['title'=>$tag.' other post','content'=>'Other details'],true);
 $pdo->prepare("UPDATE club_attachments SET owner_type='post',owner_id=? WHERE id=?")->execute([$foreignPost['id'],$foreign]);
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$foreignPost['id'],'approve');$_SESSION['club_user_id']=$users['author'];
 editor_denied(fn()=>editor_save('post',null,array_merge($input,['content'=>'<img src="/media.php?id='.$foreign.'">']),false));
 $saved=editor_save('post',null,$input,false);$postId=$saved['id'];$post=club_community_record('post',$postId);
 editor_check($post['summary']==='Latest club news'&&(int)$post['cover_attachment_id']===$photo,'Summary or cover not saved');
 editor_check(count(club_attachment_list('post',$postId))===1,'Staged photo not transferred');
 club_editor_validate($start['id'],$start['token'],$postId);editor_denied(fn()=>club_editor_validate($start['id'],$start['token'],null));
 editor_save('post',$postId,$input,true);$_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$postId,'approve');
 $_SESSION['club_user_id']=$users['author'];$edit=club_editor_start($postId);$editSession=club_editor_validate($edit['id'],$edit['token'],$postId);
 editor_check(count(club_editor_media($editSession))===1,'Saved photos absent from editor');
 $stale=club_editor_version($postId);$fresh=array_merge($input,['editor_session_id'=>$edit['id'],'editor_session_token'=>$edit['token'],'editor_version'=>$stale,'content'=>'<p>Newer change</p>']);
 editor_save('post',$postId,$fresh,true);editor_denied(fn()=>club_content_save('post',$postId,$fresh,true));

 $change=array_merge($input,['title'=>$tag.' revised','summary'=>'Pending summary','editor_session_id'=>$edit['id'],'editor_session_token'=>$edit['token']]);$revision=editor_save('post',$postId,$change,true);
 editor_check($revision['owner_type']==='revision','Published edit mutated live post');editor_check(club_community_record('post',$postId)['summary']==='Latest club news','Pending summary leaked live');
 $_SESSION['club_user_id']=$users['other'];editor_denied(fn()=>club_editor_start($postId));
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$postId,'approve','',$revision['owner_id']);
 editor_check(club_community_record('post',$postId)['summary']==='Pending summary','Approved summary missing');editor_check(str_contains(club_editor_render(club_community_record('post',$postId)),'<figcaption>A team photo</figcaption>'),'Public render loses captions');
 $_SESSION['club_user_id']=$users['author'];$limited=club_editor_start($postId);
 foreach(range(1,3) as $i)$pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'extra.webp','image/webp',100)")->execute([$limited['id'],$users['author'],$tag.$i.'.webp']);
 $extra=array_column(club_editor_media($limited),'id');
 $over=array_merge($input,['editor_session_id'=>$limited['id'],'editor_session_token'=>$limited['token'],'content'=>'<p>Too many</p>'.implode('',array_map(fn($i)=>'<img src="/media.php?id='.$i.'">',$extra))]);
 $revisionCount=(int)$pdo->query('SELECT COUNT(*) FROM club_revisions')->fetchColumn();editor_denied(fn()=>editor_save('post',$postId,$over,true));editor_check((int)$pdo->query('SELECT COUNT(*) FROM club_revisions')->fetchColumn()===$revisionCount,'Attachment cap failure left a revision');
 $pdo->prepare('UPDATE club_editor_sessions SET created_at=DATE_SUB(NOW(),INTERVAL 8 DAY) WHERE id=?')->execute([$limited['id']]);editor_denied(fn()=>club_editor_validate($limited['id'],$limited['token'],$postId));

 // Explicit shelf changes retain unplaced photos and archive removals without deleting bytes.
 $shelf=club_editor_start();$shelfIds=[];
 foreach(range(1,3) as $i){$pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'shelf.webp','image/webp',100)")->execute([$shelf['id'],$users['author'],$tag.'shelf'.$i.'.webp']);$shelfIds[]=(int)$pdo->lastInsertId();}
 $draftInput=['title'=>$tag.' shelf','content'=>'<p>Draft photos</p>','content_format'=>'html','editor_session_id'=>$shelf['id'],'editor_session_token'=>$shelf['token'],'editor_media_present'=>'1','keep_attachment_ids'=>array_slice($shelfIds,0,2)];
 $shelfPost=editor_save('post',null,$draftInput,false)['id'];
 editor_check(count(club_attachment_list('post',$shelfPost))===2,'Unplaced kept photos lost on save');
 editor_check($pdo->query('SELECT owner_type FROM club_attachments WHERE id='.$shelfIds[2])->fetchColumn()==='editor_removed','Omitted stage not archived');
 $draftInput['keep_attachment_ids']=[$shelfIds[0]];editor_save('post',$shelfPost,$draftInput,false);
 editor_check($pdo->query('SELECT owner_type FROM club_attachments WHERE id='.$shelfIds[1])->fetchColumn()==='post_removed','Draft removal not archived');
 $beforeSubmit=club_editor_version($shelfPost);editor_save('post',$shelfPost,$draftInput,true);
 editor_denied(fn()=>club_content_save('post',$shelfPost,array_merge($draftInput,['editor_version'=>$beforeSubmit]),false));
 editor_check(club_community_record('post',$shelfPost)['status']==='submitted','Stale autosave withdrew submission');
 $noVersion=$draftInput;editor_denied(fn()=>club_content_save('post',$shelfPost,$noVersion,false));
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$shelfPost,'approve');$_SESSION['club_user_id']=$users['author'];
 $replace=club_editor_start($shelfPost);$replaceIds=[];
 foreach(range(1,3) as $i){$pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'replacement.webp','image/webp',100)")->execute([$replace['id'],$users['author'],$tag.'replace'.$i.'.webp']);$replaceIds[]=(int)$pdo->lastInsertId();}
 $revisionInput=array_merge($draftInput,['editor_session_id'=>$replace['id'],'editor_session_token'=>$replace['token'],'keep_attachment_ids'=>$replaceIds,'title'=>$tag.' replacement','content'=>'<p>Replacement story</p>']);
 $replaceVersion=club_editor_version($shelfPost);$replacement=editor_save('post',$shelfPost,$revisionInput,true);
 editor_check(count(club_attachment_list('post',$shelfPost))===1,'Pending removal changed published attachments');
 editor_check(count(club_editor_media($replace))===3,'Pending removed originals reappear in shelf');
 $revData=club_editor_revision($shelfPost);editor_check(json_decode($revData['payload'],true)['removed_attachment_ids']===[$shelfIds[0]],'Revision did not record original removal');
 $beforeStale=(int)$pdo->query('SELECT COUNT(*) FROM club_revisions')->fetchColumn();editor_denied(fn()=>club_content_save('post',$shelfPost,array_merge($revisionInput,['editor_version'=>$replaceVersion]),true));editor_check((int)$pdo->query('SELECT COUNT(*) FROM club_revisions')->fetchColumn()===$beforeStale,'Stale save created a revision');
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$shelfPost,'reject','Please adjust the photo captions',$replacement['owner_id']);$_SESSION['club_user_id']=$users['author'];
 $returned=club_editor_revision($shelfPost);editor_check($returned['status']==='rejected'&&$returned['reviewer_note']==='Please adjust the photo captions','Returned revision cannot reopen');
 $reopen=club_editor_start($shelfPost);editor_check(count(club_editor_media($reopen))===3,'Returned revision photos lost');
 $revisionInput['editor_session_id']=$reopen['id'];$revisionInput['editor_session_token']=$reopen['token'];$resubmitted=editor_save('post',$shelfPost,$revisionInput,true);
 editor_check(count(club_attachment_list('revision',$resubmitted['owner_id']))===3,'Returned photos not moved to resubmission');
 $_SESSION['club_user_id']=$users['publisher'];club_content_moderate('post',$shelfPost,'approve','',$resubmitted['owner_id']);
 editor_check($pdo->query('SELECT owner_type FROM club_attachments WHERE id='.$shelfIds[0])->fetchColumn()==='post_removed','Approved removal still public');editor_check(count(club_attachment_list('post',$shelfPost))===3,'Approved replacements missing');
 $_SESSION['club_user_id']=$users['author'];$versionBeforeStage=club_editor_version($shelfPost);$newStage=club_editor_start($shelfPost);$pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('editor',?,?,?,'only-stage.webp','image/webp',100)")->execute([$newStage['id'],$users['author'],$tag.'stage-only.webp']);editor_check(club_editor_version($shelfPost)===$versionBeforeStage,'Staging invalidates editor version');
 echo "Editor integration tests passed: role gates, staging, rich save, stale-save protection, reversible removals, replacements, returned revisions and approval.\n";
} finally {if($pdo->inTransaction())$pdo->rollBack();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);}
