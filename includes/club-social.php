<?php
/** Opt-in Meta outbox delivery. No network work takes place during moderation. */
/** Non-secret readiness diagnostics; connection tests never imply publishing permission. */
function club_social_configuration(): array
{
    $page=defined('CLUB_META_PAGE_ID') && is_string(CLUB_META_PAGE_ID) && ctype_digit(CLUB_META_PAGE_ID);
    $token=defined('CLUB_META_PAGE_TOKEN') && is_string(CLUB_META_PAGE_TOKEN) && CLUB_META_PAGE_TOKEN!=='' && !preg_match('/[\r\n]/',CLUB_META_PAGE_TOKEN);
    $version=defined('CLUB_META_GRAPH_VERSION') && is_string(CLUB_META_GRAPH_VERSION) && (bool)preg_match('/^v\d+\.\d+$/D',CLUB_META_GRAPH_VERSION);
    return ['page_id_configured'=>$page,'page_token_configured'=>$token,'graph_version_configured'=>$version,
        'instagram_id_configured'=>defined('CLUB_META_INSTAGRAM_ID') && is_string(CLUB_META_INSTAGRAM_ID) && ctype_digit(CLUB_META_INSTAGRAM_ID),
        'publish_enabled'=>defined('CLUB_SOCIAL_PUBLISH_ENABLED') && CLUB_SOCIAL_PUBLISH_ENABLED===true,
        'preview'=>defined('SITE_PREVIEW') && SITE_PREVIEW===true,
        'public_media_ready'=>club_social_public_media_ready(),
        'read_probe_configured'=>$page && $token && $version];
}

/** Explicit read-only Page/linkage probe. Works before enabling delivery, including preview. */
function club_social_connection_probe(bool $check=false, ?callable $transport=null): array
{
    $configuration=club_social_configuration();
    $result=['configuration'=>$configuration,'checked'=>false,'page'=>'not_checked','instagram'=>$configuration['instagram_id_configured']?'not_checked':'not_configured','publishing_permissions'=>'not_verified','message'=>'No network check requested.'];
    if(!$check)return $result;
    if(PHP_SAPI!=='cli'&&!club_can('settings.manage'))throw new DomainException('Administrator permission is required to check social connections.');
    if(!$configuration['read_probe_configured']){$result['page']='configuration_required';$result['message']='Add a valid Page ID, private Page token and Graph version before checking the connection.';return $result;}
    try {
        // A GET only: never create media, posts, publish requests, subscriptions or permissions.
        $page=club_social_graph('GET',CLUB_META_PAGE_ID,['fields'=>'id,name,instagram_business_account{id,username}'],$transport);
        $result['checked']=true;
        if(!is_string($page['id']??null) || !hash_equals(CLUB_META_PAGE_ID,$page['id'])){$result['page']='id_mismatch';$result['message']='The returned Page does not match the configured Page ID.';return $result;}
        $result['page']='verified';
        if($configuration['instagram_id_configured']){
            $linked=$page['instagram_business_account']['id']??null;
            $result['instagram']=!is_string($linked)?'link_unavailable':(hash_equals(CLUB_META_INSTAGRAM_ID,$linked)?'verified':'id_mismatch');
        }
        $result['message']='Page access checked. Publishing permissions and actual publication have not been verified.';
        if($result['instagram']==='link_unavailable')$result['message']='Page access checked; Instagram linkage was not returned. Check linkage and token access. Publishing permissions remain unverified.';
        elseif($result['instagram']==='id_mismatch')$result['message']='Page access checked; the linked Instagram account does not match the configured ID. Publishing permissions remain unverified.';
    } catch(Throwable $e){$result['page']='check_failed';$result['message']='Meta connection could not be verified. Check private credentials, account access and Graph version.';}
    // No raw provider strings, account names, IDs or token values enter diagnostics or logs.
    return $result;
}

