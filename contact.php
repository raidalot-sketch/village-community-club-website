<?php
require_once __DIR__.'/includes/bootstrap.php';
/** Contact helpers stay here so public submission and the private inbox share exactly one access policy. */
function club_contact_can_handle(string $category):bool {
    if(club_can('enquiries.all')||($category==='membership'&&club_can('enquiries.membership')))return true;
    if(!club_can('enquiries.handle'))return false;
    $id=(int)(club_current_user()['id']??0);if(!$id)return false;
    $q=db()->prepare('SELECT 1 FROM club_enquiry_routes WHERE user_id=? AND category=?');$q->execute([$id,$category]);return (bool)$q->fetchColumn();
}
function club_contact_values(array $input):array {
    $values=[];foreach(['name'=>150,'email'=>190,'phone'=>40,'subject'=>190,'message'=>10000] as $key=>$max){$value=trim((string)($input[$key]??''));if(mb_strlen($value)>$max)throw new InvalidArgumentException(ucfirst($key).' is too long.');$values[$key]=$value;}
    if($values['name']==='')throw new InvalidArgumentException('Please enter your name.');
    if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$values['email']))throw new InvalidArgumentException('Please enter a valid email address.');
    if(mb_strlen($values['message'])<10)throw new InvalidArgumentException('Please enter a message of at least ten characters.');
    $values['category']=(string)($input['category']??'general');if(!in_array($values['category'],['general','membership','hall_hire'],true))throw new InvalidArgumentException('Choose an enquiry type.');
    if(empty($input['consent']))throw new InvalidArgumentException('Please confirm we may contact you about your enquiry.');
    if(trim((string)($input['website']??''))!=='')throw new InvalidArgumentException('Your enquiry could not be accepted. Please try again.');
    return $values;
}
function club_contact_save(array $input,array $files,string $token,string $ip):array {
    $v=club_contact_values($input);if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('This form has expired. Please refresh and try again.');
    $key='contact-submit-'.hash('sha256',$token);$pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT contact_save');
    try{
        // The unique outbox key also serialises concurrent retries before an enquiry is created.
        $pdo->prepare("INSERT INTO club_outbox(channel,event_key,payload,status) VALUES('contact_receipt',?,'{}','sent') ON DUPLICATE KEY UPDATE event_key=VALUES(event_key)")->execute([$key]);
        $q=$pdo->prepare('SELECT payload FROM club_outbox WHERE event_key=? FOR UPDATE');$q->execute([$key]);$receipt=json_decode($q->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
        if(!empty($receipt['id'])){if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT contact_save');return $receipt;}
        $rateKey='contact-rate-'.hash_hmac('sha256',$ip,APP_SECRET);
        $pdo->prepare("INSERT INTO club_outbox(channel,event_key,payload,status) VALUES('contact_rate',?,'{}','sent') ON DUPLICATE KEY UPDATE event_key=VALUES(event_key)")->execute([$rateKey]);
        $q=$pdo->prepare('SELECT payload FROM club_outbox WHERE event_key=? FOR UPDATE');$q->execute([$rateKey]);$rate=json_decode($q->fetchColumn(),true);
        if(($rate['reset_at']??0)<=time())$rate=['count'=>0,'reset_at'=>time()+900];
        if($rate['count']>=5)throw new RuntimeException('You have sent several enquiries recently. Please wait fifteen minutes before trying again.');
        $rate['count']++;$pdo->prepare('UPDATE club_outbox SET payload=? WHERE event_key=?')->execute([json_encode($rate,JSON_THROW_ON_ERROR),$rateKey]);
        $reference='NVC-'.strtoupper(substr(hash_hmac('sha256',$token,APP_SECRET),0,12));
        $pdo->prepare("INSERT INTO contact_enquiries(name,email,phone,subject,message,consent,status,ip_address,category,user_id,reference) VALUES(?,?,?,?,?,1,'new',?,?,?,?)")->execute([$v['name'],$v['email'],$v['phone'],$v['subject'],$v['message'],substr($ip,0,45),$v['category'],club_current_user()['id']??null,$reference]);$id=(int)$pdo->lastInsertId();
        club_store_uploads($files,'contact',$id,club_current_user()['id']??null);
        $receipt=['id'=>$id,'reference'=>$reference,'notification'=>'connection_required'];
        $destination=get_setting('enquiry_email_'.$v['category'],club_setting('enquiry_recipient','thenettlebedclub@gmail.com'));
        if(!filter_var($destination,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$destination))$destination='thenettlebedclub@gmail.com';
        $notice=['to'=>$destination,'subject'=>'New club enquiry '.$reference,'enquiry_id'=>$id,'reference'=>$reference,'category'=>$v['category'],'url'=>site_url('/admin/enquiries.php?id='.$id)];
        try{
            if(function_exists('club_queue_outbox'))club_queue_outbox('contact_email','contact-notification-'.$id,$notice,'connection_required');
            else $pdo->prepare("INSERT INTO club_outbox(channel,event_key,payload,status,last_error) VALUES('contact_email',?,?,'connection_required','Email delivery has not been connected')")->execute(['contact-notification-'.$id,json_encode($notice,JSON_THROW_ON_ERROR)]);
        }catch(Throwable $e){$receipt['notification']='failed';error_log('Contact notification queue failed for enquiry '.$id);try{$pdo->prepare("INSERT INTO club_outbox(channel,event_key,payload,status,last_error) VALUES('contact_email',?,?,'failed','Notification could not be queued; committee attention required') ON DUPLICATE KEY UPDATE status='failed',last_error='Notification could not be queued; committee attention required'")->execute(['contact-notification-'.$id,json_encode($notice,JSON_THROW_ON_ERROR)]);}catch(Throwable $queueFailure){error_log('Contact notification failure record unavailable for enquiry '.$id);}}
        $pdo->prepare('UPDATE club_outbox SET payload=? WHERE event_key=?')->execute([json_encode($receipt,JSON_THROW_ON_ERROR),$key]);
        if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT contact_save');return $receipt;
    }catch(Throwable $e){if($pdo->inTransaction()){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT contact_save');$pdo->exec('RELEASE SAVEPOINT contact_save');}}throw $e;}
}
function club_contact_resolve(int $id):void {
    $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT contact_resolve');
    try{
        $q=$pdo->prepare('SELECT category FROM contact_enquiries WHERE id=? FOR UPDATE');$q->execute([$id]);$category=$q->fetchColumn();if(!$category||!club_contact_can_handle($category))throw new RuntimeException('This enquiry is unavailable.');
        $pdo->prepare("UPDATE contact_enquiries SET status='resolved' WHERE id=?")->execute([$id]);club_content_audit('enquiry.resolved','contact',$id);
        if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT contact_resolve');
    }catch(Throwable $e){if($pdo->inTransaction()){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT contact_resolve');$pdo->exec('RELEASE SAVEPOINT contact_resolve');}}throw $e;}
}
if(defined('CLUB_CONTACT_LIBRARY_ONLY'))return;
$categories=['general'=>'General enquiry','membership'=>'Membership & renewals','hall_hire'=>'Hall hire'];
$requestedType=is_string($_GET['type']??null)?$_GET['type']:'general';$requestedSubject=is_string($_GET['subject']??null)?$_GET['subject']:'';
$values=['name'=>'','email'=>'','phone'=>'','subject'=>$requestedSubject,'message'=>'','category'=>array_key_exists($requestedType,$categories)?$requestedType:'general','consent'=>false];$error='';
$_SESSION['contact_forms']??=[];$now=time();foreach($_SESSION['contact_forms'] as $k=>$expires)if($expires<$now)unset($_SESSION['contact_forms'][$k]);
$token=(string)($_POST['submission_token']??'');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    csrf_verify();$values=array_merge($values,array_intersect_key($_POST,$values));
    try{if(!isset($_SESSION['contact_forms'][$token]))throw new InvalidArgumentException('This form has expired. Refresh the page and try again.');$receipt=club_contact_save($_POST,$_FILES['attachments']??[],$token,$_SERVER['REMOTE_ADDR']??'unknown');
        flash_success('Thank you — your enquiry has been saved. Your reference is '.$receipt['reference'].'. The committee can read it in their website inbox.');redirect('/contact.php');
    }catch(Throwable $e){if($e instanceof PDOException){error_log('Contact save failed: '.$e->getMessage());$error='We could not save your enquiry just now. Please try again shortly.';}else $error=$e->getMessage();}
}
if(!isset($_SESSION['contact_forms'][$token])){$token=bin2hex(random_bytes(32));$_SESSION['contact_forms'][$token]=time()+7200;if(count($_SESSION['contact_forms'])>20)array_shift($_SESSION['contact_forms']);}
seo_set(['title'=>'Contact the club','description'=>'Enquire about membership, hall hire or life at Nettlebed Village Club.']);require __DIR__.'/includes/header.php';
?>
<section class="section"><div class="container"><p class="eyebrow">LET’S TALK</p><h1>We’d love to hear from you.</h1><p>Joining the club, planning a celebration, or just a quick question? Get in touch.</p><?php flash_render(); ?><div class="two-col contact-layout" style="align-items:start"><div class="platform-panel"><h2>Send us a message</h2><?php if($error): ?><div class="alert alert--error" role="alert"><p><?= e($error) ?></p><p>Your message has not been submitted. Check the details below and select any attachments again.</p></div><?php endif ?>
<form method="post" enctype="multipart/form-data" action="<?= e(site_url('/contact.php')) ?>"><?php csrf_field(); ?><input type="hidden" name="submission_token" value="<?= e($token) ?>"><div class="form-honeypot" aria-hidden="true"><label for="website">Leave this field blank</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="platform-grid"><div class="form-field"><label for="name">Your name *</label><input id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="150" autocomplete="name"></div><div class="form-field"><label for="email">Email address *</label><input id="email" name="email" type="email" value="<?= e($values['email']) ?>" required maxlength="190" autocomplete="email"></div></div>
<div class="platform-grid"><div class="form-field"><label for="phone">Phone number (optional)</label><input id="phone" name="phone" type="tel" value="<?= e($values['phone']) ?>" maxlength="40" autocomplete="tel"></div><div class="form-field"><label for="category">What can we help with? *</label><select id="category" name="category"><?php foreach($categories as $key=>$label): ?><option value="<?= $key ?>"<?= $values['category']===$key?' selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div></div>
<div class="form-field"><label for="subject">Subject (optional)</label><input id="subject" name="subject" value="<?= e($values['subject']) ?>" maxlength="190"></div><div class="form-field"><label for="message">Your message *</label><textarea id="message" name="message" rows="7" required minlength="10" maxlength="10000"><?= e($values['message']) ?></textarea></div>
<div class="form-field"><label for="attachments">Add photographs or documents (optional)</label><input id="attachments" type="file" name="attachments[]" multiple data-club-upload accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.docx,.xlsx"><small>Up to three files, 30 MB each. Photos are previewed and reduced automatically. PDF, Word (.docx) and Excel (.xlsx) documents remain intact. Please avoid sending sensitive documents.</small></div>
<div class="form-checkbox"><input id="consent" type="checkbox" name="consent" value="1" required<?= !empty($values['consent'])?' checked':'' ?>><label for="consent">I’m happy for the club to contact me about this enquiry. <a href="<?= e(site_url('/privacy-policy.php')) ?>">How we handle your information</a>.</label></div><button type="submit" class="btn btn--primary btn--block">Send enquiry</button><p class="text-muted">Your message and attachments are kept in the club’s private inbox for authorised committee members.</p>
</form></div><aside><div class="platform-panel"><p class="eyebrow">FIND YOUR WAY HERE</p><h2>Nettlebed Village Club</h2><address><?= nl2br(e(club_address())) ?></address><?php if(get_setting('contact_phone')): ?><p><a href="tel:<?= e(preg_replace('/[^+0-9]/','',get_setting('contact_phone'))) ?>"><?= e(get_setting('contact_phone')) ?></a></p><?php endif ?><p><a href="mailto:thenettlebedclub@gmail.com">thenettlebedclub@gmail.com</a></p><h3>Opening hours</h3><p><?= nl2br(e(get_setting('opening_hours','Contact the club for current opening hours.'))) ?></p><a href="<?= e(get_setting('social_facebook','https://www.facebook.com/NettlebedVillageClub')) ?>" target="_blank" rel="noopener noreferrer">Find us on Facebook</a></div><img src="<?= e(asset_url('images/club-building.jpg')) ?>" alt="Nettlebed Village Club" loading="lazy" style="border-radius:14px;width:100%;margin-top:24px"></aside></div></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
