<?php
/** Membership owns eligibility; Invoice owns the club's merchant payment records. */
function club_membership_transaction(callable $work): mixed {
    static $serial=0;$pdo=db();$own=!$pdo->inTransaction();$savepoint='membership_'.(++$serial);
    if($own)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT '.$savepoint);
    try{$result=$work();if($own)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT '.$savepoint);return $result;}
    catch(Throwable $e){if($pdo->inTransaction()){if($own)$pdo->rollBack();else $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);}throw $e;}
}
function club_membership_year_end(string $start):string {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$start,new DateTimeZone('Europe/London'));
    if(!$date || $date->format('Y-m-d')!==$start)throw new InvalidArgumentException('Invalid membership start date.');
    $year=(int)$date->format('Y')+1;$month=(int)$date->format('m');$last=(int)(new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month)))->format('t');
    return sprintf('%04d-%02d-%02d',$year,$month,min((int)$date->format('d'),$last));
}
function club_membership_payment_day(array $remote):string {
    $raw=$remote['paid_at']??null;
    if(!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',$raw))throw new RuntimeException('Invoice has not supplied a valid payment confirmation time.');
    try{$paid=new DateTimeImmutable($raw);$errors=DateTimeImmutable::getLastErrors();}catch(Exception $e){throw new RuntimeException('Invoice returned an invalid payment confirmation time.');}
    if(($errors && ($errors['warning_count'] || $errors['error_count'])) || $paid>new DateTimeImmutable('now'))throw new RuntimeException('Invoice returned an invalid or future payment confirmation time.');
    return $paid->setTimezone(new DateTimeZone('Europe/London'))->format('Y-m-d');
}
function club_membership_row(int $id):?array {$s=db()->prepare('SELECT * FROM club_memberships WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
function club_membership_for_user(int $id):?array {
    $s=db()->prepare("SELECT * FROM club_memberships WHERE user_id=? ORDER BY (status='active' AND expires_on>?) DESC, (status='awaiting_payment') DESC, expires_on DESC, id DESC LIMIT 1");$s->execute([$id,date('Y-m-d')]);return $s->fetch()?:null;
}
function club_pending_membership_for_user(int $id):?array {$s=db()->prepare("SELECT * FROM club_memberships WHERE user_id=? AND status='awaiting_payment' ORDER BY id DESC LIMIT 1");$s->execute([$id]);return $s->fetch()?:null;}
function club_invoice_origin():string {
    $base=defined('CLUB_INVOICE_API_BASE')?rtrim((string)CLUB_INVOICE_API_BASE,'/'):'';$parts=parse_url($base);
    if(!$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user'],$parts['pass']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path']))throw new RuntimeException('The Invoice connection is being prepared. Please contact the club for membership help.');
    return $base;
}
function club_membership_payment_ready():bool {try{club_membership_assert_ready();return true;}catch(RuntimeException $e){return false;}}
function club_membership_assert_ready():int {
    $fee=filter_var(club_setting('membership_fee_minor'),FILTER_VALIDATE_INT);
    if($fee===false || $fee<1 || $fee>1000000 || trim(club_setting('bank_details'))==='')throw new RuntimeException('The committee is confirming the annual fee and bank details. Membership payment requests will open once these are ready.');
    club_invoice_origin();if(!defined('CLUB_INVOICE_CLIENT_TOKEN') || strlen((string)CLUB_INVOICE_CLIENT_TOKEN)<32)throw new RuntimeException('The Invoice connection is being prepared. Please contact the club for membership help.');return $fee;
}
function club_invoice_api(string $method,string $path,array $payload=[],?callable $transport=null):array {
    $origin=club_invoice_origin();if(!defined('CLUB_INVOICE_CLIENT_TOKEN') || strlen((string)CLUB_INVOICE_CLIENT_TOKEN)<32)throw new RuntimeException('The Invoice connection is not configured.');
    if(!in_array($method,['GET','POST'],true) || !preg_match('~^/(?:[a-f0-9-]{36}(?:/bank-transfer)?)?$~i',$path))throw new InvalidArgumentException('Invalid Invoice request.');
    $url=$origin.'/api/v1/club-memberships/invoices'.($path==='/'?'':$path);
    if($transport)$response=$transport($method,$url,$payload);
    else {
        if(!function_exists('curl_init'))throw new RuntimeException('Membership payments are temporarily unavailable. Please contact the club.');
        $curl=curl_init($url);$body='';$options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.CLUB_INVOICE_CLIENT_TOKEN],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}];
        if($method==='POST')$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR);curl_setopt_array($curl,$options);$ok=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if($ok===false)throw new RuntimeException('We could not reach Invoice. Your request is saved safely; please try again.');
        $response=['status'=>$code,'body'=>json_decode($body,true)];
    }
    $code=(int)($response['status']??0);if(!in_array($code,[200,201],true))throw new RuntimeException(match($code){409=>'Invoice needs committee review before this payment can be reconciled.',401,403=>'The club’s Invoice connection needs an administrator’s attention.',422=>'Invoice could not accept these details. Please ask the treasurer to check the record.',default=>'Invoice is not ready to process this request. Your membership has not been marked as paid.'});
    if(!is_array($response['body']??null))throw new RuntimeException('Invoice returned an unreadable response. Please try again.');return $response['body'];
}
function club_validate_invoice_response(array $data,int $expectedAmount,?string $expectedId=null):array {
    $id=(string)($data['id']??'');$url=(string)($data['hosted_url']??'');$parts=parse_url($url);$base=parse_url(club_invoice_origin());
    if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',$id) || ($expectedId!==null && !hash_equals($expectedId,$id)))throw new RuntimeException('Invoice returned an unexpected invoice reference.');
    if(($data['currency']??'')!=='GBP' || !isset($data['total_minor']) || !is_int($data['total_minor']) || $data['total_minor']!==$expectedAmount)throw new RuntimeException('Invoice returned a different amount or currency. Please contact the treasurer.');
    if(!$parts || ($parts['scheme']??'')!=='https' || strtolower($parts['host']??'')!==strtolower($base['host']) || ($parts['port']??443)!==($base['port']??443) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ($parts['path']??'')!=='/invoice/'.$id)throw new RuntimeException('Invoice returned an untrusted payment link.');
    if(!in_array($data['status']??'', ['sent','viewed','paid','partially_paid','void','credited'],true) || empty($data['number']) || !is_string($data['number']) || strlen($data['number'])>100 || empty($data['payment_reference']) || !is_string($data['payment_reference']) || strlen($data['payment_reference'])>100)throw new RuntimeException('Invoice returned incomplete payment details.');return $data;
}
function club_membership_invoice_state(int $id):?array {$s=db()->prepare('SELECT payload FROM club_outbox WHERE event_key=?');$s->execute(['membership-invoice:'.$id]);$value=$s->fetchColumn();return $value?json_decode($value,true):null;}
function club_request_membership(int $userId,?callable $transport=null):array {
    if((int)(club_current_user()['id']??0)!==$userId)throw new RuntimeException('You can request only your own membership.');
    $fee=club_membership_assert_ready();
    $reserved=club_membership_transaction(function()use($userId,$fee){
        $s=db()->prepare('SELECT * FROM club_users WHERE id=? FOR UPDATE');$s->execute([$userId]);$user=$s->fetch();if(!$user || $user['status']!=='active' || !$user['email_verified_at'])throw new RuntimeException('The committee must approve and verify your account before a membership invoice can be requested.');
        $membership=club_pending_membership_for_user($userId);
        if(!$membership){$ref='membership-'.bin2hex(random_bytes(20));db()->prepare("INSERT INTO club_memberships(user_id,status,external_ref,amount_minor,currency) VALUES(?,'awaiting_payment',?,?,'GBP')")->execute([$userId,$ref,$fee]);$membership=club_membership_row((int)db()->lastInsertId());
            club_queue_outbox('membership_invoice','membership-invoice:'.$membership['id'],['request'=>['external_ref'=>$ref,'member_name'=>$user['full_name'],'email'=>$user['email'],'amount_minor'=>$fee,'currency'=>'GBP']],'connection_required');}
        return $membership;
    });
    if($reserved['invoice_public_id'])return $reserved;
    $state=club_membership_invoice_state((int)$reserved['id']);if(empty($state['request']))throw new RuntimeException('This membership needs committee review before invoicing.');
    $remote=club_validate_invoice_response(club_invoice_api('POST','/',$state['request'],$transport),(int)$reserved['amount_minor']);
    return club_membership_transaction(function()use($reserved,$remote,$state){
        $s=db()->prepare('SELECT id FROM club_memberships WHERE invoice_public_id=? AND id<>? FOR UPDATE');$s->execute([$remote['id'],$reserved['id']]);if($s->fetchColumn())throw new RuntimeException('This Invoice invoice is already linked to another membership.');
        $s=db()->prepare('SELECT * FROM club_memberships WHERE id=? FOR UPDATE');$s->execute([$reserved['id']]);$current=$s->fetch();
        if($current['invoice_public_id'] && $current['invoice_public_id']!==$remote['id'])throw new RuntimeException('This membership already has a different invoice.');
        db()->prepare('UPDATE club_memberships SET invoice_public_id=?,payment_reference=? WHERE id=?')->execute([$remote['id'],$remote['payment_reference'],$current['id']]);$state['response']=$remote;
        db()->prepare("UPDATE club_outbox SET payload=?,status='sent',last_error=NULL WHERE event_key=?")->execute([json_encode($state,JSON_THROW_ON_ERROR),'membership-invoice:'.$current['id']]);return club_membership_row((int)$current['id']);
    });
}
function club_membership_assert_access(array $membership):void {if((int)(club_current_user()['id']??0)!==(int)$membership['user_id'] && !club_can('payments.manage'))throw new RuntimeException('You do not have access to this membership payment.');}
function club_activate_membership(int $id,array $remote,?int $actor=null):array {
    $membership=club_membership_row($id);if(!$membership)throw new RuntimeException('Membership not found.');club_membership_assert_access($membership);
    $remote=club_validate_invoice_response($remote,(int)$membership['amount_minor'],$membership['invoice_public_id']);
    if(($remote['status']??'')!=='paid' || ($remote['amount_paid_minor']??null)!==(int)$membership['amount_minor'] || ($remote['credited_minor']??null)!==0 || ($remote['balance_due_minor']??null)!==0)throw new RuntimeException('Invoice has not confirmed full payment for this membership.');
    $paymentDay=club_membership_payment_day($remote);
    return club_membership_transaction(function()use($id,$membership,$remote,$actor,$paymentDay){
        $s=db()->prepare('SELECT id FROM club_users WHERE id=? FOR UPDATE');$s->execute([$membership['user_id']]);
        $s=db()->prepare('SELECT * FROM club_memberships WHERE id=? FOR UPDATE');$s->execute([$id]);$current=$s->fetch();
        if($current['status']==='active' && $current['confirmed_at'])return $current;
        if($current['status']!=='awaiting_payment')throw new RuntimeException('This membership is not awaiting payment.');
        $s=db()->prepare("SELECT MAX(expires_on) FROM club_memberships WHERE user_id=? AND status='active' AND id<>?");$s->execute([$current['user_id'],$id]);$existing=(string)$s->fetchColumn();$start=max($paymentDay,$existing);$end=club_membership_year_end($start);
        db()->prepare("UPDATE club_memberships SET status='active',starts_on=?,expires_on=?,confirmed_at=NOW(),confirmed_by=? WHERE id=?")->execute([$start,$end,$actor,$id]);
        db()->prepare('INSERT INTO club_payment_events(event_id,membership_id,payload_hash) VALUES(?,?,?)')->execute(['invoice-paid:'.$remote['id'],$id,hash('sha256',json_encode([$remote['id'],$remote['total_minor'],$remote['currency']],JSON_THROW_ON_ERROR))]);
        club_queue_outbox('audit','membership-activated:'.$id,['action'=>'membership.activated','membership_id'=>$id,'actor_user_id'=>$actor,'starts_on'=>$start,'expires_on'=>$end],'sent');return club_membership_row($id);
    });
}
function club_confirm_membership_payment(int $id,string $reference,string $note='',?callable $transport=null):array {
    if(!club_can('payments.manage'))throw new RuntimeException('Only the treasurer can confirm bank payments.');
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/',$reference) || mb_strlen($note)>1000)throw new InvalidArgumentException('Enter a bank receipt reference using letters, numbers, periods or hyphens, and a note no longer than 1000 characters.');
    $membership=club_membership_row($id);if(!$membership || !$membership['invoice_public_id'])throw new RuntimeException('An Invoice invoice must be issued before confirming payment.');
    $payload=club_membership_transaction(function()use($membership,$reference,$note){
        $s=db()->prepare('SELECT id FROM club_users WHERE id=? FOR UPDATE');$s->execute([$membership['user_id']]);
        $s=db()->prepare('SELECT id FROM club_memberships WHERE id=? FOR UPDATE');$s->execute([$membership['id']]);
        $s=db()->prepare('SELECT payload FROM club_outbox WHERE event_key=? FOR UPDATE');$s->execute(['membership-confirm:'.$membership['id']]);$existing=$s->fetchColumn();
        if($existing){$payload=json_decode($existing,true);if(($payload['external_ref']??'')!==$reference || ($payload['note']??'')!==trim($note))throw new RuntimeException('A bank confirmation is already recorded with different receipt details. Reuse those details or review it in Invoice.');return $payload;}
        $payload=['external_ref'=>$reference,'confirmed_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format(DATE_ATOM),'note'=>trim($note)];club_queue_outbox('membership_receipt','membership-confirm:'.$membership['id'],$payload,'connection_required');return $payload;
    });
    $remote=club_invoice_api('POST','/'.$membership['invoice_public_id'].'/bank-transfer',$payload,$transport);
    $result=club_activate_membership($id,$remote,(int)club_current_user()['id']);db()->prepare("UPDATE club_outbox SET status='sent',last_error=NULL WHERE event_key=?")->execute(['membership-confirm:'.$id]);return $result;
}
function club_sync_membership(int $id,?callable $transport=null):array {
    $membership=club_membership_row($id);if(!$membership || !$membership['invoice_public_id'])throw new RuntimeException('Membership invoice not found.');club_membership_assert_access($membership);
    $remote=club_validate_invoice_response(club_invoice_api('GET','/'.$membership['invoice_public_id'],[],$transport),(int)$membership['amount_minor'],$membership['invoice_public_id']);
    if($remote['status']==='paid')return club_activate_membership($id,$remote,club_can('payments.manage')?(int)club_current_user()['id']:null);
    return $membership;
}
function club_queue_membership_reminders():int {
    $s=db()->prepare("SELECT m.*,u.email,u.full_name FROM club_memberships m JOIN club_users u ON u.id=m.user_id WHERE m.status='active' AND m.expires_on>? AND m.expires_on<=? AND u.status='active' AND u.email_verified_at IS NOT NULL AND NOT EXISTS(SELECT 1 FROM club_memberships n WHERE n.user_id=m.user_id AND n.status='active' AND n.expires_on>m.expires_on)");$s->execute([date('Y-m-d'),date('Y-m-d',strtotime('+30 days'))]);$rows=$s->fetchAll();
    foreach($rows as $m)club_queue_outbox('membership_email','membership-renewal:'.$m['id'].':'.$m['expires_on'],['to'=>$m['email'],'subject'=>'Your Nettlebed Village Club membership renewal','membership_id'=>$m['id'],'expires_on'=>$m['expires_on'],'url'=>site_url('/members/membership.php')],'connection_required');return count($rows);
}
