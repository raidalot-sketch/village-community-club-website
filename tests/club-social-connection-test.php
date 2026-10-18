<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$cases=['missing','dry','success','wrong-page','wrong-instagram','unlinked','provider-error'];
$case=$argv[1]??null;
if($case===null){
    foreach($cases as $scenario){$process=proc_open([PHP_BINARY,__FILE__,$scenario],[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Could not start isolated test.');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);if($status!==0)throw new RuntimeException('Connection fixture failed: '.$scenario.' '.$out.$err);}
    echo 'PASS: '.count($cases)." isolated read-only connection scenarios; no live calls.\n";return;
}
if(!in_array($case,$cases,true))throw new RuntimeException('Unknown fixture.');
define('SITE_PREVIEW',true);define('CLUB_SOCIAL_PUBLISH_ENABLED',false);define('CLUB_SOCIAL_PUBLIC_MEDIA_READY',false);
if($case!=='missing'){define('CLUB_META_PAGE_ID','123');define('CLUB_META_PAGE_TOKEN','fixture-private-token-never-print');define('CLUB_META_GRAPH_VERSION','v25.0');define('CLUB_META_INSTAGRAM_ID','456');}
require_once __DIR__.'/../includes/club-social.php';
function connection_check(bool $pass,string $label):void{if(!$pass)throw new RuntimeException($label);}
$calls=[];$transport=function($method,$route,$fields)use(&$calls,$case){$calls[]=[$method,$route,$fields];connection_check($method==='GET'&&$route==='123'&&$fields===['fields'=>'id,name,instagram_business_account{id,username}'],'Only exact read probe allowed');if($case==='provider-error')throw new RuntimeException('fixture-private-token-never-print raw provider details');return ['id'=>$case==='wrong-page'?'999':'123','name'=>'fixture-private-token-never-print','instagram_business_account'=>$case==='unlinked'?null:['id'=>$case==='wrong-instagram'?'999':'456','username'=>'fixture-private-token-never-print']];};
$result=club_social_connection_probe($case!=='dry',$transport);
connection_check(!str_contains(json_encode($result),'fixture-private-token-never-print'),'Diagnostics must never expose raw credentials or provider metadata');
connection_check($result['configuration']['publish_enabled']===false && $result['configuration']['preview']===true,'Read probe must preserve disabled publishing and preview');
connection_check($result['publishing_permissions']==='not_verified','Read access must not claim publishing permission');
if($case==='missing')connection_check(!$calls&&$result['page']==='configuration_required'&&!$result['checked'],'Missing config must make no call');
elseif($case==='dry')connection_check(!$calls&&$result['page']==='not_checked'&&!$result['checked'],'Default must make no call');
else {
    connection_check(count($calls)===1,'Exactly one read request expected');
    match($case){
        'success'=>connection_check($result['page']==='verified'&&$result['instagram']==='verified'&&$result['checked'],'Matching Page and linkage should verify while publishing disabled'),
        'wrong-page'=>connection_check($result['page']==='id_mismatch'&&$result['instagram']==='not_checked','Wrong Page must fail before linkage verification'),
        'wrong-instagram'=>connection_check($result['page']==='verified'&&$result['instagram']==='id_mismatch','Wrong Instagram linkage must fail'),
        'unlinked'=>connection_check($result['instagram']==='link_unavailable','Absent linkage must remain honestly unavailable'),
        'provider-error'=>connection_check($result['page']==='check_failed'&&!$result['checked'],'Provider exceptions must become generic safe errors'),
    };
}
connection_check(!club_social_connection_ready('facebook')&&!club_social_connection_ready('instagram'),'Read probe must never enable delivery');
