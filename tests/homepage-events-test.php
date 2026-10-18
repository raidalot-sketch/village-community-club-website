<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/includes/bootstrap.php';
if(DB_NAME!=='nettlebed_preview')throw new RuntimeException('Isolated preview database required.');
set_exception_handler(function(Throwable $e):void{fwrite(STDERR,$e->getMessage()."\n");exit(1);});
$pdo=db();$pdo->beginTransaction();
try{
 $pdo->exec("UPDATE events SET status='draft'");$tag='home-events-'.bin2hex(random_bytes(4));$start=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');$end=(new DateTimeImmutable('+60 days'))->format('Y-m-d');$fair=(new DateTimeImmutable('+35 days'))->format('Y-m-d');
 $q=$pdo->prepare("INSERT INTO events(title,slug,event_date,start_time,end_time,status,visibility,recurrence,recurrence_end_date) VALUES(?,?,?,'17:00','20:30','published','public',?,?)");
 $q->execute([$tag.' weekly',$tag.'-weekly',$start,'weekly',$end]);$q->execute([$tag.' fair',$tag.'-fair',$fair,'none',null]);
 $_GET=[];ob_start();include APP_ROOT.'/index.php';$html=ob_get_clean();
 if(substr_count($html,'<h3>'.$tag.' weekly</h3>')!==1)throw new RuntimeException('Homepage must show a repeating event only once');
 if(!str_contains($html,'<h3>'.$tag.' fair</h3>'))throw new RuntimeException('Repeating dates must not hide another upcoming event');
 echo "PASS distinct upcoming event cards retain weekly activities and the later fair.\n";
}finally{$pdo->rollBack();}
