<?php
// Public homepage calendar: no private event titles or counts are exposed.
$homeMonth=is_string($_GET['calendar_month']??null)?$_GET['calendar_month']:date('Y-m');
if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$homeMonth)||substr($homeMonth,0,4)<'2000'||substr($homeMonth,0,4)>'2100')$homeMonth=date('Y-m');
$homeFirst=new DateTimeImmutable($homeMonth.'-01',new DateTimeZone('Europe/London'));
$homeStart=$homeFirst->modify('monday this week');$homeEnd=$homeFirst->modify('last day of this month')->modify('sunday this week');
$homeDays=[];
foreach(club_calendar_occurrences($homeStart->format('Y-m-d'),$homeEnd->format('Y-m-d'),false) as $homeEvent){
 for($homeDay=$homeEvent['start_at']->setTime(0,0);$homeDay<$homeEvent['end_at']&&$homeDay<=$homeEnd;$homeDay=$homeDay->modify('+1 day'))if($homeDay>=$homeStart)$homeDays[$homeDay->format('Y-m-d')]=($homeDays[$homeDay->format('Y-m-d')]??0)+1;
}
?>
<div class="home-calendar" id="home-calendar">
<div class="home-calendar__heading"><a href="?calendar_month=<?= $homeFirst->modify('-1 month')->format('Y-m') ?>#home-calendar" aria-label="Previous calendar month">←</a><h3><?= e($homeFirst->format('F Y')) ?></h3><a href="?calendar_month=<?= $homeFirst->modify('+1 month')->format('Y-m') ?>#home-calendar" aria-label="Next calendar month">→</a></div>
<p class="home-calendar__hint">Choose a date to see what’s on.</p>
<div class="home-calendar__grid" aria-label="<?= e($homeFirst->format('F Y')) ?>">
<?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $homeWeekday): ?><span class="home-calendar__weekday"><?= $homeWeekday ?></span><?php endforeach ?>
<?php for($homeDay=$homeStart;$homeDay<=$homeEnd;$homeDay=$homeDay->modify('+1 day')):$homeDate=$homeDay->format('Y-m-d');$homeCount=$homeDays[$homeDate]??0; ?>
<a class="home-calendar__day<?= $homeDay->format('m')!==$homeFirst->format('m')?' home-calendar__day--muted':'' ?><?= $homeDate===date('Y-m-d')?' home-calendar__day--today':'' ?>" href="<?= e(site_url('/calendar.php?date='.$homeDate.'#day-events')) ?>" aria-label="Show events for <?= e($homeDay->format('l j F Y')) ?><?= $homeCount?' · '.$homeCount.' public '.($homeCount===1?'event':'events'):'' ?>"><time datetime="<?= $homeDate ?>"><?= $homeDay->format('j') ?></time><?php if($homeCount): ?><span class="home-calendar__dot" aria-hidden="true"></span><?php endif ?></a>
<?php endfor ?>
</div><p class="home-calendar__legend"><span class="home-calendar__dot" aria-hidden="true"></span> Public events scheduled</p>
</div>
