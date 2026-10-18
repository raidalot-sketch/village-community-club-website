<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';club_require_capability('settings.manage');$error='';
$keys=['membership_fee_minor','bank_details','enquiry_recipient','facebook_url','instagram_url'];$values=[];
foreach($keys as $key)$values[$key]=club_setting($key);
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();$values=array_intersect_key($_POST,array_flip($keys));
    try{club_save_integration_settings($values);flash_success('Club settings saved. No external services have been contacted.');redirect('/admin/club-settings.php');}catch(DomainException|InvalidArgumentException $e){$error=$e->getMessage();}
}
club_member_layout_start('Club settings');
?>
<p>Prepare the club’s details here. Connection credentials stay in private server configuration.</p>
<?php if($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif ?>
<form method="post" class="platform-panel"><?php csrf_field(); ?><h2>Membership and enquiries</h2>
<div class="form-field"><label for="fee">Confirmed annual membership fee (pence)</label><input id="fee" name="membership_fee_minor" type="number" min="1" max="1000000" value="<?= e($values['membership_fee_minor']??'') ?>"><small>Leave blank until Ed confirms the fee. Twelve-month membership; no automatic charge.</small></div>
<div class="form-field"><label for="bank">Confirmed bank-transfer instructions</label><textarea id="bank" name="bank_details" rows="5" maxlength="2000"><?= e($values['bank_details']??'') ?></textarea><small>Leave blank until the club confirms its receiving account. Never enter passwords or API credentials.</small></div>
<div class="form-field"><label for="email">Club enquiry email</label><input id="email" name="enquiry_recipient" type="email" required maxlength="190" value="<?= e($values['enquiry_recipient']??'') ?>"><small>All categories currently use this address. Enquiries remain saved if notification delivery is unavailable.</small></div>
<h2>Public social profiles</h2><div class="form-field"><label for="facebook">Facebook page</label><input id="facebook" type="url" name="facebook_url" value="<?= e($values['facebook_url']??'') ?>"></div><div class="form-field"><label for="instagram">Instagram profile (optional)</label><input id="instagram" type="url" name="instagram_url" value="<?= e($values['instagram_url']??'') ?>"></div><button class="btn btn--primary">Save club settings</button></form>
<section class="platform-panel"><h2>Service connections</h2><dl><?php foreach(club_integration_status() as $name=>$status): ?><dt><strong><?= e(ucfirst($name)) ?></strong></dt><dd><?= e($status) ?></dd><?php endforeach ?></dl><p>Invoice API address and client token must be configured privately by the website operator. They cannot be entered or read on this screen. Social publishing remains disabled until the club authorises its accounts and the connection is verified.</p><a href="<?= e(site_url('/social.php')) ?>">Open the social page</a></section>
<?php club_member_layout_end(); ?>
