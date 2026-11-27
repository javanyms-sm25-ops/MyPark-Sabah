<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/attendance-actions.php';
require_once dirname(__DIR__) . '/includes/attendance-report.php';
require_once dirname(__DIR__) . '/includes/attendance-report-view.php';

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
$page = 'admin/employee-attendance.php';
$tabs = ['overview' => 'Overview', 'records' => 'Records', 'calendar' => 'Calendar',
    'exceptions' => 'Exceptions', 'reports' => 'Reports'];
if ($super) $tabs['settings'] = 'Settings';
$sections = $tabs + ['schedule' => 'Schedule shift'];
$error = '';
$ready = false;
$detail = $report = $calendar = null;
$month = date('Y-m', $now);
$calendarEmployee = $calendarLocation = $workday = '';
$tab = 'overview';
$reportType = 'custom';
$format = 'pdf';
$filters = ['from' => $today, 'to' => $today, 'employee' => '', 'location' => '', 'status' => '', 'search' => '', 'exception' => ''];
$contextFields = array_flip([...array_keys($filters), 'tab', 'month', 'workday', 'calendar_employee', 'calendar_location', 'report_type', 'format', 'row_page']);
$returnQuery = array_filter(array_intersect_key($_GET, $contextFields), 'is_string');

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
                'save_report_policy' => attendance_save_report_policy($db, $actor, $_POST),
                'save_holiday', 'remove_holiday' => attendance_save_holiday($db, $actor, $_POST),
                default => throw new RuntimeException('Invalid attendance action.', 400),
            };
            flash($message);
            $target = match ($action) {
                'review' => attendance_report_links($returnQuery + ['tab' => 'records'], 'employee-attendance.php',
                    ['shift' => (int)$_POST['shift_id']]) . '#record',
                'save_settings' => 'employee-attendance.php?tab=settings#settings',
                'grant_scope', 'revoke_scope' => 'employee-attendance.php?tab=settings#access',
                'save_report_policy', 'save_holiday', 'remove_holiday' => 'employee-attendance.php?tab=settings#reporting-policy',
                default => 'employee-attendance.php?tab=schedule',
            };
            header('Location: ' . $target, true, 303);
            exit;
        } catch (RuntimeException $failure) {
            $error = attendance_error($failure);
        }
    }

    $accounts = attendance_accounts();
    $locations = attendance_locations($db, $actor);
    $employees = array_filter($accounts, static fn($account) => $account['role'] === 'staff');
    $staff = attendance_report_staff($db, $actor, $employees);
    uasort($staff, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    $activeEmployees = array_filter($employees, static fn($account) => !$account['blocked']);
    $activeLocations = array_filter($locations, static fn($lot) => !isset($lot['deleted_at']));
    $canSchedule = $activeEmployees && $activeLocations;
    $ready = true;
    $tab = attendance_report_choice($_GET['tab'] ?? (isset($_GET['shift']) ? 'records' : 'overview'), $sections, 'Choose a valid attendance section.');
    if ($tab === 'settings') {
        $settings = attendance_settings($db);
        $admins = array_filter($accounts, static fn($account) => $account['role'] === 'admin' && !$account['blocked']);
        $grants = attendance_query($db, 'SELECT admin_id, lot_id FROM attendance_admin_locations WHERE scope = ? ORDER BY admin_id, lot_id',
            [vehicle_account_scope()])->fetchAll();
        $audit = attendance_query($db, 'SELECT * FROM attendance_audit WHERE scope = ? ORDER BY id DESC LIMIT 50',
            [vehicle_account_scope()])->fetchAll();
    }
    $reportType = attendance_report_choice($_GET['report_type'] ?? 'custom', ATTENDANCE_REPORT_TYPES, 'Choose a valid report type.');
    $format = attendance_report_choice($_GET['format'] ?? 'pdf', ATTENDANCE_REPORT_FORMATS, 'Choose a valid report format.');
    $download = $_GET['export'] ?? (($_GET['generate'] ?? '') === 'download' ? $format : '');
    if ($download !== '') $download = attendance_report_choice($download, ATTENDANCE_REPORT_FORMATS, 'Choose a valid report format.');
    $type = match (true) {
        $tab === 'reports' || $download !== '' => $reportType,
        $tab === 'exceptions' => 'exceptions',
        default => 'custom',
    };

    if (isset($_GET['shift']) && $download === '') {
        $detail = attendance_row($db, $actor, attendance_int($_GET['shift']));
        $requests = attendance_query($db, 'SELECT * FROM attendance_requests WHERE shift_id = ? ORDER BY id DESC', [$detail['id']])->fetchAll();
    }
    if ($tab === 'calendar' && $download === '') {
        $filters = attendance_report_filters($_GET, $locations, $staff, $now, 'custom');
        $month = attendance_report_month($_GET['month'] ?? null, $now);
        $calendarEmployee = $_GET['calendar_employee'] ?? ($filters['employee'] ?: (string)array_key_first($staff));
        $calendarLocation = $_GET['calendar_location'] ?? $filters['location'];
        $workday = $_GET['workday'] ?? '';
        if (!is_string($calendarEmployee) || !is_string($calendarLocation)
            || ($calendarEmployee !== '' && !isset($staff[$calendarEmployee]))) {
            throw new RuntimeException('Choose an employee available in your attendance scope.', 422);
        }
        if ($calendarLocation !== '' && !isset($locations[$calendarLocation])) {
            throw new RuntimeException('You do not have access to that parking location.', 403);
        }
        if ($workday !== '' && (!attendance_date($workday) || !str_starts_with($workday, $month . '-'))) {
            throw new RuntimeException('Choose a date in the selected calendar month.', 422);
        }
        if ($calendarEmployee !== '') {
            $calendar = attendance_calendar($db, $actor, $locations, $staff, $calendarEmployee, $month, $now, $calendarLocation, $workday);
        }
    } elseif ($download !== '' || !in_array($tab, ['schedule', 'settings'], true)) {
        // Keep typed filter values in the builder when a report cannot be generated.
        $filters = array_replace($filters, array_intersect_key($returnQuery, $filters));
        $report = attendance_report($db, $actor, $locations, $staff, $_GET, $now, $type);
        $filters = $report['filters'];
        if ($download !== '') {
            require_once dirname(__DIR__) . '/includes/attendance-export.php';
            try {
                attendance_send_report($report, $staff, $locations, $download);
                exit;
            } catch (Throwable $failure) {
                error_log('Attendance export failed: ' . get_class($failure));
                throw new RuntimeException('The report could not be generated. Try a smaller date range or another format.', 503, $failure);
            }
        }
    }
} catch (RuntimeException $failure) {
    if ($error === '') $error = attendance_error($failure);
}

