<link rel="stylesheet" href="assets/css/date-range.css">
<dialog class="date-range-picker month-picker" id="month-picker" aria-labelledby="month-picker-title"
        data-current-month="<?= e(date('Y-m')) ?>">
  <h2 id="month-picker-title"><?= te('Choose a month') ?></h2>
  <div class="date-range-navigation month-picker-year">
    <button type="button" data-month-previous-year aria-label="<?= te('Previous year') ?>"><span aria-hidden="true">‹</span></button>
    <label class="validation-sr-only" for="month-picker-year"><?= te('Year') ?></label>
    <input type="number" id="month-picker-year" step="1" inputmode="numeric" required>
    <button type="button" data-month-next-year aria-label="<?= te('Next year') ?>"><span aria-hidden="true">›</span></button>
  </div>
  <div class="date-range-choices" data-month-choices role="group" aria-labelledby="month-picker-title"></div>
  <div class="date-range-actions">
    <button type="button" class="date-range-cancel" data-month-current><?= te('This month') ?></button>
    <button type="button" class="date-range-cancel" data-month-cancel><?= te('Cancel') ?></button>
    <button type="button" data-month-apply><?= te('Apply') ?></button>
  </div>
</dialog>
<script src="assets/js/month-picker.js" defer></script>
<script src="assets/js/monthly-attendance.js" defer></script>
