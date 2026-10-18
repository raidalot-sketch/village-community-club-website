<?php
require_once __DIR__.'/../includes/bootstrap.php';$user=club_require_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_verify();try{if(!function_exists('club_request_membership'))throw new RuntimeException('Membership payments are being prepared. Please contact the club.');club_request_membership((int)$user['id']);flash_success('Your membership request has been recorded.');redirect('/members/membership.php');}catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'We could not record your request. Please try again.';}}
$membership=function_exists('club_membership_for_user')?club_membership_for_user((int)$user['id']):null;
club_member_layout_start('My membership');?>
<div class="platform-panel"><p class="eyebrow">A YEAR OF CLUB LIFE</p><h2><?=club_is_active_member()?'Your membership is active':'Become part of the club'?></h2><p>Twelve months of membership, with a reminder when it’s time to renew. One annual payment, with no automatic renewal.</p>
<?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?>
<?php if($membership):?><p>Status: <strong><?=e(ucwords(str_replace('_',' ',$membership['status'])))?></strong></p><?php if($membership['expires_on']):?><p>Valid until <?=e(date('j F Y',strtotime($membership['expires_on'])))?>.</p><?php endif;?><?php if($membership['payment_reference']):?><p>Your payment reference: <strong><?=e($membership['payment_reference'])?></strong></p><?php endif;?><?php endif;?>
<p>The annual fee and bank details are being confirmed with the committee. Payment instructions will appear here once they are ready. Please do not send a payment without confirmed instructions.</p>
<form method="post"><?php csrf_field();?><button class="btn btn--primary" type="submit"><?=club_is_active_member()?'Request renewal':'Request membership'?></button></form><p>Need help? <a href="<?=e(site_url('/contact.php?category=membership'))?>">Contact the club</a>.</p></div>
<div class="platform-grid"><section class="platform-panel"><h2>Share and discover</h2><p>Submit club news, suggest events and browse members-only activities.</p></section><section class="platform-panel"><h2>Have your say</h2><p>Take part in members-only polls once your membership is confirmed.</p></section></div>
<?php club_member_layout_end();?>
