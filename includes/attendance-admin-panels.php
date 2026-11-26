<?php if (in_array($tab, ['overview', 'records', 'exceptions'], true)): ?>
  <section class="ops-panel attendance-panel" id="attendance-report" aria-labelledby="report-heading">
    <div class="attendance-heading">
      <div><h2 id="report-heading"><?= te($tab === 'overview' ? 'Attendance Overview' : $tabs[$tab]) ?></h2>
        <?php if ($report): ?><p class="muted"><?= te('Updated') ?> · <?= e(attendance_clock($now)) ?> MYT</p><?php endif; ?>
      </div>
      <a class="btn btn-secondary" href="<?= e(attendance_report_links($filters, $page, ['tab' => $tab])) ?>#attendance-report"><?= te('Refresh') ?></a>
    </div>
    <form method="get" action="<?= e($page) ?>#attendance-report" class="attendance-controls" data-date-range data-date-range-require-both>
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="attendance-fields">
        <?php attendance_report_filter_fields($filters, $staff, $locations, true, $tab === 'exceptions'); ?>
      </div>
      <div class="attendance-actions">
        <button type="submit" class="btn" data-date-range-submit="visible"><?= te('Apply filters') ?></button>
        <a class="btn btn-secondary" href="<?= e(attendance_report_links([], $page, ['tab' => $tab])) ?>#attendance-report"><?= te('Clear filters') ?></a>
        <a class="attendance-quick-date" href="<?= e(attendance_report_links($filters, $page,
          ['tab' => $tab, 'from' => date('Y-m-01', $now), 'to' => $today])) ?>#attendance-report"><?= te('This month so far') ?></a>
        <a class="attendance-quick-date" href="<?= e(attendance_report_links($filters, $page,
          ['tab' => $tab, 'from' => date('Y-m-d', strtotime('first day of last month', $now)),
           'to' => date('Y-m-d', strtotime('last day of last month', $now))])) ?>#attendance-report"><?= te('Last month') ?></a>
      </div>
      <small><?= te('Leave both dates blank to view today’s report.') ?> <?= te('Times shown are Malaysia time (MYT).') ?></small>
    </form>
    <?php if ($report && $tab === 'overview'): ?>
      <?php attendance_report_summary_tiles($report['summary']); attendance_performance_help(); ?>
      <?php if (!$report['rows']): ?><p class="attendance-empty"><?= te('No attendance records match these filters.') ?></p><?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($tab === 'overview' && $report && $report['rows']): ?>
  <?php require __DIR__ . '/attendance-trends.php'; ?>
<?php endif; ?>

