<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/attendance-actions.php';

require_once __DIR__ . '/includes/attendance-view.php';
require_once __DIR__ . '/includes/attendance-report.php';
require_once __DIR__ . '/includes/attendance-report-view.php';

require_login();
$actor = me();
if ($actor['role'] !== 'staff') {
    http_response_code(403);
    exit(te('Only employees can record attendance.'));
}
header('Cache-Control: private, no-store');
header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
$now = time();
$today = date('Y-m-d', $now);
$error = '';
$selected = null;
$history = [];
$calendar = null;
$calendarView = ($_GET['view'] ?? '') === 'calendar';
$month = date('Y-m', $now);
$workday = '';
$page = 'staff-attendance.php';
try {
    $db = attendance_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $shiftId = attendance_int($_POST['shift_id'] ?? null);
        $action = $_POST['action'] ?? '';
        try {
            $message = match ($action) {
                'check_in', 'check_out', 'exception' => attendance_capture($db, $actor, $shiftId, $action, $_POST, time()),
                'correction' => attendance_correct($db, $actor, $shiftId, $_POST, time()),
                'reply' => attendance_reply($db, $actor, $shiftId, $_POST, time()),
                default => throw new RuntimeException('Invalid attendance action.', 400),
            };
            flash($message);
            $back = $calendarView ? array_filter(array_intersect_key($_GET, array_flip(['view', 'month', 'workday'])), 'is_string') : [];
            header('Location: ' . attendance_report_links($back, $page, ['shift' => $shiftId]) . '#shift-attendance', true, 303);
            exit;
        } catch (RuntimeException $failure) {
            $error = attendance_error($failure);
        }
    }
    $history = attendance_rows($db, $actor, 's.shift_date BETWEEN ? AND ?', [date('Y-m-d', strtotime('-30 days', $now)), $today]);
    if (isset($shiftId) || isset($_GET['shift'])) {
        $selected = attendance_row($db, $actor, $shiftId ?? attendance_int($_GET['shift']));
    } else {
        foreach ($history as $row) {
            if ($row['shift_date'] === $today) $selected = $row;
            if (($row['starts_at'] <= $now && $row['ends_at'] > $now)
                || attendance_can_check_in($row, $now) || attendance_can_check_out($row, $now)) {
                $selected = $row;
                break;
            }
        }
    }
    if ($calendarView) {
        $month = attendance_report_month($_GET['month'] ?? null, $now);
        $workday = $_GET['workday'] ?? '';
        if ($workday !== '' && (!attendance_date($workday) || !str_starts_with($workday, $month . '-'))) {
            throw new RuntimeException('Choose a date in the selected calendar month.', 422);
        }
        $calendar = attendance_calendar($db, $actor, [], [$actor['id'] => $actor], (string)$actor['id'], $month, $now, '', $workday);
    }
} catch (RuntimeException $failure) {
    if ($error === '') $error = attendance_error($failure);
}

