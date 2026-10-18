<?php
/** Rollback-only integration fixtures; no external mail, credentials or publishing. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../config/config.php';
if (DB_NAME !== 'nettlebed_preview') throw new RuntimeException('Preview database required.');
define('APP_ROOT',dirname(__DIR__));
require APP_ROOT.'/includes/db.php';
require APP_ROOT.'/includes/functions.php';
require APP_ROOT.'/includes/auth.php';
require APP_ROOT.'/includes/member-auth.php';
require APP_ROOT.'/includes/polls.php';
date_default_timezone_set('Europe/London');
$_SESSION = [];
function check(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function denied(callable $work,string $message):void { try { $work(); } catch (DomainException $e) { check(true,$message); return; } throw new RuntimeException('Expected denial: '.$message); }
db()->exec("SET time_zone = '+01:00'");
db()->beginTransaction();
try {
    $ids = [];
    foreach (['member','member','publisher','member'] as $n=>$role) {
        db()->prepare("INSERT INTO club_users(full_name,email,password_hash,status,email_verified_at) VALUES(?,?,?,'active',NOW())")->execute(['Poll test '.$n,'poll-'.bin2hex(random_bytes(10)).'@example.invalid','unused-fixture-hash']);
        $ids[] = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO club_user_roles(user_id,role) VALUES(?,?)')->execute([$ids[$n],$role]);
        if ($n !== 3) db()->prepare("INSERT INTO club_memberships(user_id,status,starts_on,expires_on,external_ref) VALUES(?,'active',?,?,?)")->execute([$ids[$n],club_poll_now()->modify('-1 month')->format('Y-m-d'),club_poll_now()->modify('+1 year')->format('Y-m-d'),'poll-'.bin2hex(random_bytes(12))]);
    }
    $_SESSION['club_user_id']=$ids[0];
    $input=['title'=>'Which club evening?', 'description'=>'Choose a day.', 'choices'=>['Friday','Saturday'], 'opens_at'=>club_poll_now()->modify('-1 hour')->format('Y-m-d H:i:s'), 'closes_at'=>club_poll_now()->modify('+1 day')->format('Y-m-d H:i:s'),'action'=>'submit'];
    $id=club_poll_save(null,$input);
    $choices=club_poll_choices($id);
    check(club_poll_find($id)['status']==='submitted','Member submission enters approval queue');
    denied(fn()=>club_poll_vote($id,(int)$choices[0]['id']),'Submitted poll cannot accept votes');
    denied(fn()=>club_poll_moderate($id,'publish'),'Member cannot approve own poll');
    $_SESSION['club_user_id']=$ids[1];
    check(!club_poll_can_view(club_poll_find($id)),'Other members cannot view unpublished poll');
    denied(fn()=>club_poll_save($id,$input),'Member cannot edit another member’s poll');
    $_SESSION['club_user_id']=$ids[2]; club_poll_moderate($id,'publish');
    check(club_poll_state(club_poll_find($id))==='open','Publisher approval opens a poll whose opening time passed');
    denied(fn()=>club_poll_results($id),'Publisher cannot see results or participation while voting open');
    $_SESSION['club_user_id']=$ids[0];
    check(count(club_poll_list($ids[1]))===0,'Optional user ID cannot impersonate another member');
    club_poll_vote($id,(int)$choices[0]['id']);
    club_poll_vote($id,(int)$choices[1]['id']);
    check(club_poll_my_vote($id)===(int)$choices[1]['id'],'Member can change their choice before close');
    $q=db()->prepare('SELECT COUNT(*) FROM club_poll_votes WHERE poll_id=? AND user_id=?');$q->execute([$id,$ids[0]]);
    check((int)$q->fetchColumn()===1,'Repeated vote upserts maintain one vote per poll and member');
    try {db()->prepare('INSERT INTO club_poll_votes(poll_id,user_id,choice_id) VALUES(?,?,?)')->execute([$id,$ids[0],$choices[0]['id']]);throw new RuntimeException('Missing unique vote constraint');}
    catch(PDOException $e){check($e->getCode()==='23000','Database unique constraint enforces the concurrent-insert invariant');}
    $other=club_poll_save(null,array_replace($input,['action'=>'draft','title'=>'Other poll']));
    $otherChoices=club_poll_choices($other);
    denied(fn()=>club_poll_vote($id,(int)$otherChoices[0]['id']),'Choice from another poll rejected by application');
    try {db()->prepare('INSERT INTO club_poll_votes(poll_id,user_id,choice_id) VALUES(?,?,?)')->execute([$id,$ids[1],$otherChoices[0]['id']]);throw new RuntimeException('Missing compound choice constraint');}
    catch(PDOException $e){check($e->getCode()==='23000','Compound foreign key rejects cross-poll choices');}
    denied(fn()=>club_poll_save($id,$input),'Published question and choices are immutable');
    $_SESSION['club_user_id']=$ids[3];
    check(!club_poll_can_view(club_poll_find($id)),'Unpaid account cannot view member poll');
    denied(fn()=>club_poll_vote($id,(int)$choices[0]['id']),'Unpaid account cannot vote');
    $_SESSION['club_user_id']=$ids[1];
    db()->prepare('UPDATE club_users SET email_verified_at=NULL WHERE id=?')->execute([$ids[1]]);
    denied(fn()=>club_poll_vote($id,(int)$choices[0]['id']),'Unverified member cannot vote');
    db()->prepare('UPDATE club_users SET email_verified_at=NOW() WHERE id=?')->execute([$ids[1]]);
    db()->prepare("UPDATE club_memberships SET expires_on=? WHERE user_id=?")->execute([club_poll_now()->format('Y-m-d'),$ids[1]]);
    denied(fn()=>club_poll_vote($id,(int)$choices[0]['id']),'Membership expiration boundary cannot vote');
    $_SESSION['club_user_id']=$ids[2];
    club_poll_moderate($id,'close','Enough time for responses.');
    $results=club_poll_results($id);
    check($results['total']===1 && $results['choices'][1]['votes']===1 && $results['choices'][1]['percentage']===100.0,'Closed results count latest vote once and calculate percentages');
    check(!str_contains(json_encode($results),'user_id'),'Results contain no voter identities');
    denied(fn()=>club_poll_vote($id,(int)$choices[0]['id']),'Closing boundary prevents new votes');
    $_SESSION['club_user_id']=$ids[0];
    $cancelId=club_poll_save(null,$input); $_SESSION['club_user_id']=$ids[2];
    club_poll_moderate($cancelId,'publish'); club_poll_moderate($cancelId,'cancel','Replacement required.');
    denied(fn()=>club_poll_vote($cancelId,(int)club_poll_choices($cancelId)[0]['id']),'Cancelled poll cannot accept votes');
    denied(fn()=>club_poll_results($cancelId),'Cancelled poll does not expose premature results');
    $_SESSION['club_user_id']=$ids[0];
    $zeroId=club_poll_save(null,$input); $_SESSION['club_user_id']=$ids[2];
    club_poll_moderate($zeroId,'publish'); club_poll_moderate($zeroId,'close','No responses needed.');
    $zero=club_poll_results($zeroId); check($zero['total']===0 && $zero['choices'][0]['percentage']===0,'Zero-response poll avoids invalid percentages');
    $p=['status'=>'published','opens_at'=>'2026-11-01 12:00:00','closes_at'=>'2026-11-01 13:00:00'];
    $zone=new DateTimeZone('Europe/London');
    check(club_poll_state($p,new DateTimeImmutable('2026-11-01 11:59:59',$zone))==='scheduled','No voting before exact opening boundary');
    check(club_poll_state($p,new DateTimeImmutable('2026-11-01 12:00:00',$zone))==='open','Opening boundary is inclusive');
    check(club_poll_state($p,new DateTimeImmutable('2026-11-01 13:00:00',$zone))==='closed','Closing boundary is exclusive');
    denied(fn()=>club_poll_date('2027-03-28T01:30'),'Nonexistent spring clock time rejected');
    denied(fn()=>club_poll_date('2026-10-25T01:30'),'Ambiguous autumn clock time rejected');
    $q=db()->prepare("SELECT payload FROM club_outbox WHERE channel='audit' AND payload LIKE ?"); $q->execute(['%"poll_id":'.$id.',%']);
    $audits=$q->fetchAll();
    check(count($audits)===2 && !str_contains(json_encode($audits),'choice_id'),'Publish and early close audited without ballot choices');
} finally { db()->rollBack(); }
echo "All poll fixtures rolled back.\n";
