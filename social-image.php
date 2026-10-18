<?php
require_once __DIR__.'/includes/bootstrap.php';
try {
    if(!ctype_digit((string)($_GET['id']??'')) || !ctype_digit((string)($_GET['expires']??'')))throw new DomainException('Unavailable.');
    $payload=club_social_image_payload((int)$_GET['id'],(string)($_GET['hash']??''),(int)$_GET['expires'],(string)($_GET['signature']??''));
    $jpeg=club_social_jpeg($payload);
    header('Content-Type: image/jpeg');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('Content-Length: '.strlen($jpeg));echo $jpeg;
} catch(Throwable $e){http_response_code(404);header('Cache-Control: no-store');echo 'Image unavailable.';}
