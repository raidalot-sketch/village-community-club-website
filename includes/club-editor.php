<?php
/** Author-scoped staging; raw staging tokens are never persisted in the database. */
function club_editor_require_author():array {
    $user=club_current_user();
    if(!$user||(!club_can('content.submit')&&!club_can('content.publish')))throw new RuntimeException('An active membership is required to edit posts.');
    return $user;
}
function club_editor_start(?int $postId=null):array {
    $user=club_editor_require_author();
    if($postId){$post=club_community_record('post',$postId);if(!$post||!club_content_editable($post))throw new RuntimeException('You cannot edit this post.');}
    $token=bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO club_editor_sessions(user_id,post_id,token_hash) VALUES(?,?,?)')->execute([$user['id'],$postId,hash('sha256',$token)]);
    return ['id'=>(int)db()->lastInsertId(),'token'=>$token];
}
function club_editor_validate(int $id,string $token,?int $postId):array {
    $user=club_editor_require_author();$q=db()->prepare('SELECT * FROM club_editor_sessions WHERE id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)');$q->execute([$id]);$s=$q->fetch();
    if(!$s||(int)$s['user_id']!==(int)$user['id']||($s['post_id']===null?null:(int)$s['post_id'])!==$postId||!hash_equals($s['token_hash'],hash('sha256',$token)))throw new RuntimeException('This editor session is not authorised. Reopen the editor.');
    if($postId){$post=club_community_record('post',$postId);if(!$post||!club_content_editable($post))throw new RuntimeException('You cannot edit this post.');}
    return $s;
}
function club_editor_media(array $session):array {
    if(!array_key_exists('user_id',$session)){
        $q=db()->prepare('SELECT post_id FROM club_editor_sessions WHERE id=?');$q->execute([(int)($session['id']??0)]);$postId=$q->fetchColumn();
        $session=club_editor_validate((int)($session['id']??0),(string)($session['token']??''),$postId===false||$postId===null?null:(int)$postId);
    }
    $user=club_editor_require_author();
    if((int)$session['user_id']!==(int)$user['id'])throw new RuntimeException('This editor session is not authorised.');
    $params=[(int)$session['id']];$where="(owner_type='editor' AND owner_id=?)";
    if(!empty($session['post_id'])){
        $post=club_community_record('post',(int)$session['post_id']);if(!$post||!club_content_editable($post))throw new RuntimeException('You cannot edit this post.');
        $where.=" OR (owner_type='post' AND owner_id=?) OR (owner_type='revision' AND owner_id=(SELECT id FROM club_revisions WHERE owner_type='post' AND owner_id=? AND status='submitted' ORDER BY id DESC LIMIT 1))";
        $params[]=(int)$session['post_id'];$params[]=(int)$session['post_id'];
    }
    $q=db()->prepare('SELECT id,original_name,mime,bytes FROM club_attachments WHERE '.$where.' ORDER BY id');$q->execute($params);
    return array_map(fn($a)=>['id'=>(int)$a['id'],'url'=>club_media_url((int)$a['id']),'name'=>$a['original_name'],'mime'=>$a['mime'],'bytes'=>(int)$a['bytes']],$q->fetchAll());
}
function club_editor_image_id(string $url):?int {
    // Only the exact protected media route on this site's origin is eligible.
    $parts=parse_url($url);$base=parse_url(site_url('/media.php'));
    if(!$parts||isset($parts['fragment'])||isset($parts['user'])||isset($parts['pass']))return null;
    if(isset($parts['host'])&&(strtolower($parts['host'])!==strtolower($base['host']??'')||($parts['scheme']??'')!==($base['scheme']??'')||($parts['port']??null)!==($base['port']??null)))return null;
    if(!isset($parts['host'])&&isset($parts['scheme']))return null;
    if(($parts['path']??'')!==($base['path']??'/media.php')||!preg_match('/^id=([1-9][0-9]*)$/D',$parts['query']??'',$m))return null;
    return (int)$m[1];
}
function club_editor_sanitize(string $html,array $allowedMediaIds=[],bool $strictImages=true):string {
    if($strictImages&&mb_strlen($html)>50000)throw new InvalidArgumentException('Enter details of up to 50,000 characters.');
    $doc=new DOMDocument('1.0','UTF-8');$prior=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="club-editor-root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($prior);
    $root=$doc->getElementById('club-editor-root');if(!$root)return '';
    $allowed=['p','br','h2','h3','strong','b','em','i','u','s','del','blockquote','ul','ol','li','a','figure','figcaption','img'];
    $walk=function(DOMNode $node)use(&$walk,$allowed,$allowedMediaIds,$strictImages):string {
        if($node instanceof DOMText)return e($node->nodeValue);
        if(!$node instanceof DOMElement)return '';
        $tag=strtolower($node->tagName);
        if(in_array($tag,['script','style','iframe','object','embed','svg','math','template','noscript'],true))return '';
        $children='';foreach($node->childNodes as $child)$children.=$walk($child);
        if(!in_array($tag,$allowed,true))return $children;
        $attrs='';
        if($tag==='img'){
            $id=club_editor_image_id(trim($node->getAttribute('src')));
            if(!$id||!in_array($id,array_map('intval',$allowedMediaIds),true)){if($strictImages)throw new InvalidArgumentException('Choose photographs uploaded in this post editor.');return '';}
            return '<img src="'.e(club_media_url($id)).'" alt="'.e(mb_substr($node->getAttribute('alt'),0,300)).'" loading="lazy">';
        }
        if($tag==='a'){
            $href=trim($node->getAttribute('href'));$scheme=strtolower(parse_url($href,PHP_URL_SCHEME)??'');
            if(!preg_match('/[\x00-\x20\x7f]/',$href)&&in_array($scheme,['https','http','mailto','tel'],true))$attrs=' href="'.e($href).'" rel="noopener noreferrer"';
        }
        if(in_array($tag,['p','h2','h3','blockquote','li','figcaption'],true)){
            if(preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\s*(?:;|$)/i',$node->getAttribute('style'),$m))$attrs.=' style="text-align:'.strtolower($m[1]).'"';
            else foreach(['center','right','justify'] as $align)if(in_array('ql-align-'.$align,explode(' ',$node->getAttribute('class')),true))$attrs.=' style="text-align:'.$align.'"';
        }
        return '<'.$tag.$attrs.'>'.($tag==='br'?'':$children.'</'.$tag.'>');
    };
    $result='';foreach($root->childNodes as $child)$result.=$walk($child);if($strictImages&&mb_strlen($result)>50000)throw new InvalidArgumentException('The formatted post can have up to 50,000 characters.');return trim($result);
}
function club_editor_inline_ids(string $html):array {
    preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"/i',$html,$matches);$ids=[];
    foreach($matches[1] as $url){$id=club_editor_image_id(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'));if($id)$ids[]=$id;}
    return array_values(array_unique($ids));
}
function club_editor_render(array $post):string {
    $ids=array_map('intval',array_column(array_filter(club_attachment_list('post',(int)$post['id']),fn($a)=>str_starts_with($a['mime'],'image/')),'id'));
    return club_editor_sanitize($post['content'],$ids,false);
}
/** Move only referenced staged photos; retain saved attachments for existing galleries. */
function club_editor_attach(array $session,array $payload,string $ownerType,int $ownerId):void {
    $ids=club_editor_inline_ids($payload['content']);if(!empty($payload['cover_attachment_id']))$ids[]=(int)$payload['cover_attachment_id'];
    foreach(array_unique($ids) as $id)db()->prepare("UPDATE club_attachments SET owner_type=?,owner_id=? WHERE id=? AND owner_type='editor' AND owner_id=? AND user_id=?")->execute([$ownerType,$ownerId,$id,$session['id'],$session['user_id']]);
    club_editor_attachment_limit($ownerType,$ownerId,!empty($session['post_id'])?(int)$session['post_id']:null);
}
function club_editor_attachment_limit(string $ownerType,int $ownerId,?int $postId=null):void {
    $q=db()->prepare('SELECT COUNT(*) FROM club_attachments WHERE owner_type=? AND owner_id=?');$q->execute([$ownerType,$ownerId]);
    // Published originals are also accessible in a pending revision and count toward its cap.
    $count=(int)$q->fetchColumn();
    if($ownerType==='revision'&&$postId){$q=db()->prepare("SELECT COUNT(*) FROM club_attachments WHERE owner_type='post' AND owner_id=?");$q->execute([$postId]);$count+=(int)$q->fetchColumn();}
    if($count>3)throw new InvalidArgumentException('A post can have up to three attachments.');
}
