<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';
$deliver=in_array('--deliver',$argv,true);
try { echo json_encode(club_social_run($deliver),JSON_THROW_ON_ERROR).PHP_EOL; }
catch(Throwable $e){fwrite(STDERR,"Social worker could not complete. Review private server configuration and database availability.\n");exit(1);}
