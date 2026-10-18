<?php
require_once __DIR__.'/../includes/bootstrap.php';$user=club_require_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_verify();try{
  if(($_POST['action']??'')==='sync'){$result=club_sync_membership((int)($_POST['membership_id']??0));flash_success($result['status']==='active'?'Invoice confirms your membership payment. Your membership is active.':'Invoice status checked. Your membership is still awaiting confirmed payment.');}
  else{club_request_membership((int)$user['id']);flash_success('Your membership invoice is ready. Please use the payment reference shown below.');}
  redirect('/members/membership.php');
 }catch(RuntimeException|InvalidArgumentException $e){$error=$e->getMessage();}
}
$membership=club_membership_for_user((int)$user['id']);$pending=club_pending_membership_for_user((int)$user['id']);$state=$pending?club_membership_invoice_state((int)$pending['id']):null;$remote=null;
if(!empty($state['response'])){try{$remote=club_validate_invoice_response($state['response'],(int)$pending['amount_minor'],$pending['invoice_public_id']);}catch(RuntimeException $e){$remote=null;}}
$ready=club_membership_payment_ready();$fee=filter_var(club_setting('membership_fee_minor'),FILTER_VALIDATE_INT);
club_member_layout_start('My membership');?>
<?php if($error):?><div class="platform-panel"><p role="alert"><?=e($error)?></p></div><?php endif;?>
<div class="platform-panel"><p class="eyebrow">A YEAR OF CLUB LIFE</p><h2><?=club_is_active_member()?'You’re part of the club':'Become part of the club'?></h2><p>Twelve months of membership, one annual payment and a reminder when it’s time to renew. No automatic renewal.</p>
<?php if($membership && $membership['expires_on']):?><p><?=club_is_active_member()?'Current membership':'Last membership'?> valid until <strong><?=e(date('j F Y',strtotime($membership['expires_on'])))?></strong>.</p><?php endif;?>
<?php if($fee!==false && $fee>0):?><p>Confirmed annual fee: <strong>£<?=number_format($fee/100,2)?></strong>.</p><?php endif;?>
<?php if(!$ready):?><div class="notice"><p>The committee is confirming payment arrangements. Online invoice requests will open when the annual fee, bank instructions and Invoice connection are ready. Please contact the club for membership help in the meantime.</p></div><?php endif;?>
<?php if($pending):?><p><span class="status-label">Awaiting payment confirmation</span></p><?php if($pending['payment_reference']):?><p>Your payment reference: <strong><?=e($pending['payment_reference'])?></strong></p><?php endif;?>
<?php if($remote):?><p>Amount: <strong>£<?=number_format($pending['amount_minor']/100,2)?></strong></p><a class="btn btn--primary" href="<?=e($remote['hosted_url'])?>" rel="noopener noreferrer" target="_blank">View your secure invoice</a><p>Use the bank details on your invoice and include its payment reference. Bank transfers need confirmation before membership becomes active.</p><form method="post"><?php csrf_field();?><input type="hidden" name="action" value="sync"><input type="hidden" name="membership_id" value="<?=(int)$pending['id']?>"><button class="btn btn--secondary">Check payment status</button></form>
<?php elseif($ready):?><p>Your invoice request is saved. Try again to finish creating the same invoice.</p><form method="post"><?php csrf_field();?><button class="btn btn--primary">Retry invoice request</button></form><?php endif;?>
<?php elseif($ready):?><form method="post"><?php csrf_field();?><button class="btn btn--primary"><?=club_is_active_member()?'Request renewal invoice':'Request membership invoice'?></button></form><?php endif;?>
<p><a href="<?=e(site_url('/contact.php?type=membership'))?>">Contact the club about membership</a></p></div>
<div class="platform-grid"><section class="platform-panel"><h2>Share and discover</h2><p>Submit club news, suggest events and browse members-only activities.</p></section><section class="platform-panel"><h2>Have your say</h2><p>Take part in members-only polls once your membership is confirmed.</p></section></div>
<?php club_member_layout_end();?>
