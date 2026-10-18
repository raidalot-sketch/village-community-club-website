<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/calendar.php';
require dirname(__DIR__).'/includes/bootstrap.php';
if(DB_NAME!=='nettlebed_preview')throw new RuntimeException('Isolated preview database required.');
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function day_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
$pdo=db();$pdo->beginTransaction();
try{
 unset($_SESSION['club_user_id'],$_SESSION['admin_id']);$tag='calendar-day-'.bin2hex(random_bytes(4));
 $insert=$pdo->prepare("INSERT INTO events(title,slug,event_date,start_time,end_time,description,status,visibility) VALUES(?,?,?,?,?,?,'published',?)");
 $insert->execute([$tag.' public',$tag.'-public','2026-10-18','12:00','13:00','Public fixture','public']);
 $insert->execute([$tag.' private',$tag.'-private','2026-10-18','14:00','15:00','Private fixture','members']);
 $insert->execute([$tag.' overnight',$tag.'-overnight','2026-10-17','23:00','01:00','Overnight fixture','public']);
 $_GET=['calendar_month'=>'2026-10'];ob_start();include APP_ROOT.'/index.php';$home=ob_get_clean();
 day_check(str_contains($home,'calendar.php?date=2026-10-18#day-events'),'Homepage date opens the selected day');
 day_check(str_contains($home,'Show events for Sunday 18 October 2026'),'Date links have meaningful accessible labels');
 $_GET=['date'=>'2026-10-18'];ob_start();include APP_ROOT.'/calendar.php';$html=ob_get_clean();
 preg_match('/id="day-events".*?<\/section>/s',$html,$match);$dayHtml=$match[0]??'';
 day_check(str_contains($dayHtml,'Sunday 18 October 2026'),'Selected date is clearly identified');
 day_check(str_contains($dayHtml,$tag.' public')&&str_contains($dayHtml,$tag.' overnight'),'Selected day includes public and overlapping overnight events');
 day_check(!str_contains($html,$tag.' private'),'Anonymous day view cannot expose members-only events');
 $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES('Calendar reviewer',?,'fixture','active',NOW())")->execute([$tag.'@example.invalid']);$reviewer=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'publisher')")->execute([$reviewer]);$_SESSION['club_user_id']=$reviewer;
 $_GET=['date'=>'2026-10-18'];ob_start();include APP_ROOT.'/calendar.php';$memberHtml=ob_get_clean();
 day_check(str_contains($memberHtml,$tag.' private'),'Authorised members can see private events for the chosen day');unset($_SESSION['club_user_id']);
 $_GET=['date'=>'2026-02-30'];ob_start();include APP_ROOT.'/calendar.php';$html=ob_get_clean();
 day_check(!str_contains($html,'id="day-events"'),'Invalid dates cannot create a misleading day view');
 $_GET=['date'=>'2099-06-20'];ob_start();include APP_ROOT.'/calendar.php';$html=ob_get_clean();
 day_check(str_contains($html,'No events scheduled for this day'),'Empty dates explain that no events are scheduled');
}finally{$pdo->rollBack();$_GET=[];}
echo "Day calendar checks passed; fixtures rolled back.\n";
