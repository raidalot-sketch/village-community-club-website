<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/social.php';$_SERVER['REQUEST_URI']='/social.php';$_SERVER['HTTP_HOST']='127.0.0.1:4186';
require dirname(__DIR__).'/includes/bootstrap.php';
if(DB_NAME!=='nettlebed_preview')throw new RuntimeException('Preview database required.');
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
$checks=[];
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);$GLOBALS['checks'][]=$message;}
function denied(callable $work,string $message):void{try{$work();}catch(DomainException|InvalidArgumentException $e){check(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
$pdo=db();$pdo->beginTransaction();
try{
    $keys=['membership_fee_minor','bank_details','enquiry_recipient','facebook_url','instagram_url'];
    $q=$pdo->prepare('DELETE FROM settings WHERE setting_key IN ('.implode(',',array_fill(0,count($keys),'?')).')');$q->execute($keys);
    check(club_setting('membership_fee_minor')===''&&club_setting('bank_details')==='','Fee and bank instructions stay unconfirmed by default');
    check(club_setting('enquiry_recipient')==='thenettlebedclub@gmail.com','Enquiries default to confirmed club Gmail');
    check(club_setting('facebook_url')==='https://www.facebook.com/NettlebedVillageClub'&&club_setting('instagram_url')==='','Known Facebook profile and unconfirmed Instagram defaults');
    check(club_setting('missing-integration-key','fallback')==='fallback','Unknown settings preserve explicit fallback');
    set_setting('test_api_token','must-not-be-read');check(club_setting('test_api_token','hidden')==='hidden','Secret-like keys never read from settings storage');
    $tag='integration-test-'.bin2hex(random_bytes(8));$users=[];
    foreach(['member','publisher','treasurer','administrator'] as $role){$pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES(?,?,?,'active',NOW())")->execute([$role,$tag.'-'.$role.'@example.invalid','unused-fixture-password']);$users[$role]=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO club_user_roles(user_id,role) VALUES(?,?)')->execute([$users[$role],$role]);}
    unset($_SESSION['admin_id'],$_SESSION['club_auth_sig']);$_SESSION['club_user_id']=$users['member'];
    $settings=['membership_fee_minor'=>'','bank_details'=>'','enquiry_recipient'=>'thenettlebedclub@gmail.com','facebook_url'=>'https://www.facebook.com/NettlebedVillageClub','instagram_url'=>''];
    denied(fn()=>club_save_integration_settings($settings),'Member cannot change club configuration');
    $_SESSION['club_user_id']=$users['treasurer'];denied(fn()=>club_save_integration_settings($settings),'Treasurer cannot configure integrations');
    $_SESSION['club_user_id']=$users['administrator'];
    club_save_integration_settings(array_replace($settings,['membership_fee_minor'=>'2500','api_token'=>'ignored-secret']));
    check(club_setting('membership_fee_minor')==='2500','Administrator can save explicitly confirmed fee');
    club_save_integration_settings($settings);check(club_setting('membership_fee_minor')==='','Fee can be returned to unconfirmed without stale cache');
    denied(fn()=>club_save_integration_settings(array_replace($settings,['membership_fee_minor'=>'-20'])),'Negative fee rejected');
    denied(fn()=>club_save_integration_settings(array_replace($settings,['enquiry_recipient'=>"recipient@example.com\r\nBcc: other@example.com"])),'Mail-header injection in recipient rejected');
    denied(fn()=>club_save_integration_settings(array_replace($settings,['facebook_url'=>'javascript:alert(1)'])),'Unsafe social URL scheme rejected');
    denied(fn()=>club_social_url('instagram','https://www.instagram.com.evil.invalid/club'),'Lookalike social host rejected');
    denied(fn()=>club_social_url('facebook','https://user:password@www.facebook.com/club'),'Credentials in public profile URL rejected');
    $eventKey=$tag.'-delivery';club_queue_outbox('contact_email',$eventKey,['reference'=>'FIRST'],'connection_required');club_queue_outbox('contact_email',$eventKey,['reference'=>'CHANGED'],'sent');
    $q=$pdo->prepare('SELECT payload,status FROM club_outbox WHERE event_key=?');$q->execute([$eventKey]);$delivery=$q->fetchAll();
    check(count($delivery)===1&&json_decode($delivery[0]['payload'],true)['reference']==='FIRST'&&$delivery[0]['status']==='connection_required','Outbox retries preserve one original event, payload and status');
    denied(fn()=>club_queue_outbox('contact_email',$tag,['safe'=>true],'fake_success'),'Unknown outbox status rejected');
    $pdo->prepare("INSERT INTO club_posts(author_user_id,title,slug,content,status) VALUES(?,?,?,?,'published')")->execute([$users['member'],'Approved club news',$tag.'-post','Approved public text']);$postId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO events(title,slug,event_date,description,status,visibility) VALUES(?,?,? ,?,'published','members')")->execute(['Private gathering',$tag.'-event','2026-11-01','Private details']);$eventId=(int)$pdo->lastInsertId();
    $_SESSION['club_user_id']=$users['member'];denied(fn()=>club_social_queue('post',$postId,['facebook']),'Ordinary member cannot request social publication');
    $_SESSION['club_user_id']=$users['publisher'];
    denied(fn()=>club_social_queue('event',$eventId,['facebook']),'Members-only event cannot enter social queue');
    denied(fn()=>club_social_queue('poll',$postId,['facebook']),'Private polls cannot enter social queue');
    denied(fn()=>club_social_queue('post',$postId,['instagram']),'Instagram requires an approved image');
    $pdo->prepare("UPDATE club_posts SET status='submitted' WHERE id=?")->execute([$postId]);denied(fn()=>club_social_queue('post',$postId,['facebook']),'Unapproved content cannot enter social queue');
    $pdo->prepare("UPDATE club_posts SET status='published' WHERE id=?")->execute([$postId]);
    club_social_queue('post',$postId,['facebook']);club_social_queue('post',$postId,['facebook']);
    $q=$pdo->prepare("SELECT payload,status FROM club_outbox WHERE channel='facebook' AND event_key LIKE ?");$q->execute(['social-facebook-post-'.$postId.'-%']);$requests=$q->fetchAll();
    check(count($requests)===1&&$requests[0]['status']==='connection_required','Repeated explicit sharing deduplicates and never claims publication');
    $pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('post',?,?,?,'poster.webp','image/webp',100)")->execute([$postId,$users['member'],$tag.'.webp']);
    club_social_queue('post',$postId,['instagram']);$q=$pdo->prepare("SELECT status,payload FROM club_outbox WHERE channel='instagram' AND event_key LIKE ?");$q->execute(['social-instagram-post-'.$postId.'-%']);$instagramRequest=$q->fetch();
    check($instagramRequest['status']==='connection_required'&&json_decode($instagramRequest['payload'],true)['image_attachment_id']>0,'Instagram preparation stores approved image reference without sending');
    unset($_SESSION['club_user_id']);$_POST=[];$_SERVER['REQUEST_METHOD']='GET';ob_start();include APP_ROOT.'/social.php';$html=ob_get_clean();
    check(!str_contains($html,'<iframe')&&!str_contains($html,'facebook.com/plugins/page.php'),'Public social page makes no embed request before consent');
    check(str_contains($html,'Allow and show Facebook posts')&&str_contains($html,'Instagram profile is being confirmed'),'Public social page provides explicit consent and honest disconnected state');
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'show_facebook','csrf_token'=>csrf_token()];ob_start();include APP_ROOT.'/social.php';$html=ob_get_clean();
    check(str_contains($html,'<iframe')&&str_contains($html,'facebook.com/plugins/page.php'),'Only explicit consent renders optional Facebook embed');
    $statuses=club_integration_status();check(str_contains($statuses['facebook'],'Connection required')&&str_contains($statuses['instagram'],'Connection required'),'Connection status never claims enabled social publication');
}finally{$pdo->rollBack();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);}
foreach($checks as $message)echo 'PASS: '.$message."\n";
echo 'All '.count($checks)." integration checks passed; fixtures rolled back.\n";
