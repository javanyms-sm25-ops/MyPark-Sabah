<?php
$days = array_slice($report['daily'], -14, null, true);
$items = [];
foreach ($report['monthly'] as $name => $count) {
    $rate = attendance_report_rate($count['present'], $count['present'] + $count['absent']);
    $items[] = ['name' => date('M Y', strtotime($name . '-01')), 'size' => $rate ?? 0,
        'value' => attendance_report_display('attendance_rate', $rate)];
}
$statuses = [];
foreach ($report['statuses'] as $state => $count) {
    $statuses[] = ['name' => t(ATTENDANCE_LABELS[$state]), 'size' => $count, 'value' => $count];
}
$places = [];
foreach ($report['locations'] as $locationRow) {
    $places[] = ['name' => $locationRow['name'], 'size' => $locationRow['present'], 'value' => $locationRow['present']];
}
?>
<details class="ops-panel attendance-panel attendance-trends">
  <summary><?= te('Attendance Trends') ?></summary>
  <p class="muted"><?= e($filters['from']) ?> – <?= e($filters['to']) ?> · MYT</p>
  <div class="attendance-chart-card">
    <h3><?= te('Daily attendance trend') ?></h3>
    <p class="muted"><?= te('Latest 14 scheduled dates in the current selection. A dash means no completed, settled days.') ?></p>
    <div class="attendance-chart-scroll" role="region" tabindex="0" aria-label="<?= te('Daily attendance trend') ?>">
      <ul class="attendance-trend">
        <?php foreach ($days as $date => $count):
          $rate = attendance_report_rate($count['present'], $count['present'] + $count['absent']);
        ?>
          <li>
            <strong><?= e(attendance_report_display('attendance_rate', $rate)) ?></strong>
            <div class="attendance-trend-track" aria-hidden="true"><span style="height:<?= e($rate ?? 0) ?>%"></span></div>
            <time datetime="<?= e($date) ?>" title="<?= e($date) ?>"><?= e(date('j M', strtotime($date))) ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if (count($report['daily']) > count($days)): ?>
      <details class="attendance-details"><summary><?= te('All daily attendance rates') ?></summary>
        <ul class="attendance-rate-list">
          <?php foreach ($report['daily'] as $day => $count): ?>
            <li><?= e($day) ?> · <?= e(attendance_report_display('attendance_rate',
              attendance_report_rate($count['present'], $count['present'] + $count['absent']))) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </div>
  <div class="attendance-chart-grid">
    <section class="attendance-chart-card">
      <h3><?= te('Monthly attendance rate') ?></h3>
      <?php attendance_status_bars($items, 100); ?>
    </section>
    <section class="attendance-chart-card">
      <h3><?= te('On-time and late check-ins') ?></h3>
      <?php attendance_status_bars([
        ['name' => t('On time'), 'size' => $report['summary']['on_time'], 'value' => $report['summary']['on_time']],
        ['name' => t('Late'), 'size' => $report['summary']['late'], 'value' => $report['summary']['late']],
      ]); ?>
    </section>
    <section class="attendance-chart-card">
      <h3><?= te('Shift status distribution') ?></h3>
      <?php attendance_status_bars($statuses); ?>
    </section>
    <section class="attendance-chart-card">
      <h3><?= te('Attendance by Parking Location') ?></h3>
      <p class="muted"><?= te('Verified check-ins for completed required shifts') ?></p>
      <?php attendance_status_bars($places); ?>
    </section>
  </div>
</details>
