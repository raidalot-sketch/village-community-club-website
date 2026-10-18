<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!defined('CLUB_INVOICE_API_BASE'))define('CLUB_INVOICE_API_BASE','https://invoice.example.invalid');
if(!defined('CLUB_INVOICE_CLIENT_TOKEN'))define('CLUB_INVOICE_CLIENT_TOKEN','fictional-test-only-secret-32-characters');
require_once __DIR__.'/../includes/bootstrap.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL);exit(1);});
if(!str_contains(DB_NAME,'preview'))throw new RuntimeException('Membership tests require an isolated preview database.');
function membership_check(bool $condition,string $label):void {if(!$condition)throw new RuntimeException('FAIL: '.$label);}
function membership_refuses(callable $fn,string $label):void {try{$fn();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new RuntimeException('FAIL: '.$label);}
$pdo=db();$pdo->beginTransaction();$checks=0;
try{
 membership_check(club_membership_year_end('2024-02-29')==='2025-02-28','leap-year term clamps');$checks++;
 membership_check(club_membership_year_end('2026-10-18')==='2027-10-18','twelve calendar months');$checks++;
 membership_check(club_membership_payment_day(['paid_at'=>'2020-07-01T23:30:00Z'])==='2020-07-02','payment day follows London summer time');$checks++;
 membership_refuses(fn()=>club_membership_payment_day(['paid_at'=>date(DATE_ATOM,strtotime('+1 day'))]),'future payment date refused');$checks++;
 $email='membership-'.bin2hex(random_bytes(8)).'@example.invalid';$pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES('Membership Test',?,?,'active',NOW())")->execute([$email,password_hash('fictional-'.bin2hex(random_bytes(15)),PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();$_SESSION['club_user_id']=$id;
 $calls=[];$state='sent';$mock=function($method,$url,$payload)use(&$calls,&$state){$calls[]=[$method,$url,$payload];return ['status'=>$method==='POST'&&!str_ends_with($url,'bank-transfer')?201:200,'body'=>['id'=>'11111111-1111-4111-8111-111111111111','number'=>'TEST-1','status'=>$state,'total_minor'=>2500,'amount_paid_minor'=>$state==='paid'?2500:0,'credited_minor'=>0,'balance_due_minor'=>$state==='paid'?0:2500,'currency'=>'GBP','hosted_url'=>'https://invoice.example.invalid/invoice/11111111-1111-4111-8111-111111111111','payment_reference'=>'TEST-1','paid_at'=>$state==='paid'?date(DATE_ATOM):null]];};
 set_setting('membership_fee_minor','');set_setting('bank_details','Fictional bank instructions');membership_refuses(fn()=>club_request_membership($id,$mock),'unconfirmed fee refused');$checks++;
 set_setting('membership_fee_minor','2500');set_setting('bank_details','');membership_refuses(fn()=>club_request_membership($id,$mock),'unconfirmed bank refused');$checks++;
 set_setting('bank_details','Fictional bank instructions');
 $m=club_request_membership($id,$mock);membership_check($m['status']==='awaiting_payment' && $m['invoice_public_id']==='11111111-1111-4111-8111-111111111111','invoice request awaits real payment');$checks++;
 $again=club_request_membership($id,$mock);membership_check($again['id']===$m['id'] && count($calls)===1,'pending membership reused without duplicate invoice');$checks++;
 membership_check(!club_is_active_member($id),'unpaid request grants no access');$checks++;
 $s=$pdo->prepare('SELECT COUNT(*) FROM club_memberships WHERE user_id=?');$s->execute([$id]);membership_check((int)$s->fetchColumn()===1,'one pending record');$checks++;
 membership_refuses(fn()=>club_confirm_membership_payment((int)$m['id'],'receipt-1','Checked',$mock),'ordinary user cannot confirm payments');$checks++;
 $pdo->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'treasurer')")->execute([$id]);
 $state='paid';$paid=club_confirm_membership_payment((int)$m['id'],'receipt-1','Checked',$mock);membership_check($paid['status']==='active' && $paid['starts_on']===date('Y-m-d') && $paid['expires_on']===club_membership_year_end(date('Y-m-d')),'confirmed Invoice receipt activates one calendar year');$checks++;
 $again=club_confirm_membership_payment((int)$m['id'],'receipt-1','Checked',$mock);membership_check($again['expires_on']===$paid['expires_on'],'duplicate confirmation cannot extend twice');$checks++;
 $s=$pdo->prepare('SELECT COUNT(*) FROM club_payment_events WHERE membership_id=?');$s->execute([$m['id']]);membership_check((int)$s->fetchColumn()===1,'single activation audit');$checks++;
 membership_check(club_is_active_member($id),'confirmed member eligible');$checks++;
 $state='sent';$mock2=function($method,$url,$payload)use($mock){$r=$mock($method,$url,$payload);$r['body']['id']='22222222-2222-4222-8222-222222222222';$r['body']['hosted_url']='https://invoice.example.invalid/invoice/22222222-2222-4222-8222-222222222222';return $r;};
 $renew=club_request_membership($id,$mock2);$state='paid';$extended=club_confirm_membership_payment((int)$renew['id'],'receipt-2','Renewal',$mock2);membership_check($extended['starts_on']===$paid['expires_on'] && $extended['expires_on']===club_membership_year_end($paid['expires_on']),'early renewal extends existing expiry');$checks++;
 $evil=function()use($mock){$r=$mock('POST','',[]);$r['body']['hosted_url']='https://attacker.example.invalid/invoice/secret';return $r;};membership_refuses(fn()=>club_validate_invoice_response($evil()['body'],2500),'foreign hosted URL refused');$checks++;
 $bad=$mock('GET','',[])['body'];$bad['total_minor']=2000;membership_refuses(fn()=>club_validate_invoice_response($bad,2500),'wrong amount refused');$checks++;
 $bad=$mock('GET','',[])['body'];$bad['currency']='USD';membership_refuses(fn()=>club_validate_invoice_response($bad,2500),'wrong currency refused');$checks++;
 // A disconnected request reserves one stable reference and is retried with its original identity snapshot.
 $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES('Retry Test',?,?,'active',NOW())")->execute(['retry-'.$email,password_hash('fake',PASSWORD_DEFAULT)]);$retryId=(int)$pdo->lastInsertId();$_SESSION['club_user_id']=$retryId;
 $down=fn()=>['status'=>503,'body'=>[]];membership_refuses(fn()=>club_request_membership($retryId,$down),'disconnected adapter is not success');$checks++;
 $reserved=club_pending_membership_for_user($retryId);membership_check($reserved && !$reserved['invoice_public_id'],'failed request preserves retry reference');$checks++;
 $mock3=function($method,$url,$payload)use($mock){$r=$mock($method,$url,$payload);$r['body']['id']='33333333-3333-4333-8333-333333333333';$r['body']['hosted_url']='https://invoice.example.invalid/invoice/33333333-3333-4333-8333-333333333333';if($r['body']['status']==='paid')$r['body']['paid_at']=date(DATE_ATOM,strtotime('-5 days'));return $r;};
 $state='sent';$retried=club_request_membership($retryId,$mock3);membership_check($retried['id']===$reserved['id'] && $retried['external_ref']===$reserved['external_ref'],'retry reuses membership reservation');$checks++;
 membership_refuses(fn()=>club_sync_membership((int)$m['id'],$mock),'cannot sync another member invoice');$checks++;
 $state='paid';$badMock=function($method,$url,$payload)use($mock){$r=$mock($method,$url,$payload);$r['body']['id']='99999999-9999-4999-8999-999999999999';return $r;};membership_refuses(fn()=>club_sync_membership((int)$retried['id'],$badMock),'remote invoice mismatch refused');$checks++;
 $synced=club_sync_membership((int)$retried['id'],$mock3);membership_check($synced['status']==='active','verified remote paid status can settle own membership');$checks++;
 membership_check($synced['starts_on']===date('Y-m-d',strtotime('-5 days')),'delayed sync starts term on actual confirmed payment date');$checks++;
 $pdo->prepare('UPDATE club_memberships SET expires_on=DATE_ADD(CURDATE(),INTERVAL 10 DAY) WHERE id=?')->execute([$retried['id']]);
 club_queue_membership_reminders();club_queue_membership_reminders();$s=$pdo->prepare("SELECT COUNT(*) FROM club_outbox WHERE event_key LIKE ? AND status='connection_required'");$s->execute(['membership-renewal:'.$retried['id'].':%']);membership_check((int)$s->fetchColumn()===1,'renewal reminder deduplicated and captured');$checks++;
 require_once __DIR__.'/../bin/club-maintenance.php';
 $mailCalls=0;$delivery=club_deliver_outbox_email(false,1,function()use(&$mailCalls){$mailCalls++;return true;});membership_check($mailCalls===0 && $delivery['disabled']===1,'maintenance does not send by default');$checks++;
 if(MAIL_METHOD==='disabled'){$delivery=club_deliver_outbox_email(true,1,function()use(&$mailCalls){$mailCalls++;return true;});membership_check($mailCalls===0 && $delivery['disabled']===1,'explicit delivery still requires configured mail');$checks++;}
 club_queue_token($retryId,'verify','retry-'.$email);$s=$pdo->prepare("SELECT * FROM club_outbox WHERE channel='member_email' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.to'))=? ORDER BY id DESC LIMIT 1");$s->execute(['retry-'.$email]);$row=$s->fetch();$message=club_outbox_email_message($row);membership_check($message['to']==='retry-'.$email && str_contains($message['subject'],'Confirm'),'verification message validates live hashed token');$checks++;
 $payload=json_decode($row['payload'],true);$payload['to']="injected@example.invalid\r\nBcc: other@example.invalid";$row['payload']=json_encode($payload);membership_refuses(fn()=>club_outbox_email_message($row),'mail header injection refused');$checks++;
}finally{if($pdo->inTransaction())$pdo->rollBack();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);}
echo 'PASS: '.$checks.' membership checks; all fixtures rolled back.'.PHP_EOL;