function club_social_connection_ready(string $channel): bool
{
    if (!in_array($channel, ['facebook','instagram'], true) || !defined('CLUB_SOCIAL_PUBLISH_ENABLED') || CLUB_SOCIAL_PUBLISH_ENABLED !== true || (defined('SITE_PREVIEW') && SITE_PREVIEW)) return false;
    foreach (['CLUB_META_PAGE_ID','CLUB_META_PAGE_TOKEN','CLUB_META_GRAPH_VERSION'] as $key) if (!defined($key) || !is_string(constant($key)) || constant($key) === '') return false;
    if (!preg_match('/^v\d+\.\d+$/D', CLUB_META_GRAPH_VERSION) || !ctype_digit(CLUB_META_PAGE_ID) || preg_match('/[\r\n]/', CLUB_META_PAGE_TOKEN)) return false;
    if ($channel === 'instagram') return defined('CLUB_META_INSTAGRAM_ID') && is_string(CLUB_META_INSTAGRAM_ID) && ctype_digit(CLUB_META_INSTAGRAM_ID) && club_social_public_media_ready();
    return true;
}

function club_social_public_media_ready(): bool
{
    return !(defined('SITE_PREVIEW') && SITE_PREVIEW) && defined('CLUB_SOCIAL_PUBLIC_MEDIA_READY') && CLUB_SOCIAL_PUBLIC_MEDIA_READY === true && defined('APP_SECRET') && strlen(APP_SECRET) >= 32 && parse_url(site_url(), PHP_URL_SCHEME) === 'https' && extension_loaded('gd');
}

function club_social_plain_text(string $html): string
{
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
    $html = preg_replace('/<(?:br\b[^>]*|\/(?:p|div|h[1-6]|blockquote|li|ul|ol|figure|figcaption))>/i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', str_replace(["\r\n","\r"], "\n", $text));
    return trim(preg_replace('/\n{3,}/', "\n\n", $text));
}

