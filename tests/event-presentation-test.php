<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/includes/bootstrap.php';
if(DB_NAME!=='nettlebed_preview')throw new RuntimeException('Isolated preview database required.');
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function presentation_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
$pdo=db();$pdo->beginTransaction();
try{
 unset($_SESSION['club_user_id'],$_SESSION['admin_id']);$pdo->exec("UPDATE events SET status='draft'");$tag='poster-test-'.bin2hex(random_bytes(5));
 $q=$pdo->prepare("INSERT INTO events(title,slug,event_date,start_time,end_time,status,visibility,is_featured) VALUES(?,?,?,'19:30',NULL,?,?,?)");
 $q->execute([$tag.' featured',$tag.'-featured',date('Y-m-d',strtotime('+35 days')),'published','public',1]);$id=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO club_attachments(owner_type,owner_id,storage_path,original_name,mime,bytes) VALUES('event',?,'fixture.webp','Event poster','image/webp',100)")->execute([$id]);$imageId=(int)$pdo->lastInsertId();
 presentation_check((int)(club_event_cover($id)['id']??0)===$imageId,'Published event poster available');
 $pdo->prepare("UPDATE events SET visibility='members' WHERE id=?")->execute([$id]);presentation_check(club_event_cover($id)===null,'Private event poster not exposed to visitors');
 $pdo->prepare("UPDATE events SET visibility='public',status='submitted' WHERE id=?")->execute([$id]);presentation_check(club_event_cover($id)===null,'Unapproved poster not exposed');
 $pdo->prepare("UPDATE events SET status='published' WHERE id=?")->execute([$id]);
 for($i=1;$i<=3;$i++)$q->execute([$tag.' nearer '.$i,$tag.'-nearer-'.$i,date('Y-m-d',strtotime('+'.$i.' days')),'published','public',0]);
 $_GET=[];ob_start();include APP_ROOT.'/index.php';$html=ob_get_clean();
 presentation_check(str_contains($html,$tag.' featured')&&str_contains($html,'/media.php?id='.$imageId),'Featured event remains visible with its poster when nearer concerts exist');
 $event=club_community_record('event',$id);$expanded=club_expand_event($event,$event['event_date'],$event['event_date']);
 presentation_check(club_event_time_label($expanded[0])==='From 19:30','Unknown concert finish is not invented');
 presentation_check(!str_contains(club_calendar_ics($expanded),'DTEND'),'Unknown timed finish is omitted from calendar export');
 $expanded[0]['end_time']='22:00:00';$expanded[0]['end_at']=$expanded[0]['start_at']->setTime(22,0);
 presentation_check(club_event_time_label($expanded[0])==='19:30 – 22:00'&&str_contains(club_calendar_ics($expanded),'DTEND'),'Confirmed finish still displays and exports');
}finally{$pdo->rollBack();$_GET=[];}
