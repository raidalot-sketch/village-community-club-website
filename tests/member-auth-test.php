<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';
if(!defined('DB_NAME') || !str_contains(DB_NAME,'preview'))throw new RuntimeException('Identity tests require an isolated preview database.');
function identity_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL: '.$label);}
$pdo=db();$pdo->beginTransaction();$count=0;
try{
 $password='identity-test-'.bin2hex(random_bytes(20));$email='identity-'.bin2hex(random_bytes(8)).'@example.invalid';$_SERVER['REMOTE_ADDR']='198.18.'.random_int(0,254).'.'.random_int(1,254);
 $pdo->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES('Identity test',?,?,'active',NOW())")->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
 $_SESSION['club_user_id']=$id;
 identity_check(club_current_user()['email']===$email,'own profile identity');$count++;
 identity_check(!array_key_exists('password_hash',club_current_user()),'password hash not exposed to templates');$count++;
 identity_check(!club_can('users.manage') && !club_can('content.publish') && !club_can('payments.manage'),'ordinary account cannot escalate');$count++;
 identity_check(!club_can('content.submit') && !club_can('polls.vote'),'unpaid account cannot submit or vote');$count++;
 $pdo->prepare("INSERT INTO club_memberships(user_id,status,starts_on,expires_on,external_ref) VALUES(?,'active',DATE_SUB(CURDATE(),INTERVAL 1 DAY),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),?)")->execute([$id,'test-'.bin2hex(random_bytes(10))]);
 identity_check(club_can('content.submit') && club_can('polls.vote'),'paid verified active member eligible');$count++;
 $pdo->prepare('UPDATE club_users SET email_verified_at=NULL WHERE id=?')->execute([$id]);identity_check(!club_can('content.submit') && !club_can('polls.vote'),'unverified account denied');$count++;
 $pdo->prepare("UPDATE club_users SET email_verified_at=NOW(),status='pending' WHERE id=?")->execute([$id]);identity_check(!club_is_active_member(),'unapproved account denied');$count++;
 $pdo->prepare("UPDATE club_users SET status='active' WHERE id=?")->execute([$id]);$pdo->prepare('UPDATE club_memberships SET expires_on=CURDATE() WHERE user_id=?')->execute([$id]);identity_check(!club_is_active_member(),'membership expires at term boundary');$count++;
 $pdo->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'publisher')")->execute([$id]);identity_check(club_can('content.publish') && !club_can('users.manage') && !club_can('enquiries.handle'),'publisher has no user/inbox access');$count++;
 $pdo->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'administrator')")->execute([$id]);identity_check(club_can('users.manage') && !club_can('payments.manage'),'administrator cannot reconcile without treasurer');$count++;
 $pdo->prepare("INSERT INTO club_user_roles(user_id,role) VALUES(?,'treasurer')")->execute([$id]);identity_check(club_can('payments.manage') && club_can('enquiries.membership'),'explicit treasurer capability');$count++;
 $pdo->prepare('DELETE FROM club_user_roles WHERE user_id=?')->execute([$id]);$pdo->prepare("INSERT INTO club_enquiry_routes(user_id,category) VALUES(?,'hall_hire')")->execute([$id]);identity_check(club_can('enquiries.handle') && !club_can('enquiries.all') && !club_can('enquiries.membership'),'scoped enquiry handler');$count++;
 $pdo->prepare("UPDATE club_users SET status='suspended' WHERE id=?")->execute([$id]);identity_check(club_current_user()===null && !club_can('payments.manage'),'suspension invalidates session');$count++;
 $pdo->prepare("UPDATE club_users SET status='active' WHERE id=?")->execute([$id]);
 identity_check(!club_attempt_login($email,'incorrect')['success'],'wrong password denied');$count++;
 identity_check(club_attempt_login($email,$password)['success'],'valid password login');$count++;
 $pdo->prepare('UPDATE club_users SET password_hash=? WHERE id=?')->execute([password_hash('changed-'.bin2hex(random_bytes(15)),PASSWORD_DEFAULT),$id]);identity_check(club_current_user()===null,'password change revokes old session');$count++;
 for($i=0;$i<5;$i++)record_login_attempt('member:'.hash('sha256',$email),$_SERVER['REMOTE_ADDR'],false);
 identity_check(!club_attempt_login($email,$password)['success'],'login lockout enforced');$count++;
 club_queue_token($id,'verify',$email);$s=$pdo->prepare("SELECT payload,status FROM club_outbox WHERE channel='member_email' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.to'))=? ORDER BY id DESC LIMIT 1");$s->execute([$email]);$row=$s->fetch();identity_check($row && $row['status']==='connection_required','verification captured without sending');$count++;
 parse_str(parse_url(json_decode($row['payload'],true)['url'],PHP_URL_QUERY),$query);$s=$pdo->prepare('SELECT token_hash FROM club_password_tokens WHERE user_id=? AND consumed_at IS NULL');$s->execute([$id]);identity_check(hash_equals($s->fetchColumn(),hash('sha256',$query['token'])),'token stored hashed');$count++;
}finally{if($pdo->inTransaction())$pdo->rollBack();unset($_SESSION['club_user_id'],$_SESSION['club_auth_sig']);}
echo 'PASS: '.$count.' identity and role checks; all fixture data rolled back.'.PHP_EOL;
