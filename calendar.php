<?php
require_once __DIR__.'/includes/bootstrap.php';
$selectedDate=is_string($_GET['date']??null)?$_GET['date']:'';
if(!club_valid_date($selectedDate)||substr($selectedDate,0,4)<'2000'||substr($selectedDate,0,4)>'2100')$selectedDate='';
$month=$selectedDate!==''?substr($selectedDate,0,7):(string)($_GET['month']??date('Y-m'));
if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month) || substr($month,0,4)<'2000'||substr($month,0,4)>'2100')$month=date('Y-m');
$first=new DateTimeImmutable($month.'-01',new DateTimeZone('Europe/London'));$last=$first->modify('last day of this month');$gridStart=$first->modify('monday this week');$gridEnd=$last->modify('sunday this week');
$private=club_private_events_allowed();if($private){header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');}
$occurrences=club_calendar_occurrences($gridStart->format('Y-m-d'),$gridEnd->format('Y-m-d'),$private);
$byDay=[];foreach($occurrences as $item){$day=$item['start_at']->setTime(0,0);$stop=$item['end_at'];while($day<$stop && $day<=$gridEnd){if($day>=$gridStart)$byDay[$day->format('Y-m-d')][]=$item;$day=$day->modify('+1 day');}}
$view=$_GET['view']??($forceList??false?'list':'');if(!in_array($view,['list','month'],true))$view='';
seo_set(['title'=>'Club calendar','description'=>'What’s on at Nettlebed Village Club. Browse events, regular activities and club gatherings.']);require __DIR__.'/includes/header.php';
?>
<section class="section"><div class="container" data-club-calendar data-initial-view="<?= e($view) ?>">
<div class="platform-heading"><div><p class="eyebrow">COME ALONG & JOIN IN</p><h1>Club calendar</h1><p>Good company. Something to look forward to.</p></div><?php if(club_can('content.submit')||club_can('content.publish')): ?><a class="btn btn--primary" href="<?= e(site_url('/members/event-edit.php')) ?>">+ Add an event</a><?php else: ?><a class="btn btn--secondary" href="<?= e(site_url('/login.php')) ?>">Member log in</a><?php endif ?></div>
<div class="platform-panel calendar-toolbar"><div class="hero__actions"><a class="btn btn--secondary" aria-label="Previous month" href="?month=<?= $first->modify('-1 month')->format('Y-m') ?>&view=<?= e($view) ?>">←</a><h2><?= e($first->format('F Y')) ?></h2><a class="btn btn--secondary" aria-label="Next month" href="?month=<?= $first->modify('+1 month')->format('Y-m') ?>&view=<?= e($view) ?>">→</a><a class="btn btn--secondary" href="?month=<?= date('Y-m') ?>&view=<?= e($view) ?>">Today</a></div><div class="hero__actions" role="group" aria-label="Calendar view"><a class="btn btn--secondary" href="?month=<?= $month ?>&view=month<?= $selectedDate?'&amp;date='.e($selectedDate):'' ?>" data-calendar-switch="month">Month</a><a class="btn btn--secondary" href="?month=<?= $month ?>&view=list<?= $selectedDate?'&amp;date='.e($selectedDate):'' ?>" data-calendar-switch="list">List</a><a class="btn btn--secondary" href="<?= e(site_url('/calendar.ics.php?month='.$month)) ?>">Add to my calendar</a></div></div>
<p class="text-muted">Times shown in UK time. <?= $private?'You can see public and members-only events.':'Public events are shown here. Members can log in for members-only activities.' ?></p>
<?php if($selectedDate!==''): $selectedDay=new DateTimeImmutable($selectedDate,new DateTimeZone('Europe/London')); ?>
<section id="day-events" class="platform-panel day-events" aria-labelledby="day-events-title">
<div class="section__header"><h2 id="day-events-title">Events on <?= e($selectedDay->format('l j F Y')) ?></h2></div>
<div class="hero__actions"><a class="btn btn--secondary" href="?date=<?= $selectedDay->modify('-1 day')->format('Y-m-d') ?>#day-events">← Previous day</a><a class="btn btn--secondary" href="?date=<?= $selectedDay->modify('+1 day')->format('Y-m-d') ?>#day-events">Next day →</a></div>
<?php foreach($byDay[$selectedDate]??[] as $dayEvent): ?>
<article class="day-event"><span class="status-label"><?= $dayEvent['visibility']==='members'?'Members only':'Everyone welcome' ?></span><h3><a href="<?= e(site_url('/event-detail.php?slug='.rawurlencode($dayEvent['slug']).'&date='.$dayEvent['occurrence_date'])) ?>"><?= e($dayEvent['title']) ?></a></h3><p><?= e(club_event_time_label($dayEvent,true)) ?> · <?= e($dayEvent['venue']?:'Nettlebed Village Club') ?></p></article>
<?php endforeach;if(empty($byDay[$selectedDate])): ?><p>No events scheduled for this day.</p><p>Try another date or check with the club for the latest activities.</p><?php endif ?>
</section>
<?php endif ?>
<div class="platform-panel" data-calendar-view="month"<?= $view==='list'?' hidden':'' ?>><div class="calendar-grid" aria-label="<?= e($first->format('F Y')) ?>">
<?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday): ?><div class="calendar-weekday"><strong><?= $weekday ?></strong></div><?php endforeach ?>
<?php for($day=$gridStart;$day<=$gridEnd;$day=$day->modify('+1 day')): $date=$day->format('Y-m-d'); ?>
<div class="calendar-cell<?= $day->format('m')!==$first->format('m')?' calendar-cell--muted':'' ?><?= $date===date('Y-m-d')?' calendar-cell--today':'' ?>"><a class="calendar-day-link<?= $date===$selectedDate?' calendar-day-link--selected':'' ?>" href="<?= e(site_url('/calendar.php?date='.$date.'#day-events')) ?>" aria-label="Show events for <?= e($day->format('l j F Y')) ?>"<?= $date===$selectedDate?' aria-current="true"':'' ?>><time datetime="<?= $date ?>"><?= $day->format('j') ?></time></a><?php foreach($byDay[$date]??[] as $event): ?><a class="calendar-event<?= $event['visibility']==='members'?' calendar-event--members':'' ?>" href="<?= e(site_url('/event-detail.php?slug='.rawurlencode($event['slug']).'&date='.$event['occurrence_date'])) ?>"><small><?= $event['all_day']?'All day':e($event['start_at']->format('H:i')) ?><?= $event['visibility']==='members'?' · Members':'' ?></small><strong><?= e($event['title']) ?></strong></a><?php endforeach ?></div>
<?php endfor ?></div></div>
<div data-calendar-view="list"<?= $view==='month'?' hidden':'' ?>><div class="event-list">
<?php $shown=0;foreach($occurrences as $event):if($event['end_at']<=$first||$event['start_at']>=$last->modify('+1 day'))continue;$shown++; ?>
<article class="platform-panel event-list-item"><div class="event-date"><strong><?= e($event['start_at']->format('d')) ?></strong><span><?= e($event['start_at']->format('M')) ?></span></div><div><span class="status-label"><?= $event['visibility']==='members'?'Members only':'Everyone welcome' ?></span><h3><a href="<?= e(site_url('/event-detail.php?slug='.rawurlencode($event['slug']).'&date='.$event['occurrence_date'])) ?>"><?= e($event['title']) ?></a></h3><p><?= e($event['start_at']->format('l j F')) ?> · <?= e(club_event_time_label($event)) ?><?= $event['end_at']->format('Y-m-d')!==$event['start_at']->format('Y-m-d')&&!$event['all_day']?' (ends '.e($event['end_at']->format('j M')).')':'' ?> · <?= e($event['venue']?:'Nettlebed Village Club') ?></p></div></article>
<?php endforeach;if(!$shown): ?><div class="platform-panel empty-state"><h3>A little quiet this month</h3><p>New events will appear here as the committee approves them. Contact the club for the latest activities.</p><a href="<?= e(site_url('/contact.php')) ?>">Get in touch</a></div><?php endif ?></div></div>
<p>Planning a celebration? <a href="<?= e(site_url('/contact.php?type=hall_hire')) ?>">Ask about hall hire</a>. This calendar shows events, rather than room availability.</p>
</div></section><script src="<?= e(asset_url('js/club-calendar.js')) ?>" defer></script>
<?php require __DIR__.'/includes/footer.php'; ?>
