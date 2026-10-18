<?php
require_once __DIR__.'/includes/bootstrap.php';
$stmt=db()->prepare('SELECT * FROM club_attachments WHERE id=?');$stmt->execute([(int)($_GET['id']??0)]);$a=$stmt->fetch();
if(!$a || !club_attachment_allowed($a)){http_response_code(404);exit('File not found.');}
$root=realpath(club_storage_root());$path=realpath($root.'/'.$a['storage_path']);
if(!$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path)){http_response_code(404);exit;}
header('Content-Type: '.$a['mime']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; sandbox");
$inline=str_starts_with($a['mime'],'image/');
header('Content-Disposition: '.($inline?'inline':'attachment')."; filename*=UTF-8''".rawurlencode($a['original_name']));
header('Content-Length: '.filesize($path));readfile($path);