<?php if ($tab === 'calendar'): ?>
  <section class="ops-panel attendance-panel" id="attendance-calendar" aria-labelledby="attendance-calendar-heading">
    <h2 id="attendance-calendar-heading"><?= te('Monthly Calendar') ?></h2>
    <form action="<?= e($page) ?>#attendance-calendar" method="get" class="attendance-controls">
      <input type="hidden" name="tab" value="calendar">
      <?php foreach ($filters as $name => $value): if ($value === '') continue; ?>
        <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
      <?php endforeach; ?>
      <div class="attendance-fields">
        <?php attendance_select('calendar_employee', 'Employee', attendance_staff_options($staff), is_string($calendarEmployee) ? $calendarEmployee : '', 'Choose an employee'); ?>
        <label><?= te('Month') ?><input type="month" name="month" value="<?= e($month) ?>" min="1900-01" max="9998-12" required></label>
        <?php attendance_select('calendar_location', 'Parking location', array_column($locations, 'name', 'id'),
          is_string($calendarLocation) ? $calendarLocation : '', 'All permitted locations'); ?>
      </div>
      <button class="btn"><?= te('View month') ?></button>
    </form>
    <?php if ($calendar):
        $calendarQuery = $filters + ['tab' => 'calendar', 'calendar_employee' => $calendarEmployee,
            'calendar_location' => $calendarLocation, 'month' => $month];
        $subject = $staff[$calendarEmployee]['name'] . ' · ' . attendance_staff_id((int)$calendarEmployee);
        require __DIR__ . '/attendance-calendar-view.php';
    else: ?>
      <p class="attendance-empty"><?= te($staff ? 'Choose an employee and month to see scheduled shifts.' : 'No employees are available in your attendance scope.') ?></p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($tab === 'reports'): ?>
  <section class="ops-panel attendance-panel" id="report-config" aria-labelledby="report-config-title">
    <div class="attendance-heading"><div><h2 id="report-config-title"><?= te('Reports & Export') ?></h2>
      <p><?= te('Choose a report, review the results, and download a copy.') ?></p></div></div>
    <form class="attendance-controls" method="get" action="<?= e($page) ?>#report-preview" data-report-builder>
      <input type="hidden" name="tab" value="reports">
      <div class="attendance-fields">
        <?php attendance_select('report_type', 'Report type', array_map('t', [
          'daily' => 'Daily attendance', 'weekly' => 'Weekly attendance', 'monthly' => 'Monthly summary',
          'individual' => 'Individual employee', 'full' => 'Full attendance', 'exceptions' => 'Late arrivals and exceptions',
          'location' => 'By parking location', 'custom' => 'Custom date range',
        ]), $reportType); ?>
        <?php attendance_select('format', 'File format', ATTENDANCE_REPORT_FORMATS, $format); ?>
        <?php attendance_report_filter_fields($filters, $staff, $locations, false, true); ?>
      </div>
      <p class="muted"><?= te('Daily, weekly, and monthly reports use the start date to select a day, Monday–Sunday week, or calendar month.') ?></p>
      <div class="attendance-actions">
        <button type="submit" class="btn btn-secondary" name="generate" value="preview"><?= te('Preview report') ?></button>
        <button type="submit" class="btn" name="generate" value="download"><?= te('Download report') ?></button>
      </div>
    </form>
    <small><?= te('Downloads exclude photos, precise GPS coordinates, and private review notes.') ?></small>
  </section>
  <?php if ($report): ?>
    <section class="ops-panel attendance-panel" id="report-preview" aria-labelledby="preview-heading">
      <div class="attendance-heading"><div>
        <h2 id="preview-heading"><?= te(ATTENDANCE_REPORT_TYPES[$reportType]) ?></h2>
        <p class="muted"><?= e($filters['from']) ?> – <?= e($filters['to']) ?> · MYT</p>
      </div><a class="btn btn-secondary" href="<?= e(attendance_report_links($filters, $page,
        ['tab' => 'reports', 'report_type' => $reportType, 'export' => $format])) ?>"><?= te('Download {format}', ['format' => ATTENDANCE_REPORT_FORMATS[$format]]) ?></a></div>
      <?php attendance_report_summary_tiles($report['summary']); ?>
      <?php if ($reportType === 'full' && !$report['period_closed']): ?>
        <p class="attendance-notice"><?= te('Full attendance is assessed after the selected period and its shifts end.') ?></p>
      <?php endif; ?>
      <?php if ($reportType === 'location' && $report['locations']): ?>
        <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Location summary') ?>">
          <table>
            <thead><tr><?php foreach (ATTENDANCE_LOCATION_FIELDS as $label): ?>
              <th scope="col"><?= te($label) ?></th>
            <?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($report['locations'] as $locationRow): ?>
                <tr><th scope="row"><?= e($locationRow['name']) ?></th><td><?= e($locationRow['shifts']) ?></td>
                  <td><?= e($locationRow['present']) ?></td><td><?= e($locationRow['missing']) ?></td>
                  <td><?= e($locationRow['pending']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($filters['employee'] !== '' && isset($report['people'][(int)$filters['employee']])): ?>
        <h3><?= e($staff[$filters['employee']]['name']) ?> · <?= e(attendance_staff_id((int)$filters['employee'])) ?></h3>
        <?php attendance_performance_tiles($report['people'][(int)$filters['employee']]); ?>
      <?php elseif ($report['people']): ?>
        <h3><?= te('Employee performance') ?></h3>
        <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Employee performance') ?>">
          <table>
            <thead><tr><th scope="col"><?= te('Employee') ?></th>
              <?php foreach (ATTENDANCE_PERFORMANCE_FIELDS as $label): ?><th scope="col"><?= te($label) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($report['people'] as $id => $performance): ?>
                <tr><th scope="row"><?= e($staff[$id]['name'] ?? attendance_staff_id($id)) ?><br><small><?= e(attendance_staff_id($id)) ?></small></th>
                  <?php foreach (ATTENDANCE_PERFORMANCE_FIELDS as $key => $label): ?><td><?= e(attendance_report_display($key, $performance[$key])) ?></td><?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php attendance_performance_help(); ?>
    </section>
  <?php endif; ?>
  <?php if ($super): require __DIR__ . '/attendance-report-policy-view.php'; endif; ?>
<?php endif; ?>

<?php if ($report && in_array($tab, ['records', 'exceptions', 'reports'], true)): ?>
  <?php
    $reportQuery = $filters + ['tab' => $tab];
    if ($tab === 'reports') $reportQuery += ['report_type' => $reportType, 'format' => $format];
    $pagination = attendance_report_page($report['rows'], $_GET['row_page'] ?? null);
    $showExceptions = $tab === 'exceptions' || ($tab === 'reports' && $reportType === 'exceptions');
  ?>
  <section class="ops-panel attendance-panel" id="report-rows" aria-labelledby="report-rows-heading">
    <div class="attendance-heading"><h2 id="report-rows-heading"><?= te($showExceptions ? 'Exceptions to review' : 'Shift records') ?></h2>
      <span class="ops-count"><?= te(count($report['rows']) === 1 ? '{count} shift' : '{count} shifts',
        ['count' => count($report['rows'])]) ?></span>
    </div>
    <?php if (!$pagination['rows']): ?>
      <p class="attendance-empty"><?= te($showExceptions ? 'No attendance exceptions match these filters.' : 'No attendance records match these filters.') ?></p>
    <?php else: ?>
      <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Shift records') ?>">
        <table>
          <thead><tr>
            <?php foreach (['Employee', 'Assigned Location', 'Scheduled shift', 'Check In', 'Check Out', 'Attendance status', 'GPS verification'] as $heading): ?>
              <th scope="col"><?= te($heading) ?></th>
            <?php endforeach; ?>
            <?php if ($showExceptions): ?><th scope="col"><?= te('Exception') ?></th><th scope="col"><?= te('Review status') ?></th><?php endif; ?>
            <th scope="col"><?= te('Details') ?></th>
          </tr></thead>
          <tbody><?php foreach ($pagination['rows'] as $entry): ?>
            <tr>
              <th scope="row"><?= e($employees[$entry['staff_id']]['name'] ?? '—') ?><br><small><?= e(attendance_staff_id($entry['staff_id'])) ?></small></th>
              <td><?= e($entry['lot_name']) ?></td><td><?= e($entry['shift_date']) ?><br><small><?= e(date('H:i', $entry['starts_at'])) ?> – <?= e(date('H:i', $entry['ends_at'])) ?><?= date('Y-m-d', $entry['ends_at']) !== $entry['shift_date'] ? ' · ' . te('Next day') : '' ?></small></td>
              <td><?= e(attendance_clock($entry['in'])) ?></td><td><?= e(attendance_clock($entry['out'])) ?></td>
              <td><?= attendance_badge($entry['state']) ?><?php if ($entry['checkout_missing']): ?><br><?= attendance_badge('missing_checkout') ?><?php endif; ?></td>
              <td>
                <?php if ($entry['in_zone'] === null && $entry['out_zone'] === null): ?>—
                <?php else: ?>
                  <?php if ($entry['in_zone'] !== null): ?><small><?= te('Check In') ?></small> <?= attendance_badge($entry['in_zone']) ?><?php endif; ?>
                  <?php if ($entry['out_zone'] !== null): ?><br><small><?= te('Check Out') ?></small> <?= attendance_badge($entry['out_zone']) ?><?php endif; ?>
                <?php endif; ?>
              </td>
              <?php if ($showExceptions): ?>
                <td><ul class="attendance-exception-list"><?php foreach ($entry['exceptions'] as $flag): ?><li><?= te(ATTENDANCE_EXCEPTION_LABELS[$flag]) ?></li><?php endforeach; ?></ul></td>
                <td><?= e(attendance_review_label($entry)) ?></td>
              <?php endif; ?>
              <td><a class="btn btn-secondary" href="<?= e(attendance_report_links($reportQuery, $page, ['shift' => $entry['id'], 'row_page' => $pagination['current']])) ?>#record"><?= te('View details') ?></a></td>
            </tr>
          <?php endforeach; ?></tbody>
        </table>
      </div>
      <?php attendance_report_pager($pagination, $reportQuery, $page); ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
