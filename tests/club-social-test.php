<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('APP_ROOT',dirname(__DIR__));
require_once APP_ROOT.'/config/config.php';
if(DB_NAME!=='nettlebed_preview')throw new RuntimeException('Use the isolated nettlebed_preview database.');
// Standalone fixture harness: a public-looking URL and fake credentials; every request is mocked.
foreach(['CLUB_SOCIAL_PUBLISH_ENABLED','CLUB_META_PAGE_ID','CLUB_META_PAGE_TOKEN','CLUB_META_INSTAGRAM_ID','CLUB_META_GRAPH_VERSION','CLUB_SOCIAL_PUBLIC_MEDIA_READY'] as $key)if(defined($key))throw new RuntimeException('Run tests with unconfigured social credentials.');
$facebookOnly=in_array('--facebook-only',$argv,true);
define('CLUB_SOCIAL_PUBLISH_ENABLED',true);define('CLUB_META_PAGE_ID','123');define('CLUB_META_PAGE_TOKEN','fixture-not-a-real-token');define('CLUB_META_INSTAGRAM_ID',$facebookOnly?'':'456');define('CLUB_META_GRAPH_VERSION','v25.0');define('CLUB_SOCIAL_PUBLIC_MEDIA_READY',true);
function site_url(string $path=''):string{return 'https://club.example.invalid/'.ltrim($path,'/');}
require_once APP_ROOT.'/includes/db.php';require_once APP_ROOT.'/includes/club-uploads.php';require_once APP_ROOT.'/includes/club-social.php';
$checks=0;function social_check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function social_denied(callable $call,string $message):void{try{$call();}catch(Throwable $e){social_check(true,$message);return;}throw new RuntimeException($message);}
$pdo=db();$pdo->beginTransaction();$tag='social-test-'.bin2hex(random_bytes(8));$files=[];
try {
    // Hide any unrelated eligible rows inside the rolled-back transaction.
    $pdo->exec("UPDATE club_outbox SET available_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE channel IN ('facebook','instagram') AND status IN ('pending','connection_required')");
    $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES('Social fixture',?,'unused','active',NOW())")->execute([$tag.'@example.invalid']);$user=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO club_posts(author_user_id,title,slug,content,status) VALUES(?,'Club news',?,?,'published')")->execute([$user,$tag,'<p>First line<br>Second line</p><p>Third &amp; fourth</p>']);$post=(int)$pdo->lastInsertId();
    $snapshot=club_social_snapshot('post',$post);social_check(str_contains($snapshot['text'],"First line\nSecond line\nThird & fourth"),'Paragraphs and line breaks retained without HTML');
    $payload=$snapshot+['auto_share'=>true];
    $queue=function(string $channel,array $value)use($pdo,$tag):int{$pdo->prepare('INSERT INTO club_outbox(channel,event_key,payload,status) VALUES(?,?,?,?)')->execute([$channel,$tag.'-'.bin2hex(random_bytes(4)),json_encode($value,JSON_THROW_ON_ERROR),'pending']);return(int)$pdo->lastInsertId();};
    $row=function(int $id)use($pdo):array{$q=$pdo->prepare('SELECT * FROM club_outbox WHERE id=?');$q->execute([$id]);return $q->fetch();};
    if($facebookOnly){
        for($i=0;$i<15;$i++)$queue('instagram',$payload);
        $connected=$queue('facebook',$payload);$network=0;club_social_run(true,function()use(&$network){$network++;return['id'=>'123_555'];});
        social_check($row($connected)['status']==='sent'&&$network===1,'Disconnected older Instagram queue cannot starve connected Facebook');
        echo 'PASS: '.$checks." Facebook-only checks; requests mocked and fixtures rolled back.\n";
        return;
    }
    $fb=$queue('facebook',$payload);$legacy=$queue('facebook',$snapshot);$calls=[];
    $mock=function($method,$route,$fields)use(&$calls){$calls[]=[$method,$route,$fields];$nested=club_social_run(true,fn()=>throw new RuntimeException('Concurrent duplicate'));social_check($nested['sent']===0,'A second worker cannot claim the processing delivery');return['id'=>'123_789'];};
    $before=$row($fb);$dry=club_social_run(false,$mock);social_check($dry['dry_run']===1 && !$calls && $row($fb)===$before,'Dry run makes no calls or queue changes; legacy requests ignored');
    $result=club_social_run(true,$mock);social_check($result['sent']===1&&count($calls)===1&&$calls[0][1]==='123/feed','Facebook publishes once through mocked page feed');
    social_check($row($fb)['remote_id']==='123_789'&&(int)$row($fb)['attempts']===1&&$row($legacy)['status']==='pending','Receipt stored and legacy fixture untouched');
    club_social_run(true,$mock);social_check(count($calls)===1,'Sent deliveries are never repeated');
    $stale=$queue('facebook',$payload);$pdo->prepare("UPDATE club_posts SET content='New approved copy' WHERE id=?")->execute([$post]);club_social_run(true,$mock);social_check($row($stale)['status']==='failed'&&count($calls)===1,'Stale approved snapshot cannot send');
    $payload=club_social_snapshot('post',$post)+['auto_share'=>true];$tampered=$payload;$tampered['text']='Injected text';$bad=$queue('facebook',$tampered);club_social_run(true,$mock);social_check($row($bad)['status']==='failed'&&count($calls)===1,'Tampered queue payload cannot send');
    $failure=$queue('facebook',$payload);$failCalls=0;$throw=function()use(&$failCalls){$failCalls++;throw new RuntimeException('fixture-not-a-real-token simulated raw provider error');};club_social_run(true,$throw);club_social_run(true,$throw);
    social_check($row($failure)['status']==='failed'&&$failCalls===1&&!str_contains($row($failure)['last_error'],'fixture-not-a-real-token'),'Ambiguous errors require manual attention and never leak errors or credentials');
    social_check(club_social_connection_ready('facebook')&&club_social_connection_ready('instagram')&&!club_social_connection_ready('email'),'Private connection checks require correct channel and configured prerequisites');
    $ig=$queue('instagram',$payload);$sequence=[];
    $instagram=function($method,$route,$fields)use(&$sequence,$row,$ig){$sequence[]=[$method,$route,$fields];if($route==='456/media')return['id'=>'900'];if($route==='900'){social_check((json_decode($row($ig)['payload'],true)['meta_creation_id']??'')==='900','Instagram container persisted before status polling');return['status_code'=>'FINISHED'];}if($route==='456/media_publish')return['id'=>'901'];throw new RuntimeException('Unexpected request.');};
    club_social_run(true,$instagram);social_check($row($ig)['status']==='sent'&&$row($ig)['remote_id']==='901'&&array_column($sequence,1)===['456/media','900','456/media_publish'],'Instagram creates, polls and publishes once');
    parse_str(parse_url($sequence[0][2]['image_url'],PHP_URL_QUERY),$query);$image=club_social_image_payload((int)$query['id'],$query['hash'],(int)$query['expires'],$query['signature']);
    $jpeg=club_social_jpeg($image);social_check(str_starts_with($jpeg,"\xff\xd8")&&getimagesizefromstring($jpeg)[0]===1080,'Text-only post receives a signed square JPEG fallback');
    social_denied(fn()=>club_social_image_payload($ig,$query['hash'],(int)$query['expires'],'forged'),'Forged media signatures refused');
    social_denied(fn()=>club_social_image_payload($ig,$query['hash'],time()-1,$query['signature']),'Expired media signatures refused');
    social_denied(fn()=>club_social_image_url($ig,$payload['source_hash'],time()+86401),'Media signing lifetime cannot exceed 24 hours');
    social_check(str_ends_with(club_social_card_lines(str_repeat('Long title ',30),20,2)[1],'...'),'Long fallback title is visibly truncated');
    $stopped=$queue('facebook',$payload);$pdo->prepare("UPDATE club_outbox SET status='processing',updated_at=DATE_SUB(NOW(),INTERVAL 31 MINUTE) WHERE id=?")->execute([$stopped]);club_social_run(true,$mock);
    social_check($row($stopped)['status']==='failed'&&count($calls)===1,'Interrupted old processing delivery moves to manual attention without sending');
    $file=club_storage_root().'/'.$tag.'.webp';$source=imagecreatetruecolor(400,200);imagefill($source,0,0,imagecolorallocate($source,190,80,40));imagewebp($source,$file);imagedestroy($source);$files[]=$file;
    $pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES('post',?,?,?,'approved.webp','image/webp',?)")->execute([$post,$user,basename($file),filesize($file)]);
    $withImage=club_social_snapshot('post',$post)+['auto_share'=>true];$photo=$queue('facebook',$withImage);$photoCalls=[];
    club_social_run(true,function($method,$route,$fields)use(&$photoCalls){$photoCalls[]=[$method,$route,$fields];return['id'=>'903'];});
    social_check($row($photo)['status']==='sent'&&$photoCalls[0][1]==='123/photos','Facebook uses approved photo endpoint when signed public media is ready');
    parse_str(parse_url($photoCalls[0][2]['url'],PHP_URL_QUERY),$photoQuery);$photoPayload=club_social_image_payload($photo,(string)$photoQuery['hash'],(int)$photoQuery['expires'],$photoQuery['signature']);$square=club_social_jpeg($photoPayload);
    $dimensions=getimagesizefromstring($square);social_check($dimensions[0]===1080&&$dimensions[1]===1080&&$dimensions['mime']==='image/jpeg','Approved attachment is re-encoded into square JPEG');
    $pdo->prepare('DELETE FROM club_attachments WHERE id=?')->execute([$withImage['image_attachment_id']]);
    $waiting=$queue('instagram',$payload);$waitCalls=0;$wait=function($method,$route,$fields)use(&$waitCalls){$waitCalls++;return $route==='456/media'?['id'=>'902']:['status_code'=>'IN_PROGRESS'];};club_social_run(true,$wait);club_social_run(true,$wait);
    social_check($row($waiting)['status']==='failed'&&$waitCalls===6,'Unfinished containers never publish or retry blindly');
    $unpublished=$queue('facebook',$payload);$pdo->prepare("UPDATE club_posts SET status='submitted' WHERE id=?")->execute([$post]);club_social_run(true,$mock);social_check($row($unpublished)['status']==='failed','Withdrawn post never sends');
    social_denied(fn()=>club_social_image_payload($ig,$query['hash'],(int)$query['expires'],$query['signature']),'Signed image is unavailable after source withdrawal');
    social_denied(fn()=>club_social_graph('POST','evil.invalid/feed',[],$mock),'Transport rejects arbitrary routes');
} finally {$pdo->rollBack();foreach($files as $file)if(is_file($file))unlink($file);}
echo 'PASS: '.$checks." social delivery checks; requests mocked and fixtures rolled back.\n";
