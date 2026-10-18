<?php
require_once __DIR__.'/includes/bootstrap.php';
$month=(string)($_GET['month']??date('Y-m'));
if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)||substr($month,0,4)<'2000'||substr($month,0,4)>'2100'){http_response_code(400);exit('Invalid month.');}
$from=new DateTimeImmutable($month.'-01',new DateTimeZone('Europe/London'));$to=$from->modify('+1 year')->modify('-1 day');
$events=club_calendar_occurrences($from->format('Y-m-d'),$to->format('Y-m-d'),club_private_events_allowed());
header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="nettlebed-club-calendar.ics"');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
echo club_calendar_ics($events);
