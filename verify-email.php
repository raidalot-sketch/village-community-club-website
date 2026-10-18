<?php
require_once __DIR__.'/includes/bootstrap.php';$token=(string)($_POST['token']??$_GET['token']??'');$message='Confirm your email to continue setting up your club account.';
header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_verify();$message=club_consume_token($token,'verify')?'Your email is verified. Committee approval and confirmed membership are needed before you can post or vote.':'This verification link has expired or has already been used. Please contact the club.';}
seo_set(['title'=>'Verify email','canonical'=>site_url('/verify-email.php')]);require APP_ROOT.'/includes/header.php';?>
<section class="section"><div class="container platform-panel"><h1>Verify your email</h1><p role="status"><?=e($message)?></p><?php if($_SERVER['REQUEST_METHOD']!=='POST'):?><form method="post"><?php csrf_field();?><input type="hidden" name="token" value="<?=e($token)?>"><button class="btn btn--primary">Confirm email</button></form><?php endif;?><p><a href="<?=e(site_url('/login.php'))?>">Member login</a></p></div></section><?php require APP_ROOT.'/includes/footer.php';?>
