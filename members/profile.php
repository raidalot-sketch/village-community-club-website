<?php
require_once __DIR__.'/../includes/bootstrap.php';$user=club_require_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_verify();$name=trim((string)($_POST['full_name']??''));$phone=trim((string)($_POST['phone']??''));$pass=(string)($_POST['new_password']??'');
 if(!$name || mb_strlen($name)>120 || mb_strlen($phone)>40)$error='Please enter your name and a phone number no longer than 40 characters.';
 elseif($pass!=='' && (strlen($pass)<12 || strlen($pass)>1024 || $pass!==($_POST['password_confirmation']??'')))$error='New passwords must match and contain at least 12 characters.';
 else {
 $s=db()->prepare('SELECT password_hash FROM club_users WHERE id=?');$s->execute([$user['id']]);$old=$s->fetchColumn();
 if($pass!=='' && !password_verify((string)($_POST['current_password']??''),$old))$error='Your current password is incorrect.';
 else {db()->prepare('UPDATE club_users SET full_name=?,phone=? WHERE id=?')->execute([$name,$phone,$user['id']]);if($pass!==''){$hash=password_hash($pass,PASSWORD_DEFAULT);db()->prepare('UPDATE club_users SET password_hash=? WHERE id=?')->execute([$hash,$user['id']]);$_SESSION['club_auth_sig']=hash('sha256',$hash);session_regenerate_id(true);}flash_success('Your profile has been saved.');redirect('/members/profile.php');}
 }
}
club_member_layout_start('My profile');?>
<form method="post" class="platform-panel"><?php csrf_field();?><?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?><h2>A few details about you</h2>
<div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="120" autocomplete="name" required value="<?=e($_POST['full_name']??$user['full_name'])?>"></div>
<div class="form-field"><label for="email">Email address</label><input id="email" type="email" readonly value="<?=e($user['email'])?>"><p>Contact the club to change your registered email address.</p></div>
<div class="form-field"><label for="phone">Phone number (optional)</label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?=e($_POST['phone']??$user['phone'])?>"></div>
<h2>Change password</h2><p>Leave these fields empty to keep your current password.</p>
<div class="form-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" maxlength="1024" autocomplete="current-password"></div>
<div class="form-field"><label for="new_password">New password (at least 12 characters)</label><input id="new_password" name="new_password" type="password" minlength="12" maxlength="1024" autocomplete="new-password"></div>
<div class="form-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="1024" autocomplete="new-password"></div>
<button class="btn btn--primary" type="submit">Save profile</button></form><?php club_member_layout_end();?>
