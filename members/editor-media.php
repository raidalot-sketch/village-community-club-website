<?php
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);throw new InvalidArgumentException('Use POST to upload photographs.');}
    if(empty($_POST['csrf_token'])||empty($_SESSION['csrf_token'])||!is_string($_POST['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'])){http_response_code(403);throw new RuntimeException('Your session has expired. Reopen the editor.');}
    $q=db()->prepare('SELECT post_id FROM club_editor_sessions WHERE id=?');$q->execute([(int)($_POST['editor_session_id']??0)]);$postId=$q->fetchColumn();
    $session=club_editor_validate((int)($_POST['editor_session_id']??0),(string)($_POST['editor_session_token']??''),$postId===false||$postId===null?null:(int)$postId);
    $files=$_FILES['photos']??[];$incoming=club_upload_files($files);if(!$incoming)throw new InvalidArgumentException('Choose photographs to upload.');
    foreach($incoming as $file){$mime=is_file($file['tmp_name'])?(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']):'';if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new InvalidArgumentException('Choose a JPEG, PNG or WebP photograph.');}
    db()->beginTransaction();$q=db()->prepare('SELECT id FROM club_editor_sessions WHERE id=? FOR UPDATE');$q->execute([$session['id']]);
    if(!empty($session['post_id'])){$q=db()->prepare('SELECT id FROM club_posts WHERE id=? FOR UPDATE');$q->execute([$session['post_id']]);}
    if(count(club_editor_media($session))+count($incoming)>3)throw new InvalidArgumentException('A post can have up to three attachments.');
    $ids=club_store_uploads($files,'editor',(int)$session['id'],(int)$session['user_id']);$media=array_values(array_filter(club_editor_media($session),fn($a)=>in_array($a['id'],$ids,true)));
    db()->commit();echo json_encode(['media'=>$media],JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?400:403);echo json_encode(['error'=>$e instanceof PDOException?'The photographs could not be saved. Try again.':$e->getMessage()],JSON_THROW_ON_ERROR);}
