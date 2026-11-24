<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/attendance-actions.php';
require_once dirname(__DIR__) . '/includes/attendance-view.php';
require_once dirname(__DIR__) . '/includes/attendance-report.php';

require_login('../pages/login.php');
$actor = me();
if (!in_array($actor['role'], ['admin', 'superadmin'], true)) {
    http_response_code(403);
    exit(te('You do not have access to attendance management.'));
}
header('Cache-Control: private, no-store');
$super = $actor['role'] === 'superadmin';
$now = time();
$today = date('Y-m-d', $now);
$error = '';
$ready = false;
$detail = null;
$rows = [];
$summary = [];
try {
    $db = attendance_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        try {
            $action = $_POST['action'] ?? '';
            $message = match ($action) {
                'save_shift' => attendance_save_shift($db, $actor, $_POST),
                'save_settings' => attendance_save_settings($db, $actor, $_POST),
                'grant_scope', 'revoke_scope' => attendance_set_scope($db, $actor, $_POST),
                'review' => attendance_decide($db, $actor, attendance_int($_POST['shift_id'] ?? null), $_POST),
                default => throw new RuntimeException('Invalid attendance action.', 400),
            };
            flash($message);
            $target = match ($action) {
                'review' => '?shift=' . (int)$_POST['shift_id'] . '#record',
                'save_settings' => '#settings',
                'grant_scope', 'revoke_scope' => '#access',
                default => '#schedule',
            };
            header('Location: employee-attendance.php' . $target, true, 303);
            exit;
        } catch (RuntimeException $failure) {
            $error = attendance_error($failure);
        }
    }
    $accounts = attendance_accounts();
    $locations = attendance_locations($db, $actor);
    $staff = array_filter($accounts, static fn($account) => $account['role'] === 'staff');
    $report = attendance_report($db, $actor, $locations, $staff, $_GET, $now);
    $filters = $report['filters'];
    $rows = $report['rows'];
    $summary = $report['summary'];
    if (($_GET['export'] ?? '') === 'csv') {
        attendance_export($rows, $staff, $now);
        exit;
    }
    if (isset($_GET['shift'])) {
        $detail = attendance_row($db, $actor, attendance_int($_GET['shift']));
        $requests = attendance_query($db, 'SELECT * FROM attendance_requests WHERE shift_id = ? ORDER BY id DESC', [$detail['id']])->fetchAll();
    }
    if ($super) {
        $settings = attendance_settings($db);
        $admins = array_filter($accounts, static fn($account) => $account['role'] === 'admin' && !$account['blocked']);
        $grants = attendance_query($db, 'SELECT admin_id, lot_id FROM attendance_admin_locations WHERE scope = ? ORDER BY admin_id, lot_id',
            [vehicle_account_scope()])->fetchAll();
        $audit = attendance_query($db, 'SELECT * FROM attendance_audit WHERE scope = ? ORDER BY id DESC LIMIT 50',
            [vehicle_account_scope()])->fetchAll();
    }
    $ready = true;
} catch (RuntimeException $failure) {
    $error = attendance_error($failure);
}

