<?php
require_once __DIR__.'/includes/bootstrap.php';
if(club_current_user())redirect('/account.php');
$error='';$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_verify();$name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$pass=(string)($_POST['password']??'');$ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
 if(login_attempts_remaining('register:'.hash('sha256',$email),$ip)<=0)$error='Too many attempts. Please try again in 15 minutes.';
 elseif(!$name || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($pass)<12 || strlen($pass)>1024 || $pass!==($_POST['password_confirmation']??'') || empty($_POST['privacy']))$error='Enter your name, a valid email and matching passwords of at least 12 characters. Please acknowledge the privacy notice.';
 else { record_login_attempt('register:'.hash('sha256',$email),$ip,false);db()->beginTransaction();try{
 $s=db()->prepare('SELECT id FROM club_users WHERE email=?');$s->execute([$email]);
 if(!$s->fetchColumn()){db()->prepare("INSERT INTO club_users(full_name,email,password_hash,status) VALUES(?,?,?,'pending')")->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();db()->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'member')")->execute([$id]);club_queue_token($id,'verify',$email);}
 db()->commit();$done=true;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('Member registration failed: '.$e->getMessage());$error='We could not save your account. Please try again.';}}
}
seo_set(['title'=>'Join the club website']);require APP_ROOT.'/includes/header.php';?>
<section class="section"><div class="container platform-grid"><div><p class="eyebrow">BELONG TO SOMETHING LOCAL</p><h1>Welcome to your village club.</h1><p>Create your website account to manage your membership and join in with club life.</p><p>Accounts need email verification and committee approval. Paid membership gives access to member posts, events and voting.</p></div><div class="platform-panel">
<?php if($done):?><h2>Registration received</h2><p>If this email is new, your account has been saved for committee approval. Email delivery is being configured; please contact the club if you need help verifying your account.</p><a class="btn btn--primary" href="<?=e(site_url('/login.php'))?>">Go to login</a><?php else:?>
<form method="post"><h2>Create your account</h2><?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?><?php csrf_field();?>
<div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" maxlength="120" required value="<?=e($_POST['full_name']??'')?>"></div>
<div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?=e($_POST['email']??'')?>"></div>
<div class="form-field"><label for="password">Password (at least 12 characters)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="1024" required></div>
<div class="form-field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="1024" required></div>
<p><label><input type="checkbox" name="privacy" required> I have read the <a href="<?=e(site_url('/privacy-policy.php'))?>">privacy notice</a>.</label></p><button class="btn btn--primary" type="submit">Create my account</button><p>Already registered? <a href="<?=e(site_url('/login.php'))?>">Log in</a>.</p></form><?php endif;?></div></div></section><?php require APP_ROOT.'/includes/footer.php';?>
