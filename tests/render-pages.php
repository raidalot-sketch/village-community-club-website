<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
if (DB_NAME !== 'nettlebed_preview') throw new RuntimeException('Preview database required.');
$root=dirname(__DIR__);
if (isset($argv[1])) {
    chdir($root);
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['SCRIPT_NAME']='/'.$argv[1];
    $_SERVER['REQUEST_URI']='/'.$argv[1];
    $_SERVER['HTTP_HOST']='127.0.0.1:4186';
    $_COOKIE['vcc_cookie_consent']='essential';
    if (str_starts_with($argv[1], 'admin/')) {
        session_start();
        $_SESSION['admin_id']=1;
        $_SESSION['last_activity']=time();
    }
    include $root.'/'.$argv[1];
    exit;
}
$pages=['index.php','about.php','hall-hire.php','membership.php','facilities.php','contact.php','events.php','news.php','newsletters.php','gallery.php','committee.php','privacy-policy.php','cookie-policy.php','accessibility.php','sitemap.php','admin/index.php','admin/settings.php','admin/pages.php'];
foreach ($pages as $page) {
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($page);
    exec($command.' 2>&1',$lines,$status);
    $html=implode("\n",$lines); $lines=[];
    if ($status!==0 || preg_match('/Fatal error|Warning:|Uncaught|Deprecated:/',$html)) throw new RuntimeException('Render failed: '.$page.' '.substr($html,0,300));
    if ($page==='sitemap.php') {
        if (!str_contains($html,'/hall-hire.php')) throw new RuntimeException('Hall hire missing from sitemap');
    } elseif (!str_contains($html,'<html')) throw new RuntimeException('Missing HTML: '.$page);
    if ($page==='admin/settings.php' && !str_contains($html,'name="hero_image"')) throw new RuntimeException('Homepage photo upload control missing');
    if (!str_contains($page,'policy') && $page!=='accessibility.php' && preg_match('/Little Meadow|\[SAMPLE\]|PLACEHOLDER HERO/',$html)) throw new RuntimeException('Sample content remains: '.$page);
    echo 'PASS: '.$page."\n";
}
