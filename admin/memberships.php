<?php
require_once __DIR__.'/../includes/bootstrap.php';$actor=club_require_capability('payments.manage');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_verify();try{
  $id=(int)($_POST['membership_id']??0);
  if(($_POST['action']??'')==='confirm'){if(empty($_POST['checked_bank']))throw new RuntimeException('Confirm that you have checked the club’s bank account before recording payment.');club_confirm_membership_payment($id,trim((string)($_POST['receipt_reference']??'')),trim((string)($_POST['note']??'')));flash_success('Invoice has recorded the bank receipt and the membership term is confirmed.');}
  else{$m=club_sync_membership($id);flash_success($m['status']==='active'?'Invoice confirms payment and membership is active.':'Invoice checked; payment is not yet confirmed.');}
  redirect('/admin/memberships.php');
 }catch(RuntimeException|InvalidArgumentException $e){$error=$e->getMessage();}
}
$q=trim((string)($_GET['q']??''));$s=db()->prepare('SELECT m.*,u.full_name,u.email FROM club_memberships m JOIN club_users u ON u.id=m.user_id WHERE u.full_name LIKE ? OR u.email LIKE ? OR m.payment_reference LIKE ? ORDER BY m.id DESC LIMIT 100');$s->execute(['%'.$q.'%','%'.$q.'%','%'.$q.'%']);$rows=$s->fetchAll();
club_member_layout_start('Membership payments');?>
<p>Only the treasurer can reconcile payments. Confirm a bank transfer after checking the club’s receiving account. Invoice records the receipt before website access is activated.</p>
<?php if($error):?><p class="platform-panel" role="alert"><?=e($error)?></p><?php endif;?>
<?php if(!club_membership_payment_ready()):?><div class="platform-panel"><h2>Payment setup is being prepared</h2><p>The fee, bank instructions and dedicated club Invoice connection must be configured before new invoices can be requested. No estimated prices or payment details are used.</p></div><?php endif;?>
<div class="platform-panel"><form method="get"><div class="form-field"><label for="q">Find a member or payment reference</label><input id="q" name="q" maxlength="190" value="<?=e($q)?>"></div><button class="btn btn--secondary">Search</button></form></div>
<?php if(!$rows):?><div class="platform-panel"><h2>No membership requests yet</h2><p>Membership requests and payment references will appear here when members start joining.</p></div><?php endif;?>
<?php foreach($rows as $m):?><section class="platform-panel"><div class="platform-heading"><div><h2><?=e($m['full_name'])?></h2><p><?=e($m['email'])?></p></div><span class="status-label"><?=e(ucwords(str_replace('_',' ',$m['status'])))?></span></div><p>Amount: <?=is_numeric($m['amount_minor'])?'£'.number_format($m['amount_minor']/100,2):'Unconfirmed'?> · Reference: <?=e($m['payment_reference']?:'Invoice not yet issued')?></p><?php if($m['expires_on']):?><p>Membership: <?=e($m['starts_on'])?> to <?=e($m['expires_on'])?>.</p><?php endif;?>
<?php if($m['invoice_public_id']):?><form method="post"><?php csrf_field();?><input type="hidden" name="membership_id" value="<?=(int)$m['id']?>"><input type="hidden" name="action" value="sync"><button class="btn btn--secondary">Check Invoice status</button></form><?php endif;?>
<?php if($m['status']==='awaiting_payment' && $m['invoice_public_id']):?>
<form method="post"><?php csrf_field();?><input type="hidden" name="membership_id" value="<?=(int)$m['id']?>"><input type="hidden" name="action" value="confirm"><div class="form-field"><label for="receipt-<?=$m['id']?>">Unique bank receipt reference</label><input id="receipt-<?=$m['id']?>" name="receipt_reference" maxlength="190" pattern="[A-Za-z0-9][A-Za-z0-9._:\-]*" required></div><div class="form-field"><label for="note-<?=$m['id']?>">Reconciliation note (optional)</label><textarea id="note-<?=$m['id']?>" name="note" maxlength="1000" rows="2"></textarea></div><p><label><input type="checkbox" name="checked_bank" value="1" required> I checked the bank account and this full payment has been received.</label></p><button class="btn btn--primary">Record receipt in Invoice</button><p>Use the same receipt reference and note if you need to retry after a connection failure.</p></form><?php endif;?></section><?php endforeach;?>
<?php club_member_layout_end();?>