head('My Attendance');
?>
<link rel="stylesheet" href="assets/css/employee-attendance.css">
<div class="employee-attendance">
  <div class="staff-page-heading">
    <div><h1><?= te('My Attendance') ?></h1><p><?= te('Record your shift and review your attendance in Malaysia time.') ?></p></div>
    <div class="attendance-actions">
      <a class="btn btn-secondary" href="#attendance-history"><?= te('View Attendance History') ?></a>
      <a class="btn btn-secondary" href="staff-attendance.php?view=calendar#attendance-calendar"><?= te('Monthly Calendar') ?></a>
    </div>
  </div>
  <?php if ($error !== ''): ?><p class="attendance-notice" role="alert"><?= te($error) ?></p><?php endif; ?>
  <section class="staff-card" id="shift-attendance" aria-labelledby="shift-attendance-heading">
    <div class="attendance-heading">
      <div><h2 id="shift-attendance-heading"><?= te($selected && $selected['shift_date'] !== $today ? 'Shift Attendance' : 'Today’s Attendance') ?></h2>
        <p><?= e($actor['name']) ?> · <?= e(attendance_staff_id($actor['id'])) ?></p></div>
      <?php if ($selected): ?><?= attendance_badge(attendance_status($selected, $now)) ?><?php endif; ?>
    </div>
    <?php if ($selected): ?>
      <?php
        $row = $selected;
        $photoUrl = 'api/attendance-photo.php?shift=' . $row['id'];
        $requests = attendance_query($db, 'SELECT * FROM attendance_requests WHERE shift_id = ? ORDER BY id DESC', [$row['id']])->fetchAll();
        require __DIR__ . '/includes/attendance-detail.php';
      ?>
      <?php if (attendance_can_check_in($row, $now)): ?>
        <form method="post" class="attendance-controls" data-attendance-camera
          data-location-working="<?= te('Getting your location…') ?>"
          data-camera-working="<?= te('Opening the camera…') ?>"
          data-camera-failed="<?= te('Camera unavailable. Request an exception below or allow camera access and try again.') ?>"
          data-location-warning="<?= te('Location is unavailable. You can submit and an Admin will review it.') ?>"
          data-photo-ready="<?= te('Photo ready. Submit your check-in or retake the photo.') ?>"
          data-submitting="<?= te('Saving attendance…') ?>">
          <?= attendance_fields('check_in', $row['id']) ?>
          <input type="hidden" name="photo">
          <button type="button" class="staff-button" data-start-camera data-retake="<?= te('Retake photo') ?>" hidden><?= te('Check In') ?></button>
          <video class="attendance-preview" data-attendance-video autoplay muted playsinline hidden aria-label="<?= te('Live camera preview') ?>"></video>
          <img class="attendance-preview" data-attendance-preview hidden alt="<?= te('Captured attendance photo preview') ?>">
          <div class="attendance-actions">
            <button class="staff-button" type="button" data-take-photo hidden><?= te('Capture photo') ?></button>
            <button class="staff-button staff-secondary" type="button" data-cancel-camera hidden><?= te('Cancel camera') ?></button>
            <button class="staff-button" type="submit" data-submit-photo hidden><?= te('Submit check-in') ?></button>
          </div>
          <p data-capture-status role="status" hidden></p>
          <noscript><p><?= te('Enable JavaScript to take a photo, or request an exception below.') ?></p></noscript>
        </form>
        <details class="attendance-details">
          <summary><?= te('Camera unavailable? Request an exception') ?></summary>
          <p><?= te('Your check-in time will be recorded and sent for review.') ?></p>
          <form method="post" class="attendance-controls" data-attendance-location data-location-working="<?= te('Getting your location…') ?>">
            <?= attendance_fields('exception', $row['id']) ?>
            <label><?= te('Reason') ?><textarea name="reason" maxlength="500" required></textarea></label>
            <button class="staff-button"><?= te('Send check-in for review') ?></button><p data-capture-status role="status" hidden></p>
          </form>
        </details>
      <?php endif; ?>
      <?php if (attendance_can_check_out($row, $now)): ?>
        <form method="post" class="attendance-controls" data-attendance-location data-location-working="<?= te('Getting your location…') ?>">
          <?= attendance_fields('check_out', $row['id']) ?>
          <button class="staff-button"><?= te('Check Out') ?></button><p data-capture-status role="status" hidden></p>
          <noscript><p><?= te('Location cannot be captured without JavaScript. Your check-out will be sent for review.') ?></p></noscript>
        </form>
      <?php endif; ?>
      <?php if ($row['review_status'] !== 'pending' && $now >= $row['starts_at'] - 1800 && !attendance_leave($row)): ?>
        <details class="attendance-details">
          <summary><?= te('Request Attendance Correction') ?></summary>
          <p><?= te('Enter the time you want reviewed, using Malaysia time. Leave the other field blank if it is correct.') ?></p>
          <form method="post" class="attendance-controls">
            <?= attendance_fields('correction', $row['id']) ?>
            <div class="attendance-fields">
              <label><?= te('Proposed check-in') ?><input type="datetime-local" name="proposed_in"></label>
              <label><?= te('Proposed check-out') ?><input type="datetime-local" name="proposed_out"></label>
            </div>
            <label><?= te('Reason') ?><textarea name="reason" maxlength="500" required></textarea></label>
            <button class="staff-button"><?= te('Send correction request') ?></button>
          </form>
        </details>
      <?php endif; ?>
    <?php elseif ($error === ''): ?>
      <p><?= e(date('d M Y', $now)) ?> · <?= attendance_badge('rest') ?></p><p><?= te('You have no scheduled shift today.') ?></p>
    <?php endif; ?>
  </section>

  <?php if ($calendarView): ?>
    <section class="staff-card" id="attendance-calendar" aria-labelledby="attendance-calendar-heading">
      <h2 id="attendance-calendar-heading"><?= te('Monthly Calendar') ?></h2>
      <form method="get" action="staff-attendance.php#attendance-calendar" class="attendance-controls">
        <input type="hidden" name="view" value="calendar">
        <div class="attendance-fields">
          <label><?= te('Month') ?><input type="month" name="month" value="<?= e($month) ?>" min="1900-01" max="9998-12" required></label>
        </div>
        <button class="staff-button"><?= te('View month') ?></button>
      </form>
      <?php if ($calendar):
          $calendarQuery = ['view' => 'calendar', 'month' => $month];
          $subject = $actor['name'] . ' · ' . attendance_staff_id($actor['id']);
          require __DIR__ . '/includes/attendance-calendar-view.php';
      endif; ?>
    </section>
  <?php endif; ?>

  <section class="staff-card" id="attendance-history" aria-labelledby="attendance-history-heading">
    <h2 id="attendance-history-heading"><?= te('Attendance History') ?></h2>
    <p><?= te('Your shifts from the last 31 calendar days.') ?></p>
    <?php if ($history): ?>
      <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Attendance History') ?>"><table>
        <thead><tr><?php foreach (['Date', 'Assigned Location', 'Check In', 'Check Out', 'Working duration', 'Attendance status', 'Location status', 'Details'] as $heading): ?><th scope="col"><?= te($heading) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($history as $entry): [$in, $out] = attendance_times($entry); ?>
          <tr><th scope="row"><?= e($entry['shift_date']) ?></th><td><?= e($entry['lot_name']) ?></td>
            <td><?= e(attendance_clock($in)) ?></td><td><?= e(attendance_clock($out)) ?></td><td><?= e(attendance_duration($entry)) ?></td>
            <td><?= attendance_badge(attendance_status($entry, $now)) ?></td><td><?= attendance_badge($entry['in_zone']) ?></td>
            <td><a class="btn btn-secondary" href="staff-attendance.php?shift=<?= $entry['id'] ?>#shift-attendance"><?= te('View details') ?></a></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php else: ?><p><?= te($error === '' ? 'No shifts to show yet.' : 'Attendance history is unavailable.') ?></p><?php endif; ?>
  </section>
</div>
<script src="assets/js/employee-attendance.js" defer></script>
<?php foot(false); ?>