head('Employee Attendance');
?>
<link rel="stylesheet" href="assets/css/operations.css">
<link rel="stylesheet" href="assets/css/employee-attendance.css">
<div class="operations-page employee-attendance" id="employee-attendance">
  <div class="attendance-heading">
    <div><h1><?= te('Employee Attendance') ?></h1><p><?= te($super ? 'Review attendance across all parking locations.' : 'Review attendance for your assigned parking locations.') ?></p></div>
    <div class="attendance-actions"><a href="admin/"><?= te('Operations dashboard') ?></a>
      <?php if (!$super): ?><a href="admin/attendance.php"><?= te('Leave requests') ?></a><?php endif; ?>
    </div>
  </div>
  <?php if ($error !== ''): ?><p class="attendance-notice" role="alert"><?= te($error) ?></p><?php endif; ?>
  <?php if ($ready): ?>
    <?php if (!$locations): ?><p class="attendance-notice"><?= te('You have no assigned parking locations for attendance. Ask a Super Admin to grant access.') ?></p><?php endif; ?>
    <section class="ops-panel attendance-panel" id="attendance-report" aria-labelledby="report-heading">
      <div class="attendance-heading"><h2 id="report-heading"><?= te('Attendance Report') ?></h2>
        <a class="btn attendance-secondary" href="admin/employee-attendance.php?<?= e(http_build_query($filters + ['export' => 'csv'])) ?>"><?= te('Export CSV') ?></a>
      </div>
      <?php if ($super): ?>
        <dl class="attendance-facts attendance-summary">
          <?php foreach ($summary as $label => $count): ?><div><dt><?= te($label) ?></dt><dd><?= $count ?></dd></div><?php endforeach; ?>
        </dl>
      <?php endif; ?>
      <form method="get" action="admin/employee-attendance.php#attendance-report" class="attendance-controls">
        <div class="attendance-fields">
          <label><?= te('Start Date') ?><input type="date" name="from" value="<?= e($filters['from']) ?>" required></label>
          <label><?= te('End Date') ?><input type="date" name="to" value="<?= e($filters['to']) ?>" required></label>
          <label><?= te('Search by name or staff ID') ?><input type="search" name="search" maxlength="80" value="<?= e($filters['search']) ?>"></label>
          <label><?= te('Employee') ?><select name="employee"><option value=""><?= te('All employees') ?></option>
            <?php foreach ($staff as $account): ?><option value="<?= $account['id'] ?>" <?= (string)$account['id'] === $filters['employee'] ? 'selected' : '' ?>><?= e($account['name'] . ' · ' . attendance_staff_id($account['id'])) ?></option><?php endforeach; ?>
          </select></label>
          <label><?= te('Parking location') ?><select name="location"><option value=""><?= te('All permitted locations') ?></option>
            <?php foreach ($locations as $lot): ?><option value="<?= $lot['id'] ?>" <?= (string)$lot['id'] === $filters['location'] ? 'selected' : '' ?>><?= e($lot['name']) ?></option><?php endforeach; ?>
          </select></label>
          <label><?= te('Attendance status') ?><select name="status"><option value=""><?= te('All statuses') ?></option>
            <?php foreach (ATTENDANCE_REPORT_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $status === $filters['status'] ? 'selected' : '' ?>><?= te(ATTENDANCE_LABELS[$status]) ?></option><?php endforeach; ?>
          </select></label>
        </div>
        <div class="attendance-actions"><button class="btn"><?= te('Apply filters') ?></button><a href="admin/employee-attendance.php#attendance-report"><?= te('Clear filters') ?></a></div>
        <small><?= te('Choose up to 90 calendar days. Times shown are Malaysia time (MYT).') ?></small>
      </form>
      <?php if ($rows): ?>
        <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Attendance Report') ?>"><table>
          <thead><tr><?php foreach (['Staff ID', 'Employee', 'Assigned Location', 'Attendance date', 'Actual check-in', 'Captured Location', 'Distance', 'GPS Accuracy', 'Attendance status', 'Location status', 'Actual check-out', 'Evidence'] as $heading): ?><th scope="col"><?= te($heading) ?></th><?php endforeach; ?></tr></thead>
          <tbody><?php foreach ($rows as $entry): ?>
            <tr>
              <th scope="row"><?= e(attendance_staff_id($entry['staff_id'])) ?></th><td><?= e($staff[$entry['staff_id']]['name'] ?? '—') ?></td><td><?= e($entry['lot_name']) ?></td>
              <td><?= e($entry['shift_date']) ?></td><td><?= e(attendance_clock($entry['checked_in'])) ?></td><td><?= e(attendance_coordinates($entry, 'in')) ?></td>
              <td><?= $entry['in_distance'] === null ? '—' : e($entry['in_distance']) . ' m' ?></td><td><?= $entry['in_accuracy'] === null ? '—' : e($entry['in_accuracy']) . ' m' ?></td>
              <td><?= attendance_badge(attendance_status($entry, $now)) ?><?php if (attendance_punctuality($entry) && attendance_status($entry, $now) !== attendance_punctuality($entry)): ?><br><?= attendance_badge(attendance_punctuality($entry)) ?><?php endif; ?></td>
              <td><?= attendance_badge($entry['in_zone']) ?></td><td><?= e(attendance_clock($entry['checked_out'])) ?></td>
              <td><a href="admin/employee-attendance.php?<?= e(http_build_query($filters + ['shift' => $entry['id']])) ?>#record"><?= te('View details') ?></a></td>
            </tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php else: ?><p class="attendance-empty"><?= te('No attendance records match these filters.') ?></p><?php endif; ?>
    </section>

    <?php if ($detail): ?>
      <section class="ops-panel attendance-panel" id="record" aria-labelledby="record-heading">
        <div class="attendance-heading"><div><h2 id="record-heading"><?= te('Attendance Details') ?></h2>
          <p><?= e($staff[$detail['staff_id']]['name'] ?? '—') ?> · <?= e(attendance_staff_id($detail['staff_id'])) ?></p></div>
          <?= attendance_badge(attendance_status($detail, $now)) ?></div>
        <?php $row = $detail; $photoUrl = 'api/attendance-photo.php?shift=' . $row['id']; require dirname(__DIR__) . '/includes/attendance-detail.php'; ?>
      </section>
    <?php endif; ?>

    <?php if ($locations && array_filter($staff, static fn($account) => !$account['blocked'])): ?>
      <details class="ops-panel attendance-panel" id="schedule" <?= $error !== '' && ($_POST['action'] ?? '') === 'save_shift' ? 'open' : '' ?>>
        <summary><?= te('Schedule an employee shift') ?></summary>
        <p><?= te('Times are in MYT. If the end time is earlier than the start time, the shift ends the next day.') ?></p>
        <form method="post" action="admin/employee-attendance.php#schedule" class="attendance-controls">
          <?= attendance_fields('save_shift') ?>
          <div class="attendance-fields">
            <label><?= te('Employee') ?><select name="staff_id" required><option value=""><?= te('Choose an employee') ?></option>
              <?php foreach ($staff as $account): if ($account['blocked']) continue; ?><option value="<?= $account['id'] ?>"><?= e($account['name'] . ' · ' . attendance_staff_id($account['id'])) ?></option><?php endforeach; ?>
            </select></label>
            <label><?= te('Parking location') ?><select name="lot_id" required><option value=""><?= te('Choose a parking location') ?></option>
              <?php foreach ($locations as $lot): ?><option value="<?= $lot['id'] ?>"><?= e($lot['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label><?= te('Date') ?><input type="date" name="shift_date" value="<?= e($today) ?>" required></label>
            <label><?= te('Start time') ?><input type="time" name="start_time" required></label>
            <label><?= te('End time') ?><input type="time" name="end_time" required></label>
          </div>
          <button class="btn"><?= te('Save shift') ?></button>
        </form>
      </details>
    <?php endif; ?>
    <?php if ($super): require dirname(__DIR__) . '/includes/attendance-settings.php'; endif; ?>
  <?php endif; ?>
</div>
<script src="assets/js/employee-attendance.js" defer></script>
<?php foot(false); ?>
