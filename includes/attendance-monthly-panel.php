<?php $staffView = $actor['role'] === 'staff'; ?>
<section class="<?= $staffView ? 'staff-card' : 'ops-panel attendance-panel' ?>" id="attendance-calendar"
         aria-labelledby="attendance-calendar-heading" data-monthly-attendance>
  <h2 id="attendance-calendar-heading" tabindex="-1"><?= te($staffView ? 'Monthly Calendar' : 'Monthly Attendance') ?></h2>
  <p class="attendance-notice" data-monthly-error role="alert" <?= $monthlyRequest && $error !== '' ? '' : 'hidden' ?>><?= $monthlyRequest ? te($error) : '' ?></p>
  <span class="validation-sr-only" data-monthly-status role="status"></span>
  <?php if ($staffView): ?>
    <form method="get" action="staff-attendance.php#attendance-calendar" class="attendance-controls">
      <input type="hidden" name="view" value="calendar">
      <div class="attendance-fields">
        <?php require __DIR__ . '/attendance-month-field.php'; ?>
      </div>
      <button class="staff-button"><?= te('View month') ?></button>
    </form>
  <?php else: ?>
    <?php if ($monthlyStaff):
      $monthlyOptions = attendance_staff_options($monthlyStaff);
      $employeeField = $calendar ? $monthlyOptions[$calendarEmployee] : $calendarEmployeeSearch;
    ?>
    <form action="<?= e($page) ?>#attendance-calendar" method="get" class="attendance-controls">
      <input type="hidden" name="tab" value="calendar">
      <?php foreach ($filters as $name => $value): if ($value === '') continue; ?>
        <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
      <?php endforeach; ?>
      <div class="attendance-fields">
        <label><?= te('Employee') ?>
          <input type="search" name="calendar_employee_search" list="monthly-attendance-employees" maxlength="160"
                 value="<?= e($employeeField) ?>" placeholder="<?= te('Search by name or staff ID') ?>" autocomplete="off" required>
          <datalist id="monthly-attendance-employees">
            <?php foreach ($monthlyOptions as $option): ?><option value="<?= e($option) ?>"></option><?php endforeach; ?>
          </datalist>
        </label>
        <?php require __DIR__ . '/attendance-month-field.php'; ?>
        <?php attendance_select('calendar_location', 'Parking location', array_column($locations, 'name', 'id'),
          is_string($calendarLocation) ? $calendarLocation : '', 'All permitted locations'); ?>
      </div>
      <button class="btn"><?= te('View month') ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($calendar):
      $calendarQuery = $staffView ? ['view' => 'calendar', 'month' => $month]
          : $filters + ['tab' => 'calendar', 'calendar_employee' => $calendarEmployee, 'calendar_location' => $calendarLocation, 'month' => $month];
      $subject = $staffView ? $actor['name'] . ' · ' . attendance_staff_id($actor['id']) : $monthlyOptions[$calendarEmployee];
      require __DIR__ . '/attendance-calendar-view.php';
  elseif (!$staffView): ?>
    <p class="attendance-empty"><?= te($monthlyStaff ? 'Choose an employee and month to see scheduled shifts.' : 'No employees are available in your attendance scope.') ?></p>
  <?php endif; ?>
</section>
