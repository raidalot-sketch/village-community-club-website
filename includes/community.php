<?php
/** Moderated community content and date-only, Europe/London calendar expansion. */
function club_community_table(string $kind): string {
    return match($kind) {'post'=>'club_posts','event'=>'events',default=>throw new InvalidArgumentException('Unknown content type.')};
}
function club_community_record(string $kind,int $id): ?array {
    $table=club_community_table($kind); $s=db()->prepare("SELECT * FROM $table WHERE id=?".($kind==='event'?' AND deleted_at IS NULL':'')); $s->execute([$id]); return $s->fetch()?:null;
}
function club_content_editable(array $row): bool {
    return club_can('content.publish') || (club_can('content.submit') && (int)($row['author_user_id']??0)===(int)(club_current_user()['id']??-1));
}
function club_content_audit(string $action,string $kind,int $id,array $details=[]):void {
    db()->prepare("INSERT INTO club_outbox(channel,event_key,payload,status) VALUES('audit',?,?,'sent')")->execute(['content-'.bin2hex(random_bytes(16)),json_encode(['action'=>$action,'kind'=>$kind,'id'=>$id,'actor_user_id'=>club_current_user()['id']??null,'legacy_admin_id'=>$_SESSION['admin_id']??null,'details'=>$details],JSON_THROW_ON_ERROR)]);
}
function club_valid_date(string $date):bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/London')); return $d && $d->format('Y-m-d')===$date; }
function club_content_payload(string $kind,array $input,array $editorMediaIds=[]):array {
    $title=trim((string)($input['title']??''));
    if ($title==='' || mb_strlen($title)>190) throw new InvalidArgumentException('Enter a title of up to 190 characters.');
    $body=trim((string)($input[$kind==='post'?'content':'description']??''));
    if ($body==='' || mb_strlen($body)>50000) throw new InvalidArgumentException('Enter the details, up to 50,000 characters.');
    if($kind==='post'&&($input['content_format']??'')==='html') {
        $body=club_editor_sanitize($body,$editorMediaIds);
        if(trim(strip_tags($body))===''&&!club_editor_inline_ids($body))throw new InvalidArgumentException('Enter the post details.');
        $summary=trim((string)($input['summary']??''));if(mb_strlen($summary)>300)throw new InvalidArgumentException('The summary can have up to 300 characters.');
        $cover=(int)($input['cover_attachment_id']??0);
        if($cover&&!in_array($cover,$editorMediaIds,true))throw new InvalidArgumentException('Choose a cover photograph from this post.');
        return ['title'=>$title,'content'=>$body,'summary'=>$summary?:null,'cover_attachment_id'=>$cover?:null];
    }
    $body=nl2br(e($body));
    if ($kind==='post') return ['title'=>$title,'content'=>$body];
    $date=(string)($input['event_date']??''); $endDate=trim((string)($input['end_date']??''));
    if (!club_valid_date($date) || ($endDate!=='' && (!club_valid_date($endDate)||$endDate<$date))) throw new InvalidArgumentException('Enter a valid event date and an end date on or after it.');
    $start=trim((string)($input['start_time']??'')); $end=trim((string)($input['end_time']??''));
    if (!empty($input['all_day'])) $start=$end='';
    foreach([$start,$end] as $time) if ($time!=='' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$time)) throw new InvalidArgumentException('Enter valid start and end times.');
    if ($start==='' && $end!=='') throw new InvalidArgumentException('Enter a start time or choose all day.');
    if ($start!=='' && $start===$end && ($endDate==='' || $endDate===$date)) throw new InvalidArgumentException('The event end must be after its start.');
    $visibility=(string)($input['visibility']??'public'); $repeat=(string)($input['recurrence']??'none'); $repeatEnd=trim((string)($input['recurrence_end_date']??''));
    if (!in_array($visibility,['public','members'],true)||!in_array($repeat,['none','weekly','monthly'],true)) throw new InvalidArgumentException('Choose valid visibility and recurrence.');
    if ($repeat!=='none' && (!club_valid_date($repeatEnd)||$repeatEnd<$date)) throw new InvalidArgumentException('Repeating events need a final occurrence date on or after the first event.');
    $link=trim((string)($input['booking_link']??''));
    if ($link!=='' && (!filter_var($link,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($link,PHP_URL_SCHEME)??''),['http','https'],true))) throw new InvalidArgumentException('The booking link must be a complete http or https address.');
    $venue=trim((string)($input['venue']??'')); if(mb_strlen($venue)>190 || strlen($link)>255) throw new InvalidArgumentException('The venue or booking link is too long.');
    $payload=['title'=>$title,'description'=>$body,'event_date'=>$date,'end_date'=>$endDate?:null,'start_time'=>$start?:null,'end_time'=>$end?:null,'venue'=>$venue,'booking_link'=>$link,'visibility'=>$visibility,'recurrence'=>$repeat,'recurrence_end_date'=>$repeat==='none'?null:$repeatEnd];
    $times=club_event_times($payload,$date);
    if($repeat!=='none'){if($repeatEnd>(new DateTimeImmutable($date))->modify('+10 years')->format('Y-m-d'))throw new InvalidArgumentException('Repeating events can run for up to ten years.');club_expand_event($payload,$date,$repeatEnd);}
    if($times['end_at']<=$times['start_at'])throw new InvalidArgumentException('The event end must be after its start.');
    return $payload;
}
/** Returns the affected record plus upload owner (a pending revision is kept private). */
function club_content_save(string $kind,?int $id,array $input,bool $submit,array $files=[]):array {
    $user=club_current_user(); if(!$user || (!club_can('content.submit')&&!club_can('content.publish'))) throw new RuntimeException('An active membership is required to submit content.');
    $table=club_community_table($kind); $editorSession=null;$editorIds=[];
    if($kind==='post'&&($input['content_format']??'')==='html'){
        $editorSession=club_editor_validate((int)($input['editor_session_id']??0),(string)($input['editor_session_token']??''),$id);
        $editorIds=array_map('intval',array_column(array_filter(club_editor_media($editorSession),fn($a)=>str_starts_with($a['mime'],'image/')),'id'));
    }
    $payload=club_content_payload($kind,$input,$editorIds); $pdo=db(); $ownsTransaction=!$pdo->inTransaction(); if($ownsTransaction)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT community_work');
    try {
        if($editorSession){
            $lock=$pdo->prepare('SELECT id FROM club_editor_sessions WHERE id=? FOR UPDATE');$lock->execute([$editorSession['id']]);
            $editorSession=club_editor_validate((int)$editorSession['id'],(string)$input['editor_session_token'],$id);
        }
        $row=null;
        if($id){$s=$pdo->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$s->execute([$id]);$row=$s->fetch();if(!$row || ($kind==='event'&&$row['deleted_at']) || !club_content_editable($row))throw new RuntimeException('You cannot edit this item.');}
        if($editorSession){
            $editorIds=array_map('intval',array_column(array_filter(club_editor_media($editorSession),fn($a)=>str_starts_with($a['mime'],'image/')),'id'));
            $payload=club_content_payload($kind,$input,$editorIds);
        }
        if($kind==='event' && $payload['visibility']==='members' && !empty($row['featured_image']))throw new InvalidArgumentException('This older event uses a public photograph. Ask an administrator to move it to protected attachments before choosing members only.');
        if($row && $row['status']==='published'){
            // Published copies remain intact until an explicit approval. Editing published content submits a revision.
            $s=$pdo->prepare("SELECT id FROM club_revisions WHERE owner_type=? AND owner_id=? AND status='submitted' ORDER BY id DESC LIMIT 1 FOR UPDATE");$s->execute([$kind,$id]);$revision=$s->fetchColumn();
            if($revision)$pdo->prepare("UPDATE club_revisions SET status='rejected',reviewer_note='Superseded by a newer revision.' WHERE id=?")->execute([$revision]);
            $pdo->prepare('INSERT INTO club_revisions(owner_type,owner_id,author_user_id,payload) VALUES(?,?,?,?)')->execute([$kind,$id,$user['id'],json_encode($payload,JSON_THROW_ON_ERROR)]);$newRevision=(int)$pdo->lastInsertId();if($revision)$pdo->prepare("UPDATE club_attachments SET owner_id=? WHERE owner_type='revision' AND owner_id=?")->execute([$newRevision,$revision]);$revision=$newRevision;
            if(!empty($files['name']))club_store_uploads($files,'revision',(int)$revision,(int)$user['id']);if($editorSession)club_editor_attach($editorSession,$payload,'revision',(int)$revision);if($kind==='post')club_editor_attachment_limit('revision',(int)$revision,$id);club_content_audit('revision.submitted',$kind,$id);if($ownsTransaction)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT community_work');return ['id'=>$id,'owner_type'=>'revision','owner_id'=>(int)$revision,'status'=>'submitted'];
        }
        $payload['status']=$submit?'submitted':'draft';
        if($row){$assign=implode(',',array_map(fn($key)=>"$key=?",array_keys($payload)));$pdo->prepare("UPDATE $table SET $assign,reviewer_note=NULL WHERE id=?")->execute([...array_values($payload),$id]);}
        else{$payload['author_user_id']=$user['id'];$payload['slug']=unique_slug($table,slugify($payload['title']));$columns=implode(',',array_keys($payload));$marks=implode(',',array_fill(0,count($payload),'?'));$pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($payload));$id=(int)$pdo->lastInsertId();}
        if(!empty($files['name']))club_store_uploads($files,$kind,$id,(int)$user['id']);if($editorSession){club_editor_attach($editorSession,$payload,$kind,$id);if($editorSession['post_id']===null)$pdo->prepare('UPDATE club_editor_sessions SET post_id=? WHERE id=?')->execute([$id,$editorSession['id']]);}club_content_audit($submit?'submitted':'draft.saved',$kind,$id);if($ownsTransaction)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT community_work');return ['id'=>$id,'owner_type'=>$kind,'owner_id'=>$id,'status'=>$payload['status']];
    }catch(Throwable $e){if($pdo->inTransaction()){if($ownsTransaction)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT community_work');$pdo->exec('RELEASE SAVEPOINT community_work');}}throw $e;}
}
/** Fingerprint exactly the text and attachments the publisher reviewed. */
function club_content_review_hash(string $kind,int $id,?int $revisionId=null):string {
    $row=club_community_record($kind,$id);
    if($revisionId){$q=db()->prepare('SELECT * FROM club_revisions WHERE id=? AND owner_type=? AND owner_id=?');$q->execute([$revisionId,$kind,$id]);$revision=$q->fetch();$row=['record'=>$row,'revision'=>$revision];}
    $q=db()->prepare('SELECT id,storage_path,original_name,mime,bytes FROM club_attachments WHERE owner_type=? AND owner_id=? ORDER BY id');$q->execute([$revisionId?'revision':$kind,$revisionId??$id]);
    return hash('sha256',json_encode([$row,$q->fetchAll()],JSON_THROW_ON_ERROR));
}
function club_content_moderate(string $kind,int $id,string $action,string $note='',?int $revisionId=null,?string $expectedHash=null):void {
    if(!club_can('content.publish'))throw new RuntimeException('Publishing permission is required.');
    if(!in_array($action,['approve','reject','unpublish'],true))throw new InvalidArgumentException('Invalid moderation action.');
    $note=mb_substr(trim($note),0,2000);$table=club_community_table($kind);$pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT community_work');
    try{
        $s=$pdo->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$s->execute([$id]);$row=$s->fetch();if(!$row || ($kind==='event'&&$row['deleted_at']))throw new RuntimeException('Content not found.');
        if($expectedHash!==null && !hash_equals($expectedHash,club_content_review_hash($kind,$id,$revisionId)))throw new RuntimeException('This submission changed after you opened it. Refresh and review it again before approving.');
        if($revisionId){
            $s=$pdo->prepare("SELECT * FROM club_revisions WHERE id=? AND owner_type=? AND owner_id=? AND status='submitted' FOR UPDATE");$s->execute([$revisionId,$kind,$id]);$revision=$s->fetch();if(!$revision||$row['status']!=='published')throw new RuntimeException('This revision is no longer awaiting review.');
            if(!in_array($action,['approve','reject'],true))throw new InvalidArgumentException('Invalid revision decision.');
            if($action==='approve'){
                $payload=json_decode($revision['payload'],true,512,JSON_THROW_ON_ERROR);$allowed=$kind==='post'?['title','content','summary','cover_attachment_id']:['title','description','event_date','end_date','start_time','end_time','venue','booking_link','visibility','recurrence','recurrence_end_date'];$payload=array_intersect_key($payload,array_flip($allowed));
                if($kind==='event'&&($payload['visibility']??$row['visibility'])==='members'&&!empty($row['featured_image']))throw new InvalidArgumentException('Move the legacy public photograph to protected storage before approving a members-only event.');
                $assign=implode(',',array_map(fn($key)=>"$key=?",array_keys($payload)));$pdo->prepare("UPDATE $table SET $assign WHERE id=?")->execute([...array_values($payload),$id]);
                $pdo->prepare("UPDATE club_attachments SET owner_type=?,owner_id=? WHERE owner_type='revision' AND owner_id=?")->execute([$kind,$id,$revisionId]);
            }
            $pdo->prepare('UPDATE club_revisions SET status=?,reviewer_note=? WHERE id=?')->execute([$action==='approve'?'approved':'rejected',$note,$revisionId]);
        }else{
            if($action!=='unpublish'&&$row['status']!=='submitted')throw new RuntimeException('This item is no longer awaiting review.');
            if($action==='approve'&&$kind==='event'&&$row['visibility']==='members'&&!empty($row['featured_image']))throw new InvalidArgumentException('Move the legacy public photograph to protected storage before approving a members-only event.');
            $status=match($action){'approve'=>'published','reject'=>'rejected','unpublish'=>'draft'};
            $extra=$kind==='post'&&$action==='approve'?',approved_by='.(int)(club_current_user()['id']??0).',published_at=NOW()':'';
            $pdo->prepare("UPDATE $table SET status=?,reviewer_note=?$extra WHERE id=?")->execute([$status,$note,$id]);
            if($action==='unpublish')$pdo->prepare("UPDATE club_revisions SET status='rejected',reviewer_note='Publication withdrawn; submit changes again.' WHERE owner_type=? AND owner_id=? AND status='submitted'")->execute([$kind,$id]);
        }
        club_content_audit($revisionId?'revision.'.$action:$action,$kind,$id);if($ownsTransaction)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT community_work');
    }catch(Throwable $e){if($pdo->inTransaction()){if($ownsTransaction)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT community_work');$pdo->exec('RELEASE SAVEPOINT community_work');}}throw $e;}
}
/** Require exactly one real UK wall-clock instant, including during clock changes. */
function club_event_wall_time(string $date,string $time):DateTimeImmutable {
    $time=strlen($time)===5?$time.':00':$time;$wall=$date.' '.$time;$zone=new DateTimeZone('Europe/London');
    $utc=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$wall,new DateTimeZone('UTC'));
    if(!$utc||$utc->format('Y-m-d H:i:s')!==$wall)throw new InvalidArgumentException('Enter a valid UK event time.');
    $candidates=[];$offsets=[];foreach($zone->getTransitions($utc->getTimestamp()-172800,$utc->getTimestamp()+172800) as $transition)$offsets[$transition['offset']]=true;
    foreach(array_keys($offsets) as $offset){$candidate=(new DateTimeImmutable('@'.($utc->getTimestamp()-$offset)))->setTimezone($zone);if($candidate->format('Y-m-d H:i:s')===$wall)$candidates[$candidate->getTimestamp()]=$candidate;}
    if(count($candidates)!==1)throw new InvalidArgumentException('The UK clock change makes '.$wall.' '.(count($candidates)?'ambiguous':'unavailable').'. Choose a time outside the clock-change hour.');
    return array_values($candidates)[0];
}
function club_event_times(array $event,string $occurrenceDate):array {
    $tz=new DateTimeZone('Europe/London');$base=new DateTimeImmutable($event['event_date'],$tz);$day=new DateTimeImmutable($occurrenceDate,$tz);
    $allDay=empty($event['start_time']);$start=club_event_wall_time($occurrenceDate,$allDay?'00:00:00':$event['start_time']);
    $span=!empty($event['end_date'])?(int)$base->diff(new DateTimeImmutable($event['end_date'],$tz))->format('%r%a'):0;
    $endDay=$day->modify('+'.max(0,$span).' days');
    if($allDay)$end=$endDay->modify('+1 day');
    elseif(!empty($event['end_time'])){
        if(empty($event['end_date'])&&strcmp(substr($event['end_time'],0,5),substr($event['start_time'],0,5))<0)$endDay=$endDay->modify('+1 day');
        $end=club_event_wall_time($endDay->format('Y-m-d'),$event['end_time']);
    }else $end=$start->setTimestamp($start->getTimestamp()+max(0,$span)*86400+3600);
    return ['start_at'=>$start,'end_at'=>$end,'all_day'=>$allDay];
}
function club_expand_event(array $event,string $startDate,string $endDate,array $cancelled=[]):array {
    if(!club_valid_date($startDate)||!club_valid_date($endDate)||$endDate<$startDate)throw new InvalidArgumentException('Invalid calendar range.');
    $tz=new DateTimeZone('Europe/London');$from=new DateTimeImmutable($startDate,$tz);$until=(new DateTimeImmutable($endDate,$tz))->modify('+1 day');$base=new DateTimeImmutable($event['event_date'],$tz);
    $repeat=$event['recurrence']??'none';$last=!empty($event['recurrence_end_date'])?new DateTimeImmutable($event['recurrence_end_date'],$tz):$until;
    $result=[];
    // Bounded expansion starts at the relevant month/week, even for a long-running series.
    $span=!empty($event['end_date'])?(int)$base->diff(new DateTimeImmutable($event['end_date'],$tz))->format('%r%a'):1;
    $lookback=$from->modify('-'.max(2,$span+1).' days');
    $offset=0;
    if($repeat==='weekly')$offset=max(0,(int)floor((int)$base->diff($lookback)->format('%r%a')/7));
    elseif($repeat==='monthly')$offset=max(0,((int)$lookback->format('Y')-(int)$base->format('Y'))*12+(int)$lookback->format('n')-(int)$base->format('n')-1);
    for($i=$offset;$i<$offset+5000;$i++){
        if($repeat==='weekly')$day=$base->modify('+'.($i*7).' days');
        elseif($repeat==='monthly'){
            $month=$base->modify('first day of this month')->modify('+'.$i.' months');
            if((int)$base->format('j')>(int)$month->format('t'))continue; // 31st does not silently roll into the next month.
            $day=$month->setDate((int)$month->format('Y'),(int)$month->format('n'),(int)$base->format('j'));
        }else $day=$base;
        if($day>=$until||($repeat!=='none'&&$day>$last))break;
        $date=$day->format('Y-m-d');$times=club_event_times($event,$date);
        if($times['end_at']>$from && $times['start_at']<$until && !in_array($date,$cancelled,true))$result[]=array_merge($event,$times,['occurrence_date'=>$date]);
        if($repeat==='none')break;
    }
    return $result;
}
function club_private_events_allowed():bool { return club_is_active_member()||club_can('content.publish'); }
function club_calendar_occurrences(string $startDate,string $endDate,bool $includePrivate=false):array {
    if(!club_valid_date($startDate)||!club_valid_date($endDate)||$endDate<$startDate || (new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($endDate))->days>732)throw new InvalidArgumentException('Choose a calendar range of up to two years.');
    // A caller cannot opt a visitor into private events.
    $private=$includePrivate && club_private_events_allowed();
    $s=db()->prepare("SELECT * FROM events WHERE status='published' AND deleted_at IS NULL".($private?'':" AND visibility='public'")." AND event_date<=? AND (recurrence<>'none' OR COALESCE(end_date,event_date)>=? OR event_date>=DATE_SUB(?,INTERVAL 1 DAY))");$s->execute([$endDate,$startDate,$startDate]);$events=$s->fetchAll();$result=[];
    if(!$events)return [];
    $ids=array_column($events,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$s=db()->prepare("SELECT event_id,occurrence_date FROM club_event_exceptions WHERE event_id IN($marks) AND cancelled=1");$s->execute($ids);$exceptions=[];foreach($s->fetchAll() as $ex)$exceptions[$ex['event_id']][]=$ex['occurrence_date'];
    foreach($events as $event)$result=array_merge($result,club_expand_event($event,$startDate,$endDate,$exceptions[$event['id']]??[]));
    usort($result,fn($a,$b)=>$a['start_at']<=>$b['start_at']?:strcmp($a['title'],$b['title']));return $result;
}
function club_cancel_event(int $id,string $scope,?string $date,string $reason):void {
    if(!club_can('content.publish'))throw new RuntimeException('Only publishers can cancel published events.');
    if(trim($reason)==='')throw new InvalidArgumentException('Enter a reason for cancellation.');
    if(!in_array($scope,['occurrence','series'],true))throw new InvalidArgumentException('Choose an occurrence or the series.');
    $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT community_cancel');
    try{
        $s=$pdo->prepare("SELECT * FROM events WHERE id=? AND deleted_at IS NULL FOR UPDATE");$s->execute([$id]);$row=$s->fetch();if(!$row||$row['status']!=='published')throw new RuntimeException('Published event not found.');
        if($scope==='series')club_content_moderate('event',$id,'unpublish','Cancelled: '.mb_substr($reason,0,1900));
        else{
            if(!$date||!club_valid_date($date)||!club_expand_event($row,$date,$date))throw new InvalidArgumentException('This date is not an occurrence of this event.');
            $pdo->prepare('INSERT INTO club_event_exceptions(event_id,occurrence_date,cancelled) VALUES(?,?,1) ON DUPLICATE KEY UPDATE cancelled=1')->execute([$id,$date]);
            club_content_audit('occurrence.cancelled','event',$id,['occurrence_date'=>$date,'reason'=>mb_substr(trim($reason),0,1900)]);
        }
        if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT community_cancel');
    }catch(Throwable $e){if($pdo->inTransaction()){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT community_cancel');$pdo->exec('RELEASE SAVEPOINT community_cancel');}}throw $e;}
}
function club_content_attachments(string $kind,int $id,array $skipIds=[]):void {
    foreach(club_attachment_list($kind,$id) as $file){
        if(in_array((int)$file['id'],$skipIds,true))continue;
        $url=club_media_url((int)$file['id']);
        if(str_starts_with($file['mime'],'image/'))echo '<figure><img src="'.e($url).'" alt="'.e($file['original_name']).'" loading="lazy" style="max-height:460px;width:auto;max-width:100%;border-radius:12px"></figure>';
        else echo '<p><a href="'.e($url).'">'.e($file['original_name']).'</a></p>';
    }
}
function club_ics_escape(string $value):string {return str_replace(["\\","\r\n","\n","\r",',',';'],["\\\\","\\n","\\n",'',"\\,","\\;"],strip_tags($value));}
function club_calendar_ics(array $occurrences):string {
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Nettlebed Village Club//Club Calendar//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH','X-WR-CALNAME:Nettlebed Village Club'];
    foreach($occurrences as $event){$lines[]='BEGIN:VEVENT';$lines[]='UID:event-'.$event['id'].'-'.$event['occurrence_date'].'@nettlebedvillageclub.co.uk';$lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z');
        foreach(['start_at'=>'DTSTART','end_at'=>'DTEND'] as $key=>$label)$lines[]=$event['all_day']?$label.';VALUE=DATE:'.$event[$key]->format('Ymd'):$label.':'.$event[$key]->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines[]='SUMMARY:'.club_ics_escape($event['title']);$lines[]='DESCRIPTION:'.club_ics_escape(html_entity_decode($event['description']??'',ENT_QUOTES,'UTF-8'));$lines[]='LOCATION:'.club_ics_escape($event['venue']??'');$lines[]='URL:'.site_url('/event-detail.php?slug='.rawurlencode($event['slug']).'&date='.$event['occurrence_date']);$lines[]='END:VEVENT';}
    $lines[]='END:VCALENDAR';$out=[];foreach($lines as $line){while(strlen($line)>73){$part=mb_strcut($line,0,73,'UTF-8');$out[]=$part;$line=' '.substr($line,strlen($part));}$out[]=$line;}return implode("\r\n",$out)."\r\n";
}
