<?php
$first = new DateTimeImmutable($calendar['month'] . '-01');
$monthName = $first->format('F Y');
$selectedDay = is_string($workday) ? ($calendar['days'][$workday] ?? null) : null;
$legend = ['on_time' => 'On time', 'late' => 'Late', 'missing' => 'Absent', 'leave' => 'Approved leave',
    'holiday' => 'Public holiday', 'rest' => 'Rest day', 'scheduled' => 'Scheduled', 'pending' => 'Pending review'];
?>
<div class="attendance-heading">
  <div><h3><?= e($monthName) ?></h3><p class="muted"><?= e($subject) ?> · MYT</p></div>
  <div class="attendance-actions">
    <?php foreach (['-1 month' => 'Previous month', '+1 month' => 'Next month'] as $offset => $label): ?>
      <?php $targetMonth = $first->modify($offset)->format('Y-m'); ?>
      <?php if ($targetMonth >= '1900-01' && $targetMonth <= '9998-12'): ?>
        <a class="btn btn-secondary" data-monthly-link href="<?= e(attendance_report_links($calendarQuery, $page, ['month' => $targetMonth])) ?>#attendance-calendar"><?= te($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php if (!array_filter(array_column($calendar['days'], 'shifts'))): ?>
  <p class="muted"><?= te('No scheduled shifts for this employee in the selected month and locations.') ?></p>
<?php endif; ?>
<ul class="attendance-calendar-legend" aria-label="<?= te('Calendar legend') ?>">
  <?php foreach ($legend as $state => $label): ?><li><span class="attendance-calendar-dot calendar-<?= e($state) ?>" aria-hidden="true"></span><?= te($label) ?></li><?php endforeach; ?>
</ul>
<div class="attendance-calendar-grid" aria-label="<?= e($monthName) ?>">
  <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
    <span class="attendance-weekday" aria-hidden="true"><?= te($weekday) ?></span>
  <?php endforeach; ?>
  <?php for ($i = 1; $i < (int)$first->format('N'); $i++): ?><span aria-hidden="true"></span><?php endfor; ?>
  <?php foreach ($calendar['days'] as $date => $day):
    $state = $day['state'];
    $caption = $legend[$state] ?? ATTENDANCE_LABELS[$state];
  ?>
    <a class="attendance-calendar-day calendar-<?= e($state) ?>" data-monthly-link <?= $date === $workday ? 'aria-current="date"' : '' ?>
       aria-label="<?= e(date('D, j M', strtotime($date)) . ' · ' . t($caption)) ?>"
       href="<?= e(attendance_report_links($calendarQuery, $page, ['workday' => $date])) ?>#calendar-day">
      <strong><?= e((int)substr($date, -2)) ?></strong><span class="attendance-day-name"><?= te($caption) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<p class="muted"><?= te('Overnight shifts stay on the date they start.') ?></p>

<section class="attendance-calendar-detail" id="calendar-day" aria-labelledby="calendar-day-heading">
  <?php if ($selectedDay): ?>
    <h3 id="calendar-day-heading" tabindex="-1"><?= e(date('l, j F Y', strtotime($workday))) ?></h3>
    <p><?= attendance_badge($selectedDay['state']) ?><?= $selectedDay['holiday'] !== null ? ' · ' . e($selectedDay['holiday']) : '' ?></p>
    <?php foreach ($selectedDay['shifts'] as $entry): ?>
      <article class="attendance-review">
        <div class="attendance-heading"><strong><?= e($entry['lot_name']) ?></strong><?= attendance_badge($entry['state']) ?></div>
        <dl class="attendance-facts">
          <div><dt><?= te('Scheduled shift') ?></dt><dd><?= e(attendance_clock($entry['starts_at'])) ?> – <?= e(attendance_clock($entry['ends_at'])) ?></dd></div>
          <div><dt><?= te('Check In') ?></dt><dd><?= e(attendance_clock($entry['in'])) ?> · <?= attendance_badge($entry['in_zone']) ?></dd></div>
          <div><dt><?= te('Check Out') ?></dt><dd><?= e(attendance_clock($entry['out'])) ?> · <?= attendance_badge($entry['out_zone']) ?></dd></div>
          <div><dt><?= te('Working duration') ?></dt><dd><?= e(attendance_duration($entry)) ?></dd></div>
        </dl>
        <?php if ($entry['calendar_photo']): ?>
          <img class="attendance-preview" src="api/attendance-photo.php?shift=<?= $entry['id'] ?>" alt="<?= te('Check-in photo') ?>" loading="lazy">
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(attendance_report_links($calendarQuery, $page,
          ['workday' => $workday, 'shift' => $entry['id']])) ?><?= $actor['role'] === 'staff' ? '#shift-attendance' : '#record' ?>"><?= te('View details and reviews') ?></a>
      </article>
    <?php endforeach; ?>
    <?php if (!$selectedDay['shifts']): ?><p><?= te('No scheduled shift to show for this day.') ?></p><?php endif; ?>
  <?php else: ?>
    <h3 id="calendar-day-heading"><?= te('Daily attendance') ?></h3>
    <p><?= te('Choose a date to view its shift times, GPS status, and available evidence.') ?></p>
  <?php endif; ?>
</section>
<details class="attendance-details">
  <summary><?= te('Monthly attendance summary') ?></summary>
  <?php attendance_performance_tiles($calendar['performance']); ?>
</details>
<?php attendance_performance_help(); ?>
