<?php
$reportingPolicy = attendance_report_policy($db);
$publicHolidays = attendance_report_holidays($db);
?>
<details class="ops-panel attendance-panel" id="reporting-policy" <?= in_array($_POST['action'] ?? '', ['save_report_policy', 'save_holiday', 'remove_holiday'], true) ? 'open' : '' ?>>
  <summary><?= te('Reporting settings') ?></summary>
  <form method="post" action="<?= e($page) ?>?tab=settings#reporting-policy" class="attendance-controls">
    <?= attendance_fields('save_report_policy') ?>
    <h3><?= te('Full attendance policy') ?></h3>
    <label class="attendance-check"><input type="checkbox" name="require_checkout" value="1" <?= $reportingPolicy['require_checkout'] ? 'checked' : '' ?>>
      <?= te('Require check-out for full attendance') ?></label>
    <label class="attendance-check"><input type="checkbox" name="excuse_holidays" value="1" <?= $reportingPolicy['excuse_holidays'] ? 'checked' : '' ?>>
      <?= te('Excuse shifts that start on configured public holidays') ?></label>
    <button class="btn"><?= te('Save reporting settings') ?></button>
  </form>
  <form method="post" action="<?= e($page) ?>?tab=settings#reporting-policy" class="attendance-controls">
    <?= attendance_fields('save_holiday') ?>
    <h3><?= te('Public holidays') ?></h3>
    <p class="muted"><?= te('Add holidays observed by your company. Saved dates apply to this workspace’s reports.') ?></p>
    <div class="attendance-fields">
      <label><?= te('Holiday date') ?><input type="date" name="holiday_date" required></label>
      <label><?= te('Holiday name') ?><input type="text" name="holiday_name" maxlength="100" required></label>
    </div>
    <button class="btn btn-secondary"><?= te('Save holiday') ?></button>
  </form>
  <?php foreach ($publicHolidays as $date => $name): ?>
    <form method="post" action="<?= e($page) ?>?tab=settings#reporting-policy" class="attendance-grant">
      <?= attendance_fields('remove_holiday') ?><input type="hidden" name="holiday_date" value="<?= e($date) ?>">
      <span><?= e($date) ?> · <?= e($name) ?></span><button class="btn btn-secondary"><?= te('Remove holiday') ?></button>
    </form>
  <?php endforeach; ?>
</details>
