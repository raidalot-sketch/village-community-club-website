<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';
if(!str_contains(DB_NAME,'preview'))throw new RuntimeException('Use an isolated preview database.');
$temp=sys_get_temp_dir().'/club-upload-'.bin2hex(random_bytes(8));mkdir($temp,0700);$created=[];$checks=0;
function upload_check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function upload_reject(callable $call,string $message):void{try{$call();}catch(RuntimeException $e){upload_check(true,$message);return;}throw new RuntimeException($message);}
function test_upload(string $path,string $name):array{return ['name'=>$name,'tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)];}
try{
 $source=$temp.'/photo.png';$im=imagecreatetruecolor(4000,2500);imagefill($im,0,0,imagecolorallocate($im,210,180,80));imagepng($im,$source);imagedestroy($im);
 $a=club_prepare_attachment(test_upload($source,'holiday.png'),false);$created[]=$a['storage_path'];$size=getimagesize(club_storage_root().'/'.$a['storage_path']);
 upload_check($size[0]===2000 && $size[1]===1250 && $a['mime']==='image/webp','Large photo resized with aspect ratio');
 upload_check($a['bytes']<filesize($source),'Optimised image replaces larger source');
 $pdf=$temp.'/document.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");$a=club_prepare_attachment(test_upload($pdf,'../minutes.pdf'),false);$created[]=$a['storage_path'];
 upload_check($a['original_name']==='minutes.pdf' && hash_file('sha256',$pdf)===hash_file('sha256',club_storage_root().'/'.$a['storage_path']),'PDF stays intact with safe original name');
 $fake=$temp.'/fake.pdf';file_put_contents($fake,'<?php echo "no";');upload_reject(fn()=>club_prepare_attachment(test_upload($fake,'fake.pdf'),false),'Renamed script rejected');
 foreach(['docx'=>'word/document.xml','xlsx'=>'xl/workbook.xml'] as $extension=>$entry){$path=$temp.'/file.'.$extension;$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE);$zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString($entry,'<document/>');$zip->close();$a=club_prepare_attachment(test_upload($path,'minutes.'.$extension),false);$created[]=$a['storage_path'];upload_check(hash_file('sha256',$path)===hash_file('sha256',club_storage_root().'/'.$a['storage_path']),'Office document intact');}
 $macro=$temp.'/macro.docx';$zip=new ZipArchive();$zip->open($macro,ZipArchive::CREATE);$zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString('word/document.xml','<document/>');$zip->addFromString('word/vbaProject.bin','macro');$zip->close();upload_reject(fn()=>club_prepare_attachment(test_upload($macro,'macro.docx'),false),'Macro package rejected');
 $big=$temp.'/big.pdf';$fp=fopen($big,'wb');ftruncate($fp,CLUB_UPLOAD_MAX+1);fclose($fp);upload_reject(fn()=>club_prepare_attachment(test_upload($big,'big.pdf'),false),'Original over30MB rejected');
 upload_reject(fn()=>club_upload_files(['name'=>['a','b','c','d'],'error'=>[0,0,0,0]]),'Four files rejected');
 $q=db()->query("SELECT * FROM club_attachments WHERE owner_type='contact' LIMIT 1");if($a=$q->fetch())upload_check(!club_attachment_allowed($a),'Anonymous contact attachment denied');
 echo "$checks upload checks passed.\n";
}finally{foreach($created as $path)@unlink(club_storage_root().'/'.$path);foreach(glob($temp.'/*') as $path)unlink($path);rmdir($temp);}
