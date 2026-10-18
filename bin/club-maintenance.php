<?php
/** CLI-only maintenance. Default invocation never sends email or calls remote services. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';

function club_outbox_email_message(array $row):array {
    $payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$to=(string)($payload['to']??'');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL) || strlen($to)>190 || preg_match('/[\r\n]/',$to))throw new RuntimeException('The delivery address is invalid.');
    $url=(string)($payload['url']??'');$parts=parse_url($url);$site=parse_url(site_url());
    if(!$parts || ($parts['scheme']??'')!==($site['scheme']??'') || strtolower($parts['host']??'')!==strtolower($site['host']??'') || ($parts['port']??null)!==($site['port']??null) || isset($parts['user']) || isset($parts['pass']))throw new RuntimeException('The notification link does not belong to this club website.');
    if($row['channel']==='member_email'){
        $purpose=$payload['purpose']??'';$path=$purpose==='verify'?'/verify-email.php':($purpose==='reset'?'/reset-password.php':'');
        if(!$path || ($parts['path']??'')!==$path)throw new RuntimeException('Invalid account notification.');
        parse_str($parts['query']??'',$query);$token=(string)($query['token']??'');if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Invalid account token.');
        $s=db()->prepare('SELECT 1 FROM club_password_tokens t JOIN club_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.purpose=? AND t.expires_at>NOW() AND t.consumed_at IS NULL AND u.email=?');$s->execute([hash('sha256',$token),$purpose,$to]);if(!$s->fetchColumn())throw new RuntimeException('The account link has expired or has already been used. Request a fresh link.');
        $subject=$purpose==='verify'?'Confirm your Nettlebed Village Club email':'Reset your Nettlebed Village Club password';
        $body=($purpose==='verify'?'Confirm your email address using this link:':'Use this link to choose a new password:')."\n\n".$url."\n\nThis link expires one hour after it was requested. If you did not request it, you can ignore this email.";
    }elseif($row['channel']==='contact_email'){
        if(($parts['path']??'')!=='/admin/enquiries.php')throw new RuntimeException('Invalid enquiry notification link.');
        $reference=preg_replace('/[^A-Za-z0-9._:-]/','',(string)($payload['reference']??''));$subject='New club enquiry'.($reference?' '.$reference:'');
        $body="A new enquiry has been saved on the club website. Log in with an authorised committee account to view it:\n\n".$url."\n\nAttachments remain private on the website.";
    }elseif($row['channel']==='membership_email'){
        if(($parts['path']??'')!=='/members/membership.php')throw new RuntimeException('Invalid membership notification link.');
        $subject='Your Nettlebed Village Club membership renewal';$body="Your club membership is coming up for renewal. Log in to check your expiry date and renewal arrangements:\n\n".$url."\n\nMembership does not renew automatically. Please use only the payment details and reference on your own membership invoice.";
    }else throw new RuntimeException('Unsupported email delivery channel.');
    return ['to'=>$to,'subject'=>$subject,'body'=>$body];
}

function club_deliver_outbox_email(bool $deliver=false,int $limit=20,?callable $sender=null):array {
    $counts=['sent'=>0,'review'=>0,'disabled'=>0];
    if(!$deliver || !defined('MAIL_METHOD') || !in_array(MAIL_METHOD,['smtp','mail'],true)){$counts['disabled']=1;return $counts;}
    $sender??=fn($to,$subject,$body)=>send_email($to,$subject,$body);
    for($i=0;$i<max(0,min($limit,50));$i++){
        $row=club_membership_transaction(function(){
            $s=db()->query("SELECT * FROM club_outbox WHERE channel IN('member_email','contact_email','membership_email') AND status IN('pending','connection_required') AND available_at<=NOW() ORDER BY id LIMIT 1 FOR UPDATE");$row=$s->fetch();if(!$row)return null;
            db()->prepare("UPDATE club_outbox SET status='processing',attempts=attempts+1,last_error='Delivery claimed; review recipient mailbox before retrying an interrupted send.' WHERE id=?")->execute([$row['id']]);return $row;
        });
        if(!$row)break;
        try{$message=club_outbox_email_message($row);$sent=(bool)$sender($message['to'],$message['subject'],$message['body']);}
        catch(Throwable $e){$sent=false;}
        // A provider rejection/timeout can be ambiguous. Do not automatically resend failed or abandoned processing rows.
        if($sent){db()->prepare("UPDATE club_outbox SET status='sent',last_error=NULL WHERE id=? AND status='processing'")->execute([$row['id']]);$counts['sent']++;}
        else{db()->prepare("UPDATE club_outbox SET status='failed',last_error='Delivery was not confirmed. Review the mailbox and link validity before manually retrying.' WHERE id=? AND status='processing'")->execute([$row['id']]);$counts['review']++;}
    }
    return $counts;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{
        $s=db()->prepare("UPDATE club_memberships SET status='expired' WHERE status='active' AND expires_on<=?");$s->execute([date('Y-m-d')]);$expired=$s->rowCount();
        $reminders=club_queue_membership_reminders();$delivery=club_deliver_outbox_email(in_array('--deliver',$argv??[],true));
        echo json_encode(['expired_terms'=>$expired,'eligible_reminders'=>$reminders,'email'=>$delivery],JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $e){fwrite(STDERR,'Club maintenance could not complete. Check the private application log.'.PHP_EOL);error_log('Club maintenance: '.$e->getMessage());exit(1);}
}
