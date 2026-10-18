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
<section class="platform-panel"><h2>Automatic social sharing</h2><p>When the committee approves a post, it is queued for Facebook and Instagram. Text-only posts receive a club-branded image for Instagram. Changes approved before delivery update the queued announcement; editing a shared post does not publish it again.</p><dl><?php foreach(club_integration_status() as $name=>$status): ?><dt><strong><?= e(ucfirst($name)) ?></strong></dt><dd><?= e($status) ?></dd><?php endforeach ?></dl><p>To start sharing, the club must authorise its Facebook Page and linked professional Instagram account. Until that connection is ready, approved posts stay on the website with their sharing status shown here.</p><?php if(defined('SITE_PREVIEW')&&SITE_PREVIEW): ?><p>Publishing is paused on this private preview.</p><?php endif ?><p>Account credentials are stored privately by the website operator.</p><a href="<?= e(site_url('/social.php')) ?>">Open the social page</a></section>
<section class="platform-panel"><h2>Post sharing status</h2>
<?php $deliveries=db()->query("SELECT o.channel,o.status,o.remote_id,o.created_at,p.title FROM club_outbox o JOIN club_posts p ON o.event_key=CONCAT('approved-',o.channel,'-post-',p.id) WHERE o.channel IN('facebook','instagram') ORDER BY o.id DESC LIMIT 30")->fetchAll(); ?>
<?php if(!$deliveries): ?><p>No posts are waiting to be shared. Approving a new post will add Facebook and Instagram here.</p><?php endif ?>
<?php foreach($deliveries as $delivery): ?><article><h3><?= e($delivery['title']) ?></h3><p><?= e(ucfirst($delivery['channel'])) ?> · <strong><?= e(club_social_status_label($delivery['status'])) ?></strong><?php if($delivery['remote_id']): ?> · Reference <?= e($delivery['remote_id']) ?><?php endif ?></p><?php if($delivery['status']==='failed'): ?><p>The website post is still published. An administrator needs to check this delivery before it is sent again.</p><?php endif ?></article><?php endforeach ?>
</section>
<?php club_member_layout_end(); ?>
