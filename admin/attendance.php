<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/operations-data.php';

header('Cache-Control: private, no-store');
try {
    $actor = ops_actor();
    if ($actor['role'] !== 'admin') {
        throw new RuntimeException('Only Admin can review leave requests.', 403);
    }
} catch (RuntimeException $error) {
    if ($error->getCode() === 401) {
        header('Location: ../pages/login.php');
        exit;
    }
    http_response_code(403);
    exit(te($error->getMessage()));
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$validFilterDate = static fn($date): bool => is_string($date) && ($date === '' || staff_leave_date_is_valid($date));
$rangeError = '';
if (!$validFilterDate($from) || !$validFilterDate($to) || ($from !== '' && $to !== '' && $from > $to)) {
    $rangeError = 'Enter valid dates with an end date on or after the start date.';
    $from = $to = '';
}
$dateQuery = http_build_query(array_filter(['from' => $from, 'to' => $to]));
$attendanceTarget = 'attendance.php' . ($dateQuery !== '' ? '?' . $dateQuery : '');
$leaveRequests = array_filter(staff_leave_rows($actor), static fn($leave) =>
    ($from === '' || $leave['end_date'] >= $from) && ($to === '' || $leave['start_date'] <= $to)
);

$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ops_valid_csrf($_POST);
        if (($_POST['action'] ?? '') !== 'leave_review') {
            throw new RuntimeException('Invalid request fields.', 400);
        }
        flash(staff_leave_review($actor, $_POST));
        header('Location: ' . $attendanceTarget, true, 303);
        exit;
    } catch (RuntimeException $error) {
        http_response_code($error->getCode());
        $reviewError = $error->getMessage();
    }
}

head('Attendance');
?>
<link rel="stylesheet" href="assets/css/operations.css">
<link rel="stylesheet" href="assets/css/attendance.css">
<div class="operations-page attendance-page">
  <h1><?= te('Attendance') ?></h1>
  <p class="attendance-subtitle"><?= te('Review staff leave requests.') ?></p>
  <?php require dirname(__DIR__) . '/includes/attendance-management.php'; ?>
  <dialog class="attendance-review-dialog" id="attendance-review-dialog" aria-labelledby="attendance-review-title"
          aria-describedby="attendance-review-summary" data-saving-label="<?= te('Saving…') ?>">
    <h2 id="attendance-review-title"></h2>
    <div class="attendance-review-summary" id="attendance-review-summary">
      <strong data-review-staff></strong>
      <span data-review-dates></span>
    </div>
    <div class="attendance-review-actions">
      <button type="button" data-review-cancel autofocus><?= te('Cancel') ?></button>
      <button type="button" data-review-confirm></button>
    </div>
  </dialog>
</div>
<?php require dirname(__DIR__) . '/includes/date-range-dialog.php'; ?>
<script src="assets/js/attendance.js" defer></script>
<?php foot(); ?>