/** Hash deliberately excludes delivery state and auto_share. Only published posts qualify. */
function club_social_snapshot(string $kind, int $id): array
{
    if ($kind !== 'post' || $id < 1) throw new DomainException('Only approved public posts can be shared automatically.');
    $q = db()->prepare("SELECT * FROM club_posts WHERE id=? AND status='published'"); $q->execute([$id]); $post = $q->fetch();
    if (!$post) throw new DomainException('The source post is no longer published.');
    $q = db()->prepare("SELECT id,storage_path,mime,bytes FROM club_attachments WHERE owner_type='post' AND owner_id=? AND mime IN ('image/jpeg','image/png','image/webp') ORDER BY id"); $q->execute([$id]); $images = $q->fetchAll();
    $image = $images[0] ?? null;
    foreach ($images as $candidate) if ((int)$candidate['id'] === (int)($post['cover_attachment_id'] ?? 0)) $image = $candidate;
    $payload = ['kind'=>'post','id'=>$id,'title'=>club_social_plain_text($post['title']), 'text'=>club_social_plain_text($post['content']), 'url'=>site_url('/post.php?slug='.rawurlencode($post['slug'])), 'image_attachment_id'=>$image ? (int)$image['id'] : null];
    $payload['source_hash'] = hash('sha256', json_encode([$payload, $image], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    return $payload;
}

function club_social_current_payload(array $row): array
{
    $payload = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
    if (($payload['auto_share'] ?? null) !== true || ($payload['kind'] ?? '') !== 'post' || !is_int($payload['id'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $payload['source_hash'] ?? '')) throw new DomainException('This is not an automatic approved post delivery.');
    $current = club_social_snapshot('post', $payload['id']);
    if (!hash_equals($current['source_hash'], $payload['source_hash'])) throw new DomainException('The approved post changed; this delivery needs review.');
    foreach ($current as $key=>$value) if (!array_key_exists($key,$payload) || $payload[$key] !== $value) throw new DomainException('The queued snapshot does not match the approved post.');
    return $current;
}

/** Signed URL grants access only to this approved queue snapshot, for at most 24 hours. */
function club_social_image_url(int $queueId, string $hash, ?int $expires = null): string
{
    $expires ??= time() + 3600;
    if (!defined('APP_SECRET') || strlen(APP_SECRET)<32 || $queueId<1 || !preg_match('/^[a-f0-9]{64}$/D',$hash) || $expires<=time() || $expires>time()+86400) throw new DomainException('Social image signing is unavailable.');
    $signature = hash_hmac('sha256', $queueId.'|'.$hash.'|'.$expires, APP_SECRET);
    return site_url('/social-image.php?'.http_build_query(['id'=>$queueId,'hash'=>$hash,'expires'=>$expires,'signature'=>$signature]));
}

function club_social_image_payload(int $id, string $hash, int $expires, string $signature): array
{
    if ((defined('SITE_PREVIEW') && SITE_PREVIEW) || !defined('CLUB_SOCIAL_PUBLIC_MEDIA_READY') || CLUB_SOCIAL_PUBLIC_MEDIA_READY !== true || !defined('APP_SECRET') || strlen(APP_SECRET)<32 || $id<1 || $expires<time() || $expires>time()+86400 || !preg_match('/^[a-f0-9]{64}$/D',$hash) || !hash_equals(hash_hmac('sha256',$id.'|'.$hash.'|'.$expires,APP_SECRET),$signature)) throw new DomainException('Social image is unavailable.');
    $q=db()->prepare("SELECT * FROM club_outbox WHERE id=? AND channel IN ('facebook','instagram') AND status IN ('processing','sent','failed')");$q->execute([$id]);$row=$q->fetch();
    if (!$row) throw new DomainException('Social image is unavailable.');
    $payload=club_social_current_payload($row);
    if (!hash_equals($hash,$payload['source_hash'])) throw new DomainException('Social image is unavailable.');
    return $payload;
}

/** Re-encode approved images; never expose a storage path or original/private file. */
function club_social_jpeg(array $payload): string
{
    if (!extension_loaded('gd')) throw new RuntimeException('Social image processing is unavailable.');
    $out=imagecreatetruecolor(1080,1080);$bg=imagecolorallocate($out,248,241,221);imagefill($out,0,0,$bg);
    try {
        if ($payload['image_attachment_id']) {
            $q=db()->prepare("SELECT storage_path FROM club_attachments WHERE id=? AND owner_type='post' AND owner_id=? AND mime IN ('image/jpeg','image/png','image/webp')");$q->execute([$payload['image_attachment_id'],$payload['id']]);$stored=$q->fetchColumn();
            $root=realpath(club_storage_root());$path=$stored ? realpath($root.'/'.$stored) : false;
            if (!$root || !$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path) || filesize($path)>CLUB_UPLOAD_MAX) throw new RuntimeException('The approved social image is unavailable.');
            $size=@getimagesize($path);if(!$size || $size[0]*$size[1]>60000000)throw new RuntimeException('The approved social image is unavailable.');
            $src=@imagecreatefromstring(file_get_contents($path));if(!$src)throw new RuntimeException('The approved social image is unavailable.');
            $scale=min(1080/imagesx($src),1080/imagesy($src));$w=(int)round(imagesx($src)*$scale);$h=(int)round(imagesy($src)*$scale);
            imagecopyresampled($out,$src,(int)((1080-$w)/2),(int)((1080-$h)/2),0,0,$w,$h,imagesx($src),imagesy($src));imagedestroy($src);
        } else {
            // Draw at one third size so built-in fonts remain readable at social feed size.
            $card=imagecreatetruecolor(360,360);$cream=imagecolorallocate($card,248,241,221);$ink=imagecolorallocate($card,38,38,35);$gold=imagecolorallocate($card,164,130,67);imagefill($card,0,0,$cream);
            $logoPath=APP_ROOT.'/assets/images/club-logo.jpg';$logo=is_file($logoPath)?@imagecreatefromjpeg($logoPath):false;
            if($logo){imagecopyresampled($card,$logo,24,24,0,0,70,70,imagesx($logo),imagesy($logo));imagedestroy($logo);}
            imagestring($card,3,106,41,'THE NETTLEBED',$ink);imagestring($card,3,106,59,'CLUB',$ink);imagefilledrectangle($card,24,110,336,112,$gold);
            $lines=club_social_card_lines($payload['title'],32,4);
            foreach($lines as $i=>$line)imagestring($card,5,24,135+$i*20,$line,$ink);
            $body=club_social_card_lines($payload['text'],43,5);
            foreach($body as $i=>$line)imagestring($card,3,24,230+$i*15,$line,$ink);
            imagestring($card,2,24,330,'News from your village club',$gold);
            imagecopyresized($out,$card,0,0,0,0,1080,1080,360,360);imagedestroy($card);
        }
        ob_start();imagejpeg($out,null,88);return ob_get_clean();
    } finally { imagedestroy($out); }
}

function club_social_card_lines(string $text,int $width,int $maxLines): array
{
    $plain=preg_replace('/\s+/',' ',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text));$lines=explode("\n",wordwrap($plain,$width,"\n",true));
    if(count($lines)>$maxLines){$lines=array_slice($lines,0,$maxLines);$lines[$maxLines-1]=rtrim(substr($lines[$maxLines-1],0,$width-3)).'...';}return $lines;
}

/** Fixed Meta host, no redirects, credentials only in the header. Never surface provider errors. */
function club_social_graph(string $method,string $route,array $fields,?callable $transport=null): array
{
    if (!preg_match('/^\d+(?:\/(?:feed|photos|media|media_publish))?$/D',$route) || !in_array($method,['GET','POST'],true)) throw new RuntimeException('Invalid social delivery route.');
    if ($transport) $result=$transport($method,$route,$fields);
    else {
        if(!extension_loaded('curl'))throw new RuntimeException('Social network transport is unavailable.');
        $url='https://graph.facebook.com/'.CLUB_META_GRAPH_VERSION.'/'.$route;
        if($method==='GET')$url.='?'.http_build_query($fields);
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.CLUB_META_PAGE_TOKEN,'Content-Type: application/x-www-form-urlencoded']]);
        if($method==='POST')curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields)]);
        $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($body===false || $status<200 || $status>=300)throw new RuntimeException('Meta delivery was not confirmed. Manual review is required before retrying.');
        $result=json_decode($body,true);
    }
    if(!is_array($result)||isset($result['error']))throw new RuntimeException('Meta delivery was not confirmed. Manual review is required before retrying.');
    return $result;
}

