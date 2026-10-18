<?php
const CLUB_UPLOAD_MAX = 30 * 1024 * 1024;
function club_storage_root(): string {
    $root = defined('CLUB_PRIVATE_STORAGE') ? CLUB_PRIVATE_STORAGE : dirname(APP_ROOT).'/_private/nettlebed-club-uploads';
    if (!is_dir($root)) mkdir($root, 0750, true);
    if (!is_file($root.'/.htaccess')) file_put_contents($root.'/.htaccess', "Require all denied\n");
    return rtrim($root, '/\\');
}
function club_upload_files(array $files): array {
    if (empty($files['name'])) return [];
    if (!is_array($files['name'])) $files = array_map(fn($value)=>[$value], $files);
    $result=[];
    foreach($files['name'] as $i=>$name) {
        if (($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        $result[]=['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
    }
    if(count($result)>3) throw new RuntimeException('Choose no more than three attachments.');
    return $result;
}
function club_prepare_attachment(array $file, bool $httpUpload=true): array {
    if(!$httpUpload && PHP_SAPI!=='cli') throw new RuntimeException('Invalid upload context.');
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('A file could not be uploaded. Please choose it again.');
    $src=$file['tmp_name']??'';
    if(!is_file($src) || ($httpUpload && !is_uploaded_file($src))) throw new RuntimeException('The upload could not be verified.');
    $bytes=filesize($src);
    if($bytes===0 || $bytes>CLUB_UPLOAD_MAX) throw new RuntimeException('Each file must be no larger than 30 MB.');
    $name=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u','',basename(str_replace('\\','/',(string)$file['name']))),0,180);
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($src);
    $target=bin2hex(random_bytes(20)); $root=club_storage_root();
    if(in_array($mime,['image/jpeg','image/png','image/webp'],true)) {
        $dimensions=@getimagesize($src);
        if(!$dimensions || $dimensions[0]*$dimensions[1]>60000000) throw new RuntimeException('This photograph has too many pixels to process safely. Use the photo preview to resize it first.');
        $image=@imagecreatefromstring(file_get_contents($src));
        if(!$image) throw new RuntimeException('This photograph could not be read. Try another image.');
        if($mime==='image/jpeg' && function_exists('exif_read_data')) {
            $orientation=@exif_read_data($src)['Orientation']??1;
            if(in_array($orientation,[2,4,5,7],true)) imageflip($image,IMG_FLIP_HORIZONTAL);
            $angle=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};
            if($angle){$rotated=imagerotate($image,$angle,0);imagedestroy($image);$image=$rotated;}
        }
        $scale=min(1,2000/max(imagesx($image),imagesy($image)));
        $out=imagecreatetruecolor(max(1,(int)round(imagesx($image)*$scale)),max(1,(int)round(imagesy($image)*$scale)));
        imagealphablending($out,false);imagesavealpha($out,true);
        imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));
        imagecopyresampled($out,$image,0,0,0,0,imagesx($out),imagesy($out),imagesx($image),imagesy($image));
        $target.='.webp';
        if(!imagewebp($out,$root.'/'.$target,84)) throw new RuntimeException('The photograph could not be saved.');
        imagedestroy($image);imagedestroy($out);$mime='image/webp';
    } elseif($ext==='pdf' && $mime==='application/pdf' && str_starts_with(file_get_contents($src,false,null,0,8),'%PDF-')) {
        $target.='.pdf';if(!copy($src,$root.'/'.$target)) throw new RuntimeException('The document could not be saved.');
    } elseif(in_array($ext,['docx','xlsx'],true) && in_array($mime,['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],true)) {
        $zip=new ZipArchive();if($zip->open($src)!==true) throw new RuntimeException('The Office document could not be verified.');
        $expected=$ext==='docx'?'word/document.xml':'xl/workbook.xml';
        $valid=$zip->locateName('[Content_Types].xml')!==false && $zip->locateName($expected)!==false;
        $total=0;
        for($i=0;$i<$zip->numFiles;$i++){ $entry=$zip->statIndex($i);$total+=$entry['size'];if(str_contains(strtolower($entry['name']),'vbaproject') || $total>150*1024*1024)$valid=false; }
        $zip->close();if(!$valid) throw new RuntimeException('Use a standard Word or Excel document without macros.');
        $target.='.'. $ext;if(!copy($src,$root.'/'.$target))throw new RuntimeException('The document could not be saved.');
        $mime=$ext==='docx'?'application/vnd.openxmlformats-officedocument.wordprocessingml.document':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } else throw new RuntimeException('Use a photo, PDF, Word (.docx) or Excel (.xlsx) file. iPhone HEIC photos are converted in the photo preview.');
    chmod($root.'/'.$target,0640);
    return ['storage_path'=>$target,'original_name'=>$name,'mime'=>$mime,'bytes'=>filesize($root.'/'.$target)];
}
function club_store_uploads(array $files,string $ownerType,int $ownerId,?int $userId=null): array {
    if(!in_array($ownerType,['post','event','revision','contact','gallery','settings'],true))throw new RuntimeException('Invalid attachment context.');
    $incoming=club_upload_files($files);if(!$incoming)return [];
    $stmt=db()->prepare('SELECT COUNT(*) FROM club_attachments WHERE owner_type=? AND owner_id=?');$stmt->execute([$ownerType,$ownerId]);
    if(count($incoming)+(int)$stmt->fetchColumn()>3)throw new RuntimeException('An enquiry or contribution can have up to three attachments.');
    $stored=[];$ids=[];$owns=!db()->inTransaction();if($owns)db()->beginTransaction();
    try {
        foreach($incoming as $file){$a=club_prepare_attachment($file);$stored[]=$a['storage_path'];
            db()->prepare('INSERT INTO club_attachments(owner_type,owner_id,user_id,storage_path,original_name,mime,bytes) VALUES(?,?,?,?,?,?,?)')->execute([$ownerType,$ownerId,$userId,$a['storage_path'],$a['original_name'],$a['mime'],$a['bytes']]);$ids[]=(int)db()->lastInsertId();}
        if($owns)db()->commit();return $ids;
    } catch(Throwable $ex){if($owns && db()->inTransaction())db()->rollBack();foreach($stored as $path)@unlink(club_storage_root().'/'.$path);throw $ex;}
}
function club_attachment_list(string $ownerType,int $ownerId): array {
    $stmt=db()->prepare('SELECT id,original_name,mime,bytes FROM club_attachments WHERE owner_type=? AND owner_id=? ORDER BY id');$stmt->execute([$ownerType,$ownerId]);return $stmt->fetchAll();
}
function club_media_url(int $id): string {return site_url('/media.php?id='.$id);}
function club_attachment_allowed(array $a): bool {
    $user=club_current_user();$uid=(int)($user['id']??0);
    if($a['owner_type']==='contact'){
        $q=db()->prepare('SELECT category FROM contact_enquiries WHERE id=?');$q->execute([$a['owner_id']]);$category=$q->fetchColumn();
        if(!$category)return false;
        if(club_can('enquiries.all') || ($category==='membership' && club_can('enquiries.membership')))return true;
        if(!club_can('enquiries.handle'))return false;
        $q=db()->prepare('SELECT 1 FROM club_enquiry_routes WHERE user_id=? AND category=?');$q->execute([$uid,$category]);return(bool)$q->fetchColumn();
    }
    if($a['owner_type']==='revision'){
        if(club_can('content.publish'))return true;
        $q=db()->prepare('SELECT * FROM club_revisions WHERE id=?');$q->execute([$a['owner_id']]);$revision=$q->fetch();
        if(!$uid || !$revision)return false;
        if((int)$revision['author_user_id']===$uid)return true;
        $table=match($revision['owner_type']){'post'=>'club_posts','event'=>'events',default=>null};
        if(!$table)return false;
        $q=db()->prepare("SELECT author_user_id FROM $table WHERE id=?");$q->execute([$revision['owner_id']]);return (int)$q->fetchColumn()===$uid;
    }
    if(in_array($a['owner_type'],['post','event'],true)){
        $table=$a['owner_type']==='post'?'club_posts':'events';
        $q=db()->prepare("SELECT * FROM $table WHERE id=?" . ($table==='events'?' AND deleted_at IS NULL':''));$q->execute([$a['owner_id']]);$item=$q->fetch();if(!$item)return false;
        if(club_can('content.publish') || ($uid && (int)$item['author_user_id']===$uid))return true;
        return $item['status']==='published' && (($item['visibility']??'public')==='public'||club_is_active_member());
    }
    return club_can('settings.manage');
}
