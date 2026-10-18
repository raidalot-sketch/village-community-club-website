<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';
try {
    $result=club_social_connection_probe(in_array('--check',$argv,true));
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
    if(in_array('--check',$argv,true) && ($result['page']!=='verified' || in_array($result['instagram'],['id_mismatch','link_unavailable','not_checked'],true)))exit(1);
} catch(Throwable $e){fwrite(STDERR,"Social connection check could not complete. Review private configuration.\n");exit(1);}
