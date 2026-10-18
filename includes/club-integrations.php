<?php
/** Non-secret club configuration. Credentials belong only in private server configuration. */
function club_setting(string $key, string $default = ''): string
{
    $defaults = ['membership_fee_minor'=>'','bank_details'=>'','enquiry_recipient'=>'thenettlebedclub@gmail.com','facebook_url'=>'https://www.facebook.com/NettlebedVillageClub','instagram_url'=>''];
    if (preg_match('/token|secret|password|api_key/i',$key)) return $default;
    $q=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$q->execute([$key]);
    $value=$q->fetchColumn();return $value===false?($defaults[$key]??$default):(string)$value;
}

/** At-least-once producers share a unique event key; retries preserve the original payload/status. */
function club_queue_outbox(string $channel,string $eventKey,array $payload,string $status='pending'):void
{
    if(!preg_match('/^[a-z][a-z0-9_]{0,29}$/',$channel)||$eventKey===''||strlen($eventKey)>190)throw new InvalidArgumentException('Invalid delivery channel or event key.');
    if(!in_array($status,['pending','processing','sent','failed','connection_required'],true))throw new InvalidArgumentException('Invalid delivery status.');
    db()->prepare('INSERT INTO club_outbox(channel,event_key,payload,status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=id')->execute([$channel,$eventKey,json_encode($payload,JSON_THROW_ON_ERROR),$status]);
}

function club_integration_status():array
{
    return [
        'invoice'=>defined('CLUB_INVOICE_API_BASE') && defined('CLUB_INVOICE_CLIENT_TOKEN') && CLUB_INVOICE_API_BASE!=='' && CLUB_INVOICE_CLIENT_TOKEN!=='' ? 'Configuration present — connection not verified' : 'Connection required',
        'facebook'=>'Connection required — automatic publishing is disabled',
        'instagram'=>'Connection required — automatic publishing is disabled',
        'email'=>'Delivery is captured until an authorised mail connection is enabled',
    ];
}

function club_social_url(string $channel,string $url):string
{
    if($url==='')return '';
    $parts=parse_url($url);$hosts=$channel==='facebook'?['facebook.com','www.facebook.com']:['instagram.com','www.instagram.com'];
    if(!$parts||($parts['scheme']??'')!=='https'||!in_array(strtolower($parts['host']??''),$hosts,true)||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||strlen($url)>500)throw new InvalidArgumentException('Enter a secure '.$channel.' profile address.');
    return $url;
}

function club_save_integration_settings(array $input):void
{
    if(!club_can('settings.manage'))throw new DomainException('Administrator permission is required.');
    $fee=trim((string)($input['membership_fee_minor']??''));
    if($fee!==''&&(!ctype_digit($fee)||(int)$fee<1||(int)$fee>1000000))throw new InvalidArgumentException('Enter the confirmed annual fee in pence, or leave it blank.');
    $recipient=trim((string)($input['enquiry_recipient']??''));
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||strlen($recipient)>190)throw new InvalidArgumentException('Enter a valid club enquiry email.');
    $bank=trim((string)($input['bank_details']??''));if(mb_strlen($bank)>2000)throw new InvalidArgumentException('Bank instructions must be no longer than 2000 characters.');
    $values=['membership_fee_minor'=>$fee,'bank_details'=>$bank,'enquiry_recipient'=>$recipient,
        'facebook_url'=>club_social_url('facebook',trim((string)($input['facebook_url']??''))),
        'instagram_url'=>club_social_url('instagram',trim((string)($input['instagram_url']??'')))];
    $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT integration_settings');
    try{
        foreach($values as $key=>$value)set_setting($key,$value);
        club_queue_outbox('audit','club-settings-'.bin2hex(random_bytes(16)),['action'=>'club.settings.updated','keys'=>array_keys($values),'actor_user_id'=>club_current_user()['id']??null,'legacy_admin_id'=>$_SESSION['admin_id']??null],'sent');
        if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT integration_settings');
    }catch(Throwable $e){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT integration_settings');$pdo->exec('RELEASE SAVEPOINT integration_settings');}throw $e;}
}

/** This prepares an explicit publisher request only. No network request or claim of publication. */
function club_social_queue(string $kind,int $id,array $channels):void
{
    if(!club_can('content.publish'))throw new DomainException('Publishing permission is required.');
    if(!$channels || array_filter($channels,fn($v)=>!is_string($v)) || array_diff($channels,['facebook','instagram']))throw new InvalidArgumentException('Choose Facebook or Instagram.');
    $table=match($kind){'post'=>'club_posts','event'=>'events',default=>throw new InvalidArgumentException('Only public posts or events can be shared.')};
    $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT social_request');
    try{
        $q=$pdo->prepare("SELECT * FROM $table WHERE id=? AND status='published'".($kind==='event'?" AND visibility='public' AND deleted_at IS NULL":'').' FOR UPDATE');$q->execute([$id]);$record=$q->fetch();
        if(!$record)throw new DomainException('Only published public content can be selected for social sharing.');
        $body=$record[$kind==='post'?'content':'description']??'';
        $payload=['kind'=>$kind,'id'=>$id,'title'=>$record['title'],'text'=>html_entity_decode(strip_tags($body),ENT_QUOTES,'UTF-8'),
            'url'=>site_url(($kind==='post'?'/post.php?slug=':'/event-detail.php?slug=').rawurlencode($record['slug']))];
        $q=$pdo->prepare("SELECT id FROM club_attachments WHERE owner_type=? AND owner_id=? AND mime LIKE 'image/%' ORDER BY id LIMIT 1");$q->execute([$kind,$id]);$imageId=$q->fetchColumn();
        $payload['image_attachment_id']=$imageId?(int)$imageId:null;
        if(!$imageId && empty($record['featured_image']) && in_array('instagram',$channels,true))throw new DomainException('Instagram needs an approved photograph or poster. Add one to the website item and have it approved first.');
        if(!empty($record['featured_image']))$payload['legacy_image']=$record['featured_image'];
        $fingerprint=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        foreach(array_unique($channels) as $channel)club_queue_outbox($channel,'social-'.$channel.'-'.$kind.'-'.$id.'-'.$fingerprint,$payload,'connection_required');
        if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT social_request');
    }catch(Throwable $e){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT social_request');$pdo->exec('RELEASE SAVEPOINT social_request');}throw $e;}
}
