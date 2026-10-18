<?php
/** Separate member identities and explicit capabilities; never trust browser-supplied roles. */
function club_current_user(): ?array {
    if (empty($_SESSION['club_user_id'])) return null;
    $s=db()->prepare('SELECT * FROM club_users WHERE id=?'); $s->execute([(int)$_SESSION['club_user_id']]); $u=$s->fetch();
    if (!$u || $u['status']==='suspended' || (isset($_SESSION['club_auth_sig']) && !hash_equals($_SESSION['club_auth_sig'],hash('sha256',$u['password_hash'])))) { unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']); return null; }
    unset($u['password_hash']); return $u;
}
function club_user_has_role(int $id,string $role):bool { $s=db()->prepare('SELECT 1 FROM club_user_roles WHERE user_id=? AND role=?'); $s->execute([$id,$role]); return (bool)$s->fetchColumn(); }
function club_is_active_member(?int $userId=null):bool {
    $id=$userId??(int)(club_current_user()['id']??0); if (!$id) return false;
    $s=db()->prepare("SELECT 1 FROM club_users u JOIN club_memberships m ON m.user_id=u.id WHERE u.id=? AND u.status='active' AND u.email_verified_at IS NOT NULL AND m.status='active' AND m.starts_on<=? AND m.expires_on>? LIMIT 1"); $s->execute([$id,date('Y-m-d'),date('Y-m-d')]); return (bool)$s->fetchColumn();
}
function club_can(string $capability):bool {
    $u=club_current_user();
    $legacy=is_administrator();
    if (in_array($capability,['content.submit','polls.vote'],true)) return $u && club_is_active_member((int)$u['id']);
    $roles=['content.publish'=>['publisher','administrator'],'users.manage'=>['administrator'],'settings.manage'=>['administrator'],'payments.manage'=>['treasurer'],'enquiries.all'=>['administrator'],'enquiries.membership'=>['treasurer','administrator'],'enquiries.handle'=>['administrator','treasurer']];
    if (!isset($roles[$capability])) return false;
    if ($legacy && in_array($capability,['content.publish','users.manage','settings.manage','enquiries.all','enquiries.handle','enquiries.membership'],true)) return true;
    if (!$u || $u['status']!=='active' || !$u['email_verified_at']) return false;
    foreach ($roles[$capability] as $role) if (club_user_has_role((int)$u['id'],$role)) return true;
    if ($capability==='enquiries.handle') { $s=db()->prepare('SELECT 1 FROM club_enquiry_routes WHERE user_id=? LIMIT 1'); $s->execute([$u['id']]); return (bool)$s->fetchColumn(); }
    return false;
}
function club_require_login():array { $u=club_current_user(); if (!$u) redirect('/login.php'); header('Cache-Control: no-store'); return $u; }
function club_require_capability(string $capability):array {
    if (!club_can($capability)) { if (!club_current_user() && !is_logged_in()) redirect('/login.php'); http_response_code(403); exit('This account does not have permission for this action.'); }
    header('Cache-Control: no-store'); return club_current_user()??['id'=>0,'full_name'=>current_admin()['full_name'],'legacy_admin'=>true];
}
function club_attempt_login(string $email,string $password):array {
    $email=strtolower(trim($email)); $key='member:'.hash('sha256',$email); $ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
    if (login_attempts_remaining($key,$ip)<=0) return ['success'=>false,'message'=>'Too many attempts. Please wait 15 minutes and try again.'];
    $s=db()->prepare('SELECT * FROM club_users WHERE email=?'); $s->execute([$email]); $u=$s->fetch();
    if (!$u || !password_verify($password,$u['password_hash']) || $u['status']==='suspended') { record_login_attempt($key,$ip,false); return ['success'=>false,'message'=>'The email or password is incorrect, or this account is unavailable.']; }
    if (password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) { $u['password_hash']=password_hash($password,PASSWORD_DEFAULT); db()->prepare('UPDATE club_users SET password_hash=? WHERE id=?')->execute([$u['password_hash'],$u['id']]); }
    record_login_attempt($key,$ip,true); session_regenerate_id(true); $_SESSION['club_user_id']=(int)$u['id']; $_SESSION['club_auth_sig']=hash('sha256',$u['password_hash']); db()->prepare('UPDATE club_users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); return ['success'=>true,'message'=>'Welcome back.'];
}
function club_queue_token(int $id,string $purpose,string $email):void {
    $token=bin2hex(random_bytes(32));
    db()->prepare('UPDATE club_password_tokens SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL')->execute([$id,$purpose]);
    db()->prepare('INSERT INTO club_password_tokens(token_hash,user_id,purpose,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([hash('sha256',$token),$id,$purpose]);
    // Captured only. Delivery must be configured explicitly; no external email is sent here.
    $route=$purpose==='verify'?'/verify-email.php':'/reset-password.php';
    db()->prepare("INSERT INTO club_outbox(channel,event_key,payload,status,last_error) VALUES('member_email',?,?,'connection_required','Email delivery is not configured')")->execute(['member-'.$purpose.'-'.bin2hex(random_bytes(12)),json_encode(['to'=>$email,'purpose'=>$purpose,'url'=>site_url($route.'?token='.$token)],JSON_THROW_ON_ERROR)]);
}
function club_consume_token(string $token,string $purpose,?string $password=null):bool {
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) return false;
    if ($purpose==='reset' && ($password===null || strlen($password)<12 || strlen($password)>1024)) return false;
    db()->beginTransaction();
    try { $s=db()->prepare('SELECT * FROM club_password_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>NOW() FOR UPDATE'); $s->execute([hash('sha256',$token),$purpose]); $row=$s->fetch(); if (!$row) {db()->rollBack(); return false;}
        if ($purpose==='verify') db()->prepare('UPDATE club_users SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?')->execute([$row['user_id']]);
        else db()->prepare('UPDATE club_users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$row['user_id']]);
        db()->prepare('UPDATE club_password_tokens SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL')->execute([$row['user_id'],$purpose]); db()->commit(); return true;
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
}
function club_member_layout_start(string $title):void {
    seo_set(['title'=>$title]); require APP_ROOT.'/includes/header.php';
    echo '<section class="section"><div class="container member-layout"><aside class="member-sidebar"><p class="eyebrow">YOUR CLUB</p><h2>Members’ area</h2><nav aria-label="Member navigation">';
    $links=['/account.php'=>'Overview','/members/profile.php'=>'My profile','/members/membership.php'=>'My membership','/members/posts.php'=>'My posts','/calendar.php'=>'Club calendar','/polls.php'=>'Member polls'];
    if(club_can('content.publish'))$links['/admin/submissions.php']='Review submissions';
    if(club_can('users.manage'))$links['/admin/club-users.php']='People & roles';
    if(club_can('payments.manage'))$links['/admin/memberships.php']='Membership payments';
    if(club_can('enquiries.handle'))$links['/admin/enquiries.php']='Enquiries';
    if(club_can('settings.manage'))$links['/admin/club-settings.php']='Club settings';
    foreach($links as $path=>$label)echo '<a href="'.e(site_url($path)).'">'.e($label).'</a>';
    echo '</nav><form method="post" action="'.e(site_url('/logout.php')).'">'; csrf_field(); echo '<button class="btn btn--secondary" type="submit">Log out</button></form></aside><div class="member-content"><h1>'.e($title).'</h1>';
}
function club_member_layout_end():void { echo '</div></div></section><script src="'.e(asset_url('js/member-auth.js')).'" defer></script>'; require APP_ROOT.'/includes/footer.php'; }
