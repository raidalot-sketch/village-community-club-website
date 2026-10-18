<?php
require_once __DIR__.'/includes/bootstrap.php';$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_verify();$email=strtolower(trim((string)($_POST['email']??'')));$ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
 if(login_attempts_remaining('reset:'.hash('sha256',$email),$ip)>0){record_login_attempt('reset:'.hash('sha256',$email),$ip,false);$s=db()->prepare("SELECT id FROM club_users WHERE email=? AND status<>'suspended'");$s->execute([$email]);if($id=$s->fetchColumn())club_queue_token((int)$id,'reset',$email);}
 $message='If that email has an account, a reset request has been recorded. Email delivery is being configured; contact the club for help.';
}
seo_set(['title'=>'Reset your password']);require APP_ROOT.'/includes/header.php';?>
<section class="section"><div class="container"><form method="post" class="platform-panel"><h1>Forgot your password?</h1><p>Enter the email you use for your club account.</p><?php if($message):?><p role="status"><?=e($message)?></p><?php endif;?><?php csrf_field();?><div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required></div><button class="btn btn--primary">Request password reset</button><p><a href="<?=e(site_url('/login.php'))?>">Back to login</a></p></form></div></section><?php require APP_ROOT.'/includes/footer.php';?>