function club_social_remote_id(array $result): string
{
    $id=$result['id']??'';if(!is_string($id)||!preg_match('/^\d+(?:_\d+)?$/D',$id)||strlen($id)>190)throw new RuntimeException('Meta did not return a valid delivery receipt. Manual review is required.');return $id;
}

/** Called only by an explicitly authorised CLI run. Processing/failed rows never auto-retry. */
function club_social_run(bool $deliver=false,?callable $transport=null,int $limit=10): array
{
    $counts=['sent'=>0,'failed'=>0,'connection_required'=>0,'dry_run'=>0];
    if(PHP_SAPI!=='cli')throw new DomainException('Social delivery is a command line operation.');
    $pdo=db();$limit=max(1,min(50,$limit));
    if($deliver)$pdo->exec("UPDATE club_outbox SET status='failed',last_error='Worker stopped during delivery. Check Meta before any manual retry.' WHERE channel IN ('facebook','instagram') AND status='processing' AND updated_at<DATE_SUB(NOW(),INTERVAL 30 MINUTE) AND JSON_EXTRACT(payload,'$.auto_share')=true");
    // Connected destinations take precedence over old disconnected entries, preventing starvation.
    $ready=[];foreach(['facebook','instagram'] as $channel)if(club_social_connection_ready($channel))$ready[]=$pdo->quote($channel);
    $priority=$deliver && $ready ? 'CASE WHEN channel IN ('.implode(',',$ready).') THEN 0 ELSE 1 END,' : '';
    $rows=$pdo->query("SELECT id,channel FROM club_outbox WHERE channel IN ('facebook','instagram') AND status IN ('pending','connection_required') AND available_at<=NOW() AND JSON_EXTRACT(payload,'$.auto_share')=true ORDER BY ".$priority.'id LIMIT '.$limit)->fetchAll();
    foreach($rows as $candidate){
        if(!$deliver){$counts['dry_run']++;continue;}
        if(!club_social_connection_ready($candidate['channel'])){$pdo->prepare("UPDATE club_outbox SET status='connection_required',last_error='Social connection or public media configuration is required.' WHERE id=? AND status IN ('pending','connection_required')")->execute([$candidate['id']]);$counts['connection_required']++;continue;}
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT social_claim');
        try {
            $q=$pdo->prepare("SELECT * FROM club_outbox WHERE id=? AND status IN ('pending','connection_required') FOR UPDATE");$q->execute([$candidate['id']]);$row=$q->fetch();
            if($row){club_social_current_payload($row);$pdo->prepare("UPDATE club_outbox SET status='processing',attempts=attempts+1,last_error=NULL,updated_at=NOW() WHERE id=?")->execute([$row['id']]);}
            if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT social_claim');
        } catch(Throwable $e){if($owns)$pdo->rollBack();else{$pdo->exec('ROLLBACK TO SAVEPOINT social_claim');$pdo->exec('RELEASE SAVEPOINT social_claim');}$pdo->prepare("UPDATE club_outbox SET status='failed',last_error='The approved snapshot is unavailable or changed. Review this delivery.' WHERE id=? AND status IN ('pending','connection_required')")->execute([$candidate['id']]);$counts['failed']++;continue;}
        if(!$row)continue;
        try {
            $payload=club_social_current_payload($row);$caption=trim(str_starts_with($payload['text'],$payload['title'])?$payload['text']:$payload['title']."\n\n".$payload['text']);
            if($row['channel']==='facebook'){
                if($payload['image_attachment_id'] && club_social_public_media_ready())$remote=club_social_remote_id(club_social_graph('POST',CLUB_META_PAGE_ID.'/photos',['message'=>mb_substr($caption."\n\n".$payload['url'],0,60000),'url'=>club_social_image_url((int)$row['id'],$payload['source_hash'])],$transport));
                else $remote=club_social_remote_id(club_social_graph('POST',CLUB_META_PAGE_ID.'/feed',['message'=>mb_substr($caption,0,60000),'link'=>$payload['url']],$transport));
            }
            else {
                $data=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);$creation=$data['meta_creation_id']??null;
                if(!$creation){$creation=club_social_remote_id(club_social_graph('POST',CLUB_META_INSTAGRAM_ID.'/media',['caption'=>mb_substr($caption."\n\n".$payload['url'],0,2200),'image_url'=>club_social_image_url((int)$row['id'],$payload['source_hash'])],$transport));$data['meta_creation_id']=$creation;$pdo->prepare('UPDATE club_outbox SET payload=? WHERE id=?')->execute([json_encode($data,JSON_THROW_ON_ERROR),$row['id']]);}
                if(!is_string($creation)||!ctype_digit($creation))throw new RuntimeException('Invalid social media container.');
                $finished=false;
                for($poll=0;$poll<5;$poll++){$state=club_social_graph('GET',$creation,['fields'=>'status_code'],$transport);if(($state['status_code']??'')==='FINISHED'){$finished=true;break;}if(($state['status_code']??'')!=='IN_PROGRESS')break;if(!$transport&&$poll<4)sleep(2);}
                if(!$finished)throw new RuntimeException('Social media container is not ready.');
                club_social_current_payload($row);
                $remote=club_social_remote_id(club_social_graph('POST',CLUB_META_INSTAGRAM_ID.'/media_publish',['creation_id'=>$creation],$transport));
            }
            $pdo->prepare("UPDATE club_outbox SET status='sent',remote_id=?,last_error=NULL WHERE id=? AND status='processing'")->execute([$remote,$row['id']]);$counts['sent']++;
        } catch(Throwable $e){$pdo->prepare("UPDATE club_outbox SET status='failed',last_error='Social delivery was not confirmed. Check Meta and the stored container before any manual retry.' WHERE id=? AND status='processing'")->execute([$row['id']]);$counts['failed']++;}
    }
    return $counts;
}