$tabFilters = array_intersect_key($returnQuery, $filters);
head('Attendance');
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/employee-attendance.css">
<div class="operations-page employee-attendance" id="employee-attendance">
  <div class="attendance-heading attendance-page-header">
    <div class="ops-page-intro">
      <h1><?= te('Attendance') ?></h1>
      <p class="ops-subtitle"><?= te($super ? 'Review attendance across all parking locations.' : 'Review attendance for your assigned parking locations.') ?></p>
    </div>
    <div class="attendance-actions">
      <?php if (!$super): ?><a class="btn btn-secondary" href="admin/attendance.php"><?= te('Leave requests') ?></a><?php endif; ?>
      <?php if ($ready && $canSchedule): ?>
        <a class="btn<?= $tab === 'schedule' ? ' btn-secondary' : '' ?>" href="<?= e($page) ?>?tab=schedule"
          <?= $tab === 'schedule' ? 'aria-current="page"' : '' ?>><?= te('Schedule shift') ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($error !== ''): ?><p class="attendance-notice" role="alert"><?= te($error) ?></p><?php endif; ?>
  <?php if ($ready): ?>
    <nav class="attendance-tabs" aria-label="<?= te('Attendance sections') ?>">
      <?php foreach ($tabs as $key => $label): ?>
        <a <?= $key === $tab ? 'aria-current="page"' : '' ?> href="<?= e(attendance_report_links($tabFilters, $page,
          ['tab' => $key, 'exception' => $key === 'exceptions' ? ($tabFilters['exception'] ?? '') : ''])) ?>"><?= te($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if (!$locations): ?><p class="attendance-notice"><?= te('You have no assigned parking locations for attendance. Ask a Super Admin to grant access.') ?></p><?php endif; ?>

    <?php if ($detail): ?>
      <section class="ops-panel attendance-panel" id="record" aria-labelledby="record-heading">
        <?php $resultsQuery = array_diff_key(array_filter($_GET, 'is_string'), ['shift' => true]); ?>
        <a class="attendance-back" href="<?= e(attendance_report_links($resultsQuery, $page, ['tab' => $tab])) ?><?= $tab === 'calendar' ? '#attendance-calendar' : '#report-rows' ?>"><?= te('Back to results') ?></a>
        <div class="attendance-heading"><div><h2 id="record-heading"><?= te('Attendance Details') ?></h2>
          <p><?= e($employees[$detail['staff_id']]['name'] ?? '—') ?> · <?= e(attendance_staff_id($detail['staff_id'])) ?></p></div>
          <?= attendance_badge(attendance_status($detail, $now)) ?></div>
        <?php
          $row = $detail;
          $photoUrl = 'api/attendance-photo.php?shift=' . $row['id'];
          $reviewAction = attendance_report_links($returnQuery + ['tab' => $tab], $page, ['shift' => $row['id']]) . '#record';
          require dirname(__DIR__) . '/includes/attendance-detail.php';
        ?>
      </section>
    <?php endif; ?>

    <?php require dirname(__DIR__) . '/includes/attendance-admin-panels.php'; ?>

    <?php if ($tab === 'schedule' && $canSchedule): ?>
      <section class="ops-panel attendance-panel" id="schedule" aria-labelledby="schedule-heading">
        <h2 id="schedule-heading"><?= te('Schedule an employee shift') ?></h2>
        <p><?= te('Times are in MYT. If the end time is earlier than the start time, the shift ends the next day.') ?></p>
        <form method="post" action="<?= e($page) ?>?tab=schedule" class="attendance-controls">
          <?= attendance_fields('save_shift') ?>
          <div class="attendance-fields">
            <label><?= te('Employee') ?><select name="staff_id" required><option value=""><?= te('Choose an employee') ?></option>
              <?php foreach ($activeEmployees as $account): ?><option value="<?= $account['id'] ?>" <?= ($_POST['staff_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>><?= e($account['name'] . ' · ' . attendance_staff_id($account['id'])) ?></option><?php endforeach; ?>
            </select></label>
            <label><?= te('Parking location') ?><select name="lot_id" required><option value=""><?= te('Choose a parking location') ?></option>
              <?php foreach ($activeLocations as $lot): ?>
                <option value="<?= $lot['id'] ?>" <?= ($_POST['lot_id'] ?? '') === (string)$lot['id'] ? 'selected' : '' ?>><?= e($lot['name']) ?></option>
              <?php endforeach; ?>
            </select></label>
            <label><?= te('Date') ?><input type="date" name="shift_date" value="<?= e($_POST['shift_date'] ?? $today) ?>" required></label>
            <label><?= te('Start time') ?><input type="time" name="start_time" value="<?= e($_POST['start_time'] ?? '') ?>" required></label>
            <label><?= te('End time') ?><input type="time" name="end_time" value="<?= e($_POST['end_time'] ?? '') ?>" required></label>
          </div>
          <button class="btn"><?= te('Save shift') ?></button>
        </form>
      </section>
    <?php elseif ($tab === 'schedule'): ?>
      <p class="attendance-empty"><?= te('An active employee and parking location are required to schedule a shift.') ?></p>
    <?php endif; ?>
    <?php if ($tab === 'settings' && isset($audit)):
      require dirname(__DIR__) . '/includes/attendance-settings.php';
      require dirname(__DIR__) . '/includes/attendance-report-policy-view.php';
    endif; ?>
  <?php endif; ?>
</div>
<?php if ($ready && in_array($tab, ['overview', 'records', 'exceptions'], true)) require dirname(__DIR__) . '/includes/date-range-dialog.php'; ?>
<script src="assets/js/employee-attendance.js" defer></script>
<?php foot(false); ?>
