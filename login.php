<?php
require_once __DIR__.'/includes/bootstrap.php';
if(club_current_user())redirect('/account.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_verify();$result=club_attempt_login((string)($_POST['email']??''),(string)($_POST['password']??''));if($result['success'])redirect('/account.php');$error=$result['message'];}
seo_set(['title'=>'Member login']);require APP_ROOT.'/includes/header.php';
?>
<section class="section"><div class="container platform-grid"><div><p class="eyebrow">WELCOME BACK</p><h1>Your club, connected.</h1><p>Catch up with club news, discover what’s on and have your say.</p><p>New to the website? <a href="<?=e(site_url('/register.php'))?>">Create your account</a>.</p></div><form method="post" class="platform-panel">
<h2>Member login</h2><?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?><?php csrf_field();?>
<div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" maxlength="190" required value="<?=e($_POST['email']??'')?>"></div>
<div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required></div>
<button class="btn btn--primary" type="submit">Log in</button><p><a href="<?=e(site_url('/forgot-password.php'))?>">Forgot your password?</a></p><p><a href="<?=e(site_url('/admin/login.php'))?>">Committee administrator login</a></p></form></div></section>
<?php require APP_ROOT.'/includes/footer.php';?>
